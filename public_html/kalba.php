<?php
// Kalbos pasirinkimas: ?l=lt|en|ru&b=kelias - slapukas „kalba“ (1 metai) ir atgal į tą patį puslapį
require __DIR__ . '/app/bootstrap.php';

$lang = get('l');
if (isset(LANGS[$lang])) {
    setcookie('kalba', $lang, [
        'expires' => time() + 365 * 86400,
        'path' => (config('base_path') ?: '') . '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
$back = (string) get('b');
$base = (string) (config('base_path') ?: '');
if ($base !== '' && strpos($back, $base . '/') === 0) {
    $back = substr($back, strlen($base));
}
redirect(preg_match('~^/[\w\-./]*(\?[\w=&%.\-]*)?$~', $back) ? ltrim($back, '/') : 'index.php');
