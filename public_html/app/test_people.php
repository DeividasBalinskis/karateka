<?php
// Testiniai nariai: Test Parent (tėvų paskyra), Test Junior (paauglys su savo paskyra) ir Test Mažylis (vaikas be paskyros).
// Paskyros - @test.karateka.lt; adminas į jas persijungia viršuje („Peržiūra“), slaptažodžio nereikia.
// Testavimo puslapyje (Nustatymai → Testavimas) pažymima, ką sukurti: nauja registracija, kvietimai, komentarai, „Moku“...
// „Ištrinti testinius narius“ ištrina tik šias paskyras ir jų narius - su viskuo, ką jie sukūrė ar gavo. Tikri duomenys nekeičiami.

const TEST_DOMAIN = 'test.karateka.lt';
const TEST_PARENT_EMAIL = 'tevai@test.karateka.lt';
const TEST_JUNIOR_EMAIL = 'junior@test.karateka.lt';
const TEST_ACCOUNT_SQL = "a.email LIKE '%@test.karateka.lt'";

const TEST_SCENARIOS = [
    'pending'    => ['Nauja registracija', 'Laukia patvirtinimo: tėvų paskyra su vaiku - pasirodo „Patvirtinimai“ ir „Šiandien“.'],
    'invites'    => ['Kvietimai į renginius', 'Test Junior ir Mažylis pakviesti į artimiausius renginius - „Dalyvausiu / Negalėsiu“, terminas.'],
    'comments'   => ['Komentarai ir žinutės', 'Klausimas Deniui prie naujausios naujienos ir renginio, žinutė pokalbyje - atsakyti, paviešinti.'],
    'skills'     => ['„Moku“ prašymai', 'Junior pažymi kito diržo programos punktus „Moku“ - Denis tvirtina, skiriami XP (jei programa surašyta).'],
    'attendance' => ['Lankomumas ir taškai', 'Paskutinių 4 savaičių lankomumas ir taškai už seminarą - matosi paskyroje ir reitinge.'],
    'notes'      => ['Trenerio pastaba ir užduotis', 'Junior gauna pastabą ir užduotį su pamoka - „Svarbu“, „Atlikta“.'],
    'reaction'   => ['Reakcija į naujieną', 'Test Parent sureaguoja į naujausią naujieną.'],
    'consent'    => ['Atšauktas sutikimas dėl nuotraukų', 'Mažylio tėvai atšaukia sutikimą - pranešimas „Šiandien“.'],
];

function is_test_account(?array $a): bool
{
    return $a !== null && substr(strtolower((string) $a['email']), -strlen('@' . TEST_DOMAIN)) === '@' . TEST_DOMAIN;
}

/** Testinis narys - priklauso tik testinėms paskyroms */
function test_member_sql(): string
{
    $link = 'SELECT 1 FROM account_members am JOIN accounts a ON a.id = am.account_id WHERE am.member_id = m.id AND ';
    return '(EXISTS (' . $link . TEST_ACCOUNT_SQL . ') AND NOT EXISTS (' . $link . 'NOT ' . TEST_ACCOUNT_SQL . '))';
}

/** Grupė testiniam nariui pagal kategoriją ('vaikai', 'jaunimas'); jei tokios nėra - pirmoji aktyvi */
function test_group(string $category): ?int
{
    $id = q_value('SELECT id FROM training_groups WHERE is_active = 1 AND category = ? ORDER BY sort_order, id LIMIT 1', [$category])
        ?: q_value('SELECT id FROM training_groups WHERE is_active = 1 ORDER BY sort_order, id LIMIT 1');
    return $id ? (int) $id : null;
}

function test_account(string $email, string $first, string $last, string $status = 'active'): int
{
    $id = q_value('SELECT id FROM accounts WHERE email = ?', [$email]);
    if ($id) {
        return (int) $id;
    }
    $id = create_account($email, bin2hex(random_bytes(16)), $first, $last, null, $status);   // slaptažodis atsitiktinis - jungiamasi per „Peržiūra“
    q('UPDATE accounts SET email_verified_at = NOW(), approved_at = IF(status = "active", NOW(), NULL) WHERE id = ?', [$id]);
    return $id;
}

function test_member(string $first, string $last, int $age, string $category, int $belt, string $status = 'active'): int
{
    $id = create_member($first, $last, date('Y-m-d', strtotime("-$age years -40 days")), true, $belt);
    q('UPDATE members SET group_id = ?, status = ?, parent_consent_at = NOW() WHERE id = ?', [$status === 'active' ? test_group($category) : null, $status, $id]);
    return $id;
}

