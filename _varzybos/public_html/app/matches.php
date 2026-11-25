<?php
// Kovos: dalyvių perkėlimas į kitą ratą, rezultatai, atšaukimas, repechage

function match_or_404(int $id): array
{
    $m = q_one('SELECT * FROM matches WHERE id = ?', [$id]);
    if (!$m) {
        fail('Kova nerasta.', 404);
    }
    return $m;
}

function loser_of(array $m): ?int
{
    if ($m['status'] !== 'done' || !$m['winner_id']) {
        return null;
    }
    $w = (int) $m['winner_id'];
    $other = (int) $m['aka_id'] === $w ? $m['shiro_id'] : $m['aka_id'];
    return $other ? (int) $other : null;
}

/**
 * Iš kur ateina dalyvis: [entry_id|null, ar jau žinoma, ar tai bye].
 * E - įrašytas iš karto, BYE - nėra, W:id / L:id - kovos laimėtojas / pralaimėjęs.
 */
function resolve_src(string $src, $current): array
{
    if ($src === 'E') {
        return [$current ? (int) $current : null, true, !$current];
    }
    if ($src === 'BYE') {
        return [null, true, true];
    }
    [$kind, $id] = explode(':', $src);
    $from = q_one('SELECT * FROM matches WHERE id = ?', [(int) $id]);
    if (!$from || $from['status'] !== 'done') {
        return [null, false, false];
    }
    $entry = $kind === 'W' ? ($from['winner_id'] ? (int) $from['winner_id'] : null) : loser_of($from);
    return [$entry, true, $entry === null];
}

/** Atnaujina kovos dalyvius iš šaltinių; bye kovą iš karto užbaigia ir stumia toliau */
function refresh_match(int $id): void
{
    $m = match_or_404($id);
    if ($m['status'] === 'done' || $m['status'] === 'running') {
        return;
    }
    [$aka, $akaKnown, $akaBye] = resolve_src($m['aka_src'], $m['aka_id']);
    [$shiro, $shiroKnown, $shiroBye] = resolve_src($m['shiro_src'], $m['shiro_id']);
    $row = ['aka_id' => $aka, 'shiro_id' => $shiro];
    if ($akaKnown && $shiroKnown && ($akaBye || $shiroBye)) {
        $row += ['status' => 'done', 'result' => 'bye', 'winner_id' => $akaBye ? $shiro : $aka, 'finished_at' => now()];
    } elseif ($akaKnown && $shiroKnown) {
        $row['status'] = 'ready';
    } else {
        $row['status'] = 'waiting';
    }
    update('matches', $row, 'id = ?', [$id]);
    if (($row['status'] ?? '') === 'done') {
        propagate($id);
    }
}

/** Po kovos pabaigos: atnaujina kovas, kurios laukė šios kovos rezultato */
function propagate(int $matchId): void
{
    foreach (q_all('SELECT id FROM matches WHERE aka_src IN (?, ?) OR shiro_src IN (?, ?)', ["W:$matchId", "L:$matchId", "W:$matchId", "L:$matchId"]) as $d) {
        refresh_match((int) $d['id']);
    }
    $m = match_or_404($matchId);
    $cat = category_or_404((int) $m['category_id']);
    if ($m['stage'] === 'main') {
        maybe_build_repechage($cat);
    }
    update_category_status($cat);
}

function category_rounds(int $categoryId): int
{
    return (int) q_value('SELECT COALESCE(MAX(round), 0) FROM matches WHERE category_id = ? AND stage = "main"', [$categoryId]);
}

/**
 * Repechage: kai abu finalininkai žinomi, kiekvieno finalininko nugalėti sportininkai
 * kovoja "laiptais" (pirmas su antru, laimėtojas su trečiu...). Paskutinis laimėtojas - bronza.
 */
