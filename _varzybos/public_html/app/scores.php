<?php
// Balais vertinamos kategorijos (kata, en-bu, komandinė kata): ratai, teisėjų balai, vietos

/** Ratų planas: kiek dalyvių kiekviename rate. "16,8" ir 20 dalyvių -> [20, 16, 8] */
function score_round_plan(array $cat, int $firstRoundSize): array
{
    $plan = [$firstRoundSize];
    foreach (array_filter(array_map('intval', explode(',', (string) $cat['score_rounds']))) as $t) {
        if ($t >= 1 && $t < end($plan)) {
            $plan[] = $t;
        }
    }
    return $plan;
}

function round_name(int $round, int $totalRounds): string
{
    if ($round === $totalRounds) {
        return $totalRounds === 1 ? 'Vienas ratas' : 'Finalas';
    }
    if ($round === $totalRounds - 1 && $totalRounds >= 3) {
        return 'Pusfinalis';
    }
    return $round === 1 ? 'Atranka' : $round . ' ratas';
}

function build_score_round(array $cat, int $round, array $entryIds): void
{
    foreach (array_values($entryIds) as $i => $eid) {
        insert('performances', ['category_id' => $cat['id'], 'round' => $round, 'entry_id' => $eid, 'order_no' => $i + 1]);
    }
}

/** Suma: kai teisėjų 5 ar daugiau - atmetamas didžiausias ir mažiausias balas */
function score_total(array $scores, int $judges): array
{
    $dropped = [];
    if ($judges >= 5 && count($scores) >= 5) {
        $max = array_keys($scores, max($scores))[0];
        $min = array_keys($scores, min($scores));
        $min = $min[0] === $max ? ($min[1] ?? $min[0]) : $min[0];
        if ($min === $max) {   // visi balai vienodi
            $min = $max === 0 ? 1 : 0;
        }
        $dropped = [$max, $min];
    }
    $sum = 0.0;
    foreach ($scores as $i => $s) {
        if (!in_array($i, $dropped, true)) {
            $sum += $s;
        }
    }
    return [round($sum, 1), $dropped];
}

function performance_or_404(int $id): array
{
    $p = q_one('SELECT * FROM performances WHERE id = ?', [$id]);
    if (!$p) {
        fail('Atlikimas nerastas.', 404);
    }
    return $p;
}

function save_performance(int $id, array $in): void
{
    $p = performance_or_404($id);
    $cat = category_or_404((int) $p['category_id']);
    $maxRound = (int) q_value('SELECT MAX(round) FROM performances WHERE category_id = ?', [$cat['id']]);
    if ((int) $p['round'] < $maxRound) {
        fail('Šis ratas jau baigtas - kitas ratas sudarytas. Norėdami taisyti, pirma panaikinkite kitą ratą.');
    }
    $row = ['kata' => str_or_null($in['kata'] ?? $p['kata'], 60), 'updated_at' => now()];
    $status = $in['status'] ?? 'done';
    if ($status === 'dq') {
        $row += ['status' => 'dq', 'total' => 0, 'scores' => null];
    } elseif ($status === 'running') {
        $row['status'] = 'running';
    } elseif ($status === 'waiting') {
        $row += ['status' => 'waiting', 'total' => null, 'scores' => null];
    } else {
        $scores = [];
        foreach ((array) ($in['scores'] ?? []) as $s) {
            $v = dec_or_null($s);
            if ($v === null) {
                fail('Įveskite visų teisėjų balus.');
            }
            if ($v < (float) $cat['score_min'] || $v > (float) $cat['score_max']) {
                fail('Balas turi būti nuo ' . $cat['score_min'] . ' iki ' . $cat['score_max'] . '.');
            }
            $scores[] = $v;
        }
        if (count($scores) !== (int) $cat['judges']) {
            fail('Reikia ' . $cat['judges'] . ' teisėjų balų.');
        }
        [$total] = score_total($scores, (int) $cat['judges']);
        $row += ['status' => 'done', 'scores' => implode(',', array_map(fn ($v) => number_format($v, 1, '.', ''), $scores)), 'total' => $total];
    }
    if (array_key_exists('manual_rank', $in)) {
        $row['manual_rank'] = int_or_null($in['manual_rank']);
    }
    tx(function () use ($id, $row, $cat) {
        // Vienu metu "vyksta" tik vienas atlikimas kategorijoje
        if (($row['status'] ?? '') === 'running') {
            q('UPDATE performances SET status = "waiting" WHERE category_id = ? AND status = "running" AND id <> ?', [$cat['id'], $id]);
        }
        update('performances', $row, 'id = ?', [$id]);
        update_category_status(category_or_404((int) $cat['id']));
        bump_rev((int) $cat['tournament_id']);
    });
}

