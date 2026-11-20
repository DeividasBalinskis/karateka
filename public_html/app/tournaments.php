<?php
// Varžybų sistema svetainėje (db/025): Valdymas → Varžybos. Klubai, dalyviai, komandos, kategorijos, lentelės, tvarkaraštis, rezultatai.
// Visa varžybų būsena - vienas JSON (tournaments.state), redaguojamas naršyklėje (assets/varzybos.js) ir išsaugomas automatiškai.
// Rezultatai (tournaments.results) - prizininkai ir klubų medaliai: iš jų narių taškai karateka.lt ir LTKF svetainės rezultatai.
// Test varžybos (is_test): sugeneruojamos vienu mygtuku iš Karateka narių ir kitų LTKF klubų išgalvotų dalyvių, ištrinamos vienu mygtuku.

const VARZ_NAME = 'Budo';    // varžybų sistemos pavadinimas; pakeisti - tik čia
const VARZ_HOME_CLUB = 'Karateka';
const TOURNAMENT_STATUS = [
    'draft'        => 'Ruošiamos',
    'registration' => 'Registracija',
    'running'      => 'Vyksta',
    'finished'     => 'Baigtos',
];
const VARZ_DISCIPLINES = ['kata' => 'Kata', 'kumite' => 'Kumite', 'fukugo' => 'Fukugo', 'kihon' => 'Kihon', 'enbu' => 'Enbu', 'sumo' => 'Sumo'];

function tournaments_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT 1 FROM tournaments LIMIT 1');
            $ok = true;
        } catch (PDOException $ex) {
            $ok = false;   // dar neimportuotas db/025
        }
    }
    return $ok;
}

function tournament_get(int $id): ?array
{
    return tournaments_ready() ? q_one('SELECT * FROM tournaments WHERE id = ?', [$id]) : null;
}

/** Varžybos sąraše: naujausios ir būsimos viršuje */
function tournaments_list(bool $withTest = true, bool $demoOnly = false): array
{
    if (!tournaments_ready()) {
        return [];
    }
    if ($demoOnly) {
        try {
            return q_all('SELECT * FROM tournaments WHERE is_demo = 1 ORDER BY starts_on DESC, id DESC');
        } catch (PDOException $ex) {
            return [];   // dar neimportuotas db/033
        }
    }
    return q_all('SELECT * FROM tournaments' . ($withTest ? '' : ' WHERE is_test = 0') . ' ORDER BY starts_on DESC, id DESC');
}

// ---------- Taikos bandymo nuoroda (be paskyros) ----------
// Adminas sukuria nuorodą (Taikos pradžia → „👀 Duoti išbandyti“). Ją atidaręs žmogus įrašo vardą ir gali daryti viską, ką adminas,
// bet tik bandymo varžybose (is_demo): be tikrų klubo narių, be bendrų nustatymų keitimo; gali palikti atsiliepimų (💡).

function varz_demo_token(): string
{
    return (string) site_setting('taika_demo_token', '');
}

/** Bandančiojo sesija: ['name' => ...] arba null (nuoroda išjungta ar pakeista - prieiga baigiasi) */
function varz_demo(): ?array
{
    $s = $_SESSION['taika_demo'] ?? null;
    $tok = varz_demo_token();
    if (!is_array($s) || $tok === '' || !hash_equals(hash('sha256', $tok), (string) ($s['tok'] ?? ''))) {
        return null;
    }
    return $s;
}

function varz_demo_start(string $token, string $name): bool
{
    $tok = varz_demo_token();
    if ($tok === '' || !hash_equals($tok, $token)) {
        return false;
    }
    $_SESSION['taika_demo'] = ['tok' => hash('sha256', $tok), 'name' => mb_substr(trim($name), 0, 60)];
    return true;
}

// ---------- Sekretoriato nuoroda (be paskyros): tik vienoms varžyboms, be klubo narių duomenų ----------

function varz_sekr_token(int $tid): string
{
    return (string) site_setting('varz_sekr_' . $tid, '');
}

function varz_sekr_set(int $tid, bool $on): void
{
    set_site_setting('varz_sekr_' . $tid, $on ? bin2hex(random_bytes(12)) : '');
}

/** Kurių varžybų nuoroda (0 - neveikia) */
function varz_sekr_find(string $token): int
{
    if (!preg_match('/^[0-9a-f]{24}$/', $token)) {
        return 0;
    }
    $n = q_value("SELECT name FROM site_settings WHERE name LIKE 'varz\\_sekr\\_%' AND value = ?", [$token]);
    return $n ? (int) substr((string) $n, 10) : 0;
}

/** Galiojanti sekretoriato sesija (['tid', 'name']) - šioms varžyboms ar bet kurioms; nuorodą pakeitus ar išjungus - baigiasi */
function varz_sekr(?int $tid = null): ?array
{
    foreach ((array) ($_SESSION['varz_sekr'] ?? []) as $id => $s) {
        if ($tid !== null && (int) $id !== $tid) {
            continue;
        }
        $tok = varz_sekr_token((int) $id);
        if ($tok !== '' && is_array($s) && hash_equals(hash('sha256', $tok), (string) ($s['tok'] ?? ''))) {
            return ['tid' => (int) $id, 'name' => (string) ($s['name'] ?? '')];
        }
    }
    return null;
}

