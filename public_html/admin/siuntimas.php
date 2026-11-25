<?php
// C8: masinių laiškų siuntimas dalimis (app/mail_outbox.php). Atidarius - siunčiama po kelis, kol neliks; kiekvienam gavėjui - rezultatas.
// Uždarius puslapį neišsiųsti laukia: Pranešimas nariams → „Laiškai“ → „Tęsti“.
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_can('messages');

$b = preg_replace('/[^a-z0-9]/', '', (string) (get('b') ?: post('b')));
if ($b === '' || !outbox_ready() || !(int) q_value('SELECT COUNT(*) FROM mail_outbox WHERE batch = ?', [$b])) {
    not_found();
}
if (is_post()) {
    csrf_check();
    if (post('action') === 'retry') {
        outbox_retry($b);
    }
    $c = outbox_send($b);
    if (post('ajax') === '1') {
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['ok' => true] + $c));
    }
    redirect('admin/siuntimas.php?b=' . $b);
}
$c = outbox_counts($b);
$rows = q_all('SELECT o.to_email, o.status, o.error, o.sent_at, a.first_name, a.last_name FROM mail_outbox o LEFT JOIN accounts a ON a.id = o.account_id WHERE o.batch = ? ORDER BY o.status = "failed" DESC, o.id', [$b]);
$title = (string) q_value('SELECT title FROM mail_outbox WHERE batch = ? LIMIT 1', [$b]);
$label = ['queued' => t('Laukia'), 'sending' => t('Siunčiama'), 'sent' => t('Išsiųsta'), 'failed' => t('Nepavyko')];
page_start(t('Laiškų siuntimas'), ['admin' => true]);
?>
<div class="page-head">
  <h1 class="styled"><?= te('Laiškų siuntimas') ?></h1>
  <p><?= e($title) ?></p>
</div>
<div class="panel card ob-box" id="ob" data-b="<?= e($b) ?>" data-left="<?= (int) $c['left'] ?>">
  <div class="ob-bar"><span style="width:<?= $c['total'] ? round(($c['sent'] + $c['failed']) * 100 / $c['total']) : 0 ?>%"></span></div>
  <p class="ob-sum"><b id="ob-sent"><?= (int) $c['sent'] ?></b> / <?= (int) $c['total'] ?> <?= te('išsiųsta') ?>
    <span id="ob-failed"<?= $c['failed'] ? '' : ' hidden' ?>> · <b class="warn-t"><?= (int) $c['failed'] ?></b> <?= te('nepavyko') ?></span>
    <span id="ob-left"<?= $c['left'] ? '' : ' hidden' ?>> · <?= te('siunčiama… neuždarykite puslapio') ?></span></p>
  <form method="post" class="row" style="gap:8px;">
    <?= csrf_field() ?><input type="hidden" name="b" value="<?= e($b) ?>">
    <?php if ($c['left']): ?><button class="btn btn-primary btn-sm" name="action" value="send" id="ob-go"><?= te('Siųsti toliau') ?></button><?php endif; ?>
    <?php if ($c['failed']): ?><button class="btn btn-sm" name="action" value="retry"><?= te('Pakartoti nepavykusius') ?></button><?php endif; ?>
    <a class="btn btn-ghost btn-sm" href="<?= url('admin/pranesimai.php') ?>"><?= te('← Pranešimas nariams') ?></a>
  </form>
  <ul class="ob-list">
    <?php foreach ($rows as $r): ?>
      <li><span class="ob-who"><b><?= e(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))) ?></b> <span class="small muted"><?= e($r['to_email']) ?></span></span>
        <span class="badge<?= $r['status'] === 'sent' ? ' badge-ok' : ($r['status'] === 'failed' ? ' badge-warn' : '') ?>"><?= e($label[$r['status']] ?? $r['status']) ?></span><?= $r['error'] && $r['error'] !== 'Nepavyko išsiųsti' ? ' <span class="small muted">' . e(t($r['error'])) . '</span>' : '' ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<script>
(function () {
  // Siunčiama po kelis, kol neliks; tada puslapis atsinaujina (matosi rezultatas kiekvienam)
  var box = document.getElementById('ob'); if (!box || !+box.dataset.left) return;
  var csrf = box.querySelector('input[name=_csrf]').value, go = document.getElementById('ob-go');
  if (go) go.hidden = true;
  function step() {
    var f = new FormData(); f.append('_csrf', csrf); f.append('b', box.dataset.b); f.append('ajax', '1'); f.append('action', 'send');
    fetch(location.pathname, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      document.getElementById('ob-sent').textContent = d.sent;
      box.querySelector('.ob-bar span').style.width = (d.total ? Math.round((d.sent + d.failed) * 100 / d.total) : 0) + '%';
      if (d.left > 0) setTimeout(step, 300); else location.reload();
    }).catch(function () { if (go) go.hidden = false; });
  }
  step();
})();
</script>
<?php page_end();
