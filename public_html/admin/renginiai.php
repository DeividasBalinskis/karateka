<?php
// Renginiai: egzaminai, varžybos, seminarai, stovyklos
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_can('events');

$edit = get('edit') ?: post('edit');
$errors = [];
// Iš kur atėjo redaguoti (viešas renginių puslapis ar pagrindinis) - ten ir grąžinama
$back = get('back') ?: post('back');
$backPages = ['renginiai' => 'renginiai.php', 'index' => 'index.php#renginiai', 'egzaminai' => 'admin/egzaminai.php', 'admin' => 'admin/renginiai.php'];
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
        event_file_save($id, null, true);   // priedas ir nuotrauka ištrinami kartu
        event_cover_save($id, null, true);
        q('DELETE FROM events WHERE id = ?', [$id]);
        flash('ok', t('Renginys ištrintas.'));
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
        $errors[] = t('Pasirinkite renginio tipą.');
    }
    if ($ev['title'] === '') {
        $errors[] = t('Įveskite pavadinimą.');
    }
    if (!DateTime::createFromFormat('!Y-m-d', $ev['starts_on'])) {
        $errors[] = t('Įveskite datą.');
    } elseif ($ev['ends_on'] && $ev['ends_on'] < $ev['starts_on']) {
        $errors[] = t('Pabaigos data negali būti ankstesnė už pradžią.');
    }
    if ($fileErr = event_file_error($_FILES['file'] ?? null)) {
        $errors[] = $fileErr;
    }
    // Registracijos nuoroda (db/034): pvz. Google Forms - prie renginio mygtukas „📝 Registracija →“
    $regUrl = trim(post('registration_url'));
    if ($regUrl !== '' && !preg_match('~^https?://\S+$~i', $regUrl)) {
        $errors[] = t('Registracijos nuoroda turi prasidėti https:// (nukopijuokite visą nuorodą).');
    }
    if ($ev['registration_until'] && !DateTime::createFromFormat('!Y-m-d', $ev['registration_until'])) {
        $errors[] = t('Netinkama registracijos pabaigos data.');
    } elseif ($ev['registration_until'] && !$errors && $ev['registration_until'] > $ev['starts_on']) {
        $errors[] = t('Registracija negali baigtis vėliau, nei prasideda renginys.');
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
        try {
            q('UPDATE events SET registration_url = ? WHERE id = ?', [mb_substr($regUrl, 0, 500) ?: null, $id]);
        } catch (PDOException $ex) {
            // DB dar neatnaujinta (db/034) - nuoroda neišsaugoma, kita viskas veikia
        }
        try {
            event_file_save($id, $_FILES['file'] ?? null, !empty($_POST['file_remove']));
            event_cover_save($id, $_FILES['cover'] ?? null, !empty($_POST['cover_remove']));
        } catch (RuntimeException $ex) {
            flash('err', $ex->getMessage());
        }
        // Kviečiami pažymėtų grupių nariai ir atskirai pažymėti žmonės (jau sąraše esantys nekeičiami)
        $inviteIds = $picked['members'];
        if ($picked['groups']) {
            $inviteIds = array_merge($inviteIds, array_map('intval', q('SELECT id FROM members WHERE status = "active" AND group_id IN (' . implode(',', $picked['groups']) . ')')->fetchAll(PDO::FETCH_COLUMN)));
        }
        $sendEmail = !empty($_POST['invite_email']);
        $sendSms = !empty($_POST['invite_sms']) && can('sms');
        $invited = $inviteIds ? invite_members(q_one('SELECT * FROM events WHERE id = ?', [$id]), $inviteIds, (int) $me['id'], $sendEmail, $sendSms) : 0;
        $how = array_filter([t('svetainėje'), $sendEmail ? t('el. paštu') : '', $sendSms ? 'SMS' : '']);
        tr_auto_entity('event', (int) $id);
        flash('ok', t('Renginys išsaugotas.') . ($invited ? " Pakviesta: $invited (" . implode(', ', $how) . ').' : (!$inviteIds ? (' ' . t('Atviras renginys - kvietimai nesiųsti, prisijungti gali visi.')) : '')));
        back_after_save($back, $backPages, $id);
    }
}

