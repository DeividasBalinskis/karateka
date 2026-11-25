// Organizatoriaus puslapiai: varžybų valdymas, kategorijos, dalyviai, tvarkaraštis, vykdymas, integracija

import {
  api, state, html, raw, render, $, $$, on, toast, go, modal, confirmBox, formData, busy, fmtDate, dateRange,
  statusBadge, catStatusBadge, options, beltOptions, belt, genderLabel, onLeave, poll, watchTournament, esc,
} from './core.js';
import { catFacts, formatLabel, sideScore } from './components.js';

const KATAS = ['Heian Shodan', 'Heian Nidan', 'Heian Sandan', 'Heian Yondan', 'Heian Godan', 'Tekki Shodan', 'Tekki Nidan', 'Tekki Sandan',
  'Bassai Dai', 'Bassai Sho', 'Kanku Dai', 'Kanku Sho', 'Enpi', 'Jion', 'Jiin', 'Jitte', 'Hangetsu', 'Gankaku', 'Chinte', 'Sochin', 'Nijushiho',
  'Meikyo', 'Wankan', 'Unsu', 'Gojushiho Dai', 'Gojushiho Sho'];

function requireLogin() {
  if (!state.user) { const e = new Error('Reikia prisijungti.'); e.status = 401; throw e; }
}

// ---------- Valdymo pradžia ----------
export async function adminHome(main) {
  requireLogin();
  const { tournaments } = await api('tournaments');
  const isAdmin = state.user.role === 'admin';
  render(main, html`
    <div class="page-head row between"><div><div class="eyebrow">Valdymas</div><h1 class="styled">Varžybų valdymas</h1></div>
      ${isAdmin ? html`<button class="btn btn-primary" data-act="new">+ Naujos varžybos</button>` : ''}</div>
    <div class="panel card"><table class="table cards">
      <thead><tr><th>Data</th><th>Varžybos</th><th>Būsena</th><th class="num">Kategorijos</th><th class="num">Sportininkai</th><th></th></tr></thead>
      <tbody>${tournaments.map((t) => html`<tr>
        <td class="nowrap">${fmtDate(t.starts_on)}</td><td><b>${t.title}</b><div class="tiny muted">${t.location || ''}</div></td>
        <td>${statusBadge(t.status, t.status_label)}</td><td class="num">${t.categories_count}</td><td class="num">${t.athletes_count}</td>
        <td class="num"><a class="btn btn-sm btn-primary" href="#/admin/t/${t.id}">Valdyti</a></td></tr>`)}</tbody></table>
      ${tournaments.length ? '' : html`<p class="empty">Varžybų dar nėra.</p>`}</div>
    ${isAdmin ? html`<div class="panel card" id="users"></div>` : ''}`);
  on(main, 'click', '[data-act=new]', () => tournamentModal(null, (t) => go('#/admin/t/' + t.id)));
  if (isAdmin) await usersPanel($('#users', main));
}

async function usersPanel(box) {
  const draw = async () => {
    const { users } = await api('users');
    render(box, html`<h2>Vartotojai</h2>
      <p class="small muted" style="margin-bottom:10px">Administratorius tvarko varžybas. Sekretorius įveda rezultatus ir traukia burtus.</p>
      <table class="table cards"><thead><tr><th>Vardas</th><th>El. paštas</th><th>Rolė</th><th>Paskutinis prisijungimas</th><th></th></tr></thead>
      <tbody>${users.map((u) => html`<tr><td><b>${u.name}</b></td><td>${u.email}</td><td>${u.role === 'admin' ? 'Administratorius' : 'Sekretorius'}</td>
        <td class="small muted">${u.last_login_at || '—'}</td><td class="num">${u.id !== state.user.id ? html`<button class="btn btn-sm btn-danger" data-del-user="${u.id}">Ištrinti</button>` : ''}</td></tr>`)}</tbody></table>
      <form class="form" id="add-user" style="margin-top:16px"><div class="form-row four">
        <label>Vardas<input name="name" required></label><label>El. paštas<input name="email" type="email" required></label>
        <label>Slaptažodis<input name="password" type="password" minlength="8" required></label>
        <label>Rolė<select name="role"><option value="secretary">Sekretorius</option><option value="admin">Administratorius</option></select></label></div>
        <div><button class="btn btn-ghost" type="submit">+ Pridėti vartotoją</button></div></form>`);
  };
  await draw();
  on(box, 'submit', '#add-user', async (e) => { e.preventDefault(); await busy(e.submitter, async () => { await api('users', { method: 'POST', body: formData(e.target) }); toast('Vartotojas pridėtas.'); await draw(); }); });
  on(box, 'click', '[data-del-user]', async (e, b) => { if (await confirmBox('Ištrinti vartotoją?', { danger: true, ok: 'Ištrinti' })) { await api('users/' + b.dataset.delUser, { method: 'DELETE' }); await draw(); } });
}

function tournamentFields(t = {}) {
  return html`
    <label>Pavadinimas<input name="title" value="${t.title || ''}" required></label>
    <div class="form-row"><label>Data<input name="starts_on" type="date" value="${t.starts_on || ''}" required></label><label>Pabaiga (jei kelios dienos)<input name="ends_on" type="date" value="${t.ends_on || ''}"></label></div>
    <label>Vieta<input name="location" value="${t.location || ''}" placeholder="Miestas, sporto salė"></label>
    <label>Aprašymas<textarea name="description" placeholder="Svėrimas, mokesčiai, kontaktai...">${t.description || ''}</textarea></label>
    <div class="form-row"><label>Būsena<select name="status">${options(state.meta.statuses, t.status || 'draft')}</select></label>
      <label>Registracija iki<input name="reg_deadline" type="date" value="${t.reg_deadline || ''}"></label></div>
    <div class="form-row three"><label>Tatami skaičius<input name="tatami_count" type="number" min="1" max="12" value="${t.tatami_count || 2}"></label>
      <label>Pradžia<input name="day_start" type="time" value="${t.day_start || '10:00'}"></label>
      <label>Amžius skaičiuojamas<select name="age_basis">${options({ year: 'pagal gimimo metus', date: 'varžybų dieną' }, t.age_basis || 'year')}</select></label></div>
    <label class="check"><input type="checkbox" name="public_registration" ${t.public_registration ? 'checked' : ''}><span>Kiti klubai gali registruotis per viešą formą</span></label>
    <label class="check"><input type="checkbox" name="is_abroad" ${t.is_abroad ? 'checked' : ''}><span>Varžybos užsienyje (karateka.lt taškams)</span></label>`;
}