/**
 * Pagrindiniai testiniai žmonės (sukuriami, jei dar nėra): Test Parent su dviem vaikais - Test Junior (15 m., turi savo paskyrą)
 * ir Test Mažylis (8 m.). Grąžina ['parent' => paskyros id, 'junior' => paskyros id, 'member' => Junior nario id, 'kid' => Mažylio id].
 */
function test_people_base(): array
{
    $parent = test_account(TEST_PARENT_EMAIL, 'Test', 'Parent');
    $junior = test_account(TEST_JUNIOR_EMAIL, 'Test', 'Junior');
    $member = q_value('SELECT member_id FROM account_members WHERE account_id = ? AND relation = "self" LIMIT 1', [$junior]);
    if (!$member) {
        $member = test_member('Test', 'Junior', 15, 'jaunimas', 2);
        link_member($junior, (int) $member, 'self');
        link_member($parent, (int) $member, 'parent');
    }
    $kid = q_value('SELECT m.id FROM members m JOIN account_members am ON am.member_id = m.id WHERE am.account_id = ? AND m.first_name = "Test" AND m.last_name = "Mažylis" LIMIT 1', [$parent]);
    if (!$kid) {
        $kid = test_member('Test', 'Mažylis', 8, 'vaikai', 1);
        link_member($parent, (int) $kid, 'parent');
    }
    return ['parent' => $parent, 'junior' => $junior, 'member' => (int) $member, 'kid' => (int) $kid];
}

