<?php
// Registracija į egzaminą per nuorodą (?k=): be paskyros - vardas, pavardė, gimimo data, kontaktinis telefonas. Ir viskas.
// Užsiregistravęs iškart atsiranda egzamino protokole ir gauna asmeninę nuorodą į savo egzamino puslapį (mano-egzaminas.php):
// programa, testas, kaina, rezultatas. Nuorodą pamiršus - „Jau užsiregistravai?“: vardas pavardė + gimimo data.
require __DIR__ . '/app/bootstrap.php';

$link = exam_signup_ready() && preg_match('/^[a-f0-9]{24}$/', (string) get('k')) ? q_one('SELECT * FROM exam_signup_links WHERE token = ?', [(string) get('k')]) : null;
$event = $link ? q_one('SELECT * FROM events WHERE id = ? AND type = "exam"', [(int) $link['event_id']]) : null;
if (!$event) {
    not_found();
}
$open = exam_signup_open($event, $link) && reg_on('exam');
$errors = [];
$findErr = '';
$done = null;
$v = ['first_name' => '', 'last_name' => '', 'birth_date' => '', 'phone' => ''];
$_SESSION['exam_su_posts'] = array_values(array_filter((array) ($_SESSION['exam_su_posts'] ?? []), function ($t) { return $t > time() - 3600; }));

