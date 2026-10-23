<?php
// Renginiai: egzaminai, varžybos, seminarai, stovyklos
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

$edit = get('edit') ?: post('edit');
$errors = [];
// Iš kur atėjo redaguoti (viešas renginių puslapis ar pagrindinis) - ten ir grąžinama
$back = get('back') ?: post('back');
$backPages = ['renginiai' => 'renginiai.php', 'index' => 'index.php#renginiai'];
$back = isset($backPages[$back]) ? $back : '';
function back_after_save(string $back, array $backPages, int $id = 0): void
{
    if ($back === 'index') {
        take_flashes();   // pagrindinis puslapis pranešimų nerodo - kad neiššoktų vėliau kitame puslapyje
    }
    if ($back === 'renginiai') {
        redirect('renginiai.php' . ($id ? '#e' . $id : ''));
    }
    redirect($backPages[$back] ?? 'renginiai.php' . ($id ? '#e' . $id : ''));
}

if (is_post()) {
    csrf_check();
    $id = (int) post('id');

    if (post('action') === 'delete' && $id) {
        q('DELETE FROM events WHERE id = ?', [$id]);
        flash('ok', 'Renginys ištrintas.');
        back_after_save($back, $backPages);
    }

    $ev = [
        'type'        => post('type'),
        'title'       => post('title'),
        'starts_on'   => post('starts_on'),
        'ends_on'     => post('ends_on') ?: null,
        'start_time'  => post('start_time') ?: null,
        'location'    => location_from_post('location') ?: null,
        'is_abroad'   => !empty($_POST['is_abroad']) ? 1 : 0,
        'description' => post('description') ?: null,
        'members_only' => !empty($_POST['members_only']) ? 1 : 0,
        'registration' => !empty($_POST['registration']) ? 1 : 0,
        'registration_until' => post('registration_until') ?: null,
    ];
    $groupIds = audience_from_post();   // visos pažymėtos = visiems
    $picked = member_tree_from_post();  // atskirai pažymėti žmonės (ne visa grupė) - pakviečiami
    $_POST['location'] = $ev['location'];   // kad klaidos atveju forma išlaikytų pasirinkimą

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
    if ($ev['registration_until'] && !DateTime::createFromFormat('!Y-m-d', $ev['registration_until'])) {
        $errors[] = 'Netinkama registracijos pabaigos data.';
    }

    if (!$errors) {
        // Nepažymėta jokia grupė, bet pažymėti žmonės - renginys tik pakviestiems
        $wasInvitedOnly = $id && (int) q_value('SELECT invited_only FROM events WHERE id = ?', [$id]) && q_value('SELECT 1 FROM event_participants WHERE event_id = ? LIMIT 1', [$id]);
        $ev['invited_only'] = !$picked['groups'] && ($picked['members'] || $wasInvitedOnly) ? 1 : 0;
        db()->beginTransaction();
        if ($id) {
            q('UPDATE events SET type = ?, title = ?, starts_on = ?, ends_on = ?, start_time = ?, location = ?, is_abroad = ?, description = ?, members_only = ?, registration = ?, registration_until = ?, invited_only = ? WHERE id = ?', array_merge(array_values($ev), [$id]));
            q('DELETE FROM event_groups WHERE event_id = ?', [$id]);
        } else {
            q('INSERT INTO events (type, title, starts_on, ends_on, start_time, location, is_abroad, description, members_only, registration, registration_until, invited_only) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array_values($ev));
            $id = (int) db()->lastInsertId();
        }
        foreach ($groupIds as $gid) {
            q('INSERT IGNORE INTO event_groups (event_id, group_id) VALUES (?, ?)', [$id, $gid]);
        }
        db()->commit();
        $invited = $picked['members'] ? invite_members(q_one('SELECT * FROM events WHERE id = ?', [$id]), $picked['members'], (int) $me['id'], !empty($_POST['invite_email'])) : 0;
        flash('ok', 'Renginys išsaugotas.' . ($invited ? " Pakviesta: $invited" . (!empty($_POST['invite_email']) ? ' (išsiųsti laiškai).' : '.') : ''));
        back_after_save($back, $backPages, $id);
    }
}

if ($edit !== '') {
    $ev = $edit === 'new' ? ['id' => 0, 'type' => 'exam', 'title' => '', 'starts_on' => '', 'ends_on' => '', 'start_time' => '', 'location' => '', 'is_abroad' => 0, 'description' => '', 'members_only' => 0, 'registration' => 1, 'registration_until' => '']
        : q_one('SELECT * FROM events WHERE id = ?', [(int) $edit]);
    if (!$ev) {
        not_found();
    }
    $selectedGroups = $ev['id'] ? q('SELECT group_id FROM event_groups WHERE event_id = ?', [$ev['id']])->fetchAll(PDO::FETCH_COLUMN) : [];
    // Jau dalyvių sąraše esantys (pakviesti, užsiregistravę) - rodomi pažymėti; keičiami puslapyje „Dalyviai“
    $participantIds = $ev['id'] ? array_map('intval', q('SELECT member_id FROM event_participants WHERE event_id = ?', [$ev['id']])->fetchAll(PDO::FETCH_COLUMN)) : [];
    $selectedMembers = [];
    if ($errors) {
        $ev = array_merge($ev, $_POST);
        $selectedGroups = (array) ($_POST['groups'] ?? []);
        $selectedMembers = (array) ($_POST['members'] ?? []);
    }
    $allGroups = q_all('SELECT * FROM training_groups WHERE is_active = 1 ORDER BY sort_order, name');

    page_start($ev['id'] ? 'Redaguoti renginį' : 'Naujas renginys', ['admin' => true, 'section' => 'renginiai']);
    ?>
    <a class="small" href="<?= url($backPages[$back] ?? 'renginiai.php') ?>">← <?= $back === 'index' ? 'Atgal į pagrindinį puslapį' : ($back ? 'Atgal į renginius' : 'Visi renginiai') ?></a>
    <h1 class="styled" style="margin-top:8px;"><?= $ev['id'] ? 'Redaguoti renginį' : 'Naujas renginys' ?></h1>
    <?= form_errors($errors) ?>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
      <input type="hidden" name="edit" value="<?= e($edit) ?>">
      <input type="hidden" name="back" value="<?= e($back) ?>">
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
            <label>Pabaigos data <span class="hint">jei renginys trunka kelias dienas</span><input type="date" name="ends_on" value="<?= e($ev['ends_on']) ?>"></label>
            <label>Laikas <input type="time" name="start_time" value="<?= e(fmt_time($ev['start_time'])) ?>"></label>
          </div>
          <?= location_field('location', $ev['location']) ?>
          <label class="check"><input type="checkbox" name="is_abroad" value="1" <?= !empty($ev['is_abroad']) ? 'checked' : '' ?>><span>Vyksta užsienyje (varžyboms skiriama daugiau taškų)</span></label>
          <label class="check"><input type="checkbox" name="members_only" value="1" <?= !empty($ev['members_only']) ? 'checked' : '' ?>><span>Tik nariams (nerodomas pagrindiniame puslapyje neprisijungusiems)</span></label>
          <label>Aprašymas <textarea name="description" rows="5"><?= e($ev['description']) ?></textarea></label>
          <div class="form-row">
            <label class="check" style="align-self:end;"><input type="checkbox" name="registration" value="1" <?= !empty($ev['registration']) ? 'checked' : '' ?>><span>Nariai gali registruotis patys („Dalyvausiu“)</span></label>
            <label>Registracija iki <span class="hint">nebūtina</span><input type="date" name="registration_until" value="<?= e($ev['registration_until'] ?? '') ?>"></label>
          </div>
        </div>
        <div class="panel card form">
          <h2>Kam skirta?</h2>
          <?= member_tree_field(array_map('intval', (array) $selectedGroups), $selectedMembers, [
              'locked' => $participantIds, 'locked_title' => 'jau sąraše',
              'hint' => 'Nieko nepažymėjus - visiems. Pažymėjus visą grupę - renginys tai grupei. Išskleiskite grupę ▸ ir pažymėkite tik kelis vaikus - jie bus pakviesti.']) ?>
          <label class="check" style="margin-top:10px;"><input type="checkbox" name="invite_email" value="1" <?= !$errors || !empty($_POST['invite_email']) ? 'checked' : '' ?>><span>Pakviestiems išsiųsti el. laišką</span></label>
          <?php if ($ev['id']): ?><p class="hint"><a href="<?= url('admin/renginio-dalyviai.php?id=' . (int) $ev['id']) ?>">Dalyvių sąrašas →</a></p><?php endif; ?>
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

// Renginių sąrašas - pačiuose Renginiuose (renginiai.php), su trenerio mygtukais
redirect('renginiai.php');
