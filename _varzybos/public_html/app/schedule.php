<?php
// Tvarkaraštis pagal tatami ir "kas dabar / kas toliau" eilė

/** Kiek kovų / atlikimų bus kategorijoje (įvertis, kol burtai neištraukti) */
function category_item_counts(array $cat): array
{
    $n = (int) q_value('SELECT COUNT(*) FROM entries WHERE category_id = ? AND status = "active"', [$cat['id']]);
    if ($cat['format'] === 'scores') {
        if ($cat['status'] === 'open') {
            $total = array_sum(score_round_plan($cat, $n));
            return ['total' => $n ? $total : 0, 'done' => 0];
        }
        $s = score_state($cat);
        $total = 0;
        foreach ($s['plan'] as $i => $size) {
            $r = $i + 1;
            $total += $r <= $s['current'] ? (int) q_value('SELECT COUNT(*) FROM performances WHERE category_id = ? AND round = ?', [$cat['id'], $r]) : $size;
        }
        $done = (int) q_value('SELECT COUNT(*) FROM performances WHERE category_id = ? AND status IN ("done","dq")', [$cat['id']]);
        return ['total' => $total, 'done' => $done];
    }
    if ($cat['format'] === 'round_robin') {
        if ($cat['status'] === 'open') {
            return ['total' => intdiv($n * ($n - 1), 2), 'done' => 0];
        }
    } elseif ($cat['status'] === 'open') {
        if ($n < 2) {
            return ['total' => 0, 'done' => 0];
        }
        $rounds = (int) ceil(log(bracket_size($n), 2));
        $extra = $cat['third_place'] === 'match' && $rounds >= 2 ? 1 : ($cat['third_place'] === 'repechage' ? max(0, 2 * ($rounds - 2)) : 0);
        return ['total' => $n - 1 + $extra, 'done' => 0];
    }
    $total = (int) q_value('SELECT COUNT(*) FROM matches WHERE category_id = ? AND NOT (status = "done" AND result = "bye")', [$cat['id']]);
    $done = (int) q_value('SELECT COUNT(*) FROM matches WHERE category_id = ? AND status = "done" AND result <> "bye"', [$cat['id']]);
    if ($cat['third_place'] === 'repechage' && !q_value('SELECT 1 FROM matches WHERE category_id = ? AND stage = "repechage" LIMIT 1', [$cat['id']])) {
        $total += max(0, 2 * (category_rounds((int) $cat['id']) - 2));
    }
    return ['total' => $total, 'done' => $done];
}

function hm_to_min(string $hm): int
{
    [$h, $m] = array_map('intval', explode(':', substr($hm, 0, 5)));
    return $h * 60 + $m;
}

function min_to_hm(int $min): string
{
    $min = max(0, $min);
    return sprintf('%02d:%02d', intdiv($min, 60) % 24, $min % 60);
}

/**
 * Tvarkaraštis: kiekvienam tatami - kategorijų eilė su numatomu pradžios ir pabaigos laiku.
 * Laikas skaičiuojamas nuo varžybų pradžios; jau sužaistos kovos neįskaičiuojamos (vyksta - perskaičiuojama nuo dabar).
 */
function tournament_schedule(array $t): array
{
    $cats = q_all('SELECT * FROM categories WHERE tournament_id = ? ORDER BY sort_order, id', [$t['id']]);
    $dayStart = hm_to_min((string) $t['day_start']);
    $isToday = $t['starts_on'] === date('Y-m-d');
    $nowMin = (int) date('G') * 60 + (int) date('i');
    $tatami = [];
    for ($i = 1; $i <= (int) $t['tatami_count']; $i++) {
        $tatami[$i] = ['tatami' => $i, 'categories' => [], 'end' => null, 'minutes' => 0];
    }
    $unassigned = [];
    $clock = [];
    foreach ($cats as $c) {
        $counts = category_item_counts($c);
        $per = minutes_per_item($c);
        $left = max(0, $counts['total'] - $counts['done']);
        $view = category_view($c) + [
            'items_total' => $counts['total'],
            'items_done'  => $counts['done'],
            'minutes'     => (int) ceil($counts['total'] * $per),
        ];
        if (!$c['tatami'] || !isset($tatami[(int) $c['tatami']])) {
            $unassigned[] = $view;
            continue;
        }
        $n = (int) $c['tatami'];
        $start = $clock[$n] ?? $dayStart;
        if ($c['not_before']) {
            $start = max($start, hm_to_min($c['not_before']));
        }
        if ($c['status'] === 'running' && $isToday) {
            // Vyksta: likusios kovos skaičiuojamos nuo dabar
            $end = max($nowMin, $start) + (int) ceil($left * $per);
        } else {
            $end = $start + (int) ceil($counts['total'] * $per);
        }
        $view['est_start'] = min_to_hm($start);
        $view['est_end'] = min_to_hm($end);
        $clock[$n] = $end;
        $tatami[$n]['categories'][] = $view;
        $tatami[$n]['end'] = min_to_hm($end);
        $tatami[$n]['minutes'] += $view['minutes'];
    }
    return ['tatami' => array_values($tatami), 'unassigned' => $unassigned, 'day_start' => min_to_hm($dayStart)];
}

