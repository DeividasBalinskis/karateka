<?php
// Prisijungimas, paskyrų būsenos ir susiję laiškai

const MIN_PASSWORD = 8;

// ---------- Teisės (C1): viena lentelė „veiksmas → rolės“ ----------
// Puslapiai klausia tik can('veiksmas') / require_can('veiksmas'); rolių tiesiogiai niekur kitur netikrina (tikrina tests/checks.sh).
// Ta pati lentelė - docs/standartai.md („Permission table“); pridėjus veiksmą čia, jį reikia įrašyti ir ten (tikrina checks.sh).
// Rolės: guest - neprisijungęs arba paskyra dar neaktyvi; member - narys (ir vaikas su savo prisijungimu); parent - narys, kuris turi vaikų;
// coach - treneris; tester - administratoriaus (ar trenerio) rolė su žyme is_tester (gali tiek, kiek treneris, nustatymus mato tik skaityti,
// Dojo nemato); admin - tikras administratorius. „Admin režimas“ ir „Žiūrėti kaip“ - tik vaizdas, teisių nekeičia.

const ROLES = ['guest', 'member', 'parent', 'coach', 'tester', 'admin'];

const PERMISSIONS = [
    // Nariai ir tėvai
    'member_self'    => ['guest', 'member', 'parent'],           // prisijungus (ir dar nepatvirtintam) - Paskyra: „+ Aš pats treniruojuosi“, „+ Pridėti vaiką“ (vaiko prisijungimui - ne)
    'dojo'           => ['member', 'parent', 'coach', 'admin'],  // Dojo, arena, žaidimai - kai paskyra yra „kas mato žaidimą“ sąraše (admin - visada)
    'dojo_menu'      => ['member', 'parent', 'coach', 'admin'],  // „🏯 Dojo“ meniu: kaip dojo, bet kol žaidimas neatidarytas visiems - tik admin
    // Treneriai (ir testuotojai)
    'valdymas'       => ['coach', 'tester', 'admin'],            // Valdymas, trenerio antraštė ir meniu, trenerio puslapių vaizdas, admin režimas
    'members'        => ['coach', 'tester', 'admin'],            // Nariai, patvirtinimai, grupės
    'training'       => ['coach', 'tester', 'admin'],            // Treniruotė (lankomumas, pastabos), pamokos, programa, tvarkaraštis (skaityti)
    'xp'             => ['coach', 'tester', 'admin'],            // skirti aktyvumo taškus
    'events'         => ['coach', 'tester', 'admin'],            // renginiai, kvietimai, naujienos, dalyviai
    'exam_protocols' => ['coach', 'tester', 'admin'],            // egzaminų sąrašas ir protokolai
    'quizzes'        => ['coach', 'tester', 'admin'],            // teorijos testai
    'budo'           => ['coach', 'tester', 'admin'],            // Budo varžybos
    'messages'       => ['coach', 'tester', 'admin'],            // pokalbiai ir žinutės su nariais, laiškų siuntimas
    'translations'   => ['coach', 'tester', 'admin'],            // vertimai (EN / RU)
    'federation'     => ['coach', 'tester', 'admin'],            // federacijos registrai - skaityti
    'feedback_box'   => ['coach', 'tester', 'admin'],            // 💡 lange - visi atsiliepimai
    'settings_view'  => ['tester', 'admin'],                     // Nustatymų jungikliai - matyti (testuotojui - tik skaityti)
    // Tik tikras administratorius
    'accounts'       => ['admin'],                               // paskyros, rolės, testuotojai, pakeitimų žurnalas
    'game_access'    => ['admin'],                               // kas mato žaidimą
    'settings'       => ['admin'],                               // svetainės jungikliai, „Visi nustatymai“, Valdymo meniu
    'backups'        => ['admin'],                               // atsarginės kopijos, duomenų priežiūra, db atnaujinimas
    'device_codes'   => ['admin'],                               // kodas kitam įrenginiui
    'testing'        => ['admin'],                               // testavimas, „Žiūrėti kaip“
    'feedback'       => ['admin'],                               // Atsiliepimai (puslapis), atsakymai
    'review'         => ['admin'],                               // 🔎 Peržiūra
    'broadcast'      => ['admin'],                               // Pranešimai visiems (laiškai ir SMS)
    'sms'            => ['admin'],                               // SMS siuntimas
    'justifications' => ['admin'],                               // pateisinimai
    'exams'          => ['admin'],                               // kurti ir trinti egzaminus (protokolus), kainos, laukiantieji
    'schedule'       => ['admin'],                               // keisti tvarkaraštį, kainas, grupių pavadinimus; atšaukti treniruotes
    'xp_settings'    => ['admin'],                               // taškų kategorijos, lygių kaina
    'event_lists'    => ['admin'],                               // galutinis renginio dalyvių sąrašas ir rezultatai
    'members_admin'  => ['admin'],                               // ištrinti narį; kas nesutinka dėl nuotraukų
    'budo_admin'     => ['admin'],                               // Budo: ištrinti tikras varžybas, bandymo nuoroda, transliacijos raktas, tatamio šalinimas
    'ltkf'           => ['admin'],                               // LTKF svetainė, registrų keitimas, federacijos patvirtinimas
    'design'         => ['admin'],                               // svetainės tema, šriftų bandymas, „👍 Patinka“
    'dojo_admin'     => ['admin'],                               // Dojo administravimas
    'home_edit'      => ['admin'],                               // pagrindinio puslapio redagavimas (+ paskyros, pažymėtos Paskyrose „🏠 Redaguoja pradžią“)
];

