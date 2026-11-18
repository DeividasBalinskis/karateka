<?php
// 🧪 Nauja kova (bandymai): kaip combats.ru - viduryje personažas ir gyvūnėlis, aplink daiktai (ginklas, kairė ranka - skydas ar antras ginklas,
// šarvai, stebuklingi daiktai) ir/arba kolekcionuojamos pikselinės kortos. Rodyklėmis ◀ ▶ perjungiami variantai (išdėstymas ir mechanika),
// kad būtų galima išbandyti ir išsirinkti. Kova ėjimais: 5 zonos, skydas +1 gynybos zona, antras ginklas +1 ataka; ėjimas baigiasi,
// kai abu pasirinko arba baigėsi laikas. Viskas vyksta naršyklėje (kolekcija - šioje naršyklėje), tikri herojaus duomenys nekeičiami.
require __DIR__ . '/app/bootstrap.php';
require_once APP_DIR . '/game_ui.php';

$a = require_login();
if (!is_active_account($a) || !game_ready()) {
    redirect('paskyra.php');
}
$mine = game_my_heroes($a);
$hero = $mine[0];
foreach ($mine as $h) {
    if ((int) $h['id'] === (int) get('h')) {
        $hero = $h;
    }
}
$hero = game_hero_row((int) $hero['id']);
$cfg = ['catalog' => game_catalog_json(), 'hero' => game_hero_view($hero, false), 'urls' => game_urls(), 'key' => 'kova-lab-' . (int) $hero['id']];
page_start(t('Nauja kova (bandymai)'), ['noindex' => true, 'section' => 'zaidimas']);
echo game_assets();
?>
<link rel="stylesheet" href="<?= e(asset_url('assets/game-lab.css')) ?>">
<div class="lab" id="lab"><noscript><?= te('Žaidimui reikia JavaScript.') ?></noscript></div>
<script>window.GAME=<?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e(asset_url('assets/game-lab.js')) ?>"></script>
<?php
page_end();
