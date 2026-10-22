<?php
// Demo duomenys test svetainei. Sugeneruoja ikelimui/demo/demo-duomenys.sql (importuojama per phpMyAdmin):
//   docker run --rm -v "<projektas>/tools:/tools" php:7.3-cli php /tools/demo-duomenys.php > ikelimui/demo/demo-duomenys.sql
//
// Demo paskyrų el. paštai baigiasi @demo.karateka.lt, slaptažodis - DEMO_PASSWORD žemiau.
// Viską demo ištrina ikelimui/demo/demo-istrinti.sql (jūsų pačių sukurti duomenys lieka):
// demo renginiai / pamokos turi created_at = 2000-01-01, naujienos - updated_at = 2000-01-01.
// Datos skaičiuojamos nuo importo dienos (CURDATE), todėl renginiai visada "artėjantys".

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
$out[] = '-- Ištrinti: importuokite demo-istrinti.sql';
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
    // Diržas pagal amžių: vaikai baltas-žalias, jaunimas iki rudo, suaugusieji iki 2 dan
    $belt = $status !== 'active' ? 'NULL' : ($ageYears < 9 ? mt_rand(1, 2) : ($ageYears < 13 ? mt_rand(1, 4) : ($ageYears < 18 ? mt_rand(3, 8) : mt_rand(5, 11))));
    $out[] = 'INSERT INTO members (first_name, last_name, birth_date, group_id, belt_level, photo_consent, parent_consent_at, status) VALUES ('
        . s($first) . ', ' . s($last) . ', DATE_SUB(CURDATE(), INTERVAL ' . days_ago_age($ageYears) . ' DAY), '
        . ($group ?? 'NULL') . ', ' . $belt . ', ' . (mt_rand(0, 4) ? 1 : 0) . ', NOW(), ' . s($status) . ');';
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
$out[] = '-- ===== Naujienos (nuotraukos: demo-nuotraukos.zip) =====';
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

// ===== Pamokos (pagal diržą: 1 = 9 kyu baltas ... 10 = 1 dan) =====
$out[] = '';
$out[] = '-- ===== Pamokos =====';
$lessons = [
    // [pavadinimas, tema, diržas, tekstas, video, grupės]
    ['Heian Shodan - žingsnis po žingsnio', 'Kata', 1, "Pirmoji kata. Žiūrėkite video ir kartokite po 10 minučių kasdien.\n\nAtkreipkite dėmesį į stovėsenas ir kvėpavimą.", 'dQw4w9WgXcQ', []],
    ['Heian Nidan', 'Kata', 2, "Antroji kata geltonam diržui. Svarbiausia - šoniniai blokai ir posūkiai.", 'dQw4w9WgXcQ', []],
    ['Heian Sandan', 'Kata', 3, "Oranžinio diržo kata: alkūnių smūgiai ir kiba-dachi stovėsena.", 'dQw4w9WgXcQ', []],
    ['Kumite pagrindai', 'Kumite', 4, "Distancija, judėjimas ir pirmieji deriniai.", 'dQw4w9WgXcQ', []],
    ['Bassai Dai', 'Kata', 7, "Ruduoju diržu besiruošiantiems - jėga ir ritmas.", 'dQw4w9WgXcQ', []],
    ['Kihon: pagrindiniai smūgiai', 'Kihon', null, "1. Oi-zuki - smūgis į priekį žengiant.\n2. Gyaku-zuki - priešinga ranka.\n3. Age-uke - blokas aukštyn.\n\nKiekvieną pratimą kartokite po 20 kartų.", null, []],
    ['Tempimo pratimai vaikams', 'Fizinis pasiruošimas', null, "1. Atsisėskite, kojos tiesios - lėtai lenkitės į priekį.\n2. Drugelis - padai kartu, keliai žemyn.\n3. Šoninis tempimas stovint.\n\nKiekvieną padėtį laikykite 20 sekundžių.", null, [1, 2, 3, 4, 5, 6, 11]],
    ['Kyu egzamino reikalavimai', null, null, "Baltas → geltonas diržas: Heian Shodan, kihon (oi-zuki, gyaku-zuki, age-uke, gedan-barai), gohon kumite.\n\nGeltonas → oranžinis: Heian Nidan ir aukščiau išvardinti pagrindai.", null, []],
];
foreach ($lessons as $i => [$title, $topic, $belt, $body, $video, $groups]) {
    $var = '@l' . ($i + 1);
    $out[] = 'INSERT INTO lessons (title, topic, belt_level, body, videos, is_published, author_id, created_at) VALUES ('
        . s($title) . ', ' . s($topic) . ', ' . ($belt ?? 'NULL') . ', ' . s($body) . ', ' . s($video) . ", 1, @admin, '" . SENTINEL . "');";
    $out[] = "SET $var = LAST_INSERT_ID();";
    foreach ($groups as $g) {
        $out[] = "INSERT INTO lesson_groups (lesson_id, group_id) VALUES ($var, $g);";
    }
}

