<?php
// Trenerio pastabos po treniruotės: pasirenki grupę, prie vaikų parašai pastabas, išsaugai vienu mygtuku
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

/** El. laiškas vaikui / tėvams apie naują pastabą */
function notify_note(array $m, string $text, string $date, array $me): void
{
    foreach (q_all('SELECT a.* FROM accounts a JOIN account_members am ON am.account_id = a.id WHERE am.member_id = ? AND a.status = "active"', [$m['id']]) as $acc) {
        send_mail($acc['email'], "Trenerio pastaba: {$m['first_name']}",
            "Sveiki,\n\ntreneris {$me['first_name']} paliko pastabą ({$m['first_name']}, " . fmt_date($date, true) . "):\n\n$text\n\n"
            . 'Visas pastabas matysite paskyroje: ' . abs_url('paskyra.php?m=' . $m['id']));
    }
}

/** Prisegtos pamokos ID (tik jei tokia pamoka yra) */
function note_lesson_id($raw): ?int
{
    $id = (int) $raw;
    return $id && q_value('SELECT 1 FROM lessons WHERE id = ?', [$id]) ? $id : null;
}

function save_coach_note(array $m, array $me, string $date, string $text, ?string $yt, ?int $lessonId, bool $isTask, ?int $groupId = null, ?int $eventId = null): void
{
    if ($text === '') {
        $text = $lessonId ? 'Pažiūrėk prisegtą pamoką.' : 'Pažiūrėk video.';
    }
    q('INSERT INTO coach_notes (member_id, author_id, group_id, event_id, note_date, body, youtube_id, lesson_id, is_task) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$m['id'], $me['id'], $groupId, $eventId, $date, $text, $yt, $lessonId, $isTask ? 1 : 0]);
}

// Paskutinė pasirinkta grupė įsimenama, kad salėje nereikėtų rinktis kiekvieną kartą
$groupId = (int) (get('g') ?: post('g') ?: ($_SESSION['notes_group'] ?? 0));
$date = get('d') ?: post('d') ?: date('Y-m-d');
if (!DateTime::createFromFormat('!Y-m-d', $date)) {
    $date = date('Y-m-d');
}
$group = $groupId ? q_one('SELECT * FROM training_groups WHERE id = ?', [$groupId]) : null;
if ($group) {
    $_SESSION['notes_group'] = $groupId;
}

if (is_post()) {
    csrf_check();

    if (post('action') === 'delete') {
        q('DELETE FROM coach_notes WHERE id = ?', [(int) post('note_id')]);
        flash('ok', 'Pastaba ištrinta.');
        redirect('admin/treniruote.php?g=' . $groupId . '&d=' . $date);
    }

    // Lankomumas: vienas narys (buvo / nebuvo / nuimti žymą) arba visi nepažymėti - "buvo". Atsakymas JSON.
    if (in_array(post('action'), ['attendance', 'attendance_all'], true) && $group) {
        header('Content-Type: application/json; charset=utf-8');
        $mark = function (int $mid, int $present) use ($date, $groupId, $me) {
            q('INSERT INTO attendance (member_id, training_date, group_id, present, marked_by) VALUES (?, ?, ?, ?, ?)
               ON DUPLICATE KEY UPDATE present = VALUES(present), group_id = VALUES(group_id), marked_by = VALUES(marked_by)',
                [$mid, $date, $groupId, $present, $me['id']]);
        };
        $ids = q('SELECT id FROM members WHERE group_id = ? AND status = "active"', [$groupId])->fetchAll(PDO::FETCH_COLUMN);
        // Kartu skiriami / nuimami taškai už lankomumą (Treneriams -> Taškai: "Treniruotės lankymas")
        if (post('action') === 'attendance') {
            $mid = (int) post('member_id');
            if (in_array((string) $mid, array_map('strval', $ids), true)) {
                if (post('present') === '') {
                    q('DELETE FROM attendance WHERE member_id = ? AND training_date = ?', [$mid, $date]);
                } else {
                    $mark($mid, post('present') === '1' ? 1 : 0);
                }
                sync_attendance_points($mid, $date, post('present') === '1', (int) $me['id']);
            }
        } else {
            foreach ($ids as $mid) {
                q('INSERT IGNORE INTO attendance (member_id, training_date, group_id, present, marked_by) VALUES (?, ?, ?, 1, ?)',
                    [$mid, $date, $groupId, $me['id']]);
            }
            foreach (q('SELECT member_id FROM attendance WHERE training_date = ? AND group_id = ? AND present = 1', [$date, $groupId])->fetchAll(PDO::FETCH_COLUMN) as $mid) {
                sync_attendance_points((int) $mid, $date, true, (int) $me['id']);
            }
        }
        $rows = q('SELECT member_id, present FROM attendance WHERE training_date = ? AND member_id IN (SELECT id FROM members WHERE group_id = ? AND status = "active")',
            [$date, $groupId])->fetchAll(PDO::FETCH_KEY_PAIR);
        exit(json_encode(['ok' => true, 'marks' => $rows, 'present' => count(array_filter($rows)), 'total' => count($ids)]));
    }

    // Viena pastaba (paspaudus Enter) - atsakymas JSON, puslapis neperkraunamas
    if (post('action') === 'save_one' && $group) {
        header('Content-Type: application/json; charset=utf-8');
        $text = post('text');
        $link = post('video');
        $yt = $link !== '' ? youtube_id($link) : null;
        $lessonId = note_lesson_id(post('lesson_id'));
        $isTask = !empty($_POST['is_task']);
        $m = q_one('SELECT * FROM members WHERE id = ? AND status = "active"', [(int) post('member_id')]);
        if ($link !== '' && !$yt) {
            exit(json_encode(['ok' => false, 'error' => 'Neatpažinta YouTube nuoroda']));
        }
        if (!$m || ($text === '' && !$yt && !$lessonId)) {
            exit(json_encode(['ok' => false, 'error' => 'Tuščia pastaba']));
        }
        save_coach_note($m, $me, $date, $text, $yt, $lessonId, $isTask, $groupId);
        if (!empty($_POST['notify'])) {
            notify_note($m, $text, $date, $me);
        }
        exit(json_encode(['ok' => true, 'preview' => mb_strimwidth($text, 0, 120, '…') . ($yt ? ' ▶' : '') . ($lessonId ? ' · pamoka' : '') . ($isTask ? ' · užduotis' : '')], JSON_UNESCAPED_UNICODE));
    }

    if (post('action') === 'save' && $group) {
        $saved = 0;
        $badLinks = [];
        $notify = !empty($_POST['notify']);
        foreach ((array) ($_POST['note'] ?? []) as $mid => $text) {
            $text = is_string($text) ? trim($text) : '';
            $link = trim((string) ($_POST['video'][$mid] ?? ''));
            $lessonId = note_lesson_id($_POST['lesson'][$mid] ?? '');
            $isTask = !empty($_POST['task'][$mid]);
            if ($text === '' && $link === '' && !$lessonId) {
                continue;
            }
            $yt = null;
            if ($link !== '') {
                $yt = youtube_id($link);
                if (!$yt) {
                    $badLinks[] = $link;
                }
            }
            $m = q_one('SELECT * FROM members WHERE id = ? AND status = "active"', [(int) $mid]);
            if (!$m) {
                continue;
            }
            save_coach_note($m, $me, $date, $text, $yt, $lessonId, $isTask, $groupId);
            $saved++;

            if ($notify) {
                notify_note($m, $text, $date, $me);
            }
        }
        if ($badLinks) {
            flash('err', 'Neatpažintos YouTube nuorodos (pastabos išsaugotos be video): ' . implode(', ', $badLinks));
        }
        flash('ok', $saved ? "Išsaugota pastabų: $saved" : 'Nieko neįrašyta.');
        redirect('admin/treniruote.php?g=' . $groupId . '&d=' . $date);
    }
}

$members = $group ? q_all('SELECT * FROM members WHERE group_id = ? AND status = "active" ORDER BY last_name, first_name', [$groupId]) : [];
// Pamokos prisegimui (pagal diržą)
$lessonOptions = '<option value="">+ Prisegti pamoką</option>';
foreach (q_all('SELECT id, title, belt_level FROM lessons ORDER BY belt_level IS NOT NULL, belt_level, title') as $ls) {
    $lessonOptions .= '<option value="' . (int) $ls['id'] . '">' . e(($ls['belt_level'] ? BELTS[(int) $ls['belt_level']][0] . ' · ' : '') . $ls['title']) . '</option>';
}
// Šios treniruotės lankomumo žymos: [member_id => 1 buvo / 0 nebuvo]
$att = $group ? q('SELECT member_id, present FROM attendance WHERE training_date = ? AND group_id = ?', [$date, $groupId])->fetchAll(PDO::FETCH_KEY_PAIR) : [];
// Ar šią dieną grupė turi treniruotę pagal tvarkaraštį
$hasTraining = $group ? (bool) q_value('SELECT 1 FROM schedule WHERE group_id = ? AND weekday = ?', [$groupId, (int) date('N', strtotime($date))]) : false;
$todayNotes = [];
if ($group) {
    foreach (q_all('SELECT cn.*, a.first_name AS author FROM coach_notes cn JOIN members m ON m.id = cn.member_id LEFT JOIN accounts a ON a.id = cn.author_id
                     WHERE m.group_id = ? AND cn.note_date = ? ORDER BY cn.created_at', [$groupId, $date]) as $n) {
        $todayNotes[$n['member_id']][] = $n;
    }
}
$recent = $group ? q_all('SELECT cn.*, m.first_name, m.last_name, a.first_name AS author FROM coach_notes cn JOIN members m ON m.id = cn.member_id LEFT JOIN accounts a ON a.id = cn.author_id
                           WHERE m.group_id = ? AND cn.note_date <> ? ORDER BY cn.note_date DESC, cn.id DESC LIMIT 20', [$groupId, $date]) : [];

page_start('Treniruotė', ['admin' => true]);
?>
<div class="page-head">
  <h1 class="styled">Treniruotė</h1>
  <p>Pažymėkite, kas buvo treniruotėje, ir, jei reikia, parašykite pastabą vaikui.</p>
</div>

<?php
// Grupės - spalvoti kvadratai: pirma tos, kurios treniruojasi pasirinktą dieną (su laiku), po to visos pagal kategoriją
$tileGroups = q_all('SELECT g.*, (SELECT COUNT(*) FROM members m WHERE m.group_id = g.id AND m.status = "active") AS members_count,
                            (SELECT MIN(s.start_time) FROM schedule s WHERE s.group_id = g.id AND s.weekday = ?) AS day_time
                       FROM training_groups g WHERE g.is_active = 1 ORDER BY g.sort_order, g.name', [(int) date('N', strtotime($date))]);
$tile = function (array $g) use ($group, $date): string {
    $on = $group && (int) $group['id'] === (int) $g['id'];
    return '<a class="group-tile' . ($on ? ' on' : '') . '" style="--gc:' . e(group_color($g)) . '" href="' . e(url('admin/treniruote.php?g=' . (int) $g['id'] . '&d=' . $date)) . '"'
        . ($on ? ' aria-current="true"' : '') . '><span class="gt-name">' . e(group_short_name($g)) . '</span>'
        . '<span class="gt-meta">' . ($g['day_time'] ? '<b>' . e(fmt_time($g['day_time'])) . '</b> · ' : '') . (int) $g['members_count'] . ' nar.</span>'
        . (training_cancelled((int) $g['id'], $date) ? '<span class="gt-off">atšaukta</span>' : '') . '</a>';
};
$todayTiles = array_filter($tileGroups, function ($g) { return $g['day_time'] !== null; });
usort($todayTiles, function ($x, $y) { return strcmp((string) $x['day_time'], (string) $y['day_time']); });
?>
<details class="panel card group-picker"<?= $group ? '' : ' open' ?>>
  <summary>
    <?php if ($group): ?>
      <span class="gp-current"><span class="group-dot" style="background:<?= e(group_color($group)) ?>"></span><strong><?= e($group['name']) ?></strong>
        <?php if ($off = training_cancelled((int) $group['id'], $date)): ?><span class="cancel-tag">atšaukta · <?= e($off['reason']) ?></span><?php endif; ?></span>
      <span class="btn btn-ghost btn-sm">Keisti grupę</span>
    <?php else: ?>
      <strong>Pasirinkite grupę</strong>
    <?php endif; ?>
  </summary>
  <form method="get" class="gp-date">
    <?php if ($group): ?><input type="hidden" name="g" value="<?= (int) $group['id'] ?>"><?php endif; ?>
    <label>Data <input type="date" name="d" value="<?= e($date) ?>" onchange="this.form.submit()"></label>
    <?php if ($date !== date('Y-m-d')): ?><a class="small" href="<?= url('admin/treniruote.php' . ($group ? '?g=' . (int) $group['id'] : '')) ?>">Šiandien</a><?php endif; ?>
    <?php if (is_admin()): ?><a class="small gp-edit" href="<?= url('admin/grupes.php') ?>">✎ Grupių pavadinimai ir spalvos</a><?php endif; ?>
  </form>
  <?php if ($todayTiles): ?>
    <div class="kicker"><?= $date === date('Y-m-d') ? 'Šiandien treniruojasi' : 'Treniruojasi ' . e(mb_strtolower(LT_WEEKDAYS[(int) date('N', strtotime($date))])) ?></div>
    <div class="group-tiles"><?php foreach ($todayTiles as $g) { echo $tile($g); } ?></div>
  <?php endif; ?>
  <?php foreach (GROUP_CATEGORIES as $cat => $catLabel): $list = array_filter($tileGroups, function ($g) use ($cat) { return $g['category'] === $cat; }); if (!$list) { continue; } ?>
    <div class="kicker"><?= e($catLabel) ?></div>
    <div class="group-tiles"><?php foreach ($list as $g) { echo $tile($g); } ?></div>
  <?php endforeach; ?>
</details>

<?php if ($group && !$members): ?>
  <div class="panel card"><p class="muted">Šioje grupėje aktyvių narių nėra.</p></div>
<?php endif; ?>

<?php if ($members): ?>
  <form method="post" class="panel card notes-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="g" value="<?= (int) $groupId ?>">
    <input type="hidden" name="d" value="<?= e($date) ?>">
    <h2><?= e($group['name']) ?> · <?= e(fmt_date($date, true)) ?></h2>
    <div class="att-bar">
      <span>Buvo: <strong id="attPresent"><?= count(array_filter($att)) ?></strong> iš <?= count($members) ?></span>
      <button type="button" class="btn btn-primary btn-sm" id="attAll">✓ Visi buvo</button>
      <?php if (!$hasTraining && !training_cancelled($groupId, $date)): ?><span class="hint">Pagal tvarkaraštį šią dieną šios grupės treniruotės nėra - patikrinkite datą.</span><?php endif; ?>
    </div>
    <p class="hint" style="margin-bottom:10px;">Lankomumas išsaugomas iškart paspaudus „Nebuvo“ / „Buvo“ (paspaudus dar kartą - žyma nuimama). Pastaba: parašykite ir spauskite <strong>Enter</strong> -
      vaikas ir tėvai ją iškart mato paskyroje („Svarbu“). „Užduotis“ - vaikas pažymės „Atlikau“.</p>

    <?php foreach ($members as $m): ?>
      <div class="note-row">
        <div class="note-who" id="who<?= (int) $m['id'] ?>">
          <strong><?= e($m['first_name'] . ' ' . $m['last_name']) ?></strong>
          <?php $a1 = $att[$m['id']] ?? null; ?>
          <div class="att" data-member="<?= (int) $m['id'] ?>">
            <?php // „Buvo“ - dešinėje: laikant telefoną dešine ranka, nykščiu lengviau pasiekti ?>
            <button type="button" class="att-btn no<?= $a1 === 0 || $a1 === '0' ? ' on' : '' ?>" data-v="0">✗ Nebuvo</button>
            <button type="button" class="att-btn yes<?= $a1 === 1 || $a1 === '1' ? ' on' : '' ?>" data-v="1">✓ Buvo</button>
          </div>
          <?php foreach ($todayNotes[$m['id']] ?? [] as $n): ?>
            <div class="note-existing">
              <span>✓ <?= e(mb_strimwidth($n['body'], 0, 120, '…')) ?><?= $n['youtube_id'] ? ' ▶' : '' ?><?= $n['lesson_id'] ? ' · pamoka' : '' ?><?= $n['is_task'] ? ($n['done_at'] ? ' · užduotis ✓' : ' · užduotis') : '' ?></span>
              <button class="linklike danger-link" type="submit" form="del<?= (int) $n['id'] ?>" onclick="return confirm('Ištrinti šią pastabą?')">ištrinti</button>
            </div>
          <?php endforeach; ?>
        </div>
        <textarea name="note[<?= (int) $m['id'] ?>]" data-member="<?= (int) $m['id'] ?>" rows="2" enterkeyhint="send" placeholder="Parašykite ir spauskite Enter"></textarea>
        <div class="note-extras">
          <select name="lesson[<?= (int) $m['id'] ?>]" aria-label="Prisegti pamoką"><?= $lessonOptions ?></select>
          <label class="note-task" title="Tai užduotis: narys paskyroje matys mygtuką „Atlikau“ ir pažymės, kai atliks"><input type="checkbox" name="task[<?= (int) $m['id'] ?>]" value="1"> Užduotis</label>
          <details class="note-video">
            <summary>+ YouTube nuoroda</summary>
            <input type="url" name="video[<?= (int) $m['id'] ?>]" placeholder="https://youtu.be/...">
          </details>
        </div>
      </div>
    <?php endforeach; ?>

    <label class="check small notify"><input type="checkbox" name="notify" value="1"><span>Taip pat išsiųsti el. laišką (vaikui / tėvams)</span></label>
    <div class="sticky-save">
      <button class="btn btn-primary btn-block" type="submit">Išsaugoti pastabas</button>
    </div>
  </form>

  <?php foreach ($todayNotes as $list): foreach ($list as $n): ?>
    <form method="post" id="del<?= (int) $n['id'] ?>" style="display:none;">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="note_id" value="<?= (int) $n['id'] ?>">
      <input type="hidden" name="g" value="<?= (int) $groupId ?>"><input type="hidden" name="d" value="<?= e($date) ?>">
    </form>
  <?php endforeach; endforeach; ?>
<?php endif; ?>

<?php if ($recent): ?>
  <div class="panel card">
    <h2>Ankstesnės pastabos šiai grupei</h2>
    <ul class="list small">
      <?php foreach ($recent as $n): ?>
        <li class="row between">
          <span>
            <strong><?= e($n['first_name'] . ' ' . $n['last_name']) ?></strong> · <?= e(fmt_date($n['note_date'], true)) ?>
            <?= $n['read_at'] ? '<span class="badge badge-ok">perskaityta</span>' : '<span class="badge">neperskaityta</span>' ?>
            <?php if ($n['is_task']): ?><?= $n['done_at'] ? '<span class="badge badge-ok">✓ atlikta</span>' : '<span class="badge badge-warn">užduotis neatlikta</span>' ?><?php endif; ?>
            <div class="muted"><?= e($n['body']) ?><?= $n['youtube_id'] ? ' ▶ video' : '' ?></div>
          </span>
          <form method="post" class="inline-form" onsubmit="return confirm('Ištrinti šią pastabą?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="note_id" value="<?= (int) $n['id'] ?>">
            <input type="hidden" name="g" value="<?= (int) $groupId ?>"><input type="hidden" name="d" value="<?= e($date) ?>">
            <button class="btn btn-danger btn-sm" type="submit" aria-label="Ištrinti">✕</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
<script>
// Enter - iškart išsaugo to vaiko pastabą; Shift+Enter - nauja eilutė
(function () {
  var form = document.querySelector('.notes-form');
  if (!form) return;
  form.querySelectorAll('textarea[data-member]').forEach(function (ta) {
    ta.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' || e.shiftKey || e.isComposing) return;
      e.preventDefault();
      var mid = ta.dataset.member, text = ta.value.trim();
      var video = form.querySelector('[name="video[' + mid + ']"]');
      var lsel = form.querySelector('[name="lesson[' + mid + ']"]');
      if (!text && !(video && video.value.trim()) && !(lsel && lsel.value)) return;
      var data = new FormData();
      data.append('_csrf', form.querySelector('[name=_csrf]').value);
      data.append('action', 'save_one');
      data.append('g', form.querySelector('[name=g]').value);
      data.append('d', form.querySelector('[name=d]').value);
      data.append('member_id', mid);
      data.append('text', text);
      data.append('video', video ? video.value.trim() : '');
      var lesson = form.querySelector('[name="lesson[' + mid + ']"]'), task = form.querySelector('[name="task[' + mid + ']"]');
      if (lesson && lesson.value) data.append('lesson_id', lesson.value);
      if (task && task.checked) data.append('is_task', '1');
      if (form.querySelector('[name=notify]').checked) data.append('notify', '1');
      ta.disabled = true;
      fetch(location.pathname, { method: 'POST', body: data, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          ta.disabled = false;
          if (!res.ok) { alert(res.error || 'Nepavyko išsaugoti'); ta.focus(); return; }
          var line = document.createElement('div');
          line.className = 'note-existing just-saved';
          line.textContent = '✓ ' + res.preview + ' — išsaugota';
          document.getElementById('who' + mid).appendChild(line);
          ta.value = '';
          if (video) { video.value = ''; video.closest('details').open = false; }
          if (lesson) lesson.value = ''; if (task) task.checked = false;
          // pereiname prie kito vaiko laukelio
          var all = Array.prototype.slice.call(form.querySelectorAll('textarea[data-member]'));
          var next = all[all.indexOf(ta) + 1];
          if (next) next.focus();
        })
        .catch(function () { ta.disabled = false; alert('Nepavyko išsaugoti - patikrinkite internetą.'); });
    });
  });
})();
</script>
<script>
// Lankomumas: "Buvo" / "Nebuvo" išsaugoma iškart; tas pats mygtukas dar kartą - žyma nuimama
(function () {
  var form = document.querySelector('.notes-form');
  if (!form) return;
  function send(params) {
    var data = new FormData();
    data.append('_csrf', form.querySelector('[name=_csrf]').value);
    data.append('g', form.querySelector('[name=g]').value);
    data.append('d', form.querySelector('[name=d]').value);
    Object.keys(params).forEach(function (k) { data.append(k, params[k]); });
    return fetch(location.pathname, { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok) throw new Error();
        document.querySelectorAll('.att').forEach(function (box) {
          var v = res.marks[box.dataset.member];
          box.querySelector('.yes').classList.toggle('on', String(v) === '1');
          box.querySelector('.no').classList.toggle('on', String(v) === '0');
        });
        document.getElementById('attPresent').textContent = res.present;
      })
      .catch(function () { alert('Nepavyko išsaugoti lankomumo - patikrinkite internetą.'); });
  }
  document.querySelectorAll('.att-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var box = btn.closest('.att');
      send({ action: 'attendance', member_id: box.dataset.member, present: btn.classList.contains('on') ? '' : btn.dataset.v });
    });
  });
  var all = document.getElementById('attAll');
  if (all) all.addEventListener('click', function () { send({ action: 'attendance_all' }); });
})();
</script>
<?php
page_end();
