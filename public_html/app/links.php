<?php
// C3: visos slaptos nuorodos - vienas modelis (db/062 access_links) ir vienas puslapis (Valdymas → „Visos nuorodos“, admin/nuorodos.php).
// Tik šis failas kuria ir tikrina slaptas nuorodas (docs/standartai.md, 5 skyrius).
// - Duomenų bazėje - tik rakto „pirštų atspaudas“ (sha256) ir užrakinta kopija (AES-GCM; raktas - config.php 'links_key').
// - Kiekviena nuoroda: Kopijuoti · ↻ Nauja nuoroda · Išjungti (link_box) ir „sukurta …, paskutinį kartą naudota …“.
// - Senos nuorodos (site_settings, tournaments.*_token, exam_signup_links, exam_protocol_rows.token, quizzes.share_token) veikia toliau:
//   pirmą kartą prireikus jos perkeliamos čia (links_import, link_legacy_find). Senos reikšmės niekada netrinamos.
// - Nuoroda, kuri gali keisti duomenis (write), pirmiausia paklausia vardo; vardas išsaugomas su kiekvienu pakeitimu.

/** Sekretoriato nuoroda veikia iki 2 dienų po varžybų pabaigos (sprendimas v1.52; Deividas 2026-11-25: „nesvarbu“ - lieka) */
const LINK_SEKR_DAYS = 2;
/** Asmeninė egzamino nuoroda - 30 dienų po egzamino (sprendimas 2026-11-20; tikrina mano-egzaminas.php) */
const LINK_EXAM_ROW_DAYS = 30;
/** Neteisingų nuorodų iš vieno IP per valandą - daugiau netikrinama (skaičiuojama duomenų bazėje) */
const LINK_WRONG_PER_HOUR = 100;

/**
 * Nuorodų rūšys. label - pavadinimas; owner - kam priklauso (tournament / event / exam_row / quiz; null - viena visai svetainei);
 * write - gali keisti duomenis (klausia vardo); path - adresas be rakto; short - trumpas 6 ženklų kodas (/e/?, /p/?, sprendimas 2026-11-22);
 * many - kelios veikiančios vienu metu (LTKF redaktoriai); manage - kas kuria ir išjungia (staff - treneris ar adminas, admin - tik adminas);
 * where - kur ji rodoma ({o} - savininko id).
 */
const LINK_KINDS = [
    'sekr'          => ['label' => 'Budo sekretoriatas', 'owner' => 'tournament', 'write' => true, 'path' => 'varzybos-sekretoriatas.php?k=', 'manage' => 'staff', 'where' => 'admin/varzybu-sistema.php?id={o}&sekr=1'],
    'awards'        => ['label' => 'Budo apdovanojimų planšetė', 'owner' => 'tournament', 'write' => true, 'path' => 'varzybos-apdovanojimai.php?k=', 'manage' => 'staff', 'where' => 'admin/varzybu-sistema.php?id={o}'],
    'live'          => ['label' => 'Budo žiūrovams ir ekranams', 'owner' => 'tournament', 'path' => 'varzybos-gyvai.php?k=', 'manage' => 'staff', 'where' => 'admin/varzybu-sistema.php?id={o}'],
    'reg'           => ['label' => 'Budo registracija kitiems klubams', 'owner' => 'tournament', 'path' => 'varzybos-registracija.php?k=', 'manage' => 'staff', 'where' => 'admin/varzybu-sistema.php?id={o}'],
    'demo'          => ['label' => 'Budo bandymas', 'owner' => null, 'write' => true, 'path' => 'varzybos-bandymas.php?k=', 'manage' => 'admin', 'where' => 'admin/varzybos.php#bandymas'],
    'cam'           => ['label' => 'Budo transliacijos telefonai (QR)', 'owner' => null, 'path' => 'varzybos-kamera.php?k=', 'manage' => 'admin', 'where' => 'admin/varzybu-transliacija.php'],
    'exam_signup'   => ['label' => 'Egzamino registracija', 'owner' => 'event', 'short' => true, 'path' => 'e/?', 'manage' => 'staff', 'where' => 'admin/egzamino-protokolas.php?id={o}'],
    'exam_waitlist' => ['label' => 'Egzaminų registracija (laukiantieji)', 'owner' => null, 'short' => true, 'path' => 'e/?', 'manage' => 'staff', 'where' => 'admin/egzaminai.php#laukiantieji'],
    'exam_row'      => ['label' => 'Mano egzaminas (asmeninė)', 'owner' => 'exam_row', 'path' => 'mano-egzaminas.php?k=', 'manage' => 'staff', 'where' => ''],
    'quiz'          => ['label' => 'Testas be paskyros', 'owner' => 'quiz', 'path' => 'testas-svecias.php?k=', 'manage' => 'staff', 'where' => 'admin/testai.php?id={o}#nuoroda'],
    'just'          => ['label' => 'Pateisinimų prašymai', 'owner' => null, 'short' => true, 'path' => 'p/?', 'manage' => 'admin', 'where' => 'admin/pateisinimai.php'],
    'ltkf_editor'   => ['label' => 'LTKF redaktorius', 'owner' => null, 'write' => true, 'many' => true, 'path' => 'ltkf-redaktorius.php?k=', 'manage' => 'admin', 'where' => 'admin/ltkf-perziura.php#redaktoriai'],
    'ltkf_review'   => ['label' => 'LTKF peržiūra', 'owner' => null, 'path' => 'ltkf-perziura.php?k=', 'manage' => 'admin', 'where' => 'admin/ltkf-perziura.php'],
];

