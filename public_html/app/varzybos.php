<?php
// Ryšys su varžybų sistema per jos API.
// config.php: 'varzybos' => ['url' => 'https://varzybos.karateka.lt', 'key' => '...', 'club_name' => 'Karate Ateitis', 'club_city' => 'Vilnius']
// 'public_url' - adresas naršyklei, jei skiriasi nuo 'url' (pvz. lokaliai Docker'yje).
// Lokaliai (env = local) be nustatymų jungiamasi prie Docker'yje veikiančios varžybų sistemos (http://localhost:8090).

function varzybos_config(string $key, $default = null)
{
    $c = config('varzybos');
    if (!$c && config('env') === 'local') {
        $c = ['url' => 'http://host.docker.internal:8090', 'public_url' => 'http://localhost:8090', 'key' => 'vietinis-raktas-pakeiskite-serveryje'];
    }
    return is_array($c) && array_key_exists($key, $c) ? $c[$key] : $default;
}

function varzybos_enabled(): bool
{
    return (bool) varzybos_config('url') && strlen((string) varzybos_config('key')) >= 16;
}

/** Užklausa į varžybų sistemą. Klaidos atveju - RuntimeException su žinute žmogui. */
function varzybos_api(string $method, string $route, ?array $body = null, array $query = []): array
{
    if (!varzybos_enabled()) {
        throw new RuntimeException('Varžybų sistema neprijungta (config.php: varzybos).');
    }
    $url = rtrim((string) varzybos_config('url'), '/') . '/api.php?r=v1/' . $route . ($query ? '&' . http_build_query($query) : '');
    $headers = ['Accept: application/json', 'X-Api-Key: ' . varzybos_config('key')];
    $payload = $body !== null ? json_encode($body, JSON_UNESCAPED_UNICODE) : null;
    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
        ]);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $payload ?? '', 'timeout' => 10, 'ignore_errors' => true]]);
        $raw = @file_get_contents($url, false, $ctx);
        $status = isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m) ? (int) $m[1] : 0;
    }
    if ($raw === false || $status === 0) {
        throw new RuntimeException('Varžybų sistema šiuo metu nepasiekiama. Bandykite vėliau.');
    }
    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Varžybų sistema grąžino netinkamą atsakymą.');
    }
    if ($status >= 400) {
        throw new RuntimeException($data['error'] ?? 'Varžybų sistemos klaida (' . $status . ').');
    }
    return $data;
}

/** Varžybos, į kurias dabar galima registruotis */
function varzybos_open_tournaments(): array
{
    $list = varzybos_api('GET', 'tournaments')['tournaments'] ?? [];
    return array_values(array_filter($list, function ($t) { return !empty($t['registration_open']); }));
}

/** Nario duomenys varžybų sistemai. Sportininkas atpažįstamas pagal nario id. */
function varzybos_athlete(array $member): array
{
    return [
        'external_id' => (string) $member['id'],
        'first_name'  => $member['first_name'],
        'last_name'   => $member['last_name'],
        'birth_date'  => $member['birth_date'],
        'gender'      => $member['gender'] ?? null,
        'belt_level'  => $member['belt_level'] !== null ? (int) $member['belt_level'] : null,
        // Ar organizatorius gali skelbti varžybų nuotraukas (null - tėvai dar neatsakė, laikoma „ne“)
        'photo_ok'    => isset($member['photo_events']) && (int) $member['photo_events'] === 1,
    ];
}

function varzybos_club(): array
{
    return ['external_id' => 'karate-ateitis', 'name' => varzybos_config('club_name', 'Karate Ateitis'), 'city' => varzybos_config('club_city', 'Vilnius')];
}

/** Kategorijos, kurioms narys tinka */
function varzybos_eligible(array $member, int $tournamentId): array
{
    $a = varzybos_athlete($member);
    return varzybos_api('POST', 'eligible', ['tournament_id' => $tournamentId, 'athlete' => $a])['categories'] ?? [];
}

function varzybos_register(array $member, int $tournamentId, array $categoryIds): array
{
    return varzybos_api('POST', 'registrations', [
        'tournament_id' => $tournamentId,
        'athlete'       => varzybos_athlete($member),
        'club'          => varzybos_club(),
        'category_ids'  => array_values(array_map('intval', $categoryIds)),
    ]);
}

/** Narių registracijos varžybose: [member_id => [registracija, ...]] */
function varzybos_registrations(int $tournamentId, array $memberIds): array
{
    if (!$memberIds) {
        return [];
    }
    $rows = varzybos_api('GET', 'registrations', null, ['tournament_id' => $tournamentId, 'external_ids' => implode(',', $memberIds)])['registrations'] ?? [];
    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r['external_id']][] = $r;
    }
    return $out;
}