// Pamokų aplankai (reikia db/016): Kata -> Heian katos / Pažengusiems ir kt.
$out[] = '';
$out[] = '-- ===== Pamokų aplankai ir turinys =====';
$folderDefs = [
    // [kintamasis, tėvas, pavadinimas, ženkliukas, aprašymas, kam]
    ['@f1', null, 'Kata', '🥋', 'Katos nuo balto iki juodo diržo. Pradėkite nuo savo diržo katos.', 'karateka'],
    ['@f2', '@f1', 'Heian katos', '⭐', null, 'karateka'],
    ['@f3', '@f1', 'Pažengusiems', '🏆', null, 'karateka'],
    ['@f4', null, 'Kihon', '🥊', 'Pagrindinė technika: stovėsenos, smūgiai, blokai.', 'karateka'],
    ['@f5', null, 'Kumite', '🦶', null, 'karateka'],
    ['@f6', null, 'Fizinis pasiruošimas', '💪', null, 'karateka'],
    ['@f7', null, 'Egzaminai', '📜', null, 'karateka'],
    ['@f8', null, 'Teisėjams', '⚖️', 'Medžiaga teisėjams - mato tik turintys žymą „Teisėjas“.', 'teisejas'],
    ['@f9', null, 'Egzaminatoriams', '📝', null, 'egzaminatorius'],
];
foreach ($folderDefs as $i => [$var, $parent, $title, $icon, $desc, $aud]) {
    $out[] = 'INSERT INTO lesson_folders (parent_id, title, icon, description, audience, sort_order, created_at) VALUES ('
        . ($parent ?? 'NULL') . ', ' . s($title) . ', ' . s($icon) . ', ' . s($desc) . ', ' . s($aud) . ', ' . ($i + 1) . ", '" . SENTINEL . "');";
    $out[] = "SET $var = LAST_INSERT_ID();";
}
// Pamokos į aplankus (eilės tvarka aplanke)
foreach ([['@l1', '@f2', 1], ['@l2', '@f2', 2], ['@l3', '@f2', 3], ['@l5', '@f3', 1], ['@l4', '@f5', 1], ['@l6', '@f4', 1], ['@l7', '@f6', 1], ['@l8', '@f7', 1]] as [$l, $f, $n]) {
    $out[] = "UPDATE lessons SET folder_id = $f, sort_order = $n WHERE id = $l;";
}
// Viena pamoka - iš blokų: antraštė, tekstas, video, trenerio pastaba, sąrašas
$blocksDemo = [
    ['heading', 'Kaip mokytis', null, null],
    ['text', "Heian Shodan - pirmoji kata. Ją sudaro **21 judesys**.\n\nPirma pažiūrėkite video, tada kartokite po vieną dalį.", null, null],
    ['video', null, 'dQw4w9WgXcQ', 'Visa kata lėtai'],
    ['note', 'Atkreipkite dėmesį į kvėpavimą ir į tai, kur žiūrite - visada į tą pusę, į kurią darote judesį.', null, null],
    ['heading', 'Namų užduotis', null, null],
    ['text', "- Kartokite 10 minučių kasdien\n- Prieš veidrodį patikrinkite stovėsenas\n- Paprašykite tėvų nufilmuoti ir parodykite treneriui", null, null],
];
foreach ($blocksDemo as $i => [$type, $body, $yt, $caption]) {
    $out[] = 'INSERT INTO lesson_blocks (lesson_id, type, body, youtube_id, caption, sort_order) VALUES (@l1, ' . s($type) . ', ' . s($body) . ', ' . s($yt) . ', ' . s($caption) . ', ' . ($i + 1) . ');';
}

