// Ratų sistema (engine/rr.js): visi su visais („apskritimo“ metodu), lentelė - pergalės, tarpusavio kova, taškų skirtumas
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { rrMatches, parseScore, rrStandings } from '../../public_html/assets/varzybos/engine/rr.js';
import { catPlaces, catFinished, matchCount, doneCount } from '../../public_html/assets/varzybos/engine/category.js';
import { grupe } from './helpers.mjs';

test('4 participants: 6 bouts in 3 rounds, every pair once', () => {
  const c = grupe(['a', 'b', 'c', 'd'], { system: 'rr' });
  const ms = rrMatches(c);
  assert.deepEqual(ms.map((m) => [m.id, m.round, m.no]), [
    ['a~d', 1, 1], ['b~c', 1, 2], ['a~c', 2, 3], ['b~d', 2, 4], ['a~b', 3, 5], ['c~d', 3, 6]]);
  assert.equal(matchCount(c), 6);
});

test('odd number: everyone rests once, n*(n-1)/2 bouts', () => {
  const c = grupe(['a', 'b', 'c', 'd', 'e'], { system: 'rr' });
  const ms = rrMatches(c);
  assert.equal(ms.length, 10);
  assert.equal(new Set(ms.map((m) => m.id)).size, 10);
  for (let r = 1; r <= 5; r++) assert.equal(ms.filter((m) => m.round === r).length, 2);
});

test('a participant removed from the group drops out of the bouts', () => {
  const c = grupe(['a', 'b', 'c'], { system: 'rr', order: ['a', 'b', 'c', 'gone'] });
  assert.deepEqual(rrMatches(c).map((m) => m.id).sort(), ['a~b', 'a~c', 'b~c']);
});

test('score text: "3:1", "2-0", decimals with a comma', () => {
  assert.deepEqual(parseScore('3:1'), [3, 1]);
  assert.deepEqual(parseScore('2-0'), [2, 0]);
  assert.deepEqual(parseScore('1,5 : 0,5'), [1.5, 0.5]);
  assert.equal(parseScore(''), null);
  assert.equal(parseScore('laimėjo'), null);
});

test('standings: wins first, then the bout between the two', () => {
  // a ir b - po 2 pergales, a nugalėjo b; c ir d - po 1, c nugalėjo d
  const res = { 'a~b': 'a', 'a~c': 'a', 'a~d': 'd', 'b~c': 'b', 'b~d': 'b', 'c~d': 'c' };
  const c = grupe(['a', 'b', 'c', 'd'], { system: 'rr', results: res });
  const st = rrStandings(c);
  assert.deepEqual(st.map((x) => [x.id, x.w, x.l, x.played]), [['a', 2, 1, 3], ['b', 2, 1, 3], ['c', 1, 2, 3], ['d', 1, 2, 3]]);
  assert.equal(doneCount(c), 6);
  assert.equal(catFinished(c), true);
});

test('places by wins: 3, 2, 1, 0', () => {
  const res = { 'a~b': 'a', 'a~c': 'a', 'a~d': 'a', 'b~c': 'b', 'b~d': 'b', 'c~d': 'c' };
  const c = grupe(['a', 'b', 'c', 'd'], { system: 'rr', results: res });
  assert.deepEqual(catPlaces(c), [[1, ['a']], [2, ['b']], [3, ['c']], [4, ['d']]]);
});

test('standings: score difference and points from the bout score', () => {
  const c = grupe(['a', 'b'], { system: 'rr', results: { 'a~b': 'a' }, scores: { 'a~b': '5:2' } });
  const st = rrStandings(c);
  assert.deepEqual(st.map((x) => [x.id, x.w, x.diff, x.pts]), [['a', 1, 3, 5], ['b', 0, -3, 2]]);
});

test('places only when every bout is done', () => {
  const c = grupe(['a', 'b', 'c'], { system: 'rr', results: { 'a~b': 'a', 'a~c': 'a' } });
  assert.deepEqual(catPlaces(c), []);
  assert.equal(catFinished(c), false);
  c.draw.results['b~c'] = 'c';
  assert.deepEqual(catPlaces(c), [[1, ['a']], [2, ['c']], [3, ['b']]]);
});
