<?php
// Egzamino protokolas internete (db/031) - pagal LTKA protokolo formą (egz_prot_TUSCIAS_naujesnis.docx):
// antraštė (organizacija, „Egzaminas ___ KYU“, data ir vieta, egzaminatorius ir licencijos Nr.) ir lentelė:
// Nr., vardas pavardė, gimimo data, įvertinimas (Kihon, Kata, Kumite, Testas), seminarai ir stovyklos, pastabos, Išlaikė / Neišlaikė.
// Kiekvienas dalyvis gauna unikalų numerį MM-mm-dd-eilė (26-11-11-01); neatvykęs ištrinamas „minkštai“ - numeris daugiau nepriskiriamas.

const EXAM_ORG_DEFAULT = 'LIETUVOS TRADICINIO KARATE ASOCIACIJA';
const EXAM_ROW_FIELDS = ['name', 'birth_date', 'kyu', 'kihon', 'kata', 'kumite', 'test', 'seminars', 'notes', 'result'];

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
            return (int) db()->lastInsertId();
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
    return ['member_id' => (int) $m['id'], 'name' => $m['first_name'] . ' ' . $m['last_name'], 'birth_date' => $m['birth_date'],
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

/** Protokolas CSV (Excel: UTF-8, kabliataškiai) */
function exam_protocol_csv(array $event): string
{
    $p = exam_protocol($event);
    $out = fopen('php://temp', 'w+');
    fwrite($out, "\xEF\xBB\xBF");
    $put = function (array $r) use ($out) { fputcsv($out, $r, ';', '"', ''); };
    $put([t('EGZAMINO PROTOKOLAS')]);
    $put([(string) $p['org_name']]);
    $put([t('Egzaminas') . ' ' . ((string) $p['kyu_label'] ?: '___') . ' KYU', t('Egzamino data ir vieta') . ': ' . (string) $p['place_text']]);
    $put([]);
    $put(['Nr.', t('Vardas Pavardė'), t('Gimimo data'), 'Kyu', 'Kihon', 'Kata', 'Kumite', t('Testas'), t('Seminarai, stovyklos'), t('Pastabos'), t('Išlaikė / Neišlaikė')]);
    foreach (exam_protocol_rows((int) $event['id']) as $r) {
        $put([$r['number'], $r['name'], (string) $r['birth_date'], (string) $r['kyu'], (string) $r['kihon'], (string) $r['kata'], (string) $r['kumite'], (string) $r['test'],
              (string) $r['seminars'], (string) $r['notes'], $r['result'] === 'passed' ? t('Išlaikė') : ($r['result'] === 'failed' ? t('Neišlaikė') : '')]);
    }
    $put([]);
    $put([t('Egzaminatorius') . ': ' . (string) $p['examiner_name'], t('Licencijos Nr.') . ': ' . (string) $p['examiner_license']]);
    rewind($out);
    $csv = (string) stream_get_contents($out);
    fclose($out);
    return $csv;
}

function exam_protocol_filename(array $event): string
{
    return 'egzamino-protokolas-' . $event['starts_on'] . '.csv';
}
