// Vieši puslapiai: varžybų sąrašas, varžybos, kategorija, gyvai, registracija, prisijungimas

import {
  api, state, html, raw, render, $, $$, on, toast, go, fmtDate, dateRange, monthShort, dayNum, statusBadge, catStatusBadge,
  poll, onLeave, watchTournament, formData, busy, beltOptions, belt, serverNow, fmtTimer, medal,
} from './core.js';
import { bracketView, poolView, scoresView, resultsList, catFacts, catItem, formatLabel, sideScore } from './components.js';
import { categoryAdminBar, bindCategoryAdmin } from './views-admin.js';

// ---------- Pradžia: varžybų sąrašas ----------
export async function homePage(main) {
  const { tournaments } = await api('tournaments');
  const today = new Date().toISOString().slice(0, 10);
  const upcoming = tournaments.filter((t) => (t.ends_on || t.starts_on) >= today).sort((a, b) => a.starts_on.localeCompare(b.starts_on));
  const past = tournaments.filter((t) => (t.ends_on || t.starts_on) < today);
  const card = (t) => html`<a class="panel t-card" href="#/t/${t.id}">
    <div class="date-box"><span class="d">${dayNum(t.starts_on)}</span><span class="m">${monthShort(t.starts_on)}</span></div>
    <div style="min-width:0">
      <div class="row" style="gap:6px;margin-bottom:6px">${statusBadge(t.status, t.status_label)}${t.registration_open && t.public_registration ? html`<span class="badge badge-pink">Klubai gali registruotis</span>` : ''}</div>
      <h3>${t.title}</h3>
      <div class="muted small">${dateRange(t.starts_on, t.ends_on)}${t.location ? ' · ' + t.location : ''}</div>
      <div class="stats"><span><b>${t.categories_count}</b> kategorijos</span><span><b>${t.athletes_count}</b> sportininkai</span><span><b>${t.clubs_count}</b> klubai</span></div>
    </div></a>`;
  render(main, html`
    <div class="page-head"><div class="eyebrow">Tradicinis karate</div><h1 class="styled">Varžybos</h1>
      <p class="muted">Kategorijos, lentelės, tvarkaraštis ir rezultatai - gyvai.</p></div>
    ${upcoming.length ? html`<h2>Artimiausios ir vykstančios</h2><div class="t-list">${upcoming.map(card)}</div>` : html`<div class="panel card empty">Artimiausių varžybų nėra.</div>`}
    ${past.length ? html`<h2 style="margin-top:30px">Įvykusios</h2><div class="t-list">${past.map(card)}</div>` : ''}
  `);
}

// ---------- Varžybos ----------
const TABS = [
  ['kategorijos', 'Kategorijos'],
  ['dabar', 'Dabar vyksta'],
  ['tvarkarastis', 'Tvarkaraštis'],
  ['rezultatai', 'Rezultatai'],
  ['klubai', 'Klubai'],
];

