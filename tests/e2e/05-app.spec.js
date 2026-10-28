// Programėlė telefone: manifestas su ikonomis, service worker, be ryšio - „Nėra interneto ryšio“ (ne asmeniniai duomenys).
const { test, expect } = require('@playwright/test');
const { login, noJsErrors } = require('./helpers');

test('manifest, service worker and offline page', async ({ browser }) => {
  const p = await login(browser, 'tevai@karateka.test', 390, 844);
  await p.goto('paskyra.php');
  const href = await p.locator('link[rel=manifest]').getAttribute('href');
  const manifest = await (await p.request.get(href)).json();
  expect(manifest.display).toBe('standalone');
  expect(manifest.icons.map(i => i.sizes)).toEqual(expect.arrayContaining(['192x192', '512x512']));
  for (const icon of manifest.icons) {
    expect((await p.request.get(icon.src)).status(), icon.src).toBe(200);
  }
  await p.evaluate(() => navigator.serviceWorker.ready);
  await p.reload();   // nuo šiol puslapį valdo service worker (clients.claim gali įvykti šiek tiek vėliau)
  await expect.poll(() => p.evaluate(() => !!navigator.serviceWorker.controller), { timeout: 10_000 }).toBe(true);

  await p.context().setOffline(true);
  await p.goto('paskyra.php').catch(() => {});
  await expect(p.locator('h1')).toHaveText('Nėra interneto ryšio');
  // Asmeninis puslapis neišsaugotas - be ryšio jo nėra
  expect(await p.content()).not.toContain('Petrauskienė');
  // Atsiradus ryšiui puslapis atsidaro pats
  await Promise.all([p.waitForNavigation(), p.context().setOffline(false)]);
  await expect(p.getByText('Nėra interneto ryšio')).toHaveCount(0);
  await p.goto('paskyra.php?skiltis=nustatymai');
  await expect(p.locator('#programele')).toBeVisible();
  noJsErrors(p);
});