if ($edit !== '') {
    // B6: naujas renginys - „Kita“ (ne „Egzaminas“)
    $ev = $edit === 'new' ? ['id' => 0, 'type' => 'other', 'title' => '', 'starts_on' => '', 'ends_on' => '', 'start_time' => '', 'location' => '', 'is_abroad' => 0, 'description' => '', 'summary' => '', 'members_only' => 0, 'registration' => 1, 'registration_until' => '']
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

    page_start($ev['id'] ? t('Redaguoti renginį') : t('Naujas renginys'), ['admin' => true, 'section' => 'renginiai']);
    ?>
    <a class="small" href="<?= url($backPages[$back] ?? 'renginiai.php') ?>">← <?= $back === 'index' ? t('Atgal į pagrindinį puslapį') : ($back ? t('Atgal į renginius') : t('Visi renginiai')) ?></a>
    <h1 class="styled" style="margin-top:8px;"><?= $ev['id'] ? t('Redaguoti renginį') : t('Naujas renginys') ?></h1>
    <?php if ($ev['id']): ?><a class="btn btn-ghost btn-sm tr-link" href="<?= url('admin/vertimai.php?e=event&id=' . (int) $ev['id']) ?>"><?= te('🌐 Vertimai (EN / RU)') ?></a><?php endif; ?>
    <?= form_errors($errors) ?>
    <form method="post" class="form" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
      <input type="hidden" name="edit" value="<?= e($edit) ?>">
      <input type="hidden" name="back" value="<?= e($back) ?>">
      <div class="grid-2 event-form">
        <div class="stack">
          <div class="panel card form">
            <h2 class="ef-step"><span>1</span> <?= te('Renginys') ?></h2>
            <div class="form-row">
              <label><?= te('Tipas') ?>
                <select name="type">
                  <?php foreach (array_map('t', EVENT_TYPES) as $k => $label): ?>
                    <option value="<?= $k ?>" <?= $ev['type'] === $k ? 'selected' : '' ?>><?= $label ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label><?= te('Pavadinimas') ?> <input type="text" name="title" value="<?= e($ev['title']) ?>" required></label>
            </div>
            <label><?= te('Trumpai') ?> <span class="hint"><?= te('rodoma renginių sąraše, iki 300 simbolių') ?></span>
              <textarea name="summary" rows="2" maxlength="300" placeholder="<?= te('pvz. Kyu egzaminas visoms grupėms. Reikia išlaikyti testą.') ?>"><?= e((string) ($ev['summary'] ?? '')) ?></textarea></label>
            <label><?= te('Plačiau') ?> <span class="hint"><?= te('rodoma atidarius renginį') ?></span><textarea name="description" rows="6"><?= e((string) $ev['description']) ?></textarea></label>
            <div class="qf-field"><span class="qf-label"><?= te('Priedas') ?> <span class="hint"><?= te('nebūtina; pvz. reglamentas - PDF, Word ar Excel, iki 10 MB') ?></span></span>
              <?= file_drop('file', '.pdf,.doc,.docx,.xls,.xlsx,.odt,.ods,.ppt,.pptx,.jpg,.jpeg,.png', t('Pasirinkite failą'), t('PDF, Word, Excel ar nuotrauka - arba nutempkite čia')) ?></div>
            <?php if (!empty($ev['file_path'])): ?>
              <div class="row" style="gap:12px; align-items:center;"><?= event_file_link($ev) ?>
                <label class="check"><input type="checkbox" name="file_remove" value="1"><span><?= te('Pašalinti priedą') ?></span></label></div>
            <?php endif; ?>
            <?php if (event_cover_ready()): $cov = !empty($ev['cover']) ? event_cover($ev) : null; ?>
              <div class="qf-field"><span class="qf-label"><?= te('Nuotrauka') ?> <span class="hint"><?= te('nebūtina; rodoma renginių sąraše ir „Naujienos ir renginiai“') ?></span></span>
                <?= file_drop('cover', 'image/jpeg,image/png,image/webp', t('Pasirinkite nuotrauką'), t('JPG, PNG ar WEBP - arba nutempkite čia')) ?></div>
              <?php if ($cov): ?>
                <div class="row" style="gap:12px; align-items:center;"><img src="<?= e($cov) ?>" alt="" style="width:120px; height:72px; object-fit:cover; border-radius:10px;">
                  <label class="check"><input type="checkbox" name="cover_remove" value="1"><span><?= te('Pašalinti nuotrauką') ?></span></label></div>
              <?php endif; ?>
            <?php endif; ?>
            <label class="check"><input type="checkbox" name="members_only" value="1" <?= !empty($ev['members_only']) ? 'checked' : '' ?>><span><?= te('Tik nariams (neprisijungusiems nerodomas)') ?></span></label>
          </div>
          <div class="panel card form ef-place">
            <h2 class="ef-step"><span>2</span> <?= te('Kada ir kur vyksta') ?></h2>
            <div class="form-row three">
              <label><?= te('Data') ?> <input type="date" name="starts_on" id="evStart" value="<?= e($ev['starts_on']) ?>" required></label>
              <label><?= te('Iki') ?> <span class="hint"><?= te('nebūtina') ?></span><input type="date" name="ends_on" value="<?= e((string) $ev['ends_on']) ?>"></label>
              <label><?= te('Laikas') ?> <input type="time" name="start_time" value="<?= e(fmt_time($ev['start_time'])) ?>"></label>
            </div>
            <?= location_field('location', $ev['location']) ?>
            <label class="check"><input type="checkbox" name="is_abroad" value="1" <?= !empty($ev['is_abroad']) ? 'checked' : '' ?>><span><?= te('Vyksta užsienyje (varžyboms skiriama daugiau taškų)') ?></span></label>
          </div>
        </div>
        <div class="stack">
          <div class="panel card form">
            <h2 class="ef-step"><span>3</span> <?= te('Kas kviečiami') ?> <span class="badge ev-will" id="evWill" aria-live="polite"></span></h2>
            <?= member_tree_field(array_map('intval', (array) $selectedGroups), $selectedMembers, [
                'locked' => $participantIds, 'locked_title' => t('jau sąraše'),
                'hint' => t('Pažymėti gaus kvietimą ir atsakys „Dalyvausiu“ arba „Negalėsiu“. Išskleiskite grupę ▸, kad pakviestumėte tik kelis vaikus. Nieko nepažymėjus - atviras renginys: kvietimų nėra, prisijungti gali visi.')]) ?>
          </div>
          <div class="panel card form">
            <h2 class="ef-step"><span>4</span> <?= te('Registracija ir pranešimai') ?></h2>
            <label class="check"><input type="checkbox" name="registration" value="1" <?= !empty($ev['registration']) ? 'checked' : '' ?>><span><?= te('Nariai gali registruotis patys („Dalyvausiu“)') ?></span></label>
            <label><?= te('Registracijos nuoroda') ?> <span class="hint"><?= te('nebūtina; pvz. Google Forms - prie renginio atsiras mygtukas „📝 Registracija →“') ?></span>
              <input type="url" name="registration_url" maxlength="500" placeholder="https://forms.gle/…" value="<?= e((string) (post('registration_url') !== '' ? post('registration_url') : ($ev['registration_url'] ?? ''))) ?>"></label>
            <label><?= te('Registracija iki') ?> <span class="hint"><?= te('numatyta - diena prieš renginį (galima ir renginio diena); po termino Denis gauna prašymą') ?></span>
              <input type="date" name="registration_until" id="evUntil" value="<?= e((string) ($ev['registration_until'] ?? '')) ?>"></label>
            <div class="kicker" style="margin-top:6px;"><?= te('Kaip pranešti pakviestiems') ?></div>
            <label class="check"><input type="checkbox" checked disabled><span><?= te('Svetainėje (Renginiai ir Mano paskyra) - visada') ?></span></label>
            <label class="check"><input type="checkbox" name="invite_email" value="1" <?= !empty($_POST['invite_email']) ? 'checked' : '' ?>><span><?= te('El. paštu') ?></span></label>
            <?php if (can('sms')): ?><label class="check"><input type="checkbox" name="invite_sms" value="1" <?= !empty($_POST['invite_sms']) ? 'checked' : '' ?>><span><?= te('SMS tėvams') ?><?= sms_live() ? '' : (' <span class="hint">' . t('(bandomasis režimas - SMS nesiunčiamos)') . '</span>') ?></span></label><?php endif; ?>
            <?php if ($ev['id']): ?><p class="hint"><a href="<?= url('admin/renginio-dalyviai.php?id=' . (int) $ev['id']) ?>"><?= te('Dalyvių sąrašas →') ?></a></p><?php endif; ?>
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
      // B6: „Bus pakviesta: N“ - prieš išsaugant matosi, kiek žmonių gaus kvietimą (pažymėtos grupės + pavieniai vaikai, be jau esančių sąraše)
      (function () {
        var out = document.getElementById('evWill'), form = out && out.closest('form');
        if (!form) return;
        var L = document.documentElement.lang === 'en' ? ['Will be invited:', 'Open event - no invitations'] : document.documentElement.lang === 'ru' ? ['Будет приглашено:', 'Открытое мероприятие - без приглашений'] : ['Bus pakviesta:', 'Atviras renginys - kvietimų nebus'];
        function count() {
          var ids = {};
          form.querySelectorAll('input[data-mt="group"]:checked').forEach(function (g) {
            var box = g.closest('.mt-group');
            if (box) box.querySelectorAll('input[data-mt="member"]:not(:disabled)').forEach(function (m) { ids[m.value] = 1; });
          });
          form.querySelectorAll('input[data-mt="member"]:checked:not(:disabled)').forEach(function (m) { ids[m.value] = 1; });
          var n = Object.keys(ids).length;
          out.textContent = n ? L[0] + ' ' + n : L[1];
          out.classList.toggle('ok', n > 0);
        }
        form.addEventListener('change', function (e) { if (e.target.dataset && e.target.dataset.mt) setTimeout(count, 0); });
        count();
      })();
      </script>
      <div class="row" style="margin-top:20px;">
        <button class="btn btn-primary" type="submit"><?= te('Išsaugoti') ?></button>
        <?php if ($ev['id']): ?>
          <button class="btn btn-danger" type="submit" name="action" value="delete" formnovalidate onclick="return confirm('Ištrinti renginį?')"><?= te('Ištrinti') ?></button>
        <?php endif; ?>
      </div>
    </form>
    <?php
    page_end();
    exit;
}

