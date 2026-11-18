<?php
// Renginių kalendorius telefonui ir Google / Apple kalendoriui (prenumerata, webcal://…/kalendorius.php): vieši renginiai
// nuo 2 mėn. atgal iki 18 mėn. į priekį. Kalendoriaus programa atsinaujina pati. Renginiai „tik nariams“ čia nerodomi.
require __DIR__ . '/app/bootstrap.php';

$rows = q_all("SELECT * FROM events WHERE members_only = 0 AND COALESCE(ends_on, starts_on) >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
                 AND starts_on <= DATE_ADD(CURDATE(), INTERVAL 18 MONTH) ORDER BY starts_on");

$esc = function (string $s): string {
    return str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n', ''], $s);
};
// Eilutės ne ilgesnės kaip 75 baitai (RFC 5545), nekarpant UTF-8 raidžių
$fold = function (string $line): string {
    $out = '';
    while (strlen($line) > 74) {
        $cut = 74;
        while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
            $cut--;
        }
        $out .= substr($line, 0, $cut) . "\r\n ";
        $line = substr($line, $cut);
    }
    return $out . $line . "\r\n";
};
$host = parse_url(abs_url(''), PHP_URL_HOST) ?: 'karateka.lt';
$ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Karateka//Renginiai//LT\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\n"
    . $fold('X-WR-CALNAME:' . $esc('Karateka - renginiai')) . "X-WR-TIMEZONE:Europe/Vilnius\r\nREFRESH-INTERVAL;VALUE=DURATION:PT6H\r\nX-PUBLISHED-TTL:PT6H\r\n";
foreach ($rows as $ev) {
    $start = str_replace('-', '', $ev['starts_on']);
    $endDay = date('Ymd', strtotime(($ev['ends_on'] ?: $ev['starts_on']) . ' +1 day'));   // visos dienos įvykis - pabaiga išskirtinė
    $ics .= "BEGIN:VEVENT\r\nUID:renginys-" . (int) $ev['id'] . '@' . $host . "\r\nDTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
    if ($ev['start_time'] && !$ev['ends_on']) {
        $t = date('His', strtotime($ev['start_time']));
        $ics .= 'DTSTART;TZID=Europe/Vilnius:' . $start . 'T' . $t . "\r\nDURATION:PT2H\r\n";
    } else {
        $ics .= 'DTSTART;VALUE=DATE:' . $start . "\r\nDTEND;VALUE=DATE:" . $endDay . "\r\n";
    }
    $desc = trim((string) ($ev['summary'] ?: mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $ev['description'])), 0, 400)));
    $link = abs_url('renginys.php?id=' . (int) $ev['id']);
    $ics .= $fold('SUMMARY:' . $esc(t(EVENT_TYPES[$ev['type']] ?? '') . ': ' . $ev['title']))
        . ($ev['location'] ? $fold('LOCATION:' . $esc((string) $ev['location'])) : '')
        . $fold('DESCRIPTION:' . $esc(($desc !== '' ? $desc . "\n" : '') . $link))
        . $fold('URL:' . $link) . "END:VEVENT\r\n";
}
$ics .= "END:VCALENDAR\r\n";

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: ' . (get('failas') ? 'attachment' : 'inline') . '; filename="karateka-renginiai.ics"');   // ?failas=1 - atsisiųsti (Samsung ir kt. Android)
header('Cache-Control: public, max-age=1800');
echo $ics;
