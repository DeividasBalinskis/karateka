<?php
// Testiniai (pavyzdiniai) duomenys ir jų ištrynimas vienu mygtuku (Nustatymai → Atsarginės kopijos ir sistema).
// Kas laikoma testiniu:
//   - paskyros @demo.karateka.lt (demo) ir @karateka.test (kompiuterio bandomosios), išskyrus adminus;
//   - nariai, kurie priklauso tik tokioms paskyroms;
//   - demo renginiai, pamokos, aplankai, egzaminų programa, testai (created_at = 2000-01-01), naujienos (updated_at = 2000-01-01);
//   - viskas, kas prasideda „TEST:“, ir pamokos / naujienos su senu pavyzdiniu video (ne apie karate).
// Tikri duomenys - pačių užsiregistravę, Denio pridėti nariai, jo naujienos ir pamokos - lieka.

const DEMO_SENTINEL = '2000-01-01 00:00:00';
const DEMO_VIDEO = 'dQw4w9WgXcQ';
const DEMO_TEXT = '[Pavyzdys - pakeiskite]';   // senas pavyzdinis instruktoriaus aprašymas pagrindiniame puslapyje
const DEMO_ACCOUNT_SQL = "((a.email LIKE '%@demo.karateka.lt' OR a.email LIKE '%@karateka.test') AND a.role <> 'admin')";
const DEMO_LESSON_SQL = "l.created_at = '2000-01-01 00:00:00' OR l.title LIKE 'TEST:%' OR l.videos LIKE '%dQw4w9WgXcQ%'
                         OR EXISTS (SELECT 1 FROM lesson_blocks db WHERE db.lesson_id = l.id AND db.youtube_id = 'dQw4w9WgXcQ')";

const TEST_DATA_PARTS = [
    'people'  => 'Testinės paskyros ir nariai',
    'events'  => 'Renginiai',
    'news'    => 'Naujienos',
    'lessons' => 'Pamokos ir aplankai',
    'program' => 'Egzaminų programos punktai',
    'quizzes' => 'Testai (klausimynai)',
    'other'   => 'Kiti „TEST:“ pavyzdžiai',
];

/** Testinis narys: priklauso tik testinėms paskyroms (arba be paskyros, bet sukurtas kaip pavyzdys). Parametrai: [$meId, $meId] */
function test_data_member_sql(): string
{
    $link = 'SELECT 1 FROM account_members am JOIN accounts a ON a.id = am.account_id WHERE am.member_id = m.id AND ';
    return '((EXISTS (' . $link . DEMO_ACCOUNT_SQL . ' AND a.id <> ?) OR m.created_at = "' . DEMO_SENTINEL . '")
             AND NOT EXISTS (' . $link . '(NOT ' . DEMO_ACCOUNT_SQL . ' OR a.id = ?)))';
}

