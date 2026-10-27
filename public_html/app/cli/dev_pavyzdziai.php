<?php
// TIK LOKALIAI: v1.8 naujovių pavyzdžiai, kad atidarius svetainę viskas iškart matytųsi.
// Paleidžia tools/lokali-svetaine.ps1 po demo duomenų (arba ranka):
//   docker compose exec web php app/cli/dev_pavyzdziai.php
// Sukuria: kvietimus su terminu, pavėlavusį, registraciją į egzaminą, „Moku“ prašymus ir naują lygį,
// atsiųstą pamoką, pokalbį su Deniu, viešą klausimą, anoniminį komentarą, pranešimą visiems, atšauktą sutikimą.
if (PHP_SAPI !== 'cli') {
    exit;
}
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require dirname(__DIR__) . '/bootstrap.php';

if (config('env') !== 'local') {
    exit("Pavyzdžiai kuriami tik lokaliai (config env = local).\n");
}
$admin = q_one('SELECT * FROM accounts WHERE email = "info@karateka.lt"');
$parent = q_one('SELECT * FROM accounts WHERE email = "tevai@karateka.test"');
$adult = q_one('SELECT * FROM accounts WHERE email = "narys@karateka.test"');
if (!$admin || !$parent || !$adult) {
    exit("Pirma paleiskite app/cli/dev_seed.php\n");
}
if (site_setting('dev_pavyzdziai')) {
    exit("Pavyzdžiai jau sukurti.\n");
}
set_site_setting('dev_pavyzdziai', date('Y-m-d H:i'));
$kid = function (string $first) use ($parent): array {
    return q_one('SELECT m.* FROM members m JOIN account_members am ON am.member_id = m.id WHERE am.account_id = ? AND m.first_name = ?', [(int) $parent['id'], $first]);
};
$jonas = $kid('Jonas');
$austeja = $kid('Austėja');
$tomas = q_one('SELECT m.* FROM members m JOIN account_members am ON am.member_id = m.id WHERE am.account_id = ? AND am.relation = "self"', [(int) $adult['id']]);
$day = function (int $n): string {
    return date('Y-m-d', strtotime(($n >= 0 ? '+' : '') . $n . ' days'));
};
$by = (int) $admin['id'];
$allActive = array_map('intval', q('SELECT id FROM members WHERE status = "active"')->fetchAll(PDO::FETCH_COLUMN));

// ---------- Renginiai ----------
$newEvent = function (string $type, string $title, string $summary, string $desc, int $in, ?int $until, string $time, string $place) use ($day): array {
    q('INSERT INTO events (type, title, summary, description, starts_on, start_time, location, registration, registration_until) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)',
        [$type, $title, $summary, $desc, $day($in), $time, $place, $until === null ? null : $day($until)]);
    return q_one('SELECT * FROM events WHERE id = ?', [(int) db()->lastInsertId()]);
};
// Kviečiami visi, registracija dar vyksta (laikmatis)
$camp = $newEvent('camp', 'Rudens atostogų stovykla', 'Trys dienos treniruočių, žaidimų ir kata. Vietų skaičius ribotas.',
    "Stovykla vyks Trakuose.\n\nKą pasiimti: kimono, sportinę aprangą lauke, miegmaišį ir gerą nuotaiką.", 8, 6, '10:00', 'Trakai, poilsio namai „Ežerėlis“');
invite_members($camp, $allActive, $by, false);
// Registracija jau baigėsi - galima tik „pavėlavau“
$seminar = $newEvent('seminar', 'Heian kata seminaras', 'Seminaras visoms grupėms: Heian kata detalės.', 'Seminarą ves Denis. Atsineškite vandens.', 3, -1, '11:00', 'Sporto centras Viršuliškės');
invite_members($seminar, $allActive, $by, false);
set_participation((int) $seminar['id'], (int) $tomas['id'], 'late');
notify_admins('late_request', 'Pavėlavo užsiregistruoti: ' . $tomas['first_name'] . ' ' . $tomas['last_name'],
    $seminar['title'] . ' (' . fmt_date($seminar['starts_on'], true) . '). Registracija baigėsi ' . fmt_date(event_deadline($seminar)),
    'admin/renginio-dalyviai.php?id=' . (int) $seminar['id'], (int) $tomas['id'], (int) $seminar['id']);
// Atviras renginys: kvietimų nėra, prisijungti gali visi
$newEvent('other', 'Atvira treniruotė parke', 'Kviečiame visus - ir draugus, ir tėvus. Registruotis nebūtina, bet smagu žinoti, kiek būsime.',
    'Vingio parkas, prie estrados. Jei lis - treniruotė salėje.', 10, null, '12:00', 'Vingio parkas');

// Registracija į egzaminą - pranešimas Deniui
$exam = q_one('SELECT * FROM events WHERE type = "exam" AND starts_on > CURDATE() ORDER BY starts_on LIMIT 1');
if ($exam) {
    set_participation((int) $exam['id'], (int) $austeja['id'], 'going');
    notify_admins('exam_registration', 'Užsiregistravo į egzaminą: ' . $austeja['first_name'] . ' ' . $austeja['last_name'],
        $exam['title'] . ' · ' . fmt_date($exam['starts_on'], true), 'admin/renginio-dalyviai.php?id=' . (int) $exam['id'], (int) $austeja['id'], (int) $exam['id']);
}

