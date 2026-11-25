<?php
// Žaidimo „Mano dojo“ API (JSON): personažo išvaizda, daiktai, namai, dovanų dėžės, gyvūnėliai, testavimo nustatymai (adminui),
// kvietimai į kovą / žaidimą, kovos ėjimai, reakcijos, „Keturi iš eilės“ ėjimai, ritmo žaidimo rezultatai.
// Susirašinėti tarpusavyje negalima (tik reakcijos žaidimo metu) - rašyti galima tik Deniui (pokalbio langas).
require __DIR__ . '/app/bootstrap.php';
require_once APP_DIR . '/game.php';
require_once APP_DIR . '/game_fight.php';
require_once APP_DIR . '/game_board.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$out = function (array $d, int $code = 200) {
    http_response_code($code);
    exit(json_encode($d, JSON_UNESCAPED_UNICODE));
};
$a = current_account();
if (!$a || !is_active_account($a)) {
    $out(['ok' => false, 'error' => t('Prisijunkite.')], 403);
}
if (!game_allowed($a)) {
    $out(['ok' => false, 'error' => t('Žaidimas dar neprieinamas.')], 403);
}
$in = is_post() ? (json_decode((string) file_get_contents('php://input'), true) ?: $_POST) : $_GET;
if (is_post()) {
    $tok = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($in['_csrf'] ?? '');
    if (!is_string($tok) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $tok)) {
        $out(['ok' => false, 'error' => t('Sesija pasibaigė - atnaujinkite puslapį.')], 400);
    }
}
// Kuriuo personažu veikiama (savo ar vaiko)
$mine = game_my_heroes($a);
$hero = null;
foreach ($mine as $h) {
    if ((int) $h['id'] === (int) ($in['h'] ?? 0)) {
        $hero = $h;
    }
}
$hero = $hero ?? $mine[0];
$hid = (int) $hero['id'];
$action = (string) ($in['action'] ?? '');

// ---------- Skaitymas ----------
if (!is_post()) {
    if (isset($in['hero'])) {
        game_sync($hero);
        $out(['ok' => true, 'hero' => game_hero_view(game_hero_row($hid))]);
    }
    if (isset($in['heroes'])) {
        $out(['ok' => true, 'heroes' => game_club_heroes($hid)]);
    }
    if (isset($in['view'])) {   // kito personažo profilis (žiūrėti, be asmens duomenų)
        $other = game_hero_row((int) $in['view']);
        $out($other ? ['ok' => true, 'hero' => game_hero_view($other, false)] : ['ok' => false], $other ? 200 : 404);
    }
    if (isset($in['invites'])) {   // kvietimai ir vykstančios kovos visiems mano personažams
        $out(['ok' => true, 'items' => game_invites_list($mine)]);
    }
    if (isset($in['match'])) {
        $m = fight_match((int) $in['match']);
        if (!$m) {
            $out(['ok' => false], 404);
        }
        $side = null;
        foreach ($mine as $h) {
            $side = $side ?? fight_side_of($m, (int) $h['id']);
        }
        if ($m['game'] === 'kova' && $m['status'] === 'active') {
            $m = fight_tick($m);
        }
        $out(['ok' => true, 'match' => fight_view($m, $side)]);
    }
    if (isset($in['top'])) {   // ritmo žaidimo savaitės geriausi
        $rows = q_all('SELECT s.hero_id, MAX(s.score) AS best, MAX(s.grade) AS grade FROM game_scores s WHERE s.game = ? AND s.song = ? AND s.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                        GROUP BY s.hero_id ORDER BY best DESC LIMIT 10', [($in['game'] ?? '') === 'greita' ? 'greita' : 'ritmas', (string) ($in['song'] ?? '')]);
        $out(['ok' => true, 'top' => array_map(function ($r) { $h = game_hero_row((int) $r['hero_id']); return ['name' => $h ? game_public_name($h) : '?', 'score' => (int) $r['best']]; }, $rows)]);
    }
    $out(['ok' => false, 'error' => 'unknown'], 400);
}

