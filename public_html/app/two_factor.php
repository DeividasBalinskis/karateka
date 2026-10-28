<?php
// Prisijungimas su kodu el. paštu (antras žingsnis po slaptažodžio). Įjungia pats žmogus paskyros nustatymuose -
// patvirtinęs kodą, todėl įjungti galima tik tada, kai laiškai tikrai ateina. Patikimame įrenginyje kodo neprašoma 30 d.
// Jei laiškai nustotų eiti: phpMyAdmin -> UPDATE accounts SET two_factor = 0 WHERE email = '...';

const LOGIN_CODE_MINUTES = 10;
const LOGIN_CODE_ATTEMPTS = 5;
const TRUSTED_DEVICE_DAYS = 30;

function two_factor_on(array $a): bool
{
    return !empty($a['two_factor']);
}

/** j***@gmail.com - kur išsiųstas kodas */
function masked_email(string $email): string
{
    [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');
    return mb_substr($user, 0, 1) . '***@' . $domain;
}

/**
 * Naujas kodas el. paštu. Ne dažniau kaip kas minutę ir 5 kartus per 15 min.
 * Grąžina null (išsiųsta) arba klaidos tekstą. Kompiuteryje laiškų nėra - kodas įsimenamas sesijoje ir rodomas puslapyje.
 * $resend = false (prisijungiant iš naujo): jei kodas ką tik išsiųstas ir dar galioja - naujas nesiunčiamas, tinka ankstesnis.
 */
function login_code_send(array $a, string $purpose, bool $resend = false): ?string
{
    $aid = (int) $a['id'];
    $recent = q_one('SELECT COUNT(*) c, MAX(created_at) last, SUM(used_at IS NULL AND expires_at > NOW() AND attempts < ?) valid
                       FROM login_codes WHERE account_id = ? AND purpose = ? AND created_at > NOW() - INTERVAL 15 MINUTE', [LOGIN_CODE_ATTEMPTS, $aid, $purpose]);
    $justSent = $recent['last'] && strtotime($recent['last']) > time() - 60;
    if ($justSent && !$resend && (int) $recent['valid'] > 0) {
        return null;
    }
    if ((int) $recent['c'] >= 5) {
        return 'Per daug kodų per trumpą laiką. Pabandykite po 15 minučių.';
    }
    if ($justSent) {
        return 'Kodą ką tik išsiuntėme - patikrinkite el. paštą (ir „Spam“). Naują galima prašyti po minutės.';
    }
    q('UPDATE login_codes SET used_at = NOW() WHERE account_id = ? AND purpose = ? AND used_at IS NULL', [$aid, $purpose]);
    $code = sprintf('%06d', random_int(0, 999999));
    q('INSERT INTO login_codes (account_id, purpose, code_hash, expires_at) VALUES (?, ?, ?, ?)',
        [$aid, $purpose, login_code_hash($aid, $code), date('Y-m-d H:i:s', time() + LOGIN_CODE_MINUTES * 60)]);
    if (config('env') === 'local') {
        $_SESSION['dev_login_code'] = $code;
    }
    $what = $purpose === 'enable' ? 'prisijungimo su kodu įjungimo' : 'prisijungimo';
    $sent = send_mail($a['email'], "Kodas: $code — Karateka",
        "Sveiki, {$a['first_name']},\n\njūsų karateka.lt $what kodas:\n\n    $code\n\nKodas galioja " . LOGIN_CODE_MINUTES . " minučių.\n"
        . "Jei prisijungti bandote ne jūs - nedelsdami pakeiskite slaptažodį.");
    if (!$sent && config('env') !== 'local') {
        return 'Nepavyko išsiųsti kodo. Pabandykite po minutės arba parašykite info@karateka.lt.';
    }
    return null;
}

function login_code_hash(int $accountId, string $code): string
{
    return hash('sha256', $accountId . ':' . $code);
}

/** 'ok' | 'invalid' (liko bandymų) | 'expired' (reikia naujo kodo) */
function login_code_check(int $accountId, string $purpose, string $code): string
{
    $row = q_one('SELECT * FROM login_codes WHERE account_id = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1',
        [$accountId, $purpose]);
    if (!$row || (int) $row['attempts'] >= LOGIN_CODE_ATTEMPTS) {
        return 'expired';
    }
    $code = preg_replace('/\D/', '', $code);
    if (!hash_equals($row['code_hash'], login_code_hash($accountId, $code))) {
        q('UPDATE login_codes SET attempts = attempts + 1 WHERE id = ?', [(int) $row['id']]);
        return (int) $row['attempts'] + 1 >= LOGIN_CODE_ATTEMPTS ? 'expired' : 'invalid';
    }
    q('UPDATE login_codes SET used_at = NOW() WHERE id = ?', [(int) $row['id']]);
    unset($_SESSION['dev_login_code']);
    return 'ok';
}

/** Kiek bandymų liko paskutiniam kodui */
function login_code_attempts_left(int $accountId, string $purpose): int
{
    $used = q_value('SELECT attempts FROM login_codes WHERE account_id = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1',
        [$accountId, $purpose]);
    return $used === false ? 0 : max(0, LOGIN_CODE_ATTEMPTS - (int) $used);
}

// ---------- Patikimi įrenginiai ----------

function trusted_cookie(int $accountId): string
{
    return 'karateka_dev' . $accountId;
}

function cookie_options(int $expires): array
{
    return ['expires' => $expires, 'path' => (config('base_path') ?: '') . '/', 'secure' => (bool) config('force_https'), 'httponly' => true, 'samesite' => 'Lax'];
}

function device_trusted(int $accountId): bool
{
    $token = (string) ($_COOKIE[trusted_cookie($accountId)] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return false;
    }
    $id = q_value('SELECT id FROM trusted_devices WHERE account_id = ? AND token_hash = ? AND expires_at > NOW()', [$accountId, hash('sha256', $token)]);
    if ($id) {
        q('UPDATE trusted_devices SET last_used_at = NOW() WHERE id = ?', [(int) $id]);
    }
    return (bool) $id;
}

function trust_device(int $accountId): void
{
    $token = bin2hex(random_bytes(32));
    q('INSERT INTO trusted_devices (account_id, token_hash, label, expires_at, last_used_at) VALUES (?, ?, ?, ?, NOW())',
        [$accountId, hash('sha256', $token), device_label(), date('Y-m-d H:i:s', time() + TRUSTED_DEVICE_DAYS * 86400)]);
    setcookie(trusted_cookie($accountId), $token, cookie_options(time() + TRUSTED_DEVICE_DAYS * 86400));
}

function forget_devices(int $accountId): void
{
    q('DELETE FROM trusted_devices WHERE account_id = ?', [$accountId]);
    setcookie(trusted_cookie($accountId), '', cookie_options(time() - 3600));
}

/** Patikimi įrenginiai nustatymuose */
function trusted_devices(int $accountId): array
{
    return q_all('SELECT * FROM trusted_devices WHERE account_id = ? AND expires_at > NOW() ORDER BY COALESCE(last_used_at, created_at) DESC', [$accountId]);
}

/** „Chrome · Windows“ - kad būtų aišku, kuris įrenginys */
function device_label(): string
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $browser = preg_match('/Edg\//', $ua) ? 'Edge' : (preg_match('/OPR\/|Opera/', $ua) ? 'Opera' : (preg_match('/Firefox\//', $ua) ? 'Firefox'
        : (preg_match('/Chrome\//', $ua) ? 'Chrome' : (preg_match('/Safari\//', $ua) ? 'Safari' : 'Naršyklė'))));
    $os = preg_match('/iPhone|iPad/', $ua) ? 'iPhone / iPad' : (preg_match('/Android/', $ua) ? 'Android' : (preg_match('/Windows/', $ua) ? 'Windows'
        : (preg_match('/Mac OS X|Macintosh/', $ua) ? 'Mac' : (preg_match('/Linux/', $ua) ? 'Linux' : ''))));
    return $browser . ($os !== '' ? ' · ' . $os : '');
}
