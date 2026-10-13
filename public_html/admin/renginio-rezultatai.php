<?php
// Renginio rezultatai: pažymite, kas dalyvavo / išlaikė / kokią vietą užėmė - taškai skiriami automatiškai
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

$id = (int) (get('id') ?: post('id'));
$event = q_one('SELECT * FROM events WHERE id = ?', [$id]);
if (!$event || !isset(EVENT_RESULTS[$event['type']])) {
    not_found();
}
$options = EVENT_RESULTS[$event['type']];

if (is_post()) {
    csrf_check();
    $results = [];
    foreach ((array) ($_POST['result'] ?? []) as $mid => $r) {
        $r = is_string($r) ? $r : '';
        $results[(int) $mid] = isset($options[$r]) ? $r : '';
    }
    $changes = save_event_results($event, $results, (int) $me['id']);
    flash('ok', $changes ? 'Rezultatai išsaugoti, taškai atnaujinti.' : 'Pakeitimų nebuvo.');
    redirect('admin/renginio-rezultatai.php?id=' . $id);
}

// Nariai iš renginio grupių (jei renginys visiems - visi aktyvūs) + visi, kurie jau turi rezultatą
$groupIds = q('SELECT group_id FROM event_groups WHERE event_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN);
$current = event_results($event);
$sql = 'SELECT m.*, g.name AS group_name, g.sort_order FROM members m LEFT JOIN training_groups g ON g.id = m.group_id
         WHERE (m.status = "active"' . ($groupIds ? ' AND m.group_id IN (' . implode(',', array_map('intval', $groupIds)) . ')' : '') . ')'
     . ($current ? ' OR m.id IN (' . implode(',', array_map('intval', array_keys($current))) . ')' : '')
     . ' ORDER BY g.sort_order, m.last_name, m.first_name';
$members = q_all($sql);

$byGroup = [];
foreach ($members as $m) {
    $byGroup[$m['group_name'] ?? 'Be grupės'][] = $m;
}

page_start('Rezultatai: ' . $event['title'], ['admin' => true]);
?>
<a class="small" href="<?= url('admin/renginiai.php') ?>">← Renginiai</a>
<div class="page-head" style="margin-top:8px;">
  <div><span class="badge badge-pink"><?= e(EVENT_TYPES[$event['type']]) ?><?= $event['type'] === 'competition' ? ($event['is_abroad'] ? ' · užsienyje' : ' · Lietuvoje') : '' ?></span></div>
  <h1 class="styled" style="margin-top:8px;"><?= e($event['title']) ?></h1>
  <p><?= e(fmt_date($event['starts_on'], true)) ?><?= $event['location'] ? ' · ' . e($event['location']) : '' ?></p>
</div>

<div class="panel card small" style="padding:16px 20px;">
  Taškai:
  <?php foreach ($options as $key => $label):
      $sum = 0;
      foreach (result_category_codes($event, (string) $key) as $code) {
          $sum += (int) category_by_code($code)['points'];
      } ?>
    <span class="badge"><?= e($label) ?>: <?= $sum ?></span>
  <?php endforeach; ?>
  <?php if ($event['type'] === 'competition'): ?><div class="hint" style="margin-top:6px;">Prizinė vieta = dalyvavimo + vietos taškai.</div><?php endif; ?>
</div>

<?php if (!$members): ?>
  <div class="panel card"><p class="muted">Šiai grupei aktyvių narių nėra.</p></div>
<?php else: ?>
<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <?php foreach ($byGroup as $groupName => $list): ?>
    <div class="panel card">
      <h2><?= e($groupName) ?></h2>
      <?php foreach ($list as $m): $cur = $current[$m['id']] ?? ''; ?>
        <div class="result-row">
          <span class="who"><?= e($m['first_name'] . ' ' . $m['last_name']) ?></span>
          <div class="seg">
            <label><input type="radio" name="result[<?= (int) $m['id'] ?>]" value="" <?= $cur === '' ? 'checked' : '' ?>><span>—</span></label>
            <?php foreach ($options as $key => $label): ?>
              <label><input type="radio" name="result[<?= (int) $m['id'] ?>]" value="<?= e((string) $key) ?>" <?= $cur === (string) $key ? 'checked' : '' ?>><span><?= e($label) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  <div class="sticky-save"><button class="btn btn-primary btn-block" type="submit">Išsaugoti rezultatus</button></div>
</form>
<?php endif; ?>
<?php
page_end();
