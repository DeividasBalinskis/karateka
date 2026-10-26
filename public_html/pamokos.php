<?php
// Pamokos nariams (tik prisijungusiems ir patvirtintiems): skyriai (Egzaminams, Teisėjams...), pamokos iš blokų, paieška.
// Narys iš karto mato savo diržo pamokas. Treneris viską tvarko čia pat: „✎ Tvarkyti“ ir pamokos „✎ Keisti“.
require __DIR__ . '/app/bootstrap.php';

$a = require_login();
if (!can_see_lessons($a)) {
    page_start('Pamokos', ['narrow' => true, 'noindex' => true]); ?>
    <div class="panel card">
      <h1>Pamokos</h1>
      <p class="muted">Pamokos bus matomos, kai treneris patvirtins jūsų paskyrą.</p>
    </div>
<?php
    page_end();
    exit;
}
$isStaff = is_staff($a);

// „Žiūrėti kaip narys“ (treneriui): ?kaip=narys, ?kaip=0 - baigti
if ($isStaff && isset($_GET['kaip'])) {
    $_SESSION['lesson_preview'] = (int) get('kaip') ?: null;
    unset($_SESSION['lesson_belt']);
    redirect('pamokos.php');
}

// Pamokos išsaugojimas (redaguojama pamokos puslapyje)
$errors = [];
$postedBlocks = null;
$editingPost = false;
if (is_post()) {
    csrf_check();
    if (!$isStaff) {
        forbidden();
    }
    if (post('action') === 'lesson_save') {
        $savedId = lesson_save_from_post($a, $errors, $postedBlocks);
        if ($savedId) {
            flash('ok', !empty($_POST['is_published']) ? 'Pamoka išsaugota.' : 'Pamoka išsaugota (nepaskelbta - nariai jos nemato).');
            redirect('pamokos.php?id=' . $savedId . (post('after') === 'stay' ? '&keisti=1' : ''));
        }
        $editingPost = true;
    }
}

$v = lesson_viewer($a);
$staffTools = $isStaff && !$v['preview'];
$folders = lesson_folders();
$all = viewer_lessons($v);
$navOpt = ['noindex' => true, 'section' => 'pamokos'];

// Diržas: narys iš karto mato savo diržo pamokas (kitus galima pasirinkti), treneris - visas
if (isset($_GET['dirzas'])) {
    $_SESSION['lesson_belt'] = (int) get('dirzas');
}
$want = (int) ($_SESSION['lesson_belt'] ?? 0);
if ($v['staff']) {
    $belt = isset(BELTS[$want]) ? $want : null;
} else {
    $belt = in_array($want, $v['belts'], true) ? $want : (array_key_first($v['own']) ?? null);
}
$editMode = $staffTools && get('tvarkyti') === '1';
if ($editMode) {
    $belt = null;
}
$shown = lessons_for_belt($v, $all, $belt);
$counts = folder_lesson_counts($folders, $shown);
// Aplankas rodomas, jei jis matomas pasirinktam diržui ir jame (ar poaplankiuose) yra pamokų. Treneris mato ir tuščius.
$folderShown = function (int $fid) use ($v, $folders, $belt, $counts) {
    return viewer_folder_ok($v, $folders, $fid, $belt) && ($v['staff'] || ($counts[$fid] ?? 0) > 0);
};

/** 1 pamoka, 2 pamokos, 10 pamokų */
function lt_count(int $n, string $one, string $few, string $many): string
{
    $w = ($n % 10 === 1 && $n % 100 !== 11) ? $one : (($n % 10 >= 2 && ($n % 100 < 10 || $n % 100 >= 20)) ? $few : $many);
    return $n . ' ' . $w;
}

$crumbs = function (?int $fid) use ($folders) {
    $html = '<nav class="crumbs"><a href="' . e(url('pamokos.php')) . '">Pamokos</a>';
    foreach (folder_path($folders, $fid) as $f) {
        $html .= ' <span>›</span> <a href="' . e(url('pamokos.php?aplankas=' . (int) $f['id'])) . '">' . folder_icon($f) . ' ' . e($f['title']) . '</a>';
    }
    return $html . '</nav>';
};

function folder_badges(array $f): string
{
    // „8 kyu“ aplankui diržo ženkliuko nereikia - jis jau pavadinime ir ženkliuke
    $beltInTitle = $f['belt_level'] !== null && mb_stripos($f['title'], BELTS[(int) $f['belt_level']][0]) !== false;
    return ($f['belt_level'] !== null && !$beltInTitle ? belt_chip((int) $f['belt_level']) : '')
        . ($f['audience'] !== 'karateka' ? ' <span class="badge badge-tag">' . e(QUIZ_AUDIENCE[$f['audience']]) . '</span>' : '')
        . (!$f['is_published'] ? ' <span class="badge badge-warn">nepaskelbtas</span>' : '');
}

