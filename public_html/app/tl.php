<?php
// Taikos ir LTKF vertimas į EN / RU naršyklėje (assets/tl.js): puslapiai rašomi lietuviškai, pasirinkus kitą kalbą
// (LT | EN | RU viršuje, slapukas „kalba“) - įdedamas žodynas app/lang/tl/{en,ru}.json ir vertėjas.

/** Lankytojo pasirinkta kalba (slapukas), net jei puslapis serveryje rašomas lietuviškai */
function tl_lang(): string
{
    $c = (string) ($_COOKIE['kalba'] ?? '');
    return isset(LANGS[$c]) ? $c : 'lt';
}

function tl_script(): string
{
    $l = tl_lang();
    $file = APP_DIR . '/lang/tl/' . $l . '.json';
    if ($l === 'lt' || !is_file($file)) {
        return '';
    }
    return '<script>window.TL=' . str_replace('</', '<\/', (string) file_get_contents($file)) . ';</script><script src="' . e(asset_url('assets/tl.js')) . '" defer></script>';
}
