<?php
require __DIR__ . '/inc/isdestymas.php';

$tipas = isset($_GET['tipas'], RENGINIU_TIPAI[$_GET['tipas']]) ? $_GET['tipas'] : '';
$rodyti = ($_GET['rodyti'] ?? '') === 'praeje' ? 'praeje' : 'busimi';
$today = date('Y-m-d');

$visi = visi_renginiai();
$sistema = (bool) varzybos_renginiai();
$list = array_values(array_filter($visi, function ($r) use ($tipas, $rodyti, $today) {
    $praejo = ($r['iki'] ?: $r['nuo']) < $today;
    return (!$tipas || $r['tipas'] === $tipas) && ($rodyti === 'praeje' ? $praejo : !$praejo);
}));
if ($rodyti === 'praeje') {
    $list = array_reverse($list);
}
$pagalMenesi = [];
foreach ($list as $r) {
    $pagalMenesi[substr($r['nuo'], 0, 7)][] = $r;
}

puslapis_pradzia('Kalendorius', 'kalendorius');
?>
<section class="page-head"><div class="wrap">
  <p class="eyebrow">Renginiai</p>
  <h1>Kalendorius</h1>
  <p class="lead">Varžybos, seminarai, teisėjų mokymai ir egzaminai visiems federacijos klubams.</p>
</div></section>

<section class="wrap section">
  <div class="filters" role="navigation" aria-label="Filtrai">
    <div class="seg">
      <a href="?rodyti=busimi<?= $tipas ? '&tipas=' . e($tipas) : '' ?>"<?= $rodyti === 'busimi' ? ' aria-current="true"' : '' ?>>Būsimi</a>
      <a href="?rodyti=praeje<?= $tipas ? '&tipas=' . e($tipas) : '' ?>"<?= $rodyti === 'praeje' ? ' aria-current="true"' : '' ?>>Praėję</a>
    </div>
    <div class="chips-row">
      <a class="fchip" href="?rodyti=<?= e($rodyti) ?>"<?= !$tipas ? ' aria-current="true"' : '' ?>>Visi</a>
      <?php foreach (RENGINIU_TIPAI as $k => $label): ?>
        <a class="fchip tipas-<?= e($k) ?>" href="?rodyti=<?= e($rodyti) ?>&tipas=<?= e($k) ?>"<?= $tipas === $k ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (!$list): ?>
    <p class="empty">Šiuo metu tokių renginių nėra.</p>
  <?php endif; ?>
  <?php foreach ($pagalMenesi as $ym => $items): ?>
    <h2 class="month"><?= MENESIAI[(int) substr($ym, 5, 2) - 1] ?> <span><?= substr($ym, 0, 4) ?></span></h2>
    <div class="event-list"><?php foreach ($items as $r): ?><?= renginio_kortele($r) ?><?php endforeach; ?></div>
  <?php endforeach; ?>

  <p class="note"><?= $sistema
    ? 'Varžybos su žyme „Iš varžybų sistemos“ ateina automatiškai – jų nereikia įvesti antrą kartą.'
    : 'Kai prisijungs varžybų sistema, jos varžybos čia atsiras automatiškai. Dabar rodomi tik federacijos įvesti renginiai.' ?></p>
</section>
<?php puslapis_pabaiga();
