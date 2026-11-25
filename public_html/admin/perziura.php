<?php
// v2.02: 🔎 Peržiūra (tik tikri administratoriai - Deividas ir Denis). Trys dalys:
//   1. Darbų eilė - ką daro taisymo agentas (debesyje, tikrina kas valandą per perziura-api.php).
//   2. Pakeitimai test svetainėje (app/review.json) - kas ir kaip įvertino auksines žymas; „⬆ Įkelti į test“.
//   3. Ką taisyti - atviri atsiliepimai trumpai: pažymėti, parašyti komentarą → „▶ Paleisti taisymą“.
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_can('review');
$ready = review_ready() && feedback_ready();

if (is_post() && $ready) {
    csrf_check();
    switch (post('action')) {
        case 'fix':
            $items = [];
            foreach ((array) ($_POST['ids'] ?? []) as $id) {
                $id = (int) $id;
                if ($id > 0 && q_value('SELECT id FROM feedback WHERE id = ?', [$id])) {
                    $items[] = ['id' => $id, 'note' => mb_substr(trim((string) ($_POST['note'][$id] ?? '')), 0, 2000)];
                }
            }
            if (!$items && trim((string) post('note_all')) === '') {
                flash('err', t('Pažymėkite bent vieną atsiliepimą arba parašykite, ką daryti.'));
                break;
            }
            $job = review_job_add('fix', $items, (string) post('note_all'), $me);
            flash('ok', t('Darbas #{n} eilėje - agentas pradės per valandą', ['n' => $job]));
            break;
        case 'deploy':
            $job = review_job_add('deploy', [], (string) post('note_all'), $me, review_manifest()['rev']);
            flash('ok', t('Darbas #{n} eilėje: įkelti į test - per valandą', ['n' => $job]));
            break;
        case 'close':   // v2.09: darbą padarė žmogus ar kita sesija - pažymima „padaryta“ su atsakymu
            q('UPDATE review_jobs SET status = "done", result = ?, finished_at = NOW(), started_at = IFNULL(started_at, NOW()) WHERE id = ? AND status IN ("queued", "working")',
                [mb_substr(trim((string) post('result')), 0, 4000) ?: t('Padaryta.'), (int) post('id')]);
            flash('ok', t('Darbas pažymėtas padarytu.'));
            break;
        case 'cancel':
            q('UPDATE review_jobs SET status = "cancelled", finished_at = NOW() WHERE id = ? AND status = "queued"', [(int) post('id')]);
            flash('ok', t('Darbas atšauktas.'));
            break;
    }
    redirect('admin/perziura.php');
}