/** Lentelė yra (db/062). Pirmą kartą - perkeliamos senos nuorodos. */
function links_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT 1 FROM access_links LIMIT 1');
            $ok = true;
        } catch (PDOException $ex) {
            $ok = false;   // dar neimportuotas db/062 - nuorodos tikrinamos senu būdu (link_legacy_find)
        }
        if ($ok && (string) q_value("SELECT value FROM site_settings WHERE name = 'links_imported'") === '') {
            links_import();
            set_site_setting('links_imported', date('Y-m-d H:i'));
        }
    }
    return $ok;
}

function link_def(string $kind): array
{
    return LINK_KINDS[$kind] ?? [];
}

function link_kind_label(string $kind): string
{
    return t(LINK_KINDS[$kind]['label'] ?? $kind);
}

// ---------- Užrakinta kopija: kad nuorodą būtų galima nukopijuoti dar kartą ----------

function link_lock_key(): string
{
    $k = (string) config('links_key', '');
    if ($k === '') {
        // Be atskiro rakto - iš serverio nustatymų (pakeitus DB slaptažodį, senų nuorodų nukopijuoti nebus galima - tik „↻ Nauja nuoroda“)
        $k = 'karateka-links|' . json_encode(config('db'));
    }
    return hash('sha256', $k, true);
}

function link_lock(string $token): ?string
{
    if (!function_exists('openssl_encrypt')) {
        return null;
    }
    $iv = random_bytes(12);
    $tag = '';
    $c = openssl_encrypt($token, 'aes-256-gcm', link_lock_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return $c === false ? null : base64_encode($iv . $tag . $c);
}

function link_unlock(?string $lock): ?string
{
    if (!$lock || !function_exists('openssl_decrypt')) {
        return null;
    }
    $b = base64_decode($lock, true);
    if ($b === false || strlen($b) < 29) {
        return null;
    }
    $p = openssl_decrypt(substr($b, 28), 'aes-256-gcm', link_lock_key(), OPENSSL_RAW_DATA, substr($b, 0, 12), substr($b, 12, 16));
    return $p === false ? null : $p;
}

/** Naujas raktas: 32 atsitiktiniai baitai (64 ženklai) arba trumpas kodas be painių ženklų (0/o, 1/l/i) */
function link_secret(string $kind): string
{
    if (empty(link_def($kind)['short'])) {
        return bin2hex(random_bytes(32));
    }
    $abc = 'abcdefghjkmnpqrstuvwxyz23456789';
    for ($try = 0; $try < 30; $try++) {
        $c = '';
        for ($i = 0; $i < 6; $i++) {
            $c .= $abc[random_int(0, strlen($abc) - 1)];
        }
        if (!link_code_taken($c)) {
            return $c;
        }
    }
    return bin2hex(random_bytes(12));
}

/** Trumpas kodas jau naudojamas (nauja lentelė arba senos vietos) */
function link_code_taken(string $c): bool
{
    try {
        if (q_value('SELECT 1 FROM access_links WHERE token_hash = ?', [hash('sha256', $c)])) {
            return true;
        }
    } catch (PDOException $ex) {
        // dar neimportuotas db/062
    }
    try {
        return (bool) q_value("SELECT 1 FROM site_settings WHERE name IN ('exam_waitlist_token', 'just_token') AND value = ?", [$c])
            || (exam_signup_ready() && q_value('SELECT 1 FROM exam_signup_links WHERE token = ?', [$c]));
    } catch (PDOException $ex) {
        return false;
    }
}

// ---------- Kūrimas, keitimas, išjungimas ----------

function link_get(int $id): ?array
{
    return links_ready() ? (q_one('SELECT * FROM access_links WHERE id = ?', [$id]) ?: null) : null;
}

/** Veikianti (neišjungta) nuoroda: naujausia šiam savininkui */
function link_current(string $kind, ?int $owner = null): ?array
{
    if (!links_ready()) {
        return null;
    }
    return q_one('SELECT * FROM access_links WHERE kind = ? AND owner_id <=> ? AND revoked_at IS NULL ORDER BY id DESC LIMIT 1', [$kind, $owner]) ?: null;
}

/** Visos veikiančios (LTKF redaktoriai ir pan.) */
function link_list(string $kind, ?int $owner = null, bool $withOff = false): array
{
    if (!links_ready()) {
        return [];
    }
    return q_all('SELECT * FROM access_links WHERE kind = ? AND owner_id <=> ?' . ($withOff ? '' : ' AND revoked_at IS NULL') . ' ORDER BY id', [$kind, $owner]);
}

/**
 * Nauja nuoroda. Vienos rūšies ir savininko ankstesnė (jei rūšis ne „many“) pažymima „pakeista“ - ji nebeveiks.
 * Grąžina eilutę su ['token' => raktas].
 */
function link_create(string $kind, ?int $owner = null, string $label = '', ?string $expires = null, ?int $replaces = null): ?array
{
    $def = link_def($kind);
    if (!$def || !links_ready()) {
        return null;
    }
    $me = function_exists('current_account') ? current_account() : null;
    for ($try = 0; $try < 3; $try++) {
        $token = link_secret($kind);
        try {
            q('INSERT INTO access_links (kind, owner_id, label, token_hash, token_lock, can_write, created_by, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$kind, $owner, $label !== '' ? mb_substr($label, 0, 120) : null, hash('sha256', $token), link_lock($token), !empty($def['write']) ? 1 : 0,
                 $me ? (int) $me['id'] : null, $expires]);
            break;
        } catch (PDOException $ex) {
            $token = null;   // toks raktas jau yra (beveik neįmanoma) - kitas
        }
    }
    if (empty($token)) {
        return null;
    }
    $id = (int) db()->lastInsertId();
    if ($replaces) {
        q('UPDATE access_links SET revoked_at = NOW(), replaced_by = ? WHERE id = ? AND revoked_at IS NULL', [$id, $replaces]);
    }
    if (empty($def['many'])) {
        q('UPDATE access_links SET revoked_at = NOW(), replaced_by = ? WHERE kind = ? AND owner_id <=> ? AND id <> ? AND revoked_at IS NULL', [$id, $kind, $owner, $id]);
    }
    return (q_one('SELECT * FROM access_links WHERE id = ?', [$id]) ?: []) + ['token' => $token];
}