function maybe_build_repechage(array $cat): void
{
    if ($cat['format'] !== 'bracket' || $cat['third_place'] !== 'repechage') {
        return;
    }
    $rounds = category_rounds((int) $cat['id']);
    if ($rounds < 2 || q_value('SELECT 1 FROM matches WHERE category_id = ? AND stage = "repechage" LIMIT 1', [$cat['id']])) {
        return;
    }
    $final = q_one('SELECT * FROM matches WHERE category_id = ? AND stage = "main" AND round = ?', [$cat['id'], $rounds]);
    if (!$final || !$final['aka_id'] || !$final['shiro_id']) {
        return;
    }
    foreach ([(int) $final['aka_id'], (int) $final['shiro_id']] as $side => $finalist) {
        $losers = repechage_losers((int) $cat['id'], $finalist, $rounds);
        if (count($losers) < 2) {
            continue;   // vienas nugalėtasis gauna bronzą be kovos
        }
        $prev = insert('matches', ['category_id' => $cat['id'], 'stage' => 'repechage', 'round' => 1, 'position' => $side,
            'aka_id' => $losers[0], 'shiro_id' => $losers[1], 'status' => 'ready']);
        for ($i = 2; $i < count($losers); $i++) {
            $prev = insert('matches', ['category_id' => $cat['id'], 'stage' => 'repechage', 'round' => $i, 'position' => $side,
                'aka_src' => "W:$prev", 'shiro_id' => $losers[$i]]);
        }
    }
}

/** Kuriuos sportininkus finalininkas nugalėjo iki finalo (nuo pirmo rato) */
function repechage_losers(int $categoryId, int $finalist, int $rounds): array
{
    $out = [];
    foreach (q_all('SELECT * FROM matches WHERE category_id = ? AND stage = "main" AND round < ? AND winner_id = ? AND result <> "bye" ORDER BY round',
        [$categoryId, $rounds, $finalist]) as $m) {
        $l = loser_of($m);
        if ($l) {
            $out[] = $l;
        }
    }
    return $out;
}

function update_category_status(array $cat): void
{
    if ($cat['status'] === 'open') {
        return;
    }
    if ($cat['format'] === 'scores') {
        $status = score_category_finished($cat) ? 'finished'
            : (q_value('SELECT 1 FROM performances WHERE category_id = ? AND status IN ("done","dq","running") LIMIT 1', [$cat['id']]) ? 'running' : 'drawn');
    } else {
        $left = (int) q_value('SELECT COUNT(*) FROM matches WHERE category_id = ? AND status <> "done"', [$cat['id']]);
        $played = (int) q_value('SELECT COUNT(*) FROM matches WHERE category_id = ? AND ((status = "done" AND result <> "bye") OR status = "running")', [$cat['id']]);
        $status = $left === 0 ? 'finished' : ($played ? 'running' : 'drawn');
    }
    if ($status !== $cat['status']) {
        q('UPDATE categories SET status = ? WHERE id = ?', [$status, $cat['id']]);
    }
}

/** Taškų lentelės išsaugojimas kovos metu (žiūrovai mato gyvai) */
function save_match_score(int $id, array $score): array
{
    $m = match_or_404($id);
    if ($m['status'] === 'waiting') {
        fail('Kova dar neturi abiejų dalyvių.');
    }
    if ($m['status'] === 'done') {
        fail('Kova jau baigta. Norėdami taisyti, pirma atšaukite rezultatą.');
    }
    $score = normalize_score($score);
    $row = ['score_json' => json_encode($score, JSON_UNESCAPED_UNICODE), 'status' => 'running'];
    if (!$m['started_at']) {
        $row['started_at'] = now();
    }
    update('matches', $row, 'id = ?', [$id]);
    $cat = category_or_404((int) $m['category_id']);
    update_category_status($cat);
    bump_rev((int) $cat['tournament_id']);
    return match_or_404($id);
}

