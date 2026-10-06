<?php
// Demonstraciniai duomenys test svetainei. Sugeneruoja SQL, kurį importuojate per phpMyAdmin.
//   docker compose exec -T web php /tools/demo_data.php > deploy/demo-data.sql
//
// Visų demo paskyrų el. paštai baigiasi @demo.karateka.lt, slaptažodis - žr. DEMO_PASSWORD.
// Demo renginiai / pamokos turi created_at = 2000-01-01 00:00:00, naujienos - updated_at = 2000-01-01 00:00:00,
// todėl juos visus vienu kartu ištrina demo-remove.sql (jūsų pačių sukurti duomenys lieka).
// Datos skaičiuojamos nuo importo dienos (CURDATE), todėl renginiai visada „artėjantys“.

const DEMO_PASSWORD = 'demo12345';
const SENTINEL = '2000-01-01 00:00:00';
mt_srand(2026);

function s(?string $v): string
{
    return $v === null ? 'NULL' : "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $v) . "'";
}
function ascii(string $v): string
{
    return strtolower(strtr($v, ['ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i', 'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z',
        'Ą' => 'a', 'Č' => 'c', 'Ę' => 'e', 'Ė' => 'e', 'Į' => 'i', 'Š' => 's', 'Ų' => 'u', 'Ū' => 'u', 'Ž' => 'z']));
}
function days_ago_age(int $years): int
{
    return $years * 365 + mt_rand(10, 350);
}
function pick(array $a)
{
    return $a[mt_rand(0, count($a) - 1)];
}

$hash = password_hash(DEMO_PASSWORD, PASSWORD_BCRYPT);
$out = [];
$out[] = '-- Demo duomenys karateka.lt test svetainei. Prisijungimas: <vardas>.<pavarde>@demo.karateka.lt, slaptažodis: ' . DEMO_PASSWORD;
$out[] = '-- Ištrinti: importuokite demo-remove.sql';
$out[] = 'SET NAMES utf8mb4;';
$out[] = "SET @admin = (SELECT id FROM accounts WHERE role = 'admin' ORDER BY id LIMIT 1);";

// [vyriška pavardė, mergaitės, ištekėjusios moters]
$families = [
    ['Kazlauskas', 'Kazlauskaitė', 'Kazlauskienė'], ['Jankauskas', 'Jankauskaitė', 'Jankauskienė'],
    ['Petrauskas', 'Petrauskaitė', 'Petrauskienė'], ['Stankevičius', 'Stankevičiūtė', 'Stankevičienė'],
    ['Vasiliauskas', 'Vasiliauskaitė', 'Vasiliauskienė'], ['Žukauskas', 'Žukauskaitė', 'Žukauskienė'],
    ['Butkus', 'Butkutė', 'Butkienė'], ['Paulauskas', 'Paulauskaitė', 'Paulauskienė'],
    ['Urbonas', 'Urbonaitė', 'Urbonienė'], ['Kavaliauskas', 'Kavaliauskaitė', 'Kavaliauskienė'],
    ['Navickas', 'Navickaitė', 'Navickienė'], ['Rimkus', 'Rimkutė', 'Rimkienė'],
    ['Balčiūnas', 'Balčiūnaitė', 'Balčiūnienė'], ['Sakalauskas', 'Sakalauskaitė', 'Sakalauskienė'],
    ['Mockus', 'Mockutė', 'Mockienė'], ['Lukoševičius', 'Lukoševičiūtė', 'Lukoševičienė'],
    ['Adomaitis', 'Adomaitytė', 'Adomaitienė'], ['Gudaitis', 'Gudaitytė', 'Gudaitienė'],
];
$boys = ['Lukas', 'Matas', 'Jonas', 'Dominykas', 'Kajus', 'Nojus', 'Herkus', 'Joris', 'Benas', 'Gustas', 'Augustas', 'Rokas', 'Domas', 'Ignas', 'Pijus', 'Vakaris'];
$girls = ['Emilija', 'Gabija', 'Austėja', 'Ugnė', 'Kamilė', 'Liepa', 'Lėja', 'Amelija', 'Saulė', 'Goda', 'Urtė', 'Miglė', 'Ieva', 'Smiltė'];
$moms = ['Rasa', 'Inga', 'Jurgita', 'Kristina', 'Laura', 'Agnė', 'Rūta', 'Vaida', 'Eglė', 'Simona', 'Jolanta', 'Asta'];
$men = ['Tomas', 'Andrius', 'Mantas', 'Darius', 'Paulius', 'Marius'];

