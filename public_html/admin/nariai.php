<?php
// Narių sąrašas ir redagavimas
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

$id = (int) (get('id') ?: post('id'));

/** Paskyra pagal el. paštą susiejimui su nariu (null - tokios nėra) */
function account_by_email(string $email): ?array
{
    $acc = q_one('SELECT * FROM accounts WHERE email = ?', [normalize_email($email)]);
    return $acc ?: null;
}

// ---------- Naujas narys (treneris prideda pats, iškart aktyvus su grupe) ----------
if (get('new') !== '' || post('action') === 'create') {
    $errors = [];
    if (is_post()) {
        csrf_check();
        $first = post('first_name');
        $last = post('last_name');
        $birth = date_from_input($_POST['birth_date'] ?? '');
        $group = (int) post('group_id');
        $email = post('link_email');
        $acc = $email !== '' ? account_by_email($email) : null;
        if ($first === '' || $last === '' || !valid_birth_date($birth)) {
            $errors[] = 'Įveskite vardą, pavardę ir gimimo datą.';
        } elseif (!$group) {
            $errors[] = 'Pasirinkite grupę.';
        } elseif ($email !== '' && !$acc) {
            $errors[] = 'Paskyros su tokiu el. paštu nėra. Palikite tuščią - susieti galėsite vėliau nario puslapyje.';
        } else {
            $mid = create_member($first, $last, $birth, !empty($_POST['photo_consent']), belt_from_post('belt_level'));
            q('UPDATE members SET group_id = ?, status = "active" WHERE id = ?', [$group, $mid]);
            if ($acc) {
                link_member((int) $acc['id'], $mid, post('relation') === 'self' ? 'self' : 'parent');
            }
            flash('ok', "Pridėtas (-a): $first $last");
            redirect('admin/nariai.php?id=' . $mid);
        }
    }
    page_start('Naujas narys', ['admin' => true]);
    ?>
    <a class="small" href="<?= url('admin/nariai.php') ?>">← Visi nariai</a>
    <h1 class="styled" style="margin-top:8px;">Naujas narys</h1>
    <?= form_errors($errors) ?>
    <form method="post" class="panel card form" style="max-width:720px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="form-row">
        <label>Vardas <input type="text" name="first_name" value="<?= e(post('first_name')) ?>" required></label>
        <label>Pavardė <input type="text" name="last_name" value="<?= e(post('last_name')) ?>" required></label>
      </div>
      <label>Gimimo data <?= date_parts_field('birth_date', $_POST['birth_date'] ?? null) ?></label>
      <div class="form-row">
        <label>Grupė <select name="group_id" required><?= group_options((int) post('group_id') ?: null) ?></select></label>
        <label>Diržas <select name="belt_level"><?= belt_options(belt_from_post('belt_level'), '— nežinau / dar neturi —') ?></select></label>
      </div>
      <label class="check"><input type="checkbox" name="photo_consent" value="1" <?= !empty($_POST['photo_consent']) ? 'checked' : '' ?>>
        <span>Tėvai (ar pats narys) sutiko, kad klubas skelbtų nuotraukas</span></label>
      <hr class="divider">
      <div class="form-row">
        <label>Susieti su paskyra <span class="hint">nebūtina - tėvų ar paties nario el. paštas, jei jau užsiregistravę</span>
          <input type="email" name="link_email" value="<?= e(post('link_email')) ?>"></label>
        <label>Kas tai <select name="relation"><option value="parent">tėvų paskyra</option><option value="self" <?= post('relation') === 'self' ? 'selected' : '' ?>>paties nario paskyra</option></select></label>
      </div>
      <p class="hint">Jei tėvai dar neturi paskyros - palikite tuščią. Kai užsiregistruos, susiekite nario puslapyje, kad tas pats vaikas nebūtų sukurtas du kartus.</p>
      <div><button class="btn btn-primary" type="submit">Pridėti narį</button></div>
    </form>
    <?php
    page_end();
    exit;
}

