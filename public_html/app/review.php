<?php
// v2.02: Peržiūra (tik tikri administratoriai - Deividas ir Denis).
//  1. Valdymas → 🔎 Peržiūra: atviri atsiliepimai trumpai, pažymimi, ką taisyti, + komentaras → „▶ Paleisti taisymą“ (darbas eilėje).
//     Taisymo agentas (debesyje, kas valandą 7-23 val., perziura-api.php) pasiima darbą, pataiso, paruošia - „⬆ Įkelti į test“.
//  2. Test svetainėje kiekvienas pakeistas elementas apvestas auksu (sąrašas - app/review.json, jį pildo agentas).
//     Prie kiekvieno: □ → ✓ gerai → ✗ blogai → □ ir komentaras; apačioje - „Pateikti“. Blogi → naujas taisymo ratas, visi geri → į main.
//  3. „🎯 Pastaba apie elementą“: bet kurį puslapio elementą galima pažymėti ir parašyti pastabą (atsiliepimas su elemento adresu).
// Gyvoje svetainėje (env = live) auksinių žymų nėra.

const REVIEW_KINDS = ['fix' => '🛠 Taisyti', 'deploy' => '⬆ Įkelti į test', 'review' => '🔎 Peržiūros rezultatas'];
const REVIEW_STATUS = ['queued' => ['Laukia', 'badge-warn'], 'working' => ['Daroma', 'badge-pink'], 'done' => ['Padaryta', 'badge-ok'],
                       'failed' => ['Nepavyko', 'badge-warn'], 'cancelled' => ['Atšaukta', '']];

/** Ar jau importuotas db/060 */
function review_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT 1 FROM review_jobs LIMIT 1');
            q_value('SELECT 1 FROM review_marks LIMIT 1');
            $ok = true;
        } catch (PDOException $ex) {
            $ok = false;
        }
    }
    return $ok;
}

/** Pakeitimų sąrašas peržiūrai (app/review.json): rev, version, title, items [{id, page, sel, title, note}] */
function review_manifest(): array
{
    static $m = null;
    if ($m !== null) {
        return $m;
    }
    $d = json_decode((string) @file_get_contents(APP_DIR . '/review.json'), true);
    $items = [];
    foreach ((array) ($d['items'] ?? []) as $it) {
        $id = (string) ($it['id'] ?? '');
        if (preg_match('/^[a-z0-9-]{1,64}$/', $id) && !empty($it['title'])) {
            $items[] = ['id' => $id, 'page' => (string) ($it['page'] ?? ''), 'sel' => (string) ($it['sel'] ?? ''), 'title' => (string) $it['title'],
                        'note' => (string) ($it['note'] ?? ''), 'href' => (string) ($it['href'] ?? '')];
        }
    }
    $rev = preg_match('/^[0-9a-z.-]{1,24}$/', (string) ($d['rev'] ?? '')) ? (string) $d['rev'] : '';
    return $m = ['rev' => $rev, 'version' => (string) ($d['version'] ?? ''), 'title' => (string) ($d['title'] ?? ''), 'items' => $rev !== '' ? $items : []];
}

/** Puslapio kelias be svetainės pradžios ir užklausos: „admin/index.php“ */
function review_page_now(): string
{
    $p = ltrim((string) parse_url(feedback_current_page(), PHP_URL_PATH), '/');
    return $p === '' ? 'index.php' : (substr($p, -1) === '/' ? $p . 'index.php' : $p);
}

/** Ar puslapis atitinka šabloną: „admin/*“, „admin/index.php|admin/nariai.php“, „*“ - visur */
function review_page_match(string $pattern, string $page): bool
{
    foreach (explode('|', $pattern) as $p) {
        $p = ltrim(trim($p), '/');
        if ($p === '') {
            continue;
        }
        if (substr($p, -1) === '/') {
            $p .= 'index.php';
        }
        if (fnmatch($p, $page)) {
            return true;
        }
    }
    return false;
}

/** Kur atidaryti pakeitimą iš sąrašo (pirmas tikslus puslapis iš šablono) */
function review_item_href(array $it): string
{
    if ($it['href'] !== '') {
        return url($it['href']) . '#rv-' . $it['id'];
    }
    foreach (explode('|', $it['page']) as $p) {
        $p = ltrim(trim($p), '/');
        if ($p !== '' && strpos($p, '*') === false) {
            return url($p) . '#rv-' . $it['id'];
        }
    }
    return url('admin/') . '#rv-' . $it['id'];
}

/** Auksinės žymos šiam žmogui: tik test / kompiuteryje, tik tikram administratoriui */
function review_can(?array $a = null): bool
{
    return config('env') !== 'live' && is_admin($a) && review_ready();
}

/** Mano įvertinimai: [change_id => ['mark' => good|bad|null, 'comment' => '', 'sent' => bool]] */
function review_marks_mine(string $rev, int $accountId): array
{
    $out = [];
    foreach (q_all('SELECT change_id, mark, comment, job_id FROM review_marks WHERE rev = ? AND account_id = ?', [$rev, $accountId]) as $r) {
        $out[$r['change_id']] = ['mark' => $r['mark'], 'comment' => (string) $r['comment'], 'sent' => $r['job_id'] !== null];
    }
    return $out;
}

