<?php
// Pakvietimas nariui, kurį treneris pridėjo be paskyros (admin → Naujas narys / nario puslapis → „Pakviesti“).
// Per nuorodą žmogus susikuria prisijungimą (arba prisijungia esamu) ir yra susiejamas su tuo nariu - pats ar kaip tėvai.
require __DIR__ . '/app/bootstrap.php';

$t = (string) (get('t') ?: post('t'));
$token = token_find('join', $t);
$member = $token ? q_one('SELECT * FROM members WHERE id = ?', [$token['member_id']]) : null;

if (!$token || !$member) {
    page_start(t('Kvietimas'), ['narrow' => true, 'noindex' => true]); ?>
    <div class="panel card">
      <h1><?= te('Kvietimas nebegalioja') ?></h1>
      <p class="muted"><?= te('Nuoroda jau panaudota arba pasibaigė. Paprašykite trenerio atsiųsti naują.') ?></p>
      <p style="margin-top:18px;"><a class="btn btn-primary" href="<?= url('prisijungti.php') ?>"><?= te('Prisijungti') ?></a></p>
    </div>
<?php
    page_end();
    exit;
}

$me = current_account();
$name = $member['first_name'] . ' ' . $member['last_name'];
$hasSelf = (bool) q_value('SELECT 1 FROM account_members WHERE member_id = ? AND relation = "self"', [$member['id']]);
$minor = age_on($member['birth_date']) < 14;
$errors = [];

if (is_post()) {
    csrf_check();
    $rel = post('relation') === 'self' ? 'self' : 'parent';
    if ($rel === 'self' && $hasSelf) {
        $errors[] = t('Šis narys jau turi savo paskyrą. Jei esate tėvai - pasirinkite „Aš - tėvai ar globėjai“.');
    } elseif ($me) {
        link_member((int) $me['id'], (int) $member['id'], $rel);
        token_use((int) $token['id']);
        flash('ok', t('Narys pridėtas prie jūsų paskyros:') . ' ' . $name);
        redirect('paskyra.php');
    } else {
        $email = normalize_email(post('email'));
        $first = trim((string) post('first_name'));
        $last = trim((string) post('last_name'));
        $pw = (string) ($_POST['password'] ?? '');
        if ($first === '' || $last === '') {
            $errors[] = t('Įrašykite vardą ir pavardę.');
        } elseif (!valid_email($email)) {
            $errors[] = t('Įveskite teisingą el. paštą.');
        } elseif (q_value('SELECT 1 FROM accounts WHERE email = ?', [$email])) {
            $errors[] = t('Šis el. paštas jau turi paskyrą - prisijunkite ir atidarykite nuorodą dar kartą.');
        } elseif (mb_strlen($pw) < MIN_PASSWORD) {
            $errors[] = t('Slaptažodis turi būti bent') . ' ' . MIN_PASSWORD . ' ' . t('simbolių.');
        } elseif ($pw !== ($_POST['password2'] ?? '')) {
            $errors[] = t('Slaptažodžiai nesutampa.');
        } else {
            // Treneris pats pridėjo narį - paskyra aktyvi iš karto (vaikui iki 14 m. su sava paskyra - patvirtina treneris)
            $status = $rel === 'self' && $minor ? 'pending_approval' : 'active';
            db()->beginTransaction();
            try {
                $id = create_account($email, $pw, $first, $last, post('phone') ?: null, $status);
                if ($email === normalize_email((string) $token['email'])) {
                    q('UPDATE accounts SET email_verified_at = NOW() WHERE id = ?', [$id]);
                }
                link_member($id, (int) $member['id'], $rel);
                token_use((int) $token['id']);
                db()->commit();
            } catch (Throwable $ex) {
                db()->rollBack();
                throw $ex;
            }
            $acc = q_one('SELECT * FROM accounts WHERE id = ?', [$id]);
            if (!$acc['email_verified_at']) {
                send_verification_email($acc);
            }
            if ($status === 'pending_approval') {
                notify_coach_pending($acc);
            }
            login_account($id);
            flash('ok', $status === 'active' ? t('Paskyra sukurta! Narys jau susietas su jūsų paskyra.') : t('Paskyra sukurta! Kai treneris ją patvirtins, matysi savo tvarkaraštį ir renginius.'));
            redirect('paskyra.php');
        }
    }
}

