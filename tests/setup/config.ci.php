<?php
// config.php testams (GitHub Actions): nukopijuojamas į public_html/config.php
return [
    'env'         => 'local',
    'site_url'    => 'http://127.0.0.1:8088',
    'base_path'   => '',
    'force_https' => false,
    'notify_email'=> 'info@karateka.lt',
    'setup_key'   => 'ci-only',
    'retention'   => ['unfinished_days' => 30],
    'ranking'     => ['scope' => 'category'],
    'smtp'        => ['host' => '127.0.0.1', 'port' => 1025, 'user' => '', 'pass' => ''],
    'mail'        => ['host' => '127.0.0.1', 'port' => 1025, 'secure' => '', 'user' => '', 'pass' => '', 'from' => 'info@karateka.lt', 'from_name' => 'Karateka'],
    'sms'         => ['provider' => 'log', 'from' => 'Karateka', 'price_eur' => 0.05, 'twilio_sid' => '', 'twilio_token' => ''],
    'db'          => ['host' => '127.0.0.1', 'name' => 'karateka', 'user' => 'root', 'pass' => 'root'],
];
