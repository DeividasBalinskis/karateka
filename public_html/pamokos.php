<?php
// Pamokos nariams (tik prisijungusiems ir patvirtintiems): aplankai, pamokos iš blokų, paieška
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

$isStaff = is_staff();
$folders = lesson_folders();
$all = visible_lessons($a);
$tags = account_tag_list($a);
$counts = folder_lesson_counts($folders, $all);
$navOpt = ['noindex' => true, 'section' => 'pamokos'];

/** 1 pamoka, 2 pamokos, 10 pamokų */
function lt_count(int $n, string $one, string $few, string $many): string
{
    $w = ($n % 10 === 1 && $n % 100 !== 11) ? $one : (($n % 10 >= 2 && ($n % 100 < 10 || $n % 100 >= 20)) ? $few : $many);
    return $n . ' ' . $w;
}

// Aplankas rodomas, jei jis skirtas žmogui ir jame (ar poaplankiuose) yra matomų pamokų. Treneris mato viską.
$folderVisible = function (int $fid) use ($isStaff, $folders, $tags, $counts) {
    return $isStaff || (folder_allowed($folders, $fid, $tags) && ($counts[$fid] ?? 0) > 0);
};
$crumbs = function (?int $fid) use ($folders) {
    $html = '<nav class="crumbs"><a href="' . e(url('pamokos.php')) . '">Pamokos</a>';
    foreach (folder_path($folders, $fid) as $f) {
        $html .= ' <span>›</span> <a href="' . e(url('pamokos.php?aplankas=' . (int) $f['id'])) . '">' . e(($f['icon'] ? $f['icon'] . ' ' : '') . $f['title']) . '</a>';
    }
    return $html . '</nav>';
};

/** Pamokų kortelės */
function lesson_cards(array $lessons, bool $showFolder = false, array $folders = []): string
{
    if (!$lessons) {
        return '';
    }
    $info = lesson_card_info($lessons);
    $html = '<div class="lessons-grid">';
    foreach ($lessons as $n => $l) {
        $i = $info[(int) $l['id']];
        $meta = $i['videos'] ? lt_count($i['videos'], 'video', 'video', 'video') : ($i['images'] ? lt_count($i['images'], 'nuotrauka', 'nuotraukos', 'nuotraukų') : 'Tekstinė pamoka');
        $html .= '<a class="panel lesson-card rise" href="' . e(url('pamokos.php?id=' . (int) $l['id'])) . '" style="--i:' . min($n, 8) . ';"><div class="thumb">'
            . ($i['thumb'] ? '<img src="' . e($i['thumb']) . '" alt="" loading="lazy">' . ($i['video'] ? '<span class="play">▶</span>' : '') : '<span class="text-icon">Aa</span>')
            . '</div><div class="body">'
            . ($l['belt_level'] !== null ? belt_chip((int) $l['belt_level']) : '')
            . (!$l['is_published'] ? ' <span class="badge badge-warn">nepaskelbta</span>' : '')
            . '<h3>' . e($l['title']) . '</h3><div class="muted small">' . e($meta)
            . ($showFolder && $l['folder_id'] !== null && isset($folders[(int) $l['folder_id']]) ? ' · ' . e($folders[(int) $l['folder_id']]['title']) : '')
            . '</div></div></a>';
    }
    return $html . '</div>';
}