/** Paskyros rolė lentelei (be „parent“ - ji nustatoma tik kai reikia, žr. can()) */
function account_role(?array $a): string
{
    if ($a === null || ($a['status'] ?? '') !== 'active') {
        return 'guest';
    }
    $r = (string) ($a['role'] ?? 'member');
    if ($r === 'admin' || $r === 'coach') {
        return !empty($a['is_tester']) ? 'tester' : $r;
    }
    return 'member';
}

/** Ar narys turi vaikų (paskyroje yra ne tik „aš“) */
function account_is_parent(array $a): bool
{
    static $memo = [];
    $id = (int) $a['id'];
    if (!isset($memo[$id])) {
        $memo[$id] = (bool) q_value('SELECT 1 FROM account_members WHERE account_id = ? AND relation <> "self" LIMIT 1', [$id]);
    }
    return $memo[$id];
}

/** Ar paskyra ($a; nenurodžius - prisijungusi) gali atlikti veiksmą. Nežinomas veiksmas - ne. */
function can(string $action, ?array $a = null): bool
{
    $roles = PERMISSIONS[$action] ?? null;
    if ($roles === null) {
        error_log('can(): nežinomas veiksmas ' . $action);
        return false;
    }
    $a = $a ?? current_account();
    $role = account_role($a);
    if ($role === 'member' && in_array('parent', $roles, true) !== in_array('member', $roles, true)) {
        $role = account_is_parent($a) ? 'parent' : 'member';
    }
    $ok = in_array($role, $roles, true);
    if ($action === 'member_self') {
        $ok = $ok && $a !== null && !is_kid_login($a);   // tik prisijungus; vaiko vardą, PIN ir duomenis keičia tėvai
    } elseif ($action === 'dojo' || $action === 'dojo_menu') {
        $ok = $ok && can_see_game($a, $role, $action === 'dojo_menu');
    } elseif ($action === 'home_edit' && !$ok && $role !== 'guest') {
        $ok = in_array((int) $a['id'], home_editor_ids(), true);
    }
    return $ok;
}

/** Puslapio pradžioje: prisijungęs ir gali - grąžina paskyrą; kitaip - prisijungimas arba „Nėra prieigos“ */
function require_can(string $action): array
{
    $a = require_login();
    if (!can($action, $a)) {
        forbidden();
    }
    return $a;
}

/** Dojo: žaidimo lentelės sukurtos, ne testuotojas (ir ne nario paskyra su žyme), sąraše „kas mato žaidimą“ */
function can_see_game(array $a, string $role, bool $menu): bool
{
    require_once APP_DIR . '/game.php';
    if (!game_ready() || !empty($a['is_tester'])) {
        return false;   // testuotojai žaidimo nemato (kad nesugadintume staigmenos)
    }
    $ids = game_access_ids();
    $open = $role === 'admin' || $ids === null || in_array((int) $a['id'], $ids, true);
    // #140 + sprendimas 2026-11-21: kol žaidimas neatidarytas nariams - meniu jį mato tik tikri administratoriai
    return $open && (!$menu || $role === 'admin' || $ids === null);
}

/** Kas, be administratoriaus, redaguoja pagrindinį puslapį: paskyrų id (Paskyros → „🏠 Redaguoja pradžią“; ne pagal vardą) */
function home_editor_ids(): array
{
    $ids = json_decode((string) site_setting('home_editors', ''), true);
    return is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
}

/** Rolės pavadinimas ekranui (testuotojas - atskirai, tester_badge) */
function role_label(?string $role): string
{
    return $role === 'admin' ? t('adminas') : ($role === 'coach' ? t('treneris') : '');
}

function current_account(): ?array
{
    static $loaded = false, $account = null;
    if (!$loaded) {
        $loaded = true;
        if (!empty($_SESSION['account_id'])) {
            $account = q_one('SELECT * FROM accounts WHERE id = ?', [$_SESSION['account_id']]);
            if (!$account || !in_array($account['status'], ['active', 'pending_approval'], true)
                || (int) ($account['session_ver'] ?? 0) !== (int) ($_SESSION['sv'] ?? 0)
                || (!empty($_SESSION['device_code']) && !device_code_session_ok((int) $_SESSION['device_code'], (int) $account['id']))) {
                // Prisijungimas su kodu kitam įrenginiui atjungtas arba baigėsi (app/device_codes.php);
                // C1: pakeitus slaptažodį (session_ver padidėja) kiti įrenginiai atjungiami
                $account = null;
                unset($_SESSION['account_id'], $_SESSION['device_code'], $_SESSION['sv'], $_SESSION['view_admin']);
            }
        }
    }
    return $account;
}

