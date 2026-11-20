<?php
// Egzamino protokolas internete (db/031) - pagal LTKA protokolo formą (egz_prot_TUSCIAS_naujesnis.docx):
// antraštė (organizacija, „Egzaminas ___ KYU“, data ir vieta, egzaminatorius ir licencijos Nr.) ir lentelė:
// Nr., vardas pavardė, gimimo data, įvertinimas (Kihon, Kata, Kumite, Testas), seminarai ir stovyklos, pastabos, Išlaikė / Neišlaikė.
// Kiekvienas dalyvis gauna unikalų numerį MM-mm-dd-eilė (26-11-11-01); neatvykęs ištrinamas „minkštai“ - numeris daugiau nepriskiriamas.

const EXAM_ORG_DEFAULT = 'LIETUVOS TRADICINIO KARATE DO FEDERACIJA';
const EXAM_ROW_FIELDS = ['name', 'birth_date', 'kyu', 'kihon', 'kata', 'kumite', 'test', 'seminars', 'notes', 'result', 'club'];
const EXAM_BUILTIN_COLS = ['kihon', 'kata', 'kumite', 'test'];
// #133 (Denis): balas 6,3 ar mažesnis - tas langelis raudonas; neišlaikius - raudona visa eilutė
const EXAM_LOW_SCORE = 6.3;

function exam_protocol_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT 1 FROM exam_protocols LIMIT 1');
            $ok = true;
        } catch (PDOException $ex) {
            $ok = false;   // dar neimportuotas db/031
        }
    }
    return $ok;
}

/** Protokolo antraštė (pirmą kartą - su numatytais: organizacija ir egzaminatorius kaip paskutinį kartą) */
function exam_protocol(array $event): array
{
    $p = q_one('SELECT * FROM exam_protocols WHERE event_id = ?', [(int) $event['id']]);
    if ($p) {
        return $p;
    }
    $last = json_decode((string) site_setting('exam_protocol_defaults', '{}'), true) ?: [];
    $place = fmt_date($event['starts_on'], true) . ($event['location'] ? ', ' . $event['location'] : '');
    q('INSERT IGNORE INTO exam_protocols (event_id, org_name, place_text, examiner_name, examiner_license) VALUES (?, ?, ?, ?, ?)',
        [(int) $event['id'], $last['org'] ?? EXAM_ORG_DEFAULT, mb_substr($place, 0, 190), $last['examiner'] ?? null, $last['license'] ?? null]);
    return q_one('SELECT * FROM exam_protocols WHERE event_id = ?', [(int) $event['id']]);
}

function exam_protocol_rows(int $eventId, bool $deleted = false): array
{
    return q_all('SELECT * FROM exam_protocol_rows WHERE event_id = ? AND deleted_at IS ' . ($deleted ? 'NOT ' : '') . 'NULL ORDER BY seq, id', [$eventId]);
}

/** Naujas dalyvis su kitu laisvu numeriu tai datai (ištrintų numeriai irgi užimti) */
function exam_protocol_add(array $event, array $row): int
{
    $date = $event['starts_on'];
    for ($try = 0; $try < 5; $try++) {
        $seq = (int) q_value('SELECT COALESCE(MAX(seq), 0) + 1 FROM exam_protocol_rows WHERE num_date = ?', [$date]);
        $number = date('y-m-d', strtotime($date)) . '-' . sprintf('%02d', $seq);
        try {
            q('INSERT INTO exam_protocol_rows (event_id, number, num_date, seq, member_id, name, birth_date, kyu, test, seminars) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [(int) $event['id'], $number, $date, $seq, $row['member_id'] ?? null, mb_substr((string) $row['name'], 0, 160), $row['birth_date'] ?? null,
                 $row['kyu'] ?? null, $row['test'] ?? null, $row['seminars'] ?? null]);
            $rid = (int) db()->lastInsertId();
            if (!empty($row['club'])) {
                try {
                    q('UPDATE exam_protocol_rows SET club = ? WHERE id = ?', [mb_substr((string) $row['club'], 0, 120), $rid]);
                } catch (PDOException $ex) {
                }
            }
            return $rid;
        } catch (PDOException $ex) {
            if ((int) $ex->errorInfo[1] !== 1062) {
                throw $ex;   // ne dublikatas - tikra klaida
            }
        }
    }
    throw new RuntimeException(t('Nepavyko suteikti numerio - bandykite dar kartą.'));
}

