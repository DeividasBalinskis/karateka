<?php
// Antras prisijungimo žingsnis: kodas iš el. pašto (kai paskyroje įjungtas prisijungimas su kodu)
require __DIR__ . '/app/bootstrap.php';

$return = safe_return(get('r') ?: post('r'));
if (current_account()) {
    redirect($return);
}
$pending = $_SESSION['tfa'] ?? null;
$a = $pending && $pending['at'] > time() - 15 * 60
    ? q_one('SELECT * FROM accounts WHERE id = ? AND status IN ("active", "pending_approval")', [(int) $pending['account_id']])
    : null;
if (!$a) {
    unset($_SESSION['tfa']);
    flash('err', 'Prisijunkite iš naujo.');
    redirect('prisijungti.php');
}
$aid = (int) $a['id'];
$error = '';

if (is_post()) {
    csrf_check();
    $action = post('action');
    if ($action === 'cancel') {
        unset($_SESSION['tfa'], $_SESSION['dev_login_code']);
        redirect('prisijungti.php');
    }
    if ($action === 'resend') {
        $err = login_code_send($a, 'login', true);
        flash($err ? 'err' : 'ok', $err ?: 'Naują kodą išsiuntėme į ' . masked_email($a['email']) . '.');
        redirect('kodas.php?r=' . rawurlencode($return));
    }
    $res = login_code_check($aid, 'login', post('code'));
    if ($res === 'ok') {
        if (post('trust') === '1') {
            trust_device($aid);
        }
        login_account($aid);
        redirect($return);
    }
    $error = $res === 'invalid'
        ? 'Neteisingas kodas. Liko bandymų: ' . login_code_attempts_left($aid, 'login') . '.'
        : 'Kodas nebegalioja. Paspauskite „Siųsti naują kodą“.';
}

page_start('Prisijungimo kodas', ['narrow' => true, 'noindex' => true]);
?>
<div class="panel card">
  <h1 class="styled">Įveskite kodą</h1>
  <p class="muted">Išsiuntėme 6 skaitmenų kodą į <strong><?= e(masked_email($a['email'])) ?></strong>. Kodas galioja <?= LOGIN_CODE_MINUTES ?> min.</p>
  <?php if (config('env') === 'local' && !empty($_SESSION['dev_login_code'])): ?>
    <p class="hint">Kompiuteryje laiškai nesiunčiami - kodas: <code class="dev-code"><?= e($_SESSION['dev_login_code']) ?></code></p>
  <?php endif; ?>
  <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="r" value="<?= e($return) ?>">
    <label>Kodas
      <input class="code-input" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="\s*\d{3}\s?\d{3}\s*" maxlength="7" required autofocus>
    </label>
    <label class="check"><input type="checkbox" name="trust" value="1" checked><span>Pasitikėti šiuo įrenginiu <?= TRUSTED_DEVICE_DAYS ?> dienų (kodo neprašysime)</span></label>
    <button class="btn btn-primary btn-block" type="submit">Prisijungti</button>
  </form>
  <div class="row between auth-links" style="margin-top:14px; gap:10px; flex-wrap:wrap;">
    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="r" value="<?= e($return) ?>">
      <button class="linklike small" name="action" value="resend">Siųsti naują kodą</button></form>
    <form method="post" class="inline-form"><?= csrf_field() ?>
      <button class="linklike small" name="action" value="cancel">Atšaukti</button></form>
  </div>
  <p class="small muted" style="margin-top:14px;">Laiško nėra? Patikrinkite „Spam“ aplanką. Pametėte prieigą prie el. pašto - parašykite info@karateka.lt.</p>
</div>
<?php
page_end();
