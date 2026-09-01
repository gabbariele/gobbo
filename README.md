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

Serve una chiave gratuita:

1. vai su **https://aistudio.google.com/apikey**
2. *Create API key* → copiala
3. nell'app: **⚙ → Chiave API Gemini** → incolla → **Salva**

La chiave resta nel `localStorage` del telefono. Non passa da nessun server: il telefono
parla direttamente con Google.

## 2. Pubblicare su GitHub Pages

Il microfono nel browser funziona **solo su HTTPS** (o su `localhost`), quindi l'app va messa
online. GitHub Pages è gratis e basta e avanza.

Dalla cartella `gobbo/`:

```bash
git init && git add . && git commit -m "Gobbo: prima versione" && git branch -M main && git remote add origin https://github.com/TUO-UTENTE/gobbo.git && git push -u origin main
```

Poi su GitHub: **Settings → Pages → Source: Deploy from a branch → main / (root) → Save**.

Dopo un paio di minuti l'app è su `https://TUO-UTENTE.github.io/gobbo/`.

## 3. Installarla sul telefono

Apri quell'indirizzo con **Chrome su Android** → menu ⋮ → **Installa app** (o *Aggiungi a schermata Home*).
Da lì parte a schermo intero, con la sua icona, senza barra del browser.

Al primo **ASCOLTA** Chrome chiede il permesso per il microfono: concedilo.

---

## Come si usa in sala

1. Prima di salire, in **⚙** scrivi il **contesto del convegno**
   (tema, platea, di cosa parli tu). Fa una differenza enorme sulla qualità dei punti.
2. **ASCOLTA** → il pallino diventa rosso e pulsa.
3. Parli normalmente. Quando ti fermi per qualche secondo, il gobbo analizza da solo
   l'ultimo pezzo e fa comparire una scheda in cima.
4. Se vuoi forzarlo subito, **ORA**.
5. **Copia** su una scheda mette tutto negli appunti.

## Impostazioni

| Voce | Cosa fa |
|---|---|
| **Contesto del convegno** | tema, platea, il tuo taglio. Facoltativo ma consigliatissimo |
| **Modello** | `2.5 Flash` è il compromesso giusto. `Flash-Lite` è più rapido e più superficiale, `Pro` il contrario |
| **Pausa (sec)** | quanti secondi di silenzio prima che analizzi da solo (default 2,5) |
| **Modalità veloce** | disattiva il ragionamento esteso: risposte molto più rapide, ideale dal vivo |
| **Analizza da solo mentre parlo** | se la spegni, funziona solo col pulsante ORA |
| **Mostra la trascrizione** | stampa sotto ogni scheda il testo che l'ha generata (utile per capire cosa ha capito) |
| **Tieni lo schermo acceso** | wake lock, così il telefono non si spegne mentre parli |

L'analisi automatica scatta solo dopo almeno ~120 caratteri di parlato, per non riempirti
lo schermo di schede su mezze frasi.

---

## Note oneste

- **Le citazioni del blocco verde sono originali**, generate sul momento: sono tue da dire.
  Quelle del blocco viola sono **parafrasi**, non citazioni letterali — dì "come diceva X…",
  non leggerle come virgolettato. Il modello può sbagliare un'attribuzione: se un nome ti
  suona strano, non usarlo.
- **La trascrizione passa da Google.** Il riconoscimento vocale di Chrome manda l'audio ai
  server Google, e il testo va all'API Gemini. Non usarlo su contenuti riservati.
- **Serve rete.** In una sala con wifi saturo può rallentare: il 4G del telefono di solito è
  più affidabile.
- **Deve restare in primo piano.** È il limite della versione PWA: se cambi app o blocchi lo
  schermo, l'ascolto si ferma. La versione Android nativa risolve questo punto.
- **Consumo API.** Un intervento lungo può generare parecchie chiamate. Con Flash il costo è
  minimo, ma se stai sul piano gratuito puoi incontrare il limite di richieste al minuto
  (l'app te lo dice: *Quota esaurita*). In quel caso alza la **Pausa** o spegni
  l'analisi automatica e usa **ORA**.

## File

```
index.html            tutta l'app (interfaccia + logica + prompt)
manifest.webmanifest  metadati PWA per l'installazione
sw.js                 service worker: guscio in cache, si apre anche offline
icons/                icone 192 / 512 / maskable
```

Il prompt che guida Gemini è la costante `SYS` dentro `index.html`: è lì che si regola
il tono, la lunghezza e il carattere delle tre risposte.