/** Sesija - šiai paskyrai (prisijungimas, „Žiūrėti kaip“): kartu įsimenama paskyros sesijų versija (db/061) */
function session_account_set(int $accountId): void
{
    $_SESSION['account_id'] = $accountId;
    try {
        $_SESSION['sv'] = (int) q_value('SELECT session_ver FROM accounts WHERE id = ?', [$accountId]);
    } catch (PDOException $ex) {
        $_SESSION['sv'] = 0;   // dar neimportuotas db/061
    }
}

/**
 * Naujas slaptažodis (ar PIN): kiti tos paskyros įrenginiai atjungiami (session_ver + 1).
 * Jei keičia pats prisijungęs žmogus - šis įrenginys lieka prisijungęs.
 */
function account_password_set(int $accountId, string $password, string $how = 'changed'): void
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        q('UPDATE accounts SET password_hash = ?, session_ver = session_ver + 1 WHERE id = ?', [$hash, $accountId]);
    } catch (PDOException $ex) {
        q('UPDATE accounts SET password_hash = ? WHERE id = ?', [$hash, $accountId]);   // dar neimportuotas db/061
    }
    if ((int) ($_SESSION['account_id'] ?? 0) === $accountId) {
        session_regenerate_id(true);
        session_account_set($accountId);
    }
    account_log_write($accountId, 'password', null, $how);
}

// ---------- Paskyrų pakeitimų žurnalas (C1, db/061): rolė, būsena, testuotojas, žaidimas, pradžios redagavimas, slaptažodis ----------

const ACCOUNT_LOG_FIELDS = ['created', 'role', 'status', 'is_tester', 'game_access', 'home_editor', 'password'];

function account_log_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT 1 FROM account_log LIMIT 1');
            $ok = true;
        } catch (PDOException $ex) {
            $ok = false;
        }
    }
    return $ok;
}

/** Įrašas: kas (prisijungęs; null - sistema ar pats žmogus per nuorodą), kada, kuri paskyra, kas pasikeitė (sena → nauja) */
function account_log_write(int $accountId, string $field, ?string $old, ?string $new): void
{
    if (!in_array($field, ACCOUNT_LOG_FIELDS, true) || ($old === $new && $field !== 'password') || !account_log_ready()) {
        return;
    }
    $by = function_exists('current_account') ? current_account() : null;
    try {
        q('INSERT INTO account_log (account_id, field, old_value, new_value, changed_by, changed_by_name) VALUES (?, ?, ?, ?, ?, ?)',
            [$accountId, $field, $old !== null ? mb_substr($old, 0, 255) : null, $new !== null ? mb_substr($new, 0, 255) : null,
             $by ? (int) $by['id'] : null, $by ? mb_substr(trim($by['first_name'] . ' ' . $by['last_name']), 0, 120) : null]);
        q('DELETE FROM account_log WHERE changed_at < NOW() - INTERVAL 1 YEAR');
    } catch (PDOException $ex) {
        // žurnalas - ne svarbiau už patį pakeitimą
    }
}

/** Pakeisti paskyros rolę, būseną ar testuotojo žymę ir įrašyti į žurnalą. Grąžina, ar kas nors pasikeitė. */
function account_set(int $accountId, string $field, string $value): bool
{
    $allowed = ['role' => ['member', 'coach', 'admin'], 'status' => ['pending_email', 'pending_parent', 'pending_approval', 'active', 'disabled'], 'is_tester' => ['0', '1']];
    if (!isset($allowed[$field]) || !in_array($value, $allowed[$field], true)) {
        return false;
    }
    $old = q_value('SELECT ' . $field . ' FROM accounts WHERE id = ?', [$accountId]);
    if ($old === false || (string) $old === $value) {
        return false;
    }
    q('UPDATE accounts SET ' . $field . ' = ? WHERE id = ?', [$field === 'is_tester' ? (int) $value : $value, $accountId]);
    account_log_write($accountId, $field, (string) $old, $value);
    return true;
}

/** Žurnalo įrašai (naujausi pirmi): visų arba vienos paskyros; kartu - „kas mato žaidimą“ pakeitimai iš nustatymų žurnalo */
function account_log_rows(int $limit = 100, ?int $accountId = null): array
{
    if (!account_log_ready()) {
        return [];
    }
    return q_all('SELECT l.*, a.first_name, a.last_name, a.email FROM account_log l LEFT JOIN accounts a ON a.id = l.account_id'
        . ($accountId ? ' WHERE l.account_id = ' . (int) $accountId : '') . ' ORDER BY l.id DESC LIMIT ' . max(1, min(500, $limit)));
}

