<?php
// Mano paskyra -> Egzaminas: diržų kelias, kito diržo programa („Moku“), egzamino žingsniai ir testai
require __DIR__ . '/app/bootstrap.php';

$a = require_login();
if (!is_active_account($a)) {
    redirect('paskyra.php');
}
$members = array_values(array_filter(account_members((int) $a['id']), function ($m) { return $m['status'] === 'active'; }));
if (!$members) {
    redirect('paskyra.php');
}
$selected = $members[0];
foreach ($members as $m) {
    if ((int) $m['id'] === (int) (get('m') ?: post('member_id'))) {
        $selected = $m;
    }
}
$mid = (int) $selected['id'];

// „Moku“ - pažymi pats narys arba tėvai, treneris patvirtina pranešime (tada skiriami punkto XP)
if (is_post()) {
    csrf_check();
    $req = q_one('SELECT * FROM belt_requirements WHERE id = ?', [(int) post('req_id')]);
    if (post('action') === 'req' && $req && account_relation((int) $a['id'], $mid)) {
        if (post('done') === '1') {
            request_skill($selected, $req, $a);
            flash('ok', 'Pažymėta! Kai treneris patvirtins, gausi' . ((int) $req['xp'] > 0 ? ' +' . (int) $req['xp'] . ' XP' : ' žvaigždutę') . '.');
        } else {
            cancel_skill($mid, (int) $req['id']);
        }
    }
    redirect('egzaminas.php?m=' . $mid . (get('dirzas') ? '&dirzas=' . (int) get('dirzas') : '') . '#r' . (int) post('req_id'));
}

$belt = $selected['belt_level'] !== null ? (int) $selected['belt_level'] : null;
$cur = current_belt($belt);
$next = next_belt($belt);
$showBelt = isset(BELTS[(int) get('dirzas')]) ? (int) get('dirzas') : $next;
$reqs = $showBelt ? belt_requirements($showBelt) : [];
$done = requirement_done_ids($mid);   // [id => 'pending' | 'approved']
$reqTotal = 0;
$reqDone = 0;
$reqPending = 0;
foreach ($reqs as $items) {
    foreach ($items as $r) {
        $reqTotal++;
        $reqDone += ($done[(int) $r['id']] ?? '') === 'approved' ? 1 : 0;
        $reqPending += ($done[(int) $r['id']] ?? '') === 'pending' ? 1 : 0;
    }
}
$isKid = age_on($selected['birth_date']) < 12;
$tags = member_tags($mid);
$quizzes = quizzes_for_member($selected, $tags);
$examTest = exam_test_status($selected);
$exam = member_upcoming_exam($selected);

page_start('Egzaminas', ['noindex' => true, 'account' => 'egzaminas']);
?>
<div class="account-top">
  <div>
    <div class="eyebrow">Egzaminas</div>
    <div class="hello"><?= $next ? 'Kelias iki ' . e(BELTS[$next][0]) . ' (' . e(BELTS[$next][1]) . ' diržas)' : 'Aukščiausias diržas' ?></div>
  </div>
  <?php if (count($members) > 1): ?>
    <div class="member-tabs">
      <?php foreach ($members as $m): ?>
        <a href="?m=<?= (int) $m['id'] ?>" class="<?= (int) $m['id'] === $mid ? 'active' : '' ?>"><?= e($m['first_name']) ?><?= $m['relation'] === 'self' ? ' (aš)' : '' ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Diržų kelias: nuo balto iki juodo, dabartinis ir kitas diržas -->
<div class="panel card belt-path-card <?= $isKid ? 'kid' : '' ?>">
  <div class="row between" style="align-items:flex-start;">
    <div>
      <div class="kicker">Diržų kelias</div>
      <p class="muted small" style="margin:0;"><?= $belt ? 'Dabar: ' . e(belt_label($belt)) : 'Diržas nenurodytas - laikome, kad baltas. Pakeisti galima Nustatymuose.' ?></p>
    </div>
    <?= render_xp_card($selected, true) ?>
  </div>
  <ol class="belt-path">
    <?php foreach (BELTS as $lvl => [$kyu, $name, $color]):
        if ($lvl > max($cur + 3, 10)) { break; } ?>
      <li class="<?= $lvl < $cur ? 'past' : ($lvl === $cur ? 'now' : ($lvl === $next ? 'next' : '')) ?>" title="<?= e("$kyu · $name") ?>">
        <span class="belt-dot" style="--belt:<?= $color ?>;"></span>
        <span class="belt-name"><?= e($kyu) ?></span>
        <?php if ($lvl === $cur): ?><span class="belt-tag">dabar</span><?php elseif ($lvl === $next): ?><span class="belt-tag next">kitas</span><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
</div>

