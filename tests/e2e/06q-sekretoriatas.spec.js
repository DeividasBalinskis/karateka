// v1.35: sekretoriato nuoroda varžyboms (be paskyros): vardas ir pavardė, tik šios varžybos, be klubo narių duomenų;
// adminas gali peržiūrėti, kaip mato sekretoriatas; išjungus nuorodą prieiga baigiasi.
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors } = require('./helpers');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8088/';

test('secretariat link: name only, only this competition, no member data; admin preview; turning off ends access', async ({ browser }) => {
  sql('DELETE FROM tournaments WHERE is_test = 1');
  const a = await login(browser, 'info@karateka.lt', 1400, 1000);
  await a.goto('admin/varzybos.php');
  await Promise.all([a.waitForNavigation(), a.click('text=🎲 Sukurti test varžybas')]);
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);

  // Adminas sukuria nuorodą
  await a.click('#sekr-toggle');
  await Promise.all([a.waitForNavigation(), a.click('#sekr-panel button[value="sekr_on"]')]);
  const link = await a.inputValue('#sekr-url');
  check('link created', /varzybos-sekretoriatas\.php\?k=[0-9a-f]{24}$/.test(link), link);

  // Peržiūra „kaip sekretoriatas“
  await Promise.all([a.waitForNavigation(), a.click('#sekr-preview')]);
  check('preview banner', await a.locator('.sekr-preview').isVisible());
  check('preview: no club members, no other competitions', await a.locator('#ppl-members').isHidden() && await a.locator('#t-switch').isHidden() && await a.evaluate(() => window.VARZ.members.length) === 0);

  // Sekretorius: be paskyros, su vardu ir pavarde
  const ctx = await browser.newContext({ baseURL: BASE_URL, viewport: { width: 1400, height: 1000 } });
  const g = await ctx.newPage();
  await g.goto(link);
  check('entry shows the competition', (await g.textContent('h1')).length > 3);
  await g.fill('input[name="name"]', 'T');
  await Promise.all([g.waitForNavigation(), g.click('button[type="submit"]')]);
  check('a name is required', /savo vardą/.test(await g.textContent('main')));
  await g.fill('input[name="name"]', 'Tomas');   // pavardė nebūtina
  await Promise.all([g.waitForNavigation(), g.click('button[type="submit"]')]);
  check('lands in the competition', new RegExp(`admin/varzybu-sistema\\.php\\?id=${tid}`).test(g.url()), g.url());
  check('name shown, no member data, no link management', /Tomas/.test(await g.textContent('.vz-sekr-who')) &&
    await g.evaluate(() => window.VARZ.members.length) === 0 && await g.locator('#sekr-toggle').count() === 0);

  // Gali vesti varžybas: pakeitimai išsaugomi
  const v0 = +sql(`SELECT version FROM tournaments WHERE id = ${tid}`);
  await g.click('#tab-live');
  await g.waitForTimeout(600);
  if (!(await g.locator('#run').isVisible())) await g.locator('#sched [data-open-cat]').first().click();
  await g.click('#draw-sim');
  await g.waitForTimeout(1500);
  check('secretariat changes are saved', +sql(`SELECT version FROM tournaments WHERE id = ${tid}`) > v0);

  // Kitur nepatenka
  await g.goto('admin/varzybos.php');
  check('competition list → back to own competition', new RegExp(`varzybu-sistema\\.php\\?id=${tid}`).test(g.url()), g.url());
  await g.goto(`admin/varzybos.php?id=${tid}`);   // planas A2: su ?id= anksčiau atsidarydavo trenerio veiksmai visoms varžyboms
  check('competition data page → back to own competition', new RegExp(`varzybu-sistema\\.php\\?id=${tid}`).test(g.url()), g.url());
  await g.goto('admin/nariai.php');
  check('no access to members', !/admin\/nariai\.php/.test(g.url()), g.url());

  // Išjungus nuorodą - prieiga baigiasi
  await a.goto(`admin/varzybu-sistema.php?id=${tid}&sekr=1`);
  await Promise.all([a.waitForNavigation(), a.click('#sekr-panel button[value="sekr_off"]')]);
  await g.goto(`admin/varzybu-sistema.php?id=${tid}`);
  check('access ends when the link is off', /prisijungti\.php/.test(g.url()), g.url());
  await ctx.close();
  sql('DELETE FROM tournaments WHERE is_test = 1');
  noJsErrors(a);
});