$rel = post('relation') ?: ($hasSelf ? 'parent' : ($minor ? 'parent' : 'self'));
page_start(t('Kvietimas'), ['narrow' => true, 'noindex' => true]);
?>
<div class="panel card join-card">
  <div class="eyebrow"><?= te('Kvietimas į karateka.lt') ?></div>
  <h1 class="styled"><?= e($name) ?></h1>
  <p class="muted"><?= te('Treneris pridėjo šį narį į klubo sistemą. Susikurkite prisijungimą - matysite tvarkaraštį, renginius, XP ir klubo žaidimą.') ?></p>
  <?= form_errors($errors) ?>
  <form method="post" class="form" style="margin-top:14px;">
    <?= csrf_field() ?>
    <input type="hidden" name="t" value="<?= e($t) ?>">
    <fieldset class="join-who">
      <legend><?= te('Kas jūs?') ?></legend>
      <?php if (!$hasSelf): ?>
        <label class="check"><input type="radio" name="relation" value="self" <?= $rel === 'self' ? 'checked' : '' ?>> <span><?= te('Aš esu') ?> <?= e($member['first_name']) ?></span></label>
      <?php endif; ?>
      <label class="check"><input type="radio" name="relation" value="parent" <?= $rel === 'parent' ? 'checked' : '' ?>> <span><?= te('Aš - tėvai ar globėjai') ?></span></label>
    </fieldset>
    <?php if ($me): ?>
      <p><?= te('Prisijungta kaip') ?> <b><?= e($me['email']) ?></b>.</p>
      <button class="btn btn-primary btn-block" type="submit"><?= te('Pridėti prie mano paskyros') ?></button>
    <?php else: ?>
      <div class="form-row">
        <label><?= te('Vardas') ?> <input type="text" name="first_name" id="jFirst" value="<?= e(post('first_name') ?: ($rel === 'self' ? $member['first_name'] : '')) ?>" required></label>
        <label><?= te('Pavardė') ?> <input type="text" name="last_name" id="jLast" value="<?= e(post('last_name') ?: ($rel === 'self' ? $member['last_name'] : '')) ?>" required></label>
      </div>
      <label><?= te('El. paštas') ?> <input type="email" name="email" value="<?= e(post('email') ?: (string) $token['email']) ?>" autocomplete="email" required></label>
      <label><?= te('Telefonas') ?> <span class="hint"><?= te('nebūtina') ?></span><input type="tel" name="phone" value="<?= e(post('phone')) ?>" autocomplete="tel"></label>
      <label><?= te('Slaptažodis') ?> <span class="hint"><?= te('bent') ?> <?= MIN_PASSWORD ?> <?= te('simboliai') ?></span>
        <input type="password" name="password" autocomplete="new-password" minlength="<?= MIN_PASSWORD ?>" required></label>
      <label><?= te('Pakartok slaptažodį') ?> <input type="password" name="password2" autocomplete="new-password" required></label>
      <button class="btn btn-primary btn-block" type="submit"><?= te('Sukurti paskyrą') ?></button>
      <p class="small muted" style="margin-top:12px;"><?= te('Jau turite paskyrą?') ?> <a href="<?= url('prisijungti.php?r=' . rawurlencode(url('jungtis.php?t=' . $t))) ?>"><?= te('Prisijunkite') ?></a> - <?= te('narys bus pridėtas prie jos.') ?></p>
    <?php endif; ?>
  </form>
</div>
<script>
// „Aš esu …“ - vardas ir pavardė užpildomi nario; „tėvai“ - tušti (savo)
(function () {
  var f = document.getElementById('jFirst'), l = document.getElementById('jLast'); if (!f) return;
  var mf = <?= json_encode($member['first_name']) ?>, ml = <?= json_encode($member['last_name']) ?>;
  document.querySelectorAll('input[name=relation]').forEach(function (r) {
    r.addEventListener('change', function () {
      if (r.value === 'self') { if (!f.value) f.value = mf; if (!l.value) l.value = ml; }
      else { if (f.value === mf) f.value = ''; if (l.value === ml) l.value = ''; }
    });
  });
})();
</script>
<?php
page_end();