/** Visų administratorių įvertinimai (Peržiūros puslapiui ir agentui) */
function review_marks_all(string $rev): array
{
    return q_all('SELECT m.change_id, m.mark, m.comment, m.job_id, m.updated_at, a.first_name, a.last_name FROM review_marks m
                  JOIN accounts a ON a.id = m.account_id WHERE m.rev = ? ORDER BY m.change_id, a.first_name', [$rev]);
}

function review_job_add(string $kind, array $items, string $note, ?array $a, string $rev = ''): int
{
    q('INSERT INTO review_jobs (kind, items, note, rev, account_id) VALUES (?, ?, ?, ?, ?)',
        [$kind, json_encode($items, JSON_UNESCAPED_UNICODE), mb_substr(trim($note), 0, 4000) ?: null, $rev ?: null, $a ? (int) $a['id'] : null]);
    return (int) db()->lastInsertId();
}

function review_jobs(int $limit = 30): array
{
    return q_all('SELECT j.*, a.first_name FROM review_jobs j LEFT JOIN accounts a ON a.id = j.account_id ORDER BY j.id DESC LIMIT ' . max(1, $limit));
}

function review_jobs_open_count(): int
{
    return review_ready() ? (int) q_value('SELECT COUNT(*) FROM review_jobs WHERE status IN ("queued", "working")') : 0;
}

/** Pakeitimai, kurių šis administratorius dar neįvertino (meniu skaičiukui) */
function review_todo_count(?array $a = null): int
{
    $a = $a ?? current_account();
    $m = review_manifest();
    if (!$a || !review_can($a) || !$m['items']) {
        return 0;
    }
    $mine = review_marks_mine($m['rev'], (int) $a['id']);
    return count(array_filter($m['items'], function ($it) use ($mine) { return empty($mine[$it['id']]['mark']); }));
}

/** Auksinės žymos, apačios juosta ir „🎯 Pastaba apie elementą“ (įterpiama kartu su 💡 mygtuku visuose puslapiuose) */
function review_overlay_html(): string
{
    $a = current_account();
    if (!review_can($a) || !empty($GLOBALS['review_overlay_done'])) {
        return '';
    }
    $GLOBALS['review_overlay_done'] = true;
    $m = review_manifest();
    $page = review_page_now();
    $mine = $m['rev'] !== '' ? review_marks_mine($m['rev'], (int) $a['id']) : [];
    $items = array_map(function ($it) use ($page, $mine) {
        return ['id' => $it['id'], 'title' => $it['title'], 'note' => $it['note'], 'sel' => $it['sel'], 'here' => review_page_match($it['page'], $page),
                'href' => review_item_href($it), 'mark' => $mine[$it['id']]['mark'] ?? null, 'comment' => $mine[$it['id']]['comment'] ?? ''];
    }, $m['items']);
    // Pateikta: kiekvienas pakeitimas įvertintas ir išsiųstas (pakeitus įvertinimą po pateikimo - vėl „nepateikta“)
    $sent = $items && !array_filter($items, function ($it) use ($mine) { return empty($mine[$it['id']]['sent']); });
    $cfg = ['api' => url('perziura-zymos.php'), 'csrf' => csrf_token(), 'rev' => $m['rev'], 'version' => $m['version'], 'title' => $m['title'],
            'items' => $items, 'sent' => $sent, 'page' => feedback_current_page(), 'site' => feedback_site_now(), 'admin' => url('admin/perziura.php'),
            't' => [
                'bar' => t('Peržiūra'), 'here' => t('šiame puslapyje'), 'rated' => t('įvertinta'), 'list' => t('Visi pakeitimai'), 'submit' => t('Pateikti'), 'submitted' => t('Pateikta'),
                'sent' => t('Pateikta - agentas pasiims per valandą'), 'resend' => t('Pateikti dar kartą'), 'hide' => t('Slėpti žymas'), 'show' => t('Rodyti žymas'),
                'good' => t('Gerai'), 'bad' => t('Blogai'), 'none' => t('Neįvertinta'), 'cycle' => t('Spauskite: □ → ✓ gerai → ✗ blogai → □'),
                'comment' => t('Komentaras'), 'commentPh' => t('Kas negerai arba ką pakeisti?'), 'missing' => t('šiame ekrane nesimato'),
                'open' => t('Atidaryti'), 'pick' => t('🎯 Pastaba apie elementą'), 'pickHint' => t('Spauskite bet kurį elementą puslapyje. Esc - atšaukti.'),
                'pickPh' => t('Ką pakeisti šiame elemente?'), 'pickSave' => t('Išsaugoti pastabą'), 'pickDone' => t('Pastaba išsaugota - matysite Peržiūroje.'),
                'cancel' => t('Atšaukti'), 'noItems' => t('Naujų pakeitimų peržiūrai nėra.'), 'askSubmit' => t('Pateikti įvertinimus? Blogi - bus taisomi, kai visi geri - keliama į main.'),
                'unrated' => t('Dar neįvertinta:'), 'err' => t('Nepavyko išsaugoti. Atnaujinkite puslapį.'), 'page' => t('Peržiūros puslapis'),
            ]];
    return '<link rel="stylesheet" href="' . e(asset_url('assets/review.css')) . '">'
        . '<div id="rv" class="rv" data-cfg="' . e(json_encode($cfg, JSON_UNESCAPED_UNICODE)) . '" hidden></div>'
        . '<script src="' . e(asset_url('assets/review.js')) . '" defer></script>';
}
