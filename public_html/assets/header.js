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
})();
