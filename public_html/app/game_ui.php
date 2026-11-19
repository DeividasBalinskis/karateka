<?php
// Žaidimo puslapių dalys: „Mano dojo“ blokas (puslapis dojo.php), kitų herojų peržiūra, nuorodos į žaidimus.
require_once APP_DIR . '/game.php';

/** Žaidimų puslapių adresai (naršyklei) */
function game_urls(): array
{
    return ['arena' => url('arena.php'), 'greita' => url('arena.php?greita=1'), 'keturi' => url('keturi.php'), 'ritmas' => url('ritmas.php'), 'dojo' => url('dojo.php'), 'lab' => url('kova-bandymai.php')];
}

/** Bendri žaidimo scenarijai ir stilius */
function game_assets(): string
{
    // Pikselinis menas: duomenys (lapė, gyvūnėliai) + variklis, kuris pakeičia senus apvalius SVG personažus
    return '<link href="https://fonts.googleapis.com/css2?family=Pixelify+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">'
        . '<link rel="stylesheet" href="' . e(asset_url('assets/game.css')) . '">'
        . '<link rel="stylesheet" href="' . e(asset_url('assets/game-px.css')) . '">'
        . '<script src="' . e(asset_url('assets/game-art.js')) . '"></script>'
        . '<script src="' . e(asset_url('assets/game-px-data.js')) . '"></script>'
        . '<script src="' . e(asset_url('assets/game-pixel.js')) . '"></script>';
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
        'heroes' => array_map(function ($h) { return ['id' => (int) $h['id'], 'name' => $h['name']]; }, $heroes), 'heroUrl' => url('dojo.php?h=HID'),
        'admin' => is_admin() && !$readOnly, 'readOnly' => $readOnly, 'news' => $news, 'urls' => game_urls(),
    ];
    return game_assets()
        . '<section class="dojo" id="dojo" aria-label="' . e(t('Mano dojo')) . '"><noscript>' . e(t('Žaidimui reikia JavaScript.')) . '</noscript></section>'
        . '<script>window.GAME=' . json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>'
        . '<script src="' . e(asset_url('assets/game-dojo.js')) . '"></script>'
        . ($readOnly ? '' : game_dojo_social_html($hero, $heroes));
}

/** Dojo apačioje (#115): reitingas su kitais ir klubo žaidėjai - pakviesti draugą į kovą ar pažiūrėti jo dojo */
function game_dojo_social_html(array $hero, array $mine): string
{
    $hid = (int) $hero['id'];
    $myIds = array_map(function ($h) { return (int) $h['id']; }, $mine);
    $top = q_all('SELECT h.* FROM game_heroes h WHERE (h.wins + h.losses) > 0 ORDER BY h.wins DESC, h.rating_power DESC LIMIT 10');
    $mineRow = q_one('SELECT h.*, (SELECT COUNT(*) + 1 FROM game_heroes x WHERE x.wins > h.wins) AS pos FROM game_heroes h WHERE h.id = ?', [$hid]);
    $players = array_values(array_filter(game_club_heroes($hid, 60), function ($h) use ($myIds) { return !in_array($h['id'], $myIds, true); }));
    $html = '<section class="dojo-social"><div class="ds-col ds-top"><h2>' . te('🏆 Reitingas') . '</h2>';
    if ($top) {
        $html .= '<ol class="ds-rank">';
        foreach ($top as $i => $r) {
            $r = game_hero_decode($r);
            $html .= '<li class="' . ((int) $r['id'] === $hid ? 'me' : '') . '"><span class="ds-pos p' . min($i + 1, 4) . '">' . ($i + 1) . '</span><span class="ds-n">' . e(game_public_name($r))
                . ' <small>' . te('Lygis') . ' ' . game_level($r) . '</small></span><b>' . (int) $r['wins'] . ' <small>' . te('perg.') . '</small></b></li>';
        }
        $html .= '</ol>';
        if ($mineRow && !in_array($hid, array_map(function ($r) { return (int) $r['id']; }, $top), true)) {
            $html .= '<p class="ds-me">' . te('Tavo vieta') . ': <b>' . (int) $mineRow['pos'] . '</b> · ' . (int) $mineRow['wins'] . ' ' . te('perg.') . '</p>';
        }
    } else {
        $html .= '<p class="ds-empty">' . te('Dar niekas nekovojo - laimėk pirmą kovą ir būk pirmas!') . '</p>';
    }
    $html .= '<a class="ds-link" href="' . e(url('arena.php')) . '">' . te('Visi reitingai ir kovos →') . '</a></div>';
    $html .= '<div class="ds-col ds-friends"><h2>' . te('👥 Kviesk draugą į kovą') . '</h2>';
    if ($players) {
        $html .= '<input type="search" class="ds-q" placeholder="' . te('Ieškoti žaidėjo…') . '" aria-label="' . te('Ieškoti žaidėjo') . '"><ul class="ds-players">';
        foreach (array_slice($players, 0, 60) as $p) {
            $html .= '<li data-n="' . e(mb_strtolower($p['name'])) . '"><span class="ds-n">' . e($p['name']) . ' <small>' . te('Lygis') . ' ' . (int) $p['level'] . '</small></span><span class="ds-acts">'
                . ($p['invites'] ? '<button type="button" class="ds-btn" data-inv="' . (int) $p['id'] . '">⚔ ' . te('Kviesti') . '</button>' : '<small class="ds-off">🔕 ' . te('nepriima kvietimų') . '</small>')
                . '<a class="ds-btn ghost" href="' . e(url('zaidejas.php?id=' . (int) $p['id'])) . '">' . te('Dojo') . '</a></span></li>';
        }
        $html .= '</ul>';
    } else {
        $html .= '<p class="ds-empty">' . te('Kitų žaidėjų dar nėra.') . '</p>';
    }
    $html .= '<p class="ds-toast" role="status" hidden></p></div></section>';
    $cfg = ['api' => url('zaidimas.php'), 'csrf' => csrf_token(), 'h' => $hid, 'arena' => url('arena.php'), 'sent' => t('📨 Kvietimas išsiųstas! Kai draugas priims - kova prasidės arenoje.'), 'go' => t('Į areną →')];
    $html .= '<script>(function(){var C=' . json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ',box=document.querySelector(".dojo-social");if(!box)return;'
        . 'var q=box.querySelector(".ds-q");if(q)q.addEventListener("input",function(){var v=q.value.trim().toLowerCase();box.querySelectorAll(".ds-players li").forEach(function(li){li.hidden=v&&li.dataset.n.indexOf(v)<0;});});'
        . 'box.addEventListener("click",function(e){var b=e.target.closest("[data-inv]");if(!b)return;b.disabled=true;'
        . 'fetch(C.api,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","X-CSRF-Token":C.csrf,Accept:"application/json"},body:JSON.stringify({action:"invite",h:C.h,to:b.dataset.inv,mode:"power",game:"kova"})})'
        . '.then(function(r){return r.json();}).then(function(d){var t=box.querySelector(".ds-toast");t.hidden=false;t.innerHTML=d.ok?C.sent+\' <a href="\'+C.arena+\'">\'+C.go+\'</a>\':(d.error||"");b.disabled=false;}).catch(function(){b.disabled=false;});});})();</script>';
    return $html;
}
