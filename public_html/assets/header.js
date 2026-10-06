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

  // Pagrindinio meniu grupė centre; jei siaurame ekrane užliptų ant dešiniųjų nuorodų - centruojame laisvoje vietoje
  var header = document.querySelector('.site-header');
  var fitMenu = function () {
    if (!header) return;
    header.classList.remove('center-off');
    var on = header.querySelector('.onpage-section'), pg = header.querySelector('.page-section');
    if (on && pg && on.getBoundingClientRect().right > pg.getBoundingClientRect().left - 12) header.classList.add('center-off');
  };
  fitMenu();
  window.addEventListener('resize', fitMenu);
  if (document.fonts) document.fonts.ready.then(fitMenu);

  var bar = document.getElementById('progressBar');
  if (bar) {
    var update = function () {
      var h = document.documentElement, max = h.scrollHeight - h.clientHeight;
      bar.style.width = (max > 0 ? h.scrollTop / max * 100 : 0) + '%';
    };
    document.addEventListener('scroll', update, { passive: true });
    update();
  }
})();