// Grupės (id iš 002_groups.sql): vaikų grupės su tvarkaraščiu, jaunimas 12, suaugusieji 13
$kidGroups = [1, 1, 2, 2, 3, 4, 5, 6, 11];
$members = ['vaikai' => [], 'jaunimas' => [], 'suauge' => []];   // [kintamasis, grupė]
$memberVar = 0;
$accountVar = 0;
$usedEmails = [];

$addAccount = function (string $first, string $last, string $status = 'active') use (&$out, &$accountVar, $hash, &$usedEmails) {
    $email = ascii($first) . '.' . ascii($last) . '@demo.karateka.lt';
    $n = 2;
    while (isset($usedEmails[$email])) {
        $email = ascii($first) . '.' . ascii($last) . $n++ . '@demo.karateka.lt';
    }
    $usedEmails[$email] = true;
    $var = '@a' . (++$accountVar);
    $approved = $status === 'active' ? 'NOW()' : 'NULL';
    $out[] = "INSERT INTO accounts (email, password_hash, first_name, last_name, phone, status, email_verified_at, approved_at) VALUES ("
        . s($email) . ', ' . s($hash) . ', ' . s($first) . ', ' . s($last) . ", '+370 6" . mt_rand(1000000, 9999999) . "', " . s($status) . ", NOW(), $approved);";
    $out[] = "SET $var = LAST_INSERT_ID();";
    return $var;
};
$addMember = function (string $first, string $last, int $ageYears, ?int $group, string $status = 'active') use (&$out, &$memberVar) {
    $var = '@m' . (++$memberVar);
    $out[] = 'INSERT INTO members (first_name, last_name, birth_date, group_id, photo_consent, parent_consent_at, status) VALUES ('
        . s($first) . ', ' . s($last) . ', DATE_SUB(CURDATE(), INTERVAL ' . days_ago_age($ageYears) . ' DAY), '
        . ($group ?? 'NULL') . ', ' . (mt_rand(0, 4) ? 1 : 0) . ', NOW(), ' . s($status) . ');';
    $out[] = "SET $var = LAST_INSERT_ID();";
    return $var;
};
$link = function (string $acc, string $mem, string $rel) use (&$out) {
    $out[] = "INSERT INTO account_members (account_id, member_id, relation) VALUES ($acc, $mem, '$rel');";
};

$out[] = '';
$out[] = '-- ===== Šeimos su vaikais =====';
$famIdx = 0;
for ($f = 0; $f < 13; $f++) {
    [$male, $girlLast, $wifeLast] = $families[$famIdx++];
    $mom = $addAccount($moms[$f % count($moms)], $wifeLast);
    $kids = mt_rand(1, 3);
    for ($k = 0; $k < $kids; $k++) {
        $isBoy = mt_rand(0, 1) === 1;
        $first = $isBoy ? pick($boys) : pick($girls);
        $last = $isBoy ? $male : $girlLast;
        $age = mt_rand(5, 17);
        if ($age >= 13) {
            $group = 12;
            $cat = 'jaunimas';
        } else {
            $group = pick($kidGroups);
            $cat = 'vaikai';
        }
        $m = $addMember($first, $last, $age, $group);
        $link($mom, $m, 'parent');
        $members[$cat][] = [$m, $group, $first];
        // Kai kurie paaugliai turi savo prisijungimą (tas pats narys)
        if ($age >= 14 && mt_rand(0, 1)) {
            $teen = $addAccount($first, $last);
            $link($teen, $m, 'self');
        }
    }
}

$out[] = '';
$out[] = '-- ===== Suaugusieji =====';
for ($i = 0; $i < 7; $i++) {
    [$male, $girlLast] = $families[$famIdx % count($families)];
    $famIdx++;
    $isMan = $i % 3 !== 2;
    $first = $isMan ? $men[$i % count($men)] : pick($moms);
    $last = $isMan ? $male : $girlLast;
    $acc = $addAccount($first, $last);
    $m = $addMember($first, $last, mt_rand(22, 48), 13);
    $link($acc, $m, 'self');
    $members['suauge'][] = [$m, 13, $first];
}

