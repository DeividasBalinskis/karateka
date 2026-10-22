<?php
// Egzaminų programa: ko reikia kiekvienam diržui (kihon, kata, kumite, teorija). Nariai mato kito diržo programą.
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

$belt = isset(BELTS[(int) (get('dirzas') ?: post('dirzas'))]) ? (int) (get('dirzas') ?: post('dirzas')) : 2;
$back = 'admin/programa.php?dirzas=' . $belt;
$errors = [];

if (is_post()) {
    csrf_check();
    $rid = (int) post('rid');
    if (post('action') === 'delete' && $rid) {
        q('DELETE FROM belt_requirements WHERE id = ?', [$rid]);
        flash('ok', 'Punktas ištrintas.');
        redirect($back);
    }
    $section = isset(REQ_SECTIONS[post('section')]) ? post('section') : 'kita';
    $title = post('title');
    $yt = post('youtube') !== '' ? youtube_id(post('youtube')) : null;
    $lesson = (int) post('lesson_id') ?: null;
    if ($title === '') {
        $errors[] = 'Įveskite, ko reikia (pvz. „Heian Shodan“).';
    }
    if (post('youtube') !== '' && !$yt) {
        $errors[] = 'Neatpažinta YouTube nuoroda.';
    }
    if (!$errors) {
        if ($rid) {
            q('UPDATE belt_requirements SET section = ?, title = ?, body = ?, youtube_id = ?, lesson_id = ?, sort_order = ? WHERE id = ?',
                [$section, $title, post('body') ?: null, $yt, $lesson, (int) post('sort_order'), $rid]);
        } else {
            $next = (int) q_value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM belt_requirements WHERE belt_level = ? AND section = ?', [$belt, $section]);
            q('INSERT INTO belt_requirements (belt_level, section, title, body, youtube_id, lesson_id, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$belt, $section, $title, post('body') ?: null, $yt, $lesson, $next]);
        }
        flash('ok', 'Išsaugota.');
        redirect($back);
    }
}

$reqs = belt_requirements($belt);
$counts = [];
foreach (q_all('SELECT belt_level, COUNT(*) c FROM belt_requirements GROUP BY belt_level') as $r) {
    $counts[(int) $r['belt_level']] = (int) $r['c'];
}
$lessons = q_all('SELECT id, title, belt_level FROM lessons ORDER BY belt_level IS NULL, belt_level, title');

function req_form(int $belt, ?array $r, array $lessons): string
{
    $html = '<form method="post" class="form req-form">' . csrf_field()
        . '<input type="hidden" name="dirzas" value="' . $belt . '"><input type="hidden" name="rid" value="' . (int) ($r['id'] ?? 0) . '">'
        . '<div class="form-row' . ($r ? ' three' : '') . '"><label>Dalis <select name="section">';
    foreach (REQ_SECTIONS as $k => $label) {
        $html .= '<option value="' . $k . '"' . (($r['section'] ?? 'kihon') === $k ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    $html .= '</select></label><label>Ko reikia <input type="text" name="title" value="' . e($r['title'] ?? '') . '" placeholder="pvz. Heian Shodan" required></label>'
        . ($r ? '<label>Eilė <input type="number" name="sort_order" value="' . (int) $r['sort_order'] . '"></label>' : '') . '</div>'
        . '<label>Paaiškinimas <span class="hint">nebūtina</span><textarea name="body" rows="2">' . e($r['body'] ?? '') . '</textarea></label>'
        . '<div class="form-row"><label>YouTube <span class="hint">nebūtina</span><input type="text" name="youtube" value="' . (!empty($r['youtube_id']) ? 'https://youtu.be/' . e($r['youtube_id']) : '') . '"></label>'
        . '<label>Pamoka <span class="hint">nebūtina</span><select name="lesson_id"><option value="">—</option>';
    foreach ($lessons as $l) {
        $html .= '<option value="' . (int) $l['id'] . '"' . ((int) ($r['lesson_id'] ?? 0) === (int) $l['id'] ? ' selected' : '') . '>'
            . e(($l['belt_level'] ? BELTS[(int) $l['belt_level']][0] . ' · ' : '') . $l['title']) . '</option>';
    }
    return $html . '</select></label></div><div><button class="btn btn-primary btn-sm" type="submit">' . (!empty($r['id']) ? 'Išsaugoti' : '+ Pridėti') . '</button></div></form>';
}

page_start('Egzaminų programa', ['admin' => true]);
?>
<a class="small" href="<?= url('admin/testai.php') ?>">← Testai</a>
<div class="page-head" style="margin-top:8px;">
  <h1 class="styled">Egzaminų programa</h1>
  <p class="muted" style="margin:0;">Ko reikia norint gauti diržą. Nariai mato savo kito diržo programą skiltyje „Egzaminas“ ir žymi, ką jau moka.</p>
</div>
<div class="top-tabs belt-tabs">
  <?php foreach (BELTS as $lvl => [$kyu, $name, $color]): if ($lvl === 1 || $lvl > 12) { continue; } ?>
    <a href="?dirzas=<?= $lvl ?>" class="<?= $lvl === $belt ? 'active' : '' ?>"><span class="belt-swatch" style="background:<?= $color ?>"></span><?= e($kyu) ?><?= !empty($counts[$lvl]) ? ' <small>' . $counts[$lvl] . '</small>' : '' ?></a>
  <?php endforeach; ?>
</div>
<?= form_errors($errors) ?>
<div class="panel card">
  <h2><?= e(belt_label($belt)) ?></h2>
  <?php if (!$reqs): ?><p class="muted">Šiam diržui programos dar nėra.</p><?php endif; ?>
  <?php foreach ($reqs as $section => $items): ?>
    <h3 class="req-section"><?= e(REQ_SECTIONS[$section]) ?></h3>
    <?php foreach ($items as $r): ?>
      <details class="req-admin">
        <summary><strong><?= e($r['title']) ?></strong><?= $r['youtube_id'] ? ' <span class="badge">video</span>' : '' ?><?= $r['lesson_id'] ? ' <span class="badge">pamoka</span>' : '' ?>
          <?php if ($r['body']): ?><span class="small muted"> · <?= e(mb_strimwidth($r['body'], 0, 80, '…')) ?></span><?php endif; ?></summary>
        <?= req_form($belt, $r, $lessons) ?>
        <form method="post" class="inline-form" onsubmit="return confirm('Ištrinti punktą?')">
          <?= csrf_field() ?><input type="hidden" name="dirzas" value="<?= $belt ?>"><input type="hidden" name="rid" value="<?= (int) $r['id'] ?>">
          <button class="linklike small muted" name="action" value="delete" style="border:none; padding:0; text-decoration:underline;">ištrinti</button>
        </form>
      </details>
    <?php endforeach; ?>
  <?php endforeach; ?>
</div>
<div class="panel card">
  <h2>Pridėti punktą</h2>
  <?= req_form($belt, null, $lessons) ?>
</div>
<?php
page_end();