function tournamentModal(t, done) {
  const m = modal(html`<form class="form" id="tf">${tournamentFields(t || {})}
    <div class="actions"><button type="button" class="btn btn-ghost" data-close>Atšaukti</button><button class="btn btn-primary" type="submit">Išsaugoti</button></div></form>`,
  { title: t ? 'Varžybų nustatymai' : 'Naujos varžybos' });
  on(m.el, 'submit', '#tf', async (e) => {
    e.preventDefault();
    await busy(e.submitter, async () => {
      const r = await api(t ? 'tournaments/' + t.id : 'tournaments', { method: t ? 'PUT' : 'POST', body: formData(e.target) });
      m.close();
      done(r.tournament);
    });
  });
}

// ---------- Vienos varžybos valdymas ----------
const ATABS = [
  ['apzvalga', 'Apžvalga'], ['kategorijos', 'Kategorijos'], ['dalyviai', 'Dalyviai'],
  ['tvarkarastis', 'Tvarkaraštis'], ['vykdymas', 'Vykdymas'], ['integracija', 'karateka.lt'],
];

export async function adminTournament(main, { id }, query) {
  requireLogin();
  const d = await api('tournaments/' + id);
  const t = d.tournament;
  const tab = query.tab || 'apzvalga';
  render(main, html`
    <a class="back" href="#/admin">← Varžybų valdymas</a>
    <div class="page-head"><div class="row" style="gap:6px;margin-bottom:6px">${statusBadge(t.status, t.status_label)}</div>
      <h1 class="styled">${t.title}</h1>
      <div class="meta"><span>${dateRange(t.starts_on, t.ends_on)}</span>${t.location ? html`<span>${t.location}</span>` : ''}<a href="#/t/${t.id}">Viešas puslapis →</a></div></div>
    <nav class="tabs">${ATABS.map(([k, l]) => html`<a href="#/admin/t/${t.id}?tab=${k}" class="${tab === k ? 'active' : ''}">${l}</a>`)}</nav>
    <div id="tab"></div>`);
  const box = $('#tab', main);
  const views = { apzvalga: aOverview, kategorijos: aCategories, dalyviai: aAthletes, tvarkarastis: aSchedule, vykdymas: aRun, integracija: aIntegration };
  await (views[tab] || aOverview)(box, t, d.categories);
}

async function aOverview(box, t, cats) {
  const count = (s) => cats.filter((c) => c.status === s).length;
  const isAdmin = state.user.role === 'admin';
  render(box, html`
    <div class="kpis">
      <div class="panel kpi"><div class="num">${cats.length}</div><div class="label">kategorijos</div></div>
      <div class="panel kpi"><div class="num">${t.athletes_count}</div><div class="label">sportininkai</div></div>
      <div class="panel kpi"><div class="num">${t.clubs_count}</div><div class="label">klubai</div></div>
      <div class="panel kpi"><div class="num">${count('finished')}/${cats.length}</div><div class="label">kategorijų baigta</div></div>
    </div>
    <div class="grid-2" style="align-items:start">
      <div class="panel card"><h2>Eiga</h2>
        <ol class="small" style="padding-left:18px;display:grid;gap:8px">
          <li>Sukurkite kategorijas (<a href="#/admin/t/${t.id}?tab=kategorijos">Kategorijos</a>) ir įjunkite registraciją (būsena „Vyksta registracija“).</li>
          <li>Sportininkai registruojasi per karateka.lt, kiti klubai - per viešą formą, arba juos įrašote patys (<a href="#/admin/t/${t.id}?tab=dalyviai">Dalyviai</a>).</li>
          <li>Pasibaigus registracijai ištraukite burtus - lentelės sudaromos automatiškai, to paties klubo sportininkai išskiriami.</li>
          <li>Paskirstykite kategorijas tatami (<a href="#/admin/t/${t.id}?tab=tvarkarastis">Tvarkaraštis</a>).</li>
          <li>Varžybų dieną sekretoriai telefone ar planšetėje atsidaro savo tatami (<a href="#/admin/t/${t.id}?tab=vykdymas">Vykdymas</a>) ir įveda rezultatus. Žiūrovai mato viską gyvai.</li>
          <li>Po varžybų karateka.lt treneris paspaudžia „Įkelti rezultatus“ - vietos ir taškai atsiranda narių istorijoje.</li>
        </ol>
        <div class="row" style="margin-top:16px">
          <button class="btn btn-primary" data-act="draw-all" ${count('open') ? '' : 'disabled'}>Ištraukti burtus visoms (${count('open')})</button>
          <a class="btn btn-ghost" href="export.php?t=${t.id}&type=entries">⬇ Dalyviai (CSV)</a>
          <a class="btn btn-ghost" href="export.php?t=${t.id}&type=results">⬇ Rezultatai (CSV)</a>
        </div></div>
      <div class="panel card"><h2>Nustatymai</h2>
        ${isAdmin ? html`<form class="form" id="tf">${tournamentFields(t)}<div class="row between"><button class="btn btn-primary" type="submit">Išsaugoti</button>
          <button type="button" class="btn btn-danger btn-sm" data-act="delete">Ištrinti varžybas</button></div></form>` : html`<p class="muted small">Nustatymus keičia administratorius.</p>`}
      </div>
    </div>`);
  on(box, 'submit', '#tf', async (e) => {
    e.preventDefault();
    await busy(e.submitter, async () => { await api('tournaments/' + t.id, { method: 'PUT', body: formData(e.target) }); toast('Išsaugota.'); go(location.hash); });
  });
  on(box, 'click', '[data-act=draw-all]', async (e, b) => {
    if (!await confirmBox('Ištraukti burtus visoms kategorijoms, kurios dar atviros? Po to dalyvių sąrašų keisti nebus galima (nebent panaikinsite burtus).')) return;
    await busy(b, async () => {
      const r = await api(`tournaments/${t.id}/draw-all`, { method: 'POST' });
      toast(`Burtai ištraukti: ${r.drawn.length}.` + (r.skipped.length ? ` Praleista: ${r.skipped.length}.` : ''));
      if (r.skipped.length) modal(html`<ul class="small" style="padding-left:18px">${r.skipped.map((s) => html`<li>${s}</li>`)}</ul>`, { title: 'Praleistos kategorijos' });
      go(location.hash);
    });
  });
  on(box, 'click', '[data-act=delete]', async () => {
    if (!await confirmBox(`Ištrinti „${t.title}“ su visomis kategorijomis ir rezultatais? Atšaukti nebus galima.`, { danger: true, ok: 'Ištrinti' })) return;
    await api('tournaments/' + t.id, { method: 'DELETE' });
    go('#/admin');
  });
}

