<?php
require __DIR__ . '/inc/isdestymas.php';

puslapis_pradzia('Dokumentai', 'dokumentai');
?>
<section class="page-head"><div class="wrap">
  <p class="eyebrow">Taisyklės ir tvarkos</p>
  <h1>Dokumentai</h1>
  <p class="lead">Įstatai, varžybų taisyklės, nuostatai ir egzaminų programa vienoje vietoje.</p>
</div></section>

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