// Teisėjų ir egzaminatorių medžiaga (mato tik su žyma; reikia db/014)
$out[] = 'INSERT INTO lessons (title, topic, belt_level, audience, body, videos, is_published, author_id, created_at) VALUES ('
    . s('WKF kumite taisyklės: taškai ir komandos') . ", 'Teisėjavimas', NULL, 'teisejas', "
    . s("Yuko - 1 taškas, waza-ari - 2, ippon - 3.\n\nKomandos: Shobu hajime, Yame, Aka / Ao no kachi, Torimasen.\n\nPrieš varžybas išlaikykite teisėjo testą skiltyje „Egzaminas“.")
    . ", NULL, 1, @admin, '" . SENTINEL . "');";
$out[] = 'INSERT INTO lessons (title, topic, belt_level, audience, body, videos, is_published, author_id, created_at) VALUES ('
    . s('Kaip vyksta kyu egzaminas') . ", 'Egzaminai', NULL, 'egzaminatorius', "
    . s("1. Mokinys išlaiko teorijos testą (bent 83 %).\n2. Praktika: kihon, kata, kumite pagal diržo programą.\n3. Rezultatus treneris įveda svetainėje - diržas pakeliamas automatiškai.")
    . ", NULL, 1, @admin, '" . SENTINEL . "');";

$out[] = "UPDATE lessons SET folder_id = @f8 WHERE audience = 'teisejas' AND created_at = '" . SENTINEL . "';";
$out[] = "UPDATE lessons SET folder_id = @f9 WHERE audience = 'egzaminatorius' AND created_at = '" . SENTINEL . "';";

// ===== Trenerio pastabos ir užduotys =====
$out[] = '';
$out[] = '-- ===== Trenerio pastabos ir užduotys =====';
$notes = [
    // [tekstas, užduotis?, prisegta pamoka]
    ['Šaunuolis! Šiandien labai gerai sekėsi kata.', false, null],
    ['Namuose pakartok Heian Shodan bent 5 kartus.', true, '@l1'],
    ['Daugiau dėmesio stovėsenoms - kelis žemiau.', false, null],
    ['Puiki koncentracija treniruotėje, taip ir toliau!', false, null],
    ['Prieš egzaminą pakartok kihon derinius ir pažymėk, kai padarysi.', true, '@l6'],
    ['Išmok Heian Nidan pradžią iki kito antradienio.', true, '@l2'],
    ['Dirbk su kvėpavimu - per anksti pavargsti.', false, null],
    ['Kas vakarą 10 min. tempimo pratimų.', true, '@l7'],
];
$all = array_merge($members['vaikai'], $members['jaunimas']);
for ($i = 0; $i < 14 && $i < count($all); $i++) {
    [$m, $g] = $all[$i * 2 % count($all)];
    [$text, $isTask, $lesson] = $notes[$i % count($notes)];
    $read = $i % 3 === 0 ? 'NULL' : 'NOW()';
    $done = $isTask && $i % 2 === 0 ? 'NOW()' : 'NULL';     // dalis užduočių jau atlikta
    $out[] = 'INSERT INTO coach_notes (member_id, author_id, group_id, note_date, body, youtube_id, lesson_id, is_task, read_at, done_at) VALUES ('
        . "$m, @admin, $g, " . fn_date(-mt_rand(1, 14)) . ', ' . s($text) . ', NULL, ' . ($lesson ?? 'NULL') . ', ' . ($isTask ? 1 : 0) . ", $read, $done);";
}