$out[] = '';
$out[] = '-- ===== Laukia trenerio patvirtinimo =====';
$p = $addAccount('Vaida', 'Gudaitienė', 'pending_approval');
$link($p, $addMember('Joris', 'Gudaitis', 7, null, 'pending'), 'parent');
$link($p, $addMember('Goda', 'Gudaitytė', 10, null, 'pending'), 'parent');
$p2 = $addAccount('Marius', 'Adomaitis', 'pending_approval');
$link($p2, $addMember('Marius', 'Adomaitis', 34, null, 'pending'), 'self');

// ===== Renginiai =====
$out[] = '';
$out[] = '-- ===== Renginiai (artėjantys ir praėję) =====';
$events = [
    // [kintamasis, tipas, pavadinimas, nuo (dienų nuo šiandien), iki, laikas, vieta, užsienyje, aprašymas, grupės]
    ['@e1', 'other', 'Tėvų susirinkimas', 5, null, '18:00', 'Sporto centras Viršuliškės (Laisvės pr. 58)', 0, 'Aptarsime sezono planus, varžybų kalendorių ir stovyklą.', []],
    ['@e2', 'exam', 'Rudens kyu egzaminas', 12, null, '10:00', 'Sporto centras Viršuliškės (Laisvės pr. 58)', 0, 'Egzaminas vaikams ir jaunimui. Atsineškite diržą ir kimono, atvykite 20 min. anksčiau.', [1, 2, 12]],
    ['@e3', 'seminar', 'Kata seminaras su svečiu treneriu', 20, null, '11:00', 'Sporto centras Viršuliškės (Laisvės pr. 58)', 0, 'Heian ir Tekki kata detalės. Registracija pas trenerį.', [12, 13]],
    ['@e4', 'competition', 'Baltic Open', 33, 34, null, 'Ryga, Latvija', 1, 'Tarptautinės varžybos jaunimui ir suaugusiems. Kelionė autobusu.', [12, 13]],
    ['@e5', 'competition', 'Vilniaus vaikų taurė', 45, null, '09:30', 'Vilnius, Sporto rūmai', 0, 'Kata ir kumite varžybos vaikams iki 12 m.', [1, 2, 3, 4, 5, 6, 11]],
    ['@e6', 'camp', 'Žiemos stovykla', 70, 72, null, 'Trakai', 0, '3 dienų stovykla: treniruotės, žygiai, vakaronės.', []],
    ['@e7', 'competition', 'Lietuvos karate taurė', -25, null, null, 'Kaunas, Žalgirio arena', 0, 'Komandos rezultatai: 3 aukso, 4 sidabro ir 5 bronzos medaliai.', [12, 13]],
    ['@e8', 'competition', 'Riga Cup', -12, -11, null, 'Ryga, Latvija', 1, null, [12]],
    ['@e9', 'exam', 'Sezono pradžios kyu egzaminas', -30, null, '10:00', 'Sporto centras Viršuliškės (Laisvės pr. 58)', 0, null, []],
    ['@e10', 'competition', 'Vaikų rudens turnyras', -18, null, '10:00', 'Vilnius', 0, null, [1, 2, 3, 4, 5, 6, 11]],
];
foreach ($events as [$var, $type, $title, $from, $to, $time, $loc, $abroad, $desc, $groups]) {
    $date = fn_date($from);
    $out[] = 'INSERT INTO events (type, title, starts_on, ends_on, start_time, location, is_abroad, description, created_at) VALUES ('
        . s($type) . ', ' . s($title) . ", $date, " . ($to === null ? 'NULL' : fn_date($to)) . ', ' . s($time) . ', ' . s($loc) . ", $abroad, " . s($desc) . ", '" . SENTINEL . "');";
    $out[] = "SET $var = LAST_INSERT_ID();";
    foreach ($groups as $g) {
        $out[] = "INSERT INTO event_groups (event_id, group_id) VALUES ($var, $g);";
    }
}
function fn_date(int $days): string
{
    return $days >= 0 ? "DATE_ADD(CURDATE(), INTERVAL $days DAY)" : 'DATE_SUB(CURDATE(), INTERVAL ' . (-$days) . ' DAY)';
}

