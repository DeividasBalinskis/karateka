<?php
// Registracija į varžybas kitiems klubams (nuoroda: Valdymas → Varžybos → „📡 Transliacija ir registracija“).
// Klubo treneris ar tėvai įrašo dalyvius (vardas, pavardė, gimimo metai, lytis, diržas, vieno iš tėvų telefonas, sutikimas dėl nuotraukų);
// Denis juos mato varžybų skirtuke „Dalyviai“ ir prideda vienu mygtuku. Prisijungti nereikia - prieiga tik su nuorodos raktu.
require __DIR__ . '/app/bootstrap.php';

$k = (string) (get('k') ?: post('k'));
$t = tournament_by_token($k, 'reg');
if (!$t) {
    link_dead();
}
$open = !empty($t['reg_open']) && reg_on('budo');
$year = (int) date('Y');
// Ir Karateka: jos nariai (ar tėvai) irgi gali registruotis per tą pačią nuorodą
$clubs = array_values(array_unique(array_merge([VARZ_HOME_CLUB], array_map('strval', (array) (tournament_state($t)['clubs'] ?? [])))));
$phoneOk = function (string $p): bool { return (bool) preg_match('/^\+?[\d\s()-]{8,20}$/', $p) && strlen(preg_replace('/\D/', '', $p)) >= 8; };
$errors = [];
$discs = tournament_reg_disciplines($t);   // kur galima varžytis (pagal Denio sukurtas grupes)
$tid = (int) $t['id'];
// Klubo vadovas mato, ką užregistravo šiame įrenginyje (sesija), ir gali atšaukti dar nepridėtus
$mineIds = array_map('intval', (array) ($_SESSION['reg_mine'][$tid] ?? []));
$mineLoad = function () use ($tid, &$mineIds): array {
    if (!$mineIds) {
        return [];
    }
    return q_all('SELECT id, club, name, birth_year, disciplines, status FROM tournament_entries WHERE tournament_id = ? AND id IN (' . implode(',', $mineIds) . ') ORDER BY id', [$tid]);
};

if (is_post() && $open && post('cancel') !== '') {
    csrf_check();
    $cid = (int) post('cancel');
    if (in_array($cid, $mineIds, true)) {
        q('DELETE FROM tournament_entries WHERE id = ? AND tournament_id = ? AND status = "new"', [$cid, $tid]);
        $_SESSION['reg_mine'][$tid] = array_values(array_diff($mineIds, [$cid]));
        flash('ok', t('Registracija atšaukta.'));
    }
    redirect('varzybos-registracija.php?k=' . $k . '#mano');
}

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
        $beltRaw = (string) ($_POST['belt'][$i] ?? '');
        $belt = $beltRaw === '' ? null : (int) $beltRaw;   // 0 = 10 kyu
        $phone = trim(mb_substr((string) ($_POST['phone'][$i] ?? ''), 0, 40));
        $photo = $_POST['photo'][$i] ?? '';
        $disc = array_values(array_intersect(array_keys($discs), explode(',', (string) ($_POST['disc'][$i] ?? ''))));
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
        if (!$disc) {
            $errors[] = t('{name}: pažymėkite bent vieną rungtį.', ['name' => $name]);
            continue;
        }
        $rows[] = [mb_substr($name, 0, 120), $birth, $gender, $belt !== null && ($belt === 0 || isset(BELTS[$belt])) ? $belt : null, $phone !== '' ? $phone : null, (int) $photo, implode(',', $disc)];
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
        foreach ($rows as [$name, $birth, $gender, $belt, $phone, $photo, $disc]) {
            q('INSERT INTO tournament_entries (tournament_id, club, contact, parent_phone, name, birth_year, gender, belt, disciplines, photo_ok, consent_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [$tid, $club, $contact, $phone, $name, $birth, $gender, $belt, $disc, $photo]);
            $_SESSION['reg_mine'][$tid][] = (int) db()->lastInsertId();
        }
        $_SESSION['reg_club'] = ['club' => $club, 'contact' => $contact];   // kitą kartą - jau įrašyta
        $_SESSION['reg_posts'][] = time();
        notify_admins('tournament_entries', 'Registracija į varžybas: ' . $club . ' (' . count($rows) . ' dalyv.)', $t['title'] . ' · ' . $contact,
            'admin/varzybu-sistema.php?id=' . (int) $t['id'] . '#dalyviai', null, (int) $t['id']);
        flash('ok', t('Užregistruota dalyvių: {n}. Organizatorius juos peržiūrės ir įtrauks į varžybas.', ['n' => count($rows)]));
        redirect('varzybos-registracija.php?k=' . $k . '#mano');
    }
}
$mine = $mineLoad();
$last = (array) ($_SESSION['reg_club'] ?? []);
$pc = function (string $key) use ($last): string { return is_post() ? (string) post($key) : (string) ($last[$key] ?? ''); };
$statusText = ['new' => t('Laukia patvirtinimo'), 'added' => t('Įtrauktas'), 'rejected' => t('Neįtrauktas - susisiekite')];

