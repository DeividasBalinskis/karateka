// v1.87 atsiliepimai pagal vietą: grupės Budo / Karateka / LTKF ir jų skiltys, pirmiausia - kur esate;
// langas, filtrai ir tekstas išlieka pereinant į kitą puslapį (be dublikatų); Valdymas → Atsiliepimai - tos pačios grupės ir paieška.
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors, openFeedback } = require('./helpers');

test('feedback by place: groups and sections, here first, the box survives page changes', async ({ browser }) => {
  sql("REPLACE INTO site_settings (name, value) VALUES ('feedback_button', 'staff')");
  sql("DELETE FROM feedback WHERE body LIKE 'E2E vieta%'");
  const admin = sql("SELECT id FROM accounts WHERE email = 'info@karateka.lt'");
  const ins = (page, site, ctx, body) => sql(`INSERT INTO feedback (account_id, page, site, context, body, status) VALUES (${admin}, '${page}', '${site}', '${JSON.stringify(ctx)}', '${body}', 'new')`);
  ins('/admin/varzybu-sistema.php?id=1', 'taika', { url: '/admin/varzybu-sistema.php?id=1', sk: 'live', sec: 'Tvarkaraštis' }, 'E2E vieta Budo tvarkaraštis');
  ins('/admin/varzybu-sistema.php?id=1', 'taika', { url: '/admin/varzybu-sistema.php?id=1', sk: 'admin/dalyviai', sec: 'Administracija › Dalyviai' }, 'E2E vieta Budo dalyviai');
  ins('/admin/nariai.php', 'karateka', { url: '/admin/nariai.php' }, 'E2E vieta nariu sarasas');
  ins('/ltkf/klubai.php', 'ltkf', { url: '/ltkf/klubai.php' }, 'E2E vieta LTKF klubai');

  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  // Kur esu: Budo valdymas su atidarytu Administracija › Dalyviai (tas pats skirstymas, kaip atsiliepimams)
  const here = await (await a.request.get('atsiliepimas.php?list=1&site=taika&here=' + encodeURIComponent('/admin/varzybu-sistema.php?id=1') + '&sk=admin%2Fdalyviai&sec=x')).json();
  check('place of the Budo app tab: Budo › Administracija', here.here.g === 'budo' && here.here.s === 'Administracija', JSON.stringify(here.here));

  await a.goto('admin/nariai.php');
  await openFeedback(a);
  check('writing view: no filters, only own list and one button', await a.locator('#fbPlace').isHidden() && await a.locator('#fbAllBtn').isVisible());
  await a.click('#fbAllBtn');   // atskiras vaizdas „Visi atsiliepimai“: vieta, būsena, paieška - išskleidžiami sąrašai
  await a.waitForSelector('#fbAllList [data-all-id]');
  check('here first: Karateka chosen with 📍', await a.inputValue('#fbPlace') === 'karateka|' && /📍/.test(await a.locator('#fbPlace option:checked').textContent()));
  check('section of this page marked 📍', /📍\s*Valdymas · Nariai/.test(await a.locator('#fbPlace option[value="karateka|Valdymas · Nariai"]').textContent()));
  check('this place item on top', /E2E vieta nariu sarasas/.test(await a.locator('#fbAllList [data-all-id]').first().textContent()));
  check('other groups not mixed in', !/E2E vieta Budo|E2E vieta LTKF/.test(await a.locator('#fbAllList').textContent()));
  await a.selectOption('#fbPlace', 'budo|');
  check('Budo: tabs as sections in the list', await a.locator('#fbPlace option[value="budo|Tvarkaraštis"]').count() === 1 && await a.locator('#fbPlace option[value="budo|Administracija"]').count() === 1);
  await a.selectOption('#fbPlace', 'budo|Administracija');
  const budoTxt = await a.locator('#fbAllList').textContent();
  check('Budo › Administracija shows only that', /E2E vieta Budo dalyviai/.test(budoTxt) && !/E2E vieta Budo tvarkaraštis/.test(budoTxt));
  await a.selectOption('#fbPlace', '*');
  await a.fill('#fbQ', 'ltkf klubai e2e');
  check('search finds across places', await a.locator('#fbAllList [data-all-id]').count() === 1 && /E2E vieta LTKF klubai/.test(await a.locator('#fbAllList').textContent()));
  await a.fill('#fbQ', 'zzzzqq');
  check('nothing found message', /Nieko nerasta/.test(await a.locator('#fbAllList').textContent()));
  await a.fill('#fbQ', '');
  // „Visi“ vaizdas ir filtras išlieka pereinant į kitą puslapį
  await a.selectOption('#fbPlace', 'budo|');
  await a.goto('admin/egzaminai.php');
  await a.waitForSelector('#fbAll:not([hidden])');
  check('all view and filter kept after page change', await a.inputValue('#fbPlace') === 'budo|');
  await a.click('#fbBack');
  check('back to writing', await a.locator('#fbText').isVisible() && await a.locator('#fbAll').isHidden());

  // Rašau ir pereinu į kitą puslapį: langas atsidaro pats, tekstas tas pats, atsiliepimas - vienas
  await a.fill('#fbText', 'E2E vieta tekstas rašant');
  await a.waitForFunction(() => /Išsaugota/.test(document.getElementById('fbState').textContent));
  await a.goto('admin/treniruote.php');
  await a.waitForSelector('#fbPanel:not([hidden])');
  check('box open after page change', await a.locator('#fbPanel').isVisible());
  check('text kept', await a.inputValue('#fbText') === 'E2E vieta tekstas rašant');
  await a.waitForFunction(() => !document.getElementById('fbEditing').hidden);
  check('same feedback continued', /Taisomas/.test(await a.locator('#fbEditing').textContent()));
  // Rašau ir iškart pereinu (nespėjus išsisaugoti) - išsaugoma, be dublikato
  await a.fill('#fbText', 'E2E vieta tekstas rašant ir dar');
  await a.goto('admin/nariai.php');
  await a.waitForSelector('#fbPanel:not([hidden])');
  check('text kept after a fast page change', await a.inputValue('#fbText') === 'E2E vieta tekstas rašant ir dar');
  await a.waitForTimeout(1500);
  check('saved once, with the latest text', sql("SELECT CONCAT(COUNT(*), '|', MAX(body)) FROM feedback WHERE body LIKE 'E2E vieta tekstas%'") === '1|E2E vieta tekstas rašant ir dar');
  check('it keeps the place where it was started', sql("SELECT page FROM feedback WHERE body LIKE 'E2E vieta tekstas%'") === '/admin/egzaminai.php');
  // Uždarius ir pereinant - nebeatsidaro
  await a.click('#fbPanel .fb-close');
  await a.goto('admin/');
  await a.waitForTimeout(400);
  check('closed box stays closed', await a.locator('#fbPanel').isHidden());

  // Valdymas → Atsiliepimai: tos pačios grupės, skiltys, paieška
  await a.goto('admin/atsiliepimai.php?b=visi&g=budo');
  const places = await a.locator('.fb-card .fb-place').allTextContents();
  check('admin page: Budo only', await a.locator('.fb-card', { hasText: 'E2E vieta' }).count() === 2 && places.every(t => t.startsWith('Budo')) && await a.locator('select[name=p] option[value="budo|Tvarkaraštis"]').count() === 1, places.join(' | '));
  await Promise.all([a.waitForNavigation(), a.selectOption('select[name=p]', 'budo|Administracija')]);
  check('admin page: place dropdown filters', await a.locator('.fb-card', { hasText: 'E2E vieta' }).count() === 1 && /p=budo/.test(a.url()));
  check('admin page: place badge', /Budo › Administracija › Dalyviai/.test(await a.locator('.fb-card', { hasText: 'E2E vieta Budo dalyviai' }).locator('.fb-place').textContent()));
  await a.goto('admin/atsiliepimai.php?b=visi&q=' + encodeURIComponent('e2e LTKF vieta'));
  check('admin page: search', await a.locator('.fb-card').count() === 1);

  // Telefone langas pats neatsidaro (užimtų visą ekraną) - ✎ ant mygtuko rodo nebaigtą tekstą
  const p = await login(browser, 'info@karateka.lt', 390, 800);
  await p.goto('admin/');
  await openFeedback(p);
  await p.fill('#fbText', 'E2E vieta telefonas');
  await p.waitForFunction(() => /Išsaugota/.test(document.getElementById('fbState').textContent));
  await p.goto('admin/treniruote.php');
  await p.waitForTimeout(600);
  check('phone: box not reopened, ✎ mark on the button', await p.locator('#fbPanel').isHidden() && (await p.locator('#fbFab .fb-fab-n').textContent()) === '✎');
  await openFeedback(p);
  check('phone: text still there', await p.inputValue('#fbText') === 'E2E vieta telefonas');

  sql("DELETE FROM feedback WHERE body LIKE 'E2E vieta%'");
  noJsErrors(a, p);
});
