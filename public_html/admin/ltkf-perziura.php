<?php
// LTKF svetainės peržiūra federacijos nariams: nuoroda, su kuria be paskyros galima palikti atsiliepimus LTKF svetainėje.
// Su ja žmogus nemato nuorodos į karateka.lt ir negali atidaryti karateka.lt valdymo. Tik adminui.
require dirname(__DIR__) . '/app/bootstrap.php';
require_can('ltkf');

// C3: redaktorių ir peržiūros nuorodos - app/links.php (rūšys 'ltkf_editor' ir 'ltkf_review'); seni įrašai (site_settings) perkelti, netrinami
$editors = link_list('ltkf_editor');
// D6 3 dalis: svetainės būsena (visai svetainei - klausiama prieš išsaugant)
const LTKF_MODES = ['off' => 'Išjungta', 'draft' => 'Juodraštis', 'public' => 'Vieša'];
if (is_post() && post('action') === 'mode' && isset(LTKF_MODES[post('mode')])) {
    csrf_check();
    set_site_setting('ltkf_mode', post('mode'));
    flash('ok', t('LTKF svetainės būsena:') . ' ' . t(LTKF_MODES[post('mode')]));
    redirect('admin/ltkf-perziura.php#busena');
}
$ltkfMode = (string) site_setting('ltkf_mode', '');
if (!isset(LTKF_MODES[$ltkfMode])) {
    $ltkfMode = config('env') === 'live' ? 'off' : 'draft';
}
if (is_post() && post('action') === 'ed_add') {
    csrf_check();
    $name = mb_substr(trim(preg_replace('/\s+/u', ' ', post('name'))), 0, 80);
    if ($name !== '' && link_create('ltkf_editor', null, $name)) {
        flash('ok', t('Redaktoriaus nuoroda sukurta - nukopijuokite ir nusiųskite.'));
    }
    redirect('admin/ltkf-perziura.php#redaktoriai');
}
if (is_post() && in_array(post('action'), ['on', 'off'], true)) {   // seni mygtukai (C3: dabar - bendras blokas)
    csrf_check();
    post('action') === 'on' ? link_create('ltkf_review') : link_off_kind('ltkf_review');
    flash('ok', post('action') === 'on' ? t('Peržiūros nuoroda sukurta - nukopijuokite ir nusiųskite.') : t('Peržiūros nuoroda išjungta.'));
    redirect('admin/ltkf-perziura.php');
}

page_start(t('LTKF peržiūra'), ['admin' => true]);
?>
<div class="page-head">
  <h1 class="styled"><?= te('LTKF svetainė: peržiūra ir redaktoriai') ?></h1>
  <p><?= te('Nuoroda federacijos nariams: atsidarę jie mato LTKF svetainę ir gali palikti atsiliepimus (💡, galima įklijuoti nuotrauką). Nuorodos į karateka.lt nemato - ją mato tik adminas.') ?></p>
</div>
<div class="panel card form" id="busena">
  <div class="kicker"><?= te('Svetainės būsena') ?></div>
  <form method="post" class="ltkf-modes" onsubmit="return confirm(this.dataset.q)" data-q="<?= te('Pakeisti LTKF svetainės būseną visiems lankytojams?') ?>"><?= csrf_field() ?>
    <input type="hidden" name="action" value="mode">
    <?php foreach (['off' => t('niekas nemato, išskyrus jus ir redaktorius'), 'draft' => t('mato redaktoriai ir turintys peržiūros nuorodą (test svetainėje - visi); paieškose nerodoma'), 'public' => t('mato visi, rodoma paieškose; nuoroda karateka.lt apačioje')] as $m => $hint): ?>
      <label class="check"><input type="radio" name="mode" value="<?= $m ?>"<?= $ltkfMode === $m ? ' checked' : '' ?>><span><b><?= te(LTKF_MODES[$m]) ?></b> <span class="hint">- <?= e($hint) ?></span></span></label>
    <?php endforeach; ?>
    <div><button class="btn btn-primary btn-sm" type="submit"><?= te('Išsaugoti būseną') ?></button> <a class="btn btn-ghost btn-sm" href="<?= url('ltkf/') ?>"><?= te('Atidaryti LTKF svetainę →') ?></a></div>
  </form>
</div>
<div class="panel card form">
  <div class="kicker"><?= te('Peržiūros nuoroda') ?></div>
  <?= link_box('ltkf_review', null, ['id' => 'ltkfLink', 'create' => t('Sukurti peržiūros nuorodą'), 'back' => '/admin/ltkf-perziura.php']) ?>
  <p class="hint"><a href="<?= url('ltkf/index.php') ?>"><?= te('Atidaryti LTKF svetainę →') ?></a> · <a href="<?= url('admin/atsiliepimai.php') ?>"><?= te('Atsiliepimai') ?></a></p>
</div>

<div class="panel card form" id="redaktoriai">
  <h2><?= te('✏️ Redaktoriai') ?></h2>
  <p class="muted"><?= te('Žmonės, kurie gali patys keisti LTKF svetainę: tekstus, nuotraukas, failus, meniu, naujus puslapius. Kiekvienam - asmeninė nuoroda (paskyros nereikia, tik vardas). Išjungus nuorodą, redaguoti nebegalima.') ?></p>
  <p><a class="btn" href="<?= url('ltkf/index.php?redaguoti=1') ?>"><?= te('✏️ Redaguoti pačiam') ?></a> <a class="btn btn-ghost" href="<?= url('ltkf/failai.php') ?>"><?= icon('upload') ?> <?= te('Failų dėžė') ?></a></p>
  <?php foreach ($editors as $ed): /* C3: Kopijuoti · ↻ Nauja nuoroda · Išjungti */ ?>
    <div class="ltkf-ed" style="margin:8px 0;">
      <b><?= e((string) $ed['label']) ?></b>
      <?= link_box('ltkf_editor', null, ['row' => $ed, 'back' => '/admin/ltkf-perziura.php#redaktoriai']) ?>
    </div>
  <?php endforeach; ?>
  <form method="post" class="row" style="gap:8px;flex-wrap:wrap;margin-top:10px;"><?= csrf_field() ?>
    <input type="text" name="name" placeholder="<?= te('Vardas, pvz. Jonas iš Sanrei') ?>" required style="flex:1;min-width:220px;">
    <button class="btn btn-primary" name="action" value="ed_add" type="submit"><?= te('+ Nauja redaktoriaus nuoroda') ?></button></form>
</div>
<?php
page_end();
