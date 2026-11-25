// Bendros testų priemonės: prisijungimas, SQL užklausos testų bazei, pavyzdinių narių id.
const { expect } = require('@playwright/test');
const { execFileSync } = require('child_process');

const PASSWORD = 'testas123';
const DEMO_PASSWORD = 'demo12345';   // tools/demo-duomenys.php
const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8088/';

/** Viena reikšmė (ar keli stulpeliai, atskirti tab) iš testų duomenų bazės */
function sql(query) {
  const args = ['--default-character-set=utf8mb4', '-N', '-h', process.env.DB_HOST || 'localhost', '-u', process.env.DB_USER || 'root'];
  if (process.env.DB_PASS) args.push('-p' + process.env.DB_PASS);
  args.push(process.env.DB_NAME || 'karateka', '-e', query);
  return execFileSync('mysql', args).toString().trim();
}

/**
 * C3: slaptos nuorodos raktas (app/links.php saugo tik pirštų atspaudą ir užrakintą kopiją - raktą duoda tests/setup/link.php).
 * linkToken('live', tid) - dabartinė nuoroda ('' - nėra); linkNew('demo') - nauja (ankstesnė - „pakeista“).
 */
function linkCli(cmd, kind, owner, label) {
  const args = [require('path').join(__dirname, '..', 'setup', 'link.php'), cmd, kind, owner === undefined || owner === null ? '' : String(owner)];
  if (label) args.push(label);
  return execFileSync('php', args).toString().split(/\r?\n/).pop().trim();
}
function linkToken(kind, owner) { return linkCli('get', kind, owner); }
function linkNew(kind, owner, label) { return linkCli('new', kind, owner, label); }

/** Data po n dienų (YYYY-MM-DD, vietos laiku) */
function day(n) {
  const d = new Date();
  d.setDate(d.getDate() + n);
  return d.toLocaleDateString('sv-SE', { timeZone: 'Europe/Vilnius' });
}

/** Pavyzdinių narių id (app/cli/dev_seed.php) */
function ids() {
  return {
    jonas: +sql("SELECT id FROM members WHERE first_name='Jonas' AND last_name='Petrauskas' LIMIT 1"),
    austeja: +sql("SELECT id FROM members WHERE first_name='Austėja' AND last_name='Petrauskaitė' LIMIT 1"),
    tomas: +sql("SELECT m.id FROM members m JOIN account_members am ON am.member_id = m.id JOIN accounts a ON a.id = am.account_id WHERE a.email='narys@karateka.test' AND am.relation='self'"),
  };
}

/**
 * Naujas naršyklės kontekstas (ir prisijungimas, jei nurodytas el. paštas).
 * JS klaidos puslapyje surenkamos į page.jsErrors - testas jas patikrina pabaigoje (noJsErrors).
 */
// C6: ankstesnių testų langai uždaromi - kitaip šimtai atvirų puslapių kas 30 s kreipiasi į serverį ir vėlesni testai lėtėja
const openContexts = [];
function currentTestId() {
  try { return require('@playwright/test').test.info().testId; } catch (e) { return ''; }
}
async function login(browser, email, width = 1280, height = 900, password = PASSWORD, lang = 'lt') {
  const tid = currentTestId();
  for (let i = openContexts.length - 1; i >= 0; i--) {
    if (openContexts[i].tid !== tid) { await openContexts[i].ctx.close().catch(() => {}); openContexts.splice(i, 1); }
  }
  const ctx = await browser.newContext({ baseURL: BASE_URL, viewport: { width, height } });
  openContexts.push({ tid, ctx });
  if (lang !== 'lt') await ctx.addCookies([{ name: 'kalba', value: lang, url: BASE_URL }]);
  // Treneriams / adminui - admin režimas įjungtas (redagavimo mygtukai), kaip anksčiau; žaidimo testai jį išjungia patys
  await ctx.addCookies([{ name: 'adm', value: '1', url: BASE_URL }]);
  const page = await ctx.newPage();
  page.jsErrors = [];
  page.on('pageerror', e => page.jsErrors.push(e.message));
  page.on('dialog', d => d.accept());
  // Šventės langas (naujas lygis / išlaikytas egzaminas) atsiranda po XP bet kuriame puslapyje ir uždengia mygtukus -
  // uždaromas pats prieš kiekvieną veiksmą. Testas, kuris tikrina pačią šventę, nustato page.keepCelebration = true.
  await page.addLocatorHandler(page.locator('#celebrate'), async () => {
    if (page.keepCelebration) return;
    await page.locator('#celebrate button').click({ timeout: 3000 }).catch(() => {});
  }, { noWaitAfter: true });
  if (email) {
    // C2 (v1.78): kalba saugoma paskyroje - kiekvienas testas prisijungia ta kalba, kurią nurodo (kitas testas galėjo perjungti į RU)
    try { sql(`UPDATE accounts SET lang = NULL WHERE email = '${email.replace(/'/g, "''")}'`); } catch (e) {}
    await page.goto('prisijungti.php');
    await page.fill('input[name=email]', email);
    await page.fill('input[name=password]', password);
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  }
  return page;
}

/** Lygis pagal XP - kaip app/xp.php xp_level(): pirmi 5 lygiai po base XP, kas 5 lygius brangiau po step (v1.96) */
function xpLevel(xp, base = 5, step = 2, every = 5) {
  let level = 1, start = 0;
  const cost = n => base + step * Math.floor((Math.max(1, n) - 1) / every);
  while (level < 1000 && xp >= start + cost(level)) { start += cost(level); level++; }
  return level;
}

/** Uždaro šventės langus, jei jie atidaryti (kelis iš eilės - po vieną puslapyje) */
async function closeCelebrate(page) {
  for (let i = 0; i < 4 && await page.locator('#celebrate').count(); i++) {
    await page.locator('#celebrate button').click({ timeout: 3000 }).catch(() => {});
    await page.waitForTimeout(300);
  }
}

/** Paspaudimas, po kurio įkeliamas naujas puslapis */
function nav(page, action) {
  return Promise.all([page.waitForNavigation(), action()]);
}

/** Tikrinimas, kuris nesustabdo testo - pabaigoje matomi visi nepavykę */
function check(name, condition, details = '') {
  expect.soft(condition, details !== '' ? `${name} (${details})` : name).toBeTruthy();
}

function noJsErrors(...pages) {
  for (const p of pages) {
    expect.soft(p.jsErrors, 'JS klaidos puslapyje').toEqual([]);
  }
}

async function flashes(page) {
  return (await page.locator('.flash').allTextContents()).join(' | ');
}

/** 💡 atsiliepimo langas - savas mygtukas visuose ekranuose (v1.84: nebe per bendrą 💡/💬 meniu) */
async function openFeedback(page) {
  // v1.87: langas gali būti jau atidarytas (išlieka pereinant į kitą puslapį) - tada nespaudžiama, kad neužsidarytų
  await page.waitForSelector('#fbFab');
  if (await page.locator('#fbPanel').isHidden()) await page.click('#fbFab');
}

module.exports = { openFeedback, closeCelebrate, xpLevel, sql, linkToken, linkNew, day, ids, login, nav, check, noJsErrors, flashes, PASSWORD, DEMO_PASSWORD, BASE_URL };
