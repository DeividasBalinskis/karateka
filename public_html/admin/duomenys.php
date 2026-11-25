<?php
// C10: Duomenų priežiūra (tik adminas). Viršuje - kas trinama automatiškai; žemiau - kas galėtų būti ištrinta.
// Niekas netrinama, kol administratorius nepaspaudžia „Ištrinti“ ir nepatvirtina (sprendimas: pirmiausia parodyti sąrašą).
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_can('backups');

if (is_post()) {
    csrf_check();
    $key = post('key');
    if (in_array($key, CARE_KEYS, true) && post('sure') === '1') {
        $n = care_delete($key);
        flash('ok', t('Ištrinta: {n}.', ['n' => $n]));
    }
    redirect('admin/duomenys.php');
}
$cands = care_candidates();
page_start(t('Duomenų priežiūra'), ['admin' => true]);
?>
<div class="page-head">
  <h1 class="styled"><?= te('Duomenų priežiūra') ?></h1>
  <p><?= te('Kas saugoma ir kiek laiko. Niekas netrinama, kol nepaspausite „Ištrinti“.') ?> <a href="<?= url('admin/kopijos.php') ?>"><?= te('Atsarginės kopijos →') ?></a></p>
</div>

<div class="panel card">
  <div class="kicker"><?= te('Galima ištrinti') ?></div>
  <ul class="care-list">
    <?php foreach ($cands as $key => $c): ?>
      <li class="care-item" id="<?= e($key) ?>">
        <div class="row between" style="gap:8px; flex-wrap:wrap;">
          <strong><?= e($c['title']) ?></strong>
          <span class="badge<?= $c['count'] ? ' badge-warn' : ' badge-ok' ?>"><?= $c['count'] ? (int) $c['count'] : te('nėra') ?></span>
        </div>
        <p class="small muted" style="margin:4px 0 6px;"><?= e($c['why']) ?></p>
        <?php if ($c['count']): ?>
          <details class="small"><summary><?= te('Peržiūrėti sąrašą') ?></summary>
            <ul class="care-names"><?php foreach (array_slice($c['items'], 0, 60) as $it): ?><li><?= e($it['label']) ?></li><?php endforeach; ?>
              <?php if ($c['count'] > 60): ?><li class="muted"><?= te('… ir dar {n}', ['n' => $c['count'] - 60]) ?></li><?php endif; ?></ul>
          </details>
          <form method="post" class="inline" onsubmit="return confirm(this.dataset.q)" data-q="<?= te('Ištrinti visam laikui: {n}? Atkurti galima tik iš atsarginės kopijos.', ['n' => $c['count']]) ?>">
            <?= csrf_field() ?><input type="hidden" name="key" value="<?= e($key) ?>"><input type="hidden" name="sure" value="1">
            <button class="btn btn-danger btn-sm" style="margin-top:6px;"><?= te('Ištrinti') ?> (<?= (int) $c['count'] ?>)</button>
          </form>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>

<div class="panel card">
  <div class="kicker"><?= te('Trinama automatiškai') ?></div>
  <ul class="care-auto">
    <?php foreach (care_auto_rules() as [$what, $keep]): ?>
      <li class="row between"><span><?= e($what) ?></span><span class="badge"><?= e($keep) ?></span></li>
    <?php endforeach; ?>
  </ul>
  <p class="small muted"><?= te('Narių, egzaminų, varžybų rezultatai ir naujienos lieka, kol juos ištrina treneris ar administratorius.') ?></p>
</div>
<?php page_end();