/** Pamokų kortelės. $opt: edit (tempimas), parent (konteineriui), folder (rodyti aplanką), sent (atsiųstos) */
function lesson_cards(array $lessons, array $opt = []): string
{
    $edit = !empty($opt['edit']);
    $folders = lesson_folders();
    $info = lesson_card_info($lessons);
    $html = '<div class="lessons-grid"' . (array_key_exists('parent', $opt) ? ' data-kind="lessons" data-parent="' . ($opt['parent'] ?? '') . '"' : '') . '>';
    foreach ($lessons as $n => $l) {
        $i = $info[(int) $l['id']];
        $meta = $i['videos'] ? lt_count($i['videos'], 'video', 'video', 'video') : ($i['images'] ? lt_count($i['images'], 'nuotrauka', 'nuotraukos', 'nuotraukų') : 'Tekstinė pamoka');
        if (!empty($opt['folder']) && $l['folder_id'] !== null && isset($folders[(int) $l['folder_id']])) {
            $meta .= ' · ' . $folders[(int) $l['folder_id']]['title'];
        }
        if (!empty($l['sent_at'])) {
            $meta = 'Atsiųsta ' . fmt_date(substr($l['sent_at'], 0, 10));
        }
        $card = '<a class="panel lesson-card rise" href="' . e(url('pamokos.php?id=' . (int) $l['id'])) . '" style="--i:' . min($n, 8) . ';"><div class="thumb">'
            . ($i['thumb'] ? '<img src="' . e($i['thumb']) . '" alt="" loading="lazy">' . ($i['video'] ? '<span class="play">▶</span>' : '') : '<span class="text-icon">Aa</span>')
            . '</div><div class="body">'
            . ($l['belt_level'] !== null ? belt_chip((int) $l['belt_level']) : '')
            . ($l['audience'] !== 'karateka' ? ' <span class="badge badge-tag">' . e(QUIZ_AUDIENCE[$l['audience']]) . '</span>' : '')
            . (!$l['is_published'] ? ' <span class="badge badge-warn">nepaskelbta</span>' : '')
            . (!empty($l['task_open']) ? ' <span class="badge badge-new">užduotis</span>' : '')
            . '<h3>' . e($l['title']) . '</h3><div class="muted small">' . e($meta) . '</div></div></a>';
        $html .= $edit ? '<div class="lc-wrap" data-type="lesson" data-id="' . (int) $l['id'] . '">' . $card
            . '<span class="lc-tools"><span class="lc-drag" title="Tempkite">⠿</span><a href="' . e(url('pamokos.php?id=' . (int) $l['id'] . '&keisti=1')) . '" title="Keisti">✎</a></span></div>' : $card;
    }
    if ($edit && !$lessons) {
        $html .= '<p class="ls-empty">Pamokų nėra - nutempkite čia arba pridėkite.</p>';
    }
    return $html . '</div>';
}

/** Aplanko nustatymai čia pat (treneriui tvarkant) */
function folder_inline_form(array $folders, array $f, string $back): string
{
    $id = (int) $f['id'];
    $exclude = folder_descendant_ids($folders, $id);
    $html = '<form method="post" action="' . e(url('admin/pamokos.php')) . '" class="form folder-inline panel" id="ff' . $id . '" hidden>' . csrf_field()
        . '<input type="hidden" name="action" value="folder_save"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="back" value="' . e($back) . '">'
        . '<div class="form-row"><label>Pavadinimas <input type="text" name="title" value="' . e($f['title']) . '" maxlength="120" required></label>'
        . '<label>Kur <select name="parent_id">' . folder_options($folders, $f['parent_id'] !== null ? (int) $f['parent_id'] : null, $exclude) . '</select></label></div>'
        . '<label>Ženkliukas <input type="text" name="icon" id="fi' . $id . '" value="' . e((string) $f['icon']) . '" maxlength="8" style="max-width:110px;">' . icon_picker('fi' . $id, $f['icon']) . '</label>'
        . '<label>Aprašymas <span class="hint">nebūtina; rodomas viršuje</span><textarea name="description" rows="2">' . e((string) $f['description']) . '</textarea></label>'
        . '<div class="form-row"><label>Diržas <span class="hint">narys mato tik savo diržo aplanką</span><select name="belt_level">'
        . belt_options($f['belt_level'] !== null ? (int) $f['belt_level'] : null, 'Visiems diržams') . '</select></label><div><div class="kicker" style="margin-bottom:6px;">Kam skirta?</div><div class="seg-choice">';
    foreach (QUIZ_AUDIENCE as $k => $label) {
        $html .= '<label class="check"><input type="radio" name="audience" value="' . $k . '"' . ($f['audience'] === $k ? ' checked' : '') . '><span>' . e($label) . '</span></label>';
    }
    return $html . '</div></div></div>'
        . '<label class="check"><input type="checkbox" name="is_published" value="1"' . ($f['is_published'] ? ' checked' : '') . '><span>Paskelbtas (nariai mato)</span></label>'
        . '<div class="row" style="gap:8px; flex-wrap:wrap;"><button class="btn btn-primary btn-sm" type="submit">Išsaugoti</button>'
        . '<button class="btn btn-ghost btn-sm" type="button" data-close="ff' . $id . '">Atšaukti</button>'
        . '<button class="btn btn-danger btn-sm" type="submit" name="action" value="folder_delete" formnovalidate onclick="return confirm(\'Ištrinti aplanką? Jo pamokos ir poaplankiai bus perkelti lygiu aukščiau.\')" style="margin-left:auto;">Ištrinti</button></div></form>';
}

/** Naujas aplankas (tik pavadinimas) */
function folder_quick_form(?int $parent, string $back, string $placeholder): string
{
    return '<form method="post" action="' . e(url('admin/pamokos.php')) . '" class="row lt-quick" style="gap:6px;">' . csrf_field()
        . '<input type="hidden" name="action" value="folder_quick"><input type="hidden" name="parent_id" value="' . ($parent ?? '') . '"><input type="hidden" name="back" value="' . e($back) . '">'
        . '<input type="text" name="title" placeholder="' . e($placeholder) . '" maxlength="120" required><button class="btn btn-ghost btn-sm" type="submit">+ Sukurti</button></form>';
}

/**
 * Aplanko turinys: jo pamokos, tada poaplankiai su savo pamokomis (giliau - nuorodos).
 * Naudojama ir skyriaus (dropdown) viduje, ir aplanko puslapyje.
 */
