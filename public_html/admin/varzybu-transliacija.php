<?php
// Transliacija (v1.97-v1.98): vienas puslapis treneriui ir sekretoriatui.
//  - 📱 Telefonai: vienas klubo QR kodas visiems telefonams (varzybos-kamera.php); prisijungę telefonai matomi sąraše su vaizdu,
//    kiekvienas priskiriamas tatamiui (stream_phones, db/059) - tada telefonas siunčia to tatamio srautu, jį mato sekretoriatas, peržiūra ir OBS.
//  - Peržiūra „ką matys žiūrovai“ (telefono vaizdas + juosta) ir bandomoji kova (± abiem pusėms).
//  - YouTube ir OBS: viena nuoroda tatamiui (vaizdas + juosta kartu) arba „Visi tatamiai“ vienoje transliacijoje; keli tatamiai; patikra; „jei neveikia“.
// Nuorodos ir QR nuolatiniai (klubo raktai, varz_stream_key) - nustatoma vieną kartą. Veikia bet kada - varžybų ir kovų nereikia.
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
// Slaptos nuorodos pateko ne tiems žmonėms - naujos (tik pagrindinis adminas; senas QR ir OBS nuorodos nebeveiks)
if (is_post() && post('action') === 'renew' && is_admin() && !$demo) {
    csrf_check();
    varz_stream_key('cam', true);
    varz_stream_key('bar', true);
    flash('ok', 'Sukurtos naujos nuorodos. Telefonais nuskenuokite naują QR kodą, OBS įklijuokite naujas nuorodas.');
    redirect('admin/varzybu-transliacija.php?id=' . (int) $t['id'] . '&tatami=' . (int) get('tatami'));
}
$areas = array_values(array_filter((array) (tournament_state($t)['areas'] ?? []), function ($a) { return isset($a['id']); }));
if (!$areas) {
    $areas = [['id' => 1, 'name' => '1 TATAMIS']];
}
$area = $areas[0];
foreach ($areas as $a) {
    if ((int) $a['id'] === (int) get('tatami')) {
        $area = $a;
    }
}
$camKey = varz_stream_key('cam');
$barKey = varz_stream_key('bar');

// Telefonai: sąrašas ir priskyrimas tatamiui (JSON; vienas telefonas - vienam tatamiui)
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
                       WHERE seen_at > NOW() - INTERVAL 1 DAY ORDER BY area_id IS NULL, area_id, seen_at DESC LIMIT 20');
    } catch (PDOException $ex) {
        exit(json_encode(['ok' => false, 'error' => 'Duomenų bazė dar atnaujinama - perkraukite puslapį po minutės.'], JSON_UNESCAPED_UNICODE));
    }
    exit(json_encode(['ok' => true, 'phones' => array_map(function ($p) use ($camKey) {
        $aid = $p['area_id'] !== null ? (int) $p['area_id'] : 0;
        return ['id' => $p['id'], 'name' => $p['name'] !== '' ? $p['name'] : 'Telefonas', 'area' => $aid, 'online' => (int) $p['ago'] <= 30, 'ago' => (int) $p['ago'],
                'view' => varz_cam_view_url($aid ? varz_cam_id($camKey, $aid) : varz_cam_phone_id($camKey, (string) $p['id'])) . '&noaudio'];
    }, $list)], JSON_UNESCAPED_UNICODE));
}

$join = abs_url('varzybos-kamera.php?c=' . $camKey);
$view = varz_cam_view_url(varz_cam_id($camKey, (int) $area['id']));
$barOnly = abs_url('varzybos-gyvai.php?b=' . $barKey . '&juosta=1&tatami=' . (int) $area['id']);
$obsOne = $barOnly . '&vaizdas=1';
$obsAll = abs_url('varzybos-gyvai.php?b=' . $barKey . '&visi=1');
$share = tournament_token($t, 'share');
$testApi = url('varzybos-gyvai.php?k=' . $share . '&bandymas=1');
$jsonApi = url('varzybos-gyvai.php?k=' . $share . '&json=1');
$canTest = is_staff() || varz_sekr((int) $t['id']) !== null;
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

