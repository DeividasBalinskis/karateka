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

// Klaida vietoj tuščio puslapio. Dažniausia - neimportuotas DB atnaujinimas (trūksta lentelės ar stulpelio).
set_exception_handler(function (Throwable $ex) {
    error_log((string) $ex);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $live = config('env') === 'live';
    $missing = $ex instanceof PDOException && in_array((string) $ex->getCode(), ['42S02', '42S22'], true);
    echo '<!DOCTYPE html><html lang="lt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Klaida</title></head>'
        . '<body style="font-family:system-ui,sans-serif;max-width:640px;margin:60px auto;padding:0 20px;line-height:1.55;color:#17140F;background:#F7F4EC">';
    if ($missing && !$live) {
        echo '<h1>Duomenų bazė neatnaujinta</h1><p>Svetainės failai naujesni nei duomenų bazė. Importuokite trūkstamus <code>db/0xx</code> failus '
            . '(kuriuos - parašyta <code>docs/pakeitimai.md</code>), o kompiuteryje paleiskite <code>tools/lokali-svetaine.ps1</code>.</p>';
    } else {
        echo '<h1>Įvyko klaida</h1><p>' . ($missing ? 'Svetainė šiuo metu atnaujinama - užsukite po kelių minučių.' : 'Bandykite dar kartą. Jei kartojasi - parašykite info@karateka.lt.') . '</p>';
    }
    if (!$live) {
        echo '<p style="font-family:monospace;font-size:13px;color:#6B6458;word-break:break-word">' . htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    }
    echo '<p><a href="javascript:history.back()">← Atgal</a></p></body></html>';
});

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
require APP_DIR . '/public_site.php';
require APP_DIR . '/belts.php';
require APP_DIR . '/varzybos.php';
require APP_DIR . '/participants.php';
require APP_DIR . '/exams.php';
require APP_DIR . '/sms.php';
require APP_DIR . '/lessons.php';
require APP_DIR . '/pickers.php';
require APP_DIR . '/messages.php';
require APP_DIR . '/home.php';
require APP_DIR . '/notifications.php';
require APP_DIR . '/xp.php';
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
