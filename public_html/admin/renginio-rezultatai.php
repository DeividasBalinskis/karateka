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
    if (!is_admin($me)) {
        forbidden();   // galutinius rezultatus ir taškus tvirtina Denis (adminas)
    }
    $results = [];
    foreach ((array) ($_POST['result'] ?? []) as $mid => $r) {
        $r = is_string($r) ? $r : '';
        $results[(int) $mid] = isset($options[$r]) ? $r : '';
    }
    $before = event_results($event);
    $changes = save_event_results($event, $results, (int) $me['id']);
    // Egzaminas: naujai išlaikiusiems - diržas vienu aukščiau (jei pažymėta)
    if ($event['type'] === 'exam' && !empty($_POST['raise_belt'])) {
        $raised = 0;
        foreach ($results as $mid => $r) {
            if ($r === 'yes' && ($before[$mid] ?? '') !== 'yes') {
                $m = q_one('SELECT belt_level FROM members WHERE id = ?', [$mid]);
                $nb = next_belt($m && $m['belt_level'] !== null ? (int) $m['belt_level'] : null);
                if ($nb) {
                    q('UPDATE members SET belt_level = ? WHERE id = ?', [$nb, $mid]);
                    $raised++;
                }
            }
        }
        if ($raised) {
            flash('ok', "Diržas pakeltas: $raised nar.");
        }
    }
    // Išlaikiusiems egzaminą - šventė ekrane (fejerverkai) ir pranešimas paskyroje
    if ($event['type'] === 'exam') {
        foreach ($results as $mid => $r) {
            if ($r === 'yes' && ($before[$mid] ?? '') !== 'yes') {
                $m = q_one('SELECT first_name, belt_level FROM members WHERE id = ?', [$mid]);
                notify_member((int) $mid, 'exam_passed', ($m['first_name'] ?? '') . ' išlaikė egzaminą!',
                    $event['title'] . ($m && $m['belt_level'] !== null ? ' · ' . belt_label((int) $m['belt_level']) : ''), 'egzaminas.php?m=' . (int) $mid, (int) $event['id']);
            }
        }
    }
    flash('ok', $changes ? 'Rezultatai išsaugoti, taškai atnaujinti.' : 'Pakeitimų nebuvo.');
    redirect('admin/renginio-rezultatai.php?id=' . $id);
}

// Nariai iš renginio grupių (jei renginys visiems - visi aktyvūs) + visi, kurie jau turi rezultatą.
// Jei renginyje yra užsiregistravusių dalyvių - rodomi tik jie (ir jau turintys rezultatą), nebent pasirinkta „rodyti visus“.
$groupIds = q('SELECT group_id FROM event_groups WHERE event_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN);
$current = event_results($event);
$going = array_map('intval', q('SELECT member_id FROM event_participants WHERE event_id = ? AND status IN ("going", "late")', [$id])->fetchAll(PDO::FETCH_COLUMN));
$onlyGoing = $going && !get('visi');
$where = $onlyGoing
    ? 'm.id IN (' . implode(',', $going) . ')'
    : '(m.status = "active"' . ($groupIds ? ' AND m.group_id IN (' . implode(',', array_map('intval', $groupIds)) . ')' : '') . ')';
$sql = 'SELECT m.*, g.name AS group_name, g.sort_order FROM members m LEFT JOIN training_groups g ON g.id = m.group_id
         WHERE ' . $where
     . ($current ? ' OR m.id IN (' . implode(',', array_map('intval', array_keys($current))) . ')' : '')
     . ' ORDER BY g.sort_order, m.last_name, m.first_name';
$members = q_all($sql);

$byGroup = [];
foreach ($members as $m) {
    $byGroup[$m['group_name'] ?? 'Be grupės'][] = $m;
}

page_start('Rezultatai: ' . $event['title'], ['admin' => true, 'section' => 'renginiai']);
?>
<a class="small" href="<?= url('renginiai.php#e' . $id) ?>">← Renginiai</a> · <a class="small" href="<?= url('admin/renginio-dalyviai.php?id=' . $id) ?>">Dalyviai</a>
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

<?php if ($event['type'] === 'competition' && varzybos_enabled()): ?>
  <div class="panel card small row between" style="padding:16px 20px; gap:10px;">
    <span>Varžybos vyko varžybų sistemoje? Vietas galima įkelti automatiškai.</span>
    <a class="btn btn-primary btn-sm" href="<?= url('admin/varzybu-rezultatai.php?id=' . $id) ?>">Įkelti iš varžybų sistemos →</a>
  </div>
<?php endif; ?>

<?php if ($going): ?>
  <p class="small no-print" style="margin:-6px 0 16px;">
    <?= $onlyGoing ? 'Rodomi tik užsiregistravę dalyviai (' . count($going) . '). <a href="?id=' . $id . '&amp;visi=1">Rodyti visus grupių narius</a>'
        : 'Rodomi visi grupių nariai. <a href="?id=' . $id . '">Rodyti tik dalyvius</a>' ?>
  </p>
<?php endif; ?>

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
  <?php if ($event['type'] === 'exam'): ?>
    <label class="raise-belt"><input type="checkbox" name="raise_belt" value="1"><span>Naujai išlaikiusiems pakelti diržą vienu lygiu <span class="small muted">(pvz. 9 kyu → 8 kyu; nariams be diržo - geltonas)</span></span></label>
  <?php endif; ?>
  <?php if (is_admin($me)): ?>
  <div class="sticky-save">
    <button class="btn btn-primary btn-block" type="submit">Išsaugoti rezultatus</button>
  </div>
  <?php else: ?><p class="small muted">Rezultatus ir taškus tvirtina administratorius.</p><?php endif; ?>
</form>
<?php endif; ?>
<?php
page_end();