// ---------- Veiksmai ----------
switch ($action) {
    case 'save':
        $h = game_save_hero($hero, (array) ($in['data'] ?? []));
        $out(['ok' => true, 'hero' => game_hero_view($h)]);
    case 'box':
        $key = game_box_open($hero, (int) ($in['id'] ?? 0));
        $out($key ? ['ok' => true, 'item' => $key, 'hero' => game_hero_view(game_hero_row($hid))] : ['ok' => false, 'error' => t('Dėžė jau atidaryta.')]);
    case 'pet_adopt':
        $pets = game_pets($hid);
        $sp = (string) ($in['species'] ?? '');
        // Pirmas - nemokamai, pasirenkamas iš pradinių; testavimo laikotarpiu - bet kuris (iki 6)
        $starter = isset(GAME_PETS[$sp]) && ((GAME_PETS[$sp][3] && !$pets) || (GAME_UNLOCK_ALL && count($pets) < 6));
        if (!$starter) {
            $out(['ok' => false, 'error' => t('Naujas gyvūnėlis - iš kiaušinio dovanų dėžėje.')]);
        }
        game_pet_adopt($hero, $sp, (string) ($in['name'] ?? ''), (string) ($in['color'] ?? ''));
        $out(['ok' => true, 'hero' => game_hero_view(game_hero_row($hid))]);
    case 'hatch':
        $egg = (string) ($in['item'] ?? '');
        $cat = game_items();
        $owned = game_owned($hid);
        if (!isset($cat[$egg]) || $cat[$egg]['slot'] !== 'egg' || empty($owned[$egg])) {
            $out(['ok' => false, 'error' => t('Kiaušinio nėra.')]);
        }
        if (game_pet_adopt($hero, $cat[$egg]['a']['pet'], (string) ($in['name'] ?? ''))) {
            q('UPDATE game_items SET qty = qty - 1 WHERE hero_id = ? AND item_key = ?', [$hid, $egg]);
            q('DELETE FROM game_items WHERE hero_id = ? AND qty <= 0', [$hid]);
        }
        $out(['ok' => true, 'hero' => game_hero_view(game_hero_row($hid))]);
    case 'pet_feed':
        $ok = game_pet_feed($hid, (int) ($in['id'] ?? 0));
        $out(['ok' => $ok, 'error' => $ok ? '' : t('Dar sotus - pamaitinti galima kas 3 valandas.'), 'hero' => game_hero_view(game_hero_row($hid))]);
    case 'pet_release':
        // Paleisti gyvūnėlį (testavimo laikotarpiu - kad galėtum išbandyti kitus)
        if (!GAME_UNLOCK_ALL) {
            $out(['ok' => false, 'error' => t('Negalima.')]);
        }
        $pid = (int) ($in['id'] ?? 0);
        q('DELETE FROM game_pets WHERE id = ? AND hero_id = ?', [$pid, $hid]);
        if ((int) $hero['pet_id'] === $pid) {
            q('UPDATE game_heroes SET pet_id = (SELECT id FROM game_pets WHERE hero_id = ? ORDER BY id LIMIT 1) WHERE id = ?', [$hid, $hid]);
        }
        $out(['ok' => true, 'hero' => game_hero_view(game_hero_row($hid))]);
    case 'pet_rename':
        q('UPDATE game_pets SET name = ? WHERE id = ? AND hero_id = ?', [mb_substr(trim((string) ($in['name'] ?? '')) ?: 'Draugas', 0, 30), (int) ($in['id'] ?? 0), $hid]);
        $out(['ok' => true, 'hero' => game_hero_view(game_hero_row($hid))]);
    case 'test':
        // Testavimui (tik adminui): lygis, diržas, visi daiktai, dėžės, kiaušiniai
        if (!is_admin($a)) {
            $out(['ok' => false, 'error' => t('Tik adminui.')], 403);
        }
        if (array_key_exists('level', $in)) {
            q('UPDATE game_heroes SET test_level = ? WHERE id = ?', [$in['level'] === '' || $in['level'] === null ? null : max(1, min(99, (int) $in['level'])), $hid]);
        }
        if (array_key_exists('belt', $in)) {
            q('UPDATE game_heroes SET test_belt = ? WHERE id = ?', [$in['belt'] === '' || $in['belt'] === null ? null : max(1, min(18, (int) $in['belt'])), $hid]);
        }
        if (!empty($in['all_items'])) {
            foreach (array_keys(game_items()) as $k) {
                q('INSERT INTO game_items (hero_id, item_key, qty, source) VALUES (?, ?, 1, "test") ON DUPLICATE KEY UPDATE qty = GREATEST(qty, 1)', [$hid, $k]);
            }
        }
        if (!empty($in['boxes'])) {
            for ($i = 0; $i < min(10, (int) $in['boxes']); $i++) {
                q('INSERT INTO game_boxes (hero_id, reason, title) VALUES (?, ?, ?)', [$hid, 'test:' . bin2hex(random_bytes(5)), t('Testinė dovana')]);
            }
        }
        if (!empty($in['reset'])) {
            q('DELETE FROM game_items WHERE hero_id = ?', [$hid]);
            q('DELETE FROM game_boxes WHERE hero_id = ?', [$hid]);
            q('DELETE FROM game_pets WHERE hero_id = ?', [$hid]);
            q('UPDATE game_heroes SET test_level = NULL, test_belt = NULL, pet_id = NULL, alloc = ?, equipped = ?, home = ? WHERE id = ?',
                [json_encode(['str' => 0, 'agi' => 0, 'int' => 0, 'end' => 0]), json_encode(['kimono' => 'k_white', 'belt' => 'b_plain', 'hair' => 'hr_short']),
                 json_encode(['bg' => 'bg_dojo', 'house' => 'hs_tent', 'decor' => []]), $hid]);
            foreach (GAME_START_ITEMS as $k) {
                q('INSERT IGNORE INTO game_items (hero_id, item_key, source) VALUES (?, ?, "start")', [$hid, $k]);
            }
        }
        $h = game_hero_row($hid);
        game_sync($h);
        $out(['ok' => true, 'hero' => game_hero_view(game_hero_row($hid))]);
    case 'invite':
        $to = game_hero_row((int) ($in['to'] ?? 0));
        $game = ($in['game'] ?? '') === 'keturi' ? 'keturi' : 'kova';
        $mode = $game === 'keturi' ? 'fair' : (($in['mode'] ?? '') === 'fair' ? 'fair' : 'power');
        if (!$to || (int) $to['id'] === $hid) {
            $out(['ok' => false, 'error' => t('Pasirinkite priešininką.')]);
        }
        if (!$to['invites']) {
            $out(['ok' => false, 'error' => t('Šis žaidėjas dabar nepriima kvietimų.')]);
        }
        if (q_value('SELECT 1 FROM game_matches WHERE a_hero = ? AND b_hero = ? AND status = "invited" AND deadline > NOW()', [$hid, (int) $to['id']])) {
            $out(['ok' => false, 'error' => t('Kvietimas jau išsiųstas - palaukite atsakymo.')]);
        }
        q('INSERT INTO game_matches (game, mode, a_hero, b_hero, status, deadline, updated_at) VALUES (?, ?, ?, ?, "invited", DATE_ADD(NOW(), INTERVAL 3 MINUTE), NOW())',
            [$game, $mode, $hid, (int) $to['id']]);
        $out(['ok' => true, 'id' => (int) db()->lastInsertId()]);
    case 'bot':
        $game = ($in['game'] ?? '') === 'keturi' ? 'keturi' : 'kova';
        $lvl = (string) ($in['level'] ?? 'normal');
        $id = $game === 'keturi' ? board_start_bot($hero, $lvl) : fight_start_bot($hero, (string) ($in['mode'] ?? 'power'), $lvl);
        $out(['ok' => true, 'id' => $id]);
    case 'accept':
    case 'decline':
    case 'cancel':
        $m = fight_match((int) ($in['id'] ?? 0));
        $ids = array_map(function ($h) { return (int) $h['id']; }, $mine);
        if (!$m || $m['status'] !== 'invited') {
            $out(['ok' => false, 'error' => t('Kvietimas nebegalioja.')]);
        }
        if ($action === 'cancel' && in_array((int) $m['a_hero'], $ids, true)) {
            q('UPDATE game_matches SET status = "cancelled" WHERE id = ?', [(int) $m['id']]);
            $out(['ok' => true]);
        }
        if (!in_array((int) $m['b_hero'], $ids, true)) {
            $out(['ok' => false], 403);
        }
        if ($action === 'decline') {
            q('UPDATE game_matches SET status = "declined" WHERE id = ?', [(int) $m['id']]);
            $out(['ok' => true]);
        }
        $m = $m['game'] === 'keturi' ? board_begin($m) : fight_begin($m);
        $out(['ok' => true, 'id' => (int) $m['id']]);
    case 'turn':
    case 'move':
    case 'react':
    case 'forfeit':
        $m = fight_match((int) ($in['match'] ?? 0));
        $side = null;
        foreach ($mine as $h) {
            $side = $side ?? ($m ? fight_side_of($m, (int) $h['id']) : null);
        }
        if (!$m || !$side) {
            $out(['ok' => false], 404);
        }
        if ($action === 'react') {
            $m = fight_react($m, $side, (string) ($in['e'] ?? ''));
        } elseif ($action === 'forfeit' && $m['status'] === 'active') {
            $m['status'] = 'done';
            $m['winner'] = $side === 'a' ? 2 : 1;
            $m['state']['end'] = ['winner' => $m['winner'], 'forfeit' => $side];
            $m['state']['log'][] = $m['game'] === 'kova' ? ['s' => '', 'k' => 'end', 'n' => $m['state']['turn'] ?? 0, 't' => $m['state']['sides'][$side]['name'] . ' ' . t('pasidavė.')]
                : $m['state']['sides'][$side]['name'] . ' ' . t('pasidavė.');
            if ($m['game'] === 'kova') {
                fight_finish($m);
            } else {
                fight_store($m);
            }
        } elseif ($m['game'] === 'kova' && $action === 'turn') {
            $m = fight_turn($m, $side, (array) ($in['pick'] ?? []));
        } elseif ($m['game'] === 'keturi' && $action === 'move') {
            $r = board_move($m, $side, (array) ($in['mv'] ?? []));
            if (isset($r['error'])) {
                $out(['ok' => false, 'error' => $r['error']]);
            }
            $m = $r;
        }
        $out(['ok' => true, 'match' => fight_view(fight_match((int) $m['id']), $side)]);
    case 'score':
        // Ritmo žaidimas (daina) arba „Greita kova“ (realiu laiku su botu: lygis, pergalė)
        $game = ($in['game'] ?? '') === 'greita' ? 'greita' : 'ritmas';
        $song = preg_replace('/[^a-z0-9]/', '', (string) ($in['song'] ?? ''));
        $score = max(0, min(2000000, (int) ($in['score'] ?? 0)));
        $grade = in_array($in['grade'] ?? '', ['S', 'A', 'B', 'C', 'D'], true) ? $in['grade'] : 'D';
        q('INSERT INTO game_scores (hero_id, game, song, score, grade) VALUES (?, ?, ?, ?, ?)', [$hid, $game, $song, $score, $grade]);
        $prize = '';
        if ($game === 'greita') {
            // Pergalė prieš botą įskaitoma į „3 pergalės per savaitę“ (dovanų dėžė), reitingas nesikeičia
            $win = !empty($in['win']);
            q('INSERT INTO game_matches (game, mode, a_hero, b_hero, status, winner, updated_at) VALUES ("greita", ?, ?, NULL, "done", ?, NOW())',
                [($in['mode'] ?? '') === 'fair' ? 'fair' : 'power', $hid, $win ? 1 : 2]);
            if ($win) {
                $before = game_box_count($hid);
                game_week_win_box(game_hero_row($hid));
                $prize = game_box_count($hid) > $before ? 'box' : '';
            }
            $out(['ok' => true, 'prize' => $prize]);
        }
        if ($grade === 'S' && q('INSERT IGNORE INTO game_boxes (hero_id, reason, title) VALUES (?, ?, ?)', [$hid, 'ritmas:' . $song, t('Kata ritmas: S įvertinimas')])->rowCount()) {
            $prize = 'box';
        }
        if (in_array($grade, ['S', 'A'], true) && q('INSERT IGNORE INTO game_items (hero_id, item_key, source) VALUES (?, "d_drum", "ritmas")', [$hid])->rowCount()) {
            $prize = $prize ?: 'drum';
        }
        $out(['ok' => true, 'prize' => $prize]);
}
$out(['ok' => false, 'error' => 'unknown'], 400);
