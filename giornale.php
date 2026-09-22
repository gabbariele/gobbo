<?php
/* Gobbo - archivio e posta della modalita' giornalista.
 *
 * Due cose, tutte e due lato server perche' dal telefono non si possono fare bene:
 *   - salva ogni sessione (trascrizione, note, articolo) in un file .md in una cartella
 *     FUORI dal sito, cosi' non si perde se il telefono muore o Chrome si svuota;
 *   - la manda per email, via SMTP, SEMPRE e solo all'indirizzo scritto nella
 *     configurazione. La pagina non sceglie mai il destinatario: altrimenti questo file
 *     sarebbe un modo gratuito per spedire posta a chiunque dal tuo server.
 *
 * Scrive file e manda posta, quindi non gira senza parola d'ordine: con 'token' vuoto
 * nella configurazione rifiuta tutto (il ponte OpenAI invece si limita a sconsigliarlo).
 *
 * Contratto:
 *   GET  -> {"ok":true,"archivio":true,"email":true,"a":"g***@weevo.it"}   cosa e' pronto
 *   POST {"azione":"salva"|"invia", "sessione":{id, inizio, fine, note, testo, articolo}}
 *        -> {"ok":true,"file":"<id>.md","inviato":true|false}
 *   errori -> {"ok":false,"error":{"message":"...","code":"gobbo_*"}}
 *
 * La configurazione e' la stessa del ponte (openai-config.php), con in piu' 'mail_to',
 * 'mail_from', 'smtp' e, se vuoi, 'archivio': vedi openai-config.sample.php. */

