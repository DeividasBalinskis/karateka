<?php
// Pagrindinis puslapis iš blokų (app/home.php): grupės, renginiai, naujienos, apie klubą, kontaktai, „Išbandyti“, partneriai.
// Adminas viską keičia čia pat: index.php?redaguoti=1
require __DIR__ . '/app/bootstrap.php';
$edit = home_edit_mode();
$page = home_page();
?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="VšĮ Karate Ateitis — tradicinio karate-do klubas Vilniuje. Treniruotės vaikams, jaunimui ir suaugusiems: kainos, tvarkaraščiai, vietos ir registracija. Pirmos dvi treniruotės nemokamos.">
<title>Karateka — VšĮ Karate Ateitis</title>
<link rel="icon" type="image/png" sizes="32x32" href="favicon-32.png?v=2">
<link rel="icon" type="image/png" sizes="192x192" href="favicon-192.png?v=2">
<link rel="apple-touch-icon" href="apple-touch-icon.png?v=2">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#811517">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('assets/header.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('assets/widgets.css')) ?>">
<script>document.documentElement.classList.add('js');</script>
<link rel="stylesheet" href="<?= e(asset_url('assets/home.css')) ?>">
<?php if ($edit): ?><link rel="stylesheet" href="<?= e(asset_url('assets/home-edit.css')) ?>"><?php endif; ?>
</head>
<body class="<?= $edit ? 'home-edit' : '' ?>">

<?php site_header(true); ?>

<?php if ($edit): ?>
<div class="hb-toolbar" id="hbToolbar">
  <strong>✎ Redaguojate pagrindinį puslapį</strong>
  <span class="small">Spauskite ant teksto ir rašykite. Blokus tempkite ⠿.</span>
  <span class="hb-toolbar-btns">
    <button type="button" class="btn btn-primary btn-sm" id="hbSave">Išsaugoti</button>
    <a class="btn btn-ghost btn-sm" href="<?= url('index.php') ?>" id="hbExit">Baigti</a>
    <button type="button" class="btn btn-ghost btn-sm" id="hbUndo" title="Grąžinti prieš paskutinį išsaugojimą buvusį puslapį">↶ Ankstesnė versija</button>
  </span>
  <span class="hb-status" id="hbStatus"></span>
</div>
<?php endif; ?>

<main id="homeBlocks">
<?php
$visible = array_values(array_filter($page, function ($b) use ($edit) { return $edit || !$b['hidden']; }));
// Pirmas ekranas: grupės + renginiai užpildo visą ekraną, kad apačioje nesimatytų kitos dalies kraštelio
$first = !$edit && ($visible[0]['type'] ?? '') === 'groups';
$firstEnds = $first && ($visible[1]['type'] ?? '') === 'events' ? 1 : 0;
foreach ($visible as $i => $b) {
    if ($first && $i === 0) {
        echo '<div class="first-screen">';
    }
    echo home_block_wrap($b, home_render_block($b));
    if ($first && $i === $firstEnds) {
        echo '</div>';
    }
}
?>
</main>

<?php if ($edit):
    // Nauji blokai: šablonai (vieno egzemplioriaus blokai - tik jei jų puslapyje nėra)
    $present = array_column($page, 'type'); ?>
  <div class="hb-add-menu" id="hbAddMenu" hidden>
    <div class="hb-add-title">Kokį bloką pridėti?</div>
    <?php foreach (HOME_BLOCK_TYPES as $type => [$label, $multi]): if (!$multi && in_array($type, $present, true)) { continue; } ?>
      <button type="button" data-add-block="<?= $type ?>"<?= $multi ? '' : ' data-single="1"' ?>><?= e($label) ?></button>
      <template data-block-tpl="<?= $type ?>"><?= home_block_wrap(['id' => '', 'type' => $type, 'menu' => '', 'hidden' => false, 'd' => home_block_defaults($type)],
          home_render_block(['id' => '', 'type' => $type, 'menu' => '', 'hidden' => false, 'd' => home_block_defaults($type)])) ?></template>
    <?php endforeach; ?>
    <button type="button" class="hb-add-cancel">Atšaukti</button>
  </div>
  <script>window.HOME_API = <?= json_encode(['url' => url('admin/puslapis.php'), 'csrf' => csrf_token(), 'fragment' => url('admin/tvarkarastis.php?fragment=')]) ?>;</script>
  <script src="<?= e(asset_url('assets/vendor/sortable.min.js')) ?>"></script>
  <script src="<?= e(asset_url('assets/home-editor.js')) ?>"></script>
<?php elseif (is_admin()): ?>
  <a class="hb-fab" href="<?= url('index.php?redaguoti=1') ?>">✎ Redaguoti puslapį</a>
<?php endif; ?>

<footer>
  <div class="wrap">
    <div class="footer-bottom">
      <span>© <?= date('Y') ?> VšĮ Karate Ateitis. Visos teisės saugomos. · <a href="privatumas.php" style="color:inherit; text-decoration:underline;">Privatumo politika</a></span>
      <span>Nuo balto iki juodo diržo.</span>
    </div>
  </div>
</footer>

<script src="<?= e(asset_url('assets/home.js')) ?>"></script>
<?= page_widgets() ?>
<script src="<?= e(asset_url('assets/header.js')) ?>"></script>

</body>
</html>
