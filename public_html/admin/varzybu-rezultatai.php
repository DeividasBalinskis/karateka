<?php
// Varžybų rezultatai iš varžybų sistemos: susieti renginį su varžybomis ir įkelti vietas (taškai skiriami automatiškai)
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

$id = (int) (get('id') ?: post('id'));
$event = q_one('SELECT * FROM events WHERE id = ? AND type = "competition"', [$id]);
if (!$event) {
    not_found();
}

if (is_post()) {
    csrf_check();
    if (post('action') === 'link') {
        q('UPDATE events SET tournament_id = ? WHERE id = ?', [(int) post('tournament_id') ?: null, $id]);
        if ((int) post('tournament_id')) {
            flash('ok', 'Renginys susietas su varžybomis.');
            redirect('admin/varzybu-dalyviai.php?id=' . $id);
        }
        flash('ok', 'Renginys atsietas nuo varžybų.');
    } elseif (post('action') === 'import' && $event['tournament_id']) {
        try {
            $data = varzybos_event_results((int) $event['tournament_id']);
            $results = [];
            foreach ($data['members'] as $mid => $r) {
                if (q_value('SELECT 1 FROM members WHERE id = ?', [$mid])) {
                    $results[(int) $mid] = $r['result'];
                }
            }
            $changes = save_event_results($event, $results, (int) $me['id']);
            flash('ok', $changes ? 'Rezultatai įkelti, taškai paskirti (' . count($results) . ' nariai).' : 'Pakeitimų nebuvo - rezultatai jau įkelti.');
            redirect('admin/renginio-rezultatai.php?id=' . $id);
        } catch (RuntimeException $ex) {
            flash('err', $ex->getMessage());
        }
    }
    redirect('admin/varzybu-rezultatai.php?id=' . $id);
}

$error = null;
$tournaments = [];
$data = null;
try {
    if ($event['tournament_id']) {
        $data = varzybos_event_results((int) $event['tournament_id']);
    } else {
        $tournaments = varzybos_api('GET', 'tournaments')['tournaments'] ?? [];
    }
} catch (RuntimeException $ex) {
    $error = $ex->getMessage();
}
$current = event_results($event);
$labels = EVENT_RESULTS['competition'];

page_start('Varžybų rezultatai: ' . $event['title'], ['admin' => true]);
?>
<a class="small" href="<?= url('admin/varzybos.php') ?>">← Varžybos</a> · <a class="small" href="<?= url('admin/renginio-rezultatai.php?id=' . $id) ?>">Renginio rezultatai</a><?= $event['tournament_id'] ? ' · <a class="small" href="' . e(url('admin/varzybu-dalyviai.php?id=' . $id)) . '">Dalyviai → varžybos</a>' : '' ?>
<div class="page-head" style="margin-top:8px;">
  <div><span class="badge badge-pink">Varžybos<?= $event['is_abroad'] ? ' · užsienyje' : ' · Lietuvoje' ?></span></div>
  <h1 class="styled" style="margin-top:8px;"><?= e($event['title']) ?></h1>
  <p>Rezultatai iš varžybų sistemos. Kiekvienam nariui imama geriausia vieta; taškai skiriami kaip ir įvedant ranka.</p>
</div>

<?php if ($error): ?>
  <div class="flash flash-err"><?= e($error) ?></div>
<?php endif; ?>

<?php if (!$event['tournament_id']): ?>
  <div class="panel card">
    <h2>Susieti su varžybomis</h2>
    <?php if ($tournaments): ?>
      <form method="post" class="form">
        <?= csrf_field() ?><input type="hidden" name="action" value="link"><input type="hidden" name="id" value="<?= $id ?>">
        <label>Varžybos varžybų sistemoje
          <select name="tournament_id" required>
            <option value="">— pasirinkite —</option>
            <?php foreach ($tournaments as $t): ?>
              <option value="<?= (int) $t['id'] ?>" <?= $t['starts_on'] === $event['starts_on'] ? 'selected' : '' ?>><?= e($t['title'] . ' · ' . fmt_date($t['starts_on'], true)) ?></option>
            <?php endforeach; ?>
          </select></label>
        <div><button class="btn btn-primary" type="submit">Susieti</button></div>
      </form>
    <?php elseif (!$error): ?>
      <p class="muted">Varžybų sistemoje varžybų nėra.</p>
    <?php endif; ?>
  </div>
<?php elseif ($data): ?>
  <?php if ($data['unfinished']): ?>
    <div class="flash flash-info">Dar nebaigtos kategorijos: <?= e(implode(', ', $data['unfinished'])) ?>. Jų vietas galėsite įkelti vėliau (įkėlus dar kartą, pasikeitę rezultatai atsinaujins).</div>
  <?php endif; ?>
  <div class="panel card">
    <div class="row between"><h2 style="margin:0;"><?= e($data['tournament']['title'] ?? '') ?></h2>
      <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="link"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="tournament_id" value="0">
        <button class="linklike small" style="border:none; color:var(--muted);" type="submit">atsieti</button></form></div>
    <?php if (!$data['members']): ?>
      <p class="muted" style="margin-top:10px;">Klubo narių šiose varžybose nėra.</p>
    <?php else: ?>
      <table class="table cards" style="margin-top:12px;">
        <thead><tr><th>Narys</th><th>Kategorijos ir vietos</th><th>Rezultatas</th><th>Dabar įrašyta</th></tr></thead>
        <tbody>
        <?php $unknown = 0;
        foreach ($data['members'] as $mid => $r):
            $m = q_one('SELECT first_name, last_name FROM members WHERE id = ?', [$mid]);
            if (!$m) {
                $unknown++;
                continue;
            } ?>
          <tr>
            <td><strong><?= e($m['first_name'] . ' ' . $m['last_name']) ?></strong></td>
            <td class="small"><?php foreach ($r['details'] as $cat => $place): ?><?= e($cat) ?>: <strong><?= $place ? $place . ' vieta' : (empty($data['finished'][$cat]) ? 'dar vyksta' : '—') ?></strong><br><?php endforeach; ?></td>
            <td><span class="badge <?= $r['result'] === 'part' ? '' : 'badge-ok' ?>"><?= e($labels[$r['result']]) ?></span></td>
            <td class="small muted"><?= isset($current[$mid]) ? e($labels[$current[$mid]] ?? $current[$mid]) : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($unknown): ?><p class="small muted" style="margin-top:8px;">Praleista sportininkų, kurių nėra tarp narių: <?= $unknown ?>.</p><?php endif; ?>
      <form method="post" style="margin-top:14px;">
        <?= csrf_field() ?><input type="hidden" name="action" value="import"><input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-primary" type="submit">Įkelti rezultatus ir skirti taškus</button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php
page_end();
