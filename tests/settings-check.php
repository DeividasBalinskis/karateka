<?php
// C2: kiekvienas kode naudojamas nustatymo vardas turi būti registre (public_html/app/settings.php). Paleidžiama iš tests/checks.sh.
$dir = $argv[1] ?? 'public_html';
define('APP_DIR', $dir . '/app');
function config($k, $d = null) { return $d; }
require $dir . '/app/settings.php';
$bad = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
foreach ($it as $f) {
    $p = $f->getPathname();
    if (substr($p, -4) !== '.php' || strpos($p, '/lang/') !== false || strpos($p, 'config') !== false) {
        continue;
    }
    if (preg_match_all("/\\b(?:set_site_setting|site_setting|ltkf_setting|ltkf_json_setting)\\('([a-z0-9_]+)'/", file_get_contents($p), $m)) {
        foreach ($m[1] as $name) {
            if (setting_def($name) === null) {
                $bad[$name] = $p;
            }
        }
    }
}
foreach ($bad as $n => $p) {
    echo "Nustatymo nėra registre (app/settings.php): $n  ($p)\n";
}
exit($bad ? 1 : 0);
