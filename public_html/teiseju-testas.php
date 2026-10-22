<?php
// WTKU teisėjų žinių testas: egzaminas (kai treneris atidaro) ir mokomasis testas. Nustatymai - Treneriams -> Teisėjų testas.
require __DIR__ . '/app/bootstrap.php';

$a = require_login();
$s = referee_settings();
$canTake = referee_can_take($a);

if (is_post()) {
    csrf_check();
    $action = post('action');
    if (!$canTake) {
        forbidden();
    }

    if ($action === 'start') {
        if (!referee_topic_count()) {
            flash('err', 'Klausimų banke nėra aktyvių klausimų.');
            redirect('teiseju-testas.php');
        }
        if (post('mode') === 'exam') {
            if ($open = referee_unfinished((int) $a['id'], 'exam')) {
                redirect('teiseju-testas.php?a=' . (int) $open['id']);
            }
            $status = referee_exam_status($a);
            if (!$status['ok']) {
                flash('err', $status['reason']);
                redirect('teiseju-testas.php');
            }
            $id = referee_start_attempt($a, 'exam', null, (int) $s['exam_questions']);
        } else {
            if ($s['exam_open'] || !$s['practice']) {
                flash('err', 'Mokomasis testas šiuo metu nepasiekiamas.');
                redirect('teiseju-testas.php');
            }
            $category = isset(referee_categories()[post('category')]) ? post('category') : null;
            $size = in_array((int) post('size'), REFEREE_PRACTICE_SIZES, true) ? (int) post('size') : referee_topic_count($category);
            // Ankstesnis nebaigtas mokomasis testas nebereikalingas
            q('DELETE FROM referee_attempts WHERE account_id = ? AND mode = "practice" AND finished_at IS NULL', [$a['id']]);
            $id = referee_start_attempt($a, 'practice', $category, $size);
        }
        redirect('teiseju-testas.php?a=' . $id);
    }

    $attempt = referee_attempt((int) post('a'));
    if (!$attempt || (int) $attempt['account_id'] !== (int) $a['id']) {
        not_found();
    }

    // Atsakymas išsaugomas iš karto paspaudus (jei telefone dings internetas ar puslapis užsidarys - galima tęsti)
    if ($action === 'save') {
        $ok = referee_save_answer($attempt, (int) post('pos'), post('choice') === '' ? null : (int) post('choice'));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'finished' => !$ok && ($attempt['finished_at'] !== null || referee_time_is_up($attempt))]);
        exit;
    }

    if ($action === 'finish') {
        foreach ((array) ($_POST['ans'] ?? []) as $pos => $choice) {
            if (is_string($choice) && $choice !== '') {
                referee_save_answer($attempt, (int) $pos, (int) $choice);
            }
        }
        referee_finish_attempt((int) $attempt['id']);
        redirect('teiseju-testas.php?a=' . (int) $attempt['id']);
    }
    redirect('teiseju-testas.php');
}

