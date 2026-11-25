// Olimpinė lentelė (engine/bracket.js): laisvi ratai, kovų numeriai, kova dėl 3 vietos, paguoda (WKF), vietos
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { roundName, bracket, allMatches } from '../../public_html/assets/varzybos/engine/bracket.js';
import { matchCount } from '../../public_html/assets/varzybos/engine/category.js';
import { grupe } from './helpers.mjs';

const byId = (b) => Object.fromEntries(allMatches(b).map((m) => [m.id, m]));

test('round names', () => {
  assert.equal(roundName(2, 3), 'Finalas');
  assert.equal(roundName(1, 3), 'Pusfinalis');
  assert.equal(roundName(0, 3), 'Ketvirtfinalis');
  assert.equal(roundName(0, 4), '1/8');
  assert.equal(roundName(0, 5), '1/16');
});

test('5 participants: byes go through, only real bouts get numbers', () => {
  const c = grupe(['a', 'b', 'c', 'd', 'e'], { system: 'plain', slots: ['a', null, 'b', 'c', 'd', null, 'e', null] });
  const b = bracket(c);
  assert.equal(b.R, 3);
  assert.deepEqual(b.rounds.map((r) => r.length), [4, 2, 1]);
  const [m0, m1, m2, m3] = b.rounds[0];
  assert.equal(m0.bye, true); assert.equal(m0.winner, 'a'); assert.equal(m0.no, undefined);
  assert.equal(m1.bye, undefined); assert.equal(m1.no, 1);
  assert.equal(m2.winner, 'd'); assert.equal(m3.winner, 'e');
  // a laukia b / c nugalėtojo; d - e jau gali kovoti
  assert.equal(b.rounds[1][0].a, 'a'); assert.equal(b.rounds[1][0].wait, true);
  assert.deepEqual([b.rounds[1][1].a, b.rounds[1][1].b, b.rounds[1][1].wait], ['d', 'e', false]);
  assert.deepEqual(allMatches(b).map((m) => [m.id, m.no]), [['r0m1', 1], ['r1m0', 2], ['r1m1', 3], ['r2m0', 4]]);
  assert.equal(matchCount(c), 4);
  assert.deepEqual(b.places, []);
});

test('plain system: two bronzes without a bout', () => {
  const c = grupe(['a', 'b', 'c', 'd', 'e'], { system: 'plain', slots: ['a', null, 'b', 'c', 'd', null, 'e', null],
    results: { r0m1: 'b', r1m0: 'a', r1m1: 'd' } });
  assert.deepEqual(bracket(c).places, [[3, ['b']], [3, ['e']]]);   // finalas dar nevyko
  c.draw.results.r2m0 = 'a';
  assert.deepEqual(bracket(c).places, [[1, ['a']], [2, ['d']], [3, ['b']], [3, ['e']]]);
  assert.deepEqual(bracket(c).rep, []);
});

test('a result for someone who is not in the bout is ignored', () => {
  const c = grupe(['a', 'b', 'c', 'd'], { system: 'plain', slots: ['a', 'b', 'c', 'd'], results: { r0m0: 'c' } });
  const m = bracket(c).rounds[0][0];
  assert.equal(m.winner, undefined);
});

test('bronze system: semi-final losers fight for 3rd place', () => {
  const c = grupe(['a', 'b', 'c', 'd'], { system: 'bronze', slots: ['a', 'b', 'c', 'd'] });
  let b = bracket(c);
  assert.equal(b.rep.length, 1);
  assert.equal(b.rep[0].title, 'Kova dėl 3 vietos');
  assert.equal(b.rep[0].ready, false);
  c.draw.results = { r0m0: 'a', r0m1: 'c' };
  b = bracket(c);
  assert.equal(b.rep[0].ready, true);
  assert.deepEqual([byId(b).bronze.a, byId(b).bronze.b, byId(b).bronze.no], ['b', 'd', 4]);
  c.draw.results.r1m0 = 'a'; c.draw.results.bronze = 'd';
  assert.deepEqual(bracket(c).places, [[1, ['a']], [2, ['c']], [3, ['d']], [4, ['b']]]);
  assert.equal(matchCount(c), 4);
});

test('WKF repechage: everyone the finalist beat fights for bronze, one chain per side', () => {
  const ids = ['p1', 'p2', 'p3', 'p4', 'p5', 'p6', 'p7', 'p8'];
  const c = grupe(ids, { system: 'wkf', slots: ids.slice() });
  assert.equal(matchCount(c), 11);   // kol paguoda nežinoma - spėjama: 7 + 4
  c.draw.results = { r0m0: 'p1', r0m1: 'p3', r0m2: 'p5', r0m3: 'p7', r1m0: 'p1', r1m1: 'p5' };
  let b = bracket(c);
  assert.deepEqual(b.rep.map((g) => g.title), ['Paguoda A pusė', 'Paguoda B pusė']);
  const m = byId(b);
  // A pusė: p1 nugalėjo p2 (1 ratas) ir p3 (pusfinalis); B pusė: p5 nugalėjo p6 ir p7
  assert.deepEqual([m.rep0m1.a, m.rep0m1.b, m.rep0m1.no], ['p2', 'p3', 8]);
  assert.deepEqual([m.rep1m1.a, m.rep1m1.b, m.rep1m1.no], ['p6', 'p7', 9]);
  assert.equal(matchCount(c), 9);
  c.draw.results.rep0m1 = 'p3'; c.draw.results.rep1m1 = 'p6'; c.draw.results.r2m0 = 'p1';
  b = bracket(c);
  assert.deepEqual(b.places, [[1, ['p1']], [2, ['p5']], [3, ['p3']], [3, ['p6']]]);
});

test('WKF with 4 participants: the one the finalist beat gets bronze without a bout', () => {
  const c = grupe(['a', 'b', 'c', 'd'], { system: 'wkf', slots: ['a', 'b', 'c', 'd'], results: { r0m0: 'a', r0m1: 'd', r1m0: 'd' } });
  const b = bracket(c);
  assert.deepEqual(b.rep.map((g) => g.matches.length), [0, 0]);
  assert.deepEqual(b.places, [[1, ['d']], [2, ['a']], [3, ['b']], [3, ['c']]]);
});
