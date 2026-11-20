<?php
// Redaktorių sukurtas puslapis (☰ Meniu / 📄 Naujas puslapis): pavadinimas ir blokai (tekstas, nuotraukos, failai…)
require __DIR__ . '/inc/isdestymas.php';

$slug = ltkf_slug();
$d = ltkf_page($slug);
if (empty($d['custom'])) {
    http_response_code(404);
    puslapis_pradzia('Puslapis nerastas');
    echo '<section class="page-head"><div class="wrap"><h1>Puslapis nerastas</h1><p class="lead">Galbūt jis ištrintas. <a href="index.php">Į pradžią</a></p></div></section>';
    puslapis_pabaiga();
    exit;
}
$lead = 'Trumpas aprašymas po pavadinimu (nebūtina)';
$hasLead = isset($d['texts'][$lead]) && trim((string) $d['texts'][$lead]) !== '';
puslapis_pradzia((string) $d['title']);
?>
<section class="page-head"><div class="wrap">
  <h1><?= e($d['title']) ?></h1>
  <?php if ($hasLead || ltkf_edit_mode()): ?><p class="lead"><?= e($lead) ?></p><?php endif; ?>
</div></section>
<?php puslapis_pabaiga();
