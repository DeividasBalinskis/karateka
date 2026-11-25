<?php
// Prisijungimas (organizatoriai / sekretoriai), CSRF ir partnerių API raktai

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 7));
    session_name('varzybos_sid');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 7,
        'path'     => (config('base_path') ?: '') . '/',
        'secure'   => (bool) config('force_https'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $id = (int) ($_SESSION['user_id'] ?? 0);
        $user = $id ? q_one('SELECT id, email, name, role FROM users WHERE id = ?', [$id]) : null;
    }
    return $user;
}

function require_user(?string $role = null): array
{
    $u = current_user();
    if (!$u) {
        fail('Reikia prisijungti.', 401);
    }
    if ($role === 'admin' && $u['role'] !== 'admin') {
        fail('Tam reikia administratoriaus teisių.', 403);
    }
    return $u;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Keičiančios užklausos turi siųsti X-CSRF-Token antraštę */
function check_csrf(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$sent || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        fail('Puslapis paseno. Perkraukite jį (F5) ir bandykite dar kartą.', 419);
    }
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function login(string $email, string $password): array
{
    $email = mb_strtolower(trim($email));
    $ip = client_ip();
    $fails = (int) q_value('SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND attempted_at > (NOW() - INTERVAL 15 MINUTE) AND (email = ? OR ip = ?)', [$email, $ip]);
    if ($fails >= 8) {
        fail('Per daug bandymų. Palaukite 15 minučių.', 429);
    }
    $u = q_one('SELECT * FROM users WHERE email = ?', [$email]);
    $ok = $u && password_verify($password, $u['password_hash']);
    q('INSERT INTO login_attempts (email, ip, success) VALUES (?, ?, ?)', [$email, $ip, $ok ? 1 : 0]);
    if (!$ok) {
        fail('Neteisingas el. paštas arba slaptažodis.', 401);
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $u['id'];
    q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$u['id']]);
    if (random_int(1, 20) === 1) {
        q('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 30 DAY)');
    }
    return ['id' => (int) $u['id'], 'email' => $u['email'], 'name' => $u['name'], 'role' => $u['role']];
}

function logout(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

function create_user(string $email, string $password, string $name, string $role): int
{
    $email = mb_strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fail('Neteisingas el. pašto adresas.');
    }
    if (mb_strlen($password) < 8) {
        fail('Slaptažodis turi būti bent 8 simbolių.');
    }
    if (q_value('SELECT 1 FROM users WHERE email = ?', [$email])) {
        fail('Toks el. paštas jau naudojamas.');
    }
    return insert('users', [
        'email'         => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'name'          => str_or_null($name, 120) ?? $email,
        'role'          => $role === 'admin' ? 'admin' : 'secretary',
    ]);
}

/**
 * Partnerio (pvz. karateka.lt) API raktas iš X-Api-Key antraštės.
 * Grąžina šaltinio pavadinimą iš config api_keys, pvz. 'karateka'.
 */
function api_client(): string
{
    $key = $_SERVER['HTTP_X_API_KEY'] ?? '';
    foreach ((array) config('api_keys', []) as $name => $secret) {
        if ($key !== '' && is_string($secret) && strlen($secret) >= 16 && hash_equals($secret, $key)) {
            return (string) $name;
        }
    }
    fail('Neteisingas API raktas.', 401);
}
