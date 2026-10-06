<?php
// send.php — priima registracijos formos duomenis ir siunčia juos el. paštu per SMTP.
// Įkelkite šį failą TAME PAČIAME aplanke kaip ir index.php.

header('Content-Type: application/json; charset=utf-8');

// ============================================================
//  NUSTATYMAI — SMTP prisijungimai laikomi config.php (jo nėra Git'e)
// ============================================================
$config    = require __DIR__ . '/config.php';
$SMTP_HOST = $config['smtp']['host'];
$SMTP_PORT = $config['smtp']['port'];
$SMTP_USER = $config['smtp']['user'];
$SMTP_PASS = $config['smtp']['pass'];

$to             = 'info@karateka.lt';
$from_name      = 'Karateka svetainė';
$subject_prefix = 'Nauja registracija — Karateka';
// ============================================================

function clean($v) {
    return htmlspecialchars(trim($v ?? ''), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$vardas       = clean($_POST['vardas'] ?? '');
$email        = clean($_POST['email'] ?? '');
$telefonas    = clean($_POST['telefonas'] ?? '');
$lokacija     = clean($_POST['lokacija'] ?? '');
$gimimo_metai = clean($_POST['gimimo_metai'] ?? '');
$zinute       = clean($_POST['zinute'] ?? '');

// Būtini laukai
if ($vardas === '' || $email === '' || $telefonas === '' || $lokacija === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_fields']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_email']);
    exit;
}

$body = "Nauja registracija iš karateka.lt svetainės:\r\n\r\n"
    . "Vardas, pavardė: $vardas\r\n"
    . "El. paštas: $email\r\n"
    . "Telefonas: $telefonas\r\n"
    . "Lokacija: $lokacija\r\n"
    . "Gimimo metai: " . ($gimimo_metai !== '' ? $gimimo_metai : '-') . "\r\n"
    . "Žinutė: " . ($zinute !== '' ? $zinute : '-') . "\r\n";

$subject = "=?UTF-8?B?" . base64_encode("$subject_prefix — $vardas") . "?=";
$from_name_enc = "=?UTF-8?B?" . base64_encode($from_name) . "?=";

// ============================================================
//  SMTP siuntimas
// ============================================================

/**
 * Nuskaito serverio atsakymą ir patikrina, ar kodas toks, kokio tikimės.
 */
function smtp_read($socket, $expected) {
    $response = '';
    while ($line = fgets($socket, 515)) {
        $response .= $line;
        // Paskutinė eilutė neturi brūkšnio po kodo, pvz. "250 OK"
        if (isset($line[3]) && $line[3] === ' ') break;
    }
    $code = (int) substr($response, 0, 3);
    return in_array($code, (array) $expected, true) ? $response : false;
}

/**
 * Nusiunčia komandą ir patikrina atsakymą.
 */
function smtp_cmd($socket, $cmd, $expected) {
    fwrite($socket, $cmd . "\r\n");
    return smtp_read($socket, $expected);
}

$errorDetail = '';
$sent = false;

// Portas 465 = SSL nuo pat pradžių
$socket = @stream_socket_client(
    "ssl://{$SMTP_HOST}:{$SMTP_PORT}",
    $errno,
    $errstr,
    20,
    STREAM_CLIENT_CONNECT
);

if (!$socket) {
    $errorDetail = "connect: $errstr ($errno)";
} else {
    stream_set_timeout($socket, 20);

    do {
        if (!smtp_read($socket, 220))                                    { $errorDetail = 'greeting';  break; }
        if (!smtp_cmd($socket, 'EHLO karateka.lt', 250))                 { $errorDetail = 'ehlo';      break; }
        if (!smtp_cmd($socket, 'AUTH LOGIN', 334))                       { $errorDetail = 'auth';      break; }
        if (!smtp_cmd($socket, base64_encode($SMTP_USER), 334))          { $errorDetail = 'user';      break; }
        if (!smtp_cmd($socket, base64_encode($SMTP_PASS), 235))          { $errorDetail = 'password';  break; }
        if (!smtp_cmd($socket, "MAIL FROM:<{$SMTP_USER}>", 250))         { $errorDetail = 'mailfrom';  break; }
        if (!smtp_cmd($socket, "RCPT TO:<{$to}>", [250, 251]))           { $errorDetail = 'rcptto';    break; }
        if (!smtp_cmd($socket, 'DATA', 354))                             { $errorDetail = 'data';      break; }

        $message  = "From: {$from_name_enc} <{$SMTP_USER}>\r\n";
        $message .= "To: <{$to}>\r\n";
        $message .= "Reply-To: {$email}\r\n";
        $message .= "Subject: {$subject}\r\n";
        $message .= "Date: " . date('r') . "\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n";
        $message .= "\r\n";
        // Taškas eilutės pradžioje SMTP protokole reiškia pabaigą — apsaugome
        $message .= preg_replace('/^\./m', '..', $body);
        $message .= "\r\n.";

        if (!smtp_cmd($socket, $message, 250))                           { $errorDetail = 'send';      break; }

        $sent = true;
    } while (false);

    @smtp_cmd($socket, 'QUIT', [221, 250]);
    @fclose($socket);
}

if ($sent) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(500);
    // $errorDetail padeda suprasti, kurioje vietoje užstrigo (pvz. "password" = blogas slaptažodis)
    echo json_encode(['ok' => false, 'error' => 'mail_failed', 'stage' => $errorDetail]);
}
