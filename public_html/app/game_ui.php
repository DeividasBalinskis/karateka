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

/**
 * #128: reitingai - aukščiausi lygiai (pagal tikrus XP = aktyvumo taškus) ir daugiausia pergalių kiekviename žaidime.
 * Grąžina [raktas => ['label','unit','empty','rows' => [['id','name','sub','value']], 'me' => ['pos','value']|null]]
 */
function game_rankings(int $myHero): array
{
    $heroes = [];
    foreach (q_all('SELECT * FROM game_heroes') as $h) {
        $heroes[(int) $h['id']] = game_hero_decode($h);
    }
    $name = function (int $id) use ($heroes) { return isset($heroes[$id]) ? game_public_name($heroes[$id]) : '?'; };
    $out = [];
    // Lygis: tik vaikai ir nariai (herojus susietas su nariu); testinio lygio nėra - tik tikri taškai
    $pts = q_all('SELECT h.id, SUM(pa.points) AS xp FROM game_heroes h JOIN point_awards pa ON pa.member_id = h.member_id WHERE h.member_id IS NOT NULL GROUP BY h.id HAVING xp > 0 ORDER BY xp DESC');
    $rows = [];
    $me = null;
    foreach ($pts as $i => $r) {
        $lv = xp_level((int) $r['xp']);
        if ((int) $r['id'] === $myHero) {
            $me = ['pos' => $i + 1, 'value' => (string) $lv['level']];
        }
        if ($i < 10) {
            $rows[] = ['id' => (int) $r['id'], 'name' => $name((int) $r['id']), 'sub' => (int) $r['xp'] . ' XP', 'value' => (string) $lv['level']];
        }
    }
    $out['lygis'] = ['label' => t('⭐ Lygis'), 'unit' => t('lygis'), 'empty' => t('Dar niekas nesurinko XP.'), 'rows' => $rows, 'me' => $me];
    // Pergalės kiekviename žaidime (ir prieš treniruočių partnerius)
    foreach (['kova' => t('⚔ Karate kova'), 'greita' => t('⚡ Greita kova'), 'keturi' => t('🟦 Keturi iš eilės')] as $g => $label) {
        $wins = q_all('SELECT hero, COUNT(*) AS n FROM (
                         SELECT a_hero AS hero FROM game_matches WHERE game = ? AND status = "done" AND winner = 1
                         UNION ALL SELECT b_hero FROM game_matches WHERE game = ? AND status = "done" AND winner = 2 AND b_hero IS NOT NULL) w
                       GROUP BY hero ORDER BY n DESC', [$g, $g]);
        $rows = [];
        $me = null;
        foreach ($wins as $i => $r) {
            if ((int) $r['hero'] === $myHero) {
                $me = ['pos' => $i + 1, 'value' => (string) $r['n']];
            }
            if ($i < 10) {
                $rows[] = ['id' => (int) $r['hero'], 'name' => $name((int) $r['hero']), 'sub' => '', 'value' => (string) $r['n']];
            }
        }
        $out[$g] = ['label' => $label, 'unit' => t('perg.'), 'empty' => t('Dar niekas nelaimėjo - būk pirmas!'), 'rows' => $rows, 'me' => $me];
    }
    // Kata ritmas: geriausias rezultatas
    $best = q_all('SELECT hero_id, MAX(score) AS s FROM game_scores WHERE game = "ritmas" GROUP BY hero_id ORDER BY s DESC');
    $rows = [];
    $me = null;
    foreach ($best as $i => $r) {
        if ((int) $r['hero_id'] === $myHero) {
            $me = ['pos' => $i + 1, 'value' => (string) $r['s']];
        }
        if ($i < 10) {
            $rows[] = ['id' => (int) $r['hero_id'], 'name' => $name((int) $r['hero_id']), 'sub' => '', 'value' => (string) $r['s']];
        }
    }
    $out['ritmas'] = ['label' => t('🥁 Kata ritmas'), 'unit' => t('tšk.'), 'empty' => t('Dar niekas nežaidė.'), 'rows' => $rows, 'me' => $me];
    return $out;
}

/** Dojo apačioje (#115): reitingas su kitais ir klubo žaidėjai - pakviesti draugą į kovą ar pažiūrėti jo dojo */
function game_dojo_social_html(array $hero, array $mine): string
{
    $hid = (int) $hero['id'];
    $myIds = array_map(function ($h) { return (int) $h['id']; }, $mine);
    $players = array_values(array_filter(game_club_heroes($hid, 60), function ($h) use ($myIds) { return !in_array($h['id'], $myIds, true); }));
    // #128: reitingas skiltimis - aukščiausi lygiai ir daugiausia pergalių kiekviename žaidime (skiltis galima slinkti į šoną)
    $tabs = game_rankings($hid);
    $html = '<section class="dojo-social"><div class="ds-col ds-top"><h2>' . te('🏆 Reitingas') . '</h2>';
    $html .= '<div class="ds-tabs" role="tablist">';
    $first = true;
    foreach ($tabs as $key => $tb) {
        $html .= '<button type="button" role="tab" class="ds-tab" data-rk="' . e($key) . '" aria-selected="' . ($first ? 'true' : 'false') . '">' . e($tb['label']) . '</button>';
        $first = false;
    }
    $html .= '</div>';
    $first = true;
    foreach ($tabs as $key => $tb) {
        $html .= '<div class="ds-panel" data-rk="' . e($key) . '"' . ($first ? '' : ' hidden') . '>';
        if ($tb['rows']) {
            $html .= '<ol class="ds-rank">';
            foreach ($tb['rows'] as $i => $r) {
                $html .= '<li class="' . ($r['id'] === $hid ? 'me' : '') . '"><span class="ds-pos p' . min($i + 1, 4) . '">' . ($i + 1) . '</span><span class="ds-n">' . e($r['name'])
                    . ($r['sub'] !== '' ? ' <small>' . e($r['sub']) . '</small>' : '') . '</span><b>' . e($r['value']) . ' <small>' . e($tb['unit']) . '</small></b></li>';
            }
            $html .= '</ol>';
            if ($tb['me'] && !in_array($hid, array_column($tb['rows'], 'id'), true)) {
                $html .= '<p class="ds-me">' . te('Tavo vieta') . ': <b>' . (int) $tb['me']['pos'] . '</b> · ' . e($tb['me']['value']) . ' ' . e($tb['unit']) . '</p>';
            }
        } else {
            $html .= '<p class="ds-empty">' . e($tb['empty']) . '</p>';
        }
        $html .= '</div>';
        $first = false;
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
        . 'box.addEventListener("click",function(e){var tb=e.target.closest(".ds-tab");if(!tb)return;box.querySelectorAll(".ds-tab").forEach(function(x){x.setAttribute("aria-selected",x===tb?"true":"false");});box.querySelectorAll(".ds-panel").forEach(function(p){p.hidden=p.dataset.rk!==tb.dataset.rk;});});'
        . 'box.addEventListener("click",function(e){var b=e.target.closest("[data-inv]");if(!b)return;b.disabled=true;'
        . 'fetch(C.api,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","X-CSRF-Token":C.csrf,Accept:"application/json"},body:JSON.stringify({action:"invite",h:C.h,to:b.dataset.inv,mode:"power",game:"kova"})})'
        . '.then(function(r){return r.json();}).then(function(d){var t=box.querySelector(".ds-toast");t.hidden=false;t.innerHTML=d.ok?C.sent+\' <a href="\'+C.arena+\'">\'+C.go+\'</a>\':(d.error||"");b.disabled=false;}).catch(function(){b.disabled=false;});});})();</script>';
    return $html;
}
