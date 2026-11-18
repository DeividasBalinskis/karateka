<?php
// LTKF svetainės peržiūra federacijos nariams: nuoroda, su kuria be paskyros galima palikti atsiliepimus LTKF svetainėje.
// Su ja žmogus nemato nuorodos į karateka.lt ir negali atidaryti karateka.lt valdymo. Tik adminui.
require dirname(__DIR__) . '/app/bootstrap.php';
require_admin();

$editors = array_values(array_filter((array) json_decode((string) site_setting('ltkf_editors', '[]'), true), 'is_array'));
if (is_post() && in_array(post('action'), ['ed_add', 'ed_toggle', 'ed_del'], true)) {
    csrf_check();
    if (post('action') === 'ed_add' && trim(post('name')) !== '') {
        $editors[] = ['t' => bin2hex(random_bytes(12)), 'n' => mb_substr(trim(post('name')), 0, 80), 'on' => 1, 'c' => date('Y-m-d')];
        flash('ok', t('Redaktoriaus nuoroda sukurta - nukopijuokite ir nusiųskite.'));
    }
    foreach ($editors as $i => $ed) {
        if ($ed['t'] === post('t')) {
            if (post('action') === 'ed_toggle') {
                $editors[$i]['on'] = empty($ed['on']) ? 1 : 0;
            }
            if (post('action') === 'ed_del') {
                unset($editors[$i]);
            }
        }
    }
    set_site_setting('ltkf_editors', json_encode(array_values($editors), JSON_UNESCAPED_UNICODE));
    redirect('admin/ltkf-perziura.php#redaktoriai');
}
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
  <h1 class="styled"><?= te('LTKF svetainė: peržiūra ir redaktoriai') ?></h1>
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

<div class="panel card form" id="redaktoriai">
  <h2><?= te('✏️ Redaktoriai') ?></h2>
  <p class="muted"><?= te('Žmonės, kurie gali patys keisti LTKF svetainę: tekstus, nuotraukas, failus, meniu, naujus puslapius. Kiekvienam - asmeninė nuoroda (paskyros nereikia, tik vardas). Išjungus nuorodą, redaguoti nebegalima.') ?></p>
  <p><a class="btn" href="<?= url('ltkf/index.php?redaguoti=1') ?>"><?= te('✏️ Redaguoti pačiam') ?></a> <a class="btn btn-ghost" href="<?= url('ltkf/failai.php') ?>"><?= te('📥 Failų dėžė') ?></a></p>
  <?php foreach ($editors as $ed): $elink = abs_url('ltkf-redaktorius.php?k=' . $ed['t']); ?>
    <div class="row ltkf-ed<?= empty($ed['on']) ? ' muted' : '' ?>" style="gap:8px;flex-wrap:wrap;align-items:center;margin:8px 0;">
      <b style="min-width:140px;"><?= e($ed['n']) ?></b>
      <input type="text" readonly value="<?= e($elink) ?>" onclick="this.select()" style="flex:1;min-width:240px;">
      <button type="button" class="btn" onclick="navigator.clipboard && navigator.clipboard.writeText(<?= e(json_encode($elink)) ?>).then(() => { this.textContent = '✓'; })"><?= te('Kopijuoti') ?></button>
      <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="t" value="<?= e($ed['t']) ?>">
        <button class="btn btn-ghost" name="action" value="ed_toggle" type="submit"><?= empty($ed['on']) ? te('Įjungti') : te('Išjungti') ?></button>
        <button class="btn btn-ghost" name="action" value="ed_del" type="submit" onclick="return confirm('<?= e(t('Ištrinti šią nuorodą?')) ?>')">🗑</button></form>
    </div>
  <?php endforeach; ?>
  <form method="post" class="row" style="gap:8px;flex-wrap:wrap;margin-top:10px;"><?= csrf_field() ?>
    <input type="text" name="name" placeholder="<?= te('Vardas, pvz. Jonas iš Sanrei') ?>" required style="flex:1;min-width:220px;">
    <button class="btn btn-primary" name="action" value="ed_add" type="submit"><?= te('+ Nauja redaktoriaus nuoroda') ?></button></form>
</div>
<?php
page_end();
