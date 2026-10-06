<?php
// Slaptažodžio atkūrimas: 1) prašymas el. paštu, 2) naujo slaptažodžio nustatymas per nuorodą
require __DIR__ . '/app/bootstrap.php';

$t = get('t') ?: post('t');
$errors = [];

if ($t !== '') {
    $token = token_find('password_reset', $t);
    if ($token && is_post()) {
        csrf_check();
        $pw = (string) ($_POST['password'] ?? '');
        if (mb_strlen($pw) < MIN_PASSWORD) {
            $errors[] = 'Slaptažodis turi būti bent ' . MIN_PASSWORD . ' simbolių.';
        } elseif ($pw !== ($_POST['password2'] ?? '')) {
            $errors[] = 'Slaptažodžiai nesutampa.';
        } else {
            token_use((int) $token['id']);
            q('UPDATE accounts SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $token['account_id']]);
            // Nuoroda laiške patvirtina ir el. paštą
            q('UPDATE accounts SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?', [$token['account_id']]);
            advance_account_status((int) $token['account_id']);
            flash('ok', 'Slaptažodis pakeistas. Galite prisijungti.');
            redirect('prisijungti.php');
        }
    }
    page_start('Naujas slaptažodis', ['narrow' => true, 'noindex' => true]);
    if (!$token) { ?>
      <div class="panel card">
        <h1>Nuoroda nebegalioja</h1>
        <p class="muted">Nuoroda galioja 1 valandą ir tik vieną kartą.</p>
        <p style="margin-top:16px;"><a class="btn btn-primary" href="<?= url('slaptazodis.php') ?>">Gauti naują nuorodą</a></p>
      </div>
    <?php } else { ?>
      <div class="panel card">
        <h1 class="styled">Naujas slaptažodis</h1>
        <?= form_errors($errors) ?>
        <form method="post" class="form">
          <?= csrf_field() ?>
          <input type="hidden" name="t" value="<?= e($t) ?>">
          <label>Naujas slaptažodis <span class="hint">bent <?= MIN_PASSWORD ?> simboliai</span>
            <input type="password" name="password" autocomplete="new-password" minlength="<?= MIN_PASSWORD ?>" required>
          </label>
          <label>Pakartokite slaptažodį
            <input type="password" name="password2" autocomplete="new-password" required>
          </label>
          <button class="btn btn-primary btn-block" type="submit">Išsaugoti</button>
        </form>
      </div>
    <?php }
    page_end();
    exit;
}

if (is_post()) {
    csrf_check();
    $email = normalize_email(post('email'));
    $a = valid_email($email) ? q_one('SELECT * FROM accounts WHERE email = ? AND status <> "disabled"', [$email]) : null;
    // Ne daugiau 3 laiškų per valandą vienam adresui
    $recent = (int) q_value('SELECT COUNT(*) FROM email_tokens WHERE purpose = "password_reset" AND email = ? AND created_at > NOW() - INTERVAL 1 HOUR', [$email]);
    if ($a && $recent < 3) {
        $token = token_create('password_reset', $a['email'], (int) $a['id']);
        send_mail($a['email'], 'Slaptažodžio atkūrimas — Karateka',
            "Sveiki, {$a['first_name']},\n\nnorėdami nustatyti naują slaptažodį, paspauskite nuorodą:\n\n"
            . abs_url('slaptazodis.php?t=' . $token) . "\n\nNuoroda galioja 1 valandą. Jei slaptažodžio nekeitėte, šį laišką ignoruokite.");
    }
    // Atsakymas visada vienodas, kad nebūtų galima tikrinti, kurie el. paštai registruoti
    flash('ok', 'Jei toks el. paštas registruotas, išsiuntėme nuorodą slaptažodžiui atkurti.');
    redirect('slaptazodis.php');
}

page_start('Slaptažodžio atkūrimas', ['narrow' => true, 'noindex' => true]);
?>
<div class="panel card">
  <h1 class="styled">Pamiršote slaptažodį?</h1>
  <p class="muted" style="margin-bottom:18px;">Įveskite el. paštą - atsiųsime nuorodą naujam slaptažodžiui nustatyti.</p>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>El. paštas
      <input type="email" name="email" autocomplete="email" required autofocus>
    </label>
    <button class="btn btn-primary btn-block" type="submit">Siųsti nuorodą</button>
  </form>
  <hr class="divider">
  <a class="small" href="<?= url('prisijungti.php') ?>">← Grįžti į prisijungimą</a>
</div>
<?php
page_end();
