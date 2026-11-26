<?php
// Atsiliepimai apie svetainę (db/025, db/032): ryškus mygtukas „💡 Atsiliepimas“ apačioje kairėje visose svetainėse (karateka.lt, Taika, LTKF).
// Lange: rašai - išsaugoma pati (be mygtuko), Ctrl+V - įklijuoja ekrano nuotrauką (galima daug), Enter - išsaugo ir pradeda naują;
// apačioje - ankstesni atsiliepimai (pavadinimas, nuotraukų skaičius), paspaudus - galima pataisyti.
// Adminas mato visus: Valdymas → Atsiliepimai. Automatiškai jie skaitomi per atsiliepimai-api.php (raktas sukuriamas ten pat).

const FEEDBACK_STATUS = [
    'new'      => ['Naujas', 'badge-pink'],
    'planned'  => ['Bus pataisyta', 'badge-warn'],
    'question' => ['Reikia atsakymo', 'badge-warn'],   // v1.90: klausimas autoriui (db/054) - lange paryškinta, atsakoma iš karto
    'done'     => ['Pataisyta', 'badge-ok'],
    'declined' => ['Nedarysime', ''],
];
const FEEDBACK_MAX = 4000;
const FEEDBACK_SHOW = ['staff' => 'Tik treneriams ir adminui', 'members' => 'Tik prisijungusiems', 'all' => 'Visiems (ir neprisijungusiems)', 'off' => 'Niekam (išjungta)'];

/** Kam rodomas mygtukas: staff | members | all | off (numatyta - tik treneriams ir adminui; tėvai ir lankytojai jo nemato) */
function feedback_mode(): string
{
    $mode = (string) site_setting('feedback_button', '');
    return isset(FEEDBACK_SHOW[$mode]) ? $mode : 'staff';
}

function feedback_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT 1 FROM feedback LIMIT 1');
            $ok = true;
        } catch (PDOException $ex) {
            $ok = false;   // dar neimportuotas db/025
        }
    }
    return $ok;
}

/** Nauji (dar neperžiūrėti) atsiliepimai - adminui raudonas skaičiukas */
function feedback_new_count(): int
{
    return feedback_ready() ? (int) q_value('SELECT COUNT(*) FROM feedback WHERE status = "new"') : 0;
}

function feedback_status_badge(string $status): string
{
    [$label, $class] = FEEDBACK_STATUS[$status] ?? FEEDBACK_STATUS['new'];
    return '<span class="badge ' . $class . '">' . e(t($label)) . '</span>';
}

const FEEDBACK_SITES = ['karateka' => 'karateka.lt', 'taika' => 'Budo', 'ltkf' => 'LTKF'];

