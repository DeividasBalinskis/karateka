<?php
// Pamokos nariams (tik prisijungusiems ir patvirtintiems)
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

$id = (int) get('id');
if ($id) {
    $lesson = null;
    foreach (visible_lessons($a) as $l) {
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
    page_start($lesson['title'], ['noindex' => true]);
    ?>
    <a class="small" href="<?= url('pamokos.php' . ($lesson['topic'] ? '?tema=' . rawurlencode($lesson['topic']) : '')) ?>">← Visos pamokos</a>
    <article class="panel card lesson" style="margin-top:12px;">
      <?= $lesson['belt_level'] !== null ? belt_chip((int) $lesson['belt_level']) : '' ?>
      <?php if ($lesson['topic']): ?><span class="badge badge-pink"><?= e($lesson['topic']) ?></span><?php endif; ?>
      <?= staff_link('admin/pamokos.php?edit=' . (int) $lesson['id']) ?>
      <h1 style="margin-top:10px;"><?= e($lesson['title']) ?></h1>
      <?php foreach (lesson_videos($lesson) as $v): ?><?= youtube_embed($v) ?><?php endforeach; ?>
      <?php if ($lesson['body']): ?><div class="news-body"><?= text_to_html($lesson['body']) ?></div><?php endif; ?>
    </article>
    <?php
    page_end();
    exit;
}

$topics = lesson_topics();
$topic = get('tema');
$current = in_array($topic, $topics, true) ? $topic : null;
$lessons = visible_lessons($a, $current);

page_start('Pamokos', ['noindex' => true]);
?>
<div class="page-head">
  <div class="eyebrow">Tik nariams</div>
  <h1 class="styled">Pamokos</h1>
  <?= staff_link('admin/pamokos.php?edit=new', '+ Nauja pamoka') ?>
</div>

<?php if ($topics): ?>
  <div class="top-tabs" style="margin-bottom:20px;">
    <a href="<?= url('pamokos.php') ?>" class="<?= $current === null ? 'active' : '' ?>">Visos</a>
    <?php foreach ($topics as $t): ?>
      <a href="?tema=<?= e(rawurlencode($t)) ?>" class="<?= $current === $t ? 'active' : '' ?>"><?= e($t) ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (!$lessons): ?>
  <div class="panel card"><p class="muted">Pamokų dar nėra.</p></div>
<?php endif; ?>

<?php
// Pamokos sugrupuotos pagal diržą: pirma „visiems“, tada nuo balto iki juodo
$byBelt = [];
foreach ($lessons as $l) {
    $byBelt[$l['belt_level'] === null ? 0 : (int) $l['belt_level']][] = $l;
}
ksort($byBelt);
$n = 0;   // eilės nr. animacijai (laipteliai)
?>
<?php foreach ($byBelt as $beltLvl => $beltLessons): ?>
  <div class="belt-heading rise" style="--i:<?= min($n++, 8) ?>;">
    <h2><?= $beltLvl ? e(BELTS[$beltLvl][0]) : 'Visiems' ?></h2>
    <?= $beltLvl ? belt_chip($beltLvl) : '' ?>
  </div>
<div class="lessons-grid">
  <?php foreach ($beltLessons as $l): $videos = lesson_videos($l); $link = url('pamokos.php?id=' . (int) $l['id']); ?>
    <a class="panel lesson-card rise" href="<?= $link ?>" style="--i:<?= min($n++, 8) ?>;">
      <div class="thumb">
        <?php if ($videos): ?>
          <img src="https://i.ytimg.com/vi/<?= e($videos[0]) ?>/hqdefault.jpg" alt="" loading="lazy">
          <span class="play">▶</span>
        <?php else: ?>
          <span class="text-icon">Aa</span>
        <?php endif; ?>
      </div>
      <div class="body">
        <?php if ($l['topic']): ?><span class="badge badge-pink"><?= e($l['topic']) ?></span><?php endif; ?>
        <?php if (!$l['is_published']): ?><span class="badge badge-warn">nepaskelbta</span><?php endif; ?>
        <h3><?= e($l['title']) ?></h3>
        <div class="muted small"><?= $videos ? count($videos) . ' video' : 'Tekstinė pamoka' ?></div>
      </div>
    </a>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>
<?php
page_end();
