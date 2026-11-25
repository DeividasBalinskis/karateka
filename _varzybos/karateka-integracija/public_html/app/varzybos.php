<?php
// Ryšys su varžybų sistema per jos API.
// config.php: 'varzybos' => ['url' => 'https://varzybos.karateka.lt', 'key' => '...', 'club_name' => 'Karate Ateitis', 'club_city' => 'Vilnius']
// Lokaliai (env = local) be nustatymų jungiamasi prie Docker'yje veikiančios varžybų sistemos (http://localhost:8090).

function varzybos_config(string $key, $default = null)
{
    $c = config('varzybos');
    if (!$c && config('env') === 'local') {
        $c = ['url' => 'http://host.docker.internal:8090', 'key' => 'vietinis-raktas-pakeiskite-serveryje'];
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
    return array_values(array_filter($list, fn ($t) => !empty($t['registration_open'])));
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
        'unfinished' => array_values(array_filter(array_keys($ours), fn ($name) => empty($finished[$name]))),
    ];
}