export async function tournamentPage(main, { id }, query) {
  const d = await api(`tournaments/${id}`);
  const t = d.tournament;
  const tab = query.tab || (t.status === 'running' ? 'dabar' : 'kategorijos');
  const tabs = TABS.slice();
  if (t.registration_open && t.public_registration) tabs.push(['registracija', 'Registracija']);
  render(main, html`
    <a class="back" href="#/">← Visos varžybos</a>
    <div class="page-head">
      <div class="row" style="gap:6px;margin-bottom:8px">${statusBadge(t.status, t.status_label)}${t.registration_open ? html`<span class="badge">Registracija iki ${t.reg_deadline ? fmtDate(t.reg_deadline, false) : 'paskelbimo'}</span>` : ''}</div>
      <h1 class="styled">${t.title}</h1>
      <div class="meta"><span>📅 ${dateRange(t.starts_on, t.ends_on)}</span>${t.location ? html`<span>📍 ${t.location}</span>` : ''}<span>🥋 ${t.tatami_count} tatami · pradžia ${t.day_start}</span></div>
      ${t.description ? html`<p class="small muted" style="margin-top:10px;white-space:pre-line;max-width:760px">${t.description}</p>` : ''}
      <div class="row" style="margin-top:14px">
        <a class="btn btn-sm btn-dark" href="#/t/${t.id}/ekranas" target="_blank">🖥 Ekranas salėje</a>
        ${state.user ? html`<a class="btn btn-sm btn-ghost" href="#/admin/t/${t.id}">⚙ Valdyti</a>` : ''}
        ${t.registration_open && t.public_registration ? html`<a class="btn btn-sm btn-pink" href="#/t/${t.id}?tab=registracija">Registruoti sportininkus →</a>` : ''}
      </div>
    </div>
    <nav class="tabs">${tabs.map(([k, l]) => html`<a href="#/t/${t.id}?tab=${k}" class="${tab === k ? 'active' : ''}">${k === 'dabar' && t.status === 'running' ? raw('<span class="live-dot"></span> ') : ''}${l}</a>`)}</nav>
    <div id="tab"></div>`);
  const box = $('#tab', main);
  const views = { kategorijos: tabCategories, dabar: tabLive, tvarkarastis: tabSchedule, rezultatai: tabResults, klubai: tabClubs, registracija: tabRegistration };
  await (views[tab] || tabCategories)(box, t, d.categories);
}

