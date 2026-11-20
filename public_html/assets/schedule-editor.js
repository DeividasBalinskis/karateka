// Tvarkaraščio ir kainų redagavimas: kiekvienas pakeitimas išsaugomas iš karto (admin/tvarkarastis.php).
// Po išsaugojimo siunčiamas įvykis „schedule-changed“ - pagrindinis puslapis redagavimo režime atnaujina rodomą tvarkaraštį.
(function () {
  if (window.scheduleEditorReady) return;
  window.scheduleEditorReady = true;
  var api = window.SCHEDULE_API;

  function post(data) {
    var fd = new FormData();
    fd.append('_csrf', api.csrf);
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    return fetch(api.url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  function state(el, text, cls) { var s = el.querySelector('.sl-state'); s.textContent = text; s.className = 'sl-state' + (cls ? ' ' + cls : ''); s.title = cls === 'bad' || cls === 'warn' ? text : ''; }
  function changed(detail) { document.dispatchEvent(new CustomEvent('schedule-changed', { detail: detail })); }

  function saveRow(row) {
    var start = row.querySelector('.sl-start').value, end = row.querySelector('.sl-end').value, note = row.querySelector('.sl-note').value.trim();
    // Nauja eilutė saugoma, kai įvesti abu laikai arba pastaba
    if (!+row.dataset.id && !(start && end) && !note) return;
    if ((start && !end) || (!start && end)) { state(row, 'Įveskite abu laikus', 'warn'); return; }
    state(row, '…');
    var group = row.closest('.sl-group').dataset.group;
    post({ action: 'slot_save', id: row.dataset.id, group_id: group, weekday: row.querySelector('.sl-day').value, start_time: start, end_time: end, note: note })
      .then(function (d) {
        if (!d.ok) { state(row, d.error || 'Nepavyko', 'bad'); return; }
        row.dataset.id = d.id;
        d.warn ? state(row, '⚠ ' + d.warn, 'warn') : state(row, '✓', 'ok');
        changed({ group: group });
      })
      .catch(function () { state(row, 'Nepavyko - atnaujinkite puslapį', 'bad'); });
  }

  var timers = new WeakMap();
  function later(el, fn) { clearTimeout(timers.get(el)); timers.set(el, setTimeout(fn, 350)); }

  document.addEventListener('change', function (ev) {
    var row = ev.target.closest('.sl-row');
    if (row) { later(row, function () { saveRow(row); }); return; }
    var pr = ev.target.closest('.pr-edit');
    if (pr) {
      later(pr, function () {
        var main = pr.querySelector('.pr-main').value.trim();
        if (!main) { state(pr, 'Įveskite kainą', 'warn'); return; }
        state(pr, '…');
        post({ action: 'price_save', category: pr.dataset.cat, main: main, note: pr.querySelector('.pr-note').value.trim(), alt: pr.querySelector('.pr-alt').value.trim() })
          .then(function (d) { d.ok ? state(pr, '✓', 'ok') : state(pr, d.error || 'Nepavyko', 'bad'); if (d.ok) changed({ category: pr.dataset.cat }); })
          .catch(function () { state(pr, 'Nepavyko', 'bad'); });
      });
    }
  });

  function newRow(group, from) {
    var tpl = group.closest('.sl-editor').querySelector('.sl-tpl');
    var wrap = document.createElement('div'); wrap.innerHTML = tpl.innerHTML;
    var row = wrap.firstElementChild;
    if (from) {
      row.querySelector('.sl-day').value = String(+from.querySelector('.sl-day').value % 7 + 1);
      ['.sl-start', '.sl-end', '.sl-note'].forEach(function (c) { row.querySelector(c).value = from.querySelector(c).value; });
      from.after(row);
    } else {
      var last = group.querySelector('.sl-row:last-child');
      if (last) row.querySelector('.sl-day').value = String(+last.querySelector('.sl-day').value % 7 + 1);
      group.querySelector('.sl-rows').appendChild(row);
    }
    return row;
  }

  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('.sl-add, .sl-copy, .sl-del');
    if (!b) return;
    var group = b.closest('.sl-group');
    if (b.classList.contains('sl-add')) {
      newRow(group).querySelector('.sl-start').focus();
    } else if (b.classList.contains('sl-copy')) {
      var copy = newRow(group, b.closest('.sl-row'));
      saveRow(copy);
      copy.querySelector('.sl-day').focus();
    } else {
      var row = b.closest('.sl-row');
      if (!+row.dataset.id) { row.remove(); return; }
      if (!confirm('Ištrinti šį laiką?')) return;
      post({ action: 'slot_delete', id: row.dataset.id })
        .then(function (d) { if (d.ok) { row.remove(); changed({ group: group.dataset.group }); } else { state(row, d.error || 'Nepavyko', 'bad'); } })
        .catch(function () { state(row, 'Nepavyko', 'bad'); });
    }
  });
})();
