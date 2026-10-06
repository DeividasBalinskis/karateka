<?php
// TIK LOKALIAI: užpildo DB pavyzdiniais duomenimis peržiūrai.
//   docker compose exec web php app/cli/dev_seed.php
// Visų testinių paskyrų slaptažodis: testas123
if (PHP_SAPI !== 'cli') {
    exit;
}
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require dirname(__DIR__) . '/bootstrap.php';
require APP_DIR . '/images.php';

if (config('env') !== 'local') {
    exit("Seed veikia tik lokaliai (config env = local).\n");
}
if (q_value('SELECT COUNT(*) FROM accounts')) {
    exit("DB jau turi paskyrų. Išvalykite: docker compose down -v && docker compose up -d\n");
}

const PW = 'testas123';

function acc(string $email, string $first, string $last, string $role = 'member', string $status = 'active'): int
{
    $id = create_account($email, PW, $first, $last, '+370 600 00000', $status);
    q('UPDATE accounts SET role = ?, email_verified_at = NOW(), approved_at = IF(? = "active", NOW(), NULL) WHERE id = ?', [$role, $status, $id]);
    return $id;
}

function mem(string $first, string $last, string $birth, ?int $group, string $status = 'active', bool $photo = true): int
{
    $id = create_member($first, $last, $birth, $photo);
    q('UPDATE members SET group_id = ?, status = ?, parent_consent_at = NOW() WHERE id = ?', [$group, $status, $id]);
    return $id;
}

// Treneris (administratorius)
$coach = acc('treneris@karateka.test', 'Denis', 'Balinskis', 'admin');
link_member($coach, mem('Denis', 'Balinskis', '1985-04-12', 13), 'self');

// Tėvai su dviem vaikais
$parent = acc('tevai@karateka.test', 'Rasa', 'Petrauskienė');
$kid1 = mem('Jonas', 'Petrauskas', '2017-06-03', 1);
$kid2 = mem('Austėja', 'Petrauskaitė', '2011-09-15', 12);
link_member($parent, $kid1, 'parent');
link_member($parent, $kid2, 'parent');

// Jaunuolis, prisijungiantis pats
$teen = acc('jaunuolis@karateka.test', 'Austėja', 'Petrauskaitė');
link_member($teen, $kid2, 'self');

// Suaugęs narys
$adult = acc('narys@karateka.test', 'Tomas', 'Kazlauskas');
link_member($adult, mem('Tomas', 'Kazlauskas', '1992-02-28', 13), 'self');

// Laukia patvirtinimo
$new = acc('naujas@karateka.test', 'Inga', 'Jankauskienė', 'member', 'pending_approval');
link_member($new, mem('Matas', 'Jankauskas', '2018-11-30', null, 'pending', false), 'parent');
link_member($new, mem('Ugnė', 'Jankauskaitė', '2015-03-08', null, 'pending'), 'parent');

// Renginiai
$events = [
    ['exam', 'Kyu egzaminas', '+12 days', null, '10:00', 'Sporto centras Viršuliškės', 'Egzaminas vaikams ir jaunimui. Atsineškite diržą ir kimono.', [1, 2, 12]],
    ['competition', 'Lietuvos karate taurė', '+30 days', '+31 days', null, 'Kaunas, Žalgirio arena', null, []],
    ['seminar', 'Seminaras su svečiu iš Japonijos', '+45 days', null, '11:00', 'Vilnius', 'Kata ir kumite seminaras. Registracija pas trenerį.', [12, 13]],
];
foreach ($events as [$type, $title, $start, $end, $time, $loc, $desc, $groups]) {
    q('INSERT INTO events (type, title, starts_on, ends_on, start_time, location, description) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$type, $title, date('Y-m-d', strtotime($start)), $end ? date('Y-m-d', strtotime($end)) : null, $time, $loc, $desc]);
    $eid = (int) db()->lastInsertId();
    foreach ($groups as $g) {
        q('INSERT INTO event_groups (event_id, group_id) VALUES (?, ?)', [$eid, $g]);
    }
}

// Naujienos su esamomis svetainės nuotraukomis
$news = [
    ['Rudens sezonas prasidėjo!', "Sveiki sugrįžę į salę! Treniruotės vyksta pagal įprastą tvarkaraštį.\n\nNaujokams pirmos dvi treniruotės nemokamos - kvieskite draugus.", '-10 days', ['Page2ApieSlide1.jpg', 'Page2ApieSlide2.jpg', 'Page2ApieSlide3.jpg'], []],
    ['Puikūs rezultatai varžybose', "Mūsų sportininkai parvežė 3 aukso ir 2 sidabro medalius. Sveikiname!\n\nVaizdo įrašas iš varžybų žemiau.", '-3 days', ['Page2SuaugusiemsSectionBg.jpg'], ['dQw4w9WgXcQ']],
];
foreach ($news as [$title, $body, $when, $images, $videos]) {
    q('INSERT INTO news (title, body, author_id, published_at) VALUES (?, ?, ?, ?)', [$title, $body, $coach, date('Y-m-d H:i:s', strtotime($when))]);
    $nid = (int) db()->lastInsertId();
    $sort = 0;
    foreach ($images as $img) {
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        copy(PUBLIC_DIR . '/' . $img, $tmp);
        $name = save_uploaded_image(['error' => UPLOAD_ERR_OK, 'size' => filesize($tmp), 'tmp_name' => $tmp, 'name' => $img], PUBLIC_DIR . '/uploads/news');
        q('INSERT INTO news_media (news_id, type, file, sort_order) VALUES (?, "image", ?, ?)', [$nid, $name, ++$sort]);
    }
    foreach ($videos as $yt) {
        q('INSERT INTO news_media (news_id, type, youtube_id, sort_order) VALUES (?, "youtube", ?, ?)', [$nid, $yt, ++$sort]);
    }
    foreach ([[$parent, '👍'], [$adult, '🔥'], [$teen, '🥋']] as [$aid, $emoji]) {
        q('INSERT INTO reactions (news_id, account_id, emoji) VALUES (?, ?, ?)', [$nid, $aid, $emoji]);
    }
}

echo "Paruošta. Paskyros (slaptažodis " . PW . "):\n"
    . "  treneris@karateka.test   - administratorius\n"
    . "  tevai@karateka.test      - tėvai su 2 vaikais\n"
    . "  jaunuolis@karateka.test  - jaunuolis\n"
    . "  narys@karateka.test      - suaugęs narys\n"
    . "  naujas@karateka.test     - laukia patvirtinimo\n";
