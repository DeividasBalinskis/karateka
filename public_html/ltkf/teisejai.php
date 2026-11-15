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
  <?php $teisejai = karateka_teisejai(); ?>
  <?php if ($teisejai): ?>
    <div class="tbl-wrap"><table class="judges">
      <thead><tr><th>Vardas, pavardė</th><th>Klubas</th><th>Teisėjų testas</th></tr></thead>
      <tbody>
      <?php foreach ($teisejai as $tj): ?>
        <tr><td><?= e($tj['vardas']) ?></td><td><?= e($tj['klubas']) ?></td>
          <td><?= $tj['galioja_iki'] ? '<span class="chip chip-open">✓ galioja iki ' . e(data_lt($tj['galioja_iki'])) . '</span>' : '<span class="muted">nelaikytas arba nebegalioja</span>' ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <p class="note">Sąrašas atsinaujina pats: karateka.lt pažymėjus narį „Teisėju“, jis atsiranda čia; testas galioja 12 mėn. Kitų klubų teisėjai bus pridėti, kai klubai prisijungs prie sistemos.</p>
  <?php else: ?>
    <p class="empty">Teisėjų sąrašas dar tuščias.</p>
  <?php endif; ?>
</section>
<?php puslapis_pabaiga();
