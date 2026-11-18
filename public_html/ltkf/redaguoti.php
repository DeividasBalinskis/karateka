<?php
// LTKF redagavimo veiksmai (JSON): išsaugoti puslapį, atšaukti, įkelti nuotrauką ar failą, meniu, išvaizda ir kontaktai,
// nauji puslapiai, failų dėžė. Gali karateka.lt adminas arba redaktorius su nuoroda (admin/ltkf-perziura.php).
require dirname(__DIR__) . '/app/bootstrap.php';
require_once APP_DIR . '/images.php';
require_once __DIR__ . '/inc/bendri.php';

const LTKF_UP = PUBLIC_DIR . '/uploads/ltkf';
const LTKF_FILE_EXT = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf', 'txt', 'csv', 'zip', 'rar', '7z', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'mp4', 'mov', 'mp3', 'svg'];
const LTKF_FILE_MAX = 60 * 1024 * 1024;

/** Kas redaguoja: vardas arba null */
function ltkf_who(): ?string
{
    if (is_admin()) {
        $a = current_account();
        return trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?: 'Adminas';
    }
    $tok = (string) ($_SESSION['ltkf_editor'] ?? '');
    if ($tok !== '') {
        foreach ((array) json_decode((string) site_setting('ltkf_editors', '[]'), true) as $ed) {
            if (is_array($ed) && !empty($ed['on']) && hash_equals((string) $ed['t'], $tok)) {
                return (string) ($_SESSION['ltkf_editor_name'] ?? $ed['n']);
            }
        }
    }
    return null;
}

function out(array $d, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

$who = ltkf_who();
if ($who === null) {
    out(['ok' => false, 'error' => 'Redaguoti gali tik redaktoriai. Atidarykite savo redaktoriaus nuorodą.'], 403);
}

// Failų dėžės failo atsisiuntimas (tik redaktoriams)
if (!is_post() && get('get')) {
    $f = q_one('SELECT * FROM ltkf_files WHERE id = ?', [(int) get('get')]);
    $path = $f ? LTKF_UP . '/deze/' . basename((string) $f['file']) : '';
    if (!$f || !is_file($path)) {
        http_response_code(404);
        exit('Failas nerastas.');
    }
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($path));
    header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode((string) $f['orig']));
    readfile($path);
    exit;
}
if (!is_post()) {
    redirect('ltkf/index.php');
}
csrf_check();
$action = post('action');

function ltkf_slug_ok(string $s): bool
{
    return (bool) preg_match('/^(_bendri|[a-z0-9_-]{1,40}|p:[a-z0-9-]{1,60})$/', $s);
}

function ltkf_load(string $slug): array
{
    $d = json_decode((string) q_value('SELECT data FROM ltkf_pages WHERE slug = ?', [$slug]), true);
    return is_array($d) ? $d : ['texts' => [], 'hidden' => [], 'blocks' => []];
}

function ltkf_store(string $slug, array $data, string $who): void
{
    $old = q_value('SELECT data FROM ltkf_pages WHERE slug = ?', [$slug]);
    q('INSERT INTO ltkf_pages (slug, data, prev, updated_by) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE prev = data, data = VALUES(data), updated_by = VALUES(updated_by)',
        [$slug, json_encode($data, JSON_UNESCAPED_UNICODE), $old === false ? '{"texts":{},"hidden":[],"blocks":{}}' : $old, mb_substr($who, 0, 120)]);
}

/** Saugus failo vardas iš originalo */
function ltkf_file_name(string $orig): array
{
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', pathinfo($orig, PATHINFO_FILENAME)) ?: 'failas');
    return [$ext, trim(substr($base, 0, 60), '-.') ?: 'failas'];
}

