<?php
// Registracija į egzaminą per nuorodą (?k=): be paskyros - vardas, pavardė, el. paštas (+ gimimo data, klubas, laikomas kyu, telefonas).
// Užsiregistravęs iškart atsiranda egzamino protokole; treneriai gauna pranešimą. Nuorodą treneris kuria protokolo puslapyje.
require __DIR__ . '/app/bootstrap.php';

$link = exam_signup_ready() && preg_match('/^[a-f0-9]{24}$/', (string) get('k')) ? q_one('SELECT * FROM exam_signup_links WHERE token = ?', [(string) get('k')]) : null;
$event = $link ? q_one('SELECT * FROM events WHERE id = ? AND type = "exam"', [(int) $link['event_id']]) : null;
if (!$event) {
    not_found();
}
$open = exam_signup_open($event, $link);
$errors = [];
$done = null;
$v = ['first_name' => '', 'last_name' => '', 'email' => '', 'birth_date' => '', 'club' => '', 'kyu' => '', 'phone' => ''];
$_SESSION['exam_su_posts'] = array_values(array_filter((array) ($_SESSION['exam_su_posts'] ?? []), function ($t) { return $t > time() - 3600; }));

if (is_post() && $open) {
    csrf_check();
    foreach ($v as $k => $_) {
        $v[$k] = trim((string) post($k));
    }
    if (post('website') !== '' || count($_SESSION['exam_su_posts']) >= 15) {
        $errors[] = t('Per daug bandymų. Pabandykite vėliau.');
    }
    if ($v['first_name'] === '' || $v['last_name'] === '') {
        $errors[] = t('Įrašykite vardą ir pavardę.');
    }
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = t('Patikrinkite el. pašto adresą.');
    }
    if ($v['birth_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v['birth_date'])) {
        $v['birth_date'] = '';
    }
    if (empty($_POST['consent'])) {
        $errors[] = t('Pažymėkite sutikimą dėl duomenų naudojimo.');
    }
    if (!$errors) {
        $_SESSION['exam_su_posts'][] = time();
        exam_signup_save($event, $v);
        $done = $v;
        $when = fmt_date($event['starts_on'], true) . ($event['start_time'] ? ', ' . fmt_time($event['start_time']) : '') . ($event['location'] ? ' · ' . $event['location'] : '');
        send_mail($v['email'], t('Užregistruota į egzaminą') . ': ' . $event['title'],
            t('Sveiki, {n}!', ['n' => $v['first_name']]) . "\n\n" . t('Jūs užregistruoti į egzaminą') . ': ' . $event['title'] . "\n" . $when . "\n\n"
            . t('Jei norite pakeisti duomenis - užpildykite formą dar kartą tuo pačiu el. paštu:') . "\n" . exam_signup_url($link) . "\n\nKarateka");
    }
}

$when = fmt_date($event['starts_on'], true) . ($event['ends_on'] && $event['ends_on'] !== $event['starts_on'] ? ' – ' . fmt_date($event['ends_on'], true) : '')
    . ($event['start_time'] ? ', ' . fmt_time($event['start_time']) : '');
page_start(t('Registracija į egzaminą') . ': ' . tr_text($event['title']));
?>
<div class="exam-su">
  <header class="panel card">
    <div class="kicker"><?= te('Registracija į egzaminą') ?></div>
    <h1><?= e(tr_text($event['title'])) ?></h1>
    <p class="muted"><?= e($when) ?><?= $event['location'] ? ' · 📍 ' . e($event['location']) : '' ?></p>
    <?php if ($event['summary']): ?><p><?= e(tr_text($event['summary'])) ?></p><?php endif; ?>
    <?php if ($event['description']): ?><details><summary><?= te('Plačiau') ?></summary><div class="news-body"><?= text_to_html(tr_text($event['description'])) ?></div></details><?php endif; ?>
  </header>

  <?php if ($done): ?>
    <div class="panel card exam-su-ok" role="status">
      <h2>✓ <?= te('Užregistruota!') ?></h2>
      <p><?= str_replace('{e}', '<b>' . e($done['email']) . '</b>', te('{n}, jūs užregistruoti į egzaminą. Patvirtinimą išsiuntėme el. paštu {e}.', ['n' => $done['first_name'], 'e' => '{e}'])) ?></p>
      <p class="small muted"><?= te('Norėdami pakeisti duomenis, užpildykite formą dar kartą tuo pačiu el. paštu.') ?></p>
      <p><a class="btn btn-ghost" href="<?= e(url('egzamino-registracija.php?k=' . $link['token'])) ?>"><?= te('Registruoti kitą žmogų') ?></a></p>
    </div>
  <?php elseif (!$open): ?>
    <div class="panel card"><h2><?= te('Registracija uždaryta') ?></h2><p class="muted"><?= te('Registracija į šį egzaminą nebevyksta. Jei turite klausimų - parašykite info@karateka.lt.') ?></p></div>
  <?php else: ?>
    <form method="post" class="panel card form exam-su-form" novalidate>
      <?= csrf_field() ?>
      <p class="exam-su-what">📝 <b><?= e(tr_text($event['title'])) ?></b> · <?= e($when) ?></p>
      <?php if ($errors): ?><div class="flash flash-err" role="alert"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
      <div class="form-row">
        <label><?= te('Vardas') ?> *<input type="text" name="first_name" value="<?= e($v['first_name']) ?>" required maxlength="80" autocomplete="given-name"></label>
        <label><?= te('Pavardė') ?> *<input type="text" name="last_name" value="<?= e($v['last_name']) ?>" required maxlength="80" autocomplete="family-name"></label>
      </div>
      <label><?= te('El. paštas') ?> *<input type="email" name="email" value="<?= e($v['email']) ?>" required maxlength="190" autocomplete="email"></label>
      <details class="exam-su-more"<?= $v['birth_date'] . $v['club'] . $v['kyu'] . $v['phone'] !== '' ? ' open' : '' ?>>
        <summary><?= te('Daugiau (nebūtina): gimimo data, klubas, laikomas kyu, telefonas') ?></summary>
        <div class="form-row">
          <label><?= te('Gimimo data') ?><input type="date" name="birth_date" value="<?= e($v['birth_date']) ?>"></label>
          <label><?= te('Klubas') ?><input type="text" name="club" value="<?= e($v['club']) ?>" maxlength="120" autocomplete="organization"></label>
        </div>
        <div class="form-row">
          <label><?= te('Laikomas kyu') ?>
            <select name="kyu"><option value="">—</option>
              <?php foreach (BELTS as $b): ?><option<?= $v['kyu'] === $b[0] ? ' selected' : '' ?>><?= e($b[0]) ?></option><?php endforeach; ?>
            </select></label>
          <label><?= te('Telefonas') ?><input type="tel" name="phone" value="<?= e($v['phone']) ?>" maxlength="40" autocomplete="tel"></label>
        </div>
      </details>
      <div style="position:absolute; left:-9999px;" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
      <label class="check"><input type="checkbox" name="consent" value="1"<?= !empty($_POST['consent']) ? ' checked' : '' ?>>
        <span><?= te('Sutinku, kad mano duomenys būtų naudojami egzamino protokolui ir susisiekti dėl egzamino.') ?> <a href="<?= url('privatumas.php') ?>" target="_blank"><?= te('Privatumo politika') ?></a></span></label>
      <button class="btn btn-primary" type="submit"><?= te('Registruotis į egzaminą') ?></button>
    </form>
  <?php endif; ?>
</div>
<?php
page_end();
