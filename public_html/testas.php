<?php
// Testas: treniruotės režimas (iš karto rodo teisingą atsakymą, nesaugoma) arba tikras testas (kaip Regitroje)
require __DIR__ . '/app/bootstrap.php';

$a = require_login();
$isStaff = is_staff($a);

// Rezultatas
if ($resId = (int) get('rezultatas')) {
    $att = q_one('SELECT * FROM quiz_attempts WHERE id = ?', [$resId]);
    if (!$att || (!$isStaff && !account_relation((int) $a['id'], (int) $att['member_id'])) || !$att['finished_at']) {
        not_found();
    }
    $quiz = q_one('SELECT * FROM quizzes WHERE id = ?', [(int) $att['quiz_id']]);
    $member = q_one('SELECT * FROM members WHERE id = ?', [(int) $att['member_id']]);
    $ids = array_map('intval', array_filter(explode(',', $att['question_ids'])));
    $qs = quiz_questions_full((int) $quiz['id'], $ids);
    $chosen = [];
    foreach (q_all('SELECT * FROM quiz_attempt_answers WHERE attempt_id = ?', [$resId]) as $r) {
        $chosen[(int) $r['question_id']] = $r;
    }
    $pct = attempt_percent($att);
    page_start('Testo rezultatas', ['noindex' => true, 'narrow' => true, 'account' => $isStaff ? null : 'egzaminas']);
    ?>
    <div class="panel card result-card <?= $att['passed'] ? 'passed' : 'failed' ?> <?= $quiz['kids_mode'] ? 'kid' : '' ?>">
      <div class="kicker"><?= e($quiz['title']) ?> · <?= e($member['first_name']) ?></div>
      <div class="result-big"><?= $pct ?> %</div>
      <p class="result-msg"><?= $att['passed']
          ? ($quiz['kids_mode'] ? '🎉 Šaunuolis! Testas išlaikytas!' : '✓ Testas išlaikytas. Leidžiama laikyti praktinę dalį.')
          : ($quiz['kids_mode'] ? '💪 Beveik! Pasikartok ir pabandyk dar kartą.' : 'Testas neišlaikytas: reikia bent ' . (int) $quiz['pass_percent'] . ' %. Pasikartokite ir bandykite dar kartą.') ?></p>
      <p class="muted small">Teisingai <?= (int) $att['score'] ?> iš <?= (int) $att['total'] ?></p>
      <div class="row" style="gap:8px; justify-content:center; margin-top:10px;">
        <a class="btn btn-primary" href="<?= url('egzaminas.php?m=' . (int) $member['id']) ?>">← Į egzamino puslapį</a>
        <?php if (!$att['passed']): ?><a class="btn btn-ghost" href="<?= url('testas.php?q=' . (int) $quiz['id'] . '&m=' . (int) $member['id'] . '&rezimas=treniruote') ?>">Treniruotis</a><?php endif; ?>
      </div>
    </div>
    <h2 style="margin:24px 0 12px;">Atsakymai</h2>
    <?php $n = 0; foreach ($ids as $qid): if (!isset($qs[$qid])) { continue; } $q = $qs[$qid]; $ch = $chosen[$qid] ?? null; $n++; ?>
      <div class="panel card review <?= $ch && $ch['is_correct'] ? 'ok' : 'bad' ?>">
        <div class="kicker"><?= $n ?> klausimas · <?= $ch && $ch['is_correct'] ? '✓ teisingai' : '✕ neteisingai' ?></div>
        <p><strong><?= nl2br(e($q['body'])) ?></strong></p>
        <ul class="review-answers">
          <?php foreach ($q['answers'] as $ans): $mine = $ch && (int) $ch['answer_id'] === (int) $ans['id']; ?>
            <li class="<?= $ans['is_correct'] ? 'correct' : '' ?> <?= $mine ? 'mine' : '' ?>"><?= e($ans['body']) ?><?= $mine ? ' <span class="small">(tavo)</span>' : '' ?></li>
          <?php endforeach; ?>
        </ul>
        <?php if ($q['explanation']): ?><p class="small muted"><?= nl2br(e($q['explanation'])) ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if ($att['passed']): ?><script><?= confetti_js() ?></script><?php endif; ?>
    <?php
    page_end();
    exit;
}

