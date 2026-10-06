<?php
// Naujų paskyrų ir naujai pridėtų vaikų patvirtinimas su grupės priskyrimu
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

function send_approved_mail(array $account): void
{
    send_mail($account['email'], 'Paskyra patvirtinta — Karateka',
        "Sveiki, {$account['first_name']},\n\njūsų paskyra karateka.lt patvirtinta! Prisijungę matysite grupės tvarkaraštį ir artėjančius renginius:\n\n"
        . abs_url('prisijungti.php'));
}

if (is_post()) {
    csrf_check();
    $action = post('action');
    $groups = (array) ($_POST['group'] ?? []);

    // Priskiria grupes ir aktyvuoja nurodytus narius
    $activate = function (array $memberIds) use ($groups): bool {
        foreach ($memberIds as $mid) {
            if (empty($groups[$mid])) {
                return false;
            }
        }
        foreach ($memberIds as $mid) {
            q('UPDATE members SET group_id = ?, status = "active" WHERE id = ?', [(int) $groups[$mid], (int) $mid]);
        }
        return true;
    };

    if ($action === 'approve_account' || $action === 'reject_account') {
        $acc = q_one('SELECT * FROM accounts WHERE id = ? AND status = "pending_approval"', [(int) post('account_id')]);
        if ($acc) {
            $pendingIds = q('SELECT m.id FROM members m JOIN account_members am ON am.member_id = m.id WHERE am.account_id = ? AND m.status = "pending"', [$acc['id']])
                ->fetchAll(PDO::FETCH_COLUMN);
            if ($action === 'approve_account') {
                if (!$activate($pendingIds)) {
                    flash('err', 'Priskirkite grupę kiekvienam nariui.');
                    redirect('admin/patvirtinimai.php');
                }
                q('UPDATE accounts SET status = "active", approved_at = NOW() WHERE id = ?', [$acc['id']]);
                send_approved_mail($acc);
                flash('ok', "Patvirtinta: {$acc['first_name']} {$acc['last_name']}");
            } else {
                q('UPDATE accounts SET status = "disabled" WHERE id = ?', [$acc['id']]);
                foreach ($pendingIds as $mid) {
                    q('UPDATE members SET status = "inactive" WHERE id = ?', [$mid]);
                }
                flash('ok', "Atmesta: {$acc['first_name']} {$acc['last_name']}");
            }
        }
    }

    if ($action === 'approve_member' || $action === 'reject_member') {
        $mid = (int) post('member_id');
        $m = q_one('SELECT * FROM members WHERE id = ? AND status = "pending"', [$mid]);
        if ($m) {
            if ($action === 'approve_member') {
                if (!$activate([$mid])) {
                    flash('err', 'Pasirinkite grupę.');
                    redirect('admin/patvirtinimai.php');
                }
                foreach (q_all('SELECT a.* FROM accounts a JOIN account_members am ON am.account_id = a.id WHERE am.member_id = ? AND a.status = "active"', [$mid]) as $acc) {
                    send_mail($acc['email'], 'Narys patvirtintas — Karateka',
                        "Sveiki,\n\n{$m['first_name']} {$m['last_name']} patvirtintas (-a) ir priskirtas (-a) grupei. Tvarkaraštį matysite paskyroje:\n\n" . abs_url('paskyra.php'));
                }
                flash('ok', "Patvirtinta: {$m['first_name']} {$m['last_name']}");
            } else {
                q('UPDATE members SET status = "inactive" WHERE id = ?', [$mid]);
                flash('ok', "Atmesta: {$m['first_name']} {$m['last_name']}");
            }
        }
    }
    redirect('admin/patvirtinimai.php');
}

