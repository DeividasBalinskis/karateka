<?php
// Bendros funkcijos: nustatymai, turinio failai, datos lietuviškai, varžybų sistemos ryšys

date_default_timezone_set('Europe/Vilnius');

function n(string $key, $default = null)
{
    static $n = null;
    if ($n === null) {
        $n = require __DIR__ . '/nustatymai.php';
        // Redaguojami pavadinimas ir kontaktai („🎨 Išvaizda ir kontaktai“)
        if (function_exists('ltkf_nust')) {
            foreach (['pavadinimas', 'trumpas', 'el_pastas', 'telefonas'] as $k) {
                $v = trim((string) (ltkf_nust()[$k] ?? ''));
                if ($v !== '') {
                    $n[$k] = $v;
                }
            }
        }
    }
    return $n[$key] ?? $default;
}

/** Turinio failas iš turinys/ (masyvas). Vėliau - redaguojamas per administravimą. */
function turinys(string $name): array
{
    static $cache = [];
    if ($name === 'klubai' && !isset($cache[$name]) && ($db = registro_klubai())) {
        return $cache[$name] = $db;   // #160: klubų registras (Valdymas → Federacijos registrai), jei jau užpildytas
    }
    return $cache[$name] = $cache[$name] ?? require dirname(__DIR__) . '/turinys/' . $name . '.php';
}

/** #160: klubai iš karateka.lt registro (db/048) - ta pati forma kaip turinys/klubai.php; tuščias - jei registro dar nėra */
function registro_klubai(): array
{
    $db = function_exists('karateka_db') ? karateka_db() : null;
    if (!$db) {
        return [];
    }
    try {
        $rows = $db->query("SELECT * FROM ltkf_clubs WHERE status <> 'former' ORDER BY sort_order, name")->fetchAll();
    } catch (Throwable $ex) {
        return [];
    }
    return array_map(function ($r) {
        return array_filter(['id' => $r['slug'], 'pavadinimas' => $r['name'], 'juridinis' => $r['legal_name'], 'miestas' => $r['city'] ?: '[miestas]',
            'svetaine' => (string) $r['website'], 'treneris' => $r['coach'] ?: '[vyr. treneris]', 'el_pastas' => $r['email'], 'telefonas' => $r['phone'],
            'nuo' => $r['since'] ? (int) $r['since'] : null, 'lat' => $r['lat'] !== null ? (float) $r['lat'] : null, 'lng' => $r['lng'] !== null ? (float) $r['lng'] : null,
            'pavyzdys' => !$r['city'] || !$r['coach'], 'kandidatas' => $r['status'] === 'candidate'], function ($v) { return $v !== null; });
    }, $rows);
}

