<?php
// Transliacija be OBS (v1.99): telefonų prijungimas (treneriui ir adminui).
//  - 📱 Telefonai: vienas klubo QR kodas visiems telefonams (varzybos-kamera.php). Prisijungęs telefonas laukia, kol prie tatamio
//    paspausite jo pavadinimą (stream_phones, db/059); tada telefone „📹 Pradėti filmuoti“ - vaizdas eina to tatamio srautu.
//  - Tatamių kortelės: telefono vaizdas su juosta (kova ir balai), priskirtas telefonas, „Atjungti“. v2.12: ± (pavyzdinė kova) ištrinti.
//  - Vaizdą mato žiūrovai (nuoroda žiūrovams → „📹 Žiūrėti gyvai“, per Meshcast - daug žiūrovų) ir šis puslapis. Sekretoriatui vaizdo nebėra (v2.12).
// QR ir srautai nuolatiniai (klubo raktas varz_cam_key) - tinka visoms varžyboms. Veikia bet kada - varžybų ir kovų nereikia.
require dirname(__DIR__) . '/app/bootstrap.php';
$me = varz_require_access();
$demo = varz_is_demo($me);
current_lang('lt');

$t = tournament_get((int) get('id'));
if ($t && $demo && empty($t['is_demo'])) {
    $t = null;
}
if (!$t) {
    flash('err', t('Varžybos nerastos.'));
    redirect('admin/varzybos.php');
}
// QR kodas pateko ne tiems žmonėms - naujas (tik pagrindinis adminas; senas QR nebeveiks, telefonus reikės prijungti iš naujo)
if (is_post() && post('action') === 'renew' && can('budo_admin') && !$demo) {
    csrf_check();
    varz_cam_key(true);
    flash('ok', 'Sukurtas naujas QR kodas. Telefonais nuskenuokite naują kodą.');
    redirect('admin/varzybu-transliacija.php?id=' . (int) $t['id']);
}
$areas = array_values(array_filter((array) (tournament_state($t)['areas'] ?? []), function ($a) { return isset($a['id']); }));
if (!$areas) {
    $areas = [['id' => 1, 'name' => '1 TATAMIS']];
}
$key = varz_cam_key();

// Telefonai: sąrašas ir priskyrimas tatamiui (JSON; vienas telefonas - vienam tatamiui; area 0 - atjungti)
if (get('telefonai')) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if ($demo) {
        http_response_code(403);
        exit(json_encode(['ok' => false]));
    }
    try {
        if (is_post()) {
            csrf_check();
            $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
            $pid = (string) ($in['id'] ?? '');
            if (!empty($in['forget'])) {
                q('DELETE FROM stream_phones WHERE id = ?', [$pid]);
            } else {
                $aid = (int) ($in['area'] ?? 0);
                $aname = null;
                foreach ($areas as $a) {
                    if ((int) $a['id'] === $aid) {
                        $aname = (string) $a['name'];
                    }
                }
                if ($aid && $aname === null) {
                    http_response_code(400);
                    exit(json_encode(['ok' => false]));
                }
                if ($aid) {
                    q('UPDATE stream_phones SET area_id = NULL, area_name = NULL WHERE area_id = ? AND id <> ?', [$aid, $pid]);
                }
                q('UPDATE stream_phones SET area_id = ?, area_name = ? WHERE id = ?', [$aid ?: null, $aid ? $aname : null, $pid]);
            }
        }
        $list = q_all('SELECT id, name, area_id, TIMESTAMPDIFF(SECOND, seen_at, NOW()) AS ago FROM stream_phones
                       WHERE area_id IS NOT NULL OR seen_at > NOW() - INTERVAL 15 MINUTE ORDER BY seen_at DESC LIMIT 30');
    } catch (PDOException $ex) {
        exit(json_encode(['ok' => false, 'error' => 'Duomenų bazė dar atnaujinama - perkraukite puslapį po minutės.'], JSON_UNESCAPED_UNICODE));
    }
    exit(json_encode(['ok' => true, 'phones' => array_map(function ($p) {
        return ['id' => $p['id'], 'name' => $p['name'] !== '' ? $p['name'] : 'Telefonas', 'area' => $p['area_id'] !== null ? (int) $p['area_id'] : 0,
                'online' => (int) $p['ago'] <= 20];
    }, $list)], JSON_UNESCAPED_UNICODE));
}

