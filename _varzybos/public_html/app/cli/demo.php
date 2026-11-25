<?php
// Demo duomenys lokaliai peržiūrai: php app/cli/demo.php
// IŠTRINA visus esamus duomenis. Veikia tik kai config.php env = local.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Tik komandinei eilutei.\n");
}
require dirname(__DIR__) . '/bootstrap.php';

if (config('env') !== 'local' && !in_array('--force', $argv, true)) {
    exit("Demo duomenys keliami tik lokaliai (env = local).\n");
}
mt_srand(2026);
srand(2026);

foreach (['performances', 'matches', 'entry_members', 'entries', 'categories', 'tournaments', 'athletes', 'clubs', 'users', 'login_attempts'] as $tbl) {
    q("DELETE FROM $tbl");
    q("ALTER TABLE $tbl AUTO_INCREMENT = 1");
}

create_user('admin@varzybos.test', 'testas123', 'Organizatorius', 'admin');
create_user('sekretorius@varzybos.test', 'testas123', 'Tatami sekretorius', 'secretary');

// ---------- Klubai ----------
$clubDefs = [
    ['Karate Ateitis', 'Vilnius', 'karateka', 'karate-ateitis'],
    ['Shiai Kaunas', 'Kaunas', null, null],
    ['Bushido Klaipėda', 'Klaipėda', null, null],
    ['Tora Šiauliai', 'Šiauliai', null, null],
    ['Kimon Panevėžys', 'Panevėžys', null, null],
    ['Sakura Alytus', 'Alytus', null, null],
];
$clubs = [];
foreach ($clubDefs as [$name, $city, $src, $ext]) {
    $clubs[] = insert('clubs', ['name' => $name, 'city' => $city, 'external_source' => $src, 'external_id' => $ext,
        'contact_name' => 'Treneris', 'contact_email' => 'treneris@' . strtolower(preg_replace('/[^a-z]/i', '', iconv('UTF-8', 'ASCII//TRANSLIT', $city))) . '.test']);
}

// ---------- Sportininkai ----------
$boys = ['Lukas', 'Matas', 'Jokūbas', 'Dominykas', 'Nojus', 'Kajus', 'Benas', 'Herkus', 'Joris', 'Pijus', 'Adomas', 'Rokas', 'Augustas', 'Tadas', 'Mantas', 'Domantas', 'Karolis', 'Justas', 'Paulius', 'Gytis', 'Simonas', 'Vytautas', 'Martynas', 'Ignas'];
$girls = ['Emilija', 'Gabija', 'Austėja', 'Urtė', 'Kamilė', 'Liepa', 'Ugnė', 'Lėja', 'Saulė', 'Ieva', 'Goda', 'Rugilė', 'Miglė', 'Gintarė', 'Agnė', 'Viktorija', 'Rūta', 'Eglė', 'Indrė', 'Monika'];
$lastM = ['Kazlauskas', 'Jankauskas', 'Petrauskas', 'Stankevičius', 'Vasiliauskas', 'Žukauskas', 'Butkus', 'Paulauskas', 'Urbonas', 'Kavaliauskas', 'Navickas', 'Ramanauskas', 'Savickas', 'Rimkus', 'Sakalauskas', 'Baranauskas', 'Mockus', 'Šimkus', 'Lukoševičius', 'Gudaitis'];
$lastF = fn (string $m) => preg_replace(['/ius$/', '/as$/', '/is$/', '/us$/'], ['iūtė', 'aitė', 'ytė', 'utė'], $m);

$tYear = (int) date('Y');
$used = [];
$karatekaId = 100;
/** Sukuria sportininką: amžius metais (pagal gimimo metus), diržo intervalas, svoris */
$mk = function (string $g, int $age, array $beltRange, ?float $weight = null, ?int $club = null) use (&$used, $boys, $girls, $lastM, $lastF, $tYear, $clubs, &$karatekaId): int {
    do {
        $first = $g === 'M' ? $boys[array_rand($boys)] : $girls[array_rand($girls)];
        $lm = $lastM[array_rand($lastM)];
        $last = $g === 'M' ? $lm : $lastF($lm);
    } while (isset($used["$first $last"]));
    $used["$first $last"] = true;
    $club = $club ?? $clubs[array_rand($clubs)];
    $birth = sprintf('%d-%02d-%02d', $tYear - $age, mt_rand(1, 12), mt_rand(1, 28));
    $row = ['first_name' => $first, 'last_name' => $last, 'birth_date' => $birth, 'gender' => $g,
        'belt_level' => mt_rand($beltRange[0], $beltRange[1]), 'weight_kg' => $weight, 'club_id' => $club];
    if ($club === $clubs[0]) {
        $row['external_source'] = 'karateka';
        $row['external_id'] = (string) $karatekaId++;
    }
    return insert('athletes', $row);
};

