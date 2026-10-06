<?php
// Registracija: tėvai (su vaikais) arba pats narys (14+ savarankiškai, jaunesni - su tėvų sutikimu)
require __DIR__ . '/app/bootstrap.php';

if (current_account()) {
    redirect('paskyra.php');
}

const MAX_KIDS = 6;

$type = get('tipas') ?: post('tipas');
$errors = [];
$v = $_POST;   // formos reikšmės, kad nereikėtų vesti iš naujo

function check_person(string $first, string $last, string $label, array &$errors): void
{
    if ($first === '' || $last === '') {
        $errors[] = "$label: įveskite vardą ir pavardę.";
    } elseif (mb_strlen($first) > 80 || mb_strlen($last) > 80) {
        $errors[] = "$label: vardas arba pavardė per ilgi.";
    }
}

function check_password(array &$errors): string
{
    $pw = (string) ($_POST['password'] ?? '');
    if (mb_strlen($pw) < MIN_PASSWORD) {
        $errors[] = 'Slaptažodis turi būti bent ' . MIN_PASSWORD . ' simbolių.';
    } elseif ($pw !== ($_POST['password2'] ?? '')) {
        $errors[] = 'Slaptažodžiai nesutampa.';
    }
    return $pw;
}

if (is_post() && in_array($type, ['tevai', 'pats'], true)) {
    csrf_check();

    // Botų spąstai: paslėptas laukas, kurį pildo tik robotai
    if (post('website') !== '') {
        redirect('registracija.php?ok=1');
    }

    $email = normalize_email(post('email'));
    $first = post('first_name');
    $last = post('last_name');
    $phone = post('phone');

    check_person($first, $last, $type === 'tevai' ? 'Tėvai' : 'Narys', $errors);
    if (!valid_email($email)) {
        $errors[] = 'Įveskite teisingą el. paštą.';
    } elseif (q_value('SELECT 1 FROM accounts WHERE email = ?', [$email])) {
        $errors[] = 'Šis el. paštas jau registruotas. Prisijunkite arba atkurkite slaptažodį.';
    }
    $password = check_password($errors);
    if (empty($_POST['data_consent'])) {
        $errors[] = 'Reikia sutikimo dėl asmens duomenų tvarkymo.';
    }

    if ($type === 'tevai') {
        if ($phone === '') {
            $errors[] = 'Įveskite telefono numerį.';
        }
        $kids = [];
        foreach (array_slice((array) ($_POST['kids'] ?? []), 0, MAX_KIDS) as $i => $k) {
            $k = array_map(fn($x) => is_string($x) ? trim($x) : '', (array) $k);
            if (($k['first_name'] ?? '') === '' && ($k['last_name'] ?? '') === '' && ($k['birth_date'] ?? '') === '') {
                continue;   // tuščias blokas
            }
            $n = count($kids) + 1;
            check_person($k['first_name'] ?? '', $k['last_name'] ?? '', "Vaikas nr. $n", $errors);
            if (!valid_birth_date($k['birth_date'] ?? '')) {
                $errors[] = "Vaikas nr. $n: įveskite gimimo datą.";
            }
            $kids[] = $k;
        }
        if (!$kids) {
            $errors[] = 'Pridėkite bent vieną vaiką.';
        }
    } else {
        $birth = post('birth_date');
        $parentEmail = normalize_email(post('parent_email'));
        $under14 = valid_birth_date($birth) && age_on($birth) < CONSENT_AGE;
        if (!valid_birth_date($birth)) {
            $errors[] = 'Įveskite gimimo datą.';
        } elseif ($under14) {
            if (!valid_email($parentEmail)) {
                $errors[] = 'Jaunesniems nei 14 metų reikia tėvų el. pašto - tėvai turės patvirtinti registraciją.';
            } elseif ($parentEmail === $email) {
                $errors[] = 'Tėvų el. paštas turi skirtis nuo tavo el. pašto.';
            }
        }
    }

    if (!$errors) {
        db()->beginTransaction();
        try {
            $status = 'pending_email';
            $accountId = create_account($email, $password, $first, $last, $phone, $status);

            if ($type === 'tevai') {
                foreach ($kids as $k) {
                    $mid = create_member($k['first_name'], $k['last_name'], $k['birth_date'], !empty($k['photo_consent']));
                    // Tėvai patys registruoja vaiką - tai ir yra tėvų sutikimas
                    q('UPDATE members SET parent_consent_at = NOW() WHERE id = ?', [$mid]);
                    link_member($accountId, $mid, 'parent');
                }
            } else {
                // Jaunesniems nei 14 m. sutikimą dėl nuotraukų duoda tėvai
                $mid = create_member($first, $last, $birth, !$under14 && !empty($_POST['photo_consent']));
                link_member($accountId, $mid, 'self');
            }
            db()->commit();
        } catch (Throwable $ex) {
            db()->rollBack();
            throw $ex;
        }

        $account = q_one('SELECT * FROM accounts WHERE id = ?', [$accountId]);
        send_verification_email($account);
        if ($type === 'pats' && $under14) {
            send_parent_consent_email($parentEmail, $account, q_one('SELECT * FROM members WHERE id = ?', [$mid]));
        }
        redirect('registracija.php?ok=' . ($type === 'pats' && $under14 ? '2' : '1'));
    }
}

