<?php
// uzklausa.php - „Nemokama treniruotė“ formos laiškas klubui (fetch iš index.php, atsakymas JSON).
// Siunčiama bendra send_mail() funkcija (config.php -> 'mail'), apsauga nuo šlamšto - kaip registracijoje.
require __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function uzklausa_atsakymas(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if (!is_post()) {
    uzklausa_atsakymas(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

// Paslėptas laukas (žmogus jo nemato) arba forma pateikta greičiau nei per 3 s - robotas.
// Atsakome „gerai“, kad robotas nebandytų iš naujo, bet laiško nesiunčiame.
$started = (int) post('t');
if (post('website') !== '' || ($started > 0 && time() - $started < 3)) {
    uzklausa_atsakymas(200, ['ok' => true]);
}

// Ne daugiau kaip 5 užklausos per valandą iš vieno IP
$limitFile = sys_get_temp_dir() . '/karateka-uzklausa-' . hash('sha256', client_ip()) . '.json';
$recent = array_filter(json_decode((string) @file_get_contents($limitFile), true) ?: [], function ($ts) { return $ts > time() - 3600; });
if (count($recent) >= 5) {
    uzklausa_atsakymas(429, ['ok' => false, 'error' => 'too_many']);
}

$field = function (string $name, int $max): string {
    $v = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) ($_POST[$name] ?? ''));
    return mb_substr(trim($v), 0, $max);
};
$vardas = $field('vardas', 120);
$email = $field('email', 190);
$telefonas = $field('telefonas', 40);
$lokacija = $field('lokacija', 120);
$gimimoMetai = $field('gimimo_metai', 10);
$zinute = mb_substr(trim((string) ($_POST['zinute'] ?? '')), 0, 2000);

if ($vardas === '' || $email === '' || $telefonas === '' || $lokacija === '') {
    uzklausa_atsakymas(400, ['ok' => false, 'error' => 'missing_fields']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    uzklausa_atsakymas(400, ['ok' => false, 'error' => 'invalid_email']);
}

$body = "Nauja užklausa dėl nemokamos treniruotės (karateka.lt):\n\n"
    . "Vardas, pavardė: $vardas\n"
    . "El. paštas: $email\n"
    . "Telefonas: $telefonas\n"
    . "Vieta: $lokacija\n"
    . 'Gimimo metai: ' . ($gimimoMetai !== '' ? $gimimoMetai : '-') . "\n"
    . 'Žinutė: ' . ($zinute !== '' ? $zinute : '-') . "\n\n"
    . 'Atsakyti galite tiesiog paspaudę „Atsakyti“ – laiškas nueis ' . $email . '.';

$to = config('notify_email') ?: 'info@karateka.lt';
if (!send_mail($to, 'Nemokama treniruotė – ' . $vardas, $body, $email)) {
    uzklausa_atsakymas(500, ['ok' => false, 'error' => 'mail_failed']);
}

$recent[] = time();
@file_put_contents($limitFile, json_encode(array_values($recent)));
uzklausa_atsakymas(200, ['ok' => true]);
