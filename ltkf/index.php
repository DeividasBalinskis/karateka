<?php
require __DIR__ . '/inc/isdestymas.php';

$busimi = busimi_renginiai();
$artimiausias = $busimi[0] ?? null;
$klubai = turinys('klubai');
$naujienos = array_slice(turinys('naujienos'), 0, 3);

puslapis_pradzia('', 'index');
?>
<section class="hero">
  <div class="wrap hero-grid">
    <div class="hero-text">
      <p class="eyebrow">Lietuvos tradicinio karatė federacija</p>
      <h1>Tradicinis karatė Lietuvoje – vienoje vietoje</h1>
      <p class="lead">Federacija vienija klubus, rengia varžybas ir seminarus, ruošia teisėjus ir rūpinasi vienoda egzaminų programa.</p>
      <div class="hero-actions">
        <a class="btn btn-primary" href="kalendorius.php">Varžybų kalendorius</a>
        <a class="btn btn-ghost" href="klubai.php">Rasti klubą</a>
      </div>
    </div>
    <?php if ($artimiausias): ?>
    <aside class="next-event" aria-label="Artimiausias renginys">
      <p class="eyebrow">Artimiausias renginys</p>
      <h2><?= e($artimiausias['pavadinimas']) ?><?= pavyzdys($artimiausias) ?></h2>
      <p class="next-meta"><?= e(datos_lt($artimiausias['nuo'], $artimiausias['iki'] ?? null)) ?><br><?= e($artimiausias['vieta']) ?></p>
      <?php $d = (int) floor((strtotime($artimiausias['nuo']) - strtotime(date('Y-m-d'))) / 86400); ?>
      <p class="countdown"><b><?= $d ?></b> <?= $d % 10 === 1 && $d % 100 !== 11 ? 'diena' : (($d % 10 === 0 || ($d % 100 >= 11 && $d % 100 <= 19)) ? 'dienų' : 'dienos') ?> iki pradžios</p>
      <a class="btn btn-light" href="<?= !empty($artimiausias['is_sistemos']) ? e($artimiausias['nuoroda']) : 'renginys.php?id=' . e(urlencode($artimiausias['id'])) ?>">Daugiau</a>
    </aside>
    <?php endif; ?>
  </div>
</section>

<section class="wrap quick">
  <a class="quick-card" href="kalendorius.php"><span class="q-num"><?= count($busimi) ?></span><b>Renginiai</b><span>Varžybos, seminarai, egzaminai</span></a>
  <a class="quick-card" href="klubai.php"><span class="q-num"><?= count($klubai) ?></span><b>Klubai</b><span>Sąrašas ir žemėlapis</span></a>
  <a class="quick-card" href="rezultatai.php"><span class="q-num">↗</span><b>Rezultatai</b><span>Vietos ir medalių lentelės</span></a>
  <a class="quick-card" href="dokumentai.php"><span class="q-num">§</span><b>Taisyklės</b><span>Kata, kumite, fukugo, enbu</span></a>
</section>

<section class="wrap section">
  <div class="section-head"><h2>Kalendorius</h2><a href="kalendorius.php">Visi renginiai →</a></div>
  <div class="event-list">
    <?php foreach (array_slice($busimi, 0, 4) as $r): ?><?= renginio_kortele($r) ?><?php endforeach; ?>
  </div>
</section>

<section class="wrap section two-col">
  <div>
    <div class="section-head"><h2>Naujienos</h2><a href="naujienos.php">Visos →</a></div>
    <div class="news-list"><?php foreach ($naujienos as $a): ?><?= naujienos_kortele($a) ?><?php endforeach; ?></div>
  </div>
  <div class="join-box">
    <p class="eyebrow">Klubams</p>
    <h2>Tapkite federacijos nariu</h2>
    <p>Jūsų sportininkai dalyvauja federacijos varžybose, treneriai – seminaruose, teisėjai gauna kvalifikaciją, o mokiniai laiko egzaminus pagal vieną programą.</p>
    <a class="btn btn-primary" href="klubams.php">Kaip įstoti</a>
  </div>
</section>
<?php puslapis_pabaiga();
