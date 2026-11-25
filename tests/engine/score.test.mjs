// Balų sistema (engine/score.js) - kata, enbu: kraštiniai balai atmetami (5 ir daugiau teisėjų), 1 ar 2 ratai, finalininkai
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { scoreTotal, fmtScore, scoreRank, scoreFinalists, scoreDone } from '../../public_html/assets/varzybos/engine/score.js';
import { catPlaces, catFinished, matchCount } from '../../public_html/assets/varzybos/engine/category.js';
import { cfg } from '../../public_html/assets/varzybos/rules.js';
import { grupe } from './helpers.mjs';

const five = (x) => [x, x, x, x, x];

test('group settings default to 5 judges, 1 round, 8 finalists, extremes dropped', () => {
  const f = cfg(grupe(['a']));
  assert.deepEqual([f.judges, f.rounds, f.finalists, f.drop, f.avg], [5, 1, 8, true, false]);
});

test('5 judges: the highest and the lowest score are dropped, the rest summed', () => {
  const c = grupe(['a'], { system: 'score' });
  assert.equal(scoreTotal(c, [7, 8, 9, 8, 6]), 23);
  assert.equal(scoreTotal(c, ['7.5', '8', '8.5', '8', '7']), 23.5);
  assert.equal(scoreTotal(c, [7, 8, 9, 8, 6, 10]), 23);   // daugiau nei teisėjų - nežiūrima
  assert.equal(fmtScore(c, 23), '23.0');
  assert.equal(fmtScore(c, null), '–');
});

test('a missing judge score means no total yet', () => {
  const c = grupe(['a'], { system: 'score' });
  assert.equal(scoreTotal(c, ['7', '8', '', '8', '6']), null);
  assert.equal(scoreTotal(c, [7, 8, 9]), null);
  assert.equal(scoreTotal(c, undefined), null);
});

test('3 judges: nothing dropped; drop switched off: all summed', () => {
  assert.equal(scoreTotal(grupe(['a'], { cfg: { judges: 3 } }), [7, 8, 9]), 24);
  assert.equal(scoreTotal(grupe(['a'], { cfg: { drop: false } }), [7, 8, 9, 8, 6]), 38);
});

test('ranking: higher total first, missing last; a tie goes to the sum of all scores', () => {
  const c = grupe(['a', 'b', 'c'], { system: 'score', scores: { a: five(7), b: five(8), c: [] } });
  assert.deepEqual(scoreRank(c, 'scores', ['a', 'b', 'c']).map((x) => [x.id, x.total]), [['b', 24], ['a', 21], ['c', null]]);
  const t = grupe(['a', 'b'], { system: 'score', scores: { a: [10, 7, 7, 7, 5], b: five(7) } });
  assert.deepEqual(scoreRank(t, 'scores', ['b', 'a']).map((x) => [x.id, x.total, x.all]), [['a', 21, 36], ['b', 21, 35]]);
});

test('1 round: places when everyone has a total', () => {
  const c = grupe(['a', 'b', 'c'], { system: 'score', scores: { a: five(7), b: five(8) } });
  assert.equal(matchCount(c), 3);
  assert.equal(scoreDone(c), 2);
  assert.deepEqual(catPlaces(c), []);
  c.draw.scores.c = five(6);
  assert.equal(catFinished(c), true);
  assert.deepEqual(catPlaces(c), [[1, ['b']], [2, ['a']], [3, ['c']]]);
});

test('equal totals and equal sums share the place (as it works now)', () => {
  const c = grupe(['a', 'b', 'c'], { system: 'score', scores: { a: five(7), b: five(7), c: five(6) } });
  assert.deepEqual(catPlaces(c), [[1, ['a']], [1, ['b']], [3, ['c']]]);
});

test('2 rounds: finalists perform from the lowest semi-final score, final place = both rounds', () => {
  const c = grupe(['a', 'b', 'c'], { system: 'score', cfg: { rounds: 2, finalists: 2 }, scores: { a: five(7), b: five(8) } });
  assert.deepEqual(scoreFinalists(c), []);   // pusfinalis dar nebaigtas
  c.draw.scores.c = five(6);
  assert.deepEqual(scoreFinalists(c), ['a', 'b']);
  assert.equal(matchCount(c), 5);
  assert.equal(scoreDone(c), 3);
  assert.deepEqual(catPlaces(c), []);
  c.draw.final = { a: five(9), b: five(7) };   // a: 21 + 27 = 48, b: 24 + 21 = 45
  assert.deepEqual(scoreRank(c, 'final', ['a', 'b']).map((x) => [x.id, x.r1, x.total, x.sum]), [['a', 21, 27, 48], ['b', 24, 21, 45]]);
  assert.equal(scoreDone(c), 5);
  assert.deepEqual(catPlaces(c), [[1, ['a']], [2, ['b']], [3, ['c']]]);
});
