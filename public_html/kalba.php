<?php
// Kalbos pasirinkimas: ?l=lt|en|ru&b=kelias - slapukas „kalba“ (1 metai), prisijungus - ir paskyroje; atgal į tą patį puslapį
require __DIR__ . '/app/bootstrap.php';

$lang = get('l');
if (isset(LANGS[$lang])) {
    $a = current_account();
    lang_remember($lang, $a ? (int) $a['id'] : null);   // prisijungus - ir paskyroje (C2): kitame įrenginyje ir laiškuose ta pati kalba
}
$back = (string) get('b');
$base = (string) (config('base_path') ?: '');
if ($base !== '' && strpos($back, $base . '/') === 0) {
    $back = substr($back, strlen($base));
}
redirect(preg_match('~^/[\w\-./]*(\?[\w=&%.\-]*)?$~', $back) ? ltrim($back, '/') : 'index.php');
