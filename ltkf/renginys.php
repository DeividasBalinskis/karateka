<?php
require __DIR__ . '/inc/isdestymas.php';

$id = (string) ($_GET['id'] ?? '');
$r = null;
foreach (turinys('renginiai') as $item) {
    if ($item['id'] === $id) {
        $r = $item;
    }
}
if (!$r) {
    http_response_code(404);
    puslapis_pradzia('Renginys nerastas', 'kalendorius');
    echo '<section class="wrap section"><h1>Renginys nerastas</h1><p><a href="kalendorius.php">← Į kalendorių</a></p></section>';
    puslapis_pabaiga();
    exit;
}
$praejo = ($r['iki'] ?: $r['nuo']) < date('Y-m-d');
$vp = rtrim((string) (n('varzybos')['public_url'] ?? ''), '/');

puslapis_pradzia($r['pavadinimas'], 'kalendorius');
?>
<section class="page-head tipas-<?= e($r['tipas']) ?>"><div class="wrap">
  <p class="eyebrow"><a href="kalendorius.php">Kalendorius</a> · <?= e(RENGINIU_TIPAI[$r['tipas']] ?? 'Renginys') ?></p>
  <h1><?= e($r['pavadinimas']) ?><?= pavyzdys($r) ?></h1>
  <p class="lead"><?= e(datos_lt($r['nuo'], $r['iki'] ?? null)) ?> · <?= e($r['vieta']) ?></p>
</div></section>

<section class="wrap section two-col">
  <div class="prose">
    <p><?= e($r['aprasymas']) ?></p>
    <h2>Dokumentai</h2>
    <ul class="doc-list">
      <li><span>Nuostatai (PDF)</span><span class="chip">Bus įkelta</span></li>
      <?php if ($r['tipas'] === 'varzybos'): ?><li><span>Tvarkaraštis pagal tatamius</span><span class="chip">Paskelbs varžybų sistema</span></li><?php endif; ?>
    </ul>
  </div>
  <aside class="side-box">
    <?php if ($praejo && !empty($r['rezultatai'])): ?>
      <h2>Rezultatai</h2>
      <p>Renginys įvyko.</p>
      <a class="btn btn-primary" href="rezultatai.php?renginys=<?= e(urlencode($r['rezultatai'])) ?>">Žiūrėti rezultatus</a>
    <?php elseif (!$praejo && $r['tipas'] === 'varzybos'): ?>
      <h2>Registracija</h2>
      <p>Klubo treneris registruoja sportininkus varžybų sistemoje. Klubai, naudojantys karateka.lt, registruoja tiesiai iš narių sąrašo.</p>
      <?php if ($vp): ?><a class="btn btn-primary" href="<?= e($vp) ?>/" target="_blank" rel="noopener">Į varžybų sistemą ↗</a><?php endif; ?>
    <?php else: ?>
      <h2>Dalyvavimas</h2>
      <p>Registruoja klubo treneris. Klausimai – <?= e(n('el_pastas')) ?>.</p>
    <?php endif; ?>
  </aside>
</section>
<?php puslapis_pabaiga();