/** Automatiškai paskirsto kategorijas tatami (ilgiausios pirmos į mažiausiai užimtą tatami), išlaikant eilę */
function auto_assign_tatami(int $tid): void
{
    $t = tournament_or_404($tid);
    $count = (int) $t['tatami_count'];
    $cats = q_all('SELECT * FROM categories WHERE tournament_id = ? ORDER BY sort_order, id', [$tid]);
    $dur = [];
    foreach ($cats as $c) {
        $counts = category_item_counts($c);
        $dur[$c['id']] = $counts['total'] * minutes_per_item($c);
    }
    $load = array_fill(1, $count, 0.0);
    // Jaunesnių kategorijos anksčiau, ta pati disciplina kartu
    usort($cats, fn ($a, $b) => [(int) $a['age_max'] ?: 99, $a['discipline'], (int) $a['age_min'], $a['gender']] <=> [(int) $b['age_max'] ?: 99, $b['discipline'], (int) $b['age_min'], $b['gender']]);
    $order = [];
    foreach ($cats as $c) {
        if ($dur[$c['id']] <= 0) {
            $tat = null;
        } else {
            asort($load);
            $tat = (int) array_key_first($load);
            $load[$tat] += $dur[$c['id']];
        }
        $order[] = [$c['id'], $tat];
    }
    tx(function () use ($order, $tid) {
        foreach ($order as $i => [$id, $tat]) {
            q('UPDATE categories SET tatami = ?, sort_order = ? WHERE id = ?', [$tat, ($i + 1) * 10, $id]);
        }
        bump_rev($tid);
    });
}

/** Rankinis eilės keitimas: [{id, tatami, sort_order}, ...] */
function save_schedule(int $tid, array $items): void
{
    tournament_or_404($tid);
    tx(function () use ($tid, $items) {
        foreach ($items as $it) {
            $tat = int_or_null($it['tatami'] ?? null);
            q('UPDATE categories SET tatami = ?, sort_order = ?, not_before = ? WHERE id = ? AND tournament_id = ?', [
                $tat && $tat > 0 ? $tat : null,
                (int) ($it['sort_order'] ?? 0),
                valid_time($it['not_before'] ?? null) ? $it['not_before'] : null,
                (int) ($it['id'] ?? 0), $tid,
            ]);
        }
        bump_rev($tid);
    });
}

/**
 * Kas vyksta ant kiekvieno tatami: dabartinė kova / atlikimas ir kelios kitos.
 * Naudoja žiūrovų ekranas ir sekretorius.
 */
function tatami_queue(array $t, ?int $only = null, int $nextCount = 4): array
{
    $cats = q_all('SELECT * FROM categories WHERE tournament_id = ? AND tatami IS NOT NULL AND status IN ("drawn","running")'
        . ($only ? ' AND tatami = ' . (int) $only : '') . ' ORDER BY tatami, sort_order, id', [$t['id']]);
    $out = [];
    for ($i = 1; $i <= (int) $t['tatami_count']; $i++) {
        if (!$only || $only === $i) {
            $out[$i] = ['tatami' => $i, 'current' => [], 'next' => []];
        }
    }
    foreach ($cats as $c) {
        $n = (int) $c['tatami'];
        if (!isset($out[$n])) {
            continue;
        }
        $entries = category_entries((int) $c['id']);
        $catInfo = ['id' => (int) $c['id'], 'name' => $c['name'], 'format' => $c['format'], 'match_type' => $c['match_type'],
                    'discipline' => $c['discipline'], 'kumite_target' => KUMITE_RULES[$c['kumite_rule']]['target'],
                    'judges' => (int) $c['judges'], 'match_seconds' => (int) $c['match_seconds']];
        if ($c['format'] === 'scores') {
            $s = score_state($c);
            $perf = q_all('SELECT * FROM performances WHERE category_id = ? AND round = ? AND status IN ("waiting","running") ORDER BY order_no', [$c['id'], $s['current']]);
            foreach ($perf as $p) {
                $e = $entries[(int) $p['entry_id']] ?? null;
                $item = ['type' => 'performance', 'id' => (int) $p['id'], 'category' => $catInfo,
                    'label' => round_name($s['current'], $s['rounds']) . ' · ' . $p['order_no'] . '/' . (int) q_value('SELECT COUNT(*) FROM performances WHERE category_id = ? AND round = ?', [$c['id'], $s['current']]),
                    'entry' => ['id' => (int) $p['entry_id'], 'name' => $e['name'] ?? '?', 'club' => $e['club'] ?? null],
                    'status' => $p['status'], 'kata' => $p['kata']];
                if ($p['status'] === 'running') {
                    $out[$n]['current'][] = $item;
                } else {
                    $out[$n]['next'][] = $item;
                }
            }
            continue;
        }
        foreach (category_matches((int) $c['id'], $entries) as $m) {
            if ($m['status'] === 'running') {
                $out[$n]['current'][] = ['type' => 'match', 'category' => $catInfo] + $m;
            } elseif ($m['status'] === 'ready') {
                $out[$n]['next'][] = ['type' => 'match', 'category' => $catInfo] + $m;
            }
        }
    }
    foreach ($out as &$o) {
        $o['next_total'] = count($o['next']);
        $o['next'] = array_slice($o['next'], 0, $nextCount);
    }
    unset($o);
    return array_values($out);
}
