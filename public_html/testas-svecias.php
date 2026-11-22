<?php
// Testas per nuorodą žmonėms be paskyros (Valdymas → Testai → testas → „Nuoroda be paskyros“).
// Žmogus įrašo vardą ir pavardę (privaloma), klubą ir el. paštą (nebūtina), sprendžia tuos pačius atsitiktinius klausimus kaip nariai
// ir iš karto mato rezultatą su teisingais atsakymais; įrašius el. paštą - rezultatas išsiunčiamas ir laišku.
// Saugoma tik tai, ką žmogus įrašė, ir balas (quiz_guest_attempts) - treneris mato testo puslapyje. Ištrinama po metų (app/cleanup.php).
require __DIR__ . '/app/bootstrap.php';

$k = (string) (get('k') ?: post('k'));
// Iš asmeninio egzamino puslapio (mano-egzaminas.php, &e=): testas veikia ir neatidarius dalijimosi, rezultatas - į protokolą
$examRow = exam_row_by_token((string) (get('e') ?: post('e')));
$quiz = quiz_by_share_token($k) ?: ($examRow && preg_match('/^[a-f0-9]{24}$/', $k) ? q_one('SELECT * FROM quizzes WHERE share_token = ? AND is_published = 1', [$k]) : null);
if (!$quiz) {
    not_found();
}
$qzId = (int) $quiz['id'];
$kid = (bool) $quiz['kids_mode'];
$self = 'testas-svecias.php?k=' . $k . ($examRow ? '&e=' . $examRow['token'] : '');
$errors = [];
$result = null;
$state = $_SESSION['guest_quiz'][$qzId] ?? null;

