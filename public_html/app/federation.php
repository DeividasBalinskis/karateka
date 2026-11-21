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
    return ltkf_reg_ready() ? q_all('SELECT * FROM ltkf_judges ORDER BY name') : [];
}

/** Slug iš pavadinimo: „Vilniaus karate mokykla“ → vilniaus-karate-mokykla */
function ltkf_slugify(string $name): string
{
    $s = strtr(mb_strtolower(trim($name)), ['ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i', 'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z']);
    $s = trim((string) preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    return $s !== '' ? mb_substr($s, 0, 60) : 'klubas';
}
