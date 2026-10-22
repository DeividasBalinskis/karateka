<?php
// WTKU teisėjų žinių testas (db/012): klausimų bankas, egzaminas ir mokomasis testas.
// Nariams - teiseju-testas.php, treneriams - admin/teiseju-testas.php.

// Nustatymai, kuriuos keičia administratorius (Treneriams -> Teisėjų testas -> Nustatymai).
// Saugomi site_settings kaip JSON; ko nėra - imama iš čia.
const REFEREE_DEFAULTS = [
    'exam_questions' => 50,         // klausimų egzamine
    'pass_percent'   => 80,         // išlaikymo riba, % teisingų atsakymų
    'time_limit'     => 60,         // minutės; 0 - be limito
    'exam_attempts'  => 1,          // kiek kartų vienas žmogus gali laikyti, kol egzaminas atidarytas; 0 - neribota
    'proportional'   => 1,          // egzamino klausimai iš kiekvieno skyriaus proporcingai
    'show_answers'   => 0,          // po egzamino rodyti teisingus atsakymus
    'practice'       => 1,          // mokomasis testas (kol egzaminas uždarytas)
    'access'         => 'selected', // 'members' - visi patvirtinti nariai, 'selected' - tik pažymėti (treneriai - visada)
    'exam_open'      => 0,
    'exam_opened_at' => null,       // nuo kada skaičiuojami bandymai
];

// Mokomojo testo ilgio pasirinkimai
const REFEREE_PRACTICE_SIZES = [10, 20, 50];

// Kiek sekundžių po laiko pabaigos dar priimami atsakymai (lėtas internetas)
const REFEREE_GRACE = 30;

function referee_settings(): array
{
    $saved = json_decode((string) site_setting('referee_test', ''), true);
    return array_merge(REFEREE_DEFAULTS, is_array($saved) ? array_intersect_key($saved, REFEREE_DEFAULTS) : []);
}

function save_referee_settings(array $changes): void
{
    set_site_setting('referee_test', json_encode(array_merge(referee_settings(), $changes), JSON_UNESCAPED_UNICODE));
}

/** Kiek teisingų atsakymų reikia išlaikyti */
function referee_required(int $count, int $percent): int
{
    return (int) ceil($count * $percent / 100);
}

/** Ar paskyra gali laikyti testą */
function referee_can_take(?array $a = null): bool
{
    $a = $a ?? current_account();
    if (!is_active_account($a)) {
        return false;
    }
    if (is_staff($a) || referee_settings()['access'] === 'members') {
        return true;
    }
    return (bool) q_value('SELECT 1 FROM referee_access WHERE account_id = ?', [$a['id']]);
}

/** Meniu nuoroda: kai žmogus gali laikyti ir yra ką laikyti (atidarytas egzaminas arba mokomasis testas) */
function referee_menu_visible(array $a): bool
{
    try {
        $s = referee_settings();
        return ($s['exam_open'] || $s['practice']) && referee_can_take($a);
    } catch (PDOException $ex) {
        return false;   // db/012 dar neimportuotas
    }
}

/** Skyriai su aktyvių temų skaičiumi: ['Kumite' => 30, ...] (tvarka - kaip klausimų banke) */
function referee_categories(): array
{
    return q('SELECT category, COUNT(DISTINCT topic) FROM referee_questions WHERE is_active = 1 GROUP BY category ORDER BY MIN(id)')
        ->fetchAll(PDO::FETCH_KEY_PAIR);
}

/** Kiek skirtingų temų (tiek daugiausia klausimų gali būti viename teste) */
function referee_topic_count(?string $category = null): int
{
    $cats = referee_categories();
    return $category === null ? (int) array_sum($cats) : (int) ($cats[$category] ?? 0);
}

/** Kiek klausimų egzamine iš kiekvieno skyriaus, kai jie imami proporcingai: ['Kumite' => 15, ...] */
function referee_category_quotas(int $count): array
{
    $cats = referee_categories();
    $total = array_sum($cats);
    if (!$total) {
        return [];
    }
    $count = min($count, $total);
    $quotas = $rest = [];
    foreach ($cats as $cat => $n) {
        $exact = $count * $n / $total;
        $quotas[$cat] = (int) floor($exact);
        $rest[$cat] = $exact - $quotas[$cat];
    }
    // Likę klausimai - skyriams su didžiausia trupmenine dalimi
    arsort($rest);
    foreach (array_keys($rest) as $cat) {
        if (array_sum($quotas) >= $count) {
            break;
        }
        $quotas[$cat]++;
    }
    return $quotas;
}

