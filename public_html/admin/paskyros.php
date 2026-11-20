<?php
// Paskyros ir rolės (tik administratoriui): kas yra treneris, išjungti/įjungti paskyrą
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_admin();

// Nauja paskyra (pvz. kitam adminui): iškart aktyvi, slaptažodį įrašo adminas; „be pokalbių lango“ - db/035
if (is_post() && post('action') === 'create') {
    csrf_check();
    $email = normalize_email(post('email'));
    $role = in_array(post('role'), ['member', 'coach', 'admin'], true) ? post('role') : 'member';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || trim(post('first_name')) === '' || mb_strlen((string) post('password')) < MIN_PASSWORD) {
        flash('err', t('Įrašykite el. paštą, vardą ir bent {n} simbolių slaptažodį.', ['n' => MIN_PASSWORD]));
    } elseif ((string) post('password') !== (string) post('password2')) {
        flash('err', t('Slaptažodžiai nesutampa.'));
    } elseif (q_value('SELECT 1 FROM accounts WHERE email = ?', [$email])) {
        flash('err', t('Tokia paskyra jau yra:') . ' ' . $email);
    } else {
        $id = create_account($email, (string) post('password'), mb_substr(trim(post('first_name')), 0, 80), mb_substr(trim(post('last_name')), 0, 80), null, 'active');
        q('UPDATE accounts SET role = ?, email_verified_at = NOW(), approved_at = NOW() WHERE id = ?', [$role, $id]);
        if (!empty($_POST['chat_off'])) {
            try {
                q('UPDATE accounts SET chat_off = 1 WHERE id = ?', [$id]);
            } catch (PDOException $ex) {
                // db/035 dar neimportuotas
            }
        }
        flash('ok', t('Paskyra sukurta:') . ' ' . $email);
    }
    redirect('admin/paskyros.php');
}

