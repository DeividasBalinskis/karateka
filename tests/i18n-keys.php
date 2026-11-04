<?php
// Vertimų patikra: randa visus t('...') / te('...') / tn() tekstus ir praneša, kurių nėra app/lang/en.php ir ru.php.
// Paleidžiama iš tests/checks.sh. Tekstai be raidžių ar SQL fragmentai praleidžiami.
$dir = $argv[1];
$keys = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
foreach ($it as $f) {
    $p = $f->getPathname();
    if (substr($p, -4) !== '.php' || strpos($p, '/lang/') !== false || strpos($p, '/cli/') !== false || strpos($p, 'config') !== false) continue;
    $tokens = token_get_all(file_get_contents($p));
    $n = count($tokens);
    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        if (is_array($t) && $t[0] === T_STRING && in_array($t[1], ['t', 'te', 'tn'], true)) {
            // next non-ws must be '('
            $j = $i + 1;
            while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
            if ($j >= $n || $tokens[$j] !== '(') continue;
            $args = [];
            $depth = 0;
            for ($k = $j + 1; $k < $n; $k++) {
                $tk = $tokens[$k];
                if ($tk === '(') $depth++;
                if ($tk === ')') { if ($depth === 0) break; $depth--; }
                if ($depth === 0 && is_array($tk) && $tk[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $raw = $tk[1];
                    $v = $raw[0] === "'" ? str_replace(["\\'", '\\\\'], ["'", '\\'], substr($raw, 1, -1)) : stripcslashes(substr($raw, 1, -1));
                    $args[] = $v;
                }
                if ($depth === 0 && $tk === ',' && $t[1] !== 'tn') break;   // only first arg for t/te
            }
            if ($t[1] === 'tn') {
                if (count($args) >= 3) $keys[$args[0] . '|' . $args[1] . '|' . $args[2]] = $p;
            } elseif ($args) {
                $keys[$args[0]] = $p;
            }
        }
    }
}
$missing = [];
foreach (['en', 'ru'] as $l) {
    $dict = require $dir . '/app/lang/' . $l . '.php';
    foreach ($keys as $k => $file) {
        if (!isset($dict[$k]) && preg_match('/\p{L}{3,}/u', $k) && !preg_match('/\b(AND|OR|NOT|IN|EXISTS|IS NULL)\b|_id\b|^[a-z_]+$|^[A-Z][a-z]+$/', $k)) {
            $missing[$l][$k] = $file;
        }
    }
}
foreach ($missing as $l => $list) {
    echo "Vertimų ($l) trūksta: " . count($list) . "\n";
    foreach (array_slice($list, 0, 10, true) as $k => $f) {
        echo "  " . str_replace("\n", ' ', mb_strimwidth($k, 0, 80, '…')) . "  ($f)\n";
    }
}
if (!$missing) {
    echo "Vertimai: visi tekstai išversti\n";
}
