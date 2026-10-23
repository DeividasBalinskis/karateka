<?php
// Naujienų rašymas: tekstas, nuotraukos (automatiškai sumažinamos), YouTube nuorodos
require dirname(__DIR__) . '/app/bootstrap.php';
require APP_DIR . '/images.php';
$me = require_staff();

const NEWS_DIR = PUBLIC_DIR . '/uploads/news';

$edit = get('edit') ?: post('edit');
$errors = [];

function delete_news_files(string $file): void
{
    @unlink(NEWS_DIR . '/' . basename($file));
    @unlink(NEWS_DIR . '/thumb_' . basename($file));
}

if (is_post()) {
    csrf_check();
    $id = (int) post('id');
    $action = post('action');

    // Kuriuos emoji nariai gali naudoti reakcijoms
    if ($action === 'save_reactions') {
        $choices = array_merge(...array_values(REACTION_CHOICES));
        $picked = array_values(array_intersect($choices, (array) ($_POST['reactions'] ?? [])));   // tvarka - kaip sąraše
        if (!$picked) {
            flash('err', 'Pažymėkite bent vieną emoji.');
        } elseif (count($picked) > 16) {
            flash('err', 'Daugiausia 16 emoji - kitaip sąrašas bus per didelis.');
        } else {
            try {
                set_site_setting('reactions', implode(' ', $picked));
                flash('ok', 'Reakcijų emoji išsaugoti (' . count($picked) . ').');
            } catch (PDOException $ex) {
                flash('err', 'Trūksta duomenų bazės lentelės - importuokite db/011_site_settings.sql.');
            }
        }
        redirect('admin/naujienos.php?emoji=1');
    }

    if ($action === 'delete' && $id) {
        foreach (news_media($id) as $m) {
            if ($m['file']) {
                delete_news_files($m['file']);
            }
        }
        q('DELETE FROM news WHERE id = ?', [$id]);
        flash('ok', 'Naujiena ištrinta.');
        redirect('naujienos.php');
    }

    if ($action === 'delete_media' && $id) {
        $m = q_one('SELECT * FROM news_media WHERE id = ? AND news_id = ?', [(int) post('media_id'), $id]);
        if ($m) {
            if ($m['file']) {
                delete_news_files($m['file']);
            }
            q('DELETE FROM news_media WHERE id = ?', [$m['id']]);
        }
        flash('ok', 'Pašalinta.');
        redirect('admin/naujienos.php?edit=' . $id);
    }

    // Išsaugojimas
    $title = post('title');
    $body = post('body');
    $published = !empty($_POST['is_published']) ? 1 : 0;
    $membersOnly = !empty($_POST['members_only']) ? 1 : 0;
    $date = post('published_at');
    $publishedAt = $date && strtotime($date) ? date('Y-m-d H:i:s', strtotime($date)) : date('Y-m-d H:i:s');

    $videos = [];
    foreach (preg_split('/\s+/', post('youtube')) as $link) {
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
    if ($body === '') {
        $errors[] = 'Įveskite tekstą.';
    }

    if (!$errors) {
        if ($id) {
            q('UPDATE news SET title = ?, body = ?, is_published = ?, members_only = ?, published_at = ?, updated_at = NOW() WHERE id = ?', [$title, $body, $published, $membersOnly, $publishedAt, $id]);
        } else {
            q('INSERT INTO news (title, body, author_id, is_published, members_only, published_at) VALUES (?, ?, ?, ?, ?, ?)', [$title, $body, $me['id'], $published, $membersOnly, $publishedAt]);
            $id = (int) db()->lastInsertId();
        }
        $sort = (int) q_value('SELECT COALESCE(MAX(sort_order), 0) FROM news_media WHERE news_id = ?', [$id]);
        foreach ($videos as $yt) {
            q('INSERT INTO news_media (news_id, type, youtube_id, sort_order) VALUES (?, "youtube", ?, ?)', [$id, $yt, ++$sort]);
        }
        $uploadErrors = [];
        foreach (uploaded_files('images') as $f) {
            try {
                $name = save_uploaded_image($f, NEWS_DIR);
                q('INSERT INTO news_media (news_id, type, file, sort_order) VALUES (?, "image", ?, ?)', [$id, $name, ++$sort]);
            } catch (RuntimeException $ex) {
                $uploadErrors[] = $f['name'] . ': ' . $ex->getMessage();
            }
        }
        if ($uploadErrors) {
            flash('err', 'Naujiena išsaugota, bet kai kurių nuotraukų įkelti nepavyko. ' . implode(' ', $uploadErrors));
        } else {
            flash('ok', $published ? 'Naujiena paskelbta.' : 'Juodraštis išsaugotas.');
        }
        redirect('admin/naujienos.php?edit=' . $id);
    }
}

if ($edit !== '') {
    $n = $edit === 'new'
        ? ['id' => 0, 'title' => '', 'body' => '', 'is_published' => 1, 'members_only' => 0, 'published_at' => date('Y-m-d H:i:s')]
        : q_one('SELECT * FROM news WHERE id = ?', [(int) $edit]);
    if (!$n) {
        not_found();
    }
    if ($errors) {
        $n = array_merge($n, ['title' => post('title'), 'body' => post('body'), 'members_only' => !empty($_POST['members_only'])]);
    }
    $media = $n['id'] ? news_media((int) $n['id']) : [];
    // Kas neleidžia klubui skelbti nuotraukų - treneriui prieš keliant (NULL = neatsakyta, laikome „ne“)
    $noPhoto = q_all("SELECT m.first_name, m.last_name, COALESCE(g.name, 'Be grupės') AS grp
        FROM members m LEFT JOIN training_groups g ON g.id = m.group_id
        WHERE m.status = 'active' AND NOT (m.photo_consent <=> 1)
        ORDER BY g.sort_order IS NULL, g.sort_order, g.name, m.first_name, m.last_name");
    $noPhotoEvents = $noPhoto ? q_all("SELECT e.title, e.starts_on,
            GROUP_CONCAT(CONCAT(m.first_name, ' ', m.last_name) ORDER BY m.first_name, m.last_name SEPARATOR ', ') AS names
        FROM events e
        JOIN event_participants p ON p.event_id = e.id AND p.status = 'going'
        JOIN members m ON m.id = p.member_id AND m.status = 'active' AND NOT (m.photo_consent <=> 1)
        WHERE e.starts_on BETWEEN CURDATE() - INTERVAL 30 DAY AND CURDATE()
        GROUP BY e.id, e.title, e.starts_on ORDER BY e.starts_on DESC") : [];
    $noPhotoGroups = [];
    foreach ($noPhoto as $m) {
        $noPhotoGroups[$m['grp']][] = $m['first_name'] . ' ' . $m['last_name'];
    }

    page_start($n['id'] ? 'Redaguoti naujieną' : 'Nauja naujiena', ['admin' => true, 'section' => 'naujienos']);
    ?>
    <a class="small" href="<?= url('naujienos.php') ?>">← Visos naujienos</a><?php if ($n['id'] && $n['is_published']): ?> · <a class="small" href="<?= url('naujienos.php?id=' . (int) $n['id']) ?>">👁 Peržiūrėti</a><?php endif; ?>
    <h1 class="styled" style="margin-top:8px;"><?= $n['id'] ? 'Redaguoti naujieną' : 'Nauja naujiena' ?></h1>
    <?= form_errors($errors) ?>
    <form method="post" enctype="multipart/form-data" class="form" id="newsForm">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
      <input type="hidden" name="edit" value="<?= e($edit) ?>">
      <div class="panel card form">
        <label>Pavadinimas <input type="text" name="title" value="<?= e($n['title']) ?>" maxlength="190" required></label>
        <label>Tekstas <span class="hint">tuščia eilutė - nauja pastraipa; nuorodos tampa paspaudžiamos</span>
          <textarea name="body" rows="10" required><?= e($n['body']) ?></textarea></label>
        <label>Nuotraukos <span class="hint">galima pasirinkti kelias; sumažinamos automatiškai</span>
          <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple></label>
        <?php if ($noPhoto): ?>
          <details class="nophoto-list">
            <summary><span class="badge badge-nophoto">Be nuotraukų</span> <?= kiek(count($noPhoto), 'narys neleidžia', 'nariai neleidžia', 'narių neleidžia') ?> skelbti savo nuotraukų – peržiūrėkite prieš keliant</summary>
            <p class="hint">Jei nuotraukoje aiškiai matosi kuris nors iš jų – nekelkite jos arba apkarpykite. Varžybų organizatoriaus nuotraukoms tai negalioja.</p>
            <?php if ($noPhotoEvents): ?>
              <h3>Renginiai per 30 dienų</h3>
              <ul>
                <?php foreach ($noPhotoEvents as $ev): ?>
                  <li><b><?= e($ev['title']) ?></b> <span class="muted">(<?= e(fmt_date($ev['starts_on'])) ?>)</span>: <?= e($ev['names']) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <h3>Pagal grupes</h3>
            <ul>
              <?php foreach ($noPhotoGroups as $grp => $names): ?>
                <li><b><?= e($grp) ?></b>: <?= e(implode(', ', $names)) ?></li>
              <?php endforeach; ?>
            </ul>
          </details>
        <?php endif; ?>
        <label>YouTube nuorodos <span class="hint">po vieną eilutėje</span>
          <textarea name="youtube" rows="2" placeholder="https://www.youtube.com/watch?v=..." style="min-height:70px;"><?= e(post('youtube')) ?></textarea></label>
        <div class="form-row">
          <label>Paskelbimo data <input type="datetime-local" name="published_at" value="<?= e(date('Y-m-d\TH:i', strtotime($n['published_at']))) ?>"></label>
          <label class="check" style="align-self:end; padding-bottom:12px;"><input type="checkbox" name="is_published" value="1" <?= $n['is_published'] ? 'checked' : '' ?>><span>Paskelbta</span></label>
          <label class="check"><input type="checkbox" name="members_only" value="1" <?= !empty($n['members_only']) ? 'checked' : '' ?>><span>Tik nariams (matys tik prisijungę nariai)</span></label>
        </div>
        <div class="row">
          <button class="btn btn-primary" type="submit" id="saveBtn">Išsaugoti</button>
          <?php if ($n['id']): ?>
            <a class="btn btn-ghost" href="<?= url('naujienos.php?id=' . (int) $n['id']) ?>" target="_blank">Peržiūrėti</a>
            <button class="btn btn-danger" type="submit" name="action" value="delete" formnovalidate onclick="return confirm('Ištrinti naujieną kartu su nuotraukomis?')">Ištrinti</button>
          <?php endif; ?>
        </div>
      </div>
    </form>

    <?php if ($media): ?>
      <div class="panel card">
        <h2>Nuotraukos ir video</h2>
        <div class="media-admin">
          <?php foreach ($media as $m): ?>
            <div class="item">
              <?php if ($m['type'] === 'image'): ?>
                <img src="<?= e(news_image_url($m['file'], true)) ?>" alt="">
              <?php else: ?>
                <div class="yt">▶ YouTube<br><?= e($m['youtube_id']) ?></div>
              <?php endif; ?>
              <form method="post" onsubmit="return confirm('Pašalinti?')">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                <input type="hidden" name="action" value="delete_media">
                <input type="hidden" name="media_id" value="<?= (int) $m['id'] ?>">
                <button class="btn btn-danger btn-sm" style="background:#fff;" type="submit" aria-label="Pašalinti">✕</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
    <script>
      document.getElementById('newsForm').addEventListener('submit', function (e) {
        if (e.submitter && e.submitter.value === 'delete') return;
        var b = document.getElementById('saveBtn'); b.textContent = 'Įkeliama…'; b.style.opacity = .6;
      });
    </script>
    <?php
    page_end();
    exit;
}

// Naujienų sąrašas - pačiose Naujienose (naujienos.php); čia lieka tik reakcijų emoji nustatymai
if (!get('emoji')) {
    redirect('naujienos.php');
}
$allowed = allowed_reactions();

page_start('Reakcijų emoji', ['admin' => true, 'section' => 'naujienos']);
?>
<a class="small" href="<?= url('naujienos.php') ?>">← Naujienos</a>
<h1 class="styled" style="margin-top:8px;">Reakcijų emoji</h1>

<!-- Reakcijų emoji: adminas pažymi, kuriuos nariai gali naudoti po naujienomis -->
<details class="panel card emoji-settings" id="emoji" open>
  <summary>
    <span><strong>🙂 Reakcijų emoji</strong> <span class="muted small">· nariai gali naudoti <?= count($allowed) ?></span></span>
    <span class="emoji-now" aria-hidden="true"><?= e(implode(' ', $allowed)) ?></span>
    <span class="btn btn-ghost btn-sm">Keisti</span>
  </summary>
  <form method="post" class="emoji-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_reactions">
    <p class="hint">Paspauskite emoji, kad jį įjungtumėte ar išjungtumėte. Įjungti emoji atsiras po kiekviena naujiena, mygtuke 🙂+. Jei emoji išjungsite, jau paliktos reakcijos išliks.</p>
    <?php foreach (REACTION_CHOICES as $groupName => $list): ?>
      <div class="emoji-group">
        <div class="kicker"><?= e($groupName) ?></div>
        <div class="emoji-grid">
          <?php foreach ($list as $emoji): ?>
            <label class="emoji-opt" title="<?= e($emoji) ?>"><input type="checkbox" name="reactions[]" value="<?= e($emoji) ?>" <?= in_array($emoji, $allowed, true) ? 'checked' : '' ?>><span><?= e($emoji) ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <div class="row" style="margin-top:14px; gap:12px; align-items:center;">
      <button class="btn btn-primary" type="submit">Išsaugoti</button>
      <span class="muted small" id="emojiCount"></span>
    </div>
  </form>
  <script>
    (function () {
      var f = document.querySelector('.emoji-form'), out = document.getElementById('emojiCount');
      function upd() { var n = f.querySelectorAll('input:checked').length; out.textContent = 'Pažymėta: ' + n + (n > 16 ? ' (daugiausia 16)' : ''); }
      f.addEventListener('change', upd); upd();
      if (location.hash === '#emoji') document.getElementById('emoji').open = true;
    })();
  </script>
</details>

<?php
page_end();
