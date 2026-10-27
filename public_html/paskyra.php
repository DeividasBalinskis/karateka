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
            $consent = !empty($_POST['photo_consent']) ? 1 : 0;
            q('UPDATE members SET photo_consent = ? WHERE id = ?', [$consent, $mid]);
            // Atšaukė - pranešama Deniui (kad nebeskelbtų nuotraukų); sąrašas matomas prie nuotraukų įkėlimo
            if ($m['photo_consent'] && !$consent) {
                notify_admins('consent_withdrawn', 'Atšauktas sutikimas dėl nuotraukų: ' . $m['first_name'] . ' ' . $m['last_name'],
                    'Atšaukė ' . $a['first_name'] . ' ' . $a['last_name'] . ($rel === 'parent' ? ' (tėvai)' : '') . '. Naujose nuotraukose šio nario neskelbkite.',
                    'admin/nariai.php?id=' . $mid, $mid);
            } elseif (!$m['photo_consent'] && $consent) {
                notifications_done('consent_withdrawn', null, $mid);
            }
            flash('ok', 'Sutikimas dėl nuotraukų atnaujintas.');
        }
        redirect('paskyra.php?skiltis=nustatymai&m=' . $mid);
    }

    // Diržą gali pasikeisti patys (tėvai arba narys); treneris prireikus pataiso
    if ($action === 'belt') {
        $mid = (int) post('member_id');
        if (account_relation($aid, $mid)) {
            q('UPDATE members SET belt_level = ? WHERE id = ?', [belt_from_post('belt_level'), $mid]);
            flash('ok', 'Diržas atnaujintas.');
        }
        redirect('paskyra.php?skiltis=nustatymai&m=' . $mid);
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
            redirect('paskyra.php?skiltis=nustatymai&m=' . $mid);
        }
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
        redirect(preg_match('~^pamokos\.php\?id=\d+$~', post('back')) ? post('back') : 'paskyra.php?m=' . $mid . (post('back') === 'svarbu' ? '#svarbu' : '#pastabos'));
    }

    // Denio atsakymas perskaitytas („Svarbu“): visa tema - pastaba, pamoka, renginys, naujiena ar bendros žinutės
    if ($action === 'replies_read') {
        $mid = (int) post('member_id');
        $topic = post('topic');
        if (account_relation($aid, $mid) && preg_match('~^(general|[nlew]\d+)$~', $topic)) {
            $cols = ['n' => 'note_id', 'l' => 'lesson_id', 'e' => 'event_id', 'w' => 'news_id'];
            $where = $topic === 'general' ? 'note_id IS NULL AND lesson_id IS NULL AND event_id IS NULL AND news_id IS NULL'
                : $cols[$topic[0]] . ' = ' . (int) substr($topic, 1) . ($topic[0] !== 'n' ? ' AND note_id IS NULL' : '');
            q("UPDATE member_messages SET read_at = NOW() WHERE member_id = ? AND from_staff = 1 AND read_at IS NULL AND $where", [$mid]);
        }
        redirect('paskyra.php?m=' . $mid . '#svarbu');
    }

    if ($action === 'task_done' || $action === 'task_undo') {
        $note = q_one('SELECT cn.* FROM coach_notes cn JOIN account_members am ON am.member_id = cn.member_id WHERE cn.id = ? AND am.account_id = ? AND cn.is_task = 1',
            [(int) post('note_id'), $aid]);
        if ($note) {
            q('UPDATE coach_notes SET done_at = ' . ($action === 'task_done' ? 'NOW()' : 'NULL') . ', read_at = COALESCE(read_at, NOW()) WHERE id = ?', [$note['id']]);
            flash('ok', $action === 'task_done' ? 'Puiku! Užduotis pažymėta kaip atlikta.' : 'Užduotis grąžinta į neatliktas.');
        }
        redirect(preg_match('~^pamokos\.php\?id=\d+$~', post('back')) ? post('back') : 'paskyra.php?m=' . (int) ($note['member_id'] ?? 0) . (post('back') === 'svarbu' ? '#svarbu' : '#pastabos'));
    }

    handle_account_settings($a, 'paskyra.php?skiltis=nustatymai', $errors);
}

