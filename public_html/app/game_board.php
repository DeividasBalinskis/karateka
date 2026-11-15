<?php
// „Keturi iš eilės“ su gyvūnėlių kortomis: 7 stulpeliai × 6 eilutės, kas pirmas sudės 4 iš eilės (gulsčiai, stačiai, įstrižai).
// Kiekvienas turi 2 kortas (vieną duoda aktyvus gyvūnėlis, kitą - Siena), kiekviena - vieną kartą, prieš savo ėjimą:
//  💣 Sprogimas - pašalina vieną priešininko žetoną (viršutiniai nukrenta), 🧱 Siena - priešininkas kitą ėjimą negali dėti į pasirinktą stulpelį,
//  🔄 Sukeitimas - savo ir gretimo priešininko žetono spalvos susikeičia, ⏩ Dvigubas - šį ėjimą dedami du žetonai.
// Žaidžiama su kitu žaidėju (kvietimas) arba su botu. Draugiškas žaidimas - laimi gudrumas, ne lygis.

const BOARD_W = 7;
const BOARD_H = 6;
const BOARD_CARDS = ['bomb' => ['💣', 'Sprogimas', 'Pašalina vieną priešininko žetoną'], 'wall' => ['🧱', 'Siena', 'Priešininkas kitą ėjimą negali dėti į pasirinktą stulpelį'],
                     'swap' => ['🔄', 'Sukeitimas', 'Savo ir gretimo priešininko žetonų spalvos susikeičia'], 'double' => ['⏩', 'Dvigubas', 'Šį ėjimą dedi du žetonus']];
const BOARD_PET_CARD = ['scratch' => 'bomb', 'fire' => 'bomb', 'guard' => 'wall', 'shield' => 'wall', 'trick' => 'swap', 'reveal' => 'swap', 'dodge' => 'double',
                        'heal' => 'double', 'regen' => 'double', 'revive' => 'bomb'];

function board_side(array $hero): array
{
    $pet = null;
    $card = 'bomb';
    if ($hero['pet_id'] && ($p = q_one('SELECT * FROM game_pets WHERE id = ?', [(int) $hero['pet_id']]))) {
        $pet = ['species' => $p['species'], 'name' => $p['name'], 'color' => $p['color']];
        $card = BOARD_PET_CARD[GAME_PETS[$p['species']][2]] ?? 'bomb';
    }
    $cards = [$card => 1];
    $cards[$card === 'wall' ? 'bomb' : 'wall'] = 1;
    return ['hero' => (int) $hero['id'], 'name' => game_public_name($hero), 'pet' => $pet, 'cards' => $cards,
            'art' => ['species' => $hero['species'], 'gender' => $hero['gender'], 'look' => $hero['look'], 'equipped' => $hero['equipped'], 'belt' => game_belt($hero), 'name' => $hero['name']]];
}

function board_new_state(array $a, array $b): array
{
    return ['v' => 1, 'board' => array_fill(0, BOARD_H, array_fill(0, BOARD_W, 0)), 'turn' => 'a', 'sides' => ['a' => $a, 'b' => $b], 'wall' => null,
            'moves' => 0, 'last' => null, 'line' => null, 'log' => [], 'reactions' => [], 'deadline' => time() + 60, 'end' => null];
}

function board_start_bot(array $hero, string $lvl): int
{
    $a = board_side($hero);
    $names = ['easy' => t('Senpai Bambukas'), 'normal' => t('Sensei Makivara'), 'hard' => t('Meistras Drakonas')];
    $lvl = isset($names[$lvl]) ? $lvl : 'normal';
    $b = ['hero' => 0, 'bot' => $lvl, 'name' => $names[$lvl], 'pet' => ['species' => 'owlet', 'name' => t('Pelėda'), 'color' => null], 'cards' => ['wall' => 1, 'bomb' => 1],
          'art' => ['species' => ['easy' => 'panda', 'normal' => 'monkey', 'hard' => 'dragon'][$lvl], 'gender' => 'm', 'look' => [], 'equipped' => ['kimono' => 'k_white', 'belt' => 'b_plain'], 'belt' => 7, 'name' => $names[$lvl]]];
    $st = board_new_state($a, $b);
    $st['bot'] = $lvl;
    q('INSERT INTO game_matches (game, mode, a_hero, b_hero, status, state, updated_at) VALUES ("keturi", "fair", ?, NULL, "active", ?, NOW())', [(int) $hero['id'], json_encode($st, JSON_UNESCAPED_UNICODE)]);
    return (int) db()->lastInsertId();
}

