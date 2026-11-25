<?php
// Registracija į egzaminą per nuorodą (db/038) - žmonėms be paskyros (kitų klubų nariams ir kt.):
// vardas, pavardė, gimimo data, kontaktinis telefonas. Užsiregistravęs iškart įrašomas į egzamino protokolą ir gauna asmeninę nuorodą.
// Treneris nuorodą gali nusiųsti pats arba pakviesti el. paštu iš protokolo puslapio.

function exam_signup_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT 1 FROM exam_signup_links LIMIT 1');
            $ok = exam_protocol_ready();
        } catch (PDOException $ex) {
            $ok = false;   // dar neimportuotas db/038
        }
    }
    return $ok;
}

/**
 * Egzamino registracijos nuoroda (sukuriama, jei $create): ['event_id', 'is_open', 'token', 'link' => access_links eilutė].
 * C3: raktas - app/links.php (rūšis 'exam_signup'); exam_signup_links lieka registracijos jungikliui (is_open) ir seniems kodams.
 */
function exam_signup_link(int $eventId, bool $create = false): ?array
{
    if (!exam_signup_ready()) {
        return null;
    }
    $flags = q_one('SELECT * FROM exam_signup_links WHERE event_id = ?', [$eventId]);
    if (!links_ready()) {
        if (!$flags && $create) {   // db/062 dar neimportuotas - kaip anksčiau
            q('INSERT IGNORE INTO exam_signup_links (event_id, token, is_open) VALUES (?, ?, 1)', [$eventId, exam_short_code()]);
            $flags = q_one('SELECT * FROM exam_signup_links WHERE event_id = ?', [$eventId]);
        }
        return $flags ? $flags + ['link' => null] : null;
    }
    $l = link_current('exam_signup', $eventId);
    if (!$l && $create) {
        $l = link_create('exam_signup', $eventId);
    }
    if (!$l) {
        return null;
    }
    if (!$flags) {
        // Jungiklio eilutė be rakto („#id“ - tokio kodo adrese būti negali)
        q('INSERT IGNORE INTO exam_signup_links (event_id, token, is_open) VALUES (?, ?, 1)', [$eventId, '#' . $eventId]);
        $flags = q_one('SELECT * FROM exam_signup_links WHERE event_id = ?', [$eventId]) ?: ['event_id' => $eventId, 'is_open' => 1];
    }
    return ['event_id' => $eventId, 'is_open' => (int) $flags['is_open'], 'token' => (string) link_token($l), 'link' => $l];
}

function exam_signup_url(array $link): string
{
    return abs_url('e/?' . $link['token']);   // v1.92: trumpas adresas (e/index.php)
}

/** v1.92: trumpas registracijos kodas - 6 simboliai be painių (0/o, 1/l/i); nesikartoja su esamais. C3: app/links.php link_secret(). */
function exam_short_code(): string
{
    return link_secret('exam_signup');
}

/** Registracijos kodas iš adreso: trumpas (6) ar senas ilgas (24) */
function exam_code_ok(string $k): bool
{
    return (bool) preg_match('/^[a-z0-9]{4,32}$/', $k);
}

/** Ar dar galima registruotis: nuoroda atidaryta, egzaminas neįvyko, registracijos terminas nepraėjo */
function exam_signup_open(array $event, ?array $link): bool
{
    return $link && (int) $link['is_open'] === 1 && event_last_day($event) >= date('Y-m-d')
        && (empty($event['registration_until']) || $event['registration_until'] >= date('Y-m-d'));
}

function exam_signups(int $eventId): array
{
    return exam_signup_ready() ? q_all('SELECT * FROM exam_signups WHERE event_id = ? ORDER BY signed_at IS NULL, signed_at DESC, id DESC', [$eventId]) : [];
}

/** Ar importuotas db/040 (asmeninės nuorodos, telefonas, mokėjimas) */
function exam_v40_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT token FROM exam_protocol_rows LIMIT 1');
            $ok = true;
        } catch (PDOException $ex) {
            $ok = false;
        }
    }
    return $ok;
}

