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

    if ($action === 'delete' && $id) {
        foreach (news_media($id) as $m) {
            if ($m['file']) {
                delete_news_files($m['file']);
            }
        }
        q('DELETE FROM news WHERE id = ?', [$id]);
        flash('ok', t('Naujiena ištrinta.'));
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
        flash('ok', t('Pašalinta.'));
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
        $errors[] = t('Įveskite pavadinimą.');
    }
    if ($body === '') {
        $errors[] = t('Įveskite tekstą.');
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
            flash('err', (t('Naujiena išsaugota, bet kai kurių nuotraukų įkelti nepavyko.') . ' ') . implode(' ', $uploadErrors));
        } else {
            flash('ok', $published ? t('Naujiena paskelbta.') : t('Juodraštis išsaugotas.'));
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

    page_start($n['id'] ? t('Redaguoti naujieną') : t('Nauja naujiena'), ['admin' => true, 'section' => 'naujienos']);
    ?>
    <a class="small" href="<?= url('naujienos.php') ?>"><?= te('← Visos naujienos') ?></a><?php if ($n['id'] && $n['is_published']): ?> · <a class="small" href="<?= url('naujienos.php?id=' . (int) $n['id']) ?>"><?= te('👁 Peržiūrėti') ?></a><?php endif; ?>
    <h1 class="styled" style="margin-top:8px;"><?= $n['id'] ? t('Redaguoti naujieną') : t('Nauja naujiena') ?></h1>
    <?= form_errors($errors) ?>
    <form method="post" enctype="multipart/form-data" class="form" id="newsForm">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
      <input type="hidden" name="edit" value="<?= e($edit) ?>">
      <div class="panel card form">
        <label><?= te('Pavadinimas') ?> <input type="text" name="title" value="<?= e($n['title']) ?>" maxlength="190" required></label>
        <label><?= te('Tekstas') ?> <span class="hint"><?= te('tuščia eilutė - nauja pastraipa; nuorodos tampa paspaudžiamos') ?></span>
          <textarea name="body" rows="10" required><?= e($n['body']) ?></textarea></label>
        <label><?= te('Nuotraukos') ?> <span class="hint"><?= te('galima pasirinkti kelias; sumažinamos automatiškai') ?></span>
          <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple></label>
        <?= no_photo_consent_html() ?>
        <label><?= te('YouTube nuorodos') ?> <span class="hint"><?= te('po vieną eilutėje') ?></span>
          <textarea name="youtube" rows="2" placeholder="https://www.youtube.com/watch?v=..." style="min-height:70px;"><?= e(post('youtube')) ?></textarea></label>
        <div class="form-row">
          <label><?= te('Paskelbimo data') ?> <input type="datetime-local" name="published_at" value="<?= e(date('Y-m-d\TH:i', strtotime($n['published_at']))) ?>"></label>
          <label class="check" style="align-self:end; padding-bottom:12px;"><input type="checkbox" name="is_published" value="1" <?= $n['is_published'] ? 'checked' : '' ?>><span><?= te('Paskelbta') ?></span></label>
          <label class="check"><input type="checkbox" name="members_only" value="1" <?= !empty($n['members_only']) ? 'checked' : '' ?>><span><?= te('Tik nariams (matys tik prisijungę nariai)') ?></span></label>
        </div>
        <div class="row">
          <button class="btn btn-primary" type="submit" id="saveBtn"><?= te('Išsaugoti') ?></button>
          <?php if ($n['id']): ?>
            <a class="btn btn-ghost" href="<?= url('naujienos.php?id=' . (int) $n['id']) ?>" target="_blank"><?= te('Peržiūrėti') ?></a>
            <button class="btn btn-danger" type="submit" name="action" value="delete" formnovalidate onclick="return confirm('Ištrinti naujieną kartu su nuotraukomis?')"><?= te('Ištrinti') ?></button>
          <?php endif; ?>
        </div>
      </div>
    </form>

    <?php if ($media): ?>
      <div class="panel card">
        <h2><?= te('Nuotraukos ir video') ?></h2>
        <div class="media-admin">
          <?php foreach ($media as $m): ?>
            <div class="item">
              <?php if ($m['type'] === 'image'): ?>
                <img src="<?= e(news_image_url($m['file'], true)) ?>" alt="">
              <?php else: ?>
                <div class="yt"><?= te('▶ YouTube') ?><br><?= e($m['youtube_id']) ?></div>
              <?php endif; ?>
              <form method="post" onsubmit="return confirm('Pašalinti?')">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                <input type="hidden" name="action" value="delete_media">
                <input type="hidden" name="media_id" value="<?= (int) $m['id'] ?>">
                <button class="btn btn-danger btn-sm" style="background:#fff;" type="submit" aria-label="<?= te('Pašalinti') ?>">✕</button>
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

// Naujienų sąrašas - pačiose Naujienose (naujienos.php). Reakcijoms nariai gali rinktis iš visų emoji (REACTION_CHOICES).
redirect('naujienos.php');
