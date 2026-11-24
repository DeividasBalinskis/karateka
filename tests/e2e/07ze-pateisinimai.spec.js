// v1.93: pateisinimų sistema (nuoroda → prašymas → adminui laiškas ir pranešimas → peržiūra pagal šabloną → patvirtinti ir išsiųsti)
// ir automatinis duomenų bazės atnaujinimas, kai administratorius atidaro puslapį.
// v1.94: „Kai patvirtinsime“, pranešimas tik pasirinktiems (numatyta - Denis), redagavimas, savo failas (Word arba PDF).
const { test } = require('@playwright/test');
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { sql, day, login, nav, check, noJsErrors } = require('./helpers');

test('database updates itself when an admin opens a page', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1280, 900);
  sql("DELETE FROM db_versija WHERE failas = '057_pateisinimu_failai.sql'");   // tarsi įkelta nauja versija su nauju db failu
  await a.goto('admin/');
  check('auto update message', /Duomenų bazė atnaujinta automatiškai: 057_pateisinimu_failai\.sql/.test(await a.locator('body').innerText()));
  check('db file recorded again', sql("SELECT COUNT(*) FROM db_versija WHERE failas = '057_pateisinimu_failai.sql'") === '1');
  check('no update button left on Today', !/Atnaujinkite duomenų bazę naujai versijai/.test(await a.locator('body').innerText()));
  noJsErrors(a);
});