$quiz = q_one('SELECT * FROM quizzes WHERE id = ?', [(int) (get('q') ?: post('q'))]);
if (!$quiz || (!$quiz['is_published'] && !$isStaff)) {
    not_found();
}
$practice = get('rezimas') === 'treniruote';
$mid = (int) (get('m') ?: post('m'));
$member = $mid ? q_one('SELECT * FROM members WHERE id = ?', [$mid]) : null;

// Teisės: narys (ar tėvai) mato testą pagal diržą / žymas; treneris gali peržiūrėti treniruotės režimu
if (!$isStaff || $member) {
    if (!$member || !account_relation((int) $a['id'], $mid) || $member['status'] !== 'active') {
        not_found();
    }
    $visible = array_map('intval', array_column(quizzes_for_member($member, member_tags($mid)), 'id'));
    if (!in_array((int) $quiz['id'], $visible, true) && !$isStaff) {
        flash('err', 'Šis testas skirtas kitam diržui ar grupei.');
        redirect('egzaminas.php?m=' . $mid);
    }
} elseif (!$practice) {
    redirect('testas.php?q=' . (int) $quiz['id'] . '&rezimas=treniruote');
}

$kid = (bool) $quiz['kids_mode'];

// ---------- Treniruotės režimas: po vieną klausimą, iš karto rodomas teisingas atsakymas ----------
if ($practice) {
    $qs = array_values(quiz_pick_questions(quiz_questions_full((int) $quiz['id'])));
    $data = array_map(function ($q) {
        return [
            'body' => $q['body'], 'explanation' => $q['explanation'],
            'image' => $q['image'] ? quiz_image_url($q['image']) : null,
            'answers' => array_map(function ($an) { return ['t' => $an['body'], 'ok' => (bool) $an['is_correct']]; }, $q['answers']),
        ];
    }, $qs);
    page_start('Treniruotė: ' . $quiz['title'], ['noindex' => true, 'narrow' => true, 'account' => $isStaff ? null : 'egzaminas']);
    ?>
    <a class="small" href="<?= url($member ? 'egzaminas.php?m=' . $mid : 'admin/testai.php?id=' . (int) $quiz['id']) ?>">← Atgal</a>
    <div class="page-head" style="margin-top:8px;">
      <div class="eyebrow">Treniruotė · rezultatas nesaugomas</div>
      <h1 class="styled"><?= e($quiz['title']) ?></h1>
    </div>
    <?php if (!$data): ?>
      <div class="panel card"><p class="muted">Šiame teste dar nėra klausimų.</p></div>
    <?php else: ?>
      <div class="panel card quiz-box <?= $kid ? 'kid' : '' ?>" id="practice">
        <div class="quiz-progress"><span id="pNum">1</span> / <?= count($data) ?> · teisingai <span id="pOk">0</span></div>
        <img id="pImg" class="q-image" alt="" hidden>
        <p class="q-body" id="pBody"></p>
        <div class="q-answers" id="pAnswers"></div>
        <p class="q-feedback" id="pFeedback" hidden></p>
        <button class="btn btn-primary" id="pNext" hidden type="button">Toliau →</button>
      </div>
      <script>
      (function () {
        var data = <?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>, i = 0, ok = 0, kid = <?= $kid ? 'true' : 'false' ?>;
        var $ = function (id) { return document.getElementById(id); };
        function show() {
          var q = data[i];
          $('pNum').textContent = i + 1;
          $('pBody').textContent = q.body;
          if (q.image) { $('pImg').src = q.image; $('pImg').hidden = false; } else { $('pImg').hidden = true; }
          $('pFeedback').hidden = true; $('pNext').hidden = true;
          var box = $('pAnswers'); box.innerHTML = '';
          q.answers.forEach(function (an) {
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'q-answer'; b.textContent = an.t;
            b.addEventListener('click', function () { pick(b, an); });
            box.appendChild(b);
          });
        }
        function pick(btn, an) {
          var q = data[i];
          Array.prototype.forEach.call($('pAnswers').children, function (b, k) {
            b.disabled = true;
            if (q.answers[k].ok) b.classList.add('correct');
          });
          if (an.ok) { ok++; } else { btn.classList.add('wrong'); }
          $('pOk').textContent = ok;
          var fb = $('pFeedback');
          fb.textContent = (an.ok ? (kid ? '🎉 Teisingai!' : '✓ Teisingai.') : (kid ? '🙂 Ne visai. Teisingas atsakymas pažymėtas žaliai.' : '✕ Neteisingai. Teisingas atsakymas pažymėtas žaliai.'))
            + (q.explanation ? ' ' + q.explanation : '');
          fb.className = 'q-feedback ' + (an.ok ? 'ok' : 'bad'); fb.hidden = false;
          $('pNext').hidden = false;
          $('pNext').textContent = i + 1 < data.length ? 'Toliau →' : 'Baigti';
        }
        $('pNext').addEventListener('click', function () {
          i++;
          if (i < data.length) { show(); return; }
          var pct = Math.round(100 * ok / data.length);
          $('practice').innerHTML = '<div class="result-big">' + pct + ' %</div><p class="result-msg">Teisingai ' + ok + ' iš ' + data.length + '. '
            + (pct >= <?= (int) $quiz['pass_percent'] ?> ? (kid ? '🎉 Puiku! Gali laikyti tikrą testą.' : 'Pakaktų testui išlaikyti.') : 'Testui reikia bent <?= (int) $quiz['pass_percent'] ?> %. Pabandyk dar kartą.') + '</p>'
            + '<div class="row" style="gap:8px; justify-content:center;"><a class="btn btn-ghost" href="">Iš naujo</a>'
            <?php if ($member): ?> + '<a class="btn btn-primary" href="<?= e(url('testas.php?q=' . (int) $quiz['id'] . '&m=' . $mid)) ?>">Laikyti tikrą testą</a>'<?php endif; ?> + '</div>';
        });
        show();
      })();
      </script>
    <?php endif; ?>
    <?php
    page_end();
    exit;
}