// ---------- Rezultatas arba testo sprendimas ----------
$attemptId = (int) get('a');
if ($attemptId) {
    $attempt = referee_attempt($attemptId);
    if (!$attempt || (int) $attempt['account_id'] !== (int) $a['id']) {
        not_found();
    }

    if ($attempt['finished_at'] !== null) {
        $review = $attempt['mode'] === 'practice' || $s['show_answers'];
        page_start('Teisėjų testas: rezultatas', ['noindex' => true]);
        ?>
        <div class="quiz-wrap">
          <a class="small" href="<?= url('teiseju-testas.php') ?>">← Teisėjų testas</a>
          <h1 class="styled" style="margin-top:8px;">Rezultatas</h1>
          <?= render_referee_result($attempt, $review) ?>
          <?php if (!$review): ?><p class="muted small">Teisingi egzamino atsakymai nerodomi.</p><?php endif; ?>
        </div>
        <?php
        page_end();
        exit;
    }

    $questions = referee_attempt_questions($attemptId);
    $remaining = $attempt['deadline_at'] !== null ? max(0, strtotime($attempt['deadline_at']) - time()) : null;
    page_start('Teisėjų testas', ['noindex' => true]);
    ?>
    <form method="post" class="quiz-wrap quiz" id="quiz" data-remaining="<?= $remaining === null ? '' : $remaining ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="finish">
      <input type="hidden" name="a" value="<?= $attemptId ?>">
      <div class="panel card">
        <div class="quiz-top">
          <span><strong><?= $attempt['mode'] === 'exam' ? 'Egzaminas' : 'Mokomasis testas' ?></strong> · <span id="qCount">klausimų: <?= count($questions) ?></span></span>
          <span class="badge badge-pink" id="qCat" hidden></span>
          <?php if ($remaining !== null): ?><span class="quiz-timer" id="qTimer" title="Liko laiko">⏱ <?= sprintf('%d:%02d', intdiv($remaining, 60), $remaining % 60) ?></span><?php endif; ?>
        </div>
        <div class="quiz-progress"><span id="qBar"></span></div>

        <?php foreach ($questions as $q): $pos = (int) $q['position']; ?>
          <fieldset class="quiz-q" data-cat="<?= e($q['category']) ?>">
            <legend class="quiz-question"><span class="quiz-num"><?= $pos + 1 ?>.</span> <?= e($q['question']) ?></legend>
            <div class="quiz-answers">
              <?php foreach ($q['answers'] as $i => $text): ?>
                <label class="quiz-answer">
                  <input type="radio" name="ans[<?= $pos ?>]" value="<?= $i ?>" <?= $q['chosen'] === $i ? 'checked' : '' ?>>
                  <span class="letter"><?= chr(65 + $i) ?></span><span><?= e($text) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>
        <?php endforeach; ?>

        <div class="quiz-controls">
          <button type="button" class="btn btn-ghost" id="qPrev">← Atgal</button>
          <span class="muted small" id="qAnswered"></span>
          <button type="button" class="btn btn-primary" id="qNext">Toliau →</button>
          <button type="submit" class="btn btn-primary" id="qFinish">Baigti testą</button>
        </div>
        <div class="quiz-nav" id="qNav" aria-label="Klausimų sąrašas"></div>
        <p class="muted small" id="qSaveNote" style="margin-top:14px;">Atsakymai išsaugomi iš karto - uždarę puslapį galėsite tęsti.</p>
      </div>
    </form>
    <script>
    (function () {
      var form = document.getElementById('quiz');
      var qs = [].slice.call(form.querySelectorAll('.quiz-q'));
      var nav = document.getElementById('qNav'), total = qs.length, pos = 0, submitting = false;
      var csrf = form.querySelector('[name="_csrf"]').value, attempt = form.querySelector('[name="a"]').value;
      form.classList.add('js');

      function answered(i) { return !!qs[i].querySelector('input:checked'); }
      function answeredCount() { return qs.filter(function (q, i) { return answered(i); }).length; }

      qs.forEach(function (q, i) {
        var b = document.createElement('button');
        b.type = 'button'; b.textContent = i + 1; b.title = 'Klausimas ' + (i + 1);
        b.addEventListener('click', function () { show(i); });
        nav.appendChild(b);
      });

      function show(i) {
        pos = i;
        qs.forEach(function (q, k) { q.classList.toggle('current', k === i); });
        [].forEach.call(nav.children, function (b, k) {
          b.classList.toggle('current', k === i);
          b.classList.toggle('done', answered(k));
        });
        document.getElementById('qCount').textContent = (i + 1) + ' / ' + total;
        var cat = document.getElementById('qCat');
        cat.textContent = qs[i].dataset.cat; cat.hidden = false;
        document.getElementById('qBar').style.width = ((i + 1) / total * 100) + '%';
        document.getElementById('qAnswered').textContent = 'Atsakyta ' + answeredCount() + ' iš ' + total;
        document.getElementById('qPrev').disabled = i === 0;
        document.getElementById('qNext').hidden = i === total - 1;
        document.getElementById('qFinish').hidden = i !== total - 1;
      }

      document.getElementById('qPrev').addEventListener('click', function () { if (pos > 0) { show(pos - 1); } });
      document.getElementById('qNext').addEventListener('click', function () { if (pos < total - 1) { show(pos + 1); } });

      // Pasirinkus atsakymą - išsaugoma serveryje
      form.addEventListener('change', function (ev) {
        var m = /^ans\[(\d+)\]$/.exec(ev.target.name || '');
        if (!m) { return; }
        show(pos);
        var data = new FormData();
        data.append('_csrf', csrf); data.append('action', 'save'); data.append('a', attempt);
        data.append('pos', m[1]); data.append('choice', ev.target.value);
        var note = document.getElementById('qSaveNote');
        // location.href, ne form.action - jį uždengia laukas name="action"
        fetch(location.href, {method: 'POST', body: data, headers: {'Accept': 'application/json'}, credentials: 'same-origin'})
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (res.finished) { location.reload(); return; }
            note.textContent = 'Atsakymai išsaugomi iš karto - uždarę puslapį galėsite tęsti.'; note.classList.remove('quiz-warn');
          })
          .catch(function () {
            note.textContent = 'Nepavyko išsaugoti - patikrinkite internetą. Atsakymai bus išsiųsti baigiant testą.'; note.classList.add('quiz-warn');
          });
      });

      form.addEventListener('submit', function (ev) {
        if (submitting) { return; }
        var left = total - answeredCount();
        var msg = left ? 'Liko neatsakyta klausimų: ' + left + '. Ar tikrai baigti testą?' : 'Baigti testą? Atsakymų pakeisti nebegalėsite.';
        if (!confirm(msg)) { ev.preventDefault(); return; }
        submitting = true;
      });

      // Laikmatis: baigus laiką testas išsiunčiamas automatiškai
      var timer = document.getElementById('qTimer');
      if (timer && form.dataset.remaining !== '') {
        var endAt = Date.now() + parseInt(form.dataset.remaining, 10) * 1000;
        var tick = function () {
          var sec = Math.max(0, Math.round((endAt - Date.now()) / 1000));
          timer.textContent = '⏱ ' + Math.floor(sec / 60) + ':' + ('0' + sec % 60).slice(-2);
          timer.classList.toggle('low', sec <= 300);
          if (sec === 0 && !submitting) { submitting = true; form.submit(); return; }
          setTimeout(tick, 1000);
        };
        tick();
      }

      // Tęsiant - nuo pirmo neatsakyto klausimo
      var first = qs.findIndex(function (q, i) { return !answered(i); });
      show(first === -1 ? total - 1 : first);
    })();
    </script>
    <?php
    page_end();
    exit;
}