switch ($action) {
    // ---------- Puslapis: tekstai, paslėptos skiltys, blokai ----------
    case 'save':
        $pages = json_decode((string) ($_POST['pages'] ?? ''), true);
        if (!is_array($pages)) {
            out(['ok' => false, 'error' => 'Nepavyko perskaityti pakeitimų - atnaujinkite puslapį.']);
        }
        foreach ($pages as $slug => $in) {
            $slug = (string) $slug;
            if (!ltkf_slug_ok($slug) || !is_array($in)) {
                continue;
            }
            $d = ltkf_load($slug);
            $texts = (array) ($d['texts'] ?? []);
            foreach ((array) ($in['texts'] ?? []) as $orig => $new) {
                $orig = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', (string) $orig));
                $new = trim(preg_replace("/[ \t\x{00A0}]+/u", ' ', str_replace("\r", '', (string) $new)));
                $new = preg_replace("/\n{3,}/", "\n\n", $new);
                if ($orig === '' || mb_strlen($orig) > 5000) {
                    continue;
                }
                if ($new === $orig) {
                    unset($texts[$orig]);
                } else {
                    $texts[$orig] = mb_substr($new, 0, 5000);
                }
            }
            $d['texts'] = $texts;
            if (array_key_exists('hidden', $in)) {
                $d['hidden'] = array_values(array_unique(array_map('strval', array_filter((array) $in['hidden'], 'is_scalar'))));
            }
            if (array_key_exists('deleted', $in)) {
                $d['deleted'] = array_values(array_unique(array_map('strval', array_filter((array) $in['deleted'], function ($v) { return is_scalar($v) && preg_match('/^\d{1,3}$/', (string) $v); }))));
            }
            if (array_key_exists('secbg', $in)) {
                $bgs = [];
                foreach ((array) $in['secbg'] as $k => $bg) {
                    if (!preg_match('/^\d{1,3}$/', (string) $k) || !is_array($bg)) {
                        continue;
                    }
                    $c = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($bg['c'] ?? '')) ? (string) $bg['c'] : '';
                    $img = preg_match('~^\.\./uploads/ltkf/[A-Za-z0-9._-]+$~', (string) ($bg['img'] ?? '')) ? (string) $bg['img'] : '';
                    if ($c !== '' || $img !== '') {
                        $bgs[(string) $k] = ['c' => $c, 'img' => $img, 'light' => !empty($bg['light']) ? 1 : 0];
                    }
                }
                $d['secbg'] = $bgs;
            }
            if (array_key_exists('blocks', $in)) {
                $blocks = [];
                foreach ((array) $in['blocks'] as $zone => $list) {
                    if (!preg_match('/^(pradzia|po-\d{1,3})$/', (string) $zone)) {
                        continue;
                    }
                    $clean = array_values(array_filter(array_map('ltkf_clean_block', (array) $list)));
                    if ($clean) {
                        $blocks[$zone] = $clean;
                    }
                }
                $d['blocks'] = $blocks;
            }
            if (strpos($slug, 'p:') === 0 && isset($in['title']) && trim((string) $in['title']) !== '') {
                $d['title'] = mb_substr(trim((string) $in['title']), 0, 120);
            }
            ltkf_store($slug, $d, $who);
        }
        out(['ok' => true]);

    case 'undo':
        $slug = (string) post('slug');
        $row = ltkf_slug_ok($slug) ? q_one('SELECT data, prev FROM ltkf_pages WHERE slug = ?', [$slug]) : null;
        if (!$row || $row['prev'] === null) {
            out(['ok' => false, 'error' => 'Ankstesnės versijos nėra.']);
        }
        q('UPDATE ltkf_pages SET data = ?, prev = ?, updated_by = ? WHERE slug = ?', [$row['prev'], $row['data'], mb_substr($who, 0, 120), $slug]);
        out(['ok' => true]);

    // ---------- Įkėlimai ----------
    case 'image':
        try {
            $name = save_uploaded_image($_FILES['file'] ?? [], LTKF_UP);
        } catch (RuntimeException $ex) {
            out(['ok' => false, 'error' => $ex->getMessage()]);
        }
        out(['ok' => true, 'url' => '../uploads/ltkf/' . $name]);

    case 'file':
        $f = $_FILES['file'] ?? [];
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            out(['ok' => false, 'error' => 'Nepavyko įkelti failo' . (($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? ' - per didelis.' : '.')]);
        }
        [$ext, $base] = ltkf_file_name((string) $f['name']);
        if (!in_array($ext, LTKF_FILE_EXT, true) || $f['size'] > LTKF_FILE_MAX) {
            out(['ok' => false, 'error' => 'Šio tipo failo įkelti negalima (tinka PDF, Word, Excel, PowerPoint, nuotraukos, ZIP, vaizdo įrašai; iki 60 MB).']);
        }
        if (!is_dir(LTKF_UP . '/failai')) {
            mkdir(LTKF_UP . '/failai', 0755, true);
        }
        $name = bin2hex(random_bytes(4)) . '-' . $base . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], LTKF_UP . '/failai/' . $name)) {
            out(['ok' => false, 'error' => 'Nepavyko išsaugoti failo.']);
        }
        out(['ok' => true, 'url' => '../uploads/ltkf/failai/' . $name, 'name' => pathinfo((string) $f['name'], PATHINFO_FILENAME), 'size' => (int) $f['size']]);

    // ---------- Meniu ----------
    case 'menu':
        $items = [];
        foreach ((array) json_decode((string) ($_POST['menu'] ?? '[]'), true) as $i) {
            if (is_array($i) && trim((string) ($i['l'] ?? '')) !== '') {
                $items[] = ['l' => mb_substr(trim((string) $i['l']), 0, 60), 'h' => ltkf_safe_href(mb_substr(trim((string) ($i['h'] ?? '')), 0, 300)) ?: '#'];
            }
        }
        set_site_setting('ltkf_menu', json_encode(array_slice($items, 0, 20), JSON_UNESCAPED_UNICODE));
        out(['ok' => true]);

    // ---------- Išvaizda ir kontaktai ----------
    case 'settings':
        $in = (array) json_decode((string) ($_POST['nust'] ?? '{}'), true);
        $cur = (array) json_decode((string) site_setting('ltkf_nust', '{}'), true);
        foreach (['pavadinimas' => 160, 'trumpas' => 30, 'el_pastas' => 120, 'telefonas' => 60] as $k => $max) {
            if (array_key_exists($k, $in)) {
                $cur[$k] = mb_substr(trim((string) $in[$k]), 0, $max);
            }
        }
        if (array_key_exists('hero_img', $in)) {
            $v = (string) $in['hero_img'];
            $cur['hero_img'] = preg_match('~^\.\./uploads/ltkf/[A-Za-z0-9._-]+$~', $v) ? $v : '';
        }
        if (array_key_exists('hero_color', $in)) {
            $cur['hero_color'] = preg_match('/^#[0-9a-f]{6}$/i', (string) $in['hero_color']) ? (string) $in['hero_color'] : '';
        }
        set_site_setting('ltkf_nust', json_encode($cur, JSON_UNESCAPED_UNICODE));
        out(['ok' => true]);

    // ---------- Nauji puslapiai ----------
    case 'new_page':
        $title = mb_substr(trim((string) post('title')), 0, 120);
        if ($title === '') {
            out(['ok' => false, 'error' => 'Įrašykite puslapio pavadinimą.']);
        }
        $base = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: 'puslapis'), '-')) ?: 'puslapis';
        $base = substr($base, 0, 50);
        $slug = $base;
        for ($n = 2; q_value('SELECT COUNT(*) FROM ltkf_pages WHERE slug = ?', ['p:' . $slug]); $n++) {
            $slug = $base . '-' . $n;
        }
        ltkf_store('p:' . $slug, ['title' => $title, 'custom' => 1, 'texts' => [], 'hidden' => [], 'blocks' => []], $who);
        if (post('to_menu')) {
            $menu = (array) json_decode((string) site_setting('ltkf_menu', ''), true);
            if (!$menu) {
                foreach (LTKF_MENIU_NUMATYTAS as [$l, $h]) {
                    $menu[] = ['l' => $l, 'h' => $h];
                }
            }
            $menu[] = ['l' => $title, 'h' => 'puslapis.php?p=' . $slug];
            set_site_setting('ltkf_menu', json_encode($menu, JSON_UNESCAPED_UNICODE));
        }
        out(['ok' => true, 'url' => 'puslapis.php?p=' . $slug . '&redaguoti=1']);

    case 'delete_page':
        $slug = (string) post('slug');
        if (strpos($slug, 'p:') !== 0 || !ltkf_slug_ok($slug)) {
            out(['ok' => false, 'error' => 'Ištrinti galima tik naujai sukurtus puslapius.']);
        }
        q('DELETE FROM ltkf_pages WHERE slug = ?', [$slug]);
        $menu = array_values(array_filter((array) json_decode((string) site_setting('ltkf_menu', '[]'), true), function ($i) use ($slug) { return ($i['h'] ?? '') !== 'puslapis.php?p=' . substr($slug, 2); }));
        set_site_setting('ltkf_menu', json_encode($menu, JSON_UNESCAPED_UNICODE));
        out(['ok' => true, 'url' => 'index.php']);

    // ---------- Failų dėžė ----------
    case 'inbox':
        $files = $_FILES['files'] ?? null;
        $n = 0;
        $err = '';
        if ($files && is_array($files['name'])) {
            if (!is_dir(LTKF_UP . '/deze')) {
                mkdir(LTKF_UP . '/deze', 0755, true);
            }
            foreach ($files['name'] as $i => $orig) {
                if (($files['error'][$i] ?? 1) !== UPLOAD_ERR_OK) {
                    $err = 'Kai kurių failų įkelti nepavyko (gal per dideli).';
                    continue;
                }
                [$ext] = ltkf_file_name((string) $orig);
                if (preg_match('/^(php\d?|phtml|phar|cgi|pl|py|sh|exe|bat|cmd|js|html?|htaccess)$/', $ext)) {
                    $err = 'Programų failų įkelti negalima.';
                    continue;
                }
                $name = bin2hex(random_bytes(8)) . ($ext !== '' ? '.' . $ext : '');
                if (move_uploaded_file($files['tmp_name'][$i], LTKF_UP . '/deze/' . $name)) {
                    q('INSERT INTO ltkf_files (file, orig, size, note, uploaded_by) VALUES (?, ?, ?, ?, ?)',
                        [$name, mb_substr((string) $orig, 0, 255), (int) $files['size'][$i], mb_substr(trim((string) post('note')), 0, 2000) ?: null, mb_substr($who, 0, 120)]);
                    $n++;
                }
            }
        }
        if (!$n && trim((string) post('note')) !== '') {   // tik žinutė, be failo
            q('INSERT INTO ltkf_files (file, orig, size, note, uploaded_by) VALUES (?, ?, 0, ?, ?)', ['', '', mb_substr(trim((string) post('note')), 0, 2000), mb_substr($who, 0, 120)]);
            $n = 1;
        }
        out(['ok' => $n > 0, 'count' => $n, 'error' => $n ? $err : ($err ?: 'Pasirinkite failą arba parašykite žinutę.')]);

    case 'inbox_done':
        q('UPDATE ltkf_files SET done_at = IF(done_at IS NULL, NOW(), NULL) WHERE id = ?', [(int) post('id')]);
        out(['ok' => true]);

    case 'inbox_delete':
        $f = q_one('SELECT * FROM ltkf_files WHERE id = ?', [(int) post('id')]);
        if ($f) {
            if ($f['file'] !== '') {
                @unlink(LTKF_UP . '/deze/' . basename((string) $f['file']));
            }
            q('DELETE FROM ltkf_files WHERE id = ?', [(int) $f['id']]);
        }
        out(['ok' => true]);
}
out(['ok' => false, 'error' => 'Nežinomas veiksmas.'], 400);
