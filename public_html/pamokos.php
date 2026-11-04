<?php
// Pamokos nariams (tik prisijungusiems ir patvirtintiems): skyriai (Egzaminams, Teisėjams...), pamokos iš blokų, paieška.
// Narys iš karto mato savo diržo pamokas. Treneris viską tvarko čia pat: „✎ Tvarkyti“ ir pamokos „✎ Keisti“.
require __DIR__ . '/app/bootstrap.php';

$a = require_login();
if (!can_see_lessons($a)) {
    page_start(t('Pamokos'), ['narrow' => true, 'noindex' => true]); ?>
    <div class="panel card">
      <h1><?= te('Pamokos') ?></h1>
      <p class="muted"><?= te('Pamokos bus matomos, kai treneris patvirtins jūsų paskyrą.') ?></p>
    </div>
<?php
    page_end();
    exit;
}
$isStaff = is_staff($a);
lessons_belt_folders_setup();
lessons_kata_seed();
lessons_kata_update();

// Kaip pamokas mato narys - viršuje „👁 Peržiūra“ (Vaikas, su diržu); senas pamokų peržiūros režimas išjungiamas
unset($_SESSION['lesson_preview']);

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
            flash('ok', !empty($_POST['is_published']) ? t('Pamoka išsaugota.') : t('Pamoka išsaugota (nepaskelbta - nariai jos nemato).'));
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

// Diržas - atidaryto aplanko (kyu aplankai pakeitė atskirą diržo pasirinkimą)
$cur = (int) get('aplankas') ?: null;
$belt = $cur !== null && isset($folders[$cur]) ? folder_belt($folders, $cur) : null;
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
        . ($f['audience'] !== 'karateka' ? ' <span class="badge badge-tag">' . e(t(QUIZ_AUDIENCE[$f['audience']])) . '</span>' : '')
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
        $meta = $i['videos'] ? lt_count($i['videos'], 'video', 'video', 'video') : ($i['images'] ? lt_count($i['images'], 'nuotrauka', 'nuotraukos', t('nuotraukų')) : t('Tekstinė pamoka'));
        if (!empty($opt['folder']) && $l['folder_id'] !== null && isset($folders[(int) $l['folder_id']])) {
            $meta .= ' · ' . $folders[(int) $l['folder_id']]['title'];
        }
        if (!empty($l['sent_at'])) {
            $meta = (t('Atsiųsta') . ' ') . fmt_date(substr($l['sent_at'], 0, 10));
        }
        $card = '<a class="panel lesson-card rise" href="' . e(url('pamokos.php?id=' . (int) $l['id'])) . '" style="--i:' . min($n, 8) . ';"><div class="thumb">'
            . ($i['thumb'] ? '<img src="' . e($i['thumb']) . '" alt="" loading="lazy">' . ($i['video'] ? '<span class="play">▶</span>' : '') : ('<span class="text-icon">' . t('Aa') . '</span>'))
            . '</div><div class="body">'
            . ($l['belt_level'] !== null ? belt_chip((int) $l['belt_level']) : '')
            . ($l['audience'] !== 'karateka' ? ' <span class="badge badge-tag">' . e(t(QUIZ_AUDIENCE[$l['audience']])) . '</span>' : '')
            . (!$l['is_published'] ? ' <span class="badge badge-warn">nepaskelbta</span>' : '')
            . (!empty($l['task_open']) ? (' <span class="badge badge-new">' . t('užduotis') . '</span>') : '')
            . '<h3>' . e($l['title']) . '</h3><div class="muted small">' . e($meta) . '</div></div></a>';
        $html .= $edit ? '<div class="lc-wrap" data-type="lesson" data-id="' . (int) $l['id'] . '">' . $card
            . '<span class="lc-tools"><span class="lc-drag" title="' . e(t('Tempkite')) . '">⠿</span><a href="' . e(url('pamokos.php?id=' . (int) $l['id'] . '&keisti=1')) . '" title="' . e(t('Keisti')) . '">✎</a></span></div>' : $card;
    }
    if ($edit && !$lessons) {
        $html .= ('<p class="ls-empty">' . t('Pamokų nėra - nutempkite čia arba pridėkite.') . '</p>');
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
        . ('<label>' . t('Kur') . ' <select name="parent_id">') . folder_options($folders, $f['parent_id'] !== null ? (int) $f['parent_id'] : null, $exclude) . '</select></label></div>'
        . '<label>Ženkliukas <input type="text" name="icon" id="fi' . $id . '" value="' . e((string) $f['icon']) . '" maxlength="8" style="max-width:110px;">' . icon_picker('fi' . $id, $f['icon']) . '</label>'
        . ('<label>' . t('Aprašymas') . ' <span class="hint">' . t('nebūtina; rodomas viršuje') . '</span><textarea name="description" rows="2">') . e((string) $f['description']) . '</textarea></label>'
        . ('<div class="form-row"><label>' . t('Diržas') . ' <span class="hint">' . t('narys mato tik savo diržo aplanką') . '</span><select name="belt_level">')
        . belt_options($f['belt_level'] !== null ? (int) $f['belt_level'] : null, t('Visiems diržams')) . ('</select></label><div><div class="kicker" style="margin-bottom:6px;">' . t('Kam skirta?') . '</div><div class="seg-choice">');
    foreach (array_map('t', QUIZ_AUDIENCE) as $k => $label) {
        $html .= '<label class="check"><input type="radio" name="audience" value="' . $k . '"' . ($f['audience'] === $k ? ' checked' : '') . '><span>' . e($label) . '</span></label>';
    }
    return $html . '</div></div></div>'
        . '<label class="check"><input type="checkbox" name="is_published" value="1"' . ($f['is_published'] ? ' checked' : '') . ('><span>' . t('Paskelbtas (nariai mato)') . '</span></label>')
        . ('<div class="row" style="gap:8px; flex-wrap:wrap;"><button class="btn btn-primary btn-sm" type="submit">' . t('Išsaugoti') . '</button>')
        . '<button class="btn btn-ghost btn-sm" type="button" data-close="ff' . $id . '">Atšaukti</button>'
        . ('<button class="btn btn-danger btn-sm" type="submit" name="action" value="folder_delete" formnovalidate onclick="return confirm(\'Ištrinti aplanką? Jo pamokos ir poaplankiai bus perkelti lygiu aukščiau.\')" style="margin-left:auto;">' . t('Ištrinti') . '</button></div></form>');
}