// Žinutės (reikia db/018): keli nariai atsakė į pastabas, vienas klausia prie pamokos, treneris atsakė
$out[] = 'INSERT INTO member_messages (member_id, note_id, author_id, from_staff, body, created_at) '
    . "SELECT cn.member_id, cn.id, (SELECT am.account_id FROM account_members am WHERE am.member_id = cn.member_id ORDER BY am.relation = 'parent' DESC LIMIT 1), 0, "
    . "ELT(1 + MOD(cn.id, 3), 'Ačiū, pakartosime namuose!', 'Supratau, pabandysiu iki kitos treniruotės.', 'O ar galima tą patį daryti ir su Heian Nidan?'), NOW() - INTERVAL MOD(cn.id, 5) HOUR "
    . "FROM coach_notes cn WHERE cn.member_id BETWEEN @m1 AND @m$memberVar ORDER BY cn.id LIMIT 3;";
$out[] = "INSERT INTO member_messages (member_id, note_id, author_id, from_staff, body, created_at) SELECT mm.member_id, mm.note_id, @admin, 1, 'Taip, galima - tik pradėk lėtai.', NOW() FROM member_messages mm ORDER BY mm.id LIMIT 1;";
$out[] = 'INSERT INTO member_messages (member_id, lesson_id, author_id, from_staff, body, created_at) '
    . "SELECT @m1, @l1, (SELECT am.account_id FROM account_members am WHERE am.member_id = @m1 LIMIT 1), 0, 'Kur žiūrėti po pirmo posūkio?', NOW() - INTERVAL 2 HOUR;";

// Lankomumas: paskutinės 5 savaitės pagal grupių tvarkaraštį, ~85% buvo
$out[] = '';
$out[] = '-- ===== Lankomumas =====';
$days = [];
for ($i = 1; $i <= 35; $i++) {
    $days[] = "SELECT $i AS n";
}
$out[] = 'INSERT IGNORE INTO attendance (member_id, training_date, group_id, present, marked_by) '
    . 'SELECT m.id, d.dt, m.group_id, IF(RAND() < 0.85, 1, 0), @admin FROM members m '
    . 'JOIN schedule s ON s.group_id = m.group_id '
    . 'JOIN (SELECT CURDATE() - INTERVAL n DAY AS dt FROM (' . implode(' UNION ALL ', $days) . ') x) d ON WEEKDAY(d.dt) + 1 = s.weekday '
    . "WHERE m.status = 'active' AND m.id BETWEEN @m1 AND @m$memberVar;";
// Taškai už tas treniruotes - kaip ir žymint lankomumą svetainėje
$out[] = 'INSERT INTO point_awards (member_id, category_id, points, awarded_on, awarded_by) '
    . "SELECT a.member_id, pc.id, pc.points, a.training_date, @admin FROM attendance a JOIN point_categories pc ON pc.code = 'attendance' AND pc.is_active = 1 AND pc.points > 0 "
    . "WHERE a.present = 1 AND a.member_id BETWEEN @m1 AND @m$memberVar;";

// Vienas renginys ir viena naujiena - tik nariams
$out[] = "UPDATE events SET members_only = 1 WHERE id = @e1;";
$out[] = "UPDATE news SET members_only = 1 WHERE id = @n2;";