$jobs = $ready ? review_jobs(15) : [];
$open = array_values(array_filter($jobs, function ($j) { return in_array($j['status'], ['queued', 'working'], true); }));
$queuedFb = [];
foreach ($open as $j) {
    if ($j['kind'] === 'fix') {
        foreach ((array) json_decode((string) $j['items'], true) as $it) {
            $queuedFb[(int) ($it['id'] ?? 0)] = (int) $j['id'];
        }
    }
}
$st = get('b') === 'visi' ? '' : (in_array(get('b'), ['new', 'planned', 'question'], true) ? (string) get('b') : '');
$rows = $ready ? q_all('SELECT f.*, a.first_name, a.last_name FROM feedback f LEFT JOIN accounts a ON a.id = f.account_id
                        WHERE f.status IN ("new", "planned", "question")' . ($st !== '' ? ' AND f.status = ' . db()->quote($st) : '') . ' ORDER BY f.id DESC LIMIT 300') : [];
$rows = array_values(array_filter($rows, function ($f) use ($me) { return feedback_area_visible(feedback_row_area($f), $me); }));
if ($rows) {
    feedback_images_prefetch(array_column($rows, 'id'));
}
$m = review_manifest();
$marks = $ready && $m['rev'] !== '' ? review_marks_all($m['rev']) : [];
$byItem = [];
foreach ($marks as $r) {
    $byItem[$r['change_id']][] = $r;
}
$ago = function (?string $dt): string {
    if (!$dt) {
        return '';
    }
    return date('m-d H:i', strtotime($dt));
};

page_start(t('Peržiūra'), ['admin' => true]);
?>
<style>
.rvp-grid{display:grid; gap:16px;}
.rvp-head p{margin:4px 0 0;}
.rvp-steps{display:flex; flex-wrap:wrap; gap:8px; margin:10px 0 0; padding:0; list-style:none;}
.rvp-steps li{font-size:13px; padding:6px 10px; border-radius:999px; background:rgba(224,168,46,.13); color:#6B4A00; font-weight:600;}
.rvp-job{display:grid; grid-template-columns:auto 1fr auto; gap:4px 12px; align-items:start; padding:10px 0; border-top:1px solid rgba(23,20,15,.07);}
.rvp-job:first-of-type{border-top:0;}
.rvp-job .rvp-k{font-weight:700; white-space:nowrap;}
.rvp-job .rvp-res{grid-column:2 / -1; font-size:13.5px; white-space:pre-wrap; color:#4A453A; background:#F6F2E8; border-radius:10px; padding:8px 10px;}
.rvp-job .rvp-meta{font-size:12.5px; color:#6B6458;}
.rvp-ch{display:grid; grid-template-columns:1fr auto; gap:6px 12px; padding:9px 0; border-top:1px solid rgba(23,20,15,.07); align-items:start;}
.rvp-ch:first-of-type{border-top:0;}
.rvp-ch-t{font-weight:600;}
.rvp-ch-t small{display:block; font-weight:500; color:#6B6458;}
.rvp-mk{display:flex; flex-wrap:wrap; gap:6px; justify-content:flex-end;}
.rvp-mk span{font-size:12.5px; padding:4px 8px; border-radius:8px; background:#EFE6D0;}
.rvp-mk .is-good{background:#DDF3E5; color:#1E6B3D;}
.rvp-mk .is-bad{background:#FBE1DF; color:#9B2C25;}
.rvp-mk q{display:block; font-style:normal; quotes:none; margin-top:2px; color:#4A453A;}
.rvp-filter{display:flex; flex-wrap:wrap; gap:6px; margin-bottom:10px;}
.rvp-filter a{font-size:13px; font-weight:600; padding:6px 11px; border-radius:999px; background:#F1EEE6; color:#4A453A; text-decoration:none;}
.rvp-filter a.on{background:#2B2112; color:#FFE7B0;}
.rvp-fb{border-top:1px solid rgba(23,20,15,.07); padding:8px 0;}
.rvp-fb:first-of-type{border-top:0;}
.rvp-fb > label{display:grid; grid-template-columns:22px 1fr; gap:4px 10px; align-items:start; cursor:pointer;}
.rvp-fb input[type=checkbox]{width:18px; height:18px; margin-top:2px; accent-color:#C88A12;}
.rvp-fb-top{display:flex; flex-wrap:wrap; gap:4px 8px; align-items:center; font-size:12.5px; color:#6B6458;}
.rvp-fb-top b{color:#17140F;}
.rvp-fb-body{grid-column:2; font-size:14px; line-height:1.45; color:#17140F; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; white-space:pre-wrap;}
.rvp-fb.is-open .rvp-fb-body{-webkit-line-clamp:unset; display:block;}
.rvp-fb-more{grid-column:2; justify-self:start; border:0; background:none; padding:0; color:#8A5A00; font-size:12.5px; font-weight:600; cursor:pointer;}
.rvp-fb-note{display:none; margin:6px 0 0 32px;}
.rvp-fb:has(input[type=checkbox]:checked) .rvp-fb-note{display:block;}
.rvp-fb:has(input[type=checkbox]:checked){background:rgba(224,168,46,.08); border-radius:10px;}
.rvp-fb-note input{width:100%;}
.rvp-run{position:sticky; bottom:10px; z-index:5; display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-top:12px; padding:10px; border-radius:14px; background:#2B2112; color:#FFE7B0; box-shadow:0 10px 30px rgba(0,0,0,.2);}
.rvp-run textarea{flex:1 1 260px; min-height:42px; max-height:140px; border-radius:10px; border:0; padding:9px 11px; font:500 14px/1.4 'Inter', sans-serif;}
.rvp-run .btn{white-space:nowrap;}
.rvp-run .rvp-n{font-weight:700; font-size:13px;}
.rvp-close{display:inline-block; position:relative;}
.rvp-close summary{list-style:none; cursor:pointer;}
.rvp-close summary::-webkit-details-marker{display:none;}
.rvp-close form{position:absolute; right:0; top:calc(100% + 4px); z-index:6; display:flex; gap:6px; padding:8px; width:min(420px, 80vw); border-radius:12px; background:#FFFDF7; border:1px solid rgba(23,20,15,.12); box-shadow:0 12px 30px rgba(23,20,15,.18);}
.rvp-close form input{flex:1; min-width:0;}
.rvp-q{font-size:11.5px; font-weight:700; padding:2px 7px; border-radius:6px; background:#2B2112; color:#FFE7B0;}
body.theme-dark .rvp-job .rvp-res, body.theme-dark .rvp-filter a{background:rgba(255,255,255,.07); color:#ECE8F4;}
body.theme-dark .rvp-fb-body, body.theme-dark .rvp-fb-top b{color:#ECE8F4;}
@media (max-width:560px){ .rvp-ch{grid-template-columns:1fr;} .rvp-mk{justify-content:flex-start;} .rvp-job{grid-template-columns:1fr auto;} .rvp-job .rvp-res{grid-column:1 / -1;} }
</style>
<div class="page-head rvp-head">
  <h1 class="styled">🔎 <?= te('Peržiūra') ?></h1>
  <p class="muted"><?= te('Pažymėkite, ką taisyti, ir paspauskite „▶ Paleisti taisymą“. Agentas debesyje tikrina kas valandą (7–23 val.). Be prašymų jis pats taiso aiškius atsiliepimus, kad pataisymai būtų paruošti.') ?></p>
  <ol class="rvp-steps">
    <li>1. <?= te('Pažymėti ir paleisti') ?></li><li>2. <?= te('Agentas pataiso') ?></li><li>3. <?= te('⬆ Įkelti į test') ?></li>
    <li>4. <?= te('Auksinės žymos: ✓ / ✗ + komentaras → Pateikti') ?></li><li>5. <?= te('Viskas gerai → į main') ?></li>
  </ol>
</div>
<?php if (!$ready): ?>
  <div class="flash flash-err"><?= te('Duomenų bazė neatnaujinta - importuokite') ?> <code>db/060_perziura.sql</code>.</div>
<?php else: ?>
<div class="rvp-grid">

<section class="panel card" id="eile" aria-labelledby="rvp-jobs-h">
  <h2 id="rvp-jobs-h" style="margin-top:0;"><?= te('Darbų eilė') ?></h2>
  <?php if (!$jobs): ?>
    <p class="muted" style="margin:0;"><?= te('Darbų dar nebuvo. Be prašymų agentas kas valandą pats taiso aiškius atsiliepimus.') ?></p>
  <?php endif; ?>
  <?php foreach ($jobs as $j): $items = (array) json_decode((string) $j['items'], true); [$sl, $sc] = REVIEW_STATUS[$j['status']] ?? [$j['status'], '']; ?>
    <div class="rvp-job" data-job="<?= (int) $j['id'] ?>">
      <span class="rvp-k">#<?= (int) $j['id'] ?> <?= e(t(REVIEW_KINDS[$j['kind']] ?? $j['kind'])) ?></span>
      <span class="rvp-meta">
        <?php if ($j['kind'] === 'fix' && $items): ?><?= e(t('Atsiliepimai:')) ?> <?= e(implode(', ', array_map(function ($i) { return '#' . (int) $i['id']; }, $items))) ?> · <?php endif; ?>
        <?php if ($j['kind'] === 'review'): $bad = count(array_filter($items, function ($i) { return ($i['mark'] ?? '') === 'bad'; })); ?><?= $bad ? e(t('Blogai: {n}', ['n' => $bad])) : e(t('Visi geri')) ?> · <?php endif; ?>
        <?= e((string) $j['first_name']) ?> <?= e($ago($j['created_at'])) ?>
        <?php if ($j['note']): ?><br><?= e(mb_strimwidth((string) $j['note'], 0, 200, '…')) ?><?php endif; ?>
      </span>
      <span>
        <span class="badge <?= e($sc) ?>"><?= e(t($sl)) ?></span>
        <?php if ($j['status'] === 'queued'): ?>
          <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int) $j['id'] ?>">
            <button class="btn btn-ghost btn-sm" type="submit" title="<?= e(t('Atšaukti šį darbą')) ?>">✕</button></form>
        <?php endif; ?>
        <?php if (in_array($j['status'], ['queued', 'working'], true)): ?>
          <details class="rvp-close"><summary class="btn btn-ghost btn-sm" title="<?= e(t('Jau padaryta - pažymėti ir parašyti atsakymą')) ?>">✓</summary>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="close"><input type="hidden" name="id" value="<?= (int) $j['id'] ?>">
              <input type="text" name="result" maxlength="4000" placeholder="<?= e(t('Kas padaryta')) ?>" aria-label="<?= e(t('Kas padaryta')) ?>">
              <button class="btn btn-primary btn-sm" type="submit"><?= te('✓ Padaryta') ?></button></form></details>
        <?php endif; ?>
      </span>
      <?php if ($j['result']): ?><div class="rvp-res"><?= e((string) $j['result']) ?></div><?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>

<section class="panel card" id="test" aria-labelledby="rvp-test-h">
  <div class="row between" style="flex-wrap:wrap; gap:8px; align-items:center;">
    <h2 id="rvp-test-h" style="margin:0;"><?= te('Pakeitimai test svetainėje') ?><?= $m['version'] !== '' ? ' · v' . e($m['version']) : '' ?></h2>
    <form method="post" style="margin:0;"><?= csrf_field() ?><input type="hidden" name="action" value="deploy">
      <button class="btn btn-primary btn-sm" type="submit" id="rvp-deploy"><?= te('⬆ Įkelti į test') ?></button></form>
  </div>
  <p class="muted" style="margin:6px 0 10px;"><?= te('Test svetainėje kiekvienas pakeistas elementas apvestas auksu. Spauskite □ prie jo: ✓ gerai, ✗ blogai, 💬 - komentaras. Apačioje - „Pateikti“.') ?>
    <?= config('env') === 'live' ? '<br><strong>' . te('Gyvoje svetainėje žymų nėra - atidarykite test svetainę.') . '</strong>' : '' ?></p>
  <?php if (!$m['items']): ?>
    <p class="muted" style="margin:0;"><?= te('Naujų pakeitimų peržiūrai nėra.') ?></p>
  <?php endif; ?>
  <?php foreach ($m['items'] as $it): ?>
    <div class="rvp-ch">
      <div class="rvp-ch-t"><a href="<?= e(review_item_href($it)) ?>"><?= e($it['title']) ?></a><?php if ($it['note']): ?><small><?= e($it['note']) ?></small><?php endif; ?></div>
      <div class="rvp-mk">
        <?php foreach ($byItem[$it['id']] ?? [] as $r): if (!$r['mark'] && !$r['comment']) { continue; } ?>
          <span class="<?= $r['mark'] ? 'is-' . e($r['mark']) : '' ?>"><?= $r['mark'] === 'good' ? '✓' : ($r['mark'] === 'bad' ? '✗' : '💬') ?> <?= e((string) $r['first_name']) ?><?= $r['job_id'] === null ? ' · ' . e(t('nepateikta')) : '' ?>
            <?php if ($r['comment']): ?><q><?= e((string) $r['comment']) ?></q><?php endif; ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</section>

<section class="panel card" id="taisyti" aria-labelledby="rvp-fix-h">
  <h2 id="rvp-fix-h" style="margin-top:0;"><?= te('Ką taisyti') ?> <span class="muted">(<?= count($rows) ?>)</span></h2>
  <nav class="rvp-filter" aria-label="<?= e(t('Būsena')) ?>">
    <?php foreach (['' => t('Visi atviri'), 'new' => t('Nauji'), 'planned' => t('Bus pataisyta'), 'question' => t('Reikia atsakymo')] as $k => $l): ?>
      <a href="<?= e(url('admin/perziura.php' . ($k !== '' ? '?b=' . $k : '') . '#taisyti')) ?>" class="<?= $st === $k ? 'on' : '' ?>"><?= e($l) ?></a>
    <?php endforeach; ?>
  </nav>
  <form method="post" id="rvp-fix-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="fix">
    <?php if (!$rows): ?><p class="muted" style="margin:0;"><?= te('Neišspręstų atsiliepimų nėra. 🎉') ?></p><?php endif; ?>
    <?php foreach ($rows as $f): $ar = feedback_row_area($f); $ctx = json_decode((string) ($f['context'] ?? ''), true); $imgs = count(feedback_images((int) $f['id'])); $fid = (int) $f['id']; ?>
      <div class="rvp-fb" id="f<?= $fid ?>">
        <label>
          <input type="checkbox" name="ids[]" value="<?= $fid ?>" aria-label="<?= e(t('Taisyti #{n}', ['n' => $fid])) ?>">
          <span class="rvp-fb-top"><b>#<?= $fid ?></b> <?= feedback_status_badge($f['status']) ?>
            <?php if (!empty($ctx['pick'])): ?><span class="rvp-q" title="<?= e((string) ($ctx['selector'] ?? '')) ?>">🎯 <?= te('elementas') ?></span><?php endif; ?>
            <?php if (isset($queuedFb[$fid])): ?><span class="rvp-q"><?= e(t('eilėje #{n}', ['n' => $queuedFb[$fid]])) ?></span><?php endif; ?>
            <span><?= e($ar['gl'] . ' › ' . $ar['sl']) ?></span> · <span><?= e(trim((string) $f['first_name']) ?: (string) ($f['name'] ?: t('lankytojas'))) ?></span> · <span><?= e($ago($f['created_at'])) ?></span>
            <?php if ($imgs): ?> · <span>📎 <?= $imgs ?></span><?php endif; ?>
            <?php if (($href = feedback_page_href($f)) !== ''): ?> · <a href="<?= e($href) ?>" target="_blank" rel="noopener"><?= te('vieta') ?> ↗</a><?php endif; ?>
            · <a href="<?= e(url('admin/atsiliepimai.php?b=visi#f' . $fid)) ?>"><?= te('atsakyti') ?></a></span>
          <span class="rvp-fb-body"><?= e((string) $f['body']) ?><?= !empty($f['answer']) ? "\n↳ " . e((string) $f['answer']) : '' ?></span>
        </label>
        <?php if (mb_strlen((string) $f['body']) > 140 || substr_count((string) $f['body'], "\n") > 1): ?>
          <button type="button" class="rvp-fb-more" onclick="this.closest('.rvp-fb').classList.toggle('is-open')"><?= te('Rodyti visą') ?></button>
        <?php endif; ?>
        <div class="rvp-fb-note"><input type="text" name="note[<?= $fid ?>]" maxlength="2000" placeholder="<?= e(t('Komentaras agentui (nebūtina): kaip taisyti')) ?>" aria-label="<?= e(t('Komentaras agentui #{n}', ['n' => $fid])) ?>"></div>
      </div>
    <?php endforeach; ?>
    <div class="rvp-run">
      <span class="rvp-n" id="rvp-n" data-tpl="<?= e(t('Pažymėta: {n}')) ?>"><?= e(t('Pažymėta: {n}', ['n' => 0])) ?></span>
      <textarea name="note_all" maxlength="4000" placeholder="<?= e(t('Bendras komentaras ar nauja užduotis (nebūtina)')) ?>" aria-label="<?= e(t('Bendras komentaras')) ?>"></textarea>
      <button class="btn btn-primary" type="submit" id="rvp-run"><?= te('▶ Paleisti taisymą') ?></button>
    </div>
  </form>
</section>

</div>
<script>
(function () {
  var f = document.getElementById('rvp-fix-form'), n = document.getElementById('rvp-n');
  if (!f || !n) return;
  f.addEventListener('change', function () {
    n.textContent = n.getAttribute('data-tpl').replace('{n}', f.querySelectorAll('input[name="ids[]"]:checked').length);
  });
})();
</script>
<?php endif; ?>
<?php
page_end();