async function tabCategories(box, t) {
  const draw = async () => {
    const { schedule } = await api(`tournaments/${t.id}/schedule`);
    const all = [...schedule.tatami.flatMap((x) => x.categories), ...schedule.unassigned];
    if (!all.length) { render(box, html`<div class="panel card empty">Kategorijų dar nėra.</div>`); return; }
    const groups = {};
    all.sort((a, b) => a.sort_order - b.sort_order).forEach((c) => { (groups[c.discipline_label] = groups[c.discipline_label] || []).push(c); });
    render(box, Object.entries(groups).map(([g, list]) => html`<div class="cat-group"><h3>${g} <span class="badge">${list.length}</span></h3>
      <div class="cat-list">${list.map((c) => catItem(c, `#/t/${t.id}/c/${c.id}`))}</div></div>`));
  };
  await draw();
  onLeave(watchTournament(t.id, draw, 5000));
}

/** Viena kova "dabar" kortelėje */
function nowMatch(x) {
  const sc = (s) => sideScore(x, s, x.category);
  return html`<div class="now-box"><div class="cat">${x.category.name} · ${x.label}</div>
    <div class="vs"><div class="pl aka"><b>${x.aka.name}</b><span>${x.aka.club || ''}</span></div>
      <div class="score">${sc('aka') || '0'} : ${sc('shiro') || '0'}${timerHtml(x)}</div>
      <div class="pl shiro"><b>${x.shiro.name}</b><span>${x.shiro.club || ''}</span></div></div></div>`;
}
function timerHtml(x) {
  const t = x.score?.timer;
  if (!t || !t.duration) return '';
  return html`<div class="tiny mono" data-timer='${JSON.stringify(t)}'>${fmtTimer(timerLeft(t))}</div>`;
}
export function timerLeft(t) {
  if (!t) return 0;
  return t.running ? t.remaining - (serverNow() - t.server_at) : t.remaining;
}
/** Kas 250 ms atnaujina visus [data-timer] laikmačius */
export function tickTimers(root, cls) {
  const iv = setInterval(() => {
    $$('[data-timer]', root).forEach((el) => {
      const t = JSON.parse(el.dataset.timer);
      const left = timerLeft(t);
      el.textContent = fmtTimer(left);
      if (cls) el.classList.toggle('low', left < 15000);
    });
  }, 250);
  return () => clearInterval(iv);
}

async function tabLive(box, t) {
  const draw = async () => {
    const d = await api(`tournaments/${t.id}/live?next=5`, { quiet: true });
    render(box, html`<div class="live-grid">${d.tatami.map((x) => html`<div class="panel live-col">
      <h3>Tatami ${x.tatami} ${x.current.length ? raw('<span class="live-dot"></span>') : ''}</h3>
      ${x.current.length ? x.current.map((c) => c.type === 'match' ? nowMatch(c) : html`<div class="now-box"><div class="cat">${c.category.name} · ${c.label}</div><b>${c.entry.name}</b> <span class="small muted">${c.entry.club || ''}</span>${c.kata ? html`<div class="small">Kata: ${c.kata}</div>` : ''}</div>`)
        : html`<p class="small muted" style="margin-bottom:10px">Šiuo metu niekas nevyksta.</p>`}
      ${x.next.length ? html`<div class="b-round-title" style="text-align:left;margin-top:6px">Toliau${x.next_total > x.next.length ? ` (dar ${x.next_total - x.next.length})` : ''}</div>
      <ul class="next-list">${x.next.map((n) => html`<li><div class="cat">${n.category.name} · ${n.label}</div>
        ${n.type === 'match' ? html`<span style="border-left:3px solid var(--aka);padding-left:6px">${n.aka.name}</span> <span class="muted">prieš</span> <span>${n.shiro.name}</span>` : html`<b>${n.entry.name}</b> <span class="muted small">${n.entry.club !== n.entry.name ? n.entry.club || '' : ''}</span>`}</li>`)}</ul>` : ''}
    </div>`)}</div>
    <p class="small muted" style="margin-top:12px">Atsinaujina automatiškai.</p>`);
  };
  await draw();
  onLeave(poll(draw, 3000));
  onLeave(tickTimers(box));
}

async function tabSchedule(box, t) {
  const draw = async () => {
    const { schedule } = await api(`tournaments/${t.id}/schedule`);
    render(box, html`<div class="sched" style="--cols:${schedule.tatami.length}">${schedule.tatami.map((x) => html`<div class="panel sched-col">
      <h3>Tatami ${x.tatami} <span class="small muted mono">${schedule.day_start}–${x.end || schedule.day_start}</span></h3>
      ${x.categories.length ? x.categories.map((c) => html`<a class="sched-item ${c.status === 'finished' ? 'done' : ''}" href="#/t/${t.id}/c/${c.id}">
        <span class="time">${c.est_start}<small>${c.est_end}</small></span>
        <span><b class="small">${c.name}</b><br><span class="row tiny muted" style="gap:6px">${catStatusBadge(c.status)} ${c.entries_count} dal. · ${c.items_total} ${c.format === 'scores' ? 'pasirod.' : 'kovos'}</span></span></a>`)
        : html`<p class="small muted">Kategorijų nepriskirta.</p>`}</div>`)}</div>
      ${schedule.unassigned.length ? html`<div class="panel card" style="margin-top:14px"><h3>Dar nepriskirta tatami</h3><p class="small">${schedule.unassigned.map((c) => c.name).join(' · ')}</p></div>` : ''}
      <p class="small muted" style="margin-top:12px">Laikai apytiksliai: skaičiuojami pagal dalyvių skaičių ir kovos trukmę.</p>`);
  };
  await draw();
  onLeave(watchTournament(t.id, draw, 8000));
}

async function tabResults(box, t) {
  const draw = async () => {
    const d = await api(`tournaments/${t.id}/results`);
    const withRes = d.categories.filter((c) => c.results.length);
    render(box, html`<div class="grid-2" style="align-items:start">
      <div class="panel card"><h2>Medaliai pagal klubus</h2>
        ${d.medals.length ? html`<table class="table medal-table"><thead><tr><th>#</th><th>Klubas</th><th>🥇</th><th>🥈</th><th>🥉</th></tr></thead>
        <tbody>${d.medals.map((m, i) => html`<tr><td>${i + 1}</td><td><b>${m.club}</b></td><td>${m.gold}</td><td>${m.silver}</td><td>${m.bronze}</td></tr>`)}</tbody></table>` : html`<p class="muted small">Medalių dar nėra.</p>`}
        <div class="row" style="margin-top:14px"><a class="btn btn-sm btn-ghost" href="export.php?t=${t.id}&type=results">⬇ Rezultatai (CSV, Excel)</a></div>
      </div>
      <div>${withRes.length ? withRes.map((c) => html`<div class="panel card tight">
        <div class="row between"><h3 style="margin:0"><a href="#/t/${t.id}/c/${c.id}" style="color:inherit">${c.name}</a></h3>${catStatusBadge(c.status)}</div>
        <div style="margin-top:8px">${resultsList(c.results.filter((r) => r.place <= 3))}</div></div>`) : html`<div class="panel card empty">Rezultatų dar nėra.</div>`}</div>
    </div>`);
  };
  await draw();
  onLeave(watchTournament(t.id, draw, 8000));
}

async function tabClubs(box, t) {
  const { clubs } = await api(`tournaments/${t.id}/clubs`);
  render(box, clubs.length ? html`<div class="panel card"><table class="table"><thead><tr><th>Klubas</th><th>Miestas</th><th class="num">Sportininkai</th><th class="num">Startai</th></tr></thead>
    <tbody>${clubs.map((c) => html`<tr><td><b>${c.name}</b></td><td>${c.city || ''}</td><td class="num">${c.athletes}</td><td class="num">${c.entries}</td></tr>`)}</tbody></table></div>`
    : html`<div class="panel card empty">Dalyvių dar nėra.</div>`);
}

// ---------- Viešoji registracija (klubų treneriams) ----------
async function tabRegistration(box, t) {
  render(box, html`<div class="panel card" style="max-width:900px">
    <h2>Registracija</h2>
    <p class="small muted">Treneris užregistruoja savo klubo sportininkus. Kategorijos pasiūlomos pagal amžių, lytį, diržą ir svorį.
      ${t.reg_deadline ? html`Registracija iki <b>${fmtDate(t.reg_deadline)}</b>` : ''}</p>
    <form class="form" id="reg" style="margin-top:14px">
      <fieldset><legend>Klubas ir treneris</legend>
        <div class="form-row"><label>Klubo pavadinimas<input name="club_name" required></label><label>Miestas<input name="city"></label></div>
        <div class="form-row three"><label>Treneris<input name="contact_name" required></label><label>El. paštas<input name="contact_email" type="email" required></label><label>Telefonas<input name="contact_phone"></label></div>
      </fieldset>
      <div id="athletes"></div>
      <div class="row"><button type="button" class="btn btn-ghost" data-act="add">+ Pridėti sportininką</button></div>
      <button class="btn btn-pink btn-block" type="submit">Registruoti</button>
    </form></div>`);
  const list = $('#athletes', box);
  let n = 0;
  const addRow = () => {
    const i = n++;
    const el = document.createElement('div');
    el.className = 'reg-athlete';
    el.dataset.row = i;
    el.innerHTML = String(html`<div class="row between"><b>Sportininkas</b><button type="button" class="icon-btn" data-act="remove" title="Pašalinti">✕</button></div>
      <div class="form-row four">
        <label>Vardas<input data-f="first_name" required></label><label>Pavardė<input data-f="last_name" required></label>
        <label>Gimimo data<input data-f="birth_date" type="date" required></label>
        <label>Lytis<select data-f="gender" required><option value="">—</option><option value="M">Vyras / berniukas</option><option value="F">Moteris / mergaitė</option></select></label>
      </div>
      <div class="form-row"><label>Diržas<select data-f="belt_level">${beltOptions(null)}</select></label><label>Svoris, kg<input data-f="weight_kg" inputmode="decimal" placeholder="jei kumite pagal svorį"></label></div>
      <div><div class="hint" style="margin-bottom:6px">Kategorijos</div><div class="cat-checks" data-cats><span class="hint">Įveskite gimimo datą ir lytį.</span></div></div>`);
    list.appendChild(el);
  };
  const refreshCats = async (row) => {
    const g = (f) => $(`[data-f=${f}]`, row).value;
    const box2 = $('[data-cats]', row);
    if (!g('birth_date') || !g('gender')) return;
    const checked = new Set($$('input:checked', box2).map((x) => x.value));
    const { categories } = await api(`tournaments/${t.id}/eligible`, { method: 'POST', body: { birth_date: g('birth_date'), gender: g('gender'), belt_level: g('belt_level'), weight_kg: g('weight_kg') }, quiet: true });
    render(box2, categories.length ? categories.map((c) => html`<label><input type="checkbox" value="${c.id}" ${checked.has(String(c.id)) ? 'checked' : ''}>${c.name}</label>`)
      : html`<span class="hint">Tinkamos kategorijos nėra.</span>`);
  };
  addRow();
  on(box, 'click', '[data-act=add]', addRow);
  on(box, 'click', '[data-act=remove]', (e, b) => { if ($$('.reg-athlete', list).length > 1) b.closest('.reg-athlete').remove(); });
  on(box, 'change', '[data-f]', (e, el) => refreshCats(el.closest('.reg-athlete')));
  on(box, 'submit', '#reg', async (e) => {
    e.preventDefault();
    const f = formData(e.target);
    const athletes = $$('.reg-athlete', list).map((row) => {
      const a = {};
      $$('[data-f]', row).forEach((x) => { a[x.dataset.f] = x.value || null; });
      a.category_ids = $$('[data-cats] input:checked', row).map((x) => Number(x.value));
      return a;
    });
    await busy(e.submitter, async () => {
      const r = await api(`tournaments/${t.id}/register`, { method: 'POST', body: {
        club: { name: f.club_name, city: f.city, contact_name: f.contact_name, contact_email: f.contact_email, contact_phone: f.contact_phone },
        athletes,
      } });
      render(box, html`<div class="panel card"><div class="flash flash-ok">Užregistruota: ${r.athletes.join(', ')} (${r.entries} startai).</div>
        <p><a href="#/t/${t.id}?tab=kategorijos">Žiūrėti kategorijas →</a></p></div>`);
    });
  });
}

// ---------- Kategorija ----------
export async function categoryPage(main, { id, cid }) {
  const load = () => api(`categories/${cid}`);
  let d = await load();
  const draw = () => {
    const c = d.category;
    const admin = !!state.user;
    let body;
    if (c.status === 'open') {
      body = html`<div class="panel card"><h2>Dalyviai <span class="badge">${d.entries.length}</span></h2>
        ${d.entries.length ? html`<div class="table-wrap"><table class="table"><thead><tr><th>Dalyvis</th><th>Klubas</th>${c.entry_type === 'individual' ? html`<th>Diržas</th>` : html`<th>Nariai</th>`}</tr></thead>
        <tbody>${d.entries.map((e) => html`<tr><td><b>${e.name}</b>${e.seed ? html` <span class="badge badge-pink">seed ${e.seed}</span>` : ''}</td><td>${e.club || ''}</td>
          ${c.entry_type === 'individual' ? html`<td>${belt(e.belt_level)}</td>` : html`<td class="small">${e.members.map((m) => m.name).join(', ')}</td>`}</tr>`)}</tbody></table></div>`
        : html`<p class="muted">Dalyvių dar nėra.</p>`}
        <p class="small muted" style="margin-top:10px">Lentelė bus sudaryta ištraukus burtus.</p></div>`;
    } else if (c.format === 'scores') {
      body = html`${scoresView(d.performances, c, { admin })}`;
    } else if (c.format === 'round_robin') {
      body = html`<div class="panel card">${poolView(d.standings, d.matches, c, { clickable: admin })}</div>`;
    } else {
      body = html`<div class="panel card" style="padding:18px 16px">${bracketView(d.matches, d.rounds, c, { clickable: admin })}</div>`;
    }
    render(main, html`
      <a class="back no-print" href="#/t/${id}?tab=kategorijos">← ${d.tournament.title}</a>
      <div class="page-head">
        <div class="row" style="gap:6px;margin-bottom:8px">${catStatusBadge(c.status)}<span class="badge">${c.discipline_label}</span>${c.tatami ? html`<span class="badge badge-blue">Tatami ${c.tatami}</span>` : ''}</div>
        <h1 class="styled">${c.name}</h1>
        <div class="meta small"><span>${catFacts(c)}</span><span>${formatLabel(c)}</span>
          ${c.match_type === 'kumite' || c.match_type === 'fukugo' ? html`<span>${state.meta.kumite_rules[c.kumite_rule]} · ${Math.floor(c.match_seconds / 60)}:${String(c.match_seconds % 60).padStart(2, '0')} min.</span>` : ''}
          ${c.format === 'bracket' ? html`<span>3 vieta: ${state.meta.third_place[c.third_place]}</span>` : ''}</div>
      </div>
      ${admin ? categoryAdminBar(d) : ''}
      ${c.status === 'finished' && d.results.length ? html`<div class="panel card tight"><h3>Vietos</h3>${resultsList(d.results, { limit: 8, cols: true })}</div>`
        : d.results.some((r) => r.place <= 3) ? html`<div class="panel card tight"><h3>Jau aiškios vietos</h3>${resultsList(d.results.filter((r) => r.place <= 3), { cols: true })}</div>` : ''}
      ${body}
      <div class="row no-print" style="margin-top:10px"><button class="btn btn-sm btn-ghost" onclick="window.print()">🖨 Spausdinti</button></div>`);
  };
  draw();
  const reload = async () => { d = await load(); draw(); };
  if (state.user) bindCategoryAdmin(main, () => d, reload);
  onLeave(watchTournament(Number(id), reload, 3000));
}

// ---------- Prisijungimas ----------
export async function loginPage(main, params, query) {
  main.className = 'page narrow';
  const setup = state.meta.needs_setup;
  render(main, html`<div class="panel card" style="margin-top:30px">
    <div class="eyebrow">Organizatoriams</div>
    <h1 class="styled">${setup ? 'Pirmas administratorius' : 'Prisijungimas'}</h1>
    ${setup ? html`<p class="small muted" style="margin-bottom:14px">Vartotojų dar nėra. Sukurkite administratorių - reikės sąrankos rakto (setup_key) iš config.php.</p>` : ''}
    <form class="form" id="login">
      ${setup ? html`<label>Vardas<input name="name" required></label>` : ''}
      <label>El. paštas<input name="email" type="email" autocomplete="username" required></label>
      <label>Slaptažodis<input name="password" type="password" autocomplete="${setup ? 'new-password' : 'current-password'}" required minlength="${setup ? 8 : 1}"></label>
      ${setup ? html`<label>Sąrankos raktas<input name="setup_key" required></label>` : ''}
      <button class="btn btn-primary btn-block" type="submit">${setup ? 'Sukurti ir prisijungti' : 'Prisijungti'}</button>
    </form>
    ${state.meta.env === 'local' && !setup ? html`<p class="hint" style="margin-top:14px">Demo: admin@varzybos.test / testas123</p>` : ''}
  </div>`);
  on(main, 'submit', '#login', async (e) => {
    e.preventDefault();
    await busy(e.submitter, async () => {
      const r = await api(setup ? 'auth/setup' : 'auth/login', { method: 'POST', body: formData(e.target) });
      state.user = r.user;
      state.meta.needs_setup = false;
      document.dispatchEvent(new Event('auth'));
      go(query.next || '#/admin');
    });
  });
}

void medal; void toast; void poll;
