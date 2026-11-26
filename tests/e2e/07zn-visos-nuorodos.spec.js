// v2.12 (Deividas: „per visą svetainę - visi reikalingi mygtukai, viskas veikia, nėra bugų“): kiekvienai rolei apeinamos visos nuorodos
// iš meniu ir puslapių (2 lygiai nuo pradžios puslapių). Kiekvienas puslapis: atsakymas < 400, be PHP klaidų, be JS klaidų,
// tuščių nuorodų (href="") ir nuorodų į neegzistuojančius puslapius. 07-pages tikrina žinomą sąrašą - šis randa ir tai, ko sąraše nėra.
// Nuorodos, kurios kažką keičia, siunčia failą ar yra slaptos (?k=) - praleidžiamos.
const { test, expect } = require('@playwright/test');
const { login } = require('./helpers');

const PHP_ERROR = /Fatal error|Warning:|Notice:|Deprecated:|Uncaught|Įvyko klaida|Duomenų bazė neatnaujinta/;
const IGNORED_CONSOLE = /favicon|ERR_|youtube|google|vdo\.ninja|Failed to load resource|Permissions policy violation/i;
const SKIP = /atsijungti|logout|trinti|istrinti|delete|download|atsisiusti|eksport|export|\.zip|\.xlsx|\.csv|\.pdf|\.docx|\.ics|\.png|\.jpe?g|\.webp|\.svg|\.mp4|\.json|uploads\/|[?&](k|c|token|key|raktas|lang|kalba|tema|theme|action|veiksmas|zymeti|as)=|-api\.php|pulsas\.php|arena\.php|kodas\.php\?|varzybos-sso\.php|kalba\.php|testas\.php/i;   // kalba.php - keičia paskyros kalbą; testas.php - atidarius pradedamas bandymas (07-pages jį tikrina)   // varzybos-sso - perėjimas į išorinę Varžybų programą (testuose jos nėra)

const roles = [
  ['info@karateka.lt', 'admin', ['index.php', 'admin/', 'admin/nustatymai.php', 'admin/varzybos.php', 'paskyra.php', 'ltkf/'], 700],
  ['treneris@karateka.test', 'coach', ['index.php', 'admin/', 'admin/nustatymai.php', 'paskyra.php'], 350],
  ['tevai@karateka.test', 'parent', ['index.php', 'paskyra.php'], 250],
  ['narys@karateka.test', 'member', ['index.php', 'paskyra.php'], 250],
  [null, 'guest', ['index.php', 'varzybos.php', 'renginiai.php', 'ltkf/'], 200],
];

// Tas pats puslapis su kitu numeriu (?id=2, ?id=3 ...) - aplankomas vieną kartą
function shape(u) {
  const x = new URL(u);
  const q = [...x.searchParams.entries()].map(([k, v]) => k + '=' + (/^\d+$/.test(v) ? 'N' : v)).sort().join('&');
  return x.pathname + (q ? '?' + q : '');
}

for (const [email, role, start, max] of roles) {
  test(`all links work for ${role}`, async ({ browser }) => {
    test.setTimeout(15 * 60 * 1000);
    const page = await login(browser, email, 1280, 900);
    const base = new URL(process.env.BASE_URL || 'http://127.0.0.1:8088/').href;
    const consoleErrors = [];
    page.on('console', m => { if (m.type() === 'error' && !IGNORED_CONSOLE.test(m.text())) consoleErrors.push(m.text()); });
    page.on('dialog', d => d.dismiss().catch(() => {}));   // patvirtinimo langai - atšaukiami (niekas nekeičiama)
    const seen = new Set(), queue = start.map(p => ({ url: new URL(p, base).href, depth: 0, from: '(pradžia)' })), problems = [];
    let visited = 0, downloads = 0;
    while (queue.length && visited < max) {
      const { url, depth, from } = queue.shift();
      const key = shape(url);
      if (seen.has(key)) continue;
      seen.add(key);
      visited++;
      page.jsErrors.length = 0;
      consoleErrors.length = 0;
      let res = null;
      try { res = await page.goto(url, { waitUntil: 'load', timeout: 20000 }); } catch (e) {
        if (/Download is starting/.test(e.message)) { downloads++; continue; }   // Excel, kalendorius, pavyzdžiai - failas atsisiunčia, tai gerai
        problems.push(`${key} (iš ${from}): neatsidaro - ${e.message.split('\n')[0]}`);
        continue;
      }
      const status = res ? res.status() : 0;
      if (new URL(page.url()).origin !== new URL(base).origin) continue;   // nukreipė į kitą svetainę (pvz. YouTube)
      const text = await page.locator('body').innerText().catch(() => '');
      const issues = [];
      if (status >= 400) issues.push('HTTP ' + status);
      if (PHP_ERROR.test(text)) issues.push('PHP klaida: ' + (text.match(new RegExp('.{0,60}(' + PHP_ERROR.source + ').{0,80}')) || [''])[0]);
      issues.push(...page.jsErrors.map(e => 'JS: ' + e), ...consoleErrors.map(e => 'console: ' + e));
      const links = await page.$$eval('a[href]', as => as.map(a => ({ href: a.href, raw: a.getAttribute('href'), txt: (a.innerText || a.getAttribute('aria-label') || '').trim().slice(0, 40), hidden: !a.offsetParent })));
      const empty = links.filter(l => !l.hidden && (l.raw === '' || l.raw === 'undefined' || /undefined|null$/.test(l.raw)));
      if (empty.length) issues.push('tuščios nuorodos: ' + empty.map(l => `„${l.txt}“ → ${l.raw}`).join(', '));
      if (issues.length) problems.push(`${key} (iš ${from}): ${issues.join('; ')}`);
      if (depth >= 2) continue;
      for (const l of links) {
        let u;
        try { u = new URL(l.href); } catch (e) { continue; }
        u.hash = '';
        if (!u.href.startsWith(base) || SKIP.test(u.href) || seen.has(shape(u.href))) continue;
        queue.push({ url: u.href, depth: depth + 1, from: key });
      }
    }
    console.log(`${role}: aplankyta ${visited} puslapių (failų atsisiuntimų ${downloads}), liko eilėje ${queue.length}, problemų ${problems.length}`);
    expect(problems, problems.join('\n')).toEqual([]);
  });
}