// Renginių dalyviai: artimiausiam egzaminui ir varžyboms - dalis dalyvaus, dalis pakviesta, keli negalės (reikia db/013)
$out[] = '';
$out[] = '-- ===== Renginių dalyviai ir žymos =====';
foreach (['exam', 'competition'] as $type) {
    $out[] = "SET @ev = (SELECT id FROM events WHERE type = '$type' AND created_at = '" . SENTINEL . "' AND starts_on >= CURDATE() ORDER BY starts_on LIMIT 1);";
    $out[] = 'INSERT IGNORE INTO event_participants (event_id, member_id, status, invited_by, responded_at) '
        . "SELECT @ev, m.id, ELT(1 + MOD(m.id, 4), 'going', 'invited', 'going', 'declined'), @admin, IF(MOD(m.id, 4) = 1, NULL, NOW()) "
        . "FROM members m WHERE @ev IS NOT NULL AND m.status = 'active' AND m.id BETWEEN @m1 AND @m$memberVar "
        . "AND (NOT EXISTS (SELECT 1 FROM event_groups eg WHERE eg.event_id = @ev) OR m.group_id IN (SELECT group_id FROM event_groups eg WHERE eg.event_id = @ev)) "
        . 'ORDER BY m.id LIMIT 14;';
}
// Keli suaugę - teisėjai ir egzaminatoriai
$out[] = "INSERT IGNORE INTO member_tags (member_id, tag) SELECT m.id, 'teisejas' FROM members m JOIN training_groups g ON g.id = m.group_id "
    . "WHERE g.category IN ('suauge', 'jaunimas') AND m.id BETWEEN @m1 AND @m$memberVar AND TIMESTAMPDIFF(YEAR, m.birth_date, CURDATE()) >= 16 ORDER BY m.id LIMIT 4;";
$out[] = "INSERT IGNORE INTO member_tags (member_id, tag) SELECT m.id, 'egzaminatorius' FROM members m JOIN training_groups g ON g.id = m.group_id "
    . "WHERE g.category = 'suauge' AND m.id BETWEEN @m1 AND @m$memberVar ORDER BY m.id LIMIT 2;";

// ===== Egzaminų programa ir testai (reikia db/014) =====
$out[] = '';
$out[] = '-- ===== Egzaminų programa ir testai =====';
$program = [
    // diržas => [[dalis, ko reikia, paaiškinimas, pamoka]]
    2 => [
        ['kihon', 'Zenkutsu-dachi', 'Priekinė stovėsena: priekinis kelis virš pėdos, užpakalinė koja tiesi.', null],
        ['kihon', 'Oi-zuki', 'Smūgis žengiant į priekį.', '@l6'],
        ['kihon', 'Gyaku-zuki', 'Smūgis priešinga ranka.', '@l6'],
        ['kihon', 'Age-uke ir gedan-barai', 'Blokas aukštyn ir žemutinis blokas.', '@l6'],
        ['kata', 'Heian Shodan', null, '@l1'],
        ['kumite', 'Gohon kumite', 'Penkių žingsnių kumite su partneriu.', null],
        ['teorija', 'Skaičiuoti iki 10 japoniškai', 'Ichi, ni, san, shi, go, roku, shichi, hachi, kū, jū.', null],
        ['teorija', 'Dojo etiketas', 'Kada ir kaip nusilenkti, kaip kreiptis į trenerį.', null],
    ],
    3 => [
        ['kihon', 'Soto-uke ir uchi-uke', 'Blokai iš išorės ir iš vidaus.', null],
        ['kihon', 'Shuto-uke kokutsu-dachi stovėsenoje', null, null],
        ['kihon', 'Mae-geri', 'Spyris į priekį.', null],
        ['kata', 'Heian Nidan', null, '@l2'],
        ['kumite', 'Sanbon kumite', 'Trijų žingsnių kumite.', null],
        ['teorija', 'Japoniški terminai', 'Stovėsenų, blokų ir smūgių pavadinimai.', null],
    ],
    4 => [
        ['kihon', 'Kiba-dachi', 'Raitelio stovėsena.', null],
        ['kihon', 'Yoko-geri keage ir kekomi', 'Šoniniai spyriai.', null],
        ['kata', 'Heian Sandan', null, '@l3'],
        ['kumite', 'Kihon ippon kumite', 'Vieno žingsnio kumite.', '@l4'],
    ],
];
foreach ($program as $belt => $items) {
    foreach ($items as $i => [$section, $title, $body, $lesson]) {
        $out[] = 'INSERT INTO belt_requirements (belt_level, section, title, body, lesson_id, sort_order, created_at) VALUES ('
            . "$belt, " . s($section) . ', ' . s($title) . ', ' . s($body) . ', ' . ($lesson ?? 'NULL') . ', ' . ($i + 1) . ", '" . SENTINEL . "');";
    }
}
// Dalis narių jau pažymėjo, ką moka
$out[] = 'INSERT IGNORE INTO requirement_progress (member_id, requirement_id) SELECT m.id, r.id FROM members m '
    . "JOIN belt_requirements r ON r.belt_level = COALESCE(m.belt_level, 1) + 1 AND r.created_at = '" . SENTINEL . "' "
    . "WHERE m.id BETWEEN @m1 AND @m$memberVar AND MOD(m.id * 7 + r.id * 3, 10) < 6;";