function varz_sekr_start(int $tid, string $name): void
{
    $_SESSION['varz_sekr'][$tid] = ['tok' => hash('sha256', varz_sekr_token($tid)), 'name' => mb_substr(trim($name), 0, 80)];
}

/** Taikos puslapiams: treneris / adminas, bandantysis per nuorodą (['demo' => true, ...]) arba sekretoriatas (['sekr' => varžybų id, ...]) */
function varz_require_access(): array
{
    $a = current_account();
    if ($a && is_staff($a)) {
        return $a;
    }
    if ($d = varz_demo()) {
        return ['id' => 0, 'role' => 'demo', 'demo' => true, 'first_name' => $d['name'], 'last_name' => '', 'email' => ''];
    }
    $tid = (int) get('id');
    if ($s = varz_sekr()) {
        $mine = $tid ? varz_sekr($tid) : null;
        if (!$mine) {
            redirect('admin/varzybu-sistema.php?id=' . $s['tid']);   // sekretoriatas mato tik savo varžybas
        }
        return ['id' => 0, 'role' => 'sekr', 'sekr' => $mine['tid'], 'first_name' => $mine['name'], 'last_name' => '', 'email' => ''];
    }
    return require_staff();
}

function varz_is_demo(array $me): bool
{
    return !empty($me['demo']);
}

/** Viešai rodomos varžybos (LTKF, rezultatų puslapis): test - tik test svetainėje ir kompiuteryje */
function tournament_is_visible(array $t): bool
{
    return (int) $t['is_public'] === 1 && ((int) $t['is_test'] === 0 || config('env') !== 'live');
}

/** Partneriai Taikos apačioje - tie patys, kaip karateka.lt pagrindiniame puslapyje: [['image', 'name', 'url'], ...] */
function varz_partners(): array
{
    foreach (home_page() as $b) {
        if (($b['type'] ?? '') === 'partners' && empty($b['hidden'])) {
            return array_values(array_filter((array) ($b['d']['items'] ?? []), function ($i) { return !empty($i['image']); }));
        }
    }
    return home_block_defaults('partners')['items'];
}

// Temos - app/themes.php (THEMES), bendros su karateka.lt

/** Taikos išvaizdos nustatymai (bendri visoms varžyboms): tema ir meniu (tvarka, pavadinimai) */
function varz_ui(): array
{
    $ui = json_decode((string) site_setting('taika_ui', '{}'), true);
    $ui = is_array($ui) ? $ui : [];
    $ui['theme'] = isset(THEMES[$ui['theme'] ?? '']) ? $ui['theme'] : 'light';
    $ui['menu'] = is_array($ui['menu'] ?? null) ? $ui['menu'] : ['order' => [], 'names' => []];
    return $ui;
}

function varz_ui_save(array $in): array
{
    $ui = varz_ui();
    if (isset($in['theme']) && isset(THEMES[$in['theme']])) {
        $ui['theme'] = $in['theme'];
    }
    if (isset($in['menu']) && is_array($in['menu'])) {
        // Viršutinis meniu: Tvarkaraštis, Rezultatai, Administracija (v1.41+); pavadinimus galima keisti ir Administracijos skirtukams
        $tabs = ['live', 'rezultatai', 'admin'];
        $named = ['klubai', 'komandos', 'dalyviai', 'kategorijos', 'tinklai', 'live', 'rezultatai', 'admin'];
        $order = array_values(array_unique(array_intersect((array) ($in['menu']['order'] ?? []), $tabs)));
        // Nauji skirtukai (pvz. „Tinklai“ nuo v1.29) įterpiami po savo numatyto kaimyno, kad sena tvarka neprapultų
        foreach ($tabs as $i => $tab) {
            if ($order && !in_array($tab, $order, true)) {
                $prev = $i > 0 ? array_search($tabs[$i - 1], $order, true) : -1;
                array_splice($order, $prev === false ? count($order) : $prev + 1, 0, [$tab]);
            }
        }
        $names = [];
        foreach ((array) ($in['menu']['names'] ?? []) as $k => $v) {
            if (in_array($k, $named, true) && trim((string) $v) !== '') {
                $names[$k] = mb_substr(trim((string) $v), 0, 40);
            }
        }
        $ui['menu'] = ['order' => count($order) === count($tabs) ? $order : [], 'names' => $names];
    }
    // Amžiaus kategorijų šablonas (Kategorijos → „📋 Šablonas“): blokai pagal amžių, ne metus - tinka ir kitais metais
    if (isset($in['tpl']) && is_array($in['tpl'])) {
        $age = function ($v) { return $v === null || $v === '' ? null : max(0, min(99, (int) $v)); };
        $kyu = function ($v) { return $v === null || $v === '' ? null : max(-10, min(20, (int) $v)); };
        $ui['tpl'] = array_values(array_map(function ($b) use ($age, $kyu) {
            $b = (array) $b;
            return ['a0' => $age($b['a0'] ?? null), 'a1' => $age($b['a1'] ?? null), 'g' => in_array($b['g'] ?? '', ['M', 'F'], true) ? $b['g'] : '',
                    'b0' => $kyu($b['b0'] ?? null), 'b1' => $kyu($b['b1'] ?? null)];
        }, array_slice($in['tpl'], 0, 80)));
    }
    set_site_setting('taika_ui', json_encode($ui, JSON_UNESCAPED_UNICODE));
    return $ui;
}

