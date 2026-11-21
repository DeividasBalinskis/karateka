<?php
require __DIR__ . '/inc/isdestymas.php';

puslapis_pradzia('Naujienos', 'naujienos');
?>
<section class="page-head"><div class="wrap">
  <p class="eyebrow">Federacija</p>
  <h1>Naujienos</h1>
</div></section>
<?php if (ltkf_edit_mode()): ?>
<section class="wrap ed-ui ed-items" data-kind="news">
  <div class="ed-items-head"><b>Naujienos</b> <button type="button" class="ed-b ed-save" data-item-new="news">+ Nauja naujiena</button></div>
  <ul><?php foreach (turinys('naujienos') as $a): if (empty($a['db_id'])) continue; ?>
    <li data-item="<?= e(json_encode(['id' => $a['db_id'], 'kind' => 'news', 'title' => $a['pavadinimas'], 'date' => $a['data'], 'summary' => $a['santrauka'], 'body' => implode("\n\n", $a['tekstas'])], JSON_UNESCAPED_UNICODE)) ?>">
      <span><b><?= e($a['pavadinimas']) ?></b> <small><?= e(data_lt($a['data'])) ?></small></span>
      <span><button type="button" class="ed-b" data-item-edit>✏ Keisti</button> <button type="button" class="ed-b ed-danger" data-item-del>🗑</button></span></li>
  <?php endforeach; ?></ul>
</section>
<?php endif; ?>
<section class="wrap section">
  <div class="news-list news-grid"><?php foreach (turinys('naujienos') as $a): ?><?= naujienos_kortele($a) ?><?php endforeach; ?></div>
</section>
<?php puslapis_pabaiga();
