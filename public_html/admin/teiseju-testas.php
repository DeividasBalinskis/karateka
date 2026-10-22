<?php
// Teisėjų testas: egzamino atidarymas / uždarymas, rezultatai, kas gali laikyti, klausimai ir nustatymai (keičia administratorius)
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

const REFEREE_TABS = ['rezultatai' => 'Rezultatai', 'dalyviai' => 'Kas gali laikyti', 'klausimai' => 'Klausimai', 'nustatymai' => 'Nustatymai'];
$tab = isset(REFEREE_TABS[get('tab')]) ? get('tab') : 'rezultatai';
$self = 'admin/teiseju-testas.php' . ($tab !== 'rezultatai' ? '?tab=' . $tab : '');
$errors = [];

if (is_post()) {
    csrf_check();
    $action = post('action');

    if ($action === 'open_exam') {
        referee_set_exam_open(true);
        flash('ok', 'Egzaminas atidarytas. Bandymai skaičiuojami nuo dabar.');
    }
    if ($action === 'close_exam') {
        referee_set_exam_open(false);
        flash('ok', 'Egzaminas uždarytas. Nebaigti testai užbaigti su jau pažymėtais atsakymais.');
    }

    if ($action === 'finish_attempt') {
        referee_finish_attempt((int) post('id'));
        flash('ok', 'Testas užbaigtas su jau pažymėtais atsakymais.');
        redirect('admin/teiseju-testas.php?a=' . (int) post('id'));
    }
    if ($action === 'delete_attempt') {
        q('DELETE FROM referee_attempts WHERE id = ?', [(int) post('id')]);
        flash('ok', 'Rezultatas ištrintas. Jei egzaminas atidarytas, šis žmogus galės laikyti dar kartą.');
        redirect('admin/teiseju-testas.php');
    }

    if ($action === 'add_access') {
        $acc = q_one('SELECT * FROM accounts WHERE id = ? AND status = "active"', [(int) post('account_id')]);
        if ($acc) {
            q('INSERT IGNORE INTO referee_access (account_id, added_by) VALUES (?, ?)', [$acc['id'], $me['id']]);
            flash('ok', "Pridėta: {$acc['first_name']} {$acc['last_name']}");
        }
    }
    if ($action === 'remove_access') {
        q('DELETE FROM referee_access WHERE account_id = ?', [(int) post('account_id')]);
        flash('ok', 'Pašalinta iš sąrašo.');
    }

    if ($action === 'settings' && is_admin()) {
        $int = function (string $field, int $min, int $max): int {
            return max($min, min($max, (int) post($field)));
        };
        save_referee_settings([
            'exam_questions' => $int('exam_questions', 1, max(1, referee_topic_count())),
            'pass_percent'   => $int('pass_percent', 1, 100),
            'time_limit'     => $int('time_limit', 0, 600),
            'exam_attempts'  => $int('exam_attempts', 0, 99),
            'proportional'   => !empty($_POST['proportional']) ? 1 : 0,
            'show_answers'   => !empty($_POST['show_answers']) ? 1 : 0,
            'practice'       => !empty($_POST['practice']) ? 1 : 0,
            'access'         => post('access') === 'members' ? 'members' : 'selected',
        ]);
        flash('ok', 'Nustatymai išsaugoti. Jau pradėtiems testams jie netaikomi.');
    }

    if ($action === 'toggle_question' && is_admin()) {
        q('UPDATE referee_questions SET is_active = 1 - is_active WHERE id = ?', [(int) post('id')]);
        redirect('admin/teiseju-testas.php?tab=klausimai' . (get('cat') !== '' ? '&cat=' . rawurlencode(get('cat')) : '') . '#q' . (int) post('id'));
    }

    if ($action === 'question' && is_admin()) {
        $id = (int) post('id');
        $f = [];
        foreach (['category', 'topic', 'question', 'correct', 'wrong1', 'wrong2', 'wrong3', 'source'] as $k) {
            $f[$k] = post($k);
        }
        foreach (['category' => 'skyrių', 'topic' => 'temą', 'question' => 'klausimą', 'correct' => 'teisingą atsakymą', 'wrong1' => 'bent vieną neteisingą atsakymą'] as $k => $label) {
            if ($f[$k] === '') {
                $errors[] = "Įveskite $label.";
            }
        }
        if ($f['wrong2'] === '' && $f['wrong3'] !== '') {   // tuščias 2-as, užpildytas 3-ias - perkeliame
            [$f['wrong2'], $f['wrong3']] = [$f['wrong3'], ''];
        }
        if (!$errors) {
            $params = [$f['category'], $f['topic'], $f['question'], $f['correct'], $f['wrong1'], $f['wrong2'] ?: null, $f['wrong3'] ?: null, $f['source'] ?: null, !empty($_POST['is_active']) ? 1 : 0];
            if ($id) {
                q('UPDATE referee_questions SET category = ?, topic = ?, question = ?, correct = ?, wrong1 = ?, wrong2 = ?, wrong3 = ?, source = ?, is_active = ? WHERE id = ?', array_merge($params, [$id]));
            } else {
                q('INSERT INTO referee_questions (category, topic, question, correct, wrong1, wrong2, wrong3, source, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', $params);
                $id = (int) db()->lastInsertId();
            }
            flash('ok', 'Klausimas išsaugotas.');
            redirect('admin/teiseju-testas.php?tab=klausimai&cat=' . rawurlencode($f['category']) . '#q' . $id);
        }
    }

    if (!$errors) {
        redirect($self);
    }
}

