<?php
// Transliacija (v1.97): vienas puslapis treneriui ir sekretoriatui - kaip prijungti telefoną (kamera per vdo.ninja), OBS ir YouTube,
// peržiūra „kaip matys žiūrovai“ (telefono vaizdas + juosta) ir pavyzdinė kova. Veikia bet kada: vaizdui kovų nereikia,
// juosta be kovos rodo varžybų ir tatamio pavadinimą, prasidėjus kovai kovos skydelyje - vardus, balus ir laiką.
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
$key = tournament_cam_key($t);
$cam = varz_cam_id($key, (int) $area['id']);
$push = varz_cam_push_url($cam);
$view = varz_cam_view_url($cam);
$bar = abs_url('varzybos-gyvai.php?k=' . tournament_token($t, 'share') . '&juosta=1&tatami=' . (int) $area['id']);
$back = url('admin/varzybu-sistema.php?id=' . (int) $t['id']);
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
  <?php if (count($areas) > 1): ?>
  <nav class="tabs" aria-label="Tatamiai">
    <?php foreach ($areas as $a): ?>
      <a class="tr-tab<?= (int) $a['id'] === (int) $area['id'] ? ' on' : '' ?>" href="<?= url('admin/varzybu-transliacija.php?id=' . (int) $t['id'] . '&tatami=' . (int) $a['id']) ?>"><?= e($a['name']) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>
</header>