$quizzes = [
    // [pavadinimas, kam, diržas, aprašymas, klausimų teste, vaikams, [[klausimas, [atsakymai - pirmas teisingas], paaiškinimas]]]
    ['Geltonas diržas (8 kyu)', 'karateka', 2, 'Pasitikrink, ar žinai, ko reikia geltonam diržui.', 8, 1, [
        ['Ką darome įeidami į dojo?', ['Nusilenkiame', 'Bėgame į salės vidurį', 'Garsiai pasisveikiname su draugais', 'Nieko ypatingo'], 'Įeidami ir išeidami iš dojo visada nusilenkiame.'],
        ['Kaip japoniškai vadinamas treneris?', ['Sensei', 'Senpai', 'Obi', 'Dojo'], 'Senpai - vyresnis mokinys, obi - diržas, dojo - salė.'],
        ['Kaip japoniškai „vienas“?', ['Ichi', 'Ni', 'San', 'Go'], null],
        ['Kaip japoniškai „du“?', ['Ni', 'Ichi', 'Shi', 'Roku'], null],
        ['Kas yra kata?', ['Judesių seka su įsivaizduojamu priešininku', 'Diržo spalva', 'Karatė apranga', 'Šūksnis'], null],
        ['Kas yra obi?', ['Diržas', 'Treneris', 'Spyris', 'Salė'], null],
        ['Kokios spalvos diržą gauna pradedantysis?', ['Baltą', 'Geltoną', 'Juodą', 'Žalią'], null],
        ['Kas yra kiai?', ['Šūksnis, padedantis sutelkti jėgą', 'Blokas', 'Pasisveikinimas', 'Stovėsena'], null],
        ['Kuri kata yra pirmoji?', ['Heian Shodan', 'Heian Nidan', 'Bassai Dai', 'Heian Sandan'], null],
        ['Ką reiškia komanda „Yame“?', ['Sustoti', 'Pradėti', 'Nusilenkti', 'Atsisėsti'], null],
    ]],
    ['Oranžinis diržas (7 kyu)', 'karateka', 3, 'Teorija oranžiniam diržui: technikos ir terminai.', 8, 0, [
        ['Oi-zuki - tai...', ['Smūgis ranka žengiant į priekį', 'Smūgis priešinga ranka', 'Spyris į priekį', 'Blokas aukštyn'], null],
        ['Gyaku-zuki - tai...', ['Smūgis priešinga ranka nei priekinė koja', 'Smūgis žengiant į priekį', 'Šoninis spyris', 'Žemutinis blokas'], null],
        ['Age-uke - tai...', ['Blokas aukštyn', 'Žemutinis blokas', 'Blokas iš išorės', 'Spyris atgal'], null],
        ['Gedan-barai - tai...', ['Žemutinis blokas', 'Blokas aukštyn', 'Smūgis į galvą', 'Spyris į priekį'], null],
        ['Mae-geri - tai...', ['Spyris į priekį', 'Šoninis spyris', 'Spyris lanku', 'Smūgis alkūne'], null],
        ['Kokia tai stovėsena: zenkutsu-dachi?', ['Priekinė stovėsena', 'Raitelio stovėsena', 'Užpakalinė stovėsena', 'Natūrali stovėsena'], 'Zenkutsu-dachi - daugiau svorio ant priekinės kojos.'],
        ['Kokia tai stovėsena: kokutsu-dachi?', ['Užpakalinė stovėsena', 'Priekinė stovėsena', 'Raitelio stovėsena', 'Kryžminė stovėsena'], null],
        ['Ką reiškia komanda „Hajime“?', ['Pradėti', 'Sustoti', 'Nusilenkti', 'Pakeisti pusę'], null],
        ['Kaip japoniškai „penki“?', ['Go', 'Roku', 'Shi', 'Hachi'], null],
        ['Kuri kata reikalinga oranžiniam diržui?', ['Heian Nidan', 'Heian Shodan', 'Bassai Dai', 'Kanku Dai'], null],
    ]],
    ['Teisėjo testas: kumite taisyklės', 'teisejas', null, 'Pagrindinės WKF kumite taisyklės ir komandos.', 6, 0, [
        ['Kiek taškų skiriama už yuko?', ['1', '2', '3', '0,5'], null],
        ['Kiek taškų skiriama už ippon?', ['3', '2', '1', '5'], null],
        ['Už ką skiriamas waza-ari (2 taškai)?', ['Spyris į korpusą (chudan)', 'Smūgis ranka į galvą', 'Spyris į galvą (jodan)', 'Blokas'], 'Spyris į galvą ir pargriovimas su baigiamuoju smūgiu - ippon.'],
        ['Kokia komanda pradeda kovą?', ['Shobu hajime', 'Yame', 'Aka no kachi', 'Torimasen'], null],
        ['Ką reiškia „Aka no kachi“?', ['Laimėjo raudonasis (aka)', 'Laimėjo mėlynasis (ao)', 'Lygiosios', 'Įspėjimas raudonajam'], null],
        ['Ką reiškia senshu?', ['Pirmojo taško pranašumas', 'Įspėjimas', 'Diskvalifikacija', 'Papildomas laikas'], null],
        ['Kokia taškų persvara baigia kovą anksčiau laiko?', ['8 taškai', '5 taškai', '6 taškai', '10 taškų'], null],
    ]],
    ['Egzaminatoriaus testas', 'egzaminatorius', null, 'Klubo egzaminų tvarka.', 5, 0, [
        ['Kiek procentų reikia surinkti teoriniame teste, kad mokinys galėtų laikyti praktiką?', ['83 %', '50 %', '70 %', '100 %'], null],
        ['Kokia diržų tvarka nuo pradedančiojo?', ['Baltas → geltonas → oranžinis → žalias', 'Baltas → oranžinis → geltonas → žalias', 'Geltonas → baltas → žalias → mėlynas', 'Baltas → žalias → geltonas → mėlynas'], null],
        ['Kas vertinama kihon dalyje?', ['Pagrindinė technika: stovėsenos, smūgiai, blokai, spyriai', 'Tik kata', 'Tik kumite', 'Fizinis pasiruošimas'], null],
        ['Kas yra kumite?', ['Kova su partneriu', 'Judesių seka vienam', 'Teorijos testas', 'Pasisveikinimas'], null],
        ['Kada žymimas naujas diržas sistemoje?', ['Įvedus egzamino rezultatus ir pažymėjus diržo pakėlimą', 'Narys pasižymi pats', 'Automatiškai po testo', 'Kartą per metus'], null],
    ]],
];
foreach ($quizzes as $qi => [$title, $aud, $belt, $desc, $count, $kids, $questions]) {
    $qv = '@qz' . ($qi + 1);
    $out[] = 'INSERT INTO quizzes (title, audience, belt_level, description, question_count, pass_percent, kids_mode, is_published, created_at) VALUES ('
        . s($title) . ', ' . s($aud) . ', ' . ($belt ?? 'NULL') . ', ' . s($desc) . ", $count, 83, $kids, 1, '" . SENTINEL . "');";
    $out[] = "SET $qv = LAST_INSERT_ID();";
    foreach ($questions as $i => [$body, $answers, $expl]) {
        $out[] = 'INSERT INTO quiz_questions (quiz_id, body, explanation, sort_order) VALUES (' . "$qv, " . s($body) . ', ' . s($expl) . ', ' . ($i + 1) . ');';
        $out[] = 'SET @qq = LAST_INSERT_ID();';
        // Atsakymų tvarka sumaišoma, kad teisingas nebūtų visada pirmas
        $order = array_keys($answers);
        shuffle($order);
        foreach ($order as $pos => $k) {
            $out[] = 'INSERT INTO quiz_answers (question_id, body, is_correct, sort_order) VALUES (@qq, ' . s($answers[$k]) . ', ' . ($k === 0 ? 1 : 0) . ', ' . ($pos + 1) . ');';
        }
    }
}
// Bandymai: dalis narių jau laikė savo kito diržo testą (~85 % atsakymų teisingi)
$out[] = 'INSERT INTO quiz_attempts (quiz_id, member_id, account_id, question_ids, score, total, passed, started_at, finished_at) '
    . "SELECT q.id, m.id, (SELECT am.account_id FROM account_members am WHERE am.member_id = m.id ORDER BY am.relation = 'self' DESC LIMIT 1), "
    . '(SELECT GROUP_CONCAT(qq.id ORDER BY qq.sort_order) FROM quiz_questions qq WHERE qq.quiz_id = q.id), 0, (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id), 0, '
    . 'NOW() - INTERVAL MOD(m.id, 20) DAY - INTERVAL 10 MINUTE, NOW() - INTERVAL MOD(m.id, 20) DAY FROM quizzes q '
    . "JOIN members m ON COALESCE(m.belt_level, 1) + 1 = q.belt_level AND m.status = 'active' AND m.id BETWEEN @m1 AND @m$memberVar "
    . "WHERE q.created_at = '" . SENTINEL . "' AND MOD(m.id, 3) <> 0;";
