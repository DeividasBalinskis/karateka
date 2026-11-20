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
    var all = box.querySelector('.tp-all-chk');
    if (box.dataset.scope === 'device') {   // Budo sekretoriatas: tema tik šiame įrenginyje (numatytąją nustato administratorius)
      try { localStorage.setItem('budo-theme', id + '|' + (sw.dataset.dark === '1' ? 1 : 0)); } catch (x) {}
      panel.hidden = true; box.querySelector('.tp-btn').setAttribute('aria-expanded', 'false');
      return;
    }
    var f = new FormData(); f.append('_csrf', box.dataset.csrf); f.append('scope', box.dataset.scope); f.append('theme', id); if (all && all.checked) f.append('all', '1');
    fetch(box.dataset.api, { method: 'POST', body: f, credentials: 'same-origin', headers: { 'X-CSRF-Token': box.dataset.csrf } }).catch(function () {});
    // Pasirinkus temą langelis užsidaro pats (#127)
    panel.hidden = true; box.querySelector('.tp-btn').setAttribute('aria-expanded', 'false');
  });
  // „Ir numatytoji visiems“ pažymėjus jau PO pasirinkimo - dabartinė tema iš karto tampa numatytąja (anksčiau reikėjo spausti temą dar kartą)
  document.addEventListener('change', function (e) {
    var chk = e.target.closest && e.target.closest('.tp-all-chk'), box = chk && chk.closest('.theme-pick');
    if (!chk || !chk.checked || !box) return;
    var on = box.querySelector('.tp-sw.on'); if (!on) return;
    var f = new FormData(); f.append('_csrf', box.dataset.csrf); f.append('scope', box.dataset.scope); f.append('theme', on.dataset.themeId); f.append('all', '1');
    fetch(box.dataset.api, { method: 'POST', body: f, credentials: 'same-origin', headers: { 'X-CSRF-Token': box.dataset.csrf } }).catch(function () {});
  });
  // Įrenginio tema (Budo sekretoriatas) - pritaikoma iš karto atidarius
  if (document.querySelector('.theme-pick[data-scope="device"]')) {
    try {
      var saved = (localStorage.getItem('budo-theme') || '').split('|');
      if (saved[0]) { document.body.dataset.theme = saved[0]; document.body.classList.toggle('theme-dark', saved[1] === '1');
        var sw0 = document.querySelector('.theme-pick[data-scope="device"] .tp-sw[data-theme-id="' + saved[0] + '"]');
        if (sw0) { document.querySelectorAll('.theme-pick[data-scope="device"] .tp-sw').forEach(function (x) { x.classList.toggle('on', x === sw0); }); } }
    } catch (x) {}
  }
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.theme-pick .tp-panel:not([hidden])').forEach(function (p) { p.hidden = true; p.parentNode.querySelector('.tp-btn').setAttribute('aria-expanded', 'false'); });
  });
})();
