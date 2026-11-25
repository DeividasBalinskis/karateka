// Ekranas salėje (projektorius / TV): kiekvieno tatami dabartinė kova su laikmačiu ir kitos kovos

import { api, html, raw, render, $, poll, onLeave } from './core.js';
import { sideScore } from './components.js';
import { tickTimers } from './views-public.js';

export async function screenPage(main, { id }) {
  document.body.classList.add('screen');
  main.className = 'screen-wrap';
  const draw = async () => {
    const d = await api(`tournaments/${id}/live?next=4`, { quiet: true });
    const cols = d.tatami.length;
    render(main, html`
      <div class="screen-head"><div><div class="eyebrow" style="color:#C9A6B8">Tiesiogiai</div><h1>${d.tournament.title}</h1></div>
        <div class="clock" id="clock"></div></div>
      <div class="screen-grid" style="--cols:${cols > 4 ? 4 : cols}">${d.tatami.map((x) => html`<div class="scr-col">
        <h2><span>TATAMI ${x.tatami}</span>${x.current.length ? raw('<span class="live-dot"></span>') : ''}</h2>
        ${x.current.length ? x.current.slice(0, 1).map(current) : html`<div class="scr-idle">${x.next.length ? 'Ruošiamasi kitai kovai' : 'Kovų nėra'}</div>`}
        ${x.next.length ? html`<ul class="scr-next">${x.next.map((n) => html`<li><div>${n.type === 'match' ? html`${n.aka.name} – ${n.shiro.name}` : n.entry.name}<br><span>${n.category.name} · ${n.label}</span></div></li>`)}</ul>` : ''}
      </div>`)}</div>`);
    tickClock();
  };
  const current = (c) => {
    if (c.type !== 'match') {
      return html`<div class="scr-now"><div class="cat">${c.category.name} · ${c.label}</div>
        <div class="scr-perf"><div class="n">${c.entry.name}</div><div class="c" style="color:#C9C2B3">${c.entry.club || ''}${c.kata ? ' · ' + c.kata : ''}</div></div></div>`;
    }
    const t = c.score?.timer;
    const pen = (s) => (c.score?.[s]?.penalties || []).map((p) => p[0].toUpperCase() + p.slice(1)).join(' ');
    return html`<div class="scr-now"><div class="cat">${c.category.name} · ${c.label}</div>
      ${['aka', 'shiro'].map((s) => html`<div class="scr-side ${s}"><div><div class="n">${c[s].name}</div><div class="c">${c[s].club || ''}</div></div>
        <div class="pts">${sideScore(c, s, c.category) || '0'}<small>${pen(s)}</small></div></div>`)}
      ${t && t.duration ? html`<div class="scr-timer" data-timer='${JSON.stringify(t)}'></div>` : ''}</div>`;
  };
  const tickClock = () => {
    const el = $('#clock', main);
    if (el) el.textContent = new Date().toLocaleTimeString('lt-LT', { hour: '2-digit', minute: '2-digit' });
  };
  await draw();
  onLeave(poll(draw, 2000));
  onLeave(tickTimers(main, true));
}