// ---------- Egzamino programa ir XP ----------
// Jonas: 5 punktai jau patvirtinti (+100 XP - naujas lygis, tėvams iššoks šventė), 2 laukia Denio patvirtinimo
$nextBelt = next_belt($jonas['belt_level'] !== null ? (int) $jonas['belt_level'] : null);
$reqs = q_all('SELECT * FROM belt_requirements WHERE belt_level = ? ORDER BY FIELD(section, "kihon", "kata", "kumite", "teorija", "fizinis", "kita"), sort_order, id', [$nextBelt]);
q('DELETE FROM requirement_progress WHERE member_id = ?', [(int) $jonas['id']]);
foreach (array_slice($reqs, 0, 5) as $r) {
    approve_skill((int) $jonas['id'], (int) $r['id'], $by);
}
foreach (array_slice($reqs, 5, 2) as $r) {
    request_skill($jonas, $r, $parent);
}
// „Treneris patvirtino“ - paliekamas tik paskutinis, kad „Svarbu“ nebūtų per ilgas
if ($reqs) {
    q('UPDATE notifications SET done_at = NOW() WHERE type = "skill_approved" AND member_id = ? AND ref_id <> ?', [(int) $jonas['id'], (int) $reqs[min(4, count($reqs) - 1)]['id']]);
}

// ---------- Pamoka ir pokalbiai ----------
$lesson = q_one('SELECT id FROM lessons WHERE title LIKE "Heian Shodan%" AND is_published = 1 ORDER BY id LIMIT 1');
if ($lesson) {
    send_coach_note($jonas, $admin, 'Pasikartok prieš egzaminą - ypač posūkius.', (int) $lesson['id'], false, false);
}
// Bendras pokalbis: Rasa parašė, Denis atsakė (Rasai - naujas atsakymas)
$ago = function (int $min): string {
    return date('Y-m-d H:i:s', time() - $min * 60);
};
$msg = function (array $m, int $author, bool $staff, string $body, string $when, ?int $eventId = null) {
    $id = add_member_message((int) $m['id'], $author, $staff, $body, null, null, $eventId);
    q('UPDATE member_messages SET created_at = ? WHERE id = ?', [$when, $id]);
    return $id;
};
$msg($jonas, (int) $parent['id'], false, 'Laba diena, ar šį šeštadienį treniruotė vyks?', $ago(90));
q('UPDATE member_messages SET read_at = NOW() WHERE member_id = ? AND from_staff = 0', [(int) $jonas['id']]);
$msg($jonas, $by, true, 'Laba diena! Taip, vyks kaip įprastai, 10:00.', $ago(40));
// Tomas parašė - laukia Denio atsakymo
$msg($tomas, (int) $adult['id'], false, 'Ar galiu kartais ateiti ir į jaunimo treniruotę?', $ago(15));
// Klausimas prie renginio - Denis atsakė ir parodė visiems (be vardo)
$q = $msg($jonas, (int) $parent['id'], false, 'Ar reikia pasiimti miegmaišį?', $ago(120), (int) $camp['id']);
q('UPDATE member_messages SET is_public = 1, read_at = NOW() WHERE id = ?', [$q]);
$msg($jonas, $by, true, 'Taip, miegmaišį ir pagalvę pasiimkite.', $ago(100), (int) $camp['id']);
q('UPDATE member_messages SET read_at = NOW() WHERE member_id = ? AND event_id = ? AND from_staff = 1', [(int) $jonas['id'], (int) $camp['id']]);
// Anoniminis komentaras
q('INSERT INTO anon_feedback (body, page, created_at) VALUES (?, ?, ?)', ['Gal galėtumėte vasarą kartais treniruotis lauke?', '/renginiai.php', $ago(30)]);

// ---------- Pranešimas visiems (paskyroje) ----------
$title = 'Šeštadienio treniruotė perkelta';
$body = "Šį šeštadienį treniruotė vyks ne 10:00, o 11:00 - salėje vyksta varžybos.\nAčiū, kad suprantate!";
$accounts = q('SELECT DISTINCT a.id FROM accounts a JOIN account_members am ON am.account_id = a.id JOIN members m ON m.id = am.member_id
                WHERE a.status = "active" AND m.status = "active" AND a.id <> ?', [$by])->fetchAll(PDO::FETCH_COLUMN);
q('INSERT INTO broadcasts (title, body, channels, recipients, sent_by) VALUES (?, ?, "site", ?, ?)', [$title, $body, count($accounts), $by]);
$bc = (int) db()->lastInsertId();
foreach ($accounts as $aid) {
    notify_account((int) $aid, 'broadcast', $title, $body, null, null, $bc);
}

// ---------- Nuotraukos: Tomas atšaukė sutikimą ----------
q('UPDATE members SET photo_consent = 0 WHERE id = ?', [(int) $tomas['id']]);
notify_admins('consent_withdrawn', 'Atšauktas sutikimas dėl nuotraukų: ' . $tomas['first_name'] . ' ' . $tomas['last_name'],
    'Atšaukė ' . $adult['first_name'] . ' ' . $adult['last_name'] . '. Naujose nuotraukose šio nario neskelbkite.', 'admin/nariai.php?id=' . (int) $tomas['id'], (int) $tomas['id']);

// ---------- Grupių kainos (pagal sutartį) ----------
q('UPDATE training_groups SET price_note = "45 €/mėn. (1 kartą per savaitę)" WHERE name LIKE "%Jeruzal%"');
q('UPDATE training_groups SET price_note = "30 €/mėn. (1 kartą per savaitę)" WHERE name LIKE "%Pašilai%"');

// Pokalbio užuomina - visiems po prisijungimo
q('UPDATE accounts SET chat_hint_at = NULL');

echo "v1.8 pavyzdžiai sukurti: renginiai su terminu, pranešimai, XP, pokalbis.\n";
