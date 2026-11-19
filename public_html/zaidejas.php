<?php
// Kito žaidėjo dojo (tik žiūrėti): herojus, aplinka, gyvūnėliai, apranga, savybės. Be asmens duomenų ir be susirašinėjimo.
require __DIR__ . '/app/bootstrap.php';
require_once APP_DIR . '/game_ui.php';

$a = require_login();
if (!is_active_account($a) || !game_allowed($a)) {
    redirect('paskyra.php');
}
$hero = game_hero_row((int) get('id'));
if (!$hero) {
    not_found();
}
page_start(game_public_name($hero) . ' · ' . t('Dojo'), ['noindex' => true, 'section' => 'zaidimas']);
?>
<div class="row" style="display:flex; gap:8px; align-items:center; margin-bottom:10px; flex-wrap:wrap;">
  <a class="g-btn sm" href="<?= url('arena.php') ?>">← <?= te('Arena') ?></a>
  <span class="muted small"><?= te('Žiūri kito žaidėjo dojo. Pakviesti į kovą gali arenoje.') ?></span>
</div>
<?= game_dojo_html($hero, [], true) ?>
<?php page_end();