function parse_scores(?string $s): array
{
    return $s ? array_map('floatval', explode(',', $s)) : [];
}

/**
 * Rato reitingas. Lygūs balai: lemia mažiausias, tada didžiausias atmestas balas,
 * tada sekretoriaus nurodyta vieta (po papildomo atlikimo / hantei).
 */
function round_ranking(array $cat, int $round): array
{
    $rows = q_all('SELECT * FROM performances WHERE category_id = ? AND round = ?', [$cat['id'], $round]);
    $carry = [];
    if ($cat['carry_scores'] && $round > 1) {
        foreach (q_all('SELECT entry_id, SUM(total) AS t FROM performances WHERE category_id = ? AND round < ? GROUP BY entry_id', [$cat['id'], $round]) as $r) {
            $carry[(int) $r['entry_id']] = (float) $r['t'];
        }
    }
    foreach ($rows as &$r) {
        $sc = parse_scores($r['scores']);
        $r['_total'] = $r['status'] === 'done' ? (float) $r['total'] + ($carry[(int) $r['entry_id']] ?? 0) : -1;
        $r['_min'] = $sc ? min($sc) : 0;
        $r['_max'] = $sc ? max($sc) : 0;
        $r['_manual'] = $r['manual_rank'] === null ? 999 : (int) $r['manual_rank'];
    }
    unset($r);
    $cmp = fn ($a, $b) => [$b['_total'], $b['_min'], $b['_max'], $a['_manual']] <=> [$a['_total'], $a['_min'], $a['_max'], $b['_manual']];
    usort($rows, $cmp);
    $rank = 0;
    $prev = null;
    foreach ($rows as $i => &$r) {
        $key = [$r['_total'], $r['_min'], $r['_max'], $r['_manual']];
        if ($key !== $prev) {
            $rank = $i + 1;
            $prev = $key;
        }
        $r['_rank'] = $r['status'] === 'done' ? $rank : null;
        $r['_tied'] = false;
    }
    unset($r);
    // Pažymim lygiąsias, kurias reikia išspręsti
    $byRank = [];
    foreach ($rows as $r) {
        if ($r['_rank'] !== null) {
            $byRank[$r['_rank']][] = (int) $r['id'];
        }
    }
    foreach ($rows as &$r) {
        $r['_tied'] = $r['_rank'] !== null && count($byRank[$r['_rank']]) > 1;
    }
    unset($r);
    return $rows;
}

function score_state(array $cat): array
{
    $first = (int) q_value('SELECT COUNT(*) FROM performances WHERE category_id = ? AND round = 1', [$cat['id']]);
    $plan = score_round_plan($cat, $first);
    $current = (int) q_value('SELECT COALESCE(MAX(round), 0) FROM performances WHERE category_id = ?', [$cat['id']]);
    $left = (int) q_value('SELECT COUNT(*) FROM performances WHERE category_id = ? AND round = ? AND status IN ("waiting","running")', [$cat['id'], $current]);
    return ['plan' => $plan, 'rounds' => count($plan), 'current' => $current, 'left' => $left];
}

function score_category_finished(array $cat): bool
{
    $s = score_state($cat);
    return $s['current'] > 0 && $s['current'] >= $s['rounds'] && $s['left'] === 0;
}

