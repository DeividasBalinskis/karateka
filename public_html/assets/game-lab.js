/* 🧪 Nauja kova (bandymai), kova-bandymai.php.
   Kaip combats.ru: viduryje personažas ir gyvūnėlis, aplink daiktai. Rodyklėmis ◀ ▶ - variantai (išdėstymas + mechanika), kad išsirinktume.
   Bonusų rūšys atskirtos:  daiktai - kova (kiek atakų / gynybų, žala, šarvai),  stebuklingi daiktai - pasyvūs % (kritinis, išsisukimas, gydymas),
   gyvūnėlis - padeda su tikimybe kiekvieną ėjimą,  kortos - suveikia su tikimybe, sužiba ir parodo efektą.
   Kova ėjimais: 5 zonos; įprastai 1 smūgis ir 1 gynyba, skydas +1 gynyba, antras ginklas +1 smūgis, dvirankis ginklas - didesnė žala be kairės rankos.
   Ėjimas baigiasi, kai abu pasirinko (paspaudus „Smūgiuoti!“), arba kai baigiasi laikas (trūkstamos zonos parenkamos pačios).
   Kolekcija (kortos, išperėti gyvūnėliai, apranga) - šios naršyklės atmintyje. */
(function () {
  'use strict';
  var G = window.GAME || {}, A = window.GameArt || {}, root = document.getElementById('lab');
  if (!root || !A.hero) return;
  var IMG = ((G.catalog && G.catalog.scenes) || '/img/temos/').replace('temos/', 'zaidimas/');
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var rnd = function (a, b) { return a + Math.floor(Math.random() * (b - a + 1)); };
  var pick = function (arr) { return arr[Math.floor(Math.random() * arr.length)]; };
  var chance = function (p) { return Math.random() * 100 < p; };

  // ---------- Variantai (◀ ▶) ----------
  var VARIANTS = [
    { id: 'apranga', name: 'Apranga + gyvūnėlis', hands: true, armor: true, magic: 3, cards: 0, pets: 1,
      desc: 'Kaip combats: ginklas, kairė ranka (skydas arba antras ginklas), šarvai ir 3 stebuklingi daiktai. Gyvūnėlis šalia padeda kovoje.' },
    { id: 'kortos3', name: '3 kortos + gyvūnėlis', hands: true, armor: false, magic: 0, cards: 3, pets: 1,
      desc: 'Į kovą pasiimi 3 kortas. Kiekvieną ėjimą kiekviena gali suveikti - sužiba ir parodo, kas įvyko.' },
    { id: 'gyvunai3', name: '3 gyvūnėliai', hands: true, armor: false, magic: 0, cards: 0, pets: 3,
      desc: 'Pasiimi 3 gyvūnėlius - kiekvienas turi savo pagalbą. Jie auga kartu su tavimi.' },
    { id: 'kortos5', name: '5 kortos', hands: true, armor: false, magic: 0, cards: 5, pets: 0,
      desc: 'Daugiau kortų, bet be gyvūnėlio. Kortų rinkiniai (3 vienos stichijos) suveikia dažniau.' },
    { id: 'viskas', name: 'Viskas kartu', hands: true, armor: true, magic: 2, cards: 3, pets: 1,
      desc: 'Apranga, 2 stebuklingi daiktai, 3 kortos ir gyvūnėlis. Daugiausia derinių.' }
  ];

  // ---------- Daiktai: kovos struktūra (žala, kiek atakų / gynybų, šarvai) ----------
  var WEAPONS = {   // [pavadinimas, žala min, max, dvirankis]
    w_tonfa: ['Tonfos', 3, 6, 0], w_nunchaku: ['Nunčakai', 3, 7, 0], w_sai: ['Sai', 4, 7, 0], w_kama: ['Kama', 5, 8, 0], w_tessen: ['Tessenas', 3, 7, 0],
    w_sai_ice: ['Ledo sai', 5, 9, 0], w_nunchaku_flame: ['Liepsnos nunčakai', 5, 9, 0], w_kama_shadow: ['Šešėlių kama', 5, 10, 0],
    w_bo: ['Bo lazda', 6, 9, 1], w_jo: ['Jo lazda', 6, 9, 1], w_bokken: ['Bokenas', 6, 10, 1], w_bo_iron: ['Plieninė bo', 7, 11, 1],
    w_bokken_sakura: ['Sakuros bokenas', 7, 11, 1], w_katana_gold: ['Auksinė katana', 9, 13, 1], w_dragon_staff: ['Drakono lazda', 9, 14, 1]
  };
  var SHIELDS = { sh_wood: ['Medinis skydas', '🛡', 1], sh_iron: ['Geležinis skydas', '🛡', 3] };   // [pavadinimas, ikona, šarvai]
  var ARMORS = { ar_kimono: ['Baltas kimono', '🥋', 0, 0], ar_thick: ['Storas kimono', '🥋', 10, 1], ar_bamboo: ['Bambuko šarvai', '🎋', 15, 2], ar_do: ['Samurajaus šarvai', '⛩', 20, 3] };
  // ---------- Stebuklingi daiktai: pasyvūs procentai ir keistenybės ----------
  var MAGIC = {
    eye:   ['Trečioji akis', '👁', 'Kartais parodo, kur priešas smūgiuos (25 %)'],
    teddy: ['Meškiukas', '🧸', 'Kai lieka mažai gyvybių - apkabina: +15 (kartą)'],
    clover:['Keturlapis dobilas', '🍀', 'Kritinis smūgis +12 %'],
    feather:['Plunksna', '🪶', 'Išsisukti nuo smūgio +10 %'],
    onigiri:['Onigiris', '🍙', '+2 gyvybės kas ėjimą'],
    magnet:['Magnetas', '🧲', '10 % - smūgis prasimuša pro bloką (pusė žalos)'],
    bell:  ['Varpelis', '🔔', 'Pirmas gautas smūgis - perpus silpnesnis'],
    dice:  ['Kauliukas', '🎲', 'Kiekvienas smūgis +1-3 žalos'],
    duck:  ['Guminė antytė', '🦆', 'Priešas juokiasi: jo žala -1, išsisukti +5 %'],
    socks: ['Laimingos kojinės', '🧦', 'Kritinis +5 %, išsisukti +5 %']
  };
  // ---------- Gyvūnėliai: pagalba su tikimybe (auga su lygiu) ----------
  var PETS = {
    kitten: ['Kačiukas', 'Įdrėskia +4 žalos', 25], puppy: ['Šuniukas', 'Pagina dar vieną zoną', 20], foxcub: ['Lapiukas', 'Priešo smūgį nukreipia kitur', 18],
    bunny: ['Kiškutis', 'Padeda išsisukti nuo smūgio', 20], turtle: ['Vėžliukas', 'Žala perpus', 22], pandacub: ['Pandukas', 'Pagydo +6', 22],
    owlet: ['Pelėdžiukas', 'Parodo, kur priešas smūgiuos', 25], dragonling: ['Drakoniukas', 'Ugnis +7 žalos', 15], axolotl: ['Aksolotlis', 'Atsigauna +3', 30],
    phoenix: ['Feniksiukas', 'Prikelia vieną kartą (+15)', 100]
  };
  var EGG = ['kitten', 'kitten', 'puppy', 'puppy', 'foxcub', 'bunny', 'bunny', 'turtle', 'pandacub', 'owlet', 'dragonling', 'axolotl', 'phoenix'];
  var PET_COLORS = ['', '', '#F0B070', '#F4EEE6', '#8A929C', '#7A5BA6', '#4F86C6', '#3FA37A', '#E8892B'];
  // ---------- Kortos: suveikia su tikimybe, sužiba, parodo efektą ----------
  var CARDS = {   // [pavadinimas, ikona, efektas, tikimybė %, stichija, spalvos]
    kiai:    ['Kiai!', '⚡', 'Šio ėjimo žala ×2', 18, 'žaibas', ['#FFD43B', '#F59F00', '#7A4E00']],
    fort:    ['Tvirtovė', '🛡', 'Šį ėjimą apgintos visos zonos', 12, 'žemė', ['#74C0FC', '#1971C2', '#0B2E5C']],
    breath:  ['Kvėpavimas', '💚', '+10 gyvybių', 20, 'gamta', ['#8CE99A', '#2F9E44', '#0B3D1A']],
    trick:   ['Apgaulė', '🌀', 'Priešo blokas neveikia', 15, 'vėjas', ['#D0BFFF', '#7048E8', '#2B1B6B']],
    ice:     ['Ledas', '❄', 'Priešas sustingsta - kitą ėjimą nesmūgiuoja', 10, 'vanduo', ['#C5F6FA', '#15AABF', '#0B4F5C']],
    fire:    ['Ugnis', '🔥', '+5 žalos dabar ir +3 kitą ėjimą', 15, 'ugnis', ['#FFA8A8', '#F03E3E', '#5C0B0B']],
    vamp:    ['Šikšnosparnis', '🦇', 'Pavagia 6 gyvybes', 14, 'šešėlis', ['#B197FC', '#5F3DC4', '#1A1033']],
    mirror:  ['Veidrodis', '🪞', 'Grąžina pusę gautos žalos', 12, 'vanduo', ['#E9ECEF', '#868E96', '#212529']],
    lucky:   ['Laimės katinas', '🐈', 'Kitas smūgis - kritinis', 16, 'gamta', ['#FFE8CC', '#FD7E14', '#5C2B00']],
    thunder: ['Griaustinis', '🌩', 'Smūgis į visas zonas po 3', 12, 'žaibas', ['#FFF3BF', '#FAB005', '#4A3A00']]
  };
  var RARITY = [['Paprasta', 0, '#ADB5BD'], ['Reta', 3, '#4DABF7'], ['Epinė', 6, '#B197FC'], ['Legendinė', 10, '#FFD43B']];
  var ZONES = [['head', 'Galva', '🙂'], ['chest', 'Krūtinė', '👕'], ['belly', 'Pilvas', '🍙'], ['belt', 'Diržas', '🥋'], ['legs', 'Kojos', '🦵']];

  // ---------- Kolekcija (šios naršyklės atmintyje) ----------
  var S = load();
  function load() {
    var s = null;
    try { s = JSON.parse(localStorage.getItem(G.key || 'kova-lab') || 'null'); } catch (e) {}
    s = s || {};
    s.v = s.v || 0;
    s.level = s.level || (G.hero && G.hero.level) || 1;
    s.gear = s.gear || { w: 'w_tonfa', l: 'sh_wood', a: 'ar_kimono' };
    s.magic = s.magic || ['clover', 'teddy', 'duck'];
    s.pets = s.pets || [];          // išperėti: [{sp, color, born}]
    s.eggs = s.eggs == null ? 1 : s.eggs;   // pirmas kiaušinis - dovanų
    s.petSel = s.petSel || [0, 1, 2];
    s.cards = s.cards || [];
    if (!s.cards.length) for (var i = 0; i < 6; i++) s.cards.push(newCard(i < 4 ? 0 : 1));
    s.deck = s.deck || s.cards.slice(0, 5).map(function (c) { return c.id; });
    return s;
  }
  function save() { try { localStorage.setItem(G.key || 'kova-lab', JSON.stringify(S)); } catch (e) {} }
  function newCard(rar) {
    var keys = Object.keys(CARDS), r = rar != null ? rar : (chance(6) ? 3 : chance(16) ? 2 : chance(35) ? 1 : 0);
    return { id: 'c' + Date.now().toString(36) + Math.floor(Math.random() * 1e6).toString(36), t: pick(keys), r: r, seed: Math.floor(Math.random() * 1e9) };
  }
  var V = function () { return VARIANTS[S.v] || VARIANTS[0]; };
  function deck() { var n = V().cards; return S.deck.map(function (id) { return S.cards.find(function (c) { return c.id === id; }); }).filter(Boolean).slice(0, n); }
  function petsIn() { var n = V().pets; return S.petSel.map(function (i) { return S.pets[i]; }).filter(Boolean).slice(0, n); }
  function petScale(lvl) { return lvl >= 20 ? 1.15 : lvl >= 10 ? 0.95 : lvl >= 5 ? 0.75 : 0.55; }
  function petSize(lvl) { return lvl >= 20 ? 'didelis' : lvl >= 10 ? 'paaugęs' : lvl >= 5 ? 'vidutinis' : 'mažylis'; }

  // ---------- Pikselinis kortos piešinys (kiekviena korta - savas, atsitiktinis) ----------
  var artCache = {};
  function cardArt(c) {
    if (artCache[c.seed + c.t]) return artCache[c.seed + c.t];
    var W = 10, H = 12, x, y, s = c.seed, rand = function () { s = (s * 1103515245 + 12345) & 0x7fffffff; return s / 0x7fffffff; };
    var cv = document.createElement('canvas'); cv.width = W; cv.height = H;
    var ctx = cv.getContext('2d'), pal = CARDS[c.t][5];
    for (y = 0; y < H; y++) {
      for (x = 0; x < W / 2; x++) {
        var dx = Math.abs(x - W / 2 + 0.5), dy = Math.abs(y - H / 2 + 0.5), edge = dx / (W / 2) + dy / (H / 2) * 0.8;
        var v = rand();
        if (v > 0.38 + edge * 0.45) {
          ctx.fillStyle = v > 0.86 ? pal[0] : v > 0.62 ? pal[1] : pal[2];
          ctx.fillRect(x, y, 1, 1); ctx.fillRect(W - 1 - x, y, 1, 1);
        }
      }
    }
    // akys - kad būtų „padarėlis“
    if (rand() > 0.35) { ctx.fillStyle = '#fff'; var ey = 3 + Math.floor(rand() * 4); ctx.fillRect(2, ey, 2, 2); ctx.fillRect(W - 4, ey, 2, 2); ctx.fillStyle = '#111'; ctx.fillRect(3, ey + 1, 1, 1); ctx.fillRect(W - 4, ey + 1, 1, 1); }
    return (artCache[c.seed + c.t] = cv.toDataURL('image/png'));
  }
  function cardHtml(c, o) {
    o = o || {};
    var d = CARDS[c.t], r = RARITY[c.r];
    return '<div class="lc r' + c.r + (o.cls ? ' ' + o.cls : '') + '" data-card="' + c.id + '" style="--c1:' + d[5][0] + ';--c2:' + d[5][1] + ';--rc:' + r[2] + '" title="' + esc(d[0] + ' - ' + d[2] + ' (' + (d[3] + r[1]) + ' %)') + '">' +
      '<span class="lc-ch">' + (d[3] + r[1]) + '%</span><img class="lc-art" src="' + cardArt(c) + '" alt=""><span class="lc-ic">' + d[1] + '</span>' +
      '<b class="lc-n">' + esc(d[0]) + '</b><span class="lc-d">' + esc(d[2]) + '</span><span class="lc-r">' + esc(r[0]) + ' · ' + esc(d[4]) + '</span></div>';
  }
  function wImg(k) { return '<img class="px lab-wi" src="' + IMG + 'i-' + k + '.png" alt="">'; }
  function petImg(p, lvl, cls) { return '<span class="lab-pet ' + (cls || '') + '" style="--ps:' + petScale(lvl) + '">' + A.pet(p.sp, p.color || '') + '</span>'; }

  // ---------- Kovotojo savybės iš pasirinkimų ----------
  function fighter(src) {
    var v = src.v, lvl = src.level, w = WEAPONS[src.gear.w] || WEAPONS.w_tonfa, two = !!w[3];
    var left = two ? null : src.gear.l, sh = left && SHIELDS[left], w2 = left && WEAPONS[left] && !WEAPONS[left][3] ? WEAPONS[left] : null;
    var ar = v.armor ? (ARMORS[src.gear.a] || ARMORS.ar_kimono) : ARMORS.ar_kimono;
    var f = { name: src.name, lvl: lvl, hpMax: 60 + lvl * 4 + ar[2], arm: ar[3] + (sh ? sh[2] : 0) + Math.floor(lvl / 8),
      dmg: [w[1] + Math.floor(lvl / 3), w[2] + Math.floor(lvl / 3)], dmg2: w2 ? [w2[1] + Math.floor(lvl / 4), w2[2] + Math.floor(lvl / 4)] : null,
      attacks: 1 + (w2 ? 1 : 0), blocks: 1 + (sh ? 1 : 0), crit: 8, dodge: 5, magic: v.magic ? src.magic.slice(0, v.magic) : [], cards: src.cards || [], pets: src.pets || [],
      look: src.look, two: two, wName: w[0], lName: sh ? sh[0] : (w2 ? w2[0] : (two ? 'dvirankis ginklas' : 'tuščia')) };
    f.magic.forEach(function (m) { if (m === 'clover') f.crit += 12; if (m === 'feather') f.dodge += 10; if (m === 'socks') { f.crit += 5; f.dodge += 5; } if (m === 'duck') f.dodge += 5; });
    f.hp = f.hpMax;
    return f;
  }
  function mySrc() { return { v: V(), level: S.level, gear: S.gear, magic: S.magic, cards: deck(), pets: petsIn(), name: (G.hero && G.hero.publicName) || 'Tu', look: G.hero }; }
  function botSrc() {
    var v = V(), lvl = Math.max(1, S.level + rnd(-1, 1)), ws = Object.keys(WEAPONS), w = pick(ws), chars = Object.keys(A.chars ? A.chars() : {});
    var cards = []; for (var i = 0; i < v.cards; i++) cards.push(newCard());
    var pets = []; for (var j = 0; j < v.pets; j++) pets.push({ sp: pick(EGG.slice(0, 12)), color: pick(PET_COLORS) });
    return { v: v, level: lvl, gear: { w: w, l: pick(['sh_wood', 'sh_iron', 'w_sai', 'w_kama', 'w_tonfa', null]), a: pick(Object.keys(ARMORS)) },
      magic: shuffle(Object.keys(MAGIC)).slice(0, 3), cards: cards, pets: pets, name: pick(['Šešėlis', 'Audra', 'Tigras', 'Kometa', 'Ninja Nojus', 'Vėjas']),
      look: { species: chars.length ? pick(chars) : 'kitsune', look: {}, equipped: {}, belt: Math.min(9, 1 + Math.floor(lvl / 3)) } };
  }
  function shuffle(a) { a = a.slice(); for (var i = a.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)), t = a[i]; a[i] = a[j]; a[j] = t; } return a; }

  // ---------- Ekranai ----------
  var screen = 'prep', F = null, picker = null;
  function render() {
    var v = V();
    var top = '<div class="lab-top"><button type="button" class="lab-arr" data-v="-1" aria-label="Ankstesnis variantas">◀</button>' +
      '<div class="lab-vt"><span class="lab-vn">' + (S.v + 1) + ' / ' + VARIANTS.length + '</span><b>' + esc(v.name) + '</b><span class="lab-vd">' + esc(v.desc) + '</span>' +
      '<span class="lab-dots">' + VARIANTS.map(function (x, i) { return '<i class="' + (i === S.v ? 'on' : '') + '" data-vi="' + i + '"></i>'; }).join('') + '</span></div>' +
      '<button type="button" class="lab-arr" data-v="1" aria-label="Kitas variantas">▶</button></div>';
    root.innerHTML = top + (screen === 'fight' ? fightHtml() : prepHtml()) + (picker ? pickerHtml() : '');
    if (screen === 'fight') afterFightRender();
  }

  // ----- Pasiruošimas: viduryje personažas ir gyvūnėlis, aplink - daiktai -----
  function slot(kind, key, idx, label, inner, sub) {
    return '<button type="button" class="ls lsk-' + kind + (inner ? '' : ' empty') + '" data-slot="' + kind + '" data-i="' + (idx || 0) + '"><span class="ls-l">' + esc(label) + '</span>' +
      '<span class="ls-in">' + (inner || '+') + '</span>' + (sub ? '<span class="ls-s">' + esc(sub) + '</span>' : '') + '</button>';
  }
  function prepHtml() {
    var v = V(), f = fighter(mySrc()), w = WEAPONS[S.gear.w], two = w && w[3], left = '', right = '', bottom = '';
    left += slot('w', S.gear.w, 0, 'Ginklas', wImg(S.gear.w), w[0] + ' · ' + w[1] + '-' + w[2] + (two ? ' · dvirankis' : ''));
    var l = S.gear.l;
    left += two ? '<div class="ls lsk-l locked"><span class="ls-l">Kairė ranka</span><span class="ls-in">🔒</span><span class="ls-s">Dvirankis ginklas</span></div>'
      : slot('l', l, 0, 'Kairė ranka', l ? (SHIELDS[l] ? '<span class="ls-emo">' + SHIELDS[l][1] + '</span>' : wImg(l)) : '', l ? (SHIELDS[l] ? SHIELDS[l][0] + ' · +1 gynyba' : WEAPONS[l][0] + ' · +1 smūgis') : 'Skydas arba antras ginklas');
    if (v.armor) left += slot('a', S.gear.a, 0, 'Šarvai', '<span class="ls-emo">' + ARMORS[S.gear.a][1] + '</span>', ARMORS[S.gear.a][0] + (ARMORS[S.gear.a][2] ? ' · +' + ARMORS[S.gear.a][2] + ' ❤' : ''));
    for (var i = 0; i < v.magic; i++) { var m = MAGIC[S.magic[i]]; right += slot('m', S.magic[i], i, 'Stebuklingas daiktas', m ? '<span class="ls-emo">' + m[1] + '</span>' : '', m ? m[0] : 'tuščia'); }
    var pets = petsIn(), pc = '';
    if (v.pets) {
      if (!S.pets.length) pc = '<button type="button" class="lab-egg" data-hatch="1" title="Išperėti">🥚<span>Paspausk - išsiris gyvūnėlis!</span></button>';
      else pc = pets.map(function (p, k) { return '<button type="button" class="lab-petbtn" data-slot="p" data-i="' + k + '" title="' + esc(PETS[p.sp][0] + ' - ' + PETS[p.sp][1]) + '">' + petImg(p, S.level) + '</button>'; }).join('');
    }
    var mid = '<div class="lab-doll"><div class="lab-stage">' + A.hero(G.hero || {}, { cls: 'lab-hero' }) + '<div class="lab-pets n' + pets.length + '">' + pc + '</div></div>' +
      '<div class="lab-name">' + esc(f.name) + ' <span>Lygis</span> <input type="range" min="1" max="30" value="' + S.level + '" id="labLvl" aria-label="Lygis (bandymui)"><b id="labLvlN">' + S.level + '</b></div></div>';
    if (v.cards) {
      var d = deck();
      for (var c = 0; c < v.cards; c++) bottom += d[c] ? '<button type="button" class="lc-slot" data-slot="c" data-i="' + c + '">' + cardHtml(d[c]) + '</button>' : '<button type="button" class="lc-slot empty" data-slot="c" data-i="' + c + '">+ korta</button>';
    }
    if (v.pets > 1 || (v.pets && S.pets.length)) {
      bottom += '<div class="lab-petinfo">' + (pets.length ? pets.map(function (p) { var d2 = PETS[p.sp]; return '<span><b>' + esc(d2[0]) + '</b> (' + petSize(S.level) + ') - ' + esc(d2[1]) + ' · ' + Math.min(100, Math.round(d2[2] * (0.8 + petScale(S.level) * 0.4))) + ' %</span>'; }).join('') : '') +
        (v.pets > pets.length && S.pets.length ? '<span class="muted">Pasirink dar ' + (v.pets - pets.length) + ' gyvūnėlį (spausk ant jo vietos)</span>' : '') + '</div>';
    }
    var stats = '<div class="lab-stats">' +
      '<div><b>' + f.hpMax + '</b><span>❤ gyvybės</span></div><div><b>' + f.dmg[0] + '-' + f.dmg[1] + (f.dmg2 ? ' + ' + f.dmg2[0] + '-' + f.dmg2[1] : '') + '</b><span>⚔ žala</span></div>' +
      '<div><b>' + f.attacks + '</b><span>👊 smūgiai per ėjimą</span></div><div><b>' + f.blocks + '</b><span>🛡 gynybos zonos</span></div><div><b>' + f.arm + '</b><span>🪨 šarvai</span></div>' +
      '<div><b>' + f.crit + ' %</b><span>💥 kritinis</span></div><div><b>' + f.dodge + ' %</b><span>💨 išsisukti</span></div></div>' +
      '<div class="lab-kinds"><span><b>Daiktai</b> - kiek smūgių ir gynybų, žala, šarvai</span>' + (v.magic ? '<span><b>Stebuklingi</b> - pasyvūs %</span>' : '') +
      (v.pets ? '<span><b>Gyvūnėlis</b> - padeda su tikimybe</span>' : '') + (v.cards ? '<span><b>Kortos</b> - suveikia ir sužiba</span>' : '') + '</div>';
    var actions = '<div class="lab-acts">' + (v.pets ? '<button type="button" class="lab-btn ghost" data-hatch="1">🥚 Kiaušinis (' + S.eggs + ')</button>' : '') +
      (v.cards ? '<button type="button" class="lab-btn ghost" data-pack="1">🎁 Kortų paketas</button>' : '') +
      '<button type="button" class="lab-btn go" data-fight="1">⚔ Į kovą!</button></div>';
    return '<div class="lab-prep"><div class="lab-col l">' + left + '</div>' + mid + '<div class="lab-col r">' + right + stats + '</div></div>' +
      (bottom ? '<div class="lab-bottom">' + bottom + '</div>' : '') + actions;
  }

  // ----- Pasirinkimo langas (daiktai, kortos, gyvūnėliai) -----
  function pickerHtml() {
    var k = picker.kind, items = '';
    var opt = function (val, inner, name, sub, on) { return '<button type="button" class="lp-o' + (on ? ' on' : '') + '" data-val="' + esc(val) + '">' + inner + '<b>' + esc(name) + '</b>' + (sub ? '<span>' + esc(sub) + '</span>' : '') + '</button>'; };
    if (k === 'w') items = Object.keys(WEAPONS).map(function (w) { var x = WEAPONS[w]; return opt(w, wImg(w), x[0], x[1] + '-' + x[2] + ' žalos' + (x[3] ? ' · dvirankis' : ''), S.gear.w === w); }).join('');
    if (k === 'l') items = opt('', '<span class="ls-emo">✋</span>', 'Tuščia', '', !S.gear.l) + Object.keys(SHIELDS).map(function (s) { return opt(s, '<span class="ls-emo">' + SHIELDS[s][1] + '</span>', SHIELDS[s][0], '+1 gynybos zona · +' + SHIELDS[s][2] + ' šarvų', S.gear.l === s); }).join('') +
      Object.keys(WEAPONS).filter(function (w) { return !WEAPONS[w][3]; }).map(function (w) { return opt(w, wImg(w), WEAPONS[w][0], '+1 smūgis per ėjimą', S.gear.l === w); }).join('');
    if (k === 'a') items = Object.keys(ARMORS).map(function (a) { var x = ARMORS[a]; return opt(a, '<span class="ls-emo">' + x[1] + '</span>', x[0], '+' + x[2] + ' gyvybių · +' + x[3] + ' šarvų', S.gear.a === a); }).join('');
    if (k === 'm') items = Object.keys(MAGIC).map(function (m) { var x = MAGIC[m]; return opt(m, '<span class="ls-emo">' + x[1] + '</span>', x[0], x[2], S.magic[picker.i] === m); }).join('');
    if (k === 'c') items = S.cards.map(function (c) { return '<button type="button" class="lp-card' + (S.deck[picker.i] === c.id ? ' on' : '') + '" data-val="' + c.id + '">' + cardHtml(c) + '</button>'; }).join('');
    if (k === 'p') items = S.pets.map(function (p, i) { var d = PETS[p.sp]; return opt(String(i), petImg(p, S.level, 'big'), d[0], d[1] + ' · ' + d[2] + ' %', S.petSel[picker.i] === i); }).join('');
    var title = { w: 'Ginklas', l: 'Kairė ranka: skydas (+1 gynyba) arba antras ginklas (+1 smūgis)', a: 'Šarvai', m: 'Stebuklingas daiktas', c: 'Korta (' + S.cards.length + ' kolekcijoje)', p: 'Gyvūnėlis' }[k];
    return '<div class="lab-pick" role="dialog" aria-label="' + esc(title) + '"><div class="lp-box"><div class="lp-h"><b>' + esc(title) + '</b><button type="button" class="lp-x" aria-label="Uždaryti">×</button></div>' +
      '<div class="lp-grid' + (k === 'c' ? ' cards' : '') + '">' + items + '</div></div></div>';
  }
  function choose(val) {
    var k = picker.kind, i = picker.i;
    if (k === 'w') { S.gear.w = val; if (WEAPONS[val][3]) S.gear.l = null; }
    if (k === 'l') S.gear.l = val || null;
    if (k === 'a') S.gear.a = val;
    if (k === 'm') { var j = S.magic.indexOf(val); if (j > -1) S.magic[j] = S.magic[i]; S.magic[i] = val; }
    if (k === 'c') { var dj = S.deck.indexOf(val); if (dj > -1) S.deck[dj] = S.deck[i]; S.deck[i] = val; S.deck = S.deck.filter(Boolean); }
    if (k === 'p') { var n = +val, pj = S.petSel.indexOf(n); if (pj > -1) S.petSel[pj] = S.petSel[i]; S.petSel[i] = n; }
    picker = null; save(); render();
  }

  // ----- Kiaušinis: išsirita atsitiktinis gyvūnėlis (mažylis, auga su lygiu) -----
  function hatch() {
    if (S.eggs < 1) { S.eggs = 1; }   // bandymams - kiaušinių netrūksta
    var sp = pick(EGG), p = { sp: sp, color: pick(PET_COLORS), born: S.level };
    var ov = document.createElement('div'); ov.className = 'lab-hatch';
    ov.innerHTML = '<div class="lh-box"><div class="lh-egg">🥚</div><b class="lh-t">Kažkas juda…</b></div>';
    root.appendChild(ov);
    setTimeout(function () { ov.querySelector('.lh-egg').classList.add('crack'); ov.querySelector('.lh-t').textContent = 'Krak!'; }, 900);
    setTimeout(function () {
      ov.querySelector('.lh-box').innerHTML = '<div class="lh-pet">' + petImg(p, 1, 'big') + '</div><b class="lh-t">' + esc(PETS[sp][0]) + '!</b><span>' + esc(PETS[sp][1]) + '</span><span class="muted">Dabar mažylis - augs kartu su tavimi (5, 10, 20 lygis)</span><button type="button" class="lab-btn go">Super!</button>';
      ov.querySelector('button').addEventListener('click', function () { ov.remove(); render(); });
      S.pets.push(p); S.eggs = Math.max(0, S.eggs - 1) || 1; save();
    }, 1700);
  }
  function pack() {
    var got = [newCard(), newCard(), newCard()];
    S.cards = got.concat(S.cards).slice(0, 40); save();
    var ov = document.createElement('div'); ov.className = 'lab-hatch';
    ov.innerHTML = '<div class="lh-box wide"><b class="lh-t">🎁 Naujos kortos!</b><div class="lh-cards">' + got.map(function (c, i) { return '<div class="lh-flip" style="animation-delay:' + (i * 0.25) + 's">' + cardHtml(c) + '</div>'; }).join('') + '</div><button type="button" class="lab-btn go">Į kolekciją</button></div>';
    root.appendChild(ov);
    ov.querySelector('button').addEventListener('click', function () { ov.remove(); render(); });
  }

  // ---------- Kova ----------
  var TURN_SEC = 20, timer = null, tLeft = 0;
  function startFight() {
    F = { me: fighter(mySrc()), en: fighter(botSrc()), turn: 1, log: [], pick: { a: [], b: [] }, bot: null, botReady: false, last: null, over: false, peek: null };
    F.me.side = 'me'; F.en.side = 'en';
    screen = 'fight'; render(); nextTurn();
  }
  function zoneCol(f, res) {
    return '<div class="lz">' + ZONES.map(function (z, i) {
      var cls = '', mark = '';
      if (res) {
        if (res.blocked.indexOf(i) > -1) cls += ' blk';
        var h = res.hits.filter(function (x) { return x.z === i; });
        h.forEach(function (x) { cls += x.blocked ? ' parried' : x.dodged ? ' dodged' : ' hit'; mark += x.blocked ? '🛡' : x.dodged ? '💨' : '−' + x.d; });
      }
      return '<span class="lz-z' + cls + '" title="' + z[1] + '"><i>' + z[2] + '</i>' + (mark ? '<em>' + mark + '</em>' : '') + '</span>';
    }).join('') + '</div>';
  }
  function fighterHtml(f, res) {
    var pct = Math.max(0, Math.round(100 * f.hp / f.hpMax));
    return '<div class="lf lf-' + f.side + '" id="lf-' + f.side + '"><div class="lf-hp"><span style="width:' + pct + '%"></span><b>' + Math.max(0, f.hp) + ' / ' + f.hpMax + '</b></div>' +
      '<div class="lf-name">' + esc(f.name) + ' <small>Lv ' + f.lvl + ' · ' + f.attacks + '👊 ' + f.blocks + '🛡</small></div>' +
      '<div class="lf-body">' + (f.side === 'me' ? zoneCol(f, res) : '') + '<div class="lf-fig">' + A.hero(f.look || {}, { cls: 'lab-hero' }) +
      '<div class="lf-pets">' + f.pets.map(function (p, i) { return '<span class="lf-pet" data-pet="' + f.side + i + '">' + petImg(p, f.lvl) + '</span>'; }).join('') + '</div><div class="lf-fx" id="fx-' + f.side + '"></div></div>' +
      (f.side === 'en' ? zoneCol(f, res) : '') + '</div>' +
      (f.cards.length ? '<div class="lf-cards">' + f.cards.map(function (c) { return cardHtml(c, { cls: 'mini' }); }).join('') + '</div>' : '') +
      (f.magic.length ? '<div class="lf-magic">' + f.magic.map(function (m) { return '<span title="' + esc(MAGIC[m][0] + ': ' + MAGIC[m][2]) + '">' + MAGIC[m][1] + '</span>'; }).join('') + '</div>' : '') + '</div>';
  }
  function fightHtml() {
    var me = F.me, res = F.last;
    var need = 'Pasirink ' + me.attacks + ' ' + (me.attacks > 1 ? 'zonas smūgiui' : 'zoną smūgiui') + ' ir ' + me.blocks + ' ' + (me.blocks > 1 ? 'zonas gynybai' : 'zoną gynybai');
    var picks = '<div class="lk"><div class="lk-h"><span class="lk-turn">Ėjimas ' + F.turn + '</span><span class="lk-timer" id="lkT" style="--p:1">' + TURN_SEC + '</span><span class="lk-bot" id="lkBot">' + (F.botReady ? 'Priešas pasirinko ✓' : 'Priešas galvoja…') + '</span></div>' +
      (F.peek != null ? '<div class="lk-peek">👁 Priešas taikys į: <b>' + ZONES[F.peek][1] + '</b></div>' : '') +
      '<div class="lk-cols"><div><b>👊 Smūgis į <em id="lkA">' + F.pick.a.length + '/' + me.attacks + '</em></b>' + ZONES.map(function (z, i) { return '<button type="button" class="lk-z a' + (F.pick.a.indexOf(i) > -1 ? ' on' : '') + '" data-a="' + i + '">' + z[2] + ' ' + z[1] + '</button>'; }).join('') + '</div>' +
      '<div><b>🛡 Ginu <em id="lkB">' + F.pick.b.length + '/' + me.blocks + '</em></b>' + ZONES.map(function (z, i) { return '<button type="button" class="lk-z b' + (F.pick.b.indexOf(i) > -1 ? ' on' : '') + '" data-b="' + i + '">' + z[2] + ' ' + z[1] + '</button>'; }).join('') + '</div></div>' +
      '<button type="button" class="lab-btn go lk-go" id="lkGo"' + (F.pick.a.length === me.attacks && F.pick.b.length === me.blocks ? '' : ' disabled') + '>Smūgiuoti!</button><span class="lk-need">' + need + '</span></div>';
    if (F.over) picks = '<div class="lk lk-end"><b class="lk-win">' + (F.me.hp > 0 ? '🏆 Pergalė!' : '😵 Pralaimėjai') + '</b><button type="button" class="lab-btn go" data-fight="1">⚔ Dar kartą</button><button type="button" class="lab-btn ghost" data-prep="1">← Pasiruošimas</button></div>';
    return '<div class="lab-fight">' + fighterHtml(F.me, res && res.toMe) + picks + fighterHtml(F.en, res && res.toEn) + '</div>' +
      '<ol class="lab-log" id="labLog">' + F.log.slice(-14).reverse().map(function (l) { return '<li class="' + (l.c || '') + '">' + l.t + '</li>'; }).join('') + '</ol>';
  }
  function afterFightRender() {
    if (F.fx) { var fx = F.fx; F.fx = null; playFx(fx); }
  }
  function nextTurn() {
    clearInterval(timer);
    if (F.over) return;
    F.pick = { a: [], b: [] }; F.botReady = false; F.peek = null;
    // Pelėdžiukas / Trečioji akis - parodo, kur priešas smūgiuos
    F.bot = botPick(F.en, F.me);
    if (F.me.pets.some(function (p) { return p.sp === 'owlet'; }) && chance(25) || F.me.magic.indexOf('eye') > -1 && chance(25)) F.peek = F.bot.a[0];
    tLeft = TURN_SEC; render();
    setTimeout(function () { if (F && !F.over) { F.botReady = true; var b = document.getElementById('lkBot'); if (b) b.textContent = 'Priešas pasirinko ✓'; } }, rnd(600, 2600));
    timer = setInterval(function () {
      tLeft--;
      var t = document.getElementById('lkT'); if (t) { t.textContent = tLeft; t.style.setProperty('--p', tLeft / TURN_SEC); t.classList.toggle('low', tLeft <= 5); }
      if (tLeft <= 0) { autoFill(F.pick, F.me); resolve(); }
    }, 1000);
  }
  function botPick(f, foe) {
    var z = shuffle([0, 1, 2, 3, 4]), a = z.slice(0, f.attacks), b = shuffle([0, 1, 2, 3, 4]).slice(0, f.blocks);
    return { a: a, b: b };
  }
  function autoFill(p, f) {
    var free = shuffle([0, 1, 2, 3, 4]);
    while (p.a.length < f.attacks) { var x = free.find(function (i) { return p.a.indexOf(i) < 0; }); p.a.push(x); }
    free = shuffle([0, 1, 2, 3, 4]);
    while (p.b.length < f.blocks) { var y = free.find(function (i) { return p.b.indexOf(i) < 0; }); p.b.push(y); }
  }
  // Vieno kovotojo ėjimo efektai prieš smūgius: kortos, gyvūnėliai
  function triggers(f, foe, st) {
    var ev = [], fired = [];
    f.cards.forEach(function (c) {
      var d = CARDS[c.t], set = f.cards.filter(function (x) { return CARDS[x.t][4] === d[4]; }).length >= 3 ? 5 : 0;
      if (!chance(d[3] + RARITY[c.r][1] + set)) return;
      fired.push(c); ev.push({ who: f.side, card: c.id, t: d[1] + ' ' + d[0] + '!', sub: d[2] });
      if (c.t === 'kiai') st.mult *= 2;
      if (c.t === 'fort') st.blockAll = true;
      if (c.t === 'breath') st.heal += 10;
      if (c.t === 'trick') st.pierce = true;
      if (c.t === 'ice') foe.frozen = 1;
      if (c.t === 'fire') { st.bonus += 5; f.burnNext = 3; }
      if (c.t === 'vamp') { st.steal += 6; }
      if (c.t === 'mirror') st.mirror = true;
      if (c.t === 'lucky') f.nextCrit = true;
      if (c.t === 'thunder') st.thunder = true;
    });
    if (fired.length >= 2) { st.bonus += 4; ev.push({ who: f.side, t: '✨ KOMBO ×' + fired.length + '!', sub: '+4 žalos', combo: true }); }
    f.pets.forEach(function (p, i) {
      var d = PETS[p.sp], ch = Math.min(100, d[2] * (0.8 + petScale(f.lvl) * 0.4));
      if (p.sp === 'phoenix' || p.sp === 'owlet' || !chance(ch)) return;
      ev.push({ who: f.side, pet: f.side + i, t: d[0] + ': ' + d[1].toLowerCase() + '!' });
      if (p.sp === 'kitten') st.bonus += 4;
      if (p.sp === 'dragonling') st.bonus += 7;
      if (p.sp === 'puppy') st.extraBlock = true;
      if (p.sp === 'foxcub') st.redirect = true;
      if (p.sp === 'bunny') st.dodgeOne = true;
      if (p.sp === 'turtle') st.half = true;
      if (p.sp === 'pandacub') st.heal += 6;
      if (p.sp === 'axolotl') st.heal += 3;
    });
    if (f.burnNow) { st.bonus += f.burnNow; ev.push({ who: f.side, t: '🔥 Dar dega: +' + f.burnNow }); }
    f.burnNow = f.burnNext || 0; f.burnNext = 0;
    return ev;
  }
  function strike(att, def, atkPick, defPick, sa, sd, out) {
    if (att.frozen) { att.frozen = 0; out.ev.push({ who: att.side, t: '❄ ' + att.name + ' sustingo - nesmūgiuoja' }); return; }
    var blocks = defPick.b.slice();
    if (sd.extraBlock) { var free = [0, 1, 2, 3, 4].filter(function (z) { return blocks.indexOf(z) < 0; }); if (free.length) blocks.push(pick(free)); }
    out.blocked = sd.blockAll ? [0, 1, 2, 3, 4] : blocks;
    var zones = sa.thunder ? [0, 1, 2, 3, 4] : atkPick.a.slice();
    if (sd.redirect && zones.length) { zones[0] = rnd(0, 4); }
    zones.forEach(function (z, k) {
      var dm = sa.thunder && atkPick.a.indexOf(z) < 0 ? [3, 3] : (k === 1 && att.dmg2 ? att.dmg2 : att.dmg), d = rnd(dm[0], dm[1]);
      var isBlocked = (sd.blockAll || blocks.indexOf(z) > -1) && !sa.pierce;
      if (isBlocked && att.magic.indexOf('magnet') > -1 && chance(10)) { isBlocked = false; d = Math.ceil(d / 2); out.ev.push({ who: att.side, t: '🧲 Prasimušė pro bloką!' }); }
      if (isBlocked) { out.hits.push({ z: z, blocked: true, d: 0 }); return; }
      if (sd.dodgeOne || chance(def.dodge)) { sd.dodgeOne = false; out.hits.push({ z: z, dodged: true, d: 0 }); return; }
      var crit = att.nextCrit || chance(att.crit); att.nextCrit = false;
      if (k === 0) d += sa.bonus;
      if (att.magic.indexOf('dice') > -1) d += rnd(1, 3);
      if (def.magic.indexOf('duck') > -1) d -= 1;
      d = Math.round(d * sa.mult * (crit ? 1.5 : 1));
      d = Math.max(1, d - def.arm);
      if (sd.half) d = Math.ceil(d / 2);
      if (def.magic.indexOf('bell') > -1 && !def.bellUsed) { def.bellUsed = true; d = Math.ceil(d / 2); out.ev.push({ who: def.side, t: '🔔 Varpelis sušvelnino smūgį' }); }
      out.hits.push({ z: z, d: d, crit: crit });
      out.dmg += d;
    });
  }
  function resolve() {
    clearInterval(timer);
    if (!F || F.over) return;
    autoFill(F.pick, F.me);
    var me = F.me, en = F.en, mk = function () { return { mult: 1, bonus: 0, heal: 0, steal: 0 }; };
    var sMe = mk(), sEn = mk(), evMe = triggers(me, en, sMe), evEn = triggers(en, me, sEn);
    var toEn = { hits: [], blocked: [], dmg: 0, ev: [] }, toMe = { hits: [], blocked: [], dmg: 0, ev: [] };
    strike(me, en, F.pick, F.bot, sMe, sEn, toEn);
    strike(en, me, F.bot, F.pick, sEn, sMe, toMe);
    // Veidrodis: pusė gautos žalos grįžta
    if (sMe.mirror && toMe.dmg) toEn.dmg += Math.ceil(toMe.dmg / 2);
    if (sEn.mirror && toEn.dmg) toMe.dmg += Math.ceil(toEn.dmg / 2);
    en.hp -= toEn.dmg + sMe.steal; me.hp -= toMe.dmg + sEn.steal;
    me.hp = Math.min(me.hpMax, me.hp + sMe.heal + sMe.steal); en.hp = Math.min(en.hpMax, en.hp + sEn.heal + sEn.steal);
    [me, en].forEach(function (f) {
      if (f.magic.indexOf('onigiri') > -1 && f.hp > 0) f.hp = Math.min(f.hpMax, f.hp + 2);
      if (f.magic.indexOf('teddy') > -1 && !f.teddy && f.hp > 0 && f.hp < f.hpMax * 0.3) { f.teddy = true; f.hp += 15; (f === me ? evMe : evEn).push({ who: f.side, t: '🧸 Meškiukas apkabino: +15' }); }
      if (f.hp <= 0 && !f.revived && f.pets.some(function (p) { return p.sp === 'phoenix'; })) { f.revived = true; f.hp = 15; (f === me ? evMe : evEn).push({ who: f.side, t: '🔥 Feniksiukas prikėlė!' }); }
    });
    var line = function (att, res) {
      return res.hits.map(function (h) { return '<b>' + esc(att.name) + '</b> → ' + ZONES[h.z][1].toLowerCase() + ': ' + (h.blocked ? '<span class="blk">🛡 apginta</span>' : h.dodged ? '<span class="ddg">💨 išsisuko</span>' : '<span class="hit">−' + h.d + (h.crit ? ' 💥 kritinis' : '') + '</span>'); }).join(' · ');
    };
    F.log.push({ t: 'Ėjimas ' + F.turn + ': ' + (line(me, toEn) || esc(me.name) + ' nesmūgiavo'), c: 'me' });
    F.log.push({ t: 'Ėjimas ' + F.turn + ': ' + (line(en, toMe) || esc(en.name) + ' nesmūgiavo'), c: 'en' });
    evMe.concat(evEn, toEn.ev, toMe.ev).forEach(function (e) { F.log.push({ t: esc(e.t) + (e.sub ? ' <small>' + esc(e.sub) + '</small>' : ''), c: 'fx ' + e.who }); });
    F.last = { toEn: toEn, toMe: toMe };
    F.fx = { ev: evMe.concat(evEn, toEn.ev, toMe.ev), toEn: toEn, toMe: toMe };
    if (me.hp <= 0 || en.hp <= 0 || F.turn >= 40) F.over = true;
    F.turn++;
    render();
    if (!F.over) setTimeout(function () { if (screen === 'fight') nextTurn(); }, 2600);
  }
  // Matoma: pataikė (raudona, −N), apginta (mėlynas skydas), išsisuko; kortos sužiba, gyvūnėlis pašoka, užrašas
  function playFx(fx) {
    var show = function (side, res) {
      var box = document.getElementById('fx-' + side), fig = document.getElementById('lf-' + side); if (!box) return;
      res.hits.forEach(function (h, i) {
        var s = document.createElement('span');
        s.className = 'lfx ' + (h.blocked ? 'blk' : h.dodged ? 'ddg' : 'hit') + (h.crit ? ' crit' : '');
        s.textContent = h.blocked ? '🛡 APGINTA' : h.dodged ? '💨 IŠSISUKO' : '−' + h.d + (h.crit ? '!' : '');
        s.style.animationDelay = (i * 0.25) + 's'; s.style.top = (12 + h.z * 15) + '%';
        box.appendChild(s);
      });
      if (res.dmg > 0 && fig) { fig.classList.remove('shake'); void fig.offsetWidth; fig.classList.add('shake'); }
      if (res.hits.some(function (h) { return h.blocked; }) && fig) fig.classList.add('shielded');
    };
    show('en', fx.toEn); show('me', fx.toMe);
    fx.ev.forEach(function (e, i) {
      setTimeout(function () {
        if (e.card) { var c = root.querySelector('#lf-' + e.who + ' [data-card="' + e.card + '"]'); if (c) { c.classList.add('glow'); setTimeout(function () { c.classList.remove('glow'); }, 1500); } }
        if (e.pet) { var p = root.querySelector('[data-pet="' + e.pet + '"]'); if (p) { p.classList.add('jump'); setTimeout(function () { p.classList.remove('jump'); }, 900); } }
        var box = document.getElementById('fx-' + e.who); if (!box) return;
        var b = document.createElement('div'); b.className = 'lfx-banner' + (e.combo ? ' combo' : ''); b.innerHTML = esc(e.t) + (e.sub ? '<small>' + esc(e.sub) + '</small>' : '');
        box.appendChild(b); setTimeout(function () { b.remove(); }, 1700);
      }, 200 + i * 450);
    });
  }

  // ---------- Paspaudimai ----------
  root.addEventListener('click', function (e) {
    var t = e.target.closest('button, [data-vi]'); if (!t) return;
    if (t.dataset.v) { S.v = (S.v + (+t.dataset.v) + VARIANTS.length) % VARIANTS.length; save(); clearInterval(timer); screen = 'prep'; F = null; render(); return; }
    if (t.dataset.vi) { S.v = +t.dataset.vi; save(); clearInterval(timer); screen = 'prep'; F = null; render(); return; }
    if (t.classList.contains('lp-x') || (t.closest('.lab-pick') && !t.closest('.lp-box'))) { picker = null; render(); return; }
    if (t.dataset.val != null && picker) { choose(t.dataset.val); return; }
    if (t.dataset.slot) {
      if (t.dataset.slot === 'p' && !S.pets.length) { hatch(); return; }
      if (t.dataset.slot === 'c' && !S.cards.length) { pack(); return; }
      picker = { kind: t.dataset.slot, i: +t.dataset.i }; render(); return;
    }
    if (t.dataset.hatch) { hatch(); return; }
    if (t.dataset.pack) { pack(); return; }
    if (t.dataset.fight) { startFight(); return; }
    if (t.dataset.prep) { clearInterval(timer); screen = 'prep'; F = null; render(); return; }
    if (F && !F.over && (t.dataset.a != null || t.dataset.b != null)) {
      var key = t.dataset.a != null ? 'a' : 'b', z = +(t.dataset.a != null ? t.dataset.a : t.dataset.b), lim = key === 'a' ? F.me.attacks : F.me.blocks, arr = F.pick[key];
      var j = arr.indexOf(z);
      if (j > -1) arr.splice(j, 1); else { if (arr.length >= lim) arr.shift(); arr.push(z); }
      root.querySelectorAll('.lk-z.' + key).forEach(function (b) { b.classList.toggle('on', arr.indexOf(+(b.dataset[key])) > -1); });
      document.getElementById('lk' + key.toUpperCase()).textContent = arr.length + '/' + lim;
      document.getElementById('lkGo').disabled = !(F.pick.a.length === F.me.attacks && F.pick.b.length === F.me.blocks);
      return;
    }
    if (t.id === 'lkGo' && F) {
      // Abu pasirinko - ėjimas iškart; jei priešas dar galvoja - palaukiama jo
      t.disabled = true; t.textContent = F.botReady ? 'Smūgis!' : 'Laukiam priešo…';
      var go = function () { if (F && !F.over) { if (F.botReady) resolve(); else setTimeout(go, 250); } };
      go();
    }
  });
  root.addEventListener('input', function (e) {
    if (e.target.id === 'labLvl') { S.level = +e.target.value; save(); render(); var r = document.getElementById('labLvl'); if (r) r.focus(); }
  });
  render();
})();
