# Gobbo

Il suggeritore da palco. Ascolta quello che dici a un convegno, lo trascrive, lo manda a Gemini
e ti rimanda **subito** tre cose:

| | |
|---|---|
| 🟡 **Punti** | da 1 a 3 spunti intelligenti collegati a quanto hai appena detto — non riassumono, aggiungono |
| 🟣 **Parafrasi** | un concetto coerente di un filosofo famoso, riscritto pronto da dire, col nome dell'autore |
| 🟢 **Citazione** | un aforisma breve e **originale** che puoi pronunciare come tuo |

C'è anche il pulsante **✎**: scrivi (o incolli) tu un testo e ottieni le stesse tre cose.

È una PWA: si installa sulla home del telefono e si comporta come un'app nativa.

---

## 1. Chiave API Gemini

1. vai su **https://aistudio.google.com/apikey** → *Create API key* → copiala
2. nell'app: **⚙ → Chiave API Gemini** → incolla → **Verifica** → **Salva**

**Verifica** interroga l'API con quella chiave: ti dice se è valida e riempie l'elenco dei
modelli con quelli davvero abilitati per il tuo account.

La chiave resta nel `localStorage` del telefono e viaggia solo verso Google, nell'header
della richiesta. Non passa da nessun server intermedio.

## 2. Metterla online (serve HTTPS)

Il microfono nel browser funziona **solo su HTTPS** o su `localhost`. Due strade:

**GitHub Pages** (gratis, tre clic). Il codice è già su `github.com/gabbariele/gobbo`:
*Settings → Pages → Source: Deploy from a branch → main / (root) → Save*.
Dopo un paio di minuti: `https://gabbariele.github.io/gobbo/`

**Un tuo server** (Linode o altro). Basta copiare la cartella dentro una directory già
servita in HTTPS, per esempio `https://tuodominio.it/gobbo/`. Nessun backend, nessuna
configurazione: sono file statici. L'unica cosa che conta è che il certificato sia valido,
altrimenti Chrome nega il microfono senza nemmeno chiedere.

## 3. Installarla sul telefono

Apri l'indirizzo con **Chrome su Android** → menu ⋮ → **Installa app** (o *Aggiungi a
schermata Home*). Da lì parte a schermo intero, con la sua icona.
Al primo **ASCOLTA** Chrome chiede il permesso per il microfono: concedilo.

---

## Collaudo passo passo

### A. Sul PC, prima di pubblicare
Chrome desktop ha lo stesso riconoscimento vocale di Android, e `localhost` conta come
sicuro: puoi provare tutto senza deploy.

```bash
python -m http.server 8099
```
poi apri `http://localhost:8099` in Chrome.

1. **⚙ → chiave → Verifica.** Deve dire *Chiave valida: N modelli*. Se dice errore, il
   problema è la chiave, non l'app. **Salva.**
2. **✎ → incolla tre righe di un tuo intervento → Analizza.** Entro pochi secondi compare la
   scheda con i tre blocchi. Questo prova la catena Gemini → schema → rendering, senza microfono.
3. **ASCOLTA** → Chrome chiede il microfono → parla 15-20 secondi di fila, poi fermati.
   Dopo la pausa (default 2,5 s) deve analizzare da solo. Se non parte: hai detto meno di
   ~120 caratteri, oppure *Analizza da solo* è spento.
   Poi prova a parlare **senza mai fermarti** per un minuto: dopo ~30 s, alla prima frase
   compiuta, deve analizzare comunque (*Comunque ogni*). L'ascolto non si interrompe: non
   serve premere FERMA, quello è solo per chiudere la sessione.
4. Parla di nuovo e premi **ORA** a metà frase, senza aspettare la pausa: manda subito tutto
   quello che hai detto dall'ultima analisi, parole provvisorie comprese. Se non c'è nulla
   da mandare (perché l'automatico l'ha già fatto) te lo dice nella riga di stato.
5. **Copia** → incolla in un editor: i tre blocchi in testo semplice.
6. Cambia modello, riprova il punto 2. Se un modello risponde *non accetta la modalità
   veloce*, spegni *Modalità veloce* per quel modello.

### B. Sul telefono, dopo la pubblicazione
1. Apri l'URL in Chrome → **Installa app** → apri dall'icona, non dal browser.
2. Ripeti i punti 1-4 sopra. Il permesso microfono viene chiesto una sola volta.
3. **Prova il caso reale:** tienilo in mano o su un leggio, parla come parleresti in sala
   (volume normale, a 30-50 cm). Guarda se la striscia grigia sotto lo stato segue quello
   che dici: se sbaglia molte parole, avvicina il telefono o togli il rumore di fondo.
4. **Prova il limite noto:** blocca lo schermo o passa a un'altra app. L'ascolto si ferma
   (è il limite della PWA). Riapri: devi ripremere ASCOLTA. *Tieni lo schermo acceso* serve
   proprio a evitarlo.
