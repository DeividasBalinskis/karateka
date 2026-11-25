<?php
// JSON API: api.php?r=tournaments/1/live
// Naudoja ši svetainė (sesija + X-CSRF-Token) ir partneriai, pvz. karateka.lt (r=v1/..., X-Api-Key).
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/api_partner.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$method = $_SERVER['REQUEST_METHOD'];
$route = trim((string) ($_GET['r'] ?? ($_SERVER['PATH_INFO'] ?? '')), '/');

try {
    if (str_starts_with($route, 'v1/')) {
        $data = partner_api($method, substr($route, 3));
    } else {
        start_session();
        if ($method !== 'GET') {
            check_csrf();
        }
        $data = site_api($method, $route);
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (ApiError $e) {
    http_response_code($e->status);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log((string) $e);
    http_response_code(500);
    $msg = config('env') === 'local' ? $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() : 'Serverio klaida. Bandykite dar kartą.';
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
}

/** Maršrutas: grąžina [parametrai] jei sutampa, pvz. route_is('tournaments/{id}/live') */
function route_match(string $pattern, string $route): ?array
{
    $re = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[0-9]+)', $pattern) . '$#';
    if (!preg_match($re, $route, $m)) {
        return null;
    }
    return array_map('intval', array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY));
}

function site_api(string $method, string $route): array
{
    $R = fn (string $m, string $p) => $method === $m ? route_match($p, $route) : null;

    // ---------- Viešai ----------
    if ($R('GET', 'meta') !== null) {
        return meta_info();
    }
    if ($R('GET', 'tournaments') !== null) {
        $user = current_user();
        $rows = q_all('SELECT * FROM tournaments' . ($user ? '' : ' WHERE status <> "draft"') . ' ORDER BY starts_on DESC, id DESC');
        return ['tournaments' => array_map('tournament_view', $rows)];
    }
    if (($p = $R('GET', 'tournaments/{id}')) !== null) {
        $t = visible_tournament($p['id']);
        $cats = q_all('SELECT * FROM categories WHERE tournament_id = ? ORDER BY sort_order, id', [$t['id']]);
        return ['tournament' => tournament_view($t), 'categories' => array_map(fn ($c) => category_view($c), $cats), 'server_time' => server_ms()];
    }
    if (($p = $R('GET', 'tournaments/{id}/rev')) !== null) {
        $t = visible_tournament($p['id']);
        return ['rev' => (int) $t['rev'], 'server_time' => server_ms()];
    }
    if (($p = $R('GET', 'tournaments/{id}/schedule')) !== null) {
        $t = visible_tournament($p['id']);
        return ['schedule' => tournament_schedule($t), 'rev' => (int) $t['rev']];
    }
    if (($p = $R('GET', 'tournaments/{id}/live')) !== null) {
        $t = visible_tournament($p['id']);
        $only = int_or_null($_GET['tatami'] ?? null);
        return ['tournament' => tournament_view($t, false), 'tatami' => tatami_queue($t, $only ?: null, (int) ($_GET['next'] ?? 4)), 'server_time' => server_ms()];
    }
    if (($p = $R('GET', 'tournaments/{id}/results')) !== null) {
        $t = visible_tournament($p['id']);
        $out = [];
        foreach (q_all('SELECT * FROM categories WHERE tournament_id = ? ORDER BY sort_order, id', [$t['id']]) as $c) {
            $out[] = category_view($c) + ['results' => category_results($c)];
        }
        return ['categories' => $out, 'medals' => medal_table((int) $t['id']), 'rev' => (int) $t['rev']];
    }
    if (($p = $R('GET', 'tournaments/{id}/clubs')) !== null) {
        $t = visible_tournament($p['id']);
        $clubs = tournament_clubs((int) $t['id']);
        if (!current_user()) {
            $clubs = array_map(fn ($c) => array_diff_key($c, array_flip(['contact_name', 'contact_email', 'contact_phone'])), $clubs);
        }
        return ['clubs' => $clubs];
    }
    if (($p = $R('GET', 'categories/{id}')) !== null) {
        $c = category_or_404($p['id']);
        $t = visible_tournament((int) $c['tournament_id']);
        return category_detail($c, $t);
    }
    if (($p = $R('POST', 'tournaments/{id}/eligible')) !== null) {
        $t = visible_tournament($p['id']);
        $a = athlete_probe(body());
        return ['categories' => array_map(fn ($c) => category_view($c), eligible_categories($t, $a))];
    }
    if (($p = $R('POST', 'tournaments/{id}/register')) !== null) {
        return public_registration($p['id'], body());
    }

    // ---------- Prisijungimas ----------
    if ($R('POST', 'auth/login') !== null) {
        $u = login((string) input('email', ''), (string) input('password', ''));
        return ['user' => $u, 'csrf' => csrf_token()];
    }
    if ($R('POST', 'auth/logout') !== null) {
        logout();
        return ['ok' => true, 'csrf' => csrf_token()];
    }
    if ($R('POST', 'auth/setup') !== null) {
        if ((int) q_value('SELECT COUNT(*) FROM users')) {
            fail('Administratorius jau sukurtas.', 403);
        }
        $key = (string) config('setup_key', '');
        if (strlen($key) < 12 || $key === 'CHANGE_ME_LONG_RANDOM' || !hash_equals($key, (string) input('setup_key', ''))) {
            fail('Neteisingas sąrankos raktas (setup_key iš config.php).', 403);
        }
        create_user((string) input('email'), (string) input('password'), (string) input('name'), 'admin');
        return ['user' => login((string) input('email'), (string) input('password')), 'csrf' => csrf_token()];
    }

    // ---------- Organizatoriai ----------
    $user = require_user();
    $isAdmin = $user['role'] === 'admin';

    if ($R('POST', 'tournaments') !== null) {
        require_user('admin');
        $id = create_tournament(body());
        return ['tournament' => tournament_view(tournament_or_404($id))];
    }
    if (($p = $R('PUT', 'tournaments/{id}')) !== null) {
        require_user('admin');
        update_tournament($p['id'], body());
        return ['tournament' => tournament_view(tournament_or_404($p['id']))];
    }
    if (($p = $R('DELETE', 'tournaments/{id}')) !== null) {
        require_user('admin');
        tournament_or_404($p['id']);
        q('DELETE FROM tournaments WHERE id = ?', [$p['id']]);
        return ['ok' => true];
    }
    if (($p = $R('POST', 'tournaments/{id}/categories')) !== null) {
        require_user('admin');
        $id = create_category($p['id'], body());
        return ['category' => category_view(category_or_404($id))];
    }
    if (($p = $R('POST', 'tournaments/{id}/categories/template')) !== null) {
        require_user('admin');
        return ['created' => create_categories_from_template($p['id'], body())];
    }
    if (($p = $R('PUT', 'categories/{id}')) !== null) {
        require_user('admin');
        update_category($p['id'], body());
        return ['category' => category_view(category_or_404($p['id']))];
    }
    if (($p = $R('DELETE', 'categories/{id}')) !== null) {
        require_user('admin');
        delete_category($p['id']);
        return ['ok' => true];
    }
    if (($p = $R('POST', 'categories/{id}/draw')) !== null) {
        draw_category($p['id']);
        return ['ok' => true];
    }
    if (($p = $R('POST', 'tournaments/{id}/draw-all')) !== null) {
        $done = [];
        $skipped = [];
        foreach (q_all('SELECT * FROM categories WHERE tournament_id = ? AND status = "open" ORDER BY sort_order', [$p['id']]) as $c) {
            try {
                draw_category((int) $c['id']);
                $done[] = $c['name'];
            } catch (ApiError $e) {
                $skipped[] = $c['name'] . ': ' . $e->getMessage();
            }
        }
        return ['drawn' => $done, 'skipped' => $skipped];
    }
    if (($p = $R('POST', 'categories/{id}/undraw')) !== null) {
        undo_draw($p['id']);
        return ['ok' => true];
    }
    if (($p = $R('POST', 'categories/{id}/next-round')) !== null) {
        next_score_round($p['id']);
        return ['ok' => true];
    }
    if (($p = $R('POST', 'categories/{id}/undo-round')) !== null) {
        undo_score_round($p['id']);
        return ['ok' => true];
    }
    if (($p = $R('POST', 'categories/{id}/entries')) !== null) {
        $cat = category_or_404($p['id']);
        $b = body();
        if (entry_type($cat) !== 'individual') {
            $id = add_team_entry($cat, str_or_null($b['team_name'] ?? null, 120), int_or_null($b['club_id'] ?? null), (array) ($b['athlete_ids'] ?? []));
        } else {
            $aid = (int) ($b['athlete_id'] ?? 0);
            $a = q_one('SELECT * FROM athletes WHERE id = ?', [$aid]);
            if (!$a) {
                fail('Sportininkas nerastas.');
            }
            $id = add_entry($cat, $aid, $a['club_id'] ? (int) $a['club_id'] : null, 'admin', empty($b['force']));
        }
        return ['entry_id' => $id];
    }
    if (($p = $R('PUT', 'entries/{id}')) !== null) {
        $e = q_one('SELECT * FROM entries WHERE id = ?', [$p['id']]) ?? fail('Dalyvis nerastas.', 404);
        $cat = category_or_404((int) $e['category_id']);
        assert_category_open($cat);
        $row = [];
        if (array_key_exists('seed', body())) {
            $seed = int_or_null(input('seed'));
            $row['seed'] = $seed && $seed > 0 ? min($seed, 32) : null;
        }
        if (array_key_exists('team_name', body())) {
            $row['team_name'] = str_or_null(input('team_name'), 120);
        }
        update('entries', $row, 'id = ?', [$p['id']]);
        bump_rev((int) $cat['tournament_id']);
        return ['ok' => true];
    }
    if (($p = $R('DELETE', 'entries/{id}')) !== null) {
        withdraw_entry($p['id']);
        return ['ok' => true];
    }
    if (($p = $R('GET', 'tournaments/{id}/athletes')) !== null) {
        tournament_or_404($p['id']);
        return ['athletes' => tournament_athletes($p['id'])];
    }
    if (($p = $R('POST', 'tournaments/{id}/athletes')) !== null) {
        $t = tournament_or_404($p['id']);
        $b = body();
        return tx(function () use ($t, $b) {
            $clubId = int_or_null($b['club_id'] ?? null) ?: find_or_create_club((array) ($b['club'] ?? []));
            $aid = !empty($b['athlete_id']) ? (int) $b['athlete_id'] : upsert_athlete((array) ($b['athlete'] ?? []), $clubId);
            if (!empty($b['athlete_id']) && !empty($b['athlete'])) {
                update('athletes', athlete_from_input((array) $b['athlete'], q_one('SELECT * FROM athletes WHERE id = ?', [$aid])) + ['club_id' => $clubId], 'id = ?', [$aid]);
            }
            $added = [];
            foreach ((array) ($b['category_ids'] ?? []) as $cid) {
                $cat = category_or_404((int) $cid);
                if ((int) $cat['tournament_id'] !== (int) $t['id']) {
                    fail('Kategorija ne iš šių varžybų.');
                }
                $added[] = add_entry($cat, $aid, $clubId, 'admin', empty($b['force']));
            }
            return ['athlete_id' => $aid, 'entries' => $added];
        });
    }
    if (($p = $R('PUT', 'athletes/{id}')) !== null) {
        $a = q_one('SELECT * FROM athletes WHERE id = ?', [$p['id']]) ?? fail('Sportininkas nerastas.', 404);
        $row = athlete_from_input(body(), $a);
        if (array_key_exists('club_id', body())) {
            $row['club_id'] = int_or_null(input('club_id'));
        }
        update('athletes', $row, 'id = ?', [$p['id']]);
        return ['ok' => true];
    }
    if ($R('GET', 'athletes') !== null) {
        return ['athletes' => search_athletes((string) ($_GET['q'] ?? ''))];
    }
    if ($R('GET', 'clubs') !== null) {
        return ['clubs' => q_all('SELECT id, name, city, country, external_source FROM clubs ORDER BY name')];
    }
    if ($R('POST', 'clubs') !== null) {
        $id = find_or_create_club(body());
        if (!$id) {
            fail('Įrašykite klubo pavadinimą.');
        }
        return ['club' => q_one('SELECT id, name, city, country FROM clubs WHERE id = ?', [$id])];
    }
    if (($p = $R('PUT', 'matches/{id}/score')) !== null) {
        $m = save_match_score($p['id'], (array) input('score', []));
        $entries = category_entries((int) $m['category_id']);
        return ['match' => match_view($m, $entries, category_rounds((int) $m['category_id'])), 'server_time' => server_ms()];
    }
    if (($p = $R('POST', 'matches/{id}/finish')) !== null) {
        finish_match($p['id'], (string) input('winner', ''), (string) input('result', 'points'), is_array(input('score')) ? input('score') : null);
        return ['ok' => true];
    }
    if (($p = $R('POST', 'matches/{id}/reset')) !== null) {
        reset_match($p['id']);
        return ['ok' => true];
    }
    if (($p = $R('GET', 'matches/{id}')) !== null) {
        $m = match_or_404($p['id']);
        $c = category_or_404((int) $m['category_id']);
        $entries = category_entries((int) $c['id']);
        return ['match' => match_view($m, $entries, category_rounds((int) $c['id'])), 'category' => category_view($c), 'server_time' => server_ms()];
    }
    if (($p = $R('PUT', 'performances/{id}')) !== null) {
        save_performance($p['id'], body());
        return ['ok' => true];
    }
    if (($p = $R('POST', 'tournaments/{id}/schedule/auto')) !== null) {
        auto_assign_tatami($p['id']);
        return ['ok' => true];
    }
    if (($p = $R('PUT', 'tournaments/{id}/schedule')) !== null) {
        save_schedule($p['id'], (array) input('items', []));
        return ['ok' => true];
    }
    if ($R('GET', 'users') !== null) {
        require_user('admin');
        return ['users' => q_all('SELECT id, email, name, role, last_login_at FROM users ORDER BY name')];
    }
    if ($R('POST', 'users') !== null) {
        require_user('admin');
        $id = create_user((string) input('email'), (string) input('password'), (string) input('name'), (string) input('role', 'secretary'));
        return ['id' => $id];
    }
    if (($p = $R('DELETE', 'users/{id}')) !== null) {
        $me = require_user('admin');
        if ($p['id'] === (int) $me['id']) {
            fail('Savęs ištrinti negalima.');
        }
        q('DELETE FROM users WHERE id = ?', [$p['id']]);
        return ['ok' => true];
    }
    if (($p = $R('GET', 'tournaments/{id}/integration')) !== null) {
        require_user('admin');
        tournament_or_404($p['id']);
        $keys = [];
        foreach ((array) config('api_keys', []) as $name => $secret) {
            $keys[] = ['name' => $name, 'configured' => is_string($secret) && strlen($secret) >= 16 && !str_contains($secret, 'CHANGE_ME')];
        }
        return ['keys' => $keys, 'api_url' => rtrim((string) config('site_url'), '/') . '/api.php?r=v1/'];
    }
    if (($p = $R('POST', 'tournaments/{id}/integration/test')) !== null) {
        // Admin gali išbandyti partnerio API (kaip karateka.lt) nežinodamas rakto
        require_user('admin');
        $source = (string) input('source', 'karateka');
        if (!array_key_exists($source, (array) config('api_keys', []))) {
            fail('Tokio API rakto config.php nėra.');
        }
        return partner_register($source, ['tournament_id' => $p['id']] + (array) input('payload', []));
    }
    fail('Nežinomas adresas: ' . $route, 404);
}

