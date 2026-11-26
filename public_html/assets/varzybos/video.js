// 🎬 Pasibaigusios kovos vaizdas (v2.13): paspaudus 🎬 prie baigtos kovos - jos vieta YouTube transliacijos įraše arba telefono įraše.
// Kovos laikas įrašomas, kai pažymimas rezultatas (stamp). Telefono failas į serverį nekeliamas - atidaromas tik šiame įrenginyje.
import { CFG, esc, toast } from './util.js';
import { S, save } from './store.js';
import { calibrate, clock, fileStart, guessStart, inVideo, offsetSec, parseClock, pickVideo, ytId, ytLink } from './engine/video.js';

function areaId(c) { var a = (S.areas || []).find(function (x) { return x.id === c.tatami; }) || (S.areas || [])[0]; return a ? a.id : 0; }
function areaName(id) { var a = (S.areas || []).find(function (x) { return x.id === id; }); return a ? a.name : ''; }
function allTimes() {
  var out = [];
  S.categories.forEach(function (c) { var t = c.draw && c.draw.times; if (t) Object.keys(t).forEach(function (k) { out.push(t[k]); }); });
  return out;
}
function videos() { if (!Array.isArray(S.videos)) S.videos = []; return S.videos; }
function hm(ms) { var d = new Date(ms); return (d.getHours() < 10 ? '0' : '') + d.getHours() + ':' + (d.getMinutes() < 10 ? '0' : '') + d.getMinutes(); }

// Rezultatas pažymėtas: kada kova vyko (start - iš kovos skydelio; kitaip - kai baigėsi ankstesnė kova ant to tatamio)
export function stamp(c, key, start) {
  if (!c || !c.draw || !key) return;
  var now = Date.now(), a = areaId(c);
  c.draw.times = c.draw.times || {};
  c.draw.times[key] = { s: Math.round(start && start < now ? start : guessStart(allTimes(), a, now)), e: now, a: a };
}
export function unstamp(c, key) { if (c && c.draw && c.draw.times) delete c.draw.times[key]; }
export function timeOf(c, key) { return c && c.draw && c.draw.times ? c.draw.times[key] || null : null; }
export function vidBtn(c, key) { return timeOf(c, key) ? '<button type="button" class="m-vid" data-vid="' + esc(key) + '" title="Kovos vaizdas: YouTube įrašas arba įrašas iš telefono nuo šios kovos pradžios">🎬</button>' : ''; }

// Žiūrovams (viešas rezultatų puslapis): YouTube nuoroda nuo kovos pradžios; nėra įrašo - ''
export function vidLink(c, key) {
  var t = timeOf(c, key), v = t && pickVideo(videos(), t, 'yt');
  return v ? ytLink(t, v) : '';
}

// 📹 Tvarkaraštyje įklijuota tatamio YouTube nuoroda: naujas įrašas; serveris sužino, kada transliacija tikrai prasidėjo
export function addStream(area, url) {
  if (!url || !area) return;
  var id = ytId(url), list = videos();
  var v = id && list.find(function (x) { return x.yt === id; });
  if (!v) { v = { a: area.id, yt: id, from: Date.now() }; list.push(v); }
  else v.a = area.id;
  if (v.ok || !CFG.api || CFG.readOnly) return;
  fetch(CFG.api + '&yt=1', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf || '', Accept: 'application/json' }, body: JSON.stringify({ url: url }) })
    .then(function (r) { return r.json(); }).then(function (d) {
      if (!d || !d.ok) return;
      if (d.id && /^[A-Za-z0-9_-]{11}$/.test(d.id)) {
        var same = list.find(function (x) { return x !== v && x.yt === d.id; });
        if (same) { list.splice(list.indexOf(v), 1); v = same; } else v.yt = d.id;
      }
      if (typeof d.start === 'number' && d.start > 0) { v.from = d.start; v.ok = true; }
      save();
    }).catch(function () {});
}

// ---------- Langas ----------
var box = document.createElement('div'), cur = null, mem = null;   // mem - pasirinktas telefono failas (lieka, kol puslapis atidarytas)
box.className = 'vd-back'; box.hidden = true;
box.innerHTML = '<div class="vd-box" role="dialog" aria-modal="true" aria-labelledby="vd-title"><div class="vd-head"><b id="vd-title"></b><button type="button" class="vd-x" data-vd="close" aria-label="Uždaryti">✕</button></div>' +
  '<p class="vd-meta" id="vd-meta"></p><div class="vd-player" id="vd-player"></div>' +
  '<div class="vd-tools"><button type="button" class="btn btn-sm" data-vd="here" id="vd-here" title="Nuslinkite vaizdą iki kovos pradžios ir paspauskite - kitos šio įrašo kovos atsidarys tiksliai">📍 Ši kova prasideda čia</button>' +
  '<label class="btn btn-sm vd-file">📂 Įrašas iš telefono<input type="file" accept="video/*,.webm,.mp4" id="vd-file"></label>' +
  '<a class="btn btn-sm" id="vd-yt" target="_blank" rel="noopener" hidden>YouTube ↗</a></div>' +
  '<p class="vd-help" id="vd-help"></p></div>';