if (is_post()) {
    csrf_check();
    $id = (int) post('id');
    $acc = q_one('SELECT * FROM accounts WHERE id = ?', [$id]);
    if ($acc && $id !== (int) $me['id']) {   // savo rolės nekeičiame, kad neliktume be administratoriaus
        // Bent vienas tikras administratorius (ne testuotojas) turi likti visada
        $lastReal = is_admin($acc) && real_admins_left($id) === 0;
        if (post('action') === 'role' && in_array(post('role'), ['member', 'coach', 'admin'], true)) {
            if ($lastReal && post('role') !== 'admin') {
                flash('err', t('Tai paskutinis administratorius - rolės pakeisti negalima.'));
            } else {
                q('UPDATE accounts SET role = ? WHERE id = ?', [post('role'), $id]);
                flash('ok', t('Rolė pakeista:') . " {$acc['first_name']} {$acc['last_name']}");
            }
        }
        if (post('action') === 'tester' && tester_ready()) {
            $on = post('on') === '1';
            if ($on && $lastReal) {
                flash('err', t('Tai paskutinis administratorius - testuotoju padaryti negalima.'));
            } else {
                q('UPDATE accounts SET is_tester = ? WHERE id = ?', [$on ? 1 : 0, $id]);
                flash('ok', ($on ? t('Testuotojas:') : t('Nebe testuotojas:')) . " {$acc['first_name']} {$acc['last_name']}");
            }
        }
        if (post('action') === 'chat') {
            try {
                q('UPDATE accounts SET chat_off = ? WHERE id = ?', [post('chat_off') === '1' ? 1 : 0, $id]);
            } catch (PDOException $ex) {
            }
        }
        if (post('action') === 'disable') {
            if ($lastReal) {
                flash('err', t('Tai paskutinis administratorius - išjungti negalima.'));
            } else {
                q('UPDATE accounts SET status = "disabled" WHERE id = ?', [$id]);
                flash('ok', "Paskyra išjungta: {$acc['email']}");
            }
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
    'pending_email' => t('laukia el. pašto'), 'pending_parent' => t('laukia tėvų'), 'pending_approval' => 'laukia patvirtinimo',
    'active' => 'aktyvi', 'disabled' => t('išjungta'),
];
$roleLabel = ['member' => t('Narys / tėvai'), 'coach' => t('Treneris'), 'admin' => t('Administratorius')];

page_start(t('Paskyros'), ['admin' => true]);
?>
<div class="page-head">
  <h1 class="styled"><?= te('Paskyros') ?></h1>
  <p><?= te('Treneriai mato trenerio panelę. Administratorius dar gali keisti roles.') ?>
    <?php if (tester_ready()): ?><?= te('🧪 Testuotojas - turi administratoriaus rolę, bet gali tiek, kiek treneris: nustatymus mato tik skaityti, Dojo ir žaidimo nemato.') ?><?php endif; ?></p>
</div>
<details class="panel card form new-account">
  <summary><strong><?= te('+ Nauja paskyra') ?></strong> <span class="muted small"><?= te('pvz. kitam adminui ar treneriui - iškart aktyvi') ?></span></summary>
  <form method="post" class="inline-fields" style="margin-top:12px;" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <label><?= te('El. paštas') ?> <input type="email" name="email" required autocomplete="off"></label>
    <label><?= te('Vardas') ?> <input name="first_name" required maxlength="80"></label>
    <label><?= te('Pavardė') ?> <input name="last_name" maxlength="80"></label>
    <label><?= te('Slaptažodis') ?> <input type="password" name="password" required minlength="<?= MIN_PASSWORD ?>" autocomplete="new-password"></label>
    <label><?= te('Pakartokite slaptažodį') ?> <input type="password" name="password2" required autocomplete="new-password"></label>
    <label><?= te('Rolė') ?> <select name="role"><?php foreach ($roleLabel as $k => $label): ?><option value="<?= $k ?>"<?= $k === 'member' ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
    <button class="btn btn-primary" type="submit"><?= te('Sukurti') ?></button>
  </form>
</details>
<form method="get" class="panel card form inline-fields">
  <label><?= te('Paieška') ?> <input type="search" name="q" value="<?= e($search) ?>" placeholder="<?= te('Vardas arba el. paštas') ?>"></label>
  <button class="btn btn-ghost" type="submit"><?= te('Ieškoti') ?></button>
</form>
<div class="panel card">
  <table class="table cards">
    <thead><tr><th><?= te('Vardas') ?></th><th><?= te('Būsena') ?></th><th><?= te('Rolė') ?></th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): $self = (int) $r['id'] === (int) $me['id']; ?>
        <tr>
          <td><strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong><div class="muted small"><?= str_ends_with((string) $r['email'], '@' . KID_LOGIN_DOMAIN) ? te('vaiko vardas') . ' ' . e(login_name((string) $r['email'])) . ' (PIN)' : e($r['email']) ?></div>
            </td>
          <td><span class="badge <?= $r['status'] === 'active' ? 'badge-ok' : 'badge-warn' ?>"><?= $statusLabel[$r['status']] ?></span></td>
          <td>
            <?php if ($self): ?>
              <?= $roleLabel[$r['role']] ?><?= tester_badge($r) ?> <span class="muted small"><?= te('(jūs)') ?></span>
            <?php else: ?>
              <form method="post" class="inline-form form">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="role">
                <select name="role" data-cur="<?= e($r['role']) ?>" data-ask="<?= e(t('Pakeisti rolę: {name} → ', ['name' => trim($r['first_name'] . ' ' . $r['last_name']) ?: $r['email']])) ?>" onchange="if (confirm(this.dataset.ask + this.options[this.selectedIndex].text + '?')) this.form.submit(); else this.value = this.dataset.cur;" style="padding:8px 36px 8px 10px; font-size:14px;">
                  <?php foreach ($roleLabel as $k => $label): ?><option value="<?= $k ?>" <?= $r['role'] === $k ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
                </select>
              </form>
              <?php if (tester_ready()): ?>
                <form method="post" class="tester-tg"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="tester"><input type="hidden" name="on" value="<?= !empty($r['is_tester']) ? '0' : '1' ?>">
                  <button type="submit" class="btn btn-sm <?= !empty($r['is_tester']) ? 'on' : 'btn-ghost' ?>" aria-pressed="<?= !empty($r['is_tester']) ? 'true' : 'false' ?>" title="<?= te('Testuotojas: gali tiek, kiek treneris, nustatymus mato tik skaityti, Dojo nemato') ?>">🧪 <?= !empty($r['is_tester']) ? te('Testuotojas ✓') : te('Testuotojas') ?></button></form>
              <?php endif; ?>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!$self): ?>
              <form method="post" class="inline-form">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <?php if ($r['status'] === 'disabled'): ?>
                  <button class="btn btn-ghost btn-sm" name="action" value="enable"><?= te('Įjungti') ?></button>
                <?php else: ?>
                  <button class="btn btn-danger btn-sm" name="action" value="disable" onclick="return confirm('Išjungti paskyrą? Žmogus nebegalės prisijungti.')"><?= te('Išjungti') ?></button>
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
