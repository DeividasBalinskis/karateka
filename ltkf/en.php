<?php
require __DIR__ . '/inc/isdestymas.php';

$busimi = array_values(array_filter(busimi_renginiai(), function ($r) { return $r['tipas'] === 'varzybos'; }));
puslapis_pradzia('Lithuanian Traditional Karate Federation', 'en', ['lang' => 'en', 'description' => 'Lithuanian Traditional Karate Federation: clubs, competition calendar, results and contacts.']);
?>
<section class="page-head"><div class="wrap">
  <p class="eyebrow">English</p>
  <h1>Lithuanian Traditional Karate Federation</h1>
  <p class="lead">We unite traditional karate clubs in Lithuania: national competitions, referee education and one kyu/dan grading syllabus.</p>
</div></section>
<section class="wrap section two-col">
  <div class="prose">
    <h2>International clubs and guests</h2>
    <p>Clubs from abroad are welcome at our open tournaments. Registration is handled in our competition system; categories follow age, gender and grade.</p>
    <h2>Upcoming competitions</h2>
    <?php if ($busimi): ?>
      <ul><?php foreach ($busimi as $r): ?><li><b><?= e($r['pavadinimas']) ?></b> – <?= e($r['nuo']) ?>, <?= e($r['vieta']) ?></li><?php endforeach; ?></ul>
    <?php else: ?><p>No competitions announced yet.</p><?php endif; ?>
  </div>
  <aside class="side-box">
    <h2>Contact</h2>
    <p class="big"><?= e(n('el_pastas')) ?></p>
    <p class="muted">Affiliation: [WTKU / ITKF – to be confirmed]</p>
  </aside>
</section>
<?php puslapis_pabaiga();