// ===== Taškai =====
$out[] = '';
$out[] = '-- ===== Rezultatai ir taškai =====';
$award = function (string $member, string $code, int $days, ?string $event, ?string $note = null) use (&$out) {
    $out[] = "INSERT INTO point_awards (member_id, category_id, points, awarded_on, event_id, note, awarded_by) "
        . "SELECT $member, id, points, " . fn_date($days) . ', ' . ($event ?? 'NULL') . ', ' . s($note) . ", @admin FROM point_categories WHERE code = " . s($code) . ';';
};
// Varžybos: dalyvavimas + kartais prizinė vieta
$competition = function (array $list, string $event, int $days, bool $abroad, int $share) use ($award) {
    $where = $abroad ? 'abroad' : 'lt';
    foreach ($list as [$m]) {
        if (mt_rand(1, 100) > $share) {
            continue;
        }
        $award($m, "comp_$where", $days, $event);
        $r = mt_rand(1, 10);
        if ($r <= 4) {
            $award($m, 'place' . min(3, $r) . "_$where", $days, $event);
        }
    }
};
$competition(array_merge($members['jaunimas'], $members['suauge']), '@e7', -25, false, 75);
$competition($members['jaunimas'], '@e8', -11, true, 50);
$competition($members['vaikai'], '@e10', -18, false, 60);
foreach (array_merge($members['vaikai'], $members['jaunimas']) as [$m]) {
    if (mt_rand(1, 100) <= 70) {
        $award($m, 'exam', -30, '@e9');
    }
}
foreach ($members['suauge'] as $i => [$m]) {
    if ($i < 3) {
        $award($m, 'lead_training', -mt_rand(3, 20), null, 'Vedė vaikų treniruotę');
    }
    if ($i % 2 === 0) {
        $award($m, 'referee', -25, null, 'Teisėjavo Lietuvos karate taurėje');
    }
}
$award($members['jaunimas'][0][0], 'organize', -6, null, 'Padėjo organizuoti vaikų turnyrą');

// ===== Naujienos =====
$out[] = '';
$out[] = '-- ===== Naujienos (nuotraukos: demo-uploads.zip) =====';
$news = [
    ['Puikūs rezultatai Lietuvos karate taurėje', "Mūsų sportininkai parvežė 3 aukso, 4 sidabro ir 5 bronzos medalius!\n\nAčiū visiems, kurie atvyko palaikyti. Ypatingas ačiū tėvams už pagalbą kelionėje.", 2, ['demo_news_1.jpg', 'demo_news_2.jpg'], ['dQw4w9WgXcQ']],
    ['Rudens kyu egzaminas jau netrukus', "Egzaminas vyks Viršuliškėse. Prašome iki penktadienio patvirtinti dalyvavimą treneriui.\n\nReikalavimai kiekvienam diržui - pamokų skiltyje.", 6, ['demo_news_3.jpg'], []],
    ['Naujokų grupė vaikams - dar yra vietų', "Antradieniais ir ketvirtadieniais 17:30 Viršuliškėse renkasi naujokų grupė.\n\nPirmos dvi treniruotės nemokamos - kvieskite draugus!", 14, ['demo_news_4.jpg'], []],
    ['Sezonas prasidėjo!', "Sveiki sugrįžę į salę! Treniruotės vyksta pagal įprastą tvarkaraštį.\n\nPrimename: tvarkaraštį ir artėjančius renginius matote savo paskyroje.", 33, ['demo_news_5.jpg'], []],
    ['Vasaros stovyklos akimirkos', "Savaitė prie ežero: treniruotės aušroje, žygiai ir daug juoko. Ačiū visiems dalyviams!", 60, [], ['dQw4w9WgXcQ']],
];
foreach ($news as $i => [$title, $body, $daysAgo, $images, $videos]) {
    $var = '@n' . ($i + 1);
    $out[] = 'INSERT INTO news (title, body, author_id, is_published, published_at, updated_at) VALUES ('
        . s($title) . ', ' . s($body) . ", @admin, 1, DATE_SUB(NOW(), INTERVAL $daysAgo DAY), '" . SENTINEL . "');";
    $out[] = "SET $var = LAST_INSERT_ID();";
    $sort = 0;
    foreach ($images as $img) {
        $out[] = "INSERT INTO news_media (news_id, type, file, sort_order) VALUES ($var, 'image', " . s($img) . ', ' . (++$sort) . ');';
    }
    foreach ($videos as $v) {
        $out[] = "INSERT INTO news_media (news_id, type, youtube_id, sort_order) VALUES ($var, 'youtube', " . s($v) . ', ' . (++$sort) . ');';
    }
    // Reakcijos iš demo paskyrų
    for ($a = 1; $a <= $accountVar; $a++) {
        if (mt_rand(1, 100) <= 35) {
            $emoji = pick(['👍', '👍', '❤️', '🔥', '👏', '🥋']);
            $out[] = "INSERT IGNORE INTO reactions (news_id, account_id, emoji) SELECT $var, @a$a, " . s($emoji) . " FROM accounts WHERE id = @a$a AND status = 'active';";
        }
    }
}