/** Taikos puslapio pradžia (be karateka.lt meniu): <head>, šriftai, stilius */
function varz_html_head(string $title, string $bodyClass = ''): void
{
    echo '<!doctype html><html lang="lt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>' . e($title) . ' · ' . e(VARZ_NAME) . '</title>'
        . '<link rel="icon" type="image/png" sizes="32x32" href="' . url('favicon-32.png?v=2') . '">'
        . '<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">'
        . '<link rel="stylesheet" href="' . e(asset_url('assets/varzybos.css')) . '"><link rel="stylesheet" href="' . e(asset_url('assets/widgets.css')) . '">'
        . '<script src="' . e(asset_url('assets/theme-pick.js')) . '" defer></script>' . tl_script() . '</head><body' . theme_body_attrs(varz_ui()['theme'], 'vz ' . $bodyClass) . '>';
}

/** Partnerių juosta Taikos puslapių apačioje */
function varz_partners_html(): string
{
    $html = '';
    foreach (varz_partners() as $p) {
        $img = '<img src="' . e(url(ltrim((string) $p['image'], '/'))) . '" alt="' . e((string) ($p['name'] ?? '')) . '" loading="lazy">';
        $html .= !empty($p['url']) ? '<a class="vzp-logo" href="' . e($p['url']) . '" target="_blank" rel="noopener" title="' . e((string) ($p['name'] ?? '')) . '">' . $img . '</a>'
            : '<span class="vzp-logo" title="' . e((string) ($p['name'] ?? '')) . '">' . $img . '</span>';
    }
    return $html ? '<footer class="vz-partners"><div class="vzp-head">Partneriai</div><div class="vzp-row">' . $html . '</div></footer>' : '';
}

/** Federacijos klubai (LTKF sąrašas) - varžybų klubų pradžiai */
function varz_federation_clubs(): array
{
    $file = PUBLIC_DIR . '/ltkf/turinys/klubai.php';
    $names = is_file($file) ? array_column((array) require $file, 'pavadinimas') : [];
    $names = array_values(array_filter($names, function ($n) { return $n !== VARZ_HOME_CLUB && strpos($n, '[') === false; }));
    return array_values(array_unique(array_merge([VARZ_HOME_CLUB], $names)));
}

/** Tuščios varžybos: klubai, du tatamiai, kovų trukmės */
function tournament_default_state(): array
{
    return [
        'categories' => [], 'people' => (object) [], 'teams' => (object) [], 'clubs' => varz_federation_clubs(),
        'areas' => [['id' => 1, 'name' => '1 TATAMIS', 'start' => '09:30'], ['id' => 2, 'name' => '2 TATAMIS', 'start' => '09:30']],
        'minutes' => ['kata' => 2, 'kumite' => 3, 'fukugo' => 4, 'kihon' => 1.5],
    ];
}

function tournament_state(array $t): array
{
    $s = json_decode((string) $t['state'], true);
    return is_array($s) ? $s : tournament_default_state();
}

function tournament_results(array $t): array
{
    $r = json_decode((string) $t['results'], true);
    return is_array($r) ? $r : ['categories' => [], 'medals' => []];
}

/** Trumpa suvestinė sąrašui: dalyviai, kategorijos, klubai, baigtos kategorijos */
function tournament_stats(array $t): array
{
    $s = tournament_state($t);
    $r = tournament_results($t);
    $people = (array) ($s['people'] ?? []);
    $clubs = array_unique(array_column($people, 'club'));
    $cats = array_filter((array) ($s['categories'] ?? []), function ($c) { return !empty($c['competitors']); });
    return [
        'people'   => count($people),
        'home'     => count(array_filter($people, function ($p) { return ($p['club'] ?? '') === VARZ_HOME_CLUB; })),
        'clubs'    => count($clubs),
        'cats'     => count($cats),
        'finished' => count(array_filter((array) ($r['categories'] ?? []), function ($c) { return !empty($c['finished']); })),
    ];
}

function tournament_create(string $title, string $startsOn, ?string $endsOn, ?string $location, bool $isTest, int $by, ?array $state = null, bool $demo = false): int
{
    q('INSERT INTO tournaments (title, starts_on, ends_on, location, is_test, state, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [mb_substr($title, 0, 190), $startsOn, $endsOn ?: null, $location ?: null, $isTest || $demo ? 1 : 0,
         json_encode($state ?? tournament_default_state(), JSON_UNESCAPED_UNICODE), $by ?: null]);
    $id = (int) db()->lastInsertId();
    if ($demo) {
        q('UPDATE tournaments SET is_demo = 1 WHERE id = ?', [$id]);
    }
    return $id;
}

