<?php
// Tėvų kvietimas: vaikas susikuria savo prisijungimą prie JAU ESAMO nario (dublikatas nesukuriamas)
require __DIR__ . '/app/bootstrap.php';

$t = get('t') ?: post('t');
$token = token_find('invite', $t);
$member = $token ? q_one('SELECT * FROM members WHERE id = ?', [$token['member_id']]) : null;
$taken = $member && q_value('SELECT 1 FROM account_members WHERE member_id = ? AND relation = "self"', [$member['id']]);
$emailTaken = $token && q_value('SELECT 1 FROM accounts WHERE email = ?', [$token['email']]);

if (!$token || !$member || $taken || $emailTaken) {
    page_start(t('Kvietimas'), ['narrow' => true, 'noindex' => true]); ?>
    <div class="panel card">
      <h1><?= te('Kvietimas nebegalioja') ?></h1>
      <p class="muted"><?= te('Kvietimas jau panaudotas, pasibaigė arba šis el. paštas jau turi paskyrą. Paprašyk tėvų išsiųsti naują kvietimą.') ?></p>
      <p style="margin-top:18px;"><a class="btn btn-primary" href="<?= url('prisijungti.php') ?>"><?= te('Prisijungti') ?></a></p>
    </div>
<?php
    page_end();
    exit;
}

$errors = [];
if (is_post()) {
    csrf_check();
    $pw = (string) ($_POST['password'] ?? '');
    if (mb_strlen($pw) < MIN_PASSWORD) {
        $errors[] = (t('Slaptažodis turi būti bent') . ' ') . MIN_PASSWORD . (' ' . t('simbolių.'));
    } elseif ($pw !== ($_POST['password2'] ?? '')) {
        $errors[] = t('Slaptažodžiai nesutampa.');
    } else {
        db()->beginTransaction();
        try {
            $id = create_account($token['email'], $pw, $member['first_name'], $member['last_name'], null, 'pending_approval');
            q('UPDATE accounts SET email_verified_at = NOW() WHERE id = ?', [$id]);
            link_member($id, (int) $member['id'], 'self');
            token_use((int) $token['id']);
            db()->commit();
        } catch (Throwable $ex) {
            db()->rollBack();
            throw $ex;
        }
        notify_coach_pending(q_one('SELECT * FROM accounts WHERE id = ?', [$id]));
        login_account($id);
        flash('ok', t('Paskyra sukurta! Kai treneris ją patvirtins, matysi savo tvarkaraštį ir renginius.'));
        redirect('paskyra.php');
    }
}

page_start(t('Kvietimas'), ['narrow' => true, 'noindex' => true]);
?>
<div class="panel card">
  <div class="eyebrow"><?= te('Kvietimas') ?></div>
  <h1 class="styled"><?= te('Sveikas (-a),') ?> <?= e($member['first_name']) ?>!</h1>
  <p class="muted" style="margin-bottom:18px;"><?= te('Susikurk slaptažodį - prisijungsi el. paštu') ?> <strong><?= e($token['email']) ?></strong>.</p>
  <?= form_errors($errors) ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="t" value="<?= e($t) ?>">
    <label><?= te('Slaptažodis') ?> <span class="hint">bent <?= MIN_PASSWORD ?> simboliai</span>
      <input type="password" name="password" autocomplete="new-password" minlength="<?= MIN_PASSWORD ?>" required autofocus></label>
    <label><?= te('Pakartok slaptažodį') ?>
      <input type="password" name="password2" autocomplete="new-password" required></label>
    <button class="btn btn-primary btn-block" type="submit"><?= te('Sukurti paskyrą') ?></button>
  </form>
</div>
<?php
page_end();