/** „Kas mato žaidimą“ (game_access) pakeitimas: į žurnalą - kiekvienai paskyrai, kuriai pasikeitė */
function account_log_game_access(?array $oldIds, ?array $newIds): void
{
    if ($oldIds === null || $newIds === null) {   // „visi“ ↔ sąrašas - viena eilutė (paskyra 0 = visi)
        if (($oldIds === null) !== ($newIds === null)) {
            account_log_write(0, 'game_access', $oldIds === null ? 'all' : implode(',', $oldIds), $newIds === null ? 'all' : implode(',', $newIds));
        }
        return;
    }
    foreach (array_diff($newIds, $oldIds) as $id) {
        account_log_write((int) $id, 'game_access', '0', '1');
    }
    foreach (array_diff($oldIds, $newIds) as $id) {
        account_log_write((int) $id, 'game_access', '1', '0');
    }
}

function is_active_account(?array $a = null): bool
{
    $a = $a ?? current_account();
    return $a !== null && $a['status'] === 'active';
}

/** Treneris, testuotojas ar administratorius (tas pats, kas can('valdymas')) - naudojama tik šiame faile */
function is_staff(?array $a = null): bool
{
    return can('valdymas', $a);
}

/**
 * Tikras administratorius (Denis, Deividas). Testuotojas (db/044, accounts.is_tester) turi administratoriaus rolę,
 * bet sistema jį laiko treneriu: gali tiek, kiek treneris, nustatymus mato tik skaityti, Dojo ir žaidimo nemato.
 * Naudojama tik šiame faile; puslapiai klausia can().
 */
function is_admin(?array $a = null): bool
{
    return account_role($a ?? current_account()) === 'admin';
}

/** Testuotojas: ženkliukas „🧪 Testuotojas“, už testavimą vėliau - bonusai */
function is_tester(?array $a = null): bool
{
    $a = $a ?? current_account();
    return is_active_account($a) && !empty($a['is_tester']);
}

/** Ar jau yra db/044 stulpelis is_tester (iki duomenų bazės atnaujinimo - nėra) */
function tester_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            $ok = (bool) q_one("SHOW COLUMNS FROM accounts LIKE 'is_tester'");
        } catch (Throwable $e) {
            $ok = false;
        }
    }
    return $ok;
}

/** SQL sąlyga „tikras administratorius“ (be testuotojų); $al - lentelės sinonimas */
function real_admin_sql(string $al = ''): string
{
    $p = $al !== '' ? $al . '.' : '';
    return $p . "role = 'admin' AND " . $p . "status = 'active'" . (tester_ready() ? ' AND ' . $p . 'is_tester = 0' : '');
}

/** Kiek aktyvių tikrų administratorių liktų, jei paskyra $exceptId nebūtų administratorius */
function real_admins_left(int $exceptId): int
{
    return (int) q_value('SELECT COUNT(*) FROM accounts WHERE ' . real_admin_sql() . ' AND id <> ?', [$exceptId]);
}

/** Ar ši paskyra - paskutinis tikras administratorius (jo rolės, žymės ir būsenos keisti negalima) */
function account_is_last_admin(array $acc): bool
{
    return is_admin($acc) && real_admins_left((int) $acc['id']) === 0;
}

/** Aktyvios administratoriaus rolės paskyros (su testuotojais) - sąrašams, kuriuose pasirenkami žmonės */
function active_admins(): array
{
    return q_all('SELECT id, first_name, last_name, email FROM accounts WHERE role = "admin" AND status = "active" ORDER BY first_name, last_name');
}

/** Seniausia aktyvi administratoriaus paskyra (autorius automatiškai sukuriamam turiniui); nėra - null */
function first_admin_id(): ?int
{
    $id = q_value('SELECT id FROM accounts WHERE role = "admin" AND status = "active" ORDER BY id LIMIT 1');
    return $id === false || $id === null ? null : (int) $id;
}

/** Ar jau yra bent viena administratoriaus paskyra (pirmo administratoriaus puslapiui) */
function any_admin_exists(): bool
{
    return (bool) q_value('SELECT 1 FROM accounts WHERE role = "admin" LIMIT 1');
}

/** Ženkliukas prie vardo */
function tester_badge(?array $a = null): string
{
    return is_tester($a) ? ' <span class="badge badge-tester" title="' . e(t('Testuoja svetainę. Dojo ir žaidimo nemato, nustatymus mato tik skaityti.')) . '">🧪 ' . te('Testuotojas') . '</span>' : '';
}

/**
 * Admin režimas (treneriui ir adminui): įjungus matomi „Valdymas“ ir redagavimo mygtukai visoje svetainėje.
 * Išjungus - svetainė rodoma kaip paprastam nariui (Mano dojo, žaidimai). Pasirinkimas saugomas slapuke „adm“ (1 metai).
 */
function admin_mode(): bool
{
    return is_staff() && ($_COOKIE['adm'] ?? '1') === '1';   // treneriui - numatytai įjungta (#97)
}