<?php if ($exam): $days = (int) floor((strtotime($exam['starts_on']) - strtotime(date('Y-m-d'))) / 86400); ?>
  <!-- Artėjantis egzaminas: 3 žingsniai -->
  <div class="panel card exam-card <?= $isKid ? 'kid' : '' ?>">
    <div class="exam-head">
      <div class="exam-count"><span><?= $days ?></span><small><?= $days === 1 ? 'diena' : (($days % 10 === 0 || ($days % 100 > 10 && $days % 100 < 20)) ? 'dienų' : ($days % 10 === 1 ? 'diena' : 'dienos')) ?></small></div>
      <div>
        <div class="kicker">Tavo egzaminas<?= $days === 0 ? ' - šiandien!' : '' ?></div>
        <h2 style="margin:0;"><?= e($exam['title']) ?></h2>
        <div class="muted small"><?= e(fmt_date($exam['starts_on'], true)) ?><?= $exam['start_time'] ? ', ' . e(fmt_time($exam['start_time'])) : '' ?><?= $exam['location'] ? ' · ' . e($exam['location']) : '' ?></div>
      </div>
    </div>
    <ol class="exam-steps">
      <li class="<?= $reqTotal && $reqDone === $reqTotal ? 'ok' : '' ?>">
        <strong>Peržiūrėk programą</strong>
        <?php if ($reqTotal): ?>
          <div class="progress"><span style="width:<?= (int) round(100 * $reqDone / $reqTotal) ?>%"></span></div>
          <span class="small muted">Moku <?= $reqDone ?> iš <?= $reqTotal ?><?= $reqPending ? ' · ' . $reqPending . ' laukia trenerio' : '' ?></span>
        <?php else: ?>
          <span class="small muted">Programą treneris dar įkels.</span>
        <?php endif; ?>
      </li>
      <li class="<?= $examTest && $examTest['passed'] ? 'ok' : '' ?>">
        <strong>Išlaikyk testą</strong>
        <?php if (!$examTest): ?>
          <span class="small muted">Šiam diržui testo nėra - šį žingsnį gali praleisti.</span>
        <?php elseif ($examTest['passed']): ?>
          <span class="small">✓ Išlaikytas - leidžiama laikyti praktiką</span>
        <?php else: ?>
          <span class="small muted">Reikia surinkti bent <?= (int) $examTest['quiz']['pass_percent'] ?> %<?= $examTest['best'] !== null ? ' (geriausias: ' . $examTest['best'] . ' %)' : '' ?></span>
          <a class="btn btn-primary btn-sm" href="<?= url('testas.php?q=' . (int) $examTest['quiz']['id'] . '&m=' . $mid) ?>">Laikyti testą</a>
        <?php endif; ?>
      </li>
      <li class="<?= $exam['p_status'] === 'going' ? 'ok' : '' ?>">
        <strong>Atvyk į praktiką</strong>
        <?php if ($exam['p_status'] === 'going'): ?>
          <span class="small">✓ Užsiregistravai. Atsinešk diržą ir kimono.</span>
        <?php else: ?>
          <span class="small muted"><?= $exam['p_status'] === 'invited' ? 'Treneris kviečia - patvirtink:' : 'Užsiregistruok:' ?></span>
          <?= render_event_rsvp($exam, [$selected], 'egzaminas.php?m=' . $mid) ?>
        <?php endif; ?>
      </li>
    </ol>
  </div>
<?php endif; ?>