// ---------- Kur paliktas (v1.87): grupė Budo / Karateka / LTKF ir jos skiltis - pagal puslapį (seniems atsiliepimams irgi) ----------
const FEEDBACK_GROUPS = ['budo' => 'Budo', 'karateka' => 'Karateka', 'ltkf' => 'LTKF'];
/** Puslapis (be adreso pradžios) → [grupė, skiltis]. Budo valdyme skiltis - atidarytas skirtukas (assets/feedback.js įrašo jį į kontekstą). */
const FEEDBACK_PLACES = [
    'admin/varzybos.php' => ['budo', 'Varžybų sąrašas'], 'admin/varzybu-sistema.php' => ['budo', 'Varžybos'], 'varzybu-sistema.php' => ['budo', 'Varžybos'],
    'varzybos.php' => ['budo', 'Varžybos'], 'admin/varzybu-dalyviai.php' => ['budo', 'Administracija'], 'admin/varzybu-rezultatai.php' => ['budo', 'Rezultatai'],
    'admin/varzybos-isorine.php' => ['budo', 'Varžybų sąrašas'], 'admin/varzybos-sso.php' => ['budo', 'Varžybų sąrašas'],
    'varzybos-gyvai.php' => ['budo', 'Gyvai (žiūrovams)'], 'varzybos-registracija.php' => ['budo', 'Registracija'], 'varzybu-rezultatai.php' => ['budo', 'Rezultatai'],
    'varzybos-sekretoriatas.php' => ['budo', 'Sekretoriatas'], 'varzybos-apdovanojimai.php' => ['budo', 'Apdovanojimai'], 'varzybos-bandymas.php' => ['budo', 'Varžybos'],
    'admin/federacija.php' => ['ltkf', 'Registrai'], 'admin/ltkf-perziura.php' => ['ltkf', 'Redagavimas'], 'ltkf-perziura.php' => ['ltkf', 'Redagavimas'], 'ltkf-redaktorius.php' => ['ltkf', 'Redagavimas'],
    'admin/index.php' => ['karateka', 'Valdymas · Šiandien'], 'admin/treniruote.php' => ['karateka', 'Valdymas · Treniruotė'],
    'admin/egzaminai.php' => ['karateka', 'Valdymas · Egzaminai'], 'admin/egzamino-protokolas.php' => ['karateka', 'Valdymas · Egzaminai'], 'admin/programa.php' => ['karateka', 'Valdymas · Egzaminai'],
    'admin/renginiai.php' => ['karateka', 'Valdymas · Renginiai'], 'admin/renginio-dalyviai.php' => ['karateka', 'Valdymas · Renginiai'], 'admin/renginio-rezultatai.php' => ['karateka', 'Valdymas · Renginiai'],
    'admin/nariai.php' => ['karateka', 'Valdymas · Nariai'], 'admin/paskyros.php' => ['karateka', 'Valdymas · Nariai'], 'admin/grupes.php' => ['karateka', 'Valdymas · Nariai'], 'admin/atsaukimai.php' => ['karateka', 'Valdymas · Nariai'],
    'admin/taskai.php' => ['karateka', 'Valdymas · Aktyvumo taškai'], 'admin/testai.php' => ['karateka', 'Valdymas · Testai'], 'admin/testo-perziura.php' => ['karateka', 'Valdymas · Testai'],
    'admin/patvirtinimai.php' => ['karateka', 'Valdymas · Patvirtinimai'], 'admin/zinutes.php' => ['karateka', 'Valdymas · Žinutės'],
    'admin/pranesimai.php' => ['karateka', 'Valdymas · Pranešimai'], 'admin/siuntimas.php' => ['karateka', 'Valdymas · Pranešimai'], 'admin/sms.php' => ['karateka', 'Valdymas · Pranešimai'],
    'admin/atsiliepimai.php' => ['karateka', 'Valdymas · Atsiliepimai'], 'admin/pateisinimai.php' => ['karateka', 'Valdymas · Pateisinimai'],
    'pateisinimas.php' => ['karateka', 'Pateisinimai'], 'p/index.php' => ['karateka', 'Pateisinimai'], 'e/index.php' => ['karateka', 'Egzaminai'],
    'admin/naujienos.php' => ['karateka', 'Valdymas · Svetainės turinys'], 'admin/puslapis.php' => ['karateka', 'Valdymas · Svetainės turinys'], 'admin/pamokos.php' => ['karateka', 'Valdymas · Svetainės turinys'], 'admin/tvarkarastis.php' => ['karateka', 'Valdymas · Svetainės turinys'],
    'admin/nustatymai.php' => ['karateka', 'Valdymas · Nustatymai'], 'admin/nustatymai-visi.php' => ['karateka', 'Valdymas · Nustatymai'], 'admin/kopijos.php' => ['karateka', 'Valdymas · Nustatymai'],
    'admin/duomenys.php' => ['karateka', 'Valdymas · Nustatymai'], 'admin/vertimai.php' => ['karateka', 'Valdymas · Nustatymai'], 'admin/testavimas.php' => ['karateka', 'Valdymas · Nustatymai'],
    'admin/kitas-irenginys.php' => ['karateka', 'Valdymas · Nustatymai'], 'admin/kas-naujo.php' => ['karateka', 'Valdymas · Nustatymai'],
    'index.php' => ['karateka', 'Pradžia'], 'naujienos.php' => ['karateka', 'Naujienos ir renginiai'], 'renginiai.php' => ['karateka', 'Naujienos ir renginiai'],
    'renginys.php' => ['karateka', 'Naujienos ir renginiai'], 'renginys-dalyvavimas.php' => ['karateka', 'Naujienos ir renginiai'], 'mano-renginiai.php' => ['karateka', 'Naujienos ir renginiai'], 'kalendorius.php' => ['karateka', 'Naujienos ir renginiai'],
    'pamokos.php' => ['karateka', 'Pamokos'], 'paskyra.php' => ['karateka', 'Mano paskyra'], 'uzduotys.php' => ['karateka', 'Mano paskyra'], 'mano-egzaminas.php' => ['karateka', 'Mano paskyra'],
    'pranesimas.php' => ['karateka', 'Mano paskyra'], 'zinute.php' => ['karateka', 'Mano paskyra'], 'pokalbis.php' => ['karateka', 'Mano paskyra'],
    'testas.php' => ['karateka', 'Testai'], 'testas-svecias.php' => ['karateka', 'Testai'], 'egzaminas.php' => ['karateka', 'Egzaminai'], 'egzamino-registracija.php' => ['karateka', 'Egzaminai'],
    'dojo.php' => ['karateka', 'Dojo'], 'arena.php' => ['karateka', 'Dojo'], 'zaidimas.php' => ['karateka', 'Dojo'], 'zaidejas.php' => ['karateka', 'Dojo'], 'kova-bandymai.php' => ['karateka', 'Dojo'], 'ritmas.php' => ['karateka', 'Dojo'], 'keturi.php' => ['karateka', 'Dojo'],
    'prisijungti.php' => ['karateka', 'Prisijungimas'], 'registracija.php' => ['karateka', 'Prisijungimas'], 'slaptazodis.php' => ['karateka', 'Prisijungimas'], 'kodas.php' => ['karateka', 'Prisijungimas'],
    'jungtis.php' => ['karateka', 'Prisijungimas'], 'pakvietimas.php' => ['karateka', 'Prisijungimas'], 'tevu-sutikimas.php' => ['karateka', 'Prisijungimas'], 'irenginys.php' => ['karateka', 'Prisijungimas'],
];
/** LTKF svetainės puslapiai (ltkf/…) → skiltis */
const FEEDBACK_LTKF_PLACES = ['index.php' => 'Pradžia', 'en.php' => 'Pradžia', 'apie.php' => 'Apie', 'klubai.php' => 'Klubai', 'klubams.php' => 'Klubai', 'teisejai.php' => 'Teisėjai',
    'kalendorius.php' => 'Kalendorius ir rezultatai', 'renginys.php' => 'Kalendorius ir rezultatai', 'rezultatai.php' => 'Kalendorius ir rezultatai',
    'naujienos.php' => 'Naujienos', 'naujiena.php' => 'Naujienos', 'dokumentai.php' => 'Dokumentai', 'failai.php' => 'Dokumentai', 'kontaktai.php' => 'Kontaktai',
    'puslapis.php' => 'Kiti puslapiai', 'redaguoti.php' => 'Redagavimas'];