/**
 * Atsitiktiniai klausimai testui: iš kiekvienos temos ne daugiau kaip vienas (atsitiktinė formuluotė).
 * $proportional - iš kiekvieno skyriaus tiek, kiek jam tenka pagal temų skaičių.
 */
function referee_pick_questions(int $count, ?string $category, bool $proportional): array
{
    $sql = 'SELECT id, category, topic FROM referee_questions WHERE is_active = 1';
    $params = [];
    if ($category !== null) {
        $sql .= ' AND category = ?';
        $params[] = $category;
    }
    $byTopic = [];
    foreach (q_all($sql, $params) as $row) {
        $byTopic[$row['topic']][] = $row;
    }
    $topics = [];
    foreach ($byTopic as $variants) {
        $topics[] = $variants[random_int(0, count($variants) - 1)];
    }
    shuffle($topics);

    if (!$proportional || $category !== null) {
        return array_slice($topics, 0, $count);
    }
    $quotas = referee_category_quotas($count);
    $picked = [];
    foreach ($topics as $t) {
        if (($quotas[$t['category']] ?? 0) > 0) {
            $picked[] = $t;
            $quotas[$t['category']]--;
        }
    }
    shuffle($picked);
    return $picked;
}

/** Pradeda naują bandymą ir grąžina jo id */
function referee_start_attempt(array $a, string $mode, ?string $category, int $count): int
{
    $s = referee_settings();
    $questions = referee_pick_questions($count, $category, $mode === 'exam' && $s['proportional']);
    if (!$questions) {
        throw new RuntimeException('Klausimų banke nėra aktyvių klausimų.');
    }
    $ids = array_map('intval', array_column($questions, 'id'));
    $options = q('SELECT id, (wrong2 IS NOT NULL) + (wrong3 IS NOT NULL) + 2 FROM referee_questions WHERE id IN (' . implode(',', $ids) . ')')
        ->fetchAll(PDO::FETCH_KEY_PAIR);
    $now = date('Y-m-d H:i:s');
    $deadline = $mode === 'exam' && $s['time_limit'] > 0 ? date('Y-m-d H:i:s', time() + 60 * (int) $s['time_limit']) : null;

    db()->beginTransaction();
    try {
        q('INSERT INTO referee_attempts (account_id, mode, category, question_count, pass_percent, started_at, deadline_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$a['id'], $mode, $category, count($ids), (int) $s['pass_percent'], $now, $deadline]);
        $attemptId = (int) db()->lastInsertId();
        foreach ($ids as $pos => $qid) {
            $order = range(0, (int) $options[$qid] - 1);
            shuffle($order);
            q('INSERT INTO referee_answers (attempt_id, position, question_id, answer_order) VALUES (?, ?, ?, ?)',
                [$attemptId, $pos, $qid, implode('', $order)]);
        }
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }
    return $attemptId;
}

/** Bandymas; jei baigėsi laikas - užbaigiamas su išsaugotais atsakymais */
function referee_attempt(int $id): ?array
{
    $at = q_one('SELECT * FROM referee_attempts WHERE id = ?', [$id]);
    if ($at && $at['finished_at'] === null && referee_time_is_up($at)) {
        referee_finish_attempt($id);
        $at = q_one('SELECT * FROM referee_attempts WHERE id = ?', [$id]);
    }
    return $at;
}

function referee_time_is_up(array $attempt): bool
{
    return $attempt['deadline_at'] !== null && time() > strtotime($attempt['deadline_at']) + REFEREE_GRACE;
}

/** Nebaigtas paskyros bandymas (jei yra) */
function referee_unfinished(int $accountId, string $mode): ?array
{
    $id = q_value('SELECT id FROM referee_attempts WHERE account_id = ? AND mode = ? AND finished_at IS NULL ORDER BY id DESC LIMIT 1', [$accountId, $mode]);
    if ($id === false) {
        return null;
    }
    $at = referee_attempt((int) $id);
    return $at && $at['finished_at'] === null ? $at : null;
}

/** Išsaugo atsakymą; false - jei bandymas jau baigtas ar atsakymas netinkamas */
function referee_save_answer(array $attempt, int $position, ?int $chosen): bool
{
    if ($attempt['finished_at'] !== null || referee_time_is_up($attempt)) {
        return false;
    }
    $order = q_value('SELECT answer_order FROM referee_answers WHERE attempt_id = ? AND position = ?', [$attempt['id'], $position]);
    if ($order === false || ($chosen !== null && ($chosen < 0 || $chosen >= strlen($order)))) {
        return false;
    }
    q('UPDATE referee_answers SET chosen = ? WHERE attempt_id = ? AND position = ?', [$chosen, $attempt['id'], $position]);
    return true;
}

/** Suskaičiuoja rezultatą ir užbaigia bandymą (antrą kartą nieko nekeičia) */
function referee_finish_attempt(int $id): void
{
    $score = (int) q_value('SELECT COUNT(*) FROM referee_answers WHERE attempt_id = ? AND chosen IS NOT NULL AND SUBSTRING(answer_order, chosen + 1, 1) = "0"', [$id]);
    q('UPDATE referee_attempts SET finished_at = NOW(), score = ?, passed = (? >= CEIL(question_count * pass_percent / 100))
        WHERE id = ? AND finished_at IS NULL', [$score, $score, $id]);
}

/** Užbaigia visus bandymus, kurių laikas baigėsi (rezultatų sąrašui treneriams) */
function referee_finish_expired(): void
{
    $ids = q('SELECT id FROM referee_attempts WHERE finished_at IS NULL AND deadline_at < NOW() - INTERVAL ' . REFEREE_GRACE . ' SECOND')
        ->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        referee_finish_attempt((int) $id);
    }
}

/** Egzaminas atidaromas (bandymai skaičiuojami iš naujo) arba uždaromas (nebaigti egzaminai užbaigiami) */
function referee_set_exam_open(bool $open): void
{
    if ($open) {
        save_referee_settings(['exam_open' => 1, 'exam_opened_at' => date('Y-m-d H:i:s')]);
        return;
    }
    save_referee_settings(['exam_open' => 0]);
    foreach (q('SELECT id FROM referee_attempts WHERE mode = "exam" AND finished_at IS NULL')->fetchAll(PDO::FETCH_COLUMN) as $id) {
        referee_finish_attempt((int) $id);
    }
}

/** Kiek kartų paskyra laikė egzaminą nuo jo atidarymo */
function referee_exam_attempts_used(int $accountId): int
{
    $s = referee_settings();
    return (int) q_value('SELECT COUNT(*) FROM referee_attempts WHERE account_id = ? AND mode = "exam" AND started_at >= ?',
        [$accountId, $s['exam_opened_at'] ?? '2000-01-01']);
}

/** Ar galima pradėti egzaminą: ['ok' => bool, 'left' => likę bandymai | null (neribota), 'reason' => tekstas] */
function referee_exam_status(array $a): array
{
    $s = referee_settings();
    if (!$s['exam_open']) {
        return ['ok' => false, 'left' => null, 'reason' => 'Egzaminas šiuo metu uždarytas. Treneris jį atidarys egzamino dieną.'];
    }
    if (!$s['exam_attempts']) {
        return ['ok' => true, 'left' => null, 'reason' => ''];
    }
    $left = max(0, (int) $s['exam_attempts'] - referee_exam_attempts_used((int) $a['id']));
    return ['ok' => $left > 0, 'left' => $left, 'reason' => $left ? '' : 'Egzamino bandymus jau išnaudojote. Jei reikia laikyti dar kartą - kreipkitės į trenerį.'];
}

/**
 * Bandymo klausimai su atsakymais rodoma tvarka:
 * [['position', 'question', 'category', 'source', 'answers' => [tekstai], 'correct_pos', 'chosen', 'is_correct'], ...]
 */
function referee_attempt_questions(int $attemptId): array
{
    $rows = q_all('SELECT ra.position, ra.answer_order, ra.chosen, q.id AS question_id, q.question, q.category, q.source, q.correct, q.wrong1, q.wrong2, q.wrong3
                     FROM referee_answers ra JOIN referee_questions q ON q.id = ra.question_id
                    WHERE ra.attempt_id = ? ORDER BY ra.position', [$attemptId]);
    foreach ($rows as &$r) {
        $texts = [$r['correct'], $r['wrong1'], $r['wrong2'], $r['wrong3']];
        $r['answers'] = [];
        foreach (str_split($r['answer_order']) as $i) {
            $r['answers'][] = (string) $texts[(int) $i];
        }
        $r['correct_pos'] = strpos($r['answer_order'], '0');
        $r['chosen'] = $r['chosen'] === null ? null : (int) $r['chosen'];
        $r['is_correct'] = $r['chosen'] !== null && $r['chosen'] === $r['correct_pos'];
    }
    return $rows;
}

/** Rezultatas pagal skyrius: ['Kumite' => ['ok' => 12, 'total' => 15], ...] */
function referee_breakdown(array $questions): array
{
    $out = [];
    foreach ($questions as $q) {
        $out[$q['category']]['total'] = ($out[$q['category']]['total'] ?? 0) + 1;
        $out[$q['category']]['ok'] = ($out[$q['category']]['ok'] ?? 0) + ($q['is_correct'] ? 1 : 0);
    }
    return $out;
}

function referee_percent(array $attempt): int
{
    return $attempt['question_count'] ? (int) round(100 * (int) $attempt['score'] / (int) $attempt['question_count']) : 0;
}

/** "42 min. 10 s" - kiek laiko užtruko */
function referee_duration(array $attempt): string
{
    if (!$attempt['finished_at']) {
        return '';
    }
    $sec = max(0, strtotime($attempt['finished_at']) - strtotime($attempt['started_at']));
    return $sec >= 60 ? intdiv($sec, 60) . ' min.' : $sec . ' s';
}

/** Rezultato kortelė (nariui ir treneriams). $review - rodyti kiekvieną klausimą su teisingu atsakymu. */
function render_referee_result(array $attempt, bool $review): string
{
    $questions = referee_attempt_questions((int) $attempt['id']);
    $count = (int) $attempt['question_count'];
    $need = referee_required($count, (int) $attempt['pass_percent']);
    $unanswered = count(array_filter($questions, function ($q) { return $q['chosen'] === null; }));
    ob_start();
    ?>
<div class="panel card quiz-result">
  <div class="kicker"><?= $attempt['mode'] === 'exam' ? 'Egzaminas' : 'Mokomasis testas' . ($attempt['category'] ? ' · ' . e($attempt['category']) : '') ?> · <?= e(fmt_date(substr($attempt['started_at'], 0, 10), true)) ?> <?= e(substr($attempt['started_at'], 11, 5)) ?></div>
  <div class="quiz-score-row">
    <div class="quiz-score"><?= (int) $attempt['score'] ?> <span>/ <?= $count ?></span></div>
    <div>
      <div class="quiz-verdict <?= $attempt['passed'] ? 'pass' : 'fail' ?>"><?= $attempt['passed'] ? '✓ Išlaikyta' : '✕ Neišlaikyta' ?></div>
      <div class="muted small"><?= referee_percent($attempt) ?> % · išlaikymui reikėjo <?= $need ?> iš <?= $count ?> (<?= (int) $attempt['pass_percent'] ?> %) · <?= e(referee_duration($attempt)) ?><?= $unanswered ? ' · neatsakyta: ' . $unanswered : '' ?></div>
    </div>
  </div>
  <table class="table quiz-breakdown">
    <thead><tr><th>Skyrius</th><th style="width:110px;">Teisingai</th></tr></thead>
    <tbody>
      <?php foreach (referee_breakdown($questions) as $cat => $b): ?>
        <tr><td><?= e($cat) ?></td><td><strong><?= $b['ok'] ?></strong> / <?= $b['total'] ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php if ($review): ?>
  <div class="panel card">
    <h2>Atsakymai</h2>
    <ol class="quiz-review">
      <?php foreach ($questions as $q): ?>
        <li class="<?= $q['is_correct'] ? 'ok' : 'bad' ?>">
          <div class="q"><?= e($q['question']) ?></div>
          <?php if (!$q['is_correct']): ?>
            <div class="mine">Pasirinkta: <?= $q['chosen'] === null ? '<em>neatsakyta</em>' : e($q['answers'][$q['chosen']]) ?></div>
          <?php endif; ?>
          <div class="right"><?= $q['is_correct'] ? '✓ ' : 'Teisingas: ' ?><?= e($q['answers'][$q['correct_pos']]) ?></div>
          <div class="muted small"><?= e($q['category']) ?><?= $q['source'] ? ' · ' . e($q['source']) : '' ?></div>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
<?php endif; ?>
    <?php
    return ob_get_clean();
}