/**
 * Veikianti nuoroda arba nauja - nuorodoms, kurios atsiranda kartu su savininku (varžybų žiūrovams, registracijai, apdovanojimams,
 * asmeninė egzamino). Jei nuoroda buvo „Išjungta“ - nauja nekuriama (null), kol kas nors paspaus „Sukurti nuorodą“.
 */
function link_ensure(string $kind, ?int $owner = null): ?array
{
    if ($l = link_current($kind, $owner)) {
        return $l;
    }
    if (!links_ready() || q_value('SELECT 1 FROM access_links WHERE kind = ? AND owner_id <=> ? LIMIT 1', [$kind, $owner])) {
        return null;
    }
    return link_create($kind, $owner);
}

/** „↻ Nauja nuoroda“: ta pati rūšis, savininkas ir pavadinimas; senoji nebeveiks */
function link_renew(int $id): ?array
{
    $old = link_get($id);
    if (!$old) {
        return null;
    }
    return link_create((string) $old['kind'], $old['owner_id'] !== null ? (int) $old['owner_id'] : null, (string) $old['label'], $old['expires_at'] ?: null, $id);
}

/** „Išjungti“: nuoroda nebeveiks; eilutė lieka (matyti, kas ir kada buvo) */
function link_off(int $id): void
{
    if (links_ready()) {
        q('UPDATE access_links SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL', [$id]);
    }
}

/** Išjungti visas šio savininko tos rūšies nuorodas */
function link_off_kind(string $kind, ?int $owner = null): void
{
    if (links_ready()) {
        q('UPDATE access_links SET revoked_at = NOW() WHERE kind = ? AND owner_id <=> ? AND revoked_at IS NULL', [$kind, $owner]);
    }
}

/** Savininkas ištrintas (varžybos, egzaminas): jo nuorodos išjungiamos */
function links_owner_gone(array $kinds, array $ids): void
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids || !$kinds || !links_ready()) {
        return;
    }
    q('UPDATE access_links SET revoked_at = NOW() WHERE revoked_at IS NULL AND kind IN (' . implode(',', array_fill(0, count($kinds), '?')) . ') AND owner_id IN (' . implode(',', $ids) . ')', array_values($kinds));
}

/** Raktas iš užrakintos kopijos (null - nukopijuoti negalima, reikia „↻ Nauja nuoroda“) */
function link_token(?array $row): ?string
{
    if (!$row) {
        return null;
    }
    if (!empty($row['token'])) {
        return (string) $row['token'];
    }
    return link_unlock($row['token_lock'] ?? null);
}

/** Pilnas adresas nuorodai (laiškams, kopijavimui) */
function link_url(?array $row, string $suffix = ''): string
{
    $tok = link_token($row);
    return $tok !== null && $tok !== '' ? abs_url(link_def((string) $row['kind'])['path'] . $tok . $suffix) : '';
}

// ---------- Tikrinimas ----------