/** Budo valdymo skirtukai (assets/varzybos/chrome.js: tab-live, tab-rezultatai, Administracijos poskirtukai; atidaryta kova) */
const FEEDBACK_BUDO_TABS = ['live' => 'Tvarkaraštis', 'rezultatai' => 'Rezultatai', 'kova' => 'Kova'];

/**
 * Kur paliktas atsiliepimas: ['g' => 'budo', 'gl' => 'Budo', 's' => 'Tvarkaraštis' (raktas - lietuviškas pavadinimas), 'sl' => išverstas, 'sec' => smulkiau (pvz. „Administracija › Dalyviai“)].
 * $context - JSON tekstas arba masyvas iš lango (url, title, sk - skirtukų raktai, sec - skirtukų pavadinimai).
 */
function feedback_area(string $site, string $page, $context = null): array
{
    $ctx = is_array($context) ? $context : (json_decode((string) $context, true) ?: []);
    $path = (string) parse_url($page !== '' ? $page : (string) ($ctx['url'] ?? ''), PHP_URL_PATH);
    $base = (string) (config('base_path') ?: '');
    if ($base !== '' && strpos($path, $base . '/') === 0) {
        $path = substr($path, strlen($base));
    }
    $path = ltrim($path, '/');
    if ($path === '' || substr($path, -1) === '/') {
        $path .= 'index.php';
    }
    $sec = trim((string) ($ctx['sec'] ?? ''));
    if (strpos($path, 'ltkf/') === 0 || ($site === 'ltkf' && !isset(FEEDBACK_PLACES[$path]))) {
        [$g, $s] = ['ltkf', FEEDBACK_LTKF_PLACES[basename($path)] ?? 'Kiti puslapiai'];
    } elseif (isset(FEEDBACK_PLACES[$path])) {
        [$g, $s] = FEEDBACK_PLACES[$path];
    } else {
        // nežinomas puslapis: grupė pagal svetainę, skiltis - puslapio pavadinimas (iki „ — Karateka“)
        $g = $site === 'taika' ? 'budo' : ($site === 'ltkf' ? 'ltkf' : 'karateka');
        $title = trim((string) preg_replace('/\s*[—·|-]\s*(Karateka|Budo|Taika|LTKF).*$/u', '', (string) ($ctx['title'] ?? '')));
        $s = ($title !== '' ? mb_strimwidth($title, 0, 40, '…') : basename($path, '.php'));
        $s = strpos($path, 'admin/') === 0 ? 'Valdymas · ' . $s : $s;
    }
    // Budo valdymas: skiltis - atidarytas skirtukas (seniems - #live adrese, kitaip „Varžybos“)
    if ($s === 'Varžybos' && in_array($path, ['admin/varzybu-sistema.php', 'varzybu-sistema.php', 'varzybos-bandymas.php'], true)) {
        $first = strtok((string) ($ctx['sk'] ?? ''), '/');
        if ($first !== false && $first !== '') {
            $s = FEEDBACK_BUDO_TABS[$first] ?? 'Administracija';
        } elseif (strpos((string) ($ctx['url'] ?? $page), '#live') !== false) {
            $s = 'Tvarkaraštis';
        }
    }
    if ($path === 'varzybos-gyvai.php' && strpos((string) ($ctx['url'] ?? $page), 'ekranas=1') !== false) {
        $s = 'Ekranas salėje';
    }
    return ['g' => $g, 'gl' => FEEDBACK_GROUPS[$g], 's' => $s, 'sl' => t($s), 'sec' => mb_substr($sec, 0, 80)];
}

/** db/053: autoriaus įvertinimas („Patinka“ / „Grąžinti kaip buvo“) */
function feedback_verdict_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT verdict FROM feedback LIMIT 1');
            $ok = true;
        } catch (PDOException $ex) {
            $ok = false;
        }
    }
    return $ok;
}