<div class="exam-grid">
  <!-- Programa -->
  <div class="panel card program-card">
    <div class="row between" style="align-items:flex-end;">
      <div>
        <div class="kicker">Egzamino programa</div>
        <h2 style="margin:0;"><?= $showBelt ? e(belt_label($showBelt)) : 'Programa' ?></h2>
      </div>
      <form method="get" class="inline-form">
        <input type="hidden" name="m" value="<?= $mid ?>">
        <select name="dirzas" onchange="this.form.submit()" aria-label="Kito diržo programa" class="small-select">
          <?php foreach (BELTS as $lvl => [$kyu, $name]): if ($lvl === 1) { continue; } ?>
            <option value="<?= $lvl ?>" <?= $lvl === $showBelt ? 'selected' : '' ?>><?= e("$kyu · $name") ?><?= $lvl === $next ? ' (kitas)' : '' ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
    <?php if ($reqTotal): ?>
      <div class="progress big" title="Moku <?= $reqDone ?> iš <?= $reqTotal ?>"><span style="width:<?= (int) round(100 * $reqDone / $reqTotal) ?>%"></span></div>
      <p class="small muted" style="margin:4px 0 0;">Moku <?= $reqDone ?> iš <?= $reqTotal ?><?= $reqPending ? ' · ' . $reqPending . ' laukia trenerio patvirtinimo' : '' ?><?= $reqDone === $reqTotal ? ' - puiku, esi pasiruošęs (-usi)! 🎉' : '' ?></p>
      <p class="small muted" style="margin:2px 0 0;">Paspausk ☆, kai moki. Treneris patvirtins - ir gausi XP.</p>
    <?php endif; ?>
    <?php if (!$reqs): ?>
      <p class="muted" style="margin-top:14px;">Šio diržo programos treneris dar neįkėlė.</p>
    <?php endif; ?>
    <?php foreach ($reqs as $section => $items): ?>
      <h3 class="req-section"><?= e(REQ_SECTIONS[$section]) ?></h3>
      <ul class="req-list">
        <?php foreach ($items as $r): $st = $done[(int) $r['id']] ?? null; $xp = (int) ($r['xp'] ?? 0); ?>
          <li id="r<?= (int) $r['id'] ?>" class="<?= $st === 'approved' ? 'done' : ($st === 'pending' ? 'pending' : '') ?>">
            <?php if ($st === 'approved'): ?>
              <span class="req-check" title="Treneris patvirtino">★</span>
            <?php else: ?>
              <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="req"><input type="hidden" name="member_id" value="<?= $mid ?>">
                <input type="hidden" name="req_id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="done" value="<?= $st ? '0' : '1' ?>">
                <button class="req-check" type="submit" aria-label="<?= $st ? 'Laukia trenerio patvirtinimo - atšaukti' : 'Pažymėti, kad moku' ?>" title="<?= $st ? 'Laukia trenerio patvirtinimo. Paspaudus - atšaukti.' : 'Moku' ?>"><?= $st ? '⏳' : '☆' ?></button>
              </form>
            <?php endif; ?>
            <div class="req-body">
              <div class="req-title"><strong><?= e($r['title']) ?></strong>
                <?php if ($xp > 0): ?><span class="xp-chip<?= $st === 'approved' ? ' earned' : '' ?>"><?= $st === 'approved' ? '✓ ' : '+' ?><?= $xp ?> XP</span><?php endif; ?>
                <?php if ($st === 'pending'): ?><span class="req-wait">laukia trenerio</span><?php endif; ?>
              </div>
              <?php if ($r['body']): ?><div class="small"><?= text_to_html($r['body']) ?></div><?php endif; ?>
              <?php if ($r['lesson_id']): ?><a class="small" href="<?= url('pamokos.php?id=' . (int) $r['lesson_id']) ?>">Pamoka: <?= e($r['lesson_title']) ?> →</a><?php endif; ?>
              <?php if ($r['youtube_id']): ?>
                <details class="req-video"><summary class="small">▶ Žiūrėti video</summary><?= youtube_embed($r['youtube_id']) ?></details>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  </div>

  <!-- Testai -->
  <div class="panel card">
    <div class="kicker">Testai</div>
    <?php if (!$quizzes): ?>
      <p class="muted">Testų tavo diržui dar nėra.</p>
    <?php endif; ?>
    <ul class="list quiz-list">
      <?php foreach ($quizzes as $qz): $best = quiz_best_attempt($mid, (int) $qz['id']); $ok = quiz_passed_recently($mid, (int) $qz['id']); ?>
        <li>
          <div class="row between" style="align-items:flex-start;">
            <span>
              <?php if ($qz['audience'] !== 'karateka'): ?><span class="badge badge-tag"><?= e(QUIZ_AUDIENCE[$qz['audience']]) ?></span><?php endif; ?>
              <strong><?= e($qz['title']) ?></strong>
              <div class="muted small"><?= min((int) $qz['question_count'], (int) $qz['questions_total']) ?> klausimų · reikia ≥ <?= (int) $qz['pass_percent'] ?> %<?= !empty($qz['time_limit']) ? ' · ' . (int) $qz['time_limit'] . ' min.' : '' ?></div>
            </span>
            <?php if ($ok): ?><span class="badge badge-ok">✓ Išlaikytas</span><?php elseif ($best): ?><span class="badge">geriausias <?= attempt_percent($best) ?> %</span><?php endif; ?>
          </div>
          <?php if ($qz['description']): ?><p class="small muted" style="margin:6px 0 0;"><?= e($qz['description']) ?></p><?php endif; ?>
          <div class="row" style="gap:8px; margin-top:10px;">
            <a class="btn btn-ghost btn-sm" href="<?= url('testas.php?q=' . (int) $qz['id'] . '&m=' . $mid . '&rezimas=treniruote') ?>">Treniruotis</a>
            <a class="btn btn-primary btn-sm" href="<?= url('testas.php?q=' . (int) $qz['id'] . '&m=' . $mid) ?>"><?= $ok ? 'Laikyti dar kartą' : 'Laikyti testą' ?></a>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php foreach ($tags as $tag): $qual = tag_qualification($mid, $tag); ?>
      <p class="small qual <?= $qual ? 'ok' : '' ?>"><?= e(MEMBER_TAGS[$tag]) ?>: <?= $qual ? '✓ kvalifikacija galioja iki ' . e(fmt_date(date('Y-m-d', strtotime($qual['finished_at'] . ' +' . QUIZ_VALID_MONTHS . ' months')), true)) : 'testas dar neišlaikytas' ?></p>
    <?php endforeach; ?>
    <p class="small muted" style="margin-top:12px;">Treniruotės režime iš karto matai teisingą atsakymą ir rezultatas nesaugomas. „Laikyti testą“ - kaip tikras egzaminas.</p>
  </div>
</div>
<?php
page_end();
