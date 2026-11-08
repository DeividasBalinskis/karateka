<?php
// Registracija į varžybas kitiems klubams (nuoroda: Valdymas → Varžybos → „📡 Transliacija ir registracija“).
// Klubo treneris įrašo dalyvius (vardas, pavardė, gimimo metai, lytis, diržas); Denis juos mato varžybų skirtuke „Dalyviai“
// ir prideda vienu mygtuku. Prisijungti nereikia - prieiga tik su nuorodos raktu.
require __DIR__ . '/app/bootstrap.php';

$k = (string) (get('k') ?: post('k'));
$t = tournament_by_token($k, 'reg');
if (!$t) {
    not_found();
}
$open = !empty($t['reg_open']);
$year = (int) date('Y');
$clubs = array_values(array_filter((array) (tournament_state($t)['clubs'] ?? []), function ($c) { return $c !== VARZ_HOME_CLUB; }));
$errors = [];

if (is_post() && $open) {
    csrf_check();
    $_SESSION['reg_posts'] = array_values(array_filter((array) ($_SESSION['reg_posts'] ?? []), function ($ts) { return $ts > time() - 3600; }));
    $club = trim(mb_substr(post('club') === '__other' ? post('club_other') : post('club'), 0, 120));
    $contact = trim(mb_substr(post('contact'), 0, 190));
    $rows = [];
    foreach ((array) ($_POST['name'] ?? []) as $i => $name) {
        $name = trim(preg_replace('/\s+/u', ' ', is_string($name) ? $name : ''));
        if ($name === '') {
            continue;
        }
        $birth = (int) ($_POST['birth'][$i] ?? 0);
        $gender = in_array($_POST['gender'][$i] ?? '', ['M', 'F'], true) ? $_POST['gender'][$i] : null;
        $belt = (int) ($_POST['belt'][$i] ?? 0);
        if (mb_strlen($name) < 3 || $birth < $year - 90 || $birth > $year - 3) {
            $errors[] = t('Patikrinkite eilutę: {name} (vardas ir pavardė, gimimo metai).', ['name' => $name]);
            continue;
        }
        $rows[] = [mb_substr($name, 0, 120), $birth, $gender, isset(BELTS[$belt]) ? $belt : null];
    }
    if (post('website') !== '' || count($_SESSION['reg_posts']) >= 10) {
        $errors[] = t('Per daug bandymų. Pabandykite vėliau.');
    }
    if ($club === '') {
        $errors[] = t('Pasirinkite arba įrašykite klubą.');
    }
    if ($contact === '') {
        $errors[] = t('Įrašykite trenerio el. paštą ar telefoną - jei kas neaišku, susisieksime.');
    }
    if (!$rows && !$errors) {
        $errors[] = t('Įrašykite bent vieną dalyvį.');
    }
    if (count($rows) > 80) {
        $errors[] = t('Vienu kartu - iki 80 dalyvių.');
    }
    if (!$errors) {
        foreach ($rows as [$name, $birth, $gender, $belt]) {
            q('INSERT INTO tournament_entries (tournament_id, club, contact, name, birth_year, gender, belt) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [(int) $t['id'], $club, $contact, $name, $birth, $gender, $belt]);
        }
        $_SESSION['reg_posts'][] = time();
        notify_admins('tournament_entries', 'Registracija į varžybas: ' . $club . ' (' . count($rows) . ' dalyv.)', $t['title'] . ' · ' . $contact,
            'admin/varzybu-sistema.php?id=' . (int) $t['id'] . '#dalyviai', null, (int) $t['id']);
        flash('ok', t('Užregistruota dalyvių: {n}. Organizatorius juos peržiūrės ir įtrauks į varžybas.', ['n' => count($rows)]));
        redirect('varzybos-registracija.php?k=' . $k);
    }
}

page_start(t('Registracija į varžybas') . ': ' . $t['title'], ['noindex' => true, 'narrow' => true]);
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
    <label><?= te('Klubas') ?>
      <select name="club" id="regClub" required>
        <option value=""><?= te('— pasirinkite —') ?></option>
        <?php foreach ($clubs as $c): ?><option<?= post('club') === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
        <option value="__other"<?= post('club') === '__other' ? ' selected' : '' ?>><?= te('Kitas klubas…') ?></option>
      </select></label>
    <label id="regOther"<?= post('club') === '__other' ? '' : ' hidden' ?>><?= te('Klubo pavadinimas') ?> <input type="text" name="club_other" maxlength="120" value="<?= e(post('club_other')) ?>"></label>
    <label><?= te('Trenerio el. paštas ar telefonas') ?> <input type="text" name="contact" maxlength="190" required value="<?= e(post('contact')) ?>"></label>
  </div>
  <div class="kicker" style="margin-top:8px;"><?= te('Dalyviai') ?></div>
  <div class="reg-rows" id="regRows">
    <div class="reg-row reg-head"><span><?= te('Vardas ir pavardė') ?></span><span><?= te('Gimimo metai') ?></span><span><?= te('Lytis') ?></span><span><?= te('Diržas') ?></span></div>
    <?php $n = max(6, count((array) ($_POST['name'] ?? []))); for ($i = 0; $i < $n; $i++): ?>
      <div class="reg-row">
        <input type="text" name="name[]" maxlength="120" placeholder="<?= te('Vardenis Pavardenis') ?>" aria-label="<?= te('Vardas ir pavardė') ?>" value="<?= e((string) ($_POST['name'][$i] ?? '')) ?>">
        <input type="number" name="birth[]" min="<?= $year - 90 ?>" max="<?= $year - 3 ?>" placeholder="<?= $year - 10 ?>" aria-label="<?= te('Gimimo metai') ?>" value="<?= e((string) ($_POST['birth'][$i] ?? '')) ?>">
        <select name="gender[]" aria-label="<?= te('Lytis') ?>"><option value="">—</option><option value="M"<?= ($_POST['gender'][$i] ?? '') === 'M' ? ' selected' : '' ?>>M</option><option value="F"<?= ($_POST['gender'][$i] ?? '') === 'F' ? ' selected' : '' ?>>F</option></select>
        <select name="belt[]" aria-label="<?= te('Diržas') ?>"><option value="">—</option><?php foreach (BELTS as $lvl => $b): ?><option value="<?= (int) $lvl ?>"<?= (int) ($_POST['belt'][$i] ?? 0) === (int) $lvl ? ' selected' : '' ?>><?= e($b[0]) ?></option><?php endforeach; ?></select>
      </div>
    <?php endfor; ?>
  </div>
  <div class="row" style="gap:10px;">
    <button type="button" class="btn btn-ghost btn-sm" id="regMore"><?= te('+ Dar eilučių') ?></button>
    <span class="hint"><?= te('Tuščios eilutės nesiunčiamos.') ?></span>
  </div>
  <p class="hint"><?= te('Duomenys naudojami tik šioms varžyboms organizuoti (kategorijos, lentelės, rezultatai). Viešai vaikų ir jaunių iki 18 m. rodomas vardas ir pavardės pirmoji raidė.') ?>
    <a href="<?= url('privatumas.php') ?>"><?= te('Privatumo politika') ?></a></p>
  <div><button class="btn btn-primary" type="submit"><?= te('Užregistruoti') ?></button></div>
</form>
<script>
(function () {
  var sel = document.getElementById('regClub'), other = document.getElementById('regOther');
  sel.addEventListener('change', function () { other.hidden = sel.value !== '__other'; other.querySelector('input').required = sel.value === '__other'; });
  document.getElementById('regMore').addEventListener('click', function () {
    var rows = document.getElementById('regRows'), last = rows.lastElementChild;
    for (var i = 0; i < 5; i++) { var c = last.cloneNode(true); c.querySelectorAll('input, select').forEach(function (x) { x.value = ''; }); rows.appendChild(c); }
  });
})();
</script>
<?php endif; ?>
<?php
page_end();
