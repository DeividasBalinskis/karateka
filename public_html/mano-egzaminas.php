<?php
// Mano egzaminas be paskyros (?t= asmeninis raktas): kiekvienas egzamino protokolo dalyvis (užsiregistravęs per nuorodą ar įrašytas trenerio)
// mato savo egzaminą - kada ir kur, kokį diržą laikys, programą, teorijos testą, kainą ir mokėjimą, po egzamino - rezultatą ir sertifikatą.
// Nuoroda rodoma užsiregistravus, ją gali nukopijuoti treneris (protokole), o pamiršus - „Jau užsiregistravai?“ registracijos puslapyje.
require __DIR__ . '/app/bootstrap.php';

$tok = (string) (get('t') ?: post('t'));
$row = exam_row_by_token($tok);
$event = $row ? q_one('SELECT * FROM events WHERE id = ? AND type = "exam"', [(int) $row['event_id']]) : null;
if (!$event) {
    not_found();
}
$self = 'mano-egzaminas.php?t=' . $tok;
$past = event_last_day($event) < date('Y-m-d');
// Asmeninė nuoroda galioja 30 dienų po egzamino (sprendimas 2026-11-20): vėliau - tik paaiškinimas, be asmens duomenų
if (event_last_day($event) < date('Y-m-d', strtotime('-30 days'))) {
    http_response_code(410);
    page_start(t('Mano egzaminas'), ['noindex' => true, 'bare' => !current_account()]);
    echo '<div class="panel card" style="max-width:560px; margin:40px auto;"><h1 class="styled">' . te('Nuoroda nebegalioja') . '</h1><p>'
        . te('Asmeninė egzamino nuoroda galioja 30 dienų po egzamino. Rezultatą ir sertifikatą rasite paskyroje arba paklauskite trenerio.') . '</p></div>';
    page_end();
    exit;
}

// Kokį diržą laikys - pasirenka pats (kol egzaminas neįvyko ir treneris nepažymėjo rezultato)
if (is_post() && post('action') === 'belt' && !$past && !$row['result']) {
    csrf_check();
    $lvl = (int) post('belt');
    if (isset(BELTS[$lvl]) && $lvl > 1) {
        q('UPDATE exam_protocol_rows SET kyu = ? WHERE id = ?', [BELTS[$lvl][0], (int) $row['id']]);
        q('UPDATE exam_protocols SET updated_at = NOW() WHERE event_id = ?', [(int) $event['id']]);
    }
    redirect($self . '#programa');
}

$lvl = exam_kyu_level($row['kyu']);
$showLvl = isset(BELTS[(int) get('dirzas')]) ? (int) get('dirzas') : $lvl;
$reqs = $showLvl ? belt_requirements($showLvl) : [];
$quiz = $lvl ? q_one("SELECT * FROM quizzes WHERE is_published = 1 AND audience = 'karateka' AND (belt_level = ? OR belt_level IS NULL) ORDER BY belt_level IS NULL LIMIT 1", [$lvl]) : null;
$price = exam_price_for($row['kyu']);
$payInfo = exam_pay_info();
$suLink = exam_signup_link((int) $event['id']);
$when = fmt_date($event['starts_on'], true) . ($event['ends_on'] && $event['ends_on'] !== $event['starts_on'] ? ' – ' . fmt_date($event['ends_on'], true) : '')
    . ($event['start_time'] ? ', ' . fmt_time($event['start_time']) : '');
$days = (int) floor((strtotime($event['starts_on']) - strtotime(date('Y-m-d'))) / 86400);
$first = explode(' ', trim((string) $row['name']))[0];

