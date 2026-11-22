<?php
// #160: LTKF registrai - klubai ir teisėjai (db/048). Nariai - federation_people (egzaminų registras, app/exam_protocol.php).
// Tvarko administratorius: Valdymas → Federacija. LTKF svetainė rodo klubus ir teisėjus iš čia (ltkf/inc/pagalbines.php).

const LTKF_CLUB_STATUS = ['member' => 'Narys', 'candidate' => 'Kandidatas', 'former' => 'Buvęs'];

function ltkf_reg_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT 1 FROM ltkf_clubs LIMIT 1');
            $ok = true;
        } catch (PDOException $ex) {
            $ok = false;   // dar neimportuotas db/048
        }
    }
    return $ok;
}

/** Pirmą kartą - klubai iš LTKF juodraščio failo (ltkf/turinys/klubai.php) */
function ltkf_clubs_seed(): void
{
    if (!ltkf_reg_ready() || (int) q_value('SELECT COUNT(*) FROM ltkf_clubs') > 0) {
        return;
    }
    $file = PUBLIC_DIR . '/ltkf/turinys/klubai.php';
    $list = is_file($file) ? (array) require $file : [];
    foreach ($list as $i => $k) {
        if (empty($k['id']) || empty($k['pavadinimas'])) {
            continue;
        }
        $clean = function ($v) { $v = trim((string) $v); return $v === '' || preg_match('/^\[.*\]$/u', $v) ? null : $v; };   // „[miestas]“ - vietos rezervavimas
        q('INSERT IGNORE INTO ltkf_clubs (slug, name, legal_name, city, coach, email, phone, website, since, lat, lng, status, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$k['id'], $k['pavadinimas'], $clean($k['juridinis'] ?? ''), $clean($k['miestas'] ?? ''), $clean($k['treneris'] ?? ''), $clean($k['el_pastas'] ?? ''),
             $clean($k['telefonas'] ?? ''), $clean($k['svetaine'] ?? ''), $k['nuo'] ?? null, $k['lat'] ?? null, $k['lng'] ?? null, 'member', $i]);
    }
}

function ltkf_clubs(): array
{
    if (!ltkf_reg_ready()) {
        return [];
    }
    ltkf_clubs_seed();
    return q_all('SELECT c.*, (SELECT COUNT(*) FROM federation_people fp WHERE fp.club = c.name) AS people FROM ltkf_clubs c ORDER BY c.status = "former", c.sort_order, c.name');
}

function ltkf_judges(): array
{
    if (!ltkf_reg_ready()) {
        return [];
    }
    if (ltkf_judge_extra_ready()) {
        return q_all('SELECT j.*, (SELECT COUNT(*) FROM ltkf_judge_events e WHERE e.judge_id = j.id) AS events_n,
                             (SELECT MAX(on_date) FROM ltkf_judge_events e WHERE e.judge_id = j.id) AS last_event,
                             (SELECT COUNT(*) FROM ltkf_judge_files f WHERE f.judge_id = j.id) AS files_n
                        FROM ltkf_judges j ORDER BY j.name');
    }
    return q_all('SELECT *, 0 AS events_n, NULL AS last_event, 0 AS files_n FROM ltkf_judges ORDER BY name');
}

// ---------- Denis (v1.86): teisėjų lankomumas ir diplomai (db/052) ----------

const LTKF_JUDGE_EVENT_KINDS = ['seminar' => 'Seminaras', 'competition' => 'Teisėjavo varžybose', 'exam' => 'Kvalifikacijos egzaminas', 'other' => 'Kita'];
const LTKF_JUDGE_DIR = PUBLIC_DIR . '/uploads/teisejai';
const LTKF_JUDGE_FILE_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'doc', 'docx'];

function ltkf_judge_extra_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT 1 FROM ltkf_judge_events LIMIT 1');
            q_value('SELECT 1 FROM ltkf_judge_files LIMIT 1');
            $ok = true;
        } catch (PDOException $ex) {
            $ok = false;   // dar neimportuotas db/052
        }
    }
    return $ok;
}

function ltkf_judge_events(int $judgeId): array
{
    return ltkf_judge_extra_ready() ? q_all('SELECT * FROM ltkf_judge_events WHERE judge_id = ? ORDER BY on_date DESC, id DESC', [$judgeId]) : [];
}

function ltkf_judge_files(int $judgeId): array
{
    return ltkf_judge_extra_ready() ? q_all('SELECT * FROM ltkf_judge_files WHERE judge_id = ? ORDER BY created_at DESC, id DESC', [$judgeId]) : [];
}

/** Diplomo failas - į užrakintą aplanką (naršyklė tiesiogiai neatsiųs), grąžina [failas, klaida] */
function ltkf_judge_file_store(array $f): array
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
        return [null, t('Pasirinkite failą.')];
    }
    $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, LTKF_JUDGE_FILE_EXT, true) || (int) $f['size'] > 20 * 1024 * 1024) {
        return [null, t('Tinka PDF, nuotrauka ar Word failas iki 20 MB.')];
    }
    if (!is_dir(LTKF_JUDGE_DIR)) {
        @mkdir(LTKF_JUDGE_DIR, 0755, true);
    }
    if (!is_file(LTKF_JUDGE_DIR . '/.htaccess')) {
        @file_put_contents(LTKF_JUDGE_DIR . '/.htaccess', "Require all denied\n");
    }
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file((string) $f['tmp_name'], LTKF_JUDGE_DIR . '/' . $name)) {
        return [null, t('Nepavyko išsaugoti failo.')];
    }
    return [$name, null];
}

/** Slug iš pavadinimo: „Vilniaus karate mokykla“ → vilniaus-karate-mokykla */
function ltkf_slugify(string $name): string
{
    $s = strtr(mb_strtolower(trim($name)), ['ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i', 'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z']);
    $s = trim((string) preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    return $s !== '' ? mb_substr($s, 0, 60) : 'klubas';
}
