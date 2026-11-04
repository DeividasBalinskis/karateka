<?php
// Bendros funkcijos: nustatymai, turinio failai, datos lietuviškai, varžybų sistemos ryšys

date_default_timezone_set('Europe/Vilnius');

function n(string $key, $default = null)
{
    static $n = null;
    $n = $n ?? require __DIR__ . '/nustatymai.php';
    return $n[$key] ?? $default;
}

/** Turinio failas iš turinys/ (masyvas). Vėliau - redaguojamas per administravimą. */
function turinys(string $name): array
{
    static $cache = [];
    return $cache[$name] = $cache[$name] ?? require dirname(__DIR__) . '/turinys/' . $name . '.php';
}

function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

const MENESIAI_KILM = ['sausio', 'vasario', 'kovo', 'balandžio', 'gegužės', 'birželio', 'liepos', 'rugpjūčio', 'rugsėjo', 'spalio', 'lapkričio', 'gruodžio'];
const MENESIAI = ['Sausis', 'Vasaris', 'Kovas', 'Balandis', 'Gegužė', 'Birželis', 'Liepa', 'Rugpjūtis', 'Rugsėjis', 'Spalis', 'Lapkritis', 'Gruodis'];
const MENESIAI_TRUMP = ['saus.', 'vas.', 'kov.', 'bal.', 'geg.', 'birž.', 'liep.', 'rugp.', 'rugs.', 'spal.', 'lapkr.', 'gruod.'];

/** 2026-12-05 -> „2026 m. gruodžio 5 d.“ */
function data_lt(string $ymd): string
{
    $t = strtotime($ymd);
    return date('Y', $t) . ' m. ' . MENESIAI_KILM[(int) date('n', $t) - 1] . ' ' . (int) date('j', $t) . ' d.';
}

/** Renginio datos: viena diena arba intervalas */
function datos_lt(string $nuo, ?string $iki = null): string
{
    if (!$iki || $iki === $nuo) {
        return data_lt($nuo);
    }
    $a = strtotime($nuo);
    $b = strtotime($iki);
    if (date('Y-m', $a) === date('Y-m', $b)) {
        return date('Y', $a) . ' m. ' . MENESIAI_KILM[(int) date('n', $a) - 1] . ' ' . (int) date('j', $a) . '–' . (int) date('j', $b) . ' d.';
    }
    return data_lt($nuo) . ' – ' . data_lt($iki);
}

const RENGINIU_TIPAI = [
    'varzybos'  => 'Varžybos',
    'seminaras' => 'Seminaras',
    'teisejai'  => 'Teisėjams',
    'egzaminas' => 'Egzaminai',
    'stovykla'  => 'Stovykla',
];

function pavyzdys(array $item): string
{
    return !empty($item['pavyzdys']) && n('demo') ? ' <span class="chip chip-demo" title="Pavyzdiniai duomenys - bus pakeisti tikrais">Pavyzdys</span>' : '';
}

/**
 * Varžybų sistemos API (tas pats, kurį naudoja karateka.lt). Nepasiekiama - grąžina null,
 * o puslapiai rodo pavyzdinius duomenis. Rezultatas kešuojamas 60 s, kad puslapiai nelėtėtų.
 */
function varzybos_api(string $route): ?array
{
    $c = n('varzybos', []);
    if (empty($c['url']) || empty($c['key'])) {
        return null;
    }
    $cacheFile = sys_get_temp_dir() . '/ltkf-varzybos-' . md5($c['url'] . $route) . '.json';
    if (is_file($cacheFile) && filemtime($cacheFile) > time() - 60) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        return is_array($cached) && !empty($cached['ok']) ? $cached['data'] : null;
    }
    $url = rtrim($c['url'], '/') . '/api.php?r=v1/' . $route;
    $raw = false;
    $status = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'X-Api-Key: ' . $c['key']],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT        => 3,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['header' => "Accept: application/json\r\nX-Api-Key: " . $c['key'], 'timeout' => 3, 'ignore_errors' => true]]);
        $raw = @file_get_contents($url, false, $ctx);
        $status = isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m) ? (int) $m[1] : 0;
    }
    $data = $raw !== false && $status > 0 && $status < 400 ? json_decode((string) $raw, true) : null;
    $ok = is_array($data);
    @file_put_contents($cacheFile, json_encode(['ok' => $ok, 'data' => $ok ? $data : null]));
    return $ok ? $data : null;
}

/** Varžybos iš varžybų sistemos kalendoriaus formatu (tušti, jei sistema nepasiekiama) */
function varzybos_renginiai(): array
{
    $data = varzybos_api('tournaments');
    $out = [];
    foreach (($data['tournaments'] ?? []) as $t) {
        if (empty($t['starts_on']) || empty($t['title'])) {
            continue;
        }
        $out[] = [
            'id'          => 'v' . (int) ($t['id'] ?? 0),
            'pavadinimas' => (string) $t['title'],
            'nuo'         => (string) $t['starts_on'],
            'iki'         => $t['ends_on'] ?? null,
            'vieta'       => $t['location'] ?? '',
            'tipas'       => 'varzybos',
            'registracija'=> !empty($t['registration_open']),
            'is_sistemos' => true,
            'nuoroda'     => rtrim((string) (n('varzybos')['public_url'] ?? ''), '/') . '/',
        ];
    }
    return $out;
}

/** Visi renginiai: varžybų sistemos + turinio failo, surikiuoti pagal datą */
function visi_renginiai(): array
{
    $all = array_merge(varzybos_renginiai(), turinys('renginiai'));
    usort($all, function ($a, $b) { return strcmp($a['nuo'], $b['nuo']); });
    return $all;
}

function busimi_renginiai(): array
{
    $today = date('Y-m-d');
    return array_values(array_filter(visi_renginiai(), function ($r) use ($today) { return ($r['iki'] ?: $r['nuo']) >= $today; }));
}
