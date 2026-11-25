<?php
// Kategorijos: kūrimas, tinkamumas (amžius, lytis, diržas, svoris), dalyviai

function category_or_404(int $id): array
{
    $c = q_one('SELECT * FROM categories WHERE id = ?', [$id]);
    if (!$c) {
        fail('Kategorija nerasta.', 404);
    }
    return $c;
}

function entry_type(array $cat): string
{
    return DISCIPLINE_ENTRY[$cat['discipline']] ?? 'individual';
}

function category_from_input(array $in, ?array $old = null): array
{
    $g = fn ($k, $d = null) => array_key_exists($k, $in) ? $in[$k] : ($old[$k] ?? $d);
    $row = [];
    $discipline = $g('discipline', 'kata');
    if (!isset(DISCIPLINES[$discipline])) {
        fail('Nežinoma disciplina.');
    }
    $row['discipline'] = $discipline;
    $defaults = default_format_for($discipline);
    $format = $g('format', $defaults['format']);
    $row['format'] = isset(FORMATS[$format]) ? $format : $defaults['format'];
    $mt = $g('match_type', $defaults['match_type']);
    $row['match_type'] = isset(MATCH_TYPES[$mt]) ? $mt : $defaults['match_type'];
    $gender = $g('gender', 'X');
    $row['gender'] = in_array($gender, ['M', 'F', 'X'], true) ? $gender : 'X';
    foreach (['age_min', 'age_max', 'belt_min', 'belt_max'] as $k) {
        $v = int_or_null($g($k));
        $row[$k] = $v === null ? null : max(0, min(99, $v));
    }
    foreach (['weight_min', 'weight_max'] as $k) {
        $row[$k] = dec_or_null($g($k));
    }
    $rule = $g('kumite_rule', 'ippon');
    $row['kumite_rule'] = isset(KUMITE_RULES[$rule]) ? $rule : 'ippon';
    $row['match_seconds'] = max(30, min(600, (int) $g('match_seconds', 120)));
    $judges = (int) $g('judges', 5);
    $row['judges'] = in_array($judges, [1, 3, 5, 7], true) ? $judges : 5;
    $tp = $g('third_place', 'two_bronze');
    $row['third_place'] = isset(THIRD_PLACE[$tp]) ? $tp : 'two_bronze';
    $sr = preg_replace('/[^0-9,]/', '', (string) $g('score_rounds', '8'));
    $row['score_rounds'] = $sr !== '' ? trim($sr, ',') : null;
    $row['score_min'] = dec_or_null($g('score_min', 5.0)) ?? 5.0;
    $row['score_max'] = dec_or_null($g('score_max', 9.9)) ?? 9.9;
    if ($row['score_min'] >= $row['score_max']) {
        fail('Mažiausias balas turi būti mažesnis už didžiausią.');
    }
    $row['carry_scores'] = !empty($g('carry_scores', 0)) ? 1 : 0;
    $tat = int_or_null($g('tatami'));
    $row['tatami'] = $tat && $tat > 0 ? $tat : null;
    $row['sort_order'] = (int) $g('sort_order', 0);
    $nb = $g('not_before');
    $row['not_before'] = valid_time($nb) ? $nb : null;

    $name = str_or_null($g('name'), 160);
    $row['name'] = $name ?? auto_category_name($row);
    return $row;
}

