<?php
// Atsiliepimai apie svetainę (db/025, db/032): ryškus mygtukas „💡 Atsiliepimas“ apačioje kairėje visose svetainėse (karateka.lt, Taika, LTKF).
// Lange: rašai - išsaugoma pati (be mygtuko), Ctrl+V - įklijuoja ekrano nuotrauką (galima daug), Enter - išsaugo ir pradeda naują;
// apačioje - ankstesni atsiliepimai (pavadinimas, nuotraukų skaičius), paspaudus - galima pataisyti.
// Adminas mato visus: Valdymas → Atsiliepimai. Claude juos skaito per atsiliepimai-api.php (raktas sukuriamas ten pat).

const FEEDBACK_STATUS = [
    'new'      => ['Naujas', 'badge-pink'],
    'planned'  => ['Bus pataisyta', 'badge-warn'],
    'done'     => ['Pataisyta', 'badge-ok'],
    'declined' => ['Nedarysime', ''],
];
const FEEDBACK_MAX = 4000;
const FEEDBACK_SHOW = ['all' => 'Visiems (ir neprisijungusiems)', 'members' => 'Tik prisijungusiems', 'off' => 'Niekam (išjungta)'];

/** Kam rodomas mygtukas: all | members | off (numatyta: test svetainėje - visiems, oficialioje - prisijungusiems) */
function feedback_mode(): string
{
    $mode = (string) site_setting('feedback_button', '');
    return isset(FEEDBACK_SHOW[$mode]) ? $mode : (config('env') === 'live' ? 'members' : 'all');
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

const FEEDBACK_SITES = ['karateka' => 'karateka.lt', 'taika' => 'Taika', 'ltkf' => 'LTKF'];
const FEEDBACK_IMG_DIR = PUBLIC_DIR . '/uploads/feedback';
const FEEDBACK_MAX_IMAGES = 40;

/** Įrašo atsiliepimą. Grąžina id arba klaidos tekstą (string). $allowEmpty - kai pirmiausia įklijuojama nuotrauka. */
function feedback_add(?array $a, string $body, string $page, string $name = '', string $site = 'karateka', ?string $context = null, bool $allowEmpty = false)
{
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

/** Ar šis žmogus gali taisyti / matyti atsiliepimą: autorius, adminas arba (neprisijungęs) šios sesijos autorius */
function feedback_can_edit(?array $a, array $f): bool
{
    if ($a && (is_admin($a) || (int) $f['account_id'] === (int) $a['id'])) {
        return true;
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
    return feedback_images_ready() ? q_all('SELECT * FROM feedback_images WHERE feedback_id = ? ORDER BY id', [$id]) : [];
}

function feedback_image_url(array $img, bool $thumb = false): string
{
    return url('atsiliepimas.php?img=' . (int) $img['id'] . ($thumb ? '&thumb=1' : ''));
}

/** Atsiliepimas JSON (langui): tekstas, pavadinimas, nuotraukos */
function feedback_json(array $f): array
{
    return ['id' => (int) $f['id'], 'title' => feedback_title($f), 'body' => (string) $f['body'], 'status' => $f['status'], 'statusLabel' => t(FEEDBACK_STATUS[$f['status']][0] ?? ''),
            'reply' => (string) $f['reply'], 'page' => (string) $f['page'], 'site' => (string) ($f['site'] ?? ''), 'date' => fmt_date(substr($f['created_at'], 0, 10)),
            'images' => array_map(function ($i) { return ['id' => (int) $i['id'], 'thumb' => feedback_image_url($i, true), 'full' => feedback_image_url($i)]; }, feedback_images((int) $f['id']))];
}

/** Paskutiniai šio žmogaus atsiliepimai (langui) */
function feedback_mine(?array $a, int $limit = 30): array
{
    if ($a) {
        return q_all('SELECT * FROM feedback WHERE account_id = ? ORDER BY id DESC LIMIT ' . $limit, [(int) $a['id']]);
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

/** Claude prieiga (atsiliepimai-api.php): rakto patikra (saugomas tik hash) */
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
    $taika = ['varzybu-sistema.php', 'varzybu-rezultatai.php', 'varzybos-gyvai.php', 'varzybos-registracija.php'];
    return in_array(basename($script), $taika, true) || substr($script, -strlen('/admin/varzybos.php')) === '/admin/varzybos.php' ? 'taika' : 'karateka';
}

/** Ar rodyti mygtuką šiam žmogui */
function feedback_visible(?array $a): bool
{
    $mode = feedback_mode();
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    return feedback_ready() && $mode !== 'off' && ($mode === 'all' || $a) && !in_array($script, ['testas.php', 'egzaminas.php', 'testas-svecias.php'], true);
}

/** Mygtukas ir langas (visuose puslapiuose, apačioje kairėje). $site - jei kviečiama iš kitos svetainės (LTKF) */
function feedback_widget_html(?string $site = null): string
{
    $a = current_account();
    if (!feedback_visible($a)) {
        return '';
    }
    $remote = $site !== null;   // kita svetainė (LTKF) - puslapio adresą paims naršyklė
    $site = $site ?? feedback_site_now();
    $icon = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12 2a7 7 0 0 0-4 12.74V17a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-2.26A7 7 0 0 0 12 2Zm-3 18a1 1 0 0 0 1 1h4a1 1 0 0 0 0-2h-4a1 1 0 0 0-1 1Z"/></svg>';
    $cfg = ['api' => url('atsiliepimas.php'), 'csrf' => csrf_token(), 'site' => $site, 'page' => $remote ? '' : feedback_current_page(), 'guest' => !$a,
            'admin' => is_admin($a) ? url('admin/atsiliepimai.php') : '', 'images' => feedback_images_ready()];
    return '<div class="fb" id="fb" data-cfg="' . e(json_encode($cfg, JSON_UNESCAPED_UNICODE)) . '">'
        . '<button type="button" class="fb-fab" id="fbFab" aria-expanded="false" aria-controls="fbPanel" title="' . e(t('Palikite komentarą ar ekrano nuotrauką - pataisysime')) . '">' . $icon
        . '<span class="fb-fab-label">' . te('Atsiliepimas') . '</span><span class="fb-fab-n" hidden></span></button>'
        . '<div class="fb-panel" id="fbPanel" role="dialog" aria-labelledby="fbTitle" hidden>'
        . '<div class="fb-head"><strong id="fbTitle">' . te('💡 Atsiliepimas') . '</strong><span class="fb-state" id="fbState" role="status"></span>'
        . '<button type="button" class="fb-close" aria-label="' . e(t('Uždaryti')) . '">×</button></div>'
        . '<div class="fb-scroll">'
        . '<div class="fb-compose" id="fbCompose">'
        . '<div class="fb-editing" id="fbEditing" hidden><span></span><button type="button" class="fb-link fb-del" title="' . e(t('Ištrinti šį atsiliepimą')) . '">🗑</button><button type="button" class="fb-link" id="fbNew">' . te('+ Naujas') . '</button></div>'
        . '<textarea id="fbText" rows="4" maxlength="' . FEEDBACK_MAX . '" placeholder="' . e(t('Kas negerai arba ką pakeisti? Ctrl+V - įklijuokite ekrano nuotrauką.')) . '" aria-label="' . e(t('Atsiliepimas')) . '"></textarea>'
        . '<div class="fb-imgs" id="fbImgs"></div>'
        . ($a ? '' : '<input type="text" id="fbName" maxlength="120" placeholder="' . e(t('Vardas ar el. paštas (nebūtina)')) . '" aria-label="' . e(t('Vardas ar el. paštas (nebūtina)')) . '">')
        . '<div class="fb-actions">'
        . ($cfg['images'] ? '<label class="fb-attach" title="' . e(t('Pridėti nuotraukų (arba Ctrl+V, ar nutempkite čia)')) . '">📎 ' . te('Nuotraukos') . '<input type="file" id="fbFile" accept="image/*" multiple hidden></label>' : '')
        . '<span class="fb-hint">' . te('Enter - išsaugoti ir naujas · Shift+Enter - nauja eilutė') . '</span>'
        . '<button class="btn btn-primary btn-sm fb-send" type="button" id="fbSend">' . te('Išsaugoti') . '</button></div>'
        . '</div>'
        . '<div class="fb-mine" id="fbMine" hidden><div class="fb-mine-title">' . te('Jūsų atsiliepimai') . '</div><ul class="fb-list" id="fbList"></ul></div>'
        . ($cfg['admin'] ? '<a class="fb-admin" href="' . e($cfg['admin']) . '">' . te('Visi atsiliepimai →') . '</a>' : '')
        . '</div></div></div><script src="' . e(asset_url('assets/feedback.js')) . '" defer></script>';
}
