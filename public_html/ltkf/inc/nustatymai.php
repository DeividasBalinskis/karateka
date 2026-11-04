<?php
// LTKF svetainės nustatymai. Slaptažodžių čia nėra: serveryje raktus laikykite faile
// inc/nustatymai.local.php (jis neįkeliamas į GitHub), pvz.:
//   <?php return ['varzybos' => ['url' => 'https://varzybos.ltkf.lt', 'public_url' => 'https://varzybos.ltkf.lt', 'key' => '...']];

$n = [
    'pavadinimas' => 'Lietuvos tradicinio karatė federacija',
    'trumpas'     => 'LTKF',
    'el_pastas'   => 'info@ltkf.lt',      // [patikslinti]
    'telefonas'   => '[telefonas]',
    'demo'        => true,                // rodo juostą „Juodraštis“ ir žymes „Pavyzdys“

    // Varžybų sistema. Kompiuteryje (Docker) jungiamasi prie lokalios varžybų sistemos, jei ji paleista.
    // Nepaleista - kalendorius ir rezultatai rodo pavyzdinius duomenis, svetainė veikia toliau.
    'varzybos' => [
        'url'        => 'http://host.docker.internal:8090',
        'public_url' => 'http://localhost:8090',
        'key'        => 'vietinis-raktas-pakeiskite-serveryje',
    ],
];

if (is_file(__DIR__ . '/nustatymai.local.php')) {
    $n = array_replace_recursive($n, require __DIR__ . '/nustatymai.local.php');
}
return $n;