page_start(t('Registracija į varžybas') . ': ' . $t['title'], ['noindex' => true]);
$pv = function (string $key, int $i): string { return (string) ($_POST[$key][$i] ?? ''); };
?>
<div class="page-head">
  <div class="eyebrow"><?= te('Registracija klubams') ?></div>
  <h1 class="styled"><?= e($t['title']) ?></h1>
  <p><?= e(fmt_date($t['starts_on'], true)) ?><?= $t['ends_on'] ? ' – ' . e(fmt_date($t['ends_on'], true)) : '' ?><?= $t['location'] ? ' · ' . e($t['location']) : '' ?></p>
</div>
<?php if ($mine): ?>
  <div class="panel card reg-mine" id="mano">
    <div class="kicker"><?= te('Jūsų užregistruoti') ?> <span class="hint"><?= te('šiame įrenginyje') ?></span></div>
    <ul class="reg-mine-list">
      <?php foreach ($mine as $m): ?>
        <li><span class="rm-who"><b><?= e($m['name']) ?></b> <span class="muted small"><?= e($m['club']) ?> · <?= (int) $m['birth_year'] ?> · <?= e(implode(', ', array_map(function ($d) { return VARZ_REG_DISC[$d] ?? $d; }, array_filter(explode(',', (string) $m['disciplines']))))) ?></span></span>
          <span class="badge<?= $m['status'] === 'added' ? ' badge-ok' : ($m['status'] === 'rejected' ? ' badge-warn' : '') ?>"><?= e($statusText[$m['status']] ?? $m['status']) ?></span>
          <?php if ($m['status'] === 'new' && $open): ?><form method="post" class="inline" onsubmit="return confirm(this.dataset.q)" data-q="<?= te('Atšaukti šio dalyvio registraciją?') ?>"><?= csrf_field() ?><input type="hidden" name="k" value="<?= e($k) ?>"><button class="btn btn-ghost btn-xs" name="cancel" value="<?= (int) $m['id'] ?>"><?= te('Atšaukti') ?></button></form><?php endif; ?></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($open): ?><p class="small muted"><?= te('Galite pridėti dar dalyvių žemiau - klubas ir telefonas jau įrašyti.') ?></p><?php endif; ?>
  </div>