/** Nario eilutė: laikomas kyu (kitas diržas), testo rezultatas, seminarai ir stovyklos per 12 mėn. iki egzamino */
function exam_protocol_member_row(array $event, array $m): array
{
    $next = next_belt($m['belt_level'] !== null ? (int) $m['belt_level'] : null);
    $test = '';
    if ($st = exam_test_status($m)) {
        $test = $st['best'] !== null ? $st['best'] . ' %' . ($st['passed'] ? ' ✓' : '') : '';
    }
    $sem = q_all('SELECT e.type, COUNT(*) AS n FROM event_participants ep JOIN events e ON e.id = ep.event_id
                   WHERE ep.member_id = ? AND e.type IN ("seminar", "camp") AND e.starts_on <= ? AND e.starts_on >= DATE_SUB(?, INTERVAL 12 MONTH)
                     AND (ep.attended = 1 OR (ep.attended IS NULL AND ep.status IN ("going", "late")))
                   GROUP BY e.type', [(int) $m['id'], $event['starts_on'], $event['starts_on']]);
    $parts = [];
    foreach ($sem as $r) {
        $parts[] = ($r['type'] === 'camp' ? t('stovyklos') : t('seminarai')) . ': ' . (int) $r['n'];
    }
    return ['member_id' => (int) $m['id'], 'name' => $m['first_name'] . ' ' . $m['last_name'], 'birth_date' => $m['birth_date'], 'club' => VARZ_HOME_CLUB,
            'kyu' => $next && isset(BELTS[$next]) ? BELTS[$next][0] : null, 'test' => $test ?: null, 'seminars' => $parts ? implode(', ', $parts) : null];
}

/** Įtraukia renginio dalyvius (užsiregistravę ar su rezultatu), kurių protokole dar nėra. Grąžina, kiek įtraukta. */
function exam_protocol_add_participants(array $event): int
{
    $have = array_map('intval', q('SELECT member_id FROM exam_protocol_rows WHERE event_id = ? AND member_id IS NOT NULL', [(int) $event['id']])->fetchAll(PDO::FETCH_COLUMN));
    $ids = array_map('intval', q('SELECT member_id FROM event_participants WHERE event_id = ? AND status IN ("going", "late")', [(int) $event['id']])->fetchAll(PDO::FETCH_COLUMN));
    $ids = array_values(array_unique(array_merge($ids, array_map('intval', array_keys(event_results($event))))));
    $n = 0;
    foreach ($ids as $mid) {
        if (in_array($mid, $have, true)) {
            continue;
        }
        $m = q_one('SELECT * FROM members WHERE id = ?', [$mid]);
        if ($m) {
            exam_protocol_add($event, exam_protocol_member_row($event, $m));
            $n++;
        }
    }
    return $n;
}

/** Protokolas lentele (eilutės) - Excel (.xlsx) ir CSV failams */
function exam_protocol_table(array $event): array
{
    $p = exam_protocol($event);
    $out = [];
    $out[] = [t('EGZAMINO PROTOKOLAS')];
    $out[] = [(string) $p['org_name']];
    $out[] = [t('Egzaminas') . ' ' . ((string) $p['kyu_label'] ?: '___') . ' KYU', t('Egzamino data ir vieta') . ': ' . (string) $p['place_text']];
    $out[] = [];
    $cols = exam_cols($p);
    $fed = fed_numbers_for_event((int) $event['id']);
    $out[] = array_merge(['Nr.', t('Vardas Pavardė'), t('Gimimo data'), t('Klubas'), 'Kyu'], array_column($cols, 1), [t('Seminarai, stovyklos'), t('Pastabos'), t('Išlaikė / Neišlaikė')]);
    foreach (exam_protocol_rows((int) $event['id']) as $r) {
        $out[] = array_merge([$fed[(int) $r['id']] ?? $r['number'], $r['name'], (string) $r['birth_date'], (string) ($r['club'] ?? ''), (string) $r['kyu']],
            array_map(function ($c) use ($r) { return exam_cell($r, $c[0]); }, $cols),
            [(string) $r['seminars'], (string) $r['notes'], $r['result'] === 'passed' ? t('Išlaikė') : ($r['result'] === 'failed' ? t('Neišlaikė') : '')]);
    }
    $out[] = [];
    $out[] = [t('Egzaminatorius') . ': ' . (string) $p['examiner_name'], t('Licencijos Nr.') . ': ' . (string) $p['examiner_license']];
    return $out;
}

/** Protokolas CSV (Excel: UTF-8, kabliataškiai) */
function exam_protocol_csv(array $event): string
{
    $out = fopen('php://temp', 'w+');
    fwrite($out, "\xEF\xBB\xBF");
    foreach (exam_protocol_table($event) as $r) {
        fputcsv($out, $r, ';', '"', '');
    }
    rewind($out);
    $csv = (string) stream_get_contents($out);
    fclose($out);
    return $csv;
}

function exam_protocol_filename(array $event): string
{
    return 'egzamino-protokolas-' . $event['starts_on'] . '.csv';
}


// ---------- Įvertinimo stulpeliai: galima pridėti, pervadinti, išimti (db/037) ----------

/** [[raktas, pavadinimas]]: kihon, kata, kumite, test - lentelės laukai; x1, x2... - papildomi (eilutės „extra“) */
function exam_cols(array $p): array
{
    $c = json_decode((string) ($p['cols'] ?? ''), true);
    if (!is_array($c) || !$c) {
        return [['kihon', 'Kihon'], ['kata', 'Kata'], ['kumite', 'Kumite'], ['test', t('Testas')]];
    }
    $out = [];
    foreach ($c as $x) {
        if (is_array($x) && isset($x[0], $x[1]) && preg_match('/^(kihon|kata|kumite|test|x\d{1,3})$/', (string) $x[0])) {
            $out[] = [(string) $x[0], mb_substr(trim((string) $x[1]), 0, 40) ?: (string) $x[0]];
        }
    }
    return $out;
}

function exam_cols_save(int $eventId, array $cols): bool
{
    try {
        q('UPDATE exam_protocols SET cols = ?, updated_at = NOW() WHERE event_id = ?', [json_encode(array_values($cols), JSON_UNESCAPED_UNICODE), $eventId]);
        return true;
    } catch (PDOException $ex) {
        return false;   // dar neimportuotas db/037
    }
}

/** Langelio reikšmė (įprastas laukas ar papildomas stulpelis) */
function exam_cell(array $r, string $key): string
{
    if (in_array($key, EXAM_BUILTIN_COLS, true)) {
        return (string) ($r[$key] ?? '');
    }
    $x = json_decode((string) ($r['extra'] ?? ''), true);
    return is_array($x) ? (string) ($x[$key] ?? '') : '';
}

function exam_set_extra(array $row, string $key, ?string $v): void
{
    $x = json_decode((string) ($row['extra'] ?? ''), true);
    $x = is_array($x) ? $x : [];
    if ($v === null || $v === '') {
        unset($x[$key]);
    } else {
        $x[$key] = $v;
    }
    q('UPDATE exam_protocol_rows SET extra = ? WHERE id = ?', [$x ? json_encode($x, JSON_UNESCAPED_UNICODE) : null, (int) $row['id']]);
}

// ---------- Federacijos registras (db/037): kiekvienas laikęs - nuolatinis numeris 26.11.15-1, niekada nesikartoja ----------

function fed_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT 1 FROM federation_people LIMIT 1');
            $ok = true;
        } catch (PDOException $ex) {
            $ok = false;
        }
    }
    return $ok;
}