test('excuse system: link, request, admin preview with a Word template, approve and send', async ({ browser }) => {
  sql('DELETE FROM justifications');
  sql("DELETE FROM site_settings WHERE name IN ('just_token', 'just_open', 'just_text', 'just_template', 'just_notify')");
  sql("DELETE FROM accounts WHERE email = 'antras.adminas@example.test'");
  sql("INSERT INTO accounts (first_name, last_name, email, password_hash, role, status) VALUES ('Antras', 'Adminas', 'antras.adminas@example.test', 'x', 'admin', 'active')");
  const other = sql("SELECT id FROM accounts WHERE email = 'antras.adminas@example.test'");
  fs.rmSync(path.join(__dirname, '..', '..', 'public_html', 'uploads', 'pateisinimai', 'sablonas.docx'), { force: true });   // ankstesnio paleidimo šablonas
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/pateisinimai.php');
  await nav(a, () => a.click('button:has-text("Sukurti nuorodą")'));
  const url = await a.getAttribute('[data-copy]', 'data-copy');
  check('short link', /\/p\/\?[a-z2-9]{6}$/.test(url), url);

  // Tėvai / mokinys pagal nuorodą - tik forma, be svetainės meniu
  const v = await login(browser, null, 390, 844);
  const rel = url.replace(/^https?:\/\/[^/]+\//, '');
  await v.goto(rel);
  check('we approve, not an administrator', /Kai patvirtinsime/.test(await v.locator('body').innerText()) && !/administratorius/i.test(await v.locator('body').innerText()));
  const links = await v.evaluate(() => [...document.querySelectorAll('a[href]')].map(x => x.getAttribute('href')));
  check('only the form, no site links', links.every(h => /kalba\.php|privatumas\.php\?be_meniu=1/.test(h)), JSON.stringify(links));
  await nav(v, () => v.click('button[type=submit]'));
  check('all fields required', /Užpildykite visus laukus/.test(await v.locator('.flash-err').innerText()));
  await v.fill('input[name=student]', 'Jonas Jonaitis');
  await v.fill('input[name=school]', 'Vilniaus Žirmūnų gimnazija');
  await v.fill('input[name=class_name]', '5b');
  await v.fill('input[name=director]', 'Ona Onaitė');
  await v.fill('input[name=date_from]', day(3));
  await v.fill('input[name=date_to]', day(5));
  await v.fill('input[name=email]', 'mokykla@example.test');
  await nav(v, () => v.click('button[type=submit]'));
  check('request received', /Prašymas gautas/.test(await v.locator('.exam-su-ok').innerText()));
  check('stored as new', sql("SELECT CONCAT(status, '|', class_name, '|', email) FROM justifications WHERE student = 'Jonas Jonaitis'") === 'new|5b|mokykla@example.test');
  const id = sql("SELECT id FROM justifications WHERE student = 'Jonas Jonaitis'");
  check('only Denis notified', sql(`SELECT GROUP_CONCAT(a.first_name) FROM notifications n JOIN accounts a ON a.id = n.account_id WHERE n.type = 'justification' AND n.ref_id = ${id}`) === 'Denis');

  // Adminas: peržiūra pagal tekstą (be Word šablono)
  await a.goto('admin/pateisinimai.php');
  const card = a.locator(`#p${id}`);
  const prev = await card.locator('.just-preview').innerText();
  check('preview filled', /Vilniaus Žirmūnų gimnazija/.test(prev) && /Ona Onaitė/.test(prev) && /5b klasės/.test(prev) && /Jonas Jonaitis/.test(prev) && / m\. [a-ząčęėįšųūž]+ \d+ d\./.test(prev), prev);
  check('menu item with a number', /Pateisinimai\s*1/.test(await a.locator('.adm-side').innerText()));

  // Word šablonas su suskaidyta žyme ({vard + as}) - pildomas teisingai
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'pat-'));
  const docx = path.join(dir, 'sablonas.docx');
  execFileSync('php', ['-r', `$z = new ZipArchive(); $z->open('${docx}', ZipArchive::CREATE);
    $z->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $z->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
    $z->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Gerb. {direktorius}, mokinys {vard</w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>as} ({klase}) - {laikotarpis}.</w:t></w:r></w:p></w:body></w:document>');
    $z->close();`]);
  await a.setInputFiles('#sablonas input[type=file]', docx);
  await nav(a, () => a.click('#sablonas button:has-text("Įkelti Word šabloną")'));
  const prev2 = await a.locator(`#p${id} .just-preview`).innerText();
  check('Word template filled (also a split tag), no double dot', /Gerb\. Ona Onaitė, mokinys Jonas Jonaitis \(5b\)/.test(prev2) && /\d d\.$/.test(prev2.trim()) && !/d\.\./.test(prev2), prev2);
  const dl = await a.request.get(`admin/pateisinimai.php?docx=${id}`);
  const out = path.join(dir, 'out.docx');
  fs.writeFileSync(out, await dl.body());
  const xml = execFileSync('php', ['-r', `$z = new ZipArchive(); $z->open('${out}'); echo $z->getFromName('word/document.xml');`]).toString();
  check('downloaded .docx is filled and valid XML', /Jonas Jonaitis/.test(xml) && !/\{vard/.test(xml) && execFileSync('php', ['-r', `$z = new ZipArchive(); $z->open('${out}'); $d = new DOMDocument(); echo @$d->loadXML($z->getFromName('word/document.xml')) ? 'ok' : 'bad';`]).toString() === 'ok');

  // ✎ Redaguoti: duomenys ir tekstas (be Word šablono - tekstas taisomas čia)
  fs.rmSync(path.join(__dirname, '..', '..', 'public_html', 'uploads', 'pateisinimai', 'sablonas.docx'), { force: true });
  await a.goto('admin/pateisinimai.php');
  check('edit panel hidden at first', await a.locator(`#e${id}`).isHidden());
  await a.click(`#p${id} button:has-text("Redaguoti")`);
  await a.fill(`#e${id} input[name=class_name]`, '6a');
  await nav(a, () => a.click(`#e${id} button:has-text("Išsaugoti")`));
  check('data edited, text follows', sql(`SELECT CONCAT(class_name, '|', IFNULL(custom_text, '-')) FROM justifications WHERE id = ${id}`) === '6a|-' && /6a klasės/.test(await a.locator(`#p${id} .just-preview`).innerText()));
  await a.click(`#p${id} button:has-text("Redaguoti")`);
  const ta = a.locator(`#e${id} textarea[name=text]`);
  await ta.fill((await ta.inputValue()).replace('Pagarbiai', 'Ačiū.\n\nPagarbiai'));
  await nav(a, () => a.click(`#e${id} button:has-text("Išsaugoti")`));
  check('own text saved', /Ačiū\./.test(await a.locator(`#p${id} .just-preview`).innerText()) && /tekstas pataisytas/.test(await a.locator(`#p${id}`).innerText()));
  // Savo failas: PDF siunčiamas vietoj parengto
  const pdf = path.join(dir, 'galutinis.pdf');
  fs.writeFileSync(pdf, '%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n');
  await a.click(`#p${id} button:has-text("Redaguoti")`);
  await a.setInputFiles(`#e${id} input[type=file]`, pdf);
  await nav(a, () => a.click(`#e${id} button:has-text("Įkelti failą")`));
  check('PDF uploaded', /Bus išsiųstas įkeltas failas:\s*galutinis\.pdf/.test(await a.locator(`#p${id}`).innerText()));
  const txt = path.join(dir, 'ne.pdf');
  fs.writeFileSync(txt, 'just text');
  await a.click(`#p${id} button:has-text("Redaguoti")`);
  await a.setInputFiles(`#e${id} input[type=file]`, txt);
  await nav(a, () => a.click(`#e${id} button:has-text("Įkelti failą")`));
  check('not a PDF refused', /Tai ne PDF failas/.test(await a.locator('.flash-err').innerText()));
  const fdl = await a.request.get(`admin/pateisinimai.php?failas=${id}`);
  check('uploaded file downloadable by admin', fdl.ok() && (await fdl.body()).toString().startsWith('%PDF'));
  check('upload folder closed to the web', /Require all denied/.test(fs.readFileSync(path.join(__dirname, '..', '..', 'public_html', 'uploads', 'pateisinimai', '.htaccess'), 'utf8')));

  // Patvirtinti ir išsiųsti
  await nav(a, () => a.locator(`#p${id} button:has-text("Patvirtinti ir išsiųsti")`).click());
  check('sent', sql(`SELECT status FROM justifications WHERE id = ${id}`) === 'sent' && /Pateisinimas išsiųstas/.test(await a.locator('.flash').first().innerText()));
  check('to-do closed', sql(`SELECT COUNT(*) FROM notifications WHERE type = 'justification' AND ref_id = ${id} AND done_at IS NULL`) === '0');
  await a.goto('admin/pateisinimai.php?b=sent');
  check('sent again possible', await a.locator(`#p${id} button:has-text("Siųsti dar kartą")`).count() === 1);

  // Kam pranešti: pažymėtas ir antras adminas - jis irgi gauna
  await a.click('#pranesimai summary');
  await a.check(`#pranesimai input[value="${other}"]`);
  await nav(a, () => a.click('#pranesimai button:has-text("Išsaugoti")'));

  // Kitas prašymas - atmestas, laiškas nesiunčiamas
  sql(`INSERT INTO justifications (student, school, director, class_name, date_from, date_to, email) VALUES ('Petras Petraitis', 'Mokykla', 'Direktorius', '7a', '${day(1)}', '${day(1)}', 'x@example.test')`);
  const id2 = sql("SELECT id FROM justifications WHERE student = 'Petras Petraitis'");
  await a.goto('admin/pateisinimai.php');
  await nav(a, () => a.locator(`#p${id2} button:has-text("Atmesti")`).click());
  check('rejected', sql(`SELECT status FROM justifications WHERE id = ${id2}`) === 'rejected');
  await v.goto(rel);
  await v.fill('input[name=student]', 'Ona Onaitytė');
  await v.fill('input[name=school]', 'Mokykla');
  await v.fill('input[name=class_name]', '2c');
  await v.fill('input[name=director]', 'Direktorius');
  await v.fill('input[name=date_from]', day(2));
  await v.fill('input[name=date_to]', day(2));
  await v.fill('input[name=email]', 'tevai@example.test');
  await nav(v, () => v.click('button[type=submit]'));
  const id3 = sql("SELECT id FROM justifications WHERE student = 'Ona Onaitytė'");
  check('both chosen admins notified', sql(`SELECT COUNT(*) FROM notifications WHERE type = 'justification' AND ref_id = ${id3}`) === '2');

  // Nebepriimant prašymų
  await nav(a, () => a.click('button:has-text("Nebepriimti prašymų")'));
  await v.goto(rel);
  check('closed form', /Prašymai nepriimami/.test(await v.locator('body').innerText()));
  fs.rmSync(path.join(__dirname, '..', '..', 'public_html', 'uploads', 'pateisinimai', 'sablonas.docx'), { force: true });
  sql("DELETE FROM site_settings WHERE name IN ('just_token', 'just_open', 'just_text', 'just_template', 'just_notify')");
  sql('DELETE FROM justifications');
  sql("DELETE FROM notifications WHERE type = 'justification'");
  sql(`DELETE FROM notifications WHERE account_id = ${other}`);
  sql(`DELETE FROM accounts WHERE id = ${other}`);
  fs.rmSync(path.join(__dirname, '..', '..', 'public_html', 'uploads', 'pateisinimai', `p${id}.pdf`), { force: true });
  noJsErrors(a, v);
});