if (is_post()) {
    csrf_check();
    if (post('action') === 'start') {
        $name = trim(preg_replace('/\s+/u', ' ', mb_substr(post('name'), 0, 120)));
        $club = trim(preg_replace('/\s+/u', ' ', mb_substr(post('club'), 0, 120)));
        $email = mb_strtolower(trim(mb_substr(post('email'), 0, 190)));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = t('Patikrinkite el. pašto adresą (arba palikite tuščią).');
        }
        $_SESSION['guest_quiz_starts'] = array_values(array_filter((array) ($_SESSION['guest_quiz_starts'] ?? []), function ($ts) { return $ts > time() - 3600; }));
        if (mb_strlen($name) < 3) {
            $errors[] = t('Įrašykite vardą ir pavardę.');
        }
        if (post('website') !== '' || count($_SESSION['guest_quiz_starts']) >= 20) {
            $errors[] = t('Per daug bandymų. Pabandykite vėliau.');
        }
        if (!$errors) {
            $ids = array_slice(array_keys(quiz_pick_questions(quiz_questions_full($qzId))), 0, quiz_limit($quiz));
            if (!$ids) {
                $errors[] = t('Šiame teste dar nėra klausimų.');
            } else {
                $_SESSION['guest_quiz'][$qzId] = ['ids' => $ids, 'name' => $name, 'club' => $club, 'email' => $email, 'started' => time()];
                $_SESSION['guest_quiz_starts'][] = time();
                redirect($self);
            }
        }
    } elseif (post('action') === 'finish' && $state) {
        $limit = (int) ($quiz['time_limit'] ?? 0);
        $late = $limit > 0 && time() > $state['started'] + 60 * $limit + 30;
        $chosen = $late ? [] : array_map('intval', (array) ($_POST['a'] ?? []));
        $qs = quiz_questions_full($qzId, $state['ids']);
        $score = 0;
        $review = [];
        foreach ($state['ids'] as $id) {
            if (!isset($qs[$id])) {
                continue;
            }
            $ok = false;
            foreach ($qs[$id]['answers'] as $an) {
                if ((int) $an['id'] === ($chosen[$id] ?? -1) && $an['is_correct']) {
                    $ok = true;
                }
            }
            $score += $ok ? 1 : 0;
            $review[] = ['q' => $qs[$id], 'chosen' => $chosen[$id] ?? null, 'ok' => $ok];
        }
        $total = count($review);
        $passed = $total > 0 && 100 * $score >= (int) $quiz['pass_percent'] * $total;
        $email = (string) ($state['email'] ?? '');
        if ($examRow && $total && !$late) {
            // Egzamino protokole - stulpelis „Testas“ (geriausias rezultatas)
            $pct = (int) round(100 * $score / $total);
            $prev = (int) preg_replace('/\D.*$/u', '', ltrim((string) $examRow['test']));
            if ($examRow['test'] === null || $examRow['test'] === '' || $pct >= $prev) {
                q('UPDATE exam_protocol_rows SET test = ? WHERE id = ?', [$pct . ' %' . ($passed ? ' ✓' : ''), (int) $examRow['id']]);
            }
        }
        q('INSERT INTO quiz_guest_attempts (quiz_id, name, club, email, score, total, passed) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$qzId, $state['name'], $state['club'] !== '' ? $state['club'] : null, $email !== '' ? $email : null, $score, $total, $passed ? 1 : 0]);
        $mailed = false;
        if ($email !== '' && !$late) {
            // Rezultatas laišku: balas ir klausimai, kur suklysta, su teisingais atsakymais
            $body = $quiz['title'] . "\n" . $state['name'] . "\n\n" . t('Rezultatas:') . ' ' . ($total ? (int) round(100 * $score / $total) : 0) . ' % (' . $score . ' / ' . $total . ') - '
                . ($passed ? t('testas išlaikytas.') : t('testas neišlaikytas.'));
            $wrong = array_filter($review, function ($r) { return !$r['ok']; });
            if ($wrong) {
                $body .= "\n\n" . t('Klaidos ir teisingi atsakymai:');
                foreach ($wrong as $r) {
                    $ok = array_values(array_filter($r['q']['answers'], function ($an) { return $an['is_correct']; }));
                    $body .= "\n\n- " . $r['q']['body'] . "\n  ✓ " . ($ok ? $ok[0]['body'] : '');
                }
            }
            $mailed = send_mail($email, t('Testo rezultatas:') . ' ' . $quiz['title'], $body);
        }
        unset($_SESSION['guest_quiz'][$qzId]);
        $result = ['score' => $score, 'total' => $total, 'passed' => $passed, 'late' => $late, 'review' => $review, 'name' => $state['name'], 'mailed' => $mailed ? $email : ''];
        $state = null;
    }
}
if (get('naujas') !== '' && !is_post()) {
    unset($_SESSION['guest_quiz'][$qzId]);
    $state = null;
}

page_start($quiz['title'], ['noindex' => true, 'narrow' => true, 'bare' => !current_account()]);   // svečias - be svetainės meniu

