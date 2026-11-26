// v2.13: kovos vieta vaizdo įraše (engine/video.js) - kovos pradžia, kuris įrašas, kiek sekundžių nuo įrašo pradžios, telefono failas, pataisymas
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { calibrate, clock, fileStart, guessStart, inVideo, LEAD, offsetSec, parseClock, pickVideo, ytId, ytLink } from '../../public_html/assets/varzybos/engine/video.js';

const T = Date.UTC(2026, 10, 28, 9, 0, 0);   // 11:00 Vilniuje
const min = 60000;

test('bout start without the bout panel: when the previous bout on the same tatami ended', () => {
  const times = [{ s: T, e: T + 3 * min, a: 1 }, { s: T, e: T + 4 * min, a: 2 }];
  assert.equal(guessStart(times, 1, T + 5 * min), T + 3 * min);        // ankstesnė to tatamio kova
  assert.equal(guessStart(times, 1, T + 20 * min), T + 16 * min);      // ne anksčiau nei prieš 4 min.
  assert.equal(guessStart(times, 3, T + 10 * min), T + 7 * min);       // kitas tatamis, ankstesnių nėra - prieš 3 min.
  assert.equal(guessStart(times, 1, T + 3 * min + 5000), T + 3 * min + 5000 - 20000);   // ne vėliau nei prieš 20 s
});

test('which video shows the bout: the latest of that tatami that started before the bout ended', () => {
  const v1 = { a: 1, yt: 'aaaaaaaaaaa', from: T, ok: true }, v2 = { a: 1, yt: 'bbbbbbbbbbb', from: T + 60 * min, ok: true };
  const v3 = { a: 2, yt: 'ccccccccccc', from: T }, f1 = { a: 1, file: 'rec.webm|1', from: T - min };
  const list = [v1, v2, v3, f1];
  assert.equal(pickVideo(list, { s: T + 10 * min, e: T + 12 * min, a: 1 }, 'yt'), v1);
  assert.equal(pickVideo(list, { s: T + 70 * min, e: T + 72 * min, a: 1 }, 'yt'), v2);   // transliacija nutrūko, nauja
  assert.equal(pickVideo(list, { s: T + 5 * min, e: T + 6 * min, a: 2 }, 'yt'), v3);
  assert.equal(pickVideo(list, { s: T + 5 * min, e: T + 6 * min, a: 3 }, 'yt'), null);   // tatamis be įrašo
  assert.equal(pickVideo(list, { s: T + 5 * min, e: T + 6 * min, a: 1 }, 'file'), f1);
  // nuoroda įklijuota vėliau, nei prasidėjo transliacija (laikas nepatikrintas) - vis tiek tas įrašas
  const late = { a: 4, yt: 'ddddddddddd', from: T + 10 * min };
  assert.equal(pickVideo([late], { s: T + 2 * min, e: T + 5 * min, a: 4 }, 'yt'), late);
  assert.equal(pickVideo([{ ...late, ok: true }], { s: T + 2 * min, e: T + 5 * min, a: 4 }, 'yt'), null);
});

test('offset in the video: from the bout start, a little earlier', () => {
  const v = { a: 1, yt: 'abcDEF12345', from: T };
  const t = { s: T + 12 * min + 30000, e: T + 15 * min, a: 1 };
  assert.equal(offsetSec(t, v), 12 * 60 + 30 - LEAD);
  assert.equal(ytLink(t, v), 'https://youtu.be/abcDEF12345?t=' + (12 * 60 + 30 - LEAD));
  assert.equal(offsetSec({ s: T + 3000, e: T + min, a: 1 }, v), 0);   // ne mažiau nei 0
  assert.ok(inVideo(t, v, 20 * 60));
  assert.ok(!inVideo(t, v, 10 * 60));
});

test('phone file: start = last modified - duration; "starts here" fixes the video start', () => {
  assert.equal(fileStart(T + 90 * min, 90 * 60), T);
  assert.equal(fileStart(T + 1000.4, 1), T);
  const t = { s: T + 30 * min, e: T + 33 * min, a: 1 };
  const v = { a: 1, file: 'x|1', from: T + min };   // apytiksliai: 1 min. per vėlai
  v.from = calibrate(t, 29 * 60 + 15);               // vaizde kova prasideda 29:15
  assert.equal(v.from, T + 45000);
  assert.equal(offsetSec(t, v), 29 * 60 + 15 - LEAD);
  // kita to paties įrašo kova - pagal tą patį pataisytą laiką
  assert.equal(offsetSec({ s: T + 40 * min, e: T + 42 * min, a: 1 }, v), 39 * 60 + 15 - LEAD);
});

test('clock text and YouTube ids', () => {
  assert.equal(clock(65), '1:05');
  assert.equal(clock(3725), '1:02:05');
  assert.equal(clock(-4), '0:00');
  assert.equal(parseClock('1:02:05'), 3725);
  assert.equal(parseClock('62:05'), 3725);
  assert.equal(parseClock('95'), 95);
  assert.equal(parseClock('1:x'), null);
  assert.equal(parseClock(''), null);
  assert.equal(ytId('https://youtube.com/live/abcDEF12345?feature=share'), 'abcDEF12345');
  assert.equal(ytId('https://youtu.be/abcDEF12345'), 'abcDEF12345');
  assert.equal(ytId('https://www.youtube.com/watch?v=abcDEF12345&t=5'), 'abcDEF12345');
  assert.equal(ytId('https://www.youtube.com/channel/UC1234567890123456789012'), '');
});
