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

function save_coach_note(array $m, array $me, string $date, string $text, ?string $yt, ?int $lessonId, bool $isTask): void
{
    if ($text === '') {
        $text = $lessonId ? 'Pažiūrėk prisegtą pamoką.' : 'Pažiūrėk video.';
    }
    q('INSERT INTO coach_notes (member_id, author_id, note_date, body, youtube_id, lesson_id, is_task) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$m['id'], $me['id'], $date, $text, $yt, $lessonId, $isTask ? 1 : 0]);
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
        redirect('admin/pastabos.php?g=' . $groupId . '&d=' . $date);
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
        save_coach_note($m, $me, $date, $text, $yt, $lessonId, $isTask);
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
            save_coach_note($m, $me, $date, $text, $yt, $lessonId, $isTask);
            $saved++;

            if ($notify) {
                notify_note($m, $text, $date, $me);
            }
        }
        if ($badLinks) {
            flash('err', 'Neatpažintos YouTube nuorodos (pastabos išsaugotos be video): ' . implode(', ', $badLinks));
        }
        flash('ok', $saved ? "Išsaugota pastabų: $saved" : 'Nieko neįrašyta.');
        redirect('admin/pastabos.php?g=' . $groupId . '&d=' . $date);
    }
}

$members = $group ? q_all('SELECT * FROM members WHERE group_id = ? AND status = "active" ORDER BY last_name, first_name', [$groupId]) : [];
// Pamokos prisegimui (pagal diržą)
$lessonOptions = '<option value="">+ Prisegti pamoką</option>';
foreach (q_all('SELECT id, title, belt_level FROM lessons ORDER BY belt_level IS NOT NULL, belt_level, title') as $ls) {
    $lessonOptions .= '<option value="' . (int) $ls['id'] . '">' . e(($ls['belt_level'] ? BELTS[(int) $ls['belt_level']][0] . ' · ' : '') . $ls['title']) . '</option>';
}
$todayNotes = [];
if ($group) {
    foreach (q_all('SELECT cn.*, a.first_name AS author FROM coach_notes cn JOIN members m ON m.id = cn.member_id LEFT JOIN accounts a ON a.id = cn.author_id
                     WHERE m.group_id = ? AND cn.note_date = ? ORDER BY cn.created_at', [$groupId, $date]) as $n) {
        $todayNotes[$n['member_id']][] = $n;
    }
}
$recent = $group ? q_all('SELECT cn.*, m.first_name, m.last_name, a.first_name AS author FROM coach_notes cn JOIN members m ON m.id = cn.member_id LEFT JOIN accounts a ON a.id = cn.author_id
                           WHERE m.group_id = ? AND cn.note_date <> ? ORDER BY cn.note_date DESC, cn.id DESC LIMIT 20', [$groupId, $date]) : [];

page_start('Pastabos', ['admin' => true]);
?>
<div class="page-head">
  <h1 class="styled">Pastabos po treniruotės</h1>
  <p>Parašykite pastabą vaikui - ją matys jis pats ir tėvai savo paskyroje.</p>
</div>

<form method="get" class="panel card form notes-filter">
  <label>Grupė <select name="g" onchange="this.form.submit()" required><?= group_options($group ? (int) $group['id'] : null) ?></select></label>
  <label>Treniruotės data <input type="date" name="d" value="<?= e($date) ?>" onchange="this.form.submit()"></label>
  <button class="btn btn-ghost" type="submit">Rodyti</button>
</form>

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
    <p class="hint" style="margin-bottom:10px;">Parašykite pastabą ir spauskite <strong>Enter</strong> - ji iškart išsaugoma. Nauja eilutė: Shift+Enter.</p>

    <?php foreach ($members as $m): ?>
      <div class="note-row">
        <div class="note-who" id="who<?= (int) $m['id'] ?>">
          <strong><?= e($m['first_name'] . ' ' . $m['last_name']) ?></strong>
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
          <label class="note-task"><input type="checkbox" name="task[<?= (int) $m['id'] ?>]" value="1"> Užduotis (pažymės „Atlikta“)</label>
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
<?php
page_end();
