/* Gobbo - service worker minimale.
   Il guscio dell'app va in cache così si apre anche senza rete;
   le chiamate a Gemini passano sempre dalla rete (mai cache). */
var CACHE = 'gobbo-v2';
var SHELL = [
  './',
  './index.html',
  './manifest.json',
  './icons/icon-192.png',
  './icons/icon-512.png'
];

self.addEventListener('install', function(e){
  e.waitUntil(
    caches.open(CACHE)
      .then(function(c){ return c.addAll(SHELL); })
      .then(function(){ return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function(e){
  e.waitUntil(
    caches.keys().then(function(keys){
      return Promise.all(keys.map(function(k){
        return k === CACHE ? null : caches.delete(k);
      }));
    }).then(function(){ return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function(e){
  var req = e.request;
  if (req.method !== 'GET') return;
  if (req.url.indexOf('generativelanguage.googleapis.com') !== -1) return;

  // network-first sul guscio, così gli aggiornamenti arrivano subito
  e.respondWith(
    fetch(req).then(function(res){
      if (res && res.ok && req.url.indexOf(self.registration.scope) === 0) {
        var copy = res.clone();
        caches.open(CACHE).then(function(c){ c.put(req, copy); });
      }
      return res;
    }).catch(function(){
      return caches.match(req).then(function(m){
        return m || caches.match('./index.html');
      });
    })
  );
});
