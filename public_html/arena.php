<?php
// Žaidimų arena: karate kova (ėjimais, kaip combats), „Greita kova“ (realiu laiku), kvietimai klubo žaidėjams, treniruotė su botu, reitingai.
// ?m=ID - vykstanti kova, ?greita=1 - greita kova su botu, ?h=ID - kuriuo herojumi žaidžiama (savo ar vaiko).
require __DIR__ . '/app/bootstrap.php';
require_once APP_DIR . '/game_ui.php';
require_once APP_DIR . '/game_fight.php';

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
$match = get('m') ? fight_match((int) get('m')) : null;
if ($match && $match['game'] === 'keturi') {
    redirect('keturi.php?m=' . (int) $match['id']);
}
// Kovoje - tuo herojumi, kuris joje dalyvauja
if ($match) {
    foreach ($mine as $h) {
        if (fight_side_of($match, (int) $h['id'])) {
            $hero = $h;
        }
    }
}
game_sync($hero);
$hero = game_hero_row((int) $hero['id']);
$view = $match ? 'match' : (get('greita') ? 'greita' : 'lobby');

// Reitingai (jėgos ir gudrumo), klubo žaidėjai kvietimams
$top = function (string $col) {
    return array_map(function ($h) use ($col) { $h = game_hero_decode($h); return ['id' => (int) $h['id'], 'name' => game_public_name($h), 'r' => (int) $h[$col], 'w' => (int) $h['wins'], 'l' => (int) $h['losses']]; },
        q_all("SELECT h.* FROM game_heroes h WHERE (h.wins + h.losses) > 0 ORDER BY h.$col DESC, h.wins DESC LIMIT 10"));
};
// Klubo žaidėjai (be savų herojų) - kviesti į kovą ar žiūrėti jų dojo
$clubHeroes = [];
if ($view === 'lobby') {
    game_ensure_heroes();
    $myIds = array_map(function ($h) { return (int) $h['id']; }, $mine);
    $clubHeroes = array_values(array_filter(game_club_heroes((int) $hero['id']), function ($h) use ($myIds) { return !in_array($h['id'], $myIds, true); }));
}
$cfg = [
    'api' => url('zaidimas.php'), 'csrf' => csrf_token(), 'catalog' => game_catalog_json(), 'hero' => game_hero_view($hero), 'view' => $view,
    'match' => $match ? (int) $match['id'] : null, 'heroes' => $view === 'lobby' ? $clubHeroes : [],
    'mine' => array_map(function ($h) { return ['id' => (int) $h['id'], 'name' => $h['name']]; }, $mine),
    'top' => $view === 'lobby' ? ['power' => $top('rating_power'), 'fair' => $top('rating_fair')] : null,
    'attacks' => array_map(function ($x) { return ['n' => $x[0], 'z' => $x[1], 'p' => $x[2], 'acc' => $x[3]]; }, FIGHT_ATTACKS),
    'defenses' => array_map(function ($x) { return ['n' => $x[0], 'z' => $x[1]]; }, FIGHT_DEFENSES),
    'zones' => array_map('t', FIGHT_ZONES), 'cards' => array_map(function ($c) { return [$c[0], t($c[1])]; }, FIGHT_CARDS), 'reactions' => FIGHT_REACTIONS,
    'turnSec' => FIGHT_TURN_SEC, 'urls' => game_urls() + ['view' => url('zaidejas.php?id=HID'), 'match' => url('arena.php?m=MID'), 'keturiMatch' => url('keturi.php?m=MID')],
];

page_start(t('Žaidimai'), ['noindex' => true, 'section' => 'zaidimas']);
?>
<?= game_assets() ?>
<div class="arena" id="arena" data-view="<?= e($view) ?>"><noscript><?= te('Žaidimui reikia JavaScript.') ?></noscript></div>
<script>window.ARENA=<?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e(asset_url('assets/game-arena.js')) ?>"></script>
<?php page_end();