// ---------- Kategorijos ----------
async function aCategories(box, t) {
  const isAdmin = state.user.role === 'admin';
  const draw = async () => {
    const { categories } = await api('tournaments/' + t.id);
    render(box, html`
      <div class="row between" style="margin-bottom:14px"><p class="small muted">${categories.length} kategorijos. Pavadinimas sukuriamas automatiškai, jei neįrašote.</p>
        ${isAdmin ? html`<div class="row"><button class="btn btn-ghost" data-act="template">⚡ Greitai sukurti kelias</button><button class="btn btn-primary" data-act="new">+ Kategorija</button></div>` : ''}</div>
      <div class="panel card" style="padding:10px 16px"><div class="table-wrap"><table class="table cards">
        <thead><tr><th>Kategorija</th><th>Kas</th><th>Formatas</th><th class="num">Dal.</th><th>Tatami</th><th>Būsena</th><th></th></tr></thead>
        <tbody>${categories.map((c) => html`<tr>
          <td><a href="#/t/${t.id}/c/${c.id}"><b>${c.name}</b></a></td>
          <td class="small">${catFacts(c)}</td><td class="small">${formatLabel(c)}</td>
          <td class="num">${c.entries_count}</td><td>${c.tatami || '—'}</td><td>${catStatusBadge(c.status)}</td>
          <td class="num nowrap">
            ${c.entry_type !== 'individual' && c.status === 'open' ? html`<button class="btn btn-sm btn-ghost" data-team="${c.id}">+ ${c.entry_type === 'pair' ? 'Pora' : 'Komanda'}</button>` : ''}
            ${c.status === 'open' ? html`<button class="btn btn-sm btn-primary" data-draw="${c.id}" ${c.entries_count ? '' : 'disabled'}>Burtai</button>` : html`<button class="btn btn-sm btn-ghost" data-undraw="${c.id}">Panaikinti burtus</button>`}
            ${isAdmin ? html`<button class="icon-btn" data-edit="${c.id}" title="Redaguoti">✎</button><button class="icon-btn" data-del="${c.id}" title="Ištrinti">🗑</button>` : ''}
          </td></tr>`)}</tbody></table></div>
        ${categories.length ? '' : html`<p class="empty">Kategorijų dar nėra. Pradėkite nuo „Greitai sukurti kelias“.</p>`}</div>`);
    box._cats = categories;
  };
  await draw();
  const find = (cid) => box._cats.find((c) => c.id === Number(cid));
  on(box, 'click', '[data-act=new]', () => categoryModal(t, null, draw));
  on(box, 'click', '[data-act=template]', () => templateModal(t, draw));
  on(box, 'click', '[data-edit]', (e, b) => categoryModal(t, find(b.dataset.edit), draw));
  on(box, 'click', '[data-team]', (e, b) => teamModal(t, find(b.dataset.team), draw));
  on(box, 'click', '[data-del]', async (e, b) => {
    const c = find(b.dataset.del);
    if (!await confirmBox(`Ištrinti kategoriją „${c.name}“${c.entries_count ? ` su ${c.entries_count} dalyviais` : ''}?`, { danger: true, ok: 'Ištrinti' })) return;
    await api('categories/' + c.id, { method: 'DELETE' });
    await draw();
  });
  on(box, 'click', '[data-draw]', async (e, b) => { await busy(b, () => api(`categories/${b.dataset.draw}/draw`, { method: 'POST' })); toast('Burtai ištraukti.'); await draw(); });
  on(box, 'click', '[data-undraw]', async (e, b) => {
    if (!await confirmBox('Panaikinti burtus? Visos kovos ir įvesti rezultatai šioje kategorijoje bus ištrinti.', { danger: true, ok: 'Panaikinti' })) return;
    await api(`categories/${b.dataset.undraw}/undraw`, { method: 'POST' });
    await draw();
  });
}

export function categoryModal(t, c, done) {
  const m = state.meta;
  const v = c || { discipline: 'kata', gender: 'X', format: 'scores', match_type: 'flags', kumite_rule: 'ippon', match_seconds: 120, judges: 5, third_place: 'two_bronze', score_rounds: '8', score_min: 5.0, score_max: 9.9 };
  const locked = c && c.status !== 'open';
  const tat = Array.from({ length: t.tatami_count }, (_, i) => i + 1);
  const md = modal(html`<form class="form" id="cf">
    ${locked ? html`<div class="flash flash-info small">Burtai ištraukti - formato keisti negalima.</div>` : ''}
    <div class="form-row"><label>Disciplina<select name="discipline" ${locked ? 'disabled' : ''}>${options(m.disciplines, v.discipline)}</select></label>
      <label>Lytis<select name="gender">${options({ M: 'Vyrai / berniukai', F: 'Moterys / mergaitės', X: 'Mišri' }, v.gender)}</select></label></div>
    <div class="form-row four">
      <label>Amžius nuo<input name="age_min" type="number" min="3" max="99" value="${v.age_min ?? ''}"></label>
      <label>Amžius iki<input name="age_max" type="number" min="3" max="99" value="${v.age_max ?? ''}"></label>
      <label>Diržas nuo<select name="belt_min">${beltOptions(v.belt_min, 'bet koks')}</select></label>
      <label>Diržas iki<select name="belt_max">${beltOptions(v.belt_max, 'bet koks')}</select></label></div>
    <div class="form-row"><label>Svoris virš, kg<input name="weight_min" inputmode="decimal" value="${v.weight_min ?? ''}" placeholder="pvz. 75 (= +75 kg)"></label>
      <label>Svoris iki, kg<input name="weight_max" inputmode="decimal" value="${v.weight_max ?? ''}" placeholder="pvz. 60 (= −60 kg)"></label></div>
    <div class="form-row"><label>Formatas<select name="format" ${locked ? 'disabled' : ''}>${options(m.formats, v.format)}</select></label>
      <label data-show="bracket round_robin">Kaip vertinama kova<select name="match_type" ${locked ? 'disabled' : ''}>${options(m.match_types, v.match_type)}</select></label></div>
    <div class="form-row" data-show="kumite fukugo team"><label>Kumite taisyklė<select name="kumite_rule">${options(m.kumite_rules, v.kumite_rule)}</select></label>
      <label>Kovos trukmė, s<input name="match_seconds" type="number" min="30" max="600" step="10" value="${v.match_seconds}"></label></div>
    <div class="form-row"><label>Teisėjų skaičius<select name="judges">${options({ 3: '3', 5: '5', 7: '7' }, String(v.judges))}</select></label>
      <label data-show="bracket">3 vieta<select name="third_place" ${locked ? 'disabled' : ''}>${options(m.third_place, v.third_place)}</select></label></div>
    <fieldset data-show="scores"><legend>Balai</legend>
      <div class="form-row three"><label>Į kitą ratą patenka<input name="score_rounds" value="${v.score_rounds ?? ''}" placeholder="pvz. 8 arba 16,8" ${locked ? 'disabled' : ''}><span class="hint">Tuščia - vienas ratas</span></label>
        <label>Mažiausias balas<input name="score_min" inputmode="decimal" value="${v.score_min}"></label><label>Didžiausias balas<input name="score_max" inputmode="decimal" value="${v.score_max}"></label></div>
      <label class="check"><input type="checkbox" name="carry_scores" ${v.carry_scores ? 'checked' : ''}><span>Finale pridedami ankstesnio rato balai</span></label>
      <p class="hint">Kai teisėjų 5 ar daugiau - didžiausias ir mažiausias balas atmetami. Lygiems: lemia mažiausias, tada didžiausias balas.</p>
    </fieldset>
    <div class="form-row"><label>Tatami<select name="tatami"><option value="">—</option>${tat.map((n) => html`<option value="${n}" ${v.tatami === n ? 'selected' : ''}>${n}</option>`)}</select></label>
      <label>Pavadinimas<input name="name" value="${c ? c.name : ''}" placeholder="sukuriamas automatiškai"></label></div>
    <div class="actions"><button type="button" class="btn btn-ghost" data-close>Atšaukti</button><button class="btn btn-primary" type="submit">Išsaugoti</button></div>
  </form>`, { title: c ? 'Kategorija' : 'Nauja kategorija', wide: true });
  const form = $('#cf', md.el);
  const sync = (changed) => {
    const disc = form.discipline.value;
    if (changed === 'discipline' && !c) {
      const def = m.defaults[disc];
      form.format.value = def.format;
      form.match_type.value = def.match_type;
    }
    const fmt = form.format.value;
    const mt = form.match_type.value;
    $$('[data-show]', form).forEach((el) => {
      const keys = el.dataset.show.split(' ');
      el.classList.toggle('hidden', !(keys.includes(fmt) || (fmt !== 'scores' && keys.includes(mt))));
    });
  };
  sync();
  form.addEventListener('change', (e) => sync(e.target.name));
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    await busy(e.submitter, async () => {
      await api(c ? 'categories/' + c.id : `tournaments/${t.id}/categories`, { method: c ? 'PUT' : 'POST', body: formData(form) });
      md.close();
      toast('Išsaugota.');
      done();
    });
  });
}