/**
 * Būsenos žemėlapiai (rezultatai, balai, kovos...) visada lieka JSON objektais.
 * PHP tuščią {} paverčia [] - naršyklėje į tokį sąrašą įrašyti rezultatai nebeišsaugomi (dingdavo po perkrovimo).
 */
function varz_state_maps(array $state): array
{
    $map = static function (&$holder, string $key): void {
        if (is_array($holder) && array_key_exists($key, $holder) && $holder[$key] === []) {
            $holder[$key] = new stdClass();
        }
    };
    foreach (['people', 'teams', 'clubInfo'] as $k) {
        $map($state, $k);
    }
    if (isset($state['categories']) && is_array($state['categories'])) {
        foreach ($state['categories'] as &$c) {
            if (!is_array($c)) {
                continue;
            }
            $map($c, 'crit');
            $map($c, 'cfg');
            if (isset($c['draw']) && is_array($c['draw'])) {
                foreach (['results', 'scores', 'final', 'bouts', 'kata'] as $k) {
                    $map($c['draw'], $k);
                }
                if (isset($c['draw']['kata']) && is_array($c['draw']['kata'])) {
                    $map($c['draw']['kata'], 'scores');
                    $map($c['draw']['kata'], 'final');
                }
            }
        }
        unset($c);
    }
    return $state;
}

/**
 * Išsaugo naršyklės pakeitimus. $version - kokią versiją redaguotojas matė: jei kitas žmogus jau išsaugojo naujesnę,
 * grąžinama null (nieko neperrašoma), kitaip - nauja versija.
 */
function tournament_save_state(int $id, array $state, array $results, int $version): ?int
{
    unset($state['history']);
    $state = varz_state_maps($state);
    $st = q('UPDATE tournaments SET state = ?, results = ?, version = version + 1, updated_at = NOW() WHERE id = ? AND version = ?',
        [json_encode($state, JSON_UNESCAPED_UNICODE), json_encode($results, JSON_UNESCAPED_UNICODE), $id, $version]);
    if (!$st->rowCount()) {
        return null;
    }
    tournament_snapshot($id);   // kopija istorijai (ne dažniau kaip kas minutę)
    return $version + 1;
}

// ---------- Istorija (db/030): kopija kas minutę, atkūrimas „kaip prieš 5 min.“ ----------

/** Dabartinės būsenos kopija (dažniausiai - ne dažniau kaip kas minutę; su pastaba - visada) */
function tournament_snapshot(int $id, string $note = ''): void
{
    try {
        if ($note === '') {
            $last = q_value('SELECT created_at FROM tournament_versions WHERE tournament_id = ? ORDER BY id DESC LIMIT 1', [$id]);
            if ($last && strtotime((string) $last) > time() - 60) {
                return;
            }
        }
        q('INSERT INTO tournament_versions (tournament_id, version, state, results, note) SELECT id, version, COALESCE(state, "{}"), results, ? FROM tournaments WHERE id = ?',
            [$note !== '' ? mb_substr($note, 0, 120) : null, $id]);
        $cut = q_value('SELECT id FROM tournament_versions WHERE tournament_id = ? ORDER BY id DESC LIMIT 1 OFFSET 400', [$id]);
        if ($cut) {
            q('DELETE FROM tournament_versions WHERE tournament_id = ? AND id <= ?', [$id, (int) $cut]);
        }
    } catch (PDOException $ex) {
        // dar neimportuotas db/030
    }
}

/** Kopijų sąrašas (naujausios viršuje): laikas, pastaba, kiek kategorijų ir kiek baigta */
function tournament_history(int $id, int $limit = 80): array
{
    try {
        $rows = q_all('SELECT id, version, note, created_at, results FROM tournament_versions WHERE tournament_id = ? ORDER BY id DESC LIMIT ' . max(1, $limit), [$id]);
    } catch (PDOException $ex) {
        return [];
    }
    return array_map(function ($r) {
        $res = json_decode((string) $r['results'], true);
        $cats = is_array($res['categories'] ?? null) ? $res['categories'] : [];
        return ['id' => (int) $r['id'], 'at' => $r['created_at'], 'note' => (string) $r['note'], 'cats' => count($cats),
                'done' => count(array_filter($cats, function ($c) { return !empty($c['finished']); }))];
    }, $rows);
}

/** Atkuria kopiją (dabartinė būsena prieš tai išsaugoma kaip kopija). Grąžina naują versiją ir būseną arba null. */
function tournament_restore(int $id, int $snapId): ?array
{
    $snap = q_one('SELECT * FROM tournament_versions WHERE id = ? AND tournament_id = ?', [$snapId, $id]);
    if (!$snap) {
        return null;
    }
    tournament_snapshot($id, 'Prieš atkūrimą');
    q('UPDATE tournaments SET state = ?, results = ?, version = version + 1, updated_at = NOW() WHERE id = ?', [$snap['state'], $snap['results'], $id]);
    return ['version' => (int) q_value('SELECT version FROM tournaments WHERE id = ?', [$id]), 'state' => json_decode((string) $snap['state'], true)];
}