/** Pavadinimas, jei neįvestas: "Kata · Vaikinai 10–11 m." */
function auto_category_name(array $c): string
{
    $parts = [DISCIPLINES[$c['discipline']]];
    $who = ['M' => 'Vyrai', 'F' => 'Moterys', 'X' => 'Mišri'][$c['gender']];
    if ($c['age_max'] !== null && $c['age_max'] < 18) {
        $who = ['M' => 'Berniukai', 'F' => 'Mergaitės', 'X' => 'Vaikai'][$c['gender']];
    }
    $age = '';
    if ($c['age_min'] !== null && $c['age_max'] !== null) {
        $age = $c['age_min'] === $c['age_max'] ? "{$c['age_min']} m." : "{$c['age_min']}–{$c['age_max']} m.";
    } elseif ($c['age_min'] !== null) {
        $age = "{$c['age_min']}+ m.";
    } elseif ($c['age_max'] !== null) {
        $age = "iki {$c['age_max']} m.";
    }
    $s = $parts[0] . ' · ' . trim("$who $age");
    if ($c['weight_max'] !== null) {
        $s .= ' · −' . rtrim(rtrim(number_format((float) $c['weight_max'], 1, '.', ''), '0'), '.') . ' kg';
    } elseif ($c['weight_min'] !== null) {
        $s .= ' · +' . rtrim(rtrim(number_format((float) $c['weight_min'], 1, '.', ''), '0'), '.') . ' kg';
    }
    if ($c['belt_min'] !== null || $c['belt_max'] !== null) {
        $s .= ' · ' . belt_range_label($c['belt_min'], $c['belt_max']);
    }
    return $s;
}

function belt_short(?int $lvl): string
{
    return $lvl && isset(BELTS[$lvl]) ? BELTS[$lvl][0] : '';
}

function belt_range_label($min, $max): string
{
    $min = $min === null ? null : (int) $min;
    $max = $max === null ? null : (int) $max;
    if ($min !== null && $max !== null) {
        return $min === $max ? belt_short($min) : belt_short($min) . '–' . belt_short($max);
    }
    return $min !== null ? belt_short($min) . ' ir aukščiau' : 'iki ' . belt_short($max);
}

function create_category(int $tid, array $in): int
{
    tournament_or_404($tid);
    $row = category_from_input($in);
    $row['tournament_id'] = $tid;
    if (!isset($in['sort_order'])) {
        $row['sort_order'] = (int) q_value('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM categories WHERE tournament_id = ?', [$tid]);
    }
    $id = insert('categories', $row);
    bump_rev($tid);
    return $id;
}

function update_category(int $id, array $in): void
{
    $old = category_or_404($id);
    // Automatinis pavadinimas atnaujinamas kartu su amžiumi, lytimi ir pan. (jei nebuvo pakeistas ranka)
    if (($in['name'] ?? $old['name']) === $old['name'] && $old['name'] === auto_category_name(category_from_input([], $old) + ['name' => ''])) {
        $in['name'] = null;
    }
    $row = category_from_input($in, $old);
    if ($old['status'] !== 'open') {
        // Po burtų negalima keisti to, kas pakeistų lentelę
        foreach (['format', 'match_type', 'third_place', 'score_rounds', 'discipline'] as $k) {
            if ((string) $row[$k] !== (string) $old[$k]) {
                fail('Lentelė jau sudaryta. Norėdami keisti formatą, pirma panaikinkite burtus.');
            }
        }
    }
    update('categories', $row, 'id = ?', [$id]);
    bump_rev((int) $old['tournament_id']);
}

function delete_category(int $id): void
{
    $c = category_or_404($id);
    q('DELETE FROM categories WHERE id = ?', [$id]);
    bump_rev((int) $c['tournament_id']);
}

/** Ar sportininkas tinka kategorijai. Grąžina null, jei tinka, arba priežastį. */
function eligibility_problem(array $cat, array $athlete, array $t): ?string
{
    if ($cat['gender'] !== 'X' && $cat['gender'] !== $athlete['gender']) {
        return 'netinka lytis';
    }
    $age = age_on($athlete['birth_date'], $t['starts_on'], $t['age_basis']);
    if ($cat['age_min'] !== null && $age < (int) $cat['age_min']) {
        return 'per jaunas';
    }
    if ($cat['age_max'] !== null && $age > (int) $cat['age_max']) {
        return 'per vyresnis';
    }
    $belt = $athlete['belt_level'] !== null ? (int) $athlete['belt_level'] : null;
    if ($cat['belt_min'] !== null && ($belt === null || $belt < (int) $cat['belt_min'])) {
        return 'per žemas diržas';
    }
    if ($cat['belt_max'] !== null && ($belt === null || $belt > (int) $cat['belt_max'])) {
        return 'per aukštas diržas';
    }
    $w = $athlete['weight_kg'] !== null ? (float) $athlete['weight_kg'] : null;
    if (($cat['weight_min'] !== null || $cat['weight_max'] !== null) && $w === null) {
        return 'nenurodytas svoris';
    }
    if ($cat['weight_min'] !== null && $w <= (float) $cat['weight_min']) {
        return 'per mažas svoris';
    }
    if ($cat['weight_max'] !== null && $w > (float) $cat['weight_max']) {
        return 'per didelis svoris';
    }
    return null;
}