// Skirtukas: apžvalga (taškai, tvarkaraštis, pastabos) arba nustatymai (nario duomenys, vaikai, paskyra)
$tab = get('skiltis') === 'nustatymai' || (is_post() && $errors && post('action') !== '') ? 'nustatymai' : 'apzvalga';

$isStaff = is_staff($a);
$members = account_members($aid);
// Treneriui grupės ir patvirtinimo nereikia: rodomi tik aktyvūs nariai (pvz. savo vaikai, jei treniruojasi)
if ($isStaff) {
    $members = array_values(array_filter($members, function ($m) { return $m['status'] === 'active'; }));
    if (!$members) {
        redirect('admin/');   // treneriui be savo treniruojamų narių viskas (ir nustatymai) - "Paskyra" puslapyje
    }
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


$active = $selected && $selected['status'] === 'active' && $a['status'] === 'active';
if ($active) {
    [$from, $to, $seasonLabel] = season_bounds();
    $seasonPoints = member_points_total((int) $selected['id'], $from, $to);
    $history = member_history((int) $selected['id']);
    $notes = member_notes((int) $selected['id']);
    // Neperskaitytos (ne užduotys) pastabos; užduotys skaičiuojamos atskirai, kol neatliktos
    $unreadIds = array_map('intval', array_column(array_filter($notes, function ($n) { return !$n['is_task'] && $n['read_at'] === null; }), 'id'));
    // Žinutės su treneriu: atsakymai po pastabomis, klausimai prie pamokų ir bendros. Atidarius - trenerio atsakymai perskaityti.
    $noteMsgs = note_messages(array_column($notes, 'id'));
    $threads = member_free_threads((int) $selected['id']);
    // Nauji Denio atsakymai: po vieną (naujausią) kiekvienai temai. Lieka „Svarbu“, kol paspaudžiama „Perskaičiau“ ar atidaromas pokalbis.
    $newReplies = [];
    foreach (message_rows('mm.member_id = ? AND mm.from_staff = 1 AND mm.read_at IS NULL', [(int) $selected['id']]) as $r) {
        $newReplies[$r['note_id'] ? 'n' . $r['note_id'] : message_topic_key($r)] = $r;
    }

    // „Svarbu“: kvietimai (su laikmačiu), egzamino testas, naujos pastabos / užduotys / atsiųstos pamokos, pranešimai
    $invites = q_all('SELECT e.*, ep.status AS p_status FROM event_participants ep JOIN events e ON e.id = ep.event_id
                       WHERE ep.member_id = ? AND ep.status IN ("invited", "late") AND e.starts_on > CURDATE()
                       ORDER BY e.starts_on, e.start_time', [(int) $selected['id']]);
    $examSoon = member_upcoming_exam($selected);
    $examTest = $examSoon && in_array($examSoon['p_status'], ['going', 'invited'], true) ? exam_test_status($selected) : null;
    $todoNotes = array_values(array_filter($notes, function ($n) { return (!$n['is_task'] && $n['read_at'] === null) || ($n['is_task'] && !$n['done_at']); }));
    $notifs = account_notifications($aid, (int) $selected['id']);

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
    // Sava eilutė visada (ir su 0 tšk.), jei narys priklauso rodomam reitingui
    $mine = member_rank_row($rows, $selected, $partition);
    $topRows = array_filter($rows, function ($r) { return $r['rank'] <= 5; });
    $mineInTop = $mine && in_array((int) $mine['member_id'], array_map('intval', array_column($topRows, 'member_id')), true);
    // "tu" - tik kai žiūri pats narys (tėvams, žiūrintiems vaiko, užtenka paryškintos eilutės)
    $meTag = ($selected['relation'] ?? '') === 'self' ? '<span class="me-tag">tu</span>' : '';
}

page_start($tab === 'nustatymai' ? 'Nustatymai' : 'Mano paskyra', ['noindex' => true, 'account' => $tab]);
?>
<div class="account-top">
  <div>
    <div class="eyebrow"><?= $tab === 'nustatymai' ? 'Nustatymai' : 'Apžvalga' ?></div>
    <div class="hello">Sveiki, <?= e($a['first_name']) ?>!</div>
  </div>
  <?php if (count($members) > 1): ?>
    <div class="member-tabs">
      <?php foreach ($members as $m): ?>
        <a href="?<?= $tab === 'nustatymai' ? 'skiltis=nustatymai&amp;' : '' ?>m=<?= (int) $m['id'] ?>" class="<?= $selected && $m['id'] === $selected['id'] ? 'active' : '' ?>">
          <?= e($m['first_name']) ?><?= $m['relation'] === 'self' ? ' (aš)' : '' ?><?php if ($u = member_attention_count((int) $m['id']) + member_notification_count($aid, (int) $m['id'])): ?> <span class="count-badge" title="Naujos pastabos ar neatliktos užduotys"><?= $u ?></span><?php endif; ?>
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
<?php if ($active && $tab === 'apzvalga' && ($invites || ($examTest && !$examTest['passed']) || $todoNotes || $newReplies || $notifs)):
    $back = 'paskyra.php?m=' . (int) $selected['id']; ?>
  <!-- Svarbu: kas laukia atsakymo ar dėmesio. Pažymėjus „Supratau“ / „Perskaičiau“ - dingsta. -->
  <section class="panel card important" id="svarbu">
    <div class="kicker">Svarbu<?= count($members) > 1 ? ' · ' . e($selected['first_name']) : '' ?></div>
    <ul class="notif-list">
      <?php foreach ($invites as $ev): ?>
        <li class="notif is-new t-invite">
          <span class="notif-icon" aria-hidden="true">📅</span>
          <div class="notif-body">
            <div class="notif-title"><a href="<?= url('renginys.php?id=' . (int) $ev['id']) ?>"><?= $ev['p_status'] === 'late' ? '' : 'Kvietimas: ' ?><?= e($ev['title']) ?></a></div>
            <div class="notif-text"><?= e(fmt_date($ev['starts_on'], true)) ?><?= $ev['start_time'] ? ', ' . e(fmt_time($ev['start_time'])) : '' ?><?= $ev['location'] ? ' · ' . e($ev['location']) : '' ?></div>
            <?= render_event_rsvp($ev, [$selected], $back . '#svarbu') ?>
          </div>
        </li>
      <?php endforeach; ?>
      <?php if ($examTest && !$examTest['passed']): ?>
        <li class="notif is-new t-exam">
          <span class="notif-icon" aria-hidden="true">📝</span>
          <div class="notif-body">
            <div class="notif-title">Prieš egzaminą išlaikyk testą</div>
            <div class="notif-text"><?= e($examSoon['title']) ?> · <?= e(fmt_date($examSoon['starts_on'], true)) ?> Reikia surinkti bent <?= (int) $examTest['quiz']['pass_percent'] ?> %<?= $examTest['best'] !== null ? ' (geriausias: ' . (int) $examTest['best'] . ' %)' : '' ?>.</div>
          </div>
          <div class="notif-actions">
            <a class="btn btn-primary btn-sm" href="<?= url('testas.php?q=' . (int) $examTest['quiz']['id'] . '&m=' . (int) $selected['id']) ?>">Laikyti testą</a>
            <a class="btn btn-ghost btn-sm" href="<?= url('egzaminas.php?m=' . (int) $selected['id']) ?>">Programa</a>
          </div>
        </li>
      <?php endif; ?>
      <?php foreach ($todoNotes as $n): $isLesson = $n['lesson_id'] && $n['lesson_title']; ?>
        <li class="notif is-new t-note">
          <span class="notif-icon" aria-hidden="true"><?= $isLesson ? '📘' : ($n['is_task'] ? '✏️' : '💬') ?></span>
          <div class="notif-body">
            <div class="notif-title">
              <?php if ($isLesson): ?>Treneris atsiuntė pamoką: <a href="<?= url('pamokos.php?id=' . (int) $n['lesson_id']) ?>"><?= e($n['lesson_title']) ?></a>
              <?php else: ?><a href="#n<?= (int) $n['id'] ?>"><?= $n['is_task'] ? 'Užduotis nuo trenerio' : 'Nauja trenerio pastaba' ?></a><?php endif; ?>
            </div>
            <div class="notif-text"><?= e(mb_strimwidth(trim(preg_replace('~\s+~u', ' ', $n['body'])), 0, 160, '…')) ?></div>
            <div class="notif-meta"><?= e(fmt_date($n['note_date'] ?? substr($n['created_at'], 0, 10))) ?><?= $n['author'] ? ' · ' . e($n['author']) : '' ?></div>
          </div>
          <div class="notif-actions">
            <form method="post" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="member_id" value="<?= (int) $selected['id'] ?>"><input type="hidden" name="note_id" value="<?= (int) $n['id'] ?>">
              <input type="hidden" name="back" value="svarbu">
              <?php if ($n['is_task']): ?>
                <button class="btn btn-primary btn-sm" name="action" value="task_done">✓ Atlikau</button>
              <?php else: ?>
                <button class="btn btn-ghost btn-sm" name="action" value="note_read"><?= $isLesson ? '✓ Peržiūrėjau' : '✓ Perskaičiau' ?></button>
              <?php endif; ?>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
      <?php foreach ($newReplies as $key => $r): ?>
        <li class="notif is-new t-reply">
          <span class="notif-icon" aria-hidden="true">💬</span>
          <div class="notif-body">
            <div class="notif-title"><?= e(message_author($r, false)) ?> atsakė<?= $key[0] === 'n' ? ': <a href="#' . e($key) . '">į pastabą</a>' : ($key !== 'general' ? ' · ' . message_topic_title($key, $r) : '') ?></div>
            <div class="notif-text"><?= e(mb_strimwidth(trim(preg_replace('~\s+~u', ' ', $r['body'])), 0, 200, '…')) ?></div>
            <div class="notif-meta"><?= e(notification_when($r['created_at'])) ?></div>
          </div>
          <div class="notif-actions">
            <?php if ($key === 'general'): ?><button type="button" class="btn btn-primary btn-sm" data-open-chat data-member="<?= (int) $selected['id'] ?>">Atsakyti</button><?php endif; ?>
            <form method="post" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="action" value="replies_read"><input type="hidden" name="member_id" value="<?= (int) $selected['id'] ?>">
              <input type="hidden" name="topic" value="<?= e($key) ?>">
              <button class="btn btn-ghost btn-sm" type="submit">✓ Perskaičiau</button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <?= render_notifications($notifs, $back . '#svarbu') ?>
  </section>
<?php endif; ?>

<?php if ($active && $tab === 'apzvalga' && varzybos_enabled()): ?>
  <a class="flash flash-info" style="display:block;" href="<?= url('varzybos.php') ?>">🥋 Registracija į varžybas: <strong>pasirinkti varžybas ir kategorijas →</strong></a>
<?php endif; ?>

<?php if ($isStaff): ?>
  <!-- Trenerio paskyra: reitingas, renginiai, lankomumas - skiltyje Treneriams; čia tik savo šeima (jei treniruojasi) ir nustatymai -->
  <div class="panel card row between" style="gap:14px; flex-wrap:wrap;">
    <p class="muted" style="margin:0;">Tai trenerio paskyra. Reitingas, lankomumas, renginiai, nariai ir paskyros nustatymai - skiltyje <strong>Paskyra</strong>.</p>
    <a class="btn btn-primary" href="<?= url('admin/') ?>">Į Paskyrą →</a>
  </div>
  <?php if ($members): ?><h2 style="margin:28px 0 12px;">Nariai mano paskyroje</h2><?php endif; ?>
<?php endif; ?>

<?php if ($selected && $tab === 'apzvalga'): ?>
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
        </div>
        <?= render_xp_card($selected) ?>

        <div class="top-tabs">
          <?php foreach ($topChoices as $key => $p): ?>
            <a href="?m=<?= (int) $selected['id'] ?>&amp;top=<?= e($key) ?>" class="<?= $key === $topKey ? 'active' : '' ?>"><?= e($key === 'mano' ? $p[2] . ' (mano)' : $p[2]) ?></a>
          <?php endforeach; ?>
        </div>

        <div class="kicker" style="margin-top:4px;">Top 5 · <?= e($partition[2]) ?></div>
        <?php if (!$rows): ?>
          <p class="muted">Šį sezoną taškų dar niekas neturi.</p>
        <?php endif; ?>
        <?php if ($rows || $mine): ?>
          <ol class="ranking">
            <?php foreach ($topRows as $r): ?>
              <li class="<?= $mine && (int) $r['member_id'] === (int) $mine['member_id'] ? 'me' : '' ?>">
                <span class="pos r<?= (int) $r['rank'] ?>"><?= (int) $r['rank'] ?></span><?= e(short_name($r)) ?><?= $mine && (int) $r['member_id'] === (int) $mine['member_id'] ? $meTag : '' ?><span class="pts"><?= (int) $r['total'] ?></span>
              </li>
            <?php endforeach; ?>
            <?php if ($mine && !$mineInTop): ?>
              <?php if (count($topRows) < $mine['rank'] - 1): ?><li class="gap">···</li><?php endif; ?>
              <li class="me"><span class="pos"><?= (int) $mine['rank'] ?></span><?= e(short_name($mine)) ?><?= $meTag ?><span class="pts"><?= (int) $mine['total'] ?></span></li>
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
        <div class="kicker">Artėjantys renginiai <?= staff_link('renginiai.php', '✎ Keisti') ?></div>
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
        <div class="coach-note <?= in_array((int) $n['id'], $unreadIds, true) || $openTask ? 'unread' : '' ?>" id="n<?= (int) $n['id'] ?>">
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
          <?php $nm = $noteMsgs[(int) $n['id']] ?? []; ?>
          <?php if ($nm): ?><div class="msg-thread"><?php foreach ($nm as $r): ?><?= render_message($r, false) ?><?php endforeach; ?></div><?php endif; ?>
          <details class="msg-reply"<?= $nm && end($nm)['from_staff'] ? ' open' : '' ?>>
            <summary>💬 <?= $nm ? 'Atsakyti' : 'Atsakyti treneriui' ?></summary>
            <?= message_form('msg', ['member_id' => (int) $selected['id'], 'note_id' => (int) $n['id']], 'Parašykite treneriui - matys tik treneriai', 'Siųsti', url('zinute.php')) ?>
          </details>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($active): ?>
    <div class="panel card" id="zinutes" style="margin-top:20px;">
      <div class="kicker">Žinutės Deniui</div>
      <p class="small muted" style="margin-top:-4px;">Žinutę ar klausimą mato tik Denis (ir kiti treneriai), o atsakymą - tik jūs<?= $selected['relation'] === 'parent' ? ' ir ' . e($selected['first_name']) : '' ?>. Parašyti galima ir bet kuriame puslapyje - mygtukas 💬 apačioje dešinėje.</p>
      <?php foreach ($threads as $key => $msgs): ?>
        <div class="msg-topic">
          <div class="msg-topic-title"><?= message_topic_title($key, $msgs[0]) ?></div>
          <div class="msg-thread"><?php foreach ($msgs as $r): ?><?= render_message($r, false) ?><?php endforeach; ?></div>
          <?php if ($key !== 'general'): ?>
            <details class="msg-reply"><summary>💬 Atsakyti</summary><?= message_form('msg', ['member_id' => (int) $selected['id']] + message_topic_hidden($key), 'Jūsų žinutė', 'Siųsti', url('zinute.php')) ?></details>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?= message_form('msg', ['member_id' => (int) $selected['id']], 'Parašykite Deniui' . (count($members) > 1 ? ' (' . $selected['first_name'] . ')' : ''), 'Siųsti Deniui', url('zinute.php')) ?>
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

  <p class="small" style="margin-top:18px;"><a href="?skiltis=nustatymai&amp;m=<?= (int) $selected['id'] ?>">Nario duomenys, diržas ir paskyros nustatymai →</a></p>
<?php endif; ?>

<?php if ($selected && $tab === 'nustatymai'): ?>
  <div class="panel card" style="margin-top:4px;">
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

<?php if ($isParent && ($tab === 'nustatymai' || !$members)): ?>
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

<?php if ($tab === 'nustatymai'): ?>
  <?= render_account_settings($a) ?>
<?php endif; ?>
<?php
page_end();
