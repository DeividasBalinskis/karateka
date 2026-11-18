<?php
// Egzaminavimas (viršutinėje juostoje treneriams, šalia „Pamokos“): visi egzaminai vienoje vietoje - protokolas, registracijos nuoroda,
// kiek užsiregistravo, rezultatai. Kad egzaminatoriui nereikėtų ieškoti egzamino tarp renginių.
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

$ready = exam_protocol_ready();
$exams = q_all('SELECT * FROM events WHERE type = "exam" ORDER BY (COALESCE(ends_on, starts_on) < CURDATE()), IF(COALESCE(ends_on, starts_on) < CURDATE(), -TO_DAYS(starts_on), TO_DAYS(starts_on)) LIMIT 60');
$rowsBy = $signBy = $resBy = [];
if ($ready && $exams) {
    $ids = implode(',', array_map(function ($e) { return (int) $e['id']; }, $exams));
    foreach (q_all("SELECT event_id, COUNT(*) AS n, SUM(result = 'passed') AS ok, SUM(result = 'failed') AS bad FROM exam_protocol_rows WHERE deleted_at IS NULL AND event_id IN ($ids) GROUP BY event_id") as $r) {
        $rowsBy[(int) $r['event_id']] = $r;
    }
    if (exam_signup_ready()) {
        foreach (q_all("SELECT event_id, SUM(signed_at IS NOT NULL) AS n FROM exam_signups WHERE event_id IN ($ids) GROUP BY event_id") as $r) {
            $signBy[(int) $r['event_id']] = (int) $r['n'];
        }
    }
}
page_start(t('Egzaminavimas'), ['admin' => true, 'section' => 'egzaminai']);
?>
<div class="page-head">
  <h1 class="styled"><?= te('📋 Egzaminavimas') ?></h1>
  <p><?= te('Visi egzaminai vienoje vietoje: protokolas, registracija per nuorodą ir rezultatai.') ?></p>
  <div class="row" style="gap:8px; flex-wrap:wrap;">
    <?php if (is_admin($me)): ?><a class="btn btn-primary btn-sm" href="<?= url('admin/renginiai.php?edit=new&back=egzaminai') ?>"><?= te('+ Naujas egzaminas') ?></a><?php endif; ?>
    <?php if ($ready && fed_ready()): ?><a class="btn btn-ghost btn-sm" href="<?= url('admin/federacija.php') ?>"><?= te('📒 Federacijos registras') ?></a><?php endif; ?>
    <a class="btn btn-ghost btn-sm" href="<?= url('admin/testai.php') ?>"><?= te('Testai') ?></a>
  </div>
</div>
<?php if (!$ready): ?>
  <p class="panel card muted"><?= te('Protokolai veiks, kai bus atnaujinta duomenų bazė (Šiandien → „Atnaujinkite duomenų bazę“).') ?></p>
<?php elseif (!$exams): ?>
  <p class="panel card muted"><?= te('Egzaminų dar nėra. Sukurkite renginį, kurio tipas „Egzaminas“.') ?></p>
<?php else: ?>
  <div class="exam-list">
    <?php $prevPast = null; foreach ($exams as $ev): $id = (int) $ev['id']; $past = event_last_day($ev) < date('Y-m-d');
      $r = $rowsBy[$id] ?? null; $l = exam_signup_link($id); ?>
      <?php if ($prevPast !== $past): $prevPast = $past; ?><h2 class="exam-list-h"><?= $past ? te('Įvykę') : te('Artimiausi') ?></h2><?php endif; ?>
      <div class="panel card exam-item<?= $past ? ' is-past' : '' ?>">
        <div class="exam-item-main">
          <div class="exam-item-date"><b><?= e(fmt_date($ev['starts_on'], true)) ?></b><?= $ev['start_time'] ? ' ' . e(fmt_time($ev['start_time'])) : '' ?></div>
          <a class="exam-item-title" href="<?= url('renginys.php?id=' . $id) ?>"><?= e(tr_text($ev['title'])) ?></a>
          <div class="small muted"><?= $ev['location'] ? e($ev['location']) . ' · ' : '' ?>
            <?= te('protokole: {n}', ['n' => (int) ($r['n'] ?? 0)]) ?><?php if ($r && ($r['ok'] || $r['bad'])): ?> · <?= te('išlaikė {a}, neišlaikė {b}', ['a' => (int) $r['ok'], 'b' => (int) $r['bad']]) ?><?php endif; ?>
            <?php if ($l): ?> · <?= te('per nuorodą: {n}', ['n' => (int) ($signBy[$id] ?? 0)]) ?> <?= exam_signup_open($ev, $l) ? '<span class="badge ok">' . te('registracija atidaryta') . '</span>' : '<span class="badge">' . te('registracija uždaryta') . '</span>' ?><?php endif; ?></div>
        </div>
        <div class="exam-item-acts">
          <a class="btn btn-primary btn-sm" href="<?= url('admin/egzamino-protokolas.php?id=' . $id) ?>"><?= te('📋 Protokolas') ?></a>
          <?php if (!$past): ?><a class="btn btn-ghost btn-sm" href="<?= url('admin/egzamino-protokolas.php?id=' . $id . '#registracija') ?>"><?= $l ? te('🔗 Registracija') : te('🔗 Sukurti registracijos nuorodą') ?></a><?php endif; ?>
          <a class="btn btn-ghost btn-sm" href="<?= url('admin/renginio-dalyviai.php?id=' . $id) ?>"><?= te('Dalyviai') ?></a>
          <?php if ($ev['starts_on'] <= date('Y-m-d')): ?><a class="btn btn-ghost btn-sm" href="<?= url('admin/renginio-rezultatai.php?id=' . $id) ?>"><?= te('Rezultatai ir XP') ?></a><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php
page_end();
