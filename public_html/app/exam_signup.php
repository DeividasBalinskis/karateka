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

/** Egzamino registracijos nuoroda (sukuriama, jei $create) */
function exam_signup_link(int $eventId, bool $create = false): ?array
{
    if (!exam_signup_ready()) {
        return null;
    }
    $l = q_one('SELECT * FROM exam_signup_links WHERE event_id = ?', [$eventId]);
    if (!$l && $create) {
        q('INSERT IGNORE INTO exam_signup_links (event_id, token, is_open) VALUES (?, ?, 1)', [$eventId, bin2hex(random_bytes(12))]);
        $l = q_one('SELECT * FROM exam_signup_links WHERE event_id = ?', [$eventId]);
    }
    return $l ?: null;
}

function exam_signup_url(array $link): string
{
    return abs_url('egzamino-registracija.php?k=' . $link['token']);
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
    $old = q_one('SELECT * FROM exam_signups WHERE event_id = ? AND LOWER(TRIM(CONCAT(first_name, " ", last_name))) = LOWER(?) AND (birth_date <=> ? OR birth_date IS NULL) ORDER BY signed_at IS NULL, id LIMIT 1',
        [$eid, $name, $birth]);
    if (!$old && $email) {
        $old = q_one('SELECT * FROM exam_signups WHERE event_id = ? AND email = ?', [$eid, $email]);
    }
    if ($old) {
        q('UPDATE exam_signups SET first_name = ?, last_name = ?, birth_date = ?, phone = COALESCE(?, phone), signed_at = NOW() WHERE id = ?', [$first, $last, $birth, $phone, (int) $old['id']]);
        $sid = (int) $old['id'];
    } else {
        q('INSERT INTO exam_signups (event_id, email, first_name, last_name, birth_date, phone, signed_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [$eid, $email ? mb_substr($email, 0, 190) : null, $first, $last, $birth, $phone]);
        $sid = (int) db()->lastInsertId();
    }
    // Protokolo eilutė: susieta anksčiau, arba tas pats žmogus jau įrašytas trenerio (vardas + gimimo data), arba nauja
    $rid = $old && $old['row_id'] ? (int) $old['row_id'] : 0;
    $exists = $rid ? q_one('SELECT id FROM exam_protocol_rows WHERE id = ? AND event_id = ?', [$rid, $eid]) : null;
    if (!$exists) {
        $exists = q_one('SELECT id FROM exam_protocol_rows WHERE event_id = ? AND deleted_at IS NULL AND LOWER(TRIM(name)) = LOWER(?) AND (birth_date <=> ? OR birth_date IS NULL) ORDER BY id LIMIT 1',
            [$eid, $name, $birth]);
        $rid = $exists ? (int) $exists['id'] : 0;
    }
    if ($exists) {
        q('UPDATE exam_protocol_rows SET name = ?, birth_date = COALESCE(?, birth_date), deleted_at = NULL WHERE id = ?', [mb_substr($name, 0, 160), $birth, $rid]);
    } else {
        exam_protocol($event);   // antraštė (jei protokolo dar nebuvo)
        $rid = exam_protocol_add($event, ['name' => $name, 'birth_date' => $birth]);
    }
    if ($phone && exam_v40_ready()) {
        q('UPDATE exam_protocol_rows SET phone = ? WHERE id = ?', [$phone, $rid]);
    }
    q('UPDATE exam_signups SET row_id = ? WHERE id = ?', [$rid, $sid]);
    $new = !$old || !$old['signed_at'];
    if ($new) {
        notify_staff('exam_registration', t('Užsiregistravo į egzaminą (per nuorodą)') . ': ' . $name,
            $event['title'] . ' · ' . fmt_date($event['starts_on'], true) . ($phone ? ' · ' . $phone : ''), url('admin/egzamino-protokolas.php?id=' . $eid));
    }
    return ['new' => $new, 'row_id' => $rid];
}

// ---------- Asmeninis egzamino puslapis be paskyros (mano-egzaminas.php?t=) ----------

/** Protokolo eilutės asmeninis raktas (sukuriamas pirmą kartą) */
function exam_row_token(array $row): ?string
{
    if (!exam_v40_ready()) {
        return null;
    }
    if (!empty($row['token'])) {
        return (string) $row['token'];
    }
    q('UPDATE exam_protocol_rows SET token = ? WHERE id = ? AND token IS NULL', [bin2hex(random_bytes(10)), (int) $row['id']]);
    return (string) q_value('SELECT token FROM exam_protocol_rows WHERE id = ?', [(int) $row['id']]);
}

function exam_row_url(array $row): ?string
{
    $t = exam_row_token($row);
    return $t ? abs_url('mano-egzaminas.php?t=' . $t) : null;
}

function exam_row_by_token(string $token): ?array
{
    if (!exam_v40_ready() || !preg_match('/^[a-f0-9]{20}$/', $token)) {
        return null;
    }
    return q_one('SELECT * FROM exam_protocol_rows WHERE token = ? AND deleted_at IS NULL', [$token]);
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
function exam_kyu_level(?string $kyu): ?int
{
    $k = mb_strtolower(trim((string) $kyu));
    if ($k === '') {
        return null;
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
        if (isset(BELTS[(int) $lvl]) && is_numeric($v) && (float) $v > 0) {
            $out[(int) $lvl] = round((float) $v, 2);
        }
    }
    return $out;
}

function exam_price_for(?string $kyu): ?float
{
    $lvl = exam_kyu_level($kyu);
    return $lvl ? (exam_prices()[$lvl] ?? null) : null;
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
