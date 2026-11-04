<?php
require __DIR__ . '/inc/isdestymas.php';

// Pirmiausia - varžybos iš varžybų sistemos (karateka.lt „Taika“), tada archyvas iš turinio failo
$archyvas = karateka_rezultatai() + turinys('rezultatai');
$sel = (string) ($_GET['renginys'] ?? array_key_first($archyvas));
$rez = $archyvas[$sel] ?? null;

puslapis_pradzia('Rezultatai', 'rezultatai');
?>
<section class="page-head"><div class="wrap">
  <p class="eyebrow">Varžybos</p>
  <h1>Rezultatai</h1>
  <p class="lead">Vietos kiekvienoje kategorijoje ir klubų medalių lentelės.</p>
</div></section>

<section class="wrap section">
  <?php if (count($archyvas) > 1): ?>
  <form class="filters" method="get"><label>Renginys <select name="renginys" onchange="this.form.submit()">
    <?php foreach ($archyvas as $k => $a): ?><option value="<?= e($k) ?>"<?= $k === $sel ? ' selected' : '' ?>><?= e($a['pavadinimas']) ?> (<?= e(substr($a['data'], 0, 4)) ?>)</option><?php endforeach; ?>
  </select></label></form>
  <?php endif; ?>

  <?php if ($rez): ?>
    <div class="results-head">
      <h2><?= e($rez['pavadinimas']) ?><?= pavyzdys($rez) ?><?= !empty($rez['is_sistemos']) ? ' <span class="chip chip-sys">Iš varžybų sistemos</span>' : '' ?><?= !empty($rez['vyksta']) ? ' <span class="chip chip-live">Vyksta</span>' : '' ?></h2>
      <p class="muted"><?= e(data_lt($rez['data'])) ?><?= !empty($rez['nuoroda']) ? ' · <a href="' . e($rez['nuoroda']) . '" target="_blank" rel="noopener">Visi rezultatai ↗</a>' : '' ?></p>
    </div>
    <div class="results-grid">
      <div class="tbl-wrap">
        <table class="medals">
          <caption>Klubų medalių lentelė</caption>
          <thead><tr><th scope="col">#</th><th scope="col">Klubas</th><th scope="col" class="m-gold" title="Aukso">I</th><th scope="col" class="m-silver" title="Sidabro">II</th><th scope="col" class="m-bronze" title="Bronzos">III</th><th scope="col">Iš viso</th></tr></thead>
          <tbody>
          <?php foreach ($rez['klubai'] as $i => $k): ?>
            <tr><td><?= $i + 1 ?></td><td><b><?= e($k['klubas']) ?></b></td><td><?= (int) $k['a'] ?></td><td><?= (int) $k['s'] ?></td><td><?= (int) $k['b'] ?></td><td><?= (int) ($k['a'] + $k['s'] + $k['b']) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="cat-results">
        <?php foreach ($rez['kategorijos'] as $c): ?>
        <div class="cat-card">
          <h3><?= e($c['pavadinimas']) ?></h3>
          <ol class="podium">
            <?php foreach ($c['vietos'] as $i => $v): [$vardas, $klubas] = $v; $vieta = (int) ($v[2] ?? ($i < 2 ? $i + 1 : 3)); ?>
              <li class="p<?= $vieta ?>"><span class="place"><?= $vieta ?></span><span><b><?= e($vardas) ?></b><?= $klubas ? ' <span class="muted">' . e($klubas) . '</span>' : '' ?></span></li>
            <?php endforeach; ?>
          </ol>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <p class="note">Vaikų rezultatuose rodomas vardas ir pavardės pirmoji raidė. Ar rodyti pilną pavardę – federacijos sprendimas.</p>
  <?php endif; ?>
</section>
<?php puslapis_pabaiga();
