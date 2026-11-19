<?php
// „Kata ritmas“: ritmo žaidimas - būgnai muša, krentančios technikos (tsuki, uke, geri, kiai) paspaudžiamos laiku. Rezultatai - savaitės top, S - dovanų dėžė.
require __DIR__ . '/app/bootstrap.php';
require_once APP_DIR . '/game_ui.php';

$a = require_login();
if (!is_active_account($a) || !game_allowed($a)) {
    redirect('paskyra.php');
}
$mine = game_my_heroes($a);
$hero = $mine[0];
foreach ($mine as $h) {
    if ((int) $h['id'] === (int) get('h')) {
        $hero = $h;
    }
}
$cfg = ['api' => url('zaidimas.php'), 'csrf' => csrf_token(), 'catalog' => game_catalog_json(), 'hero' => game_hero_view(game_hero_row((int) $hero['id'])), 'urls' => game_urls()];
page_start(t('Kata ritmas'), ['noindex' => true, 'section' => 'zaidimas']);
?>
<?= game_assets() ?>
<div class="rt" id="rt"><noscript><?= te('Žaidimui reikia JavaScript.') ?></noscript></div>
<script>window.RITMAS=<?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e(asset_url('assets/game-ritmas.js')) ?>"></script>
<?php page_end();