document.body.appendChild(box);
var $ = function (id) { return document.getElementById(id); };

export function openVideo(c, key, title) {
  var t = timeOf(c, key);
  if (!t) { toast('Šios kovos laikas nežinomas - jis įrašomas, kai pažymimas rezultatas.'); return; }
  cur = { t: t, src: '', v: null, ytTime: null };
  $('vd-title').textContent = title || 'Kova';
  box.hidden = false; document.body.classList.add('vd-open-body');
  var yt = pickVideo(videos(), t, 'yt');
  if (mem && mem.v && mem.v.a === t.a && inVideo(t, mem.v, mem.dur || 1e7)) showFile();
  else if (yt) showYt(yt);
  else showNone();
  box.querySelector('.vd-x').focus();
}
function meta(extra) { $('vd-meta').textContent = 'Kova ' + hm(cur.t.s) + '–' + hm(cur.t.e) + (areaName(cur.t.a) ? ' · ' + areaName(cur.t.a) : '') + (extra ? ' · ' + extra : ''); }
function showNone() {
  cur.src = ''; meta('');
  $('vd-player').innerHTML = '<div class="vd-none"><b>Šio tatamio vaizdo įrašo dar nėra.</b><br>Telefono įrašą atidarykite mygtuku „📂 Įrašas iš telefono“ (failas lieka telefone ar kompiuteryje - niekur nekeliamas).<br>' +
    'YouTube: Tvarkaraštyje prie tatamio (📹) įklijuokite transliacijos nuorodą - toliau kovos atsidarys ir YouTube įraše.</div>';
  $('vd-here').hidden = true; $('vd-yt').hidden = true;
  $('vd-help').textContent = '';
}
function showYt(v) {
  cur.src = 'yt'; cur.v = v; cur.ytTime = null;
  var off = offsetSec(cur.t, v);
  meta('YouTube įraše nuo ' + clock(off) + (v.ok ? '' : ' (apytiksliai)'));
  $('vd-player').innerHTML = '<iframe id="vd-frame" src="https://www.youtube-nocookie.com/embed/' + v.yt + '?start=' + off + '&autoplay=1&playsinline=1&rel=0&enablejsapi=1&origin=' + encodeURIComponent(location.origin) + '" title="Kovos vaizdas" allow="autoplay; fullscreen; encrypted-media" allowfullscreen></iframe>';
  $('vd-frame').addEventListener('load', function () { try { this.contentWindow.postMessage(JSON.stringify({ event: 'listening', id: 'vd', channel: 'widget' }), '*'); } catch (e) {} });
  var a = $('vd-yt'); a.href = ytLink(cur.t, v); a.hidden = false;
  $('vd-here').hidden = !!CFG.readOnly;
  $('vd-help').textContent = v.ok ? 'Jei vaizdas ne nuo kovos pradžios - nuslinkite iki jos ir paspauskite „📍 Ši kova prasideda čia“.' :
    'Laikas apytikslis (nuo nuorodos įklijavimo). Nuslinkite iki kovos pradžios ir paspauskite „📍 Ši kova prasideda čia“ - kitos šio įrašo kovos atsidarys tiksliai.';
}
// YouTube grotuvas praneša, kurioje vietoje rodo (tam ir enablejsapi=1)
window.addEventListener('message', function (e) {
  if (!cur || cur.src !== 'yt' || !/^https:\/\/www\.youtube(-nocookie)?\.com$/.test(e.origin)) return;
  var d; try { d = typeof e.data === 'string' ? JSON.parse(e.data) : e.data; } catch (x) { return; }
  if (d && d.info && typeof d.info.currentTime === 'number') cur.ytTime = d.info.currentTime;
});

