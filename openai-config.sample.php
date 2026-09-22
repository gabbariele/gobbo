<?php
/* Copia questo file in openai-config.php e riempilo.
   openai-config.php non finisce su git: e' escluso in .gitignore.
 *
 * DOVE METTERLO, e conta piu' di quanto sembri: FUORI dalla cartella servita dal
 * web, non accanto a index.html. Se il server non esegue PHP - i siti statici
 * spesso non lo fanno - un file .php nella cartella pubblica viene servito come
 * testo, e la chiave qui dentro se la scarica chiunque.
 *
 *   /home/tuosito/openai-config.php      <- qui, al riparo
 *   /home/tuosito/htdocs/openai.php      <- il ponte, pubblico
 *   /home/tuosito/htdocs/index.html
 *
 * openai.php lo cerca in tre posti, in quest'ordine: il percorso nella variabile
 * d'ambiente GOBBO_CONFIG, un livello sopra se stesso, poi accanto a se stesso.
 * Se lo trova dentro il document root te lo dice, con un avviso nella risposta al
 * pulsante Verifica. In alternativa: niente file, e la chiave in OPENAI_API_KEY. */

return array(

    /* La chiave OpenAI: https://platform.openai.com/api-keys
       In alternativa lascia la stringa vuota qui e passala al server come
       variabile d'ambiente OPENAI_API_KEY. */
    'key' => 'sk-...',

    /* Parola d'ordine: una frase lunga a caso, la stessa che scrivi nelle
       impostazioni di Gobbo. Senza, chiunque trovi l'indirizzo del ponte spende
       il tuo credito. Vuota = ponte aperto a tutti: solo per una prova al volo. */
    'token' => 'cambiami-con-una-frase-lunga-a-caso',

    /* Modelli ammessi. Elenco vuoto = qualunque modello di chat OpenAI.
       Restringere e' il modo piu' semplice per non ritrovarsi una bolletta
       fatta con un modello caro. */
    'models' => array('gpt-5-mini', 'gpt-5-nano', 'gpt-4.1-mini', 'gpt-4o-mini'),

    /* Origini ammesse oltre alla propria: serve solo se provi l'app da localhost
       mentre il ponte sta gia' online. In produzione lascia l'elenco vuoto. */
    'origins' => array(),

    /* Secondi di attesa verso OpenAI. Le schede dal palco rinunciano molto prima
       (le taglia l'app); qui conta per gli articoli della modalita' giornalista. */
    'timeout' => 90,

    /* ---- Modalita' giornalista (giornale.php): archivio ed email ----
       Questo stesso file configura anche giornale.php. Senza 'token' giornale.php
       rifiuta tutto: scrive file e manda posta, non puo' restare aperto. */

    /* Unico destinatario possibile: la pagina non puo' sceglierne un altro. */
    'mail_to' => 'tu@tuodominio.it',

    /* Mittente; vuoto = lo stesso utente SMTP. Con Gmail/Workspace deve essere
       quell'indirizzo o un suo alias, altrimenti Google lo riscrive. */
    'mail_from' => '',

    /* Con Google Workspace: smtp.gmail.com, 465, ssl, l'indirizzo come utente e una
       "password per le app" (myaccount.google.com/apppasswords, serve la verifica in
       due passaggi), non la password normale. Con 587 usa 'secure' => 'tls'. */
    'smtp' => array(
        'host'   => 'smtp.gmail.com',
        'port'   => 465,
        'secure' => 'ssl',
        'user'   => 'tu@tuodominio.it',
        'pass'   => 'password-per-le-app',
    ),

    /* Dove finiscono le sessioni (un .md ciascuna). Vuoto = cartella "giornale" un
       livello sopra giornale.php, cioe' fuori dal sito. Mai dentro la cartella pubblica. */
    'archivio' => '',
);
