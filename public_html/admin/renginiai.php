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
        'summary'     => mb_substr(post('summary'), 0, 300) ?: null,
        'members_only' => !empty($_POST['members_only']) ? 1 : 0,
        'registration' => !empty($_POST['registration']) ? 1 : 0,
        'registration_until' => post('registration_until') ?: null,
    ];
    // Pažymėtos grupės ir žmonės pakviečiami (visos grupės - visiems). Nieko nepažymėta - atviras renginys: kvietimų nėra, prisijungti gali visi.
    $groupIds = audience_from_post();   // visos pažymėtos = visiems
    $picked = member_tree_from_post();  // pilnai pažymėtos grupės ir atskirai pažymėti žmonės
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
    } elseif ($ev['registration_until'] && !$errors && $ev['registration_until'] >= $ev['starts_on']) {
        $errors[] = 'Registracija turi baigtis bent dieną prieš renginį (renginio dieną registruotis nebegalima).';
    }

    if (!$errors) {
        // Nepažymėta jokia grupė, bet pažymėti žmonės - renginys tik pakviestiems
        $wasInvitedOnly = $id && (int) q_value('SELECT invited_only FROM events WHERE id = ?', [$id]) && q_value('SELECT 1 FROM event_participants WHERE event_id = ? LIMIT 1', [$id]);
        $ev['invited_only'] = !$picked['groups'] && ($picked['members'] || $wasInvitedOnly) ? 1 : 0;
        db()->beginTransaction();
        if ($id) {
            q('UPDATE events SET type = ?, title = ?, starts_on = ?, ends_on = ?, start_time = ?, location = ?, is_abroad = ?, description = ?, summary = ?, members_only = ?, registration = ?, registration_until = ?, invited_only = ? WHERE id = ?', array_merge(array_values($ev), [$id]));
            q('DELETE FROM event_groups WHERE event_id = ?', [$id]);
        } else {
            q('INSERT INTO events (type, title, starts_on, ends_on, start_time, location, is_abroad, description, summary, members_only, registration, registration_until, invited_only) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array_values($ev));
            $id = (int) db()->lastInsertId();
        }
        foreach ($groupIds as $gid) {
            q('INSERT IGNORE INTO event_groups (event_id, group_id) VALUES (?, ?)', [$id, $gid]);
        }
        db()->commit();
        // Kviečiami pažymėtų grupių nariai ir atskirai pažymėti žmonės (jau sąraše esantys nekeičiami)
        $inviteIds = $picked['members'];
        if ($picked['groups']) {
            $inviteIds = array_merge($inviteIds, array_map('intval', q('SELECT id FROM members WHERE status = "active" AND group_id IN (' . implode(',', $picked['groups']) . ')')->fetchAll(PDO::FETCH_COLUMN)));
        }
        $sendEmail = !empty($_POST['invite_email']);
        $sendSms = !empty($_POST['invite_sms']) && is_admin();
        $invited = $inviteIds ? invite_members(q_one('SELECT * FROM events WHERE id = ?', [$id]), $inviteIds, (int) $me['id'], $sendEmail, $sendSms) : 0;
        $how = array_filter(['svetainėje', $sendEmail ? 'el. paštu' : '', $sendSms ? 'SMS' : '']);
        flash('ok', 'Renginys išsaugotas.' . ($invited ? " Pakviesta: $invited (" . implode(', ', $how) . ').' : (!$inviteIds ? ' Atviras renginys - kvietimai nesiųsti, prisijungti gali visi.' : '')));
        back_after_save($back, $backPages, $id);
    }
}

