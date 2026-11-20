// C7: vienas bendras tikrintojas kas 30 s (pulsas.php): žinučių skaičius, pranešimai, kvietimai žaisti.
// Paslėptame skirtuke netikrina; grįžus - patikrina iš karto. Kiti skriptai klauso įvykio „karateka:pulse“.
(function () {
  var s = document.currentScript, url = s && s.dataset.api, etag = '', busy = false;
  if (!url) return;
  function tick() {
    if (document.hidden || busy) return;
    busy = true;
    fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: etag ? { 'If-None-Match': etag } : {} })
      .then(function (r) {
        if (r.status === 304 || !r.ok) return null;
        etag = r.headers.get('ETag') || '';
        return r.json();
      })
      .then(function (d) { if (d && d.ok) document.dispatchEvent(new CustomEvent('karateka:pulse', { detail: d })); })
      .catch(function () {})
      .then(function () { busy = false; });
  }
  window.karatekaPulse = function () { etag = ''; tick(); };   // priverstinai dabar (pvz. uždarius pokalbį)
  setInterval(tick, 30000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) tick(); });
  setTimeout(tick, 3000);
})();
