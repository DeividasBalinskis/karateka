<?php
// Varžybų sistema svetainėje (db/025): Valdymas → Varžybos. Klubai, dalyviai, komandos, kategorijos, lentelės, tvarkaraštis, rezultatai.
// Visa varžybų būsena - vienas JSON (tournaments.state), redaguojamas naršyklėje (assets/varzybos.js) ir išsaugomas automatiškai.
// Rezultatai (tournaments.results) - prizininkai ir klubų medaliai: iš jų narių taškai karateka.lt ir LTKF svetainės rezultatai.
// Test varžybos (is_test): sugeneruojamos vienu mygtuku iš Karateka narių ir kitų LTKF klubų išgalvotų dalyvių, ištrinamos vienu mygtuku.

const VARZ_NAME = 'Taika';    // varžybų sistemos pavadinimas; pakeisti - tik čia
const VARZ_HOME_CLUB = 'Karateka';
const TOURNAMENT_STATUS = [
    'draft'        => 'Ruošiamos',
    'registration' => 'Registracija',
    'running'      => 'Vyksta',
    'finished'     => 'Baigtos',
];
const VARZ_DISCIPLINES = ['kata' => 'Kata', 'kumite' => 'Kumite', 'fukugo' => 'Fukugo', 'kihon' => 'Kihon', 'enbu' => 'Enbu', 'sumo' => 'Sumo'];

function tournaments_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT 1 FROM tournaments LIMIT 1');
            $ok = true;
        } catch (PDOException $ex) {
            $ok = false;   // dar neimportuotas db/025
        }
    }
    return $ok;
}

function tournament_get(int $id): ?array
{
    return tournaments_ready() ? q_one('SELECT * FROM tournaments WHERE id = ?', [$id]) : null;
}

/** Varžybos sąraše: naujausios ir būsimos viršuje */
function tournaments_list(bool $withTest = true): array
{
    return tournaments_ready() ? q_all('SELECT * FROM tournaments' . ($withTest ? '' : ' WHERE is_test = 0') . ' ORDER BY starts_on DESC, id DESC') : [];
}

/** Viešai rodomos varžybos (LTKF, rezultatų puslapis): test - tik test svetainėje ir kompiuteryje */
function tournament_is_visible(array $t): bool
{
    return (int) $t['is_public'] === 1 && ((int) $t['is_test'] === 0 || config('env') !== 'live');
}

/** Federacijos klubai (LTKF sąrašas) - varžybų klubų pradžiai */
function varz_federation_clubs(): array
{
    $file = PUBLIC_DIR . '/ltkf/turinys/klubai.php';
    $names = is_file($file) ? array_column((array) require $file, 'pavadinimas') : [];
    $names = array_values(array_filter($names, function ($n) { return $n !== VARZ_HOME_CLUB && strpos($n, '[') === false; }));
    return array_values(array_unique(array_merge([VARZ_HOME_CLUB], $names)));
}

/** Tuščios varžybos: klubai, du tatamiai, kovų trukmės */
function tournament_default_state(): array
{
    return [
        'categories' => [], 'people' => (object) [], 'teams' => (object) [], 'clubs' => varz_federation_clubs(),
        'areas' => [['id' => 1, 'name' => '1 TATAMIS', 'start' => '09:30'], ['id' => 2, 'name' => '2 TATAMIS', 'start' => '09:30']],
        'minutes' => ['kata' => 2, 'kumite' => 3, 'fukugo' => 4, 'kihon' => 1.5],
    ];
}

function tournament_state(array $t): array
{
    $s = json_decode((string) $t['state'], true);
    return is_array($s) ? $s : tournament_default_state();
}

function tournament_results(array $t): array
{
    $r = json_decode((string) $t['results'], true);
    return is_array($r) ? $r : ['categories' => [], 'medals' => []];
}

