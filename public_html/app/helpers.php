<?php
// Bendros pagalbinės funkcijos

/** config('mail.host') */
function config(string $key, $default = null)
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** HTML escape. Naudoti VISUR, kur išvedami duomenys. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Kelias svetainės viduje: url('paskyra.php') */
function url(string $path = ''): string
{
    return (config('base_path') ?: '') . '/' . ltrim($path, '/');
}

/** Pilna nuoroda laiškams */
function abs_url(string $path = ''): string
{
    return rtrim((string) config('site_url'), '/') . url($path);
}

/** Svetainės versija (app/VERSION). Didinama kiekvienam įkėlimui į test serverį - žr. docs/pakeitimai.md */
function site_version(): string
{
    static $v = null;
    if ($v === null) {
        $v = trim((string) @file_get_contents(APP_DIR . '/VERSION')) ?: '?';
    }
    return $v;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function redirect(string $path): void
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/** Išvalyta POST reikšmė (string) */
function post(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

function get(string $key): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

/** Vienkartiniai pranešimai po peradresavimo */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** Saugus grįžimo kelias (tik vidiniai keliai) */
function safe_return(string $path, string $fallback = 'paskyra.php'): string
{
    if ($path === '' || !preg_match('#^/[^/\\\\]#', $path)) {
        return url($fallback);
    }
    return $path;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function not_found(): void
{
    http_response_code(404);
    page_start('Puslapis nerastas');
    echo '<div class="panel card"><h1>Puslapis nerastas</h1><p class="muted">Tokio puslapio nėra arba jis buvo pašalintas. Jei atėjote per seną nuorodą - svetainė atsinaujino.</p>'
        . '<p class="row" style="margin-top:12px;"><a class="btn btn-primary" href="' . e(url('')) . '">Į pradžią</a> '
        . '<a class="btn btn-ghost" href="' . e(url('renginiai.php')) . '">Renginiai</a> '
        . '<a class="btn btn-ghost" href="' . e(url('naujienos.php')) . '">Naujienos</a> '
        . '<a class="btn btn-ghost" href="' . e(url('prisijungti.php')) . '">Prisijungti</a></p></div>';
    page_end();
    exit;
}

// ---------- Datos ir amžius ----------

const LT_WEEKDAYS = [1 => 'Pirmadienis', 'Antradienis', 'Trečiadienis', 'Ketvirtadienis', 'Penktadienis', 'Šeštadienis', 'Sekmadienis'];
const LT_MONTHS_GEN = [1 => 'sausio', 'vasario', 'kovo', 'balandžio', 'gegužės', 'birželio', 'liepos', 'rugpjūčio', 'rugsėjo', 'spalio', 'lapkričio', 'gruodžio'];

/** "spalio 6 d." arba "2027 m. sausio 3 d." */
/** Skaičius su teisinga galūne: kiek(1, 'narys', 'nariai', 'narių') -> „1 narys“, 14 -> „14 narių“, 22 -> „22 nariai“ */
function kiek(int $n, string $one, string $few, string $many): string
{
    $d = $n % 10;
    $t = $n % 100;
    $word = ($d === 1 && $t !== 11) ? $one : (($d >= 2 && $d <= 9 && ($t < 12 || $t > 19)) ? $few : $many);
    return $n . ' ' . $word;
}

function fmt_date(?string $date, bool $forceYear = false): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    $s = LT_MONTHS_GEN[(int) date('n', $ts)] . ' ' . date('j', $ts) . ' d.';
    if ($forceYear || date('Y', $ts) !== date('Y')) {
        $s = date('Y', $ts) . ' m. ' . $s;
    }
    return $s;
}

function fmt_time(?string $time): string
{
    return $time ? substr($time, 0, 5) : '';
}

function age_on(string $birthDate, ?string $on = null): int
{
    return (new DateTime($birthDate))->diff(new DateTime($on ?? 'today'))->y;
}

/** Tikra data formatu YYYY-MM-DD, ne ateityje, ne senesnė nei 100 m. */
function valid_birth_date(string $d): bool
{
    $dt = DateTime::createFromFormat('!Y-m-d', $d);
    if (!$dt || $dt->format('Y-m-d') !== $d) {
        return false;
    }
    return $dt <= new DateTime('today') && $dt >= new DateTime('-100 years');
}

const CONSENT_AGE = 14;

// ---------- YouTube ----------

/** Iš bet kokios YouTube nuorodos ištraukia video ID (arba null) */
function youtube_id(string $url): ?string
{
    $url = trim($url);
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) {
        return $url;
    }
    if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
        return $m[1];
    }
    return null;
}

function youtube_embed(string $id): string
{
    return '<div class="video"><iframe src="https://www.youtube-nocookie.com/embed/' . e($id)
        . '" title="YouTube video" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen></iframe></div>';
}

/** Paprastas tekstas -> HTML pastraipos su nuorodomis */
function text_to_html(string $text): string
{
    $html = '';
    foreach (preg_split("/\R{2,}/u", trim($text)) as $para) {
        $p = e($para);
        $p = preg_replace('~(https?://[^\s<]+)~', '<a href="$1" rel="noopener" target="_blank">$1</a>', $p);
        $html .= '<p>' . nl2br($p, false) . '</p>';
    }
    return $html;
}

const EVENT_TYPES = [
    'exam'        => 'Egzaminas',
    'competition' => 'Varžybos',
    'seminar'     => 'Seminaras',
    'camp'        => 'Stovykla',
    'other'       => 'Kita',
];

// Renginių filtras: adresas renginiai.php?tipas=egzaminai ir mygtuko tekstas
const EVENT_TYPE_FILTERS = [
    'egzaminai' => ['exam', 'Egzaminai'],
    'varzybos'  => ['competition', 'Varžybos'],
    'seminarai' => ['seminar', 'Seminarai'],
    'stovyklos' => ['camp', 'Stovyklos'],
    'kita'      => ['other', 'Kita'],
];

const GROUP_CATEGORIES = [
    'vaikai'   => 'Vaikai',
    'jaunimas' => 'Jaunimas',
    'suauge'   => 'Suaugusieji',
];

/** Fiksuotas reakcijų rinkinys */
// Numatytieji reakcijų emoji (jei DB dar nėra nustatymo - žr. db/011)
const REACTIONS = ['👍', '❤️', '🔥', '👏', '🥋'];

// Iš ko adminas renkasi reakcijų emoji (Treneriams -> Naujienos)
const REACTION_CHOICES = [
    'Sportas ir karate' => ['🥋', '🥇', '🥈', '🥉', '🏅', '🏆', '🎖️', '🎯', '💪', '👊', '🤜', '🤛', '🦵', '🤸', '🧘', '⛩️', '🎌', '🇯🇵', '🇱🇹', '🐉', '🐯', '🌸'],
    'Rankos'            => ['👍', '👏', '🙌', '🙏', '🤝', '👌', '✌️', '✊', '🫡', '👋', '🤙', '🫶'],
    'Širdys'            => ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '💖', '💯'],
    'Šventė'            => ['🎉', '🥳', '🎊', '✨', '⭐', '🌟', '🔥', '⚡', '💥', '🚀', '🎂', '🎁'],
    'Veidai'            => ['😀', '😄', '😁', '😂', '🤣', '😊', '😍', '🥰', '😎', '🤩', '🥹', '😮', '😲', '🤯', '🤔', '😅', '😢', '😭', '👀'],
];
