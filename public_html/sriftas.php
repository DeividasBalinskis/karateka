<?php
// v1.95: šriftų bandymas (app/themes.php FONTS, „Aa“ - tik administratoriams). Visi stiliai, kuriuose nurodytas
// „Space Grotesk“ (antraštės) ar „Inter“ (tekstas), perrašomi pasirinktu šriftu - tie patys selektoriai su !important.
// Monospace (JetBrains Mono) lieka. Failas statinis (be paskyros), tik CSS.
require __DIR__ . '/app/bootstrap.php';
header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=86400');
$f = (string) get('f');
if (!isset(FONTS[$f]) || $f === 'default') {
    exit('/* - */');
}
[, $head, $body] = FONTS[$f];
$hv = "'" . $head . "', 'Inter', sans-serif";
$bv = "'" . $body . "', 'Inter', sans-serif";
$heads = $bodies = [];
foreach (glob(PUBLIC_DIR . '/assets/*.css') as $file) {
    $css = (string) preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents($file));
    if (!preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER)) {
        continue;
    }
    foreach ($m as [, $sel, $decl]) {
        $sel = trim($sel);
        if ($sel === '' || $sel[0] === '@' || !preg_match('/font(?:-family)?\s*:[^;]*?(Space Grotesk|\'Inter\'|"Inter"|\bInter\b)/i', $decl, $fm)) {
            continue;
        }
        if (stripos($fm[1], 'Space Grotesk') !== false) {
            $heads[$sel] = true;
        } else {
            $bodies[$sel] = true;
        }
    }
}
echo ":root{--font-body:$bv; --font-head:$hv;}\n";
echo "body, button, input, select, textarea{font-family:$bv !important;}\n";
echo "h1, h2, h3, h4, legend{font-family:$hv !important;}\n";
foreach (array_keys($bodies) as $sel) {
    echo $sel . "{font-family:$bv !important;}\n";
}
foreach (array_keys($heads) as $sel) {
    echo $sel . "{font-family:$hv !important;}\n";
}