/** Trumpa suvestinė sąrašui: dalyviai, kategorijos, klubai, baigtos kategorijos */
function tournament_stats(array $t): array
{
    $s = tournament_state($t);
    $r = tournament_results($t);
    $people = (array) ($s['people'] ?? []);
    $clubs = array_unique(array_column($people, 'club'));
    $cats = array_filter((array) ($s['categories'] ?? []), function ($c) { return !empty($c['competitors']); });
    return [
        'people'   => count($people),
        'home'     => count(array_filter($people, function ($p) { return ($p['club'] ?? '') === VARZ_HOME_CLUB; })),
        'clubs'    => count($clubs),
        'cats'     => count($cats),
        'finished' => count(array_filter((array) ($r['categories'] ?? []), function ($c) { return !empty($c['finished']); })),
    ];
}

function tournament_create(string $title, string $startsOn, ?string $endsOn, ?string $location, bool $isTest, int $by, ?array $state = null): int
{
    q('INSERT INTO tournaments (title, starts_on, ends_on, location, is_test, state, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [mb_substr($title, 0, 190), $startsOn, $endsOn ?: null, $location ?: null, $isTest ? 1 : 0,
         json_encode($state ?? tournament_default_state(), JSON_UNESCAPED_UNICODE), $by]);
    return (int) db()->lastInsertId();
}

/**
 * Išsaugo naršyklės pakeitimus. $version - kokią versiją redaguotojas matė: jei kitas žmogus jau išsaugojo naujesnę,
 * grąžinama null (nieko neperrašoma), kitaip - nauja versija.
 */
function tournament_save_state(int $id, array $state, array $results, int $version): ?int
{
    unset($state['history']);
    $st = q('UPDATE tournaments SET state = ?, results = ?, version = version + 1, updated_at = NOW() WHERE id = ? AND version = ?',
        [json_encode($state, JSON_UNESCAPED_UNICODE), json_encode($results, JSON_UNESCAPED_UNICODE), $id, $version]);
    return $st->rowCount() ? $version + 1 : null;
}

function tournament_delete(int $id): void
{
    q('DELETE FROM tournaments WHERE id = ?', [$id]);
}

/** Ištrina visas test varžybas. Grąžina, kiek ištrinta. */
function tournaments_delete_test(): int
{
    return tournaments_ready() ? q('DELETE FROM tournaments WHERE is_test = 1')->rowCount() : 0;
}

/** Lytis pagal vardą, jei nenurodyta (lietuviški vardai: -a, -ė - moteriški) */
function varz_guess_gender(string $firstName): string
{
    return preg_match('/(a|ė|ija|ia)$/u', mb_strtolower(trim($firstName))) ? 'F' : 'M';
}

