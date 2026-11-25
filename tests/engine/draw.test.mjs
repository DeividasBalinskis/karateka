// Burtai (engine/draw.js): pastovūs pagal sėklą, laisvi ratai, to paties klubo dalyviai pirmame rate nesusitinka
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { seedOrder, shuffle, seededRnd, drawSeed, makeDraw } from '../../public_html/assets/varzybos/engine/draw.js';
import { grupe } from './helpers.mjs';

const ids = (n) => Array.from({ length: n }, (_, i) => 'p' + (i + 1));
const pairs = (slots) => { const out = []; for (let i = 0; i < slots.length; i += 2) out.push([slots[i], slots[i + 1]]); return out; };

test('seed order: 1 and 2 meet only in the final', () => {
  assert.deepEqual(seedOrder(2), [1, 2]);
  assert.deepEqual(seedOrder(4), [1, 4, 2, 3]);
  assert.deepEqual(seedOrder(8), [1, 8, 4, 5, 2, 7, 3, 6]);
  assert.equal(seedOrder(16).length, 16);
});

test('seeded random: same text gives the same numbers, another text other numbers', () => {
  const a = seededRnd('7|k1|plain|p1,p2'), b = seededRnd('7|k1|plain|p1,p2'), c = seededRnd('7|k1|plain|p1,p3');
  const sa = [a(), a(), a(), a()], sb = [b(), b(), b(), b()], sc = [c(), c(), c(), c()];
  assert.deepEqual(sa, sb);
  assert.notDeepEqual(sa, sc);
  sa.concat(sc).forEach((x) => assert.ok(x >= 0 && x < 1));
});

test('draw seed: tournament, group, system and sorted participant ids', () => {
  const c = grupe(['p2', 'p1', 'p3']);
  assert.equal(drawSeed(c, 'plain', 7), '7|k1|plain|p1,p2,p3');
  assert.equal(drawSeed(c, 'wkf'), '0|k1|wkf|p1,p2,p3');
});

test('shuffle with a seeded random is repeatable and keeps every item', () => {
  const x = shuffle(ids(10), seededRnd('s')), y = shuffle(ids(10), seededRnd('s'));
  assert.deepEqual(x, y);
  assert.deepEqual(x.slice().sort(), ids(10).sort());
});

test('elimination draw: 5 participants - 8 places, 3 byes, everyone once', () => {
  const c = grupe(ids(5));
  const d = makeDraw(c, 'plain', seededRnd(drawSeed(c, 'plain', 1)));
  assert.equal(d.system, 'plain');
  assert.equal(d.slots.length, 8);
  // laisvi ratai - ten, kur pagal sėklų tvarką būtų 6, 7 ir 8 sėkla
  assert.deepEqual(d.slots.map((s) => s === null), [false, true, false, false, false, true, false, true]);
  assert.deepEqual(d.slots.filter(Boolean).sort(), ids(5));
  assert.deepEqual(d.results, {});
  assert.equal(d.locked, false);
});

test('elimination draw is the same after a reload (same seed) and differs for another group', () => {
  const c = grupe(ids(7));
  const a = makeDraw(c, 'wkf', seededRnd(drawSeed(c, 'wkf', 3)));
  const b = makeDraw(c, 'wkf', seededRnd(drawSeed(c, 'wkf', 3)));
  assert.deepEqual(a, b);
  const other = grupe(ids(7), { id: 'k2' });
  const seen = new Set();
  for (let t = 1; t <= 6; t++) seen.add(JSON.stringify(makeDraw(other, 'wkf', seededRnd(drawSeed(other, 'wkf', t))).slots));
  assert.ok(seen.size > 1, 'another tournament id gives another draw');
});

test('same club does not meet in the first round when it can be avoided', () => {
  // 2 klubai po lygiai, 3 klubai nelygiai (4 + 2 + 2), 2 + 2
  const cases = [
    { n: 8, club: (i) => (i % 2 ? 'A' : 'B') },
    { n: 8, club: (i) => (i < 4 ? 'A' : i < 6 ? 'B' : 'C') },
    { n: 4, club: (i) => (i < 2 ? 'A' : 'B') },
  ];
  for (const cs of cases) {
    const list = ids(cs.n), clubs = {};
    list.forEach((id, i) => { clubs[id] = cs.club(i); });
    for (let t = 1; t <= 40; t++) {
      const c = grupe(list, { clubs, id: 'k' + t });
      const d = makeDraw(c, 'plain', seededRnd(drawSeed(c, 'plain', t)));
      for (const [x, y] of pairs(d.slots)) assert.notEqual(clubs[x], clubs[y], `seed ${t}: ${x} and ${y} are from one club`);
    }
  }
});

test('round robin and score draws: an order of all participants', () => {
  for (const sys of ['rr', 'score']) {
    const c = grupe(ids(6));
    const d = makeDraw(c, sys, seededRnd('x' + sys));
    assert.equal(d.system, sys);
    assert.deepEqual(d.order.slice().sort(), ids(6).sort());
    assert.deepEqual([d.results, d.scores, d.final], [{}, {}, {}]);
    assert.equal(d.slots, undefined);
  }
});
