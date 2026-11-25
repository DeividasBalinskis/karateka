// Bendros priemonės: API, HTML šablonai, pranešimai, langai, datos, maršrutai

export const state = { meta: null, user: null, csrf: '', serverOffset: 0 };

// ---------- API ----------
export async function api(path, { method = 'GET', body = null, quiet = false } = {}) {
  const opt = { method, headers: { Accept: 'application/json' }, credentials: 'same-origin' };
  if (method !== 'GET') {
    opt.headers['Content-Type'] = 'application/json';
    opt.headers['X-CSRF-Token'] = state.csrf;
    opt.body = JSON.stringify(body ?? {});
  }
  const [route, query] = path.split('?');
  let res;
  try {
    res = await fetch('api.php?r=' + route + (query ? '&' + query : ''), opt);
  } catch (e) {
    const err = new Error('Nėra ryšio su serveriu.');
    if (!quiet) toast(err.message, 'err');
    throw err;
  }
  let data = {};
  try { data = await res.json(); } catch (e) { /* tuščias atsakymas */ }
  if (data.server_time) state.serverOffset = data.server_time - Date.now();
  if (data.csrf) state.csrf = data.csrf;
  if (!res.ok) {
    const err = new Error(data.error || 'Klaida (' + res.status + ')');
    err.status = res.status;
    if (res.status === 401 && state.user) {
      state.user = null;
    }
    if (!quiet) toast(err.message, 'err');
    throw err;
  }
  return data;
}

export const serverNow = () => Date.now() + state.serverOffset;

// ---------- HTML ----------
const ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
export const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ESC[c]);

class Raw { constructor(s) { this.s = s; } toString() { return this.s; } }
export const raw = (s) => new Raw(s);

/** html`<b>${vardas}</b>` - įterptos reikšmės išvalomos, masyvai sujungiami */
export function html(strings, ...vals) {
  let out = '';
  strings.forEach((s, i) => {
    out += s;
    if (i < vals.length) out += fmt(vals[i]);
  });
  return raw(out);
}
function fmt(v) {
  if (v === null || v === undefined || v === false) return '';
  if (v instanceof Raw) return v.s;
  if (Array.isArray(v)) return v.map(fmt).join('');
  return esc(v);
}
export const render = (el, content) => { el.innerHTML = Array.isArray(content) ? content.join('') : String(content); return el; };
export const $ = (sel, root = document) => root.querySelector(sel);
export const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

/** Įvykių delegavimas: on(root, 'click', '[data-act=save]', (e, el) => ...) */
export function on(root, type, sel, fn) {
  const h = (e) => {
    const el = e.target.closest(sel);
    if (el && root.contains(el)) fn(e, el);
  };
  root.addEventListener(type, h);
  return () => root.removeEventListener(type, h);
}

// ---------- Pranešimai ----------
export function toast(msg, type = 'ok') {
  let box = $('.toasts');
  if (!box) { box = document.createElement('div'); box.className = 'toasts'; document.body.appendChild(box); }
  const t = document.createElement('div');
  t.className = 'toast ' + (type === 'err' ? 'err' : '');
  t.textContent = msg;
  box.appendChild(t);
  setTimeout(() => t.remove(), type === 'err' ? 6000 : 3000);
}

/** Modalinis langas. Grąžina {el, close}. onClose kviečiamas uždarius. */
export function modal(content, { title = '', wide = false, onClose = null } = {}) {
  const back = document.createElement('div');
  back.className = 'modal-back';
  back.innerHTML = `<div class="modal ${wide ? 'wide' : ''}" role="dialog" aria-modal="true">
    <button class="icon-btn close" data-close aria-label="Uždaryti">✕</button>
    ${title ? `<h2>${esc(title)}</h2>` : ''}<div class="modal-body">${content}</div></div>`;
  document.body.appendChild(back);
  document.body.style.overflow = 'hidden';
  const close = () => {
    back.remove();
    if (!$('.modal-back')) document.body.style.overflow = '';
    document.removeEventListener('keydown', esc_);
    onClose && onClose();
  };
  const esc_ = (e) => { if (e.key === 'Escape') close(); };
  document.addEventListener('keydown', esc_);
  back.addEventListener('mousedown', (e) => { if (e.target === back) close(); });
  on(back, 'click', '[data-close]', close);
  const first = back.querySelector('input:not([type=hidden]), select, textarea');
  if (first && window.matchMedia('(hover:hover)').matches) setTimeout(() => first.focus(), 30);
  return { el: back.querySelector('.modal'), close };
}

