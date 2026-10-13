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
    $summary = mb_substr(trim(preg_replace('/\s+/', ' ', $n['body'])), 0, 160);
    page_start($n['title'], [
        'description' => $summary,
        // Facebook / Messenger peržiūra: pavadinimas, tekstas, nuotrauka
        'og' => $n['members_only'] ? null : [
            'type' => 'article', 'title' => $n['title'], 'description' => $summary,
            'url' => abs_url('naujienos.php?id=' . $id), 'image' => news_cover($id, true) ?: abs_url('img/apie-1.jpg'),
        ],
    ]);
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
      <div class="news-actions"><?= render_reactions($id) ?><?= share_button($n) ?></div>
    </article>
    <?php
    news_script();
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
      $cover = news_cover((int) $n['id']);
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
        <div class="news-actions"><?= render_reactions((int) $n['id']) ?><?= share_button($n) ?></div>
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
news_script();
page_end();

/**
 * Reakcijos be puslapio perkrovimo (be JS forma tiesiog išsiunčiama) ir "Dalintis".
 * Emoji sąrašas kompiuteryje atsiveria užvedus pelę, telefone - paspaudus.
 */
function news_script(): void
{
    ?>
<script>
(function () {
  // Kad emoji sąrašas neišlįstų už kortelės ar ekrano krašto (telefone)
  function place(box) {
    var p = box.querySelector('.r-picker'), card = box.closest('.news-card, .card');
    p.style.left = '';
    var r = p.getBoundingClientRect(),
        max = Math.min(document.documentElement.clientWidth, card ? card.getBoundingClientRect().right : 1e9) - 8;
    if (r.right > max) p.style.left = (max - r.right) + 'px';
  }
  function closeAll(except) {
    document.querySelectorAll('.r-add.open').forEach(function (b) {
      if (b === except) return;
      b.classList.remove('open');
      b.querySelector('.reaction-add').setAttribute('aria-expanded', 'false');
    });
  }
  document.querySelectorAll('.r-add').forEach(function (box) {
    box.addEventListener('mouseenter', function () { place(box); });
    box.addEventListener('mouseleave', function () { box.classList.remove('picked'); });
  });
  document.addEventListener('click', function (ev) {
    var add = ev.target.closest('.reaction-add');
    if (add) {
      var box = add.parentNode, open = !box.classList.contains('open');
      closeAll(box);
      box.classList.toggle('open', open);
      add.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) place(box);
      return;
    }
    if (!ev.target.closest('.r-picker')) closeAll(null);

    // Dalintis: telefone - telefono meniu, kompiuteryje - Facebook langas
    var share = ev.target.closest('.share-btn');
    if (share) {
      ev.preventDefault();
      if (navigator.share && window.matchMedia('(pointer: coarse)').matches) {
        navigator.share({ title: share.dataset.shareTitle, url: share.dataset.shareUrl }).catch(function () {});
      } else {
        window.open(share.href, 'fbshare', 'width=640,height=580,noopener');
      }
    }
  });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') closeAll(null); });

  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!form.classList.contains('reactions')) return;
    ev.preventDefault();
    var btn = ev.submitter, data = new FormData(form);
    if (btn) data.set('emoji', btn.value);
    var box = form.querySelector('.r-add');
    fetch(form.action, { method: 'POST', body: data, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.login) { window.location = res.login; return; }
        if (res.error) { alert(res.error); return; }
        form.querySelector('.r-used').innerHTML = res.chips;
        form.querySelectorAll('.pick').forEach(function (b) { b.classList.toggle('mine', b.value === res.mine); });
        closeAll(null);
        if (box) box.classList.add('picked');   // kad sąrašas nesimatytų, kol pelė dar virš jo
      })
      .catch(function () { form.submit(); });
  });
})();
</script>
<?php
}
