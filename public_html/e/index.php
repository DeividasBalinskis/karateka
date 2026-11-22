<?php
// Trumpa egzamino registracijos nuoroda (Deividas, v1.92): karateka.lt/e/?k7m2xq → egzamino-registracija.php
// Kodas - po „?“ (be „k=“), tinka ir senieji ilgi raktai. Forma siunčiama į tą patį trumpą adresą.
$code = strtolower((string) ($_SERVER['QUERY_STRING'] ?? ''));
$code = preg_match('/^([a-z0-9]{4,32})(?:[&=].*)?$/', $code, $m) ? $m[1] : '';
$_GET['k'] = $code;
require dirname(__DIR__) . '/egzamino-registracija.php';
