<?php
// Egzaminai: programa pagal diržą, pasiruošimo žingsniai ir testai

const REQ_SECTIONS = ['kihon' => 'Kihon', 'kata' => 'Kata', 'kumite' => 'Kumite', 'teorija' => 'Teorija', 'fizinis' => 'Fizinis pasiruošimas', 'kita' => 'Kita'];
const QUIZ_AUDIENCE = ['karateka' => 'Nariams', 'teisejas' => 'Teisėjams', 'egzaminatorius' => 'Egzaminatoriams'];
// Išlaikytas testas galioja tiek mėnesių (egzamino ir teisėjo / egzaminatoriaus statusui)
const QUIZ_VALID_MONTHS = 12;
// Kiek sekundžių po laiko pabaigos dar priimami atsakymai (lėtas internetas telefone)
const QUIZ_GRACE = 60;

/** Dabartinis diržas (nenurodytas = baltas) ir kitas diržas */
function current_belt(?int $belt): int
{
    return $belt && isset(BELTS[$belt]) ? $belt : 1;
}

function next_belt(?int $belt): ?int
{
    $n = current_belt($belt) + 1;
    return isset(BELTS[$n]) ? $n : null;
}

/** Programa diržui: [sekcija => [punktai]] */
function belt_requirements(int $belt): array
{
    $out = [];
    foreach (q_all('SELECT r.*, l.title AS lesson_title FROM belt_requirements r LEFT JOIN lessons l ON l.id = r.lesson_id
                     WHERE r.belt_level = ? ORDER BY FIELD(r.section, "kihon", "kata", "kumite", "teorija", "fizinis", "kita"), r.sort_order, r.id', [$belt]) as $r) {
        $out[$r['section']][] = $r;
    }
    return $out;
}

/** Pažymėti punktai: [requirement_id => 'pending' (laukia trenerio) | 'approved' (patvirtinta)] */
function requirement_done_ids(int $memberId): array
{
    try {
        return array_map('strval', q('SELECT requirement_id, status FROM requirement_progress WHERE member_id = ?', [$memberId])->fetchAll(PDO::FETCH_KEY_PAIR));
    } catch (PDOException $ex) {
        // dar neimportuotas db/021 - visi pažymėti laikomi patvirtintais
        return array_fill_keys(array_map('intval', q('SELECT requirement_id FROM requirement_progress WHERE member_id = ?', [$memberId])->fetchAll(PDO::FETCH_COLUMN)), 'approved');
    }
}

// ---------- Testai ----------

/** Testai, kuriuos narys mato: kitam diržui (ir bendri) + pagal žymas */
function quizzes_for_member(array $m, array $tags): array
{
    $next = next_belt($m['belt_level'] !== null ? (int) $m['belt_level'] : null);
    $aud = array_merge(['karateka'], array_values(array_intersect(array_keys(MEMBER_TAGS), $tags)));
    $in = "'" . implode("','", $aud) . "'";
    return q_all("SELECT q.*, (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id AND qq.is_draft = 0) AS questions_total
                    FROM quizzes q
                   WHERE q.is_published = 1 AND q.audience IN ($in)
                     AND (q.audience <> 'karateka' OR q.belt_level IS NULL OR q.belt_level = ?)
                   ORDER BY FIELD(q.audience, 'karateka', 'teisejas', 'egzaminatorius'), q.belt_level IS NULL, q.title", [$next ?? 0]);
}

/** Klausimai su atsakymais: [id => klausimas + 'answers' => [...]] */
function quiz_questions_full(int $quizId, ?array $onlyIds = null): array
{
    $sql = 'SELECT * FROM quiz_questions WHERE quiz_id = ?';
    if ($onlyIds === null) {
        $sql .= ' AND is_draft = 0';   // juodraščiai (įkelti peržiūrai) testuose dar neužduodami
    }
    if ($onlyIds !== null) {
        $sql .= $onlyIds ? (' ' . 'AND id IN (') . implode(',', array_map('intval', $onlyIds)) . ')' : (' ' . 'AND 0');
    }
    $qs = [];
    foreach (q_all($sql . ' ORDER BY sort_order, id', [$quizId]) as $row) {
        $row['answers'] = [];
        $qs[(int) $row['id']] = $row;
    }
    if ($qs) {
        foreach (q_all('SELECT * FROM quiz_answers WHERE question_id IN (' . implode(',', array_keys($qs)) . ') ORDER BY sort_order, id') as $a) {
            $qs[(int) $a['question_id']]['answers'][] = $a;
        }
    }
    // Tinkami tik klausimai su bent 2 atsakymais ir bent vienu teisingu
    return array_filter($qs, function ($row) {
        return count($row['answers']) >= 2 && in_array(1, array_map('intval', array_column($row['answers'], 'is_correct')), true);
    });
}

/**
 * Sumaišyti klausimai, iš kiekvienos temos - tik vienas (tema = to paties klausimo kitos formuluotės, db/019).
 * Klausimai be temos visi skirtingi.
 */
function quiz_pick_questions(array $qs): array
{
    $qs = array_values($qs);
    shuffle($qs);
    $out = $seen = [];
    foreach ($qs as $q) {
        $topic = (string) ($q['topic'] ?? '');
        if ($topic !== '') {
            if (isset($seen[$topic])) {
                continue;
            }
            $seen[$topic] = true;
        }
        $out[(int) $q['id']] = $q;
    }
    return $out;
}

/** Kiek skirtingų klausimų banke (tiek daugiausia gali būti viename teste) */
function quiz_distinct_count(array $qs): int
{
    $topics = $plain = 0;
    $seen = [];
    foreach ($qs as $q) {
        $topic = (string) ($q['topic'] ?? '');
        if ($topic === '') {
            $plain++;
        } elseif (!isset($seen[$topic])) {
            $seen[$topic] = true;
            $topics++;
        }
    }
    return $topics + $plain;
}

/** Ar baigėsi laikas (su QUIZ_GRACE atsarga) */
function quiz_time_is_up(array $attempt): bool
{
    return !empty($attempt['deadline_at']) && time() > strtotime($attempt['deadline_at']) + QUIZ_GRACE;
}

/** Geriausias baigtas bandymas (null - nelaikė) */
function quiz_best_attempt(int $memberId, int $quizId): ?array
{
    return q_one('SELECT * FROM quiz_attempts WHERE member_id = ? AND quiz_id = ? AND finished_at IS NOT NULL
                   ORDER BY passed DESC, score / total DESC, finished_at DESC LIMIT 1', [$memberId, $quizId]);
}

function quiz_passed_recently(int $memberId, int $quizId): ?array
{
    return q_one('SELECT * FROM quiz_attempts WHERE member_id = ? AND quiz_id = ? AND passed = 1
                    AND finished_at >= DATE_SUB(NOW(), INTERVAL ' . QUIZ_VALID_MONTHS . ' MONTH) ORDER BY finished_at DESC LIMIT 1', [$memberId, $quizId]);
}

function attempt_percent(array $att): int
{
    return $att['total'] ? (int) round(100 * (int) $att['score'] / (int) $att['total']) : 0;
}

/**
 * Egzamino testas nariui: kito diržo testas (arba bendras karatekų testas).
 * ['quiz' => ..., 'passed' => bool, 'best' => % | null] arba null, jei testo nėra.
 */
function exam_test_status(array $m): ?array
{
    $next = next_belt($m['belt_level'] !== null ? (int) $m['belt_level'] : null);
    $quiz = q_one("SELECT * FROM quizzes WHERE is_published = 1 AND audience = 'karateka' AND (belt_level = ? OR belt_level IS NULL)
                   ORDER BY belt_level IS NULL LIMIT 1", [$next ?? 0]);
    if (!$quiz) {
        return null;
    }
    $best = quiz_best_attempt((int) $m['id'], (int) $quiz['id']);
    return ['quiz' => $quiz, 'passed' => (bool) quiz_passed_recently((int) $m['id'], (int) $quiz['id']), 'best' => $best ? attempt_percent($best) : null];
}

/** Teisėjo / egzaminatoriaus statusas: išlaikytas tos žymos testas per paskutinius 12 mėn. */
function tag_qualification(int $memberId, string $tag): ?array
{
    return q_one('SELECT qa.*, q.title FROM quiz_attempts qa JOIN quizzes q ON q.id = qa.quiz_id
                   WHERE qa.member_id = ? AND q.audience = ? AND qa.passed = 1
                     AND qa.finished_at >= DATE_SUB(NOW(), INTERVAL ' . QUIZ_VALID_MONTHS . ' MONTH)
                   ORDER BY qa.finished_at DESC LIMIT 1', [$memberId, $tag]);
}

/** Kiek klausimų teste: 0 - visi banko klausimai */
function quiz_limit(array $quiz): int
{
    return (int) $quiz['question_count'] > 0 ? (int) $quiz['question_count'] : 100000;
}

/** Naujas bandymas: atsitiktiniai klausimai iš banko (iš temos - vienas). Su laiko limitu įrašoma, iki kada baigti. */
function start_quiz_attempt(array $quiz, int $memberId, int $accountId): int
{
    $ids = array_keys(quiz_pick_questions(quiz_questions_full((int) $quiz['id'])));
    $ids = array_slice($ids, 0, quiz_limit($quiz));
    $params = [(int) $quiz['id'], $memberId, $accountId, implode(',', $ids), count($ids)];
    $minutes = (int) ($quiz['time_limit'] ?? 0);
    if ($minutes > 0) {
        q('INSERT INTO quiz_attempts (quiz_id, member_id, account_id, question_ids, total, deadline_at) VALUES (?, ?, ?, ?, ?, ?)',
            array_merge($params, [date('Y-m-d H:i:s', time() + 60 * $minutes)]));
    } else {
        q('INSERT INTO quiz_attempts (quiz_id, member_id, account_id, question_ids, total) VALUES (?, ?, ?, ?, ?)', $params);
    }
    return (int) db()->lastInsertId();
}

/** Įvertina bandymą. $answers = [question_id => answer_id] */
function grade_quiz_attempt(array $attempt, array $quiz, array $answers): array
{
    $ids = array_map('intval', array_filter(explode(',', $attempt['question_ids'])));
    $qs = quiz_questions_full((int) $quiz['id'], $ids);
    $score = 0;
    db()->beginTransaction();
    foreach ($ids as $qid) {
        $chosen = isset($answers[$qid]) ? (int) $answers[$qid] : null;
        $ok = false;
        foreach ($qs[$qid]['answers'] ?? [] as $a) {
            if ((int) $a['id'] === $chosen && $a['is_correct']) {
                $ok = true;
            }
        }
        $score += $ok ? 1 : 0;
        q('INSERT INTO quiz_attempt_answers (attempt_id, question_id, answer_id, is_correct) VALUES (?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE answer_id = VALUES(answer_id), is_correct = VALUES(is_correct)', [(int) $attempt['id'], $qid, $chosen, $ok ? 1 : 0]);
    }
    $total = count($ids);
    $passed = $total > 0 && 100 * $score >= (int) $quiz['pass_percent'] * $total;
    q('UPDATE quiz_attempts SET score = ?, total = ?, passed = ?, finished_at = NOW() WHERE id = ?', [$score, $total, $passed ? 1 : 0, (int) $attempt['id']]);
    db()->commit();
    return q_one('SELECT * FROM quiz_attempts WHERE id = ?', [(int) $attempt['id']]);
}

/** Artėjantis egzaminas nariui: kur užsiregistravęs ar pakviestas; jei tokio nėra - egzaminas jo grupei */
function member_upcoming_exam(array $m): ?array
{
    $ev = q_one('SELECT e.*, ep.status AS p_status FROM events e JOIN event_participants ep ON ep.event_id = e.id
                  WHERE ep.member_id = ? AND ep.status IN ("going", "invited") AND e.type = "exam" AND e.starts_on >= CURDATE()
                  ORDER BY e.starts_on LIMIT 1', [(int) $m['id']]);
    if ($ev) {
        return $ev;
    }
    foreach (q_all('SELECT e.* FROM events e WHERE e.type = "exam" AND e.starts_on >= CURDATE() AND ' . event_main_sql('e') . ' ORDER BY e.starts_on') as $e) {
        if (event_targets_member($e, $m) && !event_participant((int) $e['id'], (int) $m['id'])) {
            $e['p_status'] = null;
            return $e;
        }
    }
    return null;
}

// ---------- Nuoroda be paskyros (db/028) ----------

/** Testo nuorodos raktas (sukuriamas pirmą kartą) */
function quiz_share_token(array $quiz): string
{
    if (!empty($quiz['share_token'])) {
        return (string) $quiz['share_token'];
    }
    q('UPDATE quizzes SET share_token = ? WHERE id = ? AND share_token IS NULL', [bin2hex(random_bytes(12)), (int) $quiz['id']]);
    return (string) q_value('SELECT share_token FROM quizzes WHERE id = ?', [(int) $quiz['id']]);
}

/** Testas pagal nuorodos raktą (tik jei dalijimasis įjungtas) */
function quiz_by_share_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{24}$/', $token)) {
        return null;
    }
    try {
        return q_one('SELECT * FROM quizzes WHERE share_token = ? AND share_open = 1', [$token]);
    } catch (PDOException $ex) {
        return null;   // dar neimportuotas db/028
    }
}

// ---------- Klausimų įkėlimas iš failo ar teksto (pvz. sugeneruotų ChatGPT) ----------

/** Užklausa ChatGPT: kad klausimai būtų tokiu formatu, kurį supranta quiz_import_parse() */
/** #137: visas testo klausimynas su atsakymais - tekstas, kurį galima pataisyti (pvz. su ChatGPT) ir įkelti atgal */
function quiz_export_text(int $quizId): string
{
    $qz = q_one('SELECT * FROM quizzes WHERE id = ?', [$quizId]);
    $qs = q_all('SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order, id', [$quizId]);
    $ans = [];
    if ($qs) {
        foreach (q_all('SELECT * FROM quiz_answers WHERE question_id IN (' . implode(',', array_map('intval', array_column($qs, 'id'))) . ') ORDER BY sort_order, id') as $a) {
            $ans[(int) $a['question_id']][] = $a;
        }
    }
    $out = '// ' . ($qz['title'] ?? '') . ' · ' . count($qs) . ' ' . t('klaus.') . ' · ' . date('Y-m-d') . "\n"
        . '// ' . t('Pataisykite ir įkelkite atgal: Testai → šis testas → „Įkelti klausimus“.') . "\n"
        . '// ' . t('[#123] - klausimo numeris: palikite jį, tada klausimas bus pataisytas, o ne pridėtas iš naujo. Naujiems klausimams numerio nereikia.') . "\n"
        . '// ' . t('Eilutės su // įkeliant praleidžiamos.') . "\n\n";
    foreach ($qs as $i => $q) {
        $out .= ($i + 1) . '. [#' . (int) $q['id'] . '] ' . trim(preg_replace('/\s+/', ' ', (string) $q['body'])) . "\n";
        $correct = '';
        foreach ($ans[(int) $q['id']] ?? [] as $j => $a) {
            $letter = chr(65 + $j);
            $out .= $letter . ') ' . trim((string) $a['body']) . "\n";
            if ((int) $a['is_correct']) {
                $correct = $letter;
            }
        }
        $out .= 'Atsakymas: ' . $correct . "\n";
        if (trim((string) $q['explanation']) !== '') {
            $out .= 'Paaiškinimas: ' . trim(preg_replace('/\s+/', ' ', (string) $q['explanation'])) . "\n";
        }
        if (trim((string) ($q['topic'] ?? '')) !== '') {
            $out .= 'Tema: ' . trim((string) $q['topic']) . "\n";
        }
        if (empty($q['image']) && trim((string) ($q['image_note'] ?? '')) !== '') {
            $out .= 'Paveikslėlis: ' . trim((string) $q['image_note']) . "\n";
        }
        $out .= "\n";
    }
    return $out;
}

/**
 * Peržiūros pastabos ChatGPT: tik klausimai su ✕ ar komentaru, tuo pačiu formatu, pastabos - eilutėse „>>“
 * (įkeliant jos praleidžiamos, todėl failą galima įkelti atgal ir nepataisytą).
 */
function quiz_review_export_text(int $quizId): string
{
    $qz = q_one('SELECT * FROM quizzes WHERE id = ?', [$quizId]);
    $qs = q_all('SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order, id', [$quizId]);
    $notes = quiz_review_notes($quizId);
    $ans = [];
    if ($qs) {
        foreach (q_all('SELECT * FROM quiz_answers WHERE question_id IN (' . implode(',', array_map('intval', array_column($qs, 'id'))) . ') ORDER BY sort_order, id') as $a) {
            $ans[(int) $a['question_id']][] = $a;
        }
    }
    $body = '';
    $n = 0;
    foreach ($qs as $i => $q) {
        $qn = $notes[(int) $q['id']] ?? null;
        if (!$qn || !quiz_review_has_notes($qn)) {
            continue;
        }
        $n++;
        $lines = [];
        $body .= ($i + 1) . '. [#' . (int) $q['id'] . '] ' . trim(preg_replace('/\s+/', ' ', (string) $q['body'])) . "\n";
        $correct = '';
        foreach ($ans[(int) $q['id']] ?? [] as $j => $a) {
            $letter = chr(65 + $j);
            $body .= $letter . ') ' . trim((string) $a['body']) . "\n";
            if ((int) $a['is_correct']) {
                $correct = $letter;
            }
            $an = $qn['answers'][(int) $a['id']] ?? null;
            if ($an && $an['flag']) {
                $lines[] = '>> ✕ ' . t('Atsakymas {l} netinka - pakeisk jį kitu.', ['l' => $letter]);
            }
            if ($an && trim((string) $an['comment']) !== '') {
                $lines[] = '>> ' . t('Komentaras apie {l}:', ['l' => $letter]) . ' ' . trim(preg_replace('/\s+/', ' ', (string) $an['comment']));
            }
        }
        $body .= 'Atsakymas: ' . $correct . "\n";
        if (trim((string) $q['explanation']) !== '') {
            $body .= 'Paaiškinimas: ' . trim(preg_replace('/\s+/', ' ', (string) $q['explanation'])) . "\n";
        }
        if (trim((string) ($q['topic'] ?? '')) !== '') {
            $body .= 'Tema: ' . trim((string) $q['topic']) . "\n";
        }
        if (empty($q['image']) && trim((string) ($q['image_note'] ?? '')) !== '') {
            $body .= 'Paveikslėlis: ' . trim((string) $q['image_note']) . "\n";
        }
        if ($qn['flag']) {
            array_unshift($lines, '>> ✕ ' . t('Visas klausimas netinka - parašyk naują vietoj jo (palik tą patį [#{id}]).', ['id' => (int) $q['id']]));
        }
        if (trim((string) $qn['comment']) !== '') {
            $lines[] = '>> ' . t('Komentaras:') . ' ' . trim(preg_replace('/\s+/', ' ', (string) $qn['comment']));
        }
        $body .= implode("\n", $lines) . "\n\n";
    }
    return '// ' . t('Testo „{t}“ pastabos - {n} klaus. · {d}', ['t' => $qz['title'] ?? '', 'n' => $n, 'd' => date('Y-m-d')]) . "\n"
        . '// ' . t('ChatGPT: pataisyk tik šiuos klausimus pagal eilutes „>>“ (tai trenerio pastabos).') . "\n"
        . '// ' . t('✕ visas klausimas - parašyk naują ta pačia tema; ✕ atsakymas - pakeisk tą atsakymą; komentaras - atsižvelk.') . "\n"
        . '// ' . t('Palik kiekvieno klausimo [#numerį]. Atsakyk tik pataisytais klausimais tuo pačiu formatu, be eilučių „>>“ ir „//“, geriausia - .txt failu.') . "\n\n"
        . $body;
}

/** Peržiūros žymos: [klausimo id => ['seen', 'flag', 'comment', 'answers' => [atsakymo id => ['flag', 'comment']]]] */
function quiz_review_notes(int $quizId): array
{
    $out = [];
    try {
        $rows = q_all('SELECT r.* FROM quiz_review r JOIN quiz_questions q ON q.id = r.question_id WHERE q.quiz_id = ?', [$quizId]);
    } catch (PDOException $ex) {
        return [];   // dar neimportuotas db/045
    }
    foreach ($rows as $r) {
        $qid = (int) $r['question_id'];
        $out[$qid] = $out[$qid] ?? ['seen' => false, 'flag' => 0, 'comment' => '', 'answers' => []];
        if ((int) $r['answer_id'] === 0) {
            $out[$qid]['seen'] = true;
            $out[$qid]['flag'] = (int) $r['flag'];
            $out[$qid]['comment'] = (string) $r['comment'];
        } else {
            $out[$qid]['answers'][(int) $r['answer_id']] = ['flag' => (int) $r['flag'], 'comment' => (string) $r['comment']];
        }
    }
    return $out;
}

function quiz_review_has_notes(array $qn): bool
{
    if ($qn['flag'] || trim((string) $qn['comment']) !== '') {
        return true;
    }
    foreach ($qn['answers'] as $a) {
        if ($a['flag'] || trim((string) $a['comment']) !== '') {
            return true;
        }
    }
    return false;
}

/** Nurodymai ChatGPT projektui (įklijuojama vieną kartą: ChatGPT → Projektai → Instrukcijos) */
function quiz_chatgpt_project_text(): string
{
    return "Tu padedi karate klubo „Karateka“ treneriui kurti ir taisyti testų klausimus lietuviškai.\n\n"
        . "VISADA atsakyk tik klausimais šiuo formatu (tarp klausimų - tuščia eilutė), geriausia - kaip .txt failą „klausimai.txt“:\n\n"
        . "1. Klausimo tekstas?\nA) atsakymas\nB) atsakymas\nC) atsakymas\nD) atsakymas\nAtsakymas: B\n"
        . "Paaiškinimas: vienu sakiniu, kodėl teisingas\n"
        . "Paveikslėlis: ką turi rodyti paveikslėlis (tik jei klausimui jo reikia)\n\n"
        . "Taisyklės:\n"
        . "- 3 arba 4 atsakymai, teisingas tik vienas. Neteisingi - tikroviški, panašaus ilgio, ne juokingi.\n"
        . "- Rašyk „karate“ (ne „karatė“), „karate-do“. Japoniškus terminus rašyk taip, kaip įprasta klube (pvz. zenkutsu-dachi, oi-zuki).\n"
        . "- Jei nesi tikras dėl karate taisyklės, termino ar fakto - tokio klausimo nerašyk.\n"
        . "- Klausimai trumpi ir aiškūs; vaikams - paprasti žodžiai.\n"        . "- Eilutę „Tema: …“ rašyk TIK tada, kai tai to paties klausimo kita formuluotė: abiem - ta pati tema (teste bus užduota tik viena iš jų). Skirtingiems klausimams temos nerašyk.\n"
        . "- Jokio kito teksto prieš klausimus ar po jų.\n"
        . "- [#123] klausimo pradžioje - klausimo numeris sistemoje. Jį palik nepakeistą, tada klausimas bus pataisytas, o ne pridėtas iš naujo.\n"
        . "- Eilutės, prasidedančios „>>“, yra trenerio pastabos: „✕ visas klausimas“ - parašyk naują klausimą ta pačia tema su tuo pačiu [#numeriu]; "
        . "„✕ atsakymas X“ - pakeisk tą atsakymą kitu; „Komentaras“ - atsižvelk. Pastabų eilučių atsakyme nekartok. Grąžink tik pataisytus klausimus.\n"
        . "- Eilutės su „//“ - paaiškinimai, jų nekartok.\n"
        . "- Paveikslėliai: jei paprašau sugeneruoti paveikslėlius, sudėk juos į vieną ZIP kartu su „klausimai.txt“, "
        . "pavadink Q + klausimo numeris (pvz. Q12.png) ir prie klausimo rašyk „Paveikslėlis: Q12.png“.\n";
}

/** Prašymas ChatGPT pataisyti atsisiųstą klausimyną (#137) */
function quiz_fix_prompt(): string
{
    return "Pataisyk šio testo klausimus (rašybą, aiškumą, teisingus atsakymus). Palik tą patį formatą ir kiekvieno klausimo numerį [#...] - nekeisk ir netrink jo.\n"
        . "Naujus klausimus rašyk be [#...]. Atsakyk tik klausimais, be kitų paaiškinimų.\n";
}

function quiz_import_prompt(): string
{
    return "Iš šio teksto sukurk 50 testo klausimų lietuviškai. Kiekvienam klausimui - 3 arba 4 atsakymų variantai, teisingas tik vienas.\n"
        . "Atsakyk tik klausimais, tokiu formatu, tarp klausimų - tuščia eilutė:\n\n"
        . "1. Klausimo tekstas?\nA) pirmas variantas\nB) antras variantas\nC) trečias variantas\nD) ketvirtas variantas\nAtsakymas: B\nPaaiškinimas: trumpai, kodėl (nebūtina)\n"
        . "Paveikslėlis: ką turi rodyti paveikslėlis (tik jei jo reikia)\n";
}

/**
 * Tekstas → klausimai. Supranta:
 *  - ChatGPT tekstą: „1. Klausimas?“, „A) ...“ ar „- ...“, „Atsakymas: B“ (arba teisingas pažymėtas * ar ✓), „Paaiškinimas: ...“, „Tema: ...“;
 *  - CSV (; arba tab): klausimas; A; B; C; D; teisingas (raidė, numeris ar tekstas); paaiškinimas; tema;
 *  - JSON: [{"klausimas", "atsakymai": [...], "teisingas", "paaiskinimas", "tema"}] (ar angliškai question/answers/correct).
 * Grąžina [['body', 'answers' => [..], 'correct' => indeksas|null, 'explanation', 'topic', 'error' => tekstas|null], ...]
 */
function quiz_import_parse(string $text): array
{
    $text = str_replace(["\r\n", "\r", "\u{FEFF}"], ["\n", "\n", ''], $text);
    $trim = ltrim($text);
    $items = [];
    if ($trim !== '' && ($trim[0] === '[' || $trim[0] === '{')) {
        $items = quiz_import_json($trim);
    } elseif (preg_match('/^[^\n]*[;\t][^\n]*[;\t][^\n]*[;\t]/', $trim) && !preg_match('/^\s*[A-Da-d][\).]\s/m', $trim)) {
        $items = quiz_import_csv($trim);
    } else {
        $items = quiz_import_text($text);
    }
    return array_map('quiz_import_check', $items);
}

/** Teisingo atsakymo nuoroda (raidė, numeris nuo 1 ar atsakymo tekstas) → indeksas */
function quiz_import_correct($raw, array $answers): ?int
{
    if ($raw === null || $raw === '') {
        return null;
    }
    if (is_int($raw)) {
        return $raw >= 1 && $raw <= count($answers) ? $raw - 1 : ($raw === 0 ? 0 : null);
    }
    $raw = trim((string) $raw);
    if (preg_match('/^([A-Fa-f])(?:$|[\).:\s])/u', $raw, $m)) {
        return ord(strtoupper($m[1])) - 65;
    }
    if (preg_match('/^(\d)(?:$|[\).:\s])/', $raw, $m)) {
        return (int) $m[1] - 1;
    }
    foreach ($answers as $i => $an) {
        if (mb_strtolower(trim($an)) === mb_strtolower($raw)) {
            return $i;
        }
    }
    return null;
}

function quiz_import_json(string $text): array
{
    $data = json_decode($text, true);
    if (is_array($data) && $data && array_keys($data) !== range(0, count($data) - 1)) {
        $data = $data['klausimai'] ?? $data['questions'] ?? [$data];
    }
    $out = [];
    foreach (is_array($data) ? $data : [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $pick = function (array $keys) use ($row) { foreach ($keys as $k) { if (isset($row[$k]) && $row[$k] !== '') { return $row[$k]; } } return null; };
        $answers = [];
        $marked = null;
        foreach ((array) $pick(['atsakymai', 'answers', 'variantai', 'options']) as $i => $an) {
            if (is_array($an)) {
                $answers[] = trim((string) ($an['tekstas'] ?? $an['text'] ?? $an['atsakymas'] ?? ''));
                if (!empty($an['teisingas']) || !empty($an['correct'])) {
                    $marked = count($answers) - 1;
                }
            } else {
                $answers[] = trim((string) $an);
            }
        }
        $c = $pick(['teisingas', 'correct', 'atsakymas', 'answer']);
        $out[] = ['body' => trim((string) $pick(['klausimas', 'question', 'body', 'q'])), 'answers' => $answers,
                  'correct' => $marked ?? quiz_import_correct(is_numeric($c) ? (int) $c : $c, $answers),
                  'explanation' => trim((string) $pick(['paaiskinimas', 'paaiškinimas', 'explanation'])), 'topic' => trim((string) $pick(['tema', 'topic'])),
                  'image_ref' => trim((string) $pick(['paveikslelis', 'paveikslėlis', 'image', 'picture'])), 'num' => count($out) + 1];
    }
    return $out;
}

function quiz_import_csv(string $text): array
{
    $first = strtok($text, "\n");
    $sep = substr_count($first, "\t") > substr_count($first, ';') ? "\t" : ';';
    $out = [];
    foreach (explode("\n", $text) as $n => $line) {
        if (trim($line) === '') {
            continue;
        }
        $c = array_map('trim', str_getcsv($line, $sep, '"', ''));
        if ($n === 0 && preg_match('/^(klausimas|question)$/iu', $c[0] ?? '')) {
            continue;   // antraštė
        }
        // klausimas; atsakymai...; teisingas; [paaiškinimas]; [tema] - teisingas: paskutinis trumpas laukas (raidė ar skaičius) po atsakymų
        $body = array_shift($c);
        $answers = [];
        $correct = null;
        while ($c) {
            $v = array_shift($c);
            if (preg_match('/^([A-Fa-f]|\d)$/', $v) && count($answers) >= 2) {
                $correct = $v;
                break;
            }
            if ($v !== '') {
                $answers[] = $v;
            }
        }
        $out[] = ['body' => (string) $body, 'answers' => $answers, 'correct' => quiz_import_correct($correct, $answers),
                  'explanation' => (string) ($c[0] ?? ''), 'topic' => (string) ($c[1] ?? '')];
    }
    return $out;
}

function quiz_import_text(string $text): array
{
    $out = [];
    $cur = null;
    $flush = function () use (&$cur, &$out) {
        if ($cur && ($cur['body'] !== '' || $cur['answers'])) {
            $cur['correct'] = $cur['marked'] ?? quiz_import_correct($cur['raw'], $cur['answers']);
            unset($cur['marked'], $cur['raw']);
            $out[] = $cur;
        }
        $cur = null;
    };
    $new = function (string $body, ?int $num = null) use (&$cur, $flush, &$out) {
        $flush();
        $num = $num ?? count($out) + 1;
        $qid = null;
        if (preg_match('/^\[#(\d{1,9})\]\s*(.*)$/u', $body, $mm)) {   // „[#123]“ - jau esantis klausimas (atsisiųstame faile): bus pataisytas
            $qid = (int) $mm[1];
            $body = $mm[2];
        }
        $cur = ['body' => $body, 'answers' => [], 'marked' => null, 'raw' => null, 'explanation' => '', 'topic' => '', 'qid' => $qid, 'num' => $num, 'image_ref' => ''];
    };
    foreach (explode("\n", $text) as $line) {
        $l = trim(str_replace(['**', '__'], '', $line));
        $l = preg_replace('/^#+\s*/', '', $l);
        if ($l === '' || preg_match('/^-{3,}$/', $l) || str_starts_with($l, '//') || str_starts_with($l, '>>')) {
            continue;   // tuščios eilutės, skirtukai, paaiškinimai („// ...“) ir peržiūros pastabos („>> ...“)
        }
        if ($cur && preg_match('/^(?:paveiksl[eė]lis|nuotrauka|image|picture)\s*[:\-–]\s*(.+)$/iu', $l, $m)) {
            $cur['image_ref'] = trim($m[1]);
            continue;
        }
        if ($cur && preg_match('/^(?:teisingas(?:\s+atsakymas)?|atsakymas|correct(?:\s+answer)?|answer)\s*[:\-–]\s*(.+)$/iu', $l, $m)) {
            $cur['raw'] = trim($m[1]);
            continue;
        }
        if ($cur && preg_match('/^(?:paai[sš]kinimas|explanation)\s*[:\-–]\s*(.+)$/iu', $l, $m)) {
            $cur['explanation'] = trim($m[1]);
            continue;
        }
        if ($cur && preg_match('/^(?:tema|topic)\s*[:\-–]\s*(.+)$/iu', $l, $m)) {
            $cur['topic'] = trim($m[1]);
            continue;
        }
        // Atsakymas: „A) ...“, „a. ...“, „- ...“, „[x] ...“
        if ($cur && $cur['body'] !== '' && (preg_match('/^(?:[-*•]\s*)?(?:\[( |x|X)\]\s*)?([A-Fa-f])[\).:]\s+(.+)$/u', $l, $m)
                || preg_match('/^(?:[-*•])\s+(?:\[( |x|X)\]\s*)?()(.+)$/u', $l, $m))) {
            $an = trim($m[3]);
            $ok = strtolower($m[1]) === 'x';
            if (preg_match('/\s*(?:\*|✓|✔|✅|\((?:teisingas|teisingai|correct)\))\s*$/iu', $an)) {
                $an = trim(preg_replace('/\s*(?:\*|✓|✔|✅|\((?:teisingas|teisingai|correct)\))\s*$/iu', '', $an));
                $ok = true;
            }
            $cur['answers'][] = $an;
            if ($ok) {
                $cur['marked'] = count($cur['answers']) - 1;
            }
            continue;
        }
        // Klausimas: „1. ...“, „1) ...“, „Klausimas 1: ...“ arba bet kokia eilutė po ankstesnio klausimo atsakymų
        if (preg_match('/^(?:(?:klausimas|question|q)\s*(\d*)\s*[:.)]\s*|(\d{1,4})\s*[.)]\s+)(.+)$/iu', $l, $m)) {
            $new(trim($m[3]), ($m[1] . $m[2]) !== '' ? (int) ($m[1] . $m[2]) : null);
        } elseif (!$cur || $cur['answers']) {
            $new($l);
        } else {
            $cur['body'] .= ($cur['body'] === '' ? '' : ' ') . $l;   // ilgas klausimas per kelias eilutes
        }
    }
    $flush();
    return $out;
}

/** Klausimo patikra prieš įkeliant: 2-4 atsakymai, pažymėtas teisingas */
function quiz_import_check(array $q): array
{
    $q['body'] = mb_substr(trim((string) $q['body']), 0, 2000);
    $q['answers'] = array_values(array_filter(array_map(function ($a) { return mb_substr(trim((string) $a), 0, 255); }, (array) $q['answers']), 'strlen'));
    $q['explanation'] = mb_substr(trim((string) ($q['explanation'] ?? '')), 0, 2000);
    $q['topic'] = mb_substr(trim((string) ($q['topic'] ?? '')), 0, 190);
    $q['image_ref'] = mb_substr(trim((string) ($q['image_ref'] ?? '')), 0, 255);
    $q['num'] = (int) ($q['num'] ?? 0);
    $q['error'] = null;
    if ($q['body'] === '') {
        $q['error'] = 'nėra klausimo teksto';
    } elseif (count($q['answers']) < 2) {
        $q['error'] = 'reikia bent 2 atsakymų';
    } elseif (count($q['answers']) > 4) {
        $q['error'] = 'daugiausia 4 atsakymai';
    } elseif (!isset($q['correct']) || $q['correct'] === null || !isset($q['answers'][$q['correct']])) {
        $q['error'] = 'nepažymėtas teisingas atsakymas';
    }
    return $q;
}

/**
 * Įkelia tinkamus klausimus į testą. Klausimas su „[#numeris]“ (iš atsisiųsto failo, #137) - pataisomas vietoje;
 * kiti - pridedami (jau esantys tokiu pačiu tekstu praleidžiami). Klausimai, kurių faile nėra, lieka nepaliesti.
 * $draft - įkelti peržiūrai (juodraščiai, nariams dar nerodomi). $images - quiz_import_images() rezultatas
 * („Paveikslėlis: Q12.png“ arba paveikslėlis, kurio pavadinime - klausimo numeris).
 * Pataisyto klausimo senos peržiūros žymos išvalomos. Grąžina, kiek pridėta; $updated - kiek pataisyta.
 */
function quiz_import_save(int $quizId, array $items, ?int &$updated = null, bool $draft = false, array $images = []): int
{
    $updated = 0;
    $have = array_map(function ($b) { return mb_strtolower(trim($b)); }, q('SELECT body FROM quiz_questions WHERE quiz_id = ?', [$quizId])->fetchAll(PDO::FETCH_COLUMN));
    $mine = array_map('intval', q('SELECT id FROM quiz_questions WHERE quiz_id = ?', [$quizId])->fetchAll(PDO::FETCH_COLUMN));
    $next = (int) q_value('SELECT COALESCE(MAX(sort_order), 0) FROM quiz_questions WHERE quiz_id = ?', [$quizId]);
    $n = 0;
    $used = [];
    db()->beginTransaction();
    foreach ($items as $q) {
        if ($q['error']) {
            continue;
        }
        [$img, $note] = quiz_import_pick_image($q, $images);
        if ($img) {
            $used[$img] = true;
        }
        if (!empty($q['qid']) && in_array((int) $q['qid'], $mine, true)) {
            $qid = (int) $q['qid'];
            q('UPDATE quiz_questions SET topic = ?, body = ?, explanation = ?' . ($draft ? ', is_draft = 1' : '') . ' WHERE id = ? AND quiz_id = ?',
                [$q['topic'] ?: null, $q['body'], $q['explanation'] ?: null, $qid, $quizId]);
            q('DELETE FROM quiz_answers WHERE question_id = ?', [$qid]);
            foreach ($q['answers'] as $i => $an) {
                q('INSERT INTO quiz_answers (question_id, body, is_correct, sort_order) VALUES (?, ?, ?, ?)', [$qid, $an, $i === $q['correct'] ? 1 : 0, $i]);
            }
            if ($img) {
                quiz_set_image($qid, $img);
            } elseif ($note !== '') {
                q('UPDATE quiz_questions SET image_note = ? WHERE id = ?', [$note, $qid]);
            }
            q('DELETE FROM quiz_review WHERE question_id = ?', [$qid]);   // pataisytas - peržiūrėti iš naujo
            $updated++;
            continue;
        }
        if (in_array(mb_strtolower($q['body']), $have, true)) {
            continue;
        }
        q('INSERT INTO quiz_questions (quiz_id, topic, body, explanation, sort_order, is_draft, image, image_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$quizId, $q['topic'] ?: null, $q['body'], $q['explanation'] ?: null, ++$next, $draft ? 1 : 0, $img, $img ? null : ($note ?: null)]);
        $qid = (int) db()->lastInsertId();
        foreach ($q['answers'] as $i => $an) {
            q('INSERT INTO quiz_answers (question_id, body, is_correct, sort_order) VALUES (?, ?, ?, ?)', [$qid, $an, $i === $q['correct'] ? 1 : 0, $i]);
        }
        $have[] = mb_strtolower($q['body']);
        $n++;
    }
    db()->commit();
    // Įkelti, bet niekam nepriskirti paveikslėliai - ištrinami
    foreach (array_unique(array_values(array_merge($images['by_name'] ?? [], $images['by_num'] ?? []))) as $f) {
        if (!isset($used[$f])) {
            quiz_delete_image($f);
        }
    }
    return $n;
}

/** Klausimui tinkantis paveikslėlis: [failas|null, aprašymas] („Paveikslėlis: Q12.png“, numeris pavadinime, ar tik aprašymas) */
function quiz_import_pick_image(array $q, array $images): array
{
    $ref = trim((string) ($q['image_ref'] ?? ''));
    if ($ref !== '' && preg_match('/\.(png|jpe?g|webp)$/i', $ref)) {
        $key = mb_strtolower(basename(str_replace('\\', '/', $ref)));
        if (isset($images['by_name'][$key])) {
            return [$images['by_name'][$key], ''];
        }
        if (preg_match('/(\d{1,4})\.\w+$/', $key, $m) && isset($images['by_num'][(int) $m[1]])) {
            return [$images['by_num'][(int) $m[1]], ''];
        }
        return [null, ''];
    }
    if (!empty($q['num']) && isset($images['by_num'][(int) $q['num']])) {
        return [$images['by_num'][(int) $q['num']], ''];
    }
    return [null, $ref];
}

/**
 * Kartu su klausimais įkelti paveikslėliai (atskiri failai arba ZIP): išsaugomi ir grąžinami
 * ['by_name' => [mažosiomis pavadinimas => failas], 'by_num' => [klausimo numeris => failas], 'text' => tekstas iš ZIP, 'errors' => [...]]
 */
function quiz_import_images(array $files, string $dir): array
{
    $out = ['by_name' => [], 'by_num' => [], 'text' => '', 'errors' => []];
    $add = function (string $name, string $tmp, int $size) use (&$out, $dir) {
        try {
            $saved = save_uploaded_image(['error' => UPLOAD_ERR_OK, 'tmp_name' => $tmp, 'size' => $size, 'name' => $name], $dir);
        } catch (RuntimeException $ex) {
            $out['errors'][] = $name . ': ' . $ex->getMessage();
            return;
        }
        $key = mb_strtolower(basename(str_replace('\\', '/', $name)));
        $out['by_name'][$key] = $saved;
        if (preg_match('/(\d{1,4})\.\w+$/', $key, $m)) {   // Q12.png, 12.jpg, klausimas-12.png
            $out['by_num'][(int) $m[1]] = $out['by_num'][(int) $m[1]] ?? $saved;
        }
    };
    $count = 0;
    foreach ($files as $f) {
        $name = (string) $f['name'];
        if (preg_match('/\.zip$/i', $name)) {
            if (!class_exists('ZipArchive')) {
                $out['errors'][] = t('Serveris neatidaro ZIP failų - įkelkite paveikslėlius atskirai.');
                continue;
            }
            $zip = new ZipArchive();
            if ($zip->open($f['tmp_name']) !== true) {
                $out['errors'][] = $name . ': ' . t('nepavyko atidaryti ZIP.');
                continue;
            }
            for ($i = 0; $i < $zip->numFiles && $count < 300; $i++) {
                $st = $zip->statIndex($i);
                $en = (string) $st['name'];
                if (str_ends_with($en, '/') || str_contains($en, '__MACOSX') || $st['size'] > IMAGE_MAX_BYTES) {
                    continue;
                }
                if (preg_match('/\.(txt|md|csv|json)$/i', $en) && $out['text'] === '' && $st['size'] < 2 * 1024 * 1024) {
                    $out['text'] = (string) $zip->getFromIndex($i);
                } elseif (preg_match('/\.(png|jpe?g|webp)$/i', $en)) {
                    $tmp = tempnam(sys_get_temp_dir(), 'qz');
                    file_put_contents($tmp, $zip->getFromIndex($i));
                    $add($en, $tmp, (int) filesize($tmp));
                    @unlink($tmp);
                    $count++;
                }
            }
            $zip->close();
        } elseif (preg_match('/\.(png|jpe?g|webp)$/i', $name) && $count < 300) {
            $add($name, $f['tmp_name'], (int) $f['size']);
            $count++;
        }
    }
    return $out;
}

/** Naujas klausimo paveikslėlis (senas ištrinamas, aprašymas nebereikalingas) */
function quiz_set_image(int $qid, ?string $file): void
{
    $old = q_value('SELECT image FROM quiz_questions WHERE id = ?', [$qid]);
    if ($old && $old !== $file) {
        quiz_delete_image($old);
    }
    q('UPDATE quiz_questions SET image = ?' . ($file ? ', image_note = NULL' : '') . ' WHERE id = ?', [$file, $qid]);
}

function quiz_delete_image(string $file): void
{
    $dir = PUBLIC_DIR . '/uploads/quiz';
    @unlink($dir . '/' . basename($file));
    @unlink($dir . '/thumb_' . basename($file));
}

function quiz_image_url(string $file): string
{
    return url('uploads/quiz/' . $file);
}

/** Konfeti išlaikius testą (be bibliotekų, ~3 s) */
function confetti_js(): string
{
    return <<<'JS'
(function () {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  var c = document.createElement('canvas'), x = c.getContext('2d'), W, H, parts = [], t0 = Date.now();
  c.style.cssText = 'position:fixed;inset:0;pointer-events:none;z-index:2000';
  document.body.appendChild(c);
  function size() { W = c.width = innerWidth; H = c.height = innerHeight; }
  size(); addEventListener('resize', size);
  var colors = ['#F0CB4E', '#E6842B', '#4C8353', '#2E6FA8', '#A85C7E', '#D9534F'];
  for (var i = 0; i < 140; i++) parts.push({x: W / 2, y: H / 3, vx: (Math.random() - 0.5) * 14, vy: Math.random() * -12 - 3, r: Math.random() * 6 + 3, c: colors[i % colors.length], a: Math.random() * 6});
  (function tick() {
    x.clearRect(0, 0, W, H);
    parts.forEach(function (p) { p.vy += 0.35; p.x += p.vx; p.y += p.vy; p.a += 0.1; x.fillStyle = p.c; x.save(); x.translate(p.x, p.y); x.rotate(p.a); x.fillRect(-p.r, -p.r / 2, p.r * 2, p.r); x.restore(); });
    if (Date.now() - t0 < 3200) requestAnimationFrame(tick); else c.remove();
  })();
})();
JS;
}