function server_ms(): int
{
    return (int) round(microtime(true) * 1000);
}

function visible_tournament(int $id): array
{
    $t = tournament_or_404($id);
    if ($t['status'] === 'draft' && !current_user()) {
        fail('Varžybos nerastos.', 404);
    }
    return $t;
}

function meta_info(): array
{
    $belts = [];
    foreach (BELTS as $lvl => [$kyu, $name, $color]) {
        $belts[] = ['level' => $lvl, 'kyu' => $kyu, 'name' => $name, 'color' => $color];
    }
    return [
        'site_name'   => config('site_name', 'Varžybos'),
        'env'         => config('env'),
        'user'        => current_user(),
        'csrf'        => csrf_token(),
        'needs_setup' => !(int) q_value('SELECT COUNT(*) FROM users'),
        'server_time' => server_ms(),
        'belts'       => $belts,
        'disciplines' => DISCIPLINES,
        'discipline_entry' => DISCIPLINE_ENTRY,
        'formats'     => FORMATS,
        'match_types' => MATCH_TYPES,
        'kumite_rules' => array_map(fn ($r) => $r['label'], KUMITE_RULES),
        'third_place' => THIRD_PLACE,
        'statuses'    => TOURNAMENT_STATUSES,
        'results'     => RESULT_LABELS,
        'defaults'    => array_map(fn ($d) => default_format_for($d), array_combine(array_keys(DISCIPLINES), array_keys(DISCIPLINES))),
    ];
}

/** Visa kategorijos informacija vienam puslapiui */
function category_detail(array $c, array $t): array
{
    $entries = category_entries((int) $c['id']);
    $out = [
        'tournament' => tournament_view($t, false),
        'category'   => category_view($c),
        'entries'    => array_values(array_filter($entries, fn ($e) => $e['status'] === 'active')),
        'results'    => category_results($c, $entries),
        'server_time' => server_ms(),
    ];
    if (!current_user()) {
        foreach ($out['entries'] as &$e) {
            unset($e['birth_date'], $e['weight_kg'], $e['source'], $e['external']);
        }
        unset($e);
    }
    if ($c['format'] === 'scores') {
        $out['performances'] = category_performances($c, $entries);
    } else {
        $out['matches'] = category_matches((int) $c['id'], $entries);
        $out['rounds'] = category_rounds((int) $c['id']);
        if ($c['format'] === 'round_robin') {
            $out['standings'] = pool_standings($c, array_filter($entries, fn ($e) => $e['status'] === 'active'));
        }
    }
    return $out;
}