const AGE_PRESETS = [[6, 7], [8, 9], [10, 11], [12, 13], [14, 15], [16, 17], [18, 20], [21, 34], [35, 49], [18, null], [50, null]];

function templateModal(t, done) {
  const m = state.meta;
  const md = modal(html`<form class="form" id="tpl">
    <p class="small muted">Sukuriama po kategoriją kiekvienai disciplinai ir amžiaus grupei. Vėliau kiekvieną galima pakoreguoti.</p>
    <fieldset><legend>Disciplinos</legend><div class="cat-checks">${Object.entries(m.disciplines).map(([k, l]) => html`<label><input type="checkbox" name="disciplines[]" value="${k}" ${k === 'kata' || k === 'kumite' ? 'checked' : ''}>${l}</label>`)}</div></fieldset>
    <fieldset><legend>Amžiaus grupės</legend><div class="cat-checks">${AGE_PRESETS.map(([a, b], i) => html`<label><input type="checkbox" name="ages[]" value="${i}" ${i >= 1 && i <= 5 ? 'checked' : ''}>${b ? `${a}–${b} m.` : `${a}+ m.`}</label>`)}</div>
      <div class="form-row"><label>Kita grupė nuo<input name="custom_min" type="number" min="3" max="99"></label><label>iki<input name="custom_max" type="number" min="3" max="99"></label></div></fieldset>
    <label class="check"><input type="checkbox" name="split_gender" checked><span>Atskirai berniukams ir mergaitėms (individualioms)</span></label>
    <label>Kumite: 3 vieta<select name="third_place">${options(m.third_place, 'two_bronze')}</select></label>
    <div class="actions"><button type="button" class="btn btn-ghost" data-close>Atšaukti</button><button class="btn btn-primary" type="submit">Sukurti</button></div>
  </form>`, { title: 'Greitai sukurti kategorijas', wide: true });
  on(md.el, 'submit', '#tpl', async (e) => {
    e.preventDefault();
    const f = formData(e.target);
    const ages = (f.ages || []).map((i) => ({ min: AGE_PRESETS[i][0], max: AGE_PRESETS[i][1] }));
    if (f.custom_min || f.custom_max) ages.push({ min: f.custom_min ? Number(f.custom_min) : null, max: f.custom_max ? Number(f.custom_max) : null });
    await busy(e.submitter, async () => {
      const r = await api(`tournaments/${t.id}/categories/template`, { method: 'POST', body: { disciplines: f.disciplines, ages, split_gender: f.split_gender, third_place: f.third_place } });
      md.close();
      toast(`Sukurta kategorijų: ${r.created}.`);
      done();
    });
  });
}

/** Sportininko paieška (autocomplete) - grąžina pasirinktą per onPick */
function athleteSearch(input, list, onPick) {
  let timer;
  input.addEventListener('input', () => {
    clearTimeout(timer);
    const q = input.value.trim();
    if (q.length < 2) { list.innerHTML = ''; return; }
    timer = setTimeout(async () => {
      const { athletes } = await api('athletes?q=' + encodeURIComponent(q), { quiet: true });
      render(list, athletes.length ? html`<div class="podium">${athletes.map((a) => html`<button type="button" class="p-row" style="border:none;cursor:pointer;text-align:left;font:inherit" data-pick='${JSON.stringify(a)}'>
        <span class="nm">${a.first_name} ${a.last_name}</span><span class="cl">${a.birth_date.slice(0, 4)} · ${a.club || 'be klubo'}</span></button>`)}</div>`
        : html`<p class="hint">Nerasta.</p>`);
    }, 250);
  });
  on(list, 'click', '[data-pick]', (e, b) => { onPick(JSON.parse(b.dataset.pick)); list.innerHTML = ''; input.value = ''; });
}

function teamModal(t, cat, done) {
  const picked = [];
  const md = modal(html`<form class="form" id="team">
    <div class="form-row"><label>${cat.entry_type === 'pair' ? 'Poros' : 'Komandos'} pavadinimas<input name="team_name" placeholder="pvz. Karate Ateitis I (nebūtina)"></label>
      <label>Klubas<select name="club_id" id="club-sel"><option value="">—</option></select></label></div>
    <label>Pridėti sportininką<input id="as" placeholder="Rašykite vardą ar pavardę…" autocomplete="off"></label><div id="as-list"></div>
    <div><div class="hint">Nariai</div><div class="chips" id="members" style="margin-top:6px"></div></div>
    <p class="hint">Naujus sportininkus pirma įrašykite skiltyje „Dalyviai“.</p>
    <div class="actions"><button type="button" class="btn btn-ghost" data-close>Atšaukti</button><button class="btn btn-primary" type="submit">Pridėti</button></div>
  </form>`, { title: `${cat.name}: ${cat.entry_type === 'pair' ? 'nauja pora' : 'nauja komanda'}` });
  api('clubs', { quiet: true }).then(({ clubs }) => { $('#club-sel', md.el).insertAdjacentHTML('beforeend', clubs.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('')); });
  const drawMembers = () => render($('#members', md.el), picked.length ? picked.map((a, i) => html`<span class="chip">${a.first_name} ${a.last_name}<button type="button" data-rm="${i}">✕</button></span>`) : html`<span class="hint">Dar niekas nepasirinktas.</span>`);
  drawMembers();
  athleteSearch($('#as', md.el), $('#as-list', md.el), (a) => {
    if (!picked.find((x) => x.id === a.id)) picked.push(a);
    if (a.club_id && !$('#club-sel', md.el).value) $('#club-sel', md.el).value = a.club_id;
    drawMembers();
  });
  on(md.el, 'click', '[data-rm]', (e, b) => { picked.splice(Number(b.dataset.rm), 1); drawMembers(); });
  on(md.el, 'submit', '#team', async (e) => {
    e.preventDefault();
    const f = formData(e.target);
    await busy(e.submitter, async () => {
      await api(`categories/${cat.id}/entries`, { method: 'POST', body: { team_name: f.team_name, club_id: f.club_id, athlete_ids: picked.map((a) => a.id) } });
      md.close();
      toast('Pridėta.');
      done();
    });
  });
}

