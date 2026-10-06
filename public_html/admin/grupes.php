<?php
// Grupės ir jų savaitinis tvarkaraštis
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

$id = (int) (get('id') ?: post('id'));
$errors = [];

function group_from_post(array &$errors): array
{
    $g = [
        'name'       => post('name'),
        'category'   => post('category'),
        'location'   => post('location') ?: null,
        'sort_order' => (int) post('sort_order'),
        'is_active'  => !empty($_POST['is_active']) ? 1 : 0,
    ];
    if ($g['name'] === '') {
        $errors[] = 'Įveskite grupės pavadinimą.';
    }
    if (!isset(GROUP_CATEGORIES[$g['category']])) {
        $errors[] = 'Pasirinkite kategoriją.';
    }
    return $g;
}

if (is_post()) {
    csrf_check();
    $action = post('action');

    if ($action === 'create') {
        $g = group_from_post($errors);
        if (!$errors) {
            q('INSERT INTO training_groups (name, category, location, sort_order, is_active) VALUES (?, ?, ?, ?, ?)', array_values($g));
            flash('ok', 'Grupė sukurta. Pridėkite tvarkaraštį.');
            redirect('admin/grupes.php?id=' . db()->lastInsertId());
        }
    }
    if ($action === 'update' && $id) {
        $g = group_from_post($errors);
        if (!$errors) {
            q('UPDATE training_groups SET name = ?, category = ?, location = ?, sort_order = ?, is_active = ? WHERE id = ?', [...array_values($g), $id]);
            flash('ok', 'Išsaugota.');
            redirect('admin/grupes.php?id=' . $id);
        }
    }
    if ($action === 'add_slot' && $id) {
        $wd = (int) post('weekday');
        $start = post('start_time') ?: null;
        $end = post('end_time') ?: null;
        if ($wd < 1 || $wd > 7) {
            $errors[] = 'Pasirinkite savaitės dieną.';
        } elseif (($start === null) !== ($end === null) || ($start && $end <= $start)) {
            $errors[] = 'Įveskite pradžios ir pabaigos laiką (arba palikite abu tuščius ir parašykite pastabą).';
        } else {
            q('INSERT INTO schedule (group_id, weekday, start_time, end_time, note) VALUES (?, ?, ?, ?, ?)', [$id, $wd, $start, $end, post('note') ?: null]);
            flash('ok', 'Laikas pridėtas.');
            redirect('admin/grupes.php?id=' . $id);
        }
    }
    if ($action === 'delete_slot' && $id) {
        q('DELETE FROM schedule WHERE id = ? AND group_id = ?', [(int) post('slot_id'), $id]);
        flash('ok', 'Laikas pašalintas.');
        redirect('admin/grupes.php?id=' . $id);
    }
}

function group_form(array $g, string $action): void
{ ?>
    <input type="hidden" name="action" value="<?= $action ?>">
    <label>Pavadinimas <input type="text" name="name" value="<?= e($g['name'] ?? '') ?>" required></label>
    <div class="form-row">
      <label>Kategorija
        <select name="category" required>
          <?php foreach (GROUP_CATEGORIES as $k => $label): ?>
            <option value="<?= $k ?>" <?= ($g['category'] ?? '') === $k ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Eiliškumas <span class="hint">mažesnis - aukščiau</span><input type="number" name="sort_order" value="<?= (int) ($g['sort_order'] ?? 0) ?>"></label>
    </div>
    <label>Vieta <input type="text" name="location" value="<?= e($g['location'] ?? '') ?>"></label>
    <label class="check"><input type="checkbox" name="is_active" value="1" <?= ($g['is_active'] ?? 1) ? 'checked' : '' ?>><span>Aktyvi grupė</span></label>
<?php }

