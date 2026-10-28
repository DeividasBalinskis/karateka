<?php
// TIK LOKALIAI: naujovių pavyzdžiai, kad atidarius svetainę viskas iškart matytųsi.
// Viskas, kas sukuriama, pavadinime turi „TEST:“, o aprašyme - ką patikrinti. Test ir tikroje svetainėje nepaleidžiama.
// Paleidžia tools/lokali-svetaine.ps1 po demo duomenų (arba ranka):
//   docker compose exec web php app/cli/dev_pavyzdziai.php
// Sukuria: kvietimus su terminu, pavėlavusį, registraciją į egzaminą, „Moku“ prašymus ir naują lygį,
// atsiųstą pamoką, pokalbį su Deniu, viešą klausimą, anoniminį komentarą, pranešimą visiems, atšauktą sutikimą,
// atšauktas treniruotes.
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
/** v1.10: atšauktos treniruotės (vienos grupės - po 5 d., atostogos visoms - po 20 d.), pranešimas tėvams */
function dev_examples_v110(array $admin, array $parent): void
{
    if (site_setting('dev_pavyzdziai_v110')) {
        return;
    }
    set_site_setting('dev_pavyzdziai_v110', date('Y-m-d H:i'));
    $jonas = q_one('SELECT m.* FROM members m JOIN account_members am ON am.member_id = m.id WHERE am.account_id = ? AND m.first_name = "Jonas"', [(int) $parent['id']]);
    if (!$jonas || !$jonas['group_id']) {
        return;
    }
    $in = function (int $n): string {
        return date('Y-m-d', strtotime("+$n days"));
    };
    q('INSERT INTO training_cancellations (starts_on, ends_on, reason, created_by) VALUES (?, ?, ?, ?)',
        [$in(5), $in(5), 'TEST: Salė užimta (pavyzdys - Nustatymai → Atšauktos treniruotės)', (int) $admin['id']]);
    $id = (int) db()->lastInsertId();
    q('INSERT INTO training_cancellation_groups (cancellation_id, group_id) VALUES (?, ?)', [$id, (int) $jonas['group_id']]);
    notify_account((int) $parent['id'], 'cancelled', 'Treniruočių nebus: ' . fmt_date($in(5)), 'TEST: Salė užimta (pavyzdys) (Jonas)', 'paskyra.php', null, $id);
    q('INSERT INTO training_cancellations (starts_on, ends_on, reason, created_by) VALUES (?, ?, ?, ?)',
        [$in(20), $in(22), 'TEST: Atostogos visoms grupėms (pavyzdys)', (int) $admin['id']]);
}

if (site_setting('dev_pavyzdziai_v19')) {
    $had = (bool) site_setting('dev_pavyzdziai_v110');
    dev_examples_v110($admin, $parent);
    exit($had ? "Pavyzdžiai jau sukurti.\n" : "Pridėti v1.10 pavyzdžiai: atšauktos treniruotės.\n");
}
$upgrade = (bool) site_setting('dev_pavyzdziai');   // v1.8 pavyzdžiai jau yra - tik pervadinami į TEST ir pridedami nauji
set_site_setting('dev_pavyzdziai', date('Y-m-d H:i'));
set_site_setting('dev_pavyzdziai_v19', date('Y-m-d H:i'));
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
$teen = q_one('SELECT * FROM accounts WHERE email = "jaunuolis@karateka.test"');

