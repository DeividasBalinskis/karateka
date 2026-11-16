<?php
// „Keturi iš eilės“ su gyvūnėlių kortomis: ?m=ID - žaidimas (su draugu ar botu). Be ID - į žaidimų areną (ten kvietimai ir botai).
require __DIR__ . '/app/bootstrap.php';
require_once APP_DIR . '/game_ui.php';
require_once APP_DIR . '/game_fight.php';
require_once APP_DIR . '/game_board.php';

$a = require_login();
if (!is_active_account($a) || !game_ready()) {
    redirect('paskyra.php');
}
$match = get('m') ? fight_match((int) get('m')) : null;
if (!$match || $match['game'] !== 'keturi') {
    redirect('arena.php');
}
$mine = game_my_heroes($a);
$hero = $mine[0];
foreach ($mine as $h) {
    if (fight_side_of($match, (int) $h['id'])) {
        $hero = $h;
    }
}
$cfg = [
    'api' => url('zaidimas.php'), 'csrf' => csrf_token(), 'catalog' => game_catalog_json(), 'hero' => ['id' => (int) $hero['id']], 'match' => (int) $match['id'],
    'cards' => array_map(function ($c) { return [$c[0], t($c[1]), t($c[2])]; }, BOARD_CARDS), 'reactions' => FIGHT_REACTIONS, 'w' => BOARD_W, 'h' => BOARD_H,
    'urls' => game_urls() + ['match' => url('keturi.php?m=MID')],
];
page_start(t('Keturi iš eilės'), ['noindex' => true, 'account' => 'zaidimai']);
?>
<?= game_assets() ?>
<div class="k4" id="k4"><noscript><?= te('Žaidimui reikia JavaScript.') ?></noscript></div>
<script>window.K4=<?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e(asset_url('assets/game-keturi.js')) ?>"></script>
<?php page_end();
