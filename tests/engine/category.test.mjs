// Visa grupė (engine/category.js): pasenę burtai, netinkami rezultatai, vietos, ar baigta, užrakinimas pačiai baigus (#182)
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { matchCount, doneCount, prune, drawStale, catPlaces, catFinished, autoLock } from '../../public_html/assets/varzybos/engine/category.js';
import { grupe } from './helpers.mjs';

test('draw is stale when the participants changed', () => {
  assert.equal(drawStale(grupe(['a', 'b'])), true);   // burtų dar nėra
  const c = grupe(['a', 'b'], { system: 'plain', slots: ['b', 'a'] });
  assert.equal(drawStale(c), false);
  c.competitors.push({ id: 'c', name: 'C', club: 'X' });
  assert.equal(drawStale(c), true);
  assert.deepEqual(catPlaces(c), []);
  const r = grupe(['a', 'b', 'c'], { system: 'rr', order: ['a', 'b'] });
  assert.equal(drawStale(r), true);
});

test('bout count before the draw: elimination n-1 (+bronze, +WKF repechage), round robin, scores', () => {
  const n = (k) => Array.from({ length: k }, (_, i) => 'p' + i);
  assert.equal(matchCount(grupe(n(1))), 0);
  assert.equal(matchCount(grupe(n(6))), 5);   // kata individualiai - numatyta „plain“
  assert.equal(matchCount(grupe(n(6), { team: true })), 6);   // komandos - numatyta kova dėl 3 vietos
  assert.equal(matchCount(grupe(n(6), { discipline: 'enbu' })), 6);   // enbu - balai
  assert.equal(matchCount(grupe(n(5), { system: 'rr' })), 10);
  const w = grupe(n(6), { system: 'wkf', slots: n(6).concat([null, null]) });
  w.draw.slots = [];   // pasenę burtai - spėjama: 5 + 2
  assert.equal(matchCount(w), 7);
});

test('changing an earlier result removes later results that no longer fit', () => {
  const c = grupe(['a', 'b', 'c', 'd'], { system: 'plain', slots: ['a', 'b', 'c', 'd'], results: { r0m0: 'a', r0m1: 'c', r1m0: 'a' } });
  c.draw.results.r0m0 = 'b';
  prune(c);
  assert.deepEqual(c.draw.results, { r0m0: 'b', r0m1: 'c' });
  const r = grupe(['a', 'b'], { system: 'rr', results: { 'a~b': 'a', 'a~x': 'x' } });
  prune(r);
  assert.deepEqual(r.draw.results, { 'a~b': 'a' });
});

test('finished: every bout done and a 1st place known', () => {
  const c = grupe(['a', 'b'], { system: 'plain', slots: ['a', 'b'] });
  assert.equal(matchCount(c), 1);
  assert.equal(catFinished(c), false);
  c.draw.results.r0m0 = 'b';
  assert.equal(doneCount(c), 1);
  assert.deepEqual(catPlaces(c), [[1, ['b']], [2, ['a']]]);
  assert.equal(catFinished(c), true);
  assert.equal(catFinished(grupe(['a', 'b'])), false);   // be burtų
});

test('auto-lock (#182): locks when the last bout ends, not again after it was unlocked', () => {
  const c = grupe(['a', 'b', 'c', 'd'], { system: 'bronze', slots: ['a', 'b', 'c', 'd'], results: { r0m0: 'a', r0m1: 'c', r1m0: 'a' } });
  const wasDone = catFinished(c);
  autoLock(c, wasDone);
  assert.equal(c.draw.locked, false);   // kova dėl 3 vietos dar nevyko
  c.draw.results.bronze = 'b';
  autoLock(c, wasDone);
  assert.equal(c.draw.locked, true);
  c.draw.locked = false;   // pataisyti - „🔒 Užrakinta“ atrakina
  autoLock(c, catFinished(c));
  assert.equal(c.draw.locked, false);
  assert.doesNotThrow(() => { autoLock(null, false); autoLock(grupe(['a']), false); });
});
