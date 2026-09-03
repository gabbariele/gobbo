<?php
/* Gobbo - ponte fra il browser e l'API OpenAI.
 *
 * Serve perche' OpenAI, a differenza di Gemini, non risponde alle chiamate fatte
 * direttamente dalla pagina: niente header CORS, il browser le blocca prima ancora
 * di partire. Questo file sta accanto a index.html, quindi per il browser e' la
 * stessa origine e il problema non si pone. In cambio la chiave OpenAI resta qui,
 * sul server, e non finisce piu' nel telefono di nessuno.
 *
 * Installazione: copia questo file accanto a index.html, poi copia
 * openai-config.sample.php in openai-config.php e mettici la chiave.
 *
 * Contratto (l'app si aspetta esattamente questo):
 *   GET   -> {"ok":true,"models":["gpt-5-mini", ...]}   elenco modelli di testo
 *   POST  -> inoltra il corpo a /v1/chat/completions e restituisce la risposta
 *            di OpenAI cosi' com'e', con lo stesso codice HTTP
 *   errori del ponte -> {"ok":false,"error":{"message":"...","code":"gobbo_*"}}
 */

$CFG = array(
    'key'      => getenv('OPENAI_API_KEY') ? getenv('OPENAI_API_KEY') : '',
    'token'    => getenv('GOBBO_TOKEN') ? getenv('GOBBO_TOKEN') : '',
    'origins'  => array(),   // origini extra ammesse (per provare da localhost); vuoto = solo stessa origine
    'models'   => array(),   // elenco chiuso di modelli ammessi; vuoto = qualunque modello di chat OpenAI
    'timeout'  => 25,        // secondi
    'max_body' => 200000,    // byte
);
/* Dove cercare la configurazione, in quest'ordine.
 *
 * Prima fuori dal web root, e non e' un vezzo: se il server non esegue PHP - succede, i
 * siti statici spesso non lo fanno - allora un file .php accanto all'app viene servito
 * come testo, e la chiave dentro diventa scaricabile da chiunque. Un piano sopra la
 * cartella pubblica il web non ci arriva, qualunque cosa faccia nginx.
 *
 * La seconda posizione resta per comodita', ma vale solo se hai verificato che PHP gira.
 */
$CONF_TROVATA = '';
$candidati = array(
    getenv('GOBBO_CONFIG') ? getenv('GOBBO_CONFIG') : null,  // percorso esplicito, se preferisci decidere tu
    dirname(__DIR__) . '/openai-config.php',                 // un livello sopra il ponte
    __DIR__ . '/openai-config.php',                          // accanto al ponte: solo se PHP viene eseguito
);
foreach ($candidati as $c) {
    if ($c && is_file($c)) {
        $x = require $c;
        if (is_array($x)) { $CFG = array_replace($CFG, $x); }
        $CONF_TROVATA = $c;
        break;
    }
}

/* Il file e' al riparo o dentro l'albero servito dal web? La domanda non e' "quanti
   livelli sopra sta" - se il ponte vive in una sottocartella, un livello sopra e'
   ancora pubblico - ma se il suo percorso cade dentro il document root. */
$CONF_ESPOSTA = false;
if ($CONF_TROVATA !== '' && !empty($_SERVER['DOCUMENT_ROOT'])) {
    $radice = realpath($_SERVER['DOCUMENT_ROOT']);
    $dove   = realpath($CONF_TROVATA);
    if ($radice && $dove) {
        $radice = rtrim(str_replace('\\', '/', $radice), '/') . '/';
        $dove   = str_replace('\\', '/', $dove);
        $CONF_ESPOSTA = (strpos($dove, $radice) === 0);
    }
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');   // il ponte non ha niente da far indicizzare

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if ($origin !== '' && in_array($origin, $CFG['origins'], true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Headers: Content-Type, X-Gobbo-Token');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Max-Age: 86400');
}

function fine($status, $msg, $code) {
    http_response_code($status);
    echo json_encode(
        array('ok' => false, 'error' => array('message' => $msg, 'code' => $code)),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

/* Una sola chiamata a OpenAI, con cURL se c'e' e con gli stream se non c'e'.
   Restituisce array(codice HTTP, corpo grezzo): il corpo torna al browser intatto,
   cosi' i messaggi d'errore di OpenAI arrivano fino all'app senza essere riscritti. */
function verso_openai($path, $key, $payload, $timeout) {
    $url = 'https://api.openai.com/v1/' . $path;
    $head = array('Authorization: Bearer ' . $key);
    if ($payload !== null) { $head[] = 'Content-Type: application/json'; }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $head);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) { return array(0, $err); }
        return array($status, $body);
    }

    $ctx = stream_context_create(array('http' => array(
        'method'        => $payload === null ? 'GET' : 'POST',
        'header'        => implode("\r\n", $head),
        'content'       => $payload,
        'timeout'       => $timeout,
        'ignore_errors' => true,   // senza questo, su 4xx/5xx perderei il messaggio d'errore
    )));
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) { return array(0, 'connessione non riuscita'); }
    $status = 0;
    if (isset($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) { $status = (int) $m[1]; }
        }
    }
    return array($status, $body);
}

