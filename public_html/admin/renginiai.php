<?php
// Renginiai: egzaminai, varžybos, seminarai, stovyklos
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

$edit = get('edit') ?: post('edit');
$errors = [];

if (is_post()) {
    csrf_check();
    $id = (int) post('id');

    if (post('action') === 'delete' && $id) {
        q('DELETE FROM events WHERE id = ?', [$id]);
        flash('ok', 'Renginys ištrintas.');
        redirect('admin/renginiai.php');
    }

    $ev = [
        'type'        => post('type'),
        'title'       => post('title'),
        'starts_on'   => post('starts_on'),
        'ends_on'     => post('ends_on') ?: null,
        'start_time'  => post('start_time') ?: null,
        'location'    => post('location') ?: null,
        'is_abroad'   => !empty($_POST['is_abroad']) ? 1 : 0,
        'description' => post('description') ?: null,
    ];
    $groupIds = array_map('intval', (array) ($_POST['groups'] ?? []));

    if (!isset(EVENT_TYPES[$ev['type']])) {
        $errors[] = 'Pasirinkite renginio tipą.';
    }
    if ($ev['title'] === '') {
        $errors[] = 'Įveskite pavadinimą.';
    }
    if (!DateTime::createFromFormat('!Y-m-d', $ev['starts_on'])) {
        $errors[] = 'Įveskite datą.';
    } elseif ($ev['ends_on'] && $ev['ends_on'] < $ev['starts_on']) {
        $errors[] = 'Pabaigos data negali būti ankstesnė už pradžią.';
    }

    if (!$errors) {
        db()->beginTransaction();
        if ($id) {
            q('UPDATE events SET type = ?, title = ?, starts_on = ?, ends_on = ?, start_time = ?, location = ?, is_abroad = ?, description = ? WHERE id = ?', array_merge(array_values($ev), [$id]));
            q('DELETE FROM event_groups WHERE event_id = ?', [$id]);
        } else {
            q('INSERT INTO events (type, title, starts_on, ends_on, start_time, location, is_abroad, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', array_values($ev));
            $id = (int) db()->lastInsertId();
        }
        foreach ($groupIds as $gid) {
            q('INSERT IGNORE INTO event_groups (event_id, group_id) VALUES (?, ?)', [$id, $gid]);
        }
        db()->commit();
        flash('ok', 'Renginys išsaugotas.');
        redirect('admin/renginiai.php');
    }
}