5. **Wifi vs 4G:** in sala il wifi pubblico è spesso saturo. Prova entrambi e tieni il 4G.
6. **Prova generale:** leggi 3 minuti veri di un tuo intervento senza fermarti. Guarda
   quante schede produce, se arrivano abbastanza in fretta e se i punti sono usabili.
   Se sono troppe, alza la *Pausa*; se sono generiche, scrivi meglio il *Contesto*.

### Se qualcosa non va
| Sintomo | Causa probabile |
|---|---|
| *Chiave API non valida* | chiave copiata male, o creata su un progetto senza API abilitata |
| *Modello non disponibile per questa chiave* | usa **Verifica**: ti mostra quelli che hai davvero |
| *sovraccarico (503)* / *troppe richieste (429)* | Google è in affanno o sei oltre il limite del piano: ritenta alternando principale e **riserva** finché dura il budget, e intanto ti dice cosa fa. Se vedi spesso *Niente risposta entro N s*, alza il budget o la Pausa |
| *Microfono negato* | Chrome → ⋮ → Impostazioni sito → Microfono → Consenti |
| la striscia grigia non compare | non sei su HTTPS/localhost, oppure il browser non è Chrome |
| risposte lente (> 5 s) | *Modalità veloce* spenta, o modello `pro`; passa a un flash |

---

## Impostazioni

| Voce | Cosa fa |
|---|---|
| **Contesto del convegno** | tema, platea, il tuo taglio. Facoltativo ma è la leva più forte sulla qualità |
| **Modello** | `gemini-3.5-flash` è il compromesso giusto. `3.5-flash-lite` più rapido, `3.7-flash` più profondo |
| **Modello di riserva** | se il principale è sovraccarico (503), in coda (429) o muto per 12 s, la richiesta passa subito a questo. Default `gemini-3.7-flash`. La scheda mostra ↻ e il nome quando è intervenuto |
| **Tempo massimo per una risposta** | budget in secondi (default 20) entro cui alterna principale e riserva con attese crescenti se Gemini è sovraccarico. Scaduto, rinuncia a quel pezzo. Fino a due richieste in volo insieme: una lenta non blocca le altre |
| **Pausa (sec)** | secondi di silenzio prima che analizzi da solo (default 2,5) |
| **Comunque ogni (sec)** | se non ti fermi mai, manda il pezzo alla prima frase compiuta dopo N secondi (default 30; 0 = mai). Evita che il buffer cresca all'infinito mentre parli di fila |
| **Modalità veloce** | riduce al minimo il ragionamento del modello: risposte molto più rapide |
| **Analizza da solo mentre parlo** | se spento, funziona solo col pulsante ORA |
| **Mostra la trascrizione** | stampa sotto ogni scheda il testo che l'ha generata |
| **Tieni lo schermo acceso** | wake lock, così il telefono non si spegne mentre parli |

L'analisi automatica scatta solo dopo almeno ~120 caratteri di parlato, per non riempirti
lo schermo di schede su mezze frasi.

## Cosa viene salvato, e dove

Tutto e solo **sul dispositivo**, nel `localStorage` del browser (per telefono/PC, non
sincronizzato): la chiave API, le impostazioni, le ultime 200 schede e la trascrizione della
sessione (tenuta per 12 ore, poi scartata). Nessun server tuo o mio riceve nulla.
I soli destinatari esterni sono quelli di Google: l'audio per il riconoscimento vocale di
Chrome e il testo per l'API Gemini.

**⚙ → Esporta** produce un file Markdown con tutte le schede (con il testo che le ha generate)
e la trascrizione completa: su Android apre la condivisione nativa, sul PC lo scarica.
**⚙ → Svuota** cancella schede e trascrizione. Disinstallare l'app o cancellare i dati del
sito cancella anche la chiave.

## Note oneste

- **Le citazioni del blocco verde sono originali**, generate sul momento: sono tue da dire.
  Quelle del blocco viola sono **parafrasi**, non citazioni letterali — dì "come diceva X…",
  non leggerle come virgolettato. Il modello può sbagliare un'attribuzione: se un nome ti
  suona strano, non usarlo.
- **La trascrizione passa da Google.** Il riconoscimento vocale di Chrome manda l'audio ai
  server Google, e il testo va all'API Gemini. Non usarlo su contenuti riservati.
- **Deve restare in primo piano.** È il limite della versione PWA. La versione Android
  nativa risolve questo punto.
- **Consumo API.** Un intervento lungo genera parecchie chiamate. Con un flash il costo è
  minimo, ma sul piano gratuito puoi incontrare il limite di richieste al minuto.

## File

```
index.html            tutta l'app (interfaccia + logica + prompt)
manifest.json         metadati PWA per l'installazione
sw.js                 service worker: guscio in cache, si apre anche offline
icons/                icone 192 / 512 / maskable
```

Il prompt che guida Gemini è la costante `SYS` dentro `index.html`: è lì che si regola
il tono, la lunghezza e il carattere delle tre risposte.
