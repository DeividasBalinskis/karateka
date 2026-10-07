<?php
// Vieša naujienų juosta ir atskiras įrašas (?id=)
require __DIR__ . '/app/bootstrap.php';

const PER_PAGE = 10;

$id = (int) get('id');

if ($id) {
    $n = q_one('SELECT * FROM news WHERE id = ? AND is_published = 1 AND published_at <= NOW() AND ' . visibility_sql(), [$id]);
    if (!$n) {
        not_found();
    }
    $media = news_media($id);
    page_start($n['title'], ['description' => mb_substr(preg_replace('/\s+/', ' ', $n['body']), 0, 160)]);
    ?>
    <a class="small" href="<?= url('naujienos.php') ?>">← Visos naujienos</a> <?= staff_link('admin/naujienos.php?edit=' . (int) $n['id']) ?>
    <article class="panel card" style="margin-top:12px;">
      <div class="news-meta"><?= e(fmt_date($n['published_at'], true)) ?><?= members_only_badge($n) ?></div>
      <h1><?= e($n['title']) ?></h1>
      <div class="news-body"><?= text_to_html($n['body']) ?></div>
      <?php $images = array_filter($media, function ($m) { return $m['type'] === 'image'; }); ?>
      <?php if ($images): ?>
        <div class="gallery">
          <?php foreach ($images as $m): ?>
            <a href="<?= e(news_image_url($m['file'])) ?>" target="_blank"><img src="<?= e(news_image_url($m['file'], true)) ?>" alt="" loading="lazy"></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php foreach ($media as $m): if ($m['type'] === 'youtube'): ?>
        <?= youtube_embed($m['youtube_id']) ?>
      <?php endif; endforeach; ?>
      <?= render_reactions($id) ?>
    </article>
    <?php
    reactions_script();
    page_end();
    exit;
}

$page = max(1, (int) get('p'));
$total = (int) q_value('SELECT COUNT(*) FROM news WHERE is_published = 1 AND published_at <= NOW() AND ' . visibility_sql());
$items = q_all('SELECT * FROM news WHERE is_published = 1 AND published_at <= NOW() AND ' . visibility_sql() . ' ORDER BY published_at DESC LIMIT ' . PER_PAGE . ' OFFSET ' . (($page - 1) * PER_PAGE));

page_start('Naujienos', ['description' => 'VšĮ Karate Ateitis klubo naujienos: varžybos, egzaminai, renginiai.']);
?>
<div class="page-head">
  <div class="eyebrow">Klubo gyvenimas</div>
  <h1 class="styled">Naujienos</h1> <?= staff_link('admin/naujienos.php?edit=new', '+ Nauja naujiena') ?>
</div>

<?php if (!$items): ?>
  <div class="panel card"><p class="muted">Naujienų dar nėra.</p></div>
<?php endif; ?>

<div class="news-list">
  <?php foreach ($items as $i => $n):
      $media = news_media((int) $n['id']);
      $cover = null;
      foreach ($media as $m) {
          if ($m['type'] === 'image') { $cover = news_image_url($m['file'], true); break; }
          if (!$cover && $m['type'] === 'youtube') { $cover = 'https://i.ytimg.com/vi/' . $m['youtube_id'] . '/hqdefault.jpg'; }
      }
      $link = url('naujienos.php?id=' . (int) $n['id']);
      $excerpt = mb_strlen($n['body']) > 280 ? mb_substr($n['body'], 0, 280) . '…' : $n['body'];
  ?>
    <article class="panel news-card rise<?= $cover ? '' : ' no-cover' ?>" style="--i:<?= min($i, 8) ?>;">
      <?php if ($cover): ?><a class="cover" href="<?= $link ?>" style="display:block;"><img src="<?= e($cover) ?>" alt="" loading="lazy"></a><?php endif; ?>
      <div class="body">
        <div class="news-meta"><?= e(fmt_date($n['published_at'], true)) ?><?= members_only_badge($n) ?><?= staff_link('admin/naujienos.php?edit=' . (int) $n['id']) ?></div>
        <h2><a href="<?= $link ?>"><?= e($n['title']) ?></a></h2>
        <div class="news-body muted"><?= text_to_html($excerpt) ?></div>
        <a href="<?= $link ?>" class="small">Skaityti daugiau →</a>
        <?= render_reactions((int) $n['id']) ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>

<?php if ($total > PER_PAGE): ?>
  <div class="row between" style="margin-top:24px;">
    <?php if ($page > 1): ?><a class="btn btn-ghost" href="?p=<?= $page - 1 ?>">← Naujesnės</a><?php else: ?><span></span><?php endif; ?>
    <?php if ($page * PER_PAGE < $total): ?><a class="btn btn-ghost" href="?p=<?= $page + 1 ?>">Senesnės →</a><?php endif; ?>
  </div>
<?php endif; ?>
<?php
reactions_script();
page_end();

/** Reakcijos be puslapio perkrovimo (veikia ir be JS - tada forma tiesiog išsiunčiama) */
function reactions_script(): void
{
    ?>
<script>
document.addEventListener('submit', function (ev) {
  var form = ev.target;
  if (!form.classList.contains('reactions')) return;
  ev.preventDefault();
  var btn = ev.submitter, data = new FormData(form);
  if (btn) data.set('emoji', btn.value);
  fetch(form.action, { method: 'POST', body: data, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (res.login) { window.location = res.login; return; }
      if (res.error) { alert(res.error); return; }
      form.querySelectorAll('.reaction').forEach(function (b) {
        var c = res.counts[b.value] || 0;
        b.querySelector('.count').textContent = c ? c : '';
        b.classList.toggle('mine', b.value === res.mine);
      });
    })
    .catch(function () { form.submit(); });
});
</script>
<?php
}