/** Kodėl paskutinė patikra nepavyko: wrong / off / replaced / expired ('' - pavyko) */
function link_reason(?string $set = null): string
{
    static $r = '';
    if ($set !== null) {
        $r = $set;
    }
    return $r;
}

/** Iki kada veikia: aiški data arba rūšies taisyklė (sekretoriatas - 2 d. po varžybų, asmeninė egzamino - 30 d. po egzamino) */
function link_until(array $row): ?string
{
    $until = !empty($row['expires_at']) ? (string) $row['expires_at'] : null;
    $rule = null;
    try {
        if ($row['kind'] === 'sekr' && $row['owner_id'] !== null) {
            $end = (string) q_value('SELECT COALESCE(ends_on, starts_on) FROM tournaments WHERE id = ?', [(int) $row['owner_id']]);
            $rule = $end !== '' ? date('Y-m-d 00:00:00', strtotime($end . ' +' . (LINK_SEKR_DAYS + 1) . ' days')) : date('Y-m-d H:i:s', 0);
        } elseif ($row['kind'] === 'exam_row' && $row['owner_id'] !== null) {
            $end = (string) q_value('SELECT COALESCE(e.ends_on, e.starts_on) FROM exam_protocol_rows r JOIN events e ON e.id = r.event_id WHERE r.id = ?', [(int) $row['owner_id']]);
            $rule = $end !== '' ? date('Y-m-d 00:00:00', strtotime($end . ' +' . (LINK_EXAM_ROW_DAYS + 1) . ' days')) : null;
        }
    } catch (PDOException $ex) {
        $rule = null;
    }
    if ($rule !== null && ($until === null || $rule < $until)) {
        $until = $rule;
    }
    return $until;
}

/** Būsena: on / off / replaced / expired */
function link_state(array $row): string
{
    if (!empty($row['revoked_at'])) {
        return !empty($row['replaced_by']) ? 'replaced' : 'off';
    }
    $until = link_until($row);
    // Asmeninę egzamino nuorodą po 30 dienų puslapis rodo su paaiškinimu (be asmens duomenų), todėl ji „veikia“
    if ($until !== null && $until <= date('Y-m-d H:i:s') && !($row['kind'] === 'exam_row' && empty($row['expires_at']))) {
        return 'expired';
    }
    return 'on';
}

function link_throttled(): bool
{
    try {
        return (int) q_value("SELECT COUNT(*) FROM login_attempts WHERE email = '#link' AND ip = ? AND success = 0 AND attempted_at > NOW() - INTERVAL 1 HOUR", [client_ip()]) >= LINK_WRONG_PER_HOUR;
    } catch (PDOException $ex) {
        return false;
    }
}

function link_wrong_log(): void
{
    try {
        q("INSERT INTO login_attempts (email, ip, success) VALUES ('#link', ?, 0)", [client_ip()]);
    } catch (PDOException $ex) {
    }
}

/**
 * Nuoroda pagal raktą iš adreso. $kinds - viena rūšis ar kelios (/e/? - egzamino registracija ir laukiantieji).
 * Grąžina eilutę arba null (priežastis - link_reason()). Paskutinis naudojimas įrašomas ne dažniau kaip kas minutę.
 */
function link_find($kinds, string $token): ?array
{
    $kinds = (array) $kinds;
    link_reason('');
    $token = trim($token);
    if (!preg_match('/^[A-Za-z0-9_-]{4,128}$/', $token) || link_throttled()) {
        link_reason('wrong');
        return null;
    }
    if (!links_ready()) {
        // db/062 dar neimportuotas: senu būdu
        foreach ($kinds as $k) {
            $l = link_legacy_lookup($k, $token);
            if ($l && $l['on'] && $k === 'sekr' && !varz_sekr_valid_date((int) $l['owner'])) {
                link_reason('expired');
                return null;
            }
            if ($l && $l['on']) {
                return ['id' => 0, 'kind' => $k, 'owner_id' => $l['owner'], 'label' => $l['label'], 'token_hash' => hash('sha256', $token), 'revoked_at' => null,
                        'replaced_by' => null, 'expires_at' => null, 'can_write' => empty(link_def($k)['write']) ? 0 : 1];
            }
        }
        link_reason('wrong');
        return null;
    }
    $row = q_one('SELECT * FROM access_links WHERE token_hash = ?', [hash('sha256', $token)]);
    if (!$row) {
        foreach ($kinds as $k) {
            if ($row = link_legacy_find($k, $token)) {
                break;
            }
        }
    }
    if (!$row || !in_array($row['kind'], $kinds, true)) {
        link_wrong_log();
        link_reason('wrong');
        return null;
    }
    $state = link_state($row);
    if ($state !== 'on') {
        link_reason($state);
        return null;
    }
    link_touch($row);
    return $row;
}