/**
 * Rezultatai karateka.lt renginiui: [member_id => ['result' => 'part'|'1'|'2'|'3', 'details' => [...]]].
 * Imama geriausia nario vieta visose kategorijose. Dalyvavo, bet vietos neužėmė - 'part'.
 */
function varzybos_event_results(int $tournamentId): array
{
    $res = varzybos_api('GET', 'tournaments/' . $tournamentId . '/results');
    $regs = varzybos_api('GET', 'registrations', null, ['tournament_id' => $tournamentId])['registrations'] ?? [];
    $finished = [];
    foreach ($res['categories'] ?? [] as $cat) {
        $finished[$cat['name']] = !empty($cat['finished']);
    }
    $out = [];
    $ours = [];   // kategorijos, kuriose dalyvauja klubo nariai
    foreach ($regs as $r) {
        $mid = (int) $r['external_id'];
        $out[$mid] = $out[$mid] ?? ['best' => null, 'details' => []];
        $out[$mid]['details'][$r['category']] = null;
        $ours[$r['category']] = true;
    }
    foreach ($res['categories'] ?? [] as $cat) {
        foreach ($cat['results'] as $r) {
            foreach ($r['athletes'] as $ath) {
                if (($ath['external_source'] ?? '') !== 'karateka' || !ctype_digit((string) $ath['external_id'])) {
                    continue;
                }
                $mid = (int) $ath['external_id'];
                $out[$mid] = $out[$mid] ?? ['best' => null, 'details' => []];
                $out[$mid]['details'][$cat['name']] = (int) $r['place'];
                $ours[$cat['name']] = true;
                if ($r['medal'] && ($out[$mid]['best'] === null || $r['place'] < $out[$mid]['best'])) {
                    $out[$mid]['best'] = (int) $r['place'];
                }
            }
        }
    }
    foreach ($out as &$o) {
        $o['result'] = $o['best'] !== null && $o['best'] <= 3 ? (string) $o['best'] : 'part';
    }
    unset($o);
    return [
        'members'    => $out,
        'tournament' => $res['tournament'] ?? null,
        'finished'   => $finished,
        'unfinished' => array_values(array_filter(array_keys($ours), function ($name) use ($finished) { return empty($finished[$name]); })),
    ];
}

/** Varžybų sistemos adresas naršyklei (nuorodoms ir mygtukams) */
function varzybos_public_url(string $hash = ''): string
{
    return rtrim((string) (varzybos_config('public_url') ?: varzybos_config('url')), '/') . '/' . ($hash !== '' ? '#' . $hash : '');
}

/**
 * Prisijungimas prie varžybų sistemos be atskiro slaptažodžio: pasirašyta (HMAC su API raktu), vienkartinė nuoroda, galioja 2 min.
 * Adminas tampa organizatoriumi, treneris - sekretoriumi. $to - varžybų sistemos puslapis, pvz. /admin arba /admin/t/3.
 */
function varzybos_sso_url(array $account, string $to = '/admin'): string
{
    $payload = json_encode([
        'email' => $account['email'],
        'name'  => trim($account['first_name'] . ' ' . $account['last_name']),
        'role'  => is_admin($account) ? 'admin' : 'secretary',
        'ts'    => time(),
        'nonce' => bin2hex(random_bytes(12)),
        'to'    => $to,
    ], JSON_UNESCAPED_UNICODE);
    $p = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    return varzybos_public_url() . 'sso.php?p=' . $p . '&s=' . hash_hmac('sha256', $p, (string) varzybos_config('key'));
}

/** karateka.lt renginiai, susieti su šiomis varžybomis */
function varzybos_linked_events(int $tournamentId): array
{
    return q_all('SELECT * FROM events WHERE tournament_id = ? ORDER BY starts_on', [$tournamentId]);
}

/** Užsiregistravusį varžybų sistemoje narį pažymėti „Dalyvaus“ susietame renginyje (teisėjo vaidmuo nekeičiamas) */
function varzybos_mark_going(int $tournamentId, int $memberId, ?int $by = null): int
{
    $n = 0;
    foreach (varzybos_linked_events($tournamentId) as $ev) {
        $p = event_participant((int) $ev['id'], $memberId);
        if (!$p || $p['status'] !== 'going') {
            set_participation((int) $ev['id'], $memberId, 'going', $by, $p['role'] ?? 'participant');
            $n++;
        }
    }
    return $n;
}

const VARZYBOS_STATUS = ['registration' => 'registracija', 'closed' => 'registracija baigta', 'running' => 'vyksta', 'finished' => 'baigtos'];