/** Ar šis žmogus gali įvertinti pataisymą: atsiliepimo autorius (paskyra - bet kuriame įrenginyje), kai pažymėta „Pataisyta“ ir dar neįvertinta */
function feedback_can_verdict(?array $a, array $f): bool
{
    return feedback_verdict_ready() && ($f['status'] ?? '') === 'done' && empty($f['verdict'])
        && (($a && (int) $f['account_id'] === (int) $a['id']) || feedback_can_edit($a, $f));
}

/** db/054: klausimai autoriui („Reikia atsakymo“) ir jo atsakymas */
function feedback_question_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT answer FROM feedback LIMIT 1');
            $ok = true;
        } catch (PDOException $ex) {
            $ok = false;
        }
    }
    return $ok;
}

/** Ar šis žmogus turi atsakyti į klausimą: atsiliepimo autorius (paskyra - bet kuriame įrenginyje) */
function feedback_can_answer(?array $a, array $f): bool
{
    return feedback_question_ready() && ($f['status'] ?? '') === 'question'
        && (($a && (int) $f['account_id'] === (int) $a['id']) || feedback_can_edit($a, $f));
}

/** Kiek klausimų laukia šio žmogaus atsakymo (💡 mygtuko skaičius, „Reikia padaryti“) */
function feedback_questions_count(?array $a): int
{
    if (!$a || !feedback_ready() || !feedback_question_ready()) {
        return 0;
    }
    return (int) q_value('SELECT COUNT(*) FROM feedback WHERE account_id = ? AND status = "question"', [(int) $a['id']]);
}

/** Ar šis žmogus mato atsiliepimą apie šią vietą (testuotojai Dojo atsiliepimų nemato - žaidimas jiems paslėptas) */
function feedback_area_visible(array $area, ?array $a): bool
{
    return $area['s'] !== 'Dojo' || !function_exists('game_allowed') || game_allowed($a);
}

/** Nuotraukos iš karto daugeliui atsiliepimų (sąrašui - viena užklausa vietoj N) */
function feedback_images_prefetch(array $ids): void
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids || !feedback_images_ready()) {
        return;
    }
    $map = array_fill_keys($ids, []);
    foreach (q_all('SELECT * FROM feedback_images WHERE feedback_id IN (' . implode(',', $ids) . ') ORDER BY id') as $r) {
        $map[(int) $r['feedback_id']][] = $r;
    }
    $GLOBALS['fb_img_cache'] = $map + (array) ($GLOBALS['fb_img_cache'] ?? []);
}
const FEEDBACK_IMG_DIR = PUBLIC_DIR . '/uploads/feedback';
const FEEDBACK_MAX_IMAGES = 40;

/** Įrašo atsiliepimą. Grąžina id arba klaidos tekstą (string). $allowEmpty - kai pirmiausia įklijuojama nuotrauka. */
/** A3: slapti adreso parametrai (k, t, e, key) atsiliepimuose nesaugomi - pakeičiami „…“ */
function feedback_safe_page(string $page): string
{
    $page = (string) preg_replace('~(/[ep]/?\?)[^&#]*~i', '$1…', $page);   // v1.92-v1.93: trumpos nuorodos /e/?kodas, /p/?kodas
    return (string) preg_replace('/([?&](?:k|t|e|c|key|token)=)[^&#]*/i', '$1…', $page);   // C3: ir ?c= (senas telefonų QR)
}

