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
