<?php
// config.example.php - šablonas. Nukopijuokite į config.php ir įrašykite tikrus duomenis.
// config.php yra .gitignore sąraše ir niekada nekeliamas į Git.

return [
    'env'         => 'test',                       // local | test | live
    'site_url'    => 'https://test.karateka.lt',   // be "/" gale
    'base_path'   => '',                           // jei svetainė ne domeno šaknyje, pvz. '/test'
    'force_https' => true,
    'notify_email'=> 'info@karateka.lt',
    'setup_key'   => 'CHANGE_ME_LONG_RANDOM',      // ištrinkite/pakeiskite sukūrę pirmą administratorių
    'retention'   => ['unfinished_days' => 30],    // nebaigtos registracijos ištrinamos po N dienų
    'ranking'     => ['scope' => 'category'],     // reitingas: category (vaikai/jaunimas/suaugę) | group | club

    // Visiems svetainės laiškams, ir nemokamos treniruotės formai (nuo v1.7 atskiro 'smtp' bloko nebereikia)
    'mail' => [
        'host'      => 'smtp.example.com',
        'port'      => 465,
        'secure'    => 'ssl',
        'user'      => 'info@karateka.lt',
        'pass'      => 'CHANGE_ME',
        'from'      => 'info@karateka.lt',
        'from_name' => 'Karateka',
    ],
    // SMS (tik adminui: Treneriams -> SMS). provider: log - bandomasis režimas (niekas nesiunčiama), twilio - tikras siuntimas
    'sms' => [
        'provider'     => 'log',
        'from'         => 'Karateka',   // siuntėjo vardas (iki 11 raidžių) arba numeris
        'price_eur'    => 0.05,         // vienos SMS dalies kaina - kainai skaičiuoti
        'twilio_sid'   => '',
        'twilio_token' => '',
    ],
    // Varžybų sistema (varzybos.karateka.lt ar ltkf.lt). Be šio bloko serveryje varžybų mygtukai nerodomi.
    // 'key' - tas pats raktas kaip varžybų sistemos config.php -> api_keys -> karateka.
    // 'varzybos' => [
    //     'url'       => 'https://varzybos.karateka.lt',
    //     'key'       => 'ILGAS-ATSITIKTINIS-RAKTAS',
    //     'club_name' => 'Karate Ateitis',
    //     'club_city' => 'Vilnius',
    // ],
    'db' => [
        'host' => 'localhost',
        'name' => 'database_name',
        'user' => 'database_user',
        'pass' => 'CHANGE_ME',
    ],
];