/** Sesija, pradėta su nuoroda (saugomas tik pirštų atspaudas): ar nuoroda vis dar veikia. $owner - tik šio savininko. */
function link_alive(string $kind, string $hash, ?int $owner = null): ?array
{
    if ($hash === '' || !preg_match('/^[a-f0-9]{64}$/', $hash)) {
        return null;
    }
    if (!links_ready()) {
        return link_legacy_alive($kind, $hash, $owner) ? ['id' => 0, 'kind' => $kind, 'owner_id' => $owner, 'token_hash' => $hash] : null;
    }
    $row = q_one('SELECT * FROM access_links WHERE token_hash = ? AND kind = ?', [$hash, $kind]);
    if (!$row || ($owner !== null && (int) $row['owner_id'] !== $owner) || link_state($row) !== 'on') {
        return null;
    }
    link_touch($row);
    return $row;
}

function link_touch(array $row): void
{
    if (empty($row['id'])) {
        return;
    }
    if (empty($row['last_used_at']) || strtotime((string) $row['last_used_at']) < time() - 60) {
        try {
            q('UPDATE access_links SET uses = uses + 1, last_used_at = NOW() WHERE id = ?', [(int) $row['id']]);
        } catch (PDOException $ex) {
        }
    }
}

// ---------- Vardas nuorodoms, kurios gali keisti (taisyklė 6) ----------

/** Vardas, įrašytas atidarius šią nuorodą šiame įrenginyje ('' - dar neįrašė) */
function link_name(array $row): string
{
    return (string) ($_SESSION['link_names'][(string) $row['token_hash']] ?? '');
}

function link_name_set(array $row, string $name): void
{
    $_SESSION['link_names'][(string) $row['token_hash']] = mb_substr(trim(preg_replace('/\s+/u', ' ', $name)), 0, 80);
}

function link_name_clear(array $row): void
{
    unset($_SESSION['link_names'][(string) $row['token_hash']]);
}

/** Vardo formos laukelis ir patikra: grąžina klaidą arba '' */
function link_name_check(string $name): string
{
    return mb_strlen(trim($name)) < 2 ? t('Įrašykite savo vardą - kad būtų aišku, kas ką pakeitė.') : '';
}

// ---------- Nuoroda neveikia (taisyklė 12) ----------

function link_dead_text(): string
{
    return t('Nuoroda neveikia. Ji išjungta, pasibaigė arba pakeista. Paprašykite naujos nuorodos to, kas jums ją davė.');
}

/** Vienas puslapis neveikiančiai nuorodai (JSON užklausoms - JSON) */
function link_dead(bool $json = false): void
{
    http_response_code(404);
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        exit(json_encode(['ok' => false, 'link' => link_reason() ?: 'wrong', 'error' => link_dead_text()], JSON_UNESCAPED_UNICODE));
    }
    page_start(t('Nuoroda neveikia'), ['narrow' => true, 'noindex' => true, 'bare' => true]);
    echo '<div class="panel card link-dead"><h1>' . te('Nuoroda neveikia') . '</h1><p class="muted">'
        . te('Ji išjungta, pasibaigė arba pakeista. Paprašykite naujos nuorodos to, kas jums ją davė.') . '</p></div>';
    page_end();
    exit;
}

// ---------- Kas gali tvarkyti ----------

function link_can_manage(string $kind, ?array $a = null): bool
{
    $m = link_def($kind)['manage'] ?? 'admin';
    return $m === 'staff' ? is_staff($a) : is_admin($a);
}

/** Kam priklauso: varžybų, egzamino, testo pavadinimas ar žmogaus vardas (sąrašui) */
function link_owner_name(array $row): string
{
    if ($row['owner_id'] === null) {
        return (string) ($row['label'] ?? '');
    }
    $id = (int) $row['owner_id'];
    try {
        switch (link_def((string) $row['kind'])['owner'] ?? '') {
            case 'tournament':
                $r = q_one('SELECT title, starts_on FROM tournaments WHERE id = ?', [$id]);
                return $r ? $r['title'] . ' · ' . fmt_date($r['starts_on'], true) : '#' . $id;
            case 'event':
                $r = q_one('SELECT title, starts_on FROM events WHERE id = ?', [$id]);
                return $r ? $r['title'] . ' · ' . fmt_date($r['starts_on'], true) : '#' . $id;
            case 'exam_row':
                $r = q_one('SELECT r.name, e.starts_on FROM exam_protocol_rows r JOIN events e ON e.id = r.event_id WHERE r.id = ?', [$id]);
                return $r ? $r['name'] . ' · ' . fmt_date($r['starts_on'], true) : '#' . $id;
            case 'quiz':
                $r = q_value('SELECT title FROM quizzes WHERE id = ?', [$id]);
                return $r ? (string) $r : '#' . $id;
        }
    } catch (PDOException $ex) {
    }
    return '#' . $id;
}

/** Kur ši nuoroda tvarkoma (savo puslapyje) */
function link_where(array $row): string
{
    $w = (string) (link_def((string) $row['kind'])['where'] ?? '');
    if ($row['kind'] === 'exam_row' && $row['owner_id'] !== null) {
        $ev = (int) q_value('SELECT event_id FROM exam_protocol_rows WHERE id = ?', [(int) $row['owner_id']]);
        return $ev ? 'admin/egzamino-protokolas.php?id=' . $ev : '';
    }
    return str_replace('{o}', (string) (int) $row['owner_id'], $w);
}

