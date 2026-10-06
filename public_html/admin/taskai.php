<?php
// Taškų kategorijos (vertes keičia administratorius), reitingas ir paskutiniai skyrimai
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

if (is_post()) {
    csrf_check();
    $action = post('action');

    if ($action === 'save_categories' && is_admin()) {
        foreach ((array) ($_POST['name'] ?? []) as $id => $name) {
            $name = is_string($name) ? trim($name) : '';
            if ($name === '') {
                continue;
            }
            q('UPDATE point_categories SET name = ?, points = ?, is_active = ? WHERE id = ?',
                [$name, (int) ($_POST['points'][$id] ?? 0), !empty($_POST['active'][$id]) ? 1 : 0, (int) $id]);
        }
        flash('ok', 'Taškų vertės išsaugotos. Jos galioja naujiems skyrimams.');
    }

    if ($action === 'add_category' && is_admin() && post('new_name') !== '') {
        q('INSERT INTO point_categories (name, points, sort_order) VALUES (?, ?, 100)', [post('new_name'), (int) post('new_points')]);
        flash('ok', 'Kategorija pridėta.');
    }

    if ($action === 'delete_award') {
        q('DELETE FROM point_awards WHERE id = ?', [(int) post('award_id')]);
        flash('ok', 'Taškai pašalinti.');
    }
    redirect('admin/taskai.php');
}

[$from, $to, $seasonLabel] = season_bounds();
$categories = q_all('SELECT * FROM point_categories ORDER BY sort_order, id');
$recent = q_all('SELECT pa.*, pc.name AS category_name, m.first_name, m.last_name, e.title AS event_title
                   FROM point_awards pa
                   JOIN point_categories pc ON pc.id = pa.category_id
                   JOIN members m ON m.id = pa.member_id
                   LEFT JOIN events e ON e.id = pa.event_id
                  ORDER BY pa.created_at DESC, pa.id DESC LIMIT 30');

// Reitingai pagal pasirinktą padalijimą
$partitions = [];
switch (ranking_scope()) {
    case 'club':
        $partitions[] = ['club', null, 'Visas klubas'];
        break;
    case 'group':
        foreach (q_all('SELECT * FROM training_groups WHERE is_active = 1 ORDER BY sort_order') as $g) {
            $partitions[] = ['group', (int) $g['id'], $g['name']];
        }
        break;
    default:
        foreach (GROUP_CATEGORIES as $k => $label) {
            $partitions[] = ['category', $k, $label];
        }
}

page_start('Taškai', ['admin' => true]);
?>
<div class="page-head">
  <h1 class="styled">Taškai ir reitingas</h1>
  <p><?= e($seasonLabel) ?> (<?= e(fmt_date($from, true)) ?> – <?= e(fmt_date($to, true)) ?>)</p>
</div>

<div class="grid-2">
  <div class="stack">
    <?php foreach ($partitions as $p): $rows = ranking($p, $from, $to); if (!$rows && ranking_scope() === 'group') continue; ?>
      <div class="panel card">
        <h2><?= e($p[2]) ?></h2>
        <?php if (!$rows): ?><p class="muted small">Šį sezoną taškų dar nėra.</p><?php endif; ?>
        <ol class="ranking">
          <?php foreach ($rows as $r): ?>
            <li>
              <span class="pos r<?= (int) $r['rank'] ?>"><?= (int) $r['rank'] ?></span>
              <a href="<?= url('admin/nariai.php?id=' . (int) $r['member_id']) ?>"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></a>
              <span class="pts"><?= (int) $r['total'] ?></span>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="stack">
    <form method="post" class="panel card form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_categories">
      <h2>Už ką skiriami taškai</h2>
      <p class="hint">Pakeista vertė galioja tik naujiems skyrimams - anksčiau skirti taškai nesikeičia.<?= is_admin() ? '' : ' Keisti gali administratorius.' ?></p>
      <table class="table">
        <thead><tr><th>Kategorija</th><th style="width:80px;">Taškai</th><th style="width:40px;" title="Aktyvi">✓</th></tr></thead>
        <tbody>
          <?php foreach ($categories as $c): ?>
            <tr>
              <td><input type="text" name="name[<?= (int) $c['id'] ?>]" value="<?= e($c['name']) ?>" style="padding:8px 10px; font-size:14px;" <?= is_admin() ? '' : 'disabled' ?>></td>
              <td><input type="number" name="points[<?= (int) $c['id'] ?>]" value="<?= (int) $c['points'] ?>" style="padding:8px 10px; font-size:14px;" <?= is_admin() ? '' : 'disabled' ?>></td>
              <td><input type="checkbox" name="active[<?= (int) $c['id'] ?>]" value="1" <?= $c['is_active'] ? 'checked' : '' ?> <?= is_admin() ? '' : 'disabled' ?>></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (is_admin()): ?><div><button class="btn btn-primary" type="submit">Išsaugoti vertes</button></div><?php endif; ?>
    </form>

    <?php if (is_admin()): ?>
      <form method="post" class="panel card form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_category">
        <h2>Nauja kategorija</h2>
        <div class="form-row" style="grid-template-columns:2fr 1fr;">
          <label>Pavadinimas <input type="text" name="new_name" required></label>
          <label>Taškai <input type="number" name="new_points" value="5" required></label>
        </div>
        <div><button class="btn btn-ghost" type="submit">Pridėti</button></div>
      </form>
    <?php endif; ?>

    <div class="panel card">
      <h2>Paskutiniai skyrimai</h2>
      <?php if (!$recent): ?><p class="muted small">Taškų dar neskirta.</p><?php endif; ?>
      <ul class="list small">
        <?php foreach ($recent as $r): ?>
          <li class="row between">
            <span>
              <strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong> +<?= (int) $r['points'] ?>
              <div class="muted"><?= e($r['category_name']) ?><?= $r['event_title'] ? ' · ' . e($r['event_title']) : '' ?><?= $r['note'] ? ' · ' . e($r['note']) : '' ?> · <?= e(fmt_date($r['awarded_on'])) ?></div>
            </span>
            <form method="post" class="inline-form" onsubmit="return confirm('Pašalinti šiuos taškus?')">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete_award"><input type="hidden" name="award_id" value="<?= (int) $r['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit" aria-label="Pašalinti">✕</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>
<?php
page_end();
