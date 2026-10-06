<?php
require __DIR__ . '/app/bootstrap.php';

$return = safe_return(get('r') ?: post('r'));
if (current_account()) {
    redirect($return);
}

$error = '';
$resendFor = null;
$email = '';

if (is_post()) {
    csrf_check();
    $email = post('email');

    if (post('action') === 'resend') {
        // Pakartotinis patvirtinimo laiškas (atsakymas visada vienodas)
        $a = q_one('SELECT * FROM accounts WHERE email = ? AND status = "pending_email"', [normalize_email($email)]);
        if ($a) {
            send_verification_email($a);
        }
        flash('ok', 'Jei paskyra laukia patvirtinimo, naują nuorodą išsiuntėme el. paštu.');
        redirect('prisijungti.php');
    }

    $result = attempt_login($email, (string) ($_POST['password'] ?? ''));
    if ($result['ok']) {
        redirect($return);
    }
    $messages = [
        'throttled'      => 'Per daug nesėkmingų bandymų. Pabandykite po 15 minučių arba atkurkite slaptažodį.',
        'invalid'        => 'Neteisingas el. paštas arba slaptažodis.',
        'pending_email'  => 'Pirmiausia patvirtinkite el. paštą: paspauskite nuorodą laiške, kurį išsiuntėme registruojantis.',
        'pending_parent' => 'Paskyra laukia tėvų sutikimo. Tėvams išsiuntėme laišką su nuoroda.',
        'disabled'       => 'Ši paskyra išjungta. Susisiekite su klubu: info@karateka.lt',
    ];
    $error = $messages[$result['error']];
    if ($result['error'] === 'pending_email') {
        $resendFor = $email;
    }
}

page_start('Prisijungti', ['narrow' => true, 'noindex' => true]);
?>
<div class="panel card">
  <h1 class="styled">Prisijungti</h1>
  <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
  <?php if ($resendFor): ?>
    <form method="post" class="form" style="margin-bottom:18px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="resend">
      <input type="hidden" name="email" value="<?= e($resendFor) ?>">
      <button class="btn btn-ghost btn-sm" type="submit">Siųsti patvirtinimo laišką dar kartą</button>
    </form>
  <?php endif; ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="r" value="<?= e($return) ?>">
    <label>El. paštas
      <input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required autofocus>
    </label>
    <label>Slaptažodis
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button class="btn btn-primary btn-block" type="submit">Prisijungti</button>
  </form>
  <hr class="divider">
  <div class="row between small">
    <a href="<?= url('slaptazodis.php') ?>">Pamiršote slaptažodį?</a>
    <a href="<?= url('registracija.php') ?>">Neturite paskyros? Registruokitės</a>
  </div>
</div>
<?php
page_end();
