<?php
// Mano paskyra → „📋 Užduotys“: VISOS trenerio užduotys, atsiųstos pamokos ir pastabos (ir senos, ir jau pažymėtos „Peržiūrėjau“ / „Atlikau“),
// kad vaikas netyčia paspaudęs nepamestų. Žemiau - visi pranešimai (ir tie, kur paspausta „Supratau“). Filtrai: Neatliktos, Pamokos, Pastabos.
require __DIR__ . '/app/bootstrap.php';

$a = require_login();
$aid = (int) $a['id'];
$members = array_values(array_filter(account_members($aid), function ($m) { return $m['status'] === 'active'; }));
if (!$members) {
    redirect('paskyra.php');
}
$selected = $members[0];
foreach ($members as $m) {
    if ((int) $m['id'] === (int) get('m')) {
        $selected = $m;
    }
}
$mid = (int) $selected['id'];
$filter = in_array(get('f'), ['neatliktos', 'pamokos', 'pastabos', 'pranesimai'], true) ? get('f') : 'visos';

$where = ['cn.member_id = ?'];
if ($filter === 'neatliktos') {
    $where[] = '((cn.is_task = 1 AND cn.done_at IS NULL) OR (cn.is_task = 0 AND cn.read_at IS NULL))';
} elseif ($filter === 'pamokos') {
    $where[] = 'cn.lesson_id IS NOT NULL';
} elseif ($filter === 'pastabos') {
    $where[] = 'cn.lesson_id IS NULL';
}
$notes = $filter === 'pranesimai' ? [] : q_all('SELECT cn.*, a.first_name AS author, l.title AS lesson_title, g.name AS source_group, e.title AS source_event FROM coach_notes cn
    LEFT JOIN accounts a ON a.id = cn.author_id LEFT JOIN lessons l ON l.id = cn.lesson_id
    LEFT JOIN training_groups g ON g.id = cn.group_id LEFT JOIN events e ON e.id = cn.event_id
    WHERE ' . implode(' AND ', $where) . ' ORDER BY ((cn.is_task = 1 AND cn.done_at IS NULL) OR (cn.is_task = 0 AND cn.read_at IS NULL)) DESC, cn.note_date DESC, cn.id DESC LIMIT 200', [$mid]);
$counts = q_one('SELECT COUNT(*) AS n, SUM((is_task = 1 AND done_at IS NULL) OR (is_task = 0 AND read_at IS NULL)) AS open, SUM(lesson_id IS NOT NULL) AS lessons FROM coach_notes WHERE member_id = ?', [$mid]);
// Visi pranešimai apie šį narį (ir išspręsti) - naujausi viršuje
$notifs = [];
if ($filter === 'visos' || $filter === 'pranesimai') {
    try {
        $notifs = q_all('SELECT * FROM notifications WHERE account_id = ? AND (member_id IS NULL OR member_id = ?) AND type <> "feedback" ORDER BY created_at DESC, id DESC LIMIT ' . ($filter === 'pranesimai' ? 200 : 20), [$aid, $mid]);
    } catch (PDOException $ex) {
        $notifs = [];
    }
}
$back = 'uzduotys.php?m=' . $mid . ($filter !== 'visos' ? '&f=' . $filter : '');

page_start(t('Užduotys'), ['noindex' => true, 'account' => 'uzduotys']);
?>
<div class="page-head">
  <h1 class="styled"><?= te('📋 Užduotys') ?></h1>
  <p><?= te('Visos trenerio užduotys, atsiųstos pamokos ir pastabos - čia jos lieka visada, net pažymėjus „Peržiūrėjau“ ar „Atlikau“.') ?></p>
</div>
<?php if (count($members) > 1): ?>
  <div class="member-tabs" style="margin-bottom:12px;">
    <?php foreach ($members as $m): ?>
      <a href="?m=<?= (int) $m['id'] ?><?= $filter !== 'visos' ? '&amp;f=' . $filter : '' ?>" class="<?= (int) $m['id'] === $mid ? 'active' : '' ?>"><?= e(member_short_name($m, $members)) ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<nav class="chip-tabs task-tabs" aria-label="<?= te('Rodyti') ?>">
  <?php foreach (['visos' => [t('Visos'), (int) $counts['n']], 'neatliktos' => [t('Neatliktos'), (int) $counts['open']], 'pamokos' => [t('Pamokos'), (int) $counts['lessons']],
                  'pastabos' => [t('Pastabos ir užduotys'), (int) $counts['n'] - (int) $counts['lessons']], 'pranesimai' => [t('Pranešimai'), null]] as $k => [$label, $n]): ?>
    <a class="chip-tab<?= $filter === $k ? ' active' : '' ?>" href="?m=<?= $mid ?><?= $k !== 'visos' ? '&amp;f=' . $k : '' ?>"><?= e($label) ?><?= $n !== null ? ' <span class="chip-n">' . $n . '</span>' : '' ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($filter !== 'pranesimai'): ?>
  <?php if (!$notes): ?>
    <p class="panel card muted"><?= $filter === 'neatliktos' ? te('Viskas atlikta! 🎉') : te('Kol kas nieko nėra - kai treneris atsiųs užduotį ar pamoką, ji bus čia.') ?></p>
  <?php endif; ?>
  <div class="task-list">
    <?php foreach ($notes as $n):
      $isLesson = $n['lesson_id'] && $n['lesson_title'];
      $open = $n['is_task'] ? !$n['done_at'] : $n['read_at'] === null; ?>
      <article class="panel card task-item<?= $open ? ' is-open' : '' ?>" id="n<?= (int) $n['id'] ?>">
        <div class="task-ic" aria-hidden="true"><?= $isLesson ? '📘' : ($n['is_task'] ? '✏️' : '💬') ?></div>
        <div class="task-body">
          <div class="task-top">
            <b><?= $isLesson ? te('Pamoka') : ($n['is_task'] ? te('Užduotis') : te('Pastaba')) ?></b>
            <?php if ($n['is_task']): ?>
              <?= $n['done_at'] ? '<span class="badge badge-ok">' . te('✓ Atlikta') . ' ' . e(fmt_date(substr($n['done_at'], 0, 10))) . '</span>' : '<span class="badge badge-warn">' . te('Neatlikta') . '</span>' ?>
            <?php else: ?>
              <?= $n['read_at'] ? '<span class="badge">' . ($isLesson ? te('✓ Peržiūrėta') : te('✓ Perskaityta')) . '</span>' : '<span class="badge badge-new">' . te('Nauja') . '</span>' ?>
            <?php endif; ?>
            <span class="small muted"><?= note_source($n) ?><?= $n['author'] ? ' · ' . e($n['author']) : '' ?></span>
          </div>
          <?php if ($isLesson): ?><a class="task-lesson" href="<?= url('pamokos.php?id=' . (int) $n['lesson_id']) ?>">📘 <?= e($n['lesson_title']) ?> →</a><?php endif; ?>
          <?php if (trim((string) $n['body']) !== ''): ?><div class="task-text"><?= text_to_html($n['body']) ?></div><?php endif; ?>
          <?php if ($n['youtube_id']): ?><?= youtube_embed($n['youtube_id']) ?><?php endif; ?>
          <form method="post" action="<?= url('paskyra.php') ?>" class="inline-form task-acts">
            <?= csrf_field() ?><input type="hidden" name="member_id" value="<?= $mid ?>"><input type="hidden" name="note_id" value="<?= (int) $n['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
            <?php if ($n['is_task']): ?>
              <?php if (!$n['done_at']): ?><button class="btn btn-primary btn-sm" name="action" value="task_done"><?= te('✓ Atlikau') ?></button>
              <?php else: ?><button class="btn btn-ghost btn-sm" name="action" value="task_undo"><?= te('Grąžinti į neatliktas') ?></button><?php endif; ?>
            <?php elseif (!$n['read_at']): ?>
              <button class="btn btn-ghost btn-sm" name="action" value="note_read"><?= $isLesson ? te('✓ Peržiūrėjau') : te('✓ Perskaičiau') ?></button>
            <?php endif; ?>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($notifs): ?>
  <section class="panel card task-notifs">
    <div class="kicker"><?= te('Pranešimai') ?><?= $filter === 'visos' ? ' · ' . te('naujausi') : '' ?></div>
    <ul class="notif-list">
      <?php foreach ($notifs as $n): ?>
        <li class="notif<?= !$n['read_at'] && !$n['done_at'] && $n['important'] ? ' is-new' : '' ?> t-<?= e($n['type']) ?>"><span class="notif-icon" aria-hidden="true"><?= notify_icon((string) $n['type']) ?></span>
          <div class="notif-body"><div class="notif-title"><?= $n['link'] ? '<a href="' . e(url($n['link'])) . '">' . e($n['title']) . '</a>' : e($n['title']) ?></div>
            <?= $n['body'] ? '<div class="notif-text">' . nl2br(e($n['body'])) . '</div>' : '' ?>
            <div class="notif-meta"><?= e(notification_when($n['created_at'])) ?><?= $n['done_at'] ? ' · ' . te('✓ pažymėta „Supratau“') : '' ?></div></div></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($filter === 'visos'): ?><a class="small" href="?m=<?= $mid ?>&amp;f=pranesimai"><?= te('Visi pranešimai →') ?></a><?php endif; ?>
  </section>
<?php endif; ?>
<?php
page_end();
