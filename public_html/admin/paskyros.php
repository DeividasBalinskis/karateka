<?php
// Paskyros ir rolės (tik administratoriui): kas yra treneris, išjungti/įjungti paskyrą
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_admin();

if (is_post()) {
    csrf_check();
    $id = (int) post('id');
    $acc = q_one('SELECT * FROM accounts WHERE id = ?', [$id]);
    if ($acc && $id !== (int) $me['id']) {   // savo rolės nekeičiame, kad neliktume be administratoriaus
        if (post('action') === 'role' && in_array(post('role'), ['member', 'coach', 'admin'], true)) {
            q('UPDATE accounts SET role = ? WHERE id = ?', [post('role'), $id]);
            flash('ok', "Rolė pakeista: {$acc['first_name']} {$acc['last_name']}");
        }
        if (post('action') === 'disable') {
            q('UPDATE accounts SET status = "disabled" WHERE id = ?', [$id]);
            flash('ok', "Paskyra išjungta: {$acc['email']}");
        }
        if (post('action') === 'enable' && $acc['status'] === 'disabled') {
            q('UPDATE accounts SET status = "active" WHERE id = ?', [$id]);
            flash('ok', "Paskyra įjungta: {$acc['email']}");
        }
    }
    redirect('admin/paskyros.php' . (get('q') !== '' ? '?q=' . rawurlencode(get('q')) : ''));
}

$search = get('q');
$params = [];
$sql = 'SELECT * FROM accounts';
if ($search !== '') {
    $sql .= ' WHERE email LIKE ? OR CONCAT(first_name, " ", last_name) LIKE ?';
    $params = ["%$search%", "%$search%"];
}
$rows = q_all($sql . ' ORDER BY role = "member", last_name, first_name LIMIT 300', $params);

$statusLabel = [
    'pending_email' => 'laukia el. pašto', 'pending_parent' => 'laukia tėvų', 'pending_approval' => 'laukia patvirtinimo',
    'active' => 'aktyvi', 'disabled' => 'išjungta',
];
$roleLabel = ['member' => 'Narys / tėvai', 'coach' => 'Treneris', 'admin' => 'Administratorius'];

page_start('Paskyros', ['admin' => true]);
?>
<div class="page-head">
  <h1 class="styled">Paskyros</h1>
  <p>Treneriai mato trenerio panelę. Administratorius dar gali keisti roles.</p>
</div>
<form method="get" class="panel card form inline-fields">
  <label>Paieška <input type="search" name="q" value="<?= e($search) ?>" placeholder="Vardas arba el. paštas"></label>
  <button class="btn btn-ghost" type="submit">Ieškoti</button>
</form>
<div class="panel card">
  <table class="table cards">
    <thead><tr><th>Vardas</th><th>Būsena</th><th>Rolė</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): $self = (int) $r['id'] === (int) $me['id']; ?>
        <tr>
          <td><strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong><div class="muted small"><?= e($r['email']) ?></div></td>
          <td><span class="badge <?= $r['status'] === 'active' ? 'badge-ok' : 'badge-warn' ?>"><?= $statusLabel[$r['status']] ?></span></td>
          <td>
            <?php if ($self): ?>
              <?= $roleLabel[$r['role']] ?> <span class="muted small">(jūs)</span>
            <?php else: ?>
              <form method="post" class="inline-form form">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="role">
                <select name="role" onchange="this.form.submit()" style="padding:8px 36px 8px 10px; font-size:14px;">
                  <?php foreach ($roleLabel as $k => $label): ?><option value="<?= $k ?>" <?= $r['role'] === $k ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
                </select>
              </form>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!$self): ?>
              <form method="post" class="inline-form">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <?php if ($r['status'] === 'disabled'): ?>
                  <button class="btn btn-ghost btn-sm" name="action" value="enable">Įjungti</button>
                <?php else: ?>
                  <button class="btn btn-danger btn-sm" name="action" value="disable" onclick="return confirm('Išjungti paskyrą? Žmogus nebegalės prisijungti.')">Išjungti</button>
                <?php endif; ?>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
page_end();