/** Sukuria testinius žmones ir pažymėtus scenarijus. Grąžina, kas padaryta (sakiniai). */
function test_people_generate(array $scenarios, array $admin): array
{
    $base = test_people_base();
    $done = ['Test Parent, Test Junior ir Test Mažylis'];
    $junior = q_one('SELECT * FROM members WHERE id = ?', [$base['member']]);
    $kid = q_one('SELECT * FROM members WHERE id = ?', [$base['kid']]);
    $parentAcc = q_one('SELECT * FROM accounts WHERE id = ?', [$base['parent']]);
    $juniorAcc = q_one('SELECT * FROM accounts WHERE id = ?', [$base['junior']]);
    $aid = (int) $admin['id'];

    foreach (array_intersect(array_keys(TEST_SCENARIOS), $scenarios) as $s) {
        switch ($s) {
            case 'pending':
                $n = (int) q_value('SELECT COUNT(*) FROM accounts WHERE email LIKE "naujas%@test.karateka.lt"') + 1;
                $acc = test_account('naujas' . $n . '@' . TEST_DOMAIN, 'Test', 'Naujokas ' . $n, 'pending_approval');
                link_member($acc, test_member('Test', 'Naujokėlis ' . $n, 9, 'vaikai', 1, 'pending'), 'parent');
                $done[] = t('nauja registracija (laukia patvirtinimo)');
                break;
            case 'invites':
                $events = q_all('SELECT * FROM events WHERE starts_on >= CURDATE() ORDER BY starts_on LIMIT 3');
                foreach ($events as $ev) {
                    invite_members($ev, [(int) $junior['id'], (int) $kid['id']], $aid, false);
                }
                $done[] = $events ? (t('kvietimai į') . ' ') . count($events) . ' renginius' : t('kvietimų nėra - nėra artėjančių renginių (sukurkite renginį)');
                break;
            case 'comments':
                add_member_message((int) $junior['id'], (int) $juniorAcc['id'], false, 'Testinis klausimas: ar šeštadienį treniruotė vyks?');
                if ($news = q_one('SELECT id, title FROM news WHERE is_published = 1 ORDER BY published_at DESC LIMIT 1')) {
                    add_member_message((int) $kid['id'], (int) $parentAcc['id'], false, 'Testinis klausimas prie naujienos: kur galima pamatyti daugiau nuotraukų?', null, null, null, (int) $news['id']);
                }
                if ($ev = q_one('SELECT id FROM events WHERE starts_on >= CURDATE() ORDER BY starts_on LIMIT 1')) {
                    add_member_message((int) $junior['id'], (int) $juniorAcc['id'], false, 'Testinis klausimas prie renginio: ką reikia pasiimti?', null, null, (int) $ev['id']);
                }
                $done[] = t('komentarai ir žinutės Deniui');
                break;
            case 'skills':
                $reqs = q_all('SELECT * FROM belt_requirements WHERE belt_level = ? ORDER BY sort_order LIMIT 2', [(int) $junior['belt_level'] + 1]);
                foreach ($reqs as $r) {
                    request_skill($junior, $r, $juniorAcc);
                }
                $done[] = $reqs ? (t('„Moku“ prašymai:') . ' ') . count($reqs) : t('„Moku“ - kitam diržui programa nesurašyta (Nustatymai → Egzaminų programa)');
                break;
            case 'attendance':
                foreach ([$junior, $kid] as $m) {
                    if (!$m['group_id']) {
                        continue;
                    }
                    $days = array_map('intval', q('SELECT weekday FROM schedule WHERE group_id = ?', [(int) $m['group_id']])->fetchAll(PDO::FETCH_COLUMN)) ?: [2, 4];
                    for ($i = 1; $i <= 28; $i++) {
                        $d = date('Y-m-d', strtotime("-$i days"));
                        if (in_array((int) date('N', strtotime($d)), $days, true)) {
                            q('INSERT IGNORE INTO attendance (member_id, training_date, group_id, present, marked_by) VALUES (?, ?, ?, ?, ?)',
                                [(int) $m['id'], $d, (int) $m['group_id'], $i % 5 === 0 ? 0 : 1, $aid]);
                        }
                    }
                }
                award_points((int) $junior['id'], category_by_code('seminar'), date('Y-m-d', strtotime('-3 days')), null, 'Testas: seminaras', $aid);
                $done[] = t('lankomumas (4 sav.) ir taškai');
                break;
            case 'notes':
                $lesson = q_value('SELECT id FROM lessons WHERE is_published = 1 AND belt_level = ? ORDER BY sort_order, id LIMIT 1', [(int) $junior['belt_level'] + 1]);
                send_coach_note($junior, $admin, 'Testinė pastaba: šiandien puikiai dirbai, toliau taip!', null, false, false);
                send_coach_note($junior, $admin, 'Testinė užduotis: iki kitos treniruotės pakartok katą 5 kartus.', $lesson ? (int) $lesson : null, true, false);
                $done[] = t('trenerio pastaba ir užduotis');
                break;
            case 'reaction':
                if ($news = q_value('SELECT id FROM news WHERE is_published = 1 ORDER BY published_at DESC LIMIT 1')) {
                    q('INSERT IGNORE INTO reactions (news_id, account_id, emoji) VALUES (?, ?, "👍")', [(int) $news, (int) $parentAcc['id']]);
                }
                $done[] = t('reakcija į naujieną');
                break;
            case 'consent':
                q('UPDATE members SET photo_consent = 0 WHERE id = ?', [(int) $kid['id']]);
                notify_admins('consent_withdrawn', 'Atšauktas sutikimas dėl nuotraukų: Test Mažylis',
                    'Atšaukė Test Parent. Naujose nuotraukose šio nario neskelbkite.', 'admin/nariai.php?id=' . (int) $kid['id'], (int) $kid['id']);
                $done[] = t('atšauktas sutikimas dėl nuotraukų');
                break;
        }
    }
    return $done;
}

/** Testiniai žmonės sąrašui: [['name' =>, 'email' =>, 'kids' => [...]], ...] */
function test_people_list(): array
{
    $out = [];
    foreach (q_all('SELECT a.* FROM accounts a WHERE ' . TEST_ACCOUNT_SQL . ' ORDER BY a.id') as $a) {
        $kids = q_all('SELECT m.first_name, m.last_name, m.belt_level, am.relation FROM members m JOIN account_members am ON am.member_id = m.id WHERE am.account_id = ? ORDER BY m.birth_date', [(int) $a['id']]);
        $out[] = ['name' => $a['first_name'] . ' ' . $a['last_name'], 'email' => $a['email'], 'status' => $a['status'],
            'kids' => array_map(function ($k) { return $k['first_name'] . ' ' . $k['last_name'] . ($k['relation'] === 'self' ? (' ' . t('(pats)')) : '') . ($k['belt_level'] ? ' · ' . BELTS[(int) $k['belt_level']][0] : ''); }, $kids)];
    }
    return $out;
}

