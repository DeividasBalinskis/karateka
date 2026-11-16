<?php
// LTKF svetainės peržiūra federacijos nariams: nuoroda, su kuria be paskyros galima palikti atsiliepimus LTKF svetainėje.
// Su ja žmogus nemato nuorodos į karateka.lt ir negali atidaryti karateka.lt valdymo. Tik adminui.
require dirname(__DIR__) . '/app/bootstrap.php';
require_admin();

if (is_post()) {
    csrf_check();
    set_site_setting('ltkf_review_token', post('action') === 'on' ? bin2hex(random_bytes(12)) : '');
    flash('ok', post('action') === 'on' ? t('Peržiūros nuoroda sukurta - nukopijuokite ir nusiųskite.') : t('Peržiūros nuoroda išjungta.'));
    redirect('admin/ltkf-perziura.php');
}
$tok = ltkf_review_token();
$link = $tok !== '' ? abs_url('ltkf-perziura.php?k=' . $tok) : '';

page_start(t('LTKF peržiūra'), ['admin' => true]);
?>
<div class="page-head">
  <h1 class="styled"><?= te('LTKF svetainės peržiūra') ?></h1>
  <p><?= te('Nuoroda federacijos nariams: atsidarę jie mato LTKF svetainę ir gali palikti atsiliepimus (💡, galima įklijuoti nuotrauką). Nuorodos į karateka.lt nemato - ją mato tik adminas.') ?></p>
</div>
<div class="panel card form">
  <?php if ($link): ?>
    <label><?= te('Nuoroda') ?>
      <div class="row" style="gap:8px;flex-wrap:wrap;"><input type="text" readonly value="<?= e($link) ?>" onclick="this.select()" style="flex:1;min-width:260px;" id="ltkfLink">
        <button type="button" class="btn" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('ltkfLink').value).then(() => { this.textContent = '✓'; })"><?= te('Kopijuoti') ?></button></div></label>
    <form method="post" class="row" style="gap:8px;"><?= csrf_field() ?>
      <button class="btn" name="action" value="on" type="submit" onclick="return confirm('<?= e(t('Sukurti naują nuorodą? Sena nebeveiks.')) ?>')"><?= te('↻ Nauja nuoroda') ?></button>
      <button class="btn btn-danger" name="action" value="off" type="submit"><?= te('Išjungti') ?></button></form>
  <?php else: ?>
    <form method="post"><?= csrf_field() ?><button class="btn btn-primary" name="action" value="on" type="submit"><?= te('Sukurti peržiūros nuorodą') ?></button></form>
  <?php endif; ?>
  <p class="hint"><a href="<?= url('ltkf/index.php') ?>"><?= te('Atidaryti LTKF svetainę →') ?></a> · <a href="<?= url('admin/atsiliepimai.php') ?>"><?= te('Atsiliepimai') ?></a></p>
</div>
<?php
page_end();