$join = abs_url('varzybos-kamera.php?k=' . $key);   // C3: ?k= (senas ?c= veikia)
$share = tournament_token($t, 'share');
$viewers = abs_url('varzybos-gyvai.php?k=' . $share);
$back = url('admin/varzybu-sistema.php?id=' . (int) $t['id']);
$phonesApi = url('admin/varzybu-transliacija.php?id=' . (int) $t['id'] . '&telefonai=1');
?><!doctype html>
<html lang="lt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Transliacija · <?= e($t['title']) ?> · <?= e(VARZ_NAME) ?></title>
<link rel="icon" type="image/png" sizes="32x32" href="<?= url('favicon-32.png?v=2') ?>">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('assets/varzybos.css')) ?>">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
<?= tl_script() ?>
</head>
<body<?= theme_body_attrs(varz_ui()['theme'], 'vz tr-page') ?>>
<header class="vz-top">
  <div class="vz-top-row">
    <a class="vz-back" href="<?= e($back) ?>" title="Į varžybas">←</a>
    <div class="vz-title">
      <h1>🎥 Transliacija</h1>
      <div class="sub"><?= e($t['title']) ?></div>
    </div>
    <div class="vz-top-right"><?= render_lang_switch(tl_lang()) ?></div>
  </div>
</header>