// ---------- Viena pamoka ----------
$id = (int) get('id');
if ($id) {
    $lesson = null;
    foreach ($all as $l) {
        if ((int) $l['id'] === $id) {
            $lesson = $l;
        }
    }
    // Treneris galėjo prisegti pamoką prie pastabos - tada ją matyti galima, net jei ji kito diržo
    if (!$lesson && q_value('SELECT 1 FROM coach_notes cn JOIN account_members am ON am.member_id = cn.member_id WHERE cn.lesson_id = ? AND am.account_id = ?', [$id, $a['id']])) {
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
    // Klausimai treneriui apie šią pamoką: narys mato tik savo, treneris - visų
    $myMembers = $isStaff ? [] : array_values(array_filter(account_members((int) $a['id']), function ($m) { return $m['status'] === 'active'; }));
    $lessonMsgs = [];
    if ($isStaff) {
        $lessonMsgs = q_all('SELECT mm.member_id, m.first_name, m.last_name, COUNT(*) AS cnt, SUM(mm.from_staff = 0 AND mm.read_at IS NULL) AS unread, MAX(mm.created_at) AS last_at
                               FROM member_messages mm JOIN members m ON m.id = mm.member_id WHERE mm.lesson_id = ? GROUP BY mm.member_id ORDER BY last_at DESC', [$id]);
    } elseif ($myMembers && is_active_account($a)) {
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
    <?= $crumbs($fid) ?>
    <article class="panel card lesson" style="margin-top:12px;">
      <div class="row" style="gap:8px; flex-wrap:wrap; align-items:center;">
        <?= $lesson['belt_level'] !== null ? belt_chip((int) $lesson['belt_level']) : '' ?>
        <?= $lesson['audience'] !== 'karateka' ? '<span class="badge badge-tag">' . e(QUIZ_AUDIENCE[$lesson['audience']]) . '</span>' : '' ?>
        <?= !$lesson['is_published'] ? '<span class="badge badge-warn">nepaskelbta</span>' : '' ?>
        <?= staff_link('admin/pamokos.php?edit=' . (int) $lesson['id']) ?>
      </div>
      <h1 style="margin-top:10px;"><?= e($lesson['title']) ?></h1>
      <div class="lesson-blocks">
        <?php foreach (lesson_blocks($lesson) as $b): ?><?= render_lesson_block($b) ?><?php endforeach; ?>
      </div>
    </article>
    <?php if ($isStaff): ?>
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
    <?php elseif ($myMembers && is_active_account($a)): ?>
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
if ($cur !== null && (!isset($folders[$cur]) || !$folderVisible($cur))) {
    not_found();
}
$q = trim((string) get('q'));
$subfolders = array_filter($folders, function ($f) use ($cur, $folderVisible) {
    return ($f['parent_id'] !== null ? (int) $f['parent_id'] : null) === $cur && $folderVisible((int) $f['id']);
});
$here = array_values(array_filter($all, function ($l) use ($cur) { return ($l['folder_id'] !== null ? (int) $l['folder_id'] : null) === $cur; }));
$found = [];
if ($q !== '') {
    $found = array_values(array_filter($all, function ($l) use ($q) {
        return mb_stripos($l['title'], $q) !== false || mb_stripos((string) $l['body'], $q) !== false;
    }));
}
$folder = $cur ? $folders[$cur] : null;

page_start($folder ? $folder['title'] : 'Pamokos', $navOpt);
?>
<?php if ($folder): ?>
  <?= $crumbs($folder['parent_id'] !== null ? (int) $folder['parent_id'] : null) ?>
<?php endif; ?>
<div class="page-head row between" style="align-items:flex-end; flex-wrap:wrap; gap:12px;">
  <div>
    <?php if (!$folder): ?><div class="eyebrow">Tik nariams</div><?php endif; ?>
    <h1 class="styled"><?= $folder ? e(($folder['icon'] ? $folder['icon'] . ' ' : '') . $folder['title']) : 'Pamokos' ?></h1>
    <?php if ($folder && $folder['description']): ?><div class="muted" style="max-width:720px;"><?= lesson_text_html($folder['description']) ?></div><?php endif; ?>
  </div>
  <div class="row" style="gap:8px; align-items:center;">
    <form class="lesson-search" method="get" action="<?= url('pamokos.php') ?>">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Ieškoti pamokų…" aria-label="Ieškoti pamokų">
    </form>
  </div>
</div>

<?= staff_bar([['+ Nauja pamoka', 'admin/pamokos.php?edit=new' . ($cur ? '&aplankas=' . $cur : ''), true],
    ['+ Aplankas', 'admin/pamokos.php?folder=new' . ($cur ? '&parent=' . $cur : '')],
    ['⇅ Tvarkyti (tempti, perkelti)', 'admin/pamokos.php' . ($cur ? '#f' . $cur : '')]]) ?>
<?php if ($folder && $isStaff): ?><p class="small" style="margin:-8px 0 14px;"><a href="<?= url('admin/pamokos.php?folder=' . $cur) ?>">✎ Keisti šį aplanką</a></p><?php endif; ?>

<?php if ($q !== ''): ?>
  <h2 style="margin:0 0 12px;">Paieška: „<?= e($q) ?>“ <span class="muted small"><?= lt_count(count($found), 'pamoka', 'pamokos', 'pamokų') ?></span></h2>
  <?= $found ? lesson_cards($found, true, $folders) : '<div class="panel card"><p class="muted">Nieko nerasta.</p></div>' ?>
  <p style="margin-top:16px;"><a href="<?= url('pamokos.php') ?>">← Visos pamokos</a></p>
<?php else: ?>
  <?php if (!$isStaff && !$cur): ?>
    <a class="panel exam-prep-link" href="<?= url('egzaminas.php') ?>">
      <span class="kicker" style="margin:0;">Pasiruošimas egzaminui</span>
      <strong>Kito diržo programa, testas ir egzamino žingsniai →</strong>
    </a>
  <?php endif; ?>

  <?php if ($subfolders): ?>
    <div class="folder-grid">
      <?php $n = 0; foreach ($subfolders as $f): $fid = (int) $f['id'];
        $sub = count(array_filter($folders, function ($x) use ($fid, $folderVisible) { return (int) $x['parent_id'] === $fid && $x['parent_id'] !== null && $folderVisible((int) $x['id']); })); ?>
        <a class="panel folder-card rise" href="<?= url('pamokos.php?aplankas=' . $fid) ?>" style="--i:<?= min($n++, 8) ?>;">
          <span class="fc-icon"><?= e($f['icon'] ?: '📁') ?></span>
          <span class="fc-body">
            <strong><?= e($f['title']) ?></strong>
            <span class="small muted"><?= lt_count($counts[$fid] ?? 0, 'pamoka', 'pamokos', 'pamokų') ?><?= $sub ? ' · ' . lt_count($sub, 'aplankas', 'aplankai', 'aplankų') : '' ?></span>
            <?php if ($f['audience'] !== 'karateka' || !$f['is_published']): ?>
              <span><?= $f['audience'] !== 'karateka' ? '<span class="badge badge-tag">' . e(QUIZ_AUDIENCE[$f['audience']]) . '</span>' : '' ?><?= !$f['is_published'] ? ' <span class="badge badge-warn">nepaskelbtas</span>' : '' ?></span>
            <?php endif; ?>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($here): ?>
    <?php if ($subfolders): ?><h2 class="lessons-heading"><?= $cur ? 'Pamokos' : 'Kitos pamokos' ?></h2><?php endif; ?>
    <?= lesson_cards($here) ?>
  <?php elseif (!$subfolders): ?>
    <div class="panel card"><p class="muted"><?= $cur ? 'Šiame aplanke pamokų dar nėra.' : 'Pamokų dar nėra.' ?></p></div>
  <?php endif; ?>
<?php endif; ?>
<?php
page_end();
