<?php
require __DIR__ . '/inc/isdestymas.php';

$id = (string) ($_GET['id'] ?? '');
$a = null;
foreach (turinys('naujienos') as $item) {
    if ($item['id'] === $id) {
        $a = $item;
    }
}
if (!$a) {
    http_response_code(404);
    puslapis_pradzia('Naujiena nerasta', 'naujienos');
    echo '<section class="wrap section"><h1>Naujiena nerasta</h1><p><a href="naujienos.php">← Visos naujienos</a></p></section>';
    puslapis_pabaiga();
    exit;
}
puslapis_pradzia($a['pavadinimas'], 'naujienos');
?>
<article class="wrap section article">
  <p class="eyebrow"><a href="naujienos.php">Naujienos</a> · <?= e(data_lt($a['data'])) ?></p>
  <h1><?= e($a['pavadinimas']) ?><?= pavyzdys($a) ?></h1>
  <?php foreach ($a['tekstas'] as $p): ?><p><?= e($p) ?></p><?php endforeach; ?>
  <p><a href="naujienos.php">← Visos naujienos</a></p>
</article>
<?php puslapis_pabaiga();