referee_finish_expired();
$s = referee_settings();
$topicCount = referee_topic_count();
$examCount = min((int) $s['exam_questions'], $topicCount);
$accessCount = (int) q_value('SELECT COUNT(*) FROM referee_access ra JOIN accounts a ON a.id = ra.account_id WHERE a.status = "active"');

// ---------- Vieno bandymo peržiūra ----------
if ($attemptId = (int) get('a')) {
    $attempt = referee_attempt($attemptId);
    if (!$attempt) {
        not_found();
    }
    $who = q_one('SELECT first_name, last_name, email FROM accounts WHERE id = ?', [$attempt['account_id']]);
    page_start('Teisėjų testas: rezultatas', ['admin' => true]);
    ?>
    <div class="quiz-wrap">
      <a class="small" href="<?= url('admin/teiseju-testas.php') ?>">← Visi rezultatai</a>
      <h1 class="styled" style="margin-top:8px;"><?= e($who['first_name'] . ' ' . $who['last_name']) ?></h1>
      <?php if ($attempt['finished_at'] === null): $answered = (int) q_value('SELECT COUNT(*) FROM referee_answers WHERE attempt_id = ? AND chosen IS NOT NULL', [$attemptId]); ?>
        <div class="panel card">
          <p>Testas dar sprendžiamas: atsakyta <strong><?= $answered ?></strong> iš <?= (int) $attempt['question_count'] ?>.
            <?= $attempt['deadline_at'] ? 'Laikas baigsis ' . e(substr($attempt['deadline_at'], 11, 5)) . '.' : '' ?></p>
          <form method="post" style="margin-top:14px;" onsubmit="return confirm('Užbaigti šį testą dabar? Bus įvertinti jau pažymėti atsakymai.')">
            <?= csrf_field() ?><input type="hidden" name="action" value="finish_attempt"><input type="hidden" name="id" value="<?= $attemptId ?>">
            <button class="btn btn-ghost btn-sm" type="submit">Užbaigti dabar</button>
          </form>
        </div>
      <?php else: ?>
        <?= render_referee_result($attempt, true) ?>
      <?php endif; ?>
      <form method="post" onsubmit="return confirm('Ištrinti šį rezultatą? To atšaukti negalima.')">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete_attempt"><input type="hidden" name="id" value="<?= $attemptId ?>">
        <button class="btn btn-danger btn-sm" type="submit">Ištrinti rezultatą</button>
        <span class="muted small">Ištrynus egzamino rezultatą, žmogus vėl galės laikyti (jei egzaminas atidarytas).</span>
      </form>
    </div>
    <?php
    page_end();
    exit;
}

