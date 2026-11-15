<?php
// Laiškų siuntimas per SMTP (nustatymai config.php -> 'mail').
// Lokaliai laiškai keliauja į Mailpit: http://localhost:8025

/** $attachments: [['name' => 'failas.csv', 'type' => 'text/csv', 'data' => turinys], ...] */
function send_mail(string $to, string $subject, string $body, array $attachments = []): bool
{
    $c = config('mail');
    $fromName = '=?UTF-8?B?' . base64_encode($c['from_name']) . '?=';
    $subjectEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $body .= "\r\n\r\n--\r\nVšĮ Karate Ateitis · " . config('site_url') . "\r\nŠis laiškas išsiųstas automatiškai.";
    $body = preg_replace("/\r?\n/", "\r\n", $body);

    $head = "From: {$fromName} <{$c['from']}>\r\n"
        . "To: <{$to}>\r\n"
        . "Subject: {$subjectEnc}\r\n"
        . 'Date: ' . date('r') . "\r\n"
        . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@karateka.lt>' . "\r\n"
        . "MIME-Version: 1.0\r\n";
    if (!$attachments) {
        $message = $head . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($body));
    } else {
        // Laiškas su priedais (pvz. egzamino protokolas Excel / CSV)
        $bnd = 'b' . bin2hex(random_bytes(10));
        $message = $head . "Content-Type: multipart/mixed; boundary=\"{$bnd}\"\r\n\r\n"
            . "--{$bnd}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($body));
        foreach ($attachments as $a) {
            $fn = '=?UTF-8?B?' . base64_encode((string) $a['name']) . '?=';
            $message .= "--{$bnd}\r\nContent-Type: " . ($a['type'] ?? 'application/octet-stream') . "; name=\"{$fn}\"\r\n"
                . "Content-Disposition: attachment; filename=\"{$fn}\"\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode((string) $a['data']));
        }
        $message .= "--{$bnd}--\r\n";
    }

    $prefix = $c['secure'] === 'ssl' ? 'ssl://' : 'tcp://';
    $socket = @stream_socket_client($prefix . $c['host'] . ':' . $c['port'], $errno, $errstr, 20);
    if (!$socket) {
        error_log("send_mail connect failed: $errstr ($errno)");
        return false;
    }
    stream_set_timeout($socket, 20);

    $read = function ($expected) use ($socket) {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return in_array((int) substr($response, 0, 3), (array) $expected, true) ? $response : false;
    };
    $cmd = function (string $command, $expected) use ($socket, $read) {
        fwrite($socket, $command . "\r\n");
        return $read($expected);
    };

    $ok = false;
    $stage = 'greeting';
    do {
        if (!$read(220)) break;
        $stage = 'ehlo';
        if (!$cmd('EHLO karateka.lt', 250)) break;
        if ($c['user'] !== '') {
            $stage = 'auth';
            if (!$cmd('AUTH LOGIN', 334)) break;
            if (!$cmd(base64_encode($c['user']), 334)) break;
            if (!$cmd(base64_encode($c['pass']), 235)) break;
        }
        $stage = 'envelope';
        if (!$cmd("MAIL FROM:<{$c['from']}>", 250)) break;
        if (!$cmd("RCPT TO:<{$to}>", [250, 251])) break;
        if (!$cmd('DATA', 354)) break;
        $stage = 'data';
        if (!$cmd($message . "\r\n.", 250)) break;
        $ok = true;
    } while (false);

    @$cmd('QUIT', [221, 250]);
    fclose($socket);

    if (!$ok) {
        error_log("send_mail failed at stage: $stage (to: $to)");
    }
    return $ok;
}
