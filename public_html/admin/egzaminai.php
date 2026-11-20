<?php
// Egzaminai (trenerio meniu): paprastas sąrašas - artimiausi viršuje, kiekvienam vienas pagrindinis mygtukas „📋 Protokolas“
// (ten - lentelė su balais, registracijos nuoroda ir visa kita) ir „🔗“ registracijos nuorodai nukopijuoti. Įvykę - suskleisti.
// Adminui: „💶 Kainos, mokėjimai, sertifikatai“ - egzamino kaina kiekvienam diržui (rodoma vaiko egzamino puslapyje); kas dar laukia info - pažymėta.
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

if (is_post() && post('action') === 'prices' && is_admin($me)) {
    csrf_check();
    $out = [];
    foreach ((array) ($_POST['price'] ?? []) as $lvl => $v) {
        $v = str_replace(',', '.', trim((string) $v));
        if (isset(exam_grades()[(int) $lvl]) && is_numeric($v) && (float) $v > 0) {
            $out[(int) $lvl] = round((float) $v, 2);
        }
    }
    set_site_setting('exam_prices', json_encode($out));
    set_site_setting('exam_pay_info', mb_substr(trim((string) post('pay_info')), 0, 1000));
    flash('ok', t('Kainos išsaugotos.'));
    redirect('admin/egzaminai.php#kainos');
}

