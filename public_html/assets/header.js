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
  // „Žiūrėti kaip“ telefono lape (.m-vs) visada išskleistas - jo neuždarome
  var all = function () { return Array.prototype.slice.call(document.querySelectorAll(sel)).filter(function (d) { return !d.closest('.m-vs'); }); };
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

// Valdymo šoninis meniu prasideda kartu su pirmu turinio bloku, ne su antrašte „Labas, …“ (#129)
(function () {
  function align() {
    var side = document.querySelector('.adm-shell > .adm-side'), main = document.querySelector('.adm-shell > .adm-main');
    if (!side || !main) return;
    var off = 0;
    if (getComputedStyle(side).position === 'sticky') {
      var kids = Array.prototype.filter.call(main.children, function (el) { return el.offsetParent !== null; }), first = null;
      for (var i = 0; i < kids.length; i++) {
        if (!kids[i].matches('.flash, .page-head, h1, .crumbs, .lead, .eyebrow')) { first = kids[i]; break; }
      }
      if (first && first !== kids[0]) off = Math.max(0, Math.round(first.getBoundingClientRect().top - main.getBoundingClientRect().top + parseFloat(getComputedStyle(first).marginTop || 0) * 0));
    }
    side.style.marginTop = off ? off + 'px' : '';
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', align); else align();
  window.addEventListener('load', align);
  window.addEventListener('resize', align);
})();


// #143: slaptažodžio laukeliai - paslėpti po taškais, šalia 👁 parodyti / paslėpti
(function () {
  function add(inp) {
    if (inp.dataset.eye) return; inp.dataset.eye = '1';
    var wrap = document.createElement('span'); wrap.className = 'pw-wrap';
    inp.parentNode.insertBefore(wrap, inp); wrap.appendChild(inp);
    var b = document.createElement('button'); b.type = 'button'; b.className = 'pw-eye'; b.setAttribute('aria-label', 'Rodyti slaptažodį'); b.textContent = '👁';
    b.addEventListener('click', function () { var show = inp.type === 'password'; inp.type = show ? 'text' : 'password'; b.classList.toggle('on', show); b.setAttribute('aria-label', show ? 'Slėpti slaptažodį' : 'Rodyti slaptažodį'); inp.focus(); });
    wrap.appendChild(b);
  }
  function scan() { document.querySelectorAll('input[type=password]').forEach(add); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan); else scan();
})();

// Failo laukas (.file-drop): pasirinktų failų pavadinimai, paryškinimas nutempiant; [data-copy] - kopijuoti tekstą iš elemento
(function () {
  document.addEventListener('change', function (e) {
    var inp = e.target;
    if (!inp || inp.type !== 'file' || !inp.closest('.file-drop')) return;
    var box = inp.closest('.file-drop'), out = box.querySelector('.fd-name'), names = Array.prototype.map.call(inp.files || [], function (f) { return f.name; });
    box.classList.toggle('has-file', names.length > 0);
    if (out) out.textContent = names.length > 3 ? names.slice(0, 3).join(', ') + ' +' + (names.length - 3) : names.join(', ');
  });
  ['dragenter', 'dragover'].forEach(function (ev) {
    document.addEventListener(ev, function (e) { var b = e.target.closest && e.target.closest('.file-drop'); if (b) b.classList.add('drag'); });
  });
  ['dragleave', 'drop'].forEach(function (ev) {
    document.addEventListener(ev, function (e) { var b = e.target.closest && e.target.closest('.file-drop'); if (b) b.classList.remove('drag'); });
  });
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-copy]');
    if (!b) return;
    var src = document.getElementById(b.getAttribute('data-copy')), lbl = b.querySelector('span') || b;
    if (!src) return;
    try { navigator.clipboard.writeText(src.textContent); } catch (x) {}
    if (!b.dataset.label) b.dataset.label = lbl.textContent;
    lbl.textContent = '✓ ' + (document.documentElement.lang === 'en' ? 'Copied' : document.documentElement.lang === 'ru' ? 'Скопировано' : 'Nukopijuota');
    setTimeout(function () { lbl.textContent = b.dataset.label; }, 2000);
  });
})();