<?php endif; ?>
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
      <input type="text" name="club" list="regClubs" maxlength="120" required autocomplete="off" value="<?= e($pc('club')) ?>"></label>
    <label><?= te('Kontaktinis tel. Nr.') ?> <span class="hint"><?= te('treneris ar vienas iš tėvų') ?></span>
      <input type="tel" name="contact" maxlength="40" required autocomplete="tel" placeholder="+370 6…" value="<?= e($pc('contact')) ?>"></label>
  </div>
  <datalist id="regClubs"><?php foreach ($clubs as $c): ?><option value="<?= e($c) ?>"></option><?php endforeach; ?></datalist>

  <div class="kicker" style="margin-top:8px;"><?= te('Dalyviai') ?></div>
  <div class="reg-rows" id="regRows">
    <div class="reg-row reg-head"><span>#</span><span><?= te('Vardas ir pavardė') ?></span><span><?= te('Gimimo metai') ?></span><span><?= te('Lytis') ?></span><span><?= te('Diržas') ?></span>
      <span><?= te('Vieno iš tėvų tel. Nr.') ?></span><span><?= te('Nuotraukos, video') ?></span><span></span></div>
    <?php // po klaidos - tik užpildytos eilutės, ta pačia tvarka
    $idx = array_keys(array_filter((array) ($_POST['name'] ?? []), function ($v) { return is_string($v) && trim($v) !== ''; })) ?: [0];
    foreach ($idx as $k => $i): ?>
      <div class="reg-row">
        <span class="reg-num"><span class="reg-num-l"><?= te('Dalyvis') ?> </span><span class="reg-n"><?= $k + 1 ?></span></span>
        <input type="text" name="name[]" maxlength="120" placeholder="<?= te('Vardenis Pavardenis') ?>" aria-label="<?= te('Vardas ir pavardė') ?>" value="<?= e($pv('name', $i)) ?>">
        <input type="number" name="birth[]" min="<?= $year - 90 ?>" max="<?= $year - 3 ?>" placeholder="<?= te('metai') ?>" aria-label="<?= te('Gimimo metai') ?>" value="<?= e($pv('birth', $i)) ?>">
        <select name="gender[]" aria-label="<?= te('Lytis') ?>"><option value=""><?= te('lytis') ?></option><option value="M"<?= $pv('gender', $i) === 'M' ? ' selected' : '' ?>>M</option><option value="F"<?= $pv('gender', $i) === 'F' ? ' selected' : '' ?>>F</option></select>
        <select name="belt[]" aria-label="<?= te('Diržas') ?>"><option value=""><?= te('diržas') ?></option><option value="0"<?= $pv('belt', $i) === '0' ? ' selected' : '' ?>>10 kyu</option><?php foreach (BELTS as $lvl => $b): ?><option value="<?= (int) $lvl ?>"<?= (int) $pv('belt', $i) === (int) $lvl ? ' selected' : '' ?>><?= e($b[0]) ?></option><?php endforeach; ?></select>
        <input type="tel" name="phone[]" maxlength="40" placeholder="<?= te('tėvų tel. Nr.') ?>" aria-label="<?= te('Vieno iš tėvų tel. Nr.') ?>" value="<?= e($pv('phone', $i)) ?>">
        <select name="photo[]" aria-label="<?= te('Sutinka būti fotografuojamas ir filmuojamas') ?>"><option value=""><?= te('nuotraukos?') ?></option>
          <option value="1"<?= $pv('photo', $i) === '1' ? ' selected' : '' ?>><?= te('Sutinka') ?></option><option value="0"<?= $pv('photo', $i) === '0' ? ' selected' : '' ?>><?= te('Nesutinka') ?></option></select>
        <button type="button" class="reg-del" title="<?= te('Pašalinti') ?>" aria-label="<?= te('Pašalinti') ?>">×</button>
        <?php $dv = is_post() ? array_filter(explode(',', $pv('disc', $i))) : (count($discs) === 1 ? array_keys($discs) : []); ?>
        <div class="reg-disc"><span class="reg-disc-l"><?= te('Rungtys:') ?></span>
          <?php foreach ($discs as $d => $dn): ?><label class="check"><input type="checkbox" data-disc="<?= e($d) ?>"<?= in_array($d, $dv, true) ? ' checked' : '' ?>><span><?= e($dn) ?></span></label><?php endforeach; ?>
          <input type="hidden" name="disc[]" value="<?= e(implode(',', $dv)) ?>"></div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="row" style="gap:10px;">
    <button type="button" class="btn btn-ghost btn-sm" id="regMore"><?= te('+ Pridėti dar vieną dalyvį') ?></button>
    <span class="hint"><?= te('Vaikams iki 18 m. - būtinas vieno iš tėvų telefonas.') ?></span>
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
  // Vienas dalyvis iš pradžių; „+ Pridėti dar vieną“ - nauja tuščia eilutė, × - pašalina (paskutinės - ne)
  var rows = document.getElementById('regRows');
  function renumber() {
    var list = rows.querySelectorAll('.reg-row:not(.reg-head)');
    list.forEach(function (r, i) { r.querySelector('.reg-n').textContent = i + 1; });
    rows.classList.toggle('single', list.length < 2);
  }
  document.getElementById('regMore').addEventListener('click', function () {
    var c = rows.querySelector('.reg-row:not(.reg-head)').cloneNode(true);
    c.querySelectorAll('input:not([type=checkbox]), select').forEach(function (x) { x.value = ''; });
    c.querySelectorAll('[data-disc]').forEach(function (x) { x.checked = false; });
    if (c.querySelectorAll('[data-disc]').length === 1) { c.querySelector('[data-disc]').checked = true; syncDisc(c); }
    rows.appendChild(c);
    renumber();
    c.querySelector('input').focus();
  });
  // Rungtys: pažymėti langeliai → paslėptas laukas „kata,kumite“ (eilutės kopijuojamos, todėl vienas laukas eilutei)
  function syncDisc(r) {
    r.querySelector('input[name="disc[]"]').value = [].map.call(r.querySelectorAll('[data-disc]:checked'), function (x) { return x.dataset.disc; }).join(',');
  }
  rows.addEventListener('change', function (e) { if (e.target.dataset.disc) syncDisc(e.target.closest('.reg-row')); });
  rows.addEventListener('click', function (e) {
    var b = e.target.closest('.reg-del');
    if (b && rows.querySelectorAll('.reg-row:not(.reg-head)').length > 1) { b.closest('.reg-row').remove(); renumber(); }
  });
  renumber();
})();
</script>
<?php endif; ?>
<?php
echo tl_script();   // Taikos tekstai EN / RU (assets/tl.js)
page_end();