function admin_mode_set(bool $on): void
{
    setcookie('adm', $on ? '1' : '0', ['expires' => time() + 365 * 86400, 'path' => (config('base_path') ?: '') . '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
    $_COOKIE['adm'] = $on ? '1' : '0';
}

/** Rodyti trenerio įrankius (redaguoti, pridėti) - tik admin režime */
function staff_ui(?array $a = null): bool
{
    return is_staff($a) && admin_mode();
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
    page_start(t('Nėra prieigos'));
    echo ('<div class="panel card"><h1>' . t('Nėra prieigos') . '</h1><p class="muted">' . t('Šis puslapis skirtas treneriams.') . '</p></div>');
    page_end();
    exit;
}

/** Seni vardai (puslapiai naudoja require_can) */
function require_staff(): array
{
    return require_can('valdymas');
}

function require_admin(): array
{
    return require_can('accounts');
}

function normalize_email(string $email): string
{
    return mb_strtolower(trim($email));
}

function valid_email(string $email): bool
{
    // .invalid - rezervuota sritis, laiškai į ją niekada nenueina; ja žymimi vaikų prisijungimai (žr. žemiau)
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 190 && !preg_match('/\.invalid$/i', $email);
}

// ---------- Vaiko prisijungimas: vardas + PIN (B7, sprendimas 2026-11-20) ----------
// Nuo 7 m. tėvai paskyroje sukuria vaikui prisijungimo vardą ir PIN; trenerio patvirtinti nereikia.
// Paskyra - kaip paprasta (rolė member, ryšys „self“), tik vietoj el. pašto saugoma „vardas@vaikas.invalid“
// (ne tikras adresas: laiškai ten nesiunčiami). Vaikas prisijungia įvesdamas vardą vietoj el. pašto ir PIN vietoj slaptažodžio.

const KID_LOGIN_DOMAIN = 'vaikas.invalid';
const KID_LOGIN_MIN_AGE = 7;

function kid_login_email(string $username): string
{
    return mb_strtolower(trim($username)) . '@' . KID_LOGIN_DOMAIN;
}

function is_kid_login(?array $a): bool
{
    return $a !== null && str_ends_with((string) $a['email'], '@' . KID_LOGIN_DOMAIN);
}

/** Ką rodyti vietoj el. pašto: vaikui - prisijungimo vardą */
function login_name(string $email): string
{
    return str_ends_with($email, '@' . KID_LOGIN_DOMAIN) ? substr($email, 0, -strlen('@' . KID_LOGIN_DOMAIN)) : $email;
}

/** Vardas: 3-20 mažųjų lotyniškų raidžių, skaitmenų, taškų, brūkšnelių; prasideda raide */
function valid_kid_username(string $u): bool
{
    return (bool) preg_match('/^[a-z][a-z0-9._-]{2,19}$/', $u);
}

function valid_kid_pin(string $pin): bool
{
    return (bool) preg_match('/^\d{4,6}$/', $pin);
}

/** Pasiūlymas pagal vardą: „Jonas“ → „jonas“ (be lietuviškų raidžių) */
function kid_username_suggest(string $first): string
{
    $u = strtr(mb_strtolower(trim($first)), ['ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i', 'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z']);
    $u = preg_replace('/[^a-z0-9]/', '', $u);
    return strlen($u) >= 3 ? substr($u, 0, 16) : 'karate' . ($u !== '' ? '.' . $u : '');
}

// ---------- Prisijungimas ----------

function login_throttled(string $email): bool
{
    $byEmail = (int) q_value('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND success = 0 AND attempted_at > NOW() - INTERVAL 15 MINUTE', [$email]);
    $byIp = (int) q_value('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND attempted_at > NOW() - INTERVAL 15 MINUTE', [client_ip()]);
    // PIN trumpas - vaiko vardui papildomai ne daugiau 15 klaidų per parą (tada tėvai gali pakeisti PIN)
    $byDay = str_ends_with($email, '@' . KID_LOGIN_DOMAIN)
        ? (int) q_value('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND success = 0 AND attempted_at > NOW() - INTERVAL 1 DAY', [$email]) : 0;
    return $byEmail >= 5 || $byIp >= 30 || $byDay >= 15;
}

/**
 * Grąžina ['ok' => true] arba ['ok' => false, 'error' => kodas].
 * Kodai: throttled, invalid, pending_email, pending_parent, disabled, code (reikia kodo iš el. pašto - kodas.php)
 */
function attempt_login(string $email, string $password): array
{
    $email = normalize_email($email);
    if ($email !== '' && strpos($email, '@') === false) {
        $email = kid_login_email($email);   // vaiko vardas (B7)
    }
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
    // Antras žingsnis: kodas el. paštu (jei įjungta ir įrenginys nepažymėtas kaip patikimas)
    if (two_factor_on($a) && !device_trusted((int) $a['id'])) {
        $_SESSION['tfa'] = ['account_id' => (int) $a['id'], 'at' => time()];
        return ['ok' => false, 'error' => 'code', 'mail_error' => login_code_send($a, 'login')];
    }
    login_account((int) $a['id']);
    return ['ok' => true];
}

function login_account(int $accountId): void
{
    session_regenerate_id(true);
    session_account_set($accountId);
    unset($_SESSION['csrf'], $_SESSION['tfa'], $_SESSION['dev_login_code'], $_SESSION['device_code']);
    q('UPDATE accounts SET last_login_at = NOW() WHERE id = ?', [$accountId]);
    if ($acc = q_one('SELECT * FROM accounts WHERE id = ?', [$accountId])) {
        account_self_name_sync($acc);   // #158: senas vardų neatitikimas (iki v1.73)
    }
    // C2: paskyros kalba - jei išsaugota, ji ir naršyklėje; jei ne - išsaugoma dabartinė
    try {
        $saved = (string) q_value('SELECT lang FROM accounts WHERE id = ?', [$accountId]);
    } catch (PDOException $ex) {
        return;   // dar neimportuotas db/049
    }
    if (isset(LANGS[$saved])) {
        if ($saved !== current_lang()) {
            lang_remember($saved);
        }
    } else {
        lang_remember(current_lang(), $accountId);
    }
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
    $id = (int) db()->lastInsertId();
    try {
        q('UPDATE accounts SET lang = ? WHERE id = ?', [current_lang(), $id]);   // C2: kalba, kuria registravosi (laiškams)
    } catch (PDOException $ex) {
        // dar neimportuotas db/049
    }
    account_log_write($id, 'created', null, $status);
    return $id;
}

/** $belt - diržas, kurį narys / tėvai nurodo registruodamiesi (treneris vėliau gali pataisyti) */
function create_member(string $first, string $last, string $birthDate, bool $photoConsent, ?int $belt = null): int
{
    q('INSERT INTO members (first_name, last_name, birth_date, photo_consent, belt_level) VALUES (?, ?, ?, ?, ?)',
        [$first, $last, $birthDate, $photoConsent ? 1 : 0, $belt !== null && isset(BELTS[$belt]) ? $belt : null]);
    return (int) db()->lastInsertId();
}

/**
 * Pakvietimo nuoroda nariui, kurį treneris pridėjo be paskyros: užsiregistravęs per ją (ar prisijungęs) susiejamas su šiuo nariu.
 * Jei nurodytas el. paštas - nuoroda išsiunčiama ir laišku. Grąžina nuorodą (dar neatnaujinus DB - null).
 */
function member_join_link(array $m, string $email = ''): ?string
{
    $email = normalize_email($email);
    try {
        $token = token_create('join', $email, null, (int) $m['id']);
    } catch (PDOException $ex) {
        return null;   // dar neimportuotas db/036
    }
    $link = abs_url('jungtis.php?t=' . $token);
    if ($email !== '' && valid_email($email)) {
        mail_send_tpl($email, 'join_invite', ['child' => $m['first_name'] . ' ' . $m['last_name'], 'link' => $link], 'lt');   // kviečia treneris - gavėjo kalba dar nežinoma
    }
    return $link;
}

/** Gimimo datos tikslumas: 0 - tiksli, 1 - tik metai, 2 - apytiksliai pagal grupę (iki DB atnaujinimo - 0) */
function set_birth_precision(int $memberId, int $p): void
{
    try {
        q('UPDATE members SET birth_precision = ? WHERE id = ?', [$p, $memberId]);
    } catch (PDOException $ex) {
    }
}

/** Apytiksliai gimimo metai pagal grupę: dažniausi tos grupės narių metai, kitaip - pagal grupės tipą */
function group_typical_birth_year(int $groupId): int
{
    $y = (int) q_value('SELECT YEAR(birth_date) y FROM members WHERE group_id = ? AND status = "active" GROUP BY y ORDER BY COUNT(*) DESC, y DESC LIMIT 1', [$groupId]);
    if ($y) {
        return $y;
    }
    $cat = (string) q_value('SELECT category FROM training_groups WHERE id = ?', [$groupId]);
    return (int) date('Y') - ($cat === 'suauge' ? 30 : ($cat === 'jaunimas' ? 15 : 8));
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
                  WHERE am2.member_id = m.id AND am2.relation = "self") AS own_login_email,
                (SELECT a2.status FROM account_members am2 JOIN accounts a2 ON a2.id = am2.account_id
                  WHERE am2.member_id = m.id AND am2.relation = "self") AS own_login_status
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
        account_set($accountId, 'status', $new);
        if ($new === 'pending_approval') {
            notify_coach_pending($a);
        }
    }
}

