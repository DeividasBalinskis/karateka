<?php
// Nario / tėvų paskyra: grupė, tvarkaraštis, renginiai, vaikai, nustatymai
require __DIR__ . '/app/bootstrap.php';

$a = require_login();
$aid = (int) $a['id'];
$errors = [];

if (is_post()) {
    csrf_check();
    $action = post('action');

    // B7: vaiką gali pridėti bet kuri suaugusio paskyra (ir pats treniruojantis suaugęs); vaiko PIN paskyra - ne
    $selfM = q_one('SELECT m.* FROM account_members am JOIN members m ON m.id = am.member_id WHERE am.account_id = ? AND am.relation = "self"', [$aid]);
    $adult = !$selfM || empty($selfM['birth_date']) || age_on((string) $selfM['birth_date']) >= 18;
    if ($action === 'add_self' && !is_staff($a) && !is_kid_login($a)) {
        $birth = date_from_input($_POST['birth_date'] ?? '');
        if ($selfM) {
            $errors[] = t('Jūs jau esate narys.');
        } elseif (!valid_birth_date($birth) || age_on($birth) < 18) {
            $errors[] = t('Įveskite savo gimimo datą (nuo 18 metų).');
        } elseif (!in_array(post('photo_consent'), ['0', '1'], true)) {
            $errors[] = t('Pasirinkite, ar sutinkate dėl nuotraukų.');
        } else {
            $mid = create_member($a['first_name'], $a['last_name'], $birth, !empty($_POST['photo_consent']), belt_from_post('belt_level'));
            link_member($aid, $mid, 'self');
            if ($a['status'] === 'active') {
                notify_coach_pending($a);
            }
            flash('ok', t('Pridėta: treniruojatės ir jūs. Treneris patvirtins ir priskirs grupę.'));
            redirect('paskyra.php?m=' . $mid);
        }
    }

    if ($action === 'add_kid' && !is_staff($a) && !is_kid_login($a) && $adult) {
        $first = post('first_name');
        $last = post('last_name');
        $birth = date_from_input($_POST['birth_date'] ?? '');
        if ($first === '' || $last === '' || !valid_birth_date($birth)) {
            $errors[] = t('Įveskite vaiko vardą, pavardę ir gimimo datą.');
        } elseif (!in_array(post('photo_consent'), ['0', '1'], true)) {
            $errors[] = t('Pasirinkite, ar sutinkate dėl nuotraukų.');
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
            flash('ok', t('Sutikimas dėl nuotraukų atnaujintas.'));
        }
        redirect('paskyra.php?skiltis=nustatymai&m=' . $mid);
    }

    // Diržą gali pasikeisti patys (tėvai arba narys); treneris prireikus pataiso
    if ($action === 'belt') {
        $mid = (int) post('member_id');
        if (account_relation($aid, $mid)) {
            q('UPDATE members SET belt_level = ? WHERE id = ?', [belt_from_post('belt_level'), $mid]);
            flash('ok', t('Diržas atnaujintas.'));
        }
        redirect('paskyra.php?skiltis=nustatymai&m=' . $mid);
    }

    // B7: vaiko prisijungimas vardu ir PIN (sukuria ir tvarko tėvai; trenerio patvirtinti nereikia)
    if (in_array($action, ['kid_login', 'kid_pin', 'kid_login_toggle', 'kid_chat'], true)) {
        $mid = (int) post('member_id');
        $m = q_one('SELECT * FROM members WHERE id = ?', [$mid]);
        $own = q_one('SELECT a.* FROM account_members am JOIN accounts a ON a.id = am.account_id WHERE am.member_id = ? AND am.relation = "self"', [$mid]);
        $pin = preg_replace('/\s+/', '', (string) ($_POST['pin'] ?? ''));
        $back = 'paskyra.php?skiltis=nustatymai&m=' . $mid . '#vaiko-prisijungimas';
        if (!$m || account_relation($aid, $mid) !== 'parent') {
            $errors[] = t('Šio nario prisijungimo keisti negalima.');
        } elseif ($action === 'kid_login') {
            $u = mb_strtolower(trim((string) post('username')));
            if ($own) {
                $errors[] = t('Šis vaikas jau turi savo prisijungimą.');
            } elseif (empty($m['birth_date']) || age_on((string) $m['birth_date']) < KID_LOGIN_MIN_AGE) {
                $errors[] = t('Savo prisijungimą vaikas gali turėti nuo 7 metų.');
            } elseif (!valid_kid_username($u)) {
                $errors[] = t('Vardas: 3-20 lotyniškų raidžių ar skaitmenų, be tarpų (pvz. jonas.k).');
            } elseif (!valid_kid_pin($pin)) {
                $errors[] = t('PIN: 4-6 skaitmenys.');
            } elseif (q_value('SELECT 1 FROM accounts WHERE email = ?', [kid_login_email($u)])) {
                $errors[] = t('Toks vardas jau užimtas - pasirinkite kitą.');
            } else {
                $kid = create_account(kid_login_email($u), $pin, $m['first_name'], $m['last_name'], null, 'active');
                q('UPDATE accounts SET approved_at = NOW() WHERE id = ?', [$kid]);
                link_member($kid, $mid, 'self');
                flash('ok', t('Prisijungimas sukurtas. Vaikas jungiasi su vardu {u} ir PIN.', ['u' => $u]));
                redirect($back);
            }
        } elseif (!$own || !is_kid_login($own)) {
            $errors[] = t('Šio nario prisijungimo keisti negalima.');
        } elseif ($action === 'kid_pin') {
            if (!valid_kid_pin($pin)) {
                $errors[] = t('PIN: 4-6 skaitmenys.');
            } else {
                q('UPDATE accounts SET password_hash = ? WHERE id = ?', [password_hash($pin, PASSWORD_DEFAULT), (int) $own['id']]);
                q('DELETE FROM login_attempts WHERE email = ? AND success = 0', [$own['email']]);   // užsirakinęs vaikas vėl gali jungtis
                flash('ok', t('PIN pakeistas.'));
                redirect($back);
            }
        } elseif ($action === 'kid_chat') {
            q('UPDATE accounts SET chat_off = ? WHERE id = ?', [post('chat') === '1' ? 0 : 1, (int) $own['id']]);
            flash('ok', post('chat') === '1' ? t('Vaikas gali rašyti treneriui.') : t('Žinutės treneriui vaikui išjungtos - rašote jūs.'));
            redirect($back);
        } else {
            $on = $own['status'] !== 'active';
            q('UPDATE accounts SET status = ? WHERE id = ?', [$on ? 'active' : 'disabled', (int) $own['id']]);
            flash('ok', $on ? t('Vaiko prisijungimas vėl įjungtas.') : t('Vaiko prisijungimas išjungtas - vaikas nebegali prisijungti.'));
            redirect($back);
        }
    }

    if ($action === 'invite') {
        $mid = (int) post('member_id');
        $email = normalize_email(post('email'));
        $hasOwn = q_value('SELECT 1 FROM account_members WHERE member_id = ? AND relation = "self"', [$mid]);
        if (account_relation($aid, $mid) !== 'parent' || $hasOwn) {
            $errors[] = t('Šio nario pakviesti negalima.');
        } elseif (!valid_email($email)) {
            $errors[] = t('Įveskite teisingą el. paštą.');
        } elseif (q_value('SELECT 1 FROM accounts WHERE email = ?', [$email])) {
            $errors[] = t('Šis el. paštas jau naudojamas kitos paskyros.');
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
        redirect(preg_match('~^(pamokos\.php\?id=\d+|uzduotys\.php\?m=\d+(&f=\w+)?)$~', post('back')) ? post('back') : 'paskyra.php?m=' . $mid . (post('back') === 'svarbu' ? '#svarbu' : '#pastabos'));
    }

    // Denio atsakymas perskaitytas („Svarbu“): visa tema - pastaba, pamoka, renginys, naujiena ar bendros žinutės
    if ($action === 'replies_read') {
        $mid = (int) post('member_id');
        $topic = post('topic');
        if (account_relation($aid, $mid) && preg_match('~^(general|[nlew]\d+)$~', $topic)) {
            $cols = ['n' => 'note_id', 'l' => 'lesson_id', 'e' => 'event_id', 'w' => 'news_id'];
            $where = $topic === 'general' ? 'note_id IS NULL AND lesson_id IS NULL AND event_id IS NULL AND news_id IS NULL'
                : $cols[$topic[0]] . ' = ' . (int) substr($topic, 1) . ($topic[0] !== 'n' ? (' ' . 'AND note_id IS NULL') : '');
            q("UPDATE member_messages SET read_at = NOW() WHERE member_id = ? AND from_staff = 1 AND read_at IS NULL AND $where", [$mid]);
        }
        redirect('paskyra.php?m=' . $mid . '#svarbu');
    }

    if ($action === 'task_done' || $action === 'task_undo') {
        $note = q_one('SELECT cn.* FROM coach_notes cn JOIN account_members am ON am.member_id = cn.member_id WHERE cn.id = ? AND am.account_id = ? AND cn.is_task = 1',
            [(int) post('note_id'), $aid]);
        if ($note) {
            q('UPDATE coach_notes SET done_at = ' . ($action === 'task_done' ? 'NOW()' : 'NULL') . ', read_at = COALESCE(read_at, NOW()) WHERE id = ?', [$note['id']]);
            flash('ok', $action === 'task_done' ? t('Puiku! Užduotis pažymėta kaip atlikta.') : t('Užduotis grąžinta į neatliktas.'));
        }
        redirect(preg_match('~^(pamokos\.php\?id=\d+|uzduotys\.php\?m=\d+(&f=\w+)?)$~', post('back')) ? post('back') : 'paskyra.php?m=' . (int) ($note['member_id'] ?? 0) . (post('back') === 'svarbu' ? '#svarbu' : (post('back') === 'diena' ? '' : '#pastabos')));
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
    if (!$members && admin_mode() && $tab !== 'nustatymai') {
        redirect('admin/');   // admin režime treneriui be savo treniruojamų narių - valdymas; kitaip - savo dojo kaip nariui
    }
}
$selected = null;
foreach ($members as $m) {
    if ((int) $m['id'] === (int) get('m')) {
        $selected = $m;
    }
}
$selected = $selected ?? ($members[0] ?? null);
// Žaidimo kortelė: herojus - pasirinkto nario (vaiko), arba savas (treneriui, be narių). Pats žaidimas - dojo.php.
require_once APP_DIR . '/game_ui.php';
$dojoHero = null;
if ($tab === 'apzvalga' && $a['status'] === 'active' && game_public() && game_allowed($a)) {
    $mineHeroes = game_my_heroes($a);
    foreach ($mineHeroes as $h) {
        if ((int) $h['id'] === (int) get('h')) {
            $dojoHero = $h;
            foreach ($members as $m) {
                if ($h['member_id'] && (int) $m['id'] === (int) $h['member_id']) {
                    $selected = $m;
                }
            }
        }
    }
    if (!$dojoHero && $selected && $selected['status'] === 'active') {
        $dojoHero = game_hero_for_member((int) $selected['id']);
    }
    $dojoHero = $dojoHero ?? $mineHeroes[0];
}
// Vaikus prideda tėvai; treneriai - per Treneriams -> Nariai
$isParent = !$isStaff && ((bool) array_filter($members, function ($m) { return $m['relation'] === 'parent'; }) || !array_filter($members, function ($m) { return $m['relation'] === 'self'; }));
// B7: pridėti vaiką gali ir suaugęs narys (ne tik tėvai); tėvai - pridėti save kaip treniruojantį
$selfRow = array_values(array_filter($members, function ($m) { return $m['relation'] === 'self'; }))[0] ?? null;
$canAddKid = !$isStaff && !is_kid_login($a) && (!$selfRow || empty($selfRow['birth_date']) || age_on((string) $selfRow['birth_date']) >= 18);
$canAddSelf = !$isStaff && !is_kid_login($a) && !$selfRow;


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
    foreach (array_map('t', GROUP_CATEGORIES) as $key => $label) {
        if (!$ownPartition || $ownPartition[0] !== 'category' || $ownPartition[1] !== $key) {
            $topChoices[$key] = ['category', $key, $label];
        }
    }
    $topChoices['klubas'] = ['club', null, t('Visas klubas')];
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

// Mažas vaikas (iki 10 m.) savo paskyroje: paprastesni žodžiai - „Mano diena“, „XP“, „Geriausi“ (sprendimas 2026-11-20)
$little = $selected && !empty($selected['birth_date']) && account_relation((int) $a['id'], (int) $selected['id']) === 'self' && age_on((string) $selected['birth_date']) <= 10;
page_start($tab === 'nustatymai' ? t('Nustatymai') : t('Mano paskyra'), ['noindex' => true, 'account' => $tab]);
?>
<div class="account-top">
  <div>
    <div class="eyebrow"><?= $tab === 'nustatymai' ? t('Nustatymai') : ($little ? t('Mano diena') : t('Apžvalga')) ?></div>
    <?php if (!($kidHome = $little && $active && $tab === 'apzvalga')): // B8: mažam vaikui pasisveikinimas ir diržas - kortelėje žemiau ?>
      <div class="hello"><?= te('Sveiki,') ?> <?= e($a['first_name']) ?>!</div>
      <?= $selected && $active && $tab === 'apzvalga' ? belt_strip_html($selected) : '' ?>
    <?php endif; ?>
  </div>
  <?php if (count($members) > 1): ?>
    <div class="member-tabs">
      <?php foreach ($members as $m): ?>
        <a href="?<?= $tab === 'nustatymai' ? 'skiltis=nustatymai&amp;' : '' ?>m=<?= (int) $m['id'] ?>" class="<?= $selected && $m['id'] === $selected['id'] ? 'active' : '' ?>">
          <?= e(member_short_name($m, $members)) ?><?= $m['relation'] === 'self' ? (' ' . t('(aš)')) : '' ?><?php if ($u = member_attention_count((int) $m['id']) + account_pending_invites($aid, (int) $m['id'])): ?> <span class="count-badge" title="<?= te('Reikia padaryti: užduotys, pastabos, kvietimai') ?>"><?= $u ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if (!$kidHome): // B8: vaikui „Atsijungti“ - ne pirmame ekrane (meniu ir Nustatymuose) ?>
    <form method="post" action="<?= url('atsijungti.php') ?>" class="inline-form logout"><?= csrf_field() ?><button type="submit" class="btn btn-ghost btn-sm"><?= te('Atsijungti') ?></button></form>
  <?php endif; ?>
</div>
<?php if ($kidHome):
    // B8 (sprendimas 2026-11-21, variantas B): 7-10 m. vaikas savo paskyroje - viršuje lygis ir XP, 2 mažos kortelės
    // (artimiausia treniruotė, viena užduotis su „✓ Atlikau“), „Kas toliau“ juosta. Be kainų; visa kita - žemiau.
    $kl = xp_level(member_points_total((int) $selected['id']));
    $kBelt = current_belt($selected['belt_level'] !== null ? (int) $selected['belt_level'] : null);
    $kNext = member_next_training($selected);
    $kTask = array_values(array_filter($todoNotes, function ($n) { return $n['is_task'] && !$n['done_at']; }))[0] ?? null;
    $kSoon = array_slice(upcoming_events($selected['group_id'] ? (int) $selected['group_id'] : null, 3), 0, 3); ?>
  <section class="kid-day" aria-label="<?= te('Mano diena') ?>">
    <div class="kid-hero">
      <div class="kid-ring" style="--p:<?= (int) $kl['pct'] ?>;" role="img" aria-label="<?= te('Lygis') ?> <?= (int) $kl['level'] ?>"><b><?= (int) $kl['level'] ?></b><small><?= te('lygis') ?></small></div>
      <div class="kid-hi">
        <h1><?= te('Labas,') ?> <?= e($selected['first_name']) ?>!</h1>
        <p><?= (int) $kl['xp'] ?> XP · <?= te('dar {xp} XP iki {n} lygio', ['xp' => $kl['need'], 'n' => $kl['level'] + 1]) ?><?= xp_card_gift() ? ' 🎁' : '' ?></p>
        <div class="kid-belt"><?= belt_icon_svg($kBelt) ?> <?= e(BELTS[$kBelt][0] . ' · ' . t(BELTS[$kBelt][1])) ?></div>
      </div>
    </div>
    <div class="kid-two">
      <a class="kid-mini" href="#tvarkarastis">
        <span class="kid-ic" aria-hidden="true">🥋</span>
        <?php if ($kNext): ?>
          <b><?= e(day_word($kNext['date'])) ?><?= $kNext['start'] !== '' ? ' ' . e($kNext['start']) : '' ?></b>
          <span><?= e($kNext['date'] !== date('Y-m-d') && $kNext['date'] !== date('Y-m-d', strtotime('+1 day')) ? fmt_date($kNext['date']) . ' · ' : '') ?><?= e(preg_replace('~\s*\(.*\)$~u', '', $kNext['location'])) ?></span>
        <?php else: ?>
          <b><?= te('Treniruotė') ?></b><span><?= te('Artimiausių treniruočių nėra') ?></span>
        <?php endif; ?>
      </a>
      <div class="kid-mini">
        <span class="kid-ic" aria-hidden="true"><?= $kTask ? '📋' : '🎉' ?></span>
        <?php if ($kTask): ?>
          <b class="kid-task"><?= e(mb_strimwidth(trim(preg_replace('~\s+~u', ' ', $kTask['body'])), 0, 70, '…')) ?></b>
          <span><?= te('Užduotis nuo trenerio') ?></span>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="member_id" value="<?= (int) $selected['id'] ?>"><input type="hidden" name="note_id" value="<?= (int) $kTask['id'] ?>"><input type="hidden" name="back" value="diena">
            <button class="btn btn-primary kid-done" name="action" value="task_done"><?= te('✓ Atlikau') ?></button></form>
        <?php else: ?>
          <b><?= te('Užduočių nėra') ?></b>
          <a class="small" href="<?= url('uzduotys.php?m=' . (int) $selected['id']) ?>"><?= te('Visos užduotys →') ?></a>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($kSoon): ?>
      <div class="kid-next">
        <div class="kicker"><?= te('Kas toliau') ?></div>
        <div class="kid-row">
          <?php foreach ($kSoon as $ev): ?>
            <a class="kid-chip" href="<?= url('renginys.php?id=' . (int) $ev['id']) ?>"><b><?= e(fmt_date($ev['starts_on'])) ?></b><span><?= e($ev['title']) ?></span></a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php if ($a['status'] === 'pending_approval'): ?>
  <div class="flash flash-info"><?= te('Paskyra laukia trenerio patvirtinimo. Kai treneris patvirtins ir priskirs grupę, čia matysite tvarkaraštį, renginius ir taškus.') ?></div>
<?php endif; ?>
<?= form_errors($errors) ?>
<?php $svNotes = !empty($kTask) ? array_values(array_filter($todoNotes, function ($n) use ($kTask) { return (int) $n['id'] !== (int) $kTask['id']; })) : ($todoNotes ?? []); ?>
<?php if ($active && $tab === 'apzvalga' && ($invites || ($examTest && !$examTest['passed']) || $svNotes || $newReplies || $notifs)):
    $back = 'paskyra.php?m=' . (int) $selected['id']; ?>
  <!-- Svarbu: kas laukia atsakymo ar dėmesio. Pažymėjus „Supratau“ / „Perskaičiau“ - dingsta. -->
  <section class="panel card important" id="svarbu">
    <div class="row between" style="gap:10px; flex-wrap:wrap;">
      <div class="kicker" style="margin:0;"><?= te('Svarbu') ?><?= count($members) > 1 ? ' · ' . e($selected['first_name']) : '' ?></div>
      <?php if (count($unreadIds ?? []) > 1): ?>
        <form method="post" class="inline-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="note_read"><input type="hidden" name="member_id" value="<?= (int) $selected['id'] ?>"><input type="hidden" name="note_id" value="all">
          <button class="btn btn-ghost btn-sm" type="submit"><?= te('✓ Visas pastabas perskaičiau') ?></button>
        </form>
      <?php endif; ?>
    </div>
    <ul class="notif-list">
      <?php foreach ($invites as $ev): ?>
        <li class="notif is-new t-invite">
          <span class="notif-icon" aria-hidden="true">📅</span>
          <div class="notif-body">
            <div class="notif-title"><a href="<?= url('renginys.php?id=' . (int) $ev['id']) ?>"><?= $ev['p_status'] === 'late' ? '' : (t('Kvietimas:') . ' ') ?><?= e($ev['title']) ?></a></div>
            <div class="notif-text"><?= e(fmt_date($ev['starts_on'], true)) ?><?= $ev['start_time'] ? ', ' . e(fmt_time($ev['start_time'])) : '' ?><?= $ev['location'] ? ' · ' . e($ev['location']) : '' ?></div>
            <?= render_event_rsvp($ev, [$selected], $back . '#svarbu') ?>
          </div>
        </li>
      <?php endforeach; ?>
      <?php if ($examTest && !$examTest['passed']): ?>
        <li class="notif is-new t-exam">
          <span class="notif-icon" aria-hidden="true">📝</span>
          <div class="notif-body">
            <div class="notif-title"><?= te('Prieš egzaminą išlaikyk testą') ?></div>
            <div class="notif-text"><?= e($examSoon['title']) ?> · <?= e(fmt_date($examSoon['starts_on'], true)) ?> <?= te('Reikia surinkti bent') ?> <?= (int) $examTest['quiz']['pass_percent'] ?> %<?= $examTest['best'] !== null ? (' ' . t('(geriausias:') . ' ') . (int) $examTest['best'] . ' %)' : '' ?>.</div>
          </div>
          <div class="notif-actions">
            <a class="btn btn-primary btn-sm" href="<?= url('testas.php?q=' . (int) $examTest['quiz']['id'] . '&m=' . (int) $selected['id']) ?>"><?= te('Laikyti testą') ?></a>
            <a class="btn btn-ghost btn-sm" href="<?= url('egzaminas.php?m=' . (int) $selected['id']) ?>"><?= te('Programa') ?></a>
          </div>
        </li>
      <?php endif; ?>
      <?php foreach ($svNotes as $n): $isLesson = $n['lesson_id'] && $n['lesson_title']; ?>
        <li class="notif is-new t-note">
          <span class="notif-icon" aria-hidden="true"><?= $isLesson ? '📘' : ($n['is_task'] ? '✏️' : '💬') ?></span>
          <div class="notif-body">
            <div class="notif-title">
              <?php if ($isLesson): ?><?= te('Treneris atsiuntė pamoką:') ?> <a href="<?= url('pamokos.php?id=' . (int) $n['lesson_id']) ?>"><?= e($n['lesson_title']) ?></a>
              <?php else: ?><a href="#n<?= (int) $n['id'] ?>"><?= $n['is_task'] ? t('Užduotis nuo trenerio') : t('Nauja trenerio pastaba') ?></a><?php endif; ?>
            </div>
            <div class="notif-text"><?= text_to_html($n['body']) ?></div>
            <?php if ($n['youtube_id']): ?><?= youtube_embed($n['youtube_id']) ?><?php endif; ?>
            <div class="notif-meta"><?= e(fmt_date($n['note_date'] ?? substr($n['created_at'], 0, 10))) ?><?= $n['author'] ? ' · ' . e($n['author']) : '' ?></div>
          </div>
          <div class="notif-actions">
            <form method="post" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="member_id" value="<?= (int) $selected['id'] ?>"><input type="hidden" name="note_id" value="<?= (int) $n['id'] ?>">
              <input type="hidden" name="back" value="svarbu">
              <?php if ($n['is_task']): ?>
                <button class="btn btn-primary btn-sm" name="action" value="task_done"><?= te('✓ Atlikau') ?></button>
              <?php else: ?>
                <button class="btn btn-ghost btn-sm" name="action" value="note_read"><?= $isLesson ? t('✓ Peržiūrėjau') : t('✓ Perskaičiau') ?></button>
              <?php endif; ?>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
      <?php foreach ($newReplies as $key => $r): ?>
        <li class="notif is-new t-reply">
          <span class="notif-icon" aria-hidden="true">💬</span>
          <div class="notif-body">
            <div class="notif-title"><?= e(message_author($r, false)) ?> <?= te('atsakė') ?><?= $key[0] === 'n' ? ': <a href="#' . e($key) . '">į pastabą</a>' : ($key !== 'general' ? ' · ' . message_topic_title($key, $r) : '') ?></div>
            <div class="notif-text"><?= e(mb_strimwidth(trim(preg_replace('~\s+~u', ' ', $r['body'])), 0, 200, '…')) ?></div>
            <div class="notif-meta"><?= e(notification_when($r['created_at'])) ?></div>
          </div>
          <div class="notif-actions">
            <?php if ($key === 'general'): ?><button type="button" class="btn btn-primary btn-sm" data-open-chat data-member="<?= (int) $selected['id'] ?>"><?= te('Atsakyti') ?></button><?php endif; ?>
            <form method="post" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="action" value="replies_read"><input type="hidden" name="member_id" value="<?= (int) $selected['id'] ?>">
              <input type="hidden" name="topic" value="<?= e($key) ?>">
              <button class="btn btn-ghost btn-sm" type="submit"><?= te('✓ Perskaičiau') ?></button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php // B5: naujienos - 2 naujausios, kitos suskleistos, „Supratau visus“ ?>
    <?= render_notifications(array_slice($notifs, 0, 2), $back . '#svarbu') ?>
    <?php if (count($notifs) > 2): ?>
      <details class="fb-more"><summary class="small"><?= te('Rodyti ankstesnius') ?> (<?= count($notifs) - 2 ?>)</summary><?= render_notifications(array_slice($notifs, 2), $back . '#svarbu') ?></details>
    <?php endif; ?>
    <?php if (count($notifs) > 1): ?>
      <form method="post" action="<?= url('pranesimas.php') ?>" class="inline-form notif-all"><?= csrf_field() ?><input type="hidden" name="action" value="done_notices"><input type="hidden" name="back" value="<?= e($back . '#svarbu') ?>">
        <button class="btn btn-ghost btn-sm" type="submit"><?= te('Supratau visus') ?></button></form>
    <?php endif; ?>
    <p class="small" style="margin:10px 0 0;"><a href="<?= url('uzduotys.php?m=' . (int) $selected['id']) ?>"><?= te('📋 Visos užduotys, pamokos ir pranešimai - ir jau peržiūrėti →') ?></a></p>
  </section>
<?php endif; ?>


<?php if ($dojoHero): $dh = game_hero_row((int) $dojoHero['id']); ?>
  <!-- Personažas gyvena atskirame puslapyje „🏯 Dojo“ (viršutinėje juostoje) -->
  <a class="panel card game-teaser" href="<?= url('dojo.php?h=' . (int) $dh['id']) ?>">
    <span class="gt-ic" aria-hidden="true"><?= e(GAME_SPECIES[$dh['species']][1] ?? '🥋') ?></span>
    <span><b><?= te('🏯 Dojo') ?></b><br><span class="muted"><?= te('Tavo personažas, gyvūnėliai, namas ir kovos') ?> · ⭐ <?= te('Lygis') ?> <?= game_level($dh) ?></span></span>
    <span class="btn btn-primary"><?= te('Žaisti') ?> →</span>
  </a>
<?php endif; ?>

<?php if ($isStaff): ?>
  <!-- Trenerio šeimos nariai (jei treniruojasi): taškai, pastabos, egzaminas - kaip mato narys. Visa kita - „Paskyra“ (Treneriams). -->
  <?php if ($members): ?><h2 style="margin:28px 0 12px;"><?= te('Nariai mano paskyroje') ?></h2><?php endif; ?>
<?php endif; ?>

<?php if ($selected && $tab === 'apzvalga'): ?>
  <div class="grid-2 account-main <?= $active ? 'has-points' : 'no-points' ?>">
    <?php if ($active): ?>
      <!-- Taškai ir Top 5 - svarbiausia, todėl pirma -->
      <div class="panel card points-card">
        <div class="points-head">
          <div>
            <div class="kicker"><?= count($members) > 1 ? e($selected['first_name']) . ' · ' : (t('Mano') . ' ') ?><?= te('taškai ·') ?> <?= e($seasonLabel) ?></div>
            <div class="row" style="align-items:baseline; gap:8px;">
              <span class="big-number"><?= $seasonPoints ?></span><span class="muted"><?= $little ? 'XP' : te('aktyvumo taškai') ?></span>
            </div>
          </div>
        </div>
        <?= render_xp_card($selected, false, false) ?>

        <div class="top-tabs">
          <?php foreach ($topChoices as $key => $p): ?>
            <a href="?m=<?= (int) $selected['id'] ?>&amp;top=<?= e($key) ?>" class="<?= $key === $topKey ? 'active' : '' ?>"><?= e($key === 'mano' ? $p[2] . (' ' . t('(mano)')) : $p[2]) ?></a>
          <?php endforeach; ?>
        </div>

        <div class="kicker" style="margin-top:4px;"><?= $little ? te('Geriausi ·') : te('Top 5 ·') ?> <?= e($partition[2]) ?></div>
        <?php if (!$rows): ?>
          <p class="muted"><?= te('Šį sezoną aktyvumo taškų dar niekas neturi.') ?></p>
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
      <div class="panel card" id="tvarkarastis">
        <div class="kicker"><?= te('Tvarkaraštis') ?> <?= $selected['group_id'] ? staff_link('admin/grupes.php?id=' . (int) $selected['group_id'], t('✎ Keisti')) : '' ?></div>
        <?php if ($active && $selected['group_id']): ?>
          <h2><?= e($selected['group_name']) ?></h2>
          <?= render_cancellation_notes(cancellations_for_groups([(int) $selected['group_id']], 30)) ?>
          <?= render_schedule_lines(schedule_lines(group_schedule((int) $selected['group_id']))) ?>
          <div class="kicker" style="margin-top:14px;"><?= te('Lankomumas') ?></div>
          <?= render_attendance((int) $selected['id']) ?>
          <?php // Mokestis pagal lankomumą (jei grupei nustatyta kaina už treniruotę): šio mėnesio suma
          $fg = !empty($selected['group_id']) ? q_one('SELECT * FROM training_groups WHERE id = ?', [(int) $selected['group_id']]) : null;
          $selfKid = account_relation((int) $a['id'], (int) $selected['id']) === 'self' && !empty($selected['birth_date']) && age_on((string) $selected['birth_date']) < 18;   // vaikas kainų nemato - tik tėvai
          if (!$selfKid && $fg && group_visit_price($fg)): $ym = date('Y-m'); $ff = group_month_fees($fg, $ym)[(int) $selected['id']] ?? null; $vp = group_visit_price($fg); ?>
            <p class="fee-line" style="font-size:15px;">💶 <b><?= e(group_fee_text($fg, $ff, $ym)) ?></b><?= $vp['cap'] !== null ? ' <span class="muted small">· ' . te('ne daugiau kaip {s} per mėn.', ['s' => money_fmt($vp['cap'])]) . '</span>' : '' ?></p>
          <?php endif; ?>
        <?php else: ?>
          <h2><?= e($selected['first_name'] . ' ' . $selected['last_name']) ?></h2>
          <p class="muted">
            <?= $selected['status'] === 'inactive' ? t('Narystė neaktyvi.') : t('Laukiama trenerio patvirtinimo - po to čia matysite grupę, tvarkaraštį ir taškus.') ?>
          </p>
        <?php endif; ?>
      </div>

      <div class="panel card events-card">
        <div class="kicker"><?= te('Artėjantys renginiai') ?> <?= staff_link('renginiai.php', t('✎ Keisti')) ?></div>
        <?php $events = $active ? upcoming_events($selected['group_id'] ? (int) $selected['group_id'] : null, 20) : []; ?>
        <?php if (!$events): ?>
          <p class="muted"><?= te('Artėjančių renginių nėra.') ?></p>
        <?php else: ?>
          <ul class="list events-scroll">
            <?php foreach ($events as $ev): ?><li><?= render_event($ev) ?></li><?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php if ($active && varzybos_enabled()): ?><a class="card-link" href="<?= url('varzybos.php') ?>"><?= te('🥋 Registracija į varžybas →') ?></a><?php endif; ?>
      </div>
    </div>
  </div>

  <?php // Naujos pastabos ir neatliktos užduotys - tik „Svarbu“; čia - ankstesnės (perskaitytos, atliktos), su atsakymais
  $todoIds = array_map('intval', array_column($todoNotes ?? [], 'id'));
  $pastNotes = array_values(array_filter($notes ?? [], function ($n) use ($todoIds) { return !in_array((int) $n['id'], $todoIds, true); })); ?>
  <?php if ($active && $pastNotes): ?>
    <div class="panel card" id="pastabos" style="margin-top:20px;">
      <div class="kicker"><?= te('Trenerio pastabos') ?></div>
      <?php foreach ($pastNotes as $n): ?>
        <?php $openTask = $n['is_task'] && !$n['done_at']; ?>
        <div class="coach-note <?= in_array((int) $n['id'], $unreadIds, true) || $openTask ? 'unread' : '' ?>" id="n<?= (int) $n['id'] ?>">
          <div class="meta">
            <?= note_source($n) ?><?= $n['author'] ? (' ' . t('· treneris') . ' ') . e($n['author']) : '' ?>
            <?= !$n['is_task'] && $n['read_at'] === null ? (' <span class="badge badge-new">' . t('Nauja') . '</span>') : '' ?>
            <?php if ($n['is_task']): ?>
              <?= $n['done_at'] ? (' <span class="badge badge-ok">' . t('✓ Atlikta') . ' ') . e(fmt_date(substr($n['done_at'], 0, 10))) . '</span>' : (' <span class="badge badge-warn">' . t('Užduotis') . '</span>') ?>
            <?php endif; ?>
          </div>
          <div><?= text_to_html($n['body']) ?></div>
          <?php if ($n['lesson_id'] && $n['lesson_title']): ?>
            <a class="note-lesson" href="<?= url('pamokos.php?id=' . (int) $n['lesson_id']) ?>"><?= te('📘 Pamoka:') ?> <?= e($n['lesson_title']) ?> →</a>
          <?php endif; ?>
          <?php if ($n['youtube_id']): ?><?= youtube_embed($n['youtube_id']) ?><?php endif; ?>
          <?php if ($n['is_task'] && $selected['relation']): ?>
            <form method="post" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="note_id" value="<?= (int) $n['id'] ?>">
              <?php if (!$n['done_at']): ?>
                <button class="btn btn-primary btn-sm" name="action" value="task_done" style="margin-top:10px;"><?= te('✓ Atlikau') ?></button>
              <?php else: ?>
                <button class="linklike small muted" name="action" value="task_undo" style="margin-top:6px; border:none; padding:0; text-decoration:underline;"><?= te('grąžinti į neatliktas') ?></button>
              <?php endif; ?>
            </form>
          <?php endif; ?>
          <?php if (!$n['is_task'] && $n['read_at'] === null): ?>
            <form method="post" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="action" value="note_read"><input type="hidden" name="member_id" value="<?= (int) $selected['id'] ?>"><input type="hidden" name="note_id" value="<?= (int) $n['id'] ?>">
              <button class="btn btn-primary btn-sm" type="submit" style="margin-top:10px;"><?= te('✓ Perskaičiau') ?></button>
            </form>
          <?php endif; ?>
          <?php $nm = $noteMsgs[(int) $n['id']] ?? []; ?>
          <?php if ($nm): ?><div class="msg-thread"><?php foreach ($nm as $r): ?><?= render_message($r, false) ?><?php endforeach; ?></div><?php endif; ?>
          <details class="msg-reply"<?= $nm && end($nm)['from_staff'] ? ' open' : '' ?>>
            <summary>💬 <?= $nm ? t('Atsakyti') : t('Atsakyti treneriui') ?></summary>
            <?= message_form('msg', ['member_id' => (int) $selected['id'], 'note_id' => (int) $n['id']], t('Parašykite treneriui - matys tik treneriai'), t('Siųsti'), url('zinute.php')) ?>
          </details>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="panel card" style="margin-top:20px;">
    <div class="kicker"><?= te('Istorija') ?></div>
    <?php if (!$active || !$history): ?>
      <p class="muted"><?= te('Čia matysite egzaminus, varžybas, seminarus ir kitus pasiekimus.') ?></p>
    <?php else: ?>
      <ul class="list small history-list">
        <?php foreach (array_slice($history, 0, 20) as $h): ?>
          <li class="row between">
            <span>
              <strong><?= e($h['event_title'] ?: t($h['category_name'])) ?></strong>
              <div class="muted"><?= $h['event_title'] ? e(t($h['category_name'])) . ' · ' : '' ?><?= e(fmt_date($h['awarded_on'], true)) ?><?= $h['note'] ? ' · ' . e($h['note']) : '' ?></div>
            </span>
            <span class="badge badge-ok">+<?= (int) $h['points'] ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

<?php endif; ?>

<?php if ($selected && $tab === 'nustatymai'): ?>
  <div class="panel card" style="margin-top:4px;">
    <div class="kicker"><?= te('Nario duomenys') ?></div>
    <div class="row between">
      <div>
        <strong><?= e($selected['first_name'] . ' ' . $selected['last_name']) ?></strong>
        <div class="muted small"><?= te('Gimimo data:') ?> <?= e($selected['birth_date']) ?> · <?= age_on($selected['birth_date']) ?> m.</div>
      </div>
      <span class="badge <?= $selected['status'] === 'active' ? 'badge-ok' : 'badge-warn' ?>">
        <?= ['pending' => t('Laukia patvirtinimo'), 'active' => t('Aktyvus'), 'inactive' => t('Neaktyvus')][$selected['status']] ?>
      </span>
    </div>

    <form method="post" class="form belt-form" style="margin-top:16px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="belt">
      <input type="hidden" name="member_id" value="<?= (int) $selected['id'] ?>">
      <label><?= te('Diržas') ?> <span class="hint"><?= te('pasikeitus po egzamino - pakeiskite čia') ?></span>
        <select name="belt_level" onchange="this.form.submit()"><?= belt_options($selected['belt_level'] !== null ? (int) $selected['belt_level'] : null, t('— nežinau / dar neturi —')) ?></select></label>
      <noscript><button class="btn btn-ghost btn-sm" type="submit"><?= te('Išsaugoti') ?></button></noscript>
    </form>

    <?php $canPhoto = $selected['relation'] === 'parent' || age_on($selected['birth_date']) >= CONSENT_AGE; ?>
    <?php if ($canPhoto && $selected['photo_consent']): ?>
      <!-- Sutikimas duotas - langelio nebereikia, bet atšaukti galima visada (BDAR) -->
      <form method="post" class="consent-line" onsubmit="return confirm('Atšaukti sutikimą skelbti nuotraukas ir vaizdo įrašus?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="photo_consent">
        <input type="hidden" name="member_id" value="<?= (int) $selected['id'] ?>">
        <span class="small muted"><?= te('✓ Sutikimas skelbti nuotraukas ir vaizdo įrašus duotas') ?></span>
        <button class="linklike small muted" type="submit" style="border:none; padding:0; text-decoration:underline;"><?= te('atšaukti') ?></button>
      </form>
    <?php elseif ($canPhoto): ?>
      <form method="post" class="form" style="margin-top:16px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="photo_consent">
        <input type="hidden" name="member_id" value="<?= (int) $selected['id'] ?>">
        <label class="check"><input type="checkbox" name="photo_consent" value="1" onchange="this.form.submit()">
          <span><?= te('Sutinku, kad klubas skelbtų') ?> <?= $selected['relation'] === 'parent' ? 'vaiko' : 'mano' ?> <?= te('nuotraukas ir vaizdo įrašus') ?></span></label>
        <noscript><button class="btn btn-ghost btn-sm" type="submit"><?= te('Išsaugoti') ?></button></noscript>
      </form>
    <?php else: ?>
      <p class="small muted" style="margin-top:12px;"><?= te('Sutikimas dėl nuotraukų:') ?> <?= $selected['photo_consent'] ? 'duotas' : 'neduotas' ?> <?= te('(keičia tėvai).') ?></p>
    <?php endif; ?>

    <?php if ($selected['relation'] === 'parent'):
        $kidAge = !empty($selected['birth_date']) ? age_on((string) $selected['birth_date']) : 0;
        $kidPin = $selected['own_login_email'] && str_ends_with((string) $selected['own_login_email'], '@' . KID_LOGIN_DOMAIN); ?>
      <hr class="divider">
      <?php if ($kidPin): $kidOff = $selected['own_login_status'] !== 'active'; ?>
        <div class="kid-login" id="vaiko-prisijungimas">
          <p><b><?= e($selected['first_name']) ?> <?= te('jungiasi pats (-i)') ?></b> · <?= te('vardas') ?> <code class="kl-user"><?= e(login_name((string) $selected['own_login_email'])) ?></code>
            <?= $kidOff ? '<span class="badge">' . te('išjungta') . '</span>' : '' ?></p>
          <p class="small muted"><?= te('Prisijungti → vietoj el. pašto įrašyti vardą, vietoj slaptažodžio - PIN.') ?></p>
          <form method="post" class="form kl-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="kid_pin"><input type="hidden" name="member_id" value="<?= (int) $selected['id'] ?>">
            <label><?= te('Naujas PIN') ?> <span class="hint"><?= te('4-6 skaitmenys') ?></span>
              <input type="text" name="pin" inputmode="numeric" pattern="[0-9]{4,6}" minlength="4" maxlength="6" autocomplete="off" required></label>
            <button class="btn btn-ghost" type="submit"><?= te('Keisti PIN') ?></button>
          </form>
          <?php $kidChat = !q_value('SELECT chat_off FROM accounts WHERE email = ?', [$selected['own_login_email']]); ?>
          <form method="post" class="kl-chat">
            <?= csrf_field() ?><input type="hidden" name="action" value="kid_chat"><input type="hidden" name="member_id" value="<?= (int) $selected['id'] ?>">
            <input type="hidden" name="chat" value="0">
            <label class="tg-row"><input type="checkbox" class="tg" name="chat" value="1"<?= $kidChat ? ' checked' : '' ?> onchange="this.form.submit()">
              <span><b><?= te('Gali rašyti treneriui') ?></b><small><?= te('Išjungus - žinutes treneriui rašote tik jūs.') ?></small></span></label>
            <noscript><button class="btn btn-ghost btn-sm" type="submit"><?= te('Išsaugoti') ?></button></noscript>
          </form>
          <form method="post" class="kl-toggle"<?= $kidOff ? '' : ' onsubmit="return confirm(this.dataset.ask)" data-ask="' . e(t('Išjungti vaiko prisijungimą?')) . '"' ?>>
            <?= csrf_field() ?><input type="hidden" name="action" value="kid_login_toggle"><input type="hidden" name="member_id" value="<?= (int) $selected['id'] ?>">
            <button class="btn btn-sm <?= $kidOff ? 'btn-primary' : 'btn-danger' ?>" type="submit"><?= $kidOff ? te('Įjungti vėl') : te('Išjungti prisijungimą') ?></button>
          </form>
        </div>
      <?php elseif ($selected['own_login_email']): ?>
        <p class="small"><?= e($selected['first_name']) ?> <?= te('turi savo prisijungimą:') ?> <strong><?= e($selected['own_login_email']) ?></strong></p>
      <?php else: ?>
        <?php if ($kidAge >= KID_LOGIN_MIN_AGE): $kidOpen = $errors && post('action') === 'kid_login'; ?>
        <details class="kid-login" id="vaiko-prisijungimas" <?= $kidOpen ? 'open' : '' ?>>
          <summary class="summary-link"><?= te('Prisijungimas vaikui: vardas ir PIN') ?></summary>
          <p class="small muted" style="margin:10px 0;"><?= te('Vaikui nereikia el. pašto: sugalvokite prisijungimo vardą ir PIN. Vaikas matys savo tvarkaraštį, XP ir užduotis, bet ne kainas. PIN bet kada pakeisite čia; treneriui tvirtinti nereikia.') ?></p>
          <form method="post" class="form kl-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="kid_login"><input type="hidden" name="member_id" value="<?= (int) $selected['id'] ?>">
            <label><?= te('Prisijungimo vardas') ?> <span class="hint"><?= te('lotyniškos raidės, be tarpų') ?></span>
              <input type="text" name="username" value="<?= e($kidOpen ? post('username') : kid_username_suggest((string) $selected['first_name'])) ?>" pattern="[a-zA-Z][a-zA-Z0-9._\-]{2,19}" maxlength="20" autocapitalize="none" autocomplete="off" spellcheck="false" required></label>
            <label><?= te('PIN') ?> <span class="hint"><?= te('4-6 skaitmenys') ?></span>
              <input type="text" name="pin" inputmode="numeric" pattern="[0-9]{4,6}" minlength="4" maxlength="6" autocomplete="off" required></label>
            <button class="btn btn-primary" type="submit"><?= te('Sukurti prisijungimą') ?></button>
          </form>
        </details>
        <?php endif; ?>
        <details <?= $errors && post('action') === 'invite' ? 'open' : '' ?>>
          <summary class="summary-link"><?= te('Ar') ?> <?= e($selected['first_name']) ?> <?= te('nori jungtis pats (-i)? Sukurkite atskirą prisijungimą') ?></summary>
          <p class="small muted" style="margin:10px 0;">
            <?= te('Jei vaikas turi savo el. paštą, jis gali turėti') ?> <strong><?= te('atskirą prisijungimą') ?></strong> <?= te('ir pats matyti savo tvarkaraštį, renginius ir taškus.
            Įveskite vaiko el. paštą - jis gaus laišką su nuoroda slaptažodžiui susikurti.
            Jūs ir toliau viską matysite savo paskyroje. Tai neprivaloma.') ?>
          </p>
          <form method="post" class="form inline-fields">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="invite">
            <input type="hidden" name="member_id" value="<?= (int) $selected['id'] ?>">
            <label><?= te('Vaiko el. paštas') ?> <input type="email" name="email" required></label>
            <button class="btn btn-ghost" type="submit"><?= te('Siųsti kvietimą') ?></button>
          </form>
        </details>
      <?php endif; ?>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($canAddKid && ($tab === 'nustatymai' || !$members)): ?>
  <details class="panel card" id="vaikai" style="margin-top:20px;" <?= !$members || ($errors && post('action') === 'add_kid') ? 'open' : '' ?>>
    <summary style="cursor:pointer; font-family:'Space Grotesk',sans-serif; font-weight:700;"><?= te('+ Pridėti vaiką') ?></summary>
    <form method="post" class="form" style="margin-top:16px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_kid">
      <div class="form-row">
        <label><?= te('Vardas') ?> <input type="text" name="first_name" required></label>
        <label><?= te('Pavardė') ?> <input type="text" name="last_name" required></label>
      </div>
      <div class="form-row">
        <label><?= te('Gimimo data') ?> <?= date_parts_field('birth_date', null) ?></label>
        <label><?= te('Diržas') ?> <span class="hint"><?= te('nežinote - palikite tuščią') ?></span><select name="belt_level"><?= belt_options(null, t('— nežinau / dar neturi —')) ?></select></label>
      </div>
      <?= photo_consent_choice('photo_consent', post('action') === 'add_kid' ? post('photo_consent') : null, t('Ar sutinkate, kad klubas skelbtų vaiko nuotraukas ir vaizdo įrašus?')) ?>
      <div><button class="btn btn-primary" type="submit"><?= te('Pridėti') ?></button></div>
    </form>
  </details>
<?php endif; ?>

<?php if ($canAddSelf && $tab === 'nustatymai' && $members): $selfOpen = $errors && post('action') === 'add_self'; ?>
  <details class="panel card" id="as-pats" style="margin-top:20px;" <?= $selfOpen ? 'open' : '' ?>>
    <summary style="cursor:pointer; font-family:'Space Grotesk',sans-serif; font-weight:700;"><?= te('+ Treniruojuosi ir aš') ?></summary>
    <p class="small muted" style="margin:12px 0 0;"><?= te('Jei treniruojatės ir jūs - pridėkite save. Treneris patvirtins ir priskirs grupę; vaikai lieka jūsų paskyroje.') ?></p>
    <form method="post" class="form" style="margin-top:12px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_self">
      <div class="form-row">
        <label><?= te('Mano gimimo data') ?> <?= date_parts_field('birth_date', null) ?></label>
        <label><?= te('Diržas') ?> <span class="hint"><?= te('nežinote - palikite tuščią') ?></span><select name="belt_level"><?= belt_options(null, t('— nežinau / dar neturi —')) ?></select></label>
      </div>
      <?= photo_consent_choice('photo_consent', $selfOpen ? post('photo_consent') : null, t('Ar sutinkate, kad klubas skelbtų jūsų nuotraukas ir vaizdo įrašus?')) ?>
      <div><button class="btn btn-primary" type="submit"><?= te('Pridėti save') ?></button></div>
    </form>
  </details>
<?php endif; ?>

<?php if ($tab === 'nustatymai'): ?>
  <?php // Treneriui paskyros nustatymai - Treneriams → Nustatymai (kad nesikartotų) ?>
  <?= is_staff($a) ? '' : render_account_settings($a) ?>
  <?= render_app_install_card() ?>
<?php endif; ?>
<?php
page_end();
