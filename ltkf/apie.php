<?php
require __DIR__ . '/inc/isdestymas.php';

$f = turinys('federacija');
puslapis_pradzia('Apie federaciją', 'apie');
?>
<section class="page-head"><div class="wrap">
  <p class="eyebrow">Federacija</p>
  <h1>Apie mus</h1>
  <p class="lead">Lietuvos tradicinio karatė federacija vienija klubus, kurie puoselėja tradicinį karatė: techniką, discipliną ir pagarbą.</p>
</div></section>

<section class="wrap section two-col">
  <div class="prose">
    <h2>Ką darome</h2>
    <ul>
      <li>Rengiame varžybas ir Lietuvos čempionatą: kata, kumite, fukugo, enbu.</li>
      <li>Ruošiame ir kvalifikuojame teisėjus.</li>
      <li>Prižiūrime vieną kyu ir dan egzaminų programą.</li>
      <li>Atrenkame sportininkus į tarptautines varžybas.</li>
      <li>Organizuojame seminarus ir stovyklas treneriams bei sportininkams.</li>
    </ul>
    <h2>Istorija</h2>
    <p>[Kada ir kas įkūrė federaciją, kiek klubų buvo pradžioje, svarbiausi pasiekimai. Tekstą parašys Denis.]</p>
    <h2>Valdyba</h2>
    <div class="board">
      <?php foreach ($f['valdyba'] as $v): ?>
        <div class="person"><span class="avatar" aria-hidden="true"></span><span><b><?= e($v['vardas']) ?></b><span class="muted"><?= e($v['pareigos']) ?></span></span></div>
      <?php endforeach; ?>
    </div>
  </div>
  <aside class="side-box">
    <h2>Rekvizitai</h2>
    <dl class="facts">
      <?php foreach ($f['rekvizitai'] as $k => $v): ?><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd><?php endforeach; ?>
    </dl>
    <a class="btn btn-ghost-dark" href="dokumentai.php">Įstatai ir dokumentai</a>
  </aside>
</section>
<?php puslapis_pabaiga();