<main class="tr-main">
  <?php foreach (take_flashes() as $f): ?>
    <div class="vz-flash vz-flash-<?= e($f['type']) ?>" role="status"><span><?= e($f['message']) ?></span></div>
  <?php endforeach; ?>

  <section class="panel tr-sec">
    <h2>📱 Telefonai - kameros</h2>
    <div class="tr-ph-grid">
      <div class="tr-ph-join">
        <div class="tr-qr-row">
          <div class="tr-qr" id="tr-qr" data-url="<?= e($join) ?>"></div>
          <ol>
            <li>Telefonu nuskenuokite QR kodą → „📹 Įjungti kamerą“ → leiskite naudoti kamerą.</li>
            <li>Telefonas atsiras sąraše - pasirinkite, kuriam tatamiui jis filmuoja.</li>
            <li>Telefoną pastatykite gulsčią prie tatamio ir prijunkite kroviklį.</li>
          </ol>
        </div>
        <div class="sp-row"><input class="sel sp-url" readonly value="<?= e($join) ?>" aria-label="Telefono prisijungimo nuoroda"><button type="button" class="btn" data-copy="<?= e($join) ?>">Kopijuoti</button>
          <button type="button" class="btn" id="tr-print">🖨 Spausdinti QR</button></div>
        <p class="muted small">Vienas QR kodas visiems telefonams ir visoms varžyboms - galima atsispausdinti. Neduokite jo kitiems: su juo galima prisijungti kamera.</p>
      </div>
      <div class="tr-ph-list" id="tr-ph-list" data-api="<?= e($phonesApi) ?>" data-csrf="<?= e(csrf_token()) ?>"
           data-areas="<?= e(json_encode(array_map(function ($a) { return ['id' => (int) $a['id'], 'name' => (string) $a['name']]; }, $areas), JSON_UNESCAPED_UNICODE)) ?>">
        <p class="muted" id="tr-ph-empty">Telefonų dar nėra - nuskenuokite QR kodą telefonu.</p>
      </div>
    </div>
  </section>

  <section class="panel tr-sec">
    <h2>Ką matys žiūrovai · <span><?= e($area['name']) ?></span></h2>
    <?php if (count($areas) > 1): ?>
    <nav class="tr-tabs" aria-label="Tatamiai">
      <?php foreach ($areas as $a): ?>
        <a class="tr-tab<?= (int) $a['id'] === (int) $area['id'] ? ' on' : '' ?>" href="<?= url('admin/varzybu-transliacija.php?id=' . (int) $t['id'] . '&tatami=' . (int) $a['id']) ?>"><?= e($a['name']) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>
    <p class="muted small">Tatamio telefono vaizdas ir juosta - taip, kaip transliacijoje. Juodas langas - šiam tatamiui dar nepriskirtas telefonas.</p>
    <div class="tr-preview">
      <iframe class="tr-cam" src="<?= e($view . '&noaudio') ?>" title="Telefono vaizdas" allow="autoplay; fullscreen" allowfullscreen></iframe>
      <iframe class="tr-bar" id="tr-bar" src="<?= e($barOnly . '&obs=1') ?>" title="Juosta" data-src="<?= e($barOnly . '&obs=1') ?>"></iframe>
    </div>
    <div class="tr-row">
      <?php if ($canTest): ?>
      <span class="tr-test" id="tr-test" data-api="<?= e($testApi) ?>" data-json="<?= e($jsonApi) ?>" data-area="<?= (int) $area['id'] ?>" data-csrf="<?= e(csrf_token()) ?>">
        <b>🧪 Bandomoji kova:</b>
        <span class="tr-side aka">AKA <button type="button" class="btn" data-d="a|-1">−</button><button type="button" class="btn" data-d="a|1">+</button></span>
        <span class="tr-side">SHIRO <button type="button" class="btn" data-d="b|-1">−</button><button type="button" class="btn" data-d="b|1">+</button></span>
        <button type="button" class="btn" data-end="1">Baigti</button>
      </span>
      <?php endif; ?>
      <a class="btn" href="<?= e($back) ?>">Į varžybas - atidaryti kovą</a>
    </div>
    <p class="muted small" id="tr-test-msg">Bandomąją kovą mato ir OBS. Kai vyksta tikra kova, balai rašomi kovos skydelyje.</p>
  </section>

  <section class="panel tr-sec">
    <h2>Kur matosi vaizdas</h2>
    <div class="tr-ways">
      <div class="tr-way">
        <h3>📱 Tik telefonai</h3>
        <p class="tr-way-tag">Be kompiuterio programų · 1 min.</p>
        <p>Nuskenavus QR ir priskyrus tatamį, vaizdą mato sekretoriatas (kovos skydelyje „📹 Vaizdas“) ir šis puslapis. Salei, sekretoriatui ir treneriams.</p>
        <p class="muted small">Vienas telefonas vaizdą siunčia tiesiogiai - vienu metu gali žiūrėti iki 3. Tėvams namuose reikia YouTube.</p>
      </div>
      <div class="tr-way">
        <h3>▶ YouTube - visi tatamiai vienoje transliacijoje</h3>
        <p class="tr-way-tag">Telefonai + 1 kompiuteris su OBS · rekomenduojama</p>
        <p>OBS gauna vieną nuorodą „Visi tatamiai“: YouTube rodo visus tatamius vienu metu (2 × 2), kiekvienas su savo juosta ir balais. Užtenka vieno kompiuterio ir vienos YouTube transliacijos.</p>
        <p class="muted small">Žiūrovai mato visus tatamius viename vaizde.</p>
      </div>
      <div class="tr-way">
        <h3>▶ YouTube - atskirai kiekvienas tatamis</h3>
        <p class="tr-way-tag">Kiekvienam tatamiui - kompiuteris su OBS</p>
        <p>Kiekvienam tatamiui - atskira YouTube transliacija su jo nuoroda (vaizdas + juosta). YouTube nuorodas įklijuokite Budo → Tvarkaraštis → po tatamiu „YouTube Live nuoroda“: žiūrovų puslapyje kiekvienas tatamis su savo vaizdu.</p>
        <p class="muted small">4 tatamiai = 4 kompiuteriai su OBS ir 4 transliacijos. Internetas: apie 5 Mbps išsiuntimo kiekvienai.</p>
      </div>
    </div>
  </section>

  <section class="panel tr-sec tr-steps">
    <div class="tr-step">
      <h2>OBS kompiuteryje (vieną kartą)</h2>
      <ol>
        <li>Atsisiųskite OBS Studio: obsproject.com → Windows arba macOS → įdiekite (nemokama).</li>
        <li>Pirmą kartą atidarius - vedlys: „Optimize for streaming“ → 1920 × 1080, 30 FPS → Service „YouTube - RTMPS“ → „Connect Account“ (klubo YouTube paskyra) → „Apply Settings“.</li>
        <li>Šaltiniai (Sources) → + → Naršyklė (Browser) → įklijuokite vieną nuorodą, plotis 1920, aukštis 1080, pažymėkite „Control audio via OBS“.</li>
      </ol>
      <p class="tr-link-lbl">Visi tatamiai (2 × 2) - viena transliacija:</p>
      <div class="sp-row"><input class="sel sp-url" readonly value="<?= e($obsAll) ?>" aria-label="Visi tatamiai OBS programai"><button type="button" class="btn" data-copy="<?= e($obsAll) ?>">Kopijuoti</button></div>
      <p class="tr-link-lbl">Tik <?= e($area['name']) ?> (vaizdas + juosta):</p>
      <div class="sp-row"><input class="sel sp-url" readonly value="<?= e($obsOne) ?>" aria-label="Šis tatamis OBS programai"><button type="button" class="btn" data-copy="<?= e($obsOne) ?>">Kopijuoti</button></div>
      <p class="muted small">Nuorodos nesikeičia: kitoms varžyboms OBS nieko keisti nereikia - rodomos tuo metu vykstančios varžybos.</p>
    </div>
    <div class="tr-step">
      <h2>YouTube transliacija</h2>
      <ol>
        <li>Pirmą kartą YouTube transliacijas įjungia iki 24 val. (YouTube Studio → „Create“ → „Go live“) - padarykite tai dieną prieš varžybas.</li>
        <li>OBS → „Manage Broadcast“: pavadinimas ir matomumas („Unlisted“ - bandymui, „Public“ - varžyboms) → „Create broadcast and start streaming“.</li>
        <li>YouTube vaizdas vėluoja apie 10-20 s - tai normalu.</li>
        <li>Transliacijos nuorodą žiūrovams įklijuokite Budo → „📡 Transliacija ir registracija“ → „🎥 YouTube transliacija“.</li>
      </ol>
    </div>
    <div class="tr-step">
      <h2>Kitos nuorodos</h2>
      <p class="tr-link-lbl">Tik juosta (jei OBS'e naudojate savo kamerą):</p>
      <div class="sp-row"><input class="sel sp-url" readonly value="<?= e($barOnly) ?>" aria-label="Juosta OBS programai"><button type="button" class="btn" data-copy="<?= e($barOnly) ?>">Kopijuoti</button></div>
      <p class="tr-link-lbl">Tik <?= e($area['name']) ?> telefono vaizdas:</p>
      <div class="sp-row"><input class="sel sp-url" readonly value="<?= e($view) ?>" aria-label="Telefono vaizdas OBS programai"><button type="button" class="btn" data-copy="<?= e($view) ?>">Kopijuoti</button></div>
    </div>
  </section>

  <section class="panel tr-sec tr-two">
    <div>
      <h2>✅ Prieš varžybas patikrinkite (5 min.)</h2>
      <ol class="tr-list">
        <li>Telefonai nuskenavo QR ir priskirti tatamiams - sąraše matosi jų vaizdas.</li>
        <li>„🧪 Bandomoji kova“: ± abiem pusėms - balai keičiasi peržiūroje ir OBS.</li>
        <li>Kovos skydelyje „📹 Vaizdas“ - sekretoriatas mato vaizdą.</li>
        <li>YouTube „Unlisted“ transliacija - pažiūrėkite ją savo telefone.</li>
      </ol>
    </div>
    <div>
      <h2>🛠 Jei neveikia</h2>
      <ul class="tr-when">
        <li><b>Juodas vaizdas</b> <span>- telefone užsirakino ekranas arba užsidarė puslapis. Vėl nuskenuokite QR - telefonas lieka priskirtas tam pačiam tatamiui.</span></li>
        <li><b>Vaizdas stringa</b> <span>- per daug atidarytų vaizdų arba silpnas internetas. Uždarykite nereikalingus (šį puslapį, „📹 Vaizdas“), telefoną junkite prie Wi-Fi.</span></li>
        <li><b>Telefono nėra sąraše</b> <span>- telefone paspauskite „📹 Įjungti kamerą“ ir palaukite 10 s.</span></li>
        <li><b>OBS nesimato vaizdo ar juostos</b> <span>- dešinysis pelės klavišas ant šaltinio → „Refresh“.</span></li>
        <li><b>Juosta rodo kitas varžybas</b> <span>- atidarykite kovą reikiamose varžybose: juosta pereina prie tų, kuriose paskutinė kova.</span></li>
      </ul>
    </div>
  </section>

  <section class="panel tr-sec">
    <h2>Kada kas veikia</h2>
    <ul class="tr-when">
      <li><b>📱 Telefono vaizdas</b> <span>- visada, kai telefone atidarytas kameros puslapis ir telefonas priskirtas tatamiui. Varžybų ir kovų nereikia.</span></li>
      <li><b>🎬 Juosta</b> <span>- visada. Kol kovos nėra - varžybų ir tatamio pavadinimas; sekretoriatui atidarius kovą - vardai, balai ir laikas.</span></li>
      <li><b>🧑‍💼 Sekretoriatas</b> <span>- atidaręs kovą spaudžia „📹 Vaizdas“: telefono vaizdas rodomas šalia balų, balus galima pridėti ir nuimti kaip visada.</span></li>
      <li><b>🧪 Bandymas</b> <span>- „Bandomoji kova“ šiame puslapyje (± abiem pusėms, mato ir OBS) arba test varžybos (Budo → „🎲 Sukurti test varžybas“) - jose galima atidaryti tikrą kovą.</span></li>
      <li><b>📶 Internetas</b> <span>- kiekvienas atidarytas vaizdas (OBS, sekretoriatas, šis puslapis) naudoja telefono internetą, todėl geriau ne daugiau 3 vienu metu.</span></li>
    </ul>
    <?php if (is_admin() && !$demo): ?>
    <form method="post" class="tr-renew" onsubmit="return confirm('Sukurti naujas nuorodas? Senas QR kodas ir OBS nuorodos nebeveiks.')">
      <?= csrf_field() ?><input type="hidden" name="action" value="renew">
      <button type="submit" class="btn btn-xs">↻ Naujos slaptos nuorodos</button>
      <span class="muted small">Jei QR kodas ar OBS nuoroda pateko ne tiems žmonėms. Senos nebeveiks.</span>
    </form>
    <?php endif; ?>
  </section>

  <!-- Spausdinimui: telefonų QR kodas (rodoma tik spausdinant) -->
  <section class="tr-print" aria-hidden="true">
    <div class="tr-pq">
      <b>📱 Budo kamera</b>
      <div class="tr-pq-code" data-url="<?= e($join) ?>"></div>
      <p>Nuskenuokite telefonu → „📹 Įjungti kamerą“ → leiskite kamerą. Kompiuteryje priskirkite telefoną tatamiui.</p>
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
    if (q) qr(q, 180);
    Array.prototype.forEach.call(document.querySelectorAll('.tr-pq-code'), function (el) { qr(el, 300); });
  });

  // 📱 Telefonai: sąrašas kas 3 s; kortelės nekuriamos iš naujo, kad vaizdas nepersikrautų
  var list = document.getElementById('tr-ph-list'), AREAS = [];
  try { AREAS = JSON.parse(list.dataset.areas || '[]'); } catch (e) {}
  function ago(s) { return s < 60 ? s + ' s' : s < 3600 ? Math.round(s / 60) + ' min.' : Math.round(s / 3600) + ' val.'; }
  function card(p) {
    var c = list.querySelector('[data-ph="' + p.id + '"]');
    if (!c) {
      c = document.createElement('div'); c.className = 'tr-ph'; c.dataset.ph = p.id;
      c.innerHTML = '<div class="tr-ph-vid"><iframe title="Telefono vaizdas" allow="autoplay"></iframe><span class="tr-ph-off">Neprisijungęs</span></div>' +
        '<div class="tr-ph-info"><b class="tr-ph-name"></b><span class="tr-ph-on"></span>' +
        '<label class="tr-ph-area">Tatamis <select data-assign="' + esc(p.id) + '"><option value="0">— nepriskirtas —</option>' +
        AREAS.map(function (a) { return '<option value="' + a.id + '">' + esc(a.name) + '</option>'; }).join('') + '</select></label>' +
        '<button type="button" class="btn btn-xs" data-forget="' + esc(p.id) + '">Pamiršti</button></div>';
      list.appendChild(c);
    }
    c.querySelector('.tr-ph-name').textContent = p.name;
    c.querySelector('.tr-ph-on').textContent = p.online ? '🟢 prisijungęs' : '⚪ prieš ' + ago(p.ago);
    c.classList.toggle('off', !p.online);
    var sel = c.querySelector('select');
    if (document.activeElement !== sel) sel.value = String(p.area || 0);
    var fr = c.querySelector('iframe'), src = p.online ? p.view : '';
    if ((fr.getAttribute('src') || '') !== src) { if (src) fr.setAttribute('src', src); else fr.removeAttribute('src'); }
  }
  function load(body) {
    var opt = body ? { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': list.dataset.csrf }, body: JSON.stringify(body) }
      : { credentials: 'same-origin', cache: 'no-store' };
    return fetch(list.dataset.api, opt).then(function (r) { return r.json(); }).then(function (d) {
      var empty = document.getElementById('tr-ph-empty');
      if (!d.ok) { if (d.error) empty.textContent = d.error; return; }
      var keep = {};
      d.phones.forEach(function (p) { keep[p.id] = true; card(p); });
      Array.prototype.forEach.call(list.querySelectorAll('[data-ph]'), function (c) { if (!keep[c.dataset.ph]) c.remove(); });
      empty.hidden = d.phones.length > 0;
    }).catch(function () {});
  }
  list.addEventListener('change', function (e) {
    var s = e.target.closest('[data-assign]'); if (!s) return;
    load({ id: s.dataset.assign, area: +s.value }).then(function () { var b = document.getElementById('tr-bar'); if (b) b.src = b.dataset.src; });
  });
  list.addEventListener('click', function (e) {
    var b = e.target.closest('[data-forget]'); if (!b) return;
    if (window.confirm('Pamiršti šį telefoną? Jei jis vėl atidarys kameros puslapį - atsiras sąraše iš naujo.')) load({ id: b.dataset.forget, forget: true });
  });
  load();
  setInterval(function () { if (!document.hidden) load(); }, 3000);

  var score = { sa: 0, sb: 0 }, tbox = document.getElementById('tr-test');
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]');
    if (b) {
      var o = b.textContent, done = function () { b.textContent = 'Nukopijuota ✓'; setTimeout(function () { b.textContent = o; }, 1500); };
      if (navigator.clipboard) navigator.clipboard.writeText(b.dataset.copy).then(done, function () { b.previousElementSibling.select(); });
      else { b.previousElementSibling.select(); document.execCommand('copy'); done(); }
      return;
    }
    if (e.target.id === 'tr-print') { window.print(); return; }
    // Bandomoji kova: ± abiem pusėms (serveryje - mato ir OBS, ir peržiūra viršuje)
    var tb = e.target.closest('#tr-test [data-d], #tr-test [data-end]');
    if (!tb || !tbox) return;
    var body = { area: +tbox.dataset.area };
    if (tb.dataset.end) body.end = true;
    else { var q = tb.dataset.d.split('|'), k = q[0] === 'a' ? 'sa' : 'sb'; score[k] = Math.max(0, score[k] + (+q[1])); body.sa = score.sa; body.sb = score.sb; }
    fetch(tbox.dataset.api, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': tbox.dataset.csrf }, body: JSON.stringify(body) })
      .then(function (r) { return r.json(); }).then(function (d) {
        if (!d.ok && d.error) { document.getElementById('tr-test-msg').textContent = d.error; return; }
        if (body.end) { score.sa = 0; score.sb = 0; }
      }).catch(function () {});
  });
  // Dabartiniai bandomosios kovos balai (jei ji jau vyksta)
  if (tbox) fetch(tbox.dataset.json, { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (d) {
    var l = d.live && d.live[tbox.dataset.area];
    if (l && l.data && l.data.test) { score.sa = +l.data.sa || 0; score.sb = +l.data.sb || 0; }
  }).catch(function () {});
})();
</script>
</body>
</html>
