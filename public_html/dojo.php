<?php
// „🎮 Žaidimas“ - pagrindinis vaikų puslapis (viršutinėje juostoje, šalia „Pamokos“): langas, kuriame gyvena personažas su gyvūnėliais,
// namu ir gamta (viską galima keisti), ir dideli mygtukai į žaidimus: karatė kova, greita kova, „Keturi iš eilės“, kata ritmas.
// ?h=ID - kurio herojaus pasaulis (tėvams - vaikų, treneriui - savo ir vaikų).
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

page_start(t('Žaidimas'), ['noindex' => true, 'section' => 'zaidimas']);
?>
<div class="game-page">
  <?= game_dojo_html($hero, $mine) ?>
</div>
<?php page_end();
