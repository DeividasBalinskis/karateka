<?php
// Pateisinimų sistema (v1.93): prašymas pateisinti praleistas pamokas - tik pagal mūsų atsiųstą nuorodą (karateka.lt/p/?kodas).
// Be svetainės meniu: šeši laukai, pateikus - administratoriai gauna laišką; pateisinimas siunčiamas, kai administratorius patvirtina.
require __DIR__ . '/app/bootstrap.php';

$k = (string) get('k');
if (!just_ready() || !exam_code_ok($k) || just_token() === '' || !hash_equals(just_token(), $k)) {
    not_found();
}
$open = just_is_open();
$errors = [];
$done = null;
$v = ['student' => '', 'school' => '', 'director' => '', 'class_name' => '', 'date_from' => '', 'date_to' => '', 'email' => ''];
$_SESSION['just_posts'] = array_values(array_filter((array) ($_SESSION['just_posts'] ?? []), function ($t) { return $t > time() - 3600; }));

if (is_post() && $open) {
    csrf_check();
    foreach ($v as $f => $_) {
        $v[$f] = trim(preg_replace('/\s+/u', ' ', (string) post($f)));
    }
    if (post('website') !== '' || count($_SESSION['just_posts']) >= 10) {
        $errors[] = t('Per daug bandymų. Pabandykite vėliau.');
    }
    if ($v['student'] === '' || $v['school'] === '' || $v['director'] === '' || $v['class_name'] === '') {
        $errors[] = t('Užpildykite visus laukus.');
    }
    $isDate = function ($d) { return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false; };
    if (!$isDate($v['date_from']) || !$isDate($v['date_to']) || $v['date_to'] < $v['date_from']) {
        $errors[] = t('Nurodykite laikotarpį: nuo ir iki (iki - ne anksčiau nei nuo).');
    }
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = t('Įrašykite el. pašto adresą, kuriuo atsiųsti pateisinimą.');
    }
    if (!$errors) {
        $_SESSION['just_posts'][] = time();
        just_add($v);
        $done = $v;
    }
}

page_start(t('Pamokų pateisinimas'), ['noindex' => true, 'bare' => true]);
?>
<div class="exam-su just-form">
  <header class="panel card">
    <div class="kicker"><?= te('Pateisinimų sistema') ?></div>
    <h1><?= te('Prašymas pateisinti praleistas pamokas') ?></h1>
    <p class="muted"><?= te('Užpildykite formą. Kai patvirtinsime, pateisinimą atsiųsime nurodytu el. paštu.') ?></p>
  </header>
  <?php if ($done): ?>
    <div class="panel card exam-su-ok" role="status">
      <h2>✓ <?= te('Prašymas gautas') ?></h2>
      <p><?= te('Kai patvirtinsime, pateisinimą atsiųsime adresu {e}.', ['e' => $done['email']]) ?></p>
      <p><a class="btn btn-ghost btn-sm" href="<?= e(just_url()) ?>"><?= te('Pateikti dar vieną prašymą') ?></a></p>
    </div>
  <?php elseif (!$open): ?>
    <div class="panel card"><h2><?= te('Prašymai nepriimami') ?></h2><p class="muted"><?= te('Šiuo metu pateisinimų prašymai nepriimami.') ?></p></div>
  <?php else: ?>
    <form method="post" class="panel card form exam-su-form" novalidate>
      <?= csrf_field() ?>
      <?php if ($errors): ?><div class="flash flash-err" role="alert"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
      <label><?= te('Mokinio vardas ir pavardė') ?> *<input type="text" name="student" value="<?= e($v['student']) ?>" required maxlength="160" autocomplete="off"></label>
      <div class="form-row">
        <label><?= te('Mokyklos pavadinimas') ?> *<input type="text" name="school" value="<?= e($v['school']) ?>" required maxlength="190"></label>
        <label><?= te('Klasė') ?> *<input type="text" name="class_name" value="<?= e($v['class_name']) ?>" required maxlength="30" placeholder="<?= e(t('pvz. 5b')) ?>"></label>
      </div>
      <label><?= te('Mokyklos direktoriaus vardas ir pavardė') ?> *<input type="text" name="director" value="<?= e($v['director']) ?>" required maxlength="160"></label>
      <fieldset class="just-period">
        <legend><?= te('Laikotarpis, už kurį prašoma pateisinti praleistas pamokas') ?> *</legend>
        <div class="form-row">
          <label><?= te('Nuo') ?><input type="date" name="date_from" value="<?= e($v['date_from']) ?>" required></label>
          <label><?= te('Iki') ?><input type="date" name="date_to" value="<?= e($v['date_to']) ?>" required></label>
        </div>
      </fieldset>
      <label><?= te('El. paštas, kuriuo atsiųsti pateisinimą') ?> *<input type="email" name="email" value="<?= e($v['email']) ?>" required maxlength="190" autocomplete="email"></label>
      <div style="position:absolute; left:-9999px;" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
      <button class="btn btn-primary" type="submit"><?= te('Pateikti prašymą') ?></button>
      <p class="small muted" style="margin:0;"><?= te('Pateikdami sutinkate, kad šie duomenys būtų naudojami pateisinimui parengti ir išsiųsti.') ?> <a href="<?= url('privatumas.php?be_meniu=1') ?>" target="_blank" rel="noopener"><?= te('Privatumo politika') ?></a></p>
    </form>
  <?php endif; ?>
</div>
<script>
// „Iki“ - ne anksčiau nei „Nuo“
(function () { var f = document.querySelector('[name=date_from]'), t = document.querySelector('[name=date_to]'); if (!f || !t) return;
  f.addEventListener('change', function () { t.min = f.value; if (!t.value || t.value < f.value) t.value = f.value; }); })();
</script>
<?php
page_end();
