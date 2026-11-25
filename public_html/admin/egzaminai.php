<?php
// Egzaminai (trenerio meniu): paprastas sąrašas - artimiausi viršuje, kiekvienam vienas pagrindinis mygtukas „📋 Protokolas“
// (ten - lentelė su balais, registracijos nuoroda ir visa kita) ir „🔗“ registracijos nuorodai nukopijuoti. Įvykę - suskleisti.
// Adminui: „💶 Kainos, mokėjimai, sertifikatai“ - egzamino kaina kiekvienam diržui (rodoma vaiko egzamino puslapyje); kas dar laukia info - pažymėta.
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_can('exam_protocols');

if (is_post() && post('action') === 'prices' && can('exams', $me)) {
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

// #165: laukiančiųjų sąrašas - nuoroda, atidaryta / uždaryta, įtraukimas į protokolą (su laišku), išėmimas; kitas protokolas tai pačiai datai
if (is_post() && in_array(post('action'), ['wl_link', 'wl_open', 'wl_assign', 'wl_remove', 'copy_exam'], true) && exam_waitlist_ready()) {
    csrf_check();
    switch (post('action')) {
        case 'wl_link':
            exam_waitlist_token(true);
            flash('ok', t('Registracijos nuoroda sukurta. Sena (jei buvo) nebeveikia.'));
            break;
        case 'wl_open':
            set_site_setting('exam_waitlist_open', post('open') === '1' ? '1' : '0');
            flash('ok', post('open') === '1' ? t('Registracija atidaryta.') : t('Registracija uždaryta.'));
            break;
        case 'wl_assign':
            $ev = q_one('SELECT * FROM events WHERE id = ? AND type = "exam"', [(int) post('event_id')]);
            $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
            if (!$ev || !$ids) {
                flash('err', t('Pažymėkite žmones ir pasirinkite protokolą.'));
                break;
            }
            $n = $m = 0;
            foreach ($ids as $wid) {
                $r = exam_waitlist_assign($wid, $ev, trim($me['first_name'] . ' ' . $me['last_name']) . ', ' . VARZ_HOME_CLUB);
                $n += $r['ok'] ? 1 : 0;
                $m += $r['mailed'] ? 1 : 0;
            }
            flash('ok', t('Įtraukta į protokolą: {n}. Laiškų išsiųsta: {m}.', ['n' => $n, 'm' => $m]));
            redirect('admin/egzaminai.php#laukiantieji');
        case 'wl_remove':
            q('UPDATE exam_waitlist SET removed_at = NOW() WHERE id = ? AND event_id IS NULL', [(int) post('id')]);
            flash('ok', t('Išimta iš laukiančiųjų sąrašo.'));
            redirect('admin/egzaminai.php#laukiantieji');
        case 'copy_exam':   // tai pačiai egzamino dienai - kitas protokolas (pvz. kitiems diržams)
            $src = q_one('SELECT * FROM events WHERE id = ? AND type = "exam"', [(int) post('id')]);
            $label = mb_substr(trim((string) post('label')), 0, 60);
            if ($src && $label !== '' && can('exams', $me)) {
                $title = mb_substr(preg_replace('/\s+·\s+[^·]*$/u', '', $src['title']) . ' · ' . $label, 0, 190);
                q('INSERT INTO events (type, title, starts_on, ends_on, start_time, location, is_abroad, members_only, registration, registration_until, invited_only, description, summary, cover)
                   SELECT type, ?, starts_on, ends_on, start_time, location, is_abroad, members_only, registration, registration_until, invited_only, description, summary, cover FROM events WHERE id = ?',
                    [$title, (int) $src['id']]);
                $newId = (int) db()->lastInsertId();
                if (event_main_sql() !== '1') {   // v1.94 (#174): papildomas protokolas - viešuose sąrašuose rodomas tik pagrindinis renginys
                    q('UPDATE events SET parent_id = ? WHERE id = ?', [(int) ($src['parent_id'] ?? 0) ?: (int) $src['id'], $newId]);
                }
                if (isset($_POST['start_time'])) {   // savas pradžios laikas (tuščias - be laiko); be lauko - kaip šaltinio
                    $tm = trim((string) post('start_time'));
                    q('UPDATE events SET start_time = ? WHERE id = ?', [preg_match('/^([01]\d|2[0-3]):[0-5]\d/', $tm) ? substr($tm, 0, 5) . ':00' : null, $newId]);
                }
                flash('ok', t('Protokolas sukurtas: {t}', ['t' => $title]));
            }
            break;
    }
    redirect('admin/egzaminai.php#laukiantieji');
}

$ready = exam_protocol_ready();
$exams = q_all('SELECT * FROM events WHERE type = "exam" ORDER BY (COALESCE(ends_on, starts_on) < CURDATE()), IF(COALESCE(ends_on, starts_on) < CURDATE(), -TO_DAYS(starts_on), TO_DAYS(starts_on)), start_time IS NULL, start_time, id LIMIT 60');
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
$waiting = exam_waitlist_pending();
$wlUrl = exam_waitlist_ready() ? exam_waitlist_url() : '';

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
  <?php if (can('exams', $me)): ?><a class="btn btn-primary btn-sm" href="<?= url('admin/renginiai.php?edit=new&back=egzaminai') ?>"><?= te('+ Naujas egzaminas') ?></a><?php endif; ?>
</div>

<?php if (!$ready): ?>
  <p class="panel card muted"><?= te('Protokolai veiks, kai bus atnaujinta duomenų bazė (Pradžia → „Atnaujinkite duomenų bazę“).') ?></p>
<?php elseif (!$exams): ?>
  <p class="panel card muted"><?= te('Egzaminų dar nėra. Sukurkite renginį, kurio tipas „Egzaminas“.') ?></p>
<?php else: ?>
  <?php if (exam_waitlist_ready()): ?>
  <section class="panel card exam-wl" id="laukiantieji">
    <div class="row between" style="gap:8px; flex-wrap:wrap; align-items:center;">
      <h2 style="margin:0;">⏳ <?= te('Laukiantieji') ?> <span class="badge<?= $waiting ? ' badge-warn' : '' ?>"><?= count($waiting) ?></span></h2>
      <div class="row" style="gap:6px; flex-wrap:wrap;">
        <?php if ($wlUrl): ?>
          <code class="exam-wl-url"><?= e(preg_replace('~^https?://~', '', $wlUrl)) ?></code>
          <button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e($wlUrl) ?>" title="<?= te('Kopijuoti registracijos nuorodą') ?>"><?= te('🔗 Kopijuoti nuorodą') ?></button>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="wl_open"><input type="hidden" name="open" value="<?= exam_waitlist_is_open() ? '0' : '1' ?>">
            <button class="btn btn-ghost btn-sm" type="submit"><?= exam_waitlist_is_open() ? te('Uždaryti registraciją') : te('Atidaryti registraciją') ?></button></form>
          <?php if (strlen(exam_waitlist_token()) > 6): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="wl_link">
            <button class="btn btn-ghost btn-sm" type="submit" onclick="return confirm('<?= e(t('Sukurti trumpą nuorodą? Sena (ilga) nebeveiks.')) ?>')"><?= te('↻ Trumpa nuoroda') ?></button></form><?php endif; ?>
        <?php else: ?>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="wl_link"><button class="btn btn-primary btn-sm" type="submit"><?= te('Sukurti registracijos nuorodą') ?></button></form>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($wlUrl): ?><p class="small muted" style="margin:6px 0 0;"><?= exam_waitlist_is_open() ? te('Viena nuoroda visiems egzaminams. Užsiregistravę atsiranda čia; pažymėkite ir įtraukite į protokolą - žmogus gaus laišką su data, vieta ir kaina.') : te('Registracija uždaryta - nuoroda rodo „Registracija uždaryta“.') ?></p><?php endif; ?>
    <?php if ($waiting): ?>
      <form method="post" class="exam-wl-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="wl_assign">
        <div class="table-wrap"><table class="table exam-wl-table">
          <thead><tr><th><input type="checkbox" data-wl-all aria-label="<?= te('Pažymėti visus') ?>"></th><th><?= te('Vardas Pavardė') ?></th><th><?= te('Laikys') ?></th><th><?= te('Kaina') ?></th><th><?= te('Klubas') ?></th><th><?= te('Kontaktai') ?></th><th><?= te('Užsiregistravo') ?></th><th></th></tr></thead>
          <tbody>
          <?php foreach ($waiting as $w): $pr = exam_price_for($w['kyu']); ?>
            <tr>
              <td><input type="checkbox" name="ids[]" value="<?= (int) $w['id'] ?>" aria-label="<?= e($w['first_name'] . ' ' . $w['last_name']) ?>"></td>
              <td><b><?= e($w['first_name'] . ' ' . $w['last_name']) ?></b><?= $w['birth_date'] ? '<div class="small muted">' . e(fmt_date($w['birth_date'], true)) . '</div>' : '' ?><?= $w['note'] ? '<div class="small">' . e($w['note']) . '</div>' : '' ?></td>
              <td><?= e((string) $w['kyu']) ?></td>
              <td><?= $pr !== null ? e(number_format($pr, 2, ',', '')) . ' €' : '<span class="muted">-</span>' ?></td>
              <td><?= e((string) $w['club']) ?></td>
              <td class="small"><?= e((string) $w['email']) ?><br><?= e((string) $w['phone']) ?></td>
              <td class="small"><?= e(fmt_date(substr($w['created_at'], 0, 10), true)) ?></td>
              <td><button class="btn btn-ghost btn-xs" type="submit" form="wl-rm-<?= (int) $w['id'] ?>" title="<?= te('Išimti iš sąrašo') ?>" onclick="return confirm('<?= e(t('Išimti iš laukiančiųjų sąrašo?')) ?>')">✕</button></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <div class="row exam-wl-go" style="gap:8px; flex-wrap:wrap; align-items:center;">
          <label class="small"><?= te('Pažymėtus įtraukti į protokolą:') ?>
            <select name="event_id" required><option value=""><?= te('- pasirinkite egzaminą -') ?></option>
              <?php foreach ($upcoming as $ev): ?><option value="<?= (int) $ev['id'] ?>"><?= e(fmt_date($ev['starts_on'], true) . ($ev['start_time'] ? ' ' . fmt_time($ev['start_time']) : '') . ' · ' . tr_text($ev['title'])) ?></option><?php endforeach; ?>
            </select></label>
          <button class="btn btn-primary btn-sm" type="submit"><?= te('Įtraukti ir išsiųsti laišką') ?></button>
        </div>
      </form>
      <?php foreach ($waiting as $w): ?><form method="post" id="wl-rm-<?= (int) $w['id'] ?>" hidden><?= csrf_field() ?><input type="hidden" name="action" value="wl_remove"><input type="hidden" name="id" value="<?= (int) $w['id'] ?>"></form><?php endforeach; ?>
    <?php elseif ($wlUrl): ?>
      <p class="muted" style="margin:8px 0 0;"><?= te('Laukiančiųjų nėra.') ?></p>
    <?php endif; ?>
  </section>
  <?php endif; ?>
  <div class="panel card exam-rows">
    <?php if (!$upcoming): ?><p class="muted" style="margin:0;"><?= te('Artimiausių egzaminų nėra.') ?></p><?php endif; ?>
    <?php $lastDate = null; foreach ($upcoming as $ev) {
        // #165: viena egzamino diena - keli protokolai (skirtingiems diržams); po paskutiniu tos dienos - „+ Protokolas šiai datai“
        echo $item($ev, false);
        $next = $upcoming[array_search($ev, $upcoming, true) + 1] ?? null;
        if (can('exams', $me) && exam_waitlist_ready() && (!$next || $next['starts_on'] !== $ev['starts_on'])) {
            echo '<form method="post" class="exam-copy">' . csrf_field() . '<input type="hidden" name="action" value="copy_exam"><input type="hidden" name="id" value="' . (int) $ev['id'] . '">'
                . '<input type="text" name="label" maxlength="60" required placeholder="' . e(t('pvz. 10–7 kyu')) . '" aria-label="' . e(t('Kokiems diržams')) . '">'
                . '<input type="time" name="start_time" value="' . e(fmt_time($ev['start_time'])) . '" aria-label="' . e(t('Pradžios laikas')) . '" title="' . e(t('Naujo protokolo pradžios laikas')) . '">'
                . '<button class="btn btn-ghost btn-sm" type="submit">' . te('+ Protokolas šiai datai') . '</button></form>';
        }
    } ?>
  </div>
  <?php if ($past): ?>
    <details class="exam-past">
      <summary><?= te('Įvykę egzaminai') ?> (<?= count($past) ?>)</summary>
      <div class="panel card exam-rows"><?php foreach ($past as $ev) { echo $item($ev, true); } ?></div>
    </details>
  <?php endif; ?>
<?php endif; ?>

<div class="exam-extra">
  <?php if (can('exams', $me)): ?>
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
var wlAll = document.querySelector('[data-wl-all]');
if (wlAll) wlAll.addEventListener('change', function () { document.querySelectorAll('.exam-wl-table tbody input[type=checkbox]').forEach(function (c) { c.checked = wlAll.checked; }); });
</script>
<?php
page_end();
