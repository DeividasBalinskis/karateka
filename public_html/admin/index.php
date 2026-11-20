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
    // #87: skaičiuojamos grupės (kaip „Treniruotė“ puslapyje), ne tvarkaraščio eilutės; eilutės be laiko nesiskaičiuoja
    $out = [];
    foreach ($rows as $r) {
        if ($r['start_time'] !== null && !isset($out[(int) $r['group_id']])) {
            $out[(int) $r['group_id']] = $r;
        }
    }
    return array_values($out);
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
// B5: darbai (patvirtinti „Moku“, pavėlavusieji, atšauktas sutikimas) ir naujienos kraunami atskirai
$notifs = account_notifications((int) $me['id'], null, 0, 'action');
$allNews = account_notifications((int) $me['id'], null, 200, 'notice');
// Atsakymai į atsiliepimus - ne darbai: rodomi 2 naujausi, kiti išskleidžiami, ir „Supratau visus“
$fbNotifs = array_values(array_filter($allNews, function ($n) { return $n['type'] === 'feedback'; }));
// Iš viršaus žemyn 1, 2, 3 - pagal atsiliepimo numerį (seniausias viršuje)
usort($fbNotifs, function ($x, $y) { return ((int) ($x['ref_id'] ?: 0) <=> (int) ($y['ref_id'] ?: 0)) ?: ((int) $x['id'] <=> (int) $y['id']); });
// Kitos naujienos (registracijos į egzaminą, komentarai, registracijos į varžybas...): 2 naujausios, kitos suskleistos
$news = array_values(array_filter($allNews, function ($n) { return $n['type'] !== 'feedback'; }));

$upcoming = q_all('SELECT * FROM events WHERE COALESCE(ends_on, starts_on) >= CURDATE() AND starts_on <= DATE_ADD(CURDATE(), INTERVAL 21 DAY) ORDER BY starts_on, start_time');

page_start(t('Šiandien'), ['admin' => true]);
$todoCount = count($notifs) + count($todo);
?>
<div class="page-head row between" style="align-items:flex-end; flex-wrap:wrap; gap:12px;">
  <div>
    <div class="eyebrow"><?= e(weekday_names()[$weekday]) ?>, <?= e(fmt_date($today)) ?></div>
    <h1 class="styled"><?= te('Labas,') ?> <?= e($me['first_name']) ?><?= tester_badge($me) ?></h1>
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
        <div class="kicker" style="margin:0;"><?= te('Reikia padaryti') ?><?= $todoCount ? ' <span class="count-badge">' . $todoCount . '</span>' : '' ?></div>
        <?php if ($notifs): ?><span class="small muted"><?= te('Patvirtinkite čia pat - punktas dings.') ?></span><?php endif; ?>
      </div>
      <?php if ($todo): ?>
        <ul class="notif-list">
          <?php foreach ($todo as [$text, $path, $btn]): ?>
            <li class="notif"><span class="notif-icon" aria-hidden="true">📌</span><div class="notif-body"><div class="notif-title"><?= e($text) ?></div></div>
              <div class="notif-actions"><a class="btn btn-ghost btn-sm" href="<?= url($path) ?>"<?= strpos($path, 'admin/zinutes.php') === 0 ? ' data-open-chat' : '' ?>><?= e($btn) ?> →</a></div></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?= render_notifications($notifs, 'admin/#svarbu') ?>
      <?php if (!$todoCount): ?><p class="all-done"><?= te('✓ Viskas padaryta') ?></p><?php endif; ?>
      <?php if ($news): ?>
        <div class="news-block">
          <div class="row between" style="gap:8px;flex-wrap:wrap;"><span class="small muted"><?= te('🔔 Naujienos') ?> (<?= count($news) ?>)</span>
            <form method="post" action="<?= url('pranesimas.php') ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="done_notices"><input type="hidden" name="back" value="admin/#svarbu">
              <button class="btn btn-ghost btn-sm" type="submit"><?= te('Supratau visus') ?></button></form></div>
          <?= render_notifications(array_slice($news, 0, 2), 'admin/#svarbu') ?>
          <?php if (count($news) > 2): ?>
            <details class="fb-more"><summary class="small"><?= te('Rodyti ankstesnius') ?> (<?= count($news) - 2 ?>)</summary><?= render_notifications(array_slice($news, 2), 'admin/#svarbu') ?></details>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if ($fbNotifs): ?>
        <div class="fb-replies">
          <div class="row between" style="gap:8px;flex-wrap:wrap;"><span class="small muted"><?= te('💡 Atsakymai į jūsų atsiliepimus') ?> (<?= count($fbNotifs) ?>)</span>
            <form method="post" action="<?= url('pranesimas.php') ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="done_feedback"><input type="hidden" name="back" value="admin/#svarbu">
              <button class="btn btn-ghost btn-sm" type="submit"><?= te('Supratau visus') ?></button></form></div>
          <?php // Tvarka iš viršaus žemyn 1, 2, 3 (#59), bet matomi 2 naujausi (#47) - senesni išskleidžiami virš jų ?>
          <?php if (count($fbNotifs) > 2): ?>
            <details class="fb-more"><summary class="small"><?= te('Rodyti ankstesnius') ?> (<?= count($fbNotifs) - 2 ?>)</summary><?= render_notifications(array_slice($fbNotifs, 0, -2), 'admin/#svarbu') ?></details>
          <?php endif; ?>
          <?= render_notifications(array_slice($fbNotifs, -2), 'admin/#svarbu') ?>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <div class="dash-side">
    <!-- Treniruotės - vienoje vietoje (skirtukas „Treniruotė“); čia tik trumpai, kiek jų šiandien -->
    <?php $tMarked = count(array_filter($trainings, function ($t) { return (int) $t['marked'] > 0; }));
      $tOff = count(array_filter($trainings, function ($t) use ($today) { return (bool) training_cancelled((int) $t['group_id'], $today); })); ?>
    <a class="panel card today-trainings" href="<?= url('admin/treniruote.php') ?>">
      <span class="kicker" style="margin:0;"><?= te('Treniruotės') ?></span>
      <?php if ($trainings): ?>
        <span class="tt-line"><b><?= te('Šiandien: {n}', ['n' => count($trainings)]) ?></b><?= $tMarked ? ' · ' . te('pažymėta {n}', ['n' => $tMarked]) : '' ?><?= $tOff ? ' · ' . te('atšaukta {n}', ['n' => $tOff]) : '' ?></span>
      <?php else: ?>
        <span class="tt-line muted"><?= te('Šiandien treniruočių nėra.') ?><?php if ($nextDay): ?> <?= te('Artimiausios -') ?> <?= e(mb_strtolower(weekday_names()[(int) date('N', strtotime($nextDay))])) ?>, <?= e(fmt_date($nextDay)) ?><?php endif; ?></span>
      <?php endif; ?>
      <span class="btn btn-primary btn-sm"><?= te('🥋 Lankomumas →') ?></span>
    </a>

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