// ---------- 1 varžybos: vyksta šiandien ----------
$t1 = create_tournament([
    'title' => 'Rudens taurė 2026', 'starts_on' => date('Y-m-d'), 'location' => 'Vilnius, sporto centras',
    'description' => 'Demo varžybos: dalis kategorijų jau baigtos, dalis vyksta.',
    'status' => 'running', 'tatami_count' => 3, 'day_start' => '09:00', 'age_basis' => 'year',
]);
$cat = fn (array $in) => category_or_404(create_category($t1, $in));

$c1 = $cat(['discipline' => 'kata', 'gender' => 'F', 'age_min' => 8, 'age_max' => 9, 'format' => 'bracket', 'match_type' => 'flags', 'judges' => 5, 'third_place' => 'two_bronze']);
$c2 = $cat(['discipline' => 'kata', 'gender' => 'M', 'age_min' => 10, 'age_max' => 11, 'format' => 'scores', 'judges' => 5, 'score_rounds' => '6']);
$c3 = $cat(['discipline' => 'kumite', 'gender' => 'M', 'age_min' => 12, 'age_max' => 13, 'format' => 'bracket', 'match_type' => 'kumite', 'kumite_rule' => 'nihon', 'match_seconds' => 90, 'third_place' => 'two_bronze']);
$c4 = $cat(['discipline' => 'kumite', 'gender' => 'M', 'age_min' => 18, 'age_max' => 34, 'format' => 'bracket', 'match_type' => 'kumite', 'kumite_rule' => 'ippon', 'match_seconds' => 120, 'third_place' => 'repechage']);
$c5 = $cat(['discipline' => 'kumite', 'gender' => 'F', 'age_min' => 18, 'format' => 'round_robin', 'match_type' => 'kumite', 'kumite_rule' => 'ippon', 'match_seconds' => 120]);
$c6 = $cat(['discipline' => 'kata', 'gender' => 'F', 'age_min' => 16, 'format' => 'scores', 'judges' => 5, 'score_rounds' => '6']);
$c7 = $cat(['discipline' => 'enbu', 'gender' => 'X', 'age_min' => 14, 'format' => 'scores', 'judges' => 5, 'score_rounds' => '']);
$c8 = $cat(['discipline' => 'fukugo', 'gender' => 'M', 'age_min' => 16, 'format' => 'bracket', 'match_type' => 'fukugo', 'kumite_rule' => 'ippon', 'match_seconds' => 120, 'third_place' => 'match']);
$c9 = $cat(['discipline' => 'kata_team', 'gender' => 'X', 'age_min' => 12, 'age_max' => 17, 'format' => 'scores', 'judges' => 5, 'score_rounds' => '']);

$enter = function (array $c, array $athleteIds) {
    foreach ($athleteIds as $aid) {
        $a = q_one('SELECT * FROM athletes WHERE id = ?', [$aid]);
        add_entry($c, $aid, (int) $a['club_id'], $a['external_source'] ? 'api' : (mt_rand(0, 1) ? 'admin' : 'public'), false);
    }
};
$many = fn (int $n, callable $f) => array_map($f, range(1, $n));