page_start('Teisėjų testas', ['admin' => true]);
?>
<div class="page-head row between" style="align-items:flex-end;">
  <div>
    <h1 class="styled">Teisėjų testas</h1>
    <p>WTKU taisyklių žinių testas: <?= $topicCount ?> temų, <?= (int) q_value('SELECT COUNT(*) FROM referee_questions WHERE is_active = 1') ?> klausimų.</p>
  </div>
  <a class="btn btn-ghost btn-sm" href="<?= url('teiseju-testas.php') ?>">Nario vaizdas →</a>
</div>

<div class="panel card quiz-status <?= $s['exam_open'] ? 'open' : '' ?>">
  <div>
    <div class="kicker">Egzaminas</div>
    <div class="quiz-status-title"><?= $s['exam_open'] ? '● Atidarytas' : 'Uždarytas' ?>
      <?php if ($s['exam_open'] && $s['exam_opened_at']): ?><span class="muted small">nuo <?= e(fmt_date(substr($s['exam_opened_at'], 0, 10))) ?> <?= e(substr($s['exam_opened_at'], 11, 5)) ?></span><?php endif; ?>
    </div>
    <div class="muted small">
      <?= $examCount ?> klaus. · išlaikymui <?= referee_required($examCount, (int) $s['pass_percent']) ?> teisingų (<?= (int) $s['pass_percent'] ?> %) ·
      <?= $s['time_limit'] ? (int) $s['time_limit'] . ' min.' : 'be laiko limito' ?> ·
      <?= $s['exam_attempts'] ? 'bandymų: ' . (int) $s['exam_attempts'] : 'bandymų neribota' ?> ·
      laiko: <?= $s['access'] === 'members' ? 'visi nariai' : 'pažymėti (' . $accessCount . ') ir treneriai' ?>
    </div>
  </div>
  <form method="post" onsubmit="return confirm('<?= $s['exam_open'] ? 'Uždaryti egzaminą? Nebaigti testai bus užbaigti su jau pažymėtais atsakymais.' : 'Atidaryti egzaminą? Kas gali laikyti, galės jį pradėti iš karto.' ?>')">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $s['exam_open'] ? 'close_exam' : 'open_exam' ?>">
    <button class="btn <?= $s['exam_open'] ? 'btn-danger' : 'btn-primary' ?>" type="submit"><?= $s['exam_open'] ? 'Uždaryti egzaminą' : 'Atidaryti egzaminą' ?></button>
  </form>
</div>

