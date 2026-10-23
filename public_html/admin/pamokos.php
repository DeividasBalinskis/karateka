<?php
// Pamokos treneriui: aplankai aplankuose, pamokos iš blokų (tekstas, video, nuotrauka, pastaba...), tempimas pele
require dirname(__DIR__) . '/app/bootstrap.php';
require APP_DIR . '/images.php';
$me = require_staff();

$edit = (string) (get('edit') ?: post('edit'));
$folderEdit = (string) (get('folder') ?: post('folder'));
$errors = [];
$folders = lesson_folders();

/** Formos blokai -> blokai saugojimui. Klaidos įrašomos į $errors. Grąžina ir „žalius“ duomenis formai. */
function lesson_blocks_from_post(array &$errors): array
{
    $out = [];
    foreach ((array) ($_POST['blocks'] ?? []) as $key => $b) {
        $type = (string) ($b['type'] ?? '');
        if (!isset(LESSON_BLOCK_TYPES[$type]) || !is_array($b)) {
            continue;
        }
        $row = ['type' => $type, 'body' => null, 'youtube_id' => null, 'image' => null, 'url' => null,
            'caption' => trim((string) ($b['caption'] ?? '')) ?: null, 'raw_url' => trim((string) ($b['url'] ?? ''))];
        $body = trim(str_replace("\r\n", "\n", (string) ($b['body'] ?? '')));
        switch ($type) {
            case 'heading':
            case 'text':
            case 'note':
                if ($body === '') {
                    continue 2;
                }
                $row['body'] = $body;
                break;
            case 'video':
                if ($row['raw_url'] === '') {
                    continue 2;
                }
                $row['youtube_id'] = youtube_id($row['raw_url']);
                if (!$row['youtube_id']) {
                    $errors[] = 'Neatpažinta YouTube nuoroda: ' . $row['raw_url'];
                }
                break;
            case 'image':
                $existing = basename((string) ($b['image'] ?? ''));
                $row['image'] = preg_match('/^[a-f0-9]{8,64}\.jpg$/', $existing) ? $existing : null;
                $f = $_FILES['block_image'] ?? null;
                if ($f && isset($f['error'][$key]) && $f['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                    try {
                        $row['image'] = save_uploaded_image(['name' => $f['name'][$key], 'type' => $f['type'][$key], 'tmp_name' => $f['tmp_name'][$key],
                            'error' => $f['error'][$key], 'size' => $f['size'][$key]], LESSON_DIR);
                    } catch (RuntimeException $ex) {
                        $errors[] = $ex->getMessage();
                    }
                }
                if (!$row['image']) {
                    continue 2;
                }
                break;
            case 'link':
                if ($row['raw_url'] === '') {
                    continue 2;
                }
                if (!preg_match('~^https?://~i', $row['raw_url'])) {
                    $errors[] = 'Nuoroda turi prasidėti http:// arba https:// - ' . $row['raw_url'];
                }
                $row['url'] = $row['raw_url'];
                $row['body'] = $body !== '' ? $body : null;
                break;
        }
        $out[] = $row;
    }
    return $out;
}

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

    // Greitas naujas aplankas (tik pavadinimas)
    if ($action === 'folder_quick') {
        $title = trim(post('title'));
        $parent = (int) post('parent_id') ?: null;
        if ($title !== '' && ($parent === null || isset($folders[$parent]))) {
            $sort = (int) q_value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM lesson_folders WHERE parent_id <=> ?', [$parent]);
            q('INSERT INTO lesson_folders (parent_id, title, icon, sort_order) VALUES (?, ?, ?, ?)', [$parent, mb_substr($title, 0, 120), '📁', $sort]);
            flash('ok', 'Aplankas „' . $title . '“ sukurtas. Pamokas į jį galima nutempti pele.');
        }
        redirect('admin/pamokos.php');
    }

    if ($action === 'folder_save') {
        $id = (int) post('id');
        $title = trim(post('title'));
        $parent = (int) post('parent_id') ?: null;
        $audience = isset(QUIZ_AUDIENCE[post('audience')]) ? post('audience') : 'karateka';
        $icon = mb_substr(trim(post('icon')), 0, 8) ?: null;
        if ($title === '') {
            $errors[] = 'Įveskite aplanko pavadinimą.';
        }
        if ($parent !== null && (!isset($folders[$parent]) || ($id && in_array($parent, folder_descendant_ids($folders, $id), true)))) {
            $errors[] = 'Aplanko negalima įdėti į jį patį.';
        }
        if (!$errors) {
            $vals = [$parent, mb_substr($title, 0, 120), $icon, post('description') ?: null, $audience, !empty($_POST['is_published']) ? 1 : 0];
            if ($id) {
                $moved = isset($folders[$id]) && $folders[$id]['parent_id'] != $parent;
                q('UPDATE lesson_folders SET parent_id = ?, title = ?, icon = ?, description = ?, audience = ?, is_published = ?' . ($moved ? ', sort_order = 9999' : '') . ' WHERE id = ?', array_merge($vals, [$id]));
            } else {
                $sort = (int) q_value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM lesson_folders WHERE parent_id <=> ?', [$parent]);
                q('INSERT INTO lesson_folders (parent_id, title, icon, description, audience, is_published, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)', array_merge($vals, [$sort]));
            }
            flash('ok', 'Aplankas išsaugotas.');
            redirect('admin/pamokos.php');
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
        redirect('admin/pamokos.php');
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
        redirect('admin/pamokos.php?edit=' . $new);
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
        flash('ok', 'Pamoka išsiųsta: ' . kiek(count($ids), 'nariui', 'nariams', 'narių') . '. Jie ją matys paskyroje („Trenerio pastabos“) ir galės atsakyti.');
        redirect('pamokos.php?id=' . $id);
    }

    if ($action === 'delete' && ($id = (int) post('id'))) {
        q('DELETE FROM lessons WHERE id = ?', [$id]);
        flash('ok', 'Pamoka ištrinta.');
        redirect('admin/pamokos.php');
    }

    // Pamokos išsaugojimas
    if ($action === 'lesson_save') {
        $id = (int) post('id');
        $title = trim(post('title'));
        $folderId = (int) post('folder_id') ?: null;
        $belt = belt_from_post('belt_level');
        $audience = isset(QUIZ_AUDIENCE[post('audience')]) ? post('audience') : 'karateka';
        $published = !empty($_POST['is_published']) ? 1 : 0;
        $groupIds = $audience === 'karateka' ? audience_from_post() : [];
        $blocks = $postedBlocks = lesson_blocks_from_post($errors);
        if ($title === '') {
            $errors[] = 'Įveskite pamokos pavadinimą.';
        }
        if ($folderId !== null && !isset($folders[$folderId])) {
            $folderId = null;
        }
        if (!$blocks && $published) {
            $errors[] = 'Pamokoje dar nieko nėra - pridėkite tekstą, video ar nuotrauką (arba išsaugokite nepaskelbtą).';
        }
        if (!$errors) {
            db()->beginTransaction();
            if ($id) {
                $old = q_one('SELECT folder_id FROM lessons WHERE id = ?', [$id]);
                $moved = $old && $old['folder_id'] != $folderId;
                q('UPDATE lessons SET title = ?, folder_id = ?, belt_level = ?, audience = ?, is_published = ?, updated_at = NOW()' . ($moved ? ', sort_order = 9999' : '') . ' WHERE id = ?',
                    [mb_substr($title, 0, 190), $folderId, $belt, $audience, $published, $id]);
                q('DELETE FROM lesson_groups WHERE lesson_id = ?', [$id]);
            } else {
                $sort = (int) q_value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM lessons WHERE folder_id <=> ?', [$folderId]);
                q('INSERT INTO lessons (folder_id, sort_order, title, belt_level, audience, is_published, author_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$folderId, $sort, mb_substr($title, 0, 190), $belt, $audience, $published, $me['id']]);
                $id = (int) db()->lastInsertId();
            }
            foreach ($groupIds as $gid) {
                q('INSERT IGNORE INTO lesson_groups (lesson_id, group_id) VALUES (?, ?)', [$id, $gid]);
            }
            save_lesson_blocks($id, $blocks);
            db()->commit();
            flash('ok', $published ? 'Pamoka išsaugota ir paskelbta.' : 'Pamoka išsaugota (nepaskelbta - nariai jos nemato).');
            redirect(post('after') === 'stay' ? 'admin/pamokos.php?edit=' . $id : 'admin/pamokos.php#l' . $id);
        }
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
        ? ['id' => 0, 'parent_id' => (int) get('parent') ?: null, 'title' => '', 'icon' => '📁', 'description' => '', 'audience' => 'karateka', 'is_published' => 1]
        : ($folders[(int) $folderEdit] ?? null);
    if (!$f) {
        not_found();
    }
    if ($errors) {
        $f = array_merge($f, ['parent_id' => (int) post('parent_id') ?: null, 'title' => post('title'), 'icon' => post('icon'), 'description' => post('description'),
            'audience' => post('audience'), 'is_published' => !empty($_POST['is_published'])]);
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
        <span class="icon-pick"><?php foreach (FOLDER_ICONS as $ic): ?><button type="button" data-icon="<?= $ic ?>"><?= $ic ?></button><?php endforeach; ?></span>
      </label>
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
    document.querySelectorAll('.icon-pick button').forEach(function (b) {
      b.addEventListener('click', function () { document.getElementById('folderIcon').value = b.dataset.icon; });
    });
    </script>
    <?php
    page_end();
    exit;
}

// ---------- Pamokos redaktorius ----------
if ($edit !== '') {
    $l = $edit === 'new'
        ? ['id' => 0, 'folder_id' => (int) get('aplankas') ?: null, 'title' => '', 'belt_level' => null, 'audience' => 'karateka', 'body' => '', 'videos' => '', 'is_published' => 1]
        : q_one('SELECT * FROM lessons WHERE id = ?', [(int) $edit]);
    if (!$l) {
        not_found();
    }
    $selectedGroups = $l['id'] ? array_map('intval', q('SELECT group_id FROM lesson_groups WHERE lesson_id = ?', [$l['id']])->fetchAll(PDO::FETCH_COLUMN)) : [];
    $blocks = lesson_blocks($l);
    if ($errors) {
        $l = array_merge($l, ['title' => post('title'), 'folder_id' => (int) post('folder_id') ?: null, 'belt_level' => belt_from_post('belt_level'),
            'audience' => post('audience'), 'is_published' => !empty($_POST['is_published'])]);
        $selectedGroups = array_map('intval', (array) ($_POST['groups'] ?? []));
        $blocks = $postedBlocks ?? $blocks;
    }
    if (!$blocks && !$l['id']) {
        $blocks = [['type' => 'text', 'body' => '']];
    }

    $blockHtml = function (array $b, string $key): string {
        [$label, $sign] = LESSON_BLOCK_TYPES[$b['type']];
        $n = 'blocks[' . $key . ']';
        $h = '<div class="lblock lb-' . $b['type'] . '" data-key="' . e($key) . '">'
            . '<div class="lblock-head"><span class="lb-drag" title="Tempkite">⠿</span><span class="lb-sign">' . $sign . '</span><strong>' . e($label) . '</strong>'
            . '<span class="lblock-tools"><button type="button" class="lb-up" title="Aukštyn">↑</button><button type="button" class="lb-down" title="Žemyn">↓</button>'
            . '<button type="button" class="lb-del" title="Pašalinti">✕</button></span></div>'
            . '<input type="hidden" name="' . $n . '[type]" value="' . $b['type'] . '">';
        $body = e((string) ($b['body'] ?? ''));
        switch ($b['type']) {
            case 'heading':
                $h .= '<input type="text" name="' . $n . '[body]" value="' . $body . '" placeholder="pvz. Pagrindiniai judesiai" maxlength="190">';
                break;
            case 'text':
            case 'note':
                $h .= '<div class="lb-toolbar"><button type="button" data-fmt="bold"><b>B</b> paryškinti</button><button type="button" data-fmt="list">• sąrašas</button><button type="button" data-fmt="num">1. sąrašas</button></div>'
                    . '<textarea name="' . $n . '[body]" rows="' . ($b['type'] === 'note' ? 3 : 6) . '" placeholder="' . ($b['type'] === 'note' ? 'pvz. Atkreipkite dėmesį į kvėpavimą' : 'Tekstas. Tuščia eilutė - nauja pastraipa.') . '">' . $body . '</textarea>';
                break;
            case 'video':
                $url = $b['raw_url'] ?? (!empty($b['youtube_id']) ? 'https://youtu.be/' . $b['youtube_id'] : '');
                $h .= '<div class="lb-media"><div class="lb-fields"><input type="text" class="lb-yt" name="' . $n . '[url]" value="' . e($url) . '" placeholder="YouTube nuoroda, pvz. https://youtu.be/...">'
                    . '<input type="text" name="' . $n . '[caption]" value="' . e((string) ($b['caption'] ?? '')) . '" placeholder="Prierašas po video (nebūtina)" maxlength="255"></div>'
                    . '<img class="lb-preview" alt="" ' . (!empty($b['youtube_id']) ? 'src="https://i.ytimg.com/vi/' . e($b['youtube_id']) . '/mqdefault.jpg"' : 'hidden') . '></div>';
                break;
            case 'image':
                $h .= '<div class="lb-media"><div class="lb-fields"><input type="file" name="block_image[' . e($key) . ']" accept="image/jpeg,image/png,image/webp">'
                    . '<input type="hidden" name="' . $n . '[image]" value="' . e((string) ($b['image'] ?? '')) . '">'
                    . '<input type="text" name="' . $n . '[caption]" value="' . e((string) ($b['caption'] ?? '')) . '" placeholder="Prierašas po nuotrauka (nebūtina)" maxlength="255"></div>'
                    . '<img class="lb-preview" alt="" ' . (!empty($b['image']) ? 'src="' . e(lesson_image_url($b['image'], true)) . '"' : 'hidden') . '></div>';
                break;
            case 'link':
                $h .= '<div class="form-row"><input type="text" name="' . $n . '[url]" value="' . e($b['raw_url'] ?? (string) ($b['url'] ?? '')) . '" placeholder="https://...">'
                    . '<input type="text" name="' . $n . '[body]" value="' . $body . '" placeholder="Nuorodos tekstas, pvz. WKF taisyklės (PDF)"></div>';
                break;
        }
        return $h . '</div>';
    };

    $allGroups = q_all('SELECT * FROM training_groups WHERE is_active = 1 ORDER BY sort_order, name');
    page_start($l['id'] ? 'Redaguoti pamoką' : 'Nauja pamoka', ['admin' => true, 'section' => 'pamokos']);
    ?>
    <a class="small" href="<?= url('admin/pamokos.php') ?>">← Pamokų tvarkymas</a>
    <?= form_errors($errors) ?>
    <form method="post" class="form lesson-editor" enctype="multipart/form-data" id="lessonForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="lesson_save">
      <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
      <input type="hidden" name="edit" value="<?= e($edit) ?>">
      <input type="text" class="lesson-title-input" name="title" value="<?= e($l['title']) ?>" maxlength="190" required placeholder="Pamokos pavadinimas, pvz. Heian Shodan">
      <div class="lesson-editor-grid">
        <div>
          <div id="blocks">
            <?php foreach ($blocks as $i => $b): ?><?= $blockHtml($b, 'b' . $i) ?><?php endforeach; ?>
          </div>
          <div class="lb-add">
            <span class="small muted">Pridėti:</span>
            <?php foreach (LESSON_BLOCK_TYPES as $t => [$label, $sign]): ?>
              <button type="button" class="btn btn-ghost btn-sm" data-add="<?= $t ?>"><?= $sign ?> <?= e($label) ?></button>
            <?php endforeach; ?>
          </div>
          <?php foreach (LESSON_BLOCK_TYPES as $t => $x): ?>
            <template id="tpl-<?= $t ?>"><?= $blockHtml(['type' => $t], '__K__') ?></template>
          <?php endforeach; ?>
        </div>
        <aside class="panel card lesson-settings">
          <label>Aplankas <select name="folder_id"><?= folder_options($folders, $l['folder_id'] !== null ? (int) $l['folder_id'] : null) ?></select></label>
          <label>Diržas <span class="hint">narys mato savo, žemesnių ir kito diržo pamokas</span>
            <select name="belt_level"><?= belt_options($l['belt_level'] !== null ? (int) $l['belt_level'] : null, 'Visiems diržams') ?></select></label>
          <label class="check"><input type="checkbox" name="is_published" value="1" <?= $l['is_published'] ? 'checked' : '' ?>><span>Paskelbta (nariai mato)</span></label>
          <div class="kicker" style="margin-top:6px;">Kam skirta?</div>
          <div class="seg-choice">
            <?php foreach (QUIZ_AUDIENCE as $k => $label): ?>
              <label class="check"><input type="radio" name="audience" value="<?= $k ?>" <?= ($l['audience'] ?? 'karateka') === $k ? 'checked' : '' ?>><span><?= e($label) ?></span></label>
            <?php endforeach; ?>
          </div>
          <div id="lessonGroups"><?= audience_field($selectedGroups) ?></div>
        </aside>
      </div>
      <div class="sticky-save lesson-save">
        <button class="btn btn-primary" type="submit">Išsaugoti</button>
        <button class="btn btn-ghost" type="submit" name="after" value="stay">Išsaugoti ir likti</button>
        <?php if ($l['id']): ?>
          <a class="btn btn-ghost" href="<?= url('pamokos.php?id=' . (int) $l['id']) ?>" target="_blank">👁 Peržiūrėti</a>
        <?php endif; ?>
      </div>
    </form>
    <?php if ($l['id']): ?>
      <div class="row lesson-extra">
        <a class="btn btn-ghost btn-sm" href="<?= url('admin/pamokos.php?siusti=' . (int) $l['id']) ?>">📨 Siųsti nariams</a>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $l['id'] ?>"><button class="btn btn-ghost btn-sm" name="action" value="lesson_copy">⧉ Kopijuoti pamoką</button></form>
        <form method="post" onsubmit="return confirm('Ištrinti pamoką visam laikui?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $l['id'] ?>"><button class="btn btn-danger btn-sm" name="action" value="delete">Ištrinti pamoką</button></form>
      </div>
    <?php endif; ?>
    <script src="<?= e(asset_url('assets/vendor/sortable.min.js')) ?>"></script>
    <script>
    (function () {
      var box = document.getElementById('blocks'), form = document.getElementById('lessonForm'), n = 0, dirty = false;
      function ytId(u) { var m = String(u).match(/(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/) || String(u).trim().match(/^([A-Za-z0-9_-]{11})$/); return m ? m[1] : null; }
      function wire(el) {
        el.querySelector('.lb-up').onclick = function () { if (el.previousElementSibling) { box.insertBefore(el, el.previousElementSibling); dirty = true; } };
        el.querySelector('.lb-down').onclick = function () { if (el.nextElementSibling) { box.insertBefore(el.nextElementSibling, el); dirty = true; } };
        el.querySelector('.lb-del').onclick = function () {
          var filled = Array.prototype.some.call(el.querySelectorAll('textarea, input[type=text], input[type=hidden][name$="[image]"]'), function (i) { return i.value.trim() !== ''; });
          if (!filled || confirm('Pašalinti šį bloką?')) { el.remove(); dirty = true; }
        };
        var yt = el.querySelector('.lb-yt');
        if (yt) yt.addEventListener('input', function () { var id = ytId(yt.value), img = el.querySelector('.lb-preview'); if (id) { img.src = 'https://i.ytimg.com/vi/' + id + '/mqdefault.jpg'; img.hidden = false; } else { img.hidden = true; } });
        var file = el.querySelector('input[type=file]');
        if (file) file.addEventListener('change', function () { var img = el.querySelector('.lb-preview'); if (file.files[0]) { img.src = URL.createObjectURL(file.files[0]); img.hidden = false; } });
        el.querySelectorAll('[data-fmt]').forEach(function (b) {
          b.onclick = function () {
            var ta = el.querySelector('textarea'), s = ta.selectionStart, e = ta.selectionEnd, v = ta.value, sel = v.slice(s, e), out;
            if (b.dataset.fmt === 'bold') { out = '**' + (sel || 'tekstas') + '**'; }
            else { var k = 0; out = (sel || 'punktas').split('\n').map(function (line) { k++; return (b.dataset.fmt === 'num' ? k + '. ' : '- ') + line.replace(/^\s*([-•*]|\d+[.)])\s+/, ''); }).join('\n'); }
            ta.value = v.slice(0, s) + out + v.slice(e); ta.focus(); ta.selectionStart = s; ta.selectionEnd = s + out.length; dirty = true;
          };
        });
      }
      box.querySelectorAll('.lblock').forEach(wire);
      document.querySelectorAll('[data-add]').forEach(function (b) {
        b.addEventListener('click', function () {
          var tpl = document.getElementById('tpl-' + b.dataset.add).innerHTML.replace(/__K__/g, 'n' + (++n) + Date.now());
          var wrap = document.createElement('div'); wrap.innerHTML = tpl; var el = wrap.firstElementChild;
          box.appendChild(el); wire(el); dirty = true;
          var f = el.querySelector('textarea, input[type=text], input[type=file]'); if (f) f.focus();
        });
      });
      if (window.Sortable) Sortable.create(box, { handle: '.lb-drag', animation: 150, forceFallback: true, onEnd: function () { dirty = true; } });
      form.addEventListener('input', function () { dirty = true; });
      form.addEventListener('submit', function () { dirty = false; });
      window.addEventListener('beforeunload', function (ev) { if (dirty) { ev.preventDefault(); ev.returnValue = ''; } });
      // Teisėjų / egzaminatorių pamokoms grupės netaikomos
      var groups = document.getElementById('lessonGroups');
      function sync() { var v = document.querySelector('input[name=audience]:checked'); groups.hidden = v && v.value !== 'karateka'; }
      document.querySelectorAll('input[name=audience]').forEach(function (r) { r.addEventListener('change', sync); }); sync();
    })();
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
            . '<span class="lt-icon">' . e($f['icon'] ?: '📁') . '</span>'
            . '<a class="lt-title" href="?folder=' . $fid . '">' . e($f['title']) . '</a>'
            . '<span class="lt-meta">' . ($counts[$fid] ?? 0) . ' pam.</span>'
            . ($f['audience'] !== 'karateka' ? '<span class="badge badge-tag">' . e(QUIZ_AUDIENCE[$f['audience']]) . '</span>' : '')
            . (!$f['is_published'] ? '<span class="badge badge-warn">nepaskelbtas</span>' : '')
            . '<span class="lt-actions"><a class="btn btn-ghost btn-sm" href="?edit=new&amp;aplankas=' . $fid . '">+ Pamoka</a>'
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
            . '<a class="lt-title" href="?edit=' . (int) $l['id'] . '">' . e($l['title']) . '</a>'
            . ($l['belt_level'] !== null ? belt_chip((int) $l['belt_level']) : '')
            . ($l['audience'] !== 'karateka' ? '<span class="badge badge-tag">' . e(QUIZ_AUDIENCE[$l['audience']]) . '</span>' : '')
            . (!$l['is_published'] ? '<span class="badge badge-warn">nepaskelbta</span>' : '')
            . ($meta ? '<span class="lt-meta">' . e(implode(' · ', $meta)) . '</span>' : '')
            . '<span class="lt-actions"><a class="btn btn-ghost btn-sm" href="?siusti=' . (int) $l['id'] . '" title="Siųsti nariams su komentaru">📨</a>'
            . '<a class="btn btn-ghost btn-sm" href="' . e(url('pamokos.php?id=' . (int) $l['id'])) . '" target="_blank" title="Peržiūrėti kaip narys">👁</a>'
            . '<a class="btn btn-ghost btn-sm" href="?edit=' . (int) $l['id'] . '">✎ Keisti</a>'
            . '<button type="button" class="lt-up" title="Aukštyn">↑</button><button type="button" class="lt-down" title="Žemyn">↓</button></span></div></li>';
    }
    return $html . '</ul>';
}

page_start('Pamokų tvarkymas', ['admin' => true, 'section' => 'pamokos']);
?>
<a class="small" href="<?= url('pamokos.php') ?>">← Pamokos (kaip mato nariai)</a>
<div class="page-head row between" style="align-items:flex-end; flex-wrap:wrap; gap:12px; margin-top:8px;">
  <div>
    <h1 class="styled">Pamokų tvarkymas</h1>
    <p class="muted" style="margin:0;">Aplankai, poaplankiai ir pamokos. Tempkite <strong>⠿</strong>, kad pakeistumėte tvarką ar perkeltumėte į kitą aplanką - išsaugoma iš karto.</p>
  </div>
  <div class="row" style="gap:8px;">
    <a class="btn btn-ghost" href="?folder=new">+ Aplankas</a>
    <a class="btn btn-primary" href="?edit=new">+ Nauja pamoka</a>
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