$girls89 = $many(7, fn () => $mk('F', mt_rand(8, 9), [1, 4]));
$enter($c1, $girls89);
$boys1011 = $many(9, fn () => $mk('M', mt_rand(10, 11), [2, 6]));
$enter($c2, $boys1011);
$boys1213 = $many(8, fn () => $mk('M', mt_rand(12, 13), [3, 7], (float) mt_rand(40, 58)));
$enter($c3, $boys1213);
$men = $many(11, fn () => $mk('M', mt_rand(19, 33), [6, 12], (float) mt_rand(62, 90)));
$enter($c4, $men);
$women = $many(4, fn () => $mk('F', mt_rand(19, 40), [6, 11], (float) mt_rand(52, 70)));
$enter($c5, $women);
$womenKata = array_merge($women, $many(6, fn () => $mk('F', mt_rand(16, 30), [5, 11])));
$enter($c6, $womenKata);
$enter($c8, array_slice($men, 0, 6));
// Seed'ai: du stipriausi vyrai
q('UPDATE entries SET seed = 1 WHERE category_id = ? AND athlete_id = ?', [$c4['id'], $men[0]]);
q('UPDATE entries SET seed = 2 WHERE category_id = ? AND athlete_id = ?', [$c4['id'], $men[1]]);

// En-bu poros
for ($i = 0; $i < 5; $i++) {
    $club = $clubs[$i % count($clubs)];
    $a = $mk('M', mt_rand(16, 30), [5, 10], null, $club);
    $b = $mk(mt_rand(0, 1) ? 'F' : 'M', mt_rand(16, 30), [5, 10], null, $club);
    add_team_entry($c7, null, $club, [$a, $b]);
}
// Komandinė kata
foreach (array_slice($clubs, 0, 4) as $i => $club) {
    $ids = $many(3, fn () => $mk(mt_rand(0, 1) ? 'F' : 'M', mt_rand(12, 17), [3, 8], null, $club));
    $clubName = q_value('SELECT name FROM clubs WHERE id = ?', [$club]);
    add_team_entry($c9, $clubName . ($i === 3 ? ' II' : ''), $club, $ids);
}

auto_assign_tatami($t1);
foreach ([$c1, $c2, $c3, $c4, $c5, $c6, $c7, $c8, $c9] as $c) {
    draw_category((int) $c['id']);
}

// ---------- Rezultatų imitacija ----------
function demo_score(array $cat, string $winner): array
{
    $w = ['ippon' => 0, 'wazaari' => 0, 'penalties' => []];
    $l = ['ippon' => 0, 'wazaari' => 0, 'penalties' => []];
    switch ($cat['match_type']) {
        case 'flags':
            $j = (int) $cat['judges'];
            $wf = mt_rand(intdiv($j, 2) + 1, $j);
            $w['flags'] = $wf;
            $l['flags'] = $j - $wf;
            break;
        case 'team':
            $w['bouts'] = 2;
            $l['bouts'] = 1;
            break;
        default:
            $target = KUMITE_RULES[$cat['kumite_rule']]['target'];
            if (mt_rand(0, 1)) {
                $w['ippon'] = intdiv($target, 2);
            } else {
                $w['wazaari'] = $target;
            }
            if (mt_rand(0, 2) === 0) {
                $l['wazaari'] = 1;
            }
            if (mt_rand(0, 3) === 0) {
                $l['penalties'] = ['keikoku'];
            }
            if ($cat['match_type'] === 'fukugo') {
                $w['kata_flags'] = 3;
                $l['kata_flags'] = 2;
            }
    }
    return $winner === 'aka' ? ['aka' => $w, 'shiro' => $l] : ['aka' => $l, 'shiro' => $w];
}

function demo_play(array $cat, int $limit = 999, bool $leaveRunning = false): void
{
    $played = 0;
    while ($played < $limit) {
        $m = q_one('SELECT * FROM matches WHERE category_id = ? AND status = "ready" ORDER BY FIELD(stage, "pool", "main", "repechage", "bronze"), round, position LIMIT 1', [$cat['id']]);
        if (!$m) {
            break;
        }
        $rounds = category_rounds((int) $cat['id']);
        if ($m['stage'] === 'main' && (int) $m['round'] === $rounds && q_value('SELECT 1 FROM matches WHERE category_id = ? AND status = "ready" AND id <> ? LIMIT 1', [$cat['id'], $m['id']])) {
            // finalas - po repechage / kovos dėl 3 vietos
            $m = q_one('SELECT * FROM matches WHERE category_id = ? AND status = "ready" AND id <> ? ORDER BY stage, round, position LIMIT 1', [$cat['id'], $m['id']]);
        }
        $side = mt_rand(0, 2) ? 'aka' : 'shiro';
        $result = $cat['match_type'] === 'flags' ? 'flags' : (mt_rand(0, 6) ? 'points' : 'hantei');
        finish_match((int) $m['id'], $side, $result, demo_score($cat, $side));
        $played++;
    }
    if ($leaveRunning) {
        $m = q_one('SELECT * FROM matches WHERE category_id = ? AND status = "ready" ORDER BY stage, round, position LIMIT 1', [$cat['id']]);
        if ($m) {
            $sc = demo_score($cat, 'aka');
            $sc['aka']['ippon'] = 0;
            $sc['aka']['wazaari'] = 1;
            $sc['shiro'] = ['ippon' => 0, 'wazaari' => 0, 'penalties' => ['keikoku']];
            $sc['timer'] = ['duration' => (int) $cat['match_seconds'] * 1000, 'remaining' => 47000, 'running' => false];
            save_match_score((int) $m['id'], $sc);
        }
    }
}