function tournament_delete(int $id): void
{
    q('DELETE FROM tournaments WHERE id = ?', [$id]);
}

/** Ištrina visas test varžybas. Grąžina, kiek ištrinta. */
function tournaments_delete_test(): int
{
    return tournaments_ready() ? q('DELETE FROM tournaments WHERE is_test = 1')->rowCount() : 0;
}

/** Lytis pagal vardą, jei nenurodyta (lietuviški vardai: -a, -ė - moteriški) */
function varz_guess_gender(string $firstName): string
{
    return preg_match('/(a|ė|ija|ia)$/u', mb_strtolower(trim($firstName))) ? 'F' : 'M';
}

/** Karateka aktyvūs nariai, kuriuos galima pridėti į varžybas: [{id, name, birth, gender, belt, group}] */
function varz_club_members(): array
{
    $rows = q_all('SELECT m.id, m.first_name, m.last_name, m.birth_date, m.gender, m.belt_level, m.photo_consent, g.name AS group_name
                   FROM members m LEFT JOIN training_groups g ON g.id = m.group_id
                   WHERE m.status = "active" ORDER BY g.sort_order IS NULL, g.sort_order, m.last_name, m.first_name');
    return array_map(function ($m) {
        return ['id' => (int) $m['id'], 'name' => $m['first_name'] . ' ' . $m['last_name'], 'birth' => (int) substr((string) $m['birth_date'], 0, 4) ?: null,
                'gender' => $m['gender'] ?: varz_guess_gender($m['first_name']), 'belt' => $m['belt_level'] !== null ? (int) $m['belt_level'] : null,
                'group' => (string) ($m['group_name'] ?? ''), 'photo' => (int) $m['photo_consent']];
    }, $rows);
}

/** Karateka narys kaip varžybų dalyvis (id „m“ + nario id - tas pats narys nepridedamas du kartus) */
function varz_person_from_member(array $m): array
{
    return ['id' => 'm' . $m['id'], 'name' => $m['name'], 'club' => VARZ_HOME_CLUB, 'birth' => $m['birth'], 'gender' => $m['gender'], 'belt' => $m['belt'], 'member' => $m['id']];
}

/**
 * Test varžybos: visi aktyvūs Karateka nariai + po 5-12 išgalvotų dalyvių iš kitų federacijos klubų, komandos,
 * kategorijos sudaromos automatiškai (atidarius), dalis kovų „sužaidžiama“ atsitiktinai - kad iškart matytųsi lentelės ir rezultatai.
 */
function tournament_generate_test(int $by, bool $demo = false): int
{
    $first = ['M' => ['Jonas', 'Lukas', 'Matas', 'Domas', 'Tomas', 'Paulius', 'Rokas', 'Kajus', 'Nojus', 'Augustas', 'Ignas', 'Dovydas', 'Herkus', 'Benas', 'Jokūbas', 'Adomas'],
              'F' => ['Ona', 'Ieva', 'Gabija', 'Emilija', 'Austėja', 'Kamilė', 'Urtė', 'Gustė', 'Liepa', 'Saulė', 'Rugilė', 'Smiltė', 'Viltė', 'Elzė', 'Luknė', 'Goda']];
    $last = ['Kazlauskas', 'Petrauskas', 'Jankauskas', 'Stankevičius', 'Vasiliauskas', 'Žukauskas', 'Butkus', 'Paulauskas', 'Urbonas', 'Kavaliauskas',
             'Navickas', 'Rimkus', 'Balčiūnas', 'Mockus', 'Šimkus', 'Lukoševičius', 'Morkūnas', 'Grigas', 'Sakalauskas', 'Vaitkus'];
    $female = function (string $l): string {
        return preg_match('/ius$/u', $l) ? preg_replace('/ius$/u', 'iūtė', $l) : preg_replace(['/as$/u', '/us$/u', '/is$/u'], ['aitė', 'utė', 'ytė'], $l);
    };
    $year = (int) date('Y');
    $people = [];
    // Bandymo nuorodai - tikrų narių nėra: ir Karatekos dalyviai išgalvoti
    foreach ($demo ? [] : varz_club_members() as $m) {
        if ($m['birth']) {
            $people['m' . $m['id']] = varz_person_from_member($m) + ['belt' => null];
            $people['m' . $m['id']]['belt'] = $m['belt'] ?: mt_rand(1, 4);
        }
    }
    $used = [];
    $clubs = varz_federation_clubs();
    $uid = 1;
    foreach (array_slice($clubs, $demo ? 0 : 1) as $club) {
        $n = mt_rand(5, 12);
        for ($i = 0; $i < $n; $i++) {
            $g = mt_rand(0, 9) < 5 ? 'M' : 'F';
            for ($guard = 0; $guard < 30; $guard++) {
                $l = $last[array_rand($last)];
                $name = $first[$g][array_rand($first[$g])] . ' ' . ($g === 'F' ? $female($l) : $l);
                if (!isset($used[$name])) {
                    break;
                }
            }
            $used[$name] = true;
            $age = [6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 19, 24, 31][mt_rand(0, 14)];
            $belt = max(1, min(12, (int) round(($age - 5) / 1.6) + mt_rand(-1, 2)));
            $id = 'x' . ($uid++);
            $people[$id] = ['id' => $id, 'name' => $name, 'club' => $club, 'birth' => $year - $age, 'gender' => $g, 'belt' => $belt, 'member' => null];
        }
    }
    // Komandos: po vieną iš klubų, kuriuose yra bent 3 dalyviai nuo 10 metų
    $teams = [];
    $teamCompetitors = [];
    foreach ($clubs as $club) {
        $mem = array_values(array_filter($people, function ($p) use ($club, $year) { return $p['club'] === $club && $year - (int) $p['birth'] >= 10; }));
        if (count($mem) >= 3) {
            $mem = array_slice($mem, 0, 3);
            $tid = 't' . (count($teams) + 1);
            $tname = $club . ' (' . implode(', ', array_map(function ($p) { return explode(' ', $p['name'])[0]; }, $mem)) . ')';
            $teams[$tid] = ['id' => $tid, 'name' => $tname, 'club' => $club, 'members' => array_column($mem, 'id')];
            $teamCompetitors[] = ['id' => $tid, 'name' => $tname, 'club' => $club, 'team' => true];
        }
    }
    $state = tournament_default_state();
    $state['people'] = $people ?: (object) [];
    $state['teams'] = $teams ?: (object) [];
    $state['categories'] = count($teamCompetitors) >= 2
        ? [['id' => 'kt1', 'name' => 'Kata komandos Mix', 'discipline' => 'kata', 'team' => true, 'competitors' => $teamCompetitors, 'tatami' => 3, 'draw' => null]] : [];
    $state['areas'][] = ['id' => 3, 'name' => '3 TATAMIS', 'start' => '09:30'];
    $state['areas'][] = ['id' => 4, 'name' => '4 TATAMIS', 'start' => '09:30'];   // varžybose dažniausiai 4 tatamiai
    $state['autoCats'] = true;    // kategorijos sudaromos atidarius (assets/varzybos.js)
    $state['simulate'] = 0.6;     // ir dalis kovų „sužaidžiama“
    $date = date('Y-m-d', strtotime('next saturday'));
    $n = (int) q_value('SELECT COUNT(*) FROM tournaments WHERE is_test = 1') + 1;
    return tournament_create(($demo ? 'Bandymas' : 'Test varžybos') . ' #' . $n . ' · Budo Cup pavyzdys', $date, null, 'Vilnius, Sporto centras Viršuliškės', true, $by, $state, $demo);
}

/**
 * Rezultatai → karateka.lt renginys ir narių taškai: prizininkams vieta (geriausia, jei kelios kategorijos), kitiems Karateka dalyviams - dalyvavimas.
 * Jei renginio dar nėra - sukuriamas (tipas „Varžybos“). Grąžina [renginio id, kiek narių pakeista].
 */
function tournament_results_to_points(array $t, int $by): array
{
    $state = tournament_state($t);
    $results = tournament_results($t);
    $best = [];
    foreach ((array) ($state['people'] ?? []) as $p) {
        if (!empty($p['member']) && ($p['club'] ?? '') === VARZ_HOME_CLUB) {
            $best[(int) $p['member']] = 'part';
        }
    }
    foreach ((array) ($results['categories'] ?? []) as $c) {
        foreach ((array) ($c['places'] ?? []) as $pl) {
            foreach ((array) ($pl['members'] ?? []) as $mid) {
                $mid = (int) $mid;
                $cur = $best[$mid] ?? 'part';
                if ($cur === 'part' || (int) $pl['place'] < (int) $cur) {
                    $best[$mid] = (string) (int) $pl['place'];
                }
            }
        }
    }
    // Tik tikri, aktyvūs nariai
    $ids = array_keys($best);
    $valid = $ids ? array_map('intval', q('SELECT id FROM members WHERE id IN (' . implode(',', $ids) . ')')->fetchAll(PDO::FETCH_COLUMN)) : [];
    $best = array_intersect_key($best, array_flip($valid));

    $event = $t['event_id'] ? q_one('SELECT * FROM events WHERE id = ?', [(int) $t['event_id']]) : null;
    if (!$event) {
        q('INSERT INTO events (type, title, starts_on, ends_on, location, description) VALUES ("competition", ?, ?, ?, ?, ?)',
            [$t['title'], $t['starts_on'], $t['ends_on'], $t['location'], 'Lentelės ir rezultatai - varžybų sistemoje.']);
        $eventId = (int) db()->lastInsertId();
        q('UPDATE tournaments SET event_id = ? WHERE id = ?', [$eventId, (int) $t['id']]);
        $event = q_one('SELECT * FROM events WHERE id = ?', [$eventId]);
    }
    $changes = $best ? save_event_results($event, $best, $by) : 0;
    return [(int) $event['id'], count($best), $changes];
}

/** LTKF (federacijos) svetainė: config.php 'ltkf_url', o test svetainėje ir kompiuteryje - juodraštis /ltkf/ */
function ltkf_url(): string
{
    $u = trim((string) config('ltkf_url', ''));
    return $u !== '' ? $u : (config('env') !== 'live' ? url('ltkf/') : '');
}

/** Apačios nuorodos visuose puslapiuose: federacija ir varžybų rezultatai (jei yra paskelbtų) */
function footer_links_html(): string
{
    $links = [];
    if ($u = ltkf_url()) {
        $links[] = '<a href="' . e($u) . '">' . te('Lietuvos tradicinio karate federacija (LTKF)') . '</a>';
    }
    if (tournaments_ready() && array_filter(tournaments_list(), 'tournament_is_visible')) {
        $links[] = '<a href="' . e(url('varzybu-rezultatai.php')) . '">' . te('Varžybų rezultatai') . '</a>';
    }
    return $links ? ' · ' . implode(' · ', $links) : '';
}

// ---------- Transliacija ir registracija per nuorodą (db/026) ----------

/** Nuorodos raktas: 'share' - žiūrovams ir ekranams, 'reg' - kitų klubų registracijai. Sukuriamas pirmą kartą prireikus. */
function tournament_token(array $t, string $kind): string
{
    $col = ['reg' => 'reg_token', 'awards' => 'awards_token'][$kind] ?? 'share_token';
    if (!empty($t[$col])) {
        return (string) $t[$col];
    }
    q("UPDATE tournaments SET $col = ? WHERE id = ? AND $col IS NULL", [bin2hex(random_bytes(12)), (int) $t['id']]);
    return (string) q_value("SELECT $col FROM tournaments WHERE id = ?", [(int) $t['id']]);
}

function tournament_by_token(string $token, string $kind): ?array
{
    if (!preg_match('/^[a-f0-9]{24}$/', $token) || !tournaments_ready()) {
        return null;
    }
    try {
        return q_one('SELECT * FROM tournaments WHERE ' . (['reg' => 'reg_token', 'awards' => 'awards_token'][$kind] ?? 'share_token') . ' = ?', [$token]);
    } catch (PDOException $ex) {
        return null;   // dar neimportuotas db/026
    }
}

/** YouTube transliacija: iš nuorodos - įterpimo adresas (vaizdo įrašas arba kanalo tiesioginė transliacija) ir nuoroda žiūrėti. */
function tournament_stream(array $t): ?array
{
    $url = trim((string) ($t['stream_url'] ?? ''));
    if ($url === '' || !preg_match('~^https?://~i', $url)) {
        return null;
    }
    $embed = null;
    if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|live/|embed/|shorts/))([A-Za-z0-9_-]{11})~', $url, $m)) {
        $embed = 'https://www.youtube-nocookie.com/embed/' . $m[1];
    } elseif (preg_match('~youtube\.com/channel/(UC[A-Za-z0-9_-]{22})~', $url, $m)) {
        $embed = 'https://www.youtube.com/embed/live_stream?channel=' . $m[1];
    }
    $at = !empty($t['stream_at']) ? strtotime((string) $t['stream_at']) : null;
    return ['url' => $url, 'embed' => $embed, 'at' => $at ?: null, 'soon' => $at && $at > time()];
}

