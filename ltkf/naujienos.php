<?php
require __DIR__ . '/inc/isdestymas.php';

puslapis_pradzia('Naujienos', 'naujienos');
?>
<section class="page-head"><div class="wrap">
  <p class="eyebrow">Federacija</p>
  <h1>Naujienos</h1>
</div></section>
<section class="wrap section">
  <div class="news-list news-grid"><?php foreach (turinys('naujienos') as $a): ?><?= naujienos_kortele($a) ?><?php endforeach; ?></div>
</section>
<?php puslapis_pabaiga();