// ---------- Laiškai ----------

function send_verification_email(array $account): bool
{
    $token = token_create('verify_email', $account['email'], (int) $account['id']);
    return mail_send_tpl($account['email'], 'verify_email', ['name' => $account['first_name'], 'link' => abs_url('patvirtinti.php?t=' . $token)], account_lang($account));
}

function send_parent_consent_email(string $parentEmail, array $kidAccount, array $member): bool
{
    $token = token_create('parent_consent', $parentEmail, (int) $kidAccount['id'], (int) $member['id']);
    $name = $member['first_name'] . ' ' . $member['last_name'];
    return mail_send_tpl($parentEmail, 'parent_consent', ['child' => $name, 'link' => abs_url('tevu-sutikimas.php?t=' . $token)]);   // vaiko pasirinkta kalba
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

/** Kiek paskyrų / naujai pridėtų vaikų laukia trenerio patvirtinimo */
function pending_approvals_count(): int
{
    static $count = null;
    if ($count === null) {
        $count = (int) q_value('SELECT COUNT(*) FROM accounts WHERE status = "pending_approval"')
            + (int) q_value('SELECT COUNT(DISTINCT m.id) FROM members m JOIN account_members am ON am.member_id = m.id JOIN accounts a ON a.id = am.account_id
                             WHERE m.status = "pending" AND a.status = "active"');
    }
    return $count;
}

/** #158: pakeitus vardą - Dojo personažo vardas irgi (jei jis buvo toks pat kaip senas vardas; savo slapyvardis lieka) */
function sync_hero_names(?int $accountId, ?int $memberId, string $oldFirst, string $newFirst): void
{
    if ($oldFirst === $newFirst || $newFirst === '') {
        return;
    }
    $new = mb_substr($newFirst, 0, 40);
    try {
        if ($accountId) {
            q('UPDATE game_heroes SET name = ? WHERE account_id = ? AND name = ?', [$new, $accountId, $oldFirst]);
            q('UPDATE game_heroes h JOIN account_members am ON am.member_id = h.member_id SET h.name = ? WHERE am.account_id = ? AND am.relation = "self" AND h.name = ?', [$new, $accountId, $oldFirst]);
        }
        if ($memberId) {
            q('UPDATE game_heroes SET name = ? WHERE member_id = ? AND name = ?', [$new, $memberId, $oldFirst]);
            q('UPDATE game_heroes h JOIN account_members am ON am.account_id = h.account_id SET h.name = ? WHERE am.member_id = ? AND am.relation = "self" AND h.name = ?', [$new, $memberId, $oldFirst]);
        }
    } catch (PDOException $ex) {
        // žaidimo lentelių dar nėra (db/035)
    }
}

/**
 * #158 likutis: paskyra ir jos „aš“ narys - tas pats žmogus. Nuo v1.73 vardas keičiamas abiem kartu; jei jie išsiskyrė anksčiau
 * (vardas pakeistas iki v1.73), nario kortelė ir Dojo personažas gauna paskyros vardą. Kviečiama atidarius paskyrą, Dojo ir prisijungus.
 */
function account_self_name_sync(array $a): void
{
    $first = trim((string) $a['first_name']);
    if ($first === '' || is_kid_login($a)) {
        return;   // vaiko vardą tvarko tėvai
    }
    $m = q_one('SELECT m.id, m.first_name, m.last_name FROM members m JOIN account_members am ON am.member_id = m.id
                 WHERE am.account_id = ? AND am.relation = "self" ORDER BY m.id LIMIT 1', [(int) $a['id']]);
    $last = trim((string) $a['last_name']) !== '' ? trim((string) $a['last_name']) : (string) ($m['last_name'] ?? '');
    if (!$m || ($m['first_name'] === $first && $m['last_name'] === $last)) {
        return;
    }
    q('UPDATE members SET first_name = ?, last_name = ? WHERE id = ?', [$first, $last, (int) $m['id']]);
    sync_hero_names((int) $a['id'], (int) $m['id'], (string) $m['first_name'], $first);
    error_log('name sync: member #' . (int) $m['id'] . ' renamed to the account name (account #' . (int) $a['id'] . ')');
}

/**
 * Paskyros nustatymai (el. paštas, telefonas, slaptažodis, prisijungimas su kodu) - tėvų paskyroje ir trenerio apžvalgoje.
 * Sėkmės atveju nukreipia į $back; klaidos įrašomos į $errors.
 */
function handle_account_settings(array $a, string $back, array &$errors): void
{
    if (is_kid_login($a)) {
        return;   // vaiko vardą, PIN ir duomenis keičia tėvai
    }
    $aid = (int) $a['id'];
    $action = post('action');
    if ($action === 'name') {   // #146: vardą ir pavardę galima pasikeisti pačiam
        $fn = mb_substr(trim((string) post('first_name')), 0, 80);
        $ln = mb_substr(trim((string) post('last_name')), 0, 80);
        if ($fn === '') {
            $errors[] = t('Įrašykite vardą.');
            return;
        }
        q('UPDATE accounts SET first_name = ?, last_name = ? WHERE id = ?', [$fn, $ln, $aid]);
        // #158: tas pats žmogus kaip narys („aš“) ir jo Dojo personažas - irgi naujas vardas
        q('UPDATE members m JOIN account_members am ON am.member_id = m.id SET m.first_name = ?, m.last_name = ? WHERE am.account_id = ? AND am.relation = "self"', [$fn, $ln, $aid]);
        sync_hero_names($aid, null, (string) $a['first_name'], $fn);
        flash('ok', t('Vardas pakeistas.'));
        redirect($back);
    }
    if ($action === 'profile') {
        q('UPDATE accounts SET phone = ? WHERE id = ?', [post('phone') ?: null, $aid]);
        flash('ok', t('Duomenys išsaugoti.'));
        redirect($back);
    }

    if ($action === 'email') {
        $email = normalize_email(post('email'));
        if (!password_verify((string) ($_POST['current'] ?? ''), $a['password_hash'])) {
            $errors[] = t('Neteisingas slaptažodis.');
        } elseif (!valid_email($email)) {
            $errors[] = t('Įveskite teisingą el. paštą.');
        } elseif ($email === $a['email']) {
            $errors[] = t('Tai jūsų dabartinis el. paštas.');
        } elseif (q_value('SELECT 1 FROM accounts WHERE email = ?', [$email])) {
            $errors[] = t('Šis el. paštas jau naudojamas kitos paskyros.');
        } else {
            // Pakeičiama tik paspaudus nuorodą naujame pašte - taip įsitikiname, kad adresas tikrai jūsų
            $token = token_create('change_email', $email, $aid);
            mail_send_tpl($email, 'change_email', ['name' => $a['first_name'], 'link' => abs_url('el-pastas.php?t=' . $token)], account_lang($a));
            flash('ok', "Išsiuntėme patvirtinimo nuorodą į $email. El. paštas pasikeis, kai ją paspausite.");
            redirect($back);
        }
    }

    // Prisijungimas su kodu: įjungiama tik patvirtinus kodą iš el. pašto (taip žinome, kad laiškai ateina)
    if ($action === 'tfa_start') {
        if (!password_verify((string) ($_POST['current'] ?? ''), $a['password_hash'])) {
            $errors[] = t('Neteisingas slaptažodis.');
        } elseif ($err = login_code_send($a, 'enable')) {
            $errors[] = $err;
        } else {
            $_SESSION['tfa_enable'] = time();
            flash('ok', (t('Išsiuntėme kodą į') . ' ') . masked_email($a['email']) . (' ' . t('- įveskite jį žemiau.')));
            redirect($back . '#tfa');
        }
    }
    if ($action === 'tfa_confirm') {
        $res = login_code_check($aid, 'enable', post('code'));
        if ($res === 'ok') {
            q('UPDATE accounts SET two_factor = 1 WHERE id = ?', [$aid]);
            unset($_SESSION['tfa_enable']);
            trust_device($aid);
            flash('ok', (t('Prisijungimas su kodu įjungtas. Šiame įrenginyje kodo neprašysime') . ' ') . TRUSTED_DEVICE_DAYS . (' ' . t('dienų.')));
            redirect($back . '#tfa');
        }
        $errors[] = $res === 'invalid' ? (t('Neteisingas kodas. Liko bandymų:') . ' ') . login_code_attempts_left($aid, 'enable') . '.' : t('Kodas nebegalioja - paspauskite „Siųsti kodą“ dar kartą.');
        if ($res === 'expired') {
            unset($_SESSION['tfa_enable']);
        }
    }
    if ($action === 'tfa_cancel') {
        unset($_SESSION['tfa_enable']);
        redirect($back . '#tfa');
    }
    if ($action === 'tfa_forget') {
        forget_devices($aid);
        flash('ok', t('Visi įrenginiai pamiršti - kitą kartą prisijungiant reikės kodo.'));
        redirect($back . '#tfa');
    }
    if ($action === 'tfa_off') {
        if (!password_verify((string) ($_POST['current'] ?? ''), $a['password_hash'])) {
            $errors[] = t('Neteisingas slaptažodis.');
        } else {
            q('UPDATE accounts SET two_factor = 0 WHERE id = ?', [$aid]);
            forget_devices($aid);
            flash('ok', t('Prisijungimas su kodu išjungtas.'));
            redirect($back . '#tfa');
        }
    }

    if ($action === 'password') {
        $pw = (string) ($_POST['password'] ?? '');
        if (!password_verify((string) ($_POST['current'] ?? ''), $a['password_hash'])) {
            $errors[] = t('Neteisingas dabartinis slaptažodis.');
        } elseif (mb_strlen($pw) < MIN_PASSWORD) {
            $errors[] = (t('Naujas slaptažodis turi būti bent') . ' ') . MIN_PASSWORD . (' ' . t('simbolių.'));
        } elseif (isset($_POST['password2']) && $pw !== (string) $_POST['password2']) {
            $errors[] = t('Slaptažodžiai nesutampa.');
        } else {
            account_password_set($aid, $pw);   // C1: kiti įrenginiai atjungiami
            flash('ok', t('Slaptažodis pakeistas. Kiti įrenginiai atjungti.'));
            redirect($back);
        }
    }
}