// B9: renginių sąrašas Valdyme (su šoniniu meniu): artėjantys ir praėję 60 d., kiekvienam - Redaguoti, Dalyviai, Rezultatai / Kas buvo
$upcoming = q_all('SELECT e.*, (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.id AND ep.status IN ("going", "late")) AS going,
                          (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.id AND ep.status = "invited") AS invited
                     FROM events e WHERE COALESCE(e.ends_on, e.starts_on) >= CURDATE() ORDER BY e.starts_on, e.start_time');
$past = q_all('SELECT e.*, (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.id AND ep.status IN ("going", "late")) AS going, 0 AS invited
                 FROM events e WHERE COALESCE(e.ends_on, e.starts_on) < CURDATE() AND e.starts_on >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) ORDER BY e.starts_on DESC');
$row = function (array $ev, bool $isPast): string {
    $id = (int) $ev['id'];
    $res = in_array($ev['type'], ['exam', 'competition'], true) ? ['admin/renginio-rezultatai.php?id=' . $id, t('Rezultatai')] : ['admin/treniruote.php?e=' . $id, t('Kas buvo')];
    return '<li class="aev-row"><span class="aev-date"><b>' . e(fmt_date($ev['starts_on'])) . '</b>' . ($ev['start_time'] ? '<small>' . e(fmt_time($ev['start_time'])) . '</small>' : '') . '</span>'
        . '<span class="aev-main"><span class="badge badge-pink">' . e(t(EVENT_TYPES[$ev['type']] ?? $ev['type'])) . '</span> <a href="' . e(url('renginys.php?id=' . $id)) . '"><b>' . e($ev['title']) . '</b></a>'
        . '<small class="muted">' . ($ev['location'] ? e($ev['location']) . ' · ' : '') . e(t('dalyvauja {n}', ['n' => (int) $ev['going']])) . ((int) $ev['invited'] ? ' · ' . e(t('laukia atsakymo {n}', ['n' => (int) $ev['invited']])) : '') . '</small></span>'
        . '<span class="aev-acts"><a class="btn btn-ghost btn-sm" href="' . e(url('admin/renginiai.php?edit=' . $id . '&back=admin')) . '">' . e(t('Redaguoti')) . '</a>'
        . '<a class="btn btn-ghost btn-sm" href="' . e(url('admin/renginio-dalyviai.php?id=' . $id)) . '">' . e(t('Dalyviai')) . '</a>'
        . ($isPast || $ev['starts_on'] <= date('Y-m-d') ? '<a class="btn btn-ghost btn-sm" href="' . e(url($res[0])) . '">' . e($res[1]) . '</a>' : '') . '</span></li>';
};
page_start(t('Renginiai'), ['admin' => true]);
?>
<div class="page-head row between" style="flex-wrap:wrap; gap:10px;">
  <h1 class="styled"><?= te('Renginiai') ?></h1>
  <div class="row" style="gap:8px;"><a class="btn btn-ghost btn-sm" href="<?= url('renginiai.php') ?>"><?= te('Kaip mato nariai') ?></a><a class="btn btn-primary btn-sm" href="<?= url('admin/renginiai.php?edit=new&back=admin') ?>"><?= te('+ Naujas renginys') ?></a></div>
</div>
<section class="panel card">
  <div class="kicker"><?= te('Artėjantys') ?> (<?= count($upcoming) ?>)</div>
  <?php if ($upcoming): ?><ul class="aev-list"><?php foreach ($upcoming as $ev) { echo $row($ev, false); } ?></ul>
  <?php else: ?><p class="muted"><?= te('Artėjančių renginių nėra.') ?></p><?php endif; ?>
</section>
<section class="panel card" style="margin-top:16px;">
  <div class="kicker"><?= te('Praėję (60 d.)') ?> (<?= count($past) ?>)</div>
  <?php if ($past): ?><ul class="aev-list"><?php foreach ($past as $ev) { echo $row($ev, true); } ?></ul>
  <?php else: ?><p class="muted"><?= te('Praėjusių renginių nėra.') ?></p><?php endif; ?>
</section>
<?php page_end();
