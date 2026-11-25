<?php
// Prisijungimas iš partnerio sistemos (karateka.lt) be atskiro slaptažodžio.
// Nuoroda pasirašyta partnerio API raktu (HMAC-SHA256), galioja 2 min. ir tik vieną kartą.
// Naujas vartotojas sukuriamas automatiškai: partnerio adminas - organizatorius, kiti - sekretoriai.
require __DIR__ . '/app/bootstrap.php';
start_session();

function sso_fail(string $msg): never
{
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="lt"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Prisijungimas</title>'
        . '<body style="font-family:system-ui,sans-serif;max-width:560px;margin:60px auto;padding:0 20px;line-height:1.5">'
        . '<h1>Nepavyko prisijungti</h1><p>' . htmlspecialchars($msg) . '</p><p><a href="./#/prisijungti">Prisijungti el. paštu ir slaptažodžiu →</a></p></body></html>';
    exit;
}

$p = (string) ($_GET['p'] ?? '');
$s = (string) ($_GET['s'] ?? '');
if ($p === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $p) || !preg_match('/^[a-f0-9]{64}$/', $s)) {
    sso_fail('Netinkama nuoroda.');
}
$source = null;
foreach ((array) config('api_keys', []) as $name => $secret) {
    if (is_string($secret) && strlen($secret) >= 16 && hash_equals(hash_hmac('sha256', $p, $secret), $s)) {
        $source = (string) $name;
    }
}
if ($source === null) {
    sso_fail('Netinkama nuoroda (nesutampa raktas).');
}
$data = json_decode((string) base64_decode(strtr($p, '-_', '+/')), true);
if (!is_array($data) || abs(time() - (int) ($data['ts'] ?? 0)) > 120) {
    sso_fail('Nuoroda pasenusi. Grįžkite į ' . $source . ' ir paspauskite mygtuką dar kartą.');
}
$nonce = (string) ($data['nonce'] ?? '');
if (!preg_match('/^[a-f0-9]{16,64}$/', $nonce)) {
    sso_fail('Netinkama nuoroda.');
}
try {
    q('INSERT INTO sso_nonces (nonce, source) VALUES (?, ?)', [$nonce, $source]);
} catch (PDOException $e) {
    sso_fail('Ši nuoroda jau panaudota. Grįžkite ir paspauskite mygtuką dar kartą.');
}
if (random_int(1, 20) === 1) {
    q('DELETE FROM sso_nonces WHERE used_at < (NOW() - INTERVAL 1 DAY)');
}

$email = mb_strtolower(trim((string) ($data['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sso_fail('Netinkamas el. pašto adresas.');
}
$u = q_one('SELECT * FROM users WHERE email = ?', [$email]);
if (!$u) {
    $id = insert('users', [
        'email'         => $email,
        'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),   // prisijungia tik per partnerį (arba atkūrus slaptažodį)
        'name'          => str_or_null($data['name'] ?? '', 120) ?? $email,
        'role'          => ($data['role'] ?? '') === 'admin' ? 'admin' : 'secretary',
    ]);
    $u = q_one('SELECT * FROM users WHERE id = ?', [$id]);
}
session_regenerate_id(true);
$_SESSION['user_id'] = (int) $u['id'];
q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$u['id']]);

$to = (string) ($data['to'] ?? '/admin');
if (!preg_match('#^/(admin(/t/\d+)?|t/\d+)$#', $to)) {
    $to = '/admin';
}
header('Location: ' . (config('base_path') ?: '') . '/#' . $to, true, 302);
exit;
