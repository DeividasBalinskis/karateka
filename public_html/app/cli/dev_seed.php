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

// Brolio (vyr. trenerio) administratoriaus paskyra
$coach = acc('info@karateka.lt', 'Denis', 'Balinskis', 'admin');
link_member($coach, mem('Denis', 'Balinskis', '1985-04-12', 13), 'self');

// Davido testinė paskyra: mato viską kaip tėvai (slaptažodis "admin1" - tik lokaliai)
$admin1 = create_account('admin1@karateka.test', 'admin1', 'Admin', 'Testas', null, 'active');
q('UPDATE accounts SET email_verified_at = NOW(), approved_at = NOW() WHERE id = ?', [$admin1]);

// Tėvai su dviem vaikais
$parent = acc('tevai@karateka.test', 'Rasa', 'Petrauskienė');
$kid1 = mem('Jonas', 'Petrauskas', '2017-06-03', 1);
$kid2 = mem('Austėja', 'Petrauskaitė', '2011-09-15', 12);
link_member($parent, $kid1, 'parent');
link_member($parent, $kid2, 'parent');
link_member($admin1, $kid1, 'parent');
link_member($admin1, $kid2, 'parent');

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

// Praėję renginiai su rezultatais ir taškais (2 etapas)
foreach ([['Rudenėlio taurė', 'Vilnius', 0], ['Sakura Cup', 'Ryga', 1]] as $i => [$title, $loc, $abroad]) {
    q('INSERT INTO events (type, title, starts_on, location, is_abroad) VALUES ("competition", ?, ?, ?, ?)',
        [$title, date('Y-m-d', strtotime('-' . (20 + $i * 7) . ' days')), $loc, $abroad]);
    $eid = (int) db()->lastInsertId();
    q('INSERT INTO event_groups (event_id, group_id) VALUES (?, 12), (?, 13)', [$eid, $eid]);
}
q('INSERT INTO events (type, title, starts_on, location) VALUES ("exam", "Pavasario kyu egzaminas", ?, "Viršuliškės")', [date('Y-m-d', strtotime('-30 days'))]);
$examId = (int) db()->lastInsertId();

// Daugiau jaunimo narių, kad reitingas turėtų ką rodyti
$youth = [$kid2];
foreach ([['Lukas', 'Vaitkus'], ['Gabija', 'Stankevičiūtė'], ['Matas', 'Žukauskas'], ['Emilija', 'Paulauskaitė'], ['Dovydas', 'Urbonas'], ['Kamilė', 'Rimkutė']] as $i => [$f, $l]) {
    $youth[] = mem($f, $l, (2009 + $i % 4) . '-0' . (1 + $i) . '-1' . $i, 12);
}
$comp = q_all('SELECT * FROM events WHERE type = "competition" AND starts_on < CURDATE() ORDER BY starts_on');
$results = [
    [$youth[0] => '2', $youth[1] => '1', $youth[2] => 'part', $youth[3] => '3', $youth[4] => 'part', $youth[5] => 'part'],
    [$youth[0] => 'part', $youth[1] => '3', $youth[3] => 'part', $youth[6] => '1'],
];
foreach ($comp as $i => $ev) {
    save_event_results($ev, $results[$i], $coach);
}
save_event_results(q_one('SELECT * FROM events WHERE id = ?', [$examId]), array_fill_keys(array_merge($youth, [$kid1]), 'yes'), $coach);
award_points($youth[2], category_by_code('lead_training'), date('Y-m-d', strtotime('-5 days')), null, 'Vedė vaikų treniruotę', $coach);

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

// Pamokos
q('INSERT INTO lessons (title, topic, body, videos, author_id) VALUES (?, ?, ?, ?, ?)', ['Heian Shodan žingsnis po žingsnio', 'Kata', "Kartokite kasdien po 10 minučių.", 'dQw4w9WgXcQ', $coach]);
q('INSERT INTO lessons (title, topic, body, author_id) VALUES (?, ?, ?, ?)', ['Tempimo pratimai vaikams', 'Fizinis pasiruošimas', "1. Atsisėskite, kojos tiesios.
2. Lėtai lenkitės į priekį.", $coach]);
q('INSERT INTO lesson_groups (lesson_id, group_id) VALUES (?, 1)', [db()->lastInsertId()]);

echo "Paruošta. Paskyros (slaptažodis " . PW . "):\n"
    . "  admin1@karateka.test     - testinė tėvų paskyra (slaptažodis admin1)\n"
    . "  info@karateka.lt         - administratorius (brolis)\n"
    . "  tevai@karateka.test      - tėvai su 2 vaikais\n"
    . "  jaunuolis@karateka.test  - jaunuolis\n"
    . "  narys@karateka.test      - suaugęs narys\n"
    . "  naujas@karateka.test     - laukia patvirtinimo\n";
