<?php
// Atsiliepimai (tik adminas): ką parašė nariai, treneriai ir lankytojai per mygtuką „💡 Atsiliepimas“.
// Būsena (naujas → bus pataisyta → pataisyta / nedarysime) ir atsakymas - autorius mato savo atsiliepimų sąraše ir gauna pranešimą.
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_admin();

$filter = isset(FEEDBACK_STATUS[get('b')]) || get('b') === 'visi' ? get('b') : 'atviri';

if (is_post()) {
    csrf_check();
    $id = (int) post('id');
    $f = $id ? q_one('SELECT * FROM feedback WHERE id = ?', [$id]) : null;
    switch (post('action')) {
        case 'save':
            if ($f) {
                $status = isset(FEEDBACK_STATUS[post('status')]) ? post('status') : $f['status'];
                $reply = trim(str_replace("\r\n", "\n", (string) post('reply')));
                q('UPDATE feedback SET status = ?, reply = ?, updated_at = NOW() WHERE id = ?', [$status, $reply !== '' ? $reply : null, $id]);
                if ($f['account_id'] && (int) $f['account_id'] !== (int) $me['id'] && ($status !== $f['status'] || $reply !== (string) $f['reply'])) {
                    notify_account((int) $f['account_id'], 'feedback', t('Jūsų atsiliepimas:') . ' ' . mb_strtolower(t(FEEDBACK_STATUS[$status][0])),
                        $reply !== '' ? $reply : mb_strimwidth($f['body'], 0, 200, '…'), ltrim((string) $f['page'], '/') ?: null, null, $id, false);
                }
                flash('ok', t('Išsaugota.'));
            }
            break;
        case 'delete':
            if ($f) {
                q('DELETE FROM feedback WHERE id = ?', [$id]);
                flash('ok', t('Atsiliepimas ištrintas.'));
            }
            break;
        case 'mode':
            if (isset(FEEDBACK_SHOW[post('mode')])) {
                set_site_setting('feedback_button', post('mode'));
                flash('ok', t('Išsaugota.'));
            }
            break;
    }
    redirect('admin/atsiliepimai.php?b=' . $filter . ($id && post('action') === 'save' ? '#f' . $id : ''));
}

