<?php
// Kodas iš administratoriaus (app/device_codes.php): šiame įrenginyje ribotą laiką dirbti administratoriaus teisėmis.
// Pvz. sekretoriato planšetė varžybose: adminas savo telefone sukuria kodą, čia jis įvedamas.
require __DIR__ . '/app/bootstrap.php';

$askedFor = (string) (get('r') ?: post('r')) !== '';
$return = $askedFor ? safe_return(get('r') ?: post('r')) : url('admin/');
$error = '';
$code = strtoupper(trim((string) (get('c') ?: post('code'))));   // nuorodoje gali būti kodas - įrašomas į laukelį, bet prisijungiama tik paspaudus

if (is_post()) {
    csrf_check();
    $res = device_code_redeem((string) post('code'));
    if ($res === 'ok') {
        redirect($return);
    }
    $error = $res === 'throttled' ? t('Per daug bandymų. Pabandykite po 15 minučių.') : t('Kodas neteisingas, jau panaudotas arba pasibaigęs. Paprašykite naujo.');
}
$me = current_account();

page_start(t('Kodas iš administratoriaus'), ['narrow' => true, 'noindex' => true]);
?>
<div class="panel card dc-enter">
  <h1 class="styled"><?= te('Kodas iš administratoriaus') ?></h1>
  <p class="muted"><?= te('Administratorius savo paskyroje sukuria kodą: Valdymas → Nustatymai → Kodas kitam įrenginiui. Įveskite jį čia - šis įrenginys ribotą laiką galės tvarkyti svetainę ir varžybas.') ?></p>
  <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
  <?php if ($me): ?><p class="small muted"><?= te('Dabar prisijungta kaip') ?> <b><?= e($me['first_name'] . ' ' . $me['last_name']) ?></b>. <?= te('Įvedus kodą, šis prisijungimas bus pakeistas.') ?></p><?php endif; ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <?php if ($askedFor): ?><input type="hidden" name="r" value="<?= e($return) ?>"><?php endif; ?>
    <label><?= te('Kodas') ?>
      <input type="text" name="code" class="dc-input" value="<?= e($code) ?>" placeholder="XXXX-XXXX" maxlength="12" autocomplete="off" autocapitalize="characters" spellcheck="false" required autofocus>
    </label>
    <button class="btn btn-primary btn-block" type="submit"><?= icon('check') ?><span><?= te('Prisijungti šiame įrenginyje') ?></span></button>
  </form>
  <p class="small muted" style="margin-top:12px;"><?= te('Kodas tinka vieną kartą ir tik 10 minučių. Administratorius šį įrenginį gali bet kada atjungti.') ?></p>
</div>
<?php page_end();
