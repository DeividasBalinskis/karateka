<?php
// Įtraukiama kiekvieno narių sistemos puslapio pradžioje:
//   require __DIR__ . '/app/bootstrap.php';
declare(strict_types=1);

define('APP_DIR', __DIR__);
define('PUBLIC_DIR', dirname(__DIR__));

$GLOBALS['config'] = require PUBLIC_DIR . '/config.php';

date_default_timezone_set('Europe/Vilnius');
mb_internal_encoding('UTF-8');

require APP_DIR . '/helpers.php';

if (config('env') !== 'local') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

require APP_DIR . '/db.php';
require APP_DIR . '/csrf.php';
require APP_DIR . '/mail.php';
require APP_DIR . '/tokens.php';
require APP_DIR . '/auth.php';
require APP_DIR . '/data.php';
require APP_DIR . '/points.php';
require APP_DIR . '/cleanup.php';
require APP_DIR . '/layout.php';

// HTTPS
if (config('force_https') && !is_https()) {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
    exit;
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (config('force_https')) {
    header('Strict-Transport-Security: max-age=31536000');
}

// Sesija
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 14));
session_name('karateka_sid');
session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 14,
    'path'     => (config('base_path') ?: '') . '/',
    'secure'   => (bool) config('force_https'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

maybe_run_cleanup();
