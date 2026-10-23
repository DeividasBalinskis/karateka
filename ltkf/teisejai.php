<?php
require __DIR__ . '/inc/isdestymas.php';

$f = turinys('federacija');
$seminarai = array_values(array_filter(busimi_renginiai(), function ($r) { return $r['tipas'] === 'teisejai'; }));
puslapis_pradzia('Teisėjai', 'teisejai');
?>
<section class="page-head"><div class="wrap">
  <p class="eyebrow">Teisėjavimas</p>
  <h1>Teisėjai</h1>
  <p class="lead">Geros varžybos prasideda nuo teisėjų. Federacija juos moko, tikrina ir kelia kvalifikaciją.</p>
</div></section>

<section class="wrap section">
  <h2>Kvalifikacijos lygiai</h2>
  <div class="levels">
    <?php foreach ($f['teiseju_lygiai'] as $i => $l): ?>
      <div class="level"><span class="level-n"><?= $i + 1 ?></span><b><?= e($l['lygis']) ?></b><p><?= e($l['kas']) ?></p></div>
    <?php endforeach; ?>
  </div>
  <p class="note">Lygiai – pavyzdys. Tikrą tvarką patvirtins teisėjų komisija.</p>

  <div class="two-col mt">
    <div>
      <h2>Artimiausi seminarai</h2>
      <?php if ($seminarai): ?>
        <div class="event-list"><?php foreach ($seminarai as $r): ?><?= renginio_kortele($r) ?><?php endforeach; ?></div>
      <?php else: ?><p class="empty">Šiuo metu seminarų nesuplanuota.</p><?php endif; ?>
    </div>
    <div class="side-box">
      <h2>Pasiruošk testui</h2>
      <p>Kvalifikacijos testas – 300 WTKU klausimų banke. Per testą parenkami atsitiktiniai klausimai, laikas ribotas.</p>
      <p>Treniruotės testą jau naudoja Karate Ateities teisėjai. Federacija gali jį atverti visų klubų teisėjams.</p>
      <span class="chip">Visiems klubams – vėliau</span>
    </div>
  </div>

  <h2 class="mt">Teisėjų sąrašas</h2>
  <p class="empty">[Teisėjų sąrašas su kvalifikacija ir klubu. Rodysime tik vardą, pavardę, lygį ir klubą.]</p>
</section>
<?php puslapis_pabaiga();