$ready = feedback_ready();
$counts = $ready ? q('SELECT status, COUNT(*) FROM feedback GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR) : [];
$where = ['atviri' => 'f.status IN ("new", "planned")', 'visi' => '1'][$filter] ?? 'f.status = ' . db()->quote($filter);
$rows = $ready ? q_all("SELECT f.*, a.first_name, a.last_name, a.email, a.role FROM feedback f LEFT JOIN accounts a ON a.id = f.account_id
                        WHERE $where ORDER BY f.status = 'new' DESC, f.created_at DESC LIMIT 300") : [];
$open = $ready ? q_all("SELECT f.*, a.first_name, a.last_name FROM feedback f LEFT JOIN accounts a ON a.id = f.account_id WHERE f.status IN ('new', 'planned') ORDER BY f.created_at") : [];

/** Kas parašė */
function fb_author(array $f): string
{
    if ($f['account_id'] && $f['first_name'] !== null) {
        return $f['first_name'] . ' ' . $f['last_name'];
    }
    return $f['name'] ? $f['name'] . ' (' . t('lankytojas') . ')' : t('Lankytojas');
}

page_start(t('Atsiliepimai'), ['admin' => true]);
?>
<div class="page-head row between" style="flex-wrap:wrap; gap:10px; align-items:flex-end;">
  <div>
    <h1 class="styled"><?= te('Atsiliepimai') ?></h1>
    <p class="muted" style="margin:0;"><?= te('Ką pastebėjo nariai, treneriai ir lankytojai (mygtukas „💡 Atsiliepimas“ apačioje kairėje). Pakeitus būseną ar atsakius, autorius gauna pranešimą.') ?></p>
  </div>
  <?php if ($open): ?>
    <button type="button" class="btn btn-ghost" id="fbCopy"><?= te('📋 Kopijuoti neišspręstus') ?> (<?= count($open) ?>)</button>
  <?php endif; ?>
</div>
<?php if (!$ready): ?>
  <div class="flash flash-err"><?= te('Duomenų bazė neatnaujinta - importuokite') ?> <code>db/025_varzybos_atsiliepimai.sql</code>.</div>
<?php endif; ?>

<?php
$tabs = ['atviri' => [t('Neišspręsti'), (int) ($counts['new'] ?? 0) + (int) ($counts['planned'] ?? 0)]];
foreach (FEEDBACK_STATUS as $k => $s) {
    $tabs[$k] = [t($s[0]), (int) ($counts[$k] ?? 0)];
}
$tabs['visi'] = [t('Visi'), array_sum(array_map('intval', $counts))];
?>
<nav class="chip-tabs" aria-label="<?= e(t('Filtras')) ?>">
  <?php foreach ($tabs as $key => [$label, $n]): ?>
    <a class="chip-tab<?= $filter === $key ? ' active' : '' ?>" href="<?= url('admin/atsiliepimai.php?b=' . $key) ?>"><?= e($label) ?> <span class="chip-n"><?= $n ?></span></a>
  <?php endforeach; ?>
</nav>

<?php if (!$rows): ?>
  <div class="panel card"><p class="muted" style="margin:0;"><?= $filter === 'atviri' ? te('Neišspręstų atsiliepimų nėra. 🎉') : te('Atsiliepimų nėra.') ?></p></div>
<?php endif; ?>

<div class="fb-admin-list">
<?php foreach ($rows as $f): ?>
  <form method="post" class="panel card fb-card<?= $f['status'] === 'new' ? ' is-new' : '' ?>" id="f<?= (int) $f['id'] ?>" action="<?= url('admin/atsiliepimai.php?b=' . $filter) ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
    <div class="fb-card-top">
      <div>
        <strong><?= e(fb_author($f)) ?></strong>
        <?php if ($f['role'] && $f['role'] !== 'member'): ?><span class="badge badge-tag"><?= e(t($f['role'] === 'admin' ? 'adminas' : 'treneris')) ?></span><?php endif; ?>
        <span class="muted small"> · <?= e(fmt_date(substr($f['created_at'], 0, 10))) ?> <?= e(substr($f['created_at'], 11, 5)) ?></span>
        <?php if ($f['page']): ?><span class="small"> · <a href="<?= url(ltrim($f['page'], '/')) ?>" target="_blank" rel="noopener"><?= e(mb_strimwidth($f['page'], 0, 60, '…')) ?> ↗</a></span><?php endif; ?>
      </div>
      <?= feedback_status_badge($f['status']) ?>
    </div>
    <div class="fb-card-body"><?= nl2br(e($f['body'])) ?></div>
    <div class="fb-card-edit">
      <label class="fb-status"><span class="small muted"><?= te('Būsena') ?></span>
        <select name="status"><?php foreach (FEEDBACK_STATUS as $k => $s): ?><option value="<?= $k ?>"<?= $k === $f['status'] ? ' selected' : '' ?>><?= e(t($s[0])) ?></option><?php endforeach; ?></select>
      </label>
      <label class="fb-reply-field"><span class="small muted"><?= $f['account_id'] ? te('Atsakymas (mato autorius)') : te('Pastaba sau (lankytojas atsakymo nemato)') ?></span>
        <textarea name="reply" rows="2" maxlength="2000"><?= e((string) $f['reply']) ?></textarea>
      </label>
      <div class="fb-card-btns">
        <button class="btn btn-primary btn-sm" type="submit" name="action" value="save"><?= te('Išsaugoti') ?></button>
        <button class="btn btn-ghost btn-sm" type="submit" name="action" value="delete" onclick="return confirm('<?= e(t('Ištrinti šį atsiliepimą?')) ?>')"><?= te('Ištrinti') ?></button>
      </div>
    </div>
  </form>
<?php endforeach; ?>
</div>

<form method="post" class="panel card fb-mode">
  <?= csrf_field() ?><input type="hidden" name="action" value="mode">
  <div class="kicker"><?= te('Kam rodomas mygtukas „Atsiliepimas“') ?></div>
  <div class="row" style="gap:8px; flex-wrap:wrap; align-items:center;">
    <select name="mode"><?php foreach (FEEDBACK_SHOW as $k => $label): ?><option value="<?= $k ?>"<?= $k === feedback_mode() ? ' selected' : '' ?>><?= e(t($label)) ?></option><?php endforeach; ?></select>
    <button class="btn btn-ghost btn-sm" type="submit"><?= te('Išsaugoti') ?></button>
  </div>
  <p class="small muted" style="margin:8px 0 0;"><?= te('Test svetainėje numatyta - visiems, oficialioje - tik prisijungusiems.') ?></p>
</form>

<?php if ($open): ?>
<textarea id="fbCopyText" hidden><?= e("Atsiliepimai iš karateka.lt (" . count($open) . "):\n\n" . implode("\n\n", array_map(function ($f) {
    return '#' . $f['id'] . ' · ' . substr($f['created_at'], 0, 16) . ' · ' . fb_author($f) . ($f['page'] ? ' · ' . $f['page'] : '') . ($f['status'] === 'planned' ? ' · [bus pataisyta]' : '') . "\n" . $f['body'];
}, $open))) ?></textarea>
<script>
  document.getElementById('fbCopy').addEventListener('click', function () {
    var t = document.getElementById('fbCopyText'), b = this;
    (navigator.clipboard ? navigator.clipboard.writeText(t.value) : Promise.reject()).then(function () { b.textContent = '✓ <?= e(t('Nukopijuota')) ?>'; })
      .catch(function () { t.hidden = false; t.select(); });
  });
</script>
<?php endif; ?>
<?php
page_end();
