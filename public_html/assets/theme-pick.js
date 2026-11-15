// Temos pasirinkimas (app/themes.php theme_picker_html): 🎨 - visos temos su paveikslėliais; paspaudus - pakeičiama iš karto ir išsaugoma.
(function () {
  document.addEventListener('click', function (e) {
    var box = e.target.closest && e.target.closest('.theme-pick');
    document.querySelectorAll('.theme-pick .tp-panel:not([hidden])').forEach(function (p) {
      if (!box || !box.contains(p)) { p.hidden = true; p.parentNode.querySelector('.tp-btn').setAttribute('aria-expanded', 'false'); }
    });
    if (!box) return;
    var btn = e.target.closest('.tp-btn'), sw = e.target.closest('.tp-sw'), panel = box.querySelector('.tp-panel');
    if (btn) { panel.hidden = !panel.hidden; btn.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true'); return; }
    if (!sw) return;
    var id = sw.dataset.themeId;
    document.body.dataset.theme = id;
    document.body.classList.toggle('theme-dark', sw.dataset.dark === '1');
    box.querySelectorAll('.tp-sw').forEach(function (x) { x.classList.toggle('on', x === sw); x.setAttribute('aria-pressed', x === sw); });
    box.querySelector('.tp-now').textContent = sw.querySelector('.tp-name').textContent.split(' ')[0];
    var f = new FormData(); f.append('_csrf', box.dataset.csrf); f.append('scope', box.dataset.scope); f.append('theme', id);
    fetch(box.dataset.api, { method: 'POST', body: f, credentials: 'same-origin', headers: { 'X-CSRF-Token': box.dataset.csrf } }).catch(function () {});
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.theme-pick .tp-panel:not([hidden])').forEach(function (p) { p.hidden = true; });
  });
})();
