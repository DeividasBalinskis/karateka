<?php
// Registracija į varžybas kitiems klubams (nuoroda: Valdymas → Varžybos → „📡 Transliacija ir registracija“).
// Klubo treneris ar tėvai įrašo dalyvius (vardas, pavardė, gimimo metai, lytis, diržas, vieno iš tėvų telefonas, sutikimas dėl nuotraukų);
// Denis juos mato varžybų skirtuke „Dalyviai“ ir prideda vienu mygtuku. Prisijungti nereikia - prieiga tik su nuorodos raktu.
require __DIR__ . '/app/bootstrap.php';

$k = (string) (get('k') ?: post('k'));
$t = tournament_by_token($k, 'reg');
if (!$t) {
    not_found();
}
$open = !empty($t['reg_open']);
$year = (int) date('Y');
$clubs = array_values(array_filter((array) (tournament_state($t)['clubs'] ?? []), function ($c) { return $c !== VARZ_HOME_CLUB; }));
$phoneOk = function (string $p): bool { return (bool) preg_match('/^\+?[\d\s()-]{8,20}$/', $p) && strlen(preg_replace('/\D/', '', $p)) >= 8; };
$errors = [];

if (is_post() && $open) {
    csrf_check();
    $_SESSION['reg_posts'] = array_values(array_filter((array) ($_SESSION['reg_posts'] ?? []), function ($ts) { return $ts > time() - 3600; }));
    $club = trim(preg_replace('/\s+/u', ' ', mb_substr(post('club'), 0, 120)));
    $contact = trim(mb_substr(post('contact'), 0, 40));
    $rows = [];
    foreach ((array) ($_POST['name'] ?? []) as $i => $name) {
        $name = trim(preg_replace('/\s+/u', ' ', is_string($name) ? $name : ''));
        if ($name === '') {
            continue;
        }
        $birth = (int) ($_POST['birth'][$i] ?? 0);
        $gender = in_array($_POST['gender'][$i] ?? '', ['M', 'F'], true) ? $_POST['gender'][$i] : null;
        $belt = (int) ($_POST['belt'][$i] ?? 0);
        $phone = trim(mb_substr((string) ($_POST['phone'][$i] ?? ''), 0, 40));
        $photo = $_POST['photo'][$i] ?? '';
        if (mb_strlen($name) < 3 || $birth < $year - 90 || $birth > $year - 3) {
            $errors[] = t('Patikrinkite eilutę: {name} (vardas ir pavardė, gimimo metai).', ['name' => $name]);
            continue;
        }
        if ($year - $birth < 18 && !$phoneOk($phone)) {
            $errors[] = t('{name}: įrašykite vieno iš tėvų telefono numerį.', ['name' => $name]);
            continue;
        }
        if ($phone !== '' && !$phoneOk($phone)) {
            $errors[] = t('{name}: patikrinkite telefono numerį.', ['name' => $name]);
            continue;
        }
        if (!in_array($photo, ['1', '0'], true)) {
            $errors[] = t('{name}: pasirinkite, ar sutinka būti fotografuojamas ir filmuojamas.', ['name' => $name]);
            continue;
        }
        $rows[] = [mb_substr($name, 0, 120), $birth, $gender, isset(BELTS[$belt]) ? $belt : null, $phone !== '' ? $phone : null, (int) $photo];
    }
    if (post('website') !== '' || count($_SESSION['reg_posts']) >= 10) {
        $errors[] = t('Per daug bandymų. Pabandykite vėliau.');
    }
    if ($club === '') {
        $errors[] = t('Pasirinkite arba įrašykite klubą.');
    }
    if (!$phoneOk($contact)) {
        $errors[] = t('Įrašykite kontaktinį telefono numerį - jei kas neaišku, susisieksime.');
    }
    if (!$rows && !$errors) {
        $errors[] = t('Įrašykite bent vieną dalyvį.');
    }
    if (count($rows) > 80) {
        $errors[] = t('Vienu kartu - iki 80 dalyvių.');
    }
    if (empty($_POST['consent_data']) || empty($_POST['consent_privacy'])) {
        $errors[] = t('Pažymėkite sutikimą dėl duomenų naudojimo ir kad susipažinote su privatumo politika.');
    }
    if (!$errors) {
        foreach ($rows as [$name, $birth, $gender, $belt, $phone, $photo]) {
            q('INSERT INTO tournament_entries (tournament_id, club, contact, parent_phone, name, birth_year, gender, belt, photo_ok, consent_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [(int) $t['id'], $club, $contact, $phone, $name, $birth, $gender, $belt, $photo]);
        }
        $_SESSION['reg_posts'][] = time();
        notify_admins('tournament_entries', 'Registracija į varžybas: ' . $club . ' (' . count($rows) . ' dalyv.)', $t['title'] . ' · ' . $contact,
            'admin/varzybu-sistema.php?id=' . (int) $t['id'] . '#dalyviai', null, (int) $t['id']);
        flash('ok', t('Užregistruota dalyvių: {n}. Organizatorius juos peržiūrės ir įtrauks į varžybas.', ['n' => count($rows)]));
        redirect('varzybos-registracija.php?k=' . $k);
    }
}

page_start(t('Registracija į varžybas') . ': ' . $t['title'], ['noindex' => true]);
$pv = function (string $key, int $i): string { return (string) ($_POST[$key][$i] ?? ''); };
?>
<div class="page-head">
  <div class="eyebrow"><?= te('Registracija klubams') ?></div>
  <h1 class="styled"><?= e($t['title']) ?></h1>
  <p><?= e(fmt_date($t['starts_on'], true)) ?><?= $t['ends_on'] ? ' – ' . e(fmt_date($t['ends_on'], true)) : '' ?><?= $t['location'] ? ' · ' . e($t['location']) : '' ?></p>
</div>
<?php if (!$open): ?>
  <div class="panel card"><p><?= te('Registracija šiuo metu uždaryta. Jei reikia - susisiekite su organizatoriumi.') ?></p></div>
<?php else: ?>
<?= form_errors($errors) ?>
<form method="post" class="panel card form reg-form">
  <?= csrf_field() ?>
  <input type="hidden" name="k" value="<?= e($k) ?>">
  <div style="position:absolute; left:-9999px;" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
  <div class="form-row">
    <label><?= te('Klubas') ?> <span class="hint"><?= te('pasirinkite iš sąrašo arba įrašykite') ?></span>
      <input type="text" name="club" list="regClubs" maxlength="120" required autocomplete="off" value="<?= e(post('club')) ?>"></label>
    <label><?= te('Kontaktinis tel. Nr.') ?> <span class="hint"><?= te('treneris ar vienas iš tėvų') ?></span>
      <input type="tel" name="contact" maxlength="40" required autocomplete="tel" placeholder="+370 6…" value="<?= e(post('contact')) ?>"></label>
  </div>
  <datalist id="regClubs"><?php foreach ($clubs as $c): ?><option value="<?= e($c) ?>"></option><?php endforeach; ?></datalist>

  <div class="kicker" style="margin-top:8px;"><?= te('Dalyviai') ?></div>
  <div class="reg-rows" id="regRows">
    <div class="reg-row reg-head"><span><?= te('Vardas ir pavardė') ?></span><span><?= te('Gimimo metai') ?></span><span><?= te('Lytis') ?></span><span><?= te('Diržas') ?></span>
      <span><?= te('Vieno iš tėvų tel. Nr.') ?></span><span><?= te('Nuotraukos, video') ?></span></div>
    <?php $n = max(4, count((array) ($_POST['name'] ?? []))); for ($i = 0; $i < $n; $i++): ?>
      <div class="reg-row">
        <input type="text" name="name[]" maxlength="120" placeholder="<?= te('Vardenis Pavardenis') ?>" aria-label="<?= te('Vardas ir pavardė') ?>" value="<?= e($pv('name', $i)) ?>">
        <input type="number" name="birth[]" min="<?= $year - 90 ?>" max="<?= $year - 3 ?>" placeholder="<?= te('metai') ?>" aria-label="<?= te('Gimimo metai') ?>" value="<?= e($pv('birth', $i)) ?>">
        <select name="gender[]" aria-label="<?= te('Lytis') ?>"><option value=""><?= te('lytis') ?></option><option value="M"<?= $pv('gender', $i) === 'M' ? ' selected' : '' ?>>M</option><option value="F"<?= $pv('gender', $i) === 'F' ? ' selected' : '' ?>>F</option></select>
        <select name="belt[]" aria-label="<?= te('Diržas') ?>"><option value=""><?= te('diržas') ?></option><?php foreach (BELTS as $lvl => $b): ?><option value="<?= (int) $lvl ?>"<?= (int) $pv('belt', $i) === (int) $lvl ? ' selected' : '' ?>><?= e($b[0]) ?></option><?php endforeach; ?></select>
        <input type="tel" name="phone[]" maxlength="40" placeholder="<?= te('tėvų tel. Nr.') ?>" aria-label="<?= te('Vieno iš tėvų tel. Nr.') ?>" value="<?= e($pv('phone', $i)) ?>">
        <select name="photo[]" aria-label="<?= te('Sutinka būti fotografuojamas ir filmuojamas') ?>"><option value=""><?= te('nuotraukos?') ?></option>
          <option value="1"<?= $pv('photo', $i) === '1' ? ' selected' : '' ?>><?= te('Sutinka') ?></option><option value="0"<?= $pv('photo', $i) === '0' ? ' selected' : '' ?>><?= te('Nesutinka') ?></option></select>
      </div>
    <?php endfor; ?>
  </div>
  <div class="row" style="gap:10px;">
    <button type="button" class="btn btn-ghost btn-sm" id="regMore"><?= te('+ Dar eilučių') ?></button>
    <span class="hint"><?= te('Tuščios eilutės nesiunčiamos. Vaikams iki 18 m. - būtinas vieno iš tėvų telefonas.') ?></span>
  </div>

  <div class="reg-privacy">
    <div class="kicker"><?= te('Asmens duomenų apsauga') ?></div>
    <p class="small"><?= te('Duomenys naudojami tik šioms varžyboms organizuoti: kategorijos, lentelės, tvarkaraštis, rezultatai. Telefonai - tik susisiekti dėl varžybų; jie ištrinami praėjus mėnesiui po varžybų. Varžybų lentelėse ir rezultatuose rodomas vardas, pavardė ir klubas; vaikų ir jaunių iki 18 m. - vardas ir pavardės pirmoji raidė.') ?></p>
    <p class="small"><?= te('Duomenų valdytojas - VšĮ Karate Ateitis.') ?> <?= te('Turite teisę susipažinti su savo duomenimis, juos ištaisyti ar paprašyti ištrinti:') ?> <a href="mailto:info@karateka.lt">info@karateka.lt</a>.</p>
    <p class="small"><?= te('Varžybų metu fotografuojama ir filmuojama. Dalyvių, kurie nesutinka, nuotraukų ir video organizatorius neskelbia.') ?></p>
    <label class="check"><input type="checkbox" name="consent_data" value="1" required<?= !empty($_POST['consent_data']) ? ' checked' : '' ?>><span><?= te('Sutinku, kad organizatoriai naudotų pateiktus duomenis varžybų organizavimui.') ?></span></label>
    <label class="check"><input type="checkbox" name="consent_privacy" value="1" required<?= !empty($_POST['consent_privacy']) ? ' checked' : '' ?>><span><?= te('Susipažinau su') ?> <a href="<?= url('privatumas.php') ?>" target="_blank" rel="noopener"><?= te('privatumo politika') ?></a>.</span></label>
  </div>
  <div><button class="btn btn-primary" type="submit"><?= te('Užregistruoti') ?></button></div>
</form>
<script>
(function () {
  document.getElementById('regMore').addEventListener('click', function () {
    var rows = document.getElementById('regRows'), last = rows.lastElementChild;
    for (var i = 0; i < 4; i++) { var c = last.cloneNode(true); c.querySelectorAll('input, select').forEach(function (x) { x.value = ''; }); rows.appendChild(c); }
  });
})();
</script>
<?php endif; ?>
<?php
page_end();
