<?php
// Atšauktos treniruotės ir atostogos: datos, priežastis, kurioms grupėms. Nariai mato tvarkaraštyje ir (jei pažymėta) „Svarbu“.
// Kuria ir trina adminas, treneriai mato sąrašą.
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();
$canEdit = is_admin($me);
$errors = [];

$groups = q_all('SELECT * FROM training_groups WHERE is_active = 1 ORDER BY sort_order, name');
$groupIds = array_map('intval', array_column($groups, 'id'));

if (is_post()) {
    csrf_check();
    if (!$canEdit) {
        forbidden();
    }
    if (post('action') === 'delete') {
        $id = (int) post('id');
        q('DELETE FROM training_cancellations WHERE id = ?', [$id]);
        notifications_done('cancelled', $id);
        flash('ok', t('Atšaukimas ištrintas - treniruotės vėl rodomos kaip įprastai.'));
        redirect('admin/atsaukimai.php');
    }

    $from = post('starts_on');
    $to = post('ends_on') ?: $from;
    $reason = trim(mb_substr(post('reason'), 0, 190));
    $picked = array_values(array_intersect($groupIds, array_map('intval', (array) ($_POST['groups'] ?? []))));
    $all = post('all') === '1' || count($picked) === count($groupIds);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $errors[] = t('Įveskite datą.');
    } elseif ($to < $from) {
        $errors[] = t('Pabaiga negali būti anksčiau nei pradžia.');
    } elseif ($to < date('Y-m-d')) {
        $errors[] = t('Ši data jau praėjo.');
    }
    if ($reason === '') {
        $errors[] = t('Įrašykite priežastį (pvz. „Mokyklinės atostogos“) - ją matys nariai.');
    }
    if (!$all && !$picked) {
        $errors[] = t('Pažymėkite grupes (arba „Visos grupės“).');
    }

    if (!$errors) {
        q('INSERT INTO training_cancellations (starts_on, ends_on, reason, created_by) VALUES (?, ?, ?, ?)', [$from, $to, $reason, (int) $me['id']]);
        $id = (int) db()->lastInsertId();
        if (!$all) {
            foreach ($picked as $gid) {
                q('INSERT INTO training_cancellation_groups (cancellation_id, group_id) VALUES (?, ?)', [$id, $gid]);
            }
        }
        $c = ['starts_on' => $from, 'ends_on' => $to, 'reason' => $reason];
        $sent = 0;
        if (post('notify') === '1' || post('email') === '1') {
            // Kiekviena paskyra - vieną kartą, nors ir keli vaikai atšauktose grupėse
            $accounts = q_all('SELECT a.id, a.email, a.first_name, GROUP_CONCAT(DISTINCT m.first_name ORDER BY m.first_name SEPARATOR ", ") AS kids
                                 FROM accounts a JOIN account_members am ON am.account_id = a.id JOIN members m ON m.id = am.member_id
                                WHERE a.status = "active" AND m.status = "active"' . ($all ? '' : ' AND m.group_id IN (' . implode(',', $picked) . ')') . ' AND a.id <> ?
                                GROUP BY a.id', [(int) $me['id']]);
            $title = (t('Treniruočių nebus:') . ' ') . cancellation_dates($c);
            foreach ($accounts as $acc) {
                if (post('notify') === '1') {
                    notify_account((int) $acc['id'], 'cancelled', $title, $reason . ($acc['kids'] ? ' (' . $acc['kids'] . ')' : ''), 'paskyra.php', null, $id);
                }
                if (post('email') === '1') {
                    send_mail($acc['email'], $title . ' — Karateka', "Sveiki, {$acc['first_name']},\n\n" . $title . ".\n" . $reason . "\n\nTvarkaraštis: " . abs_url('paskyra.php'));
                }
                $sent++;
            }
        }
        flash('ok', (t('Išsaugota:') . ' ') . cancellation_dates($c) . ($sent ? (' ' . t('· pranešta') . ' ') . $sent . ($sent === 1 ? ' paskyrai' : ' paskyroms') : ''));
        redirect('admin/atsaukimai.php');
    }
}

try {
    $list = cancellations_with_groups(q_all('SELECT * FROM training_cancellations WHERE ends_on >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) ORDER BY starts_on DESC, id DESC'));
} catch (PDOException $ex) {
    $list = null;
}
$groupNames = array_column($groups, 'name', 'id');
$byCat = [];
foreach ($groups as $g) {
    $byCat[$g['category']][] = $g;
}
// Iš „Šiandien“: ?g=grupė&d=data - forma jau užpildyta
$preGroup = (int) get('g');
$preDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('d')) ? get('d') : '';
$selected = is_post() ? array_map('intval', (array) ($_POST['groups'] ?? [])) : ($preGroup ? [$preGroup] : $groupIds);

