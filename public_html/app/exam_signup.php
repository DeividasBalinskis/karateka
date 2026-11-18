<?php
// Registracija į egzaminą per nuorodą (db/038) - žmonėms be paskyros (kitų klubų nariams ir kt.):
// vardas, pavardė, el. paštas (+ gimimo data, klubas, laikomas kyu). Užsiregistravęs iškart įrašomas į egzamino protokolą.
// Treneris nuorodą gali nusiųsti pats arba pakviesti el. paštu iš protokolo puslapio.

function exam_signup_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT 1 FROM exam_signup_links LIMIT 1');
            $ok = exam_protocol_ready();
        } catch (PDOException $ex) {
            $ok = false;   // dar neimportuotas db/038
        }
    }
    return $ok;
}

/** Egzamino registracijos nuoroda (sukuriama, jei $create) */
function exam_signup_link(int $eventId, bool $create = false): ?array
{
    if (!exam_signup_ready()) {
        return null;
    }
    $l = q_one('SELECT * FROM exam_signup_links WHERE event_id = ?', [$eventId]);
    if (!$l && $create) {
        q('INSERT IGNORE INTO exam_signup_links (event_id, token, is_open) VALUES (?, ?, 1)', [$eventId, bin2hex(random_bytes(12))]);
        $l = q_one('SELECT * FROM exam_signup_links WHERE event_id = ?', [$eventId]);
    }
    return $l ?: null;
}

function exam_signup_url(array $link): string
{
    return abs_url('egzamino-registracija.php?k=' . $link['token']);
}

/** Ar dar galima registruotis: nuoroda atidaryta, egzaminas neįvyko, registracijos terminas nepraėjo */
function exam_signup_open(array $event, ?array $link): bool
{
    return $link && (int) $link['is_open'] === 1 && event_last_day($event) >= date('Y-m-d')
        && (empty($event['registration_until']) || $event['registration_until'] >= date('Y-m-d'));
}

function exam_signups(int $eventId): array
{
    return exam_signup_ready() ? q_all('SELECT * FROM exam_signups WHERE event_id = ? ORDER BY signed_at IS NULL, signed_at DESC, id DESC', [$eventId]) : [];
}

/** Užsiregistravo per nuorodą: įrašas (pagal el. paštą - vienas) ir eilutė protokole. Grąžina true, jei naujas. */
function exam_signup_save(array $event, array $d): bool
{
    $email = mb_strtolower(trim((string) $d['email']));
    $name = trim($d['first_name'] . ' ' . $d['last_name']);
    $old = q_one('SELECT * FROM exam_signups WHERE event_id = ? AND email = ?', [(int) $event['id'], $email]);
    $vals = [mb_substr($d['first_name'], 0, 80), mb_substr($d['last_name'], 0, 80), $d['birth_date'] ?: null, mb_substr((string) $d['club'], 0, 120) ?: null,
             mb_substr((string) $d['kyu'], 0, 30) ?: null, mb_substr((string) $d['phone'], 0, 40) ?: null];
    if ($old) {
        q('UPDATE exam_signups SET first_name = ?, last_name = ?, birth_date = ?, club = ?, kyu = ?, phone = ?, signed_at = NOW() WHERE id = ?', array_merge($vals, [(int) $old['id']]));
        $sid = (int) $old['id'];
    } else {
        q('INSERT INTO exam_signups (event_id, email, first_name, last_name, birth_date, club, kyu, phone, signed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            array_merge([(int) $event['id'], mb_substr($email, 0, 190)], $vals));
        $sid = (int) db()->lastInsertId();
    }
    $row = ['name' => $name, 'birth_date' => $d['birth_date'] ?: null, 'club' => $d['club'] ?: null, 'kyu' => $d['kyu'] ?: null];
    $rid = $old && $old['row_id'] ? (int) $old['row_id'] : 0;
    $exists = $rid ? q_one('SELECT id, deleted_at FROM exam_protocol_rows WHERE id = ? AND event_id = ?', [$rid, (int) $event['id']]) : null;
    if ($exists) {
        q('UPDATE exam_protocol_rows SET name = ?, birth_date = ?, kyu = ?, deleted_at = NULL WHERE id = ?', [mb_substr($name, 0, 160), $row['birth_date'], $row['kyu'], $rid]);
        if ($row['club']) {
            q('UPDATE exam_protocol_rows SET club = ? WHERE id = ?', [mb_substr((string) $row['club'], 0, 120), $rid]);
        }
    } else {
        exam_protocol($event);   // antraštė (jei protokolo dar nebuvo)
        $rid = exam_protocol_add($event, $row);
        q('UPDATE exam_signups SET row_id = ? WHERE id = ?', [$rid, $sid]);
    }
    if (!$old || !$old['signed_at']) {
        notify_staff('exam_registration', t('Užsiregistravo į egzaminą (per nuorodą)') . ': ' . $name,
            $event['title'] . ' · ' . fmt_date($event['starts_on'], true) . ($d['club'] ? ' · ' . $d['club'] : ''), url('admin/egzamino-protokolas.php?id=' . (int) $event['id']));
    }
    return !$old || !$old['signed_at'];
}

/**
 * Kvietimai el. paštu: eilutės „Vardas Pavardė, el@paštas“ arba tik el. paštas. Grąžina [išsiųsta, nepavyko, netinkamos eilutės].
 */
function exam_signup_invite(array $event, array $link, string $lines, string $note, string $from): array
{
    $sent = $fail = 0;
    $bad = [];
    $url = exam_signup_url($link);
    $when = fmt_date($event['starts_on'], true) . ($event['start_time'] ? ', ' . fmt_time($event['start_time']) : '') . ($event['location'] ? ' · ' . $event['location'] : '');
    foreach (preg_split('/\R/u', $lines) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (!preg_match('/[^\s<>,;]+@[^\s<>,;]+\.[^\s<>,;]+/u', $line, $m) || !filter_var($m[0], FILTER_VALIDATE_EMAIL)) {
            $bad[] = $line;
            continue;
        }
        $email = mb_strtolower($m[0]);
        $name = trim(preg_replace('/\s+/u', ' ', trim(str_replace([$m[0], '<', '>'], '', $line), " \t,;")));
        $parts = $name !== '' ? explode(' ', $name, 2) : ['', ''];
        $hello = $name !== '' ? t('Sveiki, {n}!', ['n' => $parts[0]]) : t('Sveiki!');
        $body = $hello . "\n\n" . t('Kviečiame į egzaminą') . ': ' . $event['title'] . "\n" . $when . "\n\n"
            . ($note !== '' ? $note . "\n\n" : '')
            . t('Užsiregistruokite (užtenka vardo, pavardės ir el. pašto):') . "\n" . $url . "\n\n" . $from;
        if (send_mail($email, t('Kvietimas į egzaminą') . ': ' . $event['title'], $body)) {
            $sent++;
            q('INSERT INTO exam_signups (event_id, email, first_name, last_name, invited_at) VALUES (?, ?, ?, ?, NOW())
               ON DUPLICATE KEY UPDATE invited_at = NOW(), first_name = IF(first_name = "", VALUES(first_name), first_name), last_name = IF(last_name = "", VALUES(last_name), last_name)',
                [(int) $event['id'], mb_substr($email, 0, 190), mb_substr($parts[0] ?? '', 0, 80), mb_substr($parts[1] ?? '', 0, 80)]);
        } else {
            $fail++;
        }
    }
    return [$sent, $fail, $bad];
}
