<?php
// Testai: klausimų bankas (klausimas, 2-4 atsakymai, paveikslėlis ar video), nustatymai ir rezultatai
require dirname(__DIR__) . '/app/bootstrap.php';
require APP_DIR . '/images.php';
$me = require_can('quizzes');

const QUIZ_DIR = PUBLIC_DIR . '/uploads/quiz';

$id = (int) (get('id') ?: post('id'));

// #137: atsisiųsti visą klausimyną su atsakymais (tekstas, kurį galima pataisyti ir įkelti atgal)
if ($id && get('eksportas') === '1') {
    $qz = q_one('SELECT title FROM quizzes WHERE id = ?', [$id]);
    if ($qz) {
        $fn = preg_replace('/[^\w\-]+/u', '-', mb_strtolower(trim((string) $qz['title']))) ?: 'testas';
        header('Content-Type: text/plain; charset=utf-8');
        $fn = trim($fn, '-') . '-klausimai.txt';
        header('Content-Disposition: attachment; filename="testas-' . $id . '-klausimai.txt"; filename*=UTF-8\'\'' . rawurlencode($fn));
        echo "\u{FEFF}" . quiz_export_text($id);
        exit;
    }
}
$errors = [];
$failed = null;        // klausimas, kurio nepavyko išsaugoti (rodomas su įvestais duomenimis)
$importItems = null;   // įkeliamų klausimų peržiūra
$importText = '';
$importImages = ['by_name' => [], 'by_num' => []];

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
        $errors[] = t('Įveskite klausimą.');
    }
    if (count($answers) < 2) {
        $errors[] = t('Reikia bent 2 atsakymų.');
    } elseif (!array_filter(array_column($answers, 'correct'))) {
        $errors[] = t('Pažymėkite teisingą atsakymą.');
    }
    if (post('youtube') !== '' && !$yt) {
        $errors[] = t('Neatpažinta YouTube nuoroda.');
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
    $topic = mb_substr(post('topic'), 0, 190) ?: null;
    if ($qid) {
        q('UPDATE quiz_questions SET body = ?, topic = ?, explanation = ?, youtube_id = ? WHERE id = ? AND quiz_id = ?', [$body, $topic, post('explanation') ?: null, $yt, $qid, $quizId]);
    } else {
        $next = (int) q_value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM quiz_questions WHERE quiz_id = ?', [$quizId]);
        q('INSERT INTO quiz_questions (quiz_id, topic, body, explanation, youtube_id, sort_order) VALUES (?, ?, ?, ?, ?, ?)', [$quizId, $topic, $body, post('explanation') ?: null, $yt, $next]);
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

/** Po klaidos - forma su tuo, ką žmogus jau įvedė (kad niekas neišsitrintų) */
function question_from_post(): array
{
    $answers = [];
    for ($i = 0; $i < 4; $i++) {
        $answers[] = ['id' => (int) ($_POST['ans_id'][$i] ?? 0), 'body' => (string) ($_POST['ans_body'][$i] ?? ''), 'is_correct' => (string) $i === post('correct') ? 1 : 0];
    }
    $yt = post('youtube');
    return ['id' => (int) post('qid'), 'body' => post('body'), 'answers' => $answers, 'explanation' => post('explanation'), 'topic' => post('topic'),
            'youtube_id' => '', 'youtube_raw' => $yt, 'image' => post('qid') ? q_value('SELECT image FROM quiz_questions WHERE id = ?', [(int) post('qid')]) : null];
}

/**
 * Įkelti failai (laukai import_files[] ar import_file): klausimų tekstas (.txt, .csv, .json), paveikslėliai ir/ar ZIP.
 * Grąžina [tekstas, paveikslėliai (quiz_import_images), klaidos, ar buvo paveikslėlių / ZIP].
 */
function quiz_collect_upload(string $text): array
{
    $upl = $errors = [];
    foreach (['import_file', 'import_files'] as $fk) {
        $f = $_FILES[$fk] ?? null;
        if (!$f) {
            continue;
        }
        foreach ((array) $f['name'] as $k => $nm) {
            $tmp = is_array($f['tmp_name']) ? $f['tmp_name'][$k] : $f['tmp_name'];
            if ($nm !== '' && $tmp && is_uploaded_file($tmp)) {
                $upl[] = ['name' => (string) $nm, 'tmp_name' => $tmp, 'size' => (int) (is_array($f['size']) ? $f['size'][$k] : $f['size'])];
            }
        }
    }
    $textFiles = array_values(array_filter($upl, function ($f) { return preg_match('/\.(txt|csv|json|md)$/i', $f['name']); }));
    $mediaFiles = array_values(array_filter($upl, function ($f) { return preg_match('/\.(zip|png|jpe?g|webp)$/i', $f['name']); }));
    if ($textFiles) {
        if ($textFiles[0]['size'] > 2 * 1024 * 1024) {
            $errors[] = t('Failas per didelis (iki 2 MB).');
        } else {
            $text = (string) file_get_contents($textFiles[0]['tmp_name']);
        }
    }
    $images = ['by_name' => [], 'by_num' => []];
    if ($mediaFiles) {
        $got = quiz_import_images($mediaFiles, QUIZ_DIR);
        $images = ['by_name' => $got['by_name'], 'by_num' => $got['by_num']];
        if (!$textFiles && $got['text'] !== '') {
            $text = $got['text'];   // klausimai.txt ZIP viduje
        }
        $errors = array_merge($errors, $got['errors']);
    }
    if ($text !== '' && !mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1257');   // Excel CSV lietuviškai
    }
    return [$text, $images, $errors, (bool) $mediaFiles];
}

/** Klausimo forma (naujam - tuščia); $errs - klaidos rodomos pačioje formoje */
function question_form(int $quizId, ?array $q, array $errs = []): string
{
    $answers = $q['answers'] ?? [];
    while (count($answers) < 4) {
        $answers[] = ['id' => 0, 'body' => '', 'is_correct' => 0];
    }
    $qid = (int) ($q['id'] ?? 0);
    $yt = isset($q['youtube_raw']) ? (string) $q['youtube_raw'] : (!empty($q['youtube_id']) ? 'https://youtu.be/' . $q['youtube_id'] : '');
    $hasMore = trim((string) ($q['explanation'] ?? '')) !== '' || trim((string) ($q['topic'] ?? '')) !== '' || $yt !== '' || !empty($q['image']);
    $html = '<form method="post" enctype="multipart/form-data" class="form q-form qf" novalidate>' . csrf_field() . ($errs ? form_errors($errs) : '')
        . '<p class="q-err flash flash-err" hidden></p>'
        . '<input type="hidden" name="id" value="' . $quizId . '"><input type="hidden" name="action" value="question"><input type="hidden" name="qid" value="' . $qid . '">'
        . '<label class="qf-field"><span class="qf-label">' . te('Klausimas') . '</span>'
        . '<textarea name="body" rows="2" required class="qf-question" placeholder="' . te('pvz. Kaip japoniškai „vienas“?') . '">' . e($q['body'] ?? '') . '</textarea></label>'
        . '<div class="q-answers-edit qf-answers" role="radiogroup" aria-label="' . te('Atsakymai') . '">'
        . '<span class="qf-label">' . te('Atsakymai') . ' <span class="hint">' . te('paspauskite raidę prie teisingo; užtenka 2') . '</span></span>';
    foreach (array_slice($answers, 0, 4) as $i => $an) {
        $L = chr(65 + $i);
        $html .= '<div class="ans-row qf-ans">'
            . '<label class="qf-ok" title="' . te('Teisingas atsakymas') . '"><input type="radio" name="correct" value="' . $i . '" ' . ($an['is_correct'] ? 'checked' : '') . ' aria-label="' . e(t('Teisingas') . ': ' . $L) . '">'
            . '<span class="qf-letter" aria-hidden="true">' . $L . '</span><span class="qf-tick" aria-hidden="true">' . icon('check') . '</span></label>'
            . '<input type="hidden" name="ans_id[' . $i . ']" value="' . (int) $an['id'] . '">'
            . '<input type="text" name="ans_body[' . $i . ']" value="' . e($an['body']) . '" placeholder="' . e(t('Atsakymas') . ' ' . $L) . '" enterkeyhint="next" maxlength="255">'
            . '<span class="qf-ok-label">' . te('teisingas') . '</span></div>';
    }
    $html .= '</div>'
        . '<details class="qf-more"' . ($hasMore ? ' open' : '') . '><summary>' . icon('plus') . ' ' . te('Paaiškinimas, tema, paveikslėlis, video') . '</summary><div class="qf-more-grid">'
        . '<label class="qf-field"><span class="qf-label">' . icon('bulb') . ' ' . te('Paaiškinimas') . '</span><input type="text" name="explanation" value="' . e($q['explanation'] ?? '') . '" placeholder="' . te('rodomas po atsakymo') . '"></label>'
        . '<label class="qf-field"><span class="qf-label">' . icon('tag') . ' ' . te('Tema') . '</span><input type="text" name="topic" value="' . e($q['topic'] ?? '') . '" maxlength="190" list="quizTopics" placeholder="' . te('tik to paties klausimo kitoms formuluotėms') . '"></label>'
        . '<label class="qf-field"><span class="qf-label">' . icon('video') . ' YouTube</span><input type="text" name="youtube" value="' . e($yt) . '" placeholder="https://youtu.be/…" inputmode="url"></label>'
        . '<div class="qf-field"><span class="qf-label">' . icon('image') . ' ' . te('Paveikslėlis') . '</span>';
    if (!empty($q['image'])) {
        $html .= '<div class="qf-img"><img src="' . e(quiz_image_url($q['image'])) . '" alt=""><label class="check"><input type="checkbox" name="remove_image" value="1"><span>' . te('Pašalinti') . '</span></label></div>';
    }
    $html .= file_drop('image', 'image/jpeg,image/png,image/webp', !empty($q['image']) ? t('Pakeisti paveikslėlį') : t('Pasirinkite paveikslėlį'), t('JPG, PNG ar WEBP - arba nutempkite čia')) . '</div>'
        . '</div></details>'
        . '<div class="qf-foot"><button class="btn btn-primary" type="submit">' . icon($qid ? 'check' : 'plus') . ' ' . ($qid ? te('Išsaugoti klausimą') : te('Pridėti klausimą')) . '</button>'
        . ($qid ? '<button class="icon-btn danger" type="submit" form="delq' . $qid . '" title="' . te('Ištrinti klausimą') . '">' . icon('trash', t('Ištrinti klausimą')) . '</button>' : '')
        . '</div></form>';
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
            'question_count' => !empty($_POST['all_questions']) ? 0 : max(1, min(255, (int) post('question_count'))),   // 0 - visi banko klausimai
            'pass_percent' => max(1, min(100, (int) post('pass_percent'))),
            'time_limit' => max(0, min(180, (int) post('time_limit'))),
            'kids_mode' => !empty($_POST['kids_mode']) ? 1 : 0,
            'is_published' => !empty($_POST['is_published']) ? 1 : 0,
        ];
        if ($data['audience'] !== 'karateka') {
            $data['belt_level'] = null;
        }
        if ($data['title'] === '') {
            $errors[] = t('Įveskite pavadinimą.');
        } else {
            if ($id) {
                q('UPDATE quizzes SET title = ?, audience = ?, belt_level = ?, description = ?, question_count = ?, pass_percent = ?, time_limit = ?, kids_mode = ?, is_published = ?, updated_at = NOW() WHERE id = ?',
                    array_merge(array_values($data), [$id]));
            } else {
                q('INSERT INTO quizzes (title, audience, belt_level, description, question_count, pass_percent, time_limit, kids_mode, is_published) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', array_values($data));
                $id = (int) db()->lastInsertId();
                // Kartu su nauju testu - klausimų failas (įkeliami peržiūrai: nariams nerodomi, kol nepatvirtinsite)
                [$qText, $qImages, $qErrors] = quiz_collect_upload('');
                if ($qText !== '') {
                    // Denis: įkėlus failą testas turi veikti iš karto; peržiūra pirmiau - tik pažymėjus
                    $review = !empty($_POST['review_first']);
                    $items = array_slice(quiz_import_parse($qText), 0, 1000);
                    $bad = count(array_filter($items, function ($x) { return $x['error']; }));
                    $n = quiz_import_save($id, $items, $upd, $review, $qImages);
                    $tail = ($bad ? ' ' . t('Praleista dėl klaidų: {n}.', ['n' => $bad]) : '') . ($qErrors ? ' ' . implode(' ', $qErrors) : '');
                    if ($review) {
                        flash('ok', t('Testas sukurtas. Įkelta peržiūrai: {n}.', ['n' => $n]) . $tail);
                        redirect('admin/testo-perziura.php?id=' . $id);
                    }
                    flash('ok', t('Testas sukurtas. Įkelta klausimų: {n} - testą galima laikyti iš karto.', ['n' => $n]) . $tail);
                    redirect('admin/testai.php?id=' . $id . '#klausimai');
                }
                if ($qErrors) {
                    flash('err', implode(' ', $qErrors));
                }
            }
            flash('ok', t('Testas išsaugotas.'));
            redirect('admin/testai.php?id=' . $id);
        }
    } elseif ($action === 'question' && $id) {
        $qErrors = [];
        $qid = save_question($id, (int) post('qid'), $qErrors);
        if ($qid) {
            flash('ok', t('Klausimas išsaugotas.'));
            redirect('admin/testai.php?id=' . $id . '#q' . $qid);
        }
        $failed = ['qid' => (int) post('qid'), 'q' => question_from_post(), 'errors' => $qErrors];   // forma rodoma su įvestais duomenimis
    } elseif ($action === 'share' && $id) {
        $qz = q_one('SELECT * FROM quizzes WHERE id = ?', [$id]);
        if ($qz) {
            quiz_share_token($qz);
            q('UPDATE quizzes SET share_open = ? WHERE id = ?', [!empty($_POST['share_open']) ? 1 : 0, $id]);
            flash('ok', !empty($_POST['share_open']) ? t('Nuoroda įjungta - ją galima siųsti bet kam.') : t('Nuoroda išjungta.'));
        }
        redirect('admin/testai.php?id=' . $id . '#nuoroda');
    } elseif (($action === 'import_preview' || $action === 'import_do') && $id) {
        [$importText, $importImages, $upErrors, $hadMedia] = quiz_collect_upload((string) ($_POST['import_text'] ?? ''));
        $errors = array_merge($errors, $upErrors);
        if (!$hadMedia && post('import_images') !== '') {
            // Peržiūros metu jau išsaugoti paveikslėliai (tik mūsų sugeneruoti vardai)
            $raw = json_decode((string) post('import_images'), true);
            foreach (['by_name', 'by_num'] as $part) {
                foreach ((array) ($raw[$part] ?? []) as $k => $f) {
                    if (is_string($f) && preg_match('/^[a-f0-9]{24}\.jpg$/', $f) && is_file(QUIZ_DIR . '/' . $f)) {
                        $importImages[$part][$part === 'by_num' ? (int) $k : mb_substr((string) $k, 0, 120)] = $f;
                    }
                }
            }
        }
        $importItems = array_slice(quiz_import_parse($importText), 0, 1000);
        if (!$importItems) {
            $errors[] = t('Neradau klausimų. Patikrinkite formatą (pavyzdys - žemiau).');
        }
        if ($action === 'import_do' && $importItems) {
            $draft = post('draft') === '1';
            $n = quiz_import_save($id, $importItems, $upd, $draft, $importImages);
            if ($draft) {
                flash('ok', t('Įkelta peržiūrai: {n}. Nariams jie dar nerodomi - peržiūrėkite ir patvirtinkite.', ['n' => $n]) . ($upd ? ' ' . t('Pataisyta: {n}.', ['n' => $upd]) : ''));
                redirect('admin/testo-perziura.php?id=' . $id);
            }
            flash('ok', t('Įkelta klausimų: {n}. Juos galite taisyti ar ištrinti žemiau.', ['n' => $n]) . ($upd ? ' ' . t('Pataisyta: {n}.', ['n' => $upd]) : ''));
            redirect('admin/testai.php?id=' . $id . '#klausimai');
        }
    } elseif ($action === 'delete_questions' && $id) {
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['qids'] ?? []))));
        if ($ids) {
            $in = implode(',', $ids);
            foreach (q("SELECT image FROM quiz_questions WHERE quiz_id = ? AND id IN ($in) AND image IS NOT NULL", [$id])->fetchAll(PDO::FETCH_COLUMN) as $img) {
                @unlink(QUIZ_DIR . '/' . basename($img));
                @unlink(QUIZ_DIR . '/thumb_' . basename($img));
            }
            q("DELETE FROM quiz_questions WHERE quiz_id = ? AND id IN ($in)", [$id]);
            flash('ok', t('Ištrinta klausimų: {n}.', ['n' => count($ids)]));
        }
        redirect('admin/testai.php?id=' . $id . '#klausimai');
    } elseif ($action === 'delete_question' && $id) {
        $img = q_value('SELECT image FROM quiz_questions WHERE id = ? AND quiz_id = ?', [(int) post('qid'), $id]);
        if ($img) {
            @unlink(QUIZ_DIR . '/' . basename($img));
            @unlink(QUIZ_DIR . '/thumb_' . basename($img));
        }
        q('DELETE FROM quiz_questions WHERE id = ? AND quiz_id = ?', [(int) post('qid'), $id]);
        flash('ok', t('Klausimas ištrintas.'));
        redirect('admin/testai.php?id=' . $id . '#klausimai');
    } elseif ($action === 'delete_quiz' && $id) {
        foreach (q('SELECT image FROM quiz_questions WHERE quiz_id = ? AND image IS NOT NULL', [$id])->fetchAll(PDO::FETCH_COLUMN) as $img) {
            @unlink(QUIZ_DIR . '/' . basename($img));
            @unlink(QUIZ_DIR . '/thumb_' . basename($img));
        }
        q('DELETE FROM quizzes WHERE id = ?', [$id]);
        flash('ok', t('Testas ištrintas.'));
        redirect('admin/testai.php');
    }
}

