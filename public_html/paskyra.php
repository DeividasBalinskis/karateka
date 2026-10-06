<?php
// Nario / tėvų paskyra: grupė, tvarkaraštis, renginiai, vaikai, nustatymai
require __DIR__ . '/app/bootstrap.php';

$a = require_login();
$aid = (int) $a['id'];
$errors = [];

if (is_post()) {
    csrf_check();
    $action = post('action');

    if ($action === 'add_kid') {
        $first = post('first_name');
        $last = post('last_name');
        $birth = post('birth_date');
        if ($first === '' || $last === '' || !valid_birth_date($birth)) {
            $errors[] = 'Įveskite vaiko vardą, pavardę ir gimimo datą.';
        } else {
            $mid = create_member($first, $last, $birth, !empty($_POST['photo_consent']));
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

$members = account_members($aid);
$selected = null;
foreach ($members as $m) {
    if ((int) $m['id'] === (int) get('m')) {
        $selected = $m;
    }
}
$selected = $selected ?? ($members[0] ?? null);
$isParent = (bool) array_filter($members, function ($m) { return $m['relation'] === 'parent'; }) || !array_filter($members, function ($m) { return $m['relation'] === 'self'; });

$active = $selected && $selected['status'] === 'active' && $a['status'] === 'active';
if ($active) {
    [$from, $to, $seasonLabel] = season_bounds();
    $seasonPoints = member_points_total((int) $selected['id'], $from, $to);
    $history = member_history((int) $selected['id']);
    $notes = member_notes((int) $selected['id']);
    $unreadIds = array_map('intval', array_column(array_filter($notes, function ($n) { return $n['read_at'] === null; }), 'id'));
    // Pažymime perskaitytomis (rodoma kaip „Nauja“ tik šį kartą). Treneriui peržiūrint - nežymime.
    if ($unreadIds && !is_staff($a)) {
        q('UPDATE coach_notes SET read_at = NOW() WHERE member_id = ? AND read_at IS NULL', [$selected['id']]);
    }

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
          <?= e($m['first_name']) ?><?= $m['relation'] === 'self' ? ' (aš)' : '' ?><?php if ($u = member_unread_notes((int) $m['id'])): ?> <span class="count-badge" title="Naujos trenerio pastabos"><?= $u ?></span><?php endif; ?>
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

<?php if ($selected): ?>
  <div class="grid-2 account-main">
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
          <?= render_schedule_lines(schedule_lines(group_schedule((int) $selected['group_id']))) ?>
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
      <div class="kicker">Trenerio pastabos<?= $unreadIds ? ' <span class="badge badge-new">' . count($unreadIds) . ' nauj.</span>' : '' ?></div>
      <?php foreach ($notes as $n): ?>
        <div class="coach-note <?= in_array((int) $n['id'], $unreadIds, true) ? 'unread' : '' ?>">
          <div class="meta">
            <?= e(fmt_date($n['note_date'], true)) ?><?= $n['author'] ? ' · treneris ' . e($n['author']) : '' ?>
            <?= in_array((int) $n['id'], $unreadIds, true) ? ' <span class="badge badge-new">Nauja</span>' : '' ?>
          </div>
          <div><?= text_to_html($n['body']) ?></div>
          <?php if ($n['youtube_id']): ?><?= youtube_embed($n['youtube_id']) ?><?php endif; ?>
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

    <?php $canPhoto = $selected['relation'] === 'parent' || age_on($selected['birth_date']) >= CONSENT_AGE; ?>
    <?php if ($canPhoto): ?>
      <form method="post" class="form" style="margin-top:16px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="photo_consent">
        <input type="hidden" name="member_id" value="<?= (int) $selected['id'] ?>">
        <label class="check"><input type="checkbox" name="photo_consent" value="1" <?= $selected['photo_consent'] ? 'checked' : '' ?> onchange="this.form.submit()">
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
      <div class="form-row three">
        <label>Vardas <input type="text" name="first_name" required></label>
        <label>Pavardė <input type="text" name="last_name" required></label>
        <label>Gimimo data <input type="date" name="birth_date" max="<?= date('Y-m-d') ?>" required></label>
      </div>
      <label class="check"><input type="checkbox" name="photo_consent" value="1">
        <span>Sutinku, kad klubas skelbtų vaiko nuotraukas ir vaizdo įrašus</span></label>
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