// ---------- Dalyviai ----------
async function aAthletes(box, t) {
  const draw = async () => {
    const [{ athletes }, { categories }] = await Promise.all([api(`tournaments/${t.id}/athletes`), api('tournaments/' + t.id)]);
    const catById = Object.fromEntries(categories.map((c) => [c.id, c]));
    const clubs = [...new Set(athletes.map((a) => a.club || 'Be klubo'))].sort();
    const filter = box._filter || '';
    const club = box._club || '';
    const list = athletes.filter((a) => (!club || (a.club || 'Be klubo') === club)
      && (!filter || (a.first_name + ' ' + a.last_name).toLowerCase().includes(filter.toLowerCase())));
    render(box, html`
      <div class="grid-2" style="align-items:start;grid-template-columns:minmax(0,1.5fr) minmax(0,1fr)">
        <div class="panel card">
          <div class="row between" style="margin-bottom:10px"><h2 style="margin:0">Sportininkai <span class="badge">${athletes.length}</span></h2>
            <div class="row"><input class="input-sm" style="width:170px" placeholder="Paieška" value="${filter}" data-filter>
              <select class="input-sm" style="width:auto" data-club><option value="">Visi klubai</option>${clubs.map((c) => html`<option ${c === club ? 'selected' : ''}>${c}</option>`)}</select></div></div>
          <div class="table-wrap"><table class="table cards"><thead><tr><th>Vardas</th><th>Klubas</th><th>Gim.</th><th>Diržas</th><th>Kategorijos</th></tr></thead>
          <tbody>${list.map((a) => html`<tr><td><b>${a.first_name} ${a.last_name}</b>${a.external_source === 'karateka' ? html` <span class="badge badge-pink" title="Užsiregistravo per karateka.lt">karateka</span>` : ''}</td>
            <td class="small">${a.club || '—'}</td><td class="small">${a.birth_date.slice(0, 4)}</td><td>${belt(a.belt_level)}${a.weight_kg ? html` <span class="tiny muted">${a.weight_kg} kg</span>` : ''}</td>
            <td><div class="chips">${a.categories.map((c) => {
              const open = catById[c.id]?.status === 'open';
              return html`<span class="chip ${open ? '' : 'locked'}" title="${c.name}">${shortCat(c.name)}${open ? html`<button type="button" data-rm-entry="${c.entry_id}" title="Išbraukti">✕</button>` : ''}</span>`;
            })}</div></td></tr>`)}</tbody></table></div>
          ${list.length ? '' : html`<p class="empty">Nieko nerasta.</p>`}
        </div>
        <div class="panel card" id="add-box"></div>
      </div>`);
    addAthleteForm($('#add-box', box), t, draw);
  };
  await draw();
  on(box, 'input', '[data-filter]', (e, el) => { box._filter = el.value; clearTimeout(box._ft); box._ft = setTimeout(async () => { await draw(); const f = $('[data-filter]', box); f.focus(); f.setSelectionRange(f.value.length, f.value.length); }, 300); });
  on(box, 'change', '[data-club]', (e, el) => { box._club = el.value; draw(); });
  on(box, 'click', '[data-rm-entry]', async (e, b) => {
    if (!await confirmBox('Išbraukti sportininką iš šios kategorijos?', { ok: 'Išbraukti' })) return;
    await api('entries/' + b.dataset.rmEntry, { method: 'DELETE' });
    await draw();
  });
}
const shortCat = (n) => n.replace('Kumite', 'Kum.').replace('Komandinė kata', 'Kom. kata').replace(' · ', ' ').replace('Berniukai', 'B').replace('Mergaitės', 'M').replace('Vyrai', 'V').replace('Moterys', 'M');

function addAthleteForm(box, t, done) {
  let chosen = null;
  render(box, html`<h2>Pridėti sportininką</h2>
    <label class="form" style="margin-bottom:8px"><span class="small" style="font-weight:600">Jau buvęs sportininkas?</span><input id="as" placeholder="Paieška pagal vardą…" autocomplete="off"></label><div id="as-list"></div>
    <form class="form" id="af" style="margin-top:8px">
      <div class="form-row"><label>Vardas<input name="first_name" required></label><label>Pavardė<input name="last_name" required></label></div>
      <div class="form-row"><label>Gimimo data<input name="birth_date" type="date" required></label>
        <label>Lytis<select name="gender" required><option value="">—</option><option value="M">Vyras / berniukas</option><option value="F">Moteris / mergaitė</option></select></label></div>
      <div class="form-row"><label>Diržas<select name="belt_level">${beltOptions(null)}</select></label><label>Svoris, kg<input name="weight_kg" inputmode="decimal"></label></div>
      <div class="form-row"><label>Klubas<select name="club_id" id="club-sel"><option value="">— naujas klubas —</option></select></label><label>Naujas klubas<input name="club_name" placeholder="pavadinimas"></label></div>
      <div><div class="hint" style="margin-bottom:6px">Kategorijos (tinkamos pagal duomenis)</div><div class="cat-checks" id="cats"><span class="hint">Įveskite gimimo datą ir lytį.</span></div></div>
      <label class="check"><input type="checkbox" name="force"><span>Leisti į netinkamą kategoriją (pvz. vyresnių)</span></label>
      <button class="btn btn-primary" type="submit">Pridėti</button>
    </form>`);
  const form = $('#af', box);
  api('clubs', { quiet: true }).then(({ clubs }) => { $('#club-sel', box).insertAdjacentHTML('beforeend', clubs.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('')); });
  let allCats = null;
  const refresh = async () => {
    const f = formData(form);
    const catsBox = $('#cats', box);
    if (f.force) {
      allCats = allCats || (await api('tournaments/' + t.id)).categories.filter((c) => c.entry_type === 'individual' && c.status === 'open');
      render(catsBox, allCats.map((c) => html`<label><input type="checkbox" name="category_ids[]" value="${c.id}">${c.name}</label>`));
      return;
    }
    if (!f.birth_date || !f.gender) return;
    const { categories } = await api(`tournaments/${t.id}/eligible`, { method: 'POST', body: f, quiet: true });
    render(catsBox, categories.length ? categories.map((c) => html`<label><input type="checkbox" name="category_ids[]" value="${c.id}" checked>${c.name}</label>`) : html`<span class="hint">Tinkamų atvirų kategorijų nėra.</span>`);
  };
  form.addEventListener('change', (e) => { if (e.target.name !== 'category_ids[]') refresh(); });
  athleteSearch($('#as', box), $('#as-list', box), (a) => {
    chosen = a;
    for (const k of ['first_name', 'last_name', 'birth_date', 'gender', 'belt_level', 'weight_kg']) form[k].value = a[k] ?? '';
    form.club_id.value = a.club_id || '';
    refresh();
  });
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = formData(form);
    const body = {
      athlete: { first_name: f.first_name, last_name: f.last_name, birth_date: f.birth_date, gender: f.gender, belt_level: f.belt_level, weight_kg: f.weight_kg },
      category_ids: (f.category_ids || []).map(Number), force: f.force,
    };
    if (chosen && chosen.first_name === f.first_name && chosen.last_name === f.last_name) body.athlete_id = chosen.id;
    if (f.club_id) body.club_id = Number(f.club_id); else if (f.club_name) body.club = { name: f.club_name };
    await busy(e.submitter, async () => {
      await api(`tournaments/${t.id}/athletes`, { method: 'POST', body });
      toast(`${f.first_name} ${f.last_name} pridėtas.`);
      done();
    });
  });
}

