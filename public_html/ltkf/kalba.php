<?php
// Kalbos pasirinkimas LTKF svetainėje: ?l=lt|en|ru&b=kelias - slapukas „kalba“ (1 metai) ir atgal (LTKF - atskira svetainė, todėl savas failas)
$l = (string) ($_GET['l'] ?? '');
if (in_array($l, ['lt', 'en', 'ru'], true)) {
    setcookie('kalba', $l, ['expires' => time() + 365 * 86400, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
}
$b = (string) ($_GET['b'] ?? '');
header('Location: ' . (preg_match('~^/[\w\-./]*(\?[\w=&%.\-]*)?$~', $b) ? $b : 'index.php'), true, 302);
exit;
