// Pakartotinai naudojami vaizdai: lentelė, kova, grupė, balai, rezultatai

import { html, raw, esc, medal, catStatusBadge, genderLabel, state } from './core.js';

/** Trumpas kovos rezultatas vienai pusei: "1I 1W", vėliavėlės, laimėtos kovos */
export function sideScore(m, side, cat) {
  const s = m.score?.[side];
  if (!s) return '';
  const type = cat?.match_type || m.category?.match_type;
  if (type === 'flags') return s.flags || m.status === 'done' ? (s.flags || 0) + ' v.' : '';
  if (type === 'team') return (s.bouts ?? 0) + ' k.';
  const parts = [];
  if (s.ippon) parts.push(s.ippon + 'I');
  if (s.wazaari) parts.push(s.wazaari + 'W');
  if (type === 'fukugo' && (s.kata_flags || s.kata_flags === 0) && m.status !== 'ready') {
    if (s.kata_flags) parts.unshift('k' + s.kata_flags);
  }
  return parts.join(' ') || (m.status === 'done' || m.status === 'running' ? '0' : '');
}

const srcLabel = (src, byId) => {
  if (!src || src === 'E') return '';
  if (src === 'BYE') return '—';
  const [k, id] = src.split(':');
  const m = byId[id];
  const nr = m ? m._nr : id;
  return (k === 'W' ? 'Laimėtojas ' : 'Pralaimėjęs ') + '#' + nr;
};

/** Viena kova (kortelė lentelėje) */
export function matchCard(m, cat, { byId = {}, clickable = false } = {}) {
  const side = (s) => {
    const p = m[s];
    const win = m.winner === s;
    const lose = m.winner && m.winner !== s && m.winner !== 'draw';
    const who = p.id
      ? html`<span class="n">${p.name}</span><span class="c">${p.club && p.club !== p.name ? p.club : ''}</span>`
      : html`<span class="n tbd">${p.src === 'BYE' || (m.result === 'bye' && !p.id) ? '—' : srcLabel(p.src, byId) || '…'}</span>`;
    return html`<div class="side ${s} ${win ? 'win' : ''} ${lose ? 'lose' : ''}">
      <span class="mark"></span><span class="who">${who}</span><span class="sc">${m.result === 'bye' ? '' : sideScore(m, s, cat)}</span></div>`;
  };
  const cls = ['match', m.status === 'running' ? 'running' : '', m.result === 'bye' ? 'bye' : '', clickable && m.status !== 'waiting' && m.result !== 'bye' ? 'clickable' : ''].join(' ');
  return html`<div class="${cls}" data-match="${m.id}" title="${m.label}${m.result_label ? ' · ' + m.result_label : ''}">
    ${side('aka')}${side('shiro')}
    ${m.status === 'running' ? raw('<span class="tag"><span class="live-dot"></span></span>') : ''}
  </div>`;
}

/** Atkrintamųjų lentelė + repechage / kova dėl 3 vietos */
export function bracketView(matches, rounds, cat, opts = {}) {
  const main = matches.filter((m) => m.stage === 'main');
  const byId = {};
  // Kovų numeriai: eilės tvarka, be "bye"
  let nr = 0;
  [...matches].sort((a, b) => a.sort - b.sort).forEach((m) => {
    m._nr = m.result === 'bye' ? '' : ++nr;
    byId[m.id] = m;
  });
  const cols = [];
  for (let r = 1; r <= rounds; r++) {
    const list = main.filter((m) => m.round === r).sort((a, b) => a.position - b.position);
    const title = list[0]?.label || r + ' ratas';
    cols.push(html`<div class="b-round"><div class="b-round-title">${title}</div><div class="b-slots">
      ${list.map((m) => html`<div class="b-slot ${m.result === 'bye' ? 'empty-slot' : ''}">${matchCard(m, cat, { byId, clickable: opts.clickable })}</div>`)}
    </div></div>`);
  }
  const extra = [];
  const bronze = matches.filter((m) => m.stage === 'bronze');
  if (bronze.length) {
    extra.push(html`<div><div class="b-round-title" style="text-align:left">Kova dėl 3 vietos</div>
      <div class="ladder">${bronze.map((m) => matchCard(m, cat, { byId, clickable: opts.clickable }))}</div></div>`);
  }
  const rep = matches.filter((m) => m.stage === 'repechage');
  for (const side of [0, 1]) {
    const ladder = rep.filter((m) => m.position === side).sort((a, b) => a.round - b.round);
    if (!ladder.length) continue;
    extra.push(html`<div><div class="b-round-title" style="text-align:left">Repechage ${side ? 'B' : 'A'}</div>
      <div class="ladder">${ladder.map((m, i) => html`${i ? raw('<span class="arrow">→</span>') : ''}${matchCard(m, cat, { byId, clickable: opts.clickable })}`)}
      <span class="arrow">→</span><span class="medal m3">3</span></div></div>`);
  }
  if (cat.third_place === 'repechage' && !rep.length && rounds >= 2) {
    extra.push(html`<p class="small muted">Repechage kovos atsiras, kai paaiškės abu finalininkai.</p>`);
  }
  const legend = { kumite: 'I - ippon, W - waza-ari', fukugo: 'k - kata vėliavėlės, I - ippon, W - waza-ari', flags: 'v. - teisėjų vėliavėlės', team: 'k. - laimėtos kovos' }[cat.match_type];
  return html`<div class="bracket-scroll"><div class="bracket">${cols}</div>
    ${extra.length ? html`<div class="extra-brackets">${extra}</div>` : ''}</div>
    <p class="tiny muted" style="margin-top:8px"><span style="display:inline-block;width:10px;height:10px;background:var(--aka);border-radius:2px;vertical-align:-1px"></span> AKA (raudonas) · <span style="display:inline-block;width:10px;height:10px;background:#fff;border:1px solid #999;border-radius:2px;vertical-align:-1px"></span> SHIRO (baltas)${legend ? ' · ' + legend : ''}</p>`;
}