// ---------- Tvarkaraštis (redagavimas) ----------
async function aSchedule(box, t) {
  const draw = async () => {
    const { schedule } = await api(`tournaments/${t.id}/schedule`);
    const tat = Array.from({ length: t.tatami_count }, (_, i) => i + 1);
    const item = (c) => html`<div class="sched-edit" data-cat="${c.id}">
      <div><b class="small">${c.name}</b><div class="tiny muted">${c.entries_count} dal. · ~${c.minutes} min.${c.est_start ? ` · ${c.est_start}–${c.est_end}` : ''}</div></div>
      <div class="ctrl"><button class="icon-btn" data-move="-1" title="Aukštyn">↑</button><button class="icon-btn" data-move="1" title="Žemyn">↓</button>
        <select data-tat><option value="">—</option>${tat.map((n) => html`<option value="${n}" ${c.tatami === n ? 'selected' : ''}>T${n}</option>`)}</select>
        <span class="hint">ne anksčiau</span><input type="time" data-nb value="${c.not_before || ''}" title="Ne anksčiau nei"></div></div>`;
    render(box, html`<div class="row between" style="margin-bottom:12px"><p class="small muted">Rodyklėmis keiskite eilę, sąraše - tatami. Laikas dešinėje - „ne anksčiau nei“ (pvz. finalai po pietų).</p>
      <div class="row"><button class="btn btn-ghost" data-act="auto">⚡ Paskirstyti automatiškai</button><button class="btn btn-primary" data-act="save">Išsaugoti</button></div></div>
      <div class="sched" style="--cols:${schedule.tatami.length + (schedule.unassigned.length ? 1 : 0)}">
        ${schedule.tatami.map((x) => html`<div class="panel sched-col" data-col="${x.tatami}"><h3>Tatami ${x.tatami} <span class="small muted mono">iki ${x.end || '—'}</span></h3>${x.categories.map(item)}</div>`)}
        ${schedule.unassigned.length ? html`<div class="panel sched-col" data-col=""><h3>Nepriskirta</h3>${schedule.unassigned.map(item)}</div>` : ''}
      </div>`);
  };
  await draw();
  const collect = () => {
    const items = [];
    $$('[data-col]', box).forEach((col) => {
      $$('[data-cat]', col).forEach((el, i) => {
        items.push({ id: Number(el.dataset.cat), tatami: $('[data-tat]', el).value || null, sort_order: (i + 1) * 10 + (Number($('[data-tat]', el).value) || 0) * 1000, not_before: $('[data-nb]', el).value || null });
      });
    });
    return items;
  };
  on(box, 'click', '[data-move]', (e, b) => {
    const el = b.closest('[data-cat]');
    if (b.dataset.move === '-1' && el.previousElementSibling?.dataset.cat) el.parentNode.insertBefore(el, el.previousElementSibling);
    if (b.dataset.move === '1' && el.nextElementSibling) el.parentNode.insertBefore(el.nextElementSibling, el);
  });
  on(box, 'change', '[data-tat]', (e, s) => {
    const el = s.closest('[data-cat]');
    const col = $(`[data-col="${s.value}"]`, box);
    if (col) col.appendChild(el);
  });
  on(box, 'click', '[data-act=save]', async (e, b) => { await busy(b, () => api(`tournaments/${t.id}/schedule`, { method: 'PUT', body: { items: collect() } })); toast('Tvarkaraštis išsaugotas.'); await draw(); });
  on(box, 'click', '[data-act=auto]', async (e, b) => {
    if (!await confirmBox('Paskirstyti visas kategorijas tatami automatiškai? Dabartinė eilė bus pakeista.')) return;
    await busy(b, () => api(`tournaments/${t.id}/schedule/auto`, { method: 'POST' }));
    await draw();
  });
}

// ---------- Vykdymas: tatami pasirinkimas ----------
async function aRun(box, t) {
  const draw = async () => {
    const d = await api(`tournaments/${t.id}/live?next=3`, { quiet: true });
    render(box, html`<p class="small muted" style="margin-bottom:12px">Kiekvienas sekretorius telefone ar planšetėje atsidaro savo tatami. Rezultatai iškart matomi lentelėse ir salės ekrane.</p>
      <div class="live-grid">${d.tatami.map((x) => html`<div class="panel live-col">
        <h3>Tatami ${x.tatami} ${x.current.length ? raw('<span class="live-dot"></span>') : ''}</h3>
        <p class="small muted">${x.current.length ? `Vyksta: ${x.current[0].category.name}` : 'Laisvas'} · laukia ${x.next_total}</p>
        <a class="btn btn-primary btn-block" style="margin-top:10px" href="#/admin/t/${t.id}/tatami/${x.tatami}">Atidaryti tatami ${x.tatami} pultą</a></div>`)}</div>
      <div class="row" style="margin-top:14px"><a class="btn btn-dark" href="#/t/${t.id}/ekranas" target="_blank">🖥 Salės ekranas</a></div>`);
  };
  await draw();
  onLeave(poll(draw, 5000));
}