page_start('Registracija', ['narrow' => $type === '', 'noindex' => true,
    'description' => 'Susikurkite VšĮ Karate Ateitis nario arba tėvų paskyrą.']);

// ---------- Sėkmė ----------
if (get('ok') !== '') { ?>
  <div class="panel card">
    <h1 class="styled">Beveik baigta!</h1>
    <p>Išsiuntėme laišką su nuoroda - <strong>patvirtinkite savo el. paštą</strong>.</p>
    <?php if (get('ok') === '2'): ?>
      <p>Taip pat išsiuntėme laišką tavo tėvams: kai jie patvirtins, paskyrą peržiūrės treneris.</p>
    <?php else: ?>
      <p>Po to paskyrą peržiūrės treneris ir priskirs grupę. Apie patvirtinimą pranešime el. paštu.</p>
    <?php endif; ?>
    <p class="muted small" style="margin-top:14px;">Laiško nematote? Patikrinkite „Spam“ / „Reklamos“ aplanką.</p>
  </div>
<?php
    page_end();
    exit;
}

// ---------- Pasirinkimas ----------
if (!in_array($type, ['tevai', 'pats'], true)) { ?>
  <div class="panel card">
    <h1 class="styled">Registracija</h1>
    <p class="muted" style="margin-bottom:20px;">Narių paskyroje matysite savo grupės tvarkaraštį, artėjančius egzaminus ir varžybas.</p>
    <div class="choice-cards">
      <a class="choice-card" href="?tipas=tevai">
        <strong>Esu tėvai / globėjai</strong>
        <span>Užregistruosiu vieną ar kelis vaikus</span>
      </a>
      <a class="choice-card" href="?tipas=pats">
        <strong>Registruojuosi pats</strong>
        <span>Treniruojuosi pats (-i). Jaunesniems nei 14 m. reikės tėvų sutikimo</span>
      </a>
    </div>
    <hr class="divider">
    <p class="small">Jau turite paskyrą? <a href="<?= url('prisijungti.php') ?>">Prisijunkite</a></p>
  </div>
<?php
    page_end();
    exit;
}

$kidsInput = array_values((array) ($v['kids'] ?? [[]])) ?: [[]];
?>
<div class="page-head">
  <a class="small" href="<?= url('registracija.php') ?>">← Atgal</a>
  <h1 class="styled" style="margin-top:8px;"><?= $type === 'tevai' ? 'Tėvų registracija' : 'Nario registracija' ?></h1>
</div>
<?= form_errors($errors) ?>

