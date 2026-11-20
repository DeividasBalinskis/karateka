<?php
require __DIR__ . '/app/bootstrap.php';

$return = safe_return(get('r') ?: post('r'));
// Sprendimas 2026-11-20: treneris ar administratorius be nurodyto puslapio - iškart į Valdymas → Šiandien
$askedFor = (string) (get('r') ?: post('r')) !== '';
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
        flash('ok', t('Jei paskyra laukia patvirtinimo, naują nuorodą išsiuntėme el. paštu.'));
        redirect('prisijungti.php');
    }

    $result = attempt_login($email, (string) ($_POST['password'] ?? ''));
    if ($result['ok']) {
        $me = q_one('SELECT * FROM accounts WHERE id = ?', [(int) ($_SESSION['account_id'] ?? 0)]);   // current_account() šioje užklausoje jau atsimena „neprisijungęs“
        redirect(!$askedFor && $me && is_staff($me) ? url('admin/') : $return);
    }
    if ($result['error'] === 'code') {
        if ($result['mail_error']) {
            flash('err', $result['mail_error']);
        }
        redirect('kodas.php' . ($askedFor ? '?r=' . rawurlencode($return) : ''));
    }
    $messages = [
        'throttled'      => t('Per daug nesėkmingų bandymų. Pabandykite po 15 minučių arba atkurkite slaptažodį.'),
        'invalid'        => t('Neteisingas el. paštas arba slaptažodis.'),
        'pending_email'  => t('Pirmiausia patvirtinkite el. paštą: paspauskite nuorodą laiške, kurį išsiuntėme registruojantis.'),
        'pending_parent' => t('Paskyra laukia tėvų sutikimo. Tėvams išsiuntėme laišką su nuoroda.'),
        'disabled'       => t('Ši paskyra išjungta. Susisiekite su klubu: info@karateka.lt'),
    ];
    $error = $messages[$result['error']];
    if ($result['error'] === 'disabled' && is_kid_login($result['account'] ?? null)) {
        $error = t('Tavo prisijungimas išjungtas - paprašyk tėvų.');
    }
    if ($result['error'] === 'pending_email') {
        $resendFor = $email;
    }
}

page_start(t('Prisijungti'), ['narrow' => true, 'noindex' => true]);
?>
<div class="panel card">
  <h1 class="styled"><?= te('Prisijungti') ?></h1>
  <p class="login-intro"><?= te('Mūsų klubo platformoje galėsite matyti jums aktualius renginius, registracijas, sąskaitas, dokumentus. Taip pat jūsų lauks jau paruošta pasirengimo egzaminams ir varžyboms video medžiaga.') ?></p>
  <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
  <?php if ($resendFor): ?>
    <form method="post" class="form" style="margin-bottom:18px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="resend">
      <input type="hidden" name="email" value="<?= e($resendFor) ?>">
      <button class="btn btn-ghost btn-sm" type="submit"><?= te('Siųsti patvirtinimo laišką dar kartą') ?></button>
    </form>
  <?php endif; ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <?php if ($askedFor): ?><input type="hidden" name="r" value="<?= e($return) ?>"><?php endif; ?>
    <label><?= te('El. paštas') ?> <span class="hint"><?= te('vaikas - savo vardas') ?></span>
      <input type="text" inputmode="email" name="email" value="<?= e($email) ?>" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
    </label>
    <label><?= te('Slaptažodis') ?> <span class="hint"><?= te('vaikas - PIN') ?></span>
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button class="btn btn-primary btn-block" type="submit"><?= te('Prisijungti') ?></button>
  </form>
  <p class="small login-more"><a href="<?= url('slaptazodis.php') ?>"><?= te('Pamiršote slaptažodį?') ?></a>
    <a href="<?= url('irenginys.php' . ($askedFor ? '?r=' . rawurlencode($return) : '')) ?>"><?= te('Turiu kodą iš administratoriaus') ?></a></p>
  <?php if (reg_on('join')): ?>
  <hr class="divider">
  <p class="muted small" style="text-align:center; margin-bottom:10px;"><?= te('Neturite paskyros?') ?></p>
  <a class="btn btn-ghost btn-block" href="<?= url('registracija.php') ?>"><?= te('Sukurti paskyrą') ?></a>
  <?php endif; ?>
</div>
<?php
page_end();