if ($id) {
    $g = q_one('SELECT * FROM training_groups WHERE id = ?', [$id]);
    if (!$g) {
        not_found();
    }
    if ($errors && post('action') === 'update') {
        $g = array_merge($g, $_POST);
    }
    $slots = group_schedule($id);
    $members = q_all('SELECT * FROM members WHERE group_id = ? AND status = "active" ORDER BY last_name, first_name', [$id]);

    page_start($g['name'], ['admin' => true]);
    ?>
    <a class="small" href="<?= url('admin/grupes.php') ?>">← Visos grupės</a>
    <h1 class="styled" style="margin-top:8px;"><?= e($g['name']) ?></h1>
    <?= form_errors($errors) ?>
    <div class="grid-2">
      <div class="panel card">
        <h2>Tvarkaraštis</h2>
        <?php if (!$slots): ?><p class="muted small">Laikų dar nėra.</p><?php endif; ?>
        <?php foreach ($slots as $s): ?>
          <div class="schedule-line">
            <span><?= e(LT_WEEKDAYS[(int) $s['weekday']]) ?><?= $s['note'] ? ' <span class="muted small">· ' . e($s['note']) . '</span>' : '' ?></span>
            <span class="row" style="gap:8px;">
              <span class="schedule-time"><?= $s['start_time'] ? e(fmt_time($s['start_time']) . '–' . fmt_time($s['end_time'])) : '' ?></span>
              <form method="post" class="inline-form" onsubmit="return confirm('Pašalinti šį laiką?')">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="delete_slot"><input type="hidden" name="slot_id" value="<?= (int) $s['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit" aria-label="Pašalinti">✕</button>
              </form>
            </span>
          </div>
        <?php endforeach; ?>
        <hr class="divider">
        <h3>Pridėti laiką</h3>
        <form method="post" class="form">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="action" value="add_slot">
          <label>Diena
            <select name="weekday" required>
              <?php foreach (LT_WEEKDAYS as $n => $day): ?><option value="<?= $n ?>"><?= $day ?></option><?php endforeach; ?>
            </select>
          </label>
          <div class="form-row">
            <label>Nuo <input type="time" name="start_time"></label>
            <label>Iki <input type="time" name="end_time"></label>
          </div>
          <label>Pastaba <span class="hint">nebūtina, pvz. „pažengę“ arba „laikas tikslinamas“</span><input type="text" name="note"></label>
          <div><button class="btn btn-primary" type="submit">Pridėti</button></div>
        </form>
      </div>
      <div class="stack">
        <form method="post" class="panel card form">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $id ?>">
          <h2>Grupės duomenys</h2>
          <?php group_form($g, 'update'); ?>
          <div><button class="btn btn-ghost" type="submit">Išsaugoti</button></div>
        </form>
        <div class="panel card">
          <h2>Nariai (<?= count($members) ?>)</h2>
          <?php if (!$members): ?><p class="muted small">Aktyvių narių nėra.</p><?php endif; ?>
          <ul class="list small">
            <?php foreach ($members as $m): ?>
              <li><a href="<?= url('admin/nariai.php?id=' . (int) $m['id']) ?>"><?= e($m['first_name'] . ' ' . $m['last_name']) ?></a> <span class="muted">· <?= age_on($m['birth_date']) ?> m.</span></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
    <?php
    page_end();
    exit;
}

$groups = q_all('SELECT g.*, (SELECT COUNT(*) FROM members m WHERE m.group_id = g.id AND m.status = "active") AS members
                   FROM training_groups g ORDER BY g.is_active DESC, g.sort_order, g.name');

page_start('Grupės', ['admin' => true]);
?>
<div class="page-head"><h1 class="styled">Grupės ir tvarkaraštis</h1></div>
<?= form_errors($errors) ?>
<div class="grid-2">
  <div class="panel card">
    <ul class="list">
      <?php foreach ($groups as $g): ?>
        <li class="row between">
          <span>
            <a href="?id=<?= (int) $g['id'] ?>"><strong><?= e($g['name']) ?></strong></a>
            <?php if (!$g['is_active']): ?><span class="badge">neaktyvi</span><?php endif; ?>
            <div class="muted small"><?= e($g['location'] ?? '') ?></div>
          </span>
          <span class="badge"><?= (int) $g['members'] ?> nar.</span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <form method="post" class="panel card form">
    <?= csrf_field() ?>
    <h2>Nauja grupė</h2>
    <?php group_form(post('action') === 'create' ? $_POST : [], 'create'); ?>
    <div><button class="btn btn-primary" type="submit">Sukurti</button></div>
  </form>
</div>
<?php
page_end();