/** Saugomas tik žinomas taškų lentelės turinys; laikmatis gauna serverio laiką */
function normalize_score(array $s): array
{
    $side = function ($x): array {
        $x = is_array($x) ? $x : [];
        return [
            'ippon'    => max(0, min(20, (int) ($x['ippon'] ?? 0))),
            'wazaari'  => max(0, min(40, (int) ($x['wazaari'] ?? 0))),
            'penalties' => array_values(array_filter(array_map('strval', (array) ($x['penalties'] ?? [])),
                fn ($p) => in_array($p, ['keikoku', 'chui', 'hansoku', 'shikkaku', 'jogai'], true))),
            'flags'    => max(0, min(9, (int) ($x['flags'] ?? 0))),
            'kata_flags' => max(0, min(9, (int) ($x['kata_flags'] ?? 0))),
            'bouts'    => max(0, min(9, (int) ($x['bouts'] ?? 0))),
            'points'   => max(0, min(99, (int) ($x['points'] ?? 0))),
        ];
    };
    $out = ['aka' => $side($s['aka'] ?? []), 'shiro' => $side($s['shiro'] ?? [])];
    $t = $s['timer'] ?? null;
    if (is_array($t)) {
        $out['timer'] = [
            'duration'  => max(0, (int) ($t['duration'] ?? 0)),
            'remaining' => max(0, (int) ($t['remaining'] ?? 0)),
            'running'   => !empty($t['running']),
            'server_at' => (int) round(microtime(true) * 1000),
        ];
    }
    if (isset($s['phase']) && in_array($s['phase'], ['kata', 'kumite'], true)) {
        $out['phase'] = $s['phase'];
    }
    if (isset($s['log']) && is_array($s['log'])) {
        $out['log'] = array_slice(array_values(array_filter($s['log'], 'is_string')), -60);
    }
    return $out;
}

/** Kovos pabaiga. side: aka | shiro | draw (lygiosios tik grupėse) */
function finish_match(int $id, string $side, string $result, ?array $score = null): void
{
    $m = match_or_404($id);
    if (!in_array($m['status'], ['ready', 'running'], true)) {
        fail($m['status'] === 'done' ? 'Kova jau baigta.' : 'Kova dar neturi abiejų dalyvių.');
    }
    if (!isset(RESULT_LABELS[$result]) || $result === 'bye') {
        fail('Nežinomas rezultato tipas.');
    }
    if ($side === 'draw') {
        if ($m['stage'] !== 'pool') {
            fail('Lygiosios galimos tik grupėje. Atkrintamosiose sprendžia hantei.');
        }
        $winner = null;
        $result = 'draw';
    } elseif ($side === 'aka' || $side === 'shiro') {
        $winner = (int) ($side === 'aka' ? $m['aka_id'] : $m['shiro_id']);
    } else {
        fail('Nurodykite laimėtoją.');
    }
    tx(function () use ($m, $winner, $result, $score) {
        $row = ['winner_id' => $winner, 'result' => $result, 'status' => 'done', 'finished_at' => now()];
        if ($score !== null) {
            $sc = normalize_score($score);
            if (isset($sc['timer'])) {
                $sc['timer']['running'] = false;
            }
            $row['score_json'] = json_encode($sc, JSON_UNESCAPED_UNICODE);
        }
        if (!$m['started_at']) {
            $row['started_at'] = now();
        }
        update('matches', $row, 'id = ?', [$m['id']]);
        propagate((int) $m['id']);
        $cat = category_or_404((int) $m['category_id']);
        bump_rev((int) $cat['tournament_id']);
    });
}

/** Rezultato atšaukimas (jei dar nepradėtos kovos, kurios nuo jo priklauso) */
function reset_match(int $id): void
{
    $m = match_or_404($id);
    if ($m['status'] !== 'done' || $m['result'] === 'bye') {
        fail('Šios kovos rezultato atšaukti negalima.');
    }
    tx(function () use ($m) {
        unfill_dependents((int) $m['id']);
        if ($m['stage'] === 'main') {
            $cat = category_or_404((int) $m['category_id']);
            if ($cat['third_place'] === 'repechage') {
                $busy = q_value('SELECT id FROM matches WHERE category_id = ? AND stage = "repechage" AND (status = "running" OR (status = "done" AND result <> "bye")) LIMIT 1', [$m['category_id']]);
                if ($busy) {
                    fail('Repechage kovos jau vyksta. Pirma atšaukite jų rezultatus.');
                }
                q('DELETE FROM matches WHERE category_id = ? AND stage = "repechage"', [$m['category_id']]);
            }
        }
        update('matches', ['winner_id' => null, 'result' => null, 'status' => 'ready', 'finished_at' => null], 'id = ?', [$m['id']]);
        $cat = category_or_404((int) $m['category_id']);
        update_category_status($cat);
        if ($cat['status'] === 'finished') {
            q('UPDATE categories SET status = "running" WHERE id = ?', [$cat['id']]);
        }
        bump_rev((int) $cat['tournament_id']);
    });
}

