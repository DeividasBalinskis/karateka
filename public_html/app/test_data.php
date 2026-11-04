<?php
// Seni pavyzdiniai (demo) duomenys - išvalomi VIENĄ KARTĄ automatiškai, kai adminas atidaro „Šiandien“ ar „Nustatymai“
// (prieš tai - atsarginė kopija). Kas išvalyta - Nustatymai → Testavimas. Nauji testiniai nariai - app/test_people.php.
// Kas laikoma senu pavyzdžiu:
//   - paskyros @demo.karateka.lt (demo) ir @karateka.test (kompiuterio bandomosios), išskyrus adminus;
//   - nariai, kurie priklauso tik tokioms paskyroms;
//   - demo renginiai, pamokos, aplankai, egzaminų programa, testai (created_at = 2000-01-01), naujienos (updated_at = 2000-01-01);
//   - viskas, kas prasideda „TEST:“, ir pamokos / naujienos su senu pavyzdiniu video (ne apie karate);
//   - demo paskyrų reakcijos, komentarai ir kt. (ištrinamos kartu su paskyromis).
// Tikri duomenys - pačių užsiregistravę, Denio pridėti nariai, jo naujienos, renginiai ir pamokos (ir tai, ką jis pakeitė) - lieka.
// Egzaminų programa ir testai paliekami, jei tikri nariai juos jau naudojo.
// Seni kompiuterio pavyzdžiai (app/cli/dev_seed.php iki 2026-11-03) dar nebuvo pažymėti - jie atpažįstami pagal tikslius
// pavadinimus ir vietas (old_seed_scan), bet tik ten, kur yra tų pavyzdžių vaikai (tikslūs vardai ir gimimo datos), t.y. tik kompiuteryje.

const DEMO_SENTINEL = '2000-01-01 00:00:00';
const DEMO_VIDEO = 'dQw4w9WgXcQ';
const DEMO_TEXT = '[Pavyzdys - pakeiskite]';   // senas pavyzdinis instruktoriaus aprašymas pagrindiniame puslapyje
const DEMO_ACCOUNT_SQL = "((a.email LIKE '%@demo.karateka.lt' OR a.email LIKE '%@karateka.test') AND a.role <> 'admin')";
const DEMO_LESSON_SQL = "l.created_at = '2000-01-01 00:00:00' OR l.title LIKE 'TEST:%' OR l.videos LIKE '%dQw4w9WgXcQ%'
                         OR EXISTS (SELECT 1 FROM lesson_blocks db WHERE db.lesson_id = l.id AND db.youtube_id = 'dQw4w9WgXcQ')";

// Seni kompiuterio pavyzdžiai (be žymės): vaikai [vardas, pavardė, gimimo data], renginiai [tipas, pavadinimas, vieta],
// naujienos ir pamokos [pavadinimas, teksto pradžia]
const OLD_SEED_MEMBERS = [['Lukas', 'Vaitkus', '2009-01-10'], ['Gabija', 'Stankevičiūtė', '2010-02-11'], ['Matas', 'Žukauskas', '2011-03-12'],
    ['Emilija', 'Paulauskaitė', '2012-04-13'], ['Dovydas', 'Urbonas', '2009-05-14'], ['Kamilė', 'Rimkutė', '2010-06-15']];
const OLD_SEED_EVENTS = [['exam', 'Kyu egzaminas', 'Sporto centras Viršuliškės'], ['competition', 'Lietuvos karate taurė', 'Kaunas, Žalgirio arena'],
    ['seminar', 'Seminaras su svečiu iš Japonijos', 'Vilnius'], ['competition', 'Rudenėlio taurė', 'Vilnius'], ['competition', 'Sakura Cup', 'Ryga'],
    ['exam', 'Pavasario kyu egzaminas', 'Viršuliškės'], ['camp', 'Rudens atostogų stovykla', 'Trakai, poilsio namai „Ežerėlis“'],
    ['seminar', 'Heian kata seminaras', 'Sporto centras Viršuliškės'], ['other', 'Atvira treniruotė parke', 'Vingio parkas']];