function demo_scores_round(array $cat, int $limit = 999): void
{
    $s = score_state($cat);
    $rows = q_all('SELECT * FROM performances WHERE category_id = ? AND round = ? AND status = "waiting" ORDER BY order_no LIMIT ' . $limit, [$cat['id'], $s['current']]);
    $katas = ['Heian Shodan', 'Heian Nidan', 'Heian Sandan', 'Heian Yondan', 'Heian Godan', 'Tekki Shodan', 'Bassai Dai', 'Kanku Dai', 'Enpi', 'Jion', 'Hangetsu', 'Gankaku'];
    foreach ($rows as $p) {
        $base = 6.0 + mt_rand(0, 20) / 10;
        $scores = [];
        for ($j = 0; $j < (int) $cat['judges']; $j++) {
            $scores[] = number_format(max((float) $cat['score_min'], min((float) $cat['score_max'], $base + mt_rand(-3, 3) / 10)), 1, '.', '');
        }
        save_performance((int) $p['id'], ['scores' => $scores, 'kata' => $katas[array_rand($katas)]]);
    }
}

$c = fn ($c) => category_or_404((int) $c['id']);
demo_play($c($c1));                         // baigta
demo_scores_round($c($c2));
next_score_round((int) $c2['id']);
demo_scores_round($c($c2));                  // baigta (2 ratai)
demo_play($c($c3), 3, true);                 // vyksta, viena kova eina dabar
demo_play($c($c4), 5, true);                 // vyksta
demo_play($c($c5), 4);                       // grupė: 4 iš 6 kovų
demo_scores_round($c($c6));                  // atranka baigta
demo_scores_round($c($c7), 2);               // vyksta
demo_play($c($c8));                          // baigta su kova dėl 3 vietos

// ---------- 2 varžybos: registracija atvira ----------
$t2 = create_tournament([
    'title' => 'Žiemos taurė 2026', 'starts_on' => date('Y') . '-12-12', 'location' => 'Kaunas',
    'description' => "Registracija atvira klubams per viešą formą ir karateka.lt nariams per jų paskyrą.\nSvėrimas varžybų dieną 8:30–9:30.",
    'status' => 'registration', 'reg_deadline' => date('Y') . '-12-05', 'tatami_count' => 2, 'day_start' => '10:00',
    'public_registration' => 1,
]);
create_categories_from_template($t2, [
    'disciplines' => ['kata', 'kumite'],
    'ages' => [['min' => 8, 'max' => 9], ['min' => 10, 'max' => 11], ['min' => 12, 'max' => 13], ['min' => 14, 'max' => 15], ['min' => 16, 'max' => 17], ['min' => 18, 'max' => null]],
    'split_gender' => true,
    'third_place' => 'two_bronze',
]);
// Keli jau užsiregistravę
$t2row = tournament_or_404($t2);
foreach (array_slice(array_merge($boys1011, $girls89, $boys1213), 0, 14) as $aid) {
    $a = q_one('SELECT * FROM athletes WHERE id = ?', [$aid]);
    foreach (eligible_categories($t2row, $a) as $ec) {
        add_entry($ec, $aid, (int) $a['club_id'], $a['external_source'] ? 'api' : 'public');
    }
}
auto_assign_tatami($t2);

echo "Demo duomenys įkelti.\n";
echo "  admin@varzybos.test / testas123 (organizatorius)\n";
echo "  sekretorius@varzybos.test / testas123 (sekretorius)\n";
