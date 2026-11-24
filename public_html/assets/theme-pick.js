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
  // v1.95: „Aa“ - šrifto bandymas (administratoriams): visi šriftai užkraunami atidarius, paspaudus - slapukas ir puslapis perkraunamas
  document.addEventListener('click', function (e) {
    var box = e.target.closest && e.target.closest('.font-pick');
    document.querySelectorAll('.font-pick .fp-panel:not([hidden])').forEach(function (p) {
      if (!box || !box.contains(p)) { p.hidden = true; p.parentNode.querySelector('.fp-btn').setAttribute('aria-expanded', 'false'); }
    });
    if (!box) return;
    var btn = e.target.closest('.fp-btn'), sw = e.target.closest('.fp-sw'), panel = box.querySelector('.fp-panel');
    if (btn) {
      panel.hidden = !panel.hidden; btn.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
      if (!panel.hidden && box.dataset.fonts && !document.getElementById('fpFonts')) {
        var l = document.createElement('link'); l.id = 'fpFonts'; l.rel = 'stylesheet'; l.href = box.dataset.fonts; document.head.appendChild(l);
      }
      return;
    }
    if (!sw) return;
    var id = sw.dataset.fontId;
    document.cookie = 'sriftas=' + (id === 'default' ? '; max-age=0' : id + '; max-age=31536000') + '; path=/; SameSite=Lax';
    location.reload();
  });
  // v1.95: „👍 Patinka“ temoms ir šriftams - išsaugoma (matoma kitiems administratoriams)
  document.addEventListener('change', function (e) {
    var chk = e.target.closest && e.target.closest('.dl-chk'); if (!chk) return;
    var host = chk.closest('.theme-pick, .font-pick'), lab = chk.closest('.dl-like');
    if (lab) lab.classList.toggle('on', chk.checked);
    var f = new FormData(); f.append('_csrf', host.dataset.csrf); f.append('action', 'like'); f.append('kind', chk.dataset.kind); f.append('id', chk.dataset.id); f.append('on', chk.checked ? '1' : '0');
    fetch(host.dataset.api, { method: 'POST', body: f, credentials: 'same-origin', headers: { 'X-CSRF-Token': host.dataset.csrf } }).catch(function () {});
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.font-pick .fp-panel:not([hidden])').forEach(function (p) { p.hidden = true; p.parentNode.querySelector('.fp-btn').setAttribute('aria-expanded', 'false'); });
    document.querySelectorAll('.theme-pick .tp-panel:not([hidden])').forEach(function (p) { p.hidden = true; p.parentNode.querySelector('.tp-btn').setAttribute('aria-expanded', 'false'); });
  });
})();