function feedback_add(?array $a, string $body, string $page, string $name = '', string $site = 'karateka', ?string $context = null, bool $allowEmpty = false)
{
    $page = feedback_safe_page($page);
    // A3: ir kontekste (naršyklės adresas) slapti raktai nesaugomi
    if ($context !== null && ($cj = json_decode($context, true)) && isset($cj['url'])) {
        $cj['url'] = feedback_safe_page((string) $cj['url']);
        $context = json_encode($cj, JSON_UNESCAPED_UNICODE);
    }
    $body = trim(str_replace("\r\n", "\n", $body));
    if (!$allowEmpty && mb_strlen($body) < 3) {
        return t('Parašykite bent kelis žodžius.');
    }
    // Saugiklis nuo šlamšto: ne daugiau 10 per 10 min. iš vienos sesijos, neprisijungusių - ne daugiau 60 per valandą iš viso
    $_SESSION['fb_times'] = array_values(array_filter((array) ($_SESSION['fb_times'] ?? []), function ($t) { return $t > time() - 600; }));
    if (count($_SESSION['fb_times']) >= 10
        || (!$a && (int) q_value('SELECT COUNT(*) FROM feedback WHERE account_id IS NULL AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)') >= 60)) {
        return t('Per daug atsiliepimų per trumpą laiką - pabandykite vėliau.');
    }
    $_SESSION['fb_times'][] = time();
    if (!$a && ($demo = varz_demo())) {
        $name = (trim($name) ?: $demo['name']) . ' (Budo bandymas)';   // admin mato, kad tai bandantysis per nuorodą
    } elseif (!$a && ($sk = varz_sekr())) {
        $name = (trim($name) ?: $sk['name']) . ' (sekretoriatas)';
    } elseif (!$a && $site === 'ltkf' && ltkf_review()) {
        $name = (trim($name) ?: 'Svečias') . ' (LTKF peržiūra)';
    }
    try {
        q('INSERT INTO feedback (account_id, name, page, site, context, body) VALUES (?, ?, ?, ?, ?, ?)',
            [$a ? (int) $a['id'] : null, $a ? null : (mb_substr(trim($name), 0, 120) ?: null), mb_substr($page, 0, 255) ?: null,
             isset(FEEDBACK_SITES[$site]) ? $site : 'karateka', $context !== null ? mb_substr($context, 0, 2000) : null, mb_substr($body, 0, FEEDBACK_MAX)]);
    } catch (PDOException $ex) {
        // dar neimportuotas db/032 - be svetainės ir konteksto
        q('INSERT INTO feedback (account_id, name, page, body) VALUES (?, ?, ?, ?)',
            [$a ? (int) $a['id'] : null, $a ? null : (mb_substr(trim($name), 0, 120) ?: null), mb_substr($page, 0, 255) ?: null, mb_substr($body, 0, FEEDBACK_MAX)]);
    }
    $id = (int) db()->lastInsertId();
    if (feedback_writer_ready()) {
        q('UPDATE feedback SET writer = ? WHERE id = ?', [feedback_writer(), $id]);
    }
    if (!$a) {
        $_SESSION['fb_own'][] = $id;   // neprisijungęs gali taisyti tik savo (šios naršyklės) atsiliepimus
    }
    return $id;
}

function feedback_images_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT 1 FROM feedback_images LIMIT 1');
            $ok = true;
        } catch (PDOException $ex) {
            $ok = false;
        }
    }
    return $ok;
}

// ---------- Kas rašė: naršyklės žymė (slapukas fbw, db/040) ----------
// Net jei keli žmonės naudoja tą pačią paskyrą, kiekvienas mato („Mano“) ir taiso tik tai, ką parašė pats. „Visi“ - tik skaityti.

function feedback_writer_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT writer FROM feedback LIMIT 1');
            $ok = true;
        } catch (PDOException $ex) {
            $ok = false;
        }
    }
    return $ok;
}

