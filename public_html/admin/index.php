<?php
// Trenerio „Šiandien“: tik tai, ką reikia padaryti (meniu skirtukai - viršuje)
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

// Paskyros nustatymai (el. paštas, telefonas, slaptažodis) - šiame puslapyje
$errors = [];
if (is_post()) {
    csrf_check();
    handle_account_settings($me, 'admin/', $errors);
}
$settingsOpen = (bool) $errors;
$ownMembers = (int) q_value('SELECT COUNT(*) FROM account_members am JOIN members m ON m.id = am.member_id WHERE am.account_id = ? AND m.status = "active"', [(int) $me['id']]);

$today = date('Y-m-d');
$weekday = (int) date('N');

/** Treniruotės pagal savaitės dieną, su šiandienos lankomumo žymomis */
function trainings_on(int $weekday, string $date): array
{
    $rows = q_all('SELECT s.*, g.name AS group_name, g.location,
                          (SELECT COUNT(*) FROM members m WHERE m.group_id = g.id AND m.status = "active") AS members_count,
                          (SELECT COUNT(*) FROM attendance at WHERE at.group_id = g.id AND at.training_date = ?) AS marked
                     FROM schedule s JOIN training_groups g ON g.id = s.group_id
                    WHERE g.is_active = 1 AND s.weekday = ?
                    ORDER BY s.start_time, g.sort_order', [$date, $weekday]);
    return $rows;
}

$trainings = trainings_on($weekday, $today);
// Jei šiandien treniruočių nėra - artimiausia diena su treniruotėmis
$nextDay = null;
if (!$trainings) {
    for ($i = 1; $i <= 7 && !$nextDay; $i++) {
        $d = date('Y-m-d', strtotime("+$i day"));
        if (trainings_on((int) date('N', strtotime($d)), $d)) {
            $nextDay = $d;
        }
    }
}

// Reikia padaryti
$todo = [];
if ($n = pending_approvals_count()) {
    $todo[] = ['Laukia patvirtinimo: ' . $n . ($n === 1 ? ' paskyra ar vaikas' : ' paskyros ar vaikai'), 'admin/patvirtinimai.php', 'Patvirtinti'];
}
// Praėję renginiai be įvestų rezultatų (paskutinės 30 d.)
$types = "'" . implode("','", array_keys(EVENT_RESULTS)) . "'";
foreach (q_all("SELECT e.* FROM events e
                 WHERE e.type IN ($types) AND COALESCE(e.ends_on, e.starts_on) BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                   AND NOT EXISTS (SELECT 1 FROM point_awards pa WHERE pa.event_id = e.id)
                 ORDER BY e.starts_on DESC") as $ev) {
    $todo[] = ['Įvesk rezultatus: ' . $ev['title'] . ' (' . fmt_date($ev['starts_on']) . ')', 'admin/renginio-rezultatai.php?id=' . (int) $ev['id'], 'Rezultatai'];
}
$todo = array_merge($todo, admin_today_extra_todo());

$upcoming = q_all('SELECT * FROM events WHERE COALESCE(ends_on, starts_on) >= CURDATE() AND starts_on <= DATE_ADD(CURDATE(), INTERVAL 21 DAY) ORDER BY starts_on, start_time');

page_start('Šiandien', ['admin' => true]);
?>
<div class="page-head row between" style="align-items:flex-end; flex-wrap:wrap; gap:12px;">
  <div>
    <div class="eyebrow"><?= e(LT_WEEKDAYS[$weekday]) ?>, <?= e(fmt_date($today)) ?></div>
    <h1 class="styled">Labas, <?= e($me['first_name']) ?></h1>
  </div>
  <div class="row" style="gap:8px;">
    <button type="button" class="btn btn-ghost btn-sm" id="settingsBtn" aria-expanded="<?= $settingsOpen ? 'true' : 'false' ?>" aria-controls="settingsPanel">⚙ Paskyros nustatymai</button>
    <form method="post" action="<?= url('atsijungti.php') ?>" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn btn-ghost btn-sm">Atsijungti</button></form>
  </div>
</div>
<?= form_errors($errors) ?>
<div id="settingsPanel" style="margin:-8px 0 24px;" <?= $settingsOpen ? '' : 'hidden' ?>><?= render_account_settings($me) ?></div>
<script>
document.getElementById('settingsBtn').addEventListener('click', function () {
  var p = document.getElementById('settingsPanel'), open = p.hasAttribute('hidden');
  if (open) { p.removeAttribute('hidden'); } else { p.setAttribute('hidden', ''); }
  this.setAttribute('aria-expanded', open ? 'true' : 'false');
});
</script>

<div class="today-grid">
  <div class="panel card">
    <div class="kicker">Šiandienos treniruotės</div>
    <?php if ($trainings): ?>
      <ul class="list today-list">
        <?php foreach ($trainings as $t): $done = (int) $t['marked'] > 0; ?>
          <li class="row">
            <span class="t-time"><?= e(fmt_time($t['start_time'])) ?><?= $t['end_time'] ? '<small>' . e(fmt_time($t['end_time'])) . '</small>' : '' ?></span>
            <span>
              <strong><?= e($t['group_name']) ?></strong>
              <div class="muted small"><?= e($t['location'] ?: '') ?><?= $t['location'] ? ' · ' : '' ?><?= kiek((int) $t['members_count'], 'narys', 'nariai', 'narių') ?><?= $done ? ' · pažymėta ' . (int) $t['marked'] : '' ?></div>
            </span>
            <a class="btn btn-sm <?= $done ? 'btn-ghost' : 'btn-primary' ?>" href="<?= url('admin/treniruote.php?g=' . (int) $t['group_id'] . '&d=' . $today) ?>"><?= $done ? 'Pastabos' : 'Lankomumas' ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="muted">Šiandien treniruočių nėra.<?php if ($nextDay): ?> Artimiausios - <?= e(mb_strtolower(LT_WEEKDAYS[(int) date('N', strtotime($nextDay))])) ?>, <?= e(fmt_date($nextDay)) ?>.<?php endif; ?></p>
    <?php endif; ?>
  </div>

  <div class="panel card">
    <div class="kicker">Reikia padaryti</div>
    <?php if ($todo): ?>
      <ul class="list today-list">
        <?php foreach ($todo as [$text, $path, $btn]): ?>
          <li class="row"><span><?= e($text) ?></span><a class="btn btn-ghost btn-sm" href="<?= url($path) ?>"><?= e($btn) ?> →</a></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="all-done">✓ Viskas padaryta</p>
    <?php endif; ?>
  </div>
</div>

<div class="panel card">
  <div class="row between" style="margin-bottom:6px;">
    <div class="kicker" style="margin:0;">Artimiausi renginiai (3 savaitės)</div>
    <a class="small" href="<?= url('renginiai.php') ?>">Visi renginiai →</a>
  </div>
  <?php if (!$upcoming): ?>
    <p class="muted">Per artimiausias 3 savaites renginių nėra.</p>
  <?php else: ?>
    <ul class="list today-list">
      <?php foreach ($upcoming as $ev): ?>
        <li class="row">
          <span class="t-time"><?= date('j', strtotime($ev['starts_on'])) ?><small><?= e(mb_substr(LT_MONTHS_GEN[(int) date('n', strtotime($ev['starts_on']))], 0, 3)) ?></small></span>
          <span>
            <span class="badge type-badge t-<?= e($ev['type']) ?>"><?= e(EVENT_TYPES[$ev['type']]) ?></span>
            <strong><?= e($ev['title']) ?></strong>
            <div class="muted small"><?= admin_event_summary($ev) ?></div>
          </span>
          <a class="btn btn-ghost btn-sm" href="<?= url(admin_event_link($ev)) ?>"><?= e(admin_event_link_label($ev)) ?> →</a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<div class="row quick-actions">
  <a class="btn btn-ghost btn-sm" href="<?= url('admin/naujienos.php?edit=new') ?>">+ Naujiena</a>
  <a class="btn btn-ghost btn-sm" href="<?= url('admin/renginiai.php?edit=new') ?>">+ Renginys</a>
  <a class="btn btn-ghost btn-sm" href="<?= url('admin/pamokos.php?edit=new') ?>">+ Pamoka</a>
  <?php if ($ownMembers): ?><a class="small" style="margin-left:auto;" href="<?= url('paskyra.php') ?>">Mano šeimos nariai (taškai, pastabos) →</a><?php endif; ?>
</div>
<?php
page_end();