// ---------- Vieno testo redagavimas ----------
if ($id || get('id') === 'new' || get('new')) {
    $quiz = $id ? q_one('SELECT * FROM quizzes WHERE id = ?', [$id]) : null;
    if ($id && !$quiz) {
        not_found();
    }
    $quiz = $quiz ?? ['id' => 0, 'title' => '', 'audience' => 'karateka', 'belt_level' => null, 'description' => '', 'question_count' => 10, 'pass_percent' => 83, 'time_limit' => 0, 'kids_mode' => 0, 'is_published' => 1];
    $all = [];
    if ($id) {
        foreach (q_all('SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order, id', [$id]) as $row) {
            $row['answers'] = q_all('SELECT * FROM quiz_answers WHERE question_id = ? ORDER BY sort_order, id', [(int) $row['id']]);
            $all[] = $row;
        }
    }
    $drafts = count(array_filter($all, function ($r) { return (int) ($r['is_draft'] ?? 0) === 1; }));
    $distinct = quiz_distinct_count(array_filter($all, function ($r) { return (int) ($r['is_draft'] ?? 0) === 0; }));   // skirtingų klausimų (to paties klausimo formuluotės - vienas)
    page_start($id ? (t('Testas:') . ' ') . $quiz['title'] : t('Naujas testas'), ['admin' => true]);
    ?>
    <a class="small" href="<?= url('admin/testai.php') ?>"><?= te('← Visi testai') ?></a>
    <div class="page-head row between" style="margin-top:8px;">
      <h1 class="styled"><?= $id ? e($quiz['title']) : t('Naujas testas') ?></h1>
      <?php if ($id): ?><div class="row" style="gap:8px;">
        <a class="btn btn-sm<?= $drafts ? ' btn-primary' : ' btn-ghost' ?>" href="<?= url('admin/testo-perziura.php?id=' . $id) ?>"><?= icon('search') ?> <span><?= te('Peržiūrėti klausimus') ?><?= $drafts ? ' (' . $drafts . ')' : '' ?></span></a>
        <a class="btn btn-ghost btn-sm" href="<?= url('admin/testai.php?id=' . $id . '&eksportas=1') ?>" download title="<?= te('Visi klausimai su atsakymais ir [#numeriais] - pataisyti ir įkelti atgal') ?>"><?= icon('download') ?> <span><?= te('Atsisiųsti klausimus') ?></span></a>
        <a class="btn btn-ghost btn-sm" href="<?= url('testas.php?q=' . $id . '&rezimas=treniruote') ?>"><?= icon('eye') ?> <span><?= te('Išbandyti') ?></span></a>
      </div><?php endif; ?>
    </div>
    <?php if ($id && $drafts): ?>
      <p class="flash flash-info"><?= te('Peržiūros laukia klausimų:') ?> <strong><?= $drafts ?></strong>. <?= te('Nariams jie dar nerodomi.') ?> <a href="<?= url('admin/testo-perziura.php?id=' . $id) ?>"><?= te('Peržiūrėti →') ?></a></p>
    <?php endif; ?>
    <?= $importItems === null ? form_errors($errors) : '' ?>
    <form method="post" class="panel card form" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="quiz">
      <label><?= te('Pavadinimas') ?> <input type="text" name="title" value="<?= e($quiz['title']) ?>" placeholder="<?= te('pvz. Teorija geltonam diržui') ?>" required></label>
      <div class="form-row three">
        <label><?= te('Kam') ?> <select name="audience" id="qAudience">
          <?php foreach (array_map('t', QUIZ_AUDIENCE) as $k => $label): ?><option value="<?= $k ?>" <?= $quiz['audience'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select></label>
        <label id="qBelt"><?= te('Kuriam diržui') ?> <span class="hint"><?= te('kurį gaus išlaikęs') ?></span><select name="belt_level"><?= belt_options($quiz['belt_level'] !== null ? (int) $quiz['belt_level'] : null, t('Visiems diržams')) ?></select></label>
        <label><?= te('Klausimų teste') ?> <span class="hint"><?= te('atsitiktinai iš banko') ?><?= $id ? (' ' . t('(skirtingų:') . ' ') . $distinct . ')' : '' ?></span><input type="number" name="question_count" id="qCount" min="1" max="255" value="<?= (int) $quiz['question_count'] ?: max(1, (int) ($distinct ?? 10)) ?>"<?= (int) $quiz['question_count'] === 0 ? ' disabled' : '' ?>>
          <span class="check small"><input type="checkbox" name="all_questions" value="1" id="qAll"<?= (int) $quiz['question_count'] === 0 ? ' checked' : '' ?>> <span><?= te('Visi banko klausimai') ?></span></span></label>
      </div>
      <div class="form-row three">
        <label><?= te('Reikia surinkti, %') ?> <span class="hint" id="qNeed"></span><input type="number" name="pass_percent" id="qPercent" min="1" max="100" value="<?= (int) $quiz['pass_percent'] ?>"></label>
        <label><?= te('Laikas, min.') ?> <span class="hint"><?= te('0 - neribota; pasibaigus testas išsiunčiamas') ?></span><input type="number" name="time_limit" min="0" max="180" value="<?= (int) ($quiz['time_limit'] ?? 0) ?>"></label>
        <div class="stack" style="align-self:end;">
          <label class="check"><input type="checkbox" name="kids_mode" value="1" <?= $quiz['kids_mode'] ? 'checked' : '' ?>><span><?= te('Vaikams (didesni mygtukai, linksmesni pranešimai)') ?></span></label>
          <label class="check"><input type="checkbox" name="is_published" value="1" <?= $quiz['is_published'] ? 'checked' : '' ?>><span><?= te('Paskelbtas (mato nariai)') ?></span></label>
        </div>
      </div>
      <label><?= te('Aprašymas') ?> <span class="hint"><?= te('nebūtina') ?></span><input type="text" name="description" value="<?= e($quiz['description']) ?>"></label>
      <?php if (!$id): ?>
        <div class="qf-field">
          <span class="qf-label"><?= te('Klausimai') ?> <span class="hint"><?= te('nebūtina - galima ir vėliau') ?></span></span>
          <?= file_drop('import_files[]', '.txt,.csv,.json,.md,.zip,.png,.jpg,.jpeg,.webp,text/plain,text/csv,application/json,application/zip,image/*', t('Įkelti klausimų failą'), t('pvz. iš ChatGPT: .txt, .csv, .json, paveikslėliai ar vienas ZIP - galima nutempti čia'), true) ?>
          <label class="check small"><input type="checkbox" name="review_first" value="1"><span><?= te('Pirmiau peržiūrėti klausimus (nariams nerodomi, kol patvirtinsiu)') ?></span></label>
        </div>
      <?php endif; ?>
      <div class="row">
        <button class="btn btn-primary" type="submit"><?= te('Išsaugoti') ?></button>
        <?php if ($id): ?><button class="btn btn-danger" type="submit" name="action" value="delete_quiz" formnovalidate onclick="return confirm('Ištrinti testą su visais klausimais ir rezultatais?')"><?= te('Ištrinti testą') ?></button><?php endif; ?>
      </div>
    </form>
    <script>
      (function () { var s = document.getElementById('qAudience'), b = document.getElementById('qBelt');
        function u() { b.style.display = s.value === 'karateka' ? '' : 'none'; } s.addEventListener('change', u); u(); })();
      // "= 40 iš 50": kiek teisingų atsakymų reikia
      (function () { var c = document.getElementById('qCount'), p = document.getElementById('qPercent'), max = <?= $id ? $distinct : 100 ?>;
        var all = document.getElementById('qAll');
        if (all) all.addEventListener('change', function () { c.disabled = all.checked; u(); });
        function u() { var n = all && all.checked ? max : Math.min(parseInt(c.value, 10) || 0, max || 0), pct = parseInt(p.value, 10) || 0;
          document.getElementById('qNeed').textContent = n && pct ? '= ' + Math.ceil(n * pct / 100) + ' teisingų iš ' + n : ''; }
        c.addEventListener('input', u); p.addEventListener('input', u); u(); })();
    </script>

    <?php if ($id): $shareUrl = abs_url('testas-svecias.php?k=' . quiz_share_token($quiz)); ?>
      <div class="panel card" id="nuoroda">
        <h2><?= te('Nuoroda be paskyros') ?></h2>
        <p class="small muted" style="margin-top:0;"><?= te('Testą gali išspręsti bet kas, net be paskyros: įrašo vardą ir iš karto mato rezultatą su teisingais atsakymais. Rezultatus matote čia.') ?></p>
        <form method="post" class="row" style="gap:10px; align-items:center;">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="share">
          <label class="check"><input type="checkbox" name="share_open" value="1" <?= !empty($quiz['share_open']) ? 'checked' : '' ?> onchange="this.form.submit()"><span><?= te('Leisti spręsti per nuorodą') ?></span></label>
          <noscript><button class="btn btn-ghost btn-sm" type="submit"><?= te('Išsaugoti') ?></button></noscript>
        </form>
        <?php if (!empty($quiz['share_open'])): ?>
          <div class="row share-row" style="gap:8px; margin-top:8px;">
            <input type="text" readonly value="<?= e($shareUrl) ?>" id="quizShareUrl" aria-label="<?= te('Nuoroda') ?>" onclick="this.select()">
            <button class="btn btn-ghost btn-sm" type="button" onclick="var i=document.getElementById('quizShareUrl'); i.select(); try { navigator.clipboard.writeText(i.value); } catch (e) { document.execCommand('copy'); } this.textContent='✓ Nukopijuota';"><?= te('Kopijuoti') ?></button>
            <a class="btn btn-ghost btn-sm" href="<?= e($shareUrl) ?>" target="_blank" rel="noopener"><?= te('Atidaryti ↗') ?></a>
          </div>
        <?php endif; ?>
        <?php $guests = q_all('SELECT * FROM quiz_guest_attempts WHERE quiz_id = ? ORDER BY created_at DESC LIMIT 100', [$id]); ?>
        <?php if ($guests): ?>
          <h3 style="margin:16px 0 6px;"><?= te('Sprendė per nuorodą') ?> (<?= count($guests) ?>)</h3>
          <ul class="list small">
            <?php foreach ($guests as $g): $gp = $g['total'] ? (int) round(100 * $g['score'] / $g['total']) : 0; ?>
              <li class="row between"><span><strong><?= e($g['name']) ?></strong><?= $g['club'] ? ' · ' . e($g['club']) : '' ?><?= !empty($g['email']) ? ' · <a href="mailto:' . e($g['email']) . '">' . e($g['email']) . '</a>' : '' ?>
                <span class="muted"> · <?= e(fmt_date($g['created_at'], true)) ?>, <?= e(substr($g['created_at'], 11, 5)) ?></span></span>
                <span class="badge <?= $g['passed'] ? 'badge-ok' : 'badge-warn' ?>"><?= $gp ?> % <?= $g['passed'] ? '✓' : '✕' ?></span></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <details class="panel card import-box" id="ikelti"<?= $importItems !== null ? ' open' : '' ?>>
        <summary class="ib-head"><span class="ib-ico"><?= icon('upload') ?></span><span class="ib-title"><strong><?= te('Įkelti arba atsisiųsti klausimus (failas)') ?></strong>
          <span class="hint"><?= te('įkelti: .txt, .csv, .json, paveikslėliai ar vienas ZIP (pvz. iš ChatGPT); atsisiųsti - visi klausimai su atsakymais') ?></span></span><span class="ib-chev"><?= icon('chevron') ?></span></summary>
        <?= $importItems !== null ? form_errors($errors) : '' ?>
        <?php if ($importItems): $okCount = count(array_filter($importItems, function ($x) { return !$x['error']; })); ?>
          <h3 class="ib-sub"><?= te('Peržiūra') ?>: <?= $okCount ?> <?= te('tinkamų') ?><?= count($importItems) - $okCount ? ', ' . (count($importItems) - $okCount) . ' ' . t('su klaidomis (nebus įkelti)') : '' ?></h3>
          <div class="table-scroll import-preview"><table class="table small">
            <thead><tr><th>#</th><th><?= te('Klausimas') ?></th><th><?= te('Atsakymai') ?></th><th><?= icon('image', t('Paveikslėlis')) ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($importItems as $k => $it): ?>
              <tr class="<?= $it['error'] ? 'warn-row' : '' ?>"><td><?= $k + 1 ?></td><td><?= !empty($it['qid']) ? '<span class="badge badge-tag">' . te('pataisomas') . ' #' . (int) $it['qid'] . '</span> ' : '' ?><?= e($it['body']) ?><?= $it['topic'] ? '<div class="muted">' . te('tema:') . ' ' . e($it['topic']) . '</div>' : '' ?></td>
                <td><?php foreach ($it['answers'] as $ai => $an): ?><div<?= $ai === $it['correct'] ? ' class="imp-ok"' : '' ?>><?= $ai === $it['correct'] ? '✓ ' : '' ?><?= e($an) ?></div><?php endforeach; ?></td>
                <td><?php [$pimg, $pnote] = quiz_import_pick_image($it, $importImages);
                  if ($pimg): ?><img class="imp-thumb" src="<?= e(url('uploads/quiz/thumb_' . $pimg)) ?>" alt=""><?php
                  elseif ($it['image_ref'] !== '' && $pnote === ''): ?><span class="badge badge-warn"><?= e($it['image_ref']) ?> <?= te('nerastas') ?></span><?php
                  elseif ($pnote !== ''): ?><span class="small muted" title="<?= e($pnote) ?>"><?= te('reikės:') ?> <?= e(mb_strimwidth($pnote, 0, 60, '…')) ?></span><?php endif; ?></td>
                <td><?= $it['error'] ? '<span class="badge badge-warn">' . e($it['error']) . '</span>' : '<span class="badge badge-ok">OK</span>' ?></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
          <form method="post" class="ib-actions">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="import_do">
            <textarea name="import_text" hidden><?= e($importText) ?></textarea>
            <input type="hidden" name="import_images" value="<?= e(json_encode($importImages)) ?>">
            <button class="btn btn-primary" type="submit" name="draft" value="1"<?= $okCount ? '' : ' disabled' ?>><?= icon('search') ?> <?= te('Įkelti peržiūrai') ?> (<?= $okCount ?>)</button>
            <button class="btn btn-ghost" type="submit" name="draft" value="0"<?= $okCount ? '' : ' disabled' ?>><?= icon('upload') ?> <?= te('Įkelti iš karto į testą') ?></button>
            <p class="hint"><?= te('„Peržiūrai“ - nariams nerodomi, kol nepatvirtinsite. Jau esantys (tokiu pačiu tekstu) praleidžiami. Su [#numeriu] - pataisomi vietoje.') ?></p>
          </form>
        <?php endif; ?>
        <div class="ib-grid">
          <form method="post" enctype="multipart/form-data" class="form ib-main">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="import_preview">
            <?= file_drop('import_files[]', '.txt,.csv,.json,.md,.zip,.png,.jpg,.jpeg,.webp,text/plain,text/csv,application/json,application/zip,image/*', t('Pasirinkite failus'), t('klausimai (.txt, .csv, .json) ir paveikslėliai arba vienas ZIP - galima nutempti čia'), true) ?>
            <label class="qf-field"><span class="qf-label"><?= te('arba įklijuokite tekstą') ?></span>
              <textarea name="import_text" rows="4" placeholder="1. Klausimas?&#10;A) ...&#10;B) ...&#10;Atsakymas: B"><?= e($importItems === null ? '' : $importText) ?></textarea></label>
            <div><button class="btn btn-primary" type="submit"><?= te('Peržiūrėti') ?> <?= icon('arrow-right') ?></button></div>
          </form>
          <aside class="ib-side">
            <h3 class="ib-sub"><?= te('Pataisyti esamus') ?></h3>
            <a class="btn btn-ghost ib-dl" href="<?= url('admin/testai.php?id=' . $id . '&eksportas=1') ?>" download><?= icon('download') ?> <?= te('Atsisiųsti visus klausimus') ?></a>
            <p class="hint"><?= te('Failas su atsakymais ir [#numeriais]. Pataisykite (pvz. su ChatGPT) ir įkelkite atgal - tie klausimai bus pataisyti vietoje.') ?></p>
            <h3 class="ib-sub"><?= icon('sparkle') ?> ChatGPT</h3>
            <details class="ib-help" id="chatgpt-projektas"><summary><?= te('Nustatymas vieną kartą (kad visada ruoštų tinkamu formatu)') ?></summary>
              <ol class="small">
                <li><?= te('ChatGPT kairėje: „Projects“ → „New project“ → pavadinkite „Karateka testai“.') ?></li>
                <li><?= te('Projekte: „Instructions“ (Instrukcijos) → įklijuokite žemiau esantį tekstą → „Save“.') ?></li>
                <li><?= te('Visus testų pokalbius pradėkite šiame projekte - formato kartoti nereikės.') ?></li>
              </ol>
              <pre id="projectPrompt"><?= e(quiz_chatgpt_project_text()) ?></pre>
              <button class="btn btn-ghost btn-sm" type="button" data-copy="projectPrompt"><?= icon('copy') ?> <span><?= te('Kopijuoti nurodymus') ?></span></button>
            </details>
            <details class="ib-help"><summary><?= te('Prašymas naujiems klausimams') ?></summary>
              <p class="small"><?= te('Įkelkite ChatGPT taisykles (failą ar tekstą) ir nukopijuokite šį prašymą.') ?></p>
              <pre id="importPrompt"><?= e(quiz_import_prompt()) ?></pre>
              <button class="btn btn-ghost btn-sm" type="button" data-copy="importPrompt"><?= icon('copy') ?> <span><?= te('Kopijuoti prašymą') ?></span></button>
              <p class="hint"><?= te('Tinka ir Excel CSV: klausimas; A; B; C; D; teisingas (raidė); paaiškinimas; tema.') ?></p>
            </details>
            <details class="ib-help fix-prompt"><summary><?= te('Prašymas pataisyti atsisiųstus') ?></summary>
              <pre id="fixPrompt"><?= e(quiz_fix_prompt()) ?></pre>
              <button class="btn btn-ghost btn-sm" type="button" data-copy="fixPrompt"><?= icon('copy') ?> <span><?= te('Kopijuoti prašymą') ?></span></button>
            </details>
          </aside>
        </div>
      </details>

      <h2 id="klausimai" style="margin:28px 0 12px;"><?= te('Klausimai (') ?><?= count($all) ?>)</h2>
      <?php if ((int) $quiz['question_count'] > 0 && $distinct < (int) $quiz['question_count']): ?>
        <p class="small muted"><?= te('Banke skirtingų klausimų (') ?><?= $distinct ?><?= te(') mažiau nei teste (') ?><?= (int) $quiz['question_count'] ?><?= te(') - kol kas bus užduodami visi esami.') ?></p>
      <?php endif; ?>
      <datalist id="quizTopics"><?php foreach (array_unique(array_filter(array_column($all, 'topic'))) as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist>
      <?php if ($all): ?>
        <form method="post" id="bulkDel" class="row bulk-bar" onsubmit="var n = document.querySelectorAll('.q-pick input:checked').length; if (!n) { alert('Pažymėkite klausimus, kuriuos ištrinti.'); return false; } return confirm('Ištrinti pažymėtus klausimus (' + n + ')?');">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="delete_questions">
          <label class="check bulk-all"><input type="checkbox" id="pickAll"><span><?= te('Pažymėti visus') ?></span></label>
          <button class="btn btn-danger btn-sm" type="submit"><?= icon('trash') ?> <span><?= te('Ištrinti pažymėtus') ?> (<b id="pickN">0</b>)</span></button>
        </form>
      <?php endif; ?>
      <?php foreach ($all as $i => $qq): $correct = array_values(array_filter($qq['answers'], function ($an) { return $an['is_correct']; }));
        $isFailed = $failed && $failed['qid'] === (int) $qq['id']; ?>
        <div class="q-row">
        <label class="q-pick" title="<?= te('Pažymėti trynimui') ?>"><input type="checkbox" name="qids[]" value="<?= (int) $qq['id'] ?>" form="bulkDel" aria-label="<?= te('Pažymėti') ?> <?= $i + 1 ?>"></label>
        <details class="panel card q-item" id="q<?= (int) $qq['id'] ?>"<?= $isFailed ? ' open' : '' ?>>
          <summary>
            <span class="q-num"><?= $i + 1 ?></span>
            <span class="q-sum"><strong><?= !empty($qq['is_draft']) ? '<span class="badge badge-warn">' . te('peržiūrai') . '</span> ' : '' ?><?= e(mb_strimwidth($qq['body'], 0, 120, '…')) ?></strong>
              <span class="small muted">✓ <?= e($correct ? $correct[0]['body'] : t('nepažymėtas teisingas atsakymas!')) ?> · <?= count($qq['answers']) ?> ats.<?= !empty($qq['topic']) ? (' ' . t('· tema:') . ' ') . e($qq['topic']) : '' ?><?= $qq['image'] ? (' ' . t('· paveikslėlis')) : '' ?><?= $qq['youtube_id'] ? (' ' . t('· video')) : '' ?></span></span>
          </summary>
          <?= $isFailed ? question_form($id, $failed['q'], $failed['errors']) : question_form($id, $qq) ?>
          <form method="post" id="delq<?= (int) $qq['id'] ?>" hidden onsubmit="return confirm(<?= e(json_encode(t('Ištrinti klausimą?'))) ?>)">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="qid" value="<?= (int) $qq['id'] ?>"><input type="hidden" name="action" value="delete_question">
          </form>
        </details>
        </div>
      <?php endforeach; ?>
      <div class="panel card" id="naujas">
        <h2><?= te('Naujas klausimas') ?></h2>
        <?= $failed && !$failed['qid'] ? question_form($id, $failed['q'], $failed['errors']) : question_form($id, null) ?>
      </div>
      <script>
      (function () {
        // Po klaidos - iš karto prie tos formos (įvesti duomenys lieka)
        var bad = document.querySelector('.q-form .flash-err:not([hidden])');
        if (bad) bad.closest('.q-form').scrollIntoView({ block: 'center' });
        // Enter ar ↓ - į kitą laukelį (atsakymai, paaiškinimas, YouTube, tema); ↑ - atgal; klausime Shift+Enter - nauja eilutė
        document.addEventListener('keydown', function (e) {
          var t = e.target, f = t.closest && t.closest('.q-form'); if (!f) return;
          var fields = Array.prototype.slice.call(f.querySelectorAll('textarea[name=body], input[type=text]'));
          var i = fields.indexOf(t); if (i < 0) return;
          var isAns = /^ans_body/.test(t.name);
          var go = function (k) { if (fields[k]) { e.preventDefault(); fields[k].focus(); } };
          if (e.key === 'Enter' && !e.shiftKey && t.name !== 'topic') go(i + 1);
          else if (e.key === 'ArrowDown' && isAns) go(i + 1);
          else if (e.key === 'ArrowUp' && isAns) go(i - 1);
        });
        // Prieš siunčiant: klausimas, bent 2 atsakymai, pažymėtas teisingas (netinka - nesiunčiama ir niekas neišsitrina)
        document.addEventListener('submit', function (e) {
          var f = e.target; if (!f.classList || !f.classList.contains('q-form')) return;
          var msg = [], body = f.querySelector('[name=body]').value.trim();
          var filled = Array.prototype.filter.call(f.querySelectorAll('input[name^=ans_body]'), function (x) { return x.value.trim() !== ''; });
          var chk = f.querySelector('input[name=correct]:checked');
          if (!body) msg.push('Įveskite klausimą.');
          if (filled.length < 2) msg.push('Reikia bent 2 atsakymų.');
          else if (!chk) msg.push('Pažymėkite teisingą atsakymą (bumbuliukas kairėje).');
          else if (!f.querySelector('input[name="ans_body[' + chk.value + ']"]').value.trim()) msg.push('Pažymėtas teisingu atsakymas tuščias.');
          var box = f.querySelector('.q-err');
          if (msg.length) { e.preventDefault(); box.textContent = msg.join(' '); box.hidden = false; box.scrollIntoView({ block: 'center' }); }
          else box.hidden = true;
        });
        // Keli klausimai trynimui
        var all = document.getElementById('pickAll'), cnt = function () { var n = document.getElementById('pickN'); if (n) n.textContent = document.querySelectorAll('.q-pick input:checked').length; };
        if (all) all.addEventListener('change', function () { document.querySelectorAll('.q-pick input').forEach(function (x) { x.checked = all.checked; }); cnt(); });
        document.addEventListener('change', function (e) { if (e.target.closest && e.target.closest('.q-pick')) cnt(); });
      })();
      </script>
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

page_start(t('Testai'), ['admin' => true]);
?>
<div class="page-head row between">
  <div>
    <h1 class="styled"><?= te('Testai') ?></h1>
    <p class="muted" style="margin:0;"><?= te('Klausimai parenkami atsitiktinai iš banko. Išlaikius - „Leidžiama laikyti praktiką“ (galioja') ?> <?= QUIZ_VALID_MONTHS ?> <?= te('mėn.).') ?></p>
  </div>
  <div class="row" style="gap:8px;">
    <a class="btn btn-primary" href="?new=1"><?= te('+ Naujas testas') ?></a>
  </div>
</div>
<div class="panel card">
  <?php if (!$quizzes): ?><p class="muted"><?= te('Testų dar nėra. Sukurkite pirmą - pvz. teorijos klausimai kitam diržui.') ?></p><?php endif; ?>
  <ul class="list">
    <?php foreach ($quizzes as $qz): ?>
      <li class="row between">
        <span>
          <span class="badge <?= $qz['audience'] === 'karateka' ? 'badge-pink' : 'badge-tag' ?>"><?= e(t(QUIZ_AUDIENCE[$qz['audience']])) ?></span>
          <?= $qz['belt_level'] ? belt_chip((int) $qz['belt_level']) : '' ?>
          <?php if (!$qz['is_published']): ?><span class="badge badge-warn">nepaskelbtas</span><?php endif; ?>
          <a href="?id=<?= (int) $qz['id'] ?>"><strong><?= e($qz['title']) ?></strong></a>
          <div class="muted small"><?= (int) $qz['qcount'] ?> <?= te('klaus. banke · teste') ?> <?= (int) $qz['question_count'] ?: te('visi') ?> <?= te('· reikia') ?> <?= (int) $qz['pass_percent'] ?> %<?= !empty($qz['time_limit']) ? ' · ' . (int) $qz['time_limit'] . ' min.' : '' ?> <?= te('· laikė') ?> <?= (int) $qz['attempts'] ?><?= te(', išlaikė') ?> <?= (int) $qz['passes'] ?></div>
        </span>
        <a class="btn btn-ghost btn-sm" href="?id=<?= (int) $qz['id'] ?>"><?= te('Keisti') ?></a>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<div class="panel card">
  <h2><?= te('Paskutiniai rezultatai') ?></h2>
  <?php if (!$recent): ?><p class="muted"><?= te('Dar niekas nelaikė.') ?></p><?php endif; ?>
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