$ready = exam_protocol_ready();
$exams = q_all('SELECT * FROM events WHERE type = "exam" ORDER BY (COALESCE(ends_on, starts_on) < CURDATE()), IF(COALESCE(ends_on, starts_on) < CURDATE(), -TO_DAYS(starts_on), TO_DAYS(starts_on)) LIMIT 60');
$rowsBy = $signBy = [];
if ($ready && $exams) {
    $ids = implode(',', array_map(function ($e) { return (int) $e['id']; }, $exams));
    foreach (q_all("SELECT event_id, COUNT(*) AS n, SUM(result = 'passed') AS ok, SUM(result = 'failed') AS bad FROM exam_protocol_rows WHERE deleted_at IS NULL AND event_id IN ($ids) GROUP BY event_id") as $r) {
        $rowsBy[(int) $r['event_id']] = $r;
    }
    // B6: užsiregistravę per svetainę (renginio dalyviai), dar neįkelti į protokolą
    foreach (q_all("SELECT ep.event_id, COUNT(*) AS n, SUM(NOT EXISTS (SELECT 1 FROM exam_protocol_rows r WHERE r.event_id = ep.event_id AND r.member_id = ep.member_id)) AS missing
                      FROM event_participants ep WHERE ep.status IN ('going', 'late') AND ep.event_id IN ($ids) GROUP BY ep.event_id") as $r) {
        $signBy[(int) $r['event_id']] = $r;
    }
}
$upcoming = array_values(array_filter($exams, function ($ev) { return event_last_day($ev) >= date('Y-m-d'); }));
$past = array_values(array_filter($exams, function ($ev) { return event_last_day($ev) < date('Y-m-d'); }));
$prices = exam_prices();

$item = function (array $ev, bool $isPast) use ($rowsBy, $signBy): string {
    $id = (int) $ev['id'];
    $r = $rowsBy[$id] ?? null;
    $l = $isPast ? null : exam_signup_link($id);
    $n = (int) ($r['n'] ?? 0);
    $reg = (int) ($signBy[$id]['n'] ?? 0);
    $missing = (int) ($signBy[$id]['missing'] ?? 0);
    // B6 (sprendimas 23): abu skaičiai - kiek užsiregistravo ir kiek jau protokole
    $info = t('užsiregistravo {a} · protokole {b}', ['a' => $reg, 'b' => $n]);
    if ($r && ($r['ok'] || $r['bad'])) {
        $info .= ' · ' . t('išlaikė {a}, neišlaikė {b}', ['a' => (int) $r['ok'], 'b' => (int) $r['bad']]);
    }
    ob_start(); ?>
    <div class="exam-row<?= $isPast ? ' is-past' : '' ?>">
      <div class="exam-row-date"><b><?= e(fmt_date($ev['starts_on'], true)) ?></b><?= $ev['start_time'] ? '<span>' . e(fmt_time($ev['start_time'])) . '</span>' : '' ?></div>
      <div class="exam-row-main">
        <a class="exam-row-title" href="<?= url('admin/egzamino-protokolas.php?id=' . $id) ?>"><?= e(tr_text($ev['title'])) ?></a>
        <div class="small muted"><?= $ev['location'] ? e($ev['location']) . ' · ' : '' ?><?= e($info) ?><?php if ($l): ?> · <?= exam_signup_open($ev, $l) ? '<span class="badge ok">' . te('registracija atidaryta') . '</span>' : '<span class="badge">' . te('registracija uždaryta') . '</span>' ?><?php endif; ?></div>
      </div>
      <div class="exam-row-acts">
        <?php if ($missing && !$isPast): ?><form method="post" action="<?= url('admin/egzamino-protokolas.php?id=' . $id) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="add_members">
          <button class="btn btn-ghost btn-sm" type="submit" title="<?= te('Užsiregistravusius per svetainę įrašyti į protokolą') ?>"><?= te('↓ Į protokolą ({n})', ['n' => $missing]) ?></button></form><?php endif; ?>
        <?php if ($l): ?><button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e(exam_signup_url($l)) ?>" title="<?= te('Kopijuoti registracijos nuorodą') ?>"><?= te('🔗 Nuoroda') ?></button><?php endif; ?>
        <a class="btn btn-primary btn-sm" href="<?= url('admin/egzamino-protokolas.php?id=' . $id) ?>"><?= te('📋 Protokolas') ?></a>
      </div>
    </div>
    <?php return (string) ob_get_clean();
};

page_start(t('Egzaminai'), ['admin' => true]);
?>
<div class="page-head row between" style="gap:10px; flex-wrap:wrap; align-items:flex-end;">
  <div>
    <h1 class="styled"><?= te('Egzaminai') ?></h1>
    <p style="margin:0;"><?= te('Atidarykite protokolą - ten balai, registracijos nuoroda ir viskas kita.') ?></p>
  </div>
  <?php if (is_admin($me)): ?><a class="btn btn-primary btn-sm" href="<?= url('admin/renginiai.php?edit=new&back=egzaminai') ?>"><?= te('+ Naujas egzaminas') ?></a><?php endif; ?>
</div>

<?php if (!$ready): ?>
  <p class="panel card muted"><?= te('Protokolai veiks, kai bus atnaujinta duomenų bazė (Pradžia → „Atnaujinkite duomenų bazę“).') ?></p>
<?php elseif (!$exams): ?>
  <p class="panel card muted"><?= te('Egzaminų dar nėra. Sukurkite renginį, kurio tipas „Egzaminas“.') ?></p>
<?php else: ?>
  <div class="panel card exam-rows">
    <?php if (!$upcoming): ?><p class="muted" style="margin:0;"><?= te('Artimiausių egzaminų nėra.') ?></p><?php endif; ?>
    <?php foreach ($upcoming as $ev) { echo $item($ev, false); } ?>
  </div>
  <?php if ($past): ?>
    <details class="exam-past">
      <summary><?= te('Įvykę egzaminai') ?> (<?= count($past) ?>)</summary>
      <div class="panel card exam-rows"><?php foreach ($past as $ev) { echo $item($ev, true); } ?></div>
    </details>
  <?php endif; ?>
<?php endif; ?>

<div class="exam-extra">
  <?php if (is_admin($me)): ?>
    <details class="panel card exam-prices" id="kainos">
      <summary><b><?= te('💶 Kainos, mokėjimai, sertifikatai') ?></b> <span class="small muted"><?= $prices ? te('kainos nustatytos: {n}', ['n' => count($prices)]) : te('kainos dar nenustatytos') ?></span></summary>
      <form method="post" class="form">
        <?= csrf_field() ?><input type="hidden" name="action" value="prices">
        <p class="small muted" style="margin:0;"><?= te('Egzamino kaina kiekvienam diržui. Vaikas mato savo kainą egzamino puslapyje, treneris protokole pažymi „€ Sumokėjo“.') ?></p>
        <div class="price-grid">
          <?php foreach (exam_grades() as $lvl => [$kyu, $name, $color]): ?>
            <label><span><i class="belt-dot" style="background:<?= e($color) ?>"></i><?= e($kyu) ?></span>
              <input type="text" inputmode="decimal" name="price[<?= $lvl ?>]" value="<?= isset($prices[$lvl]) ? e((string) $prices[$lvl]) : '' ?>" placeholder="€" aria-label="<?= e($kyu) ?> €"></label>
          <?php endforeach; ?>
        </div>
        <label><?= te('Kaip sumokėti (rekvizitai, kol nėra mokėjimų internetu)') ?>
          <textarea name="pay_info" rows="2" maxlength="1000" placeholder="<?= te('pvz. Gavėjas, sąskaitos Nr., mokėjimo paskirtis: vardas, pavardė, egzaminas') ?>"><?= e(exam_pay_info()) ?></textarea></label>
        <button class="btn btn-primary btn-sm" type="submit"><?= te('Išsaugoti kainas') ?></button>
      </form>
      <ul class="need-list small">
        <li><span class="need-info"><?= te('reikia info') ?></span> <b><?= te('Mokėjimai internetu') ?></b> - <?= te('kurią sistemą naudosime (Paysera, Montonio, banko nuoroda) ir jos sutarties duomenys. Iki tol - rekvizitai aukščiau ir „€“ protokole.') ?></li>
        <li><span class="need-info"><?= te('reikia info') ?></span> <b><?= te('Sertifikatai') ?></b> - <?= te('sertifikato šablonas (PDF ar paveikslėlis), kas pasirašo, ar siųsti el. paštu, ar spausdinti. Mygtukas protokole jau paruoštas.') ?></li>
        <li><span class="need-info"><?= te('reikia info') ?></span> <b><?= te('Kainos') ?></b> - <?= te('ar kaina priklauso tik nuo diržo, ar ir nuo klubo (savo / kiti klubai).') ?></li>
      </ul>
    </details>
  <?php endif; ?>
  <div class="row small" style="gap:14px; flex-wrap:wrap;">
    <a href="<?= url('admin/programa.php') ?>"><?= te('Egzaminų programa') ?></a>
    <a href="<?= url('admin/testai.php') ?>"><?= te('Testai') ?></a>
    <?php if ($ready && fed_ready()): ?><a href="<?= url('admin/federacija.php') ?>"><?= te('📒 Federacijos registras') ?></a><?php endif; ?>
  </div>
</div>
<script>
document.querySelectorAll('[data-copy]').forEach(function (b) {
  b.addEventListener('click', function () {
    var o = b.textContent, done = function () { b.textContent = '✓ ' + <?= json_encode(t('Nukopijuota')) ?>; setTimeout(function () { b.textContent = o; }, 1500); };
    if (navigator.clipboard) navigator.clipboard.writeText(b.dataset.copy).then(done, function () {}); else { var i = document.createElement('input'); i.value = b.dataset.copy; document.body.appendChild(i); i.select(); document.execCommand('copy'); i.remove(); done(); }
  });
});
if (location.hash === '#kainos') { var k = document.getElementById('kainos'); if (k) k.open = true; }
</script>
<?php
page_end();
