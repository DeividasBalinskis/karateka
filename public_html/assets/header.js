// Bendras meniu: ☰ mygtukas ir slinkimo juosta viršuje
(function () {
  var navList = document.getElementById('navList');
  var burger = document.getElementById('burgerBtn');
  if (burger && navList) {
    burger.addEventListener('click', function () { navList.classList.toggle('nav-open'); });
    navList.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () { navList.classList.remove('nav-open'); });
    });
  }

  // Antraštės apačia (--hdr-b): prilipusios juostos (pvz. redagavimo „Išsaugoti“) - po antrašte, ne už jos (#123)
  var hdr = document.querySelector('.site-header');
  function hdrBottom() { if (hdr) document.documentElement.style.setProperty('--hdr-b', Math.round(hdr.offsetHeight + (parseFloat(getComputedStyle(hdr).top) || 0)) + 'px'); }
  hdrBottom();
  window.addEventListener('resize', hdrBottom);
  window.addEventListener('load', hdrBottom);

  var bar = document.getElementById('progressBar');
  if (bar) {
    var update = function () {
      var h = document.documentElement, max = h.scrollHeight - h.clientHeight;
      bar.style.width = (max > 0 ? h.scrollTop / max * 100 : 0) + '%';
    };
    document.addEventListener('scroll', update, { passive: true });
    update();
  }

  // Programėlė telefone (manifest.json, sw.js): įdiegta atsidaro be naršyklės juostos, be ryšio - „Nėra interneto ryšio“
  var manifest = document.querySelector('link[rel="manifest"]');
  if (manifest && 'serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(new URL('sw.js', manifest.href).href).catch(function () {});
    });
  }
  // „Įdiegti programėlę“ (paskyros nustatymuose) - kai naršyklė siūlo įdiegti
  window.addEventListener('beforeinstallprompt', function (ev) {
    var btn = document.querySelector('[data-install-app]');
    if (!btn) return;
    ev.preventDefault();
    btn.hidden = false;
    btn.onclick = function () {
      ev.prompt();
      ev.userChoice.then(function () { btn.hidden = true; });
    };
  });
})();
// „Apie klubą ‹“ (#111, #117): paspaudus išsiskleidžia į kairę, paspaudus dar kartą (ar kitur) - susiskleidžia.
// Kad dešinė pusė nejudėtų: sąrašas pritvirtinamas prie dešinio krašto ir plečiasi į kairę virš kitų elementų.
(function () {
  var g = document.querySelector('.site-header .nav-onpage.folded');
  if (!g) return;
  var btn = g.querySelector('.fold-btn');
  function set(open) { g.classList.toggle('open', open); if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false'); }
  if (btn) btn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); set(!g.classList.contains('open')); });
  document.addEventListener('click', function (e) { if (!g.contains(e.target)) set(false); });
})();
// Antraštės ir apatinės juostos išskleidžiami langai (⋯ Daugiau, ▾ sistemos, 🌐, ⚙ Daugiau, Žiūrėti kaip):
// paspaudus šalia (fone) - užsidaro (#125); atidarius vieną - kiti užsidaro; ⋯ Daugiau telefone - su pritemdytu fonu
(function () {
  var sel = '.m-more, .site-switch, .pm-site, .am-more, .view-switch';
  var all = function () { return Array.prototype.slice.call(document.querySelectorAll(sel)); };
  function sync() { document.body.classList.toggle('m-more-open', !!document.querySelector('.m-more[open]')); }
  all().forEach(function (d) {
    d.addEventListener('toggle', function () {
      if (d.open) all().forEach(function (o) { if (o !== d && o.open && !o.contains(d)) o.open = false; });
      sync();
    });
  });
  document.addEventListener('click', function (e) {
    all().forEach(function (d) { if (d.open && !d.contains(e.target)) d.open = false; });
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') all().forEach(function (d) { d.open = false; }); });
})();
