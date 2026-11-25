<?php
// Kodas kitam įrenginiui (app/device_codes.php): tik tikras administratorius. Kodas parodomas vieną kartą.
// Čia pat - kurie įrenginiai dabar prisijungę su kodu (atjungti) ir žurnalas.
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_can('device_codes');
if (!device_codes_ready()) {
    flash('err', t('Pirmiausia atnaujinkite duomenų bazę (db/047).'));
    redirect('admin/nustatymai.php');
}

$newCode = '';
if (is_post()) {
    csrf_check();
    if (post('action') === 'create') {
        $newCode = device_code_create((int) $me['id'], (string) post('label'), (int) post('hours'));
        $newLabel = trim((string) post('label'));
        $newHours = (int) post('hours');
    } elseif (post('action') === 'revoke') {
        device_code_revoke((int) post('id'), (int) $me['id']);
        flash('ok', t('Įrenginys atjungtas - kitą kartą atidarius puslapį jis bus atjungtas.'));
        redirect('admin/kitas-irenginys.php');
    }
}
$log = device_code_log();
$failed = device_code_failed_24h();
$enterUrl = abs_url('irenginys.php');

page_start(t('Kodas kitam įrenginiui'), ['admin' => true]);
?>
<div class="page-head"><h1 class="styled"><?= te('Kodas kitam įrenginiui') ?></h1></div>
<p class="muted dc-lead"><?= te('Kai reikia tvarkyti svetainę ar varžybas kitame įrenginyje (pvz. sekretoriato planšetėje), neįvedant ten savo slaptažodžio. Kodą įveskite tame įrenginyje - jis ribotą laiką dirbs jūsų teisėmis.') ?></p>

<?php if ($newCode !== ''): ?>
  <section class="panel card dc-new" aria-live="polite">
    <div class="kicker"><?= te('Naujas kodas') ?><?= $newLabel !== '' ? ' · ' . e($newLabel) : '' ?></div>
    <div class="dc-code" id="dcCode"><?= e($newCode) ?></div>
    <p><?= te('Kitame įrenginyje atidarykite') ?> <b><?= e(preg_replace('#^https?://#', '', $enterUrl)) ?></b> <?= te('(arba Prisijungti → „Turiu kodą iš administratoriaus“) ir įveskite kodą.') ?></p>
    <p class="small muted"><?= sprintf(e(t('Kodą įvesti galima %d min., tik vieną kartą. Įrenginys veiks %s, kol jo neatjungsite.')), DEVICE_CODE_MINUTES, e(t(DEVICE_CODE_HOURS[$newHours] ?? DEVICE_CODE_HOURS[12]))) ?></p>
    <div class="dc-acts">
      <button type="button" class="btn btn-ghost btn-sm" data-copy="dcCode"><?= icon('copy') ?><span><?= te('Kopijuoti kodą') ?></span></button>
      <a class="btn btn-ghost btn-sm" href="<?= url('admin/kitas-irenginys.php') ?>"><?= icon('check') ?><span><?= te('Baigta') ?></span></a>
    </div>
  </section>
<?php else: ?>
  <section class="panel card">
    <div class="kicker"><?= te('Sukurti kodą') ?></div>
    <form method="post" class="form dc-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="create">
      <label><?= te('Kuriam įrenginiui (nebūtina)') ?>
        <input type="text" name="label" maxlength="80" placeholder="<?= e(t('pvz. Tatami 2 planšetė')) ?>">
      </label>
      <fieldset class="dc-hours"><legend><?= te('Kiek laiko veiks') ?></legend>
        <?php foreach (DEVICE_CODE_HOURS as $h => $lbl): ?>
          <label class="chip-radio"><input type="radio" name="hours" value="<?= $h ?>"<?= $h === 12 ? ' checked' : '' ?>><span><?= e(t($lbl)) ?></span></label>
        <?php endforeach; ?>
      </fieldset>
      <button class="btn btn-primary" type="submit"><?= icon('plus') ?><span><?= te('Sukurti kodą') ?></span></button>
    </form>
  </section>
<?php endif; ?>

<section class="panel card">
  <div class="kicker"><?= te('Žurnalas (60 dienų)') ?></div>
  <?php if ($failed): ?><p class="small dc-warn">⚠ <?= sprintf(e(t('Per paskutines 24 val. neteisingai įvestas kodas: %d k.')), $failed) ?></p><?php endif; ?>
  <?php if (!$log): ?>
    <p class="muted"><?= te('Kodų dar nebuvo.') ?></p>
  <?php else: ?>
    <div class="dc-log">
      <?php foreach ($log as $r): [$st, $cls] = device_code_status($r); $live = !$r['revoked_at'] && (($r['used_at'] && strtotime((string) $r['session_until']) > time()) || (!$r['used_at'] && strtotime($r['expires_at']) > time())); ?>
        <div class="dc-row">
          <div class="dc-main">
            <b><?= e($r['label'] ?: t('Be pavadinimo')) ?></b>
            <span class="small muted"><?= te('Sukūrė') ?> <?= e($r['first_name'] . ' ' . mb_substr($r['last_name'], 0, 1) . '.') ?> · <?= e(date('m-d H:i', strtotime($r['created_at']))) ?>
              <?php if ($r['used_at']): ?> · <?= te('Panaudotas') ?> <?= e(date('m-d H:i', strtotime($r['used_at']))) ?> · <?= e((string) $r['used_device']) ?><?php endif; ?></span>
          </div>
          <span class="badge <?= e($cls) ?>"><?= e($st) ?></span>
          <?php if ($live): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit"><?= icon('x') ?><span><?= $r['used_at'] ? te('Atjungti') : te('Atšaukti') ?></span></button></form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php page_end();