const OLD_SEED_NEWS = [['Rudens sezonas prasidėjo!', 'Sveiki sugrįžę į salę!'], ['Puikūs rezultatai varžybose', 'Mūsų sportininkai parvežė 3 aukso ir 2 sidabro']];
const OLD_SEED_LESSONS = [['Tempimo pratimai vaikams', '1. Atsisėskite, kojos tiesios.'], ['Heian Shodan žingsnis po žingsnio', 'Kartokite kasdien po 10 minučių.']];

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
    return '((EXISTS (' . $link . DEMO_ACCOUNT_SQL . (' ' . 'AND a.id' . ' <> ?) OR m.created_at = "') . DEMO_SENTINEL . '")
             AND NOT EXISTS (' . $link . ('(NOT' . ' ') . DEMO_ACCOUNT_SQL . ' OR a.id = ?)))';
}

/**
 * Seni kompiuterio pavyzdžiai be žymės: ['members' => [id...], 'events' => [...], 'news' => [...], 'lessons' => [...]].
 * Tušti, jei nėra bent 3 tų pavyzdžių vaikų (test ir tikroje svetainėje jų nėra).
 */
function old_seed_ids(): array
{
    $ids = ['members' => [], 'events' => [], 'news' => [], 'lessons' => []];
    $pick = function (string $sql, array $rows, int $cols) {
        $where = implode(' OR ', array_fill(0, count($rows), $sql));
        $params = [];
        foreach ($rows as $r) {
            array_push($params, ...array_slice($r, 0, $cols));
        }
        return [$where, $params];
    };
    [$w, $p] = $pick('(first_name = ? AND last_name = ? AND birth_date = ?)', OLD_SEED_MEMBERS, 3);
    $ids['members'] = array_map('intval', q('SELECT id FROM members WHERE created_at <> ? AND (' . $w . ')', array_merge([DEMO_SENTINEL], $p))->fetchAll(PDO::FETCH_COLUMN));
    if (count($ids['members']) < 3) {
        return ['members' => [], 'events' => [], 'news' => [], 'lessons' => []];
    }
    [$w, $p] = $pick('(type = ? AND title = ? AND location = ?)', OLD_SEED_EVENTS, 3);
    $ids['events'] = array_map('intval', q('SELECT id FROM events WHERE created_at <> ? AND (' . $w . ')', array_merge([DEMO_SENTINEL], $p))->fetchAll(PDO::FETCH_COLUMN));
    $like = function (array $rows) {
        return array_map(function ($r) { return [$r[0], str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $r[1]) . '%']; }, $rows);
    };
    [$w, $p] = $pick('(title = ? AND body LIKE ?)', $like(OLD_SEED_NEWS), 2);
    $ids['news'] = array_map('intval', q('SELECT id FROM news WHERE (updated_at IS NULL OR updated_at <> ?) AND (' . $w . ')', array_merge([DEMO_SENTINEL], $p))->fetchAll(PDO::FETCH_COLUMN));
    [$w, $p] = $pick('(title = ? AND body LIKE ?)', $like(OLD_SEED_LESSONS), 2);
    $ids['lessons'] = array_map('intval', q('SELECT id FROM lessons WHERE created_at <> ? AND (' . $w . ')', array_merge([DEMO_SENTINEL], $p))->fetchAll(PDO::FETCH_COLUMN));
    return $ids;
}

/** Tik seni kompiuterio pavyzdžiai - ta pačia forma kaip test_data_scan() */
function old_seed_scan(): array
{
    $old = old_seed_ids();
    $out = [];
    $list = function (string $sql, array $ids) {
        return $ids ? q_all($sql . ' WHERE id IN (' . implode(',', $ids) . ') ORDER BY id') : [];
    };
    if ($old['members']) {
        $out['people'] = ['ids' => ['accounts' => [], 'members' => $old['members']],
            'items' => array_map(function ($m) { return (t('Narys:') . ' ') . $m['first_name'] . ' ' . $m['last_name']; },
                $list('SELECT first_name, last_name FROM members', $old['members']))];
    }
    if ($old['events']) {
        $out['events'] = ['ids' => $old['events'], 'items' => array_map(function ($e) { return $e['title'] . ' · ' . fmt_date($e['starts_on']); },
            $list('SELECT title, starts_on FROM events', $old['events']))];
    }
    if ($old['news']) {
        $out['news'] = ['ids' => $old['news'], 'items' => array_column($list('SELECT title FROM news', $old['news']), 'title')];
    }
    if ($old['lessons']) {
        $out['lessons'] = ['ids' => ['lessons' => $old['lessons'], 'folders' => []], 'items' => array_column($list('SELECT title FROM lessons', $old['lessons']), 'title')];
    }
    foreach ($out as $k => &$part) {
        $part['count'] = count($part['items']);
    }
    unset($part);
    return $out;
}

