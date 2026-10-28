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
$ownMembers = (int) q_value('SELECT COUNT(*) FROM account_members am JOIN members m ON m.id = am.member_id WHERE am.account_id = ? AND m.status = "active"', [(int) $me['id']]);

$today = date('Y-m-d');
$weekday = (int) date('N');

/** Treniruotės pagal savaitės dieną, su šiandienos lankomumo žymomis */
function trainings_on(int $weekday, string $date): array
{
    $rows = q_all('SELECT s.*, g.name AS group_name, g.location, g.color AS group_color,
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
// Pranešimai: registracijos į egzaminą, pavėlavusieji, „Moku“ patvirtinimai, atšaukti sutikimai - sprendžiami čia pat
$notifs = account_notifications((int) $me['id']);

$upcoming = q_all('SELECT * FROM events WHERE COALESCE(ends_on, starts_on) >= CURDATE() AND starts_on <= DATE_ADD(CURDATE(), INTERVAL 21 DAY) ORDER BY starts_on, start_time');

page_start('Šiandien', ['admin' => true]);
// Nustatymai (dešinėje): visi vienoje vietoje, sugrupuoti - meniu juostoje jų nebėra, kad nesikartotų
$settings = [
    'Klubas'                 => [['Grupės ir jų spalvos', 'admin/grupes.php', true], ['Tvarkaraštis ir kainos', 'admin/tvarkarastis.php', true], ['Taškai ir XP', 'admin/taskai.php', true]],
    'Egzaminai ir pamokos'   => [['Egzaminų programa (XP)', 'admin/programa.php', true], ['Testai', 'admin/testai.php', true], ['Pamokų medis', 'admin/pamokos.php', true]],
    'Svetainė'               => [['Pagrindinis puslapis (redaguoti)', 'index.php?redaguoti=1', is_admin()], ['Reakcijų emoji', 'admin/naujienos.php?emoji=1', is_admin()]],
    'Žmonės ir pranešimai'   => [['Paskyros ir rolės', 'admin/paskyros.php', is_admin()], ['Pranešimai nariams ir SMS', 'admin/pranesimai.php', is_admin()], ['Varžybų sistema', 'admin/varzybos.php', true]],
];
$todoCount = count($notifs) + count($todo);
?>
<div class="page-head row between" style="align-items:flex-end; flex-wrap:wrap; gap:12px;">
  <div>
    <div class="eyebrow"><?= e(LT_WEEKDAYS[$weekday]) ?>, <?= e(fmt_date($today)) ?></div>
    <h1 class="styled">Labas, <?= e($me['first_name']) ?></h1>
  </div>
</div>
<?= form_errors($errors) ?>

<div class="dash">
  <!-- Kairėje - tai, ką reikia padaryti šiandien -->
  <div class="dash-main">
    <section class="panel card important" id="svarbu">
      <div class="row between" style="gap:10px; flex-wrap:wrap;">
        <div class="kicker" style="margin:0;">Reikia padaryti<?= ($nn = notification_count((int) $me['id'])) ? ' <span class="badge badge-new">' . $nn . ' nauj.</span>' : '' ?></div>
        <?php if ($notifs): ?><span class="small muted">Patvirtinkite čia pat - punktas dings.</span><?php endif; ?>
      </div>
      <?= render_notifications($notifs, 'admin/#svarbu') ?>
      <?php if ($todo): ?>
        <ul class="notif-list">
          <?php foreach ($todo as [$text, $path, $btn]): ?>
            <li class="notif"><span class="notif-icon" aria-hidden="true">📌</span><div class="notif-body"><div class="notif-title"><?= e($text) ?></div></div>
              <div class="notif-actions"><a class="btn btn-ghost btn-sm" href="<?= url($path) ?>"<?= strpos($path, 'admin/zinutes.php') === 0 ? ' data-open-chat' : '' ?>><?= e($btn) ?> →</a></div></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if (!$todoCount): ?><p class="all-done">✓ Viskas padaryta</p><?php endif; ?>
    </section>

    <div class="panel card">
      <div class="kicker">Šiandienos treniruotės</div>
      <?php if ($trainings): ?>
        <ul class="list today-list">
          <?php foreach ($trainings as $t): $done = (int) $t['marked'] > 0; ?>
            <li class="row">
              <span class="t-time"><?= $t['start_time'] ? e(fmt_time($t['start_time'])) : '–' ?><?= $t['end_time'] ? '<small>' . e(fmt_time($t['end_time'])) . '</small>' : '' ?></span>
              <span>
                <strong><span class="group-dot" style="background:<?= e(group_color(['id' => $t['group_id'], 'color' => $t['group_color']])) ?>"></span><?= e($t['group_name']) ?></strong>
                <div class="muted small"><?= e($t['location'] ?: '') ?><?= $t['location'] ? ' · ' : '' ?><?= (int) $t['members_count'] ?> nar.<?= $done ? ' · pažymėta ' . (int) $t['marked'] : '' ?></div>
              </span>
              <a class="btn btn-sm <?= $done ? 'btn-ghost' : 'btn-primary' ?>" href="<?= url('admin/treniruote.php?g=' . (int) $t['group_id'] . '&d=' . $today) ?>"><?= $done ? 'Pastabos' : 'Lankomumas' ?></a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="muted">Šiandien treniruočių nėra.<?php if ($nextDay): ?> Artimiausios - <?= e(mb_strtolower(LT_WEEKDAYS[(int) date('N', strtotime($nextDay))])) ?>, <?= e(fmt_date($nextDay)) ?><?php endif; ?></p>
      <?php endif; ?>
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
  </div>

  <!-- Dešinėje - greiti veiksmai ir nustatymai -->
  <aside class="dash-side" id="nustatymai">
    <div class="panel card">
      <div class="kicker">Greiti veiksmai</div>
      <div class="dash-actions">
        <a class="btn btn-ghost btn-sm" href="<?= url('admin/naujienos.php?edit=new') ?>">+ Naujiena</a>
        <a class="btn btn-ghost btn-sm" href="<?= url('admin/renginiai.php?edit=new') ?>">+ Renginys</a>
        <a class="btn btn-ghost btn-sm" href="<?= url('pamokos.php?nauja=1') ?>">+ Pamoka</a>
        <a class="btn btn-ghost btn-sm" href="<?= url('admin/nariai.php?new=1') ?>">+ Narys</a>
        <?php if (is_admin()): ?><a class="btn btn-ghost btn-sm" href="<?= url('admin/pranesimai.php') ?>">📣 Pranešimas nariams</a><?php endif; ?>
      </div>
    </div>
    <div class="panel card">
      <div class="kicker">Nustatymai</div>
      <?php foreach ($settings as $groupName => $links): $links = array_filter($links, function ($l) { return $l[2]; }); if (!$links) { continue; } ?>
        <div class="dash-set">
          <div class="dash-set-title"><?= e($groupName) ?></div>
          <ul><?php foreach ($links as [$label, $path]): ?><li><a href="<?= url($path) ?>"><?= e($label) ?> →</a></li><?php endforeach; ?></ul>
        </div>
      <?php endforeach; ?>
      <?php if ($ownMembers): ?><p class="small" style="margin-top:12px;"><a href="<?= url('paskyra.php') ?>">Mano šeimos nariai (taškai, pastabos) →</a></p><?php endif; ?>
    </div>
    <?= render_account_settings($me, 'paskyra', 'Mano paskyra') ?>
    <form method="post" action="<?= url('atsijungti.php') ?>" class="dash-logout"><?= csrf_field() ?><button type="submit" class="btn btn-ghost btn-sm btn-block">Atsijungti</button></form>
  </aside>
</div>
<?php
page_end();
