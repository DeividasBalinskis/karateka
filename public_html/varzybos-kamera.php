<?php
// Telefonas - transliacijos kamera (v1.98). Telefonas nuskenuoja vieną klubo QR kodą (Budo → 🎥 Transliacija) ir atsiranda sąraše kompiuteryje.
// Kompiuteryje jis priskiriamas tatamiui: tada telefonas siunčia vaizdą to tatamio nuolatiniu srautu (vdo.ninja ?push=kt<raktas>t<tatamis>),
// kurį mato sekretoriatas (kovos skydelis „📹 Vaizdas“), Transliacijos peržiūra ir OBS. Nepriskirtas - siunčia savo srautu (kompiuteris mato jo vaizdą).
// Prieiga - tik su klubo kameros raktu (?c=), paskyros nereikia. Telefonas praneša apie save kas 8 s (stream_phones, db/059), ekranas neužmiega.
require __DIR__ . '/app/bootstrap.php';

$key = varz_stream_key('cam');
if (!hash_equals($key, (string) get('c'))) {
    http_response_code(404);
    exit('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Nuoroda netinkama</title>'
        . '<p style="font-family:system-ui;padding:40px;">Nuoroda netinkama. Nuskenuokite QR kodą iš Budo → 🎥 Transliacija.</p>');
}

if (is_post() && get('ping')) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
    $id = (string) ($in['id'] ?? '');
    if (!preg_match('/^[a-z0-9]{6,12}$/', $id)) {
        http_response_code(400);
        exit(json_encode(['ok' => false]));
    }
    $name = mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) ($in['name'] ?? ''))), 0, 40);
    try {
        q('INSERT INTO stream_phones (id, name, seen_at) VALUES (?, ?, NOW())
           ON DUPLICATE KEY UPDATE name = IF(VALUES(name) = "", name, VALUES(name)), seen_at = NOW()', [$id, $name]);
        $row = q_one('SELECT area_id, area_name FROM stream_phones WHERE id = ?', [$id]);
    } catch (PDOException $ex) {
        exit(json_encode(['ok' => false, 'error' => 'Serveris dar ruošiamas - pabandykite po minutės.'], JSON_UNESCAPED_UNICODE));   // dar neimportuotas db/059
    }
    $area = $row && $row['area_id'] !== null ? (int) $row['area_id'] : 0;
    exit(json_encode(['ok' => true, 'area' => $area, 'areaName' => $area ? (string) ($row['area_name'] ?: $area . ' TATAMIS') : '',
        'push' => varz_cam_push_url($area ? varz_cam_id($key, $area) : varz_cam_phone_id($key, $id))], JSON_UNESCAPED_UNICODE));
}
$cfg = ['ping' => url('varzybos-kamera.php?c=' . $key . '&ping=1')];
?><!doctype html>
<html lang="lt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#12151C">
<title>Budo kamera</title>
<link rel="icon" type="image/png" sizes="32x32" href="<?= url('favicon-32.png?v=2') ?>">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('assets/varzybos.css')) ?>">
<?= tl_script() ?>
</head>
<body class="cam-body">
<main class="cam-main">
  <h1>📱 Budo kamera</h1>
  <p class="cam-state" id="cam-state">Paspauskite „Įjungti kamerą“ ir leiskite naudoti kamerą.</p>
  <button type="button" class="cam-start" id="cam-start">📹 Įjungti kamerą</button>
  <div class="cam-frame" id="cam-frame" hidden><iframe id="cam-if" title="Kamera" allow="camera; microphone; autoplay; fullscreen"></iframe></div>
  <label class="cam-name">Šio telefono pavadinimas <input type="text" id="cam-name" maxlength="40" autocomplete="off"></label>
  <ul class="cam-tips">
    <li>Telefoną laikykite gulsčią, ant stovo, ir prijunkite kroviklį.</li>
    <li>Šį puslapį laikykite atidarytą - ekranas neužges.</li>
    <li>Kompiuteryje: Budo → „🎥 Transliacija“ → šį telefoną priskirkite tatamiui.</li>
  </ul>
  <p class="cam-alt"><a id="cam-open" target="_blank" rel="noopener" hidden>Kamera neįsijungia? Atidaryti ją atskirai</a></p>
</main>
<script>window.CAM = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script>
(function () {
  'use strict';
  var C = window.CAM || {}, id = '', name = '', started = false, push = '', lock = null, fails = 0;
  var $ = function (s) { return document.getElementById(s); };
  // Telefono raktas ir pavadinimas lieka šiame telefone (kitą kartą - tas pats telefonas sąraše)
  try { id = localStorage.getItem('vz-cam-id') || ''; name = localStorage.getItem('vz-cam-name') || ''; } catch (e) {}
  if (!/^[a-z0-9]{6,12}$/.test(id)) {
    id = Math.random().toString(36).slice(2, 10).replace(/[^a-z0-9]/g, 'x');
    while (id.length < 8) id += 'x';
    try { localStorage.setItem('vz-cam-id', id); } catch (e) {}
  }
  if (!name) name = 'Telefonas ' + id.slice(-3).toUpperCase();
  $('cam-name').value = name;
  $('cam-name').addEventListener('change', function () {
    name = this.value.trim() || name; this.value = name;
    try { localStorage.setItem('vz-cam-name', name); } catch (e) {}
    ping();
  });
  function state(text, cls) { var s = $('cam-state'); s.textContent = text; s.className = 'cam-state' + (cls ? ' ' + cls : ''); }
  // Ekranas neužmiega, kol puslapis atidarytas (Wake Lock; grįžus į puslapį - vėl)
  function wake() { if ('wakeLock' in navigator) navigator.wakeLock.request('screen').then(function (l) { lock = l; }).catch(function () {}); }
  document.addEventListener('visibilitychange', function () { if (!document.hidden && started) { wake(); ping(); } });
  function ping() {
    if (!started) return;
    fetch(C.ping, { method: 'POST', cache: 'no-store', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id, name: name }) })
      .then(function (r) { return r.json(); }).then(function (d) {
        if (!d.ok) { state('⚠ ' + (d.error || 'Nepavyko prisijungti.'), 'warn'); return; }
        fails = 0;
        if (d.push && d.push !== push) {   // priskyrus kitam tatamiui - kamera persijungia į to tatamio srautą
          push = d.push; $('cam-if').src = push; $('cam-open').href = push; $('cam-open').hidden = false;
        }
        if (d.area) state('🔴 Transliuojama: ' + d.areaName, 'live');
        else state('✅ Prisijungta. Kompiuteryje (Budo → „🎥 Transliacija“) priskirkite šį telefoną tatamiui.', 'ok');
      }).catch(function () { if (++fails > 1) state('⚠ Nėra ryšio su serveriu - bandoma iš naujo…', 'warn'); });
  }
  $('cam-start').addEventListener('click', function () {
    started = true; this.hidden = true; $('cam-frame').hidden = false;
    state('Jungiamasi…', '');
    wake(); ping();
    setInterval(ping, 8000);
  });
})();
</script>
</body>
</html>