/** Sudaro kitą ratą iš geriausių. Lygybę ties riba pirma reikia išspręsti (papildomas atlikimas). */
function next_score_round(int $categoryId): void
{
    $cat = category_or_404($categoryId);
    $s = score_state($cat);
    if ($s['left'] > 0) {
        fail('Dar ne visi šio rato dalyviai įvertinti.');
    }
    if ($s['current'] >= $s['rounds']) {
        fail('Tai paskutinis ratas.');
    }
    $limit = $s['plan'][$s['current']];
    $ranking = round_ranking($cat, $s['current']);
    $groupSize = [];
    foreach ($ranking as $r) {
        if ($r['_rank'] !== null) {
            $groupSize[$r['_rank']] = ($groupSize[$r['_rank']] ?? 0) + 1;
        }
    }
    $tied = array_filter($ranking, fn ($r) => $r['_rank'] !== null && $r['_rank'] <= $limit && $r['_rank'] + $groupSize[$r['_rank']] - 1 > $limit);
    if ($tied) {
        fail('Ties riba lygūs balai: ' . implode(', ', array_map(fn ($r) => entry_name((int) $r['entry_id']), $tied)) . '. Surenkite papildomą atlikimą ir nurodykite vietas.');
    }
    $adv = array_filter($ranking, fn ($r) => $r['_rank'] !== null && $r['_rank'] <= $limit);
    // Kitame rate pirmi pasirodo tie, kurių balas mažesnis
    $adv = array_reverse(array_values($adv));
    tx(function () use ($cat, $s, $adv) {
        build_score_round($cat, $s['current'] + 1, array_map(fn ($r) => (int) $r['entry_id'], $adv));
        update_category_status(category_or_404((int) $cat['id']));
        bump_rev((int) $cat['tournament_id']);
    });
}

/** Panaikina paskutinį ratą (jei jame dar niekas neįvertintas) */
function undo_score_round(int $categoryId): void
{
    $cat = category_or_404($categoryId);
    $s = score_state($cat);
    if ($s['current'] <= 1) {
        fail('Pirmo rato panaikinti negalima - naudokite „Panaikinti burtus“.');
    }
    if (q_value('SELECT 1 FROM performances WHERE category_id = ? AND round = ? AND status IN ("done","dq") LIMIT 1', [$cat['id'], $s['current']])) {
        fail('Šiame rate jau yra įvertintų atlikimų.');
    }
    q('DELETE FROM performances WHERE category_id = ? AND round = ?', [$cat['id'], $s['current']]);
    update_category_status(category_or_404($categoryId));
    bump_rev((int) $cat['tournament_id']);
}

function entry_name(int $entryId): string
{
    $e = q_one('SELECT e.*, a.first_name, a.last_name FROM entries e LEFT JOIN athletes a ON a.id = e.athlete_id WHERE e.id = ?', [$entryId]);
    return $e ? ($e['athlete_id'] ? person_name($e) : ($e['team_name'] ?: 'Komanda #' . $entryId)) : '?';
}

function category_performances(array $cat, array $entries): array
{
    $s = score_state($cat);
    $rounds = [];
    for ($r = 1; $r <= $s['current']; $r++) {
        $ranking = round_ranking($cat, $r);
        $list = [];
        foreach ($ranking as $p) {
            $sc = parse_scores($p['scores']);
            [, $dropped] = $sc ? score_total($sc, (int) $cat['judges']) : [0, []];
            $e = $entries[(int) $p['entry_id']] ?? null;
            $list[] = [
                'id'       => (int) $p['id'],
                'entry'    => ['id' => (int) $p['entry_id'], 'name' => $e['name'] ?? '?', 'club' => $e['club'] ?? null],
                'order_no' => (int) $p['order_no'],
                'kata'     => $p['kata'],
                'scores'   => $sc,
                'dropped'  => $dropped,
                'total'    => $p['total'] === null ? null : (float) $p['total'],
                'combined' => $p['status'] === 'done' ? $p['_total'] : null,
                'status'   => $p['status'],
                'rank'     => $p['_rank'],
                'tied'     => $p['_tied'],
                'manual_rank' => $p['manual_rank'] === null ? null : (int) $p['manual_rank'],
            ];
        }
        usort($list, fn ($a, $b) => $a['order_no'] <=> $b['order_no']);
        $rounds[] = [
            'round'    => $r,
            'name'     => round_name($r, $s['rounds']),
            'advance'  => $s['plan'][$r] ?? null,
            'items'    => $list,
        ];
    }
    return ['rounds' => $rounds, 'plan' => $s['plan'], 'total_rounds' => $s['rounds'], 'current' => $s['current'], 'left' => $s['left']];
}