/** Individualios kategorijos, kurioms sportininkas tinka */
function eligible_categories(array $t, array $athlete, bool $openOnly = true): array
{
    $out = [];
    foreach (q_all('SELECT * FROM categories WHERE tournament_id = ? ORDER BY sort_order, id', [$t['id']]) as $c) {
        if (entry_type($c) !== 'individual') {
            continue;
        }
        if ($openOnly && $c['status'] !== 'open') {
            continue;
        }
        if (eligibility_problem($c, $athlete, $t) === null) {
            $out[] = $c;
        }
    }
    return $out;
}

/** Kategorija ekranui */
function category_view(array $c, ?array $t = null): array
{
    $count = (int) q_value('SELECT COUNT(*) FROM entries WHERE category_id = ? AND status = "active"', [$c['id']]);
    return [
        'id'             => (int) $c['id'],
        'tournament_id'  => (int) $c['tournament_id'],
        'name'           => $c['name'],
        'discipline'     => $c['discipline'],
        'discipline_label' => DISCIPLINES[$c['discipline']],
        'entry_type'     => entry_type($c),
        'gender'         => $c['gender'],
        'age_min'        => $c['age_min'] === null ? null : (int) $c['age_min'],
        'age_max'        => $c['age_max'] === null ? null : (int) $c['age_max'],
        'belt_min'       => $c['belt_min'] === null ? null : (int) $c['belt_min'],
        'belt_max'       => $c['belt_max'] === null ? null : (int) $c['belt_max'],
        'weight_min'     => $c['weight_min'] === null ? null : (float) $c['weight_min'],
        'weight_max'     => $c['weight_max'] === null ? null : (float) $c['weight_max'],
        'format'         => $c['format'],
        'match_type'     => $c['match_type'],
        'kumite_rule'    => $c['kumite_rule'],
        'kumite_target'  => KUMITE_RULES[$c['kumite_rule']]['target'],
        'match_seconds'  => (int) $c['match_seconds'],
        'judges'         => (int) $c['judges'],
        'third_place'    => $c['third_place'],
        'score_rounds'   => $c['score_rounds'],
        'score_min'      => (float) $c['score_min'],
        'score_max'      => (float) $c['score_max'],
        'carry_scores'   => (bool) $c['carry_scores'],
        'tatami'         => $c['tatami'] === null ? null : (int) $c['tatami'],
        'sort_order'     => (int) $c['sort_order'],
        'not_before'     => $c['not_before'] ? substr($c['not_before'], 0, 5) : null,
        'status'         => $c['status'],
        'entries_count'  => $count,
    ];
}

