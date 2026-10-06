// Bendras meniu: ☰ mygtukas, „Treniruotės“ išskleidimas, slinkimo juosta viršuje
(function () {
  var navList = document.getElementById('navList');
  var burger = document.getElementById('burgerBtn');
  if (burger && navList) {
    burger.addEventListener('click', function () { navList.classList.toggle('nav-open'); });
    navList.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () {
        navList.classList.remove('nav-open');
        document.querySelectorAll('.site-header .dropdown.open').forEach(function (d) { d.classList.remove('open'); });
      });
    });
  }

  document.querySelectorAll('.site-header .dropdown').forEach(function (dd) {
    dd.querySelector('.dropdown-trigger').addEventListener('click', function (e) {
      e.stopPropagation();
      dd.classList.toggle('open');
    });
  });
  document.addEventListener('click', function (e) {
    document.querySelectorAll('.site-header .dropdown.open').forEach(function (d) {
      if (!d.contains(e.target)) d.classList.remove('open');
    });
  });

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
