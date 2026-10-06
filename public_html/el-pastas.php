<?php
// Naujo el. pašto patvirtinimas (nuoroda iš laiško, išsiųsto į naują adresą)
require __DIR__ . '/app/bootstrap.php';

$token = token_find('change_email', get('t'));
$a = $token ? q_one('SELECT * FROM accounts WHERE id = ?', [$token['account_id']]) : null;

if (!$token || !$a) {
    page_start('El. pašto keitimas', ['narrow' => true, 'noindex' => true]); ?>
    <div class="panel card">
      <h1>Nuoroda nebegalioja</h1>
      <p class="muted">Nuoroda jau panaudota arba pasibaigė. El. paštą galite pakeisti iš naujo savo paskyroje.</p>
      <p style="margin-top:18px;"><a class="btn btn-primary" href="<?= url('paskyra.php') ?>">Mano paskyra</a></p>
    </div>
<?php
    page_end();
    exit;
}

token_use((int) $token['id']);
if (q_value('SELECT 1 FROM accounts WHERE email = ? AND id <> ?', [$token['email'], $a['id']])) {
    flash('err', 'Šis el. paštas jau naudojamas kitos paskyros.');
} else {
    q('UPDATE accounts SET email = ?, email_verified_at = NOW() WHERE id = ?', [$token['email'], $a['id']]);
    // Pranešimas senu adresu - jei keitė ne pats savininkas, jis sužinos
    send_mail($a['email'], 'Jūsų el. paštas pakeistas — Karateka',
        "Sveiki, {$a['first_name']},\n\njūsų karateka.lt paskyros el. paštas pakeistas į {$token['email']}.\n"
        . "Nuo šiol prisijunkite nauju adresu.\n\nJei to nedarėte, nedelsdami parašykite info@karateka.lt.");
    flash('ok', 'El. paštas pakeistas. Nuo šiol prisijunkite: ' . $token['email']);
}
redirect(current_account() ? 'paskyra.php#nustatymai' : 'prisijungti.php');