$renderBody = function (int $fid, string $back) use ($folders, $shown, $folderShown, $counts, $editMode) {
    $lessonsIn = function (int $id) use ($shown) {
        return array_values(array_filter($shown, function ($l) use ($id) { return $l['folder_id'] !== null && (int) $l['folder_id'] === $id; }));
    };
    $html = '';
    $here = $lessonsIn($fid);
    if ($here || $editMode) {
        $html .= lesson_cards($here, ['edit' => $editMode, 'parent' => $fid]);
    }
    $subs = array_values(array_filter(folder_children($folders, $fid), function ($f) use ($folderShown) { return $folderShown((int) $f['id']); }));
    $html .= '<div class="ls-subs" data-kind="folders" data-parent="' . $fid . '">';
    foreach ($subs as $s) {
        $sid = (int) $s['id'];
        $deeper = array_values(array_filter(folder_children($folders, $sid), function ($f) use ($folderShown) { return $folderShown((int) $f['id']); }));
        $html .= '<section class="ls-sub" data-type="folder" data-id="' . $sid . '" id="f' . $sid . '"><div class="ls-sub-head">'
            . ($editMode ? '<span class="ls-drag" title="Tempkite">⠿</span>' : '')
            . '<a class="ls-sub-title" href="' . e(url('pamokos.php?aplankas=' . $sid)) . '"><span class="ls-icon">' . folder_icon($s) . '</span>' . e($s['title']) . '</a>'
            . '<span class="small muted">' . lt_count($counts[$sid] ?? 0, 'pamoka', 'pamokos', 'pamokų') . '</span> ' . folder_badges($s)
            . ($editMode ? '<span class="ls-tools"><button type="button" class="btn btn-ghost btn-sm" data-open="ff' . $sid . '">✎ Keisti</button>'
                . '<a class="btn btn-ghost btn-sm" href="' . e(url('pamokos.php?nauja=1&aplankas=' . $sid)) . '">+ Pamoka</a></span>' : '')
            . '</div>' . ($editMode ? folder_inline_form($folders, $s, $back) : '')
            . ($s['description'] ? '<div class="small muted ls-desc">' . lesson_text_html($s['description']) . '</div>' : '');
        $inner = $lessonsIn($sid);
        if ($inner || $editMode) {
            $html .= lesson_cards($inner, ['edit' => $editMode, 'parent' => $sid]);
        }
        if ($deeper || $editMode) {
            $html .= '<div class="ls-chips" data-kind="folders" data-parent="' . $sid . '">';
            foreach ($deeper as $d) {
                $html .= '<a class="ls-chip" data-type="folder" data-id="' . (int) $d['id'] . '" href="' . e(url('pamokos.php?aplankas=' . (int) $d['id'])) . '">'
                    . ($editMode ? '<span class="ls-drag">⠿</span>' : '') . folder_icon($d) . ' ' . e($d['title']) . ' <span class="muted">· ' . (int) ($counts[(int) $d['id']] ?? 0) . '</span></a>';
            }
            $html .= '</div>';
        }
        $html .= '</section>';
    }
    return $html . '</div>';
};

