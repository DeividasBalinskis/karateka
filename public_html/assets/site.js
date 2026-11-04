// Bendras visų puslapių JS: registracijos laikmačiai ir pokalbio su Deniu langelis (apačioje dešinėje)
(function () {
  'use strict';

  // ---------- Registracijos laikmatis: „liko 2 d. 5 val.“ ----------
  function leftText(sec) {
    if (sec <= 0) return 'baigėsi';
    var d = Math.floor(sec / 86400), h = Math.floor(sec % 86400 / 3600), m = Math.floor(sec % 3600 / 60);
    return 'liko ' + (d ? d + ' d. ' + h + ' val.' : (h ? h + ' val. ' + m + ' min.' : Math.max(1, m) + ' min.'));
  }
  function tickDeadlines() {
    document.querySelectorAll('.deadline[data-deadline]').forEach(function (el) {
      var left = Math.round((new Date(el.getAttribute('data-deadline')).getTime() - Date.now()) / 1000),
          span = el.querySelector('.deadline-left');
      if (span) span.textContent = leftText(left);
      el.classList.toggle('soon', left > 0 && left < 86400);
      if (left <= 0) { el.classList.add('closed'); el.removeAttribute('data-deadline'); }
    });
  }
  tickDeadlines();
  setInterval(tickDeadlines, 30000);

})();

// ---------- Atsiliepimas (app/feedback.php): langas apačioje kairėje, siuntimas be puslapio perkrovimo ----------
(function () {
  var box = document.getElementById('fb');
  if (!box) return;
  var fab = document.getElementById('fbFab'), panel = document.getElementById('fbPanel');
  var form = panel.querySelector('.fb-form'), msg = panel.querySelector('.fb-msg');
  function toggle(open) {
    panel.hidden = !open;
    box.classList.toggle('open', open);
    fab.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) form.querySelector('textarea').focus();
  }
  fab.addEventListener('click', function () { toggle(panel.hidden); });
  panel.querySelector('.fb-close').addEventListener('click', function () { toggle(false); fab.focus(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { toggle(false); fab.focus(); } });
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        msg.hidden = false;
        msg.classList.toggle('err', !d.ok);
        msg.textContent = d.ok ? d.message : d.error;
        if (d.ok) {
          form.querySelector('textarea').value = '';
          var mine = panel.querySelector('.fb-mine');
          if (mine && d.html) { mine.hidden = false; mine.querySelector('.fb-list').insertAdjacentHTML('afterbegin', d.html); }
        }
      })
      .catch(function () { form.submit(); })
      .then(function () { btn.disabled = false; });
  });
})();