/** Kas vyksta ant tatamio dabar (iš kovos skydelio): išsaugoma su serverio laiku (ms) - laikrodžiui ekranuose */
function tournament_live_set(int $id, int $area, array $data): void
{
    q('INSERT INTO tournament_live (tournament_id, area_id, data, updated_ms) VALUES (?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE data = VALUES(data), updated_ms = VALUES(updated_ms)',
        [$id, $area, json_encode($data, JSON_UNESCAPED_UNICODE), (int) round(microtime(true) * 1000)]);
}

/** [area_id => ['data' => [...], 'ms' => serverio laikas, kada gauta]] */
function tournament_live_get(int $id): array
{
    $out = [];
    try {
        foreach (q_all('SELECT area_id, data, updated_ms FROM tournament_live WHERE tournament_id = ?', [$id]) as $r) {
            $out[(int) $r['area_id']] = ['data' => json_decode((string) $r['data'], true) ?: [], 'ms' => (int) $r['updated_ms']];
        }
    } catch (PDOException $ex) {
        // dar neimportuotas db/026
    }
    return $out;
}

// ---------- Apdovanojimai (db/033): sekretoriatas siunčia baigtą grupę, apdovanojimų planšetėje - „Užbaigti“ ----------

/** [kategorijos id => ['st' => 'sent'|'done', 'sent' => laikas, 'done' => laikas]] */
function tournament_awards(int $id): array
{
    $out = [];
    try {
        foreach (q_all('SELECT cat_id, status, sent_at, done_at FROM tournament_awards WHERE tournament_id = ?', [$id]) as $r) {
            $out[(string) $r['cat_id']] = ['st' => $r['status'], 'sent' => $r['sent_at'], 'done' => $r['done_at']];
        }
    } catch (PDOException $ex) {
        // dar neimportuotas db/033
    }
    return $out;
}

