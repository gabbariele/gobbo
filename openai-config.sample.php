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

    'timeout' => 25,
);
