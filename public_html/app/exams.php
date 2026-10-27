<?php
// Egzaminai: programa pagal diržą, pasiruošimo žingsniai ir testai

const REQ_SECTIONS = ['kihon' => 'Kihon', 'kata' => 'Kata', 'kumite' => 'Kumite', 'teorija' => 'Teorija', 'kita' => 'Kita'];
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
                     WHERE r.belt_level = ? ORDER BY FIELD(r.section, "kihon", "kata", "kumite", "teorija", "kita"), r.sort_order, r.id', [$belt]) as $r) {
        $out[$r['section']][] = $r;
    }
    return $out;
}

/** Kuriuos punktus narys pažymėjo „Moku“: [requirement_id => true] */
function requirement_done_ids(int $memberId): array
{
    $ids = q('SELECT requirement_id FROM requirement_progress WHERE member_id = ?', [$memberId])->fetchAll(PDO::FETCH_COLUMN);
    return array_fill_keys(array_map('intval', $ids), true);
}

function set_requirement_done(int $memberId, int $reqId, bool $done): void
{
    if ($done) {
        q('INSERT IGNORE INTO requirement_progress (member_id, requirement_id) VALUES (?, ?)', [$memberId, $reqId]);
    } else {
        q('DELETE FROM requirement_progress WHERE member_id = ? AND requirement_id = ?', [$memberId, $reqId]);
    }
}

// ---------- Testai ----------

/** Testai, kuriuos narys mato: kitam diržui (ir bendri) + pagal žymas */
function quizzes_for_member(array $m, array $tags): array
{
    $next = next_belt($m['belt_level'] !== null ? (int) $m['belt_level'] : null);
    $aud = array_merge(['karateka'], array_values(array_intersect(array_keys(MEMBER_TAGS), $tags)));
    $in = "'" . implode("','", $aud) . "'";
    return q_all("SELECT q.*, (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS questions_total
                    FROM quizzes q
                   WHERE q.is_published = 1 AND q.audience IN ($in)
                     AND (q.audience <> 'karateka' OR q.belt_level IS NULL OR q.belt_level = ?)
                   ORDER BY FIELD(q.audience, 'karateka', 'teisejas', 'egzaminatorius'), q.belt_level IS NULL, q.title", [$next ?? 0]);
}

/** Klausimai su atsakymais: [id => klausimas + 'answers' => [...]] */
function quiz_questions_full(int $quizId, ?array $onlyIds = null): array
{
    $sql = 'SELECT * FROM quiz_questions WHERE quiz_id = ?';
    if ($onlyIds !== null) {
        $sql .= $onlyIds ? ' AND id IN (' . implode(',', array_map('intval', $onlyIds)) . ')' : ' AND 0';
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

/** Naujas bandymas: atsitiktiniai klausimai iš banko (iš temos - vienas). Su laiko limitu įrašoma, iki kada baigti. */
function start_quiz_attempt(array $quiz, int $memberId, int $accountId): int
{
    $ids = array_keys(quiz_pick_questions(quiz_questions_full((int) $quiz['id'])));
    $ids = array_slice($ids, 0, max(1, (int) $quiz['question_count']));
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
    foreach (q_all('SELECT e.* FROM events e WHERE e.type = "exam" AND e.starts_on >= CURDATE() ORDER BY e.starts_on') as $e) {
        if (event_targets_member($e, $m) && !event_participant((int) $e['id'], (int) $m['id'])) {
            $e['p_status'] = null;
            return $e;
        }
    }
    return null;
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
