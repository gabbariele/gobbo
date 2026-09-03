<?php
/* Copia questo file in openai-config.php (stessa cartella) e riempilo.
   openai-config.php non finisce su git: e' escluso in .gitignore. */

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