/** Federacijos numeriai pagal protokolo eilutę: [row_id => fed_no] */
function fed_numbers_for_event(int $eventId): array
{
    if (!fed_ready()) {
        return [];
    }
    $out = [];
    foreach (q_all('SELECT fe.row_id, fp.fed_no FROM federation_exams fe JOIN federation_people fp ON fp.id = fe.person_id WHERE fe.event_id = ?', [$eventId]) as $r) {
        $out[(int) $r['row_id']] = $r['fed_no'];
    }
    return $out;
}

/** Naujas žmogus registre: numeris = data (yy.mm.dd) + tos dienos eilės numeris */
function fed_person_create(string $date, array $row): int
{
    $pre = date('y.m.d', strtotime($date));
    for ($try = 0; $try < 8; $try++) {
        $n = 1 + (int) q_value("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(fed_no, '-', -1) AS UNSIGNED)), 0) FROM federation_people WHERE fed_no LIKE ?", [$pre . '-%']);
        try {
            q('INSERT INTO federation_people (fed_no, name, birth_date, club, member_id) VALUES (?, ?, ?, ?, ?)',
                [$pre . '-' . $n, mb_substr((string) $row['name'], 0, 160), $row['birth_date'] ?: null, $row['club'] ?? null, $row['member_id'] ?: null]);
            return (int) db()->lastInsertId();
        } catch (PDOException $ex) {
            if ((int) $ex->errorInfo[1] !== 1062) {
                throw $ex;
            }
        }
    }
    throw new RuntimeException(t('Nepavyko suteikti numerio - bandykite dar kartą.'));
}

