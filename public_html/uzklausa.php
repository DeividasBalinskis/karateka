<?php
// uzklausa.php - „2 treniruotės nemokamai“ forma (pagrindinis puslapis, assets/home.js) → laiškas klubui (config notify_email).
// Apsauga (planas A5): CSRF, „spąstų“ laukas (honeypot), mažiausias pildymo laikas, riba - 3 per valandą iš to paties IP ir el. pašto,
// patikra serveryje. Lankytojas mato tik „išsiųsta“ arba bendrą klaidą; smulkmenos - serverio žurnale.
// Atsakymas - JSON: {"ok": true} arba {"ok": false, "error": "tekstas lankytojui"}.
require __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const TRIAL_MIN_SECONDS = 3;   // greičiau užpildyta forma - robotas
const TRIAL_PER_HOUR = 3;      // daugiausia užklausų per valandą iš vieno IP ir vieno el. pašto

function trial_reply(int $code, ?string $error = null): void
{
    http_response_code($code);
    echo json_encode($error === null ? ['ok' => true] : ['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    trial_reply(405, t('Netinkama užklausa.'));
}

// CSRF (be HTML klaidos puslapio - home.js laukia JSON)
$sent = $_POST['_csrf'] ?? '';
if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
    trial_reply(400, t('Puslapis per ilgai buvo atidarytas. Atnaujinkite jį ir bandykite dar kartą.'));
}

// Spąstai robotams: paslėptas laukas turi likti tuščias, forma - pildoma bent kelias sekundes.
// Robotui atsakome „išsiųsta“, kad nebandytų iš naujo.
$started = trial_form_started((string) ($_POST['ft'] ?? ''));
if (post('website') !== '' || $started === null || time() - $started < TRIAL_MIN_SECONDS) {
    error_log('uzklausa.php: atmesta kaip robotas (' . (post('website') !== '' ? 'honeypot' : 'per greitai') . ', ip ' . client_ip() . ')');
    trial_reply(200);
}

$name = mb_substr(post('vardas'), 0, 120);
$email = mb_strtolower(mb_substr(post('email'), 0, 190));
$phone = mb_substr(post('telefonas'), 0, 40);
$place = mb_substr(post('lokacija'), 0, 120);
$year = post('gimimo_metai');
$note = mb_substr(post('zinute'), 0, 2000);

$errors = [];
if ($name === '' || $email === '' || $phone === '' || $place === '') {
    $errors[] = t('Užpildykite vardą, el. paštą, telefoną ir lokaciją.');
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = t('Patikrinkite el. pašto adresą.');
}
if ($phone !== '' && !preg_match('/^\+?[0-9 ()\-]{6,20}$/', $phone)) {
    $errors[] = t('Patikrinkite telefono numerį.');
}
if ($year !== '' && (!ctype_digit($year) || (int) $year < 1930 || (int) $year > (int) date('Y'))) {
    $errors[] = t('Patikrinkite gimimo metus.');
}
if ($errors) {
    trial_reply(400, implode(' ', $errors));
}

// Riba: 3 per valandą iš to paties IP ir to paties el. pašto
$ip = client_ip();
$recent = (int) q_value("SELECT COUNT(*) FROM form_submissions WHERE form = 'trial' AND (ip = ? OR email = ?) AND created_at > NOW() - INTERVAL 1 HOUR", [$ip, $email]);
if ($recent >= TRIAL_PER_HOUR) {
    trial_reply(429, t('Jau gavome jūsų užklausą - susisieksime. Jei skubate, parašykite mums el. paštu.'));
}
q("INSERT INTO form_submissions (form, ip, email) VALUES ('trial', ?, ?)", [$ip, $email]);

$body = "Nauja užklausa „2 treniruotės nemokamai“ iš svetainės:\n\n"
    . "Vardas, pavardė: $name\n"
    . "El. paštas: $email\n"
    . "Telefonas: $phone\n"
    . "Lokacija: $place\n"
    . 'Gimimo metai: ' . ($year !== '' ? $year : '-') . "\n"
    . 'Žinutė: ' . ($note !== '' ? $note : '-') . "\n";

$to = (string) config('notify_email', '');
if ($to === '' || !send_mail($to, 'Nauja registracija - ' . $name, $body, [], $email)) {
    error_log('uzklausa.php: laiškas neišsiųstas (notify_email: ' . ($to === '' ? 'nenurodytas' : 'yra') . ')');
    trial_reply(500, t('Nepavyko išsiųsti. Bandykite vėliau arba parašykite mums el. paštu.'));
}
trial_reply(200);