<main class="tr-main" id="tr-main" data-api="<?= e($phonesApi) ?>" data-csrf="<?= e(csrf_token()) ?>">
  <?php foreach (take_flashes() as $f): ?>
    <div class="vz-flash vz-flash-<?= e($f['type']) ?>" role="status"><span><?= e($f['message']) ?></span></div>
  <?php endforeach; ?>

  <section class="panel tr-sec tr-join">
    <div class="tr-qr" id="tr-qr" data-url="<?= e($join) ?>"></div>
    <div class="tr-join-txt">
      <h2>📱 Prijunkite telefoną</h2>
      <ol class="tr-big-steps">
        <li>Telefonu nuskenuokite QR kodą.</li>
        <li>Žemiau, prie tatamio, paspauskite telefono pavadinimą.</li>
        <li>Telefone paspauskite „📹 Pradėti filmuoti“ ir leiskite naudoti kamerą.</li>
      </ol>
      <p class="tr-waiting" id="tr-waiting"><span class="muted">Laukiantys telefonai:</span> <span id="tr-waiting-list" class="muted">nėra - nuskenuokite QR kodą.</span></p>
      <div class="sp-row"><input class="sel sp-url" readonly value="<?= e($join) ?>" aria-label="Telefono prisijungimo nuoroda"><button type="button" class="btn" data-copy="<?= e($join) ?>">Kopijuoti</button>
        <button type="button" class="btn" id="tr-print">🖨 Spausdinti QR</button></div>
      <p class="muted small">Vienas QR kodas visiems telefonams ir visoms varžyboms. Neduokite jo kitiems.</p>
    </div>
  </section>

  <section class="tr-tatamis">
    <?php foreach ($areas as $a): $aid = (int) $a['id']; ?>
    <article class="panel tr-card" data-area="<?= $aid ?>">
      <header class="tr-card-h"><h2><?= e($a['name']) ?></h2><span class="tr-card-phone" data-phone>Telefono nėra</span></header>
      <div class="tr-preview">
        <iframe class="tr-cam" src="<?= e(varz_cam_view_url(varz_cam_id($key, $aid)) . '&noaudio') ?>" title="<?= e($a['name']) ?>" allow="autoplay; fullscreen" allowfullscreen loading="lazy"></iframe>
        <iframe class="tr-bar" src="<?= e(url('varzybos-gyvai.php?k=' . $share . '&juosta=1&tatami=' . $aid)) ?>" title="Juosta"></iframe>
      </div>
      <div class="tr-card-f" data-assign>
        <span class="muted small">Nuskenuokite QR kodą telefonu - jo pavadinimas atsiras čia.</span>
      </div>
    </article>
    <?php endforeach; ?>
  </section>

  <section class="panel tr-sec tr-two">
    <div>
      <h2>👀 Kur matosi vaizdas</h2>
      <ul class="tr-when">
        <li><b>Žiūrovai</b> <span>- žiūrovų puslapyje prie tatamio „📹 Žiūrėti gyvai“: vaizdas su balais ir laiku.</span> <a href="<?= e($viewers) ?>" target="_blank" rel="noopener">Atidaryti ↗</a></li>
        <li><b>Šis puslapis</b> <span>- visi tatamiai su juosta: kas kovoja ir balai (kai kova vyksta sekretoriato kovos skydelyje).</span></li>
      </ul>
    </div>
    <div>
      <h2>🛠 Jei neveikia</h2>
      <ul class="tr-when">
        <li><b>Telefono nėra sąraše</b> <span>- telefone vėl nuskenuokite QR kodą ir palaukite kelias sekundes.</span></li>
        <li><b>Juodas vaizdas</b> <span>- telefone nepaspaustas „Pradėti filmuoti“, užsirakino ekranas arba užsidarė puslapis. Vėl nuskenuokite QR - tatamis išliks tas pats.</span></li>
        <li><b>Vaizdas stringa</b> <span>- silpnas telefono internetas: junkite prie Wi-Fi, laikykite arčiau maršrutizatoriaus.</span></li>
        <li><b>Pakeisti tatamį</b> <span>- prie kito tatamio paspauskite telefono pavadinimą, telefone vėl nuskenuokite QR.</span></li>
      </ul>
    </div>
  </section>

  <section class="panel tr-sec">
    <h2>🎬 Kovų įrašai - pažiūrėti baigtą kovą</h2>
    <ul class="tr-when">
      <li><b>YouTube</b> <span>- telefone YouTube programėlėje „Transliuoti“, nuorodą įklijuokite Budo → Tvarkaraštis → 📹 prie tatamio. YouTube įrašą pasilieka pats.</span></li>
      <li><b>Telefone</b> <span>- prieš „📹 Pradėti filmuoti“ pažymėkite „⏺ Įrašyti vaizdą ir šiame telefone“. Failas lieka telefono atsisiuntimuose (apie 0,7 GB per valandą).</span></li>
      <li><b>Peržiūra</b> <span>- Budo → Lentelė → 🎬 prie baigtos kovos: vaizdas atsidaro nuo tos kovos pradžios (YouTube arba „📂 Įrašas iš telefono“). Žiūrovai YouTube įrašą mato prie kovos rezultato.</span></li>
      <li><b>Laikas netikslus</b> <span>- nuslinkite iki kovos pradžios ir paspauskite „📍 Ši kova prasideda čia“ - kitos to įrašo kovos atsidarys tiksliai.</span></li>
    </ul>
  </section>

  <?php if (can('budo_admin') && !$demo): ?>
  <form method="post" class="tr-renew" onsubmit="return confirm('Sukurti naują QR kodą? Senas nebeveiks, telefonus reikės prijungti iš naujo.')">
    <?= csrf_field() ?><input type="hidden" name="action" value="renew">
    <button type="submit" class="btn btn-xs">↻ Naujas QR kodas</button>
    <span class="muted small">Jei QR kodas pateko ne tiems žmonėms. Senas nebeveiks.</span>
  </form>
  <?php endif; ?>

  <!-- Spausdinimui: telefonų QR kodas (rodoma tik spausdinant) -->
  <section class="tr-print" aria-hidden="true">
    <div class="tr-pq">
      <b>📱 Budo kamera</b>
      <div class="tr-pq-code" data-url="<?= e($join) ?>"></div>
      <p>Nuskenuokite telefonu. Kompiuteryje prie tatamio paspauskite telefono pavadinimą, telefone - „📹 Pradėti filmuoti“.</p>
    </div>
  </section>
