<?php
// Burtai: lentelės, grupės ir pirmas balų ratas

/** Mažiausias 2 laipsnis, ne mažesnis už n */
function bracket_size(int $n): int
{
    $s = 2;
    while ($s < $n) {
        $s *= 2;
    }
    return $s;
}

/**
 * Standartinė "seed" tvarka: indeksas = vieta lentelėje, reikšmė = seed numeris.
 * 8 vietoms: [1, 8, 4, 5, 2, 7, 3, 6] - 1 ir 2 susitiktų tik finale.
 */
function seed_order(int $size): array
{
    $o = [1];
    while (count($o) < $size) {
        $m = count($o) * 2;
        $n = [];
        foreach ($o as $s) {
            $n[] = $s;
            $n[] = $m + 1 - $s;
        }
        $o = $n;
    }
    return $o;
}

/** Kuriame rate susitiktų vietų a ir b dalyviai (1 = pirmas ratas) */
function meet_round(int $a, int $b): int
{
    $x = $a ^ $b;
    $r = 0;
    while ($x) {
        $r++;
        $x >>= 1;
    }
    return $r;
}

/**
 * Išdėsto dalyvius lentelėje: [vieta => entry_id].
 * Seed'ai - į seed vietas. Kiti - kad to paties klubo nariai susitiktų kuo vėliau.
 * Laisvos vietos (bye) atitenka stipriausiems seed'ams, todėl pasiskirsto tolygiai.
 */
function place_entries(array $entries, int $size): array
{
    $order = seed_order($size);
    $slotOfSeed = [];
    foreach ($order as $slot => $seed) {
        $slotOfSeed[$seed] = $slot;
    }
    $n = count($entries);
    $free = [];
    for ($s = 1; $s <= $n; $s++) {
        $free[$slotOfSeed[$s]] = true;
    }
    $placed = [];
    $clubSlots = [];

    $seeded = array_values(array_filter($entries, fn ($e) => $e['seed'] !== null));
    usort($seeded, fn ($a, $b) => $a['seed'] <=> $b['seed']);
    $k = 1;
    foreach ($seeded as $e) {
        $slot = $slotOfSeed[$k++];
        $placed[$slot] = $e['id'];
        unset($free[$slot]);
        $clubSlots[$e['club_id'] ?? 'none-' . $e['id']][] = $slot;
    }

    // Likę: didžiausi klubai pirmi, klubo viduje atsitiktinai
    $byClub = [];
    foreach ($entries as $e) {
        if ($e['seed'] === null) {
            $byClub[$e['club_id'] ?? 'none-' . $e['id']][] = $e;
        }
    }
    $clubs = shuffled(array_keys($byClub));
    usort($clubs, fn ($a, $b) => count($byClub[$b]) <=> count($byClub[$a]));
    foreach ($clubs as $club) {
        foreach (shuffled($byClub[$club]) as $e) {
            $best = [];
            $bestScore = -1;
            foreach (array_keys($free) as $slot) {
                $score = 99;
                foreach ($clubSlots[$club] ?? [] as $other) {
                    $score = min($score, meet_round($slot, $other));
                }
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = [$slot];
                } elseif ($score === $bestScore) {
                    $best[] = $slot;
                }
            }
            $slot = $best[array_rand($best)];
            $placed[$slot] = $e['id'];
            unset($free[$slot]);
            $clubSlots[$club][] = $slot;
        }
    }
    ksort($placed);
    return $placed;
}

function draw_category(int $categoryId): void
{
    $cat = category_or_404($categoryId);
    if ($cat['status'] !== 'open') {
        fail('Burtai jau ištraukti.');
    }
    $entries = array_values(category_entries($categoryId, true));
    $n = count($entries);
    if ($n < 1) {
        fail('Kategorijoje nėra dalyvių.');
    }
    if ($cat['format'] !== 'scores' && $n < 2) {
        fail('Lentelei reikia bent 2 dalyvių. Vienintelis dalyvis gali būti perkeltas į kitą kategoriją.');
    }
    tx(function () use ($cat, $entries) {
        switch ($cat['format']) {
            case 'round_robin':
                build_round_robin($cat, $entries);
                break;
            case 'scores':
                build_score_round($cat, 1, array_map(fn ($e) => $e['id'], shuffled($entries)));
                break;
            default:
                build_bracket($cat, $entries);
        }
        q('UPDATE categories SET status = "drawn" WHERE id = ?', [$cat['id']]);
        bump_rev((int) $cat['tournament_id']);
    });
}