/** #160: kitų klubų teisėjai iš registro (vardas, klubas, kategorija, galioja iki) - viešai (sprendimas 32) */
function registro_teisejai(): array
{
    $db = function_exists('karateka_db') ? karateka_db() : null;
    if (!$db) {
        return [];
    }
    try {
        $rows = $db->query('SELECT name, club, category, valid_until FROM ltkf_judges ORDER BY name')->fetchAll();
    } catch (Throwable $ex) {
        return [];
    }
    return array_map(function ($r) {
        return ['vardas' => $r['name'], 'klubas' => (string) $r['club'], 'kategorija' => (string) $r['category'],
                'galioja_iki' => $r['valid_until'] && $r['valid_until'] >= date('Y-m-d') ? $r['valid_until'] : null];
    }, $rows);
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

// ---------- Karateka svetainės duomenys (tas pats serveris): klubas, varžybos ir rezultatai iš varžybų sistemos „Taika“ ----------

/** Karateka config.php (env, db) arba [] */
function karateka_config(): array
{
    static $c = null;
    if ($c === null) {
        $c = @include dirname(__DIR__, 2) . '/config.php';
        $c = is_array($c) ? $c : [];
    }
    return $c;
}

/** Karateka duomenų bazė arba null (tada rodomi tik turinio failai) */
function karateka_db(): ?PDO
{
    static $pdo = false;
    if ($pdo === false) {
        $pdo = null;
        $d = karateka_config()['db'] ?? null;
        if (is_array($d)) {
            try {
                $pdo = new PDO('mysql:host=' . $d['host'] . ';dbname=' . $d['name'] . ';charset=utf8mb4', $d['user'], $d['pass'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 2]);
            } catch (Throwable $ex) {
                $pdo = null;
            }
        }
    }
    return $pdo;
}

/** Viešos varžybos iš Karateka varžybų sistemos (test - tik test svetainėje) */
function karateka_turnyrai(): array
{
    static $rows = null;
    if ($rows === null) {
        $rows = [];
        if ($db = karateka_db()) {
            try {
                $live = (karateka_config()['env'] ?? 'live') === 'live';
                $cols = 'id, title, starts_on, ends_on, location, status, is_test, results';
                try {
                    $db->query('SELECT ltkf_approved FROM tournaments LIMIT 1');
                    $cols .= ', ltkf_approved';
                } catch (Throwable $ex) {
                    // dar neimportuotas db/046
                }
                $rows = $db->query('SELECT ' . $cols . ' FROM tournaments WHERE is_public = 1'
                    . ($live ? ' AND is_test = 0' : '') . ' ORDER BY starts_on DESC, id DESC')->fetchAll();
            } catch (Throwable $ex) {
                $rows = [];   // karateka dar be db/025
            }
        }
    }
    return $rows;
}

/** Vaikų ir jaunių vardai viešai: „Vardas P.“ */
function trumpas_vardas(string $name, bool $minor): string
{
    $parts = preg_split('/\s+/u', trim($name));
    return $minor && count($parts) > 1 ? $parts[0] . ' ' . mb_substr(end($parts), 0, 1) . '.' : $name;
}

/** Karateka varžybų rezultatai rezultatų puslapio formatu [raktas => [pavadinimas, data, klubai, kategorijos]] */
function karateka_rezultatai(): array
{
    $out = [];
    foreach (karateka_turnyrai() as $t) {
        $r = json_decode((string) $t['results'], true);
        if (!is_array($r) || empty($r['medals'])) {
            continue;
        }
        $cats = [];
        foreach ((array) ($r['categories'] ?? []) as $c) {
            if (empty($c['places'])) {
                continue;
            }
            $cats[] = ['pavadinimas' => $c['name'], 'baigta' => !empty($c['finished']),
                'vietos' => array_map(function ($p) { return [trumpas_vardas((string) $p['name'], !empty($p['minor'])), (string) $p['club'], (int) $p['place']]; }, $c['places'])];
        }
        $out['k' . $t['id']] = [
            'pavadinimas' => $t['title'] . ((int) $t['is_test'] ? ' (TEST)' : ''), 'data' => $t['starts_on'], 'pavyzdys' => (int) $t['is_test'] === 1, 'is_sistemos' => true,
            'nuoroda' => '../varzybu-rezultatai.php?id=' . (int) $t['id'], 'vyksta' => $t['status'] !== 'finished',
            'klubai' => array_map(function ($m) { return ['klubas' => $m['club'], 'a' => (int) $m['g'], 's' => (int) $m['s'], 'b' => (int) $m['b']]; }, $r['medals']),
            'kategorijos' => $cats,
        ];
    }
    return $out;
}

/**
 * Partneriai - tie patys, kaip karateka.lt apačioje (redaguojami Karateka pagrindiniame puslapyje), be pačios LTKF; pirmas - Karateka.
 * [['vardas', 'logo' (kelias nuo LTKF aplanko), 'nuoroda'], ...]
 */
function partneriai(): array
{
    $items = [];
    if ($db = karateka_db()) {
        try {
            $page = json_decode((string) $db->query("SELECT value FROM site_settings WHERE name = 'home_page'")->fetchColumn(), true);
            foreach ((array) $page as $b) {
                if (($b['type'] ?? '') === 'partners' && empty($b['hidden'])) {
                    $items = (array) ($b['d']['items'] ?? []);
                }
            }
        } catch (Throwable $ex) {
            $items = [];
        }
    }
    if (!$items) {   // numatytieji (kaip karateka.lt)
        foreach (['ltkf' => 'Lietuvos tradicinio karate federacija', 'sanrei' => 'Sanrei karate klubas', 'vilniaus-karate-mokykla' => 'Vilniaus karate mokykla',
                  'karate-akademija' => 'Karate akademija', 'takami' => 'Takami tradicinio karate-do klubas', 'budo-cup' => 'Budo Cup Open'] as $f => $n) {
            $items[] = ['image' => 'img/partneriai/' . $f . '.jpg', 'name' => $n, 'url' => ''];
        }
    }
    $out = [['Karateka (VšĮ Karate Ateitis)', '../img/logo.png', '../index.php']];
    foreach ($items as $it) {
        if (empty($it['image']) || strpos((string) $it['image'], 'ltkf') !== false || preg_match('~^(https?:|/)~', (string) $it['image'])) {
            continue;
        }
        $out[] = [(string) ($it['name'] ?? ''), '../' . ltrim((string) $it['image'], '/'), (string) ($it['url'] ?? '')];
    }
    return $out;
}

/** Karateka klubo gyvi duomenys: aktyvių narių skaičius ir treniruočių vietos */
function karateka_klubas(): ?array
{
    $db = karateka_db();
    if (!$db) {
        return null;
    }
    try {
        return [
            'nariai' => (int) $db->query('SELECT COUNT(*) FROM members WHERE status = "active"')->fetchColumn(),
            'vietos' => $db->query('SELECT DISTINCT location FROM training_groups WHERE is_active = 1 AND location IS NOT NULL AND location <> "" ORDER BY location')->fetchAll(PDO::FETCH_COLUMN),
            'grupes' => (int) $db->query('SELECT COUNT(*) FROM training_groups WHERE is_active = 1')->fetchColumn(),
        ];
    } catch (Throwable $ex) {
        return null;
    }
}

/**
 * Karatekos teisėjai (nariai su žyma „Teisėjas“ karateka.lt) teisėjų sąrašui: vardas, klubas ir ar galioja teisėjų testas (išlaikytas per 12 mėn.).
 * Vaikų ir jaunių vardai - „Vardas P.“. [['vardas', 'klubas', 'galioja_iki' => 'Y-m-d' | null], ...]
 */
function karateka_teisejai(): array
{
    $db = karateka_db();
    if (!$db) {
        return [];
    }
    try {
        $rows = $db->query("SELECT m.first_name, m.last_name, m.birth_date,
                (SELECT MAX(qa.finished_at) FROM quiz_attempts qa JOIN quizzes q ON q.id = qa.quiz_id
                  WHERE qa.member_id = m.id AND q.audience = 'teisejas' AND qa.passed = 1) AS islaike
              FROM member_tags t JOIN members m ON m.id = t.member_id
             WHERE t.tag = 'teisejas' AND m.status = 'active' ORDER BY m.last_name, m.first_name")->fetchAll();
    } catch (Throwable $ex) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        $minor = $r['birth_date'] && $r['birth_date'] > date('Y-m-d', strtotime('-18 years'));
        $iki = $r['islaike'] ? date('Y-m-d', strtotime($r['islaike'] . ' +12 months')) : null;
        $out[] = ['vardas' => trumpas_vardas($r['first_name'] . ' ' . $r['last_name'], $minor), 'klubas' => 'Karateka',
                  'galioja_iki' => $iki && $iki >= date('Y-m-d') ? $iki : null];
    }
    usort($out, function ($a, $b) { return [$a['galioja_iki'] === null, $a['vardas']] <=> [$b['galioja_iki'] === null, $b['vardas']]; });
    return $out;
}

/** Varžybos iš varžybų sistemos kalendoriaus formatu (tušti, jei sistema nepasiekiama) */
function varzybos_renginiai(): array
{
    $out = [];
    foreach (karateka_turnyrai() as $t) {
        if (array_key_exists('ltkf_approved', $t) && !(int) $t['ltkf_approved']) {
            continue;   // kalendoriuje - tik federacijos patvirtintos (sprendimas 2026-11-20)
        }
        $out[] = [
            'id' => 'k' . (int) $t['id'], 'pavadinimas' => (string) $t['title'], 'nuo' => (string) $t['starts_on'], 'iki' => $t['ends_on'] ?: null,
            'vieta' => (string) $t['location'], 'tipas' => 'varzybos', 'registracija' => $t['status'] === 'registration', 'is_sistemos' => true,
            'pavyzdys' => (int) $t['is_test'] === 1, 'nuoroda' => '../varzybu-rezultatai.php?id=' . (int) $t['id'],
        ];
    }
    $data = varzybos_api('tournaments');
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

// ---------- Karateka sesija (tas pats serveris ir slapukas): ar žiūri karateka.lt adminas ----------

/** Karateka sesijos duomenys - tik skaitomi (sesija neužrakinama) */
function karateka_sesija(): array
{
    static $s = null;
    if ($s === null) {
        $s = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $s = $_SESSION;
        } elseif (preg_match('/^[A-Za-z0-9,-]{20,128}$/', (string) ($_COOKIE['karateka_sid'] ?? ''))) {
            session_name('karateka_sid');
            if (@session_start(['read_and_close' => true])) {
                $s = $_SESSION ?? [];
            }
        }
    }
    return $s;
}

/** Ar prisijungęs karateka.lt administratorius (tik jis mato nuorodą atgal į karateka.lt) */
function karateka_adminas(): bool
{
    $id = (int) (karateka_sesija()['account_id'] ?? 0);
    $db = $id ? karateka_db() : null;
    if (!$db) {
        return false;
    }
    try {
        $st = $db->prepare('SELECT * FROM accounts WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return false;
    }
    return $r && $r['role'] === 'admin' && $r['status'] === 'active' && empty($r['is_tester']);   // testuotojas - ne administratorius (db/044)
}
