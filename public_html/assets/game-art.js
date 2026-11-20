// Žaidimo „Mano dojo“ piešiniai (SVG): personažai (10 gyvūnų ir žmogus), drabužiai, ginklai, gyvūnėliai, namai, dekoracijos.
// Viskas piešiama pagal tuos pačius raktus kaip app/game.php kataloge. window.GameArt.hero(cfg, opts) → SVG tekstas.
(function () {
  var uidN = 0;
  function uid() { return 'ga' + (++uidN) + Math.floor(Math.random() * 1e4); }
  // ---------- Spalvos ----------
  function hex2rgb(h) { h = String(h || '#888888').replace('#', ''); if (h.length === 3) h = h.replace(/./g, '$&$&'); var n = parseInt(h, 16); return [n >> 16 & 255, n >> 8 & 255, n & 255]; }
  function rgb2hex(r) { return '#' + r.map(function (v) { v = Math.max(0, Math.min(255, Math.round(v))); return (v < 16 ? '0' : '') + v.toString(16); }).join(''); }
  function shade(h, k) { var c = hex2rgb(h); return rgb2hex(k >= 0 ? c.map(function (v) { return v + (255 - v) * k; }) : c.map(function (v) { return v * (1 + k); })); }
  function lum(h) { var c = hex2rgb(h); return (0.299 * c[0] + 0.587 * c[1] + 0.114 * c[2]) / 255; }
  function grad(id, c1, c2, vert, extra) { return '<linearGradient id="' + id + '" x1="0" y1="0" x2="' + (vert ? 0 : 1) + '" y2="' + (vert ? 1 : 0) + '"' + (extra || '') + '><stop offset="0" stop-color="' + c1 + '"/><stop offset="1" stop-color="' + c2 + '"/></linearGradient>'; }
  function rgrad(id, c1, c2, cx, cy, r) { return '<radialGradient id="' + id + '" cx="' + (cx || '.38') + '" cy="' + (cy || '.32') + '" r="' + (r || '.75') + '"><stop offset="0" stop-color="' + c1 + '"/><stop offset="1" stop-color="' + c2 + '"/></radialGradient>'; }
  var INK = '#2A2321';

  // ---------- Rūšių bruožai ----------
  // muz - snukio spalva, ear - ausų tipas, mark - žymės, nose - nosies spalva, tail - uodega, feet - pėdos
  var SP = {
    tiger:  { muz: '#FFF6EA', ear: 'round', mark: 'tiger', nose: '#E07A86', tail: 'tiger', feet: 'paw', whisk: 1 },
    lion:   { muz: '#FFF1D6', ear: 'round', mark: 'lion', nose: '#7A4A3A', tail: 'lion', feet: 'paw', whisk: 1 },
    panda:  { muz: '#FFFFFF', ear: 'roundDark', mark: 'panda', nose: '#2A2321', tail: 'puff', feet: 'pawDark' },
    fox:    { muz: '#FFF8F0', ear: 'fox', mark: 'fox', nose: '#2A2321', tail: 'fox', feet: 'pawDark', whisk: 1 },
    wolf:   { muz: '#EEF0F2', ear: 'wolf', mark: 'wolf', nose: '#2A2321', tail: 'wolf', feet: 'paw' },
    cat:    { muz: '#FFF6EE', ear: 'cat', mark: 'cat', nose: '#E58A9A', tail: 'cat', feet: 'paw', whisk: 1 },
    rabbit: { muz: '#FFFFFF', ear: 'rabbit', mark: 'rabbit', nose: '#E58A9A', tail: 'puff', feet: 'paw' },
    monkey: { muz: '#F2D2B0', ear: 'monkey', mark: 'monkey', nose: '#6E4630', tail: 'monkey', feet: 'skinPaw' },
    eagle:  { muz: '#FFFFFF', ear: 'none', mark: 'eagle', nose: '#F2B705', tail: 'feathers', feet: 'talon' },
    dragon: { muz: '#BFE8C9', ear: 'dragon', mark: 'dragon', nose: '#2E6B4E', tail: 'dragon', feet: 'claw' },
    human:  { muz: null, ear: 'human', mark: 'human', nose: null, tail: null, feet: 'human' },
    // Mistiniai ir reti (piešiami pagal artimą rūšį + ypatingos detalės)
    redpanda:    { base: 'cat', muz: '#FFF6EE', ear: 'round', mark: 'redpanda', nose: '#2A2321', tail: 'ringed', feet: 'pawDark', whisk: 1 },
    kitsune:     { base: 'fox', muz: '#FFFFFF', ear: 'fox', mark: 'fox', nose: '#2A2321', tail: 'kitsune', feet: 'paw', whisk: 1, extra: 'kitsune' },
    yeti:        { base: 'monkey', muz: '#A9C7E8', ear: 'none', mark: 'monkey', nose: '#4A6A8A', tail: null, feet: 'paw', extra: 'yeti' },
    tengu:       { base: 'eagle', muz: '#FFFFFF', ear: 'none', mark: 'none', nose: '#F2B705', tail: 'feathers', feet: 'talon', head: 'fur', extra: 'tengu' },
    unicorn:     { base: 'wolf', muz: '#FCEFF6', ear: 'cat', mark: 'none', nose: '#E58A9A', tail: 'unicorn', feet: 'hoof', extra: 'unicorn' },
    phoenixbird: { base: 'eagle', muz: '#FFE08A', ear: 'none', mark: 'none', nose: '#F2B705', tail: 'flame', feet: 'talon', head: 'fur', extra: 'phoenix' }
  };

  var SPC = { redpanda: '#C8562A', kitsune: '#F7F1E8', yeti: '#F2F4F7', tengu: '#2F3142', unicorn: '#FBF8FF', phoenixbird: '#E8501A', tiger: '#E8892B', lion: '#E0B04A', panda: '#F4F2EE', fox: '#E0712C', wolf: '#8C96A3', cat: '#9C8F86', rabbit: '#F2EEE8', monkey: '#8A5A3B', eagle: '#7A5236', dragon: '#3FA06A', human: '#F1C7A5' };
  function spColor(sp) { var S2 = window.GAME && GAME.catalog && GAME.catalog.species; return (S2 && S2[sp] && S2[sp].c) || SPC[sp] || '#E8892B'; }

  // ---------- Diržas ----------
  function beltColor(lvl) {
    var B = (window.GAME && GAME.catalog && GAME.catalog.belts) || {};
    var b = B[lvl] || B[String(lvl)];
    return b ? b[2] : '#F4F1EA';
  }

  // ---------- Personažas ----------
  // cfg: {species, gender, look:{fur,eyes,hair,skin}, equipped:{...}, belt, name}; o: {crop:[x,y,w,h], only:'slot', ghost, cls, items(catalog)}
  function hero(cfg, o) {
    o = o || {};
    var CAT = o.items || (window.GAME && GAME.catalog && GAME.catalog.items) || {};
    var real = SP[cfg.species] ? cfg.species : 'tiger', S = SP[real], sp = S.base || real, g = cfg.gender === 'f' ? 'f' : 'm';
    var L = cfg.look || {}, E = cfg.equipped || {}, id = uid();
    var isH = sp === 'human';
    var fur = o.ghost ? '#C9CDD3' : (isH ? (L.skin || '#F1C7A5') : (L.fur || spColor(real)));
    var furD = shade(fur, -0.22), furL = shade(fur, 0.35), dark = lum(fur) < 0.35;
    var muz = o.ghost ? '#E3E6EA' : (S.muz ? (sp === 'monkey' || sp === 'dragon' || S.base ? S.muz : (lum(fur) > 0.85 ? shade(fur, -0.06) : S.muz)) : fur);
    var hairC = o.ghost ? '#9AA1AA' : (L.hair || (isH ? '#2B1D14' : shade(fur, -0.3)));
    var eyeC = L.eyes || '#5B3A1E';
    var it = function (slot) { var k = E[slot]; return k && CAT[k] ? CAT[k] : null; };
    var kim = it('kimono') || { a: { c: '#F7F5EF', tr: '#E4DFD3' } }, kc = o.ghost && o.only !== 'kimono' ? '#E9EBEE' : kim.a.c, ktr = o.ghost && o.only !== 'kimono' ? '#D5D9DE' : kim.a.tr;
    var belt = beltColor(cfg.belt || 1), beltD = shade(belt, -0.25), beltLight = lum(belt) > 0.85;
    var show = function (slot) { return !o.only || o.only === slot || (o.only === 'all'); };
    var d = '<defs>' +
      rgrad(id + 'f', furL, fur, '.36', '.3', '.8') + grad(id + 'fd', fur, furD, true) +
      grad(id + 'k', shade(kc, 0.12), shade(kc, -0.14), false) + grad(id + 'kv', shade(kc, 0.08), shade(kc, -0.18), true) +
      rgrad(id + 'e', shade(eyeC, 0.35), shade(eyeC, -0.35), '.4', '.35', '.7') +
      grad(id + 'b', shade(belt, 0.15), beltD, true) +
      '<filter id="' + id + 'sh" x="-20%" y="-20%" width="140%" height="140%"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#000" flood-opacity=".22"/></filter>' +
      '<filter id="' + id + 'gl" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="4"/></filter>' +
      '</defs>';
    var b = '';
    // šešėlis po kojomis
    if (!o.crop) b += '<ellipse cx="100" cy="233" rx="46" ry="7" fill="#000" opacity=".18"/>';
    // aura
    var au = it('aura');
    if (au && show('aura')) b += aura(au.a, id);
    // nugara: apsiaustas, sparnai, vėliavėlė, kuprinė (už kūno)
    var bk = it('back');
    if (bk && show('back')) b += back(bk.a, id, 'behind');
    // uodega
    if (S.tail && !o.only) b += tail(S.tail, fur, furD, id, sp);
    if (S.extra === 'kitsune' && !o.only) b += '<g class="ga-spin" opacity=".9">' + [0, 120, 240].map(function (a) { var r = a * Math.PI / 180; return '<path d="M' + (100 + Math.cos(r) * 78).toFixed(0) + ' ' + (110 + Math.sin(r) * 86).toFixed(0) + ' q-7 -10 0 -20 q7 10 0 20 Z" fill="#7FD1FF" opacity=".85"/>'; }).join('') + '</g>';
    // kojos (kelnės)
    b += '<g class="ga-legs">' +
      '<path d="M74 176 L70 222 Q71 228 80 228 L92 228 Q97 228 97 222 L99 182 Z" fill="url(#' + id + 'kv)" stroke="' + shade(kc, -0.3) + '" stroke-width="1"/>' +
      '<path d="M126 176 L130 222 Q129 228 120 228 L108 228 Q103 228 103 222 L101 182 Z" fill="url(#' + id + 'kv)" stroke="' + shade(kc, -0.3) + '" stroke-width="1"/>' +
      '<path d="M86 192 L84 222 M114 192 L116 222" stroke="' + shade(kc, -0.18) + '" stroke-width="1.2" fill="none" opacity=".7"/>' +
      feet(S.feet, fur, furD, sp) + '</g>';
    // liemuo: kimono švarkas
    b += '<g class="ga-body">' + jacket(kc, ktr, kim.a.p, id, fur, isH, o.ghost && o.only !== 'kimono') + '</g>';
    // diržas
    b += beltSvg(belt, beltD, beltLight, (it('belt') || {}).a, cfg.belt || 1, id, cfg.name);
    // galinė ranka
    b += arm('rear', kc, fur, furD, id, it('hands'), S.feet);
    // kaklas
    var nk = it('neck');
    if (nk && show('neck')) b += neck(nk.a, id);
    // galva
    b += '<g class="ga-head">' + head(sp, S, g, fur, furD, furL, muz, eyeC, hairC, id, it('hair'), it('head'), it('face'), dark, o, real) + '</g>';
    // kuprinė / vėliavėlė priekyje (diržai ant pečių)
    if (bk && show('back')) b += back(bk.a, id, 'front');
    // priekinė ranka su ginklu
    var wp = it('weapon');
    if (wp && show('weapon')) b += weapon(wp.a, id, 'behind');
    b += arm('front', kc, fur, furD, id, it('hands'), S.feet);
    if (wp && show('weapon')) b += weapon(wp.a, id, 'front');
    var vb = o.crop ? o.crop.join(' ') : '0 0 200 240';
    return '<svg class="ga-hero ' + (o.cls || '') + '" viewBox="' + vb + '" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="' + esc(cfg.name || '') + '">' + d + '<g filter="url(#' + id + 'sh)">' + b + '</g></svg>';
  }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

  function feet(t, fur, furD, sp) {
    var c = t === 'pawDark' ? '#2A2321' : t === 'human' || t === 'skinPaw' ? (t === 'skinPaw' ? '#C9A27E' : fur) : t === 'talon' ? '#F2B705' : t === 'claw' ? fur : fur;
    var s = '';
    [[84, 1], [116, -1]].forEach(function (f) {
      var x = f[0];
      if (t === 'hoof') s += '<path d="M' + (x - 12) + ' 234 L' + (x - 10) + ' 224 L' + (x + 10) + ' 224 L' + (x + 12) + ' 234 Z" fill="#B9A6D9" stroke="#7E6AA8"/>';
      else if (t === 'talon') s += '<path d="M' + (x - 12) + ' 232 l6 -6 l4 6 l4 -7 l4 7 l4 -6 l4 6 Z" fill="#E5A800" stroke="#8A6400" stroke-width="1"/>';
      else if (t === 'claw') s += '<path d="M' + (x - 13) + ' 233 q13 -13 26 0 Z" fill="' + c + '" stroke="' + shade(c, -0.35) + '" stroke-width="1"/><path d="M' + (x - 9) + ' 233 l-2 3 M' + x + ' 233 l0 3 M' + (x + 9) + ' 233 l2 3" stroke="#F2F2F2" stroke-width="2" stroke-linecap="round"/>';
      else s += '<ellipse cx="' + x + '" cy="231" rx="14" ry="6.5" fill="' + c + '" stroke="' + shade(c, -0.3) + '" stroke-width="1"/>' +
        (t === 'human' ? '' : '<path d="M' + (x - 5) + ' 228 v4 M' + x + ' 227 v5 M' + (x + 5) + ' 228 v4" stroke="' + shade(c, -0.35) + '" stroke-width="1" stroke-linecap="round"/>');
    });
    return s;
  }

  function jacket(kc, tr, pat, id, fur, isH, plain) {
    var s = '';
    // švarkas
    s += '<path id="' + id + 'jk" d="M70 122 Q100 112 130 122 L138 170 Q138 186 128 186 L72 186 Q62 186 62 170 Z" fill="url(#' + id + 'k)" stroke="' + shade(kc, -0.35) + '" stroke-width="1.2"/>';
    s += '<clipPath id="' + id + 'jc"><path d="M70 122 Q100 112 130 122 L138 170 Q138 186 128 186 L72 186 Q62 186 62 170 Z"/></clipPath>';
    if (pat && !plain) s += '<g clip-path="url(#' + id + 'jc)">' + pattern(pat, kc, tr, id) + '</g>';
    // krūtinė (V iškirptė)
    s += '<path d="M90 118 L100 150 L110 118 Z" fill="' + (isH ? fur : shade(fur, 0.15)) + '"/>';
    // atlapai: kairysis ant dešiniojo, su kraštu
    s += '<path d="M110 118 L92 172 L84 172 L104 118 Z" fill="' + tr + '" opacity=".95"/>';
    s += '<path d="M90 118 L104 160 L100 168 L86 122 Z" fill="' + tr + '" opacity=".85"/>';
    s += '<path d="M76 140 Q74 160 78 176 M124 140 Q126 160 122 176" stroke="' + shade(kc, -0.2) + '" stroke-width="1.2" fill="none" opacity=".6"/>';
    return s;
  }

  function pattern(p, kc, tr, id) {
    var s = '', i;
    if (p === 'stripes') for (i = 0; i < 7; i++) s += '<path d="M' + (62 + i * 12) + ' 120 q6 14 -2 30 q8 10 2 40" stroke="' + tr + '" stroke-width="4" fill="none" opacity=".85"/>';
    else if (p === 'sakura') for (i = 0; i < 14; i++) s += flower(66 + (i * 37) % 72, 124 + (i * 23) % 60, 4, tr);
    else if (p === 'waves') for (i = 0; i < 5; i++) s += '<path d="M60 ' + (130 + i * 12) + ' q8 -8 16 0 t16 0 t16 0 t16 0 t16 0" stroke="' + tr + '" stroke-width="2.2" fill="none" opacity=".7"/>';
    else if (p === 'stars') for (i = 0; i < 16; i++) s += star(66 + (i * 41) % 72, 124 + (i * 29) % 60, 2.4, tr);
    else if (p === 'flame') s += '<path d="M60 186 q6 -22 12 -6 q4 -18 10 -2 q4 -20 10 -4 q5 -22 10 -2 q4 -18 10 -4 q5 -20 10 -2 q4 -16 10 0 L142 190 Z" fill="' + tr + '"/><path d="M60 188 q8 -12 14 -2 q6 -12 12 0 q6 -12 12 0 q6 -12 12 0 q6 -12 12 0 L140 190 Z" fill="#F2C14E" opacity=".9"/>';
    else if (p === 'camo') for (i = 0; i < 10; i++) s += '<ellipse cx="' + (64 + (i * 31) % 74) + '" cy="' + (124 + (i * 17) % 60) + '" rx="' + (6 + i % 3 * 3) + '" ry="5" fill="' + (i % 2 ? tr : shade(kc, 0.2)) + '" opacity=".8"/>';
    else if (p === 'dragon') s += '<circle cx="117" cy="140" r="11" fill="none" stroke="' + tr + '" stroke-width="2"/><path d="M108 144 q4 -10 10 -6 q6 4 2 10 q6 -2 6 -8" stroke="' + tr + '" stroke-width="2" fill="none"/><path d="M62 182 h76" stroke="' + tr + '" stroke-width="4"/>';
    else if (p === 'shine') s += '<path d="M70 122 L84 122 L68 186 L62 186 Z" fill="#fff" opacity=".25"/><path d="M96 120 L104 120 L90 186 L84 186 Z" fill="#fff" opacity=".18"/>' + star(126, 132, 3, '#fff') + star(76, 168, 2.5, '#fff');
    else if (p === 'glow') s += '<path d="M62 186 L138 186" stroke="' + tr + '" stroke-width="5"/><path d="M68 124 L64 186 M132 124 L136 186" stroke="' + tr + '" stroke-width="2" opacity=".8"/>';
    return s;
  }
  function flower(x, y, r, c) { var s = ''; for (var k = 0; k < 5; k++) { var a = k * 72 * Math.PI / 180; s += '<circle cx="' + (x + Math.cos(a) * r * 0.7).toFixed(1) + '" cy="' + (y + Math.sin(a) * r * 0.7).toFixed(1) + '" r="' + (r * 0.55).toFixed(1) + '" fill="' + c + '"/>'; } return s + '<circle cx="' + x + '" cy="' + y + '" r="' + (r * 0.35).toFixed(1) + '" fill="#F2C14E"/>'; }
  function star(x, y, r, c) { var p = []; for (var k = 0; k < 10; k++) { var a = (k * 36 - 90) * Math.PI / 180, rr = k % 2 ? r * 0.45 : r; p.push((x + Math.cos(a) * rr).toFixed(1) + ',' + (y + Math.sin(a) * rr).toFixed(1)); } return '<polygon points="' + p.join(' ') + '" fill="' + c + '"/>'; }

  function beltSvg(c, cd, light, deco, lvl, id, name) {
    var t = deco ? deco.t : 'plain', st = light ? ' stroke="#C9C2B4" stroke-width="1"' : '';
    var s = '<path d="M63 166 Q100 160 137 166 L137 177 Q100 171 63 177 Z" fill="url(#' + id + 'b)"' + st + '/>';
    s += '<path d="M96 168 L88 200 L95 201 L101 176 Z" fill="url(#' + id + 'b)"' + st + '/><path d="M104 168 L114 199 L107 201 L99 176 Z" fill="url(#' + id + 'b)"' + st + '/>';
    s += '<rect x="93" y="164" width="14" height="14" rx="3" fill="' + c + '"' + (light ? ' stroke="#C9C2B4"' : ' stroke="' + cd + '"') + '/>';
    if (t === 'stripes' || lvl >= 10) {
      var n = lvl >= 10 ? Math.min(9, lvl - 9) : 2;
      for (var i = 0; i < Math.min(n, 4); i++) s += '<path d="M' + (90 - i * 0.6) + ' ' + (193 - i * 4) + ' l6 1" stroke="#E5B53A" stroke-width="2"/>';
    }
    if (t === 'name') s += '<path d="M106 182 l4 12" stroke="#E5B53A" stroke-width="1.5" stroke-dasharray="2 1.5"/>';
    if (t === 'flame') s += '<path d="M86 200 q2 -8 5 -3 q2 -7 5 0 q1 4 -2 6 Z M106 200 q2 -8 5 -3 q2 -7 5 0 q1 4 -2 6 Z" fill="#FF7A1A"/>';
    if (t === 'stars') s += star(91, 201, 4, '#F2C14E') + star(111, 201, 4, '#F2C14E');
    if (t === 'dragon') s += '<circle cx="100" cy="171" r="7" fill="#E5B53A" stroke="#8A6400"/><path d="M96 171 q4 -5 8 0 q-4 4 -8 0" fill="#2E8B57"/>';
    return '<g class="ga-belt">' + s + '</g>';
  }

  function arm(which, kc, fur, furD, id, hands, feetT) {
    var front = which === 'front', s = '';
    var hc = hands ? hands.a : null;
    var fist = hc && hc.t === 'gloves' ? hc.c : (feetT === 'pawDark' ? '#2A2321' : fur);
    if (front) {
      s += '<path d="M128 126 Q146 128 148 140 L146 146 Q136 140 126 142 Z" fill="url(#' + id + 'k)" stroke="' + shade(kc, -0.35) + '" stroke-width="1"/>';
      s += '<path d="M142 132 Q156 134 158 138 L156 146 Q148 146 144 146 Z" fill="url(#' + id + 'k)" stroke="' + shade(kc, -0.35) + '" stroke-width="1"/>';
      if (hc && hc.t === 'bracers') s += '<rect x="146" y="133" width="11" height="13" rx="3" fill="' + hc.c + '" stroke="#4A525C"/>';
      s += '<circle cx="163" cy="141" r="' + (hc && hc.t === 'gloves' ? 11 : 9) + '" fill="' + fist + '" stroke="' + shade(fist, -0.35) + '" stroke-width="1.2"/>';
      s += '<path d="M158 137 q5 -2 9 1 M158 141 q5 -2 9 1" stroke="' + shade(fist, -0.35) + '" stroke-width="1" fill="none"/>';
      if (hc && hc.t === 'wraps') s += '<path d="M156 134 l8 14 M160 133 l6 14" stroke="' + hc.c + '" stroke-width="3" opacity=".95"/>';
    } else {
      s += '<path d="M72 126 Q56 132 58 146 L66 150 Q70 140 76 138 Z" fill="url(#' + id + 'k)" stroke="' + shade(kc, -0.35) + '" stroke-width="1"/>';
      s += '<path d="M60 144 Q66 156 84 150 L84 142 Q72 146 66 140 Z" fill="url(#' + id + 'k)" stroke="' + shade(kc, -0.35) + '" stroke-width="1"/>';
      if (hc && hc.t === 'bracers') s += '<rect x="70" y="141" width="12" height="10" rx="3" fill="' + hc.c + '" stroke="#4A525C"/>';
      s += '<circle cx="90" cy="146" r="' + (hc && hc.t === 'gloves' ? 10.5 : 8.5) + '" fill="' + fist + '" stroke="' + shade(fist, -0.35) + '" stroke-width="1.2"/>';
      if (hc && hc.t === 'wraps') s += '<path d="M84 140 l7 12" stroke="' + hc.c + '" stroke-width="3"/>';
    }
    return '<g class="ga-arm-' + which + '">' + s + '</g>';
  }

  function neck(a, id) {
    if (a.t === 'medal') return '<path d="M90 118 L100 140 L110 118" stroke="#C8372D" stroke-width="5" fill="none"/><path d="M93 118 L100 134 L107 118" stroke="#2E5FA8" stroke-width="2" fill="none"/><circle cx="100" cy="146" r="8" fill="' + a.c + '" stroke="' + shade(a.c, -0.4) + '" stroke-width="1.5"/>' + star(100, 146, 4, shade(a.c, 0.4));
    if (a.t === 'scarf') return '<path d="M80 118 Q100 128 120 118 L122 126 Q100 136 78 126 Z" fill="' + a.c + '"/><path d="M114 124 L124 150 L116 152 L108 128 Z" fill="' + shade(a.c, -0.15) + '"/>';
    if (a.t === 'beads') { var s = ''; for (var i = 0; i < 11; i++) { var t = i / 10, x = 84 + t * 32, y = 120 + Math.sin(t * Math.PI) * 18; s += '<circle cx="' + x.toFixed(1) + '" cy="' + y.toFixed(1) + '" r="2.8" fill="' + a.c + '" stroke="' + shade(a.c, -0.4) + '" stroke-width=".6"/>'; } return s; }
    if (a.t === 'amulet') return '<path d="M86 118 Q100 134 114 118" stroke="#8A6400" stroke-width="1.5" fill="none"/><path d="M100 130 l6 8 l-6 8 l-6 -8 Z" fill="' + a.c + '" stroke="#E5B53A" stroke-width="1.5"/>';
    return '';
  }

  function aura(a, id) {
    var c = a.c, s = '';
    if (a.t === 'flame') s = '<g class="ga-aura" opacity=".75" filter="url(#' + id + 'gl)"><path d="M100 18 C60 40 40 90 52 150 C58 190 70 220 100 228 C130 220 142 190 148 150 C160 90 140 40 100 18 Z" fill="' + c + '"/></g>' +
      '<g class="ga-aura2" opacity=".55"><path d="M70 60 q-6 -16 4 -26 q-2 12 8 16 Z M130 60 q6 -16 -4 -26 q2 12 -8 16 Z M54 120 q-12 -6 -10 -20 q6 8 14 8 Z M146 120 q12 -6 10 -20 q-6 8 -14 8 Z" fill="' + shade(c, 0.3) + '"/></g>';
    else if (a.t === 'stars') { for (var i = 0; i < 12; i++) { var ang = i * 30 * Math.PI / 180; s += star(100 + Math.cos(ang) * 74, 120 + Math.sin(ang) * 96, 3 + i % 3, c); } s = '<g class="ga-spin">' + s + '</g>'; }
    else if (a.t === 'petals') { for (var j = 0; j < 14; j++) { var an = j * 26 * Math.PI / 180; s += '<ellipse cx="' + (100 + Math.cos(an) * 76).toFixed(0) + '" cy="' + (120 + Math.sin(an) * 98).toFixed(0) + '" rx="4" ry="2.4" fill="' + c + '" transform="rotate(' + j * 40 + ' ' + (100 + Math.cos(an) * 76).toFixed(0) + ' ' + (120 + Math.sin(an) * 98).toFixed(0) + ')"/>'; } s = '<g class="ga-spin">' + s + '</g>'; }
    else if (a.t === 'bolts') s = '<g class="ga-flick" stroke="' + c + '" stroke-width="3" fill="none" stroke-linejoin="round"><path d="M40 60 l14 18 l-8 4 l16 22"/><path d="M160 70 l-14 16 l8 4 l-14 24"/><path d="M36 170 l14 -10 l-2 10 l16 -8"/><path d="M164 176 l-14 -10 l2 10 l-16 -8"/></g>';
    return s;
  }

  function back(a, id, layer) {
    if (a.t === 'cape' && layer === 'behind') return '<path d="M72 122 Q100 118 128 122 L144 214 Q100 226 56 214 Z" fill="' + a.c + '" stroke="' + shade(a.c, -0.35) + '" stroke-width="1"/>' + (a.m ? '<path d="M56 214 Q100 226 144 214" stroke="' + a.m + '" stroke-width="4" fill="none"/>' : '') + '<path d="M84 130 L76 206 M116 130 L124 206" stroke="' + shade(a.c, -0.2) + '" stroke-width="1.5" opacity=".7"/>';
    if (a.t === 'wings' && layer === 'behind') return '<g class="ga-wings"><path d="M76 130 Q30 90 14 120 Q30 128 22 146 Q40 144 40 162 Q56 150 72 160 Z" fill="' + a.c + '" stroke="' + shade(a.c, -0.4) + '"/><path d="M124 130 Q170 90 186 120 Q170 128 178 146 Q160 144 160 162 Q144 150 128 160 Z" fill="' + a.c + '" stroke="' + shade(a.c, -0.4) + '"/><path d="M74 132 L22 120 M74 136 L30 146 M126 132 L178 120 M126 136 L170 146" stroke="' + shade(a.c, -0.35) + '" stroke-width="1.5"/></g>';
    if (a.t === 'flag' && layer === 'behind') return '<path d="M120 124 L132 20" stroke="#4A3423" stroke-width="3"/><path d="M131 26 L162 30 L158 78 L126 72 Z" fill="' + a.c + '" stroke="#B9B2A4"/><circle cx="144" cy="52" r="9" fill="' + a.m + '"/>';
    if (a.t === 'bag' && layer === 'behind') return '<rect x="68" y="122" width="64" height="58" rx="12" fill="' + a.c + '" stroke="' + shade(a.c, -0.35) + '"/>';
    if (a.t === 'bag' && layer === 'front') return '<path d="M78 122 L80 166 M122 122 L120 166" stroke="' + shade(a.c, -0.2) + '" stroke-width="4"/>';
    return '';
  }

  function tail(t, fur, furD, id, sp) {
    var s = '';
    if (t === 'tiger' || t === 'cat' || t === 'lion') {
      s = '<path class="ga-tail" d="M128 196 Q160 196 166 172 Q170 156 160 150" stroke="' + fur + '" stroke-width="9" fill="none" stroke-linecap="round"/>';
      if (t === 'tiger') s += '<path d="M146 194 l2 -8 M158 186 l6 -6 M165 166 l7 0" stroke="#2A2321" stroke-width="3" stroke-linecap="round"/>';
      if (t === 'lion') s += '<ellipse cx="160" cy="148" rx="7" ry="8" fill="' + shade(fur, -0.35) + '"/>';
    } else if (t === 'fox' || t === 'wolf') {
      s = '<path class="ga-tail" d="M126 200 Q170 210 178 168 Q182 146 166 140 Q172 170 140 186 Z" fill="url(#' + id + 'fd)" stroke="' + shade(fur, -0.35) + '" stroke-width="1"/>';
      if (t === 'fox') s += '<path d="M166 140 Q180 146 178 166 Q172 150 160 150 Z" fill="#FFF8F0"/>';
    } else if (t === 'monkey') s = '<path class="ga-tail" d="M126 200 Q172 214 168 176 Q164 150 148 158 Q140 166 150 170" stroke="' + fur + '" stroke-width="6" fill="none" stroke-linecap="round"/>';
    else if (t === 'puff') s = '<circle cx="128" cy="196" r="9" fill="' + (sp === 'panda' ? '#F4F2EE' : '#FFFFFF') + '" stroke="#DDD6CC"/>';
    else if (t === 'feathers') s = '<path d="M112 196 L150 214 L146 204 L156 206 L148 196 L156 192 L130 190 Z" fill="' + shade(fur, -0.15) + '" stroke="' + shade(fur, -0.4) + '"/>';
    else if (t === 'ringed') { s = '<path class="ga-tail" d="M126 198 Q168 206 172 172 Q174 152 162 146" stroke="' + fur + '" stroke-width="15" fill="none" stroke-linecap="round"/>'; s += '<path d="M146 201 l2 -12 M160 194 l8 -9 M169 176 l12 -3 M168 158 l11 4" stroke="#7A2E14" stroke-width="5" stroke-linecap="round"/>'; }
    else if (t === 'kitsune') { [-28, 0, 28].forEach(function (a) { s += '<g transform="rotate(' + a + ' 126 196)"><path class="ga-tail" d="M126 198 Q176 204 180 160 Q182 140 166 136 Q170 166 140 184 Z" fill="url(#' + id + 'fd)" stroke="' + shade(fur, -0.25) + '"/><path d="M166 136 Q182 142 180 160 Q172 146 160 146 Z" fill="#7FD1FF"/></g>'; }); }
    else if (t === 'unicorn') s = '<path class="ga-tail" d="M126 196 Q164 196 170 220 Q150 206 132 204 Z" fill="#F7A8C4"/><path d="M128 196 Q160 186 176 204 Q156 198 134 202 Z" fill="#A5D8FF"/><path d="M128 194 Q150 176 172 186 Q150 186 132 198 Z" fill="#FFE08A"/>';
    else if (t === 'flame') s = '<g class="ga-aura"><path d="M112 196 Q150 230 172 200 Q156 206 150 196 Q170 196 176 172 Q156 186 140 186 Z" fill="#FFB020"/><path d="M118 196 Q146 216 162 200 Q148 202 144 194 Z" fill="#FF5A1F"/></g>';
    else if (t === 'dragon') s = '<path class="ga-tail" d="M124 198 Q168 214 180 176 L188 160 Q176 168 172 176 Q164 196 132 190 Z" fill="url(#' + id + 'fd)" stroke="' + shade(fur, -0.35) + '"/><path d="M150 198 l4 -8 l4 6 M164 190 l6 -8 l2 8 M174 176 l8 -6 l-1 8" fill="#F2B705" stroke="#B88A00" stroke-width=".8"/>';
    return s;
  }

  // ---------- Galva ----------
  function head(sp, S, g, fur, furD, furL, muz, eyeC, hairC, id, hairIt, headIt, faceIt, dark, o, real) {
    var s = '', cx = 100, cy = 80, hairT = hairIt ? hairIt.a.t : 'short', isH = sp === 'human';
    var ninja = headIt && headIt.a.t === 'ninja';
    // plaukai už galvos (ilgi, kasos, arklio uodega)
    if (isH || hairT !== 'short') s += hairBack(hairT, hairC, isH, g);
    // ausys (už galvos)
    s += ears(S.ear, sp, fur, furD, muz, g);
    if (sp === 'lion' && g === 'm') { var mc = shade(fur, -0.3), mane = ''; for (var i = 0; i < 16; i++) { var a = i * 22.5 * Math.PI / 180; mane += '<circle cx="' + (cx + Math.cos(a) * 50).toFixed(1) + '" cy="' + (cy + 8 + Math.sin(a) * 48).toFixed(1) + '" r="17" fill="' + (i % 2 ? mc : shade(mc, -0.12)) + '"/>'; } s += '<g stroke="' + shade(mc, -0.35) + '" stroke-width="1">' + mane + '</g><circle cx="' + cx + '" cy="' + (cy + 8) + '" r="50" fill="' + mc + '"/>'; }
    if (real === 'phoenixbird') s += '<g class="ga-aura"><path d="M66 58 Q50 20 72 6 Q70 30 84 40 Q80 10 100 -4 Q104 24 108 38 Q120 10 132 8 Q126 30 134 58 Z" fill="#FFB020"/><path d="M76 56 Q70 30 84 20 Q86 38 96 44 Q98 22 108 16 Q110 34 116 44 Q122 30 128 28 Q124 46 126 58 Z" fill="#FF5A1F"/></g>';
    else if (sp === 'eagle') s += '<path d="M70 60 L58 44 L78 52 L76 36 L92 50 L100 32 L108 50 L124 36 L122 52 L142 44 L130 60 Z" fill="' + (S.head ? shade(fur, 0.1) : '#FFFFFF') + '" stroke="' + (S.head ? shade(fur, -0.3) : '#D8D2C6') + '"/>';
    if (real === 'yeti') { var sh = ''; for (var yi = 0; yi < 14; yi++) { var ya = (yi / 13) * Math.PI + Math.PI; sh += '<path d="M' + (100 + Math.cos(ya) * 50).toFixed(0) + ' ' + (82 + Math.sin(ya) * 52).toFixed(0) + ' l' + (Math.cos(ya) * 12).toFixed(0) + ' ' + (Math.sin(ya) * 12 - 2).toFixed(0) + ' l6 4 Z" fill="' + fur + '" stroke="#D5DCE6"/>'; } s += sh; }
    if (real === 'unicorn') s += '<path d="M128 40 Q150 52 152 92 Q140 74 132 70 Q146 96 140 118 Q128 92 122 86 Z" fill="#F7A8C4"/><path d="M126 46 Q140 60 140 86 Q132 70 124 66 Z" fill="#A5D8FF"/><path d="M122 44 Q130 56 132 70 Q126 62 118 58 Z" fill="#FFE08A"/>';
    if (sp === 'dragon') s += '<path d="M72 46 Q60 22 70 10 Q74 30 84 40 Z M128 46 Q140 22 130 10 Q126 30 116 40 Z" fill="#F2E3B6" stroke="#B8A26A"/>';
    // galvos forma
    var shape = sp === 'fox' || sp === 'wolf' ? 'M54 74 Q54 34 100 32 Q146 34 146 74 Q146 104 120 118 Q100 128 80 118 Q54 104 54 74 Z'
      : sp === 'eagle' ? 'M58 78 Q56 36 100 34 Q144 36 142 78 Q140 116 100 124 Q60 116 58 78 Z'
      : sp === 'dragon' ? 'M54 76 Q54 36 100 34 Q146 36 146 76 Q148 108 128 120 Q100 130 72 120 Q52 108 54 76 Z'
      : 'M54 78 Q54 34 100 33 Q146 34 146 78 Q146 118 100 124 Q54 118 54 78 Z';
    var whiteHead = sp === 'eagle' && !S.head;
    var headFill = whiteHead ? '#FFFFFF' : 'url(#' + id + 'f)';
    s += '<path d="' + shape + '" fill="' + headFill + '" stroke="' + (whiteHead ? '#D8D2C6' : shade(fur, -0.38)) + '" stroke-width="1.3"/>';
    // žymės ir snukis
    s += marks(S.mark, sp, fur, furD, muz, g, id);
    if (real === 'kitsune') s += '<path d="M92 50 Q100 40 108 50 Q100 46 92 50 Z M66 92 q8 -2 12 4 M134 92 q-8 -2 -12 4 M68 98 q7 0 10 4 M132 98 q-7 0 -10 4" fill="#D7263D" stroke="#D7263D" stroke-width="2.4" stroke-linecap="round"/><circle cx="100" cy="56" r="3" fill="#D7263D"/>';
    if (real === 'redpanda') s += '<path d="M64 92 Q72 120 100 122 Q128 120 136 92 Q122 108 100 108 Q78 108 64 92 Z" fill="#FFF6EE"/><ellipse cx="90" cy="102" rx="12" ry="9" fill="#FFF6EE"/><ellipse cx="110" cy="102" rx="12" ry="9" fill="#FFF6EE"/><path d="M68 66 q8 -6 16 -2 M132 66 q-8 -6 -16 -2" stroke="#FFF6EE" stroke-width="5" stroke-linecap="round"/><path d="M78 86 q-2 10 -8 16 M122 86 q2 10 8 16" stroke="#7A2E14" stroke-width="5" stroke-linecap="round"/>';
    if (real === 'tengu') s += '<path d="M64 98 Q72 120 100 124 Q128 120 136 98 Q118 110 100 110 Q82 110 64 98 Z" fill="' + shade(fur, 0.12) + '"/>';
    if (real === 'phoenixbird') s += '<path d="M60 96 Q70 122 100 126 Q130 122 140 96 Q120 112 100 112 Q80 112 60 96 Z" fill="#FFD36B" opacity=".9"/>';
    // akys
    if (!(headIt && headIt.a.t === 'ninja' && false)) s += eyes(eyeC, g, sp, id, dark);
    // nosis, burna, ūsai
    s += snout(sp, S, fur, muz, g);
    // skruostai
    if (sp !== 'eagle') s += '<ellipse cx="74" cy="98" rx="7" ry="4" fill="#FF8FA3" opacity=".28"/><ellipse cx="126" cy="98" rx="7" ry="4" fill="#FF8FA3" opacity=".28"/>';
    // plaukai priekyje / kuokštas
    if (isH || hairT !== 'short' || sp === 'monkey') s += hairFront(hairT, hairC, isH, g, sp);
    // veido daiktai ir galvos daiktai
    if (faceIt) s += faceItem(faceIt.a);
    if (headIt) s += headItem(headIt.a, id);
    if (real === 'unicorn' && !(headIt && /ninja/.test(headIt.a.t))) s += '<path d="M98 36 Q132 28 148 58 Q140 52 130 52 Q144 66 146 88 Q132 68 118 62 Q124 50 98 44 Z" fill="#F7A8C4"/><path d="M100 36 Q126 32 138 52 Q128 48 120 50 Q128 58 130 70 Q120 58 108 54 Z" fill="#A5D8FF"/><path d="M100 36 Q118 34 126 46 Q116 44 108 48 Z" fill="#FFE08A"/>';
    if (real === 'unicorn' && !(headIt && /kabuto|cap|ninja|crown/.test(headIt.a.t))) s += '<path d="M94 38 L100 2 L106 38 Z" fill="#FFE08A" stroke="#C99A1E" stroke-width="1.2"/><path d="M95.5 30 L104.5 26 M96.5 22 L103.5 18 M97.5 14 L102.5 11" stroke="#C99A1E" stroke-width="1.2"/>';
    if (real === 'tengu' && !headIt) s += '<path d="M88 40 L100 26 L112 40 L108 46 L92 46 Z" fill="#17140F"/><path d="M100 26 L100 46" stroke="#C8372D" stroke-width="2"/>';
    return s;
  }

  function ears(t, sp, fur, furD, muz, g) {
    var inner = '#F6B6C2', s = '';
    if (t === 'round' || t === 'roundDark') { var c = t === 'roundDark' ? '#2A2321' : fur; s = '<circle cx="64" cy="42" r="15" fill="' + c + '" stroke="' + shade(c, -0.35) + '"/><circle cx="136" cy="42" r="15" fill="' + c + '" stroke="' + shade(c, -0.35) + '"/>' + (t === 'round' ? '<circle cx="64" cy="44" r="8" fill="' + (sp === 'tiger' ? '#2A2321' : inner) + '" opacity=".75"/><circle cx="136" cy="44" r="8" fill="' + (sp === 'tiger' ? '#2A2321' : inner) + '" opacity=".75"/>' : ''); }
    else if (t === 'fox' || t === 'cat' || t === 'wolf') {
      var tall = t === 'fox' ? 34 : t === 'wolf' ? 30 : 26;
      s = '<path d="M58 54 L62 ' + (44 - tall) + ' L88 38 Z" fill="' + fur + '" stroke="' + shade(fur, -0.35) + '"/><path d="M142 54 L138 ' + (44 - tall) + ' L112 38 Z" fill="' + fur + '" stroke="' + shade(fur, -0.35) + '"/>' +
        '<path d="M64 50 L66 ' + (50 - tall + 4) + ' L82 40 Z" fill="' + (t === 'cat' ? inner : '#FFF8F0') + '"/><path d="M136 50 L134 ' + (50 - tall + 4) + ' L118 40 Z" fill="' + (t === 'cat' ? inner : '#FFF8F0') + '"/>';
      if (t === 'fox') s += '<path d="M60 ' + (44 - tall + 8) + ' L62 ' + (44 - tall) + ' L70 ' + (44 - tall + 6) + ' Z M140 ' + (44 - tall + 8) + ' L138 ' + (44 - tall) + ' L130 ' + (44 - tall + 6) + ' Z" fill="#2A2321"/>';
    } else if (t === 'rabbit') {
      s = '<path d="M74 44 Q62 -6 76 -14 Q90 -6 88 40 Z" fill="' + fur + '" stroke="' + shade(fur, -0.3) + '"/><path d="M77 38 Q70 0 77 -6 Q84 0 84 38 Z" fill="' + inner + '"/>' +
        (g === 'f' ? '<path d="M126 44 Q134 4 156 0 Q160 10 140 46 Z" fill="' + fur + '" stroke="' + shade(fur, -0.3) + '"/><path d="M130 40 Q138 10 152 6 Q150 16 136 42 Z" fill="' + inner + '"/>'
          : '<path d="M126 44 Q138 -6 124 -14 Q110 -6 112 40 Z" fill="' + fur + '" stroke="' + shade(fur, -0.3) + '"/><path d="M123 38 Q130 0 123 -6 Q116 0 116 38 Z" fill="' + inner + '"/>');
    } else if (t === 'monkey') s = '<circle cx="52" cy="80" r="15" fill="' + fur + '" stroke="' + shade(fur, -0.35) + '"/><circle cx="148" cy="80" r="15" fill="' + fur + '" stroke="' + shade(fur, -0.35) + '"/><circle cx="53" cy="80" r="8" fill="#F2D2B0"/><circle cx="147" cy="80" r="8" fill="#F2D2B0"/>';
    else if (t === 'dragon') s = '<path d="M56 70 L36 58 L44 76 L32 82 L56 86 Z M144 70 L164 58 L156 76 L168 82 L144 86 Z" fill="' + shade(fur, -0.1) + '" stroke="' + shade(fur, -0.4) + '"/>';
    else if (t === 'human') s = '<ellipse cx="56" cy="84" rx="7" ry="10" fill="' + fur + '" stroke="' + shade(fur, -0.3) + '"/><ellipse cx="144" cy="84" rx="7" ry="10" fill="' + fur + '" stroke="' + shade(fur, -0.3) + '"/>';
    return s;
  }

  function marks(t, sp, fur, furD, muz, g, id) {
    var s = '';
    if (t === 'tiger' || t === 'cat' || t === 'fox' || t === 'wolf' || t === 'lion' || t === 'rabbit') {
      // šviesūs skruostai ir snukis
      s += '<path d="M66 96 Q72 120 100 122 Q128 120 134 96 Q120 108 100 108 Q80 108 66 96 Z" fill="' + muz + '" opacity=".95"/>';
      s += '<ellipse cx="90" cy="102" rx="13" ry="10" fill="' + muz + '"/><ellipse cx="110" cy="102" rx="13" ry="10" fill="' + muz + '"/>';
    }
    if (t === 'tiger') s += '<path d="M100 36 L96 52 L100 50 L104 52 Z M84 38 Q88 48 86 56 M116 38 Q112 48 114 56 M56 74 L70 78 M56 86 L68 86 M144 74 L130 78 M144 86 L132 86" stroke="#2A2321" stroke-width="3.5" stroke-linecap="round" fill="#2A2321"/>';
    if (t === 'wolf') s += '<path d="M60 70 Q100 30 140 70 Q130 54 100 50 Q70 54 60 70 Z" fill="' + shade(fur, -0.25) + '" opacity=".7"/>';
    if (t === 'cat') s += '<path d="M92 40 l2 10 M100 38 l0 11 M108 40 l-2 10" stroke="' + shade(fur, -0.35) + '" stroke-width="3" stroke-linecap="round"/>';
    if (t === 'panda') s += '<path d="M66 74 Q72 62 86 70 Q94 80 86 92 Q74 98 68 88 Z" fill="#2A2321" transform="rotate(-8 80 80)"/><path d="M134 74 Q128 62 114 70 Q106 80 114 92 Q126 98 132 88 Z" fill="#2A2321" transform="rotate(8 120 80)"/><ellipse cx="100" cy="104" rx="20" ry="13" fill="#FFFFFF"/>';
    if (t === 'monkey') s += '<path d="M100 56 Q84 46 72 58 Q62 72 70 92 Q76 116 100 120 Q124 116 130 92 Q138 72 128 58 Q116 46 100 56 Z" fill="' + muz + '"/>';
    if (t === 'eagle') s += '<path d="M58 78 Q56 104 74 116 Q66 96 70 80 Z M142 78 Q144 104 126 116 Q134 96 130 80 Z" fill="#EDE7DA"/>';
    if (t === 'dragon') { s += '<ellipse cx="100" cy="104" rx="26" ry="15" fill="' + muz + '"/>'; for (var i = 0; i < 6; i++) s += '<path d="M' + (72 + i * 11) + ' 52 q5 -5 10 0" stroke="' + shade(fur, -0.25) + '" stroke-width="1.5" fill="none"/>'; }
    return s;
  }

  function eyes(c, g, sp, id, dark) {
    var s = '', lx = sp === 'eagle' ? 80 : 81, rx = sp === 'eagle' ? 120 : 119, y = 80;
    [[lx, -1], [rx, 1]].forEach(function (e) {
      var x = e[0], side = e[1];
      s += '<g class="ga-eye"><ellipse cx="' + x + '" cy="' + y + '" rx="10" ry="' + (g === 'f' ? 11.5 : 10.5) + '" fill="#FFFFFF" stroke="' + INK + '" stroke-width="1.6"/>' +
        '<circle cx="' + (x + side * 1) + '" cy="' + (y + 1) + '" r="7" fill="url(#' + id + 'e)"/>' +
        (sp === 'cat' || sp === 'dragon' || sp === 'fox' ? '<ellipse cx="' + (x + side * 1) + '" cy="' + (y + 1) + '" rx="2" ry="5.5" fill="#15100C"/>' : '<circle cx="' + (x + side * 1) + '" cy="' + (y + 1) + '" r="3.6" fill="#15100C"/>') +
        '<circle cx="' + (x - 2.5) + '" cy="' + (y - 3) + '" r="2.6" fill="#FFFFFF"/><circle cx="' + (x + 3) + '" cy="' + (y + 4) + '" r="1.2" fill="#FFFFFF" opacity=".85"/></g>';
      // vokas ir antakis (ryžtingas žvilgsnis)
      s += '<path d="M' + (x - 11) + ' ' + (y - 4) + ' Q' + x + ' ' + (y - 14) + ' ' + (x + 11) + ' ' + (y - 4) + '" stroke="' + INK + '" stroke-width="2.4" fill="none" stroke-linecap="round"/>';
      s += '<path d="M' + (x - 10) + ' ' + (y - (side < 0 ? 18 : 15)) + ' L' + (x + 9) + ' ' + (y - (side < 0 ? 15 : 18)) + '" stroke="' + (dark ? '#F2F2F2' : shade(INK, 0.15)) + '" stroke-width="3" stroke-linecap="round" opacity=".85"/>';
      if (g === 'f') { var ox = x + side * 10; s += '<path d="M' + ox + ' ' + (y - 6) + ' l' + side * 5 + ' -4 M' + ox + ' ' + (y - 2) + ' l' + side * 6 + ' -1 M' + (ox - side * 2) + ' ' + (y - 10) + ' l' + side * 3 + ' -5" stroke="' + INK + '" stroke-width="1.6" stroke-linecap="round"/>'; }
    });
    return s;
  }

  function snout(sp, S, fur, muz, g) {
    var s = '';
    if (sp === 'eagle') return '<path d="M90 92 Q100 86 112 92 Q118 104 104 116 Q102 106 96 104 Q90 100 90 92 Z" fill="#F2B705" stroke="#B88A00" stroke-width="1.2"/><path d="M96 104 Q100 108 104 116" stroke="#B88A00" stroke-width="1" fill="none"/>';
    if (sp === 'human') return '<path d="M98 94 Q100 99 102 94" stroke="' + shade(fur, -0.35) + '" stroke-width="1.6" fill="none" stroke-linecap="round"/><path d="M92 106 Q100 112 108 106" stroke="#A0525A" stroke-width="2.4" fill="none" stroke-linecap="round"/>' + (g === 'f' ? '<path d="M93 107 Q100 111 107 107" stroke="#D96A7A" stroke-width="1.6" fill="none"/>' : '');
    if (sp === 'dragon') return '<ellipse cx="93" cy="100" rx="2.6" ry="2" fill="#1F4A36"/><ellipse cx="107" cy="100" rx="2.6" ry="2" fill="#1F4A36"/><path d="M88 110 Q100 116 112 110" stroke="#1F4A36" stroke-width="2.2" fill="none" stroke-linecap="round"/><path d="M95 112 l2 4 l2 -4 M103 112 l2 4 l2 -4" fill="#FFFFFF" stroke="none"/>';
    var nc = S.nose;
    s += '<path d="M93 94 Q100 90 107 94 Q104 100 100 101 Q96 100 93 94 Z" fill="' + nc + '" stroke="' + shade(nc, -0.4) + '" stroke-width=".8"/><ellipse cx="98" cy="93.5" rx="2" ry="1" fill="#FFFFFF" opacity=".5"/>';
    s += '<path d="M100 101 L100 105 M100 105 Q95 110 90 106 M100 105 Q105 110 110 106" stroke="' + INK + '" stroke-width="1.8" fill="none" stroke-linecap="round"/>';
    if (sp === 'rabbit') s += '<rect x="96.5" y="106" width="7" height="6" rx="1.5" fill="#FFFFFF" stroke="#CFC6BA" stroke-width=".8"/>';
    if (S.whisk) s += '<path d="M78 100 L58 96 M78 104 L58 106 M122 100 L142 96 M122 104 L142 106" stroke="' + (lum(fur) < 0.4 ? '#F2F2F2' : '#7A6E60') + '" stroke-width="1" opacity=".7"/>';
    return s;
  }

  function hairBack(t, c, isH, g) {
    if (!isH) return t === 'pony' || t === 'twin' || t === 'braids' ? hairAnimalBack(t, c) : '';
    var s = '';
    if (t === 'long' || (t === 'short' && g === 'f')) s = '<path d="M56 70 Q50 120 62 140 L84 132 L84 80 Z M144 70 Q150 120 138 140 L116 132 L116 80 Z" fill="' + c + '"/>';
    if (t === 'pony') s = '<path d="M140 52 Q170 70 160 120 Q150 106 140 82 Z" fill="' + c + '" stroke="' + shade(c, -0.3) + '"/>';
    if (t === 'braids') s = '<path d="M58 80 Q52 110 60 136 M142 80 Q148 110 140 136" stroke="' + c + '" stroke-width="10" stroke-dasharray="8 3" stroke-linecap="round" fill="none"/>';
    if (t === 'twin') s = '<path d="M60 56 Q30 70 38 116 Q48 96 62 76 Z M140 56 Q170 70 162 116 Q152 96 138 76 Z" fill="' + c + '" stroke="' + shade(c, -0.3) + '"/>';
    if (t === 'afro') s = '<circle cx="100" cy="62" r="56" fill="' + c + '"/>';
    return s;
  }
  function hairAnimalBack(t, c) {
    if (t === 'pony') return '<path d="M120 36 Q160 40 156 86 Q146 70 132 52 Z" fill="' + c + '" stroke="' + shade(c, -0.3) + '"/>';
    if (t === 'twin') return '<path d="M70 40 Q44 50 46 92 Q56 70 76 50 Z M130 40 Q156 50 154 92 Q144 70 124 50 Z" fill="' + c + '"/>';
    if (t === 'braids') return '<path d="M74 44 Q60 80 66 110 M126 44 Q140 80 134 110" stroke="' + c + '" stroke-width="8" stroke-dasharray="7 3" fill="none"/>';
    return '';
  }
  function hairFront(t, c, isH, g, sp) {
    var cs = shade(c, -0.3), s = '';
    if (!isH) {
      // gyvūnams - kuokštas viršugalvyje
      if (t === 'spiky') s = '<path d="M80 40 L86 18 L94 34 L100 12 L106 34 L114 18 L120 40 Z" fill="' + c + '" stroke="' + cs + '"/>';
      else if (t === 'mohawk') s = '<path d="M92 44 L88 14 L100 22 L112 14 L108 44 Z" fill="' + c + '" stroke="' + cs + '"/>';
      else if (t === 'bun' || t === 'topknot') s = '<circle cx="100" cy="26" r="' + (t === 'bun' ? 12 : 9) + '" fill="' + c + '" stroke="' + cs + '"/>' + (t === 'topknot' ? '<rect x="94" y="32" width="12" height="5" rx="2" fill="#C8372D"/>' : '');
      else if (t === 'afro') s = '<path d="M64 46 Q64 14 100 12 Q136 14 136 46 Q120 34 100 34 Q80 34 64 46 Z" fill="' + c + '"/>';
      else if (t === 'long' || t === 'pony' || t === 'braids' || t === 'twin') s = '<path d="M70 46 Q84 30 100 34 Q116 30 130 46 Q112 40 100 46 Q88 40 70 46 Z" fill="' + c + '" stroke="' + cs + '"/>';
      else if (sp === 'monkey') s = '<path d="M84 38 Q100 26 116 38" stroke="' + shade(c, 0) + '" stroke-width="0" fill="none"/>';
      return s;
    }
    // žmogui - kirpčiukai ir šukuosena
    if (t === 'spiky') s = '<path d="M54 70 L50 44 L64 50 L62 28 L78 40 L82 18 L94 34 L102 14 L110 34 L122 18 L124 40 L140 30 L138 52 L150 46 L146 70 Q130 46 100 46 Q72 46 54 70 Z" fill="' + c + '" stroke="' + cs + '"/>';
    else if (t === 'mohawk') s = '<path d="M90 50 L86 8 L100 20 L114 8 L110 50 Z" fill="' + c + '" stroke="' + cs + '"/><path d="M56 70 Q60 52 90 48 L90 56 Q66 58 56 70 Z M144 70 Q140 52 110 48 L110 56 Q134 58 144 70 Z" fill="' + shade(c, 0.25) + '" opacity=".5"/>';
    else if (t === 'afro') s = '<path d="M54 74 Q48 20 100 16 Q152 20 146 74 Q138 44 100 44 Q62 44 54 74 Z" fill="' + c + '"/>';
    else if (t === 'bun' || t === 'topknot') s = '<path d="M54 72 Q54 34 100 33 Q146 34 146 72 Q130 50 100 50 Q70 50 54 72 Z" fill="' + c + '" stroke="' + cs + '"/><circle cx="100" cy="24" r="' + (t === 'bun' ? 15 : 10) + '" fill="' + c + '" stroke="' + cs + '"/>' + (t === 'topknot' ? '<rect x="93" y="32" width="14" height="5" rx="2" fill="#C8372D"/>' : '');
    else s = '<path d="M54 74 Q52 34 100 32 Q148 34 146 74 Q140 56 124 52 Q122 62 108 58 Q104 50 96 56 Q86 62 76 54 Q62 58 54 74 Z" fill="' + c + '" stroke="' + cs + '"/>';
    return s;
  }

  function faceItem(a) {
    if (a.t === 'glasses') return '<path d="M68 78 Q81 70 94 78 L94 86 Q81 94 68 86 Z M106 78 Q119 70 132 78 L132 86 Q119 94 106 86 Z" fill="#1E2430" stroke="#0B0E14" stroke-width="1.5"/><path d="M94 80 Q100 76 106 80" stroke="#0B0E14" stroke-width="2" fill="none"/><path d="M72 79 l6 -3" stroke="#fff" stroke-width="2" opacity=".6"/>';
    if (a.t === 'paint') return '<path d="M64 92 l14 2 M64 98 l14 0 M136 92 l-14 2 M136 98 l-14 0" stroke="' + a.c + '" stroke-width="3" stroke-linecap="round"/>';
    if (a.t === 'oni') return '<g transform="rotate(-18 138 52)"><path d="M120 38 Q138 28 156 38 L154 64 Q138 72 122 64 Z" fill="#C8372D" stroke="#7A1E18"/><path d="M124 34 l-4 -10 l8 6 M152 34 l4 -10 l-8 6" fill="#F2E3B6" stroke="#B8A26A"/><path d="M128 46 l6 3 M148 46 l-6 3" stroke="#2A2321" stroke-width="2"/><path d="M130 58 q8 4 16 0" stroke="#F2F2F2" stroke-width="2" fill="none"/></g>';
    if (a.t === 'stars') return star(70, 100, 4, '#F2C14E') + star(130, 100, 4, '#F2C14E');
    return '';
  }

  function headItem(a, id) {
    if (a.t === 'band') {
      var s = '<path d="M54 62 Q100 50 146 62 L146 72 Q100 60 54 72 Z" fill="' + a.c + '" stroke="' + shade(a.c, -0.3) + '" stroke-width="1"/>';
      s += '<path d="M144 64 Q160 64 170 74 L164 80 Q156 72 144 72 Z M146 68 Q158 76 160 90 L152 90 Q150 78 144 72 Z" fill="' + a.c + '" stroke="' + shade(a.c, -0.3) + '" stroke-width="1"/>';
      if (a.m === 'sun') s += '<circle cx="100" cy="61" r="5.5" fill="#C8372D"/>';
      else if (a.m) s += '<path d="M90 63 h20" stroke="' + a.m + '" stroke-width="2.5"/>';
      return s;
    }
    if (a.t === 'cap') return '<path d="M56 60 Q58 22 100 22 Q142 22 144 60 Z" fill="' + a.c + '" stroke="' + shade(a.c, -0.35) + '"/><path d="M98 58 Q140 50 168 62 Q140 68 98 64 Z" fill="' + shade(a.c, -0.15) + '" stroke="' + shade(a.c, -0.4) + '"/><circle cx="100" cy="22" r="4" fill="' + shade(a.c, 0.3) + '"/>';
    if (a.t === 'flowers') { var f = ''; for (var i = 0; i < 9; i++) f += flower(60 + i * 10, 52 - Math.sin(i / 8 * Math.PI) * 14, 5, ['#F2A3BC', '#FFFFFF', '#F7D84A'][i % 3]); return '<path d="M58 58 Q100 30 142 58" stroke="#4C8C43" stroke-width="3" fill="none"/>' + f; }
    if (a.t === 'ninja') return '<path d="M50 76 Q48 26 100 26 Q152 26 150 76 L150 70 Q100 62 50 70 Z" fill="' + a.c + '"/><path d="M52 92 Q100 88 148 92 Q150 122 100 128 Q50 122 52 92 Z" fill="' + a.c + '"/><path d="M146 64 Q166 66 172 78 L164 82 Q158 72 146 72 Z" fill="' + a.c + '"/>';
    if (a.t === 'kabuto') return '<path d="M50 70 Q48 22 100 20 Q152 22 150 70 L160 76 Q100 60 40 76 Z" fill="' + a.c + '" stroke="#1E1514"/><path d="M76 24 L60 0 L90 22 Z M124 24 L140 0 L110 22 Z" fill="' + a.m + '" stroke="#8A6400"/><circle cx="100" cy="36" r="7" fill="' + a.m + '"/>';
    if (a.t === 'crown') return '<path d="M66 46 L62 16 L80 32 L100 10 L120 32 L138 16 L134 46 Z" fill="' + a.c + '" stroke="#8A6400" stroke-width="1.5"/><circle cx="100" cy="30" r="4" fill="#C8372D"/><circle cx="80" cy="38" r="3" fill="#2E5FA8"/><circle cx="120" cy="38" r="3" fill="#2E8B57"/>';
    return '';
  }

  function weapon(a, id, layer) {
    var t = a.t, c = a.c, s = '', glow = a.fx ? { fire: '#FF7A1A', ice: '#8FE0FF', shadow: '#A07CFF', gold: '#FFE08A', dragon: '#7CFFB0' }[a.fx] : null;
    if (layer === 'behind' && (t === 'bo' || t === 'jo')) {
      var y1 = t === 'jo' ? 60 : 24, y2 = t === 'jo' ? 218 : 236;
      s += (glow ? '<path d="M176 ' + y1 + ' L150 ' + y2 + '" stroke="' + glow + '" stroke-width="12" opacity=".45" filter="url(#' + id + 'gl)"/>' : '') +
        '<path d="M176 ' + y1 + ' L150 ' + y2 + '" stroke="' + shade(c, -0.3) + '" stroke-width="7" stroke-linecap="round"/><path d="M176 ' + y1 + ' L150 ' + y2 + '" stroke="' + c + '" stroke-width="5" stroke-linecap="round"/>';
      if (a.fx === 'dragon') s += '<path d="M176 24 q10 -6 14 4 q-6 0 -8 6 q-4 -4 -6 -10" fill="#2E8B57" stroke="#E5B53A"/>';
      return s;
    }
    if (layer !== 'front') return '';
    if (t === 'tonfa') s = '<path d="M150 150 L184 150" stroke="' + shade(c, -0.3) + '" stroke-width="8" stroke-linecap="round"/><path d="M150 150 L184 150" stroke="' + c + '" stroke-width="6" stroke-linecap="round"/><path d="M164 150 L164 136" stroke="' + c + '" stroke-width="5"/>';
    else if (t === 'nunchaku') s = '<path d="M162 140 L178 104" stroke="' + c + '" stroke-width="7" stroke-linecap="round"/><path d="M178 104 q6 -4 6 -10" stroke="#9AA1AA" stroke-width="1.5" stroke-dasharray="2 1.5" fill="none"/><path d="M184 94 L186 56" stroke="' + c + '" stroke-width="7" stroke-linecap="round"/>' + (glow ? '<path d="M178 104 L186 56" stroke="' + glow + '" stroke-width="12" opacity=".4" filter="url(#' + id + 'gl)"/>' : '');
    else if (t === 'sai') s = '<path d="M164 146 L164 160" stroke="#5B3A22" stroke-width="5" stroke-linecap="round"/><path d="M164 136 L166 84" stroke="' + c + '" stroke-width="4" stroke-linecap="round"/><path d="M156 138 Q154 124 160 118 M172 138 Q174 124 168 118" stroke="' + c + '" stroke-width="3" fill="none" stroke-linecap="round"/>' + (glow ? '<path d="M164 136 L166 84" stroke="' + glow + '" stroke-width="10" opacity=".45" filter="url(#' + id + 'gl)"/>' : '');
    else if (t === 'bokken' || t === 'katana') s = (glow ? '<path d="M166 132 L196 34" stroke="' + glow + '" stroke-width="12" opacity=".45" filter="url(#' + id + 'gl)"/>' : '') +
      '<path d="M162 148 L166 132" stroke="' + (t === 'katana' ? '#2A2321' : shade(c, -0.25)) + '" stroke-width="6" stroke-linecap="round"/><path d="M158 134 L174 138" stroke="' + (t === 'katana' ? '#E5B53A' : shade(c, -0.35)) + '" stroke-width="4" stroke-linecap="round"/><path d="M166 132 Q184 90 196 34" stroke="' + (t === 'katana' ? '#E8ECF0' : c) + '" stroke-width="' + (t === 'katana' ? 4 : 6) + '" fill="none" stroke-linecap="round"/>' + (t === 'katana' ? '<path d="M168 128 Q184 90 194 40" stroke="' + c + '" stroke-width="1.5" fill="none"/>' : '');
    else if (t === 'kama') s = '<path d="M162 150 L172 104" stroke="#8A5A33" stroke-width="6" stroke-linecap="round"/><path d="M172 104 Q190 100 194 116 Q184 108 172 112 Z" fill="' + c + '" stroke="' + shade(c, -0.4) + '"/>' + (glow ? '<circle cx="182" cy="108" r="12" fill="' + glow + '" opacity=".35" filter="url(#' + id + 'gl)"/>' : '');
    else if (t === 'tessen') s = '<path d="M164 142 L140 100 Q164 84 190 98 Z" fill="' + c + '" stroke="' + shade(c, -0.35) + '"/><path d="M164 142 L146 98 M164 142 L156 92 M164 142 L168 90 M164 142 L180 92" stroke="' + shade(c, -0.3) + '" stroke-width="1"/>' + flower(165, 100, 5, '#FFFFFF');
    return s;
  }

  // ---------- Gyvūnėliai (sėdintys, 100×100) ----------
  function pet(sp, color, o) {
    o = o || {};
    var id = uid(), c = color || { kitten: '#E0A060', puppy: '#C98A55', foxcub: '#E0712C', bunny: '#F2EEE8', turtle: '#5FA35A', pandacub: '#F4F2EE', owlet: '#9A7A5A', dragonling: '#4FB38A', axolotl: '#F7A8C4', phoenix: '#FF8A2A' }[sp] || '#C98A55';
    var cd = shade(c, -0.25), cl = shade(c, 0.35), s = '<defs>' + rgrad(id + 'p', cl, c, '.35', '.3', '.8') + '</defs>';
    s += '<ellipse cx="50" cy="94" rx="26" ry="4" fill="#000" opacity=".18"/>';
    var eyesP = function (y, dx, big) { return '<circle cx="' + (50 - dx) + '" cy="' + y + '" r="' + (big || 5.5) + '" fill="#1B1412"/><circle cx="' + (50 + dx) + '" cy="' + y + '" r="' + (big || 5.5) + '" fill="#1B1412"/><circle cx="' + (48.5 - dx) + '" cy="' + (y - 2) + '" r="2" fill="#fff"/><circle cx="' + (48.5 + dx) + '" cy="' + (y - 2) + '" r="2" fill="#fff"/><ellipse cx="' + (50 - dx - 5) + '" cy="' + (y + 7) + '" rx="4" ry="2.4" fill="#FF8FA3" opacity=".45"/><ellipse cx="' + (50 + dx + 5) + '" cy="' + (y + 7) + '" rx="4" ry="2.4" fill="#FF8FA3" opacity=".45"/>'; };
    var body = function () { return '<ellipse cx="50" cy="74" rx="24" ry="20" fill="url(#' + id + 'p)" stroke="' + cd + '"/><ellipse cx="50" cy="78" rx="13" ry="12" fill="' + shade(c, 0.55) + '"/><ellipse cx="38" cy="91" rx="7" ry="4" fill="' + c + '" stroke="' + cd + '"/><ellipse cx="62" cy="91" rx="7" ry="4" fill="' + c + '" stroke="' + cd + '"/>'; };
    var headC = function (y) { return '<circle cx="50" cy="' + (y || 44) + '" r="24" fill="url(#' + id + 'p)" stroke="' + cd + '"/>'; };
    if (sp === 'kitten' || sp === 'foxcub' || sp === 'puppy' || sp === 'pandacub' || sp === 'bunny') {
      if (sp === 'foxcub') s += '<path d="M70 82 Q96 80 90 56 Q84 74 66 72 Z" fill="' + c + '" stroke="' + cd + '"/><path d="M90 56 Q94 66 86 68 Q86 60 90 56 Z" fill="#fff"/>';
      if (sp === 'kitten') s += '<path d="M70 84 Q92 84 88 60" stroke="' + c + '" stroke-width="6" fill="none" stroke-linecap="round"/>';
      s += body();
      if (sp === 'bunny') s += '<path d="M40 30 Q30 -4 40 -6 Q50 -2 48 28 Z M60 30 Q70 -4 60 -6 Q50 -2 52 28 Z" fill="' + c + '" stroke="' + cd + '"/><path d="M41 26 Q36 2 41 2 Q46 4 46 26 Z M59 26 Q64 2 59 2 Q54 4 54 26 Z" fill="#F6B6C2"/>';
      if (sp === 'kitten' || sp === 'foxcub') s += '<path d="M30 34 L28 ' + (sp === 'foxcub' ? 6 : 12) + ' L46 24 Z M70 34 L72 ' + (sp === 'foxcub' ? 6 : 12) + ' L54 24 Z" fill="' + c + '" stroke="' + cd + '"/><path d="M32 30 L31 ' + (sp === 'foxcub' ? 12 : 17) + ' L42 25 Z M68 30 L69 ' + (sp === 'foxcub' ? 12 : 17) + ' L58 25 Z" fill="' + (sp === 'foxcub' ? '#fff' : '#F6B6C2') + '"/>';
      if (sp === 'puppy') s += '<ellipse cx="27" cy="44" rx="8" ry="15" fill="' + cd + '" transform="rotate(18 27 44)"/><ellipse cx="73" cy="44" rx="8" ry="15" fill="' + cd + '" transform="rotate(-18 73 44)"/>';
      if (sp === 'pandacub') s += '<circle cx="31" cy="24" r="8" fill="#2A2321"/><circle cx="69" cy="24" r="8" fill="#2A2321"/>';
      s += headC();
      if (sp === 'pandacub') s += '<ellipse cx="40" cy="44" rx="7" ry="9" fill="#2A2321" transform="rotate(-20 40 44)"/><ellipse cx="60" cy="44" rx="7" ry="9" fill="#2A2321" transform="rotate(20 60 44)"/>';
      if (sp === 'foxcub' || sp === 'kitten' || sp === 'puppy' || sp === 'bunny') s += '<ellipse cx="50" cy="54" rx="11" ry="7" fill="' + shade(c, 0.6) + '"/>';
      s += eyesP(44, 9, sp === 'pandacub' ? 4 : 5.5) + '<path d="M47 52 Q50 50 53 52 Q50 56 47 52 Z" fill="' + (sp === 'kitten' || sp === 'bunny' ? '#E58A9A' : '#2A2321') + '"/><path d="M50 55 Q46 59 43 56 M50 55 Q54 59 57 56" stroke="#2A2321" stroke-width="1.4" fill="none"/>';
    } else if (sp === 'turtle') {
      s += '<ellipse cx="50" cy="72" rx="34" ry="20" fill="#C8B27A" stroke="#8A7646"/><path d="M18 72 Q50 30 82 72 Z" fill="url(#' + id + 'p)" stroke="' + cd + '"/><path d="M38 52 L50 44 L62 52 L58 66 L42 66 Z" fill="none" stroke="' + cd + '" stroke-width="2"/><ellipse cx="14" cy="76" rx="10" ry="8" fill="#9CCB7A" stroke="#5E8A44"/>' +
        '<circle cx="11" cy="74" r="2.4" fill="#1B1412"/><circle cx="10" cy="73" r=".8" fill="#fff"/><ellipse cx="30" cy="90" rx="7" ry="4" fill="#9CCB7A"/><ellipse cx="70" cy="90" rx="7" ry="4" fill="#9CCB7A"/>';
    } else if (sp === 'owlet') {
      s += '<ellipse cx="50" cy="58" rx="30" ry="34" fill="url(#' + id + 'p)" stroke="' + cd + '"/><path d="M24 30 L30 14 L40 28 Z M76 30 L70 14 L60 28 Z" fill="' + cd + '"/><circle cx="38" cy="48" r="12" fill="#FFF6E0" stroke="' + cd + '"/><circle cx="62" cy="48" r="12" fill="#FFF6E0" stroke="' + cd + '"/>' +
        '<circle cx="38" cy="48" r="6" fill="#1B1412"/><circle cx="62" cy="48" r="6" fill="#1B1412"/><circle cx="36" cy="46" r="2" fill="#fff"/><circle cx="60" cy="46" r="2" fill="#fff"/><path d="M46 58 L50 66 L54 58 Z" fill="#F2B705"/><path d="M34 74 q8 6 16 0 q8 6 16 0" stroke="' + cd + '" stroke-width="1.5" fill="none"/><path d="M40 92 l-3 4 M44 92 l0 5 M56 92 l0 5 M60 92 l3 4" stroke="#F2B705" stroke-width="2"/>';
    } else if (sp === 'dragonling' || sp === 'axolotl' || sp === 'phoenix') {
      if (sp === 'phoenix') s += '<path d="M30 64 Q4 50 6 26 Q22 44 34 50 Z M70 64 Q96 50 94 26 Q78 44 66 50 Z" fill="#FFC23D" stroke="#E07A1A"/><path d="M50 92 L36 100 L44 88 Z M50 92 L64 100 L56 88 Z" fill="#E8451A"/>';
      if (sp === 'dragonling') s += '<path d="M28 60 Q8 44 14 30 Q24 44 34 48 Z M72 60 Q92 44 86 30 Q76 44 66 48 Z" fill="' + shade(c, -0.1) + '" stroke="' + cd + '"/><path d="M70 86 Q92 88 90 70 L96 64 Q92 82 74 80 Z" fill="' + c + '" stroke="' + cd + '"/>';
      if (sp === 'axolotl') s += '<path d="M26 34 L10 26 M26 40 L8 40 M26 46 L10 54 M74 34 L90 26 M74 40 L92 40 M74 46 L90 54" stroke="' + shade(c, -0.15) + '" stroke-width="4" stroke-linecap="round"/><path d="M70 86 Q94 90 92 72 Q84 80 72 78 Z" fill="' + c + '" stroke="' + cd + '"/>';
      s += body() + headC(42);
      if (sp === 'dragonling') s += '<path d="M38 22 L34 8 L44 18 Z M62 22 L66 8 L56 18 Z" fill="#F2E3B6" stroke="#B8A26A"/>';
      if (sp === 'phoenix') s += '<path d="M44 20 Q46 4 52 2 Q50 14 56 20 Q60 8 66 10 Q60 18 58 24 Z" fill="#FF5A1F"/>';
      s += eyesP(42, 9) + '<path d="M44 52 Q50 56 56 52" stroke="#2A2321" stroke-width="1.8" fill="none" stroke-linecap="round"/>' + (sp === 'phoenix' ? '<path d="M46 50 L50 56 L54 50 Z" fill="#F2B705"/>' : '');
    }
    return '<svg class="ga-pet ' + (o.cls || '') + '" viewBox="0 -8 100 108" xmlns="http://www.w3.org/2000/svg">' + s + '</svg>';
  }

  // ---------- Namai (200×160) ----------
  function house(t) {
    var s = '<ellipse cx="100" cy="154" rx="86" ry="6" fill="#000" opacity=".2"/>';
    if (t === 'tent') s += '<path d="M20 152 L100 30 L180 152 Z" fill="#E8C27A" stroke="#9A7A3A" stroke-width="2"/><path d="M100 30 L80 152 L120 152 Z" fill="#7A5A2A"/><path d="M100 30 L100 18" stroke="#5B3A22" stroke-width="3"/><path d="M100 18 L118 24 L100 30 Z" fill="#C8372D"/>';
    else if (t === 'hut') s += '<rect x="34" y="78" width="132" height="74" fill="#A8743F" stroke="#5B3A22" stroke-width="2"/><path d="M34 92 h132 M34 108 h132 M34 124 h132 M34 140 h132" stroke="#8A5A2B"/><path d="M20 82 L100 28 L180 82 Z" fill="#7A4E2C" stroke="#4A2E18" stroke-width="2"/><rect x="88" y="110" width="26" height="42" rx="3" fill="#4A2E18"/><rect x="48" y="96" width="24" height="20" fill="#FFE6A8" stroke="#5B3A22"/>';
    else if (t === 'dojo') s += '<rect x="30" y="84" width="140" height="68" fill="#F3E9D5" stroke="#5B3A22" stroke-width="2"/><path d="M50 84 v68 M70 84 v68 M90 84 v68 M110 84 v68 M130 84 v68 M150 84 v68 M30 104 h140 M30 126 h140" stroke="#B9A27A"/><path d="M10 90 Q100 50 190 90 L176 74 Q100 30 24 74 Z" fill="#3A2B2A" stroke="#1E1514" stroke-width="2"/><rect x="80" y="64" width="40" height="16" rx="2" fill="#C8372D"/><circle cx="100" cy="72" r="5" fill="#F7F5EF"/>';
    else if (t === 'pagoda') s += '<rect x="60" y="112" width="80" height="40" fill="#C8372D" stroke="#7A1E18"/><path d="M40 116 Q100 98 160 116 L150 106 Q100 88 50 106 Z" fill="#2A2321"/><rect x="70" y="80" width="60" height="28" fill="#C8372D" stroke="#7A1E18"/><path d="M50 84 Q100 66 150 84 L140 74 Q100 56 60 74 Z" fill="#2A2321"/><rect x="80" y="52" width="40" height="24" fill="#C8372D" stroke="#7A1E18"/><path d="M62 56 Q100 40 138 56 L128 46 Q100 32 72 46 Z" fill="#2A2321"/><path d="M100 32 L100 14" stroke="#E5B53A" stroke-width="3"/><rect x="92" y="126" width="16" height="26" fill="#2A2321"/>';
    else if (t === 'treehouse') s += '<rect x="92" y="70" width="18" height="84" fill="#6B4224"/><ellipse cx="100" cy="46" rx="70" ry="40" fill="#4C8C43"/><ellipse cx="70" cy="40" rx="30" ry="22" fill="#5FA35A"/><rect x="58" y="72" width="86" height="42" fill="#A8743F" stroke="#5B3A22" stroke-width="2"/><path d="M50 76 L101 52 L152 76 Z" fill="#7A4E2C"/><rect x="72" y="84" width="18" height="16" fill="#FFE6A8"/><path d="M80 114 L76 154 M90 114 L86 154 M76 124 h10 M75 134 h10 M74 144 h10" stroke="#5B3A22" stroke-width="2"/>';
    else if (t === 'igloo') s += '<path d="M24 152 Q24 64 100 62 Q176 64 176 152 Z" fill="#F4F8FC" stroke="#9CB8CC" stroke-width="2"/><path d="M40 120 h120 M30 140 h140 M50 100 h100 M70 80 h60 M70 120 v20 M110 120 v20 M90 100 v20 M130 100 v20" stroke="#C3D4E0"/><path d="M78 152 Q78 116 100 116 Q122 116 122 152 Z" fill="#5A7A92"/>';
    else if (t === 'temple') s += '<rect x="26" y="144" width="148" height="8" fill="#9A938A"/><rect x="40" y="94" width="120" height="50" fill="#F3E9D5" stroke="#5B3A22" stroke-width="2"/><path d="M48 94 v50 M152 94 v50" stroke="#C8372D" stroke-width="7"/><path d="M14 98 Q100 54 186 98 L170 82 Q100 40 30 82 Z" fill="#2F5263" stroke="#1A2E3A" stroke-width="2"/><rect x="86" y="110" width="28" height="34" fill="#5B3A22"/><circle cx="100" cy="68" r="6" fill="#E5B53A"/>';
    else if (t === 'castle') s += '<rect x="40" y="70" width="120" height="82" fill="#C9C2B4" stroke="#6E675C" stroke-width="2"/><rect x="24" y="50" width="32" height="102" fill="#B9B2A4" stroke="#6E675C" stroke-width="2"/><rect x="144" y="50" width="32" height="102" fill="#B9B2A4" stroke="#6E675C" stroke-width="2"/><path d="M24 50 v-8 h8 v8 h8 v-8 h8 v8 h8 v-8 M144 50 v-8 h8 v8 h8 v-8 h8 v8 h8 v-8" stroke="#6E675C" stroke-width="2" fill="#B9B2A4"/><path d="M84 152 Q84 116 100 116 Q116 116 116 152 Z" fill="#4A3423"/><path d="M40 22 L40 50 M160 22 L160 50" stroke="#5B3A22" stroke-width="2"/><path d="M40 22 L58 28 L40 34 Z M160 22 L178 28 L160 34 Z" fill="#C8372D"/>';
    return '<svg class="ga-house" viewBox="0 0 200 160" xmlns="http://www.w3.org/2000/svg">' + s + '</svg>';
  }

  // ---------- Dekoracijos (100×100) ----------
  function decor(t) {
    var s = '<ellipse cx="50" cy="96" rx="30" ry="4" fill="#000" opacity=".18"/>';
    switch (t) {
      case 'tatami': s += '<path d="M8 80 L28 58 L92 58 L72 80 Z" fill="#D9C99A" stroke="#3C4A3A" stroke-width="3"/><path d="M14 74 L80 74" stroke="#B49E68"/>'; break;
      case 'makiwara': s += '<rect x="44" y="20" width="12" height="76" fill="#A8743F" stroke="#5B3A22"/><rect x="40" y="26" width="20" height="30" rx="3" fill="#D9B77A" stroke="#8A6A3A"/><path d="M40 32 h20 M40 38 h20 M40 44 h20 M40 50 h20" stroke="#8A6A3A"/>'; break;
      case 'bag': s += '<path d="M50 4 L50 18" stroke="#6E675C" stroke-width="2"/><rect x="34" y="18" width="32" height="68" rx="14" fill="#B42318" stroke="#6E1510" stroke-width="2"/><rect x="34" y="40" width="32" height="6" fill="#2A2321"/><path d="M42 26 v50" stroke="#fff" stroke-width="2" opacity=".25"/>'; break;
      case 'banner': s += '<rect x="22" y="6" width="4" height="90" fill="#5B3A22"/><path d="M26 10 L80 10 L80 56 L26 56 Z" fill="#F7F5EF" stroke="#B9B2A4"/><circle cx="53" cy="33" r="13" fill="#C8372D"/><path d="M47 33 h12 M53 27 v12" stroke="#fff" stroke-width="2.5"/>'; break;
      case 'lantern': s += '<rect x="40" y="70" width="20" height="26" fill="#9A938A"/><rect x="30" y="62" width="40" height="8" fill="#B9B2A4"/><rect x="36" y="40" width="28" height="22" fill="#C9C2B4" stroke="#6E675C"/><rect x="42" y="46" width="16" height="10" fill="#FFD36B" class="ga-glow"/><path d="M26 40 L50 22 L74 40 Z" fill="#9A938A" stroke="#6E675C"/>'; break;
      case 'bonsai': s += '<path d="M28 78 h44 l-6 18 h-32 Z" fill="#7A4E2C"/><path d="M50 78 Q46 58 56 46 Q46 44 40 52" stroke="#5B3A22" stroke-width="5" fill="none"/><ellipse cx="58" cy="40" rx="22" ry="13" fill="#3E8E5A"/><ellipse cx="36" cy="50" rx="14" ry="9" fill="#4FA35A"/>'; break;
      case 'koi': s += '<ellipse cx="50" cy="80" rx="44" ry="16" fill="#3E7FA8" stroke="#8A938A" stroke-width="4"/><path d="M36 78 q8 -6 16 0 q-8 6 -16 0 l-6 -4 l0 8 Z" fill="#FF8A2A"/><path d="M62 84 q8 -6 16 0 q-8 6 -16 0 l-6 -4 l0 8 Z" fill="#F7F5EF"/><ellipse cx="50" cy="76" rx="30" ry="6" fill="#fff" opacity=".18"/>'; break;
      case 'weapons': s += '<rect x="18" y="86" width="64" height="8" fill="#5B3A22"/><rect x="18" y="30" width="64" height="6" fill="#5B3A22"/><path d="M28 24 L30 90 M44 20 L44 90 M60 24 L58 90 M74 28 L72 90" stroke="#A9743F" stroke-width="4"/><path d="M60 24 l-4 -8 M60 24 l4 -8" stroke="#C9D1D9" stroke-width="2"/>'; break;
      case 'torii': s += '<rect x="22" y="30" width="8" height="66" fill="#C8372D"/><rect x="70" y="30" width="8" height="66" fill="#C8372D"/><path d="M10 22 Q50 14 90 22 L88 30 Q50 22 12 30 Z" fill="#2A2321"/><rect x="16" y="38" width="68" height="6" fill="#C8372D"/>'; break;
      case 'trophy': s += '<rect x="14" y="26" width="72" height="70" fill="#7A4E2C" stroke="#4A2E18"/><path d="M14 58 h72" stroke="#4A2E18" stroke-width="3"/><path d="M28 50 h14 l-2 -12 q-5 -6 -10 0 Z" fill="#E5B53A"/><path d="M56 50 h12 l-2 -10 q-4 -5 -8 0 Z" fill="#C0C6CE"/><circle cx="36" cy="80" r="7" fill="#C77B3B"/><circle cx="62" cy="80" r="7" fill="#E5B53A"/>'; break;
      case 'campfire': s += '<path d="M24 92 L76 82 M24 82 L76 92" stroke="#6B4224" stroke-width="6" stroke-linecap="round"/><g class="ga-flicker"><path d="M50 84 Q30 66 44 44 Q46 58 54 56 Q50 40 60 30 Q66 54 68 66 Q70 80 50 84 Z" fill="#FF7A1A"/><path d="M50 84 Q40 72 48 60 Q52 70 56 66 Q62 76 50 84 Z" fill="#FFD36B"/></g>'; break;
      case 'bench': s += '<rect x="12" y="60" width="76" height="10" rx="2" fill="#A8743F" stroke="#5B3A22"/><rect x="18" y="70" width="6" height="26" fill="#5B3A22"/><rect x="76" y="70" width="6" height="26" fill="#5B3A22"/>'; break;
      case 'flowers': s += '<rect x="14" y="78" width="72" height="18" rx="4" fill="#7A4E2C"/>'; for (var i = 0; i < 7; i++) s += '<path d="M' + (20 + i * 10) + ' 78 v-14" stroke="#4C8C43" stroke-width="2"/>' + flower(20 + i * 10, 62, 5, ['#F2A3BC', '#F7D84A', '#B38AE6', '#FF8A2A'][i % 4]); break;
      case 'snowman': s += '<circle cx="50" cy="74" r="20" fill="#FBFBFD" stroke="#C3D4E0"/><circle cx="50" cy="42" r="14" fill="#FBFBFD" stroke="#C3D4E0"/><circle cx="45" cy="40" r="2" fill="#2A2321"/><circle cx="55" cy="40" r="2" fill="#2A2321"/><path d="M50 44 l10 3 l-10 1 Z" fill="#FF8A2A"/><rect x="38" y="22" width="24" height="10" fill="#2A2321"/><rect x="34" y="30" width="32" height="4" fill="#2A2321"/><path d="M36 54 h28" stroke="#C8372D" stroke-width="5"/>'; break;
      case 'pumpkin': s += '<ellipse cx="50" cy="74" rx="30" ry="20" fill="#F08A24" stroke="#B85E10"/><path d="M50 56 v36 M34 60 q-6 14 0 30 M66 60 q6 14 0 30" stroke="#B85E10" fill="none"/><path d="M50 56 q2 -10 8 -12" stroke="#4C8C43" stroke-width="4" fill="none"/>'; break;
      case 'shishi': s += '<rect x="20" y="84" width="60" height="10" rx="5" fill="#8A938A"/><path d="M30 84 L34 40 M70 84 L66 40" stroke="#7FA35A" stroke-width="6"/><path d="M30 46 L74 60" stroke="#9CCB7A" stroke-width="8" stroke-linecap="round"/><ellipse cx="74" cy="84" rx="12" ry="4" fill="#3E7FA8"/>'; break;
      case 'rocks': s += '<ellipse cx="50" cy="84" rx="44" ry="12" fill="#E8E1D0"/><path d="M14 84 q36 -8 72 0 M18 90 q32 -6 64 0" stroke="#CFC6B0" fill="none"/><ellipse cx="38" cy="78" rx="12" ry="9" fill="#8A938A"/><ellipse cx="62" cy="80" rx="9" ry="7" fill="#6E7670"/>'; break;
      case 'tea': s += '<ellipse cx="50" cy="70" rx="38" ry="10" fill="#7A4E2C"/><rect x="24" y="70" width="6" height="22" fill="#5B3A22"/><rect x="70" y="70" width="6" height="22" fill="#5B3A22"/><path d="M40 66 q10 -14 20 0 Z" fill="#3E8E5A"/><rect x="38" y="62" width="24" height="6" rx="3" fill="#2F6E45"/><path d="M48 54 q2 -6 0 -10 M54 54 q2 -6 0 -10" stroke="#C9C2B4" fill="none" class="ga-steam"/>'; break;
      case 'sakura_tree': s += '<path d="M48 96 Q46 64 52 44 Q40 34 30 40 M52 50 Q64 38 74 42" stroke="#5B3A22" stroke-width="6" fill="none"/>'; for (var j = 0; j < 12; j++) s += '<circle cx="' + (24 + (j * 37) % 58) + '" cy="' + (22 + (j * 17) % 30) + '" r="9" fill="' + (j % 2 ? '#F2A3BC' : '#F9C7D6') + '"/>'; break;
      case 'drum': s += '<ellipse cx="50" cy="40" rx="30" ry="10" fill="#E8DCC2" stroke="#7A4E2C" stroke-width="2"/><path d="M20 40 v34 q30 16 60 0 v-34" fill="#9E2A22" stroke="#5B1510" stroke-width="2"/><path d="M24 58 q26 10 52 0" stroke="#E5B53A" stroke-width="2" fill="none"/><path d="M30 92 L40 74 M70 92 L60 74" stroke="#5B3A22" stroke-width="4"/>'; break;
      case 'crystal': s += '<path d="M50 6 L70 40 L58 92 L42 92 L30 40 Z" fill="#9BD8F2" stroke="#4A8AB0" stroke-width="2" class="ga-glow"/><path d="M50 6 L50 92 M30 40 L70 40" stroke="#fff" opacity=".6"/>'; break;
      case 'statue': s += '<rect x="30" y="80" width="40" height="14" fill="#8A938A"/><circle cx="50" cy="28" r="12" fill="#E5B53A" stroke="#8A6400"/><path d="M38 80 L42 44 Q50 38 58 44 L62 80 Z" fill="#E5B53A" stroke="#8A6400"/><path d="M58 50 L76 42" stroke="#E5B53A" stroke-width="6" stroke-linecap="round"/>'; break;
    }
    return '<svg class="ga-decor" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">' + s + '</svg>';
  }

  // ---------- Daikto ženkliukas inventoriui ----------
  function itemIcon(key, it, cfg) {
    if (!it) return '';
    var base = { species: 'human', gender: 'm', look: { skin: '#D7DBE0', hair: '#9AA1AA' }, equipped: { kimono: 'k_white', belt: 'b_plain' }, belt: 1 };
    var c = function (eq, crop, only) { var x = JSON.parse(JSON.stringify(base)); Object.assign(x.equipped, eq); return hero(x, { crop: crop, only: only, ghost: true }); };
    switch (it.slot) {
      case 'weapon': var wid = uid(); return '<svg viewBox="58 25 210 210" xmlns="http://www.w3.org/2000/svg"><defs><filter id="' + wid + 'gl" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="4"/></filter></defs><g transform="rotate(' + (it.a.t === 'bo' || it.a.t === 'jo' ? 38 : 20) + ' 163 130)">' + weapon(it.a, wid, 'behind') + weapon(it.a, wid, 'front') + '</g></svg>';
      case 'kimono': var k = JSON.parse(JSON.stringify(base)); k.equipped.kimono = key; return hero(k, { crop: [50, 108, 100, 100], only: 'kimono', ghost: true });
      case 'belt': var bb = JSON.parse(JSON.stringify(base)); bb.equipped.belt = key; bb.belt = 10; return hero(bb, { crop: [60, 150, 80, 60], only: 'belt', ghost: true });
      case 'head': case 'face': case 'hair': var e = {}; e[it.slot] = key; return c(e, [36, -10, 128, 140], it.slot);
      case 'neck': return c({ neck: key }, [64, 104, 72, 60], 'neck');
      case 'hands': return c({ hands: key }, [120, 110, 60, 50], 'hands');
      case 'back': return c({ back: key }, [10, 0, 180, 240], 'back');
      case 'aura': return c({ aura: key }, [0, 0, 200, 240], 'aura');
      case 'paint': return '<svg viewBox="0 0 40 40"><defs>' + rgrad('pc' + key, shade(it.a.c, 0.5), it.a.c) + '</defs><circle cx="20" cy="20" r="16" fill="url(#pc' + key + ')" stroke="' + shade(it.a.c, -0.35) + '"/><path d="M12 14 q6 -6 14 -2" stroke="#fff" stroke-width="2.5" fill="none" opacity=".7"/></svg>';
      case 'bg': return '<svg viewBox="0 0 160 100"><image href="' + sceneUrl(it.a.sc) + '" x="0" y="0" width="160" height="100" preserveAspectRatio="xMidYMax slice"/></svg>';
      case 'house': return house(it.a.t);
      case 'decor': return decor(it.a.t);
      case 'species': return hero({ species: it.a.sp, gender: 'm', look: {}, equipped: { kimono: 'k_white' }, belt: 1 }, { crop: [20, -16, 160, 150] });
      case 'egg': return '<svg viewBox="0 0 40 46"><ellipse cx="20" cy="26" rx="15" ry="18" fill="#FFF6E0" stroke="#C9B48E"/><path d="M8 26 l6 -4 l6 4 l6 -4 l6 4" stroke="' + ({ owlet: '#9A7A5A', dragonling: '#4FB38A', axolotl: '#F7A8C4', phoenix: '#FF8A2A' }[it.a.pet] || '#888') + '" stroke-width="3" fill="none"/>' + star(28, 14, 3, '#F2C14E') + '</svg>';
    }
    return '';
  }
  function sceneUrl(sc) { return ((window.GAME && GAME.catalog && GAME.catalog.scenes) || '/img/temos/') + sc + '.svg'; }

  window.GameArt = { hero: hero, pet: pet, house: house, decor: decor, itemIcon: itemIcon, sceneUrl: sceneUrl, shade: shade, star: star };
})();
