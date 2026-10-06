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
$isParent = (bool) array_filter($members, fn($m) => $m['relation'] === 'parent') || !array_filter($members, fn($m) => $m['relation'] === 'self');

page_start('Mano paskyra', ['noindex' => true]);
?>
<div class="page-head">
  <div class="eyebrow">Mano paskyra</div>
  <h1 class="styled">Sveiki, <?= e($a['first_name']) ?>!</h1>
</div>

<?php if ($a['status'] === 'pending_approval'): ?>
  <div class="flash flash-info">Paskyra laukia trenerio patvirtinimo. Kai treneris patvirtins ir priskirs grupę, čia matysite tvarkaraštį ir renginius.</div>
<?php endif; ?>
<?= form_errors($errors) ?>

<?php if (count($members) > 1): ?>
  <div class="member-tabs">
    <?php foreach ($members as $m): ?>
      <a href="?m=<?= (int) $m['id'] ?>" class="<?= $selected && $m['id'] === $selected['id'] ? 'active' : '' ?>">
        <?= e($m['first_name']) ?><?= $m['relation'] === 'self' ? ' (aš)' : '' ?>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($selected): ?>
  <?php $active = $selected['status'] === 'active' && $a['status'] === 'active'; ?>
  <div class="grid-2">
    <div class="panel card">
      <div class="kicker">Grupė</div>
      <?php if ($active && $selected['group_id']): ?>
        <h2><?= e($selected['group_name']) ?></h2>
        <?= render_schedule(group_schedule((int) $selected['group_id'])) ?>
      <?php else: ?>
        <h2><?= e($selected['first_name'] . ' ' . $selected['last_name']) ?></h2>
        <p class="muted">
          <?= $selected['status'] === 'inactive' ? 'Narystė neaktyvi.' : 'Laukiama trenerio patvirtinimo - po to čia matysite grupę ir tvarkaraštį.' ?>
        </p>
      <?php endif; ?>
    </div>

    <div class="panel card">
      <div class="kicker">Artėjantys renginiai</div>
      <?php $events = $active ? upcoming_events($selected['group_id'] ? (int) $selected['group_id'] : null, 8) : []; ?>
      <?php if (!$events): ?>
        <p class="muted">Artėjančių renginių nėra.</p>
      <?php else: ?>
        <ul class="list">
          <?php foreach ($events as $ev): ?><li><?= render_event($ev) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
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
        <p class="small">Turi savo prisijungimą: <strong><?= e($selected['own_login_email']) ?></strong></p>
      <?php else: ?>
        <h3>Pakviesti <?= e($selected['first_name']) ?> prisijungti</h3>
        <p class="small muted" style="margin-bottom:10px;">Vaikas susikurs savo prisijungimą prie to paties nario - matys savo tvarkaraštį ir renginius.</p>
        <form method="post" class="form inline-fields">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="invite">
          <input type="hidden" name="member_id" value="<?= (int) $selected['id'] ?>">
          <label>Vaiko el. paštas <input type="email" name="email" required></label>
          <button class="btn btn-ghost" type="submit">Siųsti kvietimą</button>
        </form>
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
  <p class="muted small" style="margin-bottom:16px;"><?= e($a['first_name'] . ' ' . $a['last_name']) ?> · <?= e($a['email']) ?></p>
  <div class="grid-2">
    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="profile">
      <label>Telefonas <input type="tel" name="phone" value="<?= e($a['phone']) ?>" autocomplete="tel"></label>
      <div><button class="btn btn-ghost" type="submit">Išsaugoti</button></div>
    </form>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <label>Dabartinis slaptažodis <input type="password" name="current" autocomplete="current-password" required></label>
      <label>Naujas slaptažodis <input type="password" name="password" autocomplete="new-password" minlength="<?= MIN_PASSWORD ?>" required></label>
      <div><button class="btn btn-ghost" type="submit">Keisti slaptažodį</button></div>
    </form>
  </div>
</div>
<?php
page_end();
