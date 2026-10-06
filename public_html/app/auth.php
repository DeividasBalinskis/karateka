<?php
// Prisijungimas, paskyrų būsenos ir susiję laiškai

const MIN_PASSWORD = 8;

function current_account(): ?array
{
    static $loaded = false, $account = null;
    if (!$loaded) {
        $loaded = true;
        if (!empty($_SESSION['account_id'])) {
            $account = q_one('SELECT * FROM accounts WHERE id = ?', [$_SESSION['account_id']]);
            if (!$account || !in_array($account['status'], ['active', 'pending_approval'], true)) {
                $account = null;
                unset($_SESSION['account_id']);
            }
        }
    }
    return $account;
}

function is_active_account(?array $a = null): bool
{
    $a = $a ?? current_account();
    return $a !== null && $a['status'] === 'active';
}

function is_staff(?array $a = null): bool
{
    $a = $a ?? current_account();
    return is_active_account($a) && in_array($a['role'], ['coach', 'admin'], true);
}

function is_admin(?array $a = null): bool
{
    $a = $a ?? current_account();
    return is_active_account($a) && $a['role'] === 'admin';
}

function require_login(): array
{
    $a = current_account();
    if (!$a) {
        redirect('prisijungti.php?r=' . rawurlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
    return $a;
}

function forbidden(): void
{
    http_response_code(403);
    page_start('Nėra prieigos');
    echo '<div class="panel card"><h1>Nėra prieigos</h1><p class="muted">Šis puslapis skirtas treneriams.</p></div>';
    page_end();
    exit;
}

function require_staff(): array
{
    $a = require_login();
    if (!is_staff($a)) {
        forbidden();
    }
    return $a;
}

function require_admin(): array
{
    $a = require_login();
    if (!is_admin($a)) {
        forbidden();
    }
    return $a;
}

function normalize_email(string $email): string
{
    return mb_strtolower(trim($email));
}

function valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 190;
}

// ---------- Prisijungimas ----------

function login_throttled(string $email): bool
{
    $byEmail = (int) q_value('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND success = 0 AND attempted_at > NOW() - INTERVAL 15 MINUTE', [$email]);
    $byIp = (int) q_value('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND attempted_at > NOW() - INTERVAL 15 MINUTE', [client_ip()]);
    return $byEmail >= 5 || $byIp >= 30;
}

/**
 * Grąžina ['ok' => true] arba ['ok' => false, 'error' => kodas].
 * Kodai: throttled, invalid, pending_email, pending_parent, disabled
 */
function attempt_login(string $email, string $password): array
{
    $email = normalize_email($email);
    if (login_throttled($email)) {
        return ['ok' => false, 'error' => 'throttled'];
    }
    $a = q_one('SELECT * FROM accounts WHERE email = ?', [$email]);
    // Tikriname hash net kai paskyros nėra, kad atsakymo laikas neišduotų, ar el. paštas egzistuoja
    $hash = $a['password_hash'] ?? password_hash(random_bytes(8), PASSWORD_DEFAULT);
    $valid = password_verify($password, $hash) && $a !== null;

    q('INSERT INTO login_attempts (email, ip, success) VALUES (?, ?, ?)', [$email, client_ip(), $valid ? 1 : 0]);
    if (!$valid) {
        return ['ok' => false, 'error' => 'invalid'];
    }
    if (in_array($a['status'], ['pending_email', 'pending_parent', 'disabled'], true)) {
        return ['ok' => false, 'error' => $a['status'], 'account' => $a];
    }
    if (password_needs_rehash($a['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE accounts SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $a['id']]);
    }
    login_account((int) $a['id']);
    return ['ok' => true];
}

function login_account(int $accountId): void
{
    session_regenerate_id(true);
    $_SESSION['account_id'] = $accountId;
    unset($_SESSION['csrf']);
    q('UPDATE accounts SET last_login_at = NOW() WHERE id = ?', [$accountId]);
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ---------- Paskyros ir nariai ----------

function create_account(string $email, string $password, string $first, string $last, ?string $phone, string $status): int
{
    q('INSERT INTO accounts (email, password_hash, first_name, last_name, phone, status) VALUES (?, ?, ?, ?, ?, ?)', [
        normalize_email($email),
        password_hash($password, PASSWORD_DEFAULT),
        $first,
        $last,
        $phone ?: null,
        $status,
    ]);
    return (int) db()->lastInsertId();
}

function create_member(string $first, string $last, string $birthDate, bool $photoConsent): int
{
    q('INSERT INTO members (first_name, last_name, birth_date, photo_consent) VALUES (?, ?, ?, ?)',
        [$first, $last, $birthDate, $photoConsent ? 1 : 0]);
    return (int) db()->lastInsertId();
}

function link_member(int $accountId, int $memberId, string $relation): void
{
    q('INSERT IGNORE INTO account_members (account_id, member_id, relation) VALUES (?, ?, ?)', [$accountId, $memberId, $relation]);
}

/** Visi paskyrai priklausantys nariai (pats + vaikai) su grupės pavadinimu */
function account_members(int $accountId): array
{
    return q_all(
        'SELECT m.*, am.relation, g.name AS group_name,
                (SELECT a2.email FROM account_members am2 JOIN accounts a2 ON a2.id = am2.account_id
                  WHERE am2.member_id = m.id AND am2.relation = "self") AS own_login_email
           FROM account_members am
           JOIN members m ON m.id = am.member_id
           LEFT JOIN training_groups g ON g.id = m.group_id
          WHERE am.account_id = ?
          ORDER BY am.relation = "self" DESC, m.birth_date',
        [$accountId]
    );
}

/** Ar paskyra gali matyti/tvarkyti šį narį */
function account_relation(int $accountId, int $memberId): ?string
{
    $r = q_value('SELECT relation FROM account_members WHERE account_id = ? AND member_id = ?', [$accountId, $memberId]);
    return $r === false ? null : $r;
}

/** Jaunesniam nei 14 m. nariui, užsiregistravusiam pačiam, reikia tėvų sutikimo */
function needs_parent_consent(int $accountId): bool
{
    $m = q_one('SELECT m.* FROM account_members am JOIN members m ON m.id = am.member_id WHERE am.account_id = ? AND am.relation = "self"', [$accountId]);
    if (!$m || $m['parent_consent_at'] !== null) {
        return false;
    }
    return age_on($m['birth_date']) < CONSENT_AGE;
}

/**
 * Perkelia paskyrą į kitą registracijos etapą:
 * pending_email -> (pending_parent) -> pending_approval (+ pranešimas treneriui)
 */
function advance_account_status(int $accountId): void
{
    $a = q_one('SELECT * FROM accounts WHERE id = ?', [$accountId]);
    if (!$a || !in_array($a['status'], ['pending_email', 'pending_parent'], true)) {
        return;
    }
    if ($a['email_verified_at'] === null) {
        $new = 'pending_email';
    } elseif (needs_parent_consent($accountId)) {
        $new = 'pending_parent';
    } else {
        $new = 'pending_approval';
    }
    if ($new !== $a['status']) {
        q('UPDATE accounts SET status = ? WHERE id = ?', [$new, $accountId]);
        if ($new === 'pending_approval') {
            notify_coach_pending($a);
        }
    }
}

// ---------- Laiškai ----------

function send_verification_email(array $account): bool
{
    $token = token_create('verify_email', $account['email'], (int) $account['id']);
    return send_mail($account['email'], 'Patvirtinkite el. pašto adresą — Karateka',
        "Sveiki, {$account['first_name']},\n\n"
        . "ačiū, kad registruojatės karateka.lt. Patvirtinkite savo el. pašto adresą paspaudę nuorodą:\n\n"
        . abs_url('patvirtinti.php?t=' . $token) . "\n\n"
        . "Nuoroda galioja 3 dienas. Jei registravotės ne jūs, šį laišką tiesiog ištrinkite.");
}

function send_parent_consent_email(string $parentEmail, array $kidAccount, array $member): bool
{
    $token = token_create('parent_consent', $parentEmail, (int) $kidAccount['id'], (int) $member['id']);
    $name = $member['first_name'] . ' ' . $member['last_name'];
    return send_mail($parentEmail, "Prašome sutikimo: {$name} registruojasi karateka.lt",
        "Sveiki,\n\n"
        . "{$name} užsiregistravo VšĮ Karate Ateitis narių sistemoje karateka.lt ir nurodė jūsų el. paštą kaip tėvų / globėjų.\n\n"
        . "Kadangi vaikui dar nėra 14 metų, paskyra bus aktyvuota tik gavus jūsų sutikimą. Peržiūrėkite ir patvirtinkite:\n\n"
        . abs_url('tevu-sutikimas.php?t=' . $token) . "\n\n"
        . "Patvirtinę galėsite susikurti ir savo tėvų paskyrą, matyti vaiko tvarkaraštį ir renginius.\n"
        . "Jei nieko apie tai nežinote, laišką ignoruokite - paskyra nebus aktyvuota.");
}

function notify_coach_pending(array $account): void
{
    $to = config('notify_email');
    if (!$to) {
        return;
    }
    send_mail($to, 'Nauja paskyra laukia patvirtinimo — ' . $account['first_name'] . ' ' . $account['last_name'],
        "Nauja paskyra laukia patvirtinimo ir grupės priskyrimo:\n\n"
        . "{$account['first_name']} {$account['last_name']} ({$account['email']})\n\n"
        . abs_url('admin/patvirtinimai.php'));
}