// ---------- Rezultatas ----------
if ($result):
    $pct = $result['total'] ? (int) round(100 * $result['score'] / $result['total']) : 0; ?>
  <div class="panel card result-card <?= $result['passed'] ? 'passed' : 'failed' ?> <?= $kid ? 'kid' : '' ?>">
    <div class="kicker"><?= e($quiz['title']) ?> · <?= e($result['name']) ?></div>
    <div class="result-big"><?= $pct ?> %</div>
    <p class="result-msg"><?= $result['late'] ? te('Laikas baigėsi - atsakymai pateikti per vėlai ir neįskaityti.')
        : ($result['passed'] ? ($kid ? t('🎉 Šaunuolis! Testas išlaikytas!') : t('✓ Testas išlaikytas.'))
        : (t('Testas neišlaikytas: reikia bent') . ' ' . (int) $quiz['pass_percent'] . ' %.')) ?></p>
    <p class="muted small"><?= te('Teisingai') ?> <?= (int) $result['score'] ?> <?= te('iš') ?> <?= (int) $result['total'] ?></p>
    <?php if ($result['mailed']): ?><p class="small"><?= te('Rezultatas išsiųstas el. paštu:') ?> <strong><?= e($result['mailed']) ?></strong></p><?php endif; ?>
    <div class="row" style="gap:8px; justify-content:center; margin-top:10px;">
      <a class="btn btn-primary" href="<?= e(url($self . '&naujas=1')) ?>"><?= te('Spręsti dar kartą') ?></a>
    </div>
  </div>
  <h2 style="margin:24px 0 12px;"><?= te('Atsakymai') ?></h2>
  <?php foreach ($result['review'] as $n => $r): $q = $r['q']; ?>
    <div class="panel card review <?= $r['ok'] ? 'ok' : 'bad' ?>">
      <div class="kicker"><?= $n + 1 ?> <?= te('klausimas ·') ?> <?= $r['ok'] ? t('✓ teisingai') : t('✕ neteisingai') ?></div>
      <p><strong><?= nl2br(e($q['body'])) ?></strong></p>
      <ul class="review-answers">
        <?php foreach ($q['answers'] as $an): $mine = (int) $an['id'] === (int) $r['chosen']; ?>
          <li class="<?= $an['is_correct'] ? 'correct' : '' ?> <?= $mine ? 'mine' : '' ?>"><?= e($an['body']) ?><?= $mine ? (' <span class="small">' . t('(tavo)') . '</span>') : '' ?></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($q['explanation']): ?><p class="small muted"><?= nl2br(e($q['explanation'])) ?></p><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if ($result['passed']): ?><script><?= confetti_js() ?></script><?php endif; ?>
<?php
// ---------- Testas ----------
elseif ($state):
    $qs = quiz_questions_full($qzId, $state['ids']);
    $ids = array_values(array_filter($state['ids'], function ($id) use ($qs) { return isset($qs[$id]); }));
    $limit = (int) ($quiz['time_limit'] ?? 0);
    $left = $limit > 0 ? max(0, $state['started'] + 60 * $limit - time()) : null; ?>
  <div class="page-head">
    <div class="eyebrow"><?= te('Testas ·') ?> <?= e($state['name']) ?></div>
    <h1 class="styled"><?= e($quiz['title']) ?></h1>
    <p class="muted"><?= count($ids) ?> <?= te('klausimų. Kad išlaikytum, reikia bent') ?> <?= (int) $quiz['pass_percent'] ?> <?= te('% teisingų atsakymų. Atsakymus pamatysi pabaigoje.') ?></p>
  </div>
  <?php if ($left !== null): ?><div class="quiz-timer" id="quizTimer" data-left="<?= $left ?>" title="<?= te('Liko laiko') ?>">⏱ <?= sprintf('%d:%02d', intdiv($left, 60), $left % 60) ?></div><?php endif; ?>
  <form method="post" class="quiz-form <?= $kid ? 'kid' : '' ?>" id="quizForm">
    <?= csrf_field() ?><input type="hidden" name="k" value="<?= e($k) ?>"><?= $examRow ? '<input type="hidden" name="e" value="' . e($examRow['token']) . '">' : '' ?><input type="hidden" name="action" value="finish">
    <?php foreach ($ids as $n => $id): $q = $qs[$id]; $ans = $q['answers']; shuffle($ans); ?>
      <fieldset class="panel card quiz-q">
        <legend class="kicker"><?= $n + 1 ?> / <?= count($ids) ?></legend>
        <?php if ($q['image']): ?><img class="q-image" src="<?= e(quiz_image_url($q['image'])) ?>" alt=""><?php endif; ?>
        <?php if ($q['youtube_id']): ?><?= youtube_embed($q['youtube_id']) ?><?php endif; ?>
        <p class="q-body"><?= nl2br(e($q['body'])) ?></p>
        <div class="q-answers">
          <?php foreach ($ans as $an): ?>
            <label class="q-answer"><input type="radio" name="a[<?= $id ?>]" value="<?= (int) $an['id'] ?>"><span><?= e($an['body']) ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
    <?php endforeach; ?>
    <div class="row" style="justify-content:space-between; align-items:center;">
      <span class="small muted" id="answered"></span>
      <button class="btn btn-primary" type="submit"><?= te('Baigti testą') ?></button>
    </div>
  </form>
  <script>
  (function () {
    var f = document.getElementById('quizForm'), total = <?= count($ids) ?>;
    function upd() { document.getElementById('answered').textContent = 'Atsakyta ' + f.querySelectorAll('input[type=radio]:checked').length + ' iš ' + total; }
    f.addEventListener('change', upd); upd();
    f.addEventListener('submit', function (e) {
      var left = total - f.querySelectorAll('input[type=radio]:checked').length;
      if (left > 0 && !f.dataset.auto && !confirm('Neatsakyta į ' + left + ' klaus. Vis tiek baigti?')) e.preventDefault();
    });
    var timer = document.getElementById('quizTimer');
    if (timer) {
      var endAt = Date.now() + parseInt(timer.dataset.left, 10) * 1000;
      (function tick() {
        var s = Math.max(0, Math.round((endAt - Date.now()) / 1000));
        timer.textContent = '⏱ ' + Math.floor(s / 60) + ':' + ('0' + s % 60).slice(-2);
        timer.classList.toggle('low', s <= 300);
        if (s === 0) { f.dataset.auto = '1'; f.submit(); return; }
        setTimeout(tick, 1000);
      })();
    }
  })();
  </script>
