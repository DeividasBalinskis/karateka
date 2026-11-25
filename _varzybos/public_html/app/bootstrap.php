<?php
// Įtraukiama api.php ir index.php pradžioje
declare(strict_types=1);

define('APP_DIR', __DIR__);
define('PUBLIC_DIR', dirname(__DIR__));

if (!is_file(PUBLIC_DIR . '/config.php')) {
    http_response_code(500);
    exit('Nėra public_html/config.php (žr. README.md).');
}
$GLOBALS['config'] = require PUBLIC_DIR . '/config.php';

date_default_timezone_set('Europe/Vilnius');
mb_internal_encoding('UTF-8');

require APP_DIR . '/helpers.php';

if (config('env') !== 'local') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
require APP_DIR . '/db.php';
require APP_DIR . '/rules.php';
require APP_DIR . '/auth.php';
require APP_DIR . '/tournaments.php';
require APP_DIR . '/categories.php';
require APP_DIR . '/athletes.php';
require APP_DIR . '/draw.php';
require APP_DIR . '/matches.php';
require APP_DIR . '/scores.php';
require APP_DIR . '/results.php';
require APP_DIR . '/schedule.php';

if (config('force_https') && !is_https()) {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
    exit;
}

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (config('force_https')) {
    header('Strict-Transport-Security: max-age=31536000');
}
