// „Mano dojo“: personažo pasaulis (aplinka, namas, dekoracijos, gyvūnėliai), XP, savybės, apranga, inventorius, gyvūnėliai, namų tvarkymas,
// dovanų dėžės, išvaizdos keitimas ir testavimo skydelis adminui. Duomenys - window.GAME (iš serverio), veiksmai - zaidimas.php API.
(function () {
  var G = window.GAME, root = document.getElementById('dojo');
  if (!G || !root) return;
  var C = G.catalog, A = window.GameArt, T = window.tr || function (s) { return s; };
  var H = G.hero, ro = !!G.readOnly, filt = 'all', pendAlloc = null;
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var $ = function (s, el) { return (el || root).querySelector(s); };
  var STAT_IC = { str: '💪', agi: '⚡', int: '🧠', end: '🛡️' };
  var SAY = ['Oss!', 'Kiai!', 'Hajime!', 'Arigato!', T('Treniruojamės!'), T('Dar vienas kata!'), T('Esu pasiruošęs!'), '💪', '🥋'];

  function api(data) {
    var post = !!data.action;
    var url = G.api + (post ? '' : ('?' + Object.keys(data).map(function (k) { return k + '=' + encodeURIComponent(data[k]); }).join('&') + '&h=' + H.id));
    if (post) data.h = H.id;
    return fetch(url, { method: post ? 'POST' : 'GET', credentials: 'same-origin', headers: post ? { 'Content-Type': 'application/json', 'X-CSRF-Token': G.csrf } : {}, body: post ? JSON.stringify(data) : undefined })
      .then(function (r) { return r.json(); }).then(function (d) {
        if (d && d.hero) { H = d.hero; G.hero = H; }
        if (d && d.ok === false && d.error) toast(d.error);
        return d;
      }).catch(function () { toast(T('Nepavyko - patikrinkite ryšį.')); return { ok: false }; });
  }
  function toast(msg, btn) {
    var old = document.querySelector('.g-toast'); if (old) old.remove();
    var el = document.createElement('div'); el.className = 'g-toast'; el.innerHTML = '<span>' + msg + '</span>';
    if (btn) { var b = document.createElement('button'); b.className = 'g-btn sm gold'; b.textContent = btn[0]; b.onclick = function () { el.remove(); btn[1](); }; el.appendChild(b); }
    document.body.appendChild(el); setTimeout(function () { el.remove(); }, btn ? 9000 : 3800);
  }
  G.toast = toast;
  function item(k) { return C.items[k]; }
  function cfg(h) { return { species: h.species, gender: h.gender, look: h.look, equipped: h.equipped, belt: h.belt, name: h.name }; }
  function statTxt(it) {
    if (!it) return '';
    var s = it.s || [], out = [], keys = ['str', 'agi', 'int', 'end'];
    keys.forEach(function (k, i) { if (s[i]) out.push('+' + s[i] + ' ' + STAT_IC[k]); });
    if (s[4]) out.push('+' + s[4] + ' 🪖');
    if (it.d) out.push(it.d[0] + '–' + it.d[1] + ' ⚔️');
    return out.join(' ');
  }
  function beltName(n) { var b = C.belts[n] || C.belts[String(n)]; return b ? b[0] + ' · ' + b[1] : ''; }

  // ---------- Pasaulis: tik fonas (gamta / dvasinė vieta / dojo su vaizdu), personažas ir gyvūnėliai ----------
  function world() {
    var home = H.home || {}, bg = item(home.bg), html = '';
    var hour = new Date().getHours(), night = hour >= 21 || hour < 6;
    html += '<div class="dw-obj dw-hero live" title="' + esc(H.name) + '">' + A.hero(cfg(H)) + '</div>';
    (H.pets || []).forEach(function (p, i) {
      html += '<div class="dw-obj dw-pet" data-pet="' + p.id + '" style="left:' + [22, 48, 14, 54, 8, 60][i % 6] + '%;bottom:calc(var(--pet-lift, 0%) + ' + (4 + (i * 5) % 9) + '%)" title="' + esc(p.name) + '">' + A.pet(p.species, p.color) + '</div>';
    });
    html += '<div class="dw-fx">' + particles(night) + '</div>';
    // Pirmą kartą - kvietimas susikurti herojų (ne iššokantis langas, kad neuždengtų kitų pranešimų)
    if (!ro && !(H.look && +H.look.setup)) html += '<button type="button" class="dw-setup" data-act="editor">👋 ' + T('Susikurk savo herojų!') + '</button>';
    var w = $('.dw');
    w.style.backgroundImage = 'url(' + A.sceneUrl(bg ? bg.a.sc : 'dojo') + ')';
    w.style.setProperty('--scene', 'url(' + A.sceneUrl(bg ? bg.a.sc : 'dojo') + ')');   // v1.49: ramus vaizdas - aplinka išblukinta, personažas ryškus
    w.innerHTML = html;
    wander();
  }
  function stars() { var s = ''; for (var i = 0; i < 40; i++) s += '<i style="left:' + (Math.random() * 100).toFixed(1) + '%;top:' + (Math.random() * 100).toFixed(1) + '%;animation-delay:' + (Math.random() * 3).toFixed(1) + 's"></i>'; return s; }
  function particles(night) {
    var m = new Date().getMonth() + 1, sc = (item((H.home || {}).bg) || { a: {} }).a.sc, s = '', n = 18, kind;
    if (sc === 'ziema' || m === 12 || m <= 2) kind = 'snow';
    else if (sc === 'sakura' || (m >= 3 && m <= 5)) kind = 'petal';
    else if (sc === 'ruduo' || (m >= 9 && m <= 11)) kind = 'leaf';
    else if (sc === 'kosmosas') kind = 'star';
    else kind = night ? 'fly' : 'none';
    if (kind === 'none') return '';
    for (var i = 0; i < n; i++) {
      var x = (Math.random() * 100).toFixed(1), d = (6 + Math.random() * 8).toFixed(1), dl = (-Math.random() * 12).toFixed(1), sz = (5 + Math.random() * 6).toFixed(0);
      var st = 'left:' + x + '%;animation-duration:' + d + 's;animation-delay:' + dl + 's;';
      if (kind === 'snow') s += '<i style="' + st + 'width:' + sz + 'px;height:' + sz + 'px;background:#fff;border-radius:50%;opacity:.85"></i>';
      if (kind === 'petal') s += '<i style="' + st + 'width:' + sz + 'px;height:' + (sz * 0.6) + 'px;background:#F7B6C8;border-radius:60% 0 60% 0"></i>';
      if (kind === 'leaf') s += '<i style="' + st + 'width:' + sz + 'px;height:' + sz + 'px;background:' + ['#D9762B', '#C2452D', '#E5A93A'][i % 3] + ';border-radius:0 70% 0 70%"></i>';
      if (kind === 'star') s += '<i style="' + st + 'width:3px;height:3px;background:#fff;border-radius:50%"></i>';
      if (kind === 'fly') s += '<i class="fly" style="left:' + x + '%;bottom:' + (5 + Math.random() * 40).toFixed(0) + '%;width:4px;height:4px;border-radius:50%;background:#FFF3A0;box-shadow:0 0 8px 2px #FFE45C;animation-duration:' + (2 + Math.random() * 3).toFixed(1) + 's;animation-delay:' + dl + 's"></i>';
    }
    return s;
  }
  var wanderT = null;
  function wander() {
    clearInterval(wanderT);
    var move = function () {
      root.querySelectorAll('.dw-pet').forEach(function (el) {
        if (Math.random() < 0.45) return;
        var cur = parseFloat(el.style.left) || 50, nx = Math.max(8, Math.min(92, cur + (Math.random() * 50 - 25)));
        el.classList.toggle('flip', nx < cur);
        el.classList.add('walk');
        el.style.left = nx.toFixed(1) + '%';
        el.style.bottom = 'calc(var(--pet-lift, 0%) + ' + (3 + Math.random() * 12).toFixed(1) + '%)';   // --pet-lift: kur „žemė“ (CSS)
        clearTimeout(el._t); el._t = setTimeout(function () { el.classList.remove('walk'); }, 2600);
      });
    };
    setTimeout(move, 600);
    wanderT = setInterval(move, 4200);
  }
  function say(el, txt) {
    var w = $('.dw'), r = el.getBoundingClientRect(), wr = w.getBoundingClientRect();
    var b = document.createElement('div'); b.className = 'dw-say'; b.textContent = txt;
    b.style.left = (r.left - wr.left + r.width / 2) + 'px'; b.style.top = (r.top - wr.top + 4) + 'px';
    w.appendChild(b); setTimeout(function () { b.remove(); }, 1600);
  }
  function hearts(el) {
    var w = $('.dw'), r = el.getBoundingClientRect(), wr = w.getBoundingClientRect();
    for (var i = 0; i < 3; i++) {
      var h = document.createElement('div'); h.className = 'dw-heart'; h.textContent = ['💖', '💕', '✨'][i];
      h.style.left = (r.left - wr.left + r.width / 2 + (i - 1) * 16) + 'px'; h.style.top = (r.top - wr.top) + 'px'; h.style.animationDelay = (i * 0.15) + 's';
      w.appendChild(h); setTimeout(function (x) { return function () { x.remove(); }; }(h), 1600);
    }
  }

  function bindWorld() {
    $('.dw').addEventListener('click', function (e) {
      var t = e.target;
      if (t.closest('[data-act]')) return;
      var hero = t.closest('.dw-hero');
      if (hero) { hero.classList.remove('hero-jump'); void hero.offsetWidth; hero.classList.add('hero-jump'); say(hero, SAY[Math.floor(Math.random() * SAY.length)]); return; }
      var pet = t.closest('.dw-pet');
      if (pet) { hearts(pet); var p = (H.pets || []).filter(function (x) { return +x.id === +pet.dataset.pet; })[0]; if (p) say(pet, p.name + (p.can_feed && !ro ? ' 🍖?' : ' 💕')); }
    });
  }

  // ---------- Sąsaja aplinkoje ----------
  // Kabanti lentelė (vardas, lygis, XP), lenta su savybėmis (paspaudus - ritinys), rodyklės į žaidimus, įrankiai viršuje.
  var PANES = [['stats', 'savybes', 'Savybės'], ['equip', 'kuprine', 'Kuprinė'], ['pets', 'letena', 'Gyvūnėliai'], ['home', 'aplinka', 'Aplinka'], ['col', 'knyga', 'Kolekcija'], ['test', 'kolba', 'Testavimas (adminui)']];
  function ui() {
    var st = H.stats, x = H.xp, per = x.per || 100, pct = Math.min(100, Math.round(x.into / per * 100)), boxes = (H.boxes || []).length, html = '';
    // Vardas, lygis ir XP - po buveine (kaip anksčiau paskyroje), kad neuždengtų vaizdo (#84)
    var foot = $('.dw-foot');
    if (foot) foot.innerHTML = '<div class="df-name"><b>' + esc(H.publicName || H.name) + '</b><small>' + esc(beltName(H.belt)) + '</small></div>' +
      '<span class="df-lv">⭐ ' + T('Lygis') + ' ' + H.level + '</span>' +
      '<button type="button" class="xp-track df-xp" data-xp-more aria-expanded="false" title="' + esc(T('Už ką gauta ir kaip gauti daugiau')) + '"><i style="width:' + pct + '%"></i><span>' + x.into + ' / ' + per + ' XP</span></button>' +
      (H.xpWays ? '<button type="button" class="df-more" data-xp-more>' + T('Už ką XP?') + ' ▾</button>' +
        '<div class="df-log" hidden><div><h4>' + T('Už ką gauta') + '</h4>' + ((H.xpLog || []).length ? '<ul>' + H.xpLog.map(function (r) { return '<li><span>' + esc(r.n) + (r.e ? ' <small>' + esc(r.e) + '</small>' : '') + ' <small>' + esc(r.d) + '</small></span><b>+' + r.p + '</b></li>'; }).join('') + '</ul>' : '<p class="small muted">' + T('Dar nieko - XP ateina iš aktyvumo taškų klube.') + '</p>') + '</div>' +
        '<div><h4>' + T('Kaip gauti daugiau') + '</h4><ul>' + H.xpWays.map(function (w) { return '<li><span>' + esc(w.n) + '</span><b>+' + w.p + '</b></li>'; }).join('') + '</ul>' +
        '<p class="small df-gift">🎁 ' + T('XP = aktyvumo taškai klube. Kiekvienas naujas lygis - dovanų dėžė.') + '</p></div></div>' : '');
    if (foot && !foot.dataset.xpBound) {
      foot.dataset.xpBound = '1';
      foot.addEventListener('click', function (e) {
        if (!e.target.closest('[data-xp-more]')) return;
        var log = foot.querySelector('.df-log'); if (!log) return;
        log.hidden = !log.hidden;
        foot.querySelectorAll('[data-xp-more]').forEach(function (b) { b.setAttribute('aria-expanded', log.hidden ? 'false' : 'true'); });
        var mb = foot.querySelector('.df-more'); if (mb) mb.textContent = T('Už ką XP?') + (log.hidden ? ' ▾' : ' ▴');
      });
    }
    html += '<div class="dw-bar">';
    if (!ro) {
      html += '<button type="button" class="dw-tool' + (boxes ? ' glow' : '') + '" data-act="boxes" title="' + esc(T('Dovanos')) + '">' + A.icon('dovana') + '<span>' + T('Dovanos') + '</span>' + (boxes ? '<em>' + boxes + '</em>' : '') + '</button>';
      html += '<button type="button" class="dw-tool' + (H.look && +H.look.setup ? '' : ' glow') + '" data-act="editor" title="' + esc(T('Išvaizda')) + '">' + A.icon('teptukas') + '<span>' + T('Išvaizda') + '</span></button>';
    }
    PANES.forEach(function (p) {
      if (p[0] === 'stats' || (p[0] === 'test' && !(G.admin && !ro)) || (ro && p[0] === 'home')) return;
      html += '<button type="button" class="dw-tool" data-pane="' + p[0] + '" title="' + esc(T(p[2])) + '">' + A.icon(p[1]) + '<span>' + esc(T(p[2])) + '</span>' + (p[0] === 'pets' ? '<em class="soft" id="g-pets-cnt"></em>' : p[0] === 'col' ? '<em class="soft wide" id="g-col-cnt"></em>' : '') + '</button>';
    });
    html += '</div>';
    html += '<button type="button" class="dw-board" data-pane="stats" title="' + esc(T('Savybės')) + '"><span class="bd-h">' + T('Savybės') + (H.points > 0 && !ro ? ' <em>+' + H.points + '</em>' : '') + '</span>' +
      '<span class="bd-big"><span>' + A.icon('sirdis') + '<b>' + st.hp + '</b></span><span>' + A.icon('smugis') + '<b>' + st.dmg[0] + '–' + st.dmg[1] + '</b></span><span>' + A.icon('blokas') + '<b>' + st.arm + '</b></span></span>' +
      '<span class="bd-st">' + Object.keys(C.stats).map(function (k) { return '<span>' + STAT_IC[k] + ' ' + st.total[k] + '</span>'; }).join('') + '</span>' +
      '<span class="bd-more">' + T('Atverti') + ' ▾</span><i class="bd-leg l"></i><i class="bd-leg r"></i></button>';
    if (!ro && G.urls) {
      var sg = function (cls, ic, url, name, sub) { return '<a class="dw-sign ' + cls + '" href="' + url + '" title="' + esc(sub) + '">' + A.icon(ic) + '<span>' + name + '</span></a>'; };
      html += '<div class="dw-post"><i class="dp-pole"></i>' +
        sg('kova', 'kova', G.urls.arena, T('Karate kova'), T('Pasirink, kur smūgiuoti ir ką ginti')) +
        sg('greita', 'greita', G.urls.greita, T('Greita kova'), T('Reakcija: blokuok laiku ir kontratakuok')) +
        sg('keturi', 'keturi', G.urls.keturi, T('Keturi iš eilės'), T('Su gyvūnėlių kortomis')) +
        sg('ritmas', 'ritmas', G.urls.ritmas, T('Kata ritmas'), T('Muša būgnai - daryk techniką laiku')) +
        (G.urls.lab ? sg('lab', 'kova', G.urls.lab, T('Nauja kova 🧪'), T('Bandymai: apranga, kortos, gyvūnėliai')) : '') + '</div>';
    }
    $('.dw-ui').innerHTML = html;
    var open = ($('.dw-scroll') || {}).dataset; if (open && open.open) root.querySelectorAll('.dw-tool[data-pane="' + open.open + '"]').forEach(function (b) { b.classList.add('on'); });
  }
  function side() {
    var st = H.stats, pts = ro ? 0 : (H.points - (pendAlloc ? sum(pendAlloc) : 0)), html = '';
    var max = Math.max(12, Math.max.apply(null, Object.keys(st.total).map(function (k) { return st.total[k] + (pendAlloc ? pendAlloc[k] : 0); })));
    html += '<div class="g-derived big"><div>' + A.icon('sirdis') + '<b>' + st.hp + '</b>' + T('Gyvybės') + '</div><div>' + A.icon('smugis') + '<b>' + st.dmg[0] + '–' + st.dmg[1] + '</b>' + T('Smūgis') + '</div><div>' + A.icon('blokas') + '<b>' + st.arm + '</b>' + T('Apsauga') + '</div></div>';
    if (pts > 0 || pendAlloc) html += '<div class="g-points"><span>✨ ' + T('Laisvi taškai:') + ' <b>' + pts + '</b></span>' + (pendAlloc ? '<button type="button" class="px-btn sm" data-act="alloc-save">' + T('Išsaugoti') + '</button>' : '') + '</div>';
    Object.keys(C.stats).forEach(function (k) {
      var add = pendAlloc ? pendAlloc[k] : 0, tot = st.total[k] + add, bon = st.total[k] - st.base[k];
      html += '<div class="g-stat"><span>' + STAT_IC[k] + '</span><span>' + esc(C.stats[k]) + (bon > 0 ? ' <span class="bon">+' + bon + '</span>' : '') + '</span><b>' + tot + (add ? ' <span class="bon">(+' + add + ')</span>' : '') + '</b>' +
        '<button type="button" class="plus" data-plus="' + k + '"' + (pts > 0 ? '' : ' hidden') + ' aria-label="+">+</button>' +
        '<div class="bar"><i style="width:' + Math.min(100, tot / max * 100).toFixed(0) + '%"></i></div></div>';
    });
    if (H.skill) html += '<div class="g-skill" title="' + esc(T('Suveikia jėgos kovose. Kas 2 pergalės su šiuo personažu - +1 % (iki 25 %).')) + '">✨ <b>' + esc(H.skill.n) + '</b> - ' + esc(H.skill.d) +
      '<br><small>' + T('Tikimybė') + ' ' + H.skill.p + '% · ' + T('pergalių su šiuo personažu') + ': ' + H.skill.wins + '</small></div>';
    html += '<div class="g-derived"><div><b>' + H.ratingPower + '</b>🏆 ' + T('Jėgos reitingas') + '</div><div><b>' + H.ratingFair + '</b>🎯 ' + T('Gudrumo reitingas') + '</div><div><b>' + H.wins + ':' + H.losses + '</b>' + T('Pergalės') + '</div></div>';
    if (!ro) html += '<label class="g-switch" title="' + esc(T('Ar kiti gali kviesti tave žaisti')) + '"><input type="checkbox" data-act="invites"' + (H.invites ? ' checked' : '') + '> ' + T('Priimu kvietimus') + '</label>';
    $('#g-stats').innerHTML = html;
  }
  function sum(o) { var s = 0; for (var k in o) s += o[k]; return s; }
  // Ritinys: išsivynioja scenoje ir rodo vieną skiltį
  function openPane(name) {
    var sc = $('.dw-scroll'), p = PANES.filter(function (x) { return x[0] === name; })[0];
    if (!sc || !p) return;
    sc.querySelectorAll('.sc-pane').forEach(function (el) { el.hidden = el.dataset.pane !== name; });
    sc.querySelector('.sc-title').innerHTML = A.icon(p[1]) + ' ' + esc(T(p[2]));
    var again = !sc.hidden && sc.dataset.open === name;   // jau atvertas tas pats - be animacijos iš naujo
    sc.hidden = false;
    if (!again) { sc.classList.remove('unroll'); void sc.offsetWidth; sc.classList.add('unroll'); }
    sc.dataset.open = name;
    root.querySelectorAll('.dw-tool[data-pane]').forEach(function (b) { b.classList.toggle('on', b.dataset.pane === name); });
  }
  function closePane() { var sc = $('.dw-scroll'); if (sc) { sc.hidden = true; sc.dataset.open = ''; } root.querySelectorAll('.dw-tool.on').forEach(function (b) { b.classList.remove('on'); }); }

  // ---------- Skiltys ----------
  // Pikselinis personažas kol kas rodo kimono (spalvą) ir tikrą diržą; kiti priedai (kepurės, auros…) grįš, kai bus nupiešti pikseliais
  var PIXEL = !!A.pixel, HIDDEN = PIXEL ? ['belt', 'hair', 'head', 'face', 'neck', 'hands', 'back', 'aura', 'species', 'house', 'decor'] : [];
  var SLOT_ORDER = PIXEL ? ['weapon', 'offhand', 'kimono'] : ['weapon', 'offhand', 'kimono', 'belt', 'hair', 'head', 'face', 'neck', 'hands', 'back', 'aura'];
  function renderEquip() {
    var html = '<div class="g-slots">';
    SLOT_ORDER.forEach(function (sl) {
      var k = H.equipped[sl], it = item(k);
      html += '<button type="button" class="g-slot' + (it ? ' on' : '') + '" data-slot="' + sl + '">' + (it ? A.itemIcon(k, it) + '<b>' + esc(it.n) + '</b>' : '<span style="font-size:1.6rem;opacity:.35">＋</span>') + '<span>' + esc(C.slots[sl]) + '</span></button>';
    });
    html += '</div>';
    if (H.pet) html += '<div class="g-sub">' + T('Į kovą eina') + ' (' + (H.pet2 ? 2 : 1) + '/2)</div><div class="g-pets">' + petCard(H.pet, true) + (H.pet2 ? petCard(H.pet2, true) : '') + '</div>';
    $('#g-equip').innerHTML = html;
  }
  var FILTERS = [['all', 'Viskas'], ['wear', 'Apranga'], ['weapon', 'Ginklai'], ['home', 'Aplinkos'], ['paint', 'Spalvos'], ['egg', 'Kiaušiniai']];
  function inFilter(sl) {
    if (filt === 'all') return true;
    if (filt === 'wear') return ['kimono', 'belt', 'head', 'face', 'neck', 'hands', 'back', 'aura', 'hair'].indexOf(sl) >= 0;
    if (filt === 'home') return ['bg', 'house', 'decor'].indexOf(sl) >= 0;
    return sl === filt || (SLOT_ORDER.indexOf(filt) >= 0 && sl === filt);
  }
  function equippedKey(k) {
    var it = item(k); if (!it) return false;
    if (H.equipped[it.slot] === k) return true;
    if (it.slot === 'bg' || it.slot === 'house') return H.home[it.slot] === k;
    if (it.slot === 'decor') return (H.home.decor || []).some(function (d) { return d.k === k; });
    return false;
  }
  function renderInv() {
    var own = H.owned || {}, keys = Object.keys(own).filter(function (k) { return item(k) && HIDDEN.indexOf(item(k).slot) < 0; });
    keys.sort(function (a, b) { var A1 = item(a), B1 = item(b); return SLOT_ORDER.concat(['paint', 'bg', 'house', 'decor', 'egg']).indexOf(A1.slot) - SLOT_ORDER.concat(['paint', 'bg', 'house', 'decor', 'egg']).indexOf(B1.slot) || B1.r - A1.r; });
    var html = '<div class="g-filters">' + FILTERS.map(function (f) { return '<button type="button" data-filt="' + f[0] + '" class="' + (filt === f[0] ? 'active' : '') + '">' + esc(T(f[1])) + '</button>'; }).join('') +
      (SLOT_ORDER.indexOf(filt) >= 0 ? '<button type="button" class="active">' + esc(C.slots[filt]) + '</button>' : '') + '</div><div class="g-items">';
    var shown = keys.filter(function (k) { return inFilter(item(k).slot); });
    shown.forEach(function (k) {
      var it = item(k);
      html += '<button type="button" class="g-item r' + it.r + '" data-item="' + k + '" title="' + esc(C.rarity[it.r] + ' · ' + C.slots[it.slot]) + '">' + (own[k] > 1 ? '<span class="qty">×' + own[k] + '</span>' : '') + (equippedKey(k) ? '<span class="eq">✓</span>' : '') +
        A.itemIcon(k, it) + '<span class="nm">' + esc(it.n) + '</span><span class="st">' + statTxt(it) + '</span></button>';
    });
    if (!shown.length) html += '<div class="g-empty">' + T('Čia dar nieko nėra - atidaryk dovanų dėžę!') + '</div>';
    html += '</div>';
    // kas atsirakins kituose lygiuose
    var next = Object.keys(C.items).filter(function (k) { var it = item(k); return it.lvl > H.level && !own[k] && inFilter(it.slot); })
      .sort(function (a, b) { return item(a).lvl - item(b).lvl; }).slice(0, 8);
    if (next.length) {
      html += '<div class="g-sub">🔒 ' + T('Atsirakins kituose lygiuose') + '</div><div class="g-items">' + next.map(function (k) {
        var it = item(k); return '<div class="g-item locked r' + it.r + '">' + A.itemIcon(k, it) + '<span class="nm">' + esc(it.n) + '</span><span class="st">' + T('Lygis') + ' ' + it.lvl + '</span></div>';
      }).join('') + '</div>';
    }
    $('#g-inv').innerHTML = html;
    $('#g-inv-cnt').textContent = keys.length;
  }
  function petCard(p, mini) {
    var P = C.pets[p.species] || {}, active = (H.pet && +H.pet.id === +p.id) || (H.pet2 && +H.pet2.id === +p.id);
    return '<div class="g-pet' + (active ? ' active' : '') + '" data-pet-card="' + p.id + '">' + A.pet(p.species, p.color) +
      '<div><div class="pn">' + esc(p.name) + '</div><div class="pl">' + esc(P.n || '') + ' · ' + T('Lygis') + ' ' + p.level + '</div>' +
      '<div class="mood" title="' + esc(T('Nuotaika')) + '"><i style="width:' + p.mood + '%"></i></div><div class="pl">✨ ' + esc(P.abn || '') + '</div></div>' +
      (mini || ro ? '' : '<div class="acts">' + (p.can_feed ? '<button type="button" class="g-btn sm" data-feed="' + p.id + '">🍖 ' + T('Pamaitinti') + '</button>' : '<span class="pl">😊 ' + T('Sotus') + '</span>') +
        (active ? '<button type="button" class="g-btn sm" data-active="' + p.id + '" title="' + esc(T('Palikti namie')) + '">🏠 ' + T('Palikti namie') + '</button>'
          : '<button type="button" class="g-btn sm" data-active="' + p.id + '" title="' + esc(T('Į kovą galima pasiimti 2 gyvūnėlius')) + '">⚔️ ' + T('Imti į kovą') + (H.pet && H.pet2 ? ' (' + T('vietoj') + ' ' + esc(H.pet2.name) + ')' : '') + '</button>') +
        '<button type="button" class="g-btn sm" data-rename="' + p.id + '">✏️</button>' + (C.unlockAll ? '<button type="button" class="g-btn sm" data-release="' + p.id + '" title="' + esc(T('Paleisti')) + '">👋</button>' : '') + '</div>') + '</div>';
  }
  var starterPick = 'kitten';
  function renderPets() {
    var pets = H.pets || [], html = '';
    if (pets.length) html += '<div class="g-pets">' + pets.map(function (p) { return petCard(p); }).join('') + '</div>';
    if (!ro && pets.length < 6 && (!pets.length || C.unlockAll)) {
      html += '<p class="g-empty">' + (pets.length ? T('Testavimui visi gyvūnėliai atrakinti - priimk dar vieną (iki 6).') : T('Pasirink pirmą gyvūnėlį - jis gyvens tavo dojo ir padės kovose.')) + '</p><div class="g-starters">';
      Object.keys(C.pets).forEach(function (k) { if (C.pets[k].start) html += '<button type="button" class="g-starter' + (k === starterPick ? ' sel' : '') + '" data-starter="' + k + '">' + A.pet(k) + esc(C.pets[k].n) + '<br><small style="font-weight:500">✨ ' + esc(C.pets[k].abn) + '</small></button>'; });
      html += '</div><div class="row" style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap"><input type="text" id="g-pet-name" maxlength="30" placeholder="' + esc(T('Vardas')) + '" style="padding:8px 10px;border-radius:10px;border:1px solid rgba(23,20,15,.2);font:inherit"><button type="button" class="g-btn primary" data-act="adopt">🐾 ' + T('Priimti') + '</button></div>';
    }
    var eggs = Object.keys(H.owned || {}).filter(function (k) { return item(k) && item(k).slot === 'egg'; });
    if (eggs.length && !ro) html += '<div class="g-sub">🥚 ' + T('Kiaušiniai') + '</div><div class="g-items">' + eggs.map(function (k) { return '<button type="button" class="g-item r' + item(k).r + '" data-item="' + k + '">' + A.itemIcon(k, item(k)) + '<span class="nm">' + esc(item(k).n) + '</span><span class="st">' + T('Išperinti') + '</span></button>'; }).join('') + '</div>';
    if (pets.length && pets.length < 6 && !C.unlockAll) html += '<p class="g-empty" style="margin-top:8px">' + T('Daugiau gyvūnėlių - iš kiaušinių dovanų dėžėse (iki 6).') + '</p>';
    $('#g-pets').innerHTML = html;
    $('#g-pets-cnt').textContent = pets.length || '';
  }
  function renderHome() {
    if (ro) return;
    var own = H.owned || {}, home = H.home, cur = home.bg || '';
    var bgs = Object.keys(own).filter(function (k) { return item(k) && item(k).slot === 'bg'; });
    $('#g-home').innerHTML = '<p class="g-empty">' + T('Kur gyvena tavo personažas? Pasirink vietą.') + '</p><div class="g-scenes">' + bgs.map(function (k) {
      var it = item(k);
      return '<button type="button" data-bg="' + k + '" class="' + (cur === k || (!cur && it.a.sc === 'dojo') ? 'sel' : '') + '"><span class="bgthumb" style="background-image:url(' + A.sceneUrl(it.a.sc) + ')"></span><b>' + esc(it.n) + '</b><span class="st">' + statTxt(it) + '</span></button>';
    }).join('') + '</div>';
  }
  function renderTest() {
    var el = $('#g-test'); if (!el) return;
    var opts = Object.keys(C.belts).map(function (b) { return '<option value="' + b + '"' + (+b === +H.testBelt ? ' selected' : '') + '>' + esc(C.belts[b][0] + ' ' + C.belts[b][1]) + '</option>'; }).join('');
    el.innerHTML = '<div class="row"><label>' + T('Lygis') + ' <input type="number" min="1" max="99" id="t-lvl" value="' + (H.testLevel || H.level) + '" style="width:80px"></label><button type="button" class="g-btn sm" data-test="level">' + T('Nustatyti') + '</button><button type="button" class="g-btn sm" data-test="level-real">' + T('Tikras lygis') + '</button></div>' +
      '<div class="row"><label>' + T('Diržas') + ' <select id="t-belt"><option value="">' + T('— tikras —') + '</option>' + opts + '</select></label><button type="button" class="g-btn sm" data-test="belt">' + T('Nustatyti') + '</button></div>' +
      '<div class="row"><button type="button" class="g-btn sm" data-test="all">🎁 ' + T('Visi daiktai') + '</button><button type="button" class="g-btn sm" data-test="boxes">📦 +5 ' + T('dėžės') + '</button><button type="button" class="g-btn sm" data-test="reset" style="color:#B5281F">↺ ' + T('Pradėti iš naujo') + '</button></div>' +
      '<p class="g-empty">' + T('Matai tik tu (adminas). Vaikų lygis ir diržas imami iš tikrų XP ir diržo.') + '</p>';
  }
  function head() {
    var html = '<h2 class="dojo-title">🏯 ' + esc(ro ? (H.publicName || H.name) + ' · ' + T('Dojo') : T('Dojo')) + '</h2>';
    if ((G.heroes || []).length > 1) html += '<div class="dojo-heroes">' + G.heroes.map(function (h) { return '<button type="button" data-hero="' + h.id + '" class="' + (+h.id === +H.id ? 'active' : '') + '">' + esc(h.name) + '</button>'; }).join('') + '</div>';
    $('.dojo-head').innerHTML = html;
  }
  // Kolekcija: visi daiktai rinkiniais (turimi - spalvoti, neturimi - šešėliai), surinkta X iš Y
  var SETS = [['species', '✨ Personažai'], ['weapon', '⚔️ Ginklai'], ['kimono', '🥋 Kimono'], ['belt', '🎗 Diržo puošmenos'], ['hair', '💇 Šukuosenos'], ['head', '🎩 Galvos apdangalai'],
    ['face', '🥸 Veidui'], ['neck', '🏅 Kaklui'], ['hands', '🥊 Rankoms'], ['back', '🦸 Nugarai'], ['aura', '🌟 Auros'], ['paint', '🎨 Spalvos'], ['bg', '🌄 Aplinkos'], ['house', '🏠 Namai'], ['decor', '🏮 Dekoracijos'], ['pets', '🐾 Gyvūnėliai']];
  function renderCol() {
    var own = H.owned || {}, petsHave = {}, tot = 0, got = 0, html = '';
    var sets = SETS.filter(function (st) { return HIDDEN.indexOf(st[0]) < 0; });
    (H.pets || []).forEach(function (p) { petsHave[p.species] = 1; });
    sets.forEach(function (st) {
      var keys = st[0] === 'pets' ? Object.keys(C.pets) : Object.keys(C.items).filter(function (k) { return item(k).slot === st[0]; });
      var have = keys.filter(function (k) { return st[0] === 'pets' ? petsHave[k] : (own[k] || equippedKey(k)); });
      tot += keys.length; got += have.length;
      var done = have.length === keys.length;
      html += '<div class="col-set' + (done ? ' done' : '') + '"><div class="col-h"><b>' + esc(T(st[1])) + '</b><span>' + have.length + ' / ' + keys.length + (done ? ' ✓' : '') + '</span><i style="--p:' + Math.round(have.length / keys.length * 100) + '%"></i></div><div class="col-row">' +
        keys.map(function (k) {
          var h = have.indexOf(k) >= 0;
          if (st[0] === 'pets') return '<span class="col-it' + (h ? '' : ' no') + '" title="' + esc(C.pets[k].n) + '">' + A.pet(k) + '</span>';
          var it = item(k);
          return '<span class="col-it r' + it.r + (h ? '' : ' no') + '" title="' + esc(it.n + (h ? '' : ' · ' + (it.lvl ? T('Lygis') + ' ' + it.lvl : T('dovanų dėžėse')))) + '">' + A.itemIcon(k, it) + '</span>';
        }).join('') + '</div></div>';
    });
    $('#g-col').innerHTML = '<p class="g-empty">' + T('Rink daiktus iš lygių ir dovanų dėžių. Šešėliai - dar nerasti. Užbaigtas rinkinys pažymimas ✓.') + '</p>' + html;
    $('#g-col-cnt').textContent = got + ' / ' + tot;
  }
  function renderAll() { head(); world(); ui(); side(); renderEquip(); renderInv(); renderPets(); renderHome(); renderTest(); renderCol(); }

  function build() {
    root.classList.add('calm');   // v1.49: paprastesnis Dojo, kaip visa svetainė (šviesios kortelės)
    var pane = function (name, inner) { return '<section class="sc-pane" data-pane="' + name + '" hidden>' + inner + '</section>'; };
    root.innerHTML = '<div class="dojo-head"></div><div class="dw-frame"><i class="fr-c tl"></i><i class="fr-c tr"></i><i class="fr-c bl"></i><i class="fr-c br"></i><div class="dw-wrap"><div class="dw"></div><div class="dw-ui"></div>' +
      '<div class="dw-scroll" hidden><i class="sc-rod top"></i><div class="sc-paper"><button type="button" class="sc-x" data-act="pane-close" aria-label="' + esc(T('Uždaryti')) + '">×</button><h3 class="sc-title"></h3><div class="sc-body">' +
      pane('stats', '<div id="g-stats"></div>') +
      pane('equip', '<div id="g-equip"></div>' + (ro ? '' : '<div class="g-sub">' + T('Inventorius') + ' <span class="cnt" id="g-inv-cnt"></span></div><div id="g-inv"></div>')) +
      pane('pets', '<div id="g-pets"></div>') +
      (ro ? '' : pane('home', '<div id="g-home"></div>')) +
      pane('col', '<div id="g-col"></div>') +
      (G.admin && !ro ? pane('test', '<div id="g-test"></div>') : '') +
      '</div></div><i class="sc-rod bot"></i></div></div><div class="dw-foot"></div></div>';
    bindWorld();
    if (ro) { var noop = function () {}; renderInv = noop; renderHome = noop; }
    renderAll();
  }

  // ---------- Veiksmai ----------
  function save(data, msg) { return api({ action: 'save', data: data }).then(function (d) { if (d.ok) { renderAll(); if (msg) toast(msg); } return d; }); }
  function itemMenu(k) {
    var it = item(k), sl = it.slot, html = '<div class="box-item" style="animation:none">' + A.itemIcon(k, it) + '</div><h2>' + esc(it.n) + '</h2><p><span class="box-rar r' + it.r + '">' + esc(C.rarity[it.r]) + '</span> · ' + esc(C.slots[sl]) + '</p><p style="color:#2E8B57;font-weight:700">' + statTxt(it) + '</p><div class="gm-foot" style="justify-content:center;flex-wrap:wrap">';
    var on = equippedKey(k);
    if (SLOT_ORDER.indexOf(sl) >= 0) html += on && ['kimono', 'belt', 'hair'].indexOf(sl) < 0 ? '<button class="g-btn" data-do="unequip">' + T('Nusiimti') + '</button>' : (on ? '' : '<button class="g-btn primary" data-do="equip">' + T('Užsidėti') + '</button>');
    if (sl === 'bg' || sl === 'house') html += on ? '' : '<button class="g-btn primary" data-do="place">' + T('Naudoti') + '</button>';
    if (sl === 'decor') html += '<button class="g-btn primary" data-do="decor">+ ' + T('Padėti į dojo') + '</button>';
    if (sl === 'paint') html += '<button class="g-btn primary" data-do="paint">🎨 ' + T('Dažyti') + '</button>';
    if (sl === 'species') html += H.species === it.a.sp ? '<span class="g-empty">✓ ' + T('Tu jau esi šis personažas') + '</span>' : '<button class="g-btn gold" data-do="become">✨ ' + T('Tapti šiuo personažu') + '</button>';
    if (sl === 'egg') html += '<input type="text" id="egg-name" maxlength="30" placeholder="' + esc(T('Vardas')) + '" style="padding:8px 10px;border-radius:10px;border:1px solid rgba(23,20,15,.2);font:inherit"><button class="g-btn gold" data-do="hatch">🐣 ' + T('Išperinti') + '</button>';
    html += '<button class="g-btn" data-do="close">' + T('Uždaryti') + '</button></div>';
    var m = modal(html, 'sm');
    m.addEventListener('click', function (e) {
      var b = e.target.closest('[data-do]'); if (!b) return;
      var d = b.dataset.do, eq = {};
      if (d === 'close') return m.close();
      if (d === 'equip') { eq[sl] = k; save({ equipped: eq }); }
      if (d === 'unequip') { eq[sl] = ''; save({ equipped: eq }); }
      if (d === 'place') { var h = {}; h[sl] = k; save({ home: h }); }
      if (d === 'paint') { m.close(); return editor(it.a.c); }
      if (d === 'become') { save({ species: it.a.sp, look: { fur: C.species[it.a.sp].c } }, '✨ ' + T('Naujas personažas!')).then(confetti); }
      if (d === 'hatch') { api({ action: 'hatch', item: k, name: (m.querySelector('#egg-name') || {}).value || '' }).then(function (r) { if (r.ok) { renderAll(); confetti(); toast('🐣 ' + T('Naujas gyvūnėlis!')); } }); }
      m.close();
    });
  }
  function modal(html, cls) {
    var w = document.createElement('div'); w.className = 'gm';
    w.innerHTML = '<div class="gm-box ' + (cls || '') + '"><button type="button" class="gm-x" aria-label="×">×</button>' + html + '</div>';
    w.close = function () { w.remove(); document.removeEventListener('keydown', esc1); };
    var esc1 = function (e) { if (e.key === 'Escape') w.close(); };
    document.addEventListener('keydown', esc1);
    w.addEventListener('click', function (e) { if (e.target === w || e.target.classList.contains('gm-x')) w.close(); });
    document.body.appendChild(w);
    return w;
  }
  function confetti() {
    for (var i = 0; i < 40; i++) {
      var c = document.createElement('i'); c.className = 'confetti';
      c.style.left = (Math.random() * 100) + 'vw'; c.style.background = ['#F2C14E', '#C8372D', '#2E5FA8', '#2E8B57', '#A855F7'][i % 5];
      c.style.setProperty('--dx', (Math.random() * 200 - 100) + 'px'); c.style.animationDelay = (Math.random() * 0.5) + 's';
      document.body.appendChild(c); setTimeout(function (x) { return function () { x.remove(); }; }(c), 3000);
    }
  }
  var GIFT = '<svg class="box-gift" viewBox="0 0 120 120"><defs><linearGradient id="gbx" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#E5484D"/><stop offset="1" stop-color="#A51D24"/></linearGradient></defs><rect x="16" y="52" width="88" height="62" rx="6" fill="url(#gbx)"/><rect x="54" y="52" width="12" height="62" fill="#F2C14E"/><g class="lid"><rect x="10" y="36" width="100" height="20" rx="5" fill="#E5484D" stroke="#A51D24"/><rect x="54" y="36" width="12" height="20" fill="#F2C14E"/><path d="M60 36 C40 10 22 28 44 36 Z M60 36 C80 10 98 28 76 36 Z" fill="#F2C14E" stroke="#C99A1E"/></g></svg>';
  function openBoxes() {
    var boxes = H.boxes || [];
    if (!boxes.length) return toast(T('Dovanų dėžių neturi. Jas gauni pasiekęs naują lygį, po renginių ir už 3 pergales per dieną.'));
    var b = boxes[0];
    var m = modal('<h2>🎁 ' + esc(b.title || T('Dovana')) + '</h2><div class="box-stage">' + GIFT + '</div><p class="g-empty" id="box-hint">' + T('Paspausk dėžę!') + ' (' + boxes.length + ')</p><div class="gm-foot" style="justify-content:center" id="box-foot"></div>', 'sm');
    var g = m.querySelector('.box-gift'), busy = false;
    g.addEventListener('click', function () {
      if (busy) return; busy = true;
      g.classList.add('shake');
      var req = api({ action: 'box', id: b.id });
      setTimeout(function () {
        req.then(function (d) {
          if (!d.ok) return m.close();
          g.classList.add('open');
          var it = item(d.item);
          setTimeout(function () {
            m.querySelector('.box-stage').innerHTML = '<div class="box-rays"></div><div class="box-item">' + A.itemIcon(d.item, it) + '</div>';
            m.querySelector('#box-hint').innerHTML = '<b style="font-size:1.15rem;color:var(--ink)">' + esc(it.n) + '</b><br><span class="box-rar r' + it.r + '">' + esc(C.rarity[it.r]) + '</span> <span style="color:#2E8B57;font-weight:700">' + statTxt(it) + '</span>';
            var foot = m.querySelector('#box-foot');
            foot.innerHTML = (SLOT_ORDER.indexOf(it.slot) >= 0 ? '<button class="g-btn primary" data-b="eq">' + T('Užsidėti') + '</button>' : '') + ((H.boxes || []).length ? '<button class="g-btn gold" data-b="next">🎁 ' + T('Kita dėžė') + ' (' + H.boxes.length + ')</button>' : '') + '<button class="g-btn" data-b="ok">' + T('Puiku!') + '</button>';
            foot.onclick = function (e) {
              var x = e.target.closest('[data-b]'); if (!x) return;
              if (x.dataset.b === 'eq') { var eq = {}; eq[it.slot] = d.item; save({ equipped: eq }); }
              m.close();
              if (x.dataset.b === 'next') openBoxes();
            };
            if (it.r >= 3) confetti();
            renderAll();
          }, 450);
        });
      }, 700);
    });
  }

  // Išvaizdos redaktorius
  function editor(paintColor) {
    var d = { species: H.species, gender: H.gender, look: JSON.parse(JSON.stringify(H.look || {})), name: H.name, hair: H.equipped.hair };
    var own = H.owned || {}, paints = Object.keys(own).filter(function (k) { return item(k) && item(k).slot === 'paint'; }).map(function (k) { return item(k).a.c; });
    var hairs = Object.keys(own).filter(function (k) { return item(k) && item(k).slot === 'hair'; });
    var first = !(H.look && +H.look.setup);
    var m = modal('<h2>' + (first ? '👋 ' + T('Susikurk savo herojų!') : '✏️ ' + T('Išvaizda')) + '</h2><div class="ed"><div class="ed-prev live" id="ed-prev"></div><div id="ed-f"></div></div><div class="gm-foot"><button class="g-btn" data-e="cancel">' + T('Atšaukti') + '</button><button class="g-btn primary" data-e="save">✓ ' + T('Išsaugoti') + '</button></div>');
    function sw(field, list, extra) {
      var cur = d.look[field];
      return '<div class="ed-sw">' + list.map(function (c) { return '<button type="button" data-sw="' + field + '" data-c="' + c + '" style="background:' + c + '" class="' + (c === cur ? 'sel' : '') + '" title="' + c + '"></button>'; }).join('') +
        (extra || []).map(function (c) { return '<button type="button" data-sw="' + field + '" data-c="' + c + '" style="background:' + c + '" class="paint' + (c === cur ? ' sel' : '') + '" title="🎨"></button>'; }).join('') + '</div>';
    }
    function draw() {
      var eq = Object.assign({}, H.equipped, { hair: d.hair });
      m.querySelector('#ed-prev').innerHTML = A.hero({ species: d.species, gender: d.gender, look: d.look, equipped: eq, belt: H.belt, name: d.name });
      var isH = d.species === 'human', f = '';
      if (PIXEL) {
        var CH = C.chars || {};
        f += '<div class="ed-row"><span class="ed-lab">' + T('Kas tu?') + '</span><div class="ed-chars">' + Object.keys(CH).map(function (k) {
          return '<button type="button" data-sp="' + k + '" class="' + (k === d.species ? 'sel' : '') + '" title="' + esc(CH[k].n + (CH[k].s ? ' · ✨ ' + CH[k].s + ': ' + CH[k].sd : '')) + '">' + A.hero({ species: k, look: k === 'kitsune' ? d.look : {}, equipped: H.equipped, belt: H.belt }) + '<span>' + esc(CH[k].n) + '</span>' + (CH[k].s ? '<small class="ed-sk">✨ ' + esc(CH[k].s) + '</small>' : '') + '</button>';
        }).join('') + '</div></div>';
        if (d.species === 'kitsune') {
          f += '<div class="ed-row"><span class="ed-lab">' + T('Kailio spalva') + '</span>' + sw('fur', C.fur, paints) + '</div>';
          f += '<div class="ed-row"><span class="ed-lab">' + T('Liepsnų spalva') + '</span>' + sw('flame', C.flame || []) + '</div>';
        }
        f += '<div class="ed-row"><label for="ed-name">' + T('Herojaus vardas') + '</label><input type="text" id="ed-name" maxlength="24" value="' + esc(d.name) + '"></div>';
        m.querySelector('#ed-f').innerHTML = f;
        var selb = m.querySelector('.ed-chars .sel'); if (selb && selb.scrollIntoView) selb.scrollIntoView({ block: 'nearest' });
        return;
      }
      f += '<div class="ed-row"><span class="ed-lab">' + T('Kas tu?') + '</span><div class="ed-sp">' + Object.keys(C.species).map(function (k) {
        var sp = C.species[k], locked = sp.lock !== undefined && !own['sp_' + k];
        return '<button type="button" data-sp="' + k + '" class="' + (k === d.species ? 'sel' : '') + (sp.lock !== undefined ? ' myst r' + sp.r : '') + (locked ? ' locked' : '') + '"' + (locked ? ' disabled title="' + esc(sp.lock ? T('Atsirakins') + ' ' + T('Lygis') + ' ' + sp.lock : T('Retas - randamas dovanų dėžėse')) + '"' : '') + '><span>' + (locked ? '🔒' : sp.e) + '</span>' + esc(sp.n) + (locked ? '<em>' + (sp.lock ? '⭐ ' + sp.lock : '🎁 ' + T('retas')) + '</em>' : '') + '</button>';
      }).join('') + '</div></div>';
      f += '<div class="ed-row"><span class="ed-lab">' + T('Lytis') + '</span><div class="ed-seg"><button type="button" data-g="m" class="' + (d.gender === 'm' ? 'sel' : '') + '">♂ ' + T('Berniukas') + '</button><button type="button" data-g="f" class="' + (d.gender === 'f' ? 'sel' : '') + '">♀ ' + T('Mergaitė') + '</button></div></div>';
      f += isH ? '<div class="ed-row"><span class="ed-lab">' + T('Odos spalva') + '</span>' + sw('skin', C.skin) + '</div>' : '<div class="ed-row"><span class="ed-lab">' + T('Kailio spalva') + '</span>' + sw('fur', C.fur, paints) + '</div>';
      f += '<div class="ed-row"><span class="ed-lab">' + (isH ? T('Plaukai') : T('Kuokštas')) + '</span>' + sw('hair', C.hair, paints) + '</div>';
      if (hairs.length) f += '<div class="ed-row"><span class="ed-lab">' + T('Šukuosena') + '</span><div class="ed-seg" style="flex-wrap:wrap">' + hairs.map(function (k) { return '<button type="button" data-hair="' + k + '" class="' + (d.hair === k ? 'sel' : '') + '">' + esc(item(k).n) + '</button>'; }).join('') + '</div></div>';
      f += '<div class="ed-row"><span class="ed-lab">' + T('Akys') + '</span>' + sw('eyes', C.eyes) + '</div>';
      f += '<div class="ed-row"><label for="ed-name">' + T('Herojaus vardas') + '</label><input type="text" id="ed-name" maxlength="24" value="' + esc(d.name) + '"></div>';
      m.querySelector('#ed-f').innerHTML = f;
    }
    if (PIXEL) d.species = A.charKey(d.species);
    if (paintColor) { d.look[d.species === 'human' ? 'hair' : 'fur'] = paintColor; }
    draw();
    m.addEventListener('input', function (e) { if (e.target.id === 'ed-name') d.name = e.target.value; });
    m.addEventListener('click', function (e) {
      var t = e.target.closest('button'); if (!t) return;
      if (t.dataset.sp) { if (t.dataset.sp !== d.species) { d.species = t.dataset.sp; if (!PIXEL && t.dataset.sp !== 'human') d.look.fur = C.species[t.dataset.sp].c; } draw(); }
      else if (t.dataset.g) { d.gender = t.dataset.g; draw(); }
      else if (t.dataset.sw) { d.look[t.dataset.sw] = t.dataset.c; draw(); }
      else if (t.dataset.hair) { d.hair = t.dataset.hair; draw(); }
      else if (t.dataset.e === 'cancel') m.close();
      else if (t.dataset.e === 'save') {
        save({ species: d.species, gender: d.gender, look: d.look, name: d.name, equipped: { hair: d.hair } }, '✓ ' + T('Išsaugota')).then(function () { m.close(); });
      }
    });
  }

  root.addEventListener('click', function (e) {
    var t = e.target.closest('[data-act],[data-pane],[data-plus],[data-slot],[data-filt],[data-item],[data-feed],[data-active],[data-rename],[data-release],[data-starter],[data-bg],[data-house],[data-decor],[data-test],[data-hero],[data-ch]');
    if (!t || ro && !t.dataset.hero && !t.dataset.pane && t.dataset.act !== 'pane-close') return;
    var a = t.dataset.act;
    if (t.classList.contains('sc-pane')) return;   // paspaudimas pačiame ritinyje - ne mygtukas (#79)
    if (t.dataset.pane) return openPane(t.dataset.pane);
    if (a === 'pane-close') return closePane();
    if (a === 'boxes') return openBoxes();
    if (a === 'editor') return editor();
    if (a === 'alloc-save') { var al = {}; Object.keys(C.stats).forEach(function (k) { al[k] = (H.alloc[k] || 0) + (pendAlloc[k] || 0); }); pendAlloc = null; return save({ alloc: al }, '✓ ' + T('Taškai paskirstyti')); }
    if (a === 'adopt') { return api({ action: 'pet_adopt', species: starterPick, name: ($('#g-pet-name') || {}).value || '' }).then(function (d) { if (d.ok) { renderAll(); hearts($('.dw-pet') || $('.dw-hero')); toast('🐾 ' + T('Sveikas, naujas drauge!')); } }); }
    if (a === 'invites') return;
    if (t.dataset.plus) { pendAlloc = pendAlloc || { str: 0, agi: 0, int: 0, end: 0 }; pendAlloc[t.dataset.plus]++; return side(); }
    if (t.dataset.slot) { filt = t.dataset.slot; renderInv(); var inv = $('#g-inv'); if (inv) inv.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); return; }
    if (t.dataset.filt) { filt = t.dataset.filt; return renderInv(); }
    if (t.dataset.item) return itemMenu(t.dataset.item);
    if (t.dataset.feed) { return api({ action: 'pet_feed', id: t.dataset.feed }).then(function (d) { if (d.ok) { renderAll(); var el = root.querySelector('.dw-pet[data-pet="' + t.dataset.feed + '"]'); if (el) hearts(el); toast('🍖 ' + T('Skanu! Nuotaika pakilo.')); } }); }
    if (t.dataset.active) {
      var on = (H.pet && +H.pet.id === +t.dataset.active) || (H.pet2 && +H.pet2.id === +t.dataset.active);
      return save({ pet_toggle: +t.dataset.active }, on ? '🏠 ' + T('Gyvūnėlis liks namie') : '⚔️ ' + T('Gyvūnėlis eis į kovą su tavimi'));
    }
    if (t.dataset.release) { if (confirm(T('Paleisti šį gyvūnėlį?'))) api({ action: 'pet_release', id: t.dataset.release }).then(function () { renderAll(); }); return; }
    if (t.dataset.rename) { var p = (H.pets || []).filter(function (x) { return +x.id === +t.dataset.rename; })[0], nn = prompt(T('Naujas vardas'), p ? p.name : ''); if (nn) api({ action: 'pet_rename', id: t.dataset.rename, name: nn }).then(function () { renderAll(); }); return; }
    if (t.dataset.starter) { starterPick = t.dataset.starter; return renderPets(); }
    if (t.dataset.bg) return save({ home: { bg: t.dataset.bg } });
    if (t.dataset.house) return save({ home: { house: t.dataset.house } });
    if (t.dataset.hero) { location.href = G.heroUrl.replace('HID', t.dataset.hero); return; }
    if (t.dataset.test) {
      var x = t.dataset.test, q = { action: 'test' };
      if (x === 'level') q.level = $('#t-lvl').value;
      if (x === 'level-real') q.level = '';
      if (x === 'belt') q.belt = $('#t-belt').value;
      if (x === 'all') q.all_items = 1;
      if (x === 'boxes') q.boxes = 5;
      if (x === 'reset') { if (!confirm(T('Ištrinti visus daiktus, gyvūnėlius ir dėžes šiam herojui?'))) return; q.reset = 1; }
      return api(q).then(function (d) { if (d.ok) { renderAll(); toast('🧪 ' + T('Atnaujinta')); } });
    }
  });
  root.addEventListener('change', function (e) {
    if (e.target.dataset.act === 'invites') save({ invites: e.target.checked ? 1 : 0 }, e.target.checked ? T('Kiti gali tave kviesti žaisti') : T('Kvietimai išjungti'));
  });

  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !document.querySelector('.gm')) closePane(); });
  build();
  // Naujienos: naujas lygis, atsirakinę daiktai, dovanos
  if (!ro && G.news && G.news.length) {
    toast('⭐ ' + T('Atsirakino naujų daiktų:') + ' ' + G.news.slice(0, 4).map(function (k) { return item(k) ? item(k).n : k; }).join(', ') + (G.news.length > 4 ? '…' : ''), H.boxes && H.boxes.length ? ['🎁 ' + T('Atidaryti'), openBoxes] : null);
  } else if (!ro && H.boxes && H.boxes.length) {
    toast('🎁 ' + T('Tavęs laukia dovanų dėžės:') + ' ' + H.boxes.length, ['🎁 ' + T('Atidaryti'), openBoxes]);
  }
})();