/** Sekretoriaus pultas vienam tatami: kas dabar ir kas toliau */
export async function secretaryTatami(main, { id, n }) {
  requireLogin();
  const draw = async () => {
    const d = await api(`tournaments/${id}/live?tatami=${n}&next=12`, { quiet: true });
    const x = d.tatami[0];
    const row = (it, now) => {
      const href = it.type === 'match' ? `#/admin/m/${it.id}?back=${encodeURIComponent(location.hash)}` : `#/t/${id}/c/${it.category.id}?perf=${it.id}`;
      return html`<a class="panel card tight" style="display:block;color:inherit;text-decoration:none;${now ? 'border:2px solid #D9534F' : ''}" href="${href}">
        <div class="row between"><span class="tiny muted">${it.category.name} · ${it.label}</span>${now ? html`<span class="badge badge-red"><span class="live-dot"></span>Vyksta</span>` : ''}</div>
        ${it.type === 'match' ? html`<div class="vs" style="margin-top:8px"><div class="pl aka"><b>${it.aka.name}</b><span>${it.aka.club || ''}</span></div>
          <div class="score">${sideScore(it, 'aka', it.category) || ''} ${now ? ':' : 'prieš'} ${sideScore(it, 'shiro', it.category) || ''}</div>
          <div class="pl shiro"><b>${it.shiro.name}</b><span>${it.shiro.club || ''}</span></div></div>`
          : html`<div style="margin-top:6px"><b>${it.entry.name}</b> <span class="small muted">${it.entry.club || ''}</span></div>`}
      </a>`;
    };
    render(main, html`<a class="back" href="#/admin/t/${id}?tab=vykdymas">← Vykdymas</a>
      <div class="page-head row between"><h1 class="styled" style="margin:0">Tatami ${n}</h1><span class="small muted">${d.tournament.title}</span></div>
      ${x.current.map((it) => row(it, true))}
      ${x.next.length ? html`<h3 style="margin:16px 0 10px">Toliau (${x.next_total})</h3>${x.next.map((it) => row(it, false))}` : !x.current.length ? html`<div class="panel card empty">Šiam tatami laukiančių kovų nėra.<br><span class="small">Patikrinkite, ar ištraukti burtai ir priskirtos kategorijos (Tvarkaraštis).</span></div>` : ''}`);
  };
  await draw();
  onLeave(watchTournament(Number(id), draw, 3000));
}

// ---------- Kategorijos puslapio įrankiai (organizatoriui) ----------
export function categoryAdminBar(d) {
  const c = d.category;
  const perf = d.performances;
  const canNext = c.format === 'scores' && perf && perf.current < perf.total_rounds && perf.left === 0 && perf.current > 0;
  return html`<div class="panel card tight no-print" style="background:rgba(255,255,255,0.8)"><div class="row between">
    <span class="small"><b>Valdymas:</b> ${c.status === 'open' ? 'ištraukite burtus, kai registracija baigta.' : c.format === 'scores' ? 'paspauskite dalyvį, kad įvestumėte balus.' : 'paspauskite kovą, kad įvestumėte rezultatą.'}</span>
    <div class="row">
      ${c.status === 'open' ? html`<button class="btn btn-sm btn-primary" data-cact="draw" ${d.entries.length ? '' : 'disabled'}>Ištraukti burtus</button>` : ''}
      ${canNext ? html`<button class="btn btn-sm btn-pink" data-cact="next">Sudaryti kitą ratą (${perf.rounds[perf.current - 1]?.advance})</button>` : ''}
      ${c.format === 'scores' && perf && perf.current > 1 ? html`<button class="btn btn-sm btn-ghost" data-cact="undo-round">Panaikinti paskutinį ratą</button>` : ''}
      ${c.status !== 'open' ? html`<button class="btn btn-sm btn-danger" data-cact="undraw">Panaikinti burtus</button>` : ''}
      ${state.user.role === 'admin' ? html`<a class="btn btn-sm btn-ghost" href="#/admin/t/${c.tournament_id}?tab=kategorijos">Kategorijos</a>` : ''}
    </div></div></div>`;
}

export function bindCategoryAdmin(main, getD, reload) {
  on(main, 'click', '[data-cact]', async (e, b) => {
    const c = getD().category;
    const act = b.dataset.cact;
    if (act === 'undraw' && !await confirmBox('Panaikinti burtus? Visos kovos ir balai šioje kategorijoje bus ištrinti.', { danger: true, ok: 'Panaikinti' })) return;
    const path = { draw: 'draw', next: 'next-round', 'undo-round': 'undo-round', undraw: 'undraw' }[act];
    await busy(b, () => api(`categories/${c.id}/${path}`, { method: 'POST' }));
    await reload();
  });
  on(main, 'click', '.match.clickable', (e, el) => go(`#/admin/m/${el.dataset.match}?back=${encodeURIComponent(location.hash)}`));
  on(main, 'click', '[data-perf]', (e, el) => {
    const d = getD();
    if (!d.performances) return;
    const p = d.performances.rounds.flatMap((r) => r.items).find((x) => x.id === Number(el.dataset.perf));
    if (p && d.performances.rounds[d.performances.current - 1].items.includes(p)) performanceModal(p, d, reload);
  });
  // Atidaryti iš sekretoriaus pulto (?perf=)
  const m = location.hash.match(/[?&]perf=(\d+)/);
  if (m) {
    const d = getD();
    const p = d.performances?.rounds.flatMap((r) => r.items).find((x) => x.id === Number(m[1]));
    if (p) setTimeout(() => performanceModal(p, d, reload), 50);
  }
}