$accounts = q_all('SELECT * FROM accounts WHERE status = "pending_approval" ORDER BY created_at');
$loose = q_all('SELECT m.*, a.first_name AS parent_first, a.last_name AS parent_last, a.email AS parent_email
                  FROM members m
                  JOIN account_members am ON am.member_id = m.id AND am.relation = "parent"
                  JOIN accounts a ON a.id = am.account_id AND a.status = "active"
                 WHERE m.status = "pending"
                 ORDER BY m.created_at');
$loose = array_values(array_column($loose, null, 'id'));   // vienas įrašas, net jei abu tėvai turi paskyras
$waiting = q_all('SELECT * FROM accounts WHERE status IN ("pending_email", "pending_parent") ORDER BY created_at DESC LIMIT 30');

$relLabel = ['self' => 'pats', 'parent' => 'vaikas'];

page_start('Patvirtinimai', ['admin' => true]);
?>
<div class="page-head">
  <h1 class="styled">Patvirtinimai</h1>
  <p>Patvirtinkite naujas paskyras ir priskirkite grupes.</p>
</div>

<?php if (!$accounts && !$loose): ?>
  <div class="panel card"><p class="muted">Viskas patvirtinta - laukiančių nėra. 🎉</p></div>
<?php endif; ?>

<?php foreach ($accounts as $acc): $ms = account_members((int) $acc['id']); ?>
  <form method="post" class="panel approval form">
    <?= csrf_field() ?>
    <input type="hidden" name="account_id" value="<?= (int) $acc['id'] ?>">
    <div class="row between">
      <div>
        <strong><?= e($acc['first_name'] . ' ' . $acc['last_name']) ?></strong>
        <div class="muted small"><?= e($acc['email']) ?><?= $acc['phone'] ? ' · ' . e($acc['phone']) : '' ?></div>
      </div>
      <span class="badge"><?= e(fmt_date($acc['created_at'])) ?></span>
    </div>
    <?php if (!$ms): ?>
      <p class="small muted">Tėvų paskyra, vaikų dar nepridėta.</p>
    <?php endif; ?>
    <?php foreach ($ms as $m): ?>
      <div style="border-top:1px solid rgba(23,20,15,0.08); padding-top:12px;">
        <div class="small">
          <strong><?= e($m['first_name'] . ' ' . $m['last_name']) ?></strong>
          <span class="badge"><?= $relLabel[$m['relation']] ?></span>
          <span class="muted"><?= age_on($m['birth_date']) ?> m. · gim. <?= e($m['birth_date']) ?> · nuotraukos: <?= $m['photo_consent'] ? 'taip' : 'ne' ?></span>
          <?php if ($m['parent_consent_at'] && $m['relation'] === 'self'): ?><span class="badge badge-ok">tėvų sutikimas gautas</span><?php endif; ?>
        </div>
        <?php if ($m['status'] === 'pending'): ?>
          <label style="margin-top:8px;">Grupė
            <select name="group[<?= (int) $m['id'] ?>]" required><?= group_options($m['group_id'] ? (int) $m['group_id'] : null) ?></select>
          </label>
        <?php else: ?>
          <div class="small muted">Jau grupėje: <?= e($m['group_name'] ?: '—') ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <div class="row">
      <button class="btn btn-primary" name="action" value="approve_account">Patvirtinti</button>
      <button class="btn btn-danger" name="action" value="reject_account" formnovalidate onclick="return confirm('Atmesti šią registraciją?')">Atmesti</button>
    </div>
  </form>
<?php endforeach; ?>

<?php if ($loose): ?>
  <h2 style="margin-top:28px;">Naujai pridėti vaikai</h2>
  <?php foreach ($loose as $m): ?>
    <form method="post" class="panel approval form">
      <?= csrf_field() ?>
      <input type="hidden" name="member_id" value="<?= (int) $m['id'] ?>">
      <div>
        <strong><?= e($m['first_name'] . ' ' . $m['last_name']) ?></strong>
        <div class="muted small"><?= age_on($m['birth_date']) ?> m. · tėvai: <?= e($m['parent_first'] . ' ' . $m['parent_last']) ?> (<?= e($m['parent_email']) ?>) · nuotraukos: <?= $m['photo_consent'] ? 'taip' : 'ne' ?></div>
      </div>
      <label>Grupė <select name="group[<?= (int) $m['id'] ?>]" required><?= group_options(null) ?></select></label>
      <div class="row">
        <button class="btn btn-primary" name="action" value="approve_member">Patvirtinti</button>
        <button class="btn btn-danger" name="action" value="reject_member" formnovalidate onclick="return confirm('Atmesti?')">Atmesti</button>
      </div>
    </form>
  <?php endforeach; ?>
<?php endif; ?>

<?php if ($waiting): ?>
  <div class="panel card" style="margin-top:28px;">
    <h2>Dar nebaigė registracijos</h2>
    <p class="muted small" style="margin-bottom:10px;">Šie žmonės dar nepatvirtino el. pašto arba laukia tėvų sutikimo. Nieko daryti nereikia.</p>
    <ul class="list small">
      <?php foreach ($waiting as $w): ?>
        <li class="row between">
          <span><?= e($w['first_name'] . ' ' . $w['last_name']) ?> <span class="muted">· <?= e($w['email']) ?></span></span>
          <span class="badge badge-warn"><?= $w['status'] === 'pending_email' ? 'laukia el. pašto' : 'laukia tėvų' ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
<?php
page_end();
