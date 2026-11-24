<?php
// Trumpa pateisinimų nuoroda (v1.93): karateka.lt/p/?kodas → pateisinimas.php
$code = strtolower((string) ($_SERVER['QUERY_STRING'] ?? ''));
$code = preg_match('/^([a-z0-9]{4,32})(?:[&=].*)?$/', $code, $m) ? $m[1] : '';
$_GET['k'] = $code;
require dirname(__DIR__) . '/pateisinimas.php';