/** Balų įvedimas vienam atlikimui */
function performanceModal(p, d, reload) {
  const c = d.category;
  const J = c.judges;
  const vals = p.scores.length ? p.scores.map((s) => s.toFixed(1)) : Array(J).fill('');
  const md = modal(html`<form class="form" id="pf">
    <div><b style="font-size:1.15rem">${p.entry.name}</b> <span class="muted small">${p.entry.club || ''}</span><div class="tiny muted">#${p.order_no} · balai ${c.score_min.toFixed(1)}–${c.score_max.toFixed(1)}</div></div>
    <label>Kata<input name="kata" list="katas" value="${p.kata || ''}" placeholder="pvz. Heian Shodan"></label>
    <datalist id="katas">${KATAS.map((k) => html`<option value="${k}">`)}</datalist>
    <div class="judges" style="--j:${J}">${vals.map((v, i) => html`<label>${i + 1} teisėjas<input name="s${i}" inputmode="decimal" value="${v}" autocomplete="off"></label>`)}</div>
    <div class="total-box"><span id="total">—</span><div class="tiny muted" style="font-family:Inter">${J >= 5 ? 'didžiausias ir mažiausias atmetami' : 'balų suma'}</div></div>
    ${p.tied ? html`<label>Vieta po papildomo atlikimo (lygiems)<input name="manual_rank" type="number" min="1" value="${p.manual_rank ?? ''}"></label>` : ''}
    <div class="actions" style="justify-content:space-between">
      <div class="row"><button type="button" class="btn btn-ghost btn-sm" data-pact="running">▶ Dabar atlieka</button><button type="button" class="btn btn-danger btn-sm" data-pact="dq">Diskvalifikuoti</button>${p.status !== 'waiting' ? html`<button type="button" class="btn btn-ghost btn-sm" data-pact="waiting">Išvalyti</button>` : ''}</div>
      <button class="btn btn-primary" type="submit">Išsaugoti</button></div>
  </form>`, { title: 'Balai' });
  const form = $('#pf', md.el);
  const inputs = $$('.judges input', form);
  const calc = () => {
    const s = inputs.map((x) => parseFloat(x.value.replace(',', '.')));
    inputs.forEach((x) => x.classList.remove('drop'));
    if (s.some((v) => Number.isNaN(v))) { $('#total', form).textContent = '—'; return; }
    let drop = [];
    if (J >= 5) {
      const max = s.indexOf(Math.max(...s));
      let min = s.indexOf(Math.min(...s));
      if (min === max) min = s.findIndex((v, i) => v === Math.min(...s) && i !== max);
      drop = [max, min];
      drop.forEach((i) => inputs[i].classList.add('drop'));
    }
    $('#total', form).textContent = s.reduce((a, v, i) => (drop.includes(i) ? a : a + v), 0).toFixed(1);
  };
  calc();
  form.addEventListener('input', calc);
  // Enter pereina į kitą laukelį (patogu telefone)
  inputs.forEach((x, i) => x.addEventListener('keydown', (e) => { if (e.key === 'Enter' && i < inputs.length - 1) { e.preventDefault(); inputs[i + 1].focus(); } }));
  const save = async (body, btn) => {
    await busy(btn, () => api('performances/' + p.id, { method: 'PUT', body }));
    md.close();
    await reload();
  };
  on(md.el, 'click', '[data-pact]', async (e, b) => {
    const act = b.dataset.pact;
    if (act === 'dq' && !await confirmBox(`Diskvalifikuoti ${p.entry.name}?`, { danger: true, ok: 'Diskvalifikuoti' })) return;
    await save({ status: act, kata: form.kata.value }, b);
  });
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = formData(form);
    await save({ scores: inputs.map((x) => x.value), kata: f.kata, ...(p.tied ? { manual_rank: f.manual_rank } : {}) }, e.submitter);
    toast(`${p.entry.name}: ${$('#total', form)?.textContent || ''}`);
  });
}

// ---------- Integracija su karateka.lt ----------
async function aIntegration(box, t) {
  const info = await api(`tournaments/${t.id}/integration`);
  const k = info.keys.find((x) => x.name === 'karateka');
  render(box, html`<div class="grid-2" style="align-items:start">
    <div class="panel card"><h2>Kaip sujungta su karateka.lt</h2>
      <ol class="small" style="padding-left:18px;display:grid;gap:8px">
        <li><b>Registracija.</b> karateka.lt nario paskyroje atsiranda skiltis „Varžybos“: narys (ar tėvai už vaiką) mato atviras varžybas, jam tinkamas kategorijas ir užsiregistruoja vienu paspaudimu. Duomenys (vardas, gimimo data, lytis, diržas) imami iš paskyros.</li>
        <li><b>Atpažinimas.</b> Kiekvienas sportininkas čia saugomas su karateka.lt nario id, todėl kitais metais jo nereikia įvesti iš naujo.</li>
        <li><b>Rezultatai atgal.</b> karateka.lt trenerio panelėje prie renginio paspaudus „Įkelti iš varžybų sistemos“ užimtos vietos patenka į „Renginio rezultatus“ ir taškai paskiriami automatiškai (dalyvavimas, 1–3 vieta, LT / užsienis).</li>
      </ol>
      <hr class="divider">
      <p class="small">API adresas: <code>${info.api_url}</code></p>
      <p class="small" style="margin-top:6px">karateka.lt raktas: ${k?.configured ? html`<span class="badge badge-ok">nustatytas config.php</span>` : html`<span class="badge badge-warn">nenustatytas</span>`}</p>
      <p class="small muted" style="margin-top:6px">Paruošti karateka.lt failai yra aplanke <code>karateka-integracija/</code> (su instrukcija).</p>
    </div>
    <div class="panel card"><h2>Išbandyti</h2>
      <p class="small muted">Imituoja, ką padaro karateka.lt, kai narys paspaudžia „Registruotis“. ${t.registration_open ? '' : html`<b>Pirma įjunkite registraciją</b> (būsena „Vyksta registracija“).`}</p>
      <form class="form" id="sim" style="margin-top:12px">
        <div class="form-row"><label>Nario id karateka.lt<input name="external_id" value="9001" required></label><label>Klubas<input name="club" value="Karate Ateitis"></label></div>
        <div class="form-row"><label>Vardas<input name="first_name" value="Testas" required></label><label>Pavardė<input name="last_name" value="Karatekaitis" required></label></div>
        <div class="form-row three"><label>Gimimo data<input name="birth_date" type="date" value="2014-03-15" required></label>
          <label>Lytis<select name="gender"><option value="M">M</option><option value="F">F</option></select></label><label>Diržas<select name="belt_level">${beltOptions(4)}</select></label></div>
        <button class="btn btn-pink" type="submit">Registruoti kaip karateka.lt</button>
      </form><div id="sim-out" style="margin-top:12px"></div>
      <hr class="divider">
      <h3>Rezultatų formatas</h3>
      <p class="small muted">karateka.lt gauna kiekvienos kategorijos vietas su nario id. Pavyzdys (pirmos kategorijos):</p>
      <div id="res-sample" class="small muted">Kraunama…</div>
    </div></div>`);
  on(box, 'submit', '#sim', async (e) => {
    e.preventDefault();
    const f = formData(e.target);
    await busy(e.submitter, async () => {
      const r = await api(`tournaments/${t.id}/integration/test`, { method: 'POST', body: { source: 'karateka', payload: {
        athlete: { external_id: f.external_id, first_name: f.first_name, last_name: f.last_name, birth_date: f.birth_date, gender: f.gender, belt_level: f.belt_level },
        club: { external_id: 'karate-ateitis', name: f.club }, category_ids: 'auto',
      } } });
      render($('#sim-out', box), html`<div class="flash flash-ok">Užregistruota: ${r.registrations.map((x) => x.category).join(', ')}</div>`);
    });
  });
  const res = await api(`tournaments/${t.id}/results`, { quiet: true });
  const sample = res.categories.find((c) => c.results.length);
  render($('#res-sample', box), sample ? html`<span class="code">${JSON.stringify({ category: sample.name, results: sample.results.slice(0, 2).map((r) => ({ place: r.place, athletes: r.athletes.map((a) => ({ external_source: a.external_source, external_id: a.external_id, name: a.name })) })) }, null, 2)}</span>` : html`Rezultatų dar nėra.`);
}

void genderLabel; void watchTournament;
