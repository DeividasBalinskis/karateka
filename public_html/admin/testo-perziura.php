<?php
// Testo klausimų peržiūra (Deniui): eina per klausimus po vieną. Nieko nepažymėta - gerai;
// ✕ - visas klausimas ar atsakymas netinka; 💬 - komentaras; tekstą, atsakymus ir paveikslėlį galima taisyti vietoje.
// Pastabas galima atsisiųsti ChatGPT, o pataisytus klausimus įkelti atgal (Testai → „Įkelti klausimus“).
require dirname(__DIR__) . '/app/bootstrap.php';
require APP_DIR . '/images.php';
$me = require_staff();

const QUIZ_DIR = PUBLIC_DIR . '/uploads/quiz';

$id = (int) (get('id') ?: post('id'));
$quiz = $id ? q_one('SELECT * FROM quizzes WHERE id = ?', [$id]) : null;
if (!$quiz) {
    not_found();
}

// Pastabos ChatGPT (tik klausimai su ✕ ar komentaru)
if (get('pastabos') === '1') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="testas-' . $id . '-pastabos.txt"');
    echo "\u{FEFF}" . quiz_review_export_text($id);
    exit;
}

/** Klausimas šiame teste arba null */
function rv_question(int $quizId, int $qid): ?array
{
    return $qid ? q_one('SELECT * FROM quiz_questions WHERE id = ? AND quiz_id = ?', [$qid, $quizId]) : null;
}

/** Atsakymas šio testo klausime arba null */
function rv_answer(int $quizId, int $aid): ?array
{
    return $aid ? q_one('SELECT a.* FROM quiz_answers a JOIN quiz_questions q ON q.id = a.question_id WHERE a.id = ? AND q.quiz_id = ?', [$aid, $quizId]) : null;
}

/** Peržiūros eilutė (answer_id 0 - klausimui): sukuria, jei nėra; grąžina, ar dar liko kas nors (žyma ar komentaras) */
function rv_mark(int $qid, int $aid, array $set, int $accountId): void
{
    q('INSERT INTO quiz_review (question_id, answer_id, account_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE account_id = VALUES(account_id)', [$qid, $aid, $accountId]);
    foreach ($set as $col => $val) {
        q("UPDATE quiz_review SET $col = ? WHERE question_id = ? AND answer_id = ?", [$val, $qid, $aid]);
    }
    if ($aid) {   // atsakymo eilutė be žymos ir komentaro nereikalinga
        q("DELETE FROM quiz_review WHERE question_id = ? AND answer_id = ? AND flag = 0 AND (comment IS NULL OR comment = '')", [$qid, $aid]);
    }
}

