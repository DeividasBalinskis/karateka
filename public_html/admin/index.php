<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

$pending = pending_approvals_count();
$members = (int) q_value('SELECT COUNT(*) FROM members WHERE status = "active"');
$events = count(upcoming_events(null, 100));
$news = (int) q_value('SELECT COUNT(*) FROM news');
$waiting = (int) q_value('SELECT COUNT(*) FROM accounts WHERE status IN ("pending_email", "pending_parent")');

page_start('Treneriams', ['admin' => true]);
?>
<div class="page-head">
  <div class="eyebrow">Trenerio panelė</div>
  <h1 class="styled">Labas, <?= e($me['first_name']) ?></h1>
</div>

<div class="tiles">
  <a class="tile" href="<?= url('admin/treniruote.php') ?>">
    <div class="num">✓</div><div class="label">Treniruotė: lankomumas ir pastabos</div>
  </a>
  <a class="tile <?= $pending ? 'alert' : '' ?>" href="<?= url('admin/patvirtinimai.php') ?>">
    <div class="num"><?= $pending ?></div><div class="label">Laukia patvirtinimo</div>
  </a>
  <a class="tile" href="<?= url('admin/nariai.php') ?>">
    <div class="num"><?= $members ?></div><div class="label">Aktyvūs nariai</div>
  </a>
  <a class="tile" href="<?= url('admin/renginiai.php') ?>">
    <div class="num"><?= $events ?></div><div class="label">Artėjantys renginiai</div>
  </a>
  <a class="tile" href="<?= url('admin/naujienos.php?edit=new') ?>">
    <div class="num">+</div><div class="label">Nauja naujiena</div>
  </a>
  <a class="tile" href="<?= url('admin/naujienos.php') ?>">
    <div class="num"><?= $news ?></div><div class="label">Naujienos</div>
  </a>
  <a class="tile" href="<?= url('admin/grupes.php') ?>">
    <div class="num"><?= (int) q_value('SELECT COUNT(*) FROM training_groups WHERE is_active = 1') ?></div><div class="label">Grupės ir tvarkaraštis</div>
  </a>
</div>

<?php if ($waiting): ?>
  <p class="muted small" style="margin-top:18px;"><?= $waiting ?> registracijos dar laukia el. pašto arba tėvų patvirtinimo (jų tvirtinti nereikia).</p>
<?php endif; ?>
<?php
page_end();