// ---------- Bendras blokas: Kopijuoti · ↻ Nauja nuoroda · Išjungti (taisyklė 5) ----------

/**
 * Nuorodos blokas valdymo puslapiams. Be veikiančios nuorodos - mygtukas „Sukurti nuorodą“ (taisyklė 7: kuriama tik mygtuku).
 * $opt: row (jau rasta eilutė), back (kur grįžti; numatyta - šis puslapis), id (adreso laukelio id), create (mygtuko tekstas),
 *       open (rodyti „Atidaryti ↗“), suffix (prie adreso), label (naujos nuorodos pavadinimas), compact (be „sukurta …“),
 *       class (adreso laukelio klasė).
 */
function link_box(string $kind, ?int $owner = null, array $opt = []): string
{
    if (!links_ready()) {
        return '<p class="muted small">' . te('Atnaujinkite duomenų bazę (db/062).') . '</p>';
    }
    $row = array_key_exists('row', $opt) ? $opt['row'] : link_current($kind, $owner);
    $back = (string) ($opt['back'] ?? ($_SERVER['REQUEST_URI'] ?? ''));
    $can = link_can_manage($kind);
    $form = function (string $action, string $text, string $cls = 'btn btn-sm', string $confirm = '') use ($kind, $owner, $row, $back, $opt): string {
        return '<form method="post" action="' . e(url('admin/nuorodos.php')) . '" class="lk-form">' . csrf_field()
            . '<input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="kind" value="' . e($kind) . '">'
            . ($owner !== null ? '<input type="hidden" name="owner" value="' . (int) $owner . '">' : '')
            . ($row ? '<input type="hidden" name="id" value="' . (int) $row['id'] . '">' : '')
            . (!empty($opt['label']) ? '<input type="hidden" name="label" value="' . e((string) $opt['label']) . '">' : '')
            . '<input type="hidden" name="back" value="' . e($back) . '">'
            . '<button class="' . e($cls) . '" type="submit" data-lk="' . e($action) . '"' . ($confirm !== '' ? ' onclick="return confirm(' . e(json_encode($confirm, JSON_UNESCAPED_UNICODE)) . ')"' : '') . '>' . e($text) . '</button></form>';
    };
    $h = '<div class="lk" data-link-kind="' . e($kind) . '"' . ($row ? ' data-link-id="' . (int) $row['id'] . '"' : '') . '>';
    if (!$row) {
        $h .= $can ? '<div class="lk-row">' . $form('new', (string) ($opt['create'] ?? t('Sukurti nuorodą')), 'btn btn-primary btn-sm') . '</div>' : '<p class="muted small">' . te('Nuorodos dar nėra.') . '</p>';
        return $h . '</div>' . link_box_script();
    }
    $url = link_url($row, (string) ($opt['suffix'] ?? ''));
    $state = link_state($row);
    $h .= '<div class="lk-row">';
    if ($url !== '') {
        $h .= '<input class="sel sp-url lk-url' . (!empty($opt['class']) ? ' ' . e((string) $opt['class']) : '') . '" readonly value="' . e($url) . '"' . (!empty($opt['id']) ? ' id="' . e((string) $opt['id']) . '"' : '') . ' aria-label="' . e(link_kind_label($kind)) . '" onclick="this.select()">'
            . '<button type="button" class="btn btn-sm" data-link-copy="' . e($url) . '">' . te('Kopijuoti') . '</button>';
        if (!empty($opt['open'])) {
            $h .= '<a class="btn btn-sm" href="' . e($url) . '" target="_blank" rel="noopener">' . te('Atidaryti ↗') . '</a>';
        }
    } else {
        $h .= '<span class="muted small">' . te('Šios nuorodos nukopijuoti nebegalima - sukurkite naują.') . '</span>';
    }
    if ($can) {
        $h .= $form('renew', t('↻ Nauja nuoroda'), 'btn btn-sm', t('Sukurti naują nuorodą? Sena nebeveiks.'))
            . $form('off', t('Išjungti'), 'btn btn-danger btn-sm', t('Išjungti nuorodą? Su ja nebebus galima prisijungti.'));
    }
    $h .= '</div>';
    if (empty($opt['compact'])) {
        $h .= '<p class="muted small lk-meta">' . e(link_meta_text($row, $state)) . '</p>';
    }
    return $h . '</div>' . link_box_script();
}

