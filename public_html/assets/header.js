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
  var btn = g.querySelector('.fold-btn'), ph = null;
  function anchor() {
    if (window.innerWidth <= 1100) { g.classList.remove('anchored'); g.style.cssText = ''; if (ph) { ph.remove(); ph = null; } return; }
    var wasOpen = g.classList.contains('open');
    g.classList.remove('open', 'anchored'); g.style.cssText = '';
    if (!ph) { ph = document.createElement('span'); ph.className = 'fold-ph'; g.parentNode.insertBefore(ph, g); }
    ph.style.width = g.offsetWidth + 'px'; ph.style.height = g.offsetHeight + 'px';
    var host = g.parentNode, hr = host.getBoundingClientRect(), pr = ph.getBoundingClientRect();
    if (getComputedStyle(host).position === 'static') host.style.position = 'relative';
    g.classList.add('anchored');
    g.style.right = (hr.right - pr.right) + 'px'; g.style.top = (pr.top - hr.top) + 'px';
    if (wasOpen) g.classList.add('open');
  }
  function set(open) { g.classList.toggle('open', open); if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false'); }
  if (btn) btn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); set(!g.classList.contains('open')); });
  document.addEventListener('click', function (e) { if (!g.contains(e.target)) set(false); });
  window.addEventListener('resize', anchor);
  anchor();
})();