// ---------- Pradžia ----------
page_start('Teisėjų testas', ['noindex' => true]);
?>
<div class="page-head">
  <div class="eyebrow">WTKU taisyklės</div>
  <h1 class="styled">Teisėjų testas</h1>
  <p>Klausimai iš WTKU Traditional Karate Competition Rules: bendrosios nuostatos, kumite, kata, en-bu, fuku-go ir teisėjavimo procedūros.</p>
  <?= staff_link('admin/teiseju-testas.php', '⚙ Valdyti testą') ?>
</div>

<?php if (!$canTake): ?>
  <div class="panel card"><p class="muted">Testą laiko treneriui pažymėti teisėjai. Jei norite laikyti - kreipkitės į trenerį.</p></div>
<?php
    page_end();
    exit;
endif;

$examOpen = referee_unfinished((int) $a['id'], 'exam');
$examStatus = referee_exam_status($a);
$practiceOpen = referee_unfinished((int) $a['id'], 'practice');
$categories = referee_categories();
$examCount = min((int) $s['exam_questions'], referee_topic_count());
$history = q_all('SELECT * FROM referee_attempts WHERE account_id = ? AND finished_at IS NOT NULL ORDER BY started_at DESC LIMIT 30', [$a['id']]);
?>
<div class="grid-2 quiz-start">
  <div class="panel card">
    <div class="row between"><h2 style="margin:0;">Egzaminas</h2><?php if ($s['exam_open']): ?><span class="badge badge-ok">vyksta</span><?php endif; ?></div>
    <?php if ($examOpen): ?>
      <p style="margin-top:12px;">Turite pradėtą egzaminą<?= $examOpen['deadline_at'] ? ' (baigsis ' . e(substr($examOpen['deadline_at'], 11, 5)) . ')' : '' ?>.</p>
      <p style="margin-top:14px;"><a class="btn btn-primary" href="?a=<?= (int) $examOpen['id'] ?>">Tęsti egzaminą →</a></p>
    <?php elseif ($examStatus['ok']): ?>
      <ul class="quiz-facts">
        <li>Klausimų: <strong><?= $examCount ?></strong><?= $s['proportional'] ? ' (iš visų skyrių)' : '' ?></li>
        <li>Išlaikymui reikia <strong><?= referee_required($examCount, (int) $s['pass_percent']) ?></strong> teisingų (<?= (int) $s['pass_percent'] ?> %)</li>
        <li><?= $s['time_limit'] ? 'Laikas: <strong>' . (int) $s['time_limit'] . ' min.</strong> - pasibaigus testas išsiunčiamas automatiškai' : 'Laikas neribojamas' ?></li>
        <?php if ($examStatus['left'] !== null): ?><li>Bandymų liko: <strong><?= (int) $examStatus['left'] ?></strong></li><?php endif; ?>
      </ul>
      <form method="post" onsubmit="return confirm('Pradėti egzaminą?<?= $s['time_limit'] ? ' Laikas pradės eiti iš karto.' : '' ?>')">
        <?= csrf_field() ?><input type="hidden" name="action" value="start"><input type="hidden" name="mode" value="exam">
        <button class="btn btn-primary" type="submit">Pradėti egzaminą</button>
      </form>
    <?php else: ?>
      <p class="muted" style="margin-top:12px;"><?= e($examStatus['reason']) ?></p>
    <?php endif; ?>
  </div>

  <div class="panel card">
    <h2>Mokomasis testas</h2>
    <?php if ($s['exam_open']): ?>
      <p class="muted">Kol vyksta egzaminas, mokomasis testas nepasiekiamas.</p>
    <?php elseif (!$s['practice']): ?>
      <p class="muted">Mokomasis testas šiuo metu išjungtas.</p>
    <?php else: ?>
      <p class="muted">Spręskite kiek norite kartų. Baigę matysite teisingus atsakymus ir vietą taisyklėse.</p>
      <?php if ($practiceOpen): ?>
        <p style="margin-top:10px;"><a href="?a=<?= (int) $practiceOpen['id'] ?>">Tęsti pradėtą testą →</a></p>
      <?php endif; ?>
      <form method="post" class="form" style="margin-top:14px;">
        <?= csrf_field() ?><input type="hidden" name="action" value="start"><input type="hidden" name="mode" value="practice">
        <label>Skyrius
          <select name="category">
            <option value="">Visi skyriai</option>
            <?php foreach ($categories as $cat => $n): ?><option value="<?= e($cat) ?>"><?= e($cat) ?> (<?= (int) $n ?>)</option><?php endforeach; ?>
          </select>
        </label>
        <div>
          <div class="consent-label" style="margin-bottom:8px;">Klausimų</div>
          <div class="seg">
            <?php foreach (REFEREE_PRACTICE_SIZES as $i => $n): ?>
              <label><input type="radio" name="size" value="<?= $n ?>" <?= $i === 1 ? 'checked' : '' ?>><span><?= $n ?></span></label>
            <?php endforeach; ?>
            <label><input type="radio" name="size" value="0"><span>Visi</span></label>
          </div>
        </div>
        <div><button class="btn btn-ghost" type="submit">Pradėti mokomąjį testą</button></div>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($history): ?>
  <div class="panel card" style="margin-top:20px;">
    <h2>Mano rezultatai</h2>
    <ul class="list">
      <?php foreach ($history as $h): ?>
        <li class="row between">
          <span>
            <span class="badge <?= $h['mode'] === 'exam' ? 'badge-members' : '' ?>"><?= $h['mode'] === 'exam' ? 'Egzaminas' : 'Mokomasis' ?></span>
            <strong><?= (int) $h['score'] ?> / <?= (int) $h['question_count'] ?></strong> (<?= referee_percent($h) ?> %)
            <span class="quiz-verdict small <?= $h['passed'] ? 'pass' : 'fail' ?>"><?= $h['passed'] ? '✓ išlaikyta' : '✕ neišlaikyta' ?></span>
            <div class="muted small"><?= e(fmt_date(substr($h['started_at'], 0, 10))) ?> <?= e(substr($h['started_at'], 11, 5)) ?><?= $h['category'] ? ' · ' . e($h['category']) : '' ?></div>
          </span>
          <a class="btn btn-ghost btn-sm" href="?a=<?= (int) $h['id'] ?>">Peržiūrėti</a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
<?php
page_end();