<div class="top-tabs" style="margin-bottom:20px;">
  <?php foreach (REFEREE_TABS as $key => $label): ?>
    <a href="<?= url('admin/teiseju-testas.php' . ($key !== 'rezultatai' ? '?tab=' . $key : '')) ?>" class="<?= $tab === $key ? 'active' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'rezultatai'):
    $exams = q_all('SELECT t.*, a.first_name, a.last_name,
                           (SELECT COUNT(*) FROM referee_answers ra WHERE ra.attempt_id = t.id AND ra.chosen IS NOT NULL) AS answered
                      FROM referee_attempts t JOIN accounts a ON a.id = t.account_id
                     WHERE t.mode = "exam" ORDER BY t.started_at DESC LIMIT 300');
    $since = $s['exam_opened_at'];
    $current = $since ? array_filter($exams, function ($r) use ($since) { return $r['started_at'] >= $since; }) : [];
    $older = $since ? array_filter($exams, function ($r) use ($since) { return $r['started_at'] < $since; }) : $exams;
    $practice = q_all('SELECT a.id, a.first_name, a.last_name, COUNT(*) AS n, MAX(t.started_at) AS last_at,
                              MAX(ROUND(100 * t.score / t.question_count)) AS best, MAX(t.id) AS last_id
                         FROM referee_attempts t JOIN accounts a ON a.id = t.account_id
                        WHERE t.mode = "practice" AND t.finished_at IS NOT NULL
                        GROUP BY a.id, a.first_name, a.last_name ORDER BY last_at DESC LIMIT 100');
    $examTable = function (array $rows): void { ?>
      <table class="table cards">
        <thead><tr><th>Vardas</th><th>Laikė</th><th>Rezultatas</th><th>Trukmė</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong></td>
              <td><?= e(fmt_date(substr($r['started_at'], 0, 10))) ?> <?= e(substr($r['started_at'], 11, 5)) ?></td>
              <td>
                <?php if ($r['finished_at'] === null): ?>
                  <span class="badge badge-warn">sprendžia</span> <span class="muted small">atsakyta <?= (int) $r['answered'] ?> / <?= (int) $r['question_count'] ?></span>
                <?php else: ?>
                  <strong><?= (int) $r['score'] ?> / <?= (int) $r['question_count'] ?></strong> (<?= referee_percent($r) ?> %)
                  <span class="quiz-verdict small <?= $r['passed'] ? 'pass' : 'fail' ?>"><?= $r['passed'] ? '✓ išlaikė' : '✕ neišlaikė' ?></span>
                <?php endif; ?>
              </td>
              <td class="muted small"><?= e(referee_duration($r)) ?></td>
              <td><a class="btn btn-ghost btn-sm" href="?a=<?= (int) $r['id'] ?>">Atsakymai</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php };
?>
  <?php if ($since): ?>
    <div class="panel card">
      <h2>Egzaminas nuo <?= e(fmt_date(substr($since, 0, 10))) ?> <?= e(substr($since, 11, 5)) ?></h2>
      <?php if ($current): ?><?php $examTable($current); ?><?php else: ?><p class="muted">Šį egzaminą dar niekas nelaikė.</p><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($older || !$since): ?>
    <div class="panel card">
      <h2><?= $since ? 'Ankstesni egzaminai' : 'Egzaminai' ?></h2>
      <?php if ($older): ?><?php $examTable($older); ?><?php else: ?><p class="muted">Egzamino dar niekas nelaikė. Atidarykite egzaminą, kai teisėjai bus pasiruošę.</p><?php endif; ?>
    </div>
  <?php endif; ?>
  <div class="panel card">
    <h2>Mokomieji testai</h2>
    <?php if (!$practice): ?>
      <p class="muted">Mokomųjų testų dar niekas nesprendė.</p>
    <?php else: ?>
      <table class="table cards">
        <thead><tr><th>Vardas</th><th>Kartų</th><th>Geriausias</th><th>Paskutinis</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($practice as $p): ?>
            <tr>
              <td><strong><?= e($p['first_name'] . ' ' . $p['last_name']) ?></strong></td>
              <td><?= (int) $p['n'] ?></td>
              <td><?= (int) $p['best'] ?> %</td>
              <td><?= e(fmt_date(substr($p['last_at'], 0, 10))) ?></td>
              <td><a class="btn btn-ghost btn-sm" href="?a=<?= (int) $p['last_id'] ?>">Paskutinis</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

<?php elseif ($tab === 'dalyviai'):
    $allowed = q_all('SELECT a.id, a.first_name, a.last_name, a.email, a.status, ra.created_at
                        FROM referee_access ra JOIN accounts a ON a.id = ra.account_id ORDER BY a.last_name, a.first_name');
    $others = q_all('SELECT id, first_name, last_name, email FROM accounts
                      WHERE status = "active" AND role = "member" AND id NOT IN (SELECT account_id FROM referee_access) ORDER BY last_name, first_name');
?>
  <?php if ($s['access'] === 'members'): ?>
    <div class="flash flash-info">Dabar testą gali laikyti <strong>visi patvirtinti nariai</strong> (Nustatymai → „Kas gali laikyti“). Šis sąrašas galioja, kai pasirinkta „Tik pažymėti“.</div>
  <?php endif; ?>
  <div class="grid-2">
    <div class="panel card">
      <h2>Pažymėti teisėjai</h2>
      <p class="muted small">Treneriai ir administratoriai gali laikyti visada - jų pridėti nereikia.</p>
      <?php if (!$allowed): ?><p class="muted" style="margin-top:10px;">Sąrašas tuščias.</p><?php endif; ?>
      <ul class="list">
        <?php foreach ($allowed as $r): ?>
          <li class="row between">
            <span><strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong><?= $r['status'] !== 'active' ? ' <span class="badge badge-warn">paskyra neaktyvi</span>' : '' ?>
              <div class="muted small"><?= e($r['email']) ?></div></span>
            <form method="post" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="action" value="remove_access"><input type="hidden" name="account_id" value="<?= (int) $r['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit" aria-label="Pašalinti">✕</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <form method="post" class="panel card form">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_access">
      <h2>Pridėti</h2>
      <label>Paskyra <span class="hint">patvirtinti nariai</span>
        <select name="account_id" required>
          <option value="">— pasirinkite —</option>
          <?php foreach ($others as $o): ?><option value="<?= (int) $o['id'] ?>"><?= e($o['last_name'] . ' ' . $o['first_name'] . ' (' . $o['email'] . ')') ?></option><?php endforeach; ?>
        </select>
      </label>
      <div><button class="btn btn-primary" type="submit">Pridėti</button></div>
    </form>
  </div>

<?php elseif ($tab === 'klausimai'):
    $edit = get('edit') ?: post('edit');
    if ($edit !== '' && is_admin()):
        $q = $edit === 'new'
            ? ['id' => 0, 'category' => get('cat'), 'topic' => '', 'question' => '', 'correct' => '', 'wrong1' => '', 'wrong2' => '', 'wrong3' => '', 'source' => '', 'is_active' => 1]
            : q_one('SELECT * FROM referee_questions WHERE id = ?', [(int) $edit]);
        if (!$q) {
            not_found();
        }
        if ($errors) {
            $q = array_merge($q, $_POST, ['is_active' => !empty($_POST['is_active'])]);
        }
        $used = $q['id'] ? (int) q_value('SELECT COUNT(*) FROM referee_answers WHERE question_id = ?', [$q['id']]) : 0;
?>
    <a class="small" href="<?= url('admin/teiseju-testas.php?tab=klausimai' . ($q['category'] ? '&cat=' . rawurlencode($q['category']) : '')) ?>">← Visi klausimai</a>
    <h2 style="margin-top:10px;"><?= $q['id'] ? 'Redaguoti klausimą' : 'Naujas klausimas' ?></h2>
    <?= form_errors($errors) ?>
    <form method="post" class="panel card form" action="<?= url('admin/teiseju-testas.php?tab=klausimai') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="question">
      <input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
      <input type="hidden" name="edit" value="<?= e($edit) ?>">
      <div class="form-row">
        <label>Skyrius
          <input type="text" name="category" value="<?= e($q['category']) ?>" list="cats" maxlength="60" required>
          <datalist id="cats"><?php foreach (array_keys(referee_categories()) as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
        </label>
        <label>Šaltinis <span class="hint">vieta taisyklėse</span>
          <input type="text" name="source" value="<?= e($q['source']) ?>" maxlength="120" placeholder="pvz. II dalis, 1 str., p. 12">
        </label>
      </div>
      <label>Tema <span class="hint">klausimai su ta pačia tema - tas pats klausimas kitais žodžiais; teste būna tik vienas iš jų</span>
        <input type="text" name="topic" value="<?= e($q['topic']) ?>" list="topics" maxlength="190" required>
        <datalist id="topics"><?php foreach (q('SELECT DISTINCT topic FROM referee_questions ORDER BY topic')->fetchAll(PDO::FETCH_COLUMN) as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist>
      </label>
      <label>Klausimas <textarea name="question" rows="3" style="min-height:80px;" maxlength="500" required><?= e($q['question']) ?></textarea></label>
      <label>✓ Teisingas atsakymas <input type="text" name="correct" value="<?= e($q['correct']) ?>" maxlength="255" required></label>
      <label>Neteisingas atsakymas 1 <input type="text" name="wrong1" value="<?= e($q['wrong1']) ?>" maxlength="255" required></label>
      <div class="form-row">
        <label>Neteisingas atsakymas 2 <span class="hint">nebūtina</span><input type="text" name="wrong2" value="<?= e($q['wrong2']) ?>" maxlength="255"></label>
        <label>Neteisingas atsakymas 3 <span class="hint">nebūtina</span><input type="text" name="wrong3" value="<?= e($q['wrong3']) ?>" maxlength="255"></label>
      </div>
      <p class="hint">Atsakymų tvarka teste sumaišoma atsitiktinai.<?= $used ? " Šis klausimas jau buvo $used testuose - pakeitimas matysis ir jų peržiūroje." : '' ?></p>
      <label class="check"><input type="checkbox" name="is_active" value="1" <?= $q['is_active'] ? 'checked' : '' ?>><span>Naudojamas testuose</span></label>
      <div><button class="btn btn-primary" type="submit">Išsaugoti</button></div>
    </form>
<?php
    else:
        $cats = referee_categories();
        $cat = get('cat');
        $search = get('q');
        $sql = 'SELECT * FROM referee_questions WHERE 1';
        $params = [];
        if ($cat !== '') {
            $sql .= ' AND category = ?';
            $params[] = $cat;
        }
        if ($search !== '') {
            $sql .= ' AND CONCAT_WS(" ", question, correct, wrong1, wrong2, wrong3, topic, source) LIKE ?';
            $params[] = "%$search%";
        }
        $questions = q_all($sql . ' ORDER BY is_active DESC, id', $params);
        $stats = q('SELECT ra.question_id, COUNT(*) AS n, SUM(ra.chosen IS NOT NULL AND SUBSTRING(ra.answer_order, ra.chosen + 1, 1) = "0") AS ok
                      FROM referee_answers ra JOIN referee_attempts t ON t.id = ra.attempt_id
                     WHERE t.finished_at IS NOT NULL GROUP BY ra.question_id')->fetchAll(PDO::FETCH_UNIQUE);
?>
    <div class="row between" style="margin-bottom:14px; align-items:flex-start;">
      <div class="top-tabs" style="margin:0;">
        <a href="?tab=klausimai" class="<?= $cat === '' ? 'active' : '' ?>">Visi</a>
        <?php foreach ($cats as $c => $n): ?><a href="?tab=klausimai&amp;cat=<?= e(rawurlencode($c)) ?>" class="<?= $cat === $c ? 'active' : '' ?>"><?= e($c) ?></a><?php endforeach; ?>
      </div>
      <?php if (is_admin()): ?><a class="btn btn-primary btn-sm" href="?tab=klausimai&amp;edit=new<?= $cat !== '' ? '&amp;cat=' . e(rawurlencode($cat)) : '' ?>">+ Naujas klausimas</a><?php endif; ?>
    </div>
    <form method="get" class="panel card form inline-fields">
      <input type="hidden" name="tab" value="klausimai"><?php if ($cat !== ''): ?><input type="hidden" name="cat" value="<?= e($cat) ?>"><?php endif; ?>
      <label>Paieška <input type="search" name="q" value="<?= e($search) ?>" placeholder="Žodis iš klausimo ar atsakymo"></label>
      <button class="btn btn-ghost" type="submit">Ieškoti</button>
    </form>
    <div class="panel card">
      <p class="muted small" style="margin-bottom:6px;">Rodoma: <?= count($questions) ?>. Procentas - kiek kartų į klausimą atsakyta teisingai (iš baigtų testų): labai mažas gali reikšti neaiškų klausimą.</p>
      <?php if (!$questions): ?><p class="muted">Nieko nerasta.</p><?php endif; ?>
      <ul class="list quiz-bank">
        <?php foreach ($questions as $q): $st = $stats[$q['id']] ?? null; ?>
          <li class="row between <?= $q['is_active'] ? '' : 'off' ?>" id="q<?= (int) $q['id'] ?>">
            <span>
              <strong><?= e($q['question']) ?></strong>
              <?php if (!$q['is_active']): ?><span class="badge badge-warn">išjungtas</span><?php endif; ?>
              <div class="right">✓ <?= e($q['correct']) ?></div>
              <div class="muted small"><?= e(implode(' · ', array_filter([$q['wrong1'], $q['wrong2'], $q['wrong3']]))) ?></div>
              <div class="muted small"><?= e($q['category']) ?> · <?= e($q['topic']) ?><?= $q['source'] ? ' · ' . e($q['source']) : '' ?>
                <?php if ($st): ?> · <span class="<?= $st['ok'] / $st['n'] < 0.5 ? 'quiz-low' : '' ?>">teisingai <?= (int) round(100 * $st['ok'] / $st['n']) ?> % (<?= (int) $st['n'] ?>)</span><?php endif; ?></div>
            </span>
            <?php if (is_admin()): ?>
              <span class="row" style="gap:6px; flex-wrap:nowrap;">
                <a class="btn btn-ghost btn-sm" href="?tab=klausimai&amp;edit=<?= (int) $q['id'] ?>">Keisti</a>
                <form method="post" class="inline-form" action="<?= url('admin/teiseju-testas.php?tab=klausimai' . ($cat !== '' ? '&cat=' . rawurlencode($cat) : '')) ?>">
                  <?= csrf_field() ?><input type="hidden" name="action" value="toggle_question"><input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
                  <button class="btn btn-ghost btn-sm" type="submit" title="<?= $q['is_active'] ? 'Nebenaudoti testuose' : 'Vėl naudoti testuose' ?>"><?= $q['is_active'] ? 'Išjungti' : 'Įjungti' ?></button>
                </form>
              </span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
<?php
    endif;

else:   // nustatymai
    $disabled = is_admin() ? '' : 'disabled';
?>
  <form method="post" class="panel card form quiz-settings">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="settings">
    <?php if (!is_admin()): ?><p class="flash flash-info" style="margin:0;">Nustatymus keičia administratorius.</p><?php endif; ?>
    <h2>Egzaminas</h2>
    <div class="form-row">
      <label>Klausimų egzamine <span class="hint">daugiausia <?= $topicCount ?> (tiek skirtingų temų banke)</span>
        <input type="number" name="exam_questions" id="setCount" value="<?= (int) $s['exam_questions'] ?>" min="1" max="<?= max(1, $topicCount) ?>" required <?= $disabled ?>></label>
      <label>Išlaikymo riba, % <span class="hint" id="setNeed"></span>
        <input type="number" name="pass_percent" id="setPercent" value="<?= (int) $s['pass_percent'] ?>" min="1" max="100" required <?= $disabled ?>></label>
    </div>
    <div class="form-row">
      <label>Laikas, min. <span class="hint">0 - be laiko limito</span>
        <input type="number" name="time_limit" value="<?= (int) $s['time_limit'] ?>" min="0" max="600" required <?= $disabled ?>></label>
      <label>Kiek kartų galima laikyti <span class="hint">vienam žmogui, kol egzaminas atidarytas; 0 - neribota</span>
        <input type="number" name="exam_attempts" value="<?= (int) $s['exam_attempts'] ?>" min="0" max="99" required <?= $disabled ?>></label>
    </div>
    <label class="check"><input type="checkbox" name="proportional" value="1" id="setProp" <?= $s['proportional'] ? 'checked' : '' ?> <?= $disabled ?>>
      <span>Klausimai iš visų skyrių proporcingai <span class="hint" id="setQuotas" style="display:block;"></span></span></label>
    <label class="check"><input type="checkbox" name="show_answers" value="1" <?= $s['show_answers'] ? 'checked' : '' ?> <?= $disabled ?>>
      <span>Baigus egzaminą rodyti teisingus atsakymus <span class="hint" style="display:block;">Jei egzaminą laiko keli žmonės, geriau įjungti tik visiems baigus. Treneriai atsakymus mato visada.</span></span></label>

    <h2 style="margin-top:12px;">Mokomasis testas</h2>
    <label class="check"><input type="checkbox" name="practice" value="1" <?= $s['practice'] ? 'checked' : '' ?> <?= $disabled ?>>
      <span>Leisti spręsti mokomąjį testą <span class="hint" style="display:block;">Kiek nori kartų, pasirinktas skyrius ir klausimų skaičius (<?= implode(', ', REFEREE_PRACTICE_SIZES) ?> ar visi), baigus rodomi teisingi atsakymai. Kol egzaminas atidarytas - nepasiekiamas.</span></span></label>

    <h2 style="margin-top:12px;">Kas gali laikyti</h2>
    <div class="seg">
      <label><input type="radio" name="access" value="selected" <?= $s['access'] !== 'members' ? 'checked' : '' ?> <?= $disabled ?>><span>Tik pažymėti (<?= $accessCount ?>)</span></label>
      <label><input type="radio" name="access" value="members" <?= $s['access'] === 'members' ? 'checked' : '' ?> <?= $disabled ?>><span>Visi patvirtinti nariai</span></label>
    </div>
    <p class="hint">Treneriai gali laikyti visada. Pažymėtus tvarkykite skiltyje „Kas gali laikyti“.</p>
    <?php if (is_admin()): ?><div><button class="btn btn-primary" type="submit">Išsaugoti nustatymus</button></div><?php endif; ?>
  </form>
  <script>
  (function () {
    var cats = <?= json_encode(referee_categories(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var count = document.getElementById('setCount'), pct = document.getElementById('setPercent'), prop = document.getElementById('setProp');
    // Tas pats skaičiavimas kaip referee_category_quotas() serveryje
    function quotas(n) {
      var total = 0, out = {}, rest = [], left;
      Object.keys(cats).forEach(function (c) { total += cats[c]; });
      n = Math.min(n, total);
      left = n;
      Object.keys(cats).forEach(function (c) { var x = n * cats[c] / total; out[c] = Math.floor(x); rest.push([c, x - out[c]]); left -= out[c]; });
      rest.sort(function (a, b) { return b[1] - a[1]; }).slice(0, left).forEach(function (r) { out[r[0]]++; });
      return out;
    }
    function update() {
      var n = parseInt(count.value, 10) || 0, p = parseInt(pct.value, 10) || 0;
      document.getElementById('setNeed').textContent = n && p ? '= ' + Math.ceil(n * p / 100) + ' teisingų iš ' + n : '';
      var q = quotas(n);
      document.getElementById('setQuotas').textContent = prop.checked && n ? Object.keys(q).map(function (c) { return c + ' ' + q[c]; }).join(' · ') : 'Klausimai parenkami visiškai atsitiktinai.';
    }
    [count, pct, prop].forEach(function (el) { el.addEventListener('input', update); el.addEventListener('change', update); });
    update();
  })();
  </script>
<?php endif; ?>
<?php
page_end();
