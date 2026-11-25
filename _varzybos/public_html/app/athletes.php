<?php
// Sportininkai ir klubai

function athlete_from_input(array $in, ?array $old = null): array
{
    $g = fn ($k) => array_key_exists($k, $in) ? $in[$k] : ($old[$k] ?? null);
    $first = str_or_null($g('first_name'), 80);
    $last = str_or_null($g('last_name'), 80);
    if (!$first || !$last) {
        fail('Įrašykite vardą ir pavardę.');
    }
    $birth = $g('birth_date');
    if (!valid_date($birth) || $birth > date('Y-m-d') || $birth < '1920-01-01') {
        fail("$first $last: neteisinga gimimo data.");
    }
    $gender = $g('gender');
    if (!in_array($gender, ['M', 'F'], true)) {
        fail("$first $last: nurodykite lytį.");
    }
    $belt = int_or_null($g('belt_level'));
    $weight = dec_or_null($g('weight_kg'));
    return [
        'first_name' => $first,
        'last_name'  => $last,
        'birth_date' => $birth,
        'gender'     => $gender,
        'belt_level' => $belt && isset(BELTS[$belt]) ? $belt : null,
        'weight_kg'  => $weight !== null && $weight > 10 && $weight < 250 ? $weight : null,
    ];
}

/**
 * Suranda arba sukuria sportininką.
 * Pirma pagal išorinį id (pvz. karateka nario id), tada pagal vardą + pavardę + gimimo datą.
 */
function upsert_athlete(array $in, ?int $clubId, ?string $source = null, ?string $externalId = null): int
{
    $row = athlete_from_input($in);
    $row['club_id'] = $clubId;
    $found = null;
    if ($source && $externalId) {
        $found = q_one('SELECT * FROM athletes WHERE external_source = ? AND external_id = ?', [$source, $externalId]);
    }
    if (!$found) {
        $found = q_one('SELECT * FROM athletes WHERE first_name = ? AND last_name = ? AND birth_date = ? AND (external_id IS NULL OR external_source = ?) LIMIT 1',
            [$row['first_name'], $row['last_name'], $row['birth_date'], (string) $source]);
    }
    if ($found) {
        if ($source && $externalId) {
            $row['external_source'] = $source;
            $row['external_id'] = $externalId;
        }
        // Nenurodyti duomenys neištrina jau turimų
        foreach (['belt_level', 'weight_kg', 'club_id'] as $k) {
            if ($row[$k] === null) {
                unset($row[$k]);
            }
        }
        update('athletes', $row, 'id = ?', [$found['id']]);
        return (int) $found['id'];
    }
    if ($source && $externalId) {
        $row['external_source'] = $source;
        $row['external_id'] = $externalId;
    }
    return insert('athletes', $row);
}

function athlete_view(array $a): array
{
    return [
        'id'          => (int) $a['id'],
        'first_name'  => $a['first_name'],
        'last_name'   => $a['last_name'],
        'birth_date'  => $a['birth_date'],
        'gender'      => $a['gender'],
        'belt_level'  => $a['belt_level'] === null ? null : (int) $a['belt_level'],
        'weight_kg'   => $a['weight_kg'] === null ? null : (float) $a['weight_kg'],
        'club_id'     => $a['club_id'] ? (int) $a['club_id'] : null,
        'club'        => $a['club_name'] ?? null,
        'external_source' => $a['external_source'],
        'external_id' => $a['external_id'],
    ];
}

/** Klubas pagal pavadinimą (nesvarbu didžiosios raidės) arba naujas */
function find_or_create_club(array $in, ?string $source = null): ?int
{
    $name = str_or_null($in['name'] ?? null, 160);
    $extId = str_or_null($in['external_id'] ?? null, 60);
    if ($source && $extId) {
        $c = q_one('SELECT id FROM clubs WHERE external_source = ? AND external_id = ?', [$source, $extId]);
        if ($c) {
            if ($name) {
                q('UPDATE clubs SET name = ? WHERE id = ?', [$name, $c['id']]);
            }
            return (int) $c['id'];
        }
    }
    if (!$name) {
        return null;
    }
    $c = q_one('SELECT id FROM clubs WHERE LOWER(name) = LOWER(?) LIMIT 1', [$name]);
    $row = [
        'city'          => str_or_null($in['city'] ?? null, 80),
        'contact_name'  => str_or_null($in['contact_name'] ?? null, 120),
        'contact_email' => str_or_null($in['contact_email'] ?? null),
        'contact_phone' => str_or_null($in['contact_phone'] ?? null, 40),
    ];
    $row = array_filter($row, fn ($v) => $v !== null);
    if ($c) {
        update('clubs', $row, 'id = ?', [$c['id']]);
        if ($source && $extId) {
            q('UPDATE clubs SET external_source = ?, external_id = ? WHERE id = ? AND external_id IS NULL', [$source, $extId, $c['id']]);
        }
        return (int) $c['id'];
    }
    $row['name'] = $name;
    $country = strtoupper((string) ($in['country'] ?? 'LT'));
    $row['country'] = preg_match('/^[A-Z]{2}$/', $country) ? $country : 'LT';
    if ($source && $extId) {
        $row['external_source'] = $source;
        $row['external_id'] = $extId;
    }
    return insert('clubs', $row);
}