function board_begin(array $m): array
{
    $m['state'] = board_new_state(board_side(game_hero_row((int) $m['a_hero'])), board_side(game_hero_row((int) $m['b_hero'])));
    $m['status'] = 'active';
    fight_store($m);
    return $m;
}

/** Kur nukris žetonas stulpelyje (eilutės numeris arba -1, jei pilnas) */
function board_drop_row(array $b, int $col): int
{
    for ($r = BOARD_H - 1; $r >= 0; $r--) {
        if ($b[$r][$col] === 0) {
            return $r;
        }
    }
    return -1;
}

/** Laimėjimo linija (4 langeliai) arba null */
function board_win(array $b, int $p): ?array
{
    foreach ([[0, 1], [1, 0], [1, 1], [1, -1]] as [$dr, $dc]) {
        for ($r = 0; $r < BOARD_H; $r++) {
            for ($c = 0; $c < BOARD_W; $c++) {
                $line = [];
                for ($k = 0; $k < 4; $k++) {
                    $rr = $r + $dr * $k;
                    $cc = $c + $dc * $k;
                    if ($rr < 0 || $rr >= BOARD_H || $cc < 0 || $cc >= BOARD_W || $b[$rr][$cc] !== $p) {
                        $line = null;
                        break;
                    }
                    $line[] = [$rr, $cc];
                }
                if ($line) {
                    return $line;
                }
            }
        }
    }
    return null;
}

/** Žetonai krenta žemyn (po Sprogimo) */
function board_gravity(array $b): array
{
    for ($c = 0; $c < BOARD_W; $c++) {
        $col = [];
        for ($r = BOARD_H - 1; $r >= 0; $r--) {
            if ($b[$r][$c]) {
                $col[] = $b[$r][$c];
            }
        }
        for ($r = BOARD_H - 1, $i = 0; $r >= 0; $r--, $i++) {
            $b[$r][$c] = $col[$i] ?? 0;
        }
    }
    return $b;
}

/**
 * Ėjimas: ['card' => 'bomb'|'wall'|'swap'|'double'|'', 'target' => [r,c] arba stulpelis, 'col' => stulpelis, 'col2' => antras (dvigubas)].
 * Grąžina atnaujintą įrašą arba klaidą ['error' => '...'].
 */