/** Šios naršyklės žymė (sukuriama pirmą kartą; slapukas 2 metams) */
function feedback_writer(): string
{
    $w = (string) ($_COOKIE['fbw'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $w)) {
        $w = bin2hex(random_bytes(16));
        if (!headers_sent()) {
            setcookie('fbw', $w, ['expires' => time() + 2 * 365 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
        }
        $_COOKIE['fbw'] = $w;
    }
    return $w;
}

/** Ar šis žmogus gali taisyti atsiliepimą: tik autorius (paskyra + ta pati naršyklė) arba (neprisijungęs) šios sesijos autorius. Adminas kitų tekstų netaiso. */
function feedback_can_edit(?array $a, array $f): bool
{
    if ($a && (int) $f['account_id'] === (int) $a['id']) {
        $w = (string) ($f['writer'] ?? '');
        return $w === '' || hash_equals($w, feedback_writer());   // seni (be žymės) - paskyros savininkui
    }
    return !$a && !$f['account_id'] && in_array((int) $f['id'], array_map('intval', (array) ($_SESSION['fb_own'] ?? [])), true);
}

/** Pavadinimas sąraše: pirma eilutė (arba „Nuotrauka“) */
function feedback_title(array $f): string
{
    $first = trim((string) strtok(trim((string) $f['body']), "\n"));
    return $first !== '' ? mb_strimwidth($first, 0, 90, '…') : t('(be teksto)');
}

function feedback_images(int $id): array
{
    if (isset($GLOBALS['fb_img_cache'][$id])) {
        return $GLOBALS['fb_img_cache'][$id];
    }
    return feedback_images_ready() ? q_all('SELECT * FROM feedback_images WHERE feedback_id = ? ORDER BY id', [$id]) : [];
}

function feedback_image_url(array $img, bool $thumb = false): string
{
    return url('atsiliepimas.php?img=' . (int) $img['id'] . ($thumb ? '&thumb=1' : ''));
}

/** „Visi“ (tik skaityti): treneriams ir adminui - visų, kitiems - visi savo paskyros. Su autoriumi. */
function feedback_all(?array $a, int $limit = 400): array
{
    if (!$a) {
        return feedback_mine(null, $limit);
    }
    $rows = q_all('SELECT f.*, a.first_name, a.last_name FROM feedback f LEFT JOIN accounts a ON a.id = f.account_id'
        . (can('feedback_box', $a) ? '' : ' WHERE f.account_id = ' . (int) $a['id']) . ' ORDER BY f.id DESC LIMIT ' . $limit);
    return array_values(array_filter($rows, function ($f) use ($a) { return feedback_area_visible(feedback_row_area($f), $a); }));
}

/** Atsiliepimo vieta (eilutei iš DB) */
function feedback_row_area(array $f): array
{
    return feedback_area((string) ($f['site'] ?? ''), (string) ($f['page'] ?? ''), $f['context'] ?? null);
}

/** Nuoroda į vietą, kur paliktas (jei adrese nėra paslėptų raktų „…“) */
function feedback_page_href(array $f): string
{
    $p = (string) ($f['page'] ?? '');
    if ($p === '' || strpos($p, '…') !== false || $p[0] !== '/') {
        return '';
    }
    return strpos($p, '/ltkf/') !== false ? $p : url(ltrim($p, '/'));
}

/** Atsiliepimas JSON (langui): tekstas, pavadinimas, nuotraukos */
function feedback_json(array $f): array
{
    $ar = feedback_row_area($f);
    $viewer = current_account();
    return ['g' => $ar['g'], 'gl' => $ar['gl'], 's' => $ar['s'], 'sl' => $ar['sl'], 'sec' => $ar['sec'],
            'href' => ($viewer && can('feedback_box', $viewer)) || feedback_can_edit($viewer, $f) ? feedback_page_href($f) : '',
            'verdict' => (string) ($f['verdict'] ?? ''), 'canVerdict' => feedback_can_verdict($viewer, $f),
            'answer' => (string) ($f['answer'] ?? ''), 'canAnswer' => feedback_can_answer($viewer, $f),
            'id' => (int) $f['id'], 'title' => feedback_title($f), 'body' => (string) $f['body'], 'status' => $f['status'], 'statusLabel' => t(FEEDBACK_STATUS[$f['status']][0] ?? ''),
            'reply' => (string) $f['reply'], 'page' => (string) $f['page'], 'site' => (string) ($f['site'] ?? ''), 'siteLabel' => FEEDBACK_SITES[$f['site'] ?? ''] ?? '', 'date' => fmt_date(substr($f['created_at'], 0, 10)),
            'images' => array_map(function ($i) { return ['id' => (int) $i['id'], 'thumb' => feedback_image_url($i, true), 'full' => feedback_image_url($i)]; }, feedback_images((int) $f['id'])),
            'author' => trim((string) ($f['first_name'] ?? '') . ' ' . (string) ($f['last_name'] ?? '')) ?: (string) ($f['name'] ?? ''), 'mine' => feedback_can_edit(current_account(), $f)];
}

/** Paskutiniai šio žmogaus atsiliepimai (langui): paskyros ir šios naršyklės */
function feedback_mine(?array $a, int $limit = 100): array
{
    if ($a) {
        // + paskyros pataisyti, dar neįvertinti (iš kito įrenginio) - kad „Patinka“ / „Grąžinti“ būtų matomi visur
        return feedback_writer_ready()
            ? q_all('SELECT * FROM feedback WHERE account_id = ? AND (writer = ?' . (feedback_verdict_ready() ? ' OR (status = "done" AND verdict IS NULL)' : '') . (feedback_question_ready() ? ' OR status = "question"' : '') . ') ORDER BY id DESC LIMIT ' . $limit, [(int) $a['id'], feedback_writer()])
            : q_all('SELECT * FROM feedback WHERE account_id = ? ORDER BY id DESC LIMIT ' . $limit, [(int) $a['id']]);
    }
    $ids = array_map('intval', (array) ($_SESSION['fb_own'] ?? []));
    return $ids ? q_all('SELECT * FROM feedback WHERE account_id IS NULL AND id IN (' . implode(',', $ids) . ') ORDER BY id DESC LIMIT ' . $limit) : [];
}

/** Nuotraukos įkėlimas prie atsiliepimo (ekrano nuotrauka - PNG paverčiama JPEG, iki 1600 px) */
function feedback_add_image(int $id, array $file): array
{
    require_once APP_DIR . '/images.php';
    if ((int) q_value('SELECT COUNT(*) FROM feedback_images WHERE feedback_id = ?', [$id]) >= FEEDBACK_MAX_IMAGES) {
        throw new RuntimeException(t('Prie vieno atsiliepimo - daugiausia {n} nuotraukų.', ['n' => FEEDBACK_MAX_IMAGES]));
    }
    if (!is_dir(FEEDBACK_IMG_DIR)) {
        @mkdir(FEEDBACK_IMG_DIR, 0755, true);
        // Nuotraukos rodomos tik per atsiliepimas.php (su teisių patikra), tiesiogiai - ne
        @file_put_contents(FEEDBACK_IMG_DIR . '/.htaccess', "Require all denied\n");
    }
    $name = save_uploaded_image($file, FEEDBACK_IMG_DIR);
    q('INSERT INTO feedback_images (feedback_id, file) VALUES (?, ?)', [$id, $name]);
    $imgId = (int) db()->lastInsertId();   // prieš kitą užklausą (UPDATE jį nunulina)
    q('UPDATE feedback SET updated_at = NOW() WHERE id = ?', [$id]);
    return q_one('SELECT * FROM feedback_images WHERE id = ?', [$imgId]) ?: [];
}

function feedback_delete_images(int $feedbackId, ?int $imageId = null): void
{
    if (!feedback_images_ready()) {
        return;
    }
    $rows = $imageId ? q_all('SELECT * FROM feedback_images WHERE id = ? AND feedback_id = ?', [$imageId, $feedbackId]) : feedback_images($feedbackId);
    foreach ($rows as $r) {
        @unlink(FEEDBACK_IMG_DIR . '/' . basename($r['file']));
        @unlink(FEEDBACK_IMG_DIR . '/thumb_' . basename($r['file']));
        q('DELETE FROM feedback_images WHERE id = ?', [(int) $r['id']]);
    }
}

/** API prieiga (atsiliepimai-api.php): rakto patikra (saugomas tik hash) */
function feedback_api_check(string $key): bool
{
    $hash = (string) site_setting('feedback_api_key', '');
    return $key !== '' && $hash !== '' && password_verify($key, $hash);
}

/** Kuriame puslapyje rašoma (be svetainės adreso pradžios), pvz. „/paskyra.php?skiltis=nustatymai“ */
function feedback_current_page(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $base = (string) (config('base_path') ?: '');
    if ($base !== '' && strpos($uri, $base . '/') === 0) {
        $uri = substr($uri, strlen($base));
    }
    return mb_substr($uri, 0, 255);
}

/** Kurioje svetainėje esame: LTKF, Taika ar karateka.lt */
function feedback_site_now(): string
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if (strpos($script, '/ltkf/') !== false) {
        return 'ltkf';
    }
    $taika = ['varzybu-sistema.php', 'varzybu-rezultatai.php', 'varzybos-gyvai.php', 'varzybos-registracija.php',
              'varzybos-sekretoriatas.php', 'varzybos-apdovanojimai.php', 'varzybos-bandymas.php', 'varzybos-kamera.php', 'varzybu-transliacija.php'];
    return in_array(basename($script), $taika, true) || substr($script, -strlen('/admin/varzybos.php')) === '/admin/varzybos.php' ? 'taika' : 'karateka';
}

/** LTKF peržiūros nuoroda federacijos nariams (be paskyros): admin → admin/ltkf-perziura.php; atsidarius - gali palikti atsiliepimus LTKF svetainėje */
function ltkf_review_token(): string
{
    return (string) link_token(link_current('ltkf_review'));   // C3: app/links.php (rūšis 'ltkf_review')
}

/** Peržiūros nuorodos sesija (arba LTKF redaktorius - jis irgi gali palikti atsiliepimų) */
function ltkf_review(): bool
{
    if (link_alive('ltkf_review', (string) ($_SESSION['ltkf_review'] ?? ''))) {
        return true;
    }
    $ed = (string) ($_SESSION['ltkf_editor'] ?? '');
    return $ed !== '' && link_alive('ltkf_editor', preg_match('/^[a-f0-9]{64}$/', $ed) ? $ed : hash('sha256', $ed)) !== null;
}

function ltkf_review_start(string $token): bool
{
    $l = link_find('ltkf_review', $token);
    if (!$l) {
        return false;
    }
    $_SESSION['ltkf_review'] = (string) $l['token_hash'];
    return true;
}

/** Ar rodyti mygtuką šiam žmogui ($site - LTKF langui: peržiūros nuoroda leidžia ir be paskyros) */
function feedback_visible(?array $a, ?string $site = null): bool
{
    $mode = feedback_mode();
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    // Taikos bandymo nuoroda (be paskyros) - atsiliepimus palikti galima ir tada
    $who = $mode === 'all' || ($mode === 'members' && $a) || ($mode === 'staff' && $a && can('valdymas', $a));
    return feedback_ready() && $mode !== 'off' && ($who || varz_demo() || varz_sekr() || ($site === 'ltkf' && ltkf_review())) && !in_array($script, ['testas.php', 'egzaminas.php', 'testas-svecias.php'], true);
}

/** Mygtukas ir langas (visuose puslapiuose, apačioje kairėje). $site - jei kviečiama iš kitos svetainės (LTKF) */
function feedback_widget_html(?string $site = null): string
{
    $a = current_account();
    $rv = function_exists('review_overlay_html') ? review_overlay_html() : '';   // v2.02: auksinės žymos test svetainėje (administratoriams)
    if (!feedback_visible($a, $site)) {
        return $rv;
    }
    $remote = $site !== null;   // kita svetainė (LTKF) - puslapio adresą paims naršyklė
    $site = $site ?? feedback_site_now();
    $icon = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12 2a7 7 0 0 0-4 12.74V17a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-2.26A7 7 0 0 0 12 2Zm-3 18a1 1 0 0 0 1 1h4a1 1 0 0 0 0-2h-4a1 1 0 0 0-1 1Z"/></svg>';
    $cfg = ['api' => url('atsiliepimas.php'), 'csrf' => csrf_token(), 'site' => $site, 'page' => $remote ? '' : feedback_current_page(), 'guest' => !$a,
            'admin' => can('feedback', $a) ? url('admin/atsiliepimai.php') : '', 'images' => feedback_images_ready(), 'questions' => feedback_questions_count($a)];
    $staff = $a && can('feedback_box', $a);
    return $rv . '<div class="fb" id="fb" data-cfg="' . e(json_encode($cfg, JSON_UNESCAPED_UNICODE)) . '">'
        . '<button type="button" class="fb-fab" id="fbFab" aria-expanded="false" aria-controls="fbPanel" title="' . e(t('Palikite komentarą ar ekrano nuotrauką - pataisysime')) . '">' . $icon
        . '<span class="fb-fab-label">' . te('Atsiliepimas') . '</span><span class="fb-fab-n" hidden></span></button>'
        . '<div class="fb-panel" id="fbPanel" role="dialog" aria-labelledby="fbTitle" hidden>'
        . '<div class="fb-head"><strong id="fbTitle" data-all="' . e(t('📋 Visi atsiliepimai')) . '">' . te('💡 Atsiliepimas') . '</strong><span class="fb-state" id="fbState" role="status"></span>'
        . '<button type="button" class="fb-close" aria-label="' . e(t('Uždaryti')) . '">×</button></div>'
        . '<div class="fb-scroll">'
        . '<div class="fb-write" id="fbWrite">'
        . '<div class="fb-compose" id="fbCompose">'
        . '<div class="fb-editing" id="fbEditing" hidden><span></span><button type="button" class="fb-link fb-del" title="' . e(t('Ištrinti šį atsiliepimą')) . '">🗑</button><button type="button" class="fb-link" id="fbNew">' . te('+ Naujas') . '</button></div>'
        . '<textarea id="fbText" rows="6" maxlength="' . FEEDBACK_MAX . '" placeholder="' . e(t('Kas negerai arba ką pakeisti? Ctrl+V - įklijuokite ekrano nuotrauką.')) . '" aria-label="' . e(t('Atsiliepimas')) . '"></textarea>'
        . '<div class="fb-imgs" id="fbImgs"></div>'
        . ($a ? '' : '<input type="text" id="fbName" maxlength="120" value="' . e((string) (varz_demo()['name'] ?? (varz_sekr()['name'] ?? ''))) . '" placeholder="' . e(t('Vardas ar el. paštas (nebūtina)')) . '" aria-label="' . e(t('Vardas ar el. paštas (nebūtina)')) . '">')
        . '<div class="fb-actions">'
        . ($cfg['images'] ? '<label class="fb-attach" title="' . e(t('Pridėti nuotraukų (arba Ctrl+V, ar nutempkite čia)')) . '">📎 ' . te('Nuotraukos') . '<input type="file" id="fbFile" accept="image/*" multiple hidden></label>' : '')
        . '<span class="fb-hint">' . te('Enter - išsaugoti ir naujas · Shift+Enter - nauja eilutė') . '</span>'
        . '<button class="btn btn-primary btn-sm fb-send" type="button" id="fbSend">' . te('Išsaugoti') . '</button></div>'
        . '</div>'
        . '<div class="fb-mine" id="fbMine"><div class="fb-mine-title">' . te('Jūsų atsiliepimai') . '</div><ul class="fb-list" id="fbList"></ul></div>'
        // v1.87: personalui - atskiras vaizdas „Visi atsiliepimai“ (vieta, būsena, paieška - išskleidžiami sąrašai); rašymo vaizdas lieka paprastas
        . ($staff ? '<button type="button" class="fb-all-btn" id="fbAllBtn">' . te('📋 Visi atsiliepimai') . ' <span class="fb-all-n" id="fbAllN" hidden></span><span aria-hidden="true">→</span></button>' : '')
        . '</div>'
        . ($staff ? '<div class="fb-all" id="fbAll" hidden>'
            . '<button type="button" class="fb-back" id="fbBack">' . te('← Rašyti atsiliepimą') . '</button>'
            . '<div class="fb-filters"><select id="fbPlace" aria-label="' . e(t('Kur paliktas')) . '"></select>'
            . '<select id="fbSt" aria-label="' . e(t('Būsena')) . '"><option value="open">' . te('Neišspręsti') . '</option><option value="done">' . te('Išspręsti') . '</option><option value="all">' . te('Visos būsenos') . '</option></select>'
            . '<input type="search" id="fbQ" placeholder="' . e(t('🔎 Ieškoti')) . '" aria-label="' . e(t('Ieškoti atsiliepimų')) . '"></div>'
            . '<ul class="fb-list" id="fbAllList"></ul>'
            . ($cfg['admin'] ? '<a class="fb-admin" href="' . e($cfg['admin']) . '">' . te('Atsiliepimų puslapis Valdyme →') . '</a>' : '')
            . '</div>' : '')
        . '</div></div></div><script src="' . e(asset_url('assets/feedback.js')) . '" defer></script>';
}