if (is_post()) {
    csrf_check();
    $action = post('action');

    // Formos (ne JSON)
    if ($action === 'approve') {
        $notes = quiz_review_notes($id);
        $ok = [];
        foreach (q_all('SELECT id FROM quiz_questions WHERE quiz_id = ? AND is_draft = 1', [$id]) as $r) {
            $qn = $notes[(int) $r['id']] ?? null;
            if ($qn && $qn['seen'] && !quiz_review_has_notes($qn)) {
                $ok[] = (int) $r['id'];
            }
        }
        if ($ok) {
            q('UPDATE quiz_questions SET is_draft = 0 WHERE id IN (' . implode(',', $ok) . ')');
        }
        flash('ok', t('Į testą įtraukta klausimų: {n}.', ['n' => count($ok)]));
        redirect('admin/testo-perziura.php?id=' . $id);
    }
    if ($action === 'approve_all') {
        $n = q('UPDATE quiz_questions SET is_draft = 0 WHERE quiz_id = ? AND is_draft = 1', [$id])->rowCount();
        flash('ok', t('Į testą įtraukta klausimų: {n}.', ['n' => $n]));
        redirect(post('back') === 'try' ? 'testas.php?q=' . $id . '&rezimas=treniruote' : 'admin/testo-perziura.php?id=' . $id);   // #244: iš „Išbandyti“ - iškart pradėti
    }
    if ($action === 'clear') {
        q('DELETE r FROM quiz_review r JOIN quiz_questions q ON q.id = r.question_id WHERE q.quiz_id = ?', [$id]);
        flash('ok', t('Pastabos ir „peržiūrėta“ žymos išvalytos.'));
        redirect('admin/testo-perziura.php?id=' . $id);
    }

    // Automatinis išsaugojimas (JSON)
    header('Content-Type: application/json; charset=utf-8');
    $fail = function (string $msg) { exit(json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE)); };
    $q = rv_question($id, (int) post('qid'));
    if (!$q) {
        $fail(t('Klausimas nerastas.'));
    }
    $qid = (int) $q['id'];
    $aid = (int) post('aid');
    if ($aid && !rv_answer($id, $aid)) {
        $fail(t('Atsakymas nerastas.'));
    }
    $out = ['ok' => true];
    switch ($action) {
        case 'seen':
            q('INSERT IGNORE INTO quiz_review (question_id, answer_id, account_id) VALUES (?, 0, ?)', [$qid, (int) $me['id']]);
            break;
        case 'flag':
            rv_mark($qid, $aid, ['flag' => post('on') === '1' ? 1 : 0], (int) $me['id']);
            break;
        case 'comment':
            rv_mark($qid, $aid, ['comment' => mb_substr(post('text'), 0, 2000) ?: null], (int) $me['id']);
            break;
        case 'q_body':
            if (post('text') === '') {
                $fail(t('Klausimo tekstas negali būti tuščias.'));
            }
            q('UPDATE quiz_questions SET body = ? WHERE id = ?', [mb_substr(post('text'), 0, 2000), $qid]);
            break;
        case 'q_expl':
            q('UPDATE quiz_questions SET explanation = ? WHERE id = ?', [mb_substr(post('text'), 0, 2000) ?: null, $qid]);
            break;
        case 'q_topic':
            q('UPDATE quiz_questions SET topic = ? WHERE id = ?', [mb_substr(post('text'), 0, 190) ?: null, $qid]);
            break;
        case 'a_body':
            if (post('text') === '') {
                $fail(t('Tuščią atsakymą ištrinkite mygtuku 🗑.'));
            }
            q('UPDATE quiz_answers SET body = ? WHERE id = ?', [mb_substr(post('text'), 0, 255), $aid]);
            break;
        case 'a_correct':
            q('UPDATE quiz_answers SET is_correct = (id = ?) WHERE question_id = ?', [$aid, $qid]);
            break;
        case 'a_add':
            if ((int) q_value('SELECT COUNT(*) FROM quiz_answers WHERE question_id = ?', [$qid]) >= 4) {
                $fail(t('Daugiausia 4 atsakymai.'));
            }
            q('INSERT INTO quiz_answers (question_id, body, is_correct, sort_order) VALUES (?, ?, 0, ?)',
                [$qid, mb_substr(post('text'), 0, 255) ?: t('Naujas atsakymas'), (int) q_value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM quiz_answers WHERE question_id = ?', [$qid])]);
            $out['aid'] = (int) db()->lastInsertId();
            break;
        case 'a_del':
            if ((int) q_value('SELECT COUNT(*) FROM quiz_answers WHERE question_id = ?', [$qid]) <= 2) {
                $fail(t('Reikia bent 2 atsakymų.'));
            }
            q('DELETE FROM quiz_answers WHERE id = ?', [$aid]);
            q('DELETE FROM quiz_review WHERE question_id = ? AND answer_id = ?', [$qid, $aid]);
            break;
        case 'image':
            try {
                $file = save_uploaded_image($_FILES['image'] ?? [], QUIZ_DIR);
            } catch (RuntimeException $ex) {
                $fail($ex->getMessage());
            }
            quiz_set_image($qid, $file);
            $out['url'] = quiz_image_url($file);
            break;
        case 'image_remove':
            quiz_set_image($qid, null);
            break;
        default:
            $fail(t('Nežinomas veiksmas.'));
    }
    $out['saved'] = t('Išsaugota');
    exit(json_encode($out, JSON_UNESCAPED_UNICODE));
}

// ---------- Puslapis ----------
$all = [];
foreach (q_all('SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order, id', [$id]) as $i => $row) {
    $row['num'] = $i + 1;
    $row['answers'] = q_all('SELECT * FROM quiz_answers WHERE question_id = ? ORDER BY sort_order, id', [(int) $row['id']]);
    $all[] = $row;
}
$notes = quiz_review_notes($id);
$draftCount = count(array_filter($all, function ($r) { return (int) $r['is_draft'] === 1; }));
$show = in_array(get('rodyti'), ['visi', 'juodrasciai', 'pastabos'], true) ? get('rodyti') : ($draftCount ? 'juodrasciai' : 'visi');
$list = array_values(array_filter($all, function ($r) use ($show, $notes) {
    if ($show === 'juodrasciai') {
        return (int) $r['is_draft'] === 1;
    }
    if ($show === 'pastabos') {
        return isset($notes[(int) $r['id']]) && quiz_review_has_notes($notes[(int) $r['id']]);
    }
    return true;
}));
$noted = count(array_filter($notes, 'quiz_review_has_notes'));
$readyDrafts = count(array_filter($all, function ($r) use ($notes) {
    $qn = $notes[(int) $r['id']] ?? null;
    return (int) $r['is_draft'] === 1 && $qn && $qn['seen'] && !quiz_review_has_notes($qn);
}));
// Pradėti nuo pirmo dar neperžiūrėto
$start = 0;
foreach ($list as $k => $r) {
    if (empty($notes[(int) $r['id']]['seen'])) {
        $start = $k;
        break;
    }
}

page_start(t('Klausimų peržiūra') . ': ' . $quiz['title'], ['admin' => true]);
?>
<a class="small back-link" href="<?= url('admin/testai.php?id=' . $id) ?>"><?= icon('arrow-left') ?> <?= te('Atgal į testą') ?></a>
<div class="page-head row between" style="margin-top:8px;">
  <h1 class="styled"><?= te('Klausimų peržiūra') ?></h1>
  <span class="muted small"><?= e($quiz['title']) ?></span>
</div>

<div class="panel card rv-top">
  <p class="small muted rv-how"><?= te('Eikite per klausimus. Jei viskas gerai - nieko nežymėkite, tiesiog „Toliau →“. ✕ - netinka (visas klausimas ar atsakymas), 💬 - komentaras. Tekstą galite taisyti iškart - išsaugoma savaime.') ?></p>
  <div class="rv-stats">
    <div class="rv-bar"><i id="rvBar" style="width:0"></i></div>
    <span class="small"><?= te('Peržiūrėta') ?> <b id="rvSeen">0</b> / <?= count($list) ?> · <?= te('su pastabomis') ?> <b id="rvNoted"><?= $noted ?></b></span>
  </div>
  <div class="chips-row rv-filter" role="group" aria-label="<?= te('Rodyti') ?>">
    <?php foreach (['juodrasciai' => t('Laukia peržiūros') . ' (' . $draftCount . ')', 'pastabos' => t('Su pastabomis') . ' (' . $noted . ')', 'visi' => t('Visi') . ' (' . count($all) . ')'] as $k => $lbl): ?>
      <a class="fchip" href="<?= url('admin/testo-perziura.php?id=' . $id . '&rodyti=' . $k) ?>" aria-pressed="<?= $show === $k ? 'true' : 'false' ?>"><?= e($lbl) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="rv-strip" id="rvStrip" aria-label="<?= te('Klausimai') ?>">
    <?php foreach ($list as $k => $r): $qn = $notes[(int) $r['id']] ?? null; ?>
      <button type="button" data-go="<?= $k ?>" class="<?= $qn && $qn['seen'] ? 'seen' : '' ?><?= $qn && quiz_review_has_notes($qn) ? ' noted' : '' ?>" title="<?= e(mb_strimwidth($r['body'], 0, 80, '…')) ?>"><?= (int) $r['num'] ?></button>
    <?php endforeach; ?>
  </div>
</div>

<?php if (!$list): ?>
  <div class="panel card"><p class="muted"><?= $show === 'juodrasciai' ? te('Peržiūros laukiančių klausimų nėra.') : ($show === 'pastabos' ? te('Pastabų nėra.') : te('Klausimų dar nėra.')) ?>
    <a href="<?= url('admin/testai.php?id=' . $id . '#ikelti') ?>"><?= te('Įkelti klausimus →') ?></a></p></div>
<?php endif; ?>

<div id="rvCards">
<?php foreach ($list as $k => $r): $qid = (int) $r['id']; $qn = $notes[$qid] ?? ['seen' => false, 'flag' => 0, 'comment' => '', 'answers' => []]; ?>
  <article class="panel card rv-card<?= $qn['flag'] ? ' flagged' : '' ?>" data-k="<?= $k ?>" data-qid="<?= $qid ?>" data-seen="<?= $qn['seen'] ? 1 : 0 ?>" data-draft="<?= (int) $r['is_draft'] ?>"<?= $k === $start ? '' : ' hidden' ?>>
    <header class="rv-head">
      <span class="rv-num"><?= te('Klausimas') ?> <?= (int) $r['num'] ?> <span class="muted">/ <?= count($all) ?></span></span>
      <?php if ((int) $r['is_draft']): ?><span class="badge badge-warn"><?= te('peržiūrai') ?></span><?php endif; ?>
      <span class="rv-tools">
        <button type="button" class="icon-btn rv-x rv-x-wide" data-aid="0" aria-pressed="<?= $qn['flag'] ? 'true' : 'false' ?>" title="<?= te('Visas klausimas netinka') ?>"><?= icon('x') ?> <span><?= te('Netinka') ?></span></button>
        <button type="button" class="icon-btn rv-c" data-aid="0" aria-pressed="<?= trim($qn['comment']) !== '' ? 'true' : 'false' ?>" title="<?= te('Komentaras apie klausimą') ?>"><?= icon('comment', t('Komentaras apie klausimą')) ?></button>
      </span>
    </header>
    <textarea class="rv-body" data-act="q_body" rows="2" aria-label="<?= te('Klausimo tekstas') ?>"><?= e($r['body']) ?></textarea>
    <div class="rv-comment" data-aid="0"<?= trim($qn['comment']) !== '' ? '' : ' hidden' ?>><textarea rows="2" placeholder="<?= te('Komentaras apie visą klausimą (pvz. per sunkus, neaiškus)') ?>"><?= e($qn['comment']) ?></textarea></div>

    <div class="rv-img">
      <?php if ($r['image']): ?><img src="<?= e(quiz_image_url($r['image'])) ?>" alt=""><?php endif; ?>
      <?php if (!$r['image'] && trim((string) $r['image_note']) !== ''): ?><p class="small rv-img-note"><?= icon('image') ?> <?= te('Reikia paveikslėlio:') ?> <?= e($r['image_note']) ?></p><?php endif; ?>
      <div class="rv-img-btns">
        <label class="btn btn-ghost btn-sm rv-img-pick"><?= icon('image') ?> <span class="rv-img-lbl"><?= $r['image'] ? te('Pakeisti paveikslėlį') : te('Pridėti paveikslėlį') ?></span><input type="file" accept="image/*" hidden></label>
        <button type="button" class="btn btn-danger btn-sm rv-img-del"<?= $r['image'] ? '' : ' hidden' ?>><?= icon('trash') ?> <span><?= te('Pašalinti') ?></span></button>
        <span class="small muted rv-img-hint"><?= te('arba įklijuokite (Ctrl+V) / nutempkite čia') ?></span>
      </div>
    </div>

    <ol class="rv-answers">
      <?php foreach ($r['answers'] as $j => $a): $an = $qn['answers'][(int) $a['id']] ?? ['flag' => 0, 'comment' => '']; ?>
        <li class="rv-ans<?= $an['flag'] ? ' flagged' : '' ?><?= (int) $a['is_correct'] ? ' correct' : '' ?>" data-aid="<?= (int) $a['id'] ?>">
          <label class="rv-ok" title="<?= te('Teisingas atsakymas') ?>"><input type="radio" name="ok<?= $qid ?>" <?= (int) $a['is_correct'] ? 'checked' : '' ?>><span><?= chr(65 + $j) ?></span></label>
          <input type="text" class="rv-a" value="<?= e($a['body']) ?>" maxlength="255" aria-label="<?= te('Atsakymas') ?> <?= chr(65 + $j) ?>">
          <button type="button" class="icon-btn rv-x" data-aid="<?= (int) $a['id'] ?>" aria-pressed="<?= $an['flag'] ? 'true' : 'false' ?>" title="<?= te('Šis atsakymas netinka') ?>"><?= icon('x', t('Šis atsakymas netinka')) ?></button>
          <button type="button" class="icon-btn rv-c" data-aid="<?= (int) $a['id'] ?>" aria-pressed="<?= trim($an['comment']) !== '' ? 'true' : 'false' ?>" title="<?= te('Komentaras apie atsakymą') ?>"><?= icon('comment', t('Komentaras apie atsakymą')) ?></button>
          <button type="button" class="icon-btn rv-del" title="<?= te('Ištrinti atsakymą') ?>"><?= icon('trash', t('Ištrinti atsakymą')) ?></button>
          <div class="rv-comment" data-aid="<?= (int) $a['id'] ?>"<?= trim($an['comment']) !== '' ? '' : ' hidden' ?>><textarea rows="1" placeholder="<?= te('Komentaras apie šį atsakymą') ?>"><?= e($an['comment']) ?></textarea></div>
        </li>
      <?php endforeach; ?>
    </ol>
    <button type="button" class="linklike small rv-add"<?= count($r['answers']) >= 4 ? ' hidden' : '' ?>><?= te('+ Pridėti atsakymą') ?></button>

    <details class="rv-more small">
      <summary><?= te('Paaiškinimas ir tema') ?><?= trim((string) $r['explanation']) !== '' ? ' ✓' : '' ?></summary>
      <label><?= te('Paaiškinimas') ?> <textarea data-act="q_expl" rows="2"><?= e((string) $r['explanation']) ?></textarea></label>
      <label><?= te('Tema') ?> <span class="hint"><?= te('to paties klausimo kitoms formuluotėms - ta pati') ?></span><input type="text" data-act="q_topic" value="<?= e((string) $r['topic']) ?>" maxlength="190"></label>
    </details>

    <footer class="rv-nav">
      <button type="button" class="btn btn-ghost rv-prev"<?= $k === 0 ? ' disabled' : '' ?>><?= icon('arrow-left') ?> <span><?= te('Ankstesnis') ?></span></button>
      <span class="small muted rv-state" aria-live="polite"></span>
      <button type="button" class="btn btn-primary rv-next"><?= $k === count($list) - 1 ? '<span>' . te('Baigti') . '</span> ' . icon('check') : '<span>' . te('Toliau') . '</span> ' . icon('arrow-right') ?></button>
    </footer>
  </article>
<?php endforeach; ?>
</div>

<div class="panel card rv-done" id="rvDone">
  <h2><?= te('Kai baigsite') ?></h2>
  <div class="rv-actions">
    <a class="btn btn-ghost" href="<?= url('admin/testo-perziura.php?id=' . $id . '&pastabos=1') ?>" download><?= icon('download') ?> <span><?= te('Atsisiųsti pastabas ChatGPT') ?> (<b id="rvNoted2"><?= $noted ?></b>)</span></a>
    <?php if ($draftCount): ?>
      <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="approve">
        <button class="btn btn-primary" type="submit"><?= icon('check') ?> <span><?= te('Įtraukti į testą peržiūrėtus be pastabų') ?> (<b id="rvReady"><?= $readyDrafts ?></b>)</span></button></form>
    <?php endif; ?>
  </div>
  <ol class="small rv-steps">
    <li><?= te('Atsisiųstą pastabų failą įkelkite ChatGPT (projekte „Karateka testai“) ir parašykite: „Pataisyk pagal pastabas“.') ?></li>
    <li><?= te('ChatGPT atsakymą (failą ar tekstą) įkelkite: testas → „Įkelti klausimus iš failo“ → „Įkelti peržiūrai“. Pataisyti klausimai vėl atsiras čia.') ?></li>
    <li><?= te('Peržiūrėti klausimai be pastabų įtraukiami į testą mygtuku aukščiau. Pastabų nebeliko - vadinasi, testas paruoštas.') ?></li>
  </ol>
  <details class="small">
    <summary><?= te('Daugiau') ?></summary>
    <div class="row" style="gap:8px; margin-top:8px;">
      <?php if ($draftCount): ?>
        <form method="post" class="inline-form" onsubmit="return confirm(<?= e(json_encode(t('Įtraukti į testą visus laukiančius klausimus, net ir neperžiūrėtus ar su pastabomis?'))) ?>)"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="approve_all">
          <button class="btn btn-ghost btn-sm" type="submit"><?= te('Įtraukti visus laukiančius') ?> (<?= $draftCount ?>)</button></form>
      <?php endif; ?>
      <form method="post" class="inline-form" onsubmit="return confirm(<?= e(json_encode(t('Išvalyti visas šio testo pastabas ir „peržiūrėta“ žymas?'))) ?>)"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="clear">
        <button class="btn btn-ghost btn-sm" type="submit"><?= te('Išvalyti pastabas') ?></button></form>
    </div>
  </details>
</div>

<script>
(function () {
  var QUIZ = <?= $id ?>, CSRF = <?= json_encode(csrf_token()) ?>, URL_ = <?= json_encode(url('admin/testo-perziura.php')) ?>;
  var L = <?= json_encode(['saving' => t('Saugoma…'), 'saved' => t('✓ Išsaugota'), 'err' => t('Nepavyko išsaugoti'), 'del' => t('Ištrinti šį atsakymą?'), 'imgDel' => t('Pašalinti paveikslėlį?'), 'change' => t('Pakeisti paveikslėlį')], JSON_UNESCAPED_UNICODE) ?>;
  var cards = Array.prototype.slice.call(document.querySelectorAll('.rv-card')), strip = document.querySelectorAll('#rvStrip [data-go]');
  var cur = cards.findIndex(function (c) { return !c.hidden; });

  function send(card, data, file) {
    var fd = new FormData(), st = card.querySelector('.rv-state');
    fd.append('_csrf', CSRF); fd.append('id', QUIZ); fd.append('qid', card.dataset.qid);
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    if (file) fd.append('image', file);
    if (st) st.textContent = L.saving;
    return fetch(URL_, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      if (st) st.textContent = j.ok ? L.saved : (j.error || L.err);
      if (!j.ok) throw new Error(j.error || L.err);
      return j;
    }).catch(function (e) { if (st) st.textContent = e.message || L.err; throw e; });
  }
  function hasNotes(card) {
    return !!card.querySelector('.rv-x[aria-pressed="true"]') || Array.prototype.some.call(card.querySelectorAll('.rv-comment textarea'), function (t) { return t.value.trim() !== ''; });
  }
  function refresh() {
    var seen = 0, noted = 0, ready = 0;
    cards.forEach(function (c, i) {
      var n = hasNotes(c), s = c.dataset.seen === '1';
      seen += s ? 1 : 0; noted += n ? 1 : 0; ready += s && !n && c.dataset.draft === '1' ? 1 : 0;
      if (strip[i]) { strip[i].classList.toggle('seen', s); strip[i].classList.toggle('noted', n); strip[i].classList.toggle('cur', i === cur); }
      c.classList.toggle('flagged', !!c.querySelector('.rv-head .rv-x[aria-pressed="true"]'));
    });
    var b = document.getElementById('rvBar'); if (b) b.style.width = (cards.length ? Math.round(seen * 100 / cards.length) : 0) + '%';
    var e1 = document.getElementById('rvSeen'); if (e1) e1.textContent = seen;
    ['rvNoted', 'rvNoted2'].forEach(function (x) { var el = document.getElementById(x); if (el) el.textContent = noted; });
    var rd = document.getElementById('rvReady'); if (rd && <?= $show !== 'pastabos' ? 'true' : 'false' ?>) rd.textContent = ready;
  }
  function show(i, scroll) {
    if (i < 0 || i >= cards.length) return;
    cards.forEach(function (c, k) { c.hidden = k !== i; });
    cur = i;
    var c = cards[i];
    if (c.dataset.seen !== '1') { c.dataset.seen = '1'; send(c, { action: 'seen' }).catch(function () {}); }
    refresh();
    if (strip[i]) strip[i].scrollIntoView({ block: 'nearest', inline: 'center' });
    if (scroll) c.scrollIntoView({ block: 'start', behavior: 'smooth' });
  }

  // Tekstai: išsaugoma po pertraukėlės ir išėjus iš lauko
  var timers = new WeakMap();
  function saveField(el) {
    var card = el.closest('.rv-card'), data;
    if (el.dataset.act) data = { action: el.dataset.act, text: el.value };
    else if (el.classList.contains('rv-a')) data = { action: 'a_body', aid: el.closest('.rv-ans').dataset.aid, text: el.value };
    else if (el.closest('.rv-comment')) data = { action: 'comment', aid: el.closest('.rv-comment').dataset.aid, text: el.value };
    if (!data || el.dataset.last === el.value) return;
    el.dataset.last = el.value;
    send(card, data).catch(function () { el.dataset.last = ''; });
    refresh();
  }
  document.querySelectorAll('.rv-card textarea, .rv-card input[type=text]').forEach(function (el) {
    el.dataset.last = el.value;
    el.addEventListener('input', function () { clearTimeout(timers.get(el)); timers.set(el, setTimeout(function () { saveField(el); }, 700)); if (el.closest('.rv-comment')) refresh(); });
    el.addEventListener('blur', function () { clearTimeout(timers.get(el)); saveField(el); });
  });
  function grow(t) { t.style.height = 'auto'; t.style.height = (t.scrollHeight + 2) + 'px'; }
  document.querySelectorAll('.rv-card textarea').forEach(function (t) { t.addEventListener('input', function () { grow(t); }); });

  document.addEventListener('click', function (e) {
    var t = e.target.closest('button, [data-go]'); if (!t) return;
    var card = t.closest('.rv-card');
    if (t.dataset.go !== undefined) { show(+t.dataset.go, false); return; }
    if (!card) return;
    if (t.classList.contains('rv-next')) { if (cur < cards.length - 1) show(cur + 1, true); else document.getElementById('rvDone').scrollIntoView({ behavior: 'smooth' }); return; }
    if (t.classList.contains('rv-prev')) { show(cur - 1, true); return; }
    if (t.classList.contains('rv-x')) {
      var on = t.getAttribute('aria-pressed') !== 'true';
      t.setAttribute('aria-pressed', on ? 'true' : 'false');
      var li = t.closest('.rv-ans'); if (li) li.classList.toggle('flagged', on);
      send(card, { action: 'flag', aid: t.dataset.aid, on: on ? '1' : '0' }).catch(function () {});
      refresh(); return;
    }
    if (t.classList.contains('rv-c')) {
      var box = card.querySelector('.rv-comment[data-aid="' + t.dataset.aid + '"]');
      box.hidden = !box.hidden;
      if (!box.hidden) { var ta = box.querySelector('textarea'); grow(ta); ta.focus(); }
      t.setAttribute('aria-pressed', !box.hidden || box.querySelector('textarea').value.trim() ? 'true' : 'false');
      return;
    }
    if (t.classList.contains('rv-del')) {
      var row = t.closest('.rv-ans'); if (!confirm(L.del)) return;
      send(card, { action: 'a_del', aid: row.dataset.aid }).then(function () { row.remove(); relabel(card); }).catch(function () {});
      return;
    }
    if (t.classList.contains('rv-add')) {
      send(card, { action: 'a_add' }).then(function (j) { location.reload(); }).catch(function () {});
      return;
    }
    if (t.classList.contains('rv-img-del')) {
      if (!confirm(L.imgDel)) return;
      send(card, { action: 'image_remove' }).then(function () { var im = card.querySelector('.rv-img img'); if (im) im.remove(); t.hidden = true; }).catch(function () {});
    }
  });
  function relabel(card) {
    card.querySelectorAll('.rv-ans').forEach(function (li, i) { li.querySelector('.rv-ok span').textContent = String.fromCharCode(65 + i); });
    card.querySelector('.rv-add').hidden = card.querySelectorAll('.rv-ans').length >= 4;
  }
  document.addEventListener('change', function (e) {
    var card = e.target.closest('.rv-card'); if (!card) return;
    if (e.target.type === 'radio') {
      var li = e.target.closest('.rv-ans');
      card.querySelectorAll('.rv-ans').forEach(function (x) { x.classList.toggle('correct', x === li); });
      send(card, { action: 'a_correct', aid: li.dataset.aid }).catch(function () {});
    }
    if (e.target.type === 'file' && e.target.files[0]) upload(card, e.target.files[0]);
  });
  function upload(card, file) {
    send(card, { action: 'image' }, file).then(function (j) {
      var box = card.querySelector('.rv-img'), im = box.querySelector('img');
      if (!im) { im = document.createElement('img'); im.alt = ''; box.insertBefore(im, box.firstChild); }
      im.src = j.url;
      var note = box.querySelector('.rv-img-note'); if (note) note.remove();
      box.querySelector('.rv-img-del').hidden = false;
      box.querySelector('.rv-img-lbl').textContent = L.change;
    }).catch(function () {});
  }
  // Įklijuoti ar nutempti paveikslėlį į atidarytą klausimą
  document.addEventListener('paste', function (e) {
    var card = cards[cur]; if (!card || !e.clipboardData) return;
    var f = Array.prototype.find.call(e.clipboardData.files || [], function (x) { return /^image\//.test(x.type); });
    if (f) { e.preventDefault(); upload(card, f); }
  });
  cards.forEach(function (card) {
    var box = card.querySelector('.rv-img');
    box.addEventListener('dragover', function (e) { e.preventDefault(); box.classList.add('drop'); });
    box.addEventListener('dragleave', function () { box.classList.remove('drop'); });
    box.addEventListener('drop', function (e) { e.preventDefault(); box.classList.remove('drop'); var f = e.dataTransfer.files[0]; if (f && /^image\//.test(f.type)) upload(card, f); });
  });
  document.addEventListener('keydown', function (e) {
    if (/INPUT|TEXTAREA|SELECT/.test((document.activeElement || {}).tagName || '')) return;
    if (e.key === 'ArrowRight') show(cur + 1, false);
    if (e.key === 'ArrowLeft') show(cur - 1, false);
  });
  if (cur >= 0) show(cur, false);
  document.querySelectorAll('.rv-card:not([hidden]) textarea').forEach(grow);
  new MutationObserver(function () { var c = cards[cur]; if (c) c.querySelectorAll('textarea').forEach(grow); }).observe(document.getElementById('rvCards'), { attributes: true, subtree: true, attributeFilter: ['hidden'] });
})();
</script>
<?php page_end();
