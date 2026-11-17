<?php
// „Keturi iš eilės“ (4, ne 5) su gyvūnėlių kortomis: ?m=ID - žaidimas (su draugu ar botu). Be ID - pradžia: botas (3 lygiai), draugo kvietimas, kortų paaiškinimas.
require __DIR__ . '/app/bootstrap.php';
require_once APP_DIR . '/game_ui.php';
require_once APP_DIR . '/game_fight.php';
require_once APP_DIR . '/game_board.php';

$a = require_login();
if (!is_active_account($a) || !game_ready()) {
    redirect('paskyra.php');
}
$mine = game_my_heroes($a);
$hero = $mine[0];
foreach ($mine as $h) {
    if ((int) $h['id'] === (int) (post('h') ?: get('h'))) {
        $hero = $h;
    }
}
// Pradėti su botu
if (is_post() && post('bot') !== '') {
    csrf_check();
    redirect('keturi.php?m=' . board_start_bot($hero, post('bot')));
}
$match = get('m') ? fight_match((int) get('m')) : null;
if ($match && $match['game'] !== 'keturi') {
    redirect('arena.php?m=' . (int) $match['id']);
}
if (!$match) {
    page_start(t('Keturi iš eilės'), ['noindex' => true, 'section' => 'zaidimas']);
    $pet = $hero['pet_id'] ? q_one('SELECT species, name FROM game_pets WHERE id = ?', [(int) $hero['pet_id']]) : null;
    $petCard = $pet ? (BOARD_PET_CARD[GAME_PETS[$pet['species']][2]] ?? 'bomb') : 'bomb';
    echo game_assets();
    ?>
    <div class="k4-lobby">
      <div class="page-head">
        <h1 class="styled"><?= te('Keturi iš eilės') ?></h1>
        <p><?= te('Sudėk 4 savo žetonus iš eilės - gulsčiai, stačiai ar įstrižai. Gyvūnėlis duoda stebuklingą kortą, kurią gali panaudoti vieną kartą.') ?></p>
      </div>
      <form method="post" class="panel card k4-start">
        <?= csrf_field() ?>
        <input type="hidden" name="h" value="<?= (int) $hero['id'] ?>">
        <?php if (count($mine) > 1): ?>
          <div class="k4-who"><?php foreach ($mine as $h): ?><a class="btn btn-ghost<?= (int) $h['id'] === (int) $hero['id'] ? ' is-on' : '' ?>" href="?h=<?= (int) $h['id'] ?>"><?= e($h['name']) ?></a><?php endforeach; ?></div>
        <?php endif; ?>
        <h2><?= te('🤖 Žaisti su treniruočių partneriu') ?></h2>
        <div class="k4-bots">
          <button class="k4-bot" name="bot" value="easy" type="submit">🐼<b><?= te('Senpai Bambukas') ?></b><small><?= te('Lengvas') ?></small></button>
          <button class="k4-bot" name="bot" value="normal" type="submit">🐵<b><?= te('Sensei Makivara') ?></b><small><?= te('Vidutinis') ?></small></button>
          <button class="k4-bot" name="bot" value="hard" type="submit">🐲<b><?= te('Meistras Drakonas') ?></b><small><?= te('Sunkus') ?></small></button>
        </div>
        <p class="muted"><?= te('👫 Su draugu: arenoje prie klubo žaidėjo spausk 🔴.') ?> <a href="<?= url('arena.php?h=' . (int) $hero['id']) ?>#zaidejai"><?= te('Į areną') ?> →</a></p>
      </form>
      <div class="panel card">
        <h2><?= te('🐾 Gyvūnėlių kortos') ?></h2>
        <ul class="k4-cardlist">
          <?php foreach (BOARD_CARDS as $k => [$ic, $n, $d]): ?>
            <li class="<?= $k === $petCard ? 'is-mine' : '' ?>"><span><?= $ic ?></span><b><?= te($n) ?></b> <?= te($d) ?><?php if ($k === $petCard && $pet): ?> <em>· <?= te('tavo') ?> <?= e($pet['name']) ?></em><?php endif; ?></li>
          <?php endforeach; ?>
        </ul>
        <p class="muted small"><?= te('Kiekvienas gauna 2 kortas: vieną duoda aktyvus gyvūnėlis, kita - Siena arba Sprogimas.') ?></p>
      </div>
    </div>
    <?php
    page_end();
    exit;
}
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
page_start(t('Keturi iš eilės'), ['noindex' => true, 'section' => 'zaidimas']);
?>
<?= game_assets() ?>
<div class="k4" id="k4"><noscript><?= te('Žaidimui reikia JavaScript.') ?></noscript></div>
<script>window.K4=<?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e(asset_url('assets/game-keturi.js')) ?>"></script>
<?php page_end();
