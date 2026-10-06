<?php
// config.example.php — šablonas. Nukopijuokite į config.php ir įrašykite tikrus duomenis.
// config.php yra .gitignore sąraše ir niekada nekeliamas į Git.

return [
    'env'         => 'test',                       // local | test | live
    'site_url'    => 'https://test.karateka.lt',   // be "/" gale
    'base_path'   => '',                           // jei svetainė ne domeno šaknyje, pvz. '/test'
    'force_https' => true,
    'notify_email'=> 'info@karateka.lt',
    'setup_key'   => 'CHANGE_ME_LONG_RANDOM',      // ištrinkite/pakeiskite sukūrę pirmą administratorių

    // Registracijos formai (send.php)
    'smtp' => [
        'host' => 'smtp.example.com',
        'port' => 465,
        'user' => 'info@karateka.lt',
        'pass' => 'CHANGE_ME',
    ],
    // Narių sistemos laiškams
    'mail' => [
        'host'      => 'smtp.example.com',
        'port'      => 465,
        'secure'    => 'ssl',
        'user'      => 'info@karateka.lt',
        'pass'      => 'CHANGE_ME',
        'from'      => 'info@karateka.lt',
        'from_name' => 'Karateka',
    ],
    'db' => [
        'host' => 'localhost',
        'name' => 'database_name',
        'user' => 'database_user',
        'pass' => 'CHANGE_ME',
    ],
];
