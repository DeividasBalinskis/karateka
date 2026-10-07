<?php
// Nario / tėvų paskyra: grupė, tvarkaraštis, renginiai, vaikai, nustatymai
require __DIR__ . '/app/bootstrap.php';

$a = require_login();
$aid = (int) $a['id'];
$errors = [];

if (is_post()) {
    csrf_check();
    $action = post('action');

    if ($action === 'add_kid' && !is_staff($a)) {
        $first = post('first_name');
        $last = post('last_name');
        $birth = date_from_input($_POST['birth_date'] ?? '');
        if ($first === '' || $last === '' || !valid_birth_date($birth)) {
            $errors[] = 'Įveskite vaiko vardą, pavardę ir gimimo datą.';
        } elseif (!in_array(post('photo_consent'), ['0', '1'], true)) {
            $errors[] = 'Pasirinkite, ar sutinkate dėl nuotraukų.';
        } else {
            $mid = create_member($first, $last, $birth, !empty($_POST['photo_consent']), belt_from_post('belt_level'));
            q('UPDATE members SET parent_consent_at = NOW() WHERE id = ?', [$mid]);
            link_member($aid, $mid, 'parent');
            if ($a['status'] === 'active') {
                notify_coach_pending($a);
            }
            flash('ok', "$first pridėtas (-a). Treneris patvirtins ir priskirs grupę.");
            redirect('paskyra.php?m=' . $mid);
        }
    }

    if ($action === 'photo_consent') {
        $mid = (int) post('member_id');
        $rel = account_relation($aid, $mid);
        $m = q_one('SELECT * FROM members WHERE id = ?', [$mid]);
        // Keisti gali tėvai, o pats narys - tik nuo 14 m.
        if ($m && ($rel === 'parent' || ($rel === 'self' && age_on($m['birth_date']) >= CONSENT_AGE))) {
            q('UPDATE members SET photo_consent = ? WHERE id = ?', [!empty($_POST['photo_consent']) ? 1 : 0, $mid]);
            flash('ok', 'Sutikimas dėl nuotraukų atnaujintas.');
        }
        redirect('paskyra.php?m=' . $mid);
    }

    // Diržą gali pasikeisti patys (tėvai arba narys); treneris prireikus pataiso
    if ($action === 'belt') {
        $mid = (int) post('member_id');
        if (account_relation($aid, $mid)) {
            q('UPDATE members SET belt_level = ? WHERE id = ?', [belt_from_post('belt_level'), $mid]);
            flash('ok', 'Diržas atnaujintas.');
        }
        redirect('paskyra.php?m=' . $mid);
    }

    if ($action === 'invite') {
        $mid = (int) post('member_id');
        $email = normalize_email(post('email'));
        $hasOwn = q_value('SELECT 1 FROM account_members WHERE member_id = ? AND relation = "self"', [$mid]);
        if (account_relation($aid, $mid) !== 'parent' || $hasOwn) {
            $errors[] = 'Šio nario pakviesti negalima.';
        } elseif (!valid_email($email)) {
            $errors[] = 'Įveskite teisingą el. paštą.';
        } elseif (q_value('SELECT 1 FROM accounts WHERE email = ?', [$email])) {
            $errors[] = 'Šis el. paštas jau naudojamas kitos paskyros.';
        } else {
            $m = q_one('SELECT * FROM members WHERE id = ?', [$mid]);
            $token = token_create('invite', $email, $aid, $mid);
            send_mail($email, 'Kvietimas prisijungti prie karateka.lt',
                "Sveiki, {$m['first_name']},\n\n{$a['first_name']} {$a['last_name']} kviečia tave susikurti savo prisijungimą VšĮ Karate Ateitis narių sistemoje.\n"
                . "Matysi savo grupės tvarkaraštį, egzaminus ir varžybas.\n\n"
                . abs_url('pakvietimas.php?t=' . $token) . "\n\nNuoroda galioja 14 dienų.");
            flash('ok', "Kvietimas išsiųstas: $email");
            redirect('paskyra.php?m=' . $mid);
        }
    }

    if ($action === 'profile') {
        q('UPDATE accounts SET phone = ? WHERE id = ?', [post('phone') ?: null, $aid]);
        flash('ok', 'Duomenys išsaugoti.');
        redirect('paskyra.php#nustatymai');
    }

    // Užduotis atlikta / grąžinti į neatliktas (gali pažymėti pats narys arba tėvai)
    // Pastaba perskaityta (viena arba visos to nario) - kol nepažymėta, rodomas pranešimas ir skaičiukas
    if ($action === 'note_read') {
        $mid = (int) post('member_id');
        if (account_relation($aid, $mid)) {
            if (post('note_id') === 'all') {
                q('UPDATE coach_notes SET read_at = NOW() WHERE member_id = ? AND read_at IS NULL AND is_task = 0', [$mid]);
            } else {
                q('UPDATE coach_notes SET read_at = NOW() WHERE id = ? AND member_id = ? AND read_at IS NULL', [(int) post('note_id'), $mid]);
            }
        }
        redirect('paskyra.php?m=' . $mid . '#pastabos');
    }

    if ($action === 'task_done' || $action === 'task_undo') {
        $note = q_one('SELECT cn.* FROM coach_notes cn JOIN account_members am ON am.member_id = cn.member_id WHERE cn.id = ? AND am.account_id = ? AND cn.is_task = 1',
            [(int) post('note_id'), $aid]);
        if ($note) {
            q('UPDATE coach_notes SET done_at = ' . ($action === 'task_done' ? 'NOW()' : 'NULL') . ', read_at = COALESCE(read_at, NOW()) WHERE id = ?', [$note['id']]);
            flash('ok', $action === 'task_done' ? 'Puiku! Užduotis pažymėta kaip atlikta.' : 'Užduotis grąžinta į neatliktas.');
        }
        redirect('paskyra.php?m=' . (int) ($note['member_id'] ?? 0) . '#pastabos');
    }

    if ($action === 'email') {
        $email = normalize_email(post('email'));
        if (!password_verify((string) ($_POST['current'] ?? ''), $a['password_hash'])) {
            $errors[] = 'Neteisingas slaptažodis.';
        } elseif (!valid_email($email)) {
            $errors[] = 'Įveskite teisingą el. paštą.';
        } elseif ($email === $a['email']) {
            $errors[] = 'Tai jūsų dabartinis el. paštas.';
        } elseif (q_value('SELECT 1 FROM accounts WHERE email = ?', [$email])) {
            $errors[] = 'Šis el. paštas jau naudojamas kitos paskyros.';
        } else {
            // Pakeičiama tik paspaudus nuorodą naujame pašte - taip įsitikiname, kad adresas tikrai jūsų
            $token = token_create('change_email', $email, $aid);
            send_mail($email, 'Patvirtinkite naują el. paštą — Karateka',
                "Sveiki, {$a['first_name']},\n\nnorėdami pakeisti savo karateka.lt paskyros el. paštą į šį adresą, paspauskite nuorodą:\n\n"
                . abs_url('el-pastas.php?t=' . $token) . "\n\nNuoroda galioja 1 dieną. Jei el. pašto nekeitėte, šį laišką ignoruokite.");
            flash('ok', "Išsiuntėme patvirtinimo nuorodą į $email. El. paštas pasikeis, kai ją paspausite.");
            redirect('paskyra.php#nustatymai');
        }
    }

    if ($action === 'password') {
        $pw = (string) ($_POST['password'] ?? '');
        if (!password_verify((string) ($_POST['current'] ?? ''), $a['password_hash'])) {
            $errors[] = 'Neteisingas dabartinis slaptažodis.';
        } elseif (mb_strlen($pw) < MIN_PASSWORD) {
            $errors[] = 'Naujas slaptažodis turi būti bent ' . MIN_PASSWORD . ' simbolių.';
        } else {
            q('UPDATE accounts SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $aid]);
            session_regenerate_id(true);
            flash('ok', 'Slaptažodis pakeistas.');
            redirect('paskyra.php#nustatymai');
        }
    }
}

$isStaff = is_staff($a);
$members = account_members($aid);
// Treneriui grupės ir patvirtinimo nereikia: rodomi tik aktyvūs nariai (pvz. savo vaikai, jei treniruojasi)
if ($isStaff) {
    $members = array_values(array_filter($members, function ($m) { return $m['status'] === 'active'; }));
}
$selected = null;
foreach ($members as $m) {
    if ((int) $m['id'] === (int) get('m')) {
        $selected = $m;
    }
}
$selected = $selected ?? ($members[0] ?? null);
// Vaikus prideda tėvai; treneriai - per Treneriams -> Nariai
$isParent = !$isStaff && ((bool) array_filter($members, function ($m) { return $m['relation'] === 'parent'; }) || !array_filter($members, function ($m) { return $m['relation'] === 'self'; }));

// Treneriui - visas reitingas (pats nedalyvauja): Top 5 ir visi kiti su paieška
if ($isStaff) {
    [$sFrom, $sTo, $sLabel] = season_bounds();
    $staffChoices = [];
    foreach (GROUP_CATEGORIES as $key => $label) {
        $staffChoices[$key] = ['category', $key, $label];
    }
    $staffChoices['klubas'] = ['club', null, 'Visas klubas'];
    $staffKey = isset($staffChoices[get('rt')]) ? get('rt') : array_key_first($staffChoices);
    $staffRows = ranking($staffChoices[$staffKey], $sFrom, $sTo);
}

$active = $selected && $selected['status'] === 'active' && $a['status'] === 'active';
if ($active) {
    [$from, $to, $seasonLabel] = season_bounds();
    $seasonPoints = member_points_total((int) $selected['id'], $from, $to);
    $history = member_history((int) $selected['id']);
    $notes = member_notes((int) $selected['id']);
    // Neperskaitytos (ne užduotys) pastabos; užduotys skaičiuojamos atskirai, kol neatliktos
    $unreadIds = array_map('intval', array_column(array_filter($notes, function ($n) { return !$n['is_task'] && $n['read_at'] === null; }), 'id'));

    // Kurį reitingą rodyti: savo (numatytasis), kitos amžiaus kategorijos arba viso klubo
    $ownPartition = ranking_partition($selected);
    $topChoices = [];
    if ($ownPartition) {
        $topChoices['mano'] = $ownPartition;
    }
    foreach (GROUP_CATEGORIES as $key => $label) {
        if (!$ownPartition || $ownPartition[0] !== 'category' || $ownPartition[1] !== $key) {
            $topChoices[$key] = ['category', $key, $label];
        }
    }
    $topChoices['klubas'] = ['club', null, 'Visas klubas'];
    $topKey = isset($topChoices[get('top')]) ? get('top') : array_key_first($topChoices);
    $partition = $topChoices[$topKey];
    $rows = ranking($partition, $from, $to);
    $mine = null;
    foreach ($rows as $r) {
        if ((int) $r['member_id'] === (int) $selected['id']) {
            $mine = $r;
        }
    }
}
$openSetting = in_array(post('action'), ['email', 'password', 'profile'], true) ? post('action') : '';

page_start('Mano paskyra', ['noindex' => true]);
?>
<div class="account-top">
  <div>
    <div class="eyebrow">Mano paskyra</div>
    <div class="hello">Sveiki, <?= e($a['first_name']) ?>!</div>
  </div>
  <?php if (count($members) > 1): ?>
    <div class="member-tabs">
      <?php foreach ($members as $m): ?>
        <a href="?m=<?= (int) $m['id'] ?>" class="<?= $selected && $m['id'] === $selected['id'] ? 'active' : '' ?>">
          <?= e($m['first_name']) ?><?= $m['relation'] === 'self' ? ' (aš)' : '' ?><?php if ($u = member_attention_count((int) $m['id'])): ?> <span class="count-badge" title="Naujos pastabos ar neatliktos užduotys"><?= $u ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <form method="post" action="<?= url('atsijungti.php') ?>" class="inline-form logout"><?= csrf_field() ?><button type="submit" class="btn btn-ghost btn-sm">Atsijungti</button></form>
</div>

<?php if ($a['status'] === 'pending_approval'): ?>
  <div class="flash flash-info">Paskyra laukia trenerio patvirtinimo. Kai treneris patvirtins ir priskirs grupę, čia matysite tvarkaraštį, renginius ir taškus.</div>
<?php endif; ?>
<?= form_errors($errors) ?>
<?php if ($active && ($att = count($unreadIds) + count(array_filter($notes, function ($n) { return $n['is_task'] && !$n['done_at']; })))): ?>
  <a class="flash flash-attention" href="#pastabos">❗ <?= count($members) > 1 ? e($selected['first_name']) . ': ' : '' ?>yra naujų trenerio pastabų ar neatliktų užduočių (<?= $att ?>) - žiūrėti ↓</a>
<?php endif; ?>

<?php if ($isStaff): ?>
  <!-- Trenerio paskyra: visas reitingas ir visi artėjantys renginiai -->
  <div class="grid-2 account-main has-points">
    <div class="panel card points-card">
      <div class="kicker">Reitingas · <?= e($sLabel) ?></div>
      <div class="top-tabs">
        <?php foreach ($staffChoices as $key => $p): ?>
          <a href="?rt=<?= e($key) ?>" class="<?= $key === $staffKey ? 'active' : '' ?>"><?= e($p[2]) ?></a>
        <?php endforeach; ?>
      </div>
      <?php if (!$staffRows): ?>
        <p class="muted">Šį sezoną taškų dar niekas neturi.</p>
      <?php else: ?>
        <input type="search" class="rank-search" id="rankSearch" placeholder="Ieškoti pagal vardą ar pavardę" aria-label="Ieškoti reitinge">
        <ol class="ranking ranking-all" id="rankAll">
          <?php foreach ($staffRows as $r): ?>
            <li data-name="<?= e(mb_strtolower($r['first_name'] . ' ' . $r['last_name'])) ?>" class="<?= $r['rank'] <= 5 ? 'top5' : '' ?>">
              <span class="pos r<?= (int) $r['rank'] ?>"><?= (int) $r['rank'] ?></span><a href="<?= url('admin/nariai.php?id=' . (int) $r['member_id']) ?>"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></a><span class="pts"><?= (int) $r['total'] ?></span>
            </li>
          <?php endforeach; ?>
        </ol>
        <p class="hint" id="rankEmpty" style="display:none;">Nieko nerasta.</p>
        <p class="hint" style="margin-top:8px;">Iš viso su taškais: <?= count($staffRows) ?>. Jūs, kaip treneris, reitinge nedalyvaujate. Paspaudę vardą atidarysite nario puslapį.</p>
      <?php endif; ?>
    </div>
    <div class="panel card events-card">
      <div class="kicker">Artėjantys renginiai <?= staff_link('admin/renginiai.php', '✎ Keisti') ?></div>
      <?php $staffEvents = upcoming_events(null, 20); ?>
      <?php if (!$staffEvents): ?>
        <p class="muted">Artėjančių renginių nėra.</p>
      <?php else: ?>
        <ul class="list events-scroll">
          <?php foreach ($staffEvents as $ev): ?><li><?= render_event($ev) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
  <script>
  (function () {
    var input = document.getElementById('rankSearch');
    if (!input) return;
    // Paieška be lietuviškų raidžių skirtumo: „austeja“ randa „Austėja“
    var plain = function (s) { return s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };
    var items = document.querySelectorAll('#rankAll li');
    input.addEventListener('input', function () {
      var q = plain(input.value.trim()), shown = 0;
      items.forEach(function (li) {
        var ok = !q || plain(li.dataset.name).indexOf(q) !== -1;
        li.style.display = ok ? '' : 'none';
        if (ok) shown++;
      });
      document.getElementById('rankEmpty').style.display = shown ? 'none' : '';
    });
  })();
  </script>
  <?php if ($members): ?><h2 style="margin:28px 0 12px;">Nariai mano paskyroje</h2><?php endif; ?>
<?php endif; ?>

<?php if ($selected): ?>
  <div class="grid-2 account-main <?= $active ? 'has-points' : 'no-points' ?>">
    <?php if ($active): ?>
      <!-- Taškai ir Top 5 - svarbiausia, todėl pirma -->
      <div class="panel card points-card">
        <div class="points-head">
          <div>
            <div class="kicker"><?= count($members) > 1 ? e($selected['first_name']) . ' · ' : 'Mano ' ?>taškai · <?= e($seasonLabel) ?></div>
            <div class="row" style="align-items:baseline; gap:8px;">
              <span class="big-number"><?= $seasonPoints ?></span><span class="muted">tšk.</span>
            </div>
          </div>
          <div class="muted small" style="text-align:right;">iš viso per visą laiką<br><strong><?= member_points_total((int) $selected['id']) ?> tšk.</strong></div>
        </div>

        <div class="top-tabs">
          <?php foreach ($topChoices as $key => $p): ?>
            <a href="?m=<?= (int) $selected['id'] ?>&amp;top=<?= e($key) ?>" class="<?= $key === $topKey ? 'active' : '' ?>"><?= e($key === 'mano' ? $p[2] . ' (mano)' : $p[2]) ?></a>
          <?php endforeach; ?>
        </div>

        <div class="kicker" style="margin-top:4px;">Top 5 · <?= e($partition[2]) ?></div>
        <?php if (!$rows): ?>
          <p class="muted">Šį sezoną taškų dar niekas neturi.</p>
        <?php else: ?>
          <ol class="ranking">
            <?php foreach (array_filter($rows, function ($r) { return $r['rank'] <= 5; }) as $r): ?>
              <li class="<?= $mine && $r['member_id'] === $mine['member_id'] ? 'me' : '' ?>">
                <span class="pos r<?= (int) $r['rank'] ?>"><?= (int) $r['rank'] ?></span><?= e(short_name($r)) ?><span class="pts"><?= (int) $r['total'] ?></span>
              </li>
            <?php endforeach; ?>
            <?php if ($mine && $mine['rank'] > 5): ?>
              <li class="gap">···</li>
              <li class="me"><span class="pos"><?= (int) $mine['rank'] ?></span><?= e(short_name($mine)) ?><span class="pts"><?= (int) $mine['total'] ?></span></li>
            <?php endif; ?>
          </ol>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- Dešinė: tvarkaraštis (kompaktiškas - vienodo laiko dienos sujungtos) ir artėjantys renginiai -->
    <div class="stack">
      <div class="panel card">
        <div class="kicker">Tvarkaraštis <?= $selected['group_id'] ? staff_link('admin/grupes.php?id=' . (int) $selected['group_id'], '✎ Keisti') : '' ?></div>
        <?php if ($active && $selected['group_id']): ?>
          <h2><?= e($selected['group_name']) ?></h2>
          <div style="margin:-4px 0 10px;"><?= belt_chip($selected['belt_level'] !== null ? (int) $selected['belt_level'] : null) ?></div>
          <?= render_schedule_lines(schedule_lines(group_schedule((int) $selected['group_id']))) ?>
          <div class="kicker" style="margin-top:14px;">Lankomumas</div>
          <?= render_attendance((int) $selected['id']) ?>
        <?php else: ?>
          <h2><?= e($selected['first_name'] . ' ' . $selected['last_name']) ?></h2>
          <p class="muted">
            <?= $selected['status'] === 'inactive' ? 'Narystė neaktyvi.' : 'Laukiama trenerio patvirtinimo - po to čia matysite grupę, tvarkaraštį ir taškus.' ?>
          </p>
        <?php endif; ?>
      </div>

      <div class="panel card events-card">
        <div class="kicker">Artėjantys renginiai <?= staff_link('admin/renginiai.php', '✎ Keisti') ?></div>
        <?php $events = $active ? upcoming_events($selected['group_id'] ? (int) $selected['group_id'] : null, 20) : []; ?>
        <?php if (!$events): ?>
          <p class="muted">Artėjančių renginių nėra.</p>
        <?php else: ?>
          <ul class="list events-scroll">
            <?php foreach ($events as $ev): ?><li><?= render_event($ev) ?></li><?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($active && $notes): ?>
    <div class="panel card" id="pastabos" style="margin-top:20px;">
      <?php $unreadInfo = array_filter($notes, function ($n) { return !$n['is_task'] && $n['read_at'] === null; }); ?>
      <div class="row between" style="margin-bottom:4px;">
        <div class="kicker" style="margin:0;">Trenerio pastabos<?= $unreadIds ? ' <span class="badge badge-new">' . count($unreadIds) . ' nauj.</span>' : '' ?></div>
        <?php if (count($unreadInfo) > 1): ?>
          <form method="post" class="inline-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="note_read"><input type="hidden" name="member_id" value="<?= (int) $selected['id'] ?>"><input type="hidden" name="note_id" value="all">
            <button class="btn btn-ghost btn-sm" type="submit">✓ Visas perskaičiau</button>
          </form>
        <?php endif; ?>
      </div>
      <?php foreach ($notes as $n): ?>
        <?php $openTask = $n['is_task'] && !$n['done_at']; ?>
        <div class="coach-note <?= in_array((int) $n['id'], $unreadIds, true) || $openTask ? 'unread' : '' ?>">
          <div class="meta">
            <?= note_source($n) ?><?= $n['author'] ? ' · treneris ' . e($n['author']) : '' ?>
            <?= !$n['is_task'] && $n['read_at'] === null ? ' <span class="badge badge-new">Nauja</span>' : '' ?>
            <?php if ($n['is_task']): ?>
              <?= $n['done_at'] ? ' <span class="badge badge-ok">✓ Atlikta ' . e(fmt_date(substr($n['done_at'], 0, 10))) . '</span>' : ' <span class="badge badge-warn">Užduotis</span>' ?>
            <?php endif; ?>
          </div>
          <div><?= text_to_html($n['body']) ?></div>
          <?php if ($n['lesson_id'] && $n['lesson_title']): ?>
            <a class="note-lesson" href="<?= url('pamokos.php?id=' . (int) $n['lesson_id']) ?>">📘 Pamoka: <?= e($n['lesson_title']) ?> →</a>
          <?php endif; ?>
          <?php if ($n['youtube_id']): ?><?= youtube_embed($n['youtube_id']) ?><?php endif; ?>
          <?php if ($n['is_task'] && $selected['relation']): ?>
            <form method="post" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="note_id" value="<?= (int) $n['id'] ?>">
              <?php if (!$n['done_at']): ?>
                <button class="btn btn-primary btn-sm" name="action" value="task_done" style="margin-top:10px;">✓ Atlikau</button>
              <?php else: ?>
                <button class="linklike small muted" name="action" value="task_undo" style="margin-top:6px; border:none; padding:0; text-decoration:underline;">grąžinti į neatliktas</button>
              <?php endif; ?>
            </form>
          <?php endif; ?>
          <?php if (!$n['is_task'] && $n['read_at'] === null): ?>
            <form method="post" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="action" value="note_read"><input type="hidden" name="member_id" value="<?= (int) $selected['id'] ?>"><input type="hidden" name="note_id" value="<?= (int) $n['id'] ?>">
              <button class="btn btn-primary btn-sm" type="submit" style="margin-top:10px;">✓ Perskaičiau</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="panel card" style="margin-top:20px;">
    <div class="kicker">Istorija</div>
    <?php if (!$active || !$history): ?>
      <p class="muted">Čia matysite egzaminus, varžybas, seminarus ir kitus pasiekimus.</p>
    <?php else: ?>
      <ul class="list small history-list">
        <?php foreach (array_slice($history, 0, 20) as $h): ?>
          <li class="row between">
            <span>
              <strong><?= e($h['event_title'] ?: $h['category_name']) ?></strong>
              <div class="muted"><?= $h['event_title'] ? e($h['category_name']) . ' · ' : '' ?><?= e(fmt_date($h['awarded_on'], true)) ?><?= $h['note'] ? ' · ' . e($h['note']) : '' ?></div>
            </span>
            <span class="badge badge-ok">+<?= (int) $h['points'] ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="panel card" style="margin-top:20px;">
    <div class="kicker">Nario duomenys</div>
    <div class="row between">
      <div>
        <strong><?= e($selected['first_name'] . ' ' . $selected['last_name']) ?></strong>
        <div class="muted small">Gimimo data: <?= e($selected['birth_date']) ?> · <?= age_on($selected['birth_date']) ?> m.</div>
      </div>
      <span class="badge <?= $selected['status'] === 'active' ? 'badge-ok' : 'badge-warn' ?>">
        <?= ['pending' => 'Laukia patvirtinimo', 'active' => 'Aktyvus', 'inactive' => 'Neaktyvus'][$selected['status']] ?>
      </span>
    </div>

    <form method="post" class="form belt-form" style="margin-top:16px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="belt">
      <input type="hidden" name="member_id" value="<?= (int) $selected['id'] ?>">
      <label>Diržas <span class="hint">pasikeitus po egzamino - pakeiskite čia</span>
        <select name="belt_level" onchange="this.form.submit()"><?= belt_options($selected['belt_level'] !== null ? (int) $selected['belt_level'] : null, '— nežinau / dar neturi —') ?></select></label>
      <noscript><button class="btn btn-ghost btn-sm" type="submit">Išsaugoti</button></noscript>
    </form>

    <?php $canPhoto = $selected['relation'] === 'parent' || age_on($selected['birth_date']) >= CONSENT_AGE; ?>
    <?php if ($canPhoto && $selected['photo_consent']): ?>
      <!-- Sutikimas duotas - langelio nebereikia, bet atšaukti galima visada (BDAR) -->
      <form method="post" class="consent-line" onsubmit="return confirm('Atšaukti sutikimą skelbti nuotraukas ir vaizdo įrašus?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="photo_consent">
        <input type="hidden" name="member_id" value="<?= (int) $selected['id'] ?>">
        <span class="small muted">✓ Sutikimas skelbti nuotraukas ir vaizdo įrašus duotas</span>
        <button class="linklike small muted" type="submit" style="border:none; padding:0; text-decoration:underline;">atšaukti</button>
      </form>
    <?php elseif ($canPhoto): ?>
      <form method="post" class="form" style="margin-top:16px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="photo_consent">
        <input type="hidden" name="member_id" value="<?= (int) $selected['id'] ?>">
        <label class="check"><input type="checkbox" name="photo_consent" value="1" onchange="this.form.submit()">
          <span>Sutinku, kad klubas skelbtų <?= $selected['relation'] === 'parent' ? 'vaiko' : 'mano' ?> nuotraukas ir vaizdo įrašus</span></label>
        <noscript><button class="btn btn-ghost btn-sm" type="submit">Išsaugoti</button></noscript>
      </form>
    <?php else: ?>
      <p class="small muted" style="margin-top:12px;">Sutikimas dėl nuotraukų: <?= $selected['photo_consent'] ? 'duotas' : 'neduotas' ?> (keičia tėvai).</p>
    <?php endif; ?>

    <?php if ($selected['relation'] === 'parent'): ?>
      <hr class="divider">
      <?php if ($selected['own_login_email']): ?>
        <p class="small"><?= e($selected['first_name']) ?> turi savo prisijungimą: <strong><?= e($selected['own_login_email']) ?></strong></p>
      <?php else: ?>
        <details <?= $errors && post('action') === 'invite' ? 'open' : '' ?>>
          <summary class="summary-link">Ar <?= e($selected['first_name']) ?> nori jungtis pats (-i)? Sukurkite atskirą prisijungimą</summary>
          <p class="small muted" style="margin:10px 0;">
            Jei vaikas turi savo el. paštą, jis gali turėti <strong>atskirą prisijungimą</strong> ir pats matyti savo tvarkaraštį, renginius ir taškus.
            Įveskite vaiko el. paštą - jis gaus laišką su nuoroda slaptažodžiui susikurti.
            Jūs ir toliau viską matysite savo paskyroje. Tai neprivaloma.
          </p>
          <form method="post" class="form inline-fields">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="invite">
            <input type="hidden" name="member_id" value="<?= (int) $selected['id'] ?>">
            <label>Vaiko el. paštas <input type="email" name="email" required></label>
            <button class="btn btn-ghost" type="submit">Siųsti kvietimą</button>
          </form>
        </details>
      <?php endif; ?>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($isParent): ?>
  <details class="panel card" style="margin-top:20px;" <?= !$members || ($errors && post('action') === 'add_kid') ? 'open' : '' ?>>
    <summary style="cursor:pointer; font-family:'Space Grotesk',sans-serif; font-weight:700;">+ Pridėti vaiką</summary>
    <form method="post" class="form" style="margin-top:16px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_kid">
      <div class="form-row">
        <label>Vardas <input type="text" name="first_name" required></label>
        <label>Pavardė <input type="text" name="last_name" required></label>
      </div>
      <div class="form-row">
        <label>Gimimo data <?= date_parts_field('birth_date', null) ?></label>
        <label>Diržas <span class="hint">nežinote - palikite tuščią</span><select name="belt_level"><?= belt_options(null, '— nežinau / dar neturi —') ?></select></label>
      </div>
      <?= photo_consent_choice('photo_consent', post('action') === 'add_kid' ? post('photo_consent') : null, 'Ar sutinkate, kad klubas skelbtų vaiko nuotraukas ir vaizdo įrašus?') ?>
      <div><button class="btn btn-primary" type="submit">Pridėti</button></div>
    </form>
  </details>
<?php endif; ?>

<div class="panel card" id="nustatymai" style="margin-top:20px;">
  <h2>Paskyros nustatymai</h2>
  <p class="muted small"><?= e($a['first_name'] . ' ' . $a['last_name']) ?></p>

  <details class="setting" <?= $openSetting === 'email' ? 'open' : '' ?>>
    <summary><span class="setting-label">El. paštas</span><span class="setting-value"><?= e($a['email']) ?></span><span class="setting-btn">Keisti</span></summary>
    <form method="post" class="form setting-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="email">
      <div class="form-row">
        <label>Naujas el. paštas <input type="email" name="email" value="<?= $openSetting === 'email' ? e(post('email')) : '' ?>" autocomplete="email" required></label>
        <label>Slaptažodis <span class="hint">patvirtinimui</span><input type="password" name="current" autocomplete="current-password" required></label>
      </div>
      <p class="hint">Į naują adresą atsiųsime nuorodą. El. paštas pasikeis tik ją paspaudus.</p>
      <div><button class="btn btn-primary btn-sm" type="submit">Keisti el. paštą</button></div>
    </form>
  </details>

  <details class="setting" <?= $openSetting === 'profile' ? 'open' : '' ?>>
    <summary><span class="setting-label">Telefonas</span><span class="setting-value"><?= $a['phone'] ? e($a['phone']) : '<span class="muted">nenurodytas</span>' ?></span><span class="setting-btn">Keisti</span></summary>
    <form method="post" class="form setting-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="profile">
      <label>Telefonas <input type="tel" name="phone" value="<?= e($a['phone']) ?>" autocomplete="tel"></label>
      <div><button class="btn btn-primary btn-sm" type="submit">Išsaugoti</button></div>
    </form>
  </details>

  <details class="setting" <?= $openSetting === 'password' ? 'open' : '' ?>>
    <summary><span class="setting-label">Slaptažodis</span><span class="setting-value">••••••••</span><span class="setting-btn">Keisti</span></summary>
    <form method="post" class="form setting-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <div class="form-row">
        <label>Dabartinis slaptažodis <input type="password" name="current" autocomplete="current-password" required></label>
        <label>Naujas slaptažodis <span class="hint">bent <?= MIN_PASSWORD ?> simboliai</span><input type="password" name="password" autocomplete="new-password" minlength="<?= MIN_PASSWORD ?>" required></label>
      </div>
      <div><button class="btn btn-primary btn-sm" type="submit">Keisti slaptažodį</button></div>
    </form>
  </details>
</div>
<?php
page_end();