/** Kas būtų ištrinta: [dalis => ['count' => n, 'items' => [tekstas, ...], 'ids' => [...], 'warn' => ?string]] (tik netuščios dalys) */
function test_data_scan(int $meId): array
{
    $out = [];
    $accounts = q_all('SELECT a.id, a.email, a.first_name, a.last_name FROM accounts a WHERE ' . DEMO_ACCOUNT_SQL . ' AND a.id <> ? ORDER BY a.last_name, a.first_name', [$meId]);
    $members = q_all('SELECT m.id, m.first_name, m.last_name, m.status FROM members m WHERE ' . test_data_member_sql() . ' ORDER BY m.last_name, m.first_name', [$meId, $meId]);
    if ($accounts || $members) {
        $items = [];
        foreach ($members as $m) {
            $items[] = 'Narys: ' . $m['first_name'] . ' ' . $m['last_name'] . ($m['status'] === 'pending' ? ' (laukė patvirtinimo)' : '');
        }
        foreach ($accounts as $a) {
            $items[] = 'Paskyra: ' . $a['first_name'] . ' ' . $a['last_name'] . ' · ' . $a['email'];
        }
        $out['people'] = ['count' => count($members) + count($accounts), 'items' => $items,
            'ids' => ['accounts' => array_map('intval', array_column($accounts, 'id')), 'members' => array_map('intval', array_column($members, 'id'))],
            'label' => count($members) . ' nar. ir ' . count($accounts) . ' pask.'];
    }

    $events = q_all("SELECT id, title, starts_on FROM events WHERE created_at = ? OR title LIKE 'TEST:%' ORDER BY starts_on", [DEMO_SENTINEL]);
    if ($events) {
        $out['events'] = ['ids' => array_map('intval', array_column($events, 'id')),
            'items' => array_map(function ($e) { return $e['title'] . ' · ' . fmt_date($e['starts_on']); }, $events)];
    }

    $news = q_all("SELECT id, title FROM news n WHERE n.updated_at = ? OR n.title LIKE 'TEST:%'
                    OR EXISTS (SELECT 1 FROM news_media nm WHERE nm.news_id = n.id AND nm.youtube_id = ?) ORDER BY n.id", [DEMO_SENTINEL, DEMO_VIDEO]);
    if ($news) {
        $out['news'] = ['ids' => array_map('intval', array_column($news, 'id')), 'items' => array_column($news, 'title')];
    }

    $lessons = q_all('SELECT l.id, l.title FROM lessons l WHERE ' . DEMO_LESSON_SQL . ' ORDER BY l.belt_level, l.title');
    // Demo aplankai (ne diržų - tie lieka): jų turinys, jei tikras, perkeliamas aukštyn
    $folders = q_all('SELECT id, title FROM lesson_folders WHERE created_at = ? AND NOT (parent_id IS NULL AND belt_level IS NOT NULL) ORDER BY id', [DEMO_SENTINEL]);
    if ($lessons || $folders) {
        $out['lessons'] = ['ids' => ['lessons' => array_map('intval', array_column($lessons, 'id')), 'folders' => array_map('intval', array_column($folders, 'id'))],
            'items' => array_merge(array_column($lessons, 'title'), array_map(function ($f) { return 'Aplankas: ' . $f['title']; }, $folders))];
    }

    $req = q_all('SELECT id, belt_level, title FROM belt_requirements WHERE created_at = ? ORDER BY belt_level, sort_order', [DEMO_SENTINEL]);
    if ($req) {
        $ids = array_map('intval', array_column($req, 'id'));
        $realProgress = (int) q_value('SELECT COUNT(DISTINCT rp.member_id) FROM requirement_progress rp WHERE rp.requirement_id IN (' . implode(',', $ids) . ')
                                        AND rp.member_id NOT IN (' . (($m = array_column($members, 'id')) ? implode(',', array_map('intval', $m)) : '0') . ')');
        $out['program'] = ['ids' => $ids,
            'items' => array_map(function ($r) { return (BELTS[(int) $r['belt_level']][0] ?? $r['belt_level']) . ': ' . $r['title']; }, $req),
            'warn' => $realProgress ? 'Šiuos punktus jau pažymėjo ' . $realProgress . ' tikr. nar. - jų pažymėjimai irgi dings. Jei Denis programą jau naudoja, nuimkite varnelę.' : null];
    }

    $quizzes = q_all('SELECT id, title FROM quizzes WHERE created_at = ? ORDER BY id', [DEMO_SENTINEL]);
    if ($quizzes) {
        $out['quizzes'] = ['ids' => array_map('intval', array_column($quizzes, 'id')), 'items' => array_column($quizzes, 'title')];
    }

    $other = [];
    try {
        foreach (q_all("SELECT id, reason FROM training_cancellations WHERE reason LIKE 'TEST:%'") as $r) {
            $other['cancellations'][] = (int) $r['id'];
            $other['items'][] = 'Atšaukta treniruotė: ' . $r['reason'];
        }
    } catch (PDOException $ex) {
        // dar neimportuotas db/023
    }
    foreach (q_all("SELECT id, title FROM broadcasts WHERE title LIKE 'TEST:%'") as $r) {
        $other['broadcasts'][] = (int) $r['id'];
        $other['items'][] = 'Pranešimas nariams: ' . $r['title'];
    }
    foreach (q_all("SELECT id, body FROM member_messages WHERE body LIKE 'TEST:%'") as $r) {
        $other['messages'][] = (int) $r['id'];
        $other['items'][] = 'Žinutė: ' . mb_strimwidth($r['body'], 0, 80, '…');
    }
    if ($n = (int) q_value("SELECT COUNT(*) FROM notifications WHERE title LIKE 'TEST:%'")) {
        $other['items'][] = 'Pranešimai „TEST:“ paskyrose: ' . $n;
    }
    $videos = (int) q_value('SELECT COUNT(*) FROM coach_notes WHERE youtube_id = ?', [DEMO_VIDEO])
            + (int) q_value('SELECT COUNT(*) FROM belt_requirements WHERE youtube_id = ? AND created_at <> ?', [DEMO_VIDEO, DEMO_SENTINEL]);
    if ($videos) {
        $other['videos'] = true;
        $other['items'][] = 'Pavyzdinis video (ne apie karate) pašalinamas iš trenerio pastabų ir programos: ' . $videos;
    }
    if (strpos((string) site_setting('home_page', ''), DEMO_TEXT) !== false) {
        $other['home'] = true;
        $other['items'][] = 'Pagrindinis puslapis: pavyzdinis instruktoriaus aprašymas „' . DEMO_TEXT . ' …“ (bus tuščias)';
    }
    if (!empty($other['items'])) {
        $out['other'] = ['ids' => $other, 'items' => $other['items']];
    }

    foreach ($out as $k => &$part) {
        $part['count'] = $part['count'] ?? count($part['items']);
    }
    unset($part);
    return $out;
}

/** Tikros paskyros ir nariai, kurie lieka (kad būtų matyti, jog niekas tikras nedingsta) */
function test_data_kept(int $meId): array
{
    $members = q_all('SELECT m.first_name, m.last_name FROM members m WHERE NOT (' . test_data_member_sql() . ') ORDER BY m.first_name, m.last_name', [$meId, $meId]);
    $accounts = q_all('SELECT a.first_name, a.last_name, a.role FROM accounts a WHERE NOT ' . DEMO_ACCOUNT_SQL . ' OR a.id = ? ORDER BY a.first_name, a.last_name', [$meId]);
    return [
        'members' => array_map(function ($m) { return trim($m['first_name'] . ' ' . $m['last_name']); }, $members),
        'accounts' => array_map(function ($a) { return trim($a['first_name'] . ' ' . $a['last_name']) . ($a['role'] !== 'member' ? ' (' . ($a['role'] === 'admin' ? 'adminas' : 'treneris') . ')' : ''); }, $accounts),
    ];
}

/** Ištrina pažymėtas dalis. Grąžina [dalis => kiek ištrinta]. Viskas - vienoje transakcijoje. */
function test_data_delete(int $meId, array $parts): array
{
    $scan = test_data_scan($meId);
    $done = [];
    $in = function (array $ids): string {
        return implode(',', array_map('intval', $ids)) ?: '0';
    };
    db()->beginTransaction();
    try {
        foreach (array_intersect(array_keys(TEST_DATA_PARTS), $parts) as $part) {
            if (empty($scan[$part])) {
                continue;
            }
            $ids = $scan[$part]['ids'];
            switch ($part) {
                case 'people':
                    // Nariai pirma (jų lankomumas, taškai, žinutės ištrinami kartu), tada paskyros
                    if ($ids['members']) {
                        q('DELETE FROM members WHERE id IN (' . $in($ids['members']) . ')');
                    }
                    if ($ids['accounts']) {
                        q('DELETE FROM accounts WHERE id IN (' . $in($ids['accounts']) . ')');
                    }
                    break;
                case 'events':
                    q('DELETE FROM member_messages WHERE event_id IN (' . $in($ids) . ')');
                    test_data_drop_links('(renginys|renginio-dalyviai|renginio-rezultatai)\\.php\\?id=', $ids);
                    q('DELETE FROM events WHERE id IN (' . $in($ids) . ')');
                    break;
                case 'news':
                    q('DELETE FROM member_messages WHERE news_id IN (' . $in($ids) . ')');
                    test_data_drop_links('naujienos\\.php\\?id=', $ids);
                    q('DELETE FROM news WHERE id IN (' . $in($ids) . ')');
                    break;
                case 'lessons':
                    if ($ids['lessons']) {
                        test_data_drop_links('pamokos\\.php\\?id=', $ids['lessons']);
                        q('DELETE FROM lessons WHERE id IN (' . $in($ids['lessons']) . ')');
                    }
                    foreach ($ids['folders'] as $fid) {
                        $parent = q_value('SELECT parent_id FROM lesson_folders WHERE id = ?', [$fid]);
                        q('UPDATE lessons SET folder_id = ? WHERE folder_id = ?', [$parent, $fid]);
                        q('UPDATE lesson_folders SET parent_id = ? WHERE parent_id = ?', [$parent, $fid]);
                        q('DELETE FROM lesson_folders WHERE id = ?', [$fid]);
                    }
                    break;
                case 'program':
                    q('DELETE FROM belt_requirements WHERE id IN (' . $in($ids) . ')');
                    break;
                case 'quizzes':
                    q('DELETE FROM quizzes WHERE id IN (' . $in($ids) . ')');
                    break;
                case 'other':
                    if (!empty($ids['cancellations'])) {
                        q('DELETE FROM training_cancellations WHERE id IN (' . $in($ids['cancellations']) . ')');
                    }
                    if (!empty($ids['broadcasts'])) {
                        q('DELETE FROM broadcasts WHERE id IN (' . $in($ids['broadcasts']) . ')');
                    }
                    if (!empty($ids['messages'])) {
                        q('DELETE FROM member_messages WHERE id IN (' . $in($ids['messages']) . ')');
                    }
                    q("DELETE FROM notifications WHERE title LIKE 'TEST:%' OR body LIKE 'TEST:%'");
                    if (!empty($ids['videos'])) {
                        q('UPDATE coach_notes SET youtube_id = NULL WHERE youtube_id = ?', [DEMO_VIDEO]);
                        q('UPDATE belt_requirements SET youtube_id = NULL WHERE youtube_id = ?', [DEMO_VIDEO]);
                    }
                    if (!empty($ids['home'])) {
                        $page = json_decode((string) site_setting('home_page', ''), true);
                        if (is_array($page)) {
                            array_walk_recursive($page, function (&$v) {
                                if (is_string($v) && strpos($v, DEMO_TEXT) === 0) {
                                    $v = '';
                                }
                            });
                            set_site_setting('home_page', json_encode($page, JSON_UNESCAPED_UNICODE));
                        }
                    }
                    break;
            }
            $done[$part] = $scan[$part]['count'];
        }
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }
    return $done;
}

/** Pranešimai, vedantys į ištrintą renginį / naujieną / pamoką (pvz. „renginys.php?id=5“) */
function test_data_drop_links(string $pattern, array $ids): void
{
    if ($ids) {
        q('DELETE FROM notifications WHERE link REGEXP ?', [$pattern . '(' . implode('|', array_map('intval', $ids)) . ')([^0-9]|$)']);
    }
}
