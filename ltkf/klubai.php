<?php
require __DIR__ . '/inc/isdestymas.php';

$klubai = turinys('klubai');
usort($klubai, function ($a, $b) { return strcmp($a['pavadinimas'], $b['pavadinimas']); });
$miestai = array_values(array_unique(array_filter(array_map(function ($k) { return $k['miestas']; }, $klubai), function ($m) { return $m[0] !== '['; })));
sort($miestai);
$taskai = array_values(array_filter($klubai, function ($k) { return isset($k['lat'], $k['lng']); }));

puslapis_pradzia('Klubai', 'klubai', ['head' =>
    '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">'
    . '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin="" defer></script>']);
?>
<section class="page-head"><div class="wrap">
  <p class="eyebrow">Federacijos nariai</p>
  <h1>Klubai</h1>
  <p class="lead">Raskite tradicinio karatė klubą savo mieste. Visi klubai treniruoja pagal tą pačią egzaminų programą ir dalyvauja federacijos varžybose.</p>
</div></section>

<section class="wrap section">
  <?php if (n('demo')): ?><p class="note">Pavyzdys: sąrašas paimtas iš Budo Cup 2026 dalyvių. Kurie klubai yra LTKF nariai, jų miestus ir kontaktus patikslins Denis.</p><?php endif; ?>
  <div class="clubs-layout">
    <div>
      <label class="search"><span class="sr-only">Ieškoti klubo</span><input type="search" id="club-search" placeholder="Ieškoti pagal pavadinimą ar miestą"></label>
      <?php if ($miestai): ?>
      <div class="chips-row" id="city-filter">
        <button type="button" class="fchip" data-city="" aria-pressed="true">Visi</button>
        <?php foreach ($miestai as $m): ?><button type="button" class="fchip" data-city="<?= e($m) ?>" aria-pressed="false"><?= e($m) ?></button><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <ul class="club-list" id="club-list">
        <?php foreach ($klubai as $k): ?>
        <li class="club" data-name="<?= e(mb_strtolower($k['pavadinimas'] . ' ' . $k['miestas'])) ?>" data-city="<?= e($k['miestas']) ?>">
          <span class="club-initials" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($k['pavadinimas'], 0, 2))) ?></span>
          <span class="club-body">
            <b><?= e($k['pavadinimas']) ?></b>
            <span class="muted"><?= e($k['miestas']) ?> · Treneris: <?= e($k['treneris']) ?></span>
          </span>
          <?php if ($k['svetaine']): ?><a class="club-link" href="<?= e($k['svetaine']) ?>" target="_blank" rel="noopener">Svetainė ↗</a><?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <p class="empty" id="club-empty" hidden>Pagal paiešką klubų nerasta.</p>
    </div>
    <div class="map-wrap">
      <div id="club-map" class="club-map" data-points="<?= e(json_encode(array_map(function ($k) { return ['n' => $k['pavadinimas'], 'm' => $k['miestas'], 'lat' => $k['lat'], 'lng' => $k['lng']]; }, $taskai), JSON_UNESCAPED_UNICODE)) ?>" role="img" aria-label="Klubų žemėlapis"></div>
      <p class="muted small">Žemėlapyje – klubai su nurodytu adresu. Kiti atsiras, kai klubai įrašys adresus.</p>
    </div>
  </div>

  <div class="cta-band">
    <div><h2>Jūsų klubo nėra sąraše?</h2><p>Federacijos nariu gali tapti kiekvienas tradicinio karatė klubas Lietuvoje.</p></div>
    <a class="btn btn-primary" href="klubams.php">Kaip įstoti</a>
  </div>
</section>
<?php puslapis_pabaiga();
