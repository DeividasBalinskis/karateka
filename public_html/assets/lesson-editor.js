// Pamokos redagavimas pamokos puslapyje: blokai (tekstas, video, nuotrauka...), tempimas, „＋“ - įterpti bloką tarp kitų
(function () {
  var box = document.getElementById('blocks'), form = document.getElementById('lessonForm');
  if (!box || !form) return;
  var n = 0, dirty = false;
  var types = Array.prototype.map.call(document.querySelectorAll('[data-add]'), function (b) { return [b.dataset.add, b.innerHTML]; });

  function ytId(u) { var m = String(u).match(/(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/) || String(u).trim().match(/^([A-Za-z0-9_-]{11})$/); return m ? m[1] : null; }
  function grow(ta) { ta.style.height = 'auto'; ta.style.height = (ta.scrollHeight + 2) + 'px'; }
  function make(type) {
    var tpl = document.getElementById('tpl-' + type).innerHTML.replace(/__K__/g, 'n' + (++n) + Date.now());
    var wrap = document.createElement('div'); wrap.innerHTML = tpl;
    var el = wrap.firstElementChild; wire(el); dirty = true;
    return el;
  }
  function focusIn(el) { var f = el.querySelector('textarea, input[type=text], input[type=file]'); if (f) f.focus(); }

  function wire(el) {
    el.querySelector('.lb-up').onclick = function () { if (el.previousElementSibling) { box.insertBefore(el, el.previousElementSibling); dirty = true; } };
    el.querySelector('.lb-down').onclick = function () { if (el.nextElementSibling) { box.insertBefore(el.nextElementSibling, el); dirty = true; } };
    el.querySelector('.lb-del').onclick = function () {
      var filled = Array.prototype.some.call(el.querySelectorAll('textarea, input[type=text], input[type=hidden][name$="[image]"]'), function (i) { return i.value.trim() !== ''; });
      if (!filled || confirm('Pašalinti šį bloką?')) { el.remove(); dirty = true; }
    };
    var yt = el.querySelector('.lb-yt');
    if (yt) yt.addEventListener('input', function () { var id = ytId(yt.value), img = el.querySelector('.lb-preview'); if (id) { img.src = 'https://i.ytimg.com/vi/' + id + '/hqdefault.jpg'; img.hidden = false; } else { img.hidden = true; } });
    var file = el.querySelector('input[type=file]');
    if (file) file.addEventListener('change', function () { var img = el.querySelector('.lb-preview'); if (file.files[0]) { img.src = URL.createObjectURL(file.files[0]); img.hidden = false; } });
    el.querySelectorAll('textarea.lb-grow').forEach(function (ta) { ta.addEventListener('input', function () { grow(ta); }); grow(ta); });
    el.querySelectorAll('[data-fmt]').forEach(function (b) {
      b.onclick = function () {
        var ta = el.querySelector('textarea'), s = ta.selectionStart, e = ta.selectionEnd, v = ta.value, sel = v.slice(s, e), out;
        if (b.dataset.fmt === 'bold') { out = '**' + (sel || 'tekstas') + '**'; }
        else { var k = 0; out = (sel || 'punktas').split('\n').map(function (line) { k++; return (b.dataset.fmt === 'num' ? k + '. ' : '- ') + line.replace(/^\s*([-•*]|\d+[.)])\s+/, ''); }).join('\n'); }
        ta.value = v.slice(0, s) + out + v.slice(e); ta.focus(); ta.selectionStart = s; ta.selectionEnd = s + out.length; grow(ta); dirty = true;
      };
    });
    // ＋ po bloku: pasirinkti, kokį bloką įterpti
    var ins = document.createElement('div');
    ins.className = 'lb-insert';
    ins.innerHTML = '<button type="button" class="lb-ins-btn" title="Įterpti bloką po šiuo" aria-label="Įterpti bloką">+</button><div class="lb-ins-menu" hidden>'
      + types.map(function (t) { return '<button type="button" data-ins="' + t[0] + '">' + t[1] + '</button>'; }).join('') + '</div>';
    el.appendChild(ins);
    var menu = ins.querySelector('.lb-ins-menu');
    ins.querySelector('.lb-ins-btn').onclick = function () {
      document.querySelectorAll('.lb-ins-menu').forEach(function (m) { if (m !== menu) m.hidden = true; });
      menu.hidden = !menu.hidden;
    };
    menu.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-ins]'); if (!b) return;
      menu.hidden = true;
      var nb = make(b.dataset.ins); el.after(nb); focusIn(nb);
    });
  }

  box.querySelectorAll('.lblock').forEach(wire);
  document.querySelectorAll('[data-add]').forEach(function (b) {
    b.addEventListener('click', function () { var el = make(b.dataset.add); box.appendChild(el); focusIn(el); });
  });
  document.addEventListener('click', function (ev) { if (!ev.target.closest('.lb-insert')) document.querySelectorAll('.lb-ins-menu').forEach(function (m) { m.hidden = true; }); });
  if (window.Sortable) Sortable.create(box, { handle: '.lb-drag', animation: 150, forceFallback: true, onEnd: function () { dirty = true; } });

  form.addEventListener('input', function () { dirty = true; });
  form.addEventListener('submit', function () { dirty = false; });
  window.addEventListener('beforeunload', function (ev) { if (dirty) { ev.preventDefault(); ev.returnValue = ''; } });

  // Teisėjų / egzaminatorių pamokoms grupės netaikomos
  var groups = document.getElementById('lessonGroups');
  function sync() { var v = document.querySelector('input[name=audience]:checked'); if (groups) groups.hidden = v && v.value !== 'karateka'; }
  document.querySelectorAll('input[name=audience]').forEach(function (r) { r.addEventListener('change', sync); });
  sync();
})();