page_start(t('Atšauktos treniruotės'), ['admin' => true]);
?>
<a class="small" href="<?= url('admin/nustatymai.php') ?>"><?= te('← Nustatymai') ?></a>
<h1 class="styled" style="margin-top:8px;"><?= te('Atšauktos treniruotės ir atostogos') ?></h1>
<p class="muted"><?= te('Pažymėtomis dienomis treniruotė rodoma kaip atšaukta: „Šiandien“, „Treniruotė“, nario tvarkaraštyje ir pagrindiniame puslapyje (3 sav. prieš).') ?></p>
<?= form_errors($errors) ?>

<?php if ($list === null): ?>
  <div class="flash flash-err"><?= te('Duomenų bazė neatnaujinta - importuokite') ?> <code><?= e(DB_REQUIRED) ?></code>.</div>
<?php else: ?>
<div class="grid-2 cancel-grid">
  <?php if ($canEdit): ?>
  <form method="post" class="panel card form" id="naujas">
    <?= csrf_field() ?>
    <div class="kicker"><?= te('Atšaukti treniruotes') ?></div>
    <div class="form-row">
      <label><?= te('Nuo') ?> <input type="date" name="starts_on" value="<?= e(post('starts_on') ?: ($preDate ?: date('Y-m-d'))) ?>" required></label>
      <label><?= te('Iki') ?> <span class="hint"><?= te('vienai dienai - tuščia') ?></span><input type="date" name="ends_on" value="<?= e(post('ends_on')) ?>"></label>
    </div>
    <label><?= te('Priežastis') ?> <span class="hint">matys nariai</span>
      <input type="text" name="reason" maxlength="190" value="<?= e(post('reason')) ?>" placeholder="<?= te('Pvz. Mokyklinės atostogos, Visų šventų diena') ?>" required></label>
    <fieldset class="cancel-groups">
      <legend><?= te('Grupės') ?></legend>
      <label class="check"><input type="checkbox" name="all" value="1" id="cgAll" <?= count(array_intersect($selected, $groupIds)) === count($groupIds) ? 'checked' : '' ?>><span><strong><?= te('Visos grupės') ?></strong></span></label>
      <?php foreach (array_map('t', GROUP_CATEGORIES) as $cat => $catLabel): if (empty($byCat[$cat])) { continue; } ?>
        <div class="kicker" style="margin-top:10px;"><?= e($catLabel) ?></div>
        <div class="cg-list">
          <?php foreach ($byCat[$cat] as $g): ?>
            <label class="check"><input type="checkbox" name="groups[]" value="<?= (int) $g['id'] ?>" data-cg <?= in_array((int) $g['id'], $selected, true) ? 'checked' : '' ?>>
              <span><span class="group-dot" style="background:<?= e(group_color($g)) ?>"></span><?= e(group_short_name($g)) ?></span></label>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </fieldset>
    <label class="check"><input type="checkbox" name="notify" value="1" <?= !is_post() || post('notify') === '1' ? 'checked' : '' ?>><span><?= te('Pranešti nariams paskyroje („Svarbu“)') ?></span></label>
    <label class="check"><input type="checkbox" name="email" value="1" <?= post('email') === '1' ? 'checked' : '' ?>><span><?= te('Ir el. paštu') ?></span></label>
    <div><button class="btn btn-primary" type="submit"><?= te('Išsaugoti') ?></button></div>
  </form>
  <?php endif; ?>

  <div class="panel card">
    <div class="kicker"><?= te('Artimiausi ir neseni') ?></div>
    <?php if (!$list): ?>
      <p class="muted"><?= te('Atšauktų treniruočių nėra.') ?></p>
    <?php else: ?>
      <ul class="list cancel-list">
        <?php foreach ($list as $c): $past = $c['ends_on'] < date('Y-m-d'); ?>
          <li class="row between<?= $past ? ' is-past' : '' ?>">
            <span>
              <strong><?= e(cancellation_dates($c)) ?></strong><?= $past ? (' <span class="badge">' . t('praėjo') . '</span>') : '' ?>
              <div class="small"><?= e($c['reason']) ?></div>
              <div class="muted small"><?= $c['groups'] ? e(implode(', ', array_map(function ($gid) use ($groupNames) { return $groupNames[$gid] ?? '?'; }, $c['groups']))) : t('Visos grupės') ?></div>
            </span>
            <?php if ($canEdit): ?>
              <form method="post" class="inline-form" onsubmit="return confirm('Ištrinti? Treniruotės vėl bus rodomos kaip įprastai.')"><?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn btn-ghost btn-sm" name="action" value="delete"><?= te('Ištrinti') ?></button></form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<script>
  // „Visos grupės“ pažymi / nuima visas; nuėmus bent vieną grupę - „Visos“ nuimama
  (function () {
    var all = document.getElementById('cgAll');
    if (!all) return;
    var boxes = document.querySelectorAll('[data-cg]');
    all.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = all.checked; }); });
    boxes.forEach(function (b) { b.addEventListener('change', function () { all.checked = [].every.call(boxes, function (x) { return x.checked; }); }); });
  })();
</script>
<?php
page_end();
