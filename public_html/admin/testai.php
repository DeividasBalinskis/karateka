<?php
// Testai: klausimų bankas (klausimas, 2-4 atsakymai, paveikslėlis ar video), nustatymai ir rezultatai
require dirname(__DIR__) . '/app/bootstrap.php';
require APP_DIR . '/images.php';
$me = require_staff();

const QUIZ_DIR = PUBLIC_DIR . '/uploads/quiz';

$id = (int) (get('id') ?: post('id'));
$errors = [];

/** Klausimo formos duomenys -> DB (naujas arba esamas) */
function save_question(int $quizId, int $qid, array &$errors): ?int
{
    $body = post('body');
    $answers = [];
    foreach ((array) ($_POST['ans_body'] ?? []) as $i => $text) {
        $text = trim((string) $text);
        if ($text !== '') {
            $answers[] = ['id' => (int) ($_POST['ans_id'][$i] ?? 0), 'body' => mb_substr($text, 0, 255), 'correct' => (string) $i === post('correct')];
        }
    }
    $yt = post('youtube') !== '' ? youtube_id(post('youtube')) : null;
    if ($body === '') {
        $errors[] = 'Įveskite klausimą.';
    }
    if (count($answers) < 2) {
        $errors[] = 'Reikia bent 2 atsakymų.';
    } elseif (!array_filter(array_column($answers, 'correct'))) {
        $errors[] = 'Pažymėkite teisingą atsakymą.';
    }
    if (post('youtube') !== '' && !$yt) {
        $errors[] = 'Neatpažinta YouTube nuoroda.';
    }
    $image = null;
    if (!empty($_FILES['image']['name'])) {
        try {
            $image = save_uploaded_image($_FILES['image'], QUIZ_DIR);
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }
    if ($errors) {
        return null;
    }
    if ($qid) {
        q('UPDATE quiz_questions SET body = ?, explanation = ?, youtube_id = ? WHERE id = ? AND quiz_id = ?', [$body, post('explanation') ?: null, $yt, $qid, $quizId]);
    } else {
        $next = (int) q_value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM quiz_questions WHERE quiz_id = ?', [$quizId]);
        q('INSERT INTO quiz_questions (quiz_id, body, explanation, youtube_id, sort_order) VALUES (?, ?, ?, ?, ?)', [$quizId, $body, post('explanation') ?: null, $yt, $next]);
        $qid = (int) db()->lastInsertId();
    }
    if ($image || !empty($_POST['remove_image'])) {
        $old = q_value('SELECT image FROM quiz_questions WHERE id = ?', [$qid]);
        if ($old) {
            @unlink(QUIZ_DIR . '/' . basename($old));
            @unlink(QUIZ_DIR . '/thumb_' . basename($old));
        }
        q('UPDATE quiz_questions SET image = ? WHERE id = ?', [$image, $qid]);
    }
    // Atsakymai atnaujinami vietoje (seni bandymai išlaiko nuorodas), pašalinti - ištrinami
    $keep = [];
    foreach ($answers as $i => $an) {
        if ($an['id'] && q_value('SELECT 1 FROM quiz_answers WHERE id = ? AND question_id = ?', [$an['id'], $qid])) {
            q('UPDATE quiz_answers SET body = ?, is_correct = ?, sort_order = ? WHERE id = ?', [$an['body'], $an['correct'] ? 1 : 0, $i, $an['id']]);
            $keep[] = $an['id'];
        } else {
            q('INSERT INTO quiz_answers (question_id, body, is_correct, sort_order) VALUES (?, ?, ?, ?)', [$qid, $an['body'], $an['correct'] ? 1 : 0, $i]);
            $keep[] = (int) db()->lastInsertId();
        }
    }
    q('DELETE FROM quiz_answers WHERE question_id = ? AND id NOT IN (' . implode(',', $keep) . ')', [$qid]);
    return $qid;
}

/** Klausimo forma (naujam - tuščia) */
function question_form(int $quizId, ?array $q): string
{
    $answers = $q['answers'] ?? [];
    while (count($answers) < 4) {
        $answers[] = ['id' => 0, 'body' => '', 'is_correct' => 0];
    }
    $html = '<form method="post" enctype="multipart/form-data" class="form q-form">' . csrf_field()
        . '<input type="hidden" name="id" value="' . $quizId . '"><input type="hidden" name="action" value="question"><input type="hidden" name="qid" value="' . (int) ($q['id'] ?? 0) . '">'
        . '<label>Klausimas <textarea name="body" rows="2" required>' . e($q['body'] ?? '') . '</textarea></label>'
        . '<div class="q-answers-edit"><span class="hint">Atsakymai (užtenka 2; pažymėkite teisingą)</span>';
    foreach (array_slice($answers, 0, 4) as $i => $an) {
        $html .= '<div class="ans-row"><input type="radio" name="correct" value="' . $i . '" ' . ($an['is_correct'] ? 'checked' : '') . ' aria-label="Teisingas">'
            . '<input type="hidden" name="ans_id[' . $i . ']" value="' . (int) $an['id'] . '">'
            . '<input type="text" name="ans_body[' . $i . ']" value="' . e($an['body']) . '" placeholder="' . ($i + 1) . ' atsakymas"></div>';
    }
    $html .= '</div><div class="form-row">'
        . '<label>Paaiškinimas <span class="hint">nebūtina, rodomas po atsakymo</span><input type="text" name="explanation" value="' . e($q['explanation'] ?? '') . '"></label>'
        . '<label>YouTube <span class="hint">nebūtina</span><input type="text" name="youtube" value="' . (!empty($q['youtube_id']) ? 'https://youtu.be/' . e($q['youtube_id']) : '') . '"></label></div>'
        . '<div class="form-row"><label>Paveikslėlis <span class="hint">nebūtina, pvz. stovėsena ar smūgis</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>';
    if (!empty($q['image'])) {
        $html .= '<label class="check" style="align-self:end;"><input type="checkbox" name="remove_image" value="1"><span>Pašalinti esamą <img src="' . e(quiz_image_url($q['image'])) . '" alt="" class="q-thumb"></span></label>';
    }
    $html .= '</div><div class="row"><button class="btn btn-primary btn-sm" type="submit">' . (!empty($q['id']) ? 'Išsaugoti klausimą' : '+ Pridėti klausimą') . '</button></div></form>';
    return $html;
}

if (is_post()) {
    csrf_check();
    $action = post('action');
    if ($action === 'quiz') {
        $data = [
            'title' => post('title'),
            'audience' => isset(QUIZ_AUDIENCE[post('audience')]) ? post('audience') : 'karateka',
            'belt_level' => belt_from_post('belt_level'),
            'description' => post('description') ?: null,
            'question_count' => max(1, min(100, (int) post('question_count'))),
            'pass_percent' => max(1, min(100, (int) post('pass_percent'))),
            'kids_mode' => !empty($_POST['kids_mode']) ? 1 : 0,
            'is_published' => !empty($_POST['is_published']) ? 1 : 0,
        ];
        if ($data['audience'] !== 'karateka') {
            $data['belt_level'] = null;
        }
        if ($data['title'] === '') {
            $errors[] = 'Įveskite pavadinimą.';
        } else {
            if ($id) {
                q('UPDATE quizzes SET title = ?, audience = ?, belt_level = ?, description = ?, question_count = ?, pass_percent = ?, kids_mode = ?, is_published = ?, updated_at = NOW() WHERE id = ?',
                    array_merge(array_values($data), [$id]));
            } else {
                q('INSERT INTO quizzes (title, audience, belt_level, description, question_count, pass_percent, kids_mode, is_published) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', array_values($data));
                $id = (int) db()->lastInsertId();
            }
            flash('ok', 'Testas išsaugotas.');
            redirect('admin/testai.php?id=' . $id);
        }
    } elseif ($action === 'question' && $id) {
        $qid = save_question($id, (int) post('qid'), $errors);
        if ($qid) {
            flash('ok', 'Klausimas išsaugotas.');
            redirect('admin/testai.php?id=' . $id . '#q' . $qid);
        }
    } elseif ($action === 'delete_question' && $id) {
        $img = q_value('SELECT image FROM quiz_questions WHERE id = ? AND quiz_id = ?', [(int) post('qid'), $id]);
        if ($img) {
            @unlink(QUIZ_DIR . '/' . basename($img));
            @unlink(QUIZ_DIR . '/thumb_' . basename($img));
        }
        q('DELETE FROM quiz_questions WHERE id = ? AND quiz_id = ?', [(int) post('qid'), $id]);
        flash('ok', 'Klausimas ištrintas.');
        redirect('admin/testai.php?id=' . $id . '#klausimai');
    } elseif ($action === 'delete_quiz' && $id) {
        foreach (q('SELECT image FROM quiz_questions WHERE quiz_id = ? AND image IS NOT NULL', [$id])->fetchAll(PDO::FETCH_COLUMN) as $img) {
            @unlink(QUIZ_DIR . '/' . basename($img));
            @unlink(QUIZ_DIR . '/thumb_' . basename($img));
        }
        q('DELETE FROM quizzes WHERE id = ?', [$id]);
        flash('ok', 'Testas ištrintas.');
        redirect('admin/testai.php');
    }
}

// ---------- Vieno testo redagavimas ----------
if ($id || get('id') === 'new' || get('new')) {
    $quiz = $id ? q_one('SELECT * FROM quizzes WHERE id = ?', [$id]) : null;
    if ($id && !$quiz) {
        not_found();
    }
    $quiz = $quiz ?? ['id' => 0, 'title' => '', 'audience' => 'karateka', 'belt_level' => null, 'description' => '', 'question_count' => 10, 'pass_percent' => 83, 'kids_mode' => 0, 'is_published' => 1];
    $all = [];
    if ($id) {
        foreach (q_all('SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order, id', [$id]) as $row) {
            $row['answers'] = q_all('SELECT * FROM quiz_answers WHERE question_id = ? ORDER BY sort_order, id', [(int) $row['id']]);
            $all[] = $row;
        }
    }
    page_start($id ? 'Testas: ' . $quiz['title'] : 'Naujas testas', ['admin' => true]);
    ?>
    <a class="small" href="<?= url('admin/testai.php') ?>">← Visi testai</a>
    <div class="page-head row between" style="margin-top:8px;">
      <h1 class="styled"><?= $id ? e($quiz['title']) : 'Naujas testas' ?></h1>
      <?php if ($id): ?><a class="btn btn-ghost btn-sm" href="<?= url('testas.php?q=' . $id . '&rezimas=treniruote') ?>">Išbandyti →</a><?php endif; ?>
    </div>
    <?= form_errors($errors) ?>
    <form method="post" class="panel card form">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="quiz">
      <label>Pavadinimas <input type="text" name="title" value="<?= e($quiz['title']) ?>" placeholder="pvz. Teorija geltonam diržui" required></label>
      <div class="form-row three">
        <label>Kam <select name="audience" id="qAudience">
          <?php foreach (QUIZ_AUDIENCE as $k => $label): ?><option value="<?= $k ?>" <?= $quiz['audience'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select></label>
        <label id="qBelt">Kuriam diržui <span class="hint">kurį gaus išlaikęs</span><select name="belt_level"><?= belt_options($quiz['belt_level'] !== null ? (int) $quiz['belt_level'] : null, 'Visiems diržams') ?></select></label>
        <label>Klausimų teste <span class="hint">atsitiktinai iš banko</span><input type="number" name="question_count" min="1" max="100" value="<?= (int) $quiz['question_count'] ?>"></label>
      </div>
      <div class="form-row three">
        <label>Reikia surinkti, % <input type="number" name="pass_percent" min="1" max="100" value="<?= (int) $quiz['pass_percent'] ?>"></label>
        <label class="check" style="align-self:end;"><input type="checkbox" name="kids_mode" value="1" <?= $quiz['kids_mode'] ? 'checked' : '' ?>><span>Vaikams (didesni mygtukai, linksmesni pranešimai)</span></label>
        <label class="check" style="align-self:end;"><input type="checkbox" name="is_published" value="1" <?= $quiz['is_published'] ? 'checked' : '' ?>><span>Paskelbtas (mato nariai)</span></label>
      </div>
      <label>Aprašymas <span class="hint">nebūtina</span><input type="text" name="description" value="<?= e($quiz['description']) ?>"></label>
      <div class="row">
        <button class="btn btn-primary" type="submit">Išsaugoti</button>
        <?php if ($id): ?><button class="btn btn-danger" type="submit" name="action" value="delete_quiz" formnovalidate onclick="return confirm('Ištrinti testą su visais klausimais ir rezultatais?')">Ištrinti testą</button><?php endif; ?>
      </div>
    </form>
    <script>
      (function () { var s = document.getElementById('qAudience'), b = document.getElementById('qBelt');
        function u() { b.style.display = s.value === 'karateka' ? '' : 'none'; } s.addEventListener('change', u); u(); })();
    </script>

    <?php if ($id): ?>
      <h2 id="klausimai" style="margin:28px 0 12px;">Klausimai (<?= count($all) ?>)</h2>
      <?php if (count($all) < (int) $quiz['question_count']): ?>
        <p class="small muted">Banke klausimų mažiau nei teste (<?= (int) $quiz['question_count'] ?>) - kol kas bus užduodami visi esami.</p>
      <?php endif; ?>
      <?php foreach ($all as $i => $qq): $correct = array_values(array_filter($qq['answers'], function ($an) { return $an['is_correct']; })); ?>
        <details class="panel card q-item" id="q<?= (int) $qq['id'] ?>">
          <summary>
            <span class="q-num"><?= $i + 1 ?></span>
            <span class="q-sum"><strong><?= e(mb_strimwidth($qq['body'], 0, 120, '…')) ?></strong>
              <span class="small muted">✓ <?= e($correct ? $correct[0]['body'] : 'nepažymėtas teisingas atsakymas!') ?> · <?= count($qq['answers']) ?> ats.<?= $qq['image'] ? ' · paveikslėlis' : '' ?><?= $qq['youtube_id'] ? ' · video' : '' ?></span></span>
          </summary>
          <?= question_form($id, $qq) ?>
          <form method="post" class="inline-form" onsubmit="return confirm('Ištrinti klausimą?')">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="qid" value="<?= (int) $qq['id'] ?>">
            <button class="linklike small muted" name="action" value="delete_question" style="border:none; padding:0; text-decoration:underline;">ištrinti klausimą</button>
          </form>
        </details>
      <?php endforeach; ?>
      <div class="panel card">
        <h2>Naujas klausimas</h2>
        <?= question_form($id, null) ?>
      </div>
    <?php endif; ?>
    <?php
    page_end();
    exit;
}

// ---------- Sąrašas ----------
$quizzes = q_all('SELECT q.*, (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS qcount,
                         (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = q.id AND qa.finished_at IS NOT NULL) AS attempts,
                         (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = q.id AND qa.passed = 1) AS passes
                    FROM quizzes q ORDER BY FIELD(q.audience, "karateka", "teisejas", "egzaminatorius"), q.belt_level IS NULL, q.belt_level, q.title');
$recent = q_all('SELECT qa.*, q.title, m.first_name, m.last_name FROM quiz_attempts qa JOIN quizzes q ON q.id = qa.quiz_id JOIN members m ON m.id = qa.member_id
                  WHERE qa.finished_at IS NOT NULL ORDER BY qa.finished_at DESC LIMIT 30');

page_start('Testai', ['admin' => true]);
?>
<div class="page-head row between">
  <div>
    <h1 class="styled">Testai</h1>
    <p class="muted" style="margin:0;">Klausimai parenkami atsitiktinai iš banko. Išlaikius - „Leidžiama laikyti praktiką“ (galioja <?= QUIZ_VALID_MONTHS ?> mėn.).</p>
  </div>
  <div class="row" style="gap:8px;">
    <a class="btn btn-ghost" href="<?= url('admin/programa.php') ?>">Egzaminų programa</a>
    <a class="btn btn-primary" href="?new=1">+ Naujas testas</a>
  </div>
</div>
<div class="panel card">
  <?php if (!$quizzes): ?><p class="muted">Testų dar nėra. Sukurkite pirmą - pvz. teorijos klausimai kitam diržui.</p><?php endif; ?>
  <ul class="list">
    <?php foreach ($quizzes as $qz): ?>
      <li class="row between">
        <span>
          <span class="badge <?= $qz['audience'] === 'karateka' ? 'badge-pink' : 'badge-tag' ?>"><?= e(QUIZ_AUDIENCE[$qz['audience']]) ?></span>
          <?= $qz['belt_level'] ? belt_chip((int) $qz['belt_level']) : '' ?>
          <?php if (!$qz['is_published']): ?><span class="badge badge-warn">nepaskelbtas</span><?php endif; ?>
          <a href="?id=<?= (int) $qz['id'] ?>"><strong><?= e($qz['title']) ?></strong></a>
          <div class="muted small"><?= (int) $qz['qcount'] ?> klaus. banke · teste <?= (int) $qz['question_count'] ?> · reikia <?= (int) $qz['pass_percent'] ?> % · laikė <?= (int) $qz['attempts'] ?>, išlaikė <?= (int) $qz['passes'] ?></div>
        </span>
        <a class="btn btn-ghost btn-sm" href="?id=<?= (int) $qz['id'] ?>">Keisti</a>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<div class="panel card">
  <h2>Paskutiniai rezultatai</h2>
  <?php if (!$recent): ?><p class="muted">Dar niekas nelaikė.</p><?php endif; ?>
  <ul class="list small">
    <?php foreach ($recent as $r): ?>
      <li class="row between">
        <span><a href="<?= url('admin/nariai.php?id=' . (int) $r['member_id']) ?>"><strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong></a> · <?= e($r['title']) ?>
          <div class="muted"><?= e(fmt_date($r['finished_at'], true)) ?>, <?= e(substr($r['finished_at'], 11, 5)) ?></div></span>
        <a class="badge <?= $r['passed'] ? 'badge-ok' : 'badge-warn' ?>" href="<?= url('testas.php?rezultatas=' . (int) $r['id']) ?>"><?= attempt_percent($r) ?> % <?= $r['passed'] ? '✓' : '✕' ?></a>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php
page_end();