/** „Sukurta …, paskutinį kartą naudota …“ (+ iki kada galioja) */
function link_meta_text(array $row, ?string $state = null): string
{
    $state = $state ?? link_state($row);
    $p = [t('sukurta') . ' ' . fmt_date(substr((string) $row['created_at'], 0, 10), true) . ' ' . substr((string) $row['created_at'], 11, 5)];
    if (!empty($row['created_by']) && ($who = q_value('SELECT CONCAT(first_name, " ", last_name) FROM accounts WHERE id = ?', [(int) $row['created_by']]))) {
        $p[0] .= ' (' . $who . ')';
    }
    $p[] = !empty($row['last_used_at'])
        ? t('paskutinį kartą naudota') . ' ' . fmt_date(substr((string) $row['last_used_at'], 0, 10), true) . ' ' . substr((string) $row['last_used_at'], 11, 5)
        : t('dar nenaudota');
    $until = link_until($row);
    if ($state === 'expired') {
        $p[] = t('pasibaigė') . ' ' . fmt_date(substr((string) $until, 0, 10), true);
    } elseif ($until !== null && $row['kind'] !== 'exam_row') {
        $p[] = t('veikia iki') . ' ' . fmt_date(date('Y-m-d', strtotime((string) $until) - 1), true);
    }
    return implode(', ', $p);
}

/** Kopijavimo mygtukas (vieną kartą puslapyje) */
function link_box_script(): string
{
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;
    return '<script>document.addEventListener("click",function(e){var b=e.target.closest&&e.target.closest("[data-link-copy]");if(!b)return;'
        . 'var t=b.getAttribute("data-link-copy"),o=b.textContent,ok=function(){b.textContent=' . json_encode(t('Nukopijuota ✓')) . ';setTimeout(function(){b.textContent=o;},1500);};'
        . 'var f=function(){var i=b.parentNode.querySelector(".lk-url");if(i){i.select();try{document.execCommand("copy");ok();}catch(x){}}};'
        . 'if(navigator.clipboard)navigator.clipboard.writeText(t).then(ok,f);else f();});</script>';
}

// ---------- Senos nuorodos: perkėlimas (nieko netrinant) ----------

/** Sena nuoroda pagal raktą: ['owner' => id|null, 'label' => '', 'on' => bool] arba null */
function link_legacy_lookup(string $kind, string $token): ?array
{
    $set = function (string $name) use ($token): bool {
        $v = (string) q_value('SELECT value FROM site_settings WHERE name = ?', [$name]);
        return $v !== '' && hash_equals($v, $token);
    };
    try {
        switch ($kind) {
            case 'sekr':
                $n = q_value("SELECT name FROM site_settings WHERE name LIKE 'varz\\_sekr\\_%' AND value = ?", [$token]);
                return $n ? ['owner' => (int) substr((string) $n, 10), 'label' => '', 'on' => true] : null;
            case 'live':
            case 'reg':
            case 'awards':
                $col = ['live' => 'share_token', 'reg' => 'reg_token', 'awards' => 'awards_token'][$kind];
                $id = tournaments_ready() ? q_value("SELECT id FROM tournaments WHERE $col = ?", [$token]) : null;
                return $id ? ['owner' => (int) $id, 'label' => '', 'on' => true] : null;
            case 'demo':
                return $set('taika_demo_token') ? ['owner' => null, 'label' => '', 'on' => true] : null;
            case 'cam':
                return $set('varz_cam_key') ? ['owner' => null, 'label' => '', 'on' => true] : null;
            case 'exam_waitlist':
                return $set('exam_waitlist_token') ? ['owner' => null, 'label' => '', 'on' => true] : null;
            case 'just':
                return $set('just_token') ? ['owner' => null, 'label' => '', 'on' => true] : null;
            case 'ltkf_review':
                return $set('ltkf_review_token') ? ['owner' => null, 'label' => '', 'on' => true] : null;
            case 'exam_signup':
                $id = exam_signup_ready() ? q_value('SELECT event_id FROM exam_signup_links WHERE token = ?', [$token]) : null;
                return $id ? ['owner' => (int) $id, 'label' => '', 'on' => true] : null;
            case 'exam_row':
                $id = exam_v40_ready() ? q_value('SELECT id FROM exam_protocol_rows WHERE token = ? AND deleted_at IS NULL', [$token]) : null;
                return $id ? ['owner' => (int) $id, 'label' => '', 'on' => true] : null;
            case 'quiz':
                $id = q_value('SELECT id FROM quizzes WHERE share_token = ?', [$token]);
                return $id ? ['owner' => (int) $id, 'label' => '', 'on' => true] : null;
            case 'ltkf_editor':
                foreach ((array) json_decode((string) q_value("SELECT value FROM site_settings WHERE name = 'ltkf_editors'"), true) as $ed) {
                    if (is_array($ed) && (string) ($ed['t'] ?? '') !== '' && hash_equals((string) $ed['t'], $token)) {
                        return ['owner' => null, 'label' => (string) ($ed['n'] ?? ''), 'on' => !empty($ed['on'])];
                    }
                }
                return null;
        }
    } catch (PDOException $ex) {
        return null;   // senos lentelės dar nėra
    }
    return null;
}