/**
 * Užsiregistravo per nuorodą: vardas, pavardė, gimimo data, telefonas (el. paštas - tik jei buvo pakviestas laišku).
 * Tas pats žmogus (vardas + gimimo data) - vienas įrašas. Eilutė protokole: esama (jei treneris jau įrašė) arba nauja.
 * Grąžina ['new' => bool, 'row_id' => int].
 */
function exam_signup_save(array $event, array $d): array
{
    $eid = (int) $event['id'];
    $first = mb_substr(trim((string) $d['first_name']), 0, 80);
    $last = mb_substr(trim((string) $d['last_name']), 0, 80);
    $name = trim(preg_replace('/\s+/u', ' ', $first . ' ' . $last));
    $birth = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($d['birth_date'] ?? '')) ? $d['birth_date'] : null;
    $phone = mb_substr(trim((string) ($d['phone'] ?? '')), 0, 40) ?: null;
    $email = mb_strtolower(trim((string) ($d['email'] ?? ''))) ?: null;
    // A3: tas pats žmogus - tik vardas + TA PATI gimimo data (vien vardo neužtenka); kviestas laišku - pagal el. paštą
    $old = $birth ? q_one('SELECT * FROM exam_signups WHERE event_id = ? AND LOWER(TRIM(CONCAT(first_name, " ", last_name))) = LOWER(?) AND birth_date = ? ORDER BY signed_at IS NULL, id LIMIT 1',
        [$eid, $name, $birth]) : null;
    if (!$old && $email) {
        $old = q_one('SELECT * FROM exam_signups WHERE event_id = ? AND email = ?', [$eid, $email]);
    }
    // A3: jau užsiregistravęs - nieko nekeičiame (nei telefono, nei vardo), nuorodos nerodome, treneriui pranešame
    if ($old && $old['signed_at']) {
        notify_staff('exam_registration', t('Bandė užsiregistruoti dar kartą') . ': ' . $name,
            $event['title'] . ' · ' . fmt_date($event['starts_on'], true) . ' · ' . t('jau buvo užregistruotas (-a); nuoroda neparodyta'), 'admin/egzamino-protokolas.php?id=' . $eid);
        return ['new' => false, 'already' => true, 'row_id' => (int) $old['row_id']];
    }
    if ($old) {
        q('UPDATE exam_signups SET first_name = ?, last_name = ?, birth_date = ?, phone = COALESCE(phone, ?), signed_at = NOW() WHERE id = ?', [$first, $last, $birth, $phone, (int) $old['id']]);
        $sid = (int) $old['id'];
    } else {
        q('INSERT INTO exam_signups (event_id, email, first_name, last_name, birth_date, phone, signed_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [$eid, $email ? mb_substr($email, 0, 190) : null, $first, $last, $birth, $phone]);
        $sid = (int) db()->lastInsertId();
    }
    // Protokolo eilutė: susieta anksčiau, arba tas pats žmogus jau įrašytas trenerio (vardas + gimimo data), arba nauja
    // A3: ištrinta eilutė neatgaivinama; trenerio įrašytas žmogus atpažįstamas tik pagal vardą + tą pačią gimimo datą
    $rid = $old && $old['row_id'] ? (int) $old['row_id'] : 0;
    $exists = $rid ? q_one('SELECT id FROM exam_protocol_rows WHERE id = ? AND event_id = ? AND deleted_at IS NULL', [$rid, $eid]) : null;
    if (!$exists && $birth) {
        $exists = q_one('SELECT id FROM exam_protocol_rows WHERE event_id = ? AND deleted_at IS NULL AND LOWER(TRIM(name)) = LOWER(?) AND birth_date = ? ORDER BY id LIMIT 1',
            [$eid, $name, $birth]);
    }
    $rid = $exists ? (int) $exists['id'] : 0;
    $inProtocol = (bool) $exists;   // žmogų jau buvo įrašęs treneris - nuorodos per registraciją nerodome (A3)
    if (!$exists) {
        exam_protocol($event);   // antraštė (jei protokolo dar nebuvo)
        $rid = exam_protocol_add($event, ['name' => $name, 'birth_date' => $birth]);
    }
    if ($phone && exam_v40_ready()) {
        q('UPDATE exam_protocol_rows SET phone = ? WHERE id = ? AND (phone IS NULL OR phone = "")', [$phone, $rid]);   // esamo telefono neperrašome
    }
    q('UPDATE exam_signups SET row_id = ? WHERE id = ?', [$rid, $sid]);
    $new = !$old || !$old['signed_at'];
    if ($new) {
        notify_staff('exam_registration', t('Užsiregistravo į egzaminą (per nuorodą)') . ': ' . $name,
            $event['title'] . ' · ' . fmt_date($event['starts_on'], true) . ($phone ? ' · ' . $phone : ''), url('admin/egzamino-protokolas.php?id=' . $eid));
    }
    return ['new' => $new, 'row_id' => $rid, 'already' => $inProtocol];
}