// ---------- Tikras testas ----------
if (is_post()) {
    csrf_check();
    $att = q_one('SELECT * FROM quiz_attempts WHERE id = ? AND member_id = ? AND quiz_id = ? AND finished_at IS NULL', [(int) post('attempt'), $mid, (int) $quiz['id']]);
    if (!$att) {
        flash('err', 'Testas jau baigtas arba nebegalioja. Pradėkite iš naujo.');
        redirect('egzaminas.php?m=' . $mid);
    }
    $answers = [];
    if (quiz_time_is_up($att)) {
        flash('err', 'Laikas baigėsi - atsakymai pateikti per vėlai ir neįskaityti.');
    } else {
        foreach ((array) ($_POST['a'] ?? []) as $qid => $aid) {
            $answers[(int) $qid] = (int) $aid;
        }
    }
    $res = grade_quiz_attempt($att, $quiz, $answers);
    redirect('testas.php?rezultatas=' . (int) $res['id']);
}

// Nebaigtas (paskutinių 3 val.) bandymas tęsiamas, kitaip - naujas su atsitiktiniais klausimais
$att = q_one('SELECT * FROM quiz_attempts WHERE member_id = ? AND quiz_id = ? AND finished_at IS NULL AND started_at >= DATE_SUB(NOW(), INTERVAL 3 HOUR) ORDER BY id DESC LIMIT 1',
    [$mid, (int) $quiz['id']]);
if ($att && quiz_time_is_up($att)) {
    // Pradėtas, bet nebaigtas laiku - įvertinamas be atsakymų
    $res = grade_quiz_attempt($att, $quiz, []);
    flash('err', 'Ankstesnio testo laikas baigėsi - jis įvertintas be atsakymų. Galite laikyti iš naujo.');
    redirect('testas.php?rezultatas=' . (int) $res['id']);
}
if (!$att) {
    if (!quiz_questions_full((int) $quiz['id'])) {
        flash('err', 'Šiame teste dar nėra klausimų.');
        redirect('egzaminas.php?m=' . $mid);
    }
    $att = q_one('SELECT * FROM quiz_attempts WHERE id = ?', [start_quiz_attempt($quiz, $mid, (int) $a['id'])]);
}
$ids = array_map('intval', array_filter(explode(',', $att['question_ids'])));
$qs = quiz_questions_full((int) $quiz['id'], $ids);
$left = !empty($att['deadline_at']) ? max(0, strtotime($att['deadline_at']) - time()) : null;

