<?php
// Rankinis valymo paleidimas: docker compose exec web php app/cli/cleanup.php
if (PHP_SAPI !== 'cli') {
    exit;
}
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require dirname(__DIR__) . '/bootstrap.php';
run_cleanup();
echo "Valymas atliktas.\n";