if ($id) {
    $m = q_one('SELECT * FROM members WHERE id = ?', [$id]);
    if (!$m) {
        not_found();
    }
    $errors = [];
    // Susieti esamą narį su tėvų / paties nario paskyra
    if (is_post() && post('action') === 'link_account') {
        csrf_check();
        $acc = account_by_email(post('link_email'));
        if ($acc) {
            link_member((int) $acc['id'], $id, post('relation') === 'self' ? 'self' : 'parent');
            flash('ok', "Susieta su paskyra: {$acc['email']}");
        } else {
            flash('err', 'Paskyros su tokiu el. paštu nėra.');
        }
        redirect('admin/nariai.php?id=' . $id);
    }
    if (is_post() && in_array(post('action'), ['add_award', 'delete_award'], true)) {
        csrf_check();
        if (post('action') === 'add_award') {
            $cat = q_one('SELECT * FROM point_categories WHERE id = ? AND is_active = 1', [(int) post('category_id')]);
            $date = post('awarded_on');
            if ($cat && DateTime::createFromFormat('!Y-m-d', $date)) {
                award_points($id, $cat, $date, null, post('note'), (int) $me['id']);
                flash('ok', "Skirta +{$cat['points']} tšk.: {$cat['name']}");
            } else {
                flash('err', 'Pasirinkite kategoriją ir datą.');
            }
        } else {
            q('DELETE FROM point_awards WHERE id = ? AND member_id = ?', [(int) post('award_id'), $id]);
            flash('ok', 'Taškai pašalinti.');
        }
        redirect('admin/nariai.php?id=' . $id . '#taskai');
    }
    if (is_post()) {
        csrf_check();
        $first = post('first_name');
        $last = post('last_name');
        $birth = date_from_input($_POST['birth_date'] ?? '');
        $status = post('status');
        $group = (int) post('group_id') ?: null;
        if ($first === '' || $last === '' || !valid_birth_date($birth)) {
            $errors[] = 'Įveskite vardą, pavardę ir gimimo datą.';
        } elseif (!in_array($status, ['pending', 'active', 'inactive'], true)) {
            $errors[] = 'Netinkama būsena.';
        } elseif ($status === 'active' && !$group) {
            $errors[] = 'Aktyviam nariui reikia grupės.';
        } else {
            q('UPDATE members SET first_name = ?, last_name = ?, birth_date = ?, group_id = ?, status = ?, photo_consent = ?, belt_level = ? WHERE id = ?',
                [$first, $last, $birth, $group, $status, !empty($_POST['photo_consent']) ? 1 : 0, belt_from_post('belt_level'), $id]);
            flash('ok', 'Išsaugota.');
            redirect('admin/nariai.php?id=' . $id);
        }
        $m = array_merge($m, ['first_name' => $first, 'last_name' => $last, 'birth_date' => $birth, 'status' => $status, 'group_id' => $group, 'belt_level' => belt_from_post('belt_level')]);
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
          <?= date_parts_field('birth_date', $m['birth_date']) ?></label>
        <label>Grupė <select name="group_id"><?= group_options($m['group_id'] ? (int) $m['group_id'] : null) ?></select></label>
        <label>Diržas <select name="belt_level"><?= belt_options($m['belt_level'] !== null ? (int) $m['belt_level'] : null) ?></select></label>
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
        <details style="margin-top:12px;">
          <summary class="summary-link">+ Susieti su paskyra</summary>
          <form method="post" class="form" style="margin-top:10px;">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="action" value="link_account">
            <label>Tėvų ar paties nario el. paštas <input type="email" name="link_email" required></label>
            <label>Kas tai <select name="relation"><option value="parent">tėvų paskyra</option><option value="self">paties nario paskyra</option></select></label>
            <div><button class="btn btn-ghost btn-sm" type="submit">Susieti</button></div>
          </form>
        </details>
      </div>
    </div>

    <?php [$from, $to, $seasonLabel] = season_bounds(); $history = member_history($id); ?>
    <div class="grid-2" id="taskai" style="margin-top:20px;">
      <form method="post" class="panel card form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="action" value="add_award">
        <h2>Skirti taškų</h2>
        <p class="hint">Egzaminų ir varžybų taškus patogiau skirti per Renginiai → Rezultatai.</p>
        <label>Už ką
          <select name="category_id" required>
            <option value="">— pasirinkite —</option>
            <?php foreach (q_all('SELECT * FROM point_categories WHERE is_active = 1 ORDER BY sort_order, id') as $c): ?>
              <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?> (+<?= (int) $c['points'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </label>
        <div class="form-row">
          <label>Data <input type="date" name="awarded_on" value="<?= date('Y-m-d') ?>" required></label>
          <label>Pastaba <span class="hint">nebūtina</span><input type="text" name="note" maxlength="190" placeholder="pvz. vedė antradienio treniruotę"></label>
        </div>
        <div><button class="btn btn-primary" type="submit">Skirti</button></div>
      </form>
      <div class="panel card">
        <h2>Lankomumas</h2>
        <?= render_attendance($id) ?>
        <hr class="divider">
        <h2>Taškai</h2>
        <p class="small muted"><?= e($seasonLabel) ?>: <strong><?= member_points_total($id, $from, $to) ?></strong> · iš viso: <strong><?= member_points_total($id) ?></strong></p>
        <?php if (!$history): ?><p class="muted small" style="margin-top:8px;">Taškų dar nėra.</p><?php endif; ?>
        <ul class="list small">
          <?php foreach ($history as $h): ?>
            <li class="row between">
              <span>+<?= (int) $h['points'] ?> · <?= e($h['category_name']) ?>
                <div class="muted"><?= e(fmt_date($h['awarded_on'], true)) ?><?= $h['event_title'] ? ' · ' . e($h['event_title']) : '' ?><?= $h['note'] ? ' · ' . e($h['note']) : '' ?></div>
              </span>
              <form method="post" class="inline-form" onsubmit="return confirm('Pašalinti šiuos taškus?')">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="delete_award"><input type="hidden" name="award_id" value="<?= (int) $h['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit" aria-label="Pašalinti">✕</button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
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
<div class="page-head row between">
  <h1 class="styled">Nariai</h1>
  <a class="btn btn-primary" href="?new=1">+ Naujas narys</a>
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
    <thead><tr><th>Vardas, pavardė</th><th>Amžius</th><th>Grupė</th><th>Diržas</th><th>Nuotraukos</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href="?id=<?= (int) $r['id'] ?>"><strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong></a></td>
          <td><?= age_on($r['birth_date']) ?> m.</td>
          <td><?= e($r['group_name'] ?: '—') ?></td>
          <td><?= belt_chip($r['belt_level'] !== null ? (int) $r['belt_level'] : null) ?></td>
          <td><?= $r['photo_consent'] ? '<span class="badge badge-ok">sutinka</span>' : '<span class="badge">ne</span>' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
page_end();
