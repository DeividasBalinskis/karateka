<?php
require __DIR__ . '/inc/isdestymas.php';

$f = turinys('federacija');
puslapis_pradzia('Kontaktai', 'kontaktai');
?>
<section class="page-head"><div class="wrap">
  <p class="eyebrow">Susisiekite</p>
  <h1>Kontaktai</h1>
</div></section>
<section class="wrap section two-col">
  <div class="contact-cards">
    <div class="side-box"><h2>El. paštas</h2><p class="big"><?= e(n('el_pastas')) ?></p><p class="muted">Klubams, organizatoriams, žiniasklaidai.</p></div>
    <div class="side-box"><h2>Telefonas</h2><p class="big"><?= e(n('telefonas')) ?></p></div>
    <div class="side-box"><h2>Socialiniai tinklai</h2><p>[Facebook] · [Instagram] · [YouTube]</p></div>
  </div>
  <aside class="side-box">
    <h2>Rekvizitai</h2>
    <dl class="facts"><?php foreach ($f['rekvizitai'] as $k => $v): ?><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd><?php endforeach; ?></dl>
  </aside>
</section>
<?php puslapis_pabaiga();
