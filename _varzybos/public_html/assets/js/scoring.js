// Sekretoriaus pultas vienai kovai: kumite (ippon / waza-ari, baudos, laikmatis), vėliavėlės, fuku-go, komandos

import { api, state, html, raw, render, $, $$, on, toast, go, confirmBox, busy, fmtTimer, serverNow, onLeave } from './core.js';

const PEN = { keikoku: 'Keikoku', chui: 'Chui', hansoku: 'Hansoku', shikkaku: 'Shikkaku', jogai: 'Jogai' };
const blankSide = () => ({ ippon: 0, wazaari: 0, penalties: [], flags: 0, kata_flags: 0, bouts: 0, points: 0 });

let audioCtx = null;
function beep() {
  try {
    audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
    const o = audioCtx.createOscillator();
    const g = audioCtx.createGain();
    o.frequency.value = 880;
    o.connect(g); g.connect(audioCtx.destination);
    g.gain.setValueAtTime(0.25, audioCtx.currentTime);
    o.start(); o.stop(audioCtx.currentTime + 0.9);
  } catch (e) { /* be garso */ }
}

export async function matchPage(main, { mid }, query) {
  if (!state.user) { const e = new Error('Reikia prisijungti.'); e.status = 401; throw e; }
  main.classList.add('score-page');
  let { match: m, category: cat } = await api('matches/' + mid);
  const back = query.back || `#/t/${cat.tournament_id}/c/${cat.id}`;
  const J = cat.judges;
  const type = cat.match_type;
  const target = cat.kumite_target;

  let s = m.score ? JSON.parse(JSON.stringify(m.score)) : {};
  s.aka = Object.assign(blankSide(), s.aka || {});
  s.shiro = Object.assign(blankSide(), s.shiro || {});
  if (!s.timer || !s.timer.duration) s.timer = { duration: cat.match_seconds * 1000, remaining: cat.match_seconds * 1000, running: false };
  if (type === 'fukugo' && !s.phase) s.phase = 'kata';
  // Laikmatis tęsiasi, jei buvo įjungtas kitame įrenginyje
  let runStart = null;
  let runFrom = s.timer.remaining;
  if (s.timer.running && s.timer.server_at) {
    runFrom = Math.max(0, s.timer.remaining - (serverNow() - s.timer.server_at));
    runStart = Date.now();
  } else s.timer.running = false;

  const history = [];
  const picked = { winner: false, result: false };
  const snapshot = () => history.push(JSON.stringify({ aka: s.aka, shiro: s.shiro, phase: s.phase }));
  let saveTimer = null;
  const save = (now = false) => {
    if (m.status === 'done') return;
    clearTimeout(saveTimer);
    const run = () => api(`matches/${m.id}/score`, { method: 'PUT', body: { score: currentScore() }, quiet: true })
      .then((r) => { m.status = r.match.status; }).catch((e) => toast('Neišsaugota: ' + e.message, 'err'));
    if (now) run(); else saveTimer = setTimeout(run, 350);
  };
  const remaining = () => (runStart ? Math.max(0, runFrom - (Date.now() - runStart)) : runFrom);
  const currentScore = () => ({ ...s, timer: { duration: s.timer.duration, remaining: Math.round(remaining()), running: !!runStart } });
  const units = (side) => s[side].ippon * 2 + s[side].wazaari;
  const other = (side) => (side === 'aka' ? 'shiro' : 'aka');

  /** Ką siūlo taisyklės: [laimėtojas, būdas, paaiškinimas] arba null */
  const suggestion = () => {
    for (const side of ['aka', 'shiro']) {
      if (s[side].penalties.includes('shikkaku')) return [other(side), 'shikkaku', `${name(side)}: shikkaku`];
      if (s[side].penalties.includes('hansoku')) return [other(side), 'hansoku', `${name(side)}: hansoku`];
    }
    if (type === 'flags') {
      if (s.aka.flags + s.shiro.flags === J && s.aka.flags !== s.shiro.flags) {
        const w = s.aka.flags > s.shiro.flags ? 'aka' : 'shiro';
        return [w, 'flags', `${s[w].flags} vėliavėlės už ${name(w)}`];
      }
      return null;
    }
    if (type === 'team') {
      if (s.aka.bouts !== s.shiro.bouts) { const w = s.aka.bouts > s.shiro.bouts ? 'aka' : 'shiro'; return [w, 'points', `Daugiau laimėtų kovų: ${name(w)}`]; }
      if (s.aka.points !== s.shiro.points) { const w = s.aka.points > s.shiro.points ? 'aka' : 'shiro'; return [w, 'points', `Kovos lygios, daugiau taškų: ${name(w)}`]; }
      return null;
    }
    for (const side of ['aka', 'shiro']) {
      if (units(side) >= target) return [side, 'points', `${name(side)} pasiekė ${target === 2 ? 'ippon' : target / 2 + ' ippon'}`];
    }
    if (remaining() <= 0) {
      if (units('aka') !== units('shiro')) {
        const w = units('aka') > units('shiro') ? 'aka' : 'shiro';
        return [w, 'points', `Laikas baigėsi, pirmauja ${name(w)}`];
      }
      if (type === 'fukugo' && s.aka.kata_flags !== s.shiro.kata_flags) {
        const w = s.aka.kata_flags > s.shiro.kata_flags ? 'aka' : 'shiro';
        return [w, 'hantei', `Kumite lygiosios, kata laimėjo ${name(w)} - teisėjų sprendimas (hantei)`];
      }
      return [null, 'hantei', 'Laikas baigėsi, lygu - teisėjų sprendimas (hantei)' + (m.stage === 'pool' ? ' arba lygiosios' : '')];
    }
    return null;
  };
  const name = (side) => m[side].name;

  const sideHtml = (side) => {
    const x = s[side];
    const lbl = side === 'aka' ? 'AKA' : 'SHIRO';
    const who = html`<div class="label">${lbl}</div><div class="who"><div class="n">${m[side].name}</div><div class="c">${m[side].club || ''}</div></div>`;
    const flagsRow = (field) => html`<div class="flag-row">${Array.from({ length: J + 1 }, (_, i) => html`<button class="sbtn ${x[field] === i ? 'sel' : ''}" data-flags="${side}:${field}:${i}">${i}</button>`)}</div>`;
    if (type === 'flags' || (type === 'fukugo' && s.phase === 'kata')) {
      const field = type === 'flags' ? 'flags' : 'kata_flags';
      return html`<div class="half ${side}">${who}<div class="points"><span class="big">${x[field]}</span><span class="unit">VĖLIAVĖLĖS</span></div>${flagsRow(field)}
        <div class="btns"><button class="sbtn pen" data-act="kiken:${side}">Kiken</button><button class="sbtn pen" data-act="pen:${side}:shikkaku">Shikkaku</button></div></div>`;
    }
    if (type === 'team') {
      return html`<div class="half ${side}">${who}
        <div class="points"><span class="big">${x.bouts}</span><span class="unit">LAIMĖTOS KOVOS</span></div>
        <div class="btns"><button class="sbtn main" data-act="inc:${side}:bouts:1">+ Kova</button><button class="sbtn" data-act="inc:${side}:bouts:-1">− Kova</button></div>
        <div class="points"><span class="big" style="font-size:2rem">${x.points}</span><span class="unit">TAŠKAI (waza-ari)</span></div>
        <div class="btns"><button class="sbtn" data-act="inc:${side}:points:1">+1</button><button class="sbtn" data-act="inc:${side}:points:-1">−1</button></div></div>`;
    }
    return html`<div class="half ${side}">${who}
      <div class="points"><span class="big">${x.ippon}</span><span class="unit">IPPON</span><span class="big">${x.wazaari}</span><span class="unit">WAZA-ARI</span></div>
      <div class="btns"><button class="sbtn main" data-act="inc:${side}:ippon:1">+ Ippon</button><button class="sbtn main" data-act="inc:${side}:wazaari:1">+ Waza-ari</button></div>
      <div class="btns three">${['keikoku', 'chui', 'hansoku'].map((p) => html`<button class="sbtn pen ${x.penalties.includes(p) ? 'on' : ''}" data-act="pen:${side}:${p}">${PEN[p]}</button>`)}
        <button class="sbtn pen" data-act="pen:${side}:jogai">Jogai</button><button class="sbtn pen" data-act="kiken:${side}">Kiken</button><button class="sbtn pen ${x.penalties.includes('shikkaku') ? 'on' : ''}" data-act="pen:${side}:shikkaku">Shikkaku</button></div>
      <div class="pens">${x.penalties.map((p) => html`<span class="p">${PEN[p]}</span>`)}</div></div>`;
  };

  const draw = () => {
    const sug = suggestion();
    const done = m.status === 'done';
    const showTimer = type === 'kumite' || (type === 'fukugo' && s.phase === 'kumite') || type === 'team';
    const results = type === 'flags' ? ['flags', 'kiken', 'shikkaku'] : ['points', 'hantei', 'hansoku', 'kiken', 'shikkaku'];
    // Kol sekretorius pats nepasirinko - rodomas taisyklių pasiūlymas
    const selWinner = (picked.winner ? $('[name=winner]:checked', main)?.value : null) || (sug ? sug[0] : '') || '';
    const selRes = (picked.result ? $('[name=result]:checked', main)?.value : null) || (sug ? sug[1] : results[0]);
    render(main, html`
      <div class="score-head"><div><a class="small" href="${back}">← Atgal</a>
        <h1>${cat.name}</h1><div class="small muted">${m.label}${cat.tatami ? ' · Tatami ' + cat.tatami : ''} · ${state.meta.match_types[type]}${type === 'kumite' || type === 'fukugo' ? ' · ' + state.meta.kumite_rules[cat.kumite_rule] : ''}</div></div>
        ${done ? html`<span class="badge badge-dark">Baigta: ${m.winner === 'draw' ? 'lygiosios' : name(m.winner)} (${m.result_label})</span>` : m.status === 'running' ? html`<span class="badge badge-red"><span class="live-dot"></span>Vyksta</span>` : ''}
      </div>
      ${type === 'fukugo' ? html`<div class="phase-tabs tabs"><button class="${s.phase === 'kata' ? 'active' : ''}" data-act="phase:kata">1. Kata (vėliavėlės)</button><button class="${s.phase === 'kumite' ? 'active' : ''}" data-act="phase:kumite">2. Kumite</button>
        <span class="small muted" style="align-self:center">Kata: AKA ${s.aka.kata_flags} – ${s.shiro.kata_flags} SHIRO</span></div>` : ''}
      <div class="board" style="${done ? 'pointer-events:none;opacity:0.75' : ''}">${sideHtml('aka')}${sideHtml('shiro')}</div>
      ${showTimer ? html`<div class="panel timer-bar">
        <button class="btn btn-ghost btn-sm" data-act="time:-5">−5 s</button>
        <span class="t" id="clock">${fmtTimer(remaining())}</span>
        <button class="btn btn-ghost btn-sm" data-act="time:5">+5 s</button>
        <button class="btn ${runStart ? 'btn-danger' : 'btn-primary'}" data-act="timer" ${done ? 'disabled' : ''}>${runStart ? '⏸ Sustabdyti' : '▶ Hajime'}</button>
        <button class="btn btn-ghost btn-sm" data-act="time:reset">↺</button>
      </div>` : html`<div style="height:14px"></div>`}
      <div class="row between no-print" style="margin-bottom:12px"><button class="btn btn-ghost btn-sm" data-act="undo" ${history.length && !done ? '' : 'disabled'}>↶ Atšaukti paskutinį veiksmą</button></div>
      ${done ? html`<div class="panel decision"><p>Kova baigta. Jei rezultatas įvestas klaidingai, jį galima atšaukti (jei tolesnė kova dar neprasidėjo).</p>
          <div class="row" style="margin-top:12px"><button class="btn btn-danger" data-act="reset">Atšaukti rezultatą</button><a class="btn btn-primary" href="${back}">Atgal</a></div></div>`
        : html`<div class="panel decision">
        ${sug ? html`<div class="suggest" style="margin-bottom:12px">💡 ${sug[2]}</div>` : ''}
        <div class="row" style="align-items:flex-start;gap:18px">
          <div><div class="hint" style="margin-bottom:6px">Laimėtojas</div><div class="seg win">
            <label><input type="radio" name="winner" value="aka" ${selWinner === 'aka' ? 'checked' : ''}><span>AKA · ${m.aka.name}</span></label>
            <label><input type="radio" name="winner" value="shiro" ${selWinner === 'shiro' ? 'checked' : ''}><span>SHIRO · ${m.shiro.name}</span></label>
            ${m.stage === 'pool' ? html`<label><input type="radio" name="winner" value="draw" ${selWinner === 'draw' ? 'checked' : ''}><span>Lygiosios</span></label>` : ''}</div></div>
          <div><div class="hint" style="margin-bottom:6px">Kaip</div><div class="seg">${results.map((r) => html`<label><input type="radio" name="result" value="${r}" ${selRes === r ? 'checked' : ''}><span>${state.meta.results[r]}</span></label>`)}</div></div>
        </div>
        <button class="btn btn-pink btn-block" style="margin-top:14px" data-act="finish">✓ Patvirtinti rezultatą</button></div>`}
    `);
  };

  const change = (fn) => { snapshot(); fn(); draw(); save(); };
  on(main, 'click', '[data-act]', async (e, b) => {
    const [act, a1, a2, a3] = b.dataset.act.split(':');
    if (act === 'inc') change(() => { s[a1][a2] = Math.max(0, s[a1][a2] + Number(a3)); });
    else if (act === 'pen') change(() => {
      const list = s[a1].penalties;
      // Paspaudus tą pačią baudą antrą kartą (išskyrus jogai) - ji nuimama
      if (a2 !== 'jogai' && list.includes(a2)) list.splice(list.indexOf(a2), 1); else list.push(a2);
    });
    else if (act === 'kiken') {
      if (!await confirmBox(`${name(a1)} atsisako tęsti (kiken)? Pergalė atitenka ${name(other(a1))}.`)) return;
      await finish(other(a1), 'kiken', b);
    } else if (act === 'phase') change(() => { s.phase = a1; });
    else if (act === 'timer') toggleTimer();
    else if (act === 'time') {
      if (a1 === 'reset') { if (runStart) toggleTimer(); runFrom = s.timer.duration; }
      else { runFrom = Math.max(0, remaining() + Number(a1) * 1000); if (runStart) runStart = Date.now(); }
      draw(); save(true);
    } else if (act === 'undo') {
      const prev = history.pop();
      if (prev) { Object.assign(s, JSON.parse(prev)); draw(); save(); }
    } else if (act === 'finish') {
      const w = $('[name=winner]:checked', main)?.value;
      const r = $('[name=result]:checked', main)?.value;
      if (!w) { toast('Pasirinkite laimėtoją.', 'err'); return; }
      await finish(w, w === 'draw' ? 'draw' : r, b);
    } else if (act === 'reset') {
      if (!await confirmBox('Atšaukti šios kovos rezultatą?', { danger: true, ok: 'Atšaukti rezultatą' })) return;
      await api(`matches/${m.id}/reset`, { method: 'POST' });
      ({ match: m } = await api('matches/' + mid));
      draw();
    }
  });
  on(main, 'click', '[data-flags]', (e, b) => {
    const [side, field, n] = b.dataset.flags.split(':');
    change(() => {
      s[side][field] = Number(n);
      if (J % 2 === 1) s[other(side)][field] = J - Number(n);   // nelyginis teisėjų skaičius - lygiųjų nebūna
    });
  });
  on(main, 'change', '[name=winner], [name=result]', (e, el) => { picked[el.name] = true; });

  function toggleTimer() {
    if (runStart) { runFrom = remaining(); runStart = null; }
    else { if (remaining() <= 0) runFrom = s.timer.duration; runStart = Date.now(); }
    draw();
    save(true);
  }
  async function finish(winner, result, btn) {
    if (runStart) { runFrom = remaining(); runStart = null; }
    await busy(btn, () => api(`matches/${m.id}/finish`, { method: 'POST', body: { winner, result, score: currentScore() } }));
    toast(winner === 'draw' ? 'Lygiosios.' : 'Laimėjo ' + name(winner) + '.');
    go(back);
  }

  draw();
  // Laikmačio rodymas
  let wasZero = remaining() <= 0;
  const iv = setInterval(() => {
    const el = $('#clock', main);
    if (!el) return;
    const left = remaining();
    el.textContent = fmtTimer(left);
    el.classList.toggle('low', left > 0 && left <= 15000);
    el.classList.toggle('zero', left <= 0);
    if (left <= 0 && runStart) {
      runFrom = 0; runStart = null;
      if (!wasZero) beep();
      draw(); save(true);
    }
    wasZero = left <= 0;
  }, 200);
  onLeave(() => { clearInterval(iv); clearTimeout(saveTimer); });
  void raw; void $$;
}
