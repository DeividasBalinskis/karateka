// Programos pradžia: antraštė, maršrutai

import { api, state, html, render, $, on, route, router, go, toast } from './core.js';
import { homePage, tournamentPage, categoryPage, loginPage } from './views-public.js';
import { adminHome, adminTournament, secretaryTatami } from './views-admin.js';
import { matchPage } from './scoring.js';
import { screenPage } from './screen.js';

route('/', homePage);
route('/t/:id', tournamentPage);
route('/t/:id/c/:cid', categoryPage);
route('/t/:id/ekranas', screenPage);
route('/prisijungti', loginPage);
route('/admin', adminHome);
route('/admin/t/:id', adminTournament);
route('/admin/t/:id/tatami/:n', secretaryTatami);
route('/admin/m/:mid', matchPage);

function header() {
  const u = state.user;
  const path = location.hash.slice(1) || '/';
  render($('#top'), html`<nav>
    <a class="brand" href="#/"><img src="img/logo.png" alt="Karateka"><span class="tag">${state.meta.site_name}</span></a>
    <div class="links">
      <a href="#/" class="${path === '/' || path.startsWith('/t/') ? 'active' : ''}">Varžybos</a>
      ${u ? html`<a href="#/admin" class="${path.startsWith('/admin') ? 'active' : ''}">Valdymas</a>
        <span class="user">${u.name}</span><button data-act="logout">Atsijungti</button>`
        : html`<a class="login" href="#/prisijungti">Prisijungti</a>`}
    </div></nav>`);
}

async function start() {
  try {
    state.meta = await api('meta');
  } catch (e) {
    render($('#page'), html`<div class="panel card empty"><h2>Nepavyko prisijungti prie serverio</h2><p>${e.message}</p></div>`);
    return;
  }
  state.user = state.meta.user;
  state.csrf = state.meta.csrf;
  document.title = state.meta.site_name;
  header();
  document.addEventListener('auth', header);
  document.addEventListener('routed', header);
  on($('#top'), 'click', '[data-act=logout]', async () => {
    await api('auth/logout', { method: 'POST' });
    state.user = null;
    header();
    toast('Atsijungėte.');
    go('#/');
  });
  window.addEventListener('hashchange', router);
  router();
}

start();