$out[] = 'INSERT INTO quiz_attempt_answers (attempt_id, question_id, answer_id, is_correct) '
    . 'SELECT a.id, qq.id, IF(MOD(a.member_id * 31 + qq.id * 17, 100) < 85, '
    . '(SELECT MIN(x.id) FROM quiz_answers x WHERE x.question_id = qq.id AND x.is_correct = 1), (SELECT MIN(x.id) FROM quiz_answers x WHERE x.question_id = qq.id AND x.is_correct = 0)), '
    . 'IF(MOD(a.member_id * 31 + qq.id * 17, 100) < 85, 1, 0) '
    . "FROM quiz_attempts a JOIN quizzes q ON q.id = a.quiz_id AND q.created_at = '" . SENTINEL . "' JOIN quiz_questions qq ON qq.quiz_id = a.quiz_id;";
$out[] = 'UPDATE quiz_attempts a JOIN quizzes q ON q.id = a.quiz_id '
    . 'SET a.score = (SELECT COALESCE(SUM(x.is_correct), 0) FROM quiz_attempt_answers x WHERE x.attempt_id = a.id) '
    . "WHERE q.created_at = '" . SENTINEL . "';";
$out[] = "UPDATE quiz_attempts a JOIN quizzes q ON q.id = a.quiz_id SET a.passed = IF(100 * a.score >= q.pass_percent * a.total, 1, 0) WHERE q.created_at = '" . SENTINEL . "';";
// Teisėjų testą jau išlaikė du teisėjai
$out[] = 'INSERT INTO quiz_attempts (quiz_id, member_id, question_ids, score, total, passed, started_at, finished_at) '
    . "SELECT @qz3, t.member_id, '', 6, 6, 1, NOW() - INTERVAL 40 DAY, NOW() - INTERVAL 40 DAY FROM member_tags t "
    . "WHERE t.tag = 'teisejas' AND t.member_id BETWEEN @m1 AND @m$memberVar ORDER BY t.member_id LIMIT 2;";

echo implode("\n", $out) . "\n";
fwrite(STDERR, sprintf("Paskyros: %d, nariai: %d\n", $accountVar, $memberVar));
