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
