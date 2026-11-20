<?php
// „Kodas kitam įrenginiui“ (sprendimas 2026-11-20; vietoj bendro „dev code“, kuris būtų pavojingas):
// tikras administratorius savo paskyroje (Nustatymai → Kodas kitam įrenginiui) sukuria vienkartinį kodą, kitas įrenginys
// (pvz. sekretoriato planšetė) jį įveda (irenginys.php) ir ribotą laiką dirba to administratoriaus teisėmis.
// Kodas: 8 ženklai, įvedamas per 10 min., tik vieną kartą. Įrenginį galima bet kada atjungti. Viskas lieka žurnale (db/047).

const DEVICE_CODE_MINUTES = 10;
const DEVICE_CODE_HOURS = [4 => '4 val.', 12 => '12 val.', 48 => '2 dienos'];
const DEVICE_CODE_ABC = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // be 0/O ir 1/I - kad nesupainiotų
const DEVICE_CODE_TRIES = 10;                                  // nesėkmingų bandymų iš vieno IP per 15 min.

function device_codes_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            $ok = (bool) q_one("SHOW TABLES LIKE 'device_codes'");
        } catch (Throwable $e) {
            $ok = false;
        }
    }
    return $ok;
}

function device_code_clean(string $code): string
{
    return preg_replace('/[^A-Z0-9]/', '', strtoupper($code));
}

function device_code_hash(string $code): string
{
    return hash('sha256', 'device:' . device_code_clean($code));
}

/** Naujas kodas („K7M2-9QXP“). Rodomas tik vieną kartą - saugomas tik hash. */
function device_code_create(int $accountId, string $label, int $hours): string
{
    $hours = isset(DEVICE_CODE_HOURS[$hours]) ? $hours : 12;
    do {
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= DEVICE_CODE_ABC[random_int(0, strlen(DEVICE_CODE_ABC) - 1)];
        }
    } while (q_value('SELECT id FROM device_codes WHERE code_hash = ?', [device_code_hash($code)]));
    q('INSERT INTO device_codes (account_id, label, code_hash, hours, expires_at) VALUES (?, ?, ?, ?, ?)',
        [$accountId, mb_substr(trim($label), 0, 80) ?: null, device_code_hash($code), $hours, date('Y-m-d H:i:s', time() + DEVICE_CODE_MINUTES * 60)]);
    return substr($code, 0, 4) . '-' . substr($code, 4);
}

function device_code_throttled(): bool
{
    return (int) q_value("SELECT COUNT(*) FROM login_attempts WHERE email = '#irenginys' AND ip = ? AND success = 0 AND attempted_at > NOW() - INTERVAL 15 MINUTE",
        [client_ip()]) >= DEVICE_CODE_TRIES;
}

/** Įvestas kodas: 'ok' (šis įrenginys prisijungė), 'invalid' arba 'throttled' */
function device_code_redeem(string $code): string
{
    if (!device_codes_ready() || device_code_throttled()) {
        return 'throttled';
    }
    $row = strlen(device_code_clean($code)) === 8
        ? q_one('SELECT * FROM device_codes WHERE code_hash = ? AND used_at IS NULL AND revoked_at IS NULL AND expires_at > NOW()', [device_code_hash($code)])
        : null;
    $acc = $row ? q_one('SELECT * FROM accounts WHERE id = ?', [(int) $row['account_id']]) : null;
    $ok = $acc && is_admin($acc);   // kūrėjas vis dar tikras administratorius
    if ($ok) {
        $st = q('UPDATE device_codes SET used_at = NOW(), used_ip = ?, used_device = ?, session_until = NOW() + INTERVAL hours HOUR
                  WHERE id = ? AND used_at IS NULL', [client_ip(), mb_substr(device_label(), 0, 120), (int) $row['id']]);
        $ok = $st->rowCount() === 1;   // du įrenginiai tuo pačiu metu - laimi vienas
    }
    q('INSERT INTO login_attempts (email, ip, success) VALUES (?, ?, ?)', ['#irenginys', client_ip(), $ok ? 1 : 0]);
    if (!$ok) {
        return 'invalid';
    }
    login_account((int) $acc['id']);
    $_SESSION['device_code'] = (int) $row['id'];
    return 'ok';
}

/** Ar šio įrenginio prisijungimas su kodu dar galioja (neatjungtas, laikas nepasibaigęs). Tikrinama kiekvienoje užklausoje. */
function device_code_session_ok(int $id, int $accountId): bool
{
    try {
        return (bool) q_value('SELECT 1 FROM device_codes WHERE id = ? AND account_id = ? AND revoked_at IS NULL AND session_until > NOW()', [$id, $accountId]);
    } catch (Throwable $e) {
        return false;
    }
}

/** Šio įrenginio kodo įrašas (jei prisijungta su kodu) */
function device_code_current(): ?array
{
    $id = (int) ($_SESSION['device_code'] ?? 0);
    return $id && current_account() ? q_one('SELECT * FROM device_codes WHERE id = ?', [$id]) : null;
}

function device_code_revoke(int $id, int $byAccount): void
{
    q('UPDATE device_codes SET revoked_at = NOW(), revoked_by = ? WHERE id = ? AND revoked_at IS NULL', [$byAccount, $id]);
}

/** Žurnalas: visi kodai per 60 dienų (visi administratoriai mato visus - kad niekas nevyktų tyliai) */
function device_code_log(): array
{
    return q_all('SELECT dc.*, a.first_name, a.last_name, r.first_name AS rev_first
                    FROM device_codes dc JOIN accounts a ON a.id = dc.account_id LEFT JOIN accounts r ON r.id = dc.revoked_by
                   WHERE dc.created_at > NOW() - INTERVAL 60 DAY ORDER BY dc.id DESC LIMIT 50');
}

/** Būsena žurnale: [tekstas, klasė] */
function device_code_status(array $r): array
{
    $now = time();
    if ($r['revoked_at']) {
        return [t('Atjungtas') . ' ' . date('m-d H:i', strtotime($r['revoked_at'])) . ($r['rev_first'] ?? '' ? ' (' . $r['rev_first'] . ')' : ''), 'muted'];
    }
    if ($r['used_at']) {
        return strtotime((string) $r['session_until']) > $now
            ? [t('Veikia iki') . ' ' . date('m-d H:i', strtotime($r['session_until'])), 'ok']
            : [t('Baigėsi') . ' ' . date('m-d H:i', strtotime($r['session_until'])), 'muted'];
    }
    return strtotime($r['expires_at']) > $now ? [t('Laukia įvedimo iki') . ' ' . date('H:i', strtotime($r['expires_at'])), 'warn'] : [t('Nepanaudotas'), 'muted'];
}

/** Nesėkmingi bandymai per 24 val. (rodoma administratoriui) */
function device_code_failed_24h(): int
{
    return (int) q_value("SELECT COUNT(*) FROM login_attempts WHERE email = '#irenginys' AND success = 0 AND attempted_at > NOW() - INTERVAL 1 DAY");
}
