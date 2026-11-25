<?php
// config.example.php - šablonas. Nukopijuokite į public_html/config.php ir įrašykite tikrus duomenis.
// config.php yra .gitignore sąraše ir niekada nekeliamas į Git.

return [
    'env'         => 'local',                     // local | test | live
    'site_name'   => 'Varžybos',
    'site_url'    => 'http://localhost:8090',     // be "/" gale
    'base_path'   => '',                          // jei svetainė ne domeno šaknyje, pvz. '/varzybos'
    'force_https' => false,                       // serveryje - true

    // Pirmam administratoriui sukurti (kai lentelė users tuščia). Sukūrus - pakeiskite.
    'setup_key'   => 'CHANGE_ME_LONG_RANDOM',

    // Sistemos, kurios jungiasi per API: pavadinimas => raktas (bent 16 simbolių).
    // Tas pats raktas įrašomas karateka.lt config.php ('varzybos' => ['key' => ...]).
    'api_keys' => [
        'karateka' => 'vietinis-raktas-pakeiskite-serveryje',
    ],

    'db' => [
        'host' => 'db',          // Docker: db; serveryje dažniausiai localhost
        'name' => 'varzybos',
        'user' => 'varzybos',
        'pass' => 'varzybos_local',
    ],
];
