<?php
// Telefonas - transliacijos kamera (v1.98-v1.99). Telefonas nuskenuoja vieną klubo QR kodą (Budo → 🎥 Transliacija) ir atsiranda sąraše kompiuteryje.
// Kompiuteryje priskyrus tatamį, telefone atsiranda didelis „📹 Pradėti filmuoti“ - jis atidaro vdo.ninja kamerą (visame ekrane, patikimiausia
// telefonuose) ir siunčia vaizdą to tatamio srautu per Meshcast serverį, todėl jį mato sekretoriatas, Transliacijos puslapis ir žiūrovai.
// Prieiga - tik su klubo raktu (?c=), paskyros nereikia. Telefonas praneša apie save kas 3 s (stream_phones, db/059).
// v2.13: „⏺ Įrašyti ir šiame telefone“ - vdo.ninja &autorecordlocal įrašo vaizdą į telefono atsisiuntimus (WebM, 1500 kbps ≈ 0,7 GB/val.);
// po varžybų Budo → Lentelė → 🎬 prie kovos → „📂 Įrašas iš telefono“ atidaro failą nuo tos kovos pradžios.
require __DIR__ . '/app/bootstrap.php';

// C3: raktas - app/links.php (rūšis 'cam'), adrese ?k= (senas ?c= veikia toliau)
$key = (string) (get('k') ?: get('c'));
if (!link_find('cam', $key)) {
    link_dead(is_post());
}

if (is_post() && get('ping')) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!csrf_ok()) {   // C1: kiekvienas POST - su sesijos žetonu (window.CAM.csrf)
        http_response_code(400);
        exit(json_encode(['ok' => false, 'error' => 'Atnaujinkite puslapį.'], JSON_UNESCAPED_UNICODE));
    }
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
        'push' => $area ? varz_cam_push_url(varz_cam_id($key, $area)) : ''], JSON_UNESCAPED_UNICODE));
}
$cfg = ['ping' => url('varzybos-kamera.php?k=' . $key . '&ping=1'), 'csrf' => csrf_token()];
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
  <p class="cam-state" id="cam-state">Jungiamasi…</p>
  <a class="cam-start" id="cam-start" hidden>📹 Pradėti filmuoti</a>
  <label class="cam-rec"><input type="checkbox" id="cam-rec"> <span>⏺ Įrašyti vaizdą ir šiame telefone</span></label>
  <p class="cam-rec-note" id="cam-rec-note" hidden>Įrašas išsaugomas telefono atsisiuntimuose (apie 0,7 GB per valandą - patikrinkite, ar užtenka vietos). Baigę pirmiausia uždarykite kameros skirtuką, tik tada naršyklę. Po varžybų: Budo → Lentelė → 🎬 prie kovos → „📂 Įrašas iš telefono“. iPhone gali neįrašyti.</p>
  <label class="cam-name">Šio telefono pavadinimas <input type="text" id="cam-name" maxlength="40" autocomplete="off"></label>
  <ol class="cam-tips">
    <li>Kompiuteryje: Budo → „🎥 Transliacija“ → prie tatamio paspauskite šio telefono pavadinimą.</li>
    <li>Norint turėti įrašą telefone - pažymėkite „⏺ Įrašyti vaizdą ir šiame telefone“.</li>
    <li>Čia paspauskite „📹 Pradėti filmuoti“ ir leiskite naudoti kamerą.</li>
    <li>Telefoną laikykite gulsčią, ant stovo, prijunkite kroviklį. Ekranas turi likti įjungtas.</li>
  </ol>
  <p class="cam-alt">Norint pakeisti tatamį - vėl nuskenuokite QR kodą.</p>
</main>
<script>window.CAM = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script>
(function () {
  'use strict';
  var C = window.CAM || {}, id = '', name = '', fails = 0, rec = false, push = '';
  var $ = function (s) { return document.getElementById(s); };
  // Telefono raktas ir pavadinimas lieka šiame telefone (kitą kartą - tas pats telefonas sąraše, tas pats tatamis)
  try { id = localStorage.getItem('vz-cam-id') || ''; name = localStorage.getItem('vz-cam-name') || ''; rec = localStorage.getItem('vz-cam-rec') === '1'; } catch (e) {}
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
  // ⏺ įrašas telefone: vdo.ninja pats pradeda įrašinėti, kai atsidaro kamera
  function startHref() { var b = $('cam-start'); if (push) b.href = push + (rec ? '&autorecordlocal=1500' : ''); $('cam-rec-note').hidden = !rec; }
  $('cam-rec').checked = rec;
  $('cam-rec').addEventListener('change', function () { rec = this.checked; try { localStorage.setItem('vz-cam-rec', rec ? '1' : '0'); } catch (e) {} startHref(); });
  startHref();
  function state(text, cls) { var s = $('cam-state'); s.textContent = text; s.className = 'cam-state' + (cls ? ' ' + cls : ''); }
  function ping() {
    fetch(C.ping, { method: 'POST', cache: 'no-store', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': C.csrf || '' }, body: JSON.stringify({ id: id, name: name }) })
      .then(function (r) { return r.json(); }).then(function (d) {
        if (!d.ok) { state('⚠ ' + (d.error || 'Nepavyko prisijungti.'), 'warn'); return; }
        fails = 0;
        var b = $('cam-start');
        if (d.area && d.push) {
          state('✅ Priskirta: ' + d.areaName + '. Paspauskite „Pradėti filmuoti“.', 'live');
          push = d.push; startHref(); b.hidden = false;
        } else {
          state('✅ Prisijungta. Kompiuteryje prie tatamio paspauskite šio telefono pavadinimą.', 'ok');
          b.hidden = true;
        }
      }).catch(function () { if (++fails > 1) state('⚠ Nėra ryšio su serveriu - bandoma iš naujo…', 'warn'); });
  }
  ping();
  setInterval(function () { if (!document.hidden) ping(); }, 3000);
})();
</script>
</body>
</html>