export function confirmBox(message, { ok = 'Taip', danger = false } = {}) {
  return new Promise((resolve) => {
    let done = false;
    const m = modal(html`<p>${message}</p><div class="actions">
      <button class="btn btn-ghost" data-close>Atšaukti</button>
      <button class="btn ${danger ? 'btn-danger' : 'btn-primary'}" data-ok>${ok}</button></div>`, {
      onClose: () => { if (!done) resolve(false); },
    });
    m.el.querySelector('[data-ok]').addEventListener('click', () => { done = true; m.close(); resolve(true); });
  });
}

/** Formos laukai -> objektas (checkbox -> bool, tušti -> null) */
export function formData(form) {
  const out = {};
  for (const el of form.elements) {
    if (!el.name || el.disabled) continue;
    if (el.type === 'checkbox') {
      if (el.name.endsWith('[]')) {
        const k = el.name.slice(0, -2);
        out[k] = out[k] || [];
        if (el.checked) out[k].push(el.value);
      } else out[el.name] = el.checked;
    } else if (el.type === 'radio') {
      if (el.checked) out[el.name] = el.value;
    } else {
      out[el.name] = el.value === '' ? null : el.value;
    }
  }
  return out;
}

/** Mygtukas "užimtas" kol vyksta veiksmas */
export async function busy(btn, fn) {
  if (!btn) return fn();
  const old = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner" style="width:16px;height:16px;margin:0;border-width:2px"></span>';
  try { return await fn(); } finally { btn.disabled = false; btn.innerHTML = old; }
}

// ---------- Datos ----------
const MONTHS = ['sausio', 'vasario', 'kovo', 'balandžio', 'gegužės', 'birželio', 'liepos', 'rugpjūčio', 'rugsėjo', 'spalio', 'lapkričio', 'gruodžio'];
const MONTHS_SHORT = ['saus.', 'vas.', 'kov.', 'bal.', 'geg.', 'birž.', 'liep.', 'rugp.', 'rugs.', 'spal.', 'lapkr.', 'gruod.'];
export function fmtDate(d, withYear = true) {
  if (!d) return '';
  const [y, m, day] = d.split('-').map(Number);
  return (withYear ? y + ' m. ' : '') + MONTHS[m - 1] + ' ' + day + ' d.';
}
export function dateRange(a, b) {
  if (!b || a === b) return fmtDate(a);
  return fmtDate(a) + ' – ' + fmtDate(b, false);
}
export const monthShort = (d) => MONTHS_SHORT[Number(d.split('-')[1]) - 1];
export const dayNum = (d) => Number(d.split('-')[2]);
export const fmtTimer = (ms) => {
  const s = Math.max(0, Math.ceil(ms / 1000));
  return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
};

// ---------- Sąvokos ----------
export const belt = (level) => {
  const b = state.meta?.belts.find((x) => x.level === Number(level));
  return b ? html`<span class="belt"><i style="background:${b.color}"></i>${b.kyu}</span>` : '';
};
export const beltOptions = (sel, empty = '—') => [
  html`<option value="">${empty}</option>`,
  ...(state.meta?.belts || []).map((b) => html`<option value="${b.level}" ${Number(sel) === b.level ? 'selected' : ''}>${b.kyu} · ${b.name}</option>`),
];
export const options = (obj, sel) => Object.entries(obj).map(([k, v]) => html`<option value="${k}" ${String(sel) === k ? 'selected' : ''}>${v}</option>`);
export const genderLabel = (g) => ({ M: 'Vyrai', F: 'Moterys', X: 'Mišri' }[g] || g);
export const statusBadge = (s, label) => {
  const cls = { registration: 'badge-ok', running: 'badge-red', finished: 'badge-dark', closed: 'badge-warn', draft: '' }[s] || '';
  return html`<span class="badge ${cls}">${s === 'running' ? raw('<span class="live-dot"></span>') : ''}${label}</span>`;
};
export const catStatus = {
  open: ['Registracija', 'badge-ok'],
  drawn: ['Burtai ištraukti', 'badge-blue'],
  running: ['Vyksta', 'badge-red'],
  finished: ['Baigta', 'badge-dark'],
};
export const catStatusBadge = (s) => {
  const [l, c] = catStatus[s] || [s, ''];
  return html`<span class="badge ${c}">${s === 'running' ? raw('<span class="live-dot"></span>') : ''}${l}</span>`;
};
export const medal = (place) => html`<span class="medal ${place <= 3 ? 'm' + place : 'mx'}">${place}</span>`;