function board_move(array $m, string $side, array $mv)
{
    $st = $m['state'];
    if ($m['status'] !== 'active' || $st['end'] || $st['turn'] !== $side) {
        return ['error' => t('Ne jūsų ėjimas.')];
    }
    $p = $side === 'a' ? 1 : 2;
    $o = 3 - $p;
    $b = $st['board'];
    $card = (string) ($mv['card'] ?? '');
    $name = $st['sides'][$side]['name'];
    if ($card !== '') {
        if (empty($st['sides'][$side]['cards'][$card])) {
            return ['error' => t('Šios kortos nebeturite.')];
        }
        if ($card === 'bomb') {
            [$r, $c] = array_map('intval', (array) ($mv['target'] ?? [-1, -1]));
            if (($b[$r][$c] ?? 0) !== $o) {
                return ['error' => t('Pasirinkite priešininko žetoną.')];
            }
            $b[$r][$c] = 0;
            $b = board_gravity($b);
            $st['log'][] = '💣 ' . $name . ' ' . t('susprogdino priešininko žetoną');
        } elseif ($card === 'swap') {
            [$r, $c] = array_map('intval', (array) ($mv['target'] ?? [-1, -1]));
            [$r2, $c2] = array_map('intval', (array) ($mv['target2'] ?? [-1, -1]));
            if (($b[$r][$c] ?? 0) !== $p || ($b[$r2][$c2] ?? 0) !== $o || max(abs($r - $r2), abs($c - $c2)) !== 1) {
                return ['error' => t('Pasirinkite savo ir gretimą priešininko žetoną.')];
            }
            $b[$r][$c] = $o;
            $b[$r2][$c2] = $p;
            $st['log'][] = '🔄 ' . $name . ' ' . t('sukeitė du žetonus');
        } elseif ($card === 'wall') {
            $wc = (int) ($mv['target'] ?? -1);
            if ($wc < 0 || $wc >= BOARD_W) {
                return ['error' => t('Pasirinkite stulpelį sienai.')];
            }
            $st['wallNext'] = ['side' => $side === 'a' ? 'b' : 'a', 'col' => $wc];
            $st['log'][] = '🧱 ' . $name . ' ' . t('pastatė sieną') . ' ' . ($wc + 1) . ' ' . t('stulpelyje');
        }
        $st['sides'][$side]['cards'][$card] = 0;
    }
    $cols = [(int) ($mv['col'] ?? -1)];
    if ($card === 'double') {
        $cols[] = (int) ($mv['col2'] ?? -1);
    }
    foreach ($cols as $i => $col) {
        if ($col < 0 || $col >= BOARD_W) {
            return ['error' => t('Pasirinkite stulpelį.')];
        }
        if ($st['wall'] && $st['wall']['side'] === $side && $st['wall']['col'] === $col) {
            return ['error' => t('Šis stulpelis užtvertas siena.')];
        }
        $r = board_drop_row($b, $col);
        if ($r < 0) {
            return ['error' => t('Stulpelis pilnas.')];
        }
        $b[$r][$col] = $p;
        $st['last'] = [$r, $col];
        if ($card === 'double' && $i === 0) {
            $st['log'][] = '⏩ ' . $name . ' ' . t('deda du žetonus');
        }
    }
    $st['board'] = $b;
    $st['moves']++;
    $st['wall'] = $st['wallNext'] ?? null;
    unset($st['wallNext']);
    $win = board_win($b, $p);
    $winO = board_win($b, $o);   // po Sprogimo / Sukeitimo gali susidaryti ir priešininko linija
    if ($win || $winO) {
        $w = $win ? ($p === 1 ? 1 : 2) : ($o === 1 ? 1 : 2);
        $st['line'] = $win ?: $winO;
        $st['end'] = ['winner' => $w];
        $st['log'][] = '🏆 ' . t('Laimėjo') . ' ' . $st['sides'][$w === 1 ? 'a' : 'b']['name'];
    } elseif (count(array_filter($b[0])) === BOARD_W) {
        $st['end'] = ['winner' => 0];
        $st['log'][] = t('Lygiosios - lenta pilna');
    }
    $st['turn'] = $side === 'a' ? 'b' : 'a';
    $st['deadline'] = time() + 60;
    $st['log'] = array_slice($st['log'], -30);
    $m['state'] = $st;
    if ($st['end']) {
        $m['status'] = 'done';
        $m['winner'] = $st['end']['winner'];
        fight_store($m);
        foreach (['a' => 1, 'b' => 2] as $x => $n) {
            $hid = $x === 'a' ? (int) $m['a_hero'] : (int) $m['b_hero'];
            if ($hid && $m['winner'] === $n && ($h = game_hero_row($hid))) {
                game_daily_win_box($h);
            }
        }
        return $m;
    }
    if (!empty($st['bot']) && $st['turn'] === 'b') {
        return board_move($m, 'b', board_bot_move($st));
    }
    fight_store($m);
    return $m;
}

