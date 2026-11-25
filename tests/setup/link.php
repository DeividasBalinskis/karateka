<?php
// Testams (C3): slaptos nuorodos raktas iš app/links.php - duomenų bazėje saugomas tik pirštų atspaudas ir užrakinta kopija,
// todėl testas raktą gauna per šį skriptą (tik testų svetainėje, config env = local).
//   php tests/setup/link.php get <rūšis> [savininko id]          → dabartinės nuorodos raktas (tuščia - nėra)
//   php tests/setup/link.php new <rūšis> [savininko id] [vardas] → nauja nuoroda (ankstesnė - „pakeista“), jos raktas
if (PHP_SAPI !== 'cli') {
    exit;
}
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require dirname(__DIR__, 2) . '/public_html/app/bootstrap.php';
if (config('env') !== 'local') {
    fwrite(STDERR, "Tik testų svetainei (config env = local).\n");
    exit(1);
}
$cmd = (string) ($argv[1] ?? 'get');
$kind = (string) ($argv[2] ?? '');
$owner = isset($argv[3]) && $argv[3] !== '' ? (int) $argv[3] : null;
if (!isset(LINK_KINDS[$kind]) || !links_ready()) {
    fwrite(STDERR, "Nežinoma rūšis arba nėra db/062.\n");
    exit(1);
}
$row = $cmd === 'new' ? link_create($kind, $owner, (string) ($argv[4] ?? '')) : link_current($kind, $owner);
echo PHP_EOL . (string) link_token($row);   // paskutinė eilutė - raktas (jei kas nors būtų išvesta anksčiau)
