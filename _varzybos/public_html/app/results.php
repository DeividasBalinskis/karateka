<?php
// Vietos kategorijose, grupių lentelės, medalių lentelė

/**
 * Kategorijos vietos: [['place' => 1, 'entry_id' => .., 'medal' => true], ...].
 * Grąžina tik jau nustatytas vietas (kategorijai dar vykstant - dalinės).
 */
function category_places(array $cat): array
{
    switch ($cat['format']) {
        case 'round_robin':
            return pool_places($cat);
        case 'scores':
            return score_places($cat);
        default:
            return bracket_places($cat);
    }
}

function bracket_places(array $cat): array
{
    $rounds = category_rounds((int) $cat['id']);
    if (!$rounds) {
        return [];
    }
    $matches = q_all('SELECT * FROM matches WHERE category_id = ?', [$cat['id']]);
    $places = [];
    $set = function (?int $entry, int $place) use (&$places) {
        if ($entry && !isset($places[$entry])) {
            $places[$entry] = $place;
        }
    };
    $main = array_filter($matches, fn ($m) => $m['stage'] === 'main');
    $final = current(array_filter($main, fn ($m) => (int) $m['round'] === $rounds));
    if ($final && $final['status'] === 'done') {
        $set((int) $final['winner_id'], 1);
        $set(loser_of($final), 2);
    }
    $semiLosers = [];
    foreach ($main as $m) {
        if ((int) $m['round'] === $rounds - 1 && $m['status'] === 'done' && $m['result'] !== 'bye') {
            $semiLosers[] = loser_of($m);
        }
    }
    switch ($cat['third_place']) {
        case 'match':
            foreach ($matches as $m) {
                if ($m['stage'] === 'bronze' && $m['status'] === 'done') {
                    $set($m['winner_id'] ? (int) $m['winner_id'] : null, 3);
                    $set(loser_of($m), 4);
                }
            }
            break;
        case 'repechage':
            if ($final && $final['aka_id'] && $final['shiro_id']) {
                foreach ([(int) $final['aka_id'], (int) $final['shiro_id']] as $side => $finalist) {
                    $ladder = array_values(array_filter($matches, fn ($m) => $m['stage'] === 'repechage' && (int) $m['position'] === $side));
                    usort($ladder, fn ($a, $b) => $a['round'] <=> $b['round']);
                    if (!$ladder) {
                        $losers = repechage_losers((int) $cat['id'], $finalist, $rounds);
                        if (count($losers) === 1) {
                            $set($losers[0], 3);
                        }
                        continue;
                    }
                    $last = end($ladder);
                    if ($last['status'] === 'done') {
                        $set((int) $last['winner_id'], 3);
                        $set(loser_of($last), 5);
                    }
                    foreach ($ladder as $m) {
                        if ($m['status'] === 'done') {
                            $set(loser_of($m), 7);
                        }
                    }
                }
            }
            break;
        default:   // two_bronze, none
            foreach ($semiLosers as $l) {
                $set($l, 3);
            }
    }
    // Kiti - pagal ratą, kuriame iškrito (ketvirtfinalis - 5, 1/8 - 9 ...)
    foreach ($main as $m) {
        if ($m['status'] === 'done' && $m['result'] !== 'bye' && (int) $m['round'] < $rounds - 1) {
            $place = 2 ** ($rounds - (int) $m['round']) + 1;
            // Su repechage 5 vieta atitenka repechage finalų pralaimėjusiems, kiti iškritę - žemiau
            $set(loser_of($m), $cat['third_place'] === 'repechage' ? max(7, $place) : $place);
        }
    }
    asort($places);
    $out = [];
    foreach ($places as $entry => $place) {
        $medal = $place <= 3 && ($place < 3 || $cat['third_place'] !== 'none');
        $out[] = ['place' => $place, 'entry_id' => (int) $entry, 'medal' => $medal];
    }
    return $out;
}

/** Grupės lentelė: pergalė 2 tšk., lygiosios 1 tšk. Lygūs: tarpusavio kova, ippon, waza-ari. */
function pool_standings(array $cat, array $entries): array
{
    $rows = [];
    foreach ($entries as $e) {
        if ($e['status'] === 'active') {
            $rows[$e['id']] = ['entry_id' => $e['id'], 'name' => $e['name'], 'club' => $e['club'],
                'played' => 0, 'won' => 0, 'draw' => 0, 'lost' => 0, 'points' => 0, 'ippon' => 0, 'wazaari' => 0];
        }
    }
    $h2h = [];
    $matches = q_all('SELECT * FROM matches WHERE category_id = ? AND stage = "pool"', [$cat['id']]);
    $allDone = true;
    foreach ($matches as $m) {
        if ($m['status'] !== 'done') {
            $allDone = false;
            continue;
        }
        $a = (int) $m['aka_id'];
        $s = (int) $m['shiro_id'];
        if (!isset($rows[$a], $rows[$s])) {
            continue;
        }
        $sc = $m['score_json'] ? json_decode($m['score_json'], true) : [];
        foreach (['aka' => $a, 'shiro' => $s] as $side => $id) {
            $rows[$id]['played']++;
            $rows[$id]['ippon'] += (int) ($sc[$side]['ippon'] ?? 0);
            $rows[$id]['wazaari'] += (int) ($sc[$side]['wazaari'] ?? 0);
        }
        if ($m['result'] === 'draw') {
            foreach ([$a, $s] as $id) {
                $rows[$id]['draw']++;
                $rows[$id]['points'] += 1;
            }
        } else {
            $w = (int) $m['winner_id'];
            $l = $w === $a ? $s : $a;
            $rows[$w]['won']++;
            $rows[$w]['points'] += 2;
            $rows[$l]['lost']++;
            $h2h[$w][$l] = true;
        }
    }
    $list = array_values($rows);
    usort($list, function ($x, $y) use ($h2h) {
        if ($x['points'] !== $y['points']) {
            return $y['points'] <=> $x['points'];
        }
        if (!empty($h2h[$x['entry_id']][$y['entry_id']])) {
            return -1;
        }
        if (!empty($h2h[$y['entry_id']][$x['entry_id']])) {
            return 1;
        }
        return [$y['ippon'], $y['wazaari']] <=> [$x['ippon'], $x['wazaari']];
    });
    $rank = 0;
    $prev = null;
    foreach ($list as $i => &$r) {
        $key = [$r['points'], $r['ippon'], $r['wazaari']];
        $beatsPrev = $i > 0 && !empty($h2h[$r['entry_id']][$list[$i - 1]['entry_id']]);
        $lostToPrev = $i > 0 && !empty($h2h[$list[$i - 1]['entry_id']][$r['entry_id']]);
        if ($key !== $prev || $lostToPrev || $beatsPrev) {
            $rank = $i + 1;
        }
        $prev = $key;
        $r['rank'] = $rank;
    }
    unset($r);
    return ['rows' => $list, 'complete' => $allDone && $matches];
}