/** „Žiūrėti kaip narys“: narių sąrašas pagal grupes */
function preview_select(?int $current): string
{
    $html = '<form method="get" action="' . e(url('pamokos.php')) . '" class="preview-pick"><select name="kaip" onchange="this.form.submit()" aria-label="Žiūrėti kaip narys">'
        . '<option value="">👁 Žiūrėti kaip narys…</option>';
    $cur = null;
    foreach (q_all('SELECT m.id, m.first_name, m.last_name, m.belt_level, g.name AS group_name FROM members m LEFT JOIN training_groups g ON g.id = m.group_id
                     WHERE m.status = "active" ORDER BY g.sort_order IS NULL, g.sort_order, g.name, m.last_name, m.first_name') as $m) {
        $gn = $m['group_name'] ?: 'Be grupės';
        if ($gn !== $cur) {
            $html .= ($cur !== null ? '</optgroup>' : '') . '<optgroup label="' . e($gn) . '">';
            $cur = $gn;
        }
        $html .= '<option value="' . (int) $m['id'] . '"' . ((int) $m['id'] === $current ? ' selected' : '') . '>' . e($m['first_name'] . ' ' . $m['last_name'])
            . ' · ' . e($m['belt_level'] !== null ? BELTS[(int) $m['belt_level']][0] : 'diržas nenurodytas') . '</option>';
    }
    return $html . ($cur !== null ? '</optgroup>' : '') . '</select><noscript><button class="btn btn-ghost btn-sm">Rodyti</button></noscript></form>';
}

/** Diržo pasirinkimas: narys - savo diržas (ir kiti galimi), treneris - visi arba vienas diržas */
$beltSwitch = function (?int $cur) use ($v, $belt, $all, $folders) {
    $used = [];
    foreach ($all as $l) {
        if ($l['belt_level'] !== null) {
            $used[(int) $l['belt_level']] = true;
        }
    }
    foreach ($folders as $f) {
        if ($f['belt_level'] !== null && viewer_folder_ok($v, $folders, (int) $f['id'])) {
            $used[(int) $f['belt_level']] = true;
        }
    }
    if (!$used) {
        return '';
    }
    $base = '?' . ($cur ? 'aplankas=' . $cur . '&amp;' : '') . 'dirzas=';
    $chip = function (int $lvl, string $label, bool $active, string $cls = '') use ($base) {
        return '<a class="bs-chip ' . $cls . ($active ? ' active' : '') . '" href="' . $base . $lvl . '"><span class="belt-swatch" style="background:' . BELTS[$lvl][2] . '"></span>' . e($label) . '</a>';
    };
    $html = '<div class="belt-switch">';
    if ($v['staff']) {
        ksort($used);
        $html .= '<span class="bs-label">Diržas:</span><a class="bs-chip' . ($belt === null ? ' active' : '') . '" href="' . $base . '0">Visi</a>';
        foreach (array_keys($used) as $lvl) {
            $html .= $chip($lvl, BELTS[$lvl][0], $belt === $lvl);
        }
        return $html . '</div>';
    }
    $multi = count($v['members']) > 1;
    $html .= '<span class="bs-label">' . ($multi ? 'Diržas:' : 'Tavo diržas:') . '</span>';
    foreach ($v['own'] as $lvl => $names) {
        $html .= $chip($lvl, BELTS[$lvl][0] . ' · ' . ($multi ? implode(', ', $names) : BELTS[$lvl][1]), $belt === $lvl);
    }
    $others = array_values(array_diff($v['belts'], array_keys($v['own'])));
    if ($others) {
        $html .= '<span class="bs-label bs-others">Kiti:</span>';
        foreach ($others as $lvl) {
            $html .= $chip($lvl, BELTS[$lvl][0], $belt === $lvl, 'small');
        }
    }
    return $html . '</div>';
};

/** Viršutinė juosta: treneriui - įrankiai, peržiūroje - kaip kas mato */
$topBar = function (?int $cur) use ($v, $staffTools, $editMode) {
    if ($v['preview']) {
        $m = $v['preview'];
        return '<div class="preview-bar"><span>👁 Žiūrite kaip narys: <strong>' . e($m['first_name'] . ' ' . $m['last_name']) . '</strong> · '
            . e($m['belt_level'] !== null ? BELTS[(int) $m['belt_level']][0] : 'diržas nenurodytas') . ($m['group_name'] ? ' · ' . e($m['group_name']) : '') . '</span>'
            . preview_select((int) $m['id']) . '<a class="btn btn-ghost btn-sm" href="' . e(url('pamokos.php?kaip=0')) . '">✕ Baigti peržiūrą</a></div>';
    }
    if (!$staffTools) {
        return '';
    }
    $here = $cur ? 'pamokos.php?aplankas=' . $cur : 'pamokos.php';
    $html = '<div class="staff-bar"><span class="staff-bar-label">Treneriui</span>';
    if ($editMode) {
        $html .= '<a class="btn btn-sm btn-primary" href="' . e(url($here)) . '">✓ Baigti tvarkyti</a>'
            . '<span class="small muted">Tempkite ⠿, kad perstatytumėte; ✎ - pavadinimas, ženkliukas, diržas, kam skirta. Išsaugoma iš karto.</span>';
    } else {
        $html .= '<a class="btn btn-sm btn-primary" href="' . e(url($here . ($cur ? '&' : '?') . 'tvarkyti=1')) . '">✎ Tvarkyti</a>';
    }
    $html .= '<a class="btn btn-sm btn-ghost" href="' . e(url('pamokos.php?nauja=1' . ($cur ? '&aplankas=' . $cur : ''))) . '">+ Nauja pamoka</a>'
        . '<a class="btn btn-sm btn-ghost" href="' . e(url('admin/pamokos.php' . ($cur ? '#f' . $cur : ''))) . '" title="Visas medis: aplankai ir pamokos">⇅ Medis</a>'
        . preview_select(null);
    return $html . '</div>';
};

// ---------- Pamokos redagavimas (tame pačiame puslapyje) ----------
$id = (int) get('id');
if ($staffTools && (($id && get('keisti') === '1') || get('nauja') === '1' || $editingPost)) {
    $editId = $editingPost ? (int) post('id') : $id;
    $l = $editId ? q_one('SELECT * FROM lessons WHERE id = ?', [$editId])
        : ['id' => 0, 'folder_id' => (int) get('aplankas') ?: null, 'title' => '', 'belt_level' => null, 'audience' => 'karateka', 'body' => '', 'videos' => '', 'is_published' => 1];
    if (!$l) {
        not_found();
    }
    if (!$l['id'] && $l['folder_id'] !== null) {
        $l['belt_level'] = folder_belt($folders, (int) $l['folder_id']);   // nauja pamoka diržo aplanke - iš karto to diržo
    }
    $selectedGroups = $l['id'] ? array_map('intval', q('SELECT group_id FROM lesson_groups WHERE lesson_id = ?', [$l['id']])->fetchAll(PDO::FETCH_COLUMN)) : [];
    $blocks = lesson_blocks($l);
    if ($editingPost) {
        $l = array_merge($l, ['title' => post('title'), 'folder_id' => (int) post('folder_id') ?: null, 'belt_level' => belt_from_post('belt_level'),
            'audience' => post('audience'), 'is_published' => !empty($_POST['is_published'])]);
        $selectedGroups = array_map('intval', (array) ($_POST['groups'] ?? []));
        $blocks = $postedBlocks ?? $blocks;
    }
    if (!$blocks && !$l['id']) {
        $blocks = [['type' => 'text', 'body' => '']];
    }
    $fid = $l['folder_id'] !== null ? (int) $l['folder_id'] : null;
    $cancel = $l['id'] ? 'pamokos.php?id=' . (int) $l['id'] : ($fid ? 'pamokos.php?aplankas=' . $fid : 'pamokos.php');
    page_start($l['id'] ? 'Keisti: ' . $l['title'] : 'Nauja pamoka', $navOpt);
    ?>
    <?= $crumbs($fid) ?>
    <?= form_errors($errors) ?>
    <form method="post" action="<?= url('pamokos.php') ?>" class="form lesson-inline" enctype="multipart/form-data" id="lessonForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="lesson_save">
      <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
      <details class="panel lesson-settings-bar" <?= $l['id'] ? '' : 'open' ?>>
        <summary><span class="kicker" style="margin:0;">⚙ Nustatymai</span>
          <span class="small muted"><?= e($fid && isset($folders[$fid]) ? folder_icon_text($folders[$fid]) . $folders[$fid]['title'] : 'Be aplanko') ?> · <?php $fb = folder_belt($folders, $fid); ?><?= e($fb ? BELTS[$fb][0] . ' (aplanko)' : ($l['belt_level'] !== null ? BELTS[(int) $l['belt_level']][0] : 'visiems diržams')) ?> · <?= $l['is_published'] ? 'paskelbta' : 'nepaskelbta' ?></span></summary>
        <div class="lesson-settings-grid">
          <div>
            <label>Aplankas <select name="folder_id"><?= folder_options($folders, $fid) ?></select></label>
            <label>Diržas <span class="hint">narys iš karto mato savo diržo pamokas</span>
              <select name="belt_level"><?= belt_options($l['belt_level'] !== null ? (int) $l['belt_level'] : null, 'Visiems diržams') ?></select></label>
            <label class="check"><input type="checkbox" name="is_published" value="1" <?= $l['is_published'] ? 'checked' : '' ?>><span>Paskelbta (nariai mato)</span></label>
          </div>
          <div>
            <div class="kicker">Kam skirta?</div>
            <div class="seg-choice">
              <?php foreach (QUIZ_AUDIENCE as $k => $label): ?>
                <label class="check"><input type="radio" name="audience" value="<?= $k ?>" <?= ($l['audience'] ?? 'karateka') === $k ? 'checked' : '' ?>><span><?= e($label) ?></span></label>
              <?php endforeach; ?>
            </div>
            <div id="lessonGroups"><?= audience_field($selectedGroups) ?></div>
          </div>
        </div>
      </details>
      <article class="panel card lesson lesson-edit">
        <input type="text" class="lesson-title-input" name="title" value="<?= e($l['title']) ?>" maxlength="190" required placeholder="Pamokos pavadinimas, pvz. Heian Shodan" aria-label="Pavadinimas">
        <div id="blocks">
          <?php foreach ($blocks as $i => $b): ?><?= lesson_block_editor($b, 'b' . $i) ?><?php endforeach; ?>
        </div>
        <div class="lb-add">
          <span class="small muted">Pridėti:</span>
          <?php foreach (LESSON_BLOCK_TYPES as $t => [$label, $sign]): ?>
            <button type="button" class="btn btn-ghost btn-sm" data-add="<?= $t ?>"><?= $sign ?> <?= e($label) ?></button>
          <?php endforeach; ?>
        </div>
        <?php foreach (LESSON_BLOCK_TYPES as $t => $x): ?>
          <template id="tpl-<?= $t ?>"><?= lesson_block_editor(['type' => $t], '__K__') ?></template>
        <?php endforeach; ?>
      </article>
      <div class="sticky-save lesson-save">
        <button class="btn btn-primary" type="submit">Išsaugoti</button>
        <button class="btn btn-ghost" type="submit" name="after" value="stay">Išsaugoti ir likti</button>
        <a class="btn btn-ghost" href="<?= e(url($cancel)) ?>">Atšaukti</a>
      </div>
    </form>
    <?php if ($l['id']): ?>
      <div class="row lesson-extra">
        <a class="btn btn-ghost btn-sm" href="<?= url('admin/pamokos.php?siusti=' . (int) $l['id']) ?>">📨 Siųsti nariams</a>
        <form method="post" action="<?= url('admin/pamokos.php') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $l['id'] ?>"><button class="btn btn-ghost btn-sm" name="action" value="lesson_copy">⧉ Kopijuoti pamoką</button></form>
        <form method="post" action="<?= url('admin/pamokos.php') ?>" onsubmit="return confirm('Ištrinti pamoką visam laikui?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
          <input type="hidden" name="back" value="<?= e($fid ? 'pamokos.php?aplankas=' . $fid : 'pamokos.php') ?>"><button class="btn btn-danger btn-sm" name="action" value="delete">Ištrinti pamoką</button></form>
      </div>
    <?php endif; ?>
    <script src="<?= e(asset_url('assets/vendor/sortable.min.js')) ?>"></script>
    <script src="<?= e(asset_url('assets/lesson-editor.js')) ?>"></script>
    <?php
    page_end();
    exit;
}

// ---------- Viena pamoka ----------
if ($id) {
    $lesson = null;
    foreach ($all as $l) {
        if ((int) $l['id'] === $id) {
            $lesson = $l;
        }
    }
    // Treneris galėjo atsiųsti pamoką (pastaba su pamoka) - tada ją matyti galima, net jei ji kito diržo
    $memberIds = array_map(function ($m) { return (int) $m['id']; }, $v['members']);
    if (!$lesson && $memberIds && q_value('SELECT 1 FROM coach_notes WHERE lesson_id = ? AND member_id IN (' . implode(',', $memberIds) . ')', [$id])) {
        $lesson = q_one('SELECT * FROM lessons WHERE id = ? AND is_published = 1', [$id]);
    }
    if (!$lesson) {
        not_found();
    }
    $fid = $lesson['folder_id'] !== null ? (int) $lesson['folder_id'] : null;
    // Ankstesnė / kita pamoka tame pačiame aplanke
    $siblings = array_values(array_filter($all, function ($l) use ($fid) { return ($l['folder_id'] !== null ? (int) $l['folder_id'] : null) === $fid; }));
    $prev = $next = null;
    foreach ($siblings as $k => $s) {
        if ((int) $s['id'] === $id) {
            $prev = $siblings[$k - 1] ?? null;
            $next = $siblings[$k + 1] ?? null;
        }
    }
    // Klausimai treneriui apie šią pamoką: narys mato tik savo, treneris - visų (peržiūroje - nerodoma)
    $myMembers = $isStaff ? [] : $v['members'];
    $lessonMsgs = [];
    if ($staffTools) {
        $lessonMsgs = q_all('SELECT mm.member_id, m.first_name, m.last_name, COUNT(*) AS cnt, SUM(mm.from_staff = 0 AND mm.read_at IS NULL) AS unread, MAX(mm.created_at) AS last_at
                               FROM member_messages mm JOIN members m ON m.id = mm.member_id WHERE mm.lesson_id = ? GROUP BY mm.member_id ORDER BY last_at DESC', [$id]);
    } elseif ($myMembers) {
        foreach ($myMembers as $m) {
            $rows = message_rows('mm.member_id = ? AND mm.lesson_id = ?', [(int) $m['id'], $id]);
            if ($rows) {
                $lessonMsgs[(int) $m['id']] = $rows;
            }
            mark_staff_messages_read((int) $m['id'], $id);
        }
    }
    page_start($lesson['title'], $navOpt);
    ?>
    <?= $v['preview'] ? $topBar(null) : '' ?>
    <?= $crumbs($fid) ?>
    <article class="panel card lesson" style="margin-top:12px;">
      <div class="row" style="gap:8px; flex-wrap:wrap; align-items:center;">
        <?= $lesson['belt_level'] !== null ? belt_chip((int) $lesson['belt_level']) : '' ?>
        <?= $lesson['audience'] !== 'karateka' ? '<span class="badge badge-tag">' . e(QUIZ_AUDIENCE[$lesson['audience']]) . '</span>' : '' ?>
        <?= !$lesson['is_published'] ? '<span class="badge badge-warn">nepaskelbta</span>' : '' ?>
        <?php if ($staffTools): ?><a class="btn btn-primary btn-sm lesson-edit-btn" href="<?= url('pamokos.php?id=' . (int) $lesson['id'] . '&keisti=1') ?>">✎ Keisti</a><?php endif; ?>
      </div>
      <h1 style="margin-top:10px;"><?= e($lesson['title']) ?></h1>
      <div class="lesson-blocks">
        <?php foreach (lesson_blocks($lesson) as $b): ?><?= render_lesson_block($b) ?><?php endforeach; ?>
      </div>
    </article>
    <?php if ($staffTools): ?>
      <div class="panel card lesson-questions no-print" id="klausimas">
        <div class="row between" style="flex-wrap:wrap; gap:8px;">
          <div class="kicker" style="margin:0;">Narių klausimai apie šią pamoką</div>
          <a class="btn btn-primary btn-sm" href="<?= url('admin/pamokos.php?siusti=' . (int) $lesson['id']) ?>">📨 Siųsti pamoką nariams</a>
        </div>
        <?php if (!$lessonMsgs): ?><p class="small muted" style="margin-top:8px;">Klausimų dar nėra.</p><?php endif; ?>
        <ul class="list">
          <?php foreach ($lessonMsgs as $r): ?>
            <li class="row between"><span><strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong> <span class="small muted"><?= (int) $r['cnt'] ?> žin.</span><?= $r['unread'] ? ' <span class="badge badge-new">' . (int) $r['unread'] . ' nauj.</span>' : '' ?></span>
              <a class="btn btn-ghost btn-sm" href="<?= url('admin/zinutes.php?m=' . (int) $r['member_id']) ?>">Atsakyti →</a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php elseif ($myMembers): ?>
      <div class="panel card lesson-questions" id="klausimas">
        <div class="kicker">💬 Klausimas treneriui</div>
        <p class="small muted" style="margin-top:-4px;">Neaišku ar norite pasidalinti? Parašykite - mato tik treneriai, atsakymą matysite čia ir paskyroje.</p>
        <?php foreach ($lessonMsgs as $mid => $rows): ?>
          <?php if (count($myMembers) > 1): ?><div class="msg-topic-title"><?= e(array_column($myMembers, 'first_name', 'id')[$mid] ?? '') ?></div><?php endif; ?>
          <div class="msg-thread"><?php foreach ($rows as $r): ?><?= render_message($r, false) ?><?php endforeach; ?></div>
        <?php endforeach; ?>
        <form method="post" action="<?= url('zinute.php') ?>" class="msg-form">
          <?= csrf_field() ?><input type="hidden" name="lesson_id" value="<?= (int) $lesson['id'] ?>"><input type="hidden" name="back" value="pamoka">
          <?php if (count($myMembers) > 1): ?>
            <select name="member_id" aria-label="Kieno vardu"><?php foreach ($myMembers as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['first_name']) ?></option><?php endforeach; ?></select>
          <?php else: ?>
            <input type="hidden" name="member_id" value="<?= (int) $myMembers[0]['id'] ?>">
          <?php endif; ?>
          <textarea name="body" rows="2" maxlength="<?= MESSAGE_MAX ?>" placeholder="Jūsų klausimas ar komentaras" required></textarea>
          <button class="btn btn-primary btn-sm" type="submit">Siųsti treneriui</button>
        </form>
      </div>
    <?php endif; ?>
    <?php if ($prev || $next): ?>
      <nav class="lesson-pager">
        <?= $prev ? '<a class="panel" href="' . e(url('pamokos.php?id=' . (int) $prev['id'])) . '"><span class="small muted">← Ankstesnė</span><strong>' . e($prev['title']) . '</strong></a>' : '<span></span>' ?>
        <?= $next ? '<a class="panel next" href="' . e(url('pamokos.php?id=' . (int) $next['id'])) . '"><span class="small muted">Kita →</span><strong>' . e($next['title']) . '</strong></a>' : '' ?>
      </nav>
    <?php endif; ?>
    <?php
    page_end();
    exit;
}

// ---------- Aplankas / pagrindinis / paieška ----------
$cur = (int) get('aplankas') ?: null;
if ($cur !== null && (!isset($folders[$cur]) || !viewer_folder_ok($v, $folders, $cur))) {
    not_found();
}
$q = trim((string) get('q'));
$found = [];
if ($q !== '') {
    $found = array_values(array_filter($all, function ($l) use ($q) {
        return mb_stripos($l['title'], $q) !== false || mb_stripos((string) $l['body'], $q) !== false;
    }));
}
$folder = $cur ? $folders[$cur] : null;
$back = 'pamokos.php' . ($cur ? '?aplankas=' . $cur . '&tvarkyti=1' : '?tvarkyti=1');

page_start($folder ? $folder['title'] : 'Pamokos', $navOpt);
?>
<?= $topBar($cur) ?>
<?php if ($folder): ?>
  <?= $crumbs($folder['parent_id'] !== null ? (int) $folder['parent_id'] : null) ?>
<?php endif; ?>
<div class="page-head row between" style="align-items:flex-end; flex-wrap:wrap; gap:12px;">
  <div>
    <?php if (!$folder): ?><div class="eyebrow">Tik nariams</div><?php endif; ?>
    <h1 class="styled"><?= $folder ? '<span class="ls-icon">' . folder_icon($folder) . '</span> ' . e($folder['title']) : 'Pamokos' ?></h1>
    <?php if ($folder): ?><div class="row" style="gap:6px; flex-wrap:wrap;"><?= folder_badges($folder) ?></div><?php endif; ?>
    <?php if ($folder && $folder['description']): ?><div class="muted" style="max-width:720px;"><?= lesson_text_html($folder['description']) ?></div><?php endif; ?>
  </div>
  <form class="lesson-search" method="get" action="<?= url('pamokos.php') ?>">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Ieškoti pamokų…" aria-label="Ieškoti pamokų">
  </form>
</div>
<?php if ($folder && $editMode): ?>
  <div class="row ls-edit-row"><button type="button" class="btn btn-ghost btn-sm" data-open="ff<?= $cur ?>">✎ Keisti šį aplanką</button>
    <?= folder_quick_form($cur, $back, 'Naujo poaplankio pavadinimas') ?></div>
  <?= folder_inline_form($folders, $folder, $back) ?>
<?php endif; ?>

<?php if ($q !== ''): ?>
  <h2 style="margin:0 0 12px;">Paieška: „<?= e($q) ?>“ <span class="muted small"><?= lt_count(count($found), 'pamoka', 'pamokos', 'pamokų') ?></span></h2>
  <?= $found ? lesson_cards($found, ['folder' => true]) : '<div class="panel card"><p class="muted">Nieko nerasta.</p></div>' ?>
  <p style="margin-top:16px;"><a href="<?= url('pamokos.php') ?>">← Visos pamokos</a></p>
<?php else: ?>
  <?= $editMode ? '' : $beltSwitch($cur) ?>
  <?php if (!$v['staff'] && !$cur): ?>
    <a class="panel exam-prep-link" href="<?= url('egzaminas.php') ?>">
      <span class="kicker" style="margin:0;">Pasiruošimas egzaminui</span>
      <strong>Kito diržo programa, testas ir egzamino žingsniai →</strong>
    </a>
    <?php $sent = sent_lessons(array_map(function ($m) { return (int) $m['id']; }, $v['members'])); if ($sent): ?>
      <section class="sent-lessons">
        <h2 class="lessons-heading">📨 Treneris atsiuntė</h2>
        <?= lesson_cards($sent) ?>
      </section>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($folder): ?>
    <?= $renderBody($cur, $back) ?>
    <?php if (!$v['staff'] && !($counts[$cur] ?? 0)): ?><div class="panel card"><p class="muted">Šiame aplanke tavo diržui pamokų nėra.</p></div><?php endif; ?>
  <?php else:
    $sections = array_values(array_filter(folder_children($folders, null), function ($f) use ($folderShown) { return $folderShown((int) $f['id']); }));
    $rootLessons = array_values(array_filter($shown, function ($l) { return $l['folder_id'] === null; })); ?>
    <div class="ls-sections" data-kind="folders" data-parent="">
      <?php foreach ($sections as $n => $f): $fid = (int) $f['id']; ?>
        <details class="panel ls-section" data-type="folder" data-id="<?= $fid ?>" id="f<?= $fid ?>" <?= $editMode || $n === 0 ? 'open' : '' ?>>
          <summary>
            <span class="ls-icon"><?= folder_icon($f) ?></span>
            <span class="ls-title"><?= e($f['title']) ?></span>
            <span class="ls-count"><?= lt_count($counts[$fid] ?? 0, 'pamoka', 'pamokos', 'pamokų') ?></span>
            <span class="ls-badges"><?= folder_badges($f) ?></span>
          </summary>
          <div class="ls-body">
            <?php if ($editMode): ?>
              <div class="ls-edit-row">
                <span class="ls-drag" title="Tempkite skyrių">⠿</span>
                <button type="button" class="btn btn-ghost btn-sm" data-open="ff<?= $fid ?>">✎ Keisti skyrių</button>
                <a class="btn btn-ghost btn-sm" href="<?= url('pamokos.php?nauja=1&aplankas=' . $fid) ?>">+ Pamoka</a>
                <?= folder_quick_form($fid, $back, 'Naujas poaplankis, pvz. 8 kyu') ?>
              </div>
              <?= folder_inline_form($folders, $f, $back) ?>
            <?php endif; ?>
            <?php if ($f['description']): ?><div class="muted ls-desc"><?= lesson_text_html($f['description']) ?></div><?php endif; ?>
            <?= $renderBody($fid, $back) ?>
          </div>
        </details>
      <?php endforeach; ?>
    </div>
    <?php if ($editMode): ?>
      <div class="panel card ls-new"><span class="kicker" style="margin:0;">Naujas skyrius</span><?= folder_quick_form(null, $back, 'pvz. Egzaminams, Teisėjams, Fizinis pasiruošimas') ?></div>
    <?php endif; ?>
    <?php if ($rootLessons || $editMode): ?>
      <?php if ($sections || $editMode): ?><h2 class="lessons-heading"><?= $sections ? 'Kitos pamokos' : 'Pamokos' ?></h2><?php endif; ?>
      <?= lesson_cards($rootLessons, ['edit' => $editMode, 'parent' => null]) ?>
    <?php elseif (!$sections): ?>
      <div class="panel card"><p class="muted">Pamokų dar nėra.</p></div>
    <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>

<?php if ($editMode): ?>
  <div class="toast" id="ltToast" hidden></div>
  <script src="<?= e(asset_url('assets/vendor/sortable.min.js')) ?>"></script>
  <script>
  (function () {
    var csrf = <?= json_encode(csrf_token()) ?>, endpoint = <?= json_encode(url('admin/pamokos.php')) ?>, toast = document.getElementById('ltToast'), timer;
    function say(text, bad) { toast.textContent = text; toast.className = 'toast' + (bad ? ' bad' : ''); toast.hidden = false; clearTimeout(timer); timer = setTimeout(function () { toast.hidden = true; }, 2200); }
    function collect() {
      var items = [];
      document.querySelectorAll('[data-kind]').forEach(function (c) {
        var parent = c.dataset.parent === '' ? null : +c.dataset.parent, k = c.dataset.kind === 'folders' ? 'f' : 'l', i = 0;
        Array.prototype.forEach.call(c.children, function (el) { if (el.dataset.type) items.push([k, +el.dataset.id, parent, ++i]); });
      });
      return items;
    }
    function save(reload) {
      var fd = new FormData(); fd.append('_csrf', csrf); fd.append('action', 'order_items'); fd.append('items', JSON.stringify(collect()));
      fetch(endpoint, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); })
        .then(function (d) { if (d.ok) { say('✓ Išsaugota'); if (reload) setTimeout(function () { location.reload(); }, 500); } else { say(d.error || 'Nepavyko išsaugoti', true); setTimeout(function () { location.reload(); }, 1200); } })
        .catch(function () { say('Nepavyko išsaugoti - atnaujinkite puslapį', true); });
    }
    var opts = { animation: 150, forceFallback: true, fallbackOnBody: true, swapThreshold: 0.6, emptyInsertThreshold: 24, delayOnTouchOnly: true, delay: 120,
      onEnd: function (ev) { save(ev.from !== ev.to); } };
    document.querySelectorAll('[data-kind="folders"]').forEach(function (c) { Sortable.create(c, Object.assign({ group: 'lfolders', handle: '.ls-drag', draggable: '[data-type="folder"]' }, opts)); });
    document.querySelectorAll('[data-kind="lessons"]').forEach(function (c) { Sortable.create(c, Object.assign({ group: 'llessons', handle: '.lc-drag', draggable: '[data-type="lesson"]' }, opts)); });
    document.querySelectorAll('.ls-chip').forEach(function (a) { a.addEventListener('click', function (ev) { if (ev.target.closest('.ls-drag')) ev.preventDefault(); }); });
  })();
  </script>
