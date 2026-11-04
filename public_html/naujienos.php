<?php
// Vieša naujienų juosta ir atskiras įrašas (?id=)
require __DIR__ . '/app/bootstrap.php';

const PER_PAGE = 10;

$id = (int) get('id');

if ($id) {
    // Treneris mato ir juodraščius bei suplanuotas (peržiūrai)
    $n = is_staff() ? q_one('SELECT * FROM news WHERE id = ?', [$id])
        : q_one('SELECT * FROM news WHERE id = ? AND is_published = 1 AND published_at <= NOW() AND ' . visibility_sql(), [$id]);
    if (!$n) {
        not_found();
    }
    $media = news_media($id);
    $summary = mb_substr(trim(preg_replace('/\s+/', ' ', $n['body'])), 0, 160);
    page_start($n['title'], [
        'section' => 'naujienos',
        'description' => $summary,
        // Facebook / Messenger peržiūra: pavadinimas, tekstas, nuotrauka
        'og' => $n['members_only'] ? null : [
            'type' => 'article', 'title' => $n['title'], 'description' => $summary,
            'url' => abs_url('naujienos.php?id=' . $id), 'image' => news_cover($id, true) ?: abs_url('img/apie-1.jpg'),
        ],
    ]);
    ?>
    <a class="small" href="<?= url('naujienos.php') ?>"><?= te('← Visos naujienos') ?></a> <?= staff_link('admin/naujienos.php?edit=' . (int) $n['id']) ?>
    <?php if (!$n['is_published']): ?> <span class="badge badge-warn"><?= te('juodraštis - nariai nemato') ?></span><?php elseif ($n['published_at'] > date('Y-m-d H:i:s')): ?> <span class="badge">suplanuota</span><?php endif; ?>
    <article class="panel card" style="margin-top:12px;">
      <div class="news-meta"><?= e(fmt_date($n['published_at'], true)) ?><?= members_only_badge($n) ?></div>
      <h1><?= e(tr_text($n['title'])) ?></h1>
      <div class="news-body"><?= text_to_html(tr_text($n['body'])) ?></div>
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
    <?= current_account() ? questions_box('news', $id, 'naujienos.php?id=' . $id) : '' ?>
    <?php
    news_script();
    page_end();
    exit;
}

$page = max(1, (int) get('p'));
$total = (int) q_value('SELECT COUNT(*) FROM news WHERE is_published = 1 AND published_at <= NOW() AND ' . visibility_sql());
$items = q_all('SELECT * FROM news WHERE is_published = 1 AND published_at <= NOW() AND ' . visibility_sql() . ' ORDER BY published_at DESC LIMIT ' . PER_PAGE . ' OFFSET ' . (($page - 1) * PER_PAGE));

// Treneriui - ir juodraščiai bei suplanuotos (nariai jų nemato)
$drafts = is_staff() ? q_all('SELECT * FROM news WHERE is_published = 0 OR published_at > NOW() ORDER BY published_at DESC') : [];

page_start(t('Naujienos'), ['description' => t('VšĮ Karate Ateitis klubo naujienos: varžybos, egzaminai, renginiai.'), 'section' => 'naujienos']);
?>
<div class="page-head">
  <div class="eyebrow"><?= te('Klubo gyvenimas') ?></div>
  <h1 class="styled"><?= te('Naujienos') ?></h1>
</div>
<?= staff_bar([[t('+ Nauja naujiena'), 'admin/naujienos.php?edit=new', true]]) ?>
<?php if ($drafts): ?>
  <div class="panel card drafts-box">
    <div class="kicker"><?= te('Juodraščiai ir suplanuotos - mato tik treneriai') ?></div>
    <ul class="list">
      <?php foreach ($drafts as $d): ?>
        <li class="row between"><span><a href="<?= url('admin/naujienos.php?edit=' . (int) $d['id']) ?>"><strong><?= e($d['title']) ?></strong></a>
          <?= !$d['is_published'] ? ('<span class="badge badge-warn">' . t('juodraštis') . '</span>') : '<span class="badge">suplanuota ' . e(fmt_date($d['published_at'], true)) . '</span>' ?></span>
          <a class="btn btn-ghost btn-sm" href="<?= url('admin/naujienos.php?edit=' . (int) $d['id']) ?>"><?= te('Keisti') ?></a></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if (!$items): ?>
  <div class="panel card"><p class="muted"><?= te('Naujienų dar nėra.') ?></p></div>
<?php endif; ?>

<div class="news-list">
  <?php foreach ($items as $i => $n):
      $cover = news_cover((int) $n['id']);
      $link = url('naujienos.php?id=' . (int) $n['id']);
      $body = tr_text($n['body']);
      $excerpt = mb_strlen($body) > 280 ? mb_substr($body, 0, 280) . '…' : $body;
  ?>
    <article class="panel news-card rise<?= $cover ? '' : ' no-cover' ?>" style="--i:<?= min($i, 8) ?>;">
      <?php if ($cover): ?><a class="cover" href="<?= $link ?>" style="display:block;"><img src="<?= e($cover) ?>" alt="" loading="lazy"></a><?php endif; ?>
      <div class="body">
        <div class="news-meta"><?= e(fmt_date($n['published_at'], true)) ?><?= members_only_badge($n) ?><?= staff_link('admin/naujienos.php?edit=' . (int) $n['id']) ?></div>
        <h2><a href="<?= $link ?>"><?= e(tr_text($n['title'])) ?></a></h2>
        <div class="news-body muted"><?= text_to_html($excerpt) ?></div>
        <a href="<?= $link ?>" class="small"><?= te('Skaityti daugiau →') ?></a>
        <div class="news-actions"><?= render_reactions((int) $n['id']) ?><?= share_button($n) ?></div>
      </div>
    </article>
  <?php endforeach; ?>
</div>

<?php if ($total > PER_PAGE): ?>
  <div class="row between" style="margin-top:24px;">
    <?php if ($page > 1): ?><a class="btn btn-ghost" href="?p=<?= $page - 1 ?>"><?= te('← Naujesnės') ?></a><?php else: ?><span></span><?php endif; ?>
    <?php if ($page * PER_PAGE < $total): ?><a class="btn btn-ghost" href="?p=<?= $page + 1 ?>"><?= te('Senesnės →') ?></a><?php endif; ?>
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
  // Kad emoji sąrašas neišlįstų už ekrano krašto: per siaura - pastumiama kairėn, viršuje nėra vietos - atsidaro žemyn
  function place(box) {
    var p = box.querySelector('.r-picker');
    p.style.left = '';
    box.classList.remove('below');
    var r = p.getBoundingClientRect(), max = document.documentElement.clientWidth - 8;
    if (r.right > max) p.style.left = Math.max(8 - box.getBoundingClientRect().left, max - r.right) + 'px';
    if (r.top < 70) box.classList.add('below');
  }
  function closeAll(except) {
    document.querySelectorAll('.r-add.open').forEach(function (b) {
      if (b === except) return;
      b.classList.remove('open');
      b.querySelector('.reaction-add').setAttribute('aria-expanded', 'false');
    });
  }
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
    fetch(form.action, { method: 'POST', body: data, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.login) { window.location = res.login; return; }
        if (res.error) { alert(res.error); return; }
        form.querySelector('.r-used').innerHTML = res.chips;
        form.querySelectorAll('.pick').forEach(function (b) { b.classList.toggle('mine', b.value === res.mine); });
        closeAll(null);
      })
      .catch(function () { form.submit(); });
  });
})();
</script>
<?php
}