/** Panaikina burtus (visos kovos ir balai ištrinami) */
function undo_draw(int $categoryId): void
{
    $cat = category_or_404($categoryId);
    tx(function () use ($cat) {
        q('DELETE FROM matches WHERE category_id = ?', [$cat['id']]);
        q('DELETE FROM performances WHERE category_id = ?', [$cat['id']]);
        q('UPDATE categories SET status = "open" WHERE id = ?', [$cat['id']]);
        bump_rev((int) $cat['tournament_id']);
    });
}

function build_bracket(array $cat, array $entries): void
{
    $n = count($entries);
    $size = bracket_size($n);
    $rounds = (int) log($size, 2);
    $placed = place_entries($entries, $size);

    // Kovos: pirmas ratas iš vietų, kiti - iš ankstesnių kovų laimėtojų
    $ids = [];
    for ($r = 1; $r <= $rounds; $r++) {
        $count = $size >> $r;
        for ($p = 0; $p < $count; $p++) {
            if ($r === 1) {
                $aka = $placed[2 * $p] ?? null;
                $shiro = $placed[2 * $p + 1] ?? null;
                $row = ['category_id' => $cat['id'], 'stage' => 'main', 'round' => 1, 'position' => $p,
                        'aka_id' => $aka, 'shiro_id' => $shiro,
                        'aka_src' => $aka ? 'E' : 'BYE', 'shiro_src' => $shiro ? 'E' : 'BYE'];
            } else {
                $row = ['category_id' => $cat['id'], 'stage' => 'main', 'round' => $r, 'position' => $p,
                        'aka_src' => 'W:' . $ids[$r - 1][2 * $p], 'shiro_src' => 'W:' . $ids[$r - 1][2 * $p + 1]];
            }
            $ids[$r][$p] = insert('matches', $row);
        }
    }

    if ($cat['third_place'] === 'match' && $rounds >= 2) {
        insert('matches', ['category_id' => $cat['id'], 'stage' => 'bronze', 'round' => $rounds, 'position' => 0,
            'aka_src' => 'L:' . $ids[$rounds - 1][0], 'shiro_src' => 'L:' . $ids[$rounds - 1][1]]);
    }

    // Kovos be priešininko (bye) - iš karto baigtos
    foreach ($ids[1] as $mid) {
        refresh_match($mid);
    }
}

/** Kiekvienas su kiekvienu (ratų metodas). Nelyginiam skaičiui kiekviename rate vienas ilsisi. */
function build_round_robin(array $cat, array $entries): void
{
    $ids = array_map(fn ($e) => $e['id'], shuffled($entries));
    if (count($ids) % 2) {
        $ids[] = null;
    }
    $n = count($ids);
    $pos = 0;
    for ($r = 0; $r < $n - 1; $r++) {
        for ($i = 0; $i < $n / 2; $i++) {
            $a = $ids[$i];
            $b = $ids[$n - 1 - $i];
            if ($a !== null && $b !== null) {
                // Kas antrame rate keičiamos spalvos, kad visi būtų ir aka, ir shiro
                [$aka, $shiro] = ($r % 2) ? [$b, $a] : [$a, $b];
                insert('matches', ['category_id' => $cat['id'], 'stage' => 'pool', 'round' => $r + 1, 'position' => $pos++,
                    'aka_id' => $aka, 'shiro_id' => $shiro, 'status' => 'ready']);
            }
        }
        // Pasukam visus, išskyrus pirmą
        $last = array_pop($ids);
        array_splice($ids, 1, 0, [$last]);
    }
}