/** Išima dalyvį iš kovų, kurios laukė šios kovos rezultato */
function unfill_dependents(int $matchId): void
{
    foreach (q_all('SELECT * FROM matches WHERE aka_src IN (?, ?) OR shiro_src IN (?, ?)', ["W:$matchId", "L:$matchId", "W:$matchId", "L:$matchId"]) as $d) {
        if ($d['status'] === 'running' || ($d['status'] === 'done' && $d['result'] !== 'bye')) {
            fail('Pirma atšaukite tolesnės kovos (' . match_label($d, category_rounds((int) $d['category_id'])) . ') rezultatą.');
        }
        if ($d['status'] === 'done') {
            unfill_dependents((int) $d['id']);
        }
        $row = ['status' => 'waiting', 'winner_id' => null, 'result' => null, 'finished_at' => null];
        if (in_array($d['aka_src'], ["W:$matchId", "L:$matchId"], true)) {
            $row['aka_id'] = null;
        }
        if (in_array($d['shiro_src'], ["W:$matchId", "L:$matchId"], true)) {
            $row['shiro_id'] = null;
        }
        update('matches', $row, 'id = ?', [$d['id']]);
    }
}

function match_label(array $m, int $rounds): string
{
    switch ($m['stage']) {
        case 'bronze':
            return 'Kova dėl 3 vietos';
        case 'repechage':
            return 'Repechage ' . ((int) $m['position'] === 0 ? 'A' : 'B') . ', ' . $m['round'] . ' kova';
        case 'pool':
            return $m['round'] . ' ratas';
    }
    $left = $rounds - (int) $m['round'];
    if ($left === 0) {
        return 'Finalas';
    }
    if ($left === 1) {
        return 'Pusfinalis';
    }
    if ($left === 2) {
        return 'Ketvirtfinalis';
    }
    return '1/' . (2 ** $left) . ' finalo';
}

/** Rikiavimo raktas: pirmi ratai, tada repechage ir kova dėl 3 vietos, finalas paskutinis */
function match_sort_key(array $m, int $rounds): float
{
    $r = (int) $m['round'];
    switch ($m['stage']) {
        case 'repechage':
            return $rounds - 0.5 + $r * 0.01 + (int) $m['position'] * 0.001;
        case 'bronze':
            return $rounds - 0.1;
        case 'pool':
            return $r + (int) $m['position'] * 0.001;
    }
    return $r + (int) $m['position'] * 0.0001;
}

function match_view(array $m, array $entries, int $rounds): array
{
    $side = function ($id, $src) use ($entries) {
        if (!$id) {
            return ['id' => null, 'name' => null, 'club' => null, 'src' => $src];
        }
        $e = $entries[(int) $id] ?? null;
        return ['id' => (int) $id, 'name' => $e['name'] ?? '?', 'club' => $e['club'] ?? null, 'src' => $src];
    };
    $winnerSide = null;
    if ($m['winner_id']) {
        $winnerSide = (int) $m['winner_id'] === (int) $m['aka_id'] ? 'aka' : 'shiro';
    } elseif ($m['result'] === 'draw') {
        $winnerSide = 'draw';
    }
    return [
        'id'          => (int) $m['id'],
        'category_id' => (int) $m['category_id'],
        'stage'       => $m['stage'],
        'round'       => (int) $m['round'],
        'position'    => (int) $m['position'],
        'label'       => match_label($m, $rounds),
        'aka'         => $side($m['aka_id'], $m['aka_src']),
        'shiro'       => $side($m['shiro_id'], $m['shiro_src']),
        'winner'      => $winnerSide,
        'status'      => $m['status'],
        'result'      => $m['result'],
        'result_label' => $m['result'] ? RESULT_LABELS[$m['result']] : null,
        'score'       => $m['score_json'] ? json_decode($m['score_json'], true) : null,
        'started_at'  => $m['started_at'],
        'finished_at' => $m['finished_at'],
        'sort'        => match_sort_key($m, $rounds),
    ];
}

/** Visos kategorijos kovos (surikiuotos) */
function category_matches(int $categoryId, ?array $entries = null): array
{
    $entries = $entries ?? category_entries($categoryId);
    $rounds = category_rounds($categoryId);
    $list = array_map(fn ($m) => match_view($m, $entries, $rounds), q_all('SELECT * FROM matches WHERE category_id = ?', [$categoryId]));
    usort($list, fn ($a, $b) => $a['sort'] <=> $b['sort']);
    return $list;
}
