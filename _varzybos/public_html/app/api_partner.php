<?php
// Partnerių API (pvz. karateka.lt): api.php?r=v1/..., antraštė X-Api-Key.
// Sportininkai atpažįstami pagal external_id (karateka.lt - nario id), todėl rezultatus galima grąžinti atgal.

function partner_api(string $method, string $route): array
{
    $source = api_client();
    $R = fn (string $m, string $p) => $method === $m ? route_match($p, $route) : null;

    if ($R('GET', 'tournaments') !== null) {
        $rows = q_all('SELECT * FROM tournaments WHERE status <> "draft" ORDER BY starts_on DESC');
        return ['tournaments' => array_map(fn ($t) => partner_tournament($t, false), $rows)];
    }
    if (($p = $R('GET', 'tournaments/{id}')) !== null) {
        return ['tournament' => partner_tournament(partner_visible($p['id']), true)];
    }
    if ($R('POST', 'eligible') !== null) {
        $t = partner_visible((int) input('tournament_id'));
        $a = athlete_probe((array) input('athlete', []));
        return ['categories' => array_map('partner_category', eligible_categories($t, $a))];
    }
    if ($R('POST', 'registrations') !== null) {
        return partner_register($source, body());
    }
    if ($R('GET', 'registrations') !== null) {
        $tid = (int) ($_GET['tournament_id'] ?? 0);
        partner_visible($tid);
        $ext = array_filter(array_map('trim', explode(',', (string) ($_GET['external_ids'] ?? ''))));
        return ['registrations' => partner_registrations($source, $tid, $ext)];
    }
    if ($R('DELETE', 'registrations') !== null) {
        $tid = (int) input('tournament_id');
        $t = partner_visible($tid);
        $a = q_one('SELECT * FROM athletes WHERE external_source = ? AND external_id = ?', [$source, (string) input('external_id')]);
        if (!$a) {
            fail('Sportininkas nerastas.', 404);
        }
        if (!registration_open($t)) {
            fail('Registracija jau baigta - atšaukti gali tik organizatorius.', 403);
        }
        $cid = int_or_null(input('category_id'));
        $rows = q_all('SELECT e.id FROM entries e JOIN categories c ON c.id = e.category_id WHERE c.tournament_id = ? AND e.athlete_id = ?' . ($cid ? ' AND c.id = ' . $cid : ''), [$tid, $a['id']]);
        foreach ($rows as $r) {
            withdraw_entry((int) $r['id']);
        }
        return ['withdrawn' => count($rows)];
    }
    if (($p = $R('GET', 'tournaments/{id}/results')) !== null) {
        $t = partner_visible($p['id']);
        $out = [];
        foreach (q_all('SELECT * FROM categories WHERE tournament_id = ? ORDER BY sort_order, id', [$t['id']]) as $c) {
            $out[] = partner_category($c) + ['finished' => $c['status'] === 'finished', 'results' => category_results($c)];
        }
        return ['tournament' => partner_tournament($t, false), 'categories' => $out];
    }
    fail('Nežinomas adresas: v1/' . $route, 404);
}

function partner_visible(int $id): array
{
    $t = tournament_or_404($id);
    if ($t['status'] === 'draft') {
        fail('Varžybos nerastos.', 404);
    }
    return $t;
}

function partner_tournament(array $t, bool $withCategories): array
{
    $out = [
        'id'                => (int) $t['id'],
        'title'             => $t['title'],
        'starts_on'         => $t['starts_on'],
        'ends_on'           => $t['ends_on'],
        'location'          => $t['location'],
        'status'            => $t['status'],
        'registration_open' => registration_open($t),
        'reg_deadline'      => $t['reg_deadline'],
        'is_abroad'         => (bool) $t['is_abroad'],
        'url'               => rtrim((string) config('site_url'), '/') . '/#/t/' . $t['id'],
    ];
    if ($withCategories) {
        $out['categories'] = array_map('partner_category', q_all('SELECT * FROM categories WHERE tournament_id = ? ORDER BY sort_order, id', [$t['id']]));
    }
    return $out;
}