if ($edit !== '') {
    $ev = $edit === 'new' ? ['id' => 0, 'type' => 'exam', 'title' => '', 'starts_on' => '', 'ends_on' => '', 'start_time' => '', 'location' => '', 'is_abroad' => 0, 'description' => '', 'summary' => '', 'members_only' => 0, 'registration' => 1, 'registration_until' => '']
        : q_one('SELECT * FROM events WHERE id = ?', [(int) $edit]);
    if (!$ev) {
        not_found();
    }
    // Naujas renginys - numatyta pakviesti visus (visos grupės pažymėtos)
    $selectedGroups = $ev['id'] ? q('SELECT group_id FROM event_groups WHERE event_id = ?', [$ev['id']])->fetchAll(PDO::FETCH_COLUMN)
        : q('SELECT id FROM training_groups WHERE is_active = 1')->fetchAll(PDO::FETCH_COLUMN);
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
      <div class="grid-2 event-form">
        <div class="stack">
          <div class="panel card form">
            <h2 class="ef-step"><span>1</span> Renginys</h2>
            <div class="form-row">
              <label>Tipas
                <select name="type">
                  <?php foreach (EVENT_TYPES as $k => $label): ?>
                    <option value="<?= $k ?>" <?= $ev['type'] === $k ? 'selected' : '' ?>><?= $label ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Pavadinimas <input type="text" name="title" value="<?= e($ev['title']) ?>" required></label>
            </div>
            <label>Trumpai <span class="hint">rodoma renginių sąraše, iki 300 simbolių</span>
              <textarea name="summary" rows="2" maxlength="300" placeholder="pvz. Kyu egzaminas visoms grupėms. Reikia išlaikyti testą."><?= e((string) ($ev['summary'] ?? '')) ?></textarea></label>
            <label>Plačiau <span class="hint">rodoma atidarius renginį</span><textarea name="description" rows="6"><?= e((string) $ev['description']) ?></textarea></label>
            <label class="check"><input type="checkbox" name="members_only" value="1" <?= !empty($ev['members_only']) ? 'checked' : '' ?>><span>Tik nariams (neprisijungusiems nerodomas)</span></label>
          </div>
          <div class="panel card form ef-place">
            <h2 class="ef-step"><span>2</span> Kada ir kur vyksta</h2>
            <div class="form-row three">
              <label>Data <input type="date" name="starts_on" id="evStart" value="<?= e($ev['starts_on']) ?>" required></label>
              <label>Iki <span class="hint">nebūtina</span><input type="date" name="ends_on" value="<?= e((string) $ev['ends_on']) ?>"></label>
              <label>Laikas <input type="time" name="start_time" value="<?= e(fmt_time($ev['start_time'])) ?>"></label>
            </div>
            <?= location_field('location', $ev['location']) ?>
            <label class="check"><input type="checkbox" name="is_abroad" value="1" <?= !empty($ev['is_abroad']) ? 'checked' : '' ?>><span>Vyksta užsienyje (varžyboms skiriama daugiau taškų)</span></label>
          </div>
        </div>
        <div class="stack">
          <div class="panel card form">
            <h2 class="ef-step"><span>3</span> Kas kviečiami</h2>
            <?= member_tree_field(array_map('intval', (array) $selectedGroups), $selectedMembers, [
                'locked' => $participantIds, 'locked_title' => 'jau sąraše',
                'hint' => 'Pažymėti gaus kvietimą ir atsakys „Dalyvausiu“ arba „Negalėsiu“. Išskleiskite grupę ▸, kad pakviestumėte tik kelis vaikus. Nieko nepažymėjus - atviras renginys: kvietimų nėra, prisijungti gali visi.']) ?>
          </div>
          <div class="panel card form">
            <h2 class="ef-step"><span>4</span> Registracija ir pranešimai</h2>
            <label class="check"><input type="checkbox" name="registration" value="1" <?= !empty($ev['registration']) ? 'checked' : '' ?>><span>Nariai gali registruotis patys („Dalyvausiu“)</span></label>
            <label>Registracija iki <span class="hint">numatyta - diena prieš renginį; po termino Denis gauna prašymą</span>
              <input type="date" name="registration_until" id="evUntil" value="<?= e((string) ($ev['registration_until'] ?? '')) ?>"></label>
            <div class="kicker" style="margin-top:6px;">Kaip pranešti pakviestiems</div>
            <label class="check"><input type="checkbox" checked disabled><span>Svetainėje (Renginiai ir Mano paskyra) - visada</span></label>
            <label class="check"><input type="checkbox" name="invite_email" value="1" <?= !empty($_POST['invite_email']) ? 'checked' : '' ?>><span>El. paštu</span></label>
            <?php if (is_admin()): ?><label class="check"><input type="checkbox" name="invite_sms" value="1" <?= !empty($_POST['invite_sms']) ? 'checked' : '' ?>><span>SMS tėvams<?= sms_live() ? '' : ' <span class="hint">(bandomasis režimas - SMS nesiunčiamos)</span>' ?></span></label><?php endif; ?>
            <?php if ($ev['id']): ?><p class="hint"><a href="<?= url('admin/renginio-dalyviai.php?id=' . (int) $ev['id']) ?>">Dalyvių sąrašas →</a></p><?php endif; ?>
          </div>
        </div>
      </div>
      <script>
      // Registracijos pabaiga numatyta - diena prieš renginį
      (function () {
        var start = document.getElementById('evStart'), until = document.getElementById('evUntil'), auto = !until.value;
        until.addEventListener('input', function () { auto = false; });
        start.addEventListener('change', function () {
          if (!auto || !start.value) return;
          var d = new Date(start.value + 'T12:00:00'); d.setDate(d.getDate() - 1);
          until.value = d.toISOString().slice(0, 10);
        });
      })();
      </script>
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
