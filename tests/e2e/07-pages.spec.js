// Visi puslapiai kiekvienai rolei, kompiuterio ir telefono pločiu: be PHP ir JS klaidų, be horizontalaus slinkimo.
const { test, expect } = require('@playwright/test');
const { login } = require('./helpers');

const common = ['index.php', 'renginiai.php', 'renginiai.php?tipas=egzaminai', 'renginys.php?id=17', 'renginys.php?id=18', 'renginys.php?id=19', 'renginys.php?id=1',
  'naujienos.php', 'naujienos.php?id=1', 'naujienos.php?id=2', 'pamokos.php', 'pamokos.php?id=1', 'pamokos.php?id=3', 'pamokos.php?q=heian', 'privatumas.php',
  'varzybos.php', 'prisijungti.php', 'registracija.php', 'uzklausa.php', 'mano-renginiai.php', 'kodas.php', 'offline.html'];
const member = ['paskyra.php', 'paskyra.php?m=2', 'paskyra.php?m=3', 'paskyra.php?skiltis=nustatymai', 'egzaminas.php', 'egzaminas.php?m=2', 'egzaminas.php?m=3',
  'testas.php?q=1', 'testas.php?q=1&rezimas=treniruote', 'klausimai.php'];
const admin = ['admin/', 'admin/treniruote.php', 'admin/treniruote.php?g=12', 'admin/zinutes.php', 'admin/zinutes.php?m=2', 'admin/zinutes.php?m=4',
  'admin/nariai.php', 'admin/nariai.php?id=2', 'admin/nariai.php?id=4', 'admin/nariai.php?new=1', 'admin/grupes.php', 'admin/grupes.php?id=4',
  'admin/tvarkarastis.php', 'admin/taskai.php', 'admin/varzybos.php', 'admin/testai.php', 'admin/programa.php', 'admin/programa.php?dirzas=2',
  'admin/pranesimai.php', 'admin/sms.php', 'admin/paskyros.php', 'admin/patvirtinimai.php', 'admin/renginiai.php?edit=new', 'admin/renginiai.php?edit=17',
  'admin/renginio-dalyviai.php?id=17', 'admin/renginio-dalyviai.php?id=18', 'admin/renginio-rezultatai.php?id=6', 'admin/naujienos.php?edit=new',
  'admin/naujienos.php?edit=1', 'admin/naujienos.php?emoji=1', 'admin/pamokos.php', 'admin/kopijos.php', 'admin/atsaukimai.php', 'admin/atsaukimai.php?g=1&d=2030-01-07', 'pamokos.php?tvarkyti=1', 'pamokos.php?nauja=1',
  'pamokos.php?id=1&keisti=1', 'index.php?redaguoti=1', 'admin/puslapis.php', 'pokalbis.php?list=1', 'pokalbis.php?list=1&q=tom', 'pokalbis.php?m=4'];
const coach = ['admin/', 'admin/atsaukimai.php', 'admin/treniruote.php?g=12', 'admin/nariai.php', 'admin/nariai.php?id=2', 'admin/taskai.php', 'admin/programa.php', 'pokalbis.php?list=1'];

const roles = [
  ['info@karateka.lt', 'admin', [...common, ...member, ...admin]],
  ['treneris@karateka.test', 'coach', [...common, ...coach]],
  ['tevai@karateka.test', 'parent', [...common, ...member]],
  ['narys@karateka.test', 'member', [...common, ...member]],
  ['jaunuolis@karateka.test', 'teen', [...common, ...member]],
  [null, 'guest', common],
];
const PHP_ERROR = /Fatal error|Warning:|Notice:|Deprecated:|Uncaught|Įvyko klaida|Duomenų bazė neatnaujinta/;
const IGNORED_CONSOLE = /favicon|ERR_|youtube|google|Failed to load resource/i;

for (const width of [1280, 390]) {
  for (const [email, role, pages] of roles) {
    test(`${role} pages at ${width}px`, async ({ browser }) => {
      const page = await login(browser, email, width);
      const consoleErrors = [];
      page.on('console', m => { if (m.type() === 'error' && !IGNORED_CONSOLE.test(m.text())) consoleErrors.push(m.text()); });
      const problems = [];
      for (const path of pages) {
        page.jsErrors.length = 0;
        consoleErrors.length = 0;
        const res = await page.goto(path, { waitUntil: 'load' });
        const status = res ? res.status() : 0;
        const body = await page.content();
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        const issues = [];
        if (status >= 500) issues.push('HTTP ' + status);
        if (PHP_ERROR.test(body)) issues.push('PHP klaida');
        if (overflow > 2) issues.push(`horizontalus slinkimas ${overflow}px`);
        issues.push(...page.jsErrors.map(e => 'JS: ' + e), ...consoleErrors.map(e => 'console: ' + e));
        if (issues.length) problems.push(`${path}: ${issues.join('; ')}`);
      }
      expect(problems).toEqual([]);
    });
  }
}