function pool_places(array $cat): array
{
    $st = pool_standings($cat, category_entries((int) $cat['id'], true));
    if (!$st['complete']) {
        return [];
    }
    return array_map(fn ($r) => ['place' => $r['rank'], 'entry_id' => $r['entry_id'], 'medal' => $r['rank'] <= 3], $st['rows']);
}

function score_places(array $cat): array
{
    $s = score_state($cat);
    if (!$s['current']) {
        return [];
    }
    return renumber_score_places($cat, $s);
}

/** Nuo paskutinio rato žemyn: finalininkai, tada iškritusieji ankstesniuose ratuose (lygūs dalijasi vieta) */
function renumber_score_places(array $cat, array $s): array
{
    $finalDone = $s['current'] >= $s['rounds'] && $s['left'] === 0;
    $out = [];
    $placed = [];
    $base = 0;
    for ($r = $s['current']; $r >= 1; $r--) {
        $ranking = round_ranking($cat, $r);
        $ids = array_map(fn ($p) => (int) $p['entry_id'], $ranking);
        if ($r === $s['current'] && !$finalDone) {
            foreach ($ids as $id) {
                $placed[$id] = true;
            }
            $base += count($ids);
            continue;
        }
        $rest = array_values(array_filter($ranking, fn ($p) => !isset($placed[(int) $p['entry_id']])));
        // vieta rato viduje tarp likusių (lygūs - ta pati)
        $rank = 0;
        $prevKey = null;
        foreach ($rest as $i => $p) {
            $key = [$p['_total'], $p['_min'], $p['_max'], $p['_manual']];
            if ($key !== $prevKey) {
                $rank = $i + 1;
                $prevKey = $key;
            }
            $place = $base + $rank;
            if ($p['status'] === 'dq' && $r === $s['current']) {
                $place = $base + count($rest);
            }
            $out[] = ['place' => $place, 'entry_id' => (int) $p['entry_id'], 'medal' => $place <= 3 && $finalDone && $r === $s['current']];
            $placed[(int) $p['entry_id']] = true;
        }
        $base += count($rest);
    }
    usort($out, fn ($a, $b) => $a['place'] <=> $b['place']);
    return $out;
}

/** Vietos su vardais, klubais ir išoriniais id (karateka.lt) */
function category_results(array $cat, ?array $entries = null): array
{
    $entries = $entries ?? category_entries((int) $cat['id']);
    $out = [];
    foreach (category_places($cat) as $p) {
        $e = $entries[$p['entry_id']] ?? null;
        if (!$e) {
            continue;
        }
        $people = $e['athlete_id']
            ? [['athlete_id' => $e['athlete_id'], 'name' => $e['name'], 'external_source' => $e['external']['source'] ?? null, 'external_id' => $e['external']['id'] ?? null]]
            : array_map(fn ($m) => ['athlete_id' => $m['id'], 'name' => $m['name'], 'external_source' => $m['external_source'], 'external_id' => $m['external_id']], $e['members']);
        $out[] = [
            'place'    => $p['place'],
            'medal'    => $p['medal'],
            'entry_id' => $p['entry_id'],
            'name'     => $e['name'],
            'club'     => $e['club'],
            'club_id'  => $e['club_id'],
            'athletes' => $people,
        ];
    }
    return $out;
}

/** Medalių lentelė pagal klubus */
function medal_table(int $tid): array
{
    $clubs = [];
    foreach (q_all('SELECT * FROM categories WHERE tournament_id = ?', [$tid]) as $cat) {
        foreach (category_results($cat) as $r) {
            if (!$r['medal'] || !$r['club_id']) {
                continue;
            }
            $id = $r['club_id'];
            $clubs[$id] = $clubs[$id] ?? ['club_id' => $id, 'club' => $r['club'], 'gold' => 0, 'silver' => 0, 'bronze' => 0];
            $key = [1 => 'gold', 2 => 'silver', 3 => 'bronze'][$r['place']] ?? null;
            if ($key) {
                $clubs[$id][$key]++;
            }
        }
    }
    $list = array_values($clubs);
    usort($list, fn ($a, $b) => [$b['gold'], $b['silver'], $b['bronze']] <=> [$a['gold'], $a['silver'], $a['bronze']]);
    return $list;
}