// ---------- Asmeninis egzamino puslapis be paskyros (mano-egzaminas.php?t=) ----------

/** Protokolo eilutės asmeninis raktas (sukuriamas pirmą kartą). C3: app/links.php (rūšis 'exam_row'). */
function exam_row_token(array $row): ?string
{
    if (!exam_v40_ready()) {
        return null;
    }
    if (!links_ready()) {   // db/062 dar neimportuotas - kaip anksčiau
        if (!empty($row['token'])) {
            return (string) $row['token'];
        }
        q('UPDATE exam_protocol_rows SET token = ? WHERE id = ? AND token IS NULL', [bin2hex(random_bytes(10)), (int) $row['id']]);
        return (string) q_value('SELECT token FROM exam_protocol_rows WHERE id = ?', [(int) $row['id']]);
    }
    if (!empty($row['token']) && !link_current('exam_row', (int) $row['id'])) {
        link_find('exam_row', (string) $row['token']);   // sena nuoroda - perkeliama, veikia toliau
    }
    $tok = link_token(link_ensure('exam_row', (int) $row['id']));
    return $tok !== null && $tok !== '' ? $tok : null;
}

function exam_row_url(array $row): ?string
{
    $t = exam_row_token($row);
    return $t ? abs_url('mano-egzaminas.php?k=' . $t) : null;
}

/** ↻ Nauja nuoroda: senoji asmeninė nuoroda nebeveiks (A3) */
function exam_row_new_token(int $rowId): void
{
    if (!exam_v40_ready()) {
        return;
    }
    if (!links_ready()) {
        q('UPDATE exam_protocol_rows SET token = ? WHERE id = ?', [bin2hex(random_bytes(10)), $rowId]);
        return;
    }
    $l = link_current('exam_row', $rowId);
    $l ? link_renew((int) $l['id']) : link_create('exam_row', $rowId);
}

/** „Jau užsiregistravai?“ - ne daugiau 10 nesėkmių per valandą iš vieno IP (duomenų bazėje, ne tik sesijoje) */
function exam_find_throttled(): bool
{
    return (int) q_value("SELECT COUNT(*) FROM login_attempts WHERE email = '#exam-find' AND ip = ? AND success = 0 AND attempted_at > NOW() - INTERVAL 1 HOUR", [client_ip()]) >= 10;
}

function exam_find_log(bool $ok): void
{
    q('INSERT INTO login_attempts (email, ip, success) VALUES (?, ?, ?)', ['#exam-find', client_ip(), $ok ? 1 : 0]);
}

/** Paieška „Jau užsiregistravai?“ veikia iki egzamino dienos pabaigos */
function exam_find_open(array $event): bool
{
    return (string) ($event['ends_on'] ?: $event['starts_on']) >= date('Y-m-d');
}

/** Protokolo eilutė pagal asmeninę nuorodą (null - nuoroda neveikia, priežastis - link_reason()) */
function exam_row_by_token(string $token): ?array
{
    if (!exam_v40_ready() || $token === '') {
        return null;
    }
    $l = link_find('exam_row', $token);
    return $l ? (q_one('SELECT * FROM exam_protocol_rows WHERE id = ? AND deleted_at IS NULL', [(int) $l['owner_id']]) ?: null) : null;
}