<form method="post" class="form" id="regForm">
  <?= csrf_field() ?>
  <input type="hidden" name="tipas" value="<?= e($type) ?>">
  <div style="position:absolute; left:-9999px;" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>

  <div class="grid-2">
    <div class="panel card">
      <h2><?= $type === 'tevai' ? 'Jūsų duomenys' : 'Tavo duomenys' ?></h2>
      <div class="form">
        <div class="form-row">
          <label>Vardas <input type="text" name="first_name" value="<?= e($v['first_name'] ?? '') ?>" autocomplete="given-name" required></label>
          <label>Pavardė <input type="text" name="last_name" value="<?= e($v['last_name'] ?? '') ?>" autocomplete="family-name" required></label>
        </div>
        <?php if ($type === 'pats'): ?>
          <label>Gimimo data
            <input type="date" name="birth_date" id="birthDate" value="<?= e($v['birth_date'] ?? '') ?>" max="<?= date('Y-m-d') ?>" required>
          </label>
        <?php endif; ?>
        <label>El. paštas <span class="hint">juo prisijungsite</span>
          <input type="email" name="email" value="<?= e($v['email'] ?? '') ?>" autocomplete="email" required>
        </label>
        <label>Telefonas <?= $type === 'pats' ? '<span class="hint">nebūtina</span>' : '' ?>
          <input type="tel" name="phone" value="<?= e($v['phone'] ?? '') ?>" autocomplete="tel" <?= $type === 'tevai' ? 'required' : '' ?>>
        </label>
        <div class="form-row">
          <label>Slaptažodis <span class="hint">bent <?= MIN_PASSWORD ?> simboliai</span>
            <input type="password" name="password" autocomplete="new-password" minlength="<?= MIN_PASSWORD ?>" required>
          </label>
          <label>Pakartokite
            <input type="password" name="password2" autocomplete="new-password" required>
          </label>
        </div>
      </div>
    </div>

    <div class="panel card">
      <?php if ($type === 'tevai'): ?>
        <h2>Vaikai</h2>
        <div id="kids" class="form">
          <?php foreach ($kidsInput as $i => $k): ?>
            <fieldset class="kid">
              <legend>Vaikas</legend>
              <div class="form-row">
                <label>Vardas <input type="text" name="kids[<?= $i ?>][first_name]" value="<?= e($k['first_name'] ?? '') ?>"></label>
                <label>Pavardė <input type="text" name="kids[<?= $i ?>][last_name]" value="<?= e($k['last_name'] ?? '') ?>"></label>
              </div>
              <label>Gimimo data <input type="date" name="kids[<?= $i ?>][birth_date]" value="<?= e($k['birth_date'] ?? '') ?>" max="<?= date('Y-m-d') ?>"></label>
              <label class="check"><input type="checkbox" name="kids[<?= $i ?>][photo_consent]" value="1" <?= !empty($k['photo_consent']) ? 'checked' : '' ?>>
                <span>Sutinku, kad klubas skelbtų vaiko nuotraukas ir vaizdo įrašus iš treniruočių ir renginių (svetainėje, socialiniuose tinkluose).</span></label>
            </fieldset>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" id="addKid" style="margin-top:14px;">+ Pridėti dar vieną vaiką</button>
      <?php else: ?>
        <h2>Sutikimai</h2>
        <div class="form">
          <div id="under14" class="flash flash-info" style="display:none; margin:0;">
            Kadangi tau dar nėra 14 metų, registraciją turi patvirtinti tėvai. Įvesk jų el. paštą - išsiųsime jiems laišką.
            Sutikimą dėl nuotraukų skelbimo duos tėvai.
          </div>
          <label id="parentEmailWrap" style="display:none;">Tėvų / globėjų el. paštas
            <input type="email" name="parent_email" value="<?= e($v['parent_email'] ?? '') ?>">
          </label>
          <label class="check" id="photoWrap"><input type="checkbox" name="photo_consent" value="1" <?= !empty($v['photo_consent']) ? 'checked' : '' ?>>
            <span>Sutinku, kad klubas skelbtų mano nuotraukas ir vaizdo įrašus iš treniruočių ir renginių.</span></label>
          <p class="hint">Neturi savo el. pašto? Tegul tėvai tave užregistruoja per <a href="?tipas=tevai">tėvų registraciją</a> - vėliau jie galės pakviesti tave prisijungti.</p>
        </div>
      <?php endif; ?>
      <hr class="divider">
      <label class="check"><input type="checkbox" name="data_consent" value="1" required <?= !empty($v['data_consent']) ? 'checked' : '' ?>>
        <span>Sutinku, kad VšĮ Karate Ateitis tvarkytų pateiktus duomenis narystės administravimo tikslais. Duomenys neperduodami tretiesiems asmenims.</span></label>
      <button class="btn btn-primary btn-block" type="submit" style="margin-top:18px;">Registruotis</button>
      <p class="hint" style="margin-top:10px;">Paskyrą patvirtins treneris ir priskirs grupę.</p>
    </div>
  </div>
</form>

<script>
<?php if ($type === 'tevai'): ?>
(function () {
  var kids = document.getElementById('kids'), max = <?= MAX_KIDS ?>;
  document.getElementById('addKid').addEventListener('click', function () {
    var all = kids.querySelectorAll('.kid');
    if (all.length >= max) return;
    var copy = all[all.length - 1].cloneNode(true), idx = all.length;
    copy.querySelectorAll('input').forEach(function (inp) {
      inp.name = inp.name.replace(/kids\[\d+\]/, 'kids[' + idx + ']');
      if (inp.type === 'checkbox') inp.checked = false; else inp.value = '';
    });
    kids.appendChild(copy);
    if (idx + 1 >= max) this.style.display = 'none';
  });
})();
<?php else: ?>
(function () {
  var bd = document.getElementById('birthDate');
  function update() {
    var under = false;
    if (bd.value) {
      var b = new Date(bd.value), t = new Date();
      var age = t.getFullYear() - b.getFullYear() - ((t.getMonth() < b.getMonth() || (t.getMonth() === b.getMonth() && t.getDate() < b.getDate())) ? 1 : 0);
      under = age < <?= CONSENT_AGE ?>;
    }
    document.getElementById('under14').style.display = under ? '' : 'none';
    document.getElementById('parentEmailWrap').style.display = under ? '' : 'none';
    document.getElementById('photoWrap').style.display = under ? 'none' : '';
    document.querySelector('[name=parent_email]').required = under;
  }
  bd.addEventListener('change', update); bd.addEventListener('input', update); update();
})();
<?php endif; ?>
</script>
<?php
page_end();