/** Karateka aktyvūs nariai, kuriuos galima pridėti į varžybas: [{id, name, birth, gender, belt, group}] */
function varz_club_members(): array
{
    $rows = q_all('SELECT m.id, m.first_name, m.last_name, m.birth_date, m.gender, m.belt_level, g.name AS group_name
                   FROM members m LEFT JOIN training_groups g ON g.id = m.group_id
                   WHERE m.status = "active" ORDER BY g.sort_order IS NULL, g.sort_order, m.last_name, m.first_name');
    return array_map(function ($m) {
        return ['id' => (int) $m['id'], 'name' => $m['first_name'] . ' ' . $m['last_name'], 'birth' => (int) substr((string) $m['birth_date'], 0, 4) ?: null,
                'gender' => $m['gender'] ?: varz_guess_gender($m['first_name']), 'belt' => $m['belt_level'] !== null ? (int) $m['belt_level'] : null,
                'group' => (string) ($m['group_name'] ?? '')];
    }, $rows);
}

/** Karateka narys kaip varžybų dalyvis (id „m“ + nario id - tas pats narys nepridedamas du kartus) */
function varz_person_from_member(array $m): array
{
    return ['id' => 'm' . $m['id'], 'name' => $m['name'], 'club' => VARZ_HOME_CLUB, 'birth' => $m['birth'], 'gender' => $m['gender'], 'belt' => $m['belt'], 'member' => $m['id']];
}

/**
 * Test varžybos: visi aktyvūs Karateka nariai + po 5-12 išgalvotų dalyvių iš kitų federacijos klubų, komandos,
 * kategorijos sudaromos automatiškai (atidarius), dalis kovų „sužaidžiama“ atsitiktinai - kad iškart matytųsi lentelės ir rezultatai.
 */
function tournament_generate_test(int $by): int
{
    $first = ['M' => ['Jonas', 'Lukas', 'Matas', 'Domas', 'Tomas', 'Paulius', 'Rokas', 'Kajus', 'Nojus', 'Augustas', 'Ignas', 'Dovydas', 'Herkus', 'Benas', 'Jokūbas', 'Adomas'],
              'F' => ['Ona', 'Ieva', 'Gabija', 'Emilija', 'Austėja', 'Kamilė', 'Urtė', 'Gustė', 'Liepa', 'Saulė', 'Rugilė', 'Smiltė', 'Viltė', 'Elzė', 'Luknė', 'Goda']];
    $last = ['Kazlauskas', 'Petrauskas', 'Jankauskas', 'Stankevičius', 'Vasiliauskas', 'Žukauskas', 'Butkus', 'Paulauskas', 'Urbonas', 'Kavaliauskas',
             'Navickas', 'Rimkus', 'Balčiūnas', 'Mockus', 'Šimkus', 'Lukoševičius', 'Morkūnas', 'Grigas', 'Sakalauskas', 'Vaitkus'];
    $female = function (string $l): string {
        return preg_match('/ius$/u', $l) ? preg_replace('/ius$/u', 'iūtė', $l) : preg_replace(['/as$/u', '/us$/u', '/is$/u'], ['aitė', 'utė', 'ytė'], $l);
    };
    $year = (int) date('Y');
    $people = [];
    foreach (varz_club_members() as $m) {
        if ($m['birth']) {
            $people['m' . $m['id']] = varz_person_from_member($m) + ['belt' => null];
            $people['m' . $m['id']]['belt'] = $m['belt'] ?: mt_rand(1, 4);
        }
    }
    $used = [];
    $clubs = varz_federation_clubs();
    $uid = 1;
    foreach (array_slice($clubs, 1) as $club) {
        $n = mt_rand(5, 12);
        for ($i = 0; $i < $n; $i++) {
            $g = mt_rand(0, 9) < 5 ? 'M' : 'F';
            for ($guard = 0; $guard < 30; $guard++) {
                $l = $last[array_rand($last)];
                $name = $first[$g][array_rand($first[$g])] . ' ' . ($g === 'F' ? $female($l) : $l);
                if (!isset($used[$name])) {
                    break;
                }
            }
            $used[$name] = true;
            $age = [6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 19, 24, 31][mt_rand(0, 14)];
            $belt = max(1, min(12, (int) round(($age - 5) / 1.6) + mt_rand(-1, 2)));
            $id = 'x' . ($uid++);
            $people[$id] = ['id' => $id, 'name' => $name, 'club' => $club, 'birth' => $year - $age, 'gender' => $g, 'belt' => $belt, 'member' => null];
        }
    }
    // Komandos: po vieną iš klubų, kuriuose yra bent 3 dalyviai nuo 10 metų
    $teams = [];
    $teamCompetitors = [];
    foreach ($clubs as $club) {
        $mem = array_values(array_filter($people, function ($p) use ($club, $year) { return $p['club'] === $club && $year - (int) $p['birth'] >= 10; }));
        if (count($mem) >= 3) {
            $mem = array_slice($mem, 0, 3);
            $tid = 't' . (count($teams) + 1);
            $tname = $club . ' (' . implode(', ', array_map(function ($p) { return explode(' ', $p['name'])[0]; }, $mem)) . ')';
            $teams[$tid] = ['id' => $tid, 'name' => $tname, 'club' => $club, 'members' => array_column($mem, 'id')];
            $teamCompetitors[] = ['id' => $tid, 'name' => $tname, 'club' => $club, 'team' => true];
        }
    }
    $state = tournament_default_state();
    $state['people'] = $people ?: (object) [];
    $state['teams'] = $teams ?: (object) [];
    $state['categories'] = count($teamCompetitors) >= 2
        ? [['id' => 'kt1', 'name' => 'Kata komandos Mix', 'discipline' => 'kata', 'team' => true, 'competitors' => $teamCompetitors, 'tatami' => 3, 'draw' => null]] : [];
    $state['areas'][] = ['id' => 3, 'name' => '3 TATAMIS', 'start' => '09:30'];
    $state['autoCats'] = true;    // kategorijos sudaromos atidarius (assets/varzybos.js)
    $state['simulate'] = 0.6;     // ir dalis kovų „sužaidžiama“
    $date = date('Y-m-d', strtotime('next saturday'));
    $n = (int) q_value('SELECT COUNT(*) FROM tournaments WHERE is_test = 1') + 1;
    return tournament_create('Test varžybos #' . $n . ' · Budo Cup pavyzdys', $date, null, 'Vilnius, Sporto centras Viršuliškės', true, $by, $state);
}

/**
 * Rezultatai → karateka.lt renginys ir narių taškai: prizininkams vieta (geriausia, jei kelios kategorijos), kitiems Karateka dalyviams - dalyvavimas.
 * Jei renginio dar nėra - sukuriamas (tipas „Varžybos“). Grąžina [renginio id, kiek narių pakeista].
 */
function tournament_results_to_points(array $t, int $by): array
{
    $state = tournament_state($t);
    $results = tournament_results($t);
    $best = [];
    foreach ((array) ($state['people'] ?? []) as $p) {
        if (!empty($p['member']) && ($p['club'] ?? '') === VARZ_HOME_CLUB) {
            $best[(int) $p['member']] = 'part';
        }
    }
    foreach ((array) ($results['categories'] ?? []) as $c) {
        foreach ((array) ($c['places'] ?? []) as $pl) {
            foreach ((array) ($pl['members'] ?? []) as $mid) {
                $mid = (int) $mid;
                $cur = $best[$mid] ?? 'part';
                if ($cur === 'part' || (int) $pl['place'] < (int) $cur) {
                    $best[$mid] = (string) (int) $pl['place'];
                }
            }
        }
    }
    // Tik tikri, aktyvūs nariai
    $ids = array_keys($best);
    $valid = $ids ? array_map('intval', q('SELECT id FROM members WHERE id IN (' . implode(',', $ids) . ')')->fetchAll(PDO::FETCH_COLUMN)) : [];
    $best = array_intersect_key($best, array_flip($valid));

    $event = $t['event_id'] ? q_one('SELECT * FROM events WHERE id = ?', [(int) $t['event_id']]) : null;
    if (!$event) {
        q('INSERT INTO events (type, title, starts_on, ends_on, location, description) VALUES ("competition", ?, ?, ?, ?, ?)',
            [$t['title'], $t['starts_on'], $t['ends_on'], $t['location'], 'Lentelės ir rezultatai - varžybų sistemoje.']);
        $eventId = (int) db()->lastInsertId();
        q('UPDATE tournaments SET event_id = ? WHERE id = ?', [$eventId, (int) $t['id']]);
        $event = q_one('SELECT * FROM events WHERE id = ?', [$eventId]);
    }
    $changes = $best ? save_event_results($event, $best, $by) : 0;
    return [(int) $event['id'], count($best), $changes];
}

/** LTKF (federacijos) svetainė: config.php 'ltkf_url', o test svetainėje ir kompiuteryje - juodraštis /ltkf/ */
function ltkf_url(): string
{
    $u = trim((string) config('ltkf_url', ''));
    return $u !== '' ? $u : (config('env') !== 'live' ? url('ltkf/') : '');
}

/** Apačios nuorodos visuose puslapiuose: federacija ir varžybų rezultatai (jei yra paskelbtų) */
function footer_links_html(): string
{
    $links = [];
    if ($u = ltkf_url()) {
        $links[] = '<a href="' . e($u) . '">' . te('Lietuvos tradicinio karatė federacija (LTKF)') . '</a>';
    }
    if (tournaments_ready() && array_filter(tournaments_list(), 'tournament_is_visible')) {
        $links[] = '<a href="' . e(url('varzybu-rezultatai.php')) . '">' . te('Varžybų rezultatai') . '</a>';
    }
    return $links ? ' · ' . implode(' · ', $links) : '';
}

// ---------- Transliacija ir registracija per nuorodą (db/026) ----------

/** Nuorodos raktas: 'share' - žiūrovams ir ekranams, 'reg' - kitų klubų registracijai. Sukuriamas pirmą kartą prireikus. */
function tournament_token(array $t, string $kind): string
{
    $col = $kind === 'reg' ? 'reg_token' : 'share_token';
    if (!empty($t[$col])) {
        return (string) $t[$col];
    }
    q("UPDATE tournaments SET $col = ? WHERE id = ? AND $col IS NULL", [bin2hex(random_bytes(12)), (int) $t['id']]);
    return (string) q_value("SELECT $col FROM tournaments WHERE id = ?", [(int) $t['id']]);
}

function tournament_by_token(string $token, string $kind): ?array
{
    if (!preg_match('/^[a-f0-9]{24}$/', $token) || !tournaments_ready()) {
        return null;
    }
    try {
        return q_one('SELECT * FROM tournaments WHERE ' . ($kind === 'reg' ? 'reg_token' : 'share_token') . ' = ?', [$token]);
    } catch (PDOException $ex) {
        return null;   // dar neimportuotas db/026
    }
}

/** Kas vyksta ant tatamio dabar (iš kovos skydelio): išsaugoma su serverio laiku (ms) - laikrodžiui ekranuose */
function tournament_live_set(int $id, int $area, array $data): void
{
    q('INSERT INTO tournament_live (tournament_id, area_id, data, updated_ms) VALUES (?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE data = VALUES(data), updated_ms = VALUES(updated_ms)',
        [$id, $area, json_encode($data, JSON_UNESCAPED_UNICODE), (int) round(microtime(true) * 1000)]);
}

/** [area_id => ['data' => [...], 'ms' => serverio laikas, kada gauta]] */
function tournament_live_get(int $id): array
{
    $out = [];
    try {
        foreach (q_all('SELECT area_id, data, updated_ms FROM tournament_live WHERE tournament_id = ?', [$id]) as $r) {
            $out[(int) $r['area_id']] = ['data' => json_decode((string) $r['data'], true) ?: [], 'ms' => (int) $r['updated_ms']];
        }
    } catch (PDOException $ex) {
        // dar neimportuotas db/026
    }
    return $out;
}

/** Nauji (dar nepridėti) užsiregistravę per nuorodą */
function tournament_new_entries(int $id): array
{
    try {
        return q_all('SELECT id, club, contact, name, birth_year, gender, belt, created_at FROM tournament_entries WHERE tournament_id = ? AND status = "new" ORDER BY club, id', [$id]);
    } catch (PDOException $ex) {
        return [];
    }
}

/** Vardas viešai: vaikų ir jaunių (iki 18 m.) - „Vardas P.“ */
function varz_public_name(array $p): string
{
    $parts = preg_split('/\s+/u', trim((string) ($p['name'] ?? '')));
    return !empty($p['minor']) && count($parts) > 1 ? $parts[0] . ' ' . mb_substr(end($parts), 0, 1) . '.' : (string) ($p['name'] ?? '');
}