/** Rasti savo eilutę protokole: vardas pavardė + gimimo data (be paskyros) */
function exam_row_find(int $eventId, string $name, string $birth): ?array
{
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    if ($name === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth)) {
        return null;
    }
    return q_one('SELECT * FROM exam_protocol_rows WHERE event_id = ? AND deleted_at IS NULL AND LOWER(TRIM(name)) = LOWER(?) AND birth_date = ? ORDER BY id LIMIT 1', [$eventId, $name, $birth]);
}

/** Diržo lygis pagal protokolo „kyu“ tekstą (pvz. „8 kyu“ → 2) */
/**
 * Egzaminų laipsniai (#136): 10 kyu (pradinis, baltas) + diržai nuo 9 kyu iki 1 dan.
 * 10 kyu turi raktą 0 - nauji raktai, jau išsaugotos kainos (1-10) nepasislenka.
 */
const EXAM_EXTRA_GRADES = [0 => ['10 kyu', 'baltas', '#FFFFFF']];
function exam_grades(): array
{
    $out = EXAM_EXTRA_GRADES;
    foreach (BELTS as $lvl => $b) {
        if ($lvl <= 10) {
            $out[$lvl] = $b;
        }
    }
    return $out;
}

function exam_kyu_level(?string $kyu): ?int
{
    $k = mb_strtolower(trim((string) $kyu));
    if ($k === '') {
        return null;
    }
    if ($k === '10 kyu' || $k === '10') {
        return 0;
    }
    foreach (BELTS as $lvl => $b) {
        if ($k === mb_strtolower($b[0]) || $k === (string) (int) $b[0] && mb_strpos(mb_strtolower($b[0]), 'kyu') !== false) {
            return $lvl;
        }
    }
    return null;
}

// ---------- Kainos, mokėjimai, sertifikatai (kol kas - vietos, kai bus info) ----------

/** Egzamino kainos pagal diržą: [lygis => kaina €] (Egzaminavimas → „Kainos“) */
function exam_prices(): array
{
    $p = json_decode((string) site_setting('exam_prices', '{}'), true);
    $out = [];
    foreach (is_array($p) ? $p : [] as $lvl => $v) {
        if (isset(exam_grades()[(int) $lvl]) && is_numeric($v) && (float) $v > 0) {
            $out[(int) $lvl] = round((float) $v, 2);
        }
    }
    return $out;
}

function exam_price_for(?string $kyu): ?float
{
    $lvl = exam_kyu_level($kyu);
    return $lvl !== null ? (exam_prices()[$lvl] ?? null) : null;
}

/** Mokėjimo informacija (rekvizitai, kol nėra mokėjimų internetu) */
function exam_pay_info(): string
{
    return trim((string) site_setting('exam_pay_info', ''));
}

/**
 * Kvietimai el. paštu: eilutės „Vardas Pavardė, el@paštas“ arba tik el. paštas. Grąžina [išsiųsta, nepavyko, netinkamos eilutės].
 */