function partner_category(array $c): array
{
    return [
        'id'         => (int) $c['id'],
        'name'       => $c['name'],
        'discipline' => $c['discipline'],
        'entry_type' => entry_type($c),
        'gender'     => $c['gender'],
        'age_min'    => $c['age_min'] === null ? null : (int) $c['age_min'],
        'age_max'    => $c['age_max'] === null ? null : (int) $c['age_max'],
        'belt_min'   => $c['belt_min'] === null ? null : (int) $c['belt_min'],
        'belt_max'   => $c['belt_max'] === null ? null : (int) $c['belt_max'],
        'weight_min' => $c['weight_min'] === null ? null : (float) $c['weight_min'],
        'weight_max' => $c['weight_max'] === null ? null : (float) $c['weight_max'],
        'open'       => $c['status'] === 'open',
    ];
}

/**
 * Registracija iš partnerio sistemos.
 * {tournament_id, athlete: {external_id, first_name, last_name, birth_date, gender, belt_level, weight_kg},
 *  club: {external_id, name, city}, category_ids: [..] arba "auto" (visos tinkamos individualios)}
 */
function partner_register(string $source, array $b): array
{
    $t = partner_visible((int) ($b['tournament_id'] ?? 0));
    if (!registration_open($t)) {
        fail('Registracija į šias varžybas uždaryta.', 403);
    }
    $a = (array) ($b['athlete'] ?? []);
    $ext = str_or_null($a['external_id'] ?? null, 60);
    if (!$ext) {
        fail('Trūksta athlete.external_id.');
    }
    return tx(function () use ($t, $a, $ext, $b, $source) {
        $clubId = find_or_create_club((array) ($b['club'] ?? []), $source);
        $aid = upsert_athlete($a, $clubId, $source, $ext);
        $athlete = q_one('SELECT * FROM athletes WHERE id = ?', [$aid]);
        $ids = $b['category_ids'] ?? [];
        if ($ids === 'auto') {
            $ids = array_map(fn ($c) => (int) $c['id'], eligible_categories($t, $athlete));
        }
        $ids = array_values(array_unique(array_map('intval', (array) $ids)));
        if (!$ids) {
            fail(person_name($athlete) . ' netinka nė vienai atvirai kategorijai.');
        }
        foreach ($ids as $cid) {
            $cat = category_or_404($cid);
            if ((int) $cat['tournament_id'] !== (int) $t['id']) {
                fail('Kategorija ne iš šių varžybų.');
            }
            add_entry($cat, $aid, $clubId, 'api');
        }
        return ['athlete_id' => $aid, 'registrations' => partner_registrations($source, (int) $t['id'], [$ext])];
    });
}

function partner_registrations(string $source, int $tid, array $externalIds): array
{
    $sql = 'SELECT a.external_id, a.first_name, a.last_name, c.id AS category_id, c.name AS category_name, c.status AS category_status, e.id AS entry_id
              FROM entries e
              JOIN categories c ON c.id = e.category_id
              JOIN athletes a ON a.id = e.athlete_id
             WHERE c.tournament_id = ? AND a.external_source = ? AND e.status = "active"';
    $params = [$tid, $source];
    if ($externalIds) {
        $sql .= ' AND a.external_id IN (' . implode(',', array_fill(0, count($externalIds), '?')) . ')';
        $params = array_merge($params, array_values($externalIds));
    }
    $out = [];
    foreach (q_all($sql . ' ORDER BY a.last_name, c.sort_order', $params) as $r) {
        $out[] = [
            'external_id' => $r['external_id'],
            'name'        => person_name($r),
            'category_id' => (int) $r['category_id'],
            'category'    => $r['category_name'],
            'entry_id'    => (int) $r['entry_id'],
            'locked'      => $r['category_status'] !== 'open',
        ];
    }
    return $out;
}
