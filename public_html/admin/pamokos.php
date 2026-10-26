<?php
// Pamokos treneriui: aplankai aplankuose, pamokos iš blokų (tekstas, video, nuotrauka, pastaba...), tempimas pele
require dirname(__DIR__) . '/app/bootstrap.php';
require_once APP_DIR . '/images.php';
$me = require_staff();

// Pamoka redaguojama pačiame pamokos puslapyje (senos nuorodos ?edit=... nukreipiamos ten)
if (($edit = (string) get('edit')) !== '') {
    redirect($edit === 'new' ? 'pamokos.php?nauja=1' . ((int) get('aplankas') ? '&aplankas=' . (int) get('aplankas') : '') : 'pamokos.php?id=' . (int) $edit . '&keisti=1');
}
$folderEdit = (string) (get('folder') ?: post('folder'));
$errors = [];
$folders = lesson_folders();

if (is_post()) {
    csrf_check();
    $action = post('action');

    // Tvarka po tempimo: aplankai ir pamokos su nauja vieta
    if ($action === 'order') {
        header('Content-Type: application/json; charset=utf-8');
        $tree = json_decode((string) post('tree'), true);
        $fRows = is_array($tree['folders'] ?? null) ? $tree['folders'] : [];
        $lRows = is_array($tree['lessons'] ?? null) ? $tree['lessons'] : [];
        $parents = [];
        foreach ($fRows as $r) {
            $parents[(int) $r[0]] = $r[1] !== null ? (int) $r[1] : null;
        }
        // Patikra: aplankas negali atsidurti savo paties viduje
        foreach (array_keys($parents) as $id) {
            $seen = [];
            for ($p = $parents[$id]; $p !== null; $p = $parents[$p] ?? null) {
                if ($p === $id || isset($seen[$p]) || !isset($folders[$p])) {
                    echo json_encode(['ok' => false, 'error' => 'Netinkama vieta.']);
                    exit;
                }
                $seen[$p] = true;
            }
        }
        db()->beginTransaction();
        foreach ($fRows as $r) {
            if (isset($folders[(int) $r[0]])) {
                q('UPDATE lesson_folders SET parent_id = ?, sort_order = ? WHERE id = ?', [$r[1] !== null ? (int) $r[1] : null, (int) $r[2], (int) $r[0]]);
            }
        }
        foreach ($lRows as $r) {
            $fid = $r[1] !== null ? (int) $r[1] : null;
            if ($fid === null || isset($folders[$fid])) {
                q('UPDATE lessons SET folder_id = ?, sort_order = ? WHERE id = ?', [$fid, (int) $r[2], (int) $r[0]]);
            }
        }
        db()->commit();
        echo json_encode(['ok' => true]);
        exit;
    }

    // Tvarka pamokų puslapyje: [tipas f|l, id, tėvinis aplankas, vieta] - tik tie sąrašai, kurie ten rodomi
    if ($action === 'order_items') {
        header('Content-Type: application/json; charset=utf-8');
        $items = json_decode((string) post('items'), true);
        $items = is_array($items) ? $items : [];
        $parents = [];
        foreach ($folders as $fid => $f) {
            $parents[$fid] = $f['parent_id'] !== null ? (int) $f['parent_id'] : null;
        }
        foreach ($items as $r) {
            if (($r[0] ?? '') === 'f' && isset($folders[(int) $r[1]])) {
                $parents[(int) $r[1]] = $r[2] !== null ? (int) $r[2] : null;
            }
        }
        foreach (array_keys($parents) as $fid) {
            $seen = [];
            for ($p = $parents[$fid]; $p !== null; $p = $parents[$p] ?? null) {
                if ($p === $fid || isset($seen[$p]) || !isset($folders[$p])) {
                    echo json_encode(['ok' => false, 'error' => 'Aplanko negalima įdėti į jį patį.']);
                    exit;
                }
                $seen[$p] = true;
            }
        }
        db()->beginTransaction();
        foreach ($items as $r) {
            $parent = ($r[2] ?? null) !== null ? (int) $r[2] : null;
            if ($parent !== null && !isset($folders[$parent])) {
                continue;
            }
            if (($r[0] ?? '') === 'f' && isset($folders[(int) $r[1]])) {
                q('UPDATE lesson_folders SET parent_id = ?, sort_order = ? WHERE id = ?', [$parent, (int) $r[3], (int) $r[1]]);
            } elseif (($r[0] ?? '') === 'l') {
                q('UPDATE lessons SET folder_id = ?, sort_order = ? WHERE id = ?', [$parent, (int) $r[3], (int) $r[1]]);
            }
        }
        db()->commit();
        echo json_encode(['ok' => true]);
        exit;
    }

    // Greitas naujas aplankas (tik pavadinimas)
    if ($action === 'folder_quick') {
        $title = trim(post('title'));
        $parent = (int) post('parent_id') ?: null;
        if ($title !== '' && ($parent === null || isset($folders[$parent]))) {
            $sort = (int) q_value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM lesson_folders WHERE parent_id <=> ?', [$parent]);
            // Diržo pavadinimo aplankas (pvz. „8 kyu“) iš karto gauna tą diržą
            $belt = null;
            foreach (BELTS as $lvl => [$kyu]) {
                if (mb_strtolower($title) === $kyu) {
                    $belt = $lvl;
                }
            }
            q('INSERT INTO lesson_folders (parent_id, title, icon, belt_level, sort_order) VALUES (?, ?, ?, ?, ?)', [$parent, mb_substr($title, 0, 120), $belt ? '' : '📁', $belt, $sort]);
            flash('ok', 'Aplankas „' . $title . '“ sukurtas. Pamokas į jį galima nutempti pele.');
        }
        redirect(lessons_back('admin/pamokos.php'));
    }

    if ($action === 'folder_save') {
        $id = (int) post('id');
        $title = trim(post('title'));
        $parent = (int) post('parent_id') ?: null;
        $audience = isset(QUIZ_AUDIENCE[post('audience')]) ? post('audience') : 'karateka';
        $icon = mb_substr(trim(post('icon')), 0, 8) ?: null;
        $belt = belt_from_post('belt_level');
        if ($title === '') {
            $errors[] = 'Įveskite aplanko pavadinimą.';
        }
        if ($parent !== null && (!isset($folders[$parent]) || ($id && in_array($parent, folder_descendant_ids($folders, $id), true)))) {
            $errors[] = 'Aplanko negalima įdėti į jį patį.';
        }
        if (!$errors) {
            $vals = [$parent, mb_substr($title, 0, 120), $icon, post('description') ?: null, $audience, $belt, !empty($_POST['is_published']) ? 1 : 0];
            if ($id) {
                $moved = isset($folders[$id]) && $folders[$id]['parent_id'] != $parent;
                q('UPDATE lesson_folders SET parent_id = ?, title = ?, icon = ?, description = ?, audience = ?, belt_level = ?, is_published = ?' . ($moved ? ', sort_order = 9999' : '') . ' WHERE id = ?', array_merge($vals, [$id]));
            } else {
                $sort = (int) q_value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM lesson_folders WHERE parent_id <=> ?', [$parent]);
                q('INSERT INTO lesson_folders (parent_id, title, icon, description, audience, belt_level, is_published, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', array_merge($vals, [$sort]));
            }
            flash('ok', 'Aplankas išsaugotas.');
            redirect(lessons_back('admin/pamokos.php'));
        }
        if (post('back') !== '') {
            flash('err', implode(' ', $errors));
            redirect(lessons_back('admin/pamokos.php'));
        }
    }

    // Ištrinant aplanką jo turinys (pamokos ir poaplankiai) perkeliamas lygiu aukščiau - niekas nedingsta
    if ($action === 'folder_delete' && ($id = (int) post('id')) && isset($folders[$id])) {
        $up = $folders[$id]['parent_id'] !== null ? (int) $folders[$id]['parent_id'] : null;
        db()->beginTransaction();
        q('UPDATE lessons SET folder_id = ? WHERE folder_id = ?', [$up, $id]);
        q('UPDATE lesson_folders SET parent_id = ? WHERE parent_id = ?', [$up, $id]);
        q('DELETE FROM lesson_folders WHERE id = ?', [$id]);
        db()->commit();
        flash('ok', 'Aplankas ištrintas, jo turinys perkeltas lygiu aukščiau.');
        // Ištrintas aplankas, kuriame buvome - grįžtame lygiu aukščiau
        $back = lessons_back('admin/pamokos.php');
        redirect(preg_match('~aplankas=' . $id . '(&|$)~', $back) ? 'pamokos.php' . ($up ? '?aplankas=' . $up . '&' : '?') . 'tvarkyti=1' : $back);
    }

    if ($action === 'lesson_copy' && ($id = (int) post('id')) && ($src = q_one('SELECT * FROM lessons WHERE id = ?', [$id]))) {
        db()->beginTransaction();
        q('INSERT INTO lessons (folder_id, sort_order, title, topic, belt_level, audience, body, videos, is_published, author_id)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)',
            [$src['folder_id'], (int) $src['sort_order'] + 1, mb_substr($src['title'] . ' (kopija)', 0, 190), $src['topic'], $src['belt_level'], $src['audience'], $src['body'], $src['videos'], $me['id']]);
        $new = (int) db()->lastInsertId();
        q('INSERT INTO lesson_blocks (lesson_id, type, body, youtube_id, image, url, caption, sort_order)
           SELECT ?, type, body, youtube_id, image, url, caption, sort_order FROM lesson_blocks WHERE lesson_id = ?', [$new, $id]);
        q('INSERT INTO lesson_groups (lesson_id, group_id) SELECT ?, group_id FROM lesson_groups WHERE lesson_id = ?', [$new, $id]);
        db()->commit();
        flash('ok', 'Sukurta kopija (nepaskelbta). Pakeiskite ir paskelbkite.');
        redirect('pamokos.php?id=' . $new . '&keisti=1');
    }

    // Pamoką su komentaru nusiųsti pasirinktiems nariams (kiekvienam - trenerio pastaba su prisegta pamoka)
    if ($action === 'lesson_send' && ($id = (int) post('id')) && q_value('SELECT 1 FROM lessons WHERE id = ?', [$id])) {
        $picked = member_tree_from_post();
        $ids = $picked['members'];
        if ($picked['groups']) {
            $ids = array_merge($ids, array_map('intval', q("SELECT id FROM members WHERE status = 'active' AND group_id IN (" . implode(',', $picked['groups']) . ')')->fetchAll(PDO::FETCH_COLUMN)));
        }
        $ids = array_values(array_unique($ids));
        if (!$ids) {
            flash('err', 'Pažymėkite, kam siųsti.');
            redirect('admin/pamokos.php?siusti=' . $id);
        }
        $body = trim(str_replace("\r\n", "\n", post('body')));
        foreach ($ids as $mid) {
            $m = q_one('SELECT * FROM members WHERE id = ? AND status = "active"', [$mid]);
            if ($m) {
                send_coach_note($m, $me, $body, $id, !empty($_POST['is_task']), !empty($_POST['email']));
            }
        }
        flash('ok', 'Pamoka išsiųsta: ' . count($ids) . ' nar. Jie ją matys paskyroje („Trenerio pastabos“) ir galės atsakyti.');
        redirect('pamokos.php?id=' . $id);
    }

    if ($action === 'delete' && ($id = (int) post('id'))) {
        q('DELETE FROM lessons WHERE id = ?', [$id]);
        flash('ok', 'Pamoka ištrinta.');
        redirect(lessons_back('admin/pamokos.php'));
    }

}

$folders = lesson_folders();

// ---------- Pamokos siuntimas nariams ----------
if ($sendId = (int) get('siusti')) {
    $l = q_one('SELECT * FROM lessons WHERE id = ?', [$sendId]);
    if (!$l) {
        not_found();
    }
    page_start('Siųsti pamoką: ' . $l['title'], ['admin' => true, 'section' => 'pamokos']);
    ?>
    <a class="small" href="<?= url('pamokos.php?id=' . $sendId) ?>">← <?= e($l['title']) ?></a>
    <h1 class="styled" style="margin-top:8px;">📨 Siųsti pamoką nariams</h1>
    <p class="muted">Kiekvienas pažymėtas narys gaus pastabą su šia pamoka savo paskyroje (tėvai - vaiko paskyroje) ir galės jums atsakyti. Atsakymus matysite skiltyje „Žinutės“.</p>
    <form method="post" class="form lesson-send">
      <?= csrf_field() ?><input type="hidden" name="action" value="lesson_send"><input type="hidden" name="id" value="<?= $sendId ?>">
      <div class="grid-2">
        <div class="panel card">
          <div class="kicker">Pamoka</div>
          <p><strong><?= e($l['title']) ?></strong><?= $l['belt_level'] !== null ? ' ' . belt_chip((int) $l['belt_level']) : '' ?><?= !$l['is_published'] ? ' <span class="badge badge-warn">nepaskelbta - nariai jos neatidarys</span>' : '' ?></p>
          <label>Komentaras <span class="hint">nebūtina</span>
            <textarea name="body" rows="4" maxlength="<?= MESSAGE_MAX ?>" placeholder="pvz. Prieš egzaminą pasižiūrėkite ir pakartokite 3 kartus."></textarea></label>
          <label class="check"><input type="checkbox" name="is_task" value="1"><span>Kaip užduotis (narys pažymi „Atlikau“)</span></label>
          <label class="check"><input type="checkbox" name="email" value="1" checked><span>Išsiųsti laišką</span></label>
          <div style="margin-top:8px;"><button class="btn btn-primary" type="submit">📨 Siųsti</button></div>
        </div>
        <div class="panel card">
          <div class="kicker">Kam</div>
          <?= member_tree_field([], [], ['hint' => 'Pažymėkite visą grupę arba išskleiskite ▸ ir pasirinkite tik norimus.']) ?>
        </div>
      </div>
    </form>
    <?php
    page_end();
    exit;
}

// ---------- Aplanko forma ----------
if ($folderEdit !== '') {
    $f = $folderEdit === 'new'
        ? ['id' => 0, 'parent_id' => (int) get('parent') ?: null, 'title' => '', 'icon' => '📁', 'description' => '', 'audience' => 'karateka', 'belt_level' => null, 'is_published' => 1]
        : ($folders[(int) $folderEdit] ?? null);
    if (!$f) {
        not_found();
    }
    if ($errors) {
        $f = array_merge($f, ['parent_id' => (int) post('parent_id') ?: null, 'title' => post('title'), 'icon' => post('icon'), 'description' => post('description'),
            'audience' => post('audience'), 'belt_level' => belt_from_post('belt_level'), 'is_published' => !empty($_POST['is_published'])]);
    }
    $exclude = $f['id'] ? folder_descendant_ids($folders, (int) $f['id']) : [];
    page_start($f['id'] ? 'Aplankas: ' . $f['title'] : 'Naujas aplankas', ['admin' => true, 'section' => 'pamokos']);
    ?>
    <a class="small" href="<?= url('admin/pamokos.php') ?>">← Pamokų tvarkymas</a>
    <h1 class="styled" style="margin-top:8px;"><?= $f['id'] ? 'Aplankas' : 'Naujas aplankas' ?></h1>
    <?= form_errors($errors) ?>
    <form method="post" class="form panel card folder-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="folder_save"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><input type="hidden" name="folder" value="<?= e($folderEdit) ?>">
      <div class="form-row">
        <label>Pavadinimas <input type="text" name="title" value="<?= e($f['title']) ?>" maxlength="120" required placeholder="pvz. Kata"></label>
        <label>Kur <select name="parent_id"><?= folder_options($folders, $f['parent_id'] !== null ? (int) $f['parent_id'] : null, $exclude) ?></select></label>
      </div>
      <label>Ženkliukas
        <input type="text" name="icon" id="folderIcon" value="<?= e((string) $f['icon']) ?>" maxlength="8" style="max-width:90px;">
        <?= icon_picker('folderIcon', $f['icon']) ?>
      </label>
      <label>Diržas <span class="hint">pvz. Egzaminams → „8 kyu“: narys iš karto mato tik savo diržo aplanką</span>
        <select name="belt_level" style="max-width:320px;"><?= belt_options($f['belt_level'] !== null ? (int) $f['belt_level'] : null, 'Visiems diržams') ?></select></label>
      <label>Aprašymas <span class="hint">nebūtina; rodomas aplanko viršuje</span>
        <textarea name="description" rows="3"><?= e((string) $f['description']) ?></textarea></label>
      <div class="seg-choice">
        <?php foreach (QUIZ_AUDIENCE as $k => $label): ?>
          <label class="check"><input type="radio" name="audience" value="<?= $k ?>" <?= $f['audience'] === $k ? 'checked' : '' ?>><span><?= e($label) ?></span></label>
        <?php endforeach; ?>
      </div>
      <p class="hint" style="margin-top:-6px;">„Teisėjams“ / „Egzaminatoriams“ - visą aplanką mato tik su ta žyma.</p>
      <label class="check"><input type="checkbox" name="is_published" value="1" <?= $f['is_published'] ? 'checked' : '' ?>><span>Paskelbtas (nariai mato)</span></label>
      <div class="row" style="gap:8px;">
        <button class="btn btn-primary" type="submit">Išsaugoti</button>
        <?php if ($f['id']): ?>
          <button class="btn btn-danger" type="submit" name="action" value="folder_delete" formnovalidate onclick="return confirm('Ištrinti aplanką? Jo pamokos ir poaplankiai bus perkelti lygiu aukščiau.')">Ištrinti aplanką</button>
        <?php endif; ?>
      </div>
    </form>
    <script>
    document.querySelectorAll('.icon-pick').forEach(function (p) {
      p.addEventListener('click', function (ev) { var b = ev.target.closest('button'); if (b) { document.getElementById(p.dataset.for).value = b.dataset.icon; p.querySelector('.icon-now').innerHTML = b.innerHTML; } });
    });
    </script>
    <?php
    page_end();
    exit;
}

// ---------- Medis: aplankai ir pamokos ----------
$lessons = q_all('SELECT l.*, (SELECT GROUP_CONCAT(g.name SEPARATOR ", ") FROM lesson_groups lg JOIN training_groups g ON g.id = lg.group_id WHERE lg.lesson_id = l.id) AS group_names
                    FROM lessons l ORDER BY l.sort_order, l.id');
$info = lesson_card_info($lessons);
$byFolder = [];
foreach ($lessons as $l) {
    $byFolder[$l['folder_id'] === null ? 0 : (int) $l['folder_id']][] = $l;
}
$counts = folder_lesson_counts($folders, $lessons);

function render_lesson_tree(?int $parent, array $folders, array $byFolder, array $info, array $counts): string
{
    $html = '<ul class="ltree" data-folder="' . ($parent ?? '') . '">';
    foreach ($folders as $f) {
        if (($f['parent_id'] !== null ? (int) $f['parent_id'] : null) !== $parent) {
            continue;
        }
        $fid = (int) $f['id'];
        $html .= '<li class="lt-folder" data-type="folder" data-id="' . $fid . '" id="f' . $fid . '"><div class="lt-row">'
            . '<span class="lt-drag" title="Tempkite">⠿</span><button type="button" class="lt-toggle" aria-label="Išskleisti / suskleisti">▾</button>'
            . '<span class="lt-icon">' . folder_icon($f) . '</span>'
            . '<a class="lt-title" href="?folder=' . $fid . '">' . e($f['title']) . '</a>'
            . '<span class="lt-meta">' . ($counts[$fid] ?? 0) . ' pam.</span>'
            . ($f['belt_level'] !== null ? belt_chip((int) $f['belt_level']) : '')
            . ($f['audience'] !== 'karateka' ? '<span class="badge badge-tag">' . e(QUIZ_AUDIENCE[$f['audience']]) . '</span>' : '')
            . (!$f['is_published'] ? '<span class="badge badge-warn">nepaskelbtas</span>' : '')
            . '<span class="lt-actions"><a class="btn btn-ghost btn-sm" href="' . e(url('pamokos.php?nauja=1&aplankas=' . $fid)) . '">+ Pamoka</a>'
            . '<a class="btn btn-ghost btn-sm" href="?folder=new&amp;parent=' . $fid . '">+ Aplankas</a>'
            . '<button type="button" class="lt-up" title="Aukštyn">↑</button><button type="button" class="lt-down" title="Žemyn">↓</button></span></div>'
            . render_lesson_tree($fid, $folders, $byFolder, $info, $counts) . '</li>';
    }
    foreach ($byFolder[$parent ?? 0] ?? [] as $l) {
        $i = $info[(int) $l['id']];
        $groups = $l['group_names'] ? explode(', ', $l['group_names']) : [];
        $meta = array_filter([$i['videos'] ? '▶ ' . $i['videos'] : '', $i['images'] ? '🖼 ' . $i['images'] : '',
            $groups ? 'tik: ' . (count($groups) > 2 ? count($groups) . ' grupėms' : implode(', ', $groups)) : '']);
        $html .= '<li class="lt-lesson" data-type="lesson" data-id="' . (int) $l['id'] . '" id="l' . (int) $l['id'] . '"><div class="lt-row">'
            . '<span class="lt-drag" title="Tempkite">⠿</span><span class="lt-icon">' . ($i['video'] ? '▶' : '📄') . '</span>'
            . '<a class="lt-title" href="' . e(url('pamokos.php?id=' . (int) $l['id'] . '&keisti=1')) . '">' . e($l['title']) . '</a>'
            . ($l['belt_level'] !== null ? belt_chip((int) $l['belt_level']) : '')
            . ($l['audience'] !== 'karateka' ? '<span class="badge badge-tag">' . e(QUIZ_AUDIENCE[$l['audience']]) . '</span>' : '')
            . (!$l['is_published'] ? '<span class="badge badge-warn">nepaskelbta</span>' : '')
            . ($meta ? '<span class="lt-meta">' . e(implode(' · ', $meta)) . '</span>' : '')
            . '<span class="lt-actions"><a class="btn btn-ghost btn-sm" href="?siusti=' . (int) $l['id'] . '" title="Siųsti nariams su komentaru">📨</a>'
            . '<a class="btn btn-ghost btn-sm" href="' . e(url('pamokos.php?id=' . (int) $l['id'])) . '" target="_blank" title="Peržiūrėti kaip narys">👁</a>'
            . '<a class="btn btn-ghost btn-sm" href="' . e(url('pamokos.php?id=' . (int) $l['id'] . '&keisti=1')) . '">✎ Keisti</a>'
            . '<button type="button" class="lt-up" title="Aukštyn">↑</button><button type="button" class="lt-down" title="Žemyn">↓</button></span></div></li>';
    }
    return $html . '</ul>';
}

page_start('Pamokų tvarkymas', ['admin' => true, 'section' => 'pamokos']);
?>
<a class="small" href="<?= url('pamokos.php') ?>">← Pamokos (kaip mato nariai)</a> · <a class="small" href="<?= url('pamokos.php?tvarkyti=1') ?>">✎ Tvarkyti pamokų puslapyje</a>
<div class="page-head row between" style="align-items:flex-end; flex-wrap:wrap; gap:12px; margin-top:8px;">
  <div>
    <h1 class="styled">Pamokų tvarkymas</h1>
    <p class="muted" style="margin:0;">Aplankai, poaplankiai ir pamokos. Tempkite <strong>⠿</strong>, kad pakeistumėte tvarką ar perkeltumėte į kitą aplanką - išsaugoma iš karto.</p>
  </div>
  <div class="row" style="gap:8px;">
    <a class="btn btn-ghost" href="?folder=new">+ Aplankas</a>
    <a class="btn btn-primary" href="<?= url('pamokos.php?nauja=1') ?>">+ Nauja pamoka</a>
  </div>
</div>
<div class="panel card lesson-tree-card">
  <div class="row between lt-toolbar">
    <form method="post" class="row lt-quick" style="gap:6px;">
      <?= csrf_field() ?><input type="hidden" name="action" value="folder_quick">
      <input type="text" name="title" placeholder="Greitai: naujo aplanko pavadinimas" maxlength="120" required>
      <button class="btn btn-ghost btn-sm" type="submit">Sukurti</button>
    </form>
    <span class="row" style="gap:6px;"><button type="button" class="btn btn-ghost btn-sm" id="ltOpen">Išskleisti viską</button><button type="button" class="btn btn-ghost btn-sm" id="ltClose">Suskleisti</button></span>
  </div>
  <?php if (!$folders && !$lessons): ?>
    <p class="muted">Dar nieko nėra. Pradėkite nuo aplanko, pvz. „Kata“, tada į jį pridėkite pamokų.</p>
  <?php endif; ?>
  <div id="ltree"><?= render_lesson_tree(null, $folders, $byFolder, $info, $counts) ?></div>
  <p class="small muted" style="margin-top:12px;">Aplanko pavadinimą, ženkliuką ir kam jis skirtas pakeisite paspaudę ant jo pavadinimo. Telefone tvarką patogiau keisti mygtukais ↑ ↓.</p>
</div>
<div class="toast" id="ltToast" hidden></div>
<script src="<?= e(asset_url('assets/vendor/sortable.min.js')) ?>"></script>
<script>
(function () {
  var root = document.getElementById('ltree'), toast = document.getElementById('ltToast'), csrf = <?= json_encode(csrf_token()) ?>, timer;
  function say(text, bad) { toast.textContent = text; toast.className = 'toast' + (bad ? ' bad' : ''); toast.hidden = false; clearTimeout(timer); timer = setTimeout(function () { toast.hidden = true; }, 2200); }
  function collect() {
    var out = { folders: [], lessons: [] };
    (function walk(ul, parent) {
      Array.prototype.forEach.call(ul.children, function (li, i) {
        var id = +li.dataset.id;
        if (li.dataset.type === 'folder') { out.folders.push([id, parent, i + 1]); walk(li.querySelector(':scope > ul'), id); }
        else { out.lessons.push([id, parent, i + 1]); }
      });
    })(root.querySelector(':scope > ul'), null);
    return out;
  }
  function save() {
    var fd = new FormData(); fd.append('_csrf', csrf); fd.append('action', 'order'); fd.append('tree', JSON.stringify(collect()));
    fetch(location.pathname, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); })
      .then(function (d) { d.ok ? say('✓ Tvarka išsaugota') : say(d.error || 'Nepavyko išsaugoti', true); if (!d.ok) setTimeout(function () { location.reload(); }, 1200); })
      .catch(function () { say('Nepavyko išsaugoti - atnaujinkite puslapį', true); });
  }
  root.querySelectorAll('ul.ltree').forEach(function (ul) {
    Sortable.create(ul, { group: 'ltree', handle: '.lt-drag', animation: 150, forceFallback: true, fallbackOnBody: true, swapThreshold: 0.65, emptyInsertThreshold: 14,
      delayOnTouchOnly: true, delay: 120, onStart: function () { document.body.classList.add('lt-dragging'); },
      onEnd: function () { document.body.classList.remove('lt-dragging'); save(); } });
  });
  root.addEventListener('click', function (ev) {
    var b = ev.target.closest('button'); if (!b) return;
    var li = b.closest('li');
    if (b.classList.contains('lt-up') && li.previousElementSibling) { li.parentNode.insertBefore(li, li.previousElementSibling); save(); }
    if (b.classList.contains('lt-down') && li.nextElementSibling) { li.parentNode.insertBefore(li.nextElementSibling, li); save(); }
    if (b.classList.contains('lt-toggle')) { li.classList.toggle('collapsed'); remember(); }
  });
  // Kurie aplankai suskleisti - prisimenama šioje naršyklėje
  var KEY = 'karateka-lessons-collapsed';
  function remember() { try { localStorage.setItem(KEY, JSON.stringify(Array.prototype.map.call(root.querySelectorAll('li.collapsed'), function (li) { return li.dataset.id; }))); } catch (e) {} }
  try { (JSON.parse(localStorage.getItem(KEY) || '[]') || []).forEach(function (id) { var li = document.getElementById('f' + id); if (li) li.classList.add('collapsed'); }); } catch (e) {}
  document.getElementById('ltOpen').onclick = function () { root.querySelectorAll('li.collapsed').forEach(function (li) { li.classList.remove('collapsed'); }); remember(); };
  document.getElementById('ltClose').onclick = function () { root.querySelectorAll('li.lt-folder').forEach(function (li) { li.classList.add('collapsed'); }); remember(); };
  // Po išsaugojimo - parodyti pamoką medyje
  if (location.hash) { var t = document.querySelector(location.hash); if (t) { var p = t.parentElement; while (p && p !== root) { if (p.classList && p.classList.contains('collapsed')) p.classList.remove('collapsed'); p = p.parentElement; } t.classList.add('lt-flash'); } }
})();
</script>
<?php
page_end();