/** Siųsti protokolą Federacijai: visi laikę įrašomi į registrą (esamiems - tas pats numeris). Grąžina [nauji, jau buvę] */
function fed_send_protocol(array $event): array
{
    $new = $old = 0;
    $p = exam_protocol($event);
    $cols = exam_cols($p);
    foreach (exam_protocol_rows((int) $event['id']) as $r) {
        $pid = 0;
        if ($r['member_id']) {
            $pid = (int) q_value('SELECT id FROM federation_people WHERE member_id = ? ORDER BY id LIMIT 1', [(int) $r['member_id']]);
        }
        if (!$pid) {
            $pid = (int) q_value('SELECT id FROM federation_people WHERE LOWER(name) = LOWER(?) AND (birth_date <=> ? OR birth_date IS NULL OR ? IS NULL) ORDER BY id LIMIT 1',
                [trim((string) $r['name']), $r['birth_date'] ?: null, $r['birth_date'] ?: null]);
        }
        if ($pid) {
            $old++;
            q('UPDATE federation_people SET member_id = COALESCE(member_id, ?), birth_date = COALESCE(birth_date, ?), club = COALESCE(?, club) WHERE id = ?',
                [$r['member_id'] ?: null, $r['birth_date'] ?: null, $r['club'] ?: null, $pid]);
        } else {
            $new++;
            $pid = fed_person_create($event['starts_on'], $r);
        }
        $scores = [];
        foreach ($cols as [$k, $label]) {
            $scores[$label] = exam_cell($r, $k);
        }
        q('INSERT INTO federation_exams (person_id, event_id, row_id, exam_date, kyu, result, scores) VALUES (?, ?, ?, ?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE person_id = VALUES(person_id), kyu = VALUES(kyu), result = VALUES(result), scores = VALUES(scores)',
            [$pid, (int) $event['id'], (int) $r['id'], $event['starts_on'], $r['kyu'] ?: null, $r['result'] ?: null, json_encode($scores, JSON_UNESCAPED_UNICODE)]);
    }
    q('UPDATE exam_protocols SET fed_sent_at = NOW() WHERE event_id = ?', [(int) $event['id']]);
    return [$new, $old];
}