/** Sportininkų paieška pagal vardą (admin įrašant dalyvius) */
function search_athletes(string $qstr, int $limit = 20): array
{
    $qstr = trim($qstr);
    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $qstr) . '%';
    $rows = q_all(
        'SELECT a.*, cl.name AS club_name FROM athletes a LEFT JOIN clubs cl ON cl.id = a.club_id
          WHERE CONCAT(a.first_name, " ", a.last_name) LIKE ? OR CONCAT(a.last_name, " ", a.first_name) LIKE ?
          ORDER BY a.last_name, a.first_name LIMIT ' . (int) $limit,
        [$like, $like]
    );
    return array_map('athlete_view', $rows);
}

/** Visi sportininkai, dalyvaujantys varžybose, su jų kategorijomis */
function tournament_athletes(int $tid): array
{
    $rows = q_all(
        'SELECT a.*, cl.name AS club_name, e.id AS entry_id, e.category_id, c.name AS category_name, e.source
           FROM entries e
           JOIN categories c ON c.id = e.category_id
           LEFT JOIN entry_members em ON em.entry_id = e.id
           JOIN athletes a ON a.id = COALESCE(e.athlete_id, em.athlete_id)
           LEFT JOIN clubs cl ON cl.id = COALESCE(e.club_id, a.club_id)
          WHERE c.tournament_id = ? AND e.status = "active"
          ORDER BY cl.name, a.last_name, a.first_name, c.sort_order',
        [$tid]
    );
    $out = [];
    foreach ($rows as $r) {
        $id = (int) $r['id'];
        if (!isset($out[$id])) {
            $out[$id] = athlete_view($r) + ['categories' => [], 'source' => $r['source']];
        }
        $out[$id]['categories'][] = ['id' => (int) $r['category_id'], 'name' => $r['category_name'], 'entry_id' => (int) $r['entry_id']];
    }
    return array_values($out);
}

/** Viešoji registracija (kiti klubai): treneris užregistruoja savo sportininkus */
function public_registration(int $tid, array $b): array
{
    $t = tournament_or_404($tid);
    if (!$t['public_registration'] || !registration_open($t)) {
        fail('Registracija į šias varžybas uždaryta.', 403);
    }
    $club = (array) ($b['club'] ?? []);
    if (!str_or_null($club['name'] ?? null) || !filter_var($club['contact_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        fail('Įrašykite klubo pavadinimą ir trenerio el. paštą.');
    }
    $athletes = (array) ($b['athletes'] ?? []);
    if (!$athletes || count($athletes) > 100) {
        fail('Įrašykite bent vieną sportininką.');
    }
    return tx(function () use ($t, $club, $athletes) {
        $clubId = find_or_create_club($club);
        $count = 0;
        $names = [];
        foreach ($athletes as $a) {
            $aid = upsert_athlete((array) $a, $clubId);
            $cats = array_map('intval', (array) ($a['category_ids'] ?? []));
            if (!$cats) {
                fail(person_name($a) . ': pasirinkite bent vieną kategoriją.');
            }
            foreach ($cats as $cid) {
                $cat = category_or_404($cid);
                if ((int) $cat['tournament_id'] !== (int) $t['id']) {
                    fail('Kategorija ne iš šių varžybų.');
                }
                add_entry($cat, $aid, $clubId, 'public');
                $count++;
            }
            $names[] = person_name($a);
        }
        return ['ok' => true, 'entries' => $count, 'athletes' => $names];
    });
}