page_start(t('Mano egzaminas') . ': ' . tr_text($event['title']), ['noindex' => true, 'bare' => !current_account()]);   // be paskyros - be svetainės meniu
?>
<div class="my-exam">
  <header class="panel card my-exam-hero">
    <div class="kicker"><?= te('Mano egzaminas') ?></div>
    <h1><?= te('Labas, {n}!', ['n' => $first]) ?></h1>
    <p class="my-exam-what"><b><?= e(tr_text($event['title'])) ?></b></p>
    <p class="muted">📅 <?= e($when) ?><?= $event['location'] ? ' · 📍 ' . e($event['location']) : '' ?></p>
    <?php if (!$past && $days >= 0): ?>
      <div class="my-exam-count"><?= $days === 0 ? te('Egzaminas šiandien! Sėkmės! 🥋') : te('Iki egzamino liko {n} d.', ['n' => $days]) ?></div>
    <?php endif; ?>
    <p class="small muted" style="margin:6px 0 0;"><?= te('Tavo numeris protokole') ?>: <b><?= e($row['number']) ?></b></p>
  </header>

  <?php if ($row['result']): ?>
    <section class="panel card my-exam-res <?= $row['result'] === 'passed' ? 'ok' : 'no' ?>">
      <h2><?= $row['result'] === 'passed' ? te('🎉 Egzaminas išlaikytas!') : te('Egzaminas šįkart neišlaikytas') ?></h2>
      <p class="small muted"><?= te('Sertifikatas atsiras čia, kai bus paruoštas.') ?> <span class="need-info"><?= te('ruošiama') ?></span></p>
    </section>
  <?php endif; ?>

  <ol class="exam-steps my-exam-steps">
    <li class="ok">
      <strong><?= te('Registracija') ?></strong>
      <span class="small muted"><?= e((string) $row['name']) ?><?= $row['birth_date'] ? ' · ' . e(substr((string) $row['birth_date'], 0, 4)) . ' ' . te('m.') : '' ?></span>
    </li>
    <li id="programa" class="<?= $lvl ? 'ok' : '' ?>">
      <strong><?= te('Kokį diržą laikysi?') ?></strong>
      <?php if (!$past && !$row['result']): ?>
        <form method="post" class="row" style="gap:6px; flex-wrap:wrap;">
          <?= csrf_field() ?><input type="hidden" name="t" value="<?= e($tok) ?>"><input type="hidden" name="action" value="belt">
          <select name="belt" onchange="this.form.submit()" aria-label="<?= te('Laikomas diržas') ?>">
            <option value=""><?= te('— pasirink —') ?></option>
            <?php foreach (BELTS as $l => [$kyu, $name]): if ($l < 2 || $l > 10) { continue; } ?>
              <option value="<?= $l ?>"<?= $l === $lvl ? ' selected' : '' ?>><?= e($kyu . ' (' . t($name) . ')') ?></option>
            <?php endforeach; ?>
          </select>
          <noscript><button class="btn btn-sm" type="submit"><?= te('Išsaugoti') ?></button></noscript>
        </form>
        <span class="small muted"><?= te('Nežinai? Paklausk trenerio - jis gali pakeisti.') ?></span>
      <?php else: ?>
        <span><?= $lvl ? belt_chip($lvl) : e((string) $row['kyu']) ?></span>
      <?php endif; ?>
    </li>
    <li class="<?= $reqs ? '' : 'off' ?>">
      <strong><?= te('Peržiūrėk programą') ?></strong>
      <span class="small muted"><?= $reqs ? te('Žemiau - ką reikia mokėti šiam diržui.') : ($showLvl ? te('Šio diržo programos treneris dar neįkėlė.') : te('Pirma pasirink diržą.')) ?></span>
    </li>
    <li class="<?= $row['test'] && mb_strpos((string) $row['test'], '✓') !== false ? 'ok' : '' ?>">
      <strong><?= te('Teorijos testas') ?></strong>
      <?php if ($quiz): ?>
        <span class="small muted"><?= te('Reikia surinkti bent') ?> <?= (int) $quiz['pass_percent'] ?> %<?= $row['test'] ? ' · ' . te('tavo rezultatas') . ': <b>' . e((string) $row['test']) . '</b>' : '' ?></span>
        <?php if (!$past): ?><a class="btn btn-primary btn-sm" href="<?= e(url('testas-svecias.php?k=' . quiz_share_token($quiz) . '&e=' . $tok)) ?>"><?= $row['test'] ? te('Spręsti dar kartą') : te('Laikyti testą') ?></a><?php endif; ?>
      <?php else: ?>
        <span class="small muted"><?= $lvl ? te('Šiam diržui testo nėra.') : te('Pirma pasirink diržą.') ?></span>
      <?php endif; ?>
    </li>
    <li class="<?= !empty($row['paid_at']) ? 'ok' : '' ?>">
      <strong><?= te('Egzamino mokestis') ?></strong>
      <?php if (!empty($row['paid_at'])): ?>
        <span class="small"><?= te('✓ Sumokėta') ?><?= $price ? ' · ' . e(money_fmt($price)) : '' ?></span>
      <?php else: ?>
        <span class="small muted"><?= $price ? te('Kaina') . ': <b>' . e(money_fmt($price)) . '</b>' : te('Kainą pasakys treneris.') ?></span>
        <?php if ($payInfo !== ''): ?><div class="small my-exam-pay"><?= nl2br(e($payInfo)) ?></div><?php endif; ?>
        <button class="btn btn-ghost btn-sm" type="button" disabled title="<?= te('Ruošiama') ?>"><?= te('💳 Apmokėti internetu') ?></button> <span class="need-info"><?= te('ruošiama') ?></span>
      <?php endif; ?>
    </li>
    <li class="<?= $row['result'] ? 'ok' : '' ?>">
      <strong><?= te('Egzaminas ir rezultatas') ?></strong>
      <span class="small muted"><?= $row['result'] ? ($row['result'] === 'passed' ? te('Išlaikė') : te('Neišlaikė')) : te('Rezultatą matysi čia po egzamino.') ?></span>
    </li>
  </ol>

  <?php if ($showLvl): ?>
    <section class="panel card program-card">
      <div class="row between" style="gap:10px; flex-wrap:wrap;">
        <div><div class="kicker"><?= te('Egzamino programa') ?></div><h2 style="margin:2px 0 0;"><?= belt_chip($showLvl) ?></h2></div>
        <form method="get" class="row" style="gap:6px;"><input type="hidden" name="t" value="<?= e($tok) ?>">
          <select name="dirzas" onchange="this.form.submit()" aria-label="<?= te('Kito diržo programa') ?>" class="small-select">
            <?php foreach (BELTS as $l => [$kyu, $name]): if ($l < 2 || $l > 10) { continue; } ?><option value="<?= $l ?>"<?= $l === $showLvl ? ' selected' : '' ?>><?= e($kyu) ?></option><?php endforeach; ?>
          </select></form>
      </div>
      <?php if (!$reqs): ?><p class="muted" style="margin-top:14px;"><?= te('Šio diržo programos treneris dar neįkėlė.') ?></p><?php endif; ?>
      <?php foreach ($reqs as $section => $items): ?>
        <h3 class="req-section"><?= e(t(REQ_SECTIONS[$section])) ?></h3>
        <ul class="req-list">
          <?php foreach ($items as $r): ?>
            <li class="req"><div class="req-body"><div class="req-title"><strong><?= e($r['title']) ?></strong></div>
              <?php if ($r['description']): ?><div class="small muted"><?= nl2br(e($r['description'])) ?></div><?php endif; ?></div></li>
          <?php endforeach; ?>
        </ul>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <section class="panel card my-exam-save">
    <b><?= te('🔖 Išsaugok šį puslapį') ?></b>
    <span class="small muted"><?= te('Tai tavo asmeninė nuoroda - paskyros nereikia. Pamiršus, ją rasi registracijos puslapyje pagal vardą ir gimimo datą.') ?></span>
    <div class="row" style="gap:6px; flex-wrap:wrap;">
      <input type="text" readonly value="<?= e(abs_url($self)) ?>" class="su-url" onclick="this.select()" aria-label="<?= te('Asmeninė nuoroda') ?>">
      <button type="button" class="btn btn-sm" data-copy="<?= e(abs_url($self)) ?>"><?= te('Kopijuoti') ?></button>
    </div>
    <?php if ($suLink && !$past): ?><a class="small" href="<?= e(url('egzamino-registracija.php?k=' . $suLink['token'])) ?>"><?= te('Registruoti brolį, sesę ar draugą →') ?></a><?php endif; ?>
  </section>
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