/** Dalyviai su vardais ir klubais. Grąžina [entry_id => [...]] */
function category_entries(int $categoryId, bool $activeOnly = false): array
{
    $rows = q_all(
        'SELECT e.*, a.first_name, a.last_name, a.birth_date, a.gender, a.belt_level, a.weight_kg,
                a.external_source, a.external_id, cl.name AS club_name, cl.city AS club_city
           FROM entries e
           LEFT JOIN athletes a ON a.id = e.athlete_id
           LEFT JOIN clubs cl ON cl.id = e.club_id
          WHERE e.category_id = ?' . ($activeOnly ? ' AND e.status = "active"' : '') . '
          ORDER BY e.status, cl.name, a.last_name, a.first_name, e.team_name',
        [$categoryId]
    );
    $members = [];
    if ($rows) {
        $ids = implode(',', array_map(fn ($r) => (int) $r['id'], $rows));
        foreach (q_all("SELECT em.entry_id, a.id, a.first_name, a.last_name, a.external_source, a.external_id, a.belt_level
                          FROM entry_members em JOIN athletes a ON a.id = em.athlete_id
                         WHERE em.entry_id IN ($ids) ORDER BY em.sort_order, a.last_name") as $m) {
            $members[(int) $m['entry_id']][] = $m;
        }
    }
    $out = [];
    foreach ($rows as $r) {
        $id = (int) $r['id'];
        $mem = $members[$id] ?? [];
        if ($r['athlete_id']) {
            $name = person_name($r);
        } else {
            $name = $r['team_name'] ?: implode(' / ', array_map(fn ($m) => $m['first_name'] . ' ' . mb_substr($m['last_name'], 0, 1) . '.', $mem));
        }
        $out[$id] = [
            'id'          => $id,
            'athlete_id'  => $r['athlete_id'] ? (int) $r['athlete_id'] : null,
            'name'        => $name ?: 'Komanda #' . $id,
            'club_id'     => $r['club_id'] ? (int) $r['club_id'] : null,
            'club'        => $r['club_name'],
            'birth_date'  => $r['birth_date'],
            'gender'      => $r['gender'],
            'belt_level'  => $r['belt_level'] === null ? null : (int) $r['belt_level'],
            'weight_kg'   => $r['weight_kg'] === null ? null : (float) $r['weight_kg'],
            'seed'        => $r['seed'] === null ? null : (int) $r['seed'],
            'status'      => $r['status'],
            'source'      => $r['source'],
            'members'     => array_map(fn ($m) => [
                'id' => (int) $m['id'], 'name' => person_name($m),
                'external_source' => $m['external_source'], 'external_id' => $m['external_id'],
            ], $mem),
            'external'    => $r['athlete_id'] ? ['source' => $r['external_source'], 'id' => $r['external_id']] : null,
        ];
    }
    return $out;
}

/** Ar galima keisti dalyvių sąrašą (iki burtų) */
function assert_category_open(array $cat): void
{
    if ($cat['status'] !== 'open') {
        fail('Kategorijos „' . $cat['name'] . '“ burtai jau ištraukti - dalyvių keisti nebegalima. Pirma panaikinkite burtus.');
    }
}

/** Įrašo sportininką į kategoriją (jei jau yra - atnaujina statusą) */
function add_entry(array $cat, int $athleteId, ?int $clubId, string $source = 'admin', bool $checkEligibility = true): int
{
    assert_category_open($cat);
    if (entry_type($cat) !== 'individual') {
        fail('Į porų / komandų kategoriją dalyviai įrašomi kaip komanda.');
    }
    if ($checkEligibility) {
        $t = tournament_or_404((int) $cat['tournament_id']);
        $a = q_one('SELECT * FROM athletes WHERE id = ?', [$athleteId]);
        $problem = eligibility_problem($cat, $a, $t);
        if ($problem) {
            fail(person_name($a) . ' netinka kategorijai „' . $cat['name'] . '“: ' . $problem . '.');
        }
    }
    $existing = q_one('SELECT id FROM entries WHERE category_id = ? AND athlete_id = ?', [$cat['id'], $athleteId]);
    if ($existing) {
        q('UPDATE entries SET status = "active", club_id = ? WHERE id = ?', [$clubId, $existing['id']]);
        $id = (int) $existing['id'];
    } else {
        $id = insert('entries', ['category_id' => $cat['id'], 'athlete_id' => $athleteId, 'club_id' => $clubId, 'source' => $source]);
    }
    bump_rev((int) $cat['tournament_id']);
    return $id;
}

/** Pora / komanda */
function add_team_entry(array $cat, ?string $teamName, ?int $clubId, array $athleteIds, string $source = 'admin'): int
{
    assert_category_open($cat);
    if (entry_type($cat) === 'individual') {
        fail('Ši kategorija individuali.');
    }
    $athleteIds = array_values(array_unique(array_filter(array_map('intval', $athleteIds))));
    $need = entry_type($cat) === 'pair' ? 2 : 3;
    if (count($athleteIds) < $need) {
        fail(entry_type($cat) === 'pair' ? 'Porai reikia 2 sportininkų.' : 'Komandai reikia bent 3 sportininkų.');
    }
    return tx(function () use ($cat, $teamName, $clubId, $athleteIds, $source) {
        $id = insert('entries', ['category_id' => $cat['id'], 'team_name' => $teamName, 'club_id' => $clubId, 'source' => $source]);
        foreach ($athleteIds as $i => $aid) {
            insert('entry_members', ['entry_id' => $id, 'athlete_id' => $aid, 'sort_order' => $i]);
        }
        bump_rev((int) $cat['tournament_id']);
        return $id;
    });
}

function withdraw_entry(int $entryId): void
{
    $e = q_one('SELECT * FROM entries WHERE id = ?', [$entryId]);
    if (!$e) {
        fail('Dalyvis nerastas.', 404);
    }
    $cat = category_or_404((int) $e['category_id']);
    assert_category_open($cat);
    // Nauji (dar neprasidėję) įrašai ištrinami, kad nekauptų šiukšlių
    q('DELETE FROM entries WHERE id = ?', [$entryId]);
    bump_rev((int) $cat['tournament_id']);
}

/** Laikinas sportininkas tinkamumo patikrai (be įrašymo į DB) */
function athlete_probe(array $b): array
{
    if (!valid_date($b['birth_date'] ?? null) || !in_array($b['gender'] ?? null, ['M', 'F'], true)) {
        fail('Nurodykite gimimo datą ir lytį.');
    }
    return ['birth_date' => $b['birth_date'], 'gender' => $b['gender'],
            'belt_level' => int_or_null($b['belt_level'] ?? null), 'weight_kg' => dec_or_null($b['weight_kg'] ?? null)];
}

/** Greitas kategorijų sukūrimas: disciplinos × amžiaus grupės × lytys */
function create_categories_from_template(int $tid, array $b): int
{
    tournament_or_404($tid);
    $disciplines = array_values(array_filter((array) ($b['disciplines'] ?? []), fn ($d) => isset(DISCIPLINES[$d])));
    $ages = (array) ($b['ages'] ?? []);
    $split = !empty($b['split_gender']);
    if (!$disciplines || !$ages) {
        fail('Pasirinkite bent vieną discipliną ir amžiaus grupę.');
    }
    return tx(function () use ($tid, $disciplines, $ages, $split, $b) {
        $n = 0;
        foreach ($ages as $age) {
            foreach ($disciplines as $d) {
                $genders = $split && DISCIPLINE_ENTRY[$d] === 'individual' ? ['M', 'F'] : ['X'];
                foreach ($genders as $g) {
                    $in = ['discipline' => $d, 'gender' => $g, 'age_min' => $age['min'] ?? null, 'age_max' => $age['max'] ?? null]
                        + default_format_for($d);
                    if ($d === 'kumite' || $d === 'fukugo') {
                        $in['third_place'] = $b['third_place'] ?? 'two_bronze';
                        $in['kumite_rule'] = ($age['max'] ?? 99) <= 11 ? 'nihon' : 'ippon';
                        $in['match_seconds'] = ($age['max'] ?? 99) <= 13 ? 90 : 120;
                    }
                    if (in_array($d, ['kata', 'enbu', 'kata_team'], true)) {
                        $in['score_rounds'] = '8';
                    }
                    create_category($tid, $in);
                    $n++;
                }
            }
        }
        return $n;
    });
}
