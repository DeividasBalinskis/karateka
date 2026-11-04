<?php
// Trenerio „Šiandien“: tik tai, ką reikia padaryti (meniu skirtukai - viršuje)
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();
if ($msg = legacy_demo_autopurge($me)) {   // vieną kartą: seni pavyzdiniai duomenys (žr. app/test_data.php)
    flash('ok', $msg);
}


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

// Reikia padaryti (tas pats skaičius - ant „Valdymas“ mygtuko)
$todo = admin_todo_items();
// Pranešimai: registracijos į egzaminą, pavėlavusieji, „Moku“ patvirtinimai, atšaukti sutikimai - sprendžiami čia pat
$notifs = account_notifications((int) $me['id']);

$upcoming = q_all('SELECT * FROM events WHERE COALESCE(ends_on, starts_on) >= CURDATE() AND starts_on <= DATE_ADD(CURDATE(), INTERVAL 21 DAY) ORDER BY starts_on, start_time');

page_start(t('Šiandien'), ['admin' => true]);
$todoCount = count($notifs) + count($todo);
?>
<div class="page-head row between" style="align-items:flex-end; flex-wrap:wrap; gap:12px;">
  <div>
    <div class="eyebrow"><?= e(weekday_names()[$weekday]) ?>, <?= e(fmt_date($today)) ?></div>
    <h1 class="styled"><?= te('Labas,') ?> <?= e($me['first_name']) ?></h1>
  </div>
  <!-- Greiti veiksmai - vienintelė vieta, kur kartojasi kitų skilčių mygtukai (patogumui) -->
  <div class="dash-actions">
    <a class="btn btn-ghost btn-sm" href="<?= url('admin/naujienos.php?edit=new') ?>"><?= te('+ Naujiena') ?></a>
    <a class="btn btn-ghost btn-sm" href="<?= url('admin/renginiai.php?edit=new') ?>"><?= te('+ Renginys') ?></a>
    <a class="btn btn-ghost btn-sm" href="<?= url('admin/nariai.php?new=1') ?>"><?= te('+ Narys') ?></a>
  </div>
</div>

<div class="dash">
  <!-- Kairėje - tai, ką reikia padaryti; dešinėje - šiandienos treniruotės ir artimiausi renginiai -->
  <div class="dash-main">
    <section class="panel card important" id="svarbu">
      <div class="row between" style="gap:10px; flex-wrap:wrap;">
        <div class="kicker" style="margin:0;"><?= te('Reikia padaryti') ?><?= ($nn = notification_count((int) $me['id'])) ? ' <span class="badge badge-new">' . $nn . ' nauj.</span>' : '' ?></div>
        <?php if ($notifs): ?><span class="small muted"><?= te('Patvirtinkite čia pat - punktas dings.') ?></span><?php endif; ?>
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
      <?php if (!$todoCount): ?><p class="all-done"><?= te('✓ Viskas padaryta') ?></p><?php endif; ?>
    </section>
  </div>

  <div class="dash-side">
    <div class="panel card">
      <div class="kicker"><?= te('Šiandienos treniruotės') ?></div>
      <?php if ($trainings): ?>
        <ul class="list today-list">
          <?php foreach ($trainings as $t): $done = (int) $t['marked'] > 0; $off = training_cancelled((int) $t['group_id'], $today); ?>
            <li class="row<?= $off ? ' is-cancelled' : '' ?>">
              <span class="t-time"><?= $t['start_time'] ? e(fmt_time($t['start_time'])) : '–' ?><?= $t['end_time'] ? '<small>' . e(fmt_time($t['end_time'])) . '</small>' : '' ?></span>
              <span>
                <strong><span class="group-dot" style="background:<?= e(group_color(['id' => $t['group_id'], 'color' => $t['group_color']])) ?>"></span><?= e($t['group_name']) ?></strong>
                <div class="muted small"><?= e($t['location'] ?: '') ?><?= $t['location'] ? ' · ' : '' ?><?= (int) $t['members_count'] ?> nar.<?= $done ? (' ' . t('· pažymėta') . ' ') . (int) $t['marked'] : '' ?>
                  <?php if (is_admin() && !$done && !$off): ?> · <a class="cancel-btn" href="<?= url('admin/atsaukimai.php?g=' . (int) $t['group_id'] . '&d=' . $today . '#naujas') ?>"><?= te('atšaukti') ?></a><?php endif; ?></div>
                <?php if ($off): ?><div class="cancel-tag"><?= te('Atšaukta ·') ?> <?= e($off['reason']) ?></div><?php endif; ?>
              </span>
              <?php if ($off): ?>
                <a class="btn btn-sm btn-ghost" href="<?= url('admin/atsaukimai.php') ?>"><?= te('Atšaukimai') ?></a>
              <?php else: ?>
                <a class="btn btn-sm <?= $done ? 'btn-ghost' : 'btn-primary' ?>" href="<?= url('admin/treniruote.php?g=' . (int) $t['group_id'] . '&d=' . $today) ?>"><?= $done ? t('Pastabos') : t('Lankomumas') ?></a>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="muted"><?= te('Šiandien treniruočių nėra.') ?><?php if ($nextDay): ?> <?= te('Artimiausios -') ?> <?= e(mb_strtolower(weekday_names()[(int) date('N', strtotime($nextDay))])) ?>, <?= e(fmt_date($nextDay)) ?><?php endif; ?></p>
      <?php endif; ?>
    </div>

    <div class="panel card">
      <div class="row between" style="margin-bottom:6px;">
        <div class="kicker" style="margin:0;"><?= te('Artimiausi renginiai (3 savaitės)') ?></div>
        <a class="small" href="<?= url('renginiai.php') ?>"><?= te('Visi renginiai →') ?></a>
      </div>
      <?php if (!$upcoming): ?>
        <p class="muted"><?= te('Per artimiausias 3 savaites renginių nėra.') ?></p>
      <?php else: ?>
        <ul class="list today-list">
          <?php foreach ($upcoming as $ev): ?>
            <li class="row">
              <span class="t-time"><?= date('j', strtotime($ev['starts_on'])) ?><small><?= e(mb_substr(month_names_gen()[(int) date('n', strtotime($ev['starts_on']))], 0, 3)) ?></small></span>
              <span>
                <span class="badge type-badge t-<?= e($ev['type']) ?>"><?= e(t(EVENT_TYPES[$ev['type']])) ?></span>
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

</div>
<?php
page_end();