$CFG = array(
    'token'     => getenv('GOBBO_TOKEN') ? getenv('GOBBO_TOKEN') : '',
    'origins'   => array(),
    'archivio'  => '',          // vuoto = cartella "giornale" un livello sopra questo file
    'mail_to'   => '',
    'mail_from' => '',          // vuoto = lo stesso utente SMTP
    'smtp'      => array('host' => '', 'port' => 465, 'secure' => 'ssl', 'user' => '', 'pass' => ''),
    'max_body'  => 2000000,     // byte: un'ora e mezza di parlato sono ~100 KB, qui c'e' margine
);
foreach (array(getenv('GOBBO_CONFIG') ? getenv('GOBBO_CONFIG') : null,
               dirname(__DIR__) . '/openai-config.php',
               __DIR__ . '/openai-config.php') as $c) {
    if ($c && is_file($c)) {
        $x = require $c;
        if (is_array($x)) {
            if (isset($x['smtp']) && is_array($x['smtp'])) { $x['smtp'] = array_replace($CFG['smtp'], $x['smtp']); }
            $CFG = array_replace($CFG, $x);
        }
        break;
    }
}
if ($CFG['archivio'] === '') { $CFG['archivio'] = dirname(__DIR__) . '/giornale'; }

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

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
    echo json_encode(array('ok' => false, 'error' => array('message' => $msg, 'code' => $code)),
                     JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$metodo = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
if ($metodo === 'OPTIONS') { http_response_code(204); exit; }

if ((string) $CFG['token'] === '') {
    fine(500, 'Sul server manca la parola d ordine (token in openai-config.php): senza, archivio ed email restano spenti.', 'gobbo_no_token');
}
$t = isset($_SERVER['HTTP_X_GOBBO_TOKEN']) ? $_SERVER['HTTP_X_GOBBO_TOKEN'] : '';
if (!is_string($t) || !hash_equals((string) $CFG['token'], $t)) {
    fine(401, 'Parola d ordine mancante o sbagliata.', 'gobbo_token');
}

/* L'archivio deve stare fuori dall'albero servito dal web: dentro, le trascrizioni
   sarebbero leggibili da chiunque ne indovini il nome. Stessa verifica del ponte. */
function dentro_al_sito($dir) {
    if (empty($_SERVER['DOCUMENT_ROOT'])) { return false; }
    $radice = realpath($_SERVER['DOCUMENT_ROOT']);
    $dove = realpath($dir);
    if (!$radice || !$dove) { return false; }
    $radice = rtrim(str_replace('\\', '/', $radice), '/') . '/';
    return strpos(rtrim(str_replace('\\', '/', $dove), '/') . '/', $radice) === 0;
}

function smtp_pronto($CFG) {
    return $CFG['mail_to'] !== '' && $CFG['smtp']['host'] !== '' && $CFG['smtp']['user'] !== '' && $CFG['smtp']['pass'] !== '';
}

/* Mostro a chi andra' la posta senza scriverlo per intero: la pagina e' pubblica. */
function mascherato($a) {
    $p = strpos($a, '@');
    return $p === false ? '***' : substr($a, 0, 1) . '***' . substr($a, $p);
}

if ($metodo === 'GET') {
    echo json_encode(array('ok' => true,
                           'archivio' => !dentro_al_sito($CFG['archivio']),
                           'email' => smtp_pronto($CFG),
                           'a' => $CFG['mail_to'] !== '' ? mascherato($CFG['mail_to']) : ''),
                     JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
if ($metodo !== 'POST') { fine(405, 'Metodo non ammesso.', 'gobbo_method'); }

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') { fine(400, 'Richiesta vuota.', 'gobbo_bad_request'); }
if (strlen($raw) > $CFG['max_body']) { fine(413, 'Sessione troppo grande.', 'gobbo_too_big'); }
$in = json_decode($raw, true);
$azione = isset($in['azione']) ? $in['azione'] : '';
$s = isset($in['sessione']) && is_array($in['sessione']) ? $in['sessione'] : null;
if (($azione !== 'salva' && $azione !== 'invia') || !$s) { fine(400, 'Servono azione (salva/invia) e sessione.', 'gobbo_bad_request'); }

$id = isset($s['id']) ? (string) $s['id'] : '';
if (!preg_match('/^[0-9a-z-]{6,40}$/', $id)) { fine(400, 'Identificativo di sessione non valido.', 'gobbo_bad_request'); }
function campo($a, $k) { return isset($a[$k]) && is_scalar($a[$k]) ? trim((string) $a[$k]) : ''; }
$testo = campo($s, 'testo');
$note  = campo($s, 'note');
$art   = isset($s['articolo']) && is_array($s['articolo']) ? $s['articolo'] : null;
if ($testo === '' && !$art) { fine(400, 'Sessione vuota.', 'gobbo_bad_request'); }

/* ---------------------------- il file .md ---------------------------- */
function quando($ms) {
    $ms = is_numeric($ms) ? (float) $ms : 0;
    if ($ms <= 0) { return ''; }
    $d = new DateTime('@' . (int) floor($ms / 1000));
    $d->setTimezone(new DateTimeZone('Europe/Rome'));
    return $d->format('d/m/Y H:i');
}
$inizio = quando(isset($s['inizio']) ? $s['inizio'] : 0);
$fine_s = quando(isset($s['fine']) ? $s['fine'] : 0);
$titolo = $art ? campo($art, 'titolo') : '';

$md = '# ' . ($titolo !== '' ? $titolo : 'Trascrizione del ' . ($inizio !== '' ? $inizio : $id)) . "\n\n";
$md .= '_Sessione ' . $id . ($inizio !== '' ? ' · ' . $inizio . ($fine_s !== '' && $fine_s !== $inizio ? ' - ' . $fine_s : '') : '') .
       ' · ' . preg_match_all('/\S+/u', $testo) . " parole_\n\n";
if ($note !== '') { $md .= "## Note\n\n" . $note . "\n\n"; }
if ($art) {
    $md .= "## Articolo\n\n";
    if (campo($art, 'occhiello') !== '') { $md .= '_' . campo($art, 'occhiello') . "_\n\n"; }
    if ($titolo !== '') { $md .= '### ' . $titolo . "\n\n"; }
    if (campo($art, 'sommario') !== '') { $md .= '**' . campo($art, 'sommario') . "**\n\n"; }
    $md .= campo($art, 'testo') . "\n\n";
    if (campo($art, 'modello') !== '') { $md .= '_Scritto da ' . campo($art, 'modello') . " a partire dalla trascrizione qui sotto._\n\n"; }
}
$md .= "## Trascrizione\n\n" . ($testo !== '' ? $testo : '(vuota)') . "\n";

$dir = $CFG['archivio'];
if (!is_dir($dir) && !@mkdir($dir, 0750, true)) { fine(500, 'Non riesco a creare la cartella dell archivio sul server.', 'gobbo_archivio'); }
if (dentro_al_sito($dir)) { fine(500, 'La cartella dell archivio e dentro il sito: spostala fuori (chiave archivio nella configurazione).', 'gobbo_archivio'); }
$file = $dir . '/' . $id . '.md';
$tmp = $file . '.tmp';
if (@file_put_contents($tmp, $md, LOCK_EX) === false || !@rename($tmp, $file)) {
    fine(500, 'Non riesco a scrivere nell archivio sul server.', 'gobbo_archivio');
}
@chmod($file, 0640);

if ($azione === 'salva') {
    echo json_encode(array('ok' => true, 'file' => $id . '.md', 'inviato' => false), JSON_UNESCAPED_SLASHES);
    exit;
}

/* ---------------------------- l'email ---------------------------- */
if (!smtp_pronto($CFG)) {
    fine(500, 'Salvata sul server, ma l email non e configurata: servono mail_to e smtp in openai-config.php.', 'gobbo_no_mail');
}

$oggetto = 'Gobbo · ' . ($titolo !== '' ? $titolo : 'trascrizione del ' . ($inizio !== '' ? $inizio : $id));
$corpo = '';
if ($art) {
    if (campo($art, 'occhiello') !== '') { $corpo .= campo($art, 'occhiello') . "\n\n"; }
    $corpo .= $titolo . "\n" . str_repeat('=', 40) . "\n\n";
    if (campo($art, 'sommario') !== '') { $corpo .= campo($art, 'sommario') . "\n\n"; }
    $corpo .= campo($art, 'testo') . "\n\n";
    $corpo .= str_repeat('-', 40) . "\n\n";
}
if ($note !== '') { $corpo .= "NOTE\n" . $note . "\n\n"; }
$corpo .= "TRASCRIZIONE" . ($inizio !== '' ? ' (' . $inizio . ')' : '') . "\n\n" . ($testo !== '' ? $testo : '(vuota)') . "\n";
$corpo .= "\n--\nSalvata anche sul server: " . $id . ".md\n";

function intestazione($s) { return '=?UTF-8?B?' . base64_encode($s) . '?='; }

$da = $CFG['mail_from'] !== '' ? $CFG['mail_from'] : $CFG['smtp']['user'];
$confine = 'gobbo-' . bin2hex(random_bytes(12));
$dominio = substr(strrchr($da, '@'), 1);
$msg  = 'From: ' . intestazione('Gobbo') . ' <' . $da . ">\r\n";
$msg .= 'To: <' . $CFG['mail_to'] . ">\r\n";
$msg .= 'Subject: ' . intestazione($oggetto) . "\r\n";
$msg .= 'Date: ' . date(DATE_RFC2822) . "\r\n";
$msg .= 'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . ($dominio ? $dominio : 'gobbo') . ">\r\n";
$msg .= "MIME-Version: 1.0\r\n";
$msg .= 'Content-Type: multipart/mixed; boundary="' . $confine . "\"\r\n\r\n";
$msg .= '--' . $confine . "\r\n";
$msg .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
$msg .= chunk_split(base64_encode($corpo), 76, "\r\n");
$msg .= '--' . $confine . "\r\n";
$msg .= 'Content-Type: text/markdown; charset=UTF-8; name="' . $id . ".md\"\r\n";
$msg .= 'Content-Disposition: attachment; filename="' . $id . ".md\"\r\n";
$msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
$msg .= chunk_split(base64_encode($md), 76, "\r\n");
$msg .= '--' . $confine . "--\r\n";

/* Un client SMTP minimo: sul server non c'e' sendmail, e PHP mail() senza sendmail
   non parte. Porta 465 = TLS da subito; 587 = in chiaro e poi STARTTLS. */
function smtp_invia($c, $da, $a, $msg) {
    $host = $c['host']; $port = (int) $c['port'];
    $ssl = ($c['secure'] === 'ssl' || $port === 465);
    $fp = @stream_socket_client(($ssl ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 15);
    if (!$fp) { return 'connessione a ' . $host . ':' . $port . ' non riuscita (' . $errstr . ')'; }
    stream_set_timeout($fp, 20);
    $leggi = function() use ($fp) {
        $r = '';
        while (($l = fgets($fp, 1024)) !== false) { $r .= $l; if (strlen($l) < 4 || $l[3] !== '-') { break; } }
        return $r;
    };
    $cmd = function($s, $atteso) use ($fp, $leggi) {
        if ($s !== null) { fwrite($fp, $s . "\r\n"); }
        $r = $leggi();
        if ((int) substr($r, 0, 3) !== $atteso) { throw new Exception(trim($r) !== '' ? trim($r) : 'il server SMTP non risponde'); }
        return $r;
    };
    try {
        $cmd(null, 220);
        $cmd('EHLO gobbo', 250);
        if (!$ssl) {
            $cmd('STARTTLS', 220);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new Exception('STARTTLS non riuscito');
            }
            $cmd('EHLO gobbo', 250);
        }
        $cmd('AUTH LOGIN', 334);
        $cmd(base64_encode($c['user']), 334);
        $cmd(base64_encode($c['pass']), 235);
        $cmd('MAIL FROM:<' . $da . '>', 250);
        $cmd('RCPT TO:<' . $a . '>', 250);
        $cmd('DATA', 354);
        $dati = preg_replace('/^\./m', '..', $msg);   // una riga che comincia col punto chiuderebbe il messaggio
        $cmd($dati . "\r\n.", 250);
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return '';
    } catch (Exception $e) {
        @fclose($fp);
        return $e->getMessage();
    }
}

$errore = smtp_invia($CFG['smtp'], $da, $CFG['mail_to'], $msg);
if ($errore !== '') {
    /* Il messaggio del server SMTP puo' contenere l'utente, non la password: la password
       viaggia solo in base64 dentro la sessione e non finisce mai nelle risposte. */
    fine(502, 'Salvata sul server, ma l email non e partita: ' . preg_replace('/\s+/', ' ', $errore), 'gobbo_smtp');
}
echo json_encode(array('ok' => true, 'file' => $id . '.md', 'inviato' => true, 'a' => mascherato($CFG['mail_to'])),
                 JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