if ($edit !== '') {
    $ev = $edit === 'new' ? ['id' => 0, 'type' => 'exam', 'title' => '', 'starts_on' => '', 'ends_on' => '', 'start_time' => '', 'location' => '', 'is_abroad' => 0, 'description' => '']
        : q_one('SELECT * FROM events WHERE id = ?', [(int) $edit]);
    if (!$ev) {
        not_found();
    }
    $selectedGroups = $ev['id'] ? q('SELECT group_id FROM event_groups WHERE event_id = ?', [$ev['id']])->fetchAll(PDO::FETCH_COLUMN) : [];
    if ($errors) {
        $ev = array_merge($ev, $_POST);
        $selectedGroups = (array) ($_POST['groups'] ?? []);
    }
    $allGroups = q_all('SELECT * FROM training_groups WHERE is_active = 1 ORDER BY sort_order, name');

    page_start($ev['id'] ? 'Redaguoti renginį' : 'Naujas renginys', ['admin' => true]);
    ?>
    <a class="small" href="<?= url('admin/renginiai.php') ?>">← Visi renginiai</a>
    <h1 class="styled" style="margin-top:8px;"><?= $ev['id'] ? 'Redaguoti renginį' : 'Naujas renginys' ?></h1>
    <?= form_errors($errors) ?>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
      <input type="hidden" name="edit" value="<?= e($edit) ?>">
      <div class="grid-2">
        <div class="panel card form">
          <label>Tipas
            <select name="type">
              <?php foreach (EVENT_TYPES as $k => $label): ?>
                <option value="<?= $k ?>" <?= $ev['type'] === $k ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Pavadinimas <input type="text" name="title" value="<?= e($ev['title']) ?>" required></label>
          <div class="form-row three">
            <label>Data <input type="date" name="starts_on" value="<?= e($ev['starts_on']) ?>" required></label>
            <label>Iki <span class="hint">nebūtina</span><input type="date" name="ends_on" value="<?= e($ev['ends_on']) ?>"></label>
            <label>Laikas <input type="time" name="start_time" value="<?= e(fmt_time($ev['start_time'])) ?>"></label>
          </div>
          <label>Vieta <input type="text" name="location" value="<?= e($ev['location']) ?>"></label>
          <label class="check"><input type="checkbox" name="is_abroad" value="1" <?= !empty($ev['is_abroad']) ? 'checked' : '' ?>><span>Vyksta užsienyje (varžyboms skiriama daugiau taškų)</span></label>
          <label>Aprašymas <textarea name="description" rows="5"><?= e($ev['description']) ?></textarea></label>
        </div>
        <div class="panel card form">
          <h2>Kam skirta?</h2>
          <p class="hint">Nieko nepažymėjus - visoms grupėms.</p>
          <?php foreach ($allGroups as $g): ?>
            <label class="check"><input type="checkbox" name="groups[]" value="<?= (int) $g['id'] ?>" <?= in_array((string) $g['id'], array_map('strval', $selectedGroups), true) ? 'checked' : '' ?>>
              <span><?= e($g['name']) ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="row" style="margin-top:20px;">
        <button class="btn btn-primary" type="submit">Išsaugoti</button>
        <?php if ($ev['id']): ?>
          <button class="btn btn-danger" type="submit" name="action" value="delete" formnovalidate onclick="return confirm('Ištrinti renginį?')">Ištrinti</button>
        <?php endif; ?>
      </div>
    </form>
    <?php
    page_end();
    exit;
}

$upcoming = upcoming_events(null, 100);
$past = q_all('SELECT * FROM events WHERE COALESCE(ends_on, starts_on) < CURDATE() ORDER BY starts_on DESC LIMIT 20');

function event_row(array $ev): void
{
    $groups = event_group_names((int) $ev['id']); ?>
    <li class="row between">
      <span>
        <span class="badge badge-pink"><?= e(EVENT_TYPES[$ev['type']]) ?></span>
        <a href="?edit=<?= (int) $ev['id'] ?>"><strong><?= e($ev['title']) ?></strong></a>
        <div class="muted small"><?= e(fmt_date($ev['starts_on'], true)) ?><?= $ev['location'] ? ' · ' . e($ev['location']) : '' ?> · <?= $groups ? e(implode(', ', $groups)) : 'visiems' ?></div>
      </span>
      <span class="row" style="gap:6px;">
        <?php if (isset(EVENT_RESULTS[$ev['type']]) && $ev['starts_on'] <= date('Y-m-d')): ?><a class="btn btn-primary btn-sm" href="<?= url('admin/renginio-rezultatai.php?id=' . (int) $ev['id']) ?>">Rezultatai</a><?php endif; ?>
        <a class="btn btn-ghost btn-sm" href="?edit=<?= (int) $ev['id'] ?>">Keisti</a>
      </span>
    </li>
<?php }

page_start('Renginiai', ['admin' => true]);
?>
<div class="page-head row between">
  <h1 class="styled">Renginiai</h1>
  <a class="btn btn-primary" href="?edit=new">+ Naujas renginys</a>
</div>
<div class="panel card">
  <h2>Artėjantys</h2>
  <?php if (!$upcoming): ?><p class="muted">Artėjančių renginių nėra.</p><?php endif; ?>
  <ul class="list"><?php foreach ($upcoming as $ev) event_row($ev); ?></ul>
</div>
<?php if ($past): ?>
  <div class="panel card">
    <h2>Praėję</h2>
    <ul class="list"><?php foreach ($past as $ev) event_row($ev); ?></ul>
  </div>
<?php endif; ?>
<?php
page_end();