</main>
<script>
(function () {
  'use strict';
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  // QR kodai (biblioteka iš cdnjs; jei neįsikėlė - lieka nuoroda su „Kopijuoti“)
  function qr(el, size) {
    if (!window.QRCode) { el.hidden = true; return; }
    try { new QRCode(el, { text: el.dataset.url, width: size, height: size, correctLevel: QRCode.CorrectLevel.M }); } catch (e) { el.hidden = true; }
  }
  window.addEventListener('load', function () {
    var q = document.getElementById('tr-qr');
    if (q) qr(q, 190);
    Array.prototype.forEach.call(document.querySelectorAll('.tr-pq-code'), function (el) { qr(el, 300); });
  });

  // 📱 Telefonai: kas 3 s; prie kiekvieno tatamio - priskirtas telefonas („Atjungti“) arba laukiančių telefonų mygtukai („Priskirti“)
  var main = document.getElementById('tr-main'), last = '';
  function render(phones) {
    var key = JSON.stringify(phones); if (key === last) return; last = key;
    var waiting = phones.filter(function (p) { return !p.area && p.online; });
    var wl = document.getElementById('tr-waiting-list');
    wl.className = waiting.length ? '' : 'muted';
    wl.innerHTML = waiting.length ? waiting.map(function (p) { return '<span class="tr-chip">📱 ' + esc(p.name) + ' <button type="button" class="tr-x" data-forget="' + esc(p.id) + '" aria-label="Pamiršti">✕</button></span>'; }).join(' ')
      : 'nėra - nuskenuokite QR kodą.';
    Array.prototype.forEach.call(document.querySelectorAll('.tr-card'), function (card) {
      var aid = +card.dataset.area, mine = phones.filter(function (p) { return p.area === aid; })[0];
      card.classList.toggle('has-phone', !!mine);
      card.querySelector('[data-phone]').textContent = mine ? '📱 ' + mine.name : 'Telefono nėra';
      var f = card.querySelector('[data-assign]');
      if (mine) f.innerHTML = '<span class="tr-ok">✅ ' + esc(mine.name) + '</span> <button type="button" class="btn btn-xs" data-unassign="' + esc(mine.id) + '">Atjungti</button>';
      else if (waiting.length) f.innerHTML = '<span class="muted small">Priskirti:</span> ' + waiting.map(function (p) { return '<button type="button" class="btn tr-assign" data-assign-id="' + esc(p.id) + '" data-area="' + aid + '">📱 ' + esc(p.name) + '</button>'; }).join(' ');
      else f.innerHTML = '<span class="muted small">Nuskenuokite QR kodą telefonu - jo pavadinimas atsiras čia.</span>';
    });
  }
  function load(body) {
    var opt = body ? { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': main.dataset.csrf }, body: JSON.stringify(body) }
      : { credentials: 'same-origin', cache: 'no-store' };
    return fetch(main.dataset.api, opt).then(function (r) { return r.json(); }).then(function (d) {
      if (!d.ok) { if (d.error) document.getElementById('tr-waiting-list').textContent = d.error; return; }
      render(d.phones);
    }).catch(function () {});
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]');
    if (b) {
      var o = b.textContent, done = function () { b.textContent = 'Nukopijuota ✓'; setTimeout(function () { b.textContent = o; }, 1500); };
      if (navigator.clipboard) navigator.clipboard.writeText(b.dataset.copy).then(done, function () { b.previousElementSibling.select(); });
      else { b.previousElementSibling.select(); document.execCommand('copy'); done(); }
      return;
    }
    if (e.target.id === 'tr-print') { window.print(); return; }
    var a = e.target.closest('[data-assign-id]');
    if (a) { load({ id: a.dataset.assignId, area: +a.dataset.area }); return; }
    var u = e.target.closest('[data-unassign]');
    if (u) { load({ id: u.dataset.unassign, area: 0 }); return; }
    var x = e.target.closest('[data-forget]');
    if (x) { load({ id: x.dataset.forget, forget: true }); }
  });
  load();
  setInterval(function () { if (!document.hidden) load(); }, 3000);
})();
</script>
</body>
</html>
