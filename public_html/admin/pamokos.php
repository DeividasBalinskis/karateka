<?php
// Pamokos nariams: tekstas ir / ar YouTube („Unlisted“) video, temos, kam skirta
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

$edit = get('edit') ?: post('edit');
$errors = [];

if (is_post()) {
    csrf_check();
    $id = (int) post('id');

    if (post('action') === 'delete' && $id) {
        q('DELETE FROM lessons WHERE id = ?', [$id]);
        flash('ok', 'Pamoka ištrinta.');
        redirect('admin/pamokos.php');
    }

    $title = post('title');
    $topic = post('topic') ?: null;
    $belt = belt_from_post('belt_level');
    $body = post('body') ?: null;
    $published = !empty($_POST['is_published']) ? 1 : 0;
    $groupIds = array_map('intval', (array) ($_POST['groups'] ?? []));

    $videos = [];
    foreach (preg_split('/\s+/', post('videos')) as $link) {
        if ($link === '') {
            continue;
        }
        $yt = youtube_id($link);
        if ($yt) {
            $videos[] = $yt;
        } else {
            $errors[] = "Neatpažinta YouTube nuoroda: $link";
        }
    }
    if ($title === '') {
        $errors[] = 'Įveskite pavadinimą.';
    }
    if (!$body && !$videos) {
        $errors[] = 'Pamokoje turi būti tekstas arba bent vienas video.';
    }

    if (!$errors) {
        db()->beginTransaction();
        $videoStr = $videos ? implode(',', array_unique($videos)) : null;
        if ($id) {
            q('UPDATE lessons SET title = ?, topic = ?, belt_level = ?, body = ?, videos = ?, is_published = ?, updated_at = NOW() WHERE id = ?',
                [$title, $topic, $belt, $body, $videoStr, $published, $id]);
            q('DELETE FROM lesson_groups WHERE lesson_id = ?', [$id]);
        } else {
            q('INSERT INTO lessons (title, topic, belt_level, body, videos, is_published, author_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$title, $topic, $belt, $body, $videoStr, $published, $me['id']]);
            $id = (int) db()->lastInsertId();
        }
        foreach ($groupIds as $gid) {
            q('INSERT IGNORE INTO lesson_groups (lesson_id, group_id) VALUES (?, ?)', [$id, $gid]);
        }
        db()->commit();
        flash('ok', $published ? 'Pamoka paskelbta.' : 'Pamoka išsaugota (nepaskelbta).');
        redirect('admin/pamokos.php');
    }
}

if ($edit !== '') {
    $l = $edit === 'new'
        ? ['id' => 0, 'title' => '', 'topic' => '', 'belt_level' => null, 'body' => '', 'videos' => '', 'is_published' => 1]
        : q_one('SELECT * FROM lessons WHERE id = ?', [(int) $edit]);
    if (!$l) {
        not_found();
    }
    $selectedGroups = $l['id'] ? array_map('strval', q('SELECT group_id FROM lesson_groups WHERE lesson_id = ?', [$l['id']])->fetchAll(PDO::FETCH_COLUMN)) : [];
    $videoLinks = implode("\n", array_map(function ($v) { return 'https://youtu.be/' . $v; }, lesson_videos($l)));
    if ($errors) {
        $l = array_merge($l, ['title' => post('title'), 'topic' => post('topic'), 'belt_level' => belt_from_post('belt_level'), 'body' => post('body'), 'is_published' => !empty($_POST['is_published'])]);
        $videoLinks = post('videos');
        $selectedGroups = array_map('strval', (array) ($_POST['groups'] ?? []));
    }
    $allGroups = q_all('SELECT * FROM training_groups WHERE is_active = 1 ORDER BY sort_order, name');

    page_start($l['id'] ? 'Redaguoti pamoką' : 'Nauja pamoka', ['admin' => true]);
    ?>
    <a class="small" href="<?= url('admin/pamokos.php') ?>">← Visos pamokos</a>
    <h1 class="styled" style="margin-top:8px;"><?= $l['id'] ? 'Redaguoti pamoką' : 'Nauja pamoka' ?></h1>
    <?= form_errors($errors) ?>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
      <input type="hidden" name="edit" value="<?= e($edit) ?>">
      <div class="grid-2">
        <div class="panel card form">
          <label>Pavadinimas <input type="text" name="title" value="<?= e($l['title']) ?>" maxlength="190" required placeholder="pvz. Heian Shodan - žingsnis po žingsnio"></label>
          <label>Tema <span class="hint">nebūtina; pasirinkite esamą arba įrašykite naują</span>
            <input type="text" name="topic" value="<?= e($l['topic']) ?>" list="topics" maxlength="60" placeholder="pvz. Kata">
            <datalist id="topics">
              <?php foreach (array_unique(array_merge(['Kata', 'Kihon', 'Kumite', 'Fizinis pasiruošimas'], lesson_topics())) as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?>
            </datalist>
          </label>
          <label>Diržas <span class="hint">kuriam diržui skirta; vaikai mato savo ir kito diržo pamokas</span>
            <select name="belt_level"><?= belt_options($l['belt_level'] !== null ? (int) $l['belt_level'] : null, 'Visiems diržams') ?></select></label>
          <label>YouTube nuorodos <span class="hint">po vieną eilutėje; video įkelkite į YouTube kaip „Unlisted“</span>
            <textarea name="videos" rows="3" style="min-height:80px;" placeholder="https://youtu.be/..."><?= e($videoLinks) ?></textarea></label>
          <label>Tekstas <span class="hint">nebūtina, jei yra video; tuščia eilutė - nauja pastraipa</span>
            <textarea name="body" rows="10"><?= e($l['body']) ?></textarea></label>
          <label class="check"><input type="checkbox" name="is_published" value="1" <?= $l['is_published'] ? 'checked' : '' ?>><span>Paskelbta (matoma nariams)</span></label>
        </div>
        <div class="panel card form">
          <h2>Kam skirta?</h2>
          <p class="hint">Nieko nepažymėjus - visiems nariams.</p>
          <?php foreach ($allGroups as $g): ?>
            <label class="check"><input type="checkbox" name="groups[]" value="<?= (int) $g['id'] ?>" <?= in_array((string) $g['id'], $selectedGroups, true) ? 'checked' : '' ?>><span><?= e($g['name']) ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="row" style="margin-top:20px;">
        <button class="btn btn-primary" type="submit">Išsaugoti</button>
        <?php if ($l['id']): ?>
          <a class="btn btn-ghost" href="<?= url('pamokos.php?id=' . (int) $l['id']) ?>" target="_blank">Peržiūrėti</a>
          <button class="btn btn-danger" type="submit" name="action" value="delete" formnovalidate onclick="return confirm('Ištrinti pamoką?')">Ištrinti</button>
        <?php endif; ?>
      </div>
    </form>
    <?php
    page_end();
    exit;
}

$lessons = q_all('SELECT l.*, (SELECT GROUP_CONCAT(g.name SEPARATOR ", ") FROM lesson_groups lg JOIN training_groups g ON g.id = lg.group_id WHERE lg.lesson_id = l.id) AS group_names
                    FROM lessons l ORDER BY l.belt_level IS NOT NULL, l.belt_level, l.topic, l.created_at DESC');

page_start('Pamokos', ['admin' => true]);
?>
<div class="page-head row between">
  <div>
    <h1 class="styled">Pamokos</h1>
    <p>Video ir tekstinės pamokos - matomos tik prisijungusiems nariams.</p>
  </div>
  <a class="btn btn-primary" href="?edit=new">+ Nauja pamoka</a>
</div>
<div class="panel card">
  <?php if (!$lessons): ?><p class="muted">Pamokų dar nėra.</p><?php endif; ?>
  <ul class="list">
    <?php foreach ($lessons as $l): $v = count(lesson_videos($l)); ?>
      <li class="row between">
        <span>
          <?= $l['belt_level'] !== null ? belt_chip((int) $l['belt_level']) : '' ?>
          <?php if ($l['topic']): ?><span class="badge badge-pink"><?= e($l['topic']) ?></span><?php endif; ?>
          <a href="?edit=<?= (int) $l['id'] ?>"><strong><?= e($l['title']) ?></strong></a>
          <?php if (!$l['is_published']): ?><span class="badge badge-warn">nepaskelbta</span><?php endif; ?>
          <div class="muted small"><?= $v ? "▶ $v video · " : '' ?><?= $l['body'] ? 'tekstas · ' : '' ?><?= $l['group_names'] ? e($l['group_names']) : 'visiems' ?></div>
        </span>
        <a class="btn btn-ghost btn-sm" href="?edit=<?= (int) $l['id'] ?>">Keisti</a>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php
page_end();