if (is_post()) {
    csrf_check();
    $tooMany = post('website') !== '' || count($_SESSION['exam_su_posts']) >= 15;
    if (post('action') === 'find') {
        // Jau užsiregistravęs: vardas pavardė + gimimo data → asmeninis puslapis.
        // A3: ne daugiau 10 nesėkmių per valandą iš vieno IP (duomenų bazėje); po egzamino paieška išjungiama
        $_SESSION['exam_su_posts'][] = time();
        $tooMany = $tooMany || exam_find_throttled();
        $row = $tooMany || !exam_find_open($event) ? null : exam_row_find((int) $event['id'], (string) post('name'), (string) post('birth_date'));
        $tok = $row ? exam_row_token($row) : null;
        if (!$tooMany && exam_find_open($event)) {
            exam_find_log((bool) $tok);
        }
        if ($tok) {
            redirect('mano-egzaminas.php?t=' . $tok);
        }
        $findErr = $tooMany ? t('Per daug bandymų. Pabandykite vėliau.') : (!exam_find_open($event) ? t('Egzaminas jau įvyko - savo rezultatą sužinokite iš trenerio.')
            : t('Neradome. Patikrinkite vardą, pavardę ir gimimo datą arba užsiregistruokite.'));
    } elseif ($open) {
        foreach ($v as $k => $_) {
            $v[$k] = trim((string) post($k));
        }
        if ($tooMany) {
            $errors[] = t('Per daug bandymų. Pabandykite vėliau.');
        }
        if ($v['first_name'] === '' || $v['last_name'] === '') {
            $errors[] = t('Įrašykite vardą ir pavardę.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v['birth_date']) || $v['birth_date'] > date('Y-m-d') || $v['birth_date'] < '1920-01-01') {
            $errors[] = t('Įrašykite gimimo datą.');
        }
        if (strlen(preg_replace('/\D/', '', $v['phone'])) < 8) {
            $errors[] = t('Įrašykite kontaktinį telefono numerį.');
        }
        if (!$errors) {
            $_SESSION['exam_su_posts'][] = time();
            $res = exam_signup_save($event, $v);
            if (!empty($res['already'])) {
                $done = $v + ['url' => null, 'already' => true];   // A3: kito žmogaus nuorodos nerodome
            } else {
                $row = q_one('SELECT * FROM exam_protocol_rows WHERE id = ?', [$res['row_id']]);
                $done = $v + ['url' => $row ? exam_row_url($row) : null];
            }
        }
    }
}

$when = fmt_date($event['starts_on'], true) . ($event['ends_on'] && $event['ends_on'] !== $event['starts_on'] ? ' – ' . fmt_date($event['ends_on'], true) : '')
    . ($event['start_time'] ? ', ' . fmt_time($event['start_time']) : '');
page_start(t('Registracija į egzaminą') . ': ' . tr_text($event['title']), ['noindex' => true]);
?>
<div class="exam-su">
  <header class="panel card">
    <div class="kicker"><?= te('Registracija į egzaminą') ?></div>
    <h1><?= e(tr_text($event['title'])) ?></h1>
    <p class="muted"><?= e($when) ?><?= $event['location'] ? ' · 📍 ' . e($event['location']) : '' ?></p>
    <?php if ($event['summary']): ?><p><?= e(tr_text($event['summary'])) ?></p><?php endif; ?>
    <?php if ($event['description']): ?><details><summary><?= te('Plačiau') ?></summary><div class="news-body"><?= text_to_html(tr_text($event['description'])) ?></div></details><?php endif; ?>
  </header>

  <?php if ($done && !empty($done['already'])): ?>
    <div class="panel card exam-su-ok" role="status">
      <h2>✓ <?= te('Jūs jau užregistruotas (-a)') ?></h2>
      <p><?= te('{n} jau yra šio egzamino sąraše - dar kartą registruotis nereikia. Treneriui pranešėme.', ['n' => $done['first_name']]) ?></p>
      <p class="small muted"><?= te('Savo egzamino puslapio nuorodą rasite registracijos metu gautoje žinutėje arba „Jau užsiregistravai?“ žemiau. Jei nepavyksta - parašykite treneriui.') ?></p>
      <p><a class="btn btn-ghost btn-sm" href="<?= e(url('egzamino-registracija.php?k=' . $link['token'])) ?>"><?= te('Registruoti kitą žmogų') ?></a></p>
    </div>
  <?php elseif ($done): ?>
    <div class="panel card exam-su-ok" role="status">
      <h2>✓ <?= te('Užregistruota!') ?></h2>
      <p><?= te('{n}, tu užregistruotas (-a) į egzaminą.', ['n' => $done['first_name']]) ?></p>
      <?php if ($done['url']): ?>
        <div class="exam-su-mine">
          <b><?= te('Tavo egzamino puslapis') ?></b>
          <span class="small muted"><?= te('Čia - programa, teorijos testas, kaina ir rezultatas. Išsaugok nuorodą (paskyros nereikia).') ?></span>
          <div class="row" style="gap:6px; flex-wrap:wrap;">
            <input type="text" readonly value="<?= e($done['url']) ?>" class="su-url" aria-label="<?= te('Tavo egzamino puslapis') ?>" onclick="this.select()">
            <button type="button" class="btn btn-sm" data-copy="<?= e($done['url']) ?>"><?= te('Kopijuoti') ?></button>
          </div>
          <a class="btn btn-primary" href="<?= e($done['url']) ?>"><?= te('Atidaryti mano egzaminą →') ?></a>
        </div>
      <?php endif; ?>
      <p class="small muted"><?= te('Pamiršai nuorodą? Šiame puslapyje įrašyk vardą, pavardę ir gimimo datą - „Jau užsiregistravai?“.') ?></p>
      <p><a class="btn btn-ghost btn-sm" href="<?= e(url('egzamino-registracija.php?k=' . $link['token'])) ?>"><?= te('Registruoti kitą žmogų') ?></a></p>
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
      <div class="form-row">
        <label><?= te('Gimimo data') ?> *<input type="date" name="birth_date" value="<?= e($v['birth_date']) ?>" required max="<?= date('Y-m-d') ?>"></label>
        <label><?= te('Kontaktinis telefonas') ?> *<input type="tel" name="phone" value="<?= e($v['phone']) ?>" required maxlength="40" autocomplete="tel" placeholder="+370 6xx xxxxx"></label>
      </div>
      <div style="position:absolute; left:-9999px;" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
      <button class="btn btn-primary" type="submit"><?= te('Registruotis į egzaminą') ?></button>
      <p class="small muted" style="margin:0;"><?= te('Registruodamiesi sutinkate, kad šie duomenys būtų naudojami egzamino protokolui ir susisiekti dėl egzamino.') ?> <a href="<?= url('privatumas.php') ?>" target="_blank"><?= te('Privatumo politika') ?></a></p>
    </form>
  <?php endif; ?>

  <?php if ((!$done || !empty($done['already'])) && exam_find_open($event)): ?>
    <details class="panel card exam-su-find" id="rasti"<?= $findErr !== '' || !$open ? ' open' : '' ?>>
      <summary><b><?= te('Jau užsiregistravai? Rask savo egzaminą') ?></b></summary>
      <form method="post" class="form" style="margin-top:10px;">
        <?= csrf_field() ?><input type="hidden" name="action" value="find">
        <?php if ($findErr !== ''): ?><div class="flash flash-err" role="alert"><?= e($findErr) ?></div><?php endif; ?>
        <div class="form-row">
          <label><?= te('Vardas Pavardė') ?><input type="text" name="name" value="<?= e((string) post('name')) ?>" required maxlength="160" autocomplete="name"></label>
          <label><?= te('Gimimo data') ?><input type="date" name="birth_date" value="<?= e((string) post('birth_date')) ?>" required></label>
        </div>
        <div style="position:absolute; left:-9999px;" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
        <button class="btn btn-ghost" type="submit"><?= te('Rasti mano egzaminą') ?></button>
      </form>
    </details>
  <?php endif; ?>
</div>
<script>
document.querySelectorAll('[data-copy]').forEach(function (b) {
  b.addEventListener('click', function () {
    var done = function () { var o = b.textContent; b.textContent = '✓'; setTimeout(function () { b.textContent = o; }, 1500); };
    if (navigator.clipboard) navigator.clipboard.writeText(b.dataset.copy).then(done, function () {}); else { b.previousElementSibling.select(); document.execCommand('copy'); done(); }
  });
});
</script>
<?php
page_end();
