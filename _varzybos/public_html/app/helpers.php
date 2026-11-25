<?php
// Bendros pagalbinės funkcijos

/** config('db.host') - reikšmė iš config.php (taškas = gilesnis lygis) */
function config(string $key, $default = null)
{
    $v = $GLOBALS['config'];
    foreach (explode('.', $key) as $k) {
        if (!is_array($v) || !array_key_exists($k, $v)) {
            return $default;
        }
        $v = $v[$k];
    }
    return $v;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/** Klaida, kurią API grąžina vartotojui (žinutė rodoma ekrane) */
class ApiError extends RuntimeException
{
    public int $status;

    public function __construct(string $message, int $status = 400)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

function fail(string $message, int $status = 400): never
{
    throw new ApiError($message, $status);
}

/** JSON užklausos kūnas */
function body(): array
{
    static $b = null;
    if ($b === null) {
        $raw = file_get_contents('php://input');
        $b = $raw ? json_decode($raw, true) : [];
        if (!is_array($b)) {
            $b = [];
        }
    }
    return $b;
}

function input(string $key, $default = null)
{
    return body()[$key] ?? $default;
}

function str_or_null($v, int $max = 190): ?string
{
    $v = trim((string) ($v ?? ''));
    return $v === '' ? null : mb_substr($v, 0, $max);
}

function int_or_null($v): ?int
{
    return ($v === null || $v === '' || !is_numeric($v)) ? null : (int) $v;
}

function dec_or_null($v): ?float
{
    if ($v === null || $v === '') {
        return null;
    }
    $v = str_replace(',', '.', (string) $v);
    return is_numeric($v) ? round((float) $v, 1) : null;
}

function valid_date($d): bool
{
    if (!is_string($d) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        return false;
    }
    [$y, $m, $day] = array_map('intval', explode('-', $d));
    return checkdate($m, $day, $y);
}

function valid_time($t): bool
{
    return is_string($t) && (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $t);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/** Amžius varžybose: 'year' - pagal gimimo metus (kaip ITKF), 'date' - pilni metai varžybų dieną */
function age_on(string $birthDate, string $onDate, string $basis = 'year'): int
{
    $b = new DateTime($birthDate);
    $d = new DateTime($onDate);
    if ($basis === 'year') {
        return (int) $d->format('Y') - (int) $b->format('Y');
    }
    return (int) $b->diff($d)->y;
}

/** Atsitiktinai sumaišo masyvą (galima perduoti seed testams) */
function shuffled(array $a): array
{
    shuffle($a);
    return $a;
}

function person_name(array $a): string
{
    return trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
}
