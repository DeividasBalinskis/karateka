<?php
// Narių sąrašas ir redagavimas
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

$id = (int) (get('id') ?: post('id'));

if ($id) {
    $m = q_one('SELECT * FROM members WHERE id = ?', [$id]);
    if (!$m) {
        not_found();
    }
    $errors = [];
    if (is_post()) {
        csrf_check();
        $first = post('first_name');
        $last = post('last_name');
        $birth = post('birth_date');
        $status = post('status');
        $group = (int) post('group_id') ?: null;
        if ($first === '' || $last === '' || !valid_birth_date($birth)) {
            $errors[] = 'Įveskite vardą, pavardę ir gimimo datą.';
        } elseif (!in_array($status, ['pending', 'active', 'inactive'], true)) {
            $errors[] = 'Netinkama būsena.';
        } elseif ($status === 'active' && !$group) {
            $errors[] = 'Aktyviam nariui reikia grupės.';
        } else {
            q('UPDATE members SET first_name = ?, last_name = ?, birth_date = ?, group_id = ?, status = ?, photo_consent = ? WHERE id = ?',
                [$first, $last, $birth, $group, $status, !empty($_POST['photo_consent']) ? 1 : 0, $id]);
            flash('ok', 'Išsaugota.');
            redirect('admin/nariai.php?id=' . $id);
        }
        $m = array_merge($m, ['first_name' => $first, 'last_name' => $last, 'birth_date' => $birth, 'status' => $status, 'group_id' => $group]);
    }
    $accounts = q_all('SELECT a.*, am.relation FROM account_members am JOIN accounts a ON a.id = am.account_id WHERE am.member_id = ?', [$id]);

    page_start($m['first_name'] . ' ' . $m['last_name'], ['admin' => true]);
    ?>
    <a class="small" href="<?= url('admin/nariai.php') ?>">← Visi nariai</a>
    <h1 class="styled" style="margin-top:8px;"><?= e($m['first_name'] . ' ' . $m['last_name']) ?></h1>
    <?= form_errors($errors) ?>
    <div class="grid-2">
      <form method="post" class="panel card form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-row">
          <label>Vardas <input type="text" name="first_name" value="<?= e($m['first_name']) ?>" required></label>
          <label>Pavardė <input type="text" name="last_name" value="<?= e($m['last_name']) ?>" required></label>
        </div>
        <label>Gimimo data <span class="hint"><?= age_on($m['birth_date']) ?> m.</span>
          <input type="date" name="birth_date" value="<?= e($m['birth_date']) ?>" required></label>
        <label>Grupė <select name="group_id"><?= group_options($m['group_id'] ? (int) $m['group_id'] : null) ?></select></label>
        <label>Būsena
          <select name="status">
            <?php foreach (['active' => 'Aktyvus', 'pending' => 'Laukia patvirtinimo', 'inactive' => 'Neaktyvus'] as $k => $label): ?>
              <option value="<?= $k ?>" <?= $m['status'] === $k ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="check"><input type="checkbox" name="photo_consent" value="1" <?= $m['photo_consent'] ? 'checked' : '' ?>>
          <span>Sutikimas skelbti nuotraukas</span></label>
        <div><button class="btn btn-primary" type="submit">Išsaugoti</button></div>
      </form>
      <div class="panel card">
        <h2>Paskyros</h2>
        <?php if (!$accounts): ?><p class="muted small">Nesusieta su jokia paskyra.</p><?php endif; ?>
        <ul class="list small">
          <?php foreach ($accounts as $acc): ?>
            <li>
              <strong><?= e($acc['first_name'] . ' ' . $acc['last_name']) ?></strong>
              <span class="badge"><?= $acc['relation'] === 'self' ? 'pats' : 'tėvai' ?></span>
              <div class="muted"><?= e($acc['email']) ?><?= $acc['phone'] ? ' · <a href="tel:' . e($acc['phone']) . '">' . e($acc['phone']) . '</a>' : '' ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if ($m['parent_consent_at']): ?><p class="small muted" style="margin-top:10px;">Tėvų sutikimas: <?= e($m['parent_consent_at']) ?></p><?php endif; ?>
      </div>
    </div>
    <?php
    page_end();
    exit;
}

// ---------- Sąrašas ----------
$groupFilter = (int) get('g');
$search = get('q');
$status = get('s') ?: 'active';

$sql = 'SELECT m.*, g.name AS group_name FROM members m LEFT JOIN training_groups g ON g.id = m.group_id WHERE 1';
$params = [];
if ($status !== 'all') {
    $sql .= ' AND m.status = ?';
    $params[] = $status;
}
if ($groupFilter) {
    $sql .= ' AND m.group_id = ?';
    $params[] = $groupFilter;
}
if ($search !== '') {
    $sql .= ' AND CONCAT(m.first_name, " ", m.last_name) LIKE ?';
    $params[] = '%' . $search . '%';
}
$rows = q_all($sql . ' ORDER BY g.sort_order, m.last_name, m.first_name LIMIT 500', $params);

page_start('Nariai', ['admin' => true]);
?>
<div class="page-head">
  <h1 class="styled">Nariai</h1>
</div>
<form method="get" class="panel card form filters">
  <label>Paieška <input type="search" name="q" value="<?= e($search) ?>" placeholder="Vardas ar pavardė"></label>
  <label>Grupė <select name="g"><option value="">Visos grupės</option><?= str_replace('<option value="">— pasirinkite grupę —</option>', '', group_options($groupFilter ?: null)) ?></select></label>
  <label>Būsena
    <select name="s">
      <?php foreach (['active' => 'Aktyvūs', 'pending' => 'Laukia', 'inactive' => 'Neaktyvūs', 'all' => 'Visi'] as $k => $label): ?>
        <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="btn btn-ghost" type="submit">Rodyti</button>
</form>

<div class="panel card">
  <p class="muted small" style="margin-bottom:8px;">Rasta: <?= count($rows) ?></p>
  <table class="table cards">
    <thead><tr><th>Vardas, pavardė</th><th>Amžius</th><th>Grupė</th><th>Nuotraukos</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href="?id=<?= (int) $r['id'] ?>"><strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong></a></td>
          <td><?= age_on($r['birth_date']) ?> m.</td>
          <td><?= e($r['group_name'] ?: '—') ?></td>
          <td><?= $r['photo_consent'] ? '<span class="badge badge-ok">sutinka</span>' : '<span class="badge">ne</span>' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
page_end();