function exam_signup_invite(array $event, array $link, string $lines, string $note, string $from): array
{
    $sent = $fail = 0;
    $bad = [];
    $url = exam_signup_url($link);
    $when = fmt_date($event['starts_on'], true) . ($event['start_time'] ? ', ' . fmt_time($event['start_time']) : '') . ($event['location'] ? ' · ' . $event['location'] : '');
    foreach (preg_split('/\R/u', $lines) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (!preg_match('/[^\s<>,;]+@[^\s<>,;]+\.[^\s<>,;]+/u', $line, $m) || !filter_var($m[0], FILTER_VALIDATE_EMAIL)) {
            $bad[] = $line;
            continue;
        }
        $email = mb_strtolower($m[0]);
        $name = trim(preg_replace('/\s+/u', ' ', trim(str_replace([$m[0], '<', '>'], '', $line), " \t,;")));
        $parts = $name !== '' ? explode(' ', $name, 2) : ['', ''];
        $hello = $name !== '' ? t('Sveiki, {n}!', ['n' => $parts[0]]) : t('Sveiki!');
        $body = $hello . "\n\n" . t('Kviečiame į egzaminą') . ': ' . $event['title'] . "\n" . $when . "\n\n"
            . ($note !== '' ? $note . "\n\n" : '')
            . t('Užsiregistruokite (užtenka vardo, pavardės, gimimo datos ir telefono):') . "\n" . $url . "\n\n" . $from;
        if (send_mail($email, t('Kvietimas į egzaminą') . ': ' . $event['title'], $body)) {
            $sent++;
            q('INSERT INTO exam_signups (event_id, email, first_name, last_name, invited_at) VALUES (?, ?, ?, ?, NOW())
               ON DUPLICATE KEY UPDATE invited_at = NOW(), first_name = IF(first_name = "", VALUES(first_name), first_name), last_name = IF(last_name = "", VALUES(last_name), last_name)',
                [(int) $event['id'], mb_substr($email, 0, 190), mb_substr($parts[0] ?? '', 0, 80), mb_substr($parts[1] ?? '', 0, 80)]);
        } else {
            $fail++;
        }
    }
    return [$sent, $fail, $bad];
}

// ---------- #165 (Denis): laukiančiųjų sąrašas - viena registracijos nuoroda visiems egzaminams (db/055) ----------
// Užsiregistravęs patenka į Egzaminai → „⏳ Laukiantieji“; treneris pažymi ir įtraukia į egzamino protokolą
// (vienai egzamino dienai gali būti keli protokolai - skirtingiems diržams); žmogus gauna laišką su data, vieta ir kaina pagal diržą.

function exam_waitlist_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT 1 FROM exam_waitlist LIMIT 1');
            $ok = exam_protocol_ready();
        } catch (PDOException $ex) {
            $ok = false;
        }
    }
    return $ok;
}

/** Registracijos nuorodos raktas ('' - nėra); $new - sukurti naują (sena nebeveiks). C3: app/links.php (rūšis 'exam_waitlist'). */
function exam_waitlist_token(bool $new = false): string
{
    if (!links_ready()) {   // db/062 dar neimportuotas - kaip anksčiau
        $t = (string) site_setting('exam_waitlist_token', '');
        if ($new) {
            $t = exam_short_code();
            set_site_setting('exam_waitlist_token', $t);
        }
        return exam_code_ok($t) ? $t : '';
    }
    $l = link_current('exam_waitlist');
    if ($new) {
        $l = $l ? link_renew((int) $l['id']) : link_create('exam_waitlist');
    }
    return (string) link_token($l);
}

function exam_waitlist_url(): string
{
    $t = exam_waitlist_token();
    return $t !== '' ? abs_url('e/?' . $t) : '';
}

function exam_waitlist_is_open(): bool
{
    return site_setting('exam_waitlist_open', '1') !== '0' && reg_on('exam');
}

/** Laukiantys (dar neįtraukti į protokolą), seniausi viršuje */
function exam_waitlist_pending(): array
{
    return exam_waitlist_ready() ? q_all('SELECT * FROM exam_waitlist WHERE event_id IS NULL AND removed_at IS NULL ORDER BY created_at, id') : [];
}

/**
 * Registracija į laukiančiuosius. Tas pats žmogus (vardas + gimimo data), dar laukiantis - neįrašomas antrą kartą.
 * Grąžina ['already' => bool, 'id' => int].
 */