/** Boto ėjimas: minimax (gylis pagal lygį), kartais korta */
function board_bot_move(array $st): array
{
    $b = $st['board'];
    $depth = ['easy' => 1, 'normal' => 3, 'hard' => 5][$st['bot']] ?? 3;
    $blocked = $st['wall'] && $st['wall']['side'] === 'b' ? $st['wall']['col'] : -1;
    // Sprogimas, jei žaidėjas grasina laimėti, o užblokuoti nebegalima
    if (!empty($st['sides']['b']['cards']['bomb']) && $st['bot'] !== 'easy') {
        $threats = 0;
        for ($c = 0; $c < BOARD_W; $c++) {
            $r = board_drop_row($b, $c);
            if ($r >= 0) {
                $t = $b;
                $t[$r][$c] = 1;
                if (board_win($t, 1)) {
                    $threats++;
                }
            }
        }
        if ($threats >= 2) {
            for ($r = 0; $r < BOARD_H; $r++) {
                for ($c = 0; $c < BOARD_W; $c++) {
                    if ($b[$r][$c] === 1) {
                        $t = $b;
                        $t[$r][$c] = 0;
                        $t = board_gravity($t);
                        $col = board_best_col($t, $depth, -1);
                        return ['card' => 'bomb', 'target' => [$r, $c], 'col' => $col];
                    }
                }
            }
        }
    }
    $col = board_best_col($b, $depth, $blocked);
    if ($st['bot'] === 'easy' && random_int(1, 100) <= 35) {
        $ok = array_values(array_filter(range(0, BOARD_W - 1), function ($c) use ($b, $blocked) { return $c !== $blocked && board_drop_row($b, $c) >= 0; }));
        $col = $ok[random_int(0, count($ok) - 1)];
    }
    return ['col' => $col];
}

function board_best_col(array $b, int $depth, int $blocked): int
{
    $best = -INF;
    $bestCol = 3;
    foreach ([3, 2, 4, 1, 5, 0, 6] as $c) {
        if ($c === $blocked || board_drop_row($b, $c) < 0) {
            continue;
        }
        $t = $b;
        $t[board_drop_row($b, $c)][$c] = 2;
        $v = board_minimax($t, $depth - 1, false, -INF, INF);
        if ($v > $best) {
            $best = $v;
            $bestCol = $c;
        }
    }
    return $bestCol;
}

function board_minimax(array $b, int $depth, bool $max, float $alpha, float $beta): float
{
    if (board_win($b, 2)) {
        return 1000 + $depth;
    }
    if (board_win($b, 1)) {
        return -1000 - $depth;
    }
    if ($depth <= 0) {
        return board_eval($b);
    }
    $cols = array_values(array_filter([3, 2, 4, 1, 5, 0, 6], function ($c) use ($b) { return board_drop_row($b, $c) >= 0; }));
    if (!$cols) {
        return 0;
    }
    $v = $max ? -INF : INF;
    foreach ($cols as $c) {
        $t = $b;
        $t[board_drop_row($b, $c)][$c] = $max ? 2 : 1;
        $s = board_minimax($t, $depth - 1, !$max, $alpha, $beta);
        if ($max) {
            $v = max($v, $s);
            $alpha = max($alpha, $v);
        } else {
            $v = min($v, $s);
            $beta = min($beta, $v);
        }
        if ($beta <= $alpha) {
            break;
        }
    }
    return $v;
}

/** Pozicijos įvertinimas botui: centras ir trejetai / dvejetai su laisva vieta */
function board_eval(array $b): float
{
    $score = 0;
    for ($r = 0; $r < BOARD_H; $r++) {
        $score += ($b[$r][3] === 2 ? 3 : 0) - ($b[$r][3] === 1 ? 3 : 0);
    }
    foreach ([[0, 1], [1, 0], [1, 1], [1, -1]] as [$dr, $dc]) {
        for ($r = 0; $r < BOARD_H; $r++) {
            for ($c = 0; $c < BOARD_W; $c++) {
                $mine = $theirs = $empty = 0;
                for ($k = 0; $k < 4; $k++) {
                    $rr = $r + $dr * $k;
                    $cc = $c + $dc * $k;
                    if ($rr < 0 || $rr >= BOARD_H || $cc < 0 || $cc >= BOARD_W) {
                        continue 2;
                    }
                    $v = $b[$rr][$cc];
                    $v === 2 ? $mine++ : ($v === 1 ? $theirs++ : $empty++);
                }
                if ($mine && !$theirs) {
                    $score += [0, 1, 4, 18][$mine] ?? 0;
                }
                if ($theirs && !$mine) {
                    $score -= [0, 1, 5, 22][$theirs] ?? 0;
                }
            }
        }
    }
    return $score;
}