$metodo = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

if ($metodo === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($CFG['key'] === '') {
    fine(500, 'Il ponte non ha la chiave OpenAI: copia openai-config.sample.php in openai-config.php e mettila li dentro.', 'gobbo_no_key');
}

/* Il ponte sta su un indirizzo pubblico: senza parola d'ordine chiunque lo trovi
   puo' spendere il tuo credito. Impostarla e' facoltativo ma vivamente consigliato. */
if ($CFG['token'] !== '') {
    $t = isset($_SERVER['HTTP_X_GOBBO_TOKEN']) ? $_SERVER['HTTP_X_GOBBO_TOKEN'] : '';
    if (!is_string($t) || !hash_equals((string) $CFG['token'], $t)) {
        fine(401, 'Parola d ordine del ponte mancante o sbagliata.', 'gobbo_token');
    }
}

/* --- GET: a che modelli da' diritto questa chiave (serve al pulsante Verifica) --- */
if ($metodo === 'GET') {
    list($status, $body) = verso_openai('models', $CFG['key'], null, $CFG['timeout']);
    if ($status === 0) { fine(502, 'OpenAI non raggiungibile dal server: ' . $body, 'gobbo_upstream'); }
    $j = json_decode($body, true);
    if ($status !== 200 || !isset($j['data'])) {
        $m = isset($j['error']['message']) ? $j['error']['message'] : ('HTTP ' . $status);
        fine($status ? $status : 502, 'OpenAI dice: ' . $m, 'gobbo_upstream');
    }
    $ids = array();
    foreach ($j['data'] as $m) {
        $n = isset($m['id']) ? $m['id'] : '';
        if (!preg_match('/^(gpt-|o[1-9]|chatgpt)/i', $n)) { continue; }
        if (preg_match('/audio|realtime|transcribe|tts|image|embedding|moderation|search|instruct|codex|\d{4}-\d{2}-\d{2}$/i', $n)) { continue; }
        if (!empty($CFG['models']) && !in_array($n, $CFG['models'], true)) { continue; }
        $ids[] = $n;
    }
    $esito = array('ok' => true, 'models' => array_values($ids));
    /* Il ponte sa dove ha letto la chiave, e lo dice: se sta nella cartella pubblica
       conviene saperlo subito, non il giorno in cui il server smette di eseguire PHP. */
    if ($CONF_ESPOSTA) {
        $esito['avviso'] = 'la chiave e in un file dentro la cartella servita dal web: spostalo fuori, se il server smette di eseguire PHP diventa scaricabile';
    }
    echo json_encode($esito, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($metodo !== 'POST') { fine(405, 'Metodo non ammesso.', 'gobbo_method'); }

/* --- POST: inoltro della richiesta di analisi --- */
$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') { fine(400, 'Richiesta vuota.', 'gobbo_bad_request'); }
if (strlen($raw) > $CFG['max_body']) { fine(413, 'Richiesta troppo grande.', 'gobbo_too_big'); }

$body = json_decode($raw, true);
if (!is_array($body) || !isset($body['model']) || !isset($body['messages'])) {
    fine(400, 'Richiesta non valida: servono model e messages.', 'gobbo_bad_request');
}

/* Filtro sul modello: il ponte parla solo con i modelli di chat di OpenAI, e solo
   con quelli in elenco se ne hai messo uno. Cosi' non diventa un rubinetto aperto. */
$model = (string) $body['model'];
if (!preg_match('/^(gpt-|o[1-9]|chatgpt)[A-Za-z0-9._-]*$/i', $model)) {
    fine(400, 'Modello non ammesso dal ponte: ' . $model, 'gobbo_model');
}
if (!empty($CFG['models']) && !in_array($model, $CFG['models'], true)) {
    fine(400, 'Modello fuori dall elenco ammesso dal ponte: ' . $model, 'gobbo_model');
}

list($status, $risposta) = verso_openai('chat/completions', $CFG['key'], $raw, $CFG['timeout']);
if ($status === 0) { fine(502, 'OpenAI non raggiungibile dal server: ' . $risposta, 'gobbo_upstream'); }
http_response_code($status);
echo $risposta;
