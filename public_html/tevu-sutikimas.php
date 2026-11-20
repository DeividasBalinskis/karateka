<?php
// Tėvų sutikimas, kai jaunesnis nei 14 m. vaikas registruojasi pats.
// Nuoroda siunčiama tėvų el. paštu, todėl ją paspaudus el. paštas laikomas patvirtintu.
require __DIR__ . '/app/bootstrap.php';

$t = get('t') ?: post('t');
$token = token_find('parent_consent', $t);

if (!$token) {
    page_start(t('Tėvų sutikimas'), ['narrow' => true, 'noindex' => true]); ?>
    <div class="panel card">
      <h1><?= te('Nuoroda nebegalioja') ?></h1>
      <p class="muted"><?= te('Sutikimas jau pateiktas arba nuoroda pasibaigė. Jei reikia naujos nuorodos, parašykite info@karateka.lt.') ?></p>
    </div>
<?php
    page_end();
    exit;
}

$member = q_one('SELECT * FROM members WHERE id = ?', [$token['member_id']]);
$kidAccount = q_one('SELECT * FROM accounts WHERE id = ?', [$token['account_id']]);
$parent = q_one('SELECT * FROM accounts WHERE email = ?', [$token['email']]);
$errors = [];
$v = $_POST;

if (is_post()) {
    csrf_check();
    if (empty($_POST['consent'])) {
        $errors[] = t('Pažymėkite sutikimą, kad patvirtintumėte registraciją.');
    }
    $createAccount = !$parent && !empty($_POST['create_account']);
    if ($createAccount) {
        if (post('first_name') === '' || post('last_name') === '') {
            $errors[] = t('Įveskite savo vardą ir pavardę.');
        }
        $pw = (string) ($_POST['password'] ?? '');
        if (mb_strlen($pw) < MIN_PASSWORD) {
            $errors[] = (t('Slaptažodis turi būti bent') . ' ') . MIN_PASSWORD . (' ' . t('simbolių.'));
        }
    }

    if (!$errors) {
        db()->beginTransaction();
        try {
            q('UPDATE members SET parent_consent_at = NOW(), photo_consent = ? WHERE id = ?',
                [!empty($_POST['photo_consent']) ? 1 : 0, $member['id']]);
            if ($createAccount) {
                $pid = create_account($token['email'], $pw, post('first_name'), post('last_name'), post('phone'), 'pending_approval');
                q('UPDATE accounts SET email_verified_at = NOW() WHERE id = ?', [$pid]);
                link_member($pid, (int) $member['id'], 'parent');
            } elseif ($parent) {
                link_member((int) $parent['id'], (int) $member['id'], 'parent');
            }
            token_use((int) $token['id']);
            db()->commit();
        } catch (Throwable $ex) {
            db()->rollBack();
            throw $ex;
        }
        advance_account_status((int) $kidAccount['id']);

        page_start(t('Ačiū'), ['narrow' => true, 'noindex' => true]); ?>
        <div class="panel card">
          <h1 class="styled"><?= te('Ačiū!') ?></h1>
          <p><?= te('Sutikimas gautas.') ?> <?= e($member['first_name']) ?> <?= te('paskyrą dabar peržiūrės treneris ir priskirs grupę.') ?></p>
          <?php if ($createAccount || $parent): ?>
            <p><?= te('Prisijungę savo paskyroje matysite vaiko tvarkaraštį ir renginius') ?><?= $createAccount ? (' ' . t('(kai treneris patvirtins paskyrą)')) : '' ?>.</p>
            <p style="margin-top:18px;"><a class="btn btn-primary" href="<?= url('prisijungti.php') ?>"><?= te('Prisijungti') ?></a></p>
          <?php endif; ?>
        </div>
<?php
        page_end();
        exit;
    }
}

page_start(t('Tėvų sutikimas'), ['narrow' => true, 'noindex' => true]);
?>
<div class="panel card">
  <div class="eyebrow"><?= te('Tėvų sutikimas') ?></div>
  <h1 class="styled"><?= e($member['first_name'] . ' ' . $member['last_name']) ?></h1>
  <p class="muted"><?= te('Gimimo data:') ?> <?= e($member['birth_date']) ?> <?= te('· El. paštas:') ?> <?= e($kidAccount['email']) ?></p>
  <p style="margin-top:12px;"><?= te('Vaikas užsiregistravo VšĮ Karate Ateitis narių sistemoje. Kadangi jam (-ai) dar nėra 14 metų, reikia jūsų sutikimo.') ?></p>
  <hr class="divider">
  <?= form_errors($errors) ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="t" value="<?= e($t) ?>">
    <label class="check"><input type="checkbox" name="consent" value="1" required>
      <span><?= te('Esu vaiko tėvas / mama / globėjas ir sutinku, kad vaikas turėtų paskyrą, o klubas tvarkytų jo duomenis narystės administravimo tikslais (') ?><a href="<?= url('privatumas.php') ?>" target="_blank">privatumo politika</a>).</span></label>
    <label class="check"><input type="checkbox" name="photo_consent" value="1" <?= !empty($v['photo_consent']) ? 'checked' : '' ?>>
      <span><?= te('Sutinku, kad klubas skelbtų vaiko nuotraukas ir vaizdo įrašus iš treniruočių ir renginių.') ?></span></label>

    <?php if ($parent): ?>
      <p class="hint"><?= te('Vaikas bus susietas su jūsų paskyra (') ?><?= e($parent['email']) ?>).</p>
    <?php else: ?>
      <hr class="divider">
      <label class="check"><input type="checkbox" name="create_account" value="1" id="createAcc" <?= !is_post() || !empty($v['create_account']) ? 'checked' : '' ?>>
        <span><?= te('Susikurti tėvų paskyrą (') ?><?= e($token['email']) ?><?= te(') - matysiu vaiko tvarkaraštį ir renginius') ?></span></label>
      <div id="accFields" class="form">
        <div class="form-row">
          <label><?= te('Vardas') ?> <input type="text" name="first_name" value="<?= e($v['first_name'] ?? '') ?>" autocomplete="given-name"></label>
          <label><?= te('Pavardė') ?> <input type="text" name="last_name" value="<?= e($v['last_name'] ?? '') ?>" autocomplete="family-name"></label>
        </div>
        <label><?= te('Telefonas') ?> <input type="tel" name="phone" value="<?= e($v['phone'] ?? '') ?>" autocomplete="tel"></label>
        <label><?= te('Slaptažodis') ?> <span class="hint">bent <?= MIN_PASSWORD ?> simboliai</span>
          <input type="password" name="password" autocomplete="new-password"></label>
      </div>
      <script>
        (function () {
          var c = document.getElementById('createAcc'), f = document.getElementById('accFields');
          function u() { f.style.display = c.checked ? '' : 'none'; }
          c.addEventListener('change', u); u();
        })();
      </script>
    <?php endif; ?>
    <button class="btn btn-primary btn-block" type="submit"><?= te('Patvirtinti') ?></button>
  </form>
</div>
<?php
page_end();