page_start('Testas: ' . $quiz['title'], ['noindex' => true, 'narrow' => true, 'account' => 'egzaminas']);
?>
<div class="page-head">
  <div class="eyebrow">Testas · <?= e($member['first_name']) ?></div>
  <h1 class="styled"><?= e($quiz['title']) ?></h1>
  <p class="muted"><?= kiek(count($ids), 'klausimas', 'klausimai', 'klausimų') ?>. Kad išlaikytum, reikia bent <?= (int) $quiz['pass_percent'] ?> % teisingų atsakymų. Atsakymus pamatysi pabaigoje.<?php if ($left !== null): ?>
    Laikas: <?= (int) round((strtotime($att['deadline_at']) - strtotime($att['started_at'])) / 60) ?> min. - pasibaigus testas išsiunčiamas automatiškai.<?php endif; ?></p>
</div>
<?php if ($left !== null): ?><div class="quiz-timer" id="quizTimer" data-left="<?= $left ?>" title="Liko laiko">⏱ <?= sprintf('%d:%02d', intdiv($left, 60), $left % 60) ?></div><?php endif; ?>
<form method="post" class="quiz-form <?= $kid ? 'kid' : '' ?>" id="quizForm">
  <?= csrf_field() ?>
  <input type="hidden" name="q" value="<?= (int) $quiz['id'] ?>"><input type="hidden" name="m" value="<?= $mid ?>"><input type="hidden" name="attempt" value="<?= (int) $att['id'] ?>">
  <?php $n = 0; foreach ($ids as $qid): if (!isset($qs[$qid])) { continue; } $q = $qs[$qid]; $n++; $ans = $q['answers']; shuffle($ans); ?>
    <fieldset class="panel card quiz-q">
      <legend class="kicker"><?= $n ?> / <?= count($ids) ?></legend>
      <?php if ($q['image']): ?><img class="q-image" src="<?= e(quiz_image_url($q['image'])) ?>" alt=""><?php endif; ?>
      <?php if ($q['youtube_id']): ?><?= youtube_embed($q['youtube_id']) ?><?php endif; ?>
      <p class="q-body"><?= nl2br(e($q['body'])) ?></p>
      <div class="q-answers">
        <?php foreach ($ans as $an): ?>
          <label class="q-answer"><input type="radio" name="a[<?= $qid ?>]" value="<?= (int) $an['id'] ?>"><span><?= e($an['body']) ?></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
  <?php endforeach; ?>
  <div class="row" style="justify-content:space-between; align-items:center;">
    <span class="small muted" id="answered"></span>
    <button class="btn btn-primary" type="submit">Baigti testą</button>
  </div>
</form>
<script>
(function () {
  var f = document.getElementById('quizForm'), total = <?= count($ids) ?>, key = 'quiz-attempt-<?= (int) $att['id'] ?>';
  function upd() { document.getElementById('answered').textContent = 'Atsakyta ' + f.querySelectorAll('input[type=radio]:checked').length + ' iš ' + total; }
  // Pažymėti atsakymai saugomi naršyklėje - netyčia perkrovus puslapį jie nedingsta
  try {
    var saved = JSON.parse(localStorage.getItem(key) || '{}');
    Object.keys(saved).forEach(function (n) { var el = f.querySelector('input[name="' + n + '"][value="' + saved[n] + '"]'); if (el) { el.checked = true; } });
  } catch (err) {}
  f.addEventListener('change', function (e) {
    upd();
    try { var s = JSON.parse(localStorage.getItem(key) || '{}'); s[e.target.name] = e.target.value; localStorage.setItem(key, JSON.stringify(s)); } catch (err) {}
  });
  upd();
  f.addEventListener('submit', function (e) {
    var left = total - f.querySelectorAll('input[type=radio]:checked').length;
    if (left > 0 && !confirm('Neatsakyta į ' + left + ' klaus. Vis tiek baigti?')) { e.preventDefault(); return; }
    try { localStorage.removeItem(key); } catch (err) {}
  });
  // Laikmatis: baigus laiką testas išsiunčiamas automatiškai (be klausimo)
  var timer = document.getElementById('quizTimer');
  if (timer) {
    var endAt = Date.now() + parseInt(timer.dataset.left, 10) * 1000;
    (function tick() {
      var s = Math.max(0, Math.round((endAt - Date.now()) / 1000));
      timer.textContent = '⏱ ' + Math.floor(s / 60) + ':' + ('0' + s % 60).slice(-2);
      timer.classList.toggle('low', s <= 300);
      if (s === 0) { try { localStorage.removeItem(key); } catch (err) {} f.submit(); return; }
      setTimeout(tick, 1000);
    })();
  }
})();
</script>
<?php
page_end();
