/* Pikselinis žaidimo menas: personažas - lapė (kitsunė) iš assets/game-px-data.js, perdažoma pagal žaidėją:
   kailis (išvaizdos spalva), kimono (užsidėtas kimono), diržas (tikras diržas), liepsnos; gyvūnėliai - pūkų gumulėliai.
   Pakeičia GameArt.hero / GameArt.pet / GameArt.sceneUrl - visur žaidime rodomi pikseliai, ne apvalūs SVG. */
(function () {
  'use strict';
  var PX = window.GAME_PX, A = window.GameArt;
  if (!PX || !A) return;
  var cache = {}, NAVY = [44, 46, 86], LAV = [150, 132, 190];

  function rgb(h) { h = String(h || '#888888').replace('#', ''); if (h.length === 3) h = h.replace(/./g, '$&$&'); var n = parseInt(h, 16); return [n >> 16 & 255, n >> 8 & 255, n & 255]; }
  function mix(a, b, t) { return [a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t, a[2] + (b[2] - a[2]) * t]; }
  function lighten(c, t) { return mix(c, [255, 255, 255], t); }
  function darken(c, t) { return mix(c, [0, 0, 0], t); }
  function lum(c) { return (0.3 * c[0] + 0.59 * c[1] + 0.11 * c[2]) / 255; }
  function sat(c) { var mx = Math.max(c[0], c[1], c[2]), mn = Math.min(c[0], c[1], c[2]); return mx ? (mx - mn) / mx : 0; }
  function bytes(s) { if (!s._b) { var b = atob(s.data), a = new Uint8Array(b.length); for (var i = 0; i < b.length; i++) a[i] = b.charCodeAt(i); s._b = a; } return s._b; }

  /** Nupiešia indeksuotą paveikslą su duota palete, grąžina data: adresą (su podėliu). box [x0,y0,x1,y1] - tik iškarpa. */
  function paint(s, colors, key, box) {
    if (cache[key]) return cache[key];
    var c = document.createElement('canvas'); c.width = s.w; c.height = s.h;
    var ctx = c.getContext('2d'), im = ctx.createImageData(s.w, s.h), d = im.data, b = bytes(s);
    for (var i = 0; i < b.length; i++) {
      var col = colors[b[i]];
      if (col) { d[i * 4] = col[0]; d[i * 4 + 1] = col[1]; d[i * 4 + 2] = col[2]; d[i * 4 + 3] = 255; }
    }
    ctx.putImageData(im, 0, 0);
    if (box) {
      var c2 = document.createElement('canvas'); c2.width = box[2] - box[0]; c2.height = box[3] - box[1];
      c2.getContext('2d').drawImage(c, -box[0], -box[1]); c = c2;
    }
    return (cache[key] = c.toDataURL('image/png'));
  }

  // ---------- Lapė ----------
  var FOX = PX.fox, RANGE = {};
  FOX.pal.forEach(function (p) {
    if (!p) return;
    var l = lum(p.slice(2)), r = RANGE[p[0]] || (RANGE[p[0]] = [l, l]);
    r[0] = Math.min(r[0], l); r[1] = Math.max(r[1], l);
  });
  /** o: {fur, flame, kimono, trim, pattern, belt} - spalvos (#hex); ko nėra - originalo spalvos */
  function foxColors(o) {
    var fur = o.fur ? rgb(o.fur) : null;
    if (fur && lum(fur) > 0.86 && sat(fur) < 0.12) fur = null;            // balta - originalus kailis su alyviniais šešėliais
    var acc = o.flame ? rgb(o.flame) : null;
    var K = rgb(o.kimono || '#F7F5EF'), tr = rgb(o.trim || '#E4DFD3'), B = o.belt ? rgb(o.belt) : null;
    var lightK = lum(K) > 0.75, kTone = function (t) { return mix(lighten(K, 0.3), lightK ? mix(K, LAV, 0.62) : darken(K, 0.52), t); };
    return FOX.pal.map(function (p) {
      if (!p) return null;
      var reg = p[0], tone = p[1], orig = p.slice(2), r = RANGE[reg], t = r && r[1] > r[0] ? 1 - (lum(orig) - r[0]) / (r[1] - r[0]) : 0;   // 0 - šviesu, 1 - tamsu
      switch (reg) {
        case 'fur': return fur ? mix(mix(lighten(fur, 0.24), darken(fur, 0.46), t), LAV, t * 0.14) : orig;
        case 'fline': return fur ? mix(darken(fur, 0.55), NAVY, 0.3) : orig;
        case 'pink': return fur && lum(fur) < 0.4 ? mix(orig, darken(fur, 0.2), 0.35) : orig;
        case 'acc': return acc ? mix(lighten(acc, 0.82), darken(acc, 0.3), Math.pow(t, 1.1)) : orig;
        case 'aline': return acc ? darken(acc, 0.56) : orig;
        case 'kim': return kTone(tone / 4);
        case 'kline': return lightK ? mix(K, NAVY, 0.62) : darken(K, 0.68);
        case 'petal': return o.pattern ? (lum(tr) > 0.5 ? tr : lighten(tr, 0.45)) : kTone(0.25);
        case 'hem': return lum(tr) > 0.82 && lightK ? mix(K, LAV, 0.35) : tr;
        case 'obi': return B ? [lighten(B, 0.2), B, darken(B, 0.3)][tone] : orig;
        case 'oline': return B ? (lum(B) < 0.2 ? lighten(B, 0.28) : darken(B, 0.62)) : orig;
        default: return orig;
      }
    });
  }
  function beltHex(lvl) {
    var Bt = (window.GAME && GAME.catalog && GAME.catalog.belts) || {}, b = Bt[lvl] || Bt[String(lvl)];
    return b ? b[2] : null;
  }
  // Seni (apvalūs) personažai → artimiausias naujas pikselinis
  var OLD = { tiger: 'scat', wtiger: 'scat', lion: 'scat', panda: 'turtle', raven: 'crane', fox: 'kitsune', wolf: 'swolf', cat: 'scat', rabbit: 'weasel', monkey: 'frog', eagle: 'crane', dragon: 'gdragon',
    human: 'ghost', redpanda: 'kitsune', yeti: 'ram', tengu: 'crane', unicorn: 'deer', phoenixbird: 'phoenix' };   // #115: be pandos, baltojo tigro ir varno
  function chars() { return (window.GAME && GAME.catalog && GAME.catalog.chars) || {}; }
  function charKey(sp) { var C2 = chars(); if (C2[sp]) return sp; sp = OLD[sp] || sp; return C2[sp] ? sp : 'kitsune'; }
  /** Į kurią pusę žiūri personažas: 'r', 'l' arba 'c' */
  function face(sp) { var c = chars()[charKey(sp)]; return c ? c.f : 'l'; }
  /** Personažas: cfg {species, look:{fur,flame}, equipped:{kimono}, belt}; o: {cls, items} */
  function hero(cfg, o) {
    o = o || {}; cfg = cfg || {};
    var key = charKey(cfg.species);
    if (key !== 'kitsune') {
      var img = ((window.GAME && GAME.catalog && GAME.catalog.charImg) || '/img/zaidimas/herojai/') + key + '.webp';
      return '<img class="px-char px-hero ga-hero' + (o.cls ? ' ' + o.cls : '') + '" data-face="' + face(key) + '" src="' + img + '" alt="" draggable="false">';
    }
    var CAT = o.items || (window.GAME && GAME.catalog && GAME.catalog.items) || {}, L = cfg.look || {}, E = cfg.equipped || {};
    var kim = (E.kimono && CAT[E.kimono] && CAT[E.kimono].a) || { c: '#F7F5EF', tr: '#E4DFD3' };
    var SPC = (window.GAME && GAME.catalog && GAME.catalog.species) || {};
    var c = { fur: o.ghost ? '#C9CDD3' : (L.fur || (SPC[cfg.species] && SPC[cfg.species].c) || ''), flame: o.ghost ? '#AAB2C0' : (L.flame || ''), kimono: kim.c, trim: kim.tr, pattern: kim.p || '', belt: beltHex(cfg.belt) || '' };
    key = 'fox|' + [c.fur, c.flame, c.kimono, c.trim, c.pattern, c.belt].join('|');
    return '<img class="px px-hero ga-hero' + (o.cls ? ' ' + o.cls : '') + '" data-face="l" src="' + paint(FOX, foxColors(c), key) + '" width="' + FOX.w + '" height="' + FOX.h + '" alt="" draggable="false">';
  }

  // ---------- Gyvūnėliai: pūkų gumulėliai ----------
  var PET = {   // rūšis → [forma, spalva]
    kitten: ['eared', '#F0B070'], puppy: ['wide', '#C89A70'], foxcub: ['drop', '#F08A4C'], bunny: ['bunny', '#F4EEE6'], turtle: ['round', '#7CC88C'],
    pandacub: ['cloud', '#F2F0F4'], owlet: ['wing', '#B08C6E'], dragonling: ['horn', '#6EC496'], axolotl: ['spiky', '#FAAAC4'], phoenix: ['flame', '#FF9646']
  };
  function petColors(hex) {
    var b = rgb(hex), l2 = [120, 104, 168];
    return [null, lighten(b, 0.42), b, mix(darken(b, 0.2), l2, 0.12), mix(darken(b, 0.42), l2, 0.2), NAVY, [34, 30, 52], [255, 255, 255], [255, 150, 170], mix(darken(b, 0.32), l2, 0.1), lighten(b, 0.62)];
  }
  function pet(sp, color) {
    var def = PET[sp] || ['round', '#F4EEE6'], s = PX.pets[def[0]], hex = color || def[1];
    return '<img class="px px-pet" src="' + paint(s, petColors(hex), 'pet|' + def[0] + '|' + hex) + '" width="' + s.w + '" height="' + s.h + '" alt="" draggable="false">';
  }

  var base = function () { return ((window.GAME && GAME.catalog && GAME.catalog.scenes) || '/img/temos/'); };
  var spr = function (name, cls) { return '<img class="px ' + (cls || 'px-spr') + '" src="' + base().replace('temos/', 'zaidimas/') + name + '.png" alt="" draggable="false">'; };
  /** Daikto ikona: kimono - iš pačios lapės (spalva matosi), ginklai / spalvos / kiaušiniai / namai / dekoracijos - pikseliniai paveikslėliai */
  var svgIcon = A.itemIcon;
  function itemIcon(key, it, cfg) {
    if (!it) return '';
    switch (it.slot) {
      case 'kimono':
        var c = { fur: '#D9DCE4', flame: '#AAB2C0', kimono: it.a.c, trim: it.a.tr, pattern: it.a.p || '', belt: '#F4F1EA' };
        return '<img class="px px-spr" src="' + paint(FOX, foxColors(c), 'kim|' + key, FOX.kbox) + '" alt="" draggable="false">';
      case 'weapon': case 'paint': case 'egg': return spr('i-' + key);
      case 'house': return spr('h-' + it.a.t);
      case 'decor': return spr('d-' + it.a.t);
      case 'bg': return '<img class="px px-bg" src="' + A.sceneUrl(it.a.sc) + '" alt="" draggable="false">';
    }
    return svgIcon(key, it, cfg);
  }
  A.heroSvg = A.hero; A.petSvg = A.pet;
  A.hero = hero; A.pet = pet; A.itemIcon = itemIcon;
  A.house = function (t) { return spr('h-' + t); };
  A.decor = function (t) { return spr('d-' + t); };
  A.sceneUrl = function (sc) { return base() + 'px/' + sc + '.png'; };
  A.icon = function (name, cls) { return '<img class="px px-ic' + (cls ? ' ' + cls : '') + '" src="' + base().replace('temos/', 'zaidimas/') + name + '.png" width="18" height="18" alt="" draggable="false">'; };
  A.pixel = true; A.face = face; A.charKey = charKey; A.chars = chars;
})();