/** Kas būtų ištrinta: [dalis => ['count' => n, 'items' => [tekstas, ...], 'ids' => [...], 'warn' => ?string]] (tik netuščios dalys) */
function test_data_scan(int $meId): array
{
    $out = [];
    $old = old_seed_ids();
    $orIn = function (string $col, array $ids): string {
        return $ids ? ' OR ' . $col . ' IN (' . implode(',', $ids) . ')' : '';
    };
    $accounts = q_all('SELECT a.id, a.email, a.first_name, a.last_name FROM accounts a WHERE ' . DEMO_ACCOUNT_SQL . ' AND a.id <> ? ORDER BY a.last_name, a.first_name', [$meId]);
    $members = q_all('SELECT m.id, m.first_name, m.last_name, m.status FROM members m WHERE ' . test_data_member_sql() . $orIn('m.id', $old['members'])
                     . ' ORDER BY m.last_name, m.first_name', [$meId, $meId]);
    if ($accounts || $members) {
        $items = [];
        foreach ($members as $m) {
            $items[] = (t('Narys:') . ' ') . $m['first_name'] . ' ' . $m['last_name'] . ($m['status'] === 'pending' ? (' ' . t('(laukė patvirtinimo)')) : '');
        }
        foreach ($accounts as $a) {
            $items[] = (t('Paskyra:') . ' ') . $a['first_name'] . ' ' . $a['last_name'] . ' · ' . $a['email'];
        }
        $out['people'] = ['count' => count($members) + count($accounts), 'items' => $items,
            'ids' => ['accounts' => array_map('intval', array_column($accounts, 'id')), 'members' => array_map('intval', array_column($members, 'id'))],
            'label' => count($members) . (' ' . t('nar. ir') . ' ') . count($accounts) . ' pask.'];
    }

    $events = q_all("SELECT id, title, starts_on FROM events WHERE created_at = ? OR title LIKE 'TEST:%'" . $orIn('id', $old['events']) . ' ORDER BY starts_on', [DEMO_SENTINEL]);
    if ($events) {
        $out['events'] = ['ids' => array_map('intval', array_column($events, 'id')),
            'items' => array_map(function ($e) { return $e['title'] . ' · ' . fmt_date($e['starts_on']); }, $events)];
    }

    $news = q_all("SELECT id, title FROM news n WHERE n.updated_at = ? OR n.title LIKE 'TEST:%'
                    OR EXISTS (SELECT 1 FROM news_media nm WHERE nm.news_id = n.id AND nm.youtube_id = ?)" . $orIn('n.id', $old['news']) . ' ORDER BY n.id', [DEMO_SENTINEL, DEMO_VIDEO]);
    if ($news) {
        $out['news'] = ['ids' => array_map('intval', array_column($news, 'id')), 'items' => array_column($news, 'title')];
    }

    $lessons = q_all('SELECT l.id, l.title FROM lessons l WHERE ' . DEMO_LESSON_SQL . $orIn('l.id', $old['lessons']) . ' ORDER BY l.belt_level, l.title');
    // Demo aplankai (ne diržų - tie lieka): jų turinys, jei tikras, perkeliamas aukštyn
    $folders = q_all('SELECT id, title FROM lesson_folders WHERE created_at = ? AND NOT (parent_id IS NULL AND belt_level IS NOT NULL) ORDER BY id', [DEMO_SENTINEL]);
    if ($lessons || $folders) {
        $out['lessons'] = ['ids' => ['lessons' => array_map('intval', array_column($lessons, 'id')), 'folders' => array_map('intval', array_column($folders, 'id'))],
            'items' => array_merge(array_column($lessons, 'title'), array_map(function ($f) { return (t('Aplankas:') . ' ') . $f['title']; }, $folders))];
    }

    $req = q_all('SELECT id, belt_level, title FROM belt_requirements WHERE created_at = ? ORDER BY belt_level, sort_order', [DEMO_SENTINEL]);
    if ($req) {
        $ids = array_map('intval', array_column($req, 'id'));
        $realProgress = (int) q_value('SELECT COUNT(DISTINCT rp.member_id) FROM requirement_progress rp WHERE rp.requirement_id IN (' . implode(',', $ids) . ')
                                        AND rp.member_id NOT IN (' . (($m = array_column($members, 'id')) ? implode(',', array_map('intval', $m)) : '0') . ')');
        $out['program'] = ['ids' => $ids,
            'items' => array_map(function ($r) { return (BELTS[(int) $r['belt_level']][0] ?? $r['belt_level']) . ': ' . $r['title']; }, $req),
            'keep' => $realProgress > 0];
    }

    $quizzes = q_all('SELECT id, title FROM quizzes WHERE created_at = ? ORDER BY id', [DEMO_SENTINEL]);
    if ($quizzes) {
        $ids = array_map('intval', array_column($quizzes, 'id'));
        $realAttempts = (int) q_value('SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id IN (' . implode(',', $ids) . ')
                                        AND member_id NOT IN (' . (($m = array_column($members, 'id')) ? implode(',', array_map('intval', $m)) : '0') . ')');
        $out['quizzes'] = ['ids' => $ids, 'items' => array_column($quizzes, 'title'), 'keep' => $realAttempts > 0];
    }

    $other = [];
    try {
        foreach (q_all("SELECT id, reason FROM training_cancellations WHERE reason LIKE 'TEST:%'") as $r) {
            $other['cancellations'][] = (int) $r['id'];
            $other['items'][] = (t('Atšaukta treniruotė:') . ' ') . $r['reason'];
        }
    } catch (PDOException $ex) {
        // dar neimportuotas db/023
    }
    foreach (q_all("SELECT id, title FROM broadcasts WHERE title LIKE 'TEST:%'") as $r) {
        $other['broadcasts'][] = (int) $r['id'];
        $other['items'][] = (t('Pranešimas nariams:') . ' ') . $r['title'];
    }
    foreach (q_all("SELECT id, body FROM member_messages WHERE body LIKE 'TEST:%'") as $r) {
        $other['messages'][] = (int) $r['id'];
        $other['items'][] = (t('Žinutė:') . ' ') . mb_strimwidth($r['body'], 0, 80, '…');
    }
    if ($n = (int) q_value("SELECT COUNT(*) FROM notifications WHERE title LIKE 'TEST:%'")) {
        $other['items'][] = (t('Pranešimai „TEST:“ paskyrose:') . ' ') . $n;
    }
    $videos = (int) q_value('SELECT COUNT(*) FROM coach_notes WHERE youtube_id = ?', [DEMO_VIDEO])
            + (int) q_value('SELECT COUNT(*) FROM belt_requirements WHERE youtube_id = ? AND created_at <> ?', [DEMO_VIDEO, DEMO_SENTINEL]);
    if ($videos) {
        $other['videos'] = true;
        $other['items'][] = (t('Pavyzdinis video (ne apie karate) pašalinamas iš trenerio pastabų ir programos:') . ' ') . $videos;
    }
    if (strpos((string) site_setting('home_page', ''), DEMO_TEXT) !== false) {
        $other['home'] = true;
        $other['items'][] = t('Pagrindinis puslapis: pavyzdinis instruktoriaus aprašymas „') . DEMO_TEXT . (' ' . t('…“ (bus pakeistas Denio aprašymu)'));
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

/** Ištrina pažymėtas dalis ($scan - kas rasta; numatyta - test_data_scan). Grąžina [dalis => kiek ištrinta]. Viskas - vienoje transakcijoje. */
function test_data_delete(int $meId, array $parts, ?array $scan = null): array
{
    $scan = $scan ?? test_data_scan($meId);
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
                    q("DELETE FROM site_settings WHERE name = 'reactions'");   // reakcijų pasirinkimo nebėra - visi emoji
                    if (!empty($ids['videos'])) {
                        q('UPDATE coach_notes SET youtube_id = NULL WHERE youtube_id = ?', [DEMO_VIDEO]);
                        q('UPDATE belt_requirements SET youtube_id = NULL WHERE youtube_id = ?', [DEMO_VIDEO]);
                    }
                    if (!empty($ids['home'])) {
                        $page = json_decode((string) site_setting('home_page', ''), true);
                        if (is_array($page)) {
                            array_walk_recursive($page, function (&$v) {
                                if (is_string($v) && strpos($v, DEMO_TEXT) === 0) {
                                    $v = HOME_DENIS_BIO;   // pavyzdinis tekstas -> Denio aprašymas
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

/**
 * Vieną kartą: seni pavyzdžiai išvalomi automatiškai (tik adminui, ne peržiūros režime). Prieš tai - atsarginė kopija;
 * jei kopija nepavyksta - niekas netrinama. Rezultatas įrašomas į site_settings 'demo_isvalyta' (JSON) ir rodomas Testavimo puslapyje.
 * Grąžina trumpą pranešimą (arba null - nieko nedaryta).
 */
function legacy_demo_autopurge(array $me): ?string
{
    if (!is_admin($me) || !empty($_SESSION['view_admin'])) {
        return null;
    }
    if ($prev = site_setting('demo_isvalyta')) {
        return old_seed_autopurge($me, (string) $prev);
    }
    // Nebeliko pranešimų apie kiekvieną komentarą - seni pažymimi atliktais
    try {
        q('UPDATE notifications SET done_at = NOW(), read_at = COALESCE(read_at, NOW()) WHERE type = "comment" AND done_at IS NULL');
    } catch (PDOException $ex) {
        // dar neimportuotas db/021
    }
    $scan = test_data_scan((int) $me['id']);
    $parts = array_keys(array_filter($scan, function ($p) { return empty($p['keep']); }));
    if (!$parts) {
        set_site_setting('demo_isvalyta', json_encode(['date' => date('Y-m-d H:i'), 'parts' => [], 'kept' => array_keys($scan)], JSON_UNESCAPED_UNICODE));
        return null;
    }
    try {
        $backup = backup_create();
    } catch (Throwable $ex) {
        error_log('demo purge: backup failed: ' . $ex->getMessage());
        return t('Senų pavyzdžių neišvalėme: nepavyko atsarginė kopija (') . $ex->getMessage() . ').';
    }
    $done = test_data_delete((int) $me['id'], $parts);
    $summary = ['date' => date('Y-m-d H:i'), 'backup' => $backup, 'parts' => [], 'kept' => array_keys(array_filter($scan, function ($p) { return !empty($p['keep']); }))];
    foreach ($done as $k => $n) {
        $summary['parts'][$k] = ['count' => $n, 'items' => array_slice($scan[$k]['items'], 0, 300)];
    }
    set_site_setting('demo_isvalyta', json_encode($summary, JSON_UNESCAPED_UNICODE));
    return t('Išvalyti seni pavyzdiniai duomenys (') . array_sum($done) . t('). Sąrašas - Nustatymai → Testavimas.');
}

/**
 * Jei po išvalymo liko senų kompiuterio pavyzdžių be žymės (tik kompiuteryje) - išvalomi ir jie, irgi su atsargine kopija.
 * Kas išvalyta, pridedama prie 'demo_isvalyta' sąrašo.
 */
function old_seed_autopurge(array $me, string $prev): ?string
{
    $scan = old_seed_scan();
    if (!$scan) {
        return null;
    }
    try {
        $backup = backup_create();
    } catch (Throwable $ex) {
        error_log('old seed purge: backup failed: ' . $ex->getMessage());
        return null;
    }
    $done = test_data_delete((int) $me['id'], array_keys($scan), $scan);
    $summary = json_decode($prev, true);
    if (!is_array($summary)) {
        $summary = ['parts' => [], 'kept' => []];
    }
    $summary['date'] = date('Y-m-d H:i');
    $summary['backup'] = $backup;
    foreach ($done as $k => $n) {
        $summary['parts'][$k]['count'] = (int) ($summary['parts'][$k]['count'] ?? 0) + $n;
        $summary['parts'][$k]['items'] = array_merge($summary['parts'][$k]['items'] ?? [], $scan[$k]['items']);
    }
    set_site_setting('demo_isvalyta', json_encode($summary, JSON_UNESCAPED_UNICODE));
    return t('Išvalyti seni pavyzdiniai duomenys (') . array_sum($done) . t('). Sąrašas - Nustatymai → Testavimas.');
}