/**
 * #122: baigta rungtis pati atsiranda apdovanojimuose („sent“, laikas - kada baigėsi), nebereikia spausti „Siųsti apdovanojimams“.
 * Jei rungtis vėl nebaigta (atšauktas rezultatas) ir dar neapdovanota - iš apdovanojimų dingsta.
 */
function tournament_awards_sync(int $id, array $results): void
{
    $have = tournament_awards($id);
    foreach ((array) ($results['categories'] ?? []) as $c) {
        $cid = (string) ($c['id'] ?? '');
        if ($cid === '' || empty($c['count'])) {
            continue;
        }
        $st = $have[$cid]['st'] ?? '';
        if (!empty($c['finished']) && $st === '') {
            tournament_award_set($id, $cid, 'sent');
        } elseif (empty($c['finished']) && $st === 'sent') {
            tournament_award_set($id, $cid, '');
        }
    }
}

/** Būsena: 'sent' - išsiųsta apdovanojimams, 'done' - apdovanota, '' - atšaukta (vėl tik baigta) */
function tournament_award_set(int $id, string $catId, string $status): bool
{
    if (!preg_match('/^[\w-]{1,40}$/', $catId)) {
        return false;
    }
    try {
        if ($status === 'sent') {
            q('INSERT INTO tournament_awards (tournament_id, cat_id, status, sent_at) VALUES (?, ?, "sent", NOW())
               ON DUPLICATE KEY UPDATE status = "sent", sent_at = COALESCE(sent_at, NOW()), done_at = NULL', [$id, $catId]);
        } elseif ($status === 'done') {
            q('INSERT INTO tournament_awards (tournament_id, cat_id, status, sent_at, done_at) VALUES (?, ?, "done", NOW(), NOW())
               ON DUPLICATE KEY UPDATE status = "done", done_at = NOW()', [$id, $catId]);
        } else {
            q('DELETE FROM tournament_awards WHERE tournament_id = ? AND cat_id = ?', [$id, $catId]);
        }
        return true;
    } catch (PDOException $ex) {
        return false;   // dar neimportuotas db/033
    }
}

/** Nauji (dar nepridėti) užsiregistravę per nuorodą */
function tournament_new_entries(int $id): array
{
    try {
        return q_all('SELECT * FROM tournament_entries WHERE tournament_id = ? AND status = "new" ORDER BY club, id', [$id]);
    } catch (PDOException $ex) {
        return [];
    }
}

/** Registracijų kontaktai pagal klubą (skirtukas „Klubai“ → „Kontaktai“): [klubas => [['phone', 'n' => kiek dalyvių], ...]] */
function tournament_club_contacts(int $id): array
{
    $out = [];
    try {
        foreach (q_all('SELECT club, contact, COUNT(*) AS n FROM tournament_entries WHERE tournament_id = ? AND contact IS NOT NULL AND contact <> "" GROUP BY club, contact ORDER BY club, n DESC', [$id]) as $r) {
            $out[$r['club']][] = ['phone' => $r['contact'], 'n' => (int) $r['n']];
        }
    } catch (PDOException $ex) {
        $out = [];
    }
    return $out;
}

/** Klubų kontaktai iš LTKF sąrašo (tik tikri, be „[...]“ vietos rezervavimo): [klubas => ['person', 'phone', 'email']] */
function varz_club_directory(): array
{
    $file = PUBLIC_DIR . '/ltkf/turinys/klubai.php';
    $out = [];
    foreach (is_file($file) ? (array) require $file : [] as $k) {
        $real = function ($v) { $v = trim((string) $v); return $v !== '' && strpos($v, '[') === false ? $v : ''; };
        $c = ['person' => $real($k['treneris'] ?? ''), 'phone' => $real($k['telefonas'] ?? ''), 'email' => $real($k['el_pastas'] ?? '')];
        if (array_filter($c)) {
            $out[(string) $k['pavadinimas']] = $c;
        }
    }
    return $out;
}

/** Senieji pavyzdiniai klubai (iki v1.21 LTKF sąraše) - išimami iš varžybų, jei juose nėra dalyvių */
function varz_drop_example_clubs(array $state): array
{
    $old = ['Danas', 'Lūšis', 'Sokratus', 'Tikslas'];
    $used = [];
    foreach ((array) ($state['people'] ?? []) as $p) {
        $used[(string) ($p['club'] ?? '')] = true;
    }
    foreach ((array) ($state['teams'] ?? []) as $tm) {
        $used[(string) ($tm['club'] ?? '')] = true;
    }
    if (isset($state['clubs']) && is_array($state['clubs'])) {
        $state['clubs'] = array_values(array_filter($state['clubs'], function ($c) use ($old, $used) { return !in_array($c, $old, true) || isset($used[$c]); }));
    }
    return $state;
}

/** Vardas viešai: vaikų ir jaunių (iki 18 m.) - „Vardas P.“ */
function varz_public_name(array $p): string
{
    $parts = preg_split('/\s+/u', trim((string) ($p['name'] ?? '')));
    return !empty($p['minor']) && count($parts) > 1 ? $parts[0] . ' ' . mb_substr(end($parts), 0, 1) . '.' : (string) ($p['name'] ?? '');
}