<?php endif; ?>
<?php if ($staffTools): ?>
  <script>
  // Aplanko nustatymai čia pat: ✎ atidaro formą, ženkliuko mygtukai įrašo reikšmę
  document.querySelectorAll('[data-open]').forEach(function (b) { b.addEventListener('click', function () { var f = document.getElementById(b.dataset.open); f.hidden = !f.hidden; if (!f.hidden) f.querySelector('input[name=title]').focus(); }); });
  document.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', function () { document.getElementById(b.dataset.close).hidden = true; }); });
  document.querySelectorAll('.icon-pick').forEach(function (p) { p.addEventListener('click', function (ev) { var b = ev.target.closest('button'); if (b) { document.getElementById(p.dataset.for).value = b.dataset.icon; p.querySelector('.icon-now').innerHTML = b.innerHTML; } }); });
  </script>
<?php endif; ?>
<?php if (!$editMode): ?>
  <script>
  // Kurie skyriai atidaryti - prisimenama šioje naršyklėje
  (function () {
    var KEY = 'karateka-lesson-sections', st = {};
    try { st = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) {}
    document.querySelectorAll('details.ls-section').forEach(function (d) {
      if (st[d.dataset.id] !== undefined) d.open = st[d.dataset.id];
      d.addEventListener('toggle', function () { st[d.dataset.id] = d.open; try { localStorage.setItem(KEY, JSON.stringify(st)); } catch (e) {} });
    });
  })();
  </script>
<?php endif; ?>
<?php
page_end();