/** Grupė: lentelė ir kovų sąrašas */
export function poolView(standings, matches, cat, opts = {}) {
  const byRound = {};
  matches.forEach((m) => { (byRound[m.round] = byRound[m.round] || []).push(m); });
  return html`<div class="table-wrap"><table class="table">
    <thead><tr><th>Vieta</th><th>Dalyvis</th><th class="num">Kovos</th><th class="num">Perg.</th><th class="num">Lyg.</th><th class="num">Pral.</th><th class="num">Ippon</th><th class="num">Waza-ari</th><th class="num">Taškai</th></tr></thead>
    <tbody>${standings.rows.map((r) => html`<tr>
      <td>${standings.complete ? medal(r.rank) : r.rank}</td>
      <td><b>${r.name}</b><div class="tiny muted">${r.club || ''}</div></td>
      <td class="num">${r.played}</td><td class="num">${r.won}</td><td class="num">${r.draw}</td><td class="num">${r.lost}</td>
      <td class="num">${r.ippon}</td><td class="num">${r.wazaari}</td><td class="num"><b>${r.points}</b></td></tr>`)}
    </tbody></table></div>
    <p class="tiny muted" style="margin-top:6px">Pergalė - 2 tšk., lygiosios - 1 tšk. Lygiems: tarpusavio kova, ippon, waza-ari.</p>
    <div class="grid-3" style="margin-top:16px">${Object.entries(byRound).map(([r, list]) => html`<div>
      <div class="b-round-title" style="text-align:left">${r} ratas</div>
      <div class="stack" style="--gap:8px">${list.map((m) => matchCard(m, cat, { clickable: opts.clickable }))}</div></div>`)}</div>`;
}