function exam_waitlist_add(array $d): array
{
    $first = mb_substr(trim((string) $d['first_name']), 0, 80);
    $last = mb_substr(trim((string) $d['last_name']), 0, 80);
    $birth = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($d['birth_date'] ?? '')) ? $d['birth_date'] : null;
    $old = q_one('SELECT id FROM exam_waitlist WHERE event_id IS NULL AND removed_at IS NULL AND LOWER(first_name) = LOWER(?) AND LOWER(last_name) = LOWER(?) AND birth_date <=> ?',
        [$first, $last, $birth]);
    if ($old) {
        return ['already' => true, 'id' => (int) $old['id']];
    }
    q('INSERT INTO exam_waitlist (first_name, last_name, birth_date, club, kyu, phone, email, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$first, $last, $birth, mb_substr(trim((string) ($d['club'] ?? '')), 0, 120) ?: null, mb_substr(trim((string) ($d['kyu'] ?? '')), 0, 30) ?: null,
         mb_substr(trim((string) ($d['phone'] ?? '')), 0, 40) ?: null, mb_strtolower(mb_substr(trim((string) ($d['email'] ?? '')), 0, 190)) ?: null,
         mb_substr(trim((string) ($d['note'] ?? '')), 0, 500) ?: null]);
    $id = (int) db()->lastInsertId();
    notify_staff('exam_registration', t('Užsiregistravo į egzaminą (laukiantieji)') . ': ' . $first . ' ' . $last,
        trim(($d['kyu'] ?? '') . ' · ' . ($d['club'] ?? ''), ' ·'), url('admin/egzaminai.php#laukiantieji'));
    return ['already' => false, 'id' => $id];
}

/**
 * Įtraukti laukiantįjį į egzamino protokolą ir išsiųsti laišką (data, laikas, vieta, kaina pagal diržą, mokėjimas, asmeninė nuoroda).
 * Grąžina ['ok' => bool, 'mailed' => bool].
 */
function exam_waitlist_assign(int $id, array $event, string $from): array
{
    $w = q_one('SELECT * FROM exam_waitlist WHERE id = ? AND event_id IS NULL AND removed_at IS NULL', [$id]);
    if (!$w) {
        return ['ok' => false, 'mailed' => false];
    }
    exam_protocol($event);
    $name = trim($w['first_name'] . ' ' . $w['last_name']);
    $rid = exam_protocol_add($event, ['name' => $name, 'birth_date' => $w['birth_date'], 'kyu' => $w['kyu'], 'club' => $w['club']]);
    if (exam_v40_ready() && $w['phone']) {
        q('UPDATE exam_protocol_rows SET phone = ? WHERE id = ?', [$w['phone'], $rid]);
    }
    q('UPDATE exam_waitlist SET event_id = ?, row_id = ?, assigned_at = NOW() WHERE id = ?', [(int) $event['id'], $rid, $id]);
    $mailed = false;
    if ($w['email'] && filter_var($w['email'], FILTER_VALIDATE_EMAIL)) {
        $row = q_one('SELECT * FROM exam_protocol_rows WHERE id = ?', [$rid]);
        $link = $row ? exam_row_url($row) : null;
        $price = exam_price_for($w['kyu']);
        $when = fmt_date($event['starts_on'], true) . ($event['start_time'] ? ', ' . fmt_time($event['start_time']) : '');
        $body = t('Sveiki, {n}!', ['n' => $w['first_name']]) . "\n\n"
            . t('Jūs įtraukti į egzaminą') . ': ' . $event['title'] . "\n"
            . t('Kada') . ': ' . $when . "\n"
            . ($event['location'] ? t('Kur') . ': ' . $event['location'] . "\n" : '')
            . ($w['kyu'] ? t('Laikomas') . ': ' . $w['kyu'] . "\n" : '')
            . t('Kaina') . ': ' . ($price !== null ? number_format($price, 2, ',', '') . ' €' : t('pasakys treneris')) . "\n"
            . (exam_pay_info() !== '' ? "\n" . t('Kaip sumokėti') . ":\n" . exam_pay_info() . "\n" : '')
            . ($link ? "\n" . t('Jūsų egzamino puslapis (programa, testas, rezultatas):') . "\n" . $link . "\n" : '')
            . "\n" . $from;
        $mailed = send_mail($w['email'], t('Egzaminas') . ': ' . $event['title'] . ' · ' . fmt_date($event['starts_on'], true), $body);
        if ($mailed) {
            q('UPDATE exam_waitlist SET mailed_at = NOW() WHERE id = ?', [$id]);
        }
    }
    return ['ok' => true, 'mailed' => $mailed];
}

