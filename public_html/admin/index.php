<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

$pending = pending_approvals_count();
$members = (int) q_value('SELECT COUNT(*) FROM members WHERE status = "active"');
$events = count(upcoming_events(null, 100));
$news = (int) q_value('SELECT COUNT(*) FROM news');
$waiting = (int) q_value('SELECT COUNT(*) FROM accounts WHERE status IN ("pending_email", "pending_parent")');

// Paskyros nustatymai (el. paštas, telefonas, slaptažodis) - šiame puslapyje
$errors = [];
if (is_post()) {
    csrf_check();
    handle_account_settings($me, 'admin/', $errors);
}
$settingsOpen = (bool) $errors;
// Savo vaikai (ar pats), jei treniruojasi - jų pastabos ir taškai „Mano paskyroje“
$ownMembers = (int) q_value('SELECT COUNT(*) FROM account_members am JOIN members m ON m.id = am.member_id WHERE am.account_id = ? AND m.status = "active"', [(int) $me['id']]);

page_start('Paskyra', ['admin' => true]);
?>
<div class="page-head row between" style="align-items:flex-end; flex-wrap:wrap; gap:12px;">
  <div>
    <div class="eyebrow">Trenerio panelė</div>
    <h1 class="styled">Labas, <?= e($me['first_name']) ?></h1>
  </div>
  <div class="row" style="gap:8px;">
    <button type="button" class="btn btn-ghost btn-sm" id="settingsBtn" aria-expanded="<?= $settingsOpen ? 'true' : 'false' ?>" aria-controls="settingsPanel">⚙ Paskyros nustatymai</button>
    <form method="post" action="<?= url('atsijungti.php') ?>" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn btn-ghost btn-sm">Atsijungti</button></form>
  </div>
</div>
<?= form_errors($errors) ?>
<!-- El. paštas, telefonas, slaptažodis - čia pat, paspaudus „Paskyros nustatymai“ -->
<div id="settingsPanel" style="margin:-8px 0 24px;" <?= $settingsOpen ? '' : 'hidden' ?>><?= render_account_settings($me) ?></div>
<script>
document.getElementById('settingsBtn').addEventListener('click', function () {
  var p = document.getElementById('settingsPanel'), open = p.hasAttribute('hidden');
  if (open) { p.removeAttribute('hidden'); } else { p.setAttribute('hidden', ''); }
  this.setAttribute('aria-expanded', open ? 'true' : 'false');
});
</script>
<?php if ($ownMembers): ?>
  <p class="small" style="margin:-8px 0 18px;"><a href="<?= url('paskyra.php') ?>">Mano šeimos nariai (taškai, pastabos) →</a></p>
<?php endif; ?>

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