if ($upgrade) {
    // Ankstesni (v1.8) pavyzdžiai: aiškiai pažymimi „TEST:“ - nieko neištrinant
    $rename = [
        'Rudens atostogų stovykla' => ['TEST: Stovykla - registracija vyksta', 'Ką patikrinti: kvietimas „Svarbu“ su laikmačiu, „Dalyvausiu“, komentaras Deniui.'],
        'Heian kata seminaras'     => ['TEST: Seminaras - registracija baigėsi', 'Ką patikrinti: po termino - „Noriu dalyvauti (pavėlavau)“ ir Denio patvirtinimas.'],
        'Atvira treniruotė parke'  => ['TEST: Atviras renginys', 'Ką patikrinti: niekas nepakviestas, bet prisijungti gali visi.'],
    ];
    foreach ($rename as $old => [$title, $summary]) {
        q('UPDATE events SET title = ?, summary = ? WHERE title = ?', [$title, $summary, $old]);
    }
    q('UPDATE notifications SET title = "TEST: Pranešimas nariams" WHERE type = "broadcast" AND title = "Šeštadienio treniruotė perkelta"');
    q('UPDATE broadcasts SET title = "TEST: Pranešimas nariams" WHERE title = "Šeštadienio treniruotė perkelta"');
    // Nauji v1.9: anoniminis komentaras su autoriumi ir neatsakytas komentaras prie stovyklos
    $id = add_member_message((int) $tomas['id'], (int) $adult['id'], false, 'TEST: Gal galėtumėte vasarą kartais treniruotis lauke? (anoniminis komentaras - Denis mato, kas parašė, ir gali paviešinti be vardo)');
    q('UPDATE member_messages SET anonymous = 1, page = "/renginiai.php" WHERE id = ?', [$id]);
    notify_comment($tomas, $adult, $id);
    $camp = q_one('SELECT * FROM events WHERE title = "TEST: Stovykla - registracija vyksta" ORDER BY id DESC LIMIT 1');
    if ($camp && $teen) {
        $id = add_member_message((int) $austeja['id'], (int) $teen['id'], false, 'TEST: Ar stovykloje bus maitinimas?', null, null, (int) $camp['id']);
        notify_comment($austeja, $teen, $id);
    }
    if (site_setting('reactions') === null) {
        set_site_setting('reactions', '👍 ❤️ 🔥 👏 🥋 belt:2 belt:4 belt:10 teisejas');
    }
    dev_examples_v110($admin, $parent);
    exit("Ankstesni pavyzdžiai pažymėti „TEST:“, pridėti nauji (komentarai, anoniminis komentaras, atšauktos treniruotės).\n");
}

// ---------- Renginiai ----------
$newEvent = function (string $type, string $title, string $summary, string $desc, int $in, ?int $until, string $time, string $place) use ($day): array {
    q('INSERT INTO events (type, title, summary, description, starts_on, start_time, location, registration, registration_until) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)',
        [$type, $title, $summary, $desc, $day($in), $time, $place, $until === null ? null : $day($until)]);
    return q_one('SELECT * FROM events WHERE id = ?', [(int) db()->lastInsertId()]);
};
// Kviečiami visi, registracija dar vyksta (laikmatis)
$camp = $newEvent('camp', 'TEST: Stovykla - registracija vyksta', 'Ką patikrinti: kvietimas „Svarbu“ su laikmačiu, „Dalyvausiu“, komentaras Deniui.',
    "Ką patikrinti:\n\n1. tevai@karateka.test - „Mano paskyra“ → „Svarbu“: kvietimas su laikmačiu „liko … d.“.\n2. Paspauskite „Dalyvausiu“ - kvietimas dingsta, čia rodoma „✓ Dalyvaus“.\n3. Apačioje parašykite komentarą Deniui (galima ir „Anonimiškai“).\n4. info@karateka.lt - „Šiandien“ pranešimas „Komentaras“ → „Atsakyti“ atveda čia; atsakykite ir paspauskite „👁 Rodyti visiems“.", 8, 6, '10:00', 'Trakai, poilsio namai „Ežerėlis“');
invite_members($camp, $allActive, $by, false);
// Registracija jau baigėsi - galima tik „pavėlavau“
$seminar = $newEvent('seminar', 'TEST: Seminaras - registracija baigėsi', 'Ką patikrinti: po termino - „Noriu dalyvauti (pavėlavau)“ ir Denio patvirtinimas.',
    "Ką patikrinti:\n\n1. tevai@karateka.test - paspauskite „Noriu dalyvauti (pavėlavau)“: būsena „Pavėlavo - laukia Denio patvirtinimo“.\n2. info@karateka.lt - „Šiandien“ → „✓ Patvirtinti dalyvavimą“.\n3. Dalyvių sąraše (Renginys → Dalyviai) - stulpelis „Vėlavo“.", 3, -1, '11:00', 'Sporto centras Viršuliškės');
