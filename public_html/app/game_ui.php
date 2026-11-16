<?php
// Žaidimo puslapių dalys: „Mano dojo“ blokas paskyroje, kitų herojų peržiūra, nuorodos į žaidimus.
require_once APP_DIR . '/game.php';

/** Žaidimų puslapių adresai (naršyklei) */
function game_urls(): array
{
    return ['arena' => url('arena.php'), 'greita' => url('arena.php?greita=1'), 'keturi' => url('keturi.php'), 'ritmas' => url('ritmas.php'), 'dojo' => url('paskyra.php')];
}

/** Bendri žaidimo scenarijai ir stilius */
function game_assets(): string
{
    return '<link rel="stylesheet" href="' . e(asset_url('assets/game.css')) . '">'
        . '<script src="' . e(asset_url('assets/game-art.js')) . '"></script>';
}

/**
 * „Mano dojo“: herojaus pasaulis, XP, savybės, apranga, inventorius, gyvūnėliai, namai.
 * $heroes - kuriuos herojus galima perjungti (treneriui: savo ir vaikų), $readOnly - kito žaidėjo peržiūra.
 */
function game_dojo_html(array $hero, array $heroes = [], bool $readOnly = false): string
{
    $news = $readOnly ? [] : game_sync($hero);
    $hero = game_hero_row((int) $hero['id']);
    $cfg = [
        'api' => url('zaidimas.php'), 'csrf' => csrf_token(), 'catalog' => game_catalog_json(), 'hero' => game_hero_view($hero, !$readOnly),
        'heroes' => array_map(function ($h) { return ['id' => (int) $h['id'], 'name' => $h['name']]; }, $heroes), 'heroUrl' => url('paskyra.php?h=HID'),
        'admin' => is_admin() && !$readOnly, 'readOnly' => $readOnly, 'news' => $news, 'urls' => game_urls(),
    ];
    return game_assets()
        . '<section class="dojo" id="dojo" aria-label="' . e(t('Mano dojo')) . '"><noscript>' . e(t('Žaidimui reikia JavaScript.')) . '</noscript></section>'
        . '<script>window.GAME=' . json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>'
        . '<script src="' . e(asset_url('assets/game-dojo.js')) . '"></script>';
}