// ===== Pamokos =====
$out[] = '';
$out[] = '-- ===== Pamokos =====';
$lessons = [
    ['Heian Shodan - žingsnis po žingsnio', 'Kata', "Pirmoji kata. Žiūrėkite video ir kartokite po 10 minučių kasdien.\n\nAtkreipkite dėmesį į stovėsenas ir kvėpavimą.", 'dQw4w9WgXcQ', []],
    ['Kihon: pagrindiniai smūgiai', 'Kihon', "1. Oi-zuki - smūgis į priekį žengiant.\n2. Gyaku-zuki - priešinga ranka.\n3. Age-uke - blokas aukštyn.\n\nKiekvieną pratimą kartokite po 20 kartų.", null, []],
    ['Kumite pagrindai jaunimui', 'Kumite', "Distancija, judėjimas ir pirmieji deriniai.", 'dQw4w9WgXcQ', [12]],
    ['Tempimo pratimai vaikams', 'Fizinis pasiruošimas', "1. Atsisėskite, kojos tiesios - lėtai lenkitės į priekį.\n2. Drugelis - padai kartu, keliai žemyn.\n3. Šoninis tempimas stovint.\n\nKiekvieną padėtį laikykite 20 sekundžių.", null, [1, 2, 3, 4, 5, 6, 11]],
    ['Kyu egzamino reikalavimai', null, "Baltas → geltonas diržas: Heian Shodan, kihon (oi-zuki, gyaku-zuki, age-uke, gedan-barai), gohon kumite.\n\nGeltonas → oranžinis: Heian Nidan ir aukščiau išvardinti pagrindai.", null, []],
];
foreach ($lessons as $i => [$title, $topic, $body, $video, $groups]) {
    $var = '@l' . ($i + 1);
    $out[] = 'INSERT INTO lessons (title, topic, body, videos, is_published, author_id, created_at) VALUES ('
        . s($title) . ', ' . s($topic) . ', ' . s($body) . ', ' . s($video) . ", 1, @admin, '" . SENTINEL . "');";
    $out[] = "SET $var = LAST_INSERT_ID();";
    foreach ($groups as $g) {
        $out[] = "INSERT INTO lesson_groups (lesson_id, group_id) VALUES ($var, $g);";
    }
}

// ===== Trenerio pastabos =====
$out[] = '';
$out[] = '-- ===== Trenerio pastabos =====';
$notes = [
    'Šaunuolis! Šiandien labai gerai sekėsi kata.',
    'Namuose pakartok Heian Shodan bent 5 kartus.',
    'Daugiau dėmesio stovėsenoms - kelis žemiau.',
    'Puiki koncentracija treniruotėje, taip ir toliau!',
    'Prieš egzaminą pakartok kihon derinius.',
    'Dirbk su kvėpavimu - per anksti pavargsti.',
];
$all = array_merge($members['vaikai'], $members['jaunimas']);
for ($i = 0; $i < 12 && $i < count($all); $i++) {
    [$m] = $all[$i * 2 % count($all)];
    $read = $i % 3 === 0 ? 'NULL' : 'NOW()';
    $video = $i % 4 === 1 ? "'dQw4w9WgXcQ'" : 'NULL';
    $out[] = 'INSERT INTO coach_notes (member_id, author_id, note_date, body, youtube_id, read_at) VALUES ('
        . "$m, @admin, " . fn_date(-mt_rand(1, 14)) . ', ' . s(pick($notes)) . ", $video, $read);";
}

echo implode("\n", $out) . "\n";
fwrite(STDERR, sprintf("Paskyros: %d, nariai: %d\n", $accountVar, $memberVar));