invite_members($seminar, $allActive, $by, false);
set_participation((int) $seminar['id'], (int) $tomas['id'], 'late');
notify_admins('late_request', 'Pavėlavo užsiregistruoti: ' . $tomas['first_name'] . ' ' . $tomas['last_name'],
    $seminar['title'] . ' (' . fmt_date($seminar['starts_on'], true) . '). Registracija baigėsi ' . fmt_date(event_deadline($seminar)),
    'admin/renginio-dalyviai.php?id=' . (int) $seminar['id'], (int) $tomas['id'], (int) $seminar['id']);
// Atviras renginys: kvietimų nėra, prisijungti gali visi
$newEvent('other', 'TEST: Atviras renginys', 'Ką patikrinti: niekas nepakviestas, bet prisijungti gali visi.',
    "Ką patikrinti:\n\n1. Kuriant renginį nepažymėta nė viena grupė - kvietimų nėra, „Svarbu“ jis nerodomas.\n2. Bet kuris narys šiame puslapyje gali paspausti „Dalyvausiu“.", 10, null, '12:00', 'Vingio parkas');

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
    send_coach_note($jonas, $admin, 'TEST: atsiųsta pamoka - paspauskite „Peržiūrėjau“ (čia arba pamokos viršuje), ji dings iš „Svarbu“.', (int) $lesson['id'], false, false);
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
$msg($jonas, (int) $parent['id'], false, 'TEST: Laba diena, ar šį šeštadienį treniruotė vyks?', $ago(90));
q('UPDATE member_messages SET read_at = NOW() WHERE member_id = ? AND from_staff = 0', [(int) $jonas['id']]);
$msg($jonas, $by, true, 'TEST: Taip, vyks kaip įprastai, 10:00. (atsakymas matosi pokalbyje ir „Svarbu“)', $ago(40));
// Tomas parašė - laukia Denio atsakymo
$msg($tomas, (int) $adult['id'], false, 'TEST: Ar galiu kartais ateiti ir į jaunimo treniruotę? (Denis - atsakykite pokalbio langelyje)', $ago(15));
// Klausimas prie renginio - Denis atsakė ir parodė visiems (be vardo)
$q = $msg($jonas, (int) $parent['id'], false, 'TEST: Ar reikia pasiimti miegmaišį?', $ago(120), (int) $camp['id']);
q('UPDATE member_messages SET is_public = 1, read_at = NOW() WHERE id = ?', [$q]);
$msg($jonas, $by, true, 'TEST: Taip, miegmaišį ir pagalvę pasiimkite. (paviešintas atsakymas - mato visi nariai)', $ago(100), (int) $camp['id']);
q('UPDATE member_messages SET read_at = NOW() WHERE member_id = ? AND event_id = ? AND from_staff = 1', [(int) $jonas['id'], (int) $camp['id']]);
// Anoniminis komentaras: Denis mato, kas parašė (Tomas), paviešinus - be vardo
$anonId = $msg($tomas, (int) $adult['id'], false, 'TEST: Gal galėtumėte vasarą kartais treniruotis lauke? (anoniminis komentaras - Denis mato, kas parašė, ir gali paviešinti be vardo)', $ago(30));
q('UPDATE member_messages SET anonymous = 1, page = "/renginiai.php" WHERE id = ?', [$anonId]);
notify_comment($tomas, $adult, $anonId);
// Komentaras prie stovyklos, dar neatsakytas - Deniui pranešimas „Komentaras“ su nuoroda į renginį
$c = $msg($austeja, (int) $teen['id'], false, 'TEST: Ar stovykloje bus maitinimas?', $ago(20), (int) $camp['id']);
notify_comment($austeja, $teen, $c);

// ---------- Pranešimas visiems (paskyroje) ----------
$title = 'TEST: Pranešimas nariams';
$body = "Ką patikrinti: šis pranešimas visiems atėjo per Treneriams → Pranešimai. Paspauskite „Supratau“ - jis dingsta iš „Svarbu“.";
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

// Reakcijos: ir diržų spalvos bei teisėjo vėliavėlės (jei adminas dar nieko nekeitė)
if (site_setting('reactions') === null) {
    set_site_setting('reactions', '👍 ❤️ 🔥 👏 🥋 belt:2 belt:4 belt:10 teisejas');
}

dev_examples_v110($admin, $parent);

echo "TEST pavyzdžiai sukurti: renginiai su terminu, pranešimai, XP, pokalbis, komentarai, atšauktos treniruotės.\n";