/** Sesija su sena nuoroda, kol db/062 dar neimportuotas */
function link_legacy_alive(string $kind, string $hash, ?int $owner = null): bool
{
    $name = ['demo' => 'taika_demo_token', 'ltkf_review' => 'ltkf_review_token'][$kind] ?? ($kind === 'sekr' && $owner ? 'varz_sekr_' . $owner : '');
    if ($name === '') {
        return false;
    }
    try {
        $v = (string) q_value('SELECT value FROM site_settings WHERE name = ?', [$name]);
    } catch (PDOException $ex) {
        return false;
    }
    return $v !== '' && hash_equals(hash('sha256', $v), $hash) && ($kind !== 'sekr' || varz_sekr_valid_date((int) $owner));
}

/** Sena nuoroda, kurios dar nėra lentelėje: perkeliama (ta pati veikia toliau) ir grąžinama */
function link_legacy_find(string $kind, string $token): ?array
{
    $l = link_legacy_lookup($kind, $token);
    if (!$l) {
        return null;
    }
    $id = link_import_one($kind, $l['owner'], $token, $l['label'], $l['on']);
    if ($id && $l['on'] && empty(link_def($kind)['many'])) {
        // Sena reikšmė svarbesnė už anksčiau perkeltą tos pačios vietos (ją pakeitė senas kodas): ankstesnė - „pakeista“
        q('UPDATE access_links SET revoked_at = NOW(), replaced_by = ? WHERE kind = ? AND owner_id <=> ? AND id <> ? AND revoked_at IS NULL', [$id, $kind, $l['owner'], $id]);
    }
    return q_one('SELECT * FROM access_links WHERE token_hash = ?', [hash('sha256', $token)]) ?: null;
}

function link_import_one(string $kind, ?int $owner, string $token, string $label = '', bool $on = true): int
{
    if ($token === '') {
        return 0;
    }
    $st = q('INSERT IGNORE INTO access_links (kind, owner_id, label, token_hash, token_lock, can_write, legacy, revoked_at) VALUES (?, ?, ?, ?, ?, ?, 1, ' . ($on ? 'NULL' : 'NOW()') . ')',
        [$kind, $owner, $label !== '' ? mb_substr($label, 0, 120) : null, hash('sha256', $token), link_lock($token), empty(link_def($kind)['write']) ? 0 : 1]);
    return $st->rowCount() ? (int) db()->lastInsertId() : 0;
}

/** Visos senos nuorodos - į lentelę (vieną kartą; dar kartą - nieko nedubliuoja) */
function links_import(): void
{
    $try = function (callable $f): void {
        try {
            $f();
        } catch (PDOException $ex) {
            // tos senos lentelės ar stulpelio dar nėra
        }
    };
    $try(function () {
        foreach (q_all("SELECT name, value FROM site_settings WHERE name LIKE 'varz\\_sekr\\_%' AND value <> ''") as $r) {
            link_import_one('sekr', (int) substr((string) $r['name'], 10), (string) $r['value']);
        }
    });
    foreach (['live' => 'share_token', 'reg' => 'reg_token', 'awards' => 'awards_token'] as $kind => $col) {
        $try(function () use ($kind, $col) {
            foreach (q_all("SELECT id, $col AS t FROM tournaments WHERE $col IS NOT NULL AND $col <> ''") as $r) {
                link_import_one($kind, (int) $r['id'], (string) $r['t']);
            }
        });
    }
    foreach (['demo' => 'taika_demo_token', 'cam' => 'varz_cam_key', 'exam_waitlist' => 'exam_waitlist_token', 'just' => 'just_token', 'ltkf_review' => 'ltkf_review_token'] as $kind => $name) {
        $try(function () use ($kind, $name) {
            $v = (string) q_value('SELECT value FROM site_settings WHERE name = ?', [$name]);
            if ($v !== '') {
                link_import_one($kind, null, $v);
            }
        });
    }
    $try(function () {
        foreach (q_all("SELECT event_id, token FROM exam_signup_links WHERE token IS NOT NULL AND token NOT LIKE '#%'") as $r) {
            link_import_one('exam_signup', (int) $r['event_id'], (string) $r['token']);
        }
    });
    $try(function () {
        foreach (q_all('SELECT id, token FROM exam_protocol_rows WHERE token IS NOT NULL AND deleted_at IS NULL') as $r) {
            link_import_one('exam_row', (int) $r['id'], (string) $r['token']);
        }
    });
    $try(function () {
        foreach (q_all('SELECT id, share_token FROM quizzes WHERE share_token IS NOT NULL') as $r) {
            link_import_one('quiz', (int) $r['id'], (string) $r['share_token']);
        }
    });
    $try(function () {
        foreach ((array) json_decode((string) q_value("SELECT value FROM site_settings WHERE name = 'ltkf_editors'"), true) as $ed) {
            if (is_array($ed) && (string) ($ed['t'] ?? '') !== '') {
                link_import_one('ltkf_editor', null, (string) $ed['t'], (string) ($ed['n'] ?? ''), !empty($ed['on']));
            }
        }
    });
}