<?php
// ---------- Pradžia: vardas ----------
else:
    $count = count(quiz_pick_questions(quiz_questions_full($qzId))); ?>
  <div class="page-head">
    <div class="eyebrow"><?= te('Testas') ?></div>
    <h1 class="styled"><?= e($quiz['title']) ?></h1>
    <?php if ($quiz['description']): ?><p><?= e($quiz['description']) ?></p><?php endif; ?>
    <p class="muted"><?= min($count, quiz_limit($quiz)) ?> <?= te('klausimų, reikia bent') ?> <?= (int) $quiz['pass_percent'] ?> % <?= te('teisingų') ?><?= !empty($quiz['time_limit']) ? ' · ' . (int) $quiz['time_limit'] . ' ' . t('min.') : '' ?>.
      <?= te('Atsakymus ir rezultatą pamatysi pabaigoje.') ?></p>
  </div>
  <?= form_errors($errors) ?>
  <form method="post" class="panel card form" id="guestStart">
    <?= csrf_field() ?><input type="hidden" name="k" value="<?= e($k) ?>"><?= $examRow ? '<input type="hidden" name="e" value="' . e($examRow['token']) . '">' : '' ?><input type="hidden" name="action" value="start">
    <div style="position:absolute; left:-9999px;" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
    <div class="form-row">
      <label><?= te('Vardas ir pavardė') ?> <input type="text" name="name" maxlength="120" required autocomplete="name" value="<?= e(post('name') ?: ($examRow['name'] ?? '')) ?>"></label>
      <label><?= te('Klubas') ?> <span class="hint"><?= te('nebūtina') ?></span><input type="text" name="club" maxlength="120" value="<?= e(post('club')) ?>"></label>
    </div>
    <label><?= te('El. paštas') ?> <span class="hint"><?= te('nebūtina - atsiųsime rezultatą') ?></span><input type="email" name="email" maxlength="190" autocomplete="email" value="<?= e(post('email')) ?>"></label>
    <p class="small muted" style="margin:0;"><?= te('Treneris mato tik tai, ką įrašei, ir rezultatą; tai ištrinama po metų.') ?></p>
    <div><button class="btn btn-primary" type="submit"<?= $count ? '' : ' disabled' ?>><?= te('Pradėti testą') ?></button></div>
  </form>
<?php endif;
page_end();