<main class="tr-main">
  <section class="panel tr-sec">
    <h2>Kaip atrodys transliacija · <span><?= e($area['name']) ?></span></h2>
    <p class="muted small">Telefono vaizdas ir juosta - taip, kaip matys žiūrovai. Juodas langas - telefonas dar neprijungtas.</p>
    <div class="tr-preview">
      <iframe class="tr-cam" src="<?= e($view) ?>" title="Telefono vaizdas" allow="autoplay; fullscreen" allowfullscreen></iframe>
      <iframe class="tr-bar" id="tr-bar" src="<?= e($bar . '&obs=1') ?>" title="Juosta" data-src="<?= e($bar . '&obs=1') ?>"></iframe>
    </div>
    <div class="tr-row">
      <button type="button" class="btn" id="tr-sample">🧪 Pavyzdinė kova</button>
      <a class="btn" href="<?= e($back) ?>">Į varžybas - atidaryti kovą</a>
    </div>
  </section>

  <section class="panel tr-sec tr-steps">
    <div class="tr-step">
      <h2>1. Telefonas (kamera)</h2>
      <div class="tr-qr-row">
        <div class="tr-qr" id="tr-qr" data-url="<?= e($push) ?>"></div>
        <ol>
          <li>Nuskenuokite kodą telefono kamera arba atsiųskite nuorodą į telefoną.</li>
          <li>Leiskite naudoti kamerą ir mikrofoną - įsijungs galinė kamera.</li>
          <li>Telefoną laikykite gulsčią, prijunkite kroviklį ir neužrakinkite ekrano - kitaip vaizdas dings.</li>
          <li>Po kelių sekundžių vaizdas atsiras peržiūroje viršuje.</li>
        </ol>
      </div>
      <div class="sp-row"><input class="sel sp-url" readonly value="<?= e($push) ?>" aria-label="Telefono nuoroda"><button type="button" class="btn" data-copy="<?= e($push) ?>">Kopijuoti</button></div>
      <p class="muted small">Nuoroda skirta tik šiam tatamiui. Neduokite jos kitiems - su ja galima siųsti vaizdą į šį tatamį.</p>
    </div>

    <div class="tr-step">
      <h2>2. Kompiuteris (OBS Studio)</h2>
      <ol>
        <li>Šaltiniai (Sources) → + → Naršyklė (Browser) → pavadinimas „Telefonas“ → įklijuokite šią nuorodą, plotis 1920, aukštis 1080, pažymėkite „Control audio via OBS“.</li>
      </ol>
      <div class="sp-row"><input class="sel sp-url" readonly value="<?= e($view) ?>" aria-label="Telefono vaizdas OBS programai"><button type="button" class="btn" data-copy="<?= e($view) ?>">Kopijuoti</button></div>
      <ol start="2">
        <li>Dar kartą + → Naršyklė (Browser) → „Juosta“ → šią nuorodą, 1920 × 1080.</li>
      </ol>
      <div class="sp-row"><input class="sel sp-url" readonly value="<?= e($bar) ?>" aria-label="Juosta OBS programai"><button type="button" class="btn" data-copy="<?= e($bar) ?>">Kopijuoti</button></div>
      <ol start="3">
        <li>Šaltinių sąraše „Juosta“ turi būti virš „Telefonas“. Jei vaizdas neužpildo ekrano: dešinysis pelės klavišas → Transform → Fit to screen.</li>
      </ol>
    </div>

    <div class="tr-step">
      <h2>3. YouTube</h2>
      <ol>
        <li>OBS → Settings → Stream → YouTube → Connect Account, tada „Start Streaming“.</li>
        <li>Pirmą kartą YouTube transliacijas įjungia iki 24 val. - padarykite tai dieną prieš varžybas.</li>
        <li>Bandymui pasirinkite „Unlisted“ (nerodoma viešai).</li>
        <li>Transliacijos nuorodą žiūrovams įklijuokite Budo → „📡 Transliacija ir registracija“ → „🎥 YouTube transliacija“.</li>
      </ol>
    </div>
  </section>

  <section class="panel tr-sec">
    <h2>Kada kas veikia</h2>
    <ul class="tr-when">
      <li><b>📱 Telefono vaizdas</b> <span>- visada, kai telefone atidaryta kameros nuoroda. Varžybų ir kovų nereikia.</span></li>
      <li><b>🎬 Juosta</b> <span>- visada. Kol kovos nėra - varžybų ir tatamio pavadinimas; sekretoriatui atidarius kovą - vardai, balai ir laikas.</span></li>
      <li><b>🧑‍💼 Sekretoriatas</b> <span>- atidaręs kovą spaudžia „📹 Vaizdas“: telefono vaizdas rodomas šalia balų, balus galima pridėti ir nuimti kaip visada.</span></li>
      <li><b>🧪 Bandymas</b> <span>- „Pavyzdinė kova“ šiame puslapyje arba test varžybos (Budo → „🧪 Test varžybos“) - jose galima atidaryti tikrą kovą.</span></li>
      <li><b>📶 Internetas</b> <span>- kiekvienas atidarytas vaizdas (OBS, sekretoriatas, šis puslapis) naudoja telefono internetą, todėl geriau ne daugiau 3 vienu metu.</span></li>
    </ul>
  </section>
</main>
<script>
(function () {
  'use strict';
  // QR kodas telefonui (biblioteka iš cdnjs; jei neįsikėlė - lieka nuoroda su „Kopijuoti“)
  window.addEventListener('load', function () {
    var q = document.getElementById('tr-qr');
    if (q && window.QRCode) { try { new QRCode(q, { text: q.dataset.url, width: 180, height: 180, correctLevel: QRCode.CorrectLevel.M }); } catch (e) { q.hidden = true; } }
    else if (q) q.hidden = true;
  });
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]');
    if (b) {
      var o = b.textContent, done = function () { b.textContent = 'Nukopijuota ✓'; setTimeout(function () { b.textContent = o; }, 1500); };
      if (navigator.clipboard) navigator.clipboard.writeText(b.dataset.copy).then(done, function () { b.previousElementSibling.select(); });
      else { b.previousElementSibling.select(); document.execCommand('copy'); done(); }
      return;
    }
    if (e.target.id === 'tr-sample') {
      var f = document.getElementById('tr-bar'), on = /pavyzdys=1/.test(f.src);
      f.src = f.dataset.src + (on ? '' : '&pavyzdys=1');
      e.target.textContent = on ? '🧪 Pavyzdinė kova' : '↺ Tikra juosta';
    }
  });
})();
</script>
</body>
</html>