// Telefono įrašas (vdo.ninja įrašo WebM failą į telefono atsisiuntimus): pradžia = failo pakeitimo laikas − trukmė (arba jau pataisyta)
function useFile(f) {
  if (!f || !cur) return;
  if (mem && mem.url) URL.revokeObjectURL(mem.url);
  var key = f.name + '|' + f.size, list = videos();
  var v = list.find(function (x) { return x.file === key; });
  mem = { url: URL.createObjectURL(f), key: key, last: f.lastModified || Date.now(), v: v || null, dur: 0 };
  showFile();
}
function showFile() {
  cur.src = 'file'; cur.v = mem.v;
  $('vd-yt').hidden = true; $('vd-here').hidden = !!CFG.readOnly;
  $('vd-player').innerHTML = '<video id="vd-vid" controls playsinline preload="metadata"></video>';
  var vid = $('vd-vid'), done = false;
  meta('telefono įrašas, ruošiama…');
  vid.addEventListener('error', function () { meta(''); $('vd-help').textContent = 'Ši naršyklė šio failo neatidaro. Pabandykite kompiuteryje su Chrome (iPhone WebM failų dažnai nerodo).'; });
  function go(dur) {
    if (done) return; done = true;
    mem.dur = dur || mem.dur || 0;
    if (!mem.v) {   // pirmą kartą: pradžia iš failo; išsaugoma, kad kitos kovos ir kiti kartai naudotų tą patį (ir pataisytą) laiką
      if (!mem.dur) { meta(''); $('vd-help').textContent = 'Nepavyko sužinoti įrašo trukmės - nuslinkite iki kovos ir paspauskite „📍 Ši kova prasideda čia“.'; mem.v = { a: cur.t.a, file: mem.key, from: mem.last - 3600000 }; videos().push(mem.v); cur.v = mem.v; return; }
      mem.v = { a: cur.t.a, file: mem.key, from: fileStart(mem.last, mem.dur) };
      videos().push(mem.v); save();
    }
    cur.v = mem.v;
    var off = offsetSec(cur.t, mem.v);
    if (mem.dur && !inVideo(cur.t, mem.v, mem.dur)) {
      meta('ne šiame įraše');
      $('vd-help').textContent = 'Šis įrašas - ' + hm(mem.v.from) + '–' + hm(mem.v.from + mem.dur * 1000) + ', o kova vyko ' + hm(cur.t.s) + '. Pasirinkite kitą failą arba nuslinkite iki kovos ir paspauskite „📍 Ši kova prasideda čia“.';
      vid.currentTime = 0; return;
    }
    meta('telefono įraše nuo ' + clock(off) + (mem.v.ok ? '' : ' (apytiksliai)'));
    $('vd-help').textContent = 'Jei vaizdas ne nuo kovos pradžios - nuslinkite iki jos ir paspauskite „📍 Ši kova prasideda čia“.';
    vid.currentTime = off;
    var p = vid.play(); if (p && p.catch) p.catch(function () {});
  }
  vid.addEventListener('loadedmetadata', function () {
    if (isFinite(vid.duration) && vid.duration > 0) { go(vid.duration); return; }
    // Telefonu įrašytas WebM failas trukmės neturi - nušokus į galą ji paaiškėja
    var onDur = function () { if (isFinite(vid.duration) && vid.duration > 0) { vid.removeEventListener('durationchange', onDur); go(vid.duration); } };
    vid.addEventListener('durationchange', onDur);
    vid.currentTime = 1e9;
    setTimeout(function () { go(isFinite(vid.duration) ? vid.duration : 0); }, 8000);
  });
  vid.src = mem.url;
}
function here() {
  if (!cur || !cur.v) return;
  var sec = null;
  if (cur.src === 'file') sec = ($('vd-vid') || {}).currentTime;
  else if (cur.src === 'yt') sec = cur.ytTime;
  if (typeof sec !== 'number' || !isFinite(sec)) {
    sec = parseClock(prompt('Kiek rodo vaizdo laikas, kai ši kova prasideda? (pvz. 1:02:15)', ''));
    if (sec === null) return;
  }
  cur.v.from = calibrate(cur.t, sec); cur.v.ok = true;
  save();
  meta((cur.src === 'yt' ? 'YouTube' : 'telefono') + ' įraše nuo ' + clock(sec));
  $('vd-help').textContent = 'Jei vaizdas ne nuo kovos pradžios - nuslinkite iki jos ir paspauskite „📍 Ši kova prasideda čia“.';
  if (cur.src === 'yt') { var a = $('vd-yt'); a.href = ytLink(cur.t, cur.v); }
  toast('📍 Laikas pataisytas - kitos šio įrašo kovos atsidarys nuo jų pradžios.');
}
export function closeVideo() {
  if (box.hidden) return;
  box.hidden = true; document.body.classList.remove('vd-open-body');
  var vid = $('vd-vid'); if (vid) vid.pause();
  $('vd-player').innerHTML = '';
  cur = null;
}
box.addEventListener('click', function (e) {
  if (e.target === box) { closeVideo(); return; }
  var b = e.target.closest('[data-vd]'); if (!b) return;
  if (b.dataset.vd === 'close') closeVideo();
  else if (b.dataset.vd === 'here') here();
});
$('vd-file').addEventListener('change', function () { useFile(this.files && this.files[0]); this.value = ''; });
document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !box.hidden) closeVideo(); });