/** Naujas aplankas (tik pavadinimas) */
function folder_quick_form(?int $parent, string $back, string $placeholder): string
{
    return '<form method="post" action="' . e(url('admin/pamokos.php')) . '" class="row lt-quick" style="gap:6px;">' . csrf_field()
        . '<input type="hidden" name="action" value="folder_quick"><input type="hidden" name="parent_id" value="' . ($parent ?? '') . '"><input type="hidden" name="back" value="' . e($back) . '">'
        . '<input type="text" name="title" placeholder="' . e($placeholder) . ('" maxlength="120" required><button class="btn btn-ghost btn-sm" type="submit">' . t('+ Sukurti') . '</button></form>');
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
            . ($editMode ? '<span class="ls-drag" title="' . e(t('Tempkite')) . '">⠿</span>' : '')
            . '<a class="ls-sub-title" href="' . e(url('pamokos.php?aplankas=' . $sid)) . '"><span class="ls-icon">' . folder_icon($s) . '</span>' . e($s['title']) . '</a>'
            . '<span class="small muted">' . lt_count($counts[$sid] ?? 0, 'pamoka', 'pamokos', t('pamokų')) . '</span> ' . folder_badges($s)
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

/** Aplankų plytelės: kyu aplankai - diržo spalva, nario diržo aplankas pažymėtas */
function folder_tiles(array $list, array $counts, array $v): string
{
    if (!$list) {
        return '';
    }
    $html = '<div class="kyu-grid">';
    foreach ($list as $f) {
        $fid = (int) $f['id'];
        $b = $f['belt_level'] !== null && isset(BELTS[(int) $f['belt_level']]) ? (int) $f['belt_level'] : null;
        $own = $b !== null && isset($v['own'][$b]);
        $html .= '<a class="kyu-tile' . ($b ? ' has-belt' : '') . ($b === 1 ? ' belt-light' : '') . ($own ? ' is-own' : '') . '" href="' . e(url('pamokos.php?aplankas=' . $fid)) . '"'
            . ($b ? ' style="--belt:' . BELTS[$b][2] . ';"' : '') . '>'
            . '<span class="kyu-ico">' . folder_icon($f) . '</span>'
            . '<span class="kyu-text"><strong>' . e($f['title']) . '</strong>'
            . '<span class="kyu-sub">' . ($b && is_belt_folder($f) ? e(t(BELTS[$b][1])) . (' ' . t('diržas ·') . ' ') : '') . lt_count($counts[$fid] ?? 0, 'pamoka', 'pamokos', t('pamokų')) . '</span></span>'
            . ($own ? '<span class="kyu-own">' . e(count($v['members']) > 1 ? implode(', ', $v['own'][$b]) : t('Tavo diržas')) . '</span>' : '')
            . (!$f['is_published'] ? '<span class="badge badge-warn">nepaskelbtas</span>' : '')
            . '</a>';
    }
    return $html . '</div>';
}

/** Treneriui aplanke: pridėti tekstą, nuotrauką, video ar poaplankį */
function folder_add_bar(int $fid, string $back): string
{
    $link = function (string $type, string $label) use ($fid) {
        return '<a class="btn btn-ghost btn-sm" href="' . e(url('pamokos.php?nauja=1&aplankas=' . $fid . '&tipas=' . $type)) . '">' . $label . '</a>';
    };
    return ('<div class="ls-add-bar"><span class="small muted">' . t('Pridėti į šį aplanką:') . '</span>')
        . $link('text', t('¶ Tekstas')) . $link('image', t('🖼 Nuotrauka')) . $link('video', t('▶ Video'))
        . '<button type="button" class="btn btn-ghost btn-sm" data-open="nf' . $fid . '">📁 Aplankas</button></div>'
        . '<div id="nf' . $fid . '" class="ls-new-folder" hidden>' . folder_quick_form($fid, $back, t('Naujo aplanko pavadinimas, pvz. Kata')) . '</div>';
}

/** Viršutinė juosta: treneriui - įrankiai, peržiūroje - kaip kas mato */
$topBar = function (?int $cur) use ($v, $staffTools, $editMode) {
    if (!$staffTools) {
        return '';
    }
    $here = $cur ? 'pamokos.php?aplankas=' . $cur : 'pamokos.php';
    $html = ('<div class="staff-bar"><span class="staff-bar-label">' . t('Treneriui') . '</span>');
    if ($editMode) {
        $html .= '<a class="btn btn-sm btn-primary" href="' . e(url($here)) . '">✓ Baigti tvarkyti</a>'
            . ('<span class="small muted">' . t('Tempkite ⠿, kad perstatytumėte; ✎ - pavadinimas, ženkliukas, diržas, kam skirta. Išsaugoma iš karto.') . '</span>');
    } else {
        $html .= '<a class="btn btn-sm btn-primary" href="' . e(url($here . ($cur ? '&' : '?') . 'tvarkyti=1')) . '">✎ Tvarkyti</a>';
    }
    // Nauja pamoka - aplanke („Pridėti: Tekstas / Nuotrauka / Video“), tvarka - „✎ Tvarkyti“; kaip mato narys - viršuje 👁
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
        // Iš aplanko „Pridėti: Tekstas / Nuotrauka / Video“ - iš karto tas blokas
        $blocks = [['type' => in_array(get('tipas'), ['text', 'image', 'video'], true) ? get('tipas') : 'text', 'body' => '']];
    }
    $fid = $l['folder_id'] !== null ? (int) $l['folder_id'] : null;
    $cancel = $l['id'] ? 'pamokos.php?id=' . (int) $l['id'] : ($fid ? 'pamokos.php?aplankas=' . $fid : 'pamokos.php');
    page_start($l['id'] ? (t('Keisti:') . ' ') . $l['title'] : t('Nauja pamoka'), $navOpt);
    ?>
    <?= $crumbs($fid) ?>
    <?= form_errors($errors) ?>
    <form method="post" action="<?= url('pamokos.php') ?>" class="form lesson-inline" enctype="multipart/form-data" id="lessonForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="lesson_save">
      <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
      <details class="panel lesson-settings-bar" <?= $l['id'] ? '' : 'open' ?>>
        <summary><span class="kicker" style="margin:0;"><?= te('⚙ Nustatymai') ?></span>
          <span class="small muted"><?= e($fid && isset($folders[$fid]) ? folder_icon_text($folders[$fid]) . $folders[$fid]['title'] : t('Be aplanko')) ?> · <?php $fb = folder_belt($folders, $fid); ?><?= e($fb ? BELTS[$fb][0] . (' ' . t('(aplanko)')) : ($l['belt_level'] !== null ? BELTS[(int) $l['belt_level']][0] : t('visiems diržams'))) ?> · <?= $l['is_published'] ? 'paskelbta' : 'nepaskelbta' ?></span></summary>
        <div class="lesson-settings-grid">
          <div>
            <label><?= te('Aplankas') ?> <select name="folder_id"><?= folder_options($folders, $fid) ?></select></label>
            <label><?= te('Diržas') ?> <span class="hint"><?= te('narys iš karto mato savo diržo pamokas') ?></span>
              <select name="belt_level"><?= belt_options($l['belt_level'] !== null ? (int) $l['belt_level'] : null, t('Visiems diržams')) ?></select></label>
            <label class="check"><input type="checkbox" name="is_published" value="1" <?= $l['is_published'] ? 'checked' : '' ?>><span><?= te('Paskelbta (nariai mato)') ?></span></label>
          </div>
          <div>
            <div class="kicker"><?= te('Kam skirta?') ?></div>
            <div class="seg-choice">
              <?php foreach (array_map('t', QUIZ_AUDIENCE) as $k => $label): ?>
                <label class="check"><input type="radio" name="audience" value="<?= $k ?>" <?= ($l['audience'] ?? 'karateka') === $k ? 'checked' : '' ?>><span><?= e($label) ?></span></label>
              <?php endforeach; ?>
            </div>
            <div id="lessonGroups"><?= audience_field($selectedGroups) ?></div>
          </div>
        </div>
      </details>
      <article class="panel card lesson lesson-edit">
        <input type="text" class="lesson-title-input" name="title" value="<?= e($l['title']) ?>" maxlength="190" placeholder="<?= te('Pavadinimas, pvz. Heian Shodan (nebūtina - paims iš teksto ar prierašo)') ?>" aria-label="<?= te('Pavadinimas') ?>">
        <div id="blocks">
          <?php foreach ($blocks as $i => $b): ?><?= lesson_block_editor($b, 'b' . $i) ?><?php endforeach; ?>
        </div>
        <div class="lb-add">
          <span class="small muted"><?= te('Pridėti:') ?></span>
          <?php foreach (LESSON_BLOCK_TYPES as $t => [$label, $sign]): ?>
            <button type="button" class="btn btn-ghost btn-sm" data-add="<?= $t ?>"><?= $sign ?> <?= e($label) ?></button>
          <?php endforeach; ?>
        </div>
        <?php foreach (LESSON_BLOCK_TYPES as $t => $x): ?>
          <template id="tpl-<?= $t ?>"><?= lesson_block_editor(['type' => $t], '__K__') ?></template>
        <?php endforeach; ?>
      </article>
      <div class="sticky-save lesson-save">
        <button class="btn btn-primary" type="submit"><?= te('Išsaugoti') ?></button>
        <button class="btn btn-ghost" type="submit" name="after" value="stay"><?= te('Išsaugoti ir likti') ?></button>
        <a class="btn btn-ghost" href="<?= e(url($cancel)) ?>"><?= te('Atšaukti') ?></a>
      </div>
    </form>
    <?php if ($l['id']): ?>
      <div class="row lesson-extra">
        <a class="btn btn-ghost btn-sm" href="<?= url('admin/pamokos.php?siusti=' . (int) $l['id']) ?>"><?= te('📨 Siųsti nariams') ?></a>
        <form method="post" action="<?= url('admin/pamokos.php') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $l['id'] ?>"><button class="btn btn-ghost btn-sm" name="action" value="lesson_copy"><?= te('⧉ Kopijuoti pamoką') ?></button></form>
        <form method="post" action="<?= url('admin/pamokos.php') ?>" onsubmit="return confirm('Ištrinti pamoką visam laikui?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
          <input type="hidden" name="back" value="<?= e($fid ? 'pamokos.php?aplankas=' . $fid : 'pamokos.php') ?>"><button class="btn btn-danger btn-sm" name="action" value="delete"><?= te('Ištrinti pamoką') ?></button></form>
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
    // Treneris atsiuntė šią pamoką (pastaba su pamoka) - narys pažymi „Peržiūrėjau“ ir ji dingsta iš svarbių
    $sentNotes = $memberIds ? q_all('SELECT cn.*, m.first_name FROM coach_notes cn JOIN members m ON m.id = cn.member_id
                                      WHERE cn.lesson_id = ? AND cn.member_id IN (' . implode(',', $memberIds) . ') AND (cn.read_at IS NULL OR (cn.is_task = 1 AND cn.done_at IS NULL))', [$id]) : [];
    page_start($lesson['title'], $navOpt);
    ?>
    <?= $v['preview'] ? $topBar(null) : '' ?>
    <?= $crumbs($fid) ?>
    <?php foreach ($v['preview'] ? [] : $sentNotes as $sn): ?>
      <div class="sent-banner">
        <span>📨 <?= count($v['members']) > 1 ? e($sn['first_name']) . ': ' : '' ?><?= te('treneris atsiuntė šią pamoką') ?><?= $sn['body'] && $sn['body'] !== 'Pažiūrėk prisegtą pamoką.' ? ' - „' . e(mb_strimwidth($sn['body'], 0, 160, '…')) . '“' : '' ?><?= $sn['is_task'] ? (' <span class="badge badge-warn">' . t('užduotis') . '</span>') : '' ?></span>
        <form method="post" action="<?= url('paskyra.php') ?>" class="inline-form">
          <?= csrf_field() ?><input type="hidden" name="member_id" value="<?= (int) $sn['member_id'] ?>"><input type="hidden" name="note_id" value="<?= (int) $sn['id'] ?>">
          <input type="hidden" name="back" value="pamokos.php?id=<?= (int) $lesson['id'] ?>">
          <button class="btn btn-primary btn-sm" name="action" value="<?= $sn['is_task'] ? 'task_done' : 'note_read' ?>"><?= $sn['is_task'] ? t('✓ Atlikau') : t('✓ Peržiūrėjau') ?></button>
        </form>
      </div>
    <?php endforeach; ?>
    <article class="panel card lesson" style="margin-top:12px;">
      <div class="row" style="gap:8px; flex-wrap:wrap; align-items:center;">
        <?= $lesson['belt_level'] !== null ? belt_chip((int) $lesson['belt_level']) : '' ?>
        <?= $lesson['audience'] !== 'karateka' ? '<span class="badge badge-tag">' . e(t(QUIZ_AUDIENCE[$lesson['audience']])) . '</span>' : '' ?>
        <?= !$lesson['is_published'] ? '<span class="badge badge-warn">nepaskelbta</span>' : '' ?>
        <?php if ($staffTools): ?><a class="btn btn-primary btn-sm lesson-edit-btn" href="<?= url('pamokos.php?id=' . (int) $lesson['id'] . '&keisti=1') ?>"><?= te('✎ Keisti') ?></a><?php endif; ?>
      </div>
      <h1 style="margin-top:10px;"><?= e($lesson['title']) ?></h1>
      <div class="lesson-blocks">
        <?php foreach (lesson_blocks($lesson) as $b): ?><?= render_lesson_block($b) ?><?php endforeach; ?>
      </div>
    </article>
    <?php if ($staffTools): ?>
      <p class="no-print" style="margin-top:14px;"><a class="btn btn-primary btn-sm" href="<?= url('admin/pamokos.php?siusti=' . (int) $lesson['id']) ?>"><?= te('📨 Siųsti pamoką nariams') ?></a></p>
    <?php endif; ?>
    <?= $v['preview'] ? '' : questions_box('lesson', (int) $lesson['id'], 'pamokos.php?id=' . (int) $lesson['id']) ?>
    <?php if ($prev || $next): ?>
      <nav class="lesson-pager">
        <?= $prev ? '<a class="panel" href="' . e(url('pamokos.php?id=' . (int) $prev['id'])) . ('"><span class="small muted">' . t('← Ankstesnė') . '</span><strong>') . e($prev['title']) . '</strong></a>' : '<span></span>' ?>
        <?= $next ? '<a class="panel next" href="' . e(url('pamokos.php?id=' . (int) $next['id'])) . ('"><span class="small muted">' . t('Kita →') . '</span><strong>') . e($next['title']) . '</strong></a>' : '' ?>
      </nav>
    <?php endif; ?>
    <?php
    page_end();
    exit;
}

// ---------- Aplankas / pagrindinis / paieška ----------
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

page_start($folder ? $folder['title'] : t('Pamokos'), $navOpt);
?>
<?= $topBar($cur) ?>
<?php if ($folder): ?>
  <?= $crumbs($folder['parent_id'] !== null ? (int) $folder['parent_id'] : null) ?>
<?php endif; ?>
<div class="page-head row between" style="align-items:flex-end; flex-wrap:wrap; gap:12px;">
  <div>
    <?php if (!$folder): ?><div class="eyebrow"><?= te('Tik nariams') ?></div><?php endif; ?>
    <h1 class="styled"><?= $folder ? '<span class="ls-icon">' . folder_icon($folder) . '</span> ' . e($folder['title']) : t('Pamokos') ?></h1>
    <?php if ($folder): ?><div class="row" style="gap:6px; flex-wrap:wrap;"><?= folder_badges($folder) ?></div><?php endif; ?>
    <?php if ($folder && $folder['description']): ?><div class="muted" style="max-width:720px;"><?= lesson_text_html($folder['description']) ?></div><?php endif; ?>
  </div>
  <form class="lesson-search" method="get" action="<?= url('pamokos.php') ?>">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= te('Ieškoti pamokų…') ?>" aria-label="<?= te('Ieškoti pamokų') ?>">
  </form>
</div>
<?php if ($folder && $editMode): ?>
  <div class="row ls-edit-row"><button type="button" class="btn btn-ghost btn-sm" data-open="ff<?= $cur ?>"><?= te('✎ Keisti šį aplanką') ?></button>
    <?= folder_quick_form($cur, $back, t('Naujo poaplankio pavadinimas')) ?></div>
  <?= folder_inline_form($folders, $folder, $back) ?>
<?php endif; ?>

<?php if ($q !== ''): ?>
  <h2 style="margin:0 0 12px;"><?= te('Paieška: „') ?><?= e($q) ?>“ <span class="muted small"><?= lt_count(count($found), 'pamoka', 'pamokos', t('pamokų')) ?></span></h2>
  <?= $found ? lesson_cards($found, ['folder' => true]) : ('<div class="panel card"><p class="muted">' . t('Nieko nerasta.') . '</p></div>') ?>
  <p style="margin-top:16px;"><a href="<?= url('pamokos.php') ?>"><?= te('← Visos pamokos') ?></a></p>
<?php else: ?>
  <?php if (!$v['staff'] && !$cur): ?>
    <a class="panel exam-prep-link" href="<?= url('egzaminas.php') ?>">
      <span class="kicker" style="margin:0;"><?= te('Pasiruošimas egzaminui') ?></span>
      <strong><?= te('Kito diržo programa, testas ir egzamino žingsniai →') ?></strong>
    </a>
    <?php $sent = sent_lessons(array_map(function ($m) { return (int) $m['id']; }, $v['members'])); if ($sent): ?>
      <section class="sent-lessons">
        <h2 class="lessons-heading"><?= te('📨 Treneris atsiuntė') ?></h2>
        <?= lesson_cards($sent) ?>
      </section>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($folder && $editMode): ?>
    <?= $renderBody($cur, $back) ?>
  <?php elseif ($folder):
    $subs = array_values(array_filter(folder_children($folders, $cur), function ($f) use ($folderShown) { return $folderShown((int) $f['id']); }));
    $here = array_values(array_filter($shown, function ($l) use ($cur) { return $l['folder_id'] !== null && (int) $l['folder_id'] === $cur; })); ?>
    <?= $staffTools ? folder_add_bar($cur, 'pamokos.php?aplankas=' . $cur) : '' ?>
    <?= folder_tiles($subs, $counts, $v) ?>
    <?= $here ? lesson_cards($here) : '' ?>
    <?php if (!$subs && !$here): ?>
      <div class="panel card"><p class="muted"><?= $staffTools ? t('Aplankas tuščias - pridėkite tekstą, nuotrauką, video ar aplanką.') : t('Šiame aplanke dar nieko nėra.') ?></p></div>
    <?php endif; ?>
  <?php else:
    $sections = array_values(array_filter(folder_children($folders, null), function ($f) use ($folderShown) { return $folderShown((int) $f['id']); }));
    $rootLessons = array_values(array_filter($shown, function ($l) { return $l['folder_id'] === null; })); ?>
    <?php if (!$editMode): ?>
      <?= folder_tiles($sections, $counts, $v) ?>
      <?php if ($rootLessons): ?>
        <h2 class="lessons-heading"><?= te('Kitos pamokos') ?></h2>
        <?= lesson_cards($rootLessons) ?>
      <?php elseif (!$sections): ?>
        <div class="panel card"><p class="muted"><?= te('Pamokų dar nėra.') ?></p></div>
      <?php endif; ?>
    <?php else: ?>
    <div class="ls-sections" data-kind="folders" data-parent="">
      <?php foreach ($sections as $n => $f): $fid = (int) $f['id']; ?>
        <details class="panel ls-section" data-type="folder" data-id="<?= $fid ?>" id="f<?= $fid ?>" <?= $editMode || $n === 0 ? 'open' : '' ?>>
          <summary>
            <span class="ls-icon"><?= folder_icon($f) ?></span>
            <span class="ls-title"><?= e($f['title']) ?></span>
            <span class="ls-count"><?= lt_count($counts[$fid] ?? 0, 'pamoka', 'pamokos', t('pamokų')) ?></span>
            <span class="ls-badges"><?= folder_badges($f) ?></span>
          </summary>
          <div class="ls-body">
            <?php if ($editMode): ?>
              <div class="ls-edit-row">
                <span class="ls-drag" title="<?= te('Tempkite aplanką') ?>">⠿</span>
                <button type="button" class="btn btn-ghost btn-sm" data-open="ff<?= $fid ?>"><?= te('✎ Keisti aplanką') ?></button>
                <a class="btn btn-ghost btn-sm" href="<?= url('pamokos.php?nauja=1&aplankas=' . $fid) ?>"><?= te('+ Pamoka') ?></a>
                <?= folder_quick_form($fid, $back, t('Naujas poaplankis, pvz. 8 kyu')) ?>
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
      <div class="panel card ls-new"><span class="kicker" style="margin:0;"><?= te('Naujas aplankas') ?></span><?= folder_quick_form(null, $back, t('pvz. Teisėjams')) ?></div>
    <?php endif; ?>
    <h2 class="lessons-heading"><?= $sections ? t('Kitos pamokos') : t('Pamokos') ?></h2>
    <?= lesson_cards($rootLessons, ['edit' => true, 'parent' => null]) ?>
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