// ---------- Periodinis atnaujinimas ----------
/** Kas ms kviečia fn, kol puslapis matomas. Grąžina stop funkciją. */
export function poll(fn, ms) {
  let timer = null;
  let stopped = false;
  const tick = async () => {
    if (stopped) return;
    if (!document.hidden) {
      try { await fn(); } catch (e) { /* bandysim vėliau */ }
    }
    if (!stopped) timer = setTimeout(tick, ms);
  };
  timer = setTimeout(tick, ms);
  return () => { stopped = true; clearTimeout(timer); };
}

/** Stebi varžybų "rev" ir kviečia onChange, kai kas nors pasikeitė */
export function watchTournament(tid, onChange, ms = 3000) {
  let rev = null;
  return poll(async () => {
    const d = await api(`tournaments/${tid}/rev`, { quiet: true });
    if (rev !== null && d.rev !== rev) await onChange();
    rev = d.rev;
  }, ms);
}

// ---------- Maršrutai ----------
const routes = [];
let cleanup = [];
export const route = (pattern, handler) => {
  const keys = [];
  const re = new RegExp('^' + pattern.replace(/:(\w+)/g, (_, k) => { keys.push(k); return '([^/]+)'; }) + '/?$');
  routes.push({ re, keys, handler });
};
/** Puslapio uždarymo darbai (sustabdyti atnaujinimą ir pan.) */
export const onLeave = (fn) => cleanup.push(fn);
export const go = (hash) => { if (location.hash === hash) router(); else location.hash = hash; };

export async function router() {
  cleanup.forEach((f) => { try { f(); } catch (e) { /* */ } });
  cleanup = [];
  document.body.classList.remove('screen');
  // Atidaryti langai užsidaro pakeitus puslapį
  $$('.modal-back').forEach((m) => m.remove());
  document.body.style.overflow = '';
  const path = (location.hash || '#/').slice(1).split('?')[0] || '/';
  const query = Object.fromEntries(new URLSearchParams(location.hash.split('?')[1] || ''));
  // Naujas elementas kiekvienam puslapiui - kad seno puslapio įvykių klausytojai neliktų
  const old = $('#page');
  const main = old.cloneNode(false);
  old.replaceWith(main);
  for (const r of routes) {
    const m = path.match(r.re);
    if (m) {
      const params = Object.fromEntries(r.keys.map((k, i) => [k, decodeURIComponent(m[i + 1])]));
      main.className = 'page';
      render(main, '<div class="loading"><div class="spinner"></div>Kraunama…</div>');
      window.scrollTo(0, 0);
      try {
        await r.handler(main, params, query);
      } catch (e) {
        if (e.status === 401) { go('#/prisijungti?next=' + encodeURIComponent(location.hash)); return; }
        render(main, html`<div class="panel card empty"><h2>Nepavyko atidaryti</h2><p>${e.message}</p><p><a href="#/">← Į pradžią</a></p></div>`);
      }
      document.dispatchEvent(new CustomEvent('routed', { detail: path }));
      return;
    }
  }
  render(main, html`<div class="panel card empty"><h2>Puslapis nerastas</h2><p><a href="#/">← Į pradžią</a></p></div>`);
}