/** Ištrina visas testines paskyras ir jų narius (su lankomumu, žinutėmis, kvietimais, reakcijomis ir t. t.). Grąžina [narių, paskyrų] skaičių. */
function test_people_delete(): array
{
    $members = array_map('intval', q('SELECT m.id FROM members m WHERE ' . test_member_sql())->fetchAll(PDO::FETCH_COLUMN));
    $accounts = array_map('intval', q('SELECT a.id FROM accounts a WHERE ' . TEST_ACCOUNT_SQL . ' AND a.role = "member"')->fetchAll(PDO::FETCH_COLUMN));
    db()->beginTransaction();
    try {
        if ($members) {
            q('DELETE FROM members WHERE id IN (' . implode(',', $members) . ')');
        }
        if ($accounts) {
            q('DELETE FROM accounts WHERE id IN (' . implode(',', $accounts) . ')');
        }
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }
    return [count($members), count($accounts)];
}

// ---------- Peržiūra: adminas persijungia į Test Parent / Test Junior ir atgal ----------

/** Tikras adminas, net kai šiuo metu peržiūrima testinė paskyra (null - ne adminas) */
function view_admin(): ?array
{
    $id = $_SESSION['view_admin'] ?? null;
    if (!$id) {
        $a = current_account();
        if (!$a || !is_admin($a)) {
            return null;
        }
        $id = $a['id'];
    }
    $a = q_one('SELECT * FROM accounts WHERE id = ? AND status = "active" AND role = "admin"', [(int) $id]);
    return $a ?: null;
}

/** Dabartinė peržiūra: 'admin', 'parent' arba 'junior' */
function view_mode(): string
{
    if (empty($_SESSION['view_admin'])) {
        return 'admin';
    }
    return strtolower((string) (current_account()['email'] ?? '')) === TEST_JUNIOR_EMAIL ? 'junior' : 'parent';
}

/** Persijungti: 'admin' - atgal į savo paskyrą, 'parent' / 'junior' - į testinę paskyrą (sukuriama, jei nėra) */
function view_switch(string $mode): void
{
    $admin = view_admin();
    if (!$admin) {
        forbidden();
    }
    session_regenerate_id(true);
    if ($mode === 'admin') {
        $_SESSION['account_id'] = (int) $admin['id'];
        unset($_SESSION['view_admin']);
        return;
    }
    $base = test_people_base();
    $_SESSION['view_admin'] = (int) $admin['id'];
    $_SESSION['account_id'] = $mode === 'junior' ? $base['junior'] : $base['parent'];
}

/** Viršutinėje juostoje adminui: „Peržiūra: Adminas / Tėvai / Vaikas“, vaiko diržas */
function render_view_switch(): string
{
    $admin = view_admin();
    if (!$admin) {
        return '';
    }
    $mode = view_mode();
    $belt = null;
    if ($mode !== 'admin') {
        $belt = q_value('SELECT m.belt_level FROM members m JOIN account_members am ON am.member_id = m.id JOIN accounts a ON a.id = am.account_id
                          WHERE a.email = ? AND am.relation = "self" LIMIT 1', [TEST_JUNIOR_EMAIL]);
    }
    $labels = ['admin' => t('Adminas'), 'parent' => t('Tėvai'), 'junior' => t('Vaikas')];
    $back = e($_SERVER['REQUEST_URI'] ?? '');
    $html = '<details class="view-switch' . ($mode !== 'admin' ? ' is-test' : '') . '"><summary title="' . e(t('Peržiūrėti svetainę kaip tėvai ar vaikas (testinės paskyros)')) . '">'
        . '<span class="vs-eye" aria-hidden="true">👁</span> ' . e($labels[$mode]) . ($belt ? ' · ' . e(BELTS[(int) $belt][0]) : '') . '</summary>'
        . '<form method="post" action="' . e(url('perziura.php')) . '" class="vs-panel">' . csrf_field() . '<input type="hidden" name="back" value="' . $back . '">'
        . '<div class="vs-modes">';
    foreach ($labels as $k => $label) {
        $html .= '<button type="submit" name="mode" value="' . $k . '"' . ($k === $mode ? ' class="active" aria-pressed="true"' : '') . '>' . e($label) . '</button>';
    }
    $html .= ('</div><p class="vs-hint">' . t('Tėvai - Test Parent (2 vaikai), Vaikas - Test Junior (15 m., savo paskyra).') . '</p>');
    if ($mode !== 'admin') {
        $html .= ('<label class="vs-belt">' . t('Test Junior diržas') . '<select name="belt" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">')
            . belt_options($belt !== null && $belt !== false ? (int) $belt : null, t('— be diržo —')) . '</select></label>';
    }
    return $html . '</form></details>';
}
