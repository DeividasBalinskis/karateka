<?php
require __DIR__ . '/inc/isdestymas.php';

puslapis_pradzia('Dokumentai', 'dokumentai');
?>
<section class="page-head"><div class="wrap">
  <p class="eyebrow">Taisyklės ir tvarkos</p>
  <h1>Dokumentai</h1>
  <p class="lead">Įstatai, varžybų taisyklės, nuostatai ir egzaminų programa vienoje vietoje.</p>
</div></section>

<?php if (ltkf_edit_mode()): ?>
<section class="wrap ed-ui ed-items" data-kind="doc" data-groups="<?= e(json_encode(array_keys(turinys('dokumentai')), JSON_UNESCAPED_UNICODE)) ?>">
  <div class="ed-items-head"><b>Dokumentai</b> <button type="button" class="ed-b ed-save" data-item-new="doc">+ Naujas dokumentas</button></div>
  <ul><?php foreach (turinys('dokumentai') as $grupe => $docs): foreach ($docs as $d): if (empty($d['db_id'])) continue; ?>
    <li data-item="<?= e(json_encode(['id' => $d['db_id'], 'kind' => 'doc', 'title' => $d['pavadinimas'], 'grp' => $grupe, 'file' => $d['failas']], JSON_UNESCAPED_UNICODE)) ?>">
      <span><b><?= e($d['pavadinimas']) ?></b> <small><?= e($grupe) ?> · <?= $d['failas'] ? 'failas įkeltas' : 'be failo' ?></small></span>
      <span><button type="button" class="ed-b" data-item-edit>✏ Keisti</button> <button type="button" class="ed-b ed-danger" data-item-del>🗑</button></span></li>
  <?php endforeach; endforeach; ?></ul>
</section>
<?php endif; ?>
<section class="wrap section docs">
  <?php foreach (turinys('dokumentai') as $grupe => $docs): ?>
    <div class="doc-group">
      <h2><?= e($grupe) ?></h2>
      <ul class="doc-list">
        <?php foreach ($docs as $d): ?>
          <li>
            <span class="doc-icon" aria-hidden="true">PDF</span>
            <span><?= e($d['pavadinimas']) ?></span>
            <?php if ($d['failas']): ?><a class="btn btn-small" href="<?= e($d['failas']) ?>" target="_blank" rel="noopener">Atsisiųsti</a><?php else: ?><span class="chip">Bus įkelta</span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
</section>
<?php puslapis_pabaiga();