/** Balais vertinamos kategorijos ratai */
export function scoresView(perf, cat, opts = {}) {
  if (!perf.rounds.length) return html`<p class="muted">Ratai dar nesudaryti.</p>`;
  const judges = cat.judges;
  return perf.rounds.slice().reverse().map((r) => {
    const sorted = r.items.slice().sort((a, b) => a.order_no - b.order_no);
    const isCurrent = r.round === perf.current;
    const done = sorted.filter((p) => p.status === 'done' || p.status === 'dq').length;
    return html`<div class="card panel" style="padding:18px 20px">
      <div class="row between" style="margin-bottom:8px">
        <h3 style="margin:0">${r.name} <span class="badge">${done}/${sorted.length}</span></h3>
        ${r.advance ? html`<span class="badge badge-pink">Į kitą ratą: ${r.advance}</span>` : ''}
      </div>
      <div class="table-wrap"><table class="table">
      <thead><tr><th>#</th><th>Dalyvis</th><th>Kata</th><th>Teisėjų balai (${judges})</th><th class="num">Suma</th>${cat.carry_scores && r.round > 1 ? html`<th class="num">Viso</th>` : ''}<th class="num">Vieta</th>${opts.admin && isCurrent ? html`<th></th>` : ''}</tr></thead>
      <tbody>${sorted.map((p) => html`<tr class="${p.status === 'running' ? 'perf-now' : ''} ${opts.admin && isCurrent ? 'clickable' : ''}" data-perf="${p.id}">
        <td class="mono">${p.order_no}</td>
        <td><b>${p.entry.name}</b>${p.status === 'running' ? raw(' <span class="live-dot"></span>') : ''}<div class="tiny muted">${p.entry.club || ''}</div></td>
        <td class="small">${p.kata || ''}</td>
        <td class="scores-cell">${p.status === 'dq' ? html`<span class="badge badge-red">Diskvalifikuota</span>` : p.scores.map((s, i) => p.dropped.includes(i) ? html`<s>${s.toFixed(1)}</s> ` : html`${s.toFixed(1)} `)}</td>
        <td class="num"><b>${p.total !== null && p.status !== 'dq' ? p.total.toFixed(1) : ''}</b></td>
        ${cat.carry_scores && r.round > 1 ? html`<td class="num">${p.combined !== null ? p.combined.toFixed(1) : ''}</td>` : ''}
        <td class="num">${p.rank ? html`${r.advance && p.rank <= r.advance && perf.current > r.round ? raw('<span class="badge badge-ok" title="Pateko į kitą ratą">→</span> ') : ''}${p.rank}${p.tied ? raw(' <span class="badge badge-warn" title="Lygūs balai">=</span>') : ''}` : ''}</td>
        ${opts.admin && isCurrent ? html`<td class="num"><button class="btn btn-sm btn-ghost" data-perf-edit="${p.id}">${p.status === 'done' || p.status === 'dq' ? 'Taisyti' : 'Vertinti'}</button></td>` : ''}
      </tr>`)}</tbody></table></div></div>`;
  });
}

/** Vietos (podiumas) */
export function resultsList(results, { limit = 99, cols = false } = {}) {
  if (!results.length) return html`<p class="muted small">Vietos dar nenustatytos.</p>`;
  return html`<div class="podium ${cols ? 'cols' : ''}">${results.slice(0, limit).map((r) => html`<div class="p-row">
    ${medal(r.place)}<span class="nm">${r.name}</span><span class="cl">${r.club || ''}</span></div>`)}</div>`;
}

/** Kategorijos aprašas trumpai: "Mergaitės · 8–9 m. · 6 kyu+" */
export function catFacts(c) {
  const f = [];
  const kids = c.age_max !== null && c.age_max < 18;
  f.push(kids ? ({ M: 'Berniukai', F: 'Mergaitės', X: 'Vaikai' }[c.gender]) : genderLabel(c.gender));
  if (c.age_min !== null || c.age_max !== null) f.push(c.age_min !== null && c.age_max !== null ? `${c.age_min}–${c.age_max} m.` : c.age_min !== null ? `${c.age_min}+ m.` : `iki ${c.age_max} m.`);
  if (c.weight_max !== null) f.push(`−${c.weight_max} kg`);
  else if (c.weight_min !== null) f.push(`+${c.weight_min} kg`);
  const b = (l) => state.meta.belts.find((x) => x.level === l)?.kyu;
  if (c.belt_min !== null || c.belt_max !== null) f.push(c.belt_min !== null && c.belt_max !== null ? `${b(c.belt_min)}–${b(c.belt_max)}` : c.belt_min !== null ? `nuo ${b(c.belt_min)}` : `iki ${b(c.belt_max)}`);
  return f.join(' · ');
}

export function formatLabel(c) {
  if (c.format === 'scores') return 'Balai (' + c.judges + ' teisėjai)';
  if (c.format === 'round_robin') return 'Kiekvienas su kiekvienu';
  const mt = { kumite: 'Kumite', flags: 'Vėliavėlės', fukugo: 'Fuku-go', team: 'Komandos' }[c.match_type];
  return 'Lentelė · ' + mt;
}

export function catItem(c, href, extra = '') {
  const pct = c.items_total ? Math.round((c.items_done / c.items_total) * 100) : (c.status === 'finished' ? 100 : 0);
  return html`<a class="cat-item" href="${href}">
    <span class="name">${c.name}</span>
    <span class="sub">${catStatusBadge(c.status)}<span>${c.entries_count} ${c.entry_type === 'individual' ? 'dal.' : c.entry_type === 'pair' ? 'poros' : 'kom.'}</span>
      ${c.tatami ? html`<span>· Tatami ${c.tatami}</span>` : ''}${c.est_start ? html`<span>· ~${c.est_start}</span>` : ''}</span>
    ${c.status === 'running' || c.status === 'finished' ? html`<span class="progress"><span style="width:${pct}%"></span></span>` : ''}
    ${raw(String(extra))}
  </a>`;
}

export const loading = () => raw('<div class="loading"><div class="spinner"></div>Kraunama…</div>');
export { esc };
