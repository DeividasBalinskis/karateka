// Taikos ir LTKF vertimas į EN / RU naršyklėje (app/tl.php įdeda window.TL - žodyną iš app/lang/tl/*.json).
// Verčiami matomi tekstai ir title / placeholder / aria-label, taip pat ir vėliau JavaScript'u įdėti (MutationObserver), confirm / alert.
// Tikslus atitikmuo → šablonas su skaičiais ({#}) → datos ir frazės sudėtiniuose tekstuose (kategorijų pavadinimai ir pan.).
(function () {
  var D = window.TL;
  if (!D || !D.map) return;
  var map = D.map, pat = D.pat || {}, NUM = /\d+(?:[.,:–-]\d+)*/g, SKIP = { SCRIPT: 1, STYLE: 1, TEXTAREA: 1, NOSCRIPT: 1, CODE: 1 };
  var escRe = function (s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); };
  var rules = (D.rules || []).map(function (r) { return [new RegExp('(^|[^\\p{L}])' + escRe(r[0]) + '(?=$|[^\\p{L}])', 'gu'), r[1]]; });
  var months = D.months || [[], []];
  var dateRe = new RegExp('(\\d{4}) m\\. (' + months[0].join('|') + ') (\\d{1,2}) d\\.', 'g');
  var dayRe = new RegExp('(' + months[0].join('|') + ') (\\d{1,2}) d\\.', 'g');
  var yearRe = /(\d{4}) m\.(?=\s|$|,)/g;
  var cache = Object.create(null);

  // Frazių taisyklės - tik trumpiems sudėtiniams tekstams (kategorijų pavadinimai, „Ištrinti Jonas“), ne sakiniams
  var PREFIX = /^(Ištrinti|Išimti iš|Pašalinti|Perkelti į|Dabar|Toliau)\b/;
  function wrap(s, x) { return s.match(/^\s*/)[0] + x + s.match(/\s*$/)[0]; }
  function tr(s) {
    var t = s.replace(/\s+/g, ' ').trim();
    if (!t || !/[A-Za-zĄ-ž]{2}/.test(t)) return s;
    if (cache[t] !== undefined) return cache[t] === null ? s : wrap(s, cache[t]);
    var x = map[t];
    if (x === undefined) {
      var nums = [], key = t.replace(NUM, function (m) { nums.push(m); return '{#}'; });
      var p = pat[key];
      if (p !== undefined) { var i = 0; x = p.replace(/\{#\}/g, function () { return nums[i++] || ''; }); }
    }
    if (x === undefined) {
      var y = t.replace(dateRe, function (_, yr, mo, d) { return D.dateFmt.replace('{d}', d).replace('{m}', months[1][months[0].indexOf(mo)]).replace('{y}', yr); })
        .replace(dayRe, function (_, mo, d) { return d + ' ' + months[1][months[0].indexOf(mo)]; })
        .replace(yearRe, function (_, yr) { return yr + (D.yearSuffix || ''); });
      if (t.length <= 70 && (/\d/.test(t) || PREFIX.test(t) || t.indexOf(' · ') >= 0)) rules.forEach(function (r) { y = y.replace(r[0], function (m, pre) { return pre + r[1]; }); });
      x = y !== t ? y : undefined;
    }
    cache[t] = x === undefined ? null : x;
    return x === undefined ? s : wrap(s, x);
  }
  window.tlText = tr;

  function skipEl(el) { return !el || SKIP[el.nodeName] || (el.closest && el.closest('[contenteditable],[data-no-tl]')); }
  var ATTRS = ['title', 'placeholder', 'aria-label'];
  function attrs(el) {
    ATTRS.forEach(function (a) { var v = el.getAttribute(a); if (v) { var n = tr(v); if (n !== v) el.setAttribute(a, n); } });
    if (el.nodeName === 'INPUT' && (el.type === 'button' || el.type === 'submit') && el.value) { var n = tr(el.value); if (n !== el.value) el.value = n; }
  }
  function walk(root) {
    if (root.nodeType === 3) { if (!skipEl(root.parentNode)) { var v = tr(root.nodeValue); if (v !== root.nodeValue) root.nodeValue = v; } return; }
    if (root.nodeType !== 1 || skipEl(root)) return;
    attrs(root);
    root.querySelectorAll('[title],[placeholder],[aria-label],input[type=button],input[type=submit]').forEach(function (el) { if (!skipEl(el)) attrs(el); });
    var w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT), n, list = [];
    while ((n = w.nextNode())) list.push(n);
    list.forEach(function (t) { if (!skipEl(t.parentNode)) { var v = tr(t.nodeValue); if (v !== t.nodeValue) t.nodeValue = v; } });
  }
  function start() {
    document.documentElement.lang = D.lang;
    if (document.title) document.title = tr(document.title);
    walk(document.body);
    new MutationObserver(function (muts) {
      muts.forEach(function (m) {
        if (m.type === 'characterData') { walk(m.target); return; }
        if (m.type === 'attributes') { if (!skipEl(m.target)) attrs(m.target); return; }
        m.addedNodes.forEach(walk);
      });
    }).observe(document.body, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ATTRS });
  }
  // Klausimai ir pranešimai lange
  ['confirm', 'alert', 'prompt'].forEach(function (f) {
    var o = window[f];
    window[f] = function (msg) { var a = [].slice.call(arguments); if (typeof msg === 'string') a[0] = tr(msg); return o.apply(window, a); };
  });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
