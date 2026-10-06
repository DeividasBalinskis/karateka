<?php
// Pirmo administratoriaus sukūrimas naujame serveryje.
// Veikia TIK kol nėra nė vieno administratoriaus ir tik su raktu iš config.php:
//   https://test.karateka.lt/admin/pirmas-adminas.php?key=<setup_key>
require dirname(__DIR__) . '/app/bootstrap.php';

$key = (string) config('setup_key', '');
$given = get('key') ?: post('key');
$hasAdmin = (bool) q_value('SELECT 1 FROM accounts WHERE role = "admin" LIMIT 1');

if ($hasAdmin || strlen($key) < 8 || !hash_equals($key, $given)) {
    not_found();
}

$errors = [];
if (is_post()) {
    csrf_check();
    $email = normalize_email(post('email'));
    $pw = (string) ($_POST['password'] ?? '');
    if (post('first_name') === '' || post('last_name') === '') {
        $errors[] = 'Įveskite vardą ir pavardę.';
    }
    if (!valid_email($email)) {
        $errors[] = 'Įveskite el. paštą.';
    } elseif (q_value('SELECT 1 FROM accounts WHERE email = ?', [$email])) {
        $errors[] = 'Toks el. paštas jau yra.';
    }
    if (mb_strlen($pw) < 10) {
        $errors[] = 'Administratoriaus slaptažodis turi būti bent 10 simbolių.';
    }
    if (!$errors) {
        $id = create_account($email, $pw, post('first_name'), post('last_name'), null, 'active');
        q('UPDATE accounts SET role = "admin", email_verified_at = NOW(), approved_at = NOW() WHERE id = ?', [$id]);
        login_account($id);
        flash('ok', 'Administratorius sukurtas. Pakeiskite setup_key config.php faile.');
        redirect('admin/');
    }
}

page_start('Pirmas administratorius', ['narrow' => true, 'noindex' => true]);
?>
<div class="panel card">
  <h1 class="styled">Pirmas administratorius</h1>
  <p class="muted" style="margin-bottom:18px;">Šis puslapis veikia tik vieną kartą - kol sistemoje nėra administratoriaus.</p>
  <?= form_errors($errors) ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="key" value="<?= e($given) ?>">
    <div class="form-row">
      <label>Vardas <input type="text" name="first_name" value="<?= e(post('first_name')) ?>" required></label>
      <label>Pavardė <input type="text" name="last_name" value="<?= e(post('last_name')) ?>" required></label>
    </div>
    <label>El. paštas <input type="email" name="email" value="<?= e(post('email')) ?>" required></label>
    <label>Slaptažodis <span class="hint">bent 10 simbolių</span><input type="password" name="password" minlength="10" required></label>
    <button class="btn btn-primary btn-block" type="submit">Sukurti</button>
  </form>
</div>
<?php
page_end();
