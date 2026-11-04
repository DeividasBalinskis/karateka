<?php
// Testavimas (tik adminas): testiniai nariai (Test Parent, Test Junior, Test Mažylis) ir ką jiems sukurti; jų ištrynimas.
// Čia trinami TIK testiniai nariai (@test.karateka.lt) - tikri žmonės, naujienos, renginiai ir pamokos čia nekeičiami.
// Peržiūra kaip tėvai ar vaikas - viršuje, „👁“ (app/test_people.php). Seni pavyzdžiai išvalomi vieną kartą automatiškai (app/test_data.php).
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_admin();

if ($msg = legacy_demo_autopurge($me)) {
    flash('ok', $msg);
}

if (is_post()) {
    csrf_check();
    $action = post('action');
    if ($action === 'generate') {
        $done = test_people_generate((array) ($_POST['scenarios'] ?? []), $me);
        flash('ok', (t('Sukurta:') . ' ') . implode(', ', $done) . '. Peržiūrėti - viršuje 👁 „Tėvai“ arba „Vaikas“.');
    } elseif ($action === 'delete') {
        [$members, $accounts] = test_people_delete();
        flash('ok', (t('Ištrinta testinių:') . ' ') . $members . (' ' . t('nar. ir') . ' ') . $accounts . (' ' . t('pask. Tikri duomenys nepakeisti.')));
    }
    redirect('admin/testavimas.php');
}

$people = test_people_list();
$purged = json_decode((string) site_setting('demo_isvalyta', ''), true);

page_start(t('Testavimas'), ['admin' => true]);
?>
<a class="small" href="<?= url('admin/nustatymai.php') ?>"><?= te('← Nustatymai') ?></a>
<h1 class="styled" style="margin-top:8px;"><?= te('Testavimas') ?></h1>
<p class="muted"><?= te('Čia - tik testiniai nariai. Tikri žmonės, naujienos, renginiai ir pamokos šiame puslapyje nekeičiami.') ?></p>

<div class="grid-2 testing-grid">
  <form method="post" class="panel card form">
    <?= csrf_field() ?>
    <div class="kicker"><?= te('Sukurti testinius') ?></div>
    <p class="small"><?= te('Visada sukuriami') ?> <strong><?= te('Test Parent') ?></strong> <?= te('(tėvai su 2 vaikais),') ?> <strong><?= te('Test Junior') ?></strong> <?= te('(15 m., savo paskyra) ir') ?> <strong><?= te('Test Mažylis') ?></strong> <?= te('(8 m.).
      Pažymėkite, ką dar jiems sukurti:') ?></p>
    <div class="scenario-list">
      <?php foreach (TEST_SCENARIOS as $k => [$label, $hint]): ?>
        <label class="check"><input type="checkbox" name="scenarios[]" value="<?= e($k) ?>" checked><span><strong><?= e($label) ?></strong><br><span class="small muted"><?= e($hint) ?></span></span></label>
      <?php endforeach; ?>
    </div>
    <div><button class="btn btn-primary" name="action" value="generate"><?= te('Sukurti') ?></button></div>
  </form>

  <div>
    <div class="panel card">
      <div class="kicker"><?= te('Peržiūra kaip tėvai ar vaikas') ?></div>
      <p class="small"><?= te('Viršuje, šalia „Valdymas“ -') ?> <strong><?= te('👁 Adminas') ?></strong>. Pasirinkus <strong><?= te('Tėvai') ?></strong> ar <strong><?= te('Vaikas') ?></strong><?= te(', svetainė rodoma taip, kaip ją mato Test Parent ar Test Junior:
        galite palikti komentarą, reaguoti, atsakyti į kvietimą. Vaiko režime ten pat keičiamas diržas - matysite, kaip keičiasi pamokų aplankai, programa ir diržo spalva.
        Grįžti -') ?> <strong><?= te('👁 → Adminas') ?></strong>.</p>
    </div>

    <form method="post" class="panel card" onsubmit="return confirm('Ištrinti visus testinius narius ir viską, ką jie sukūrė ar gavo?')">
      <?= csrf_field() ?>
      <div class="kicker"><?= te('Testiniai nariai dabar') ?></div>
      <?php if (!$people): ?>
        <p class="muted small"><?= te('Testinių narių nėra.') ?></p>
      <?php else: ?>
        <ul class="list small test-people">
          <?php foreach ($people as $p): ?>
            <li><strong><?= e($p['name']) ?></strong> <span class="muted">· <?= e($p['email']) ?><?= $p['status'] !== 'active' ? (' ' . t('· laukia patvirtinimo')) : '' ?></span>
              <?php if ($p['kids']): ?><div class="muted"><?= e(implode(', ', $p['kids'])) ?></div><?php endif; ?></li>
          <?php endforeach; ?>
        </ul>
        <p class="small muted"><?= te('Ištrinami kartu su jų lankomumu, žinutėmis, kvietimais, reakcijomis ir taškais.') ?></p>
        <button class="btn btn-danger btn-sm" name="action" value="delete"><?= te('Ištrinti visus testinius') ?></button>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php if ($purged && !empty($purged['parts'])): ?>
  <details class="panel card td-part purged">
    <summary><span><strong><?= te('Seni pavyzdžiai išvalyti') ?></strong> <span class="badge"><?= e($purged['date']) ?></span></span><span class="small muted">rodyti</span></summary>
    <p class="small muted"><?= te('Demo duomenys, kuriuos buvo sukūrę pavyzdžių skriptai (ne žmonės). Prieš valant sukurta kopija') ?> <code><?= e($purged['backup'] ?? '') ?></code>
      <?= te('(Nustatymai → Atsarginės kopijos) - jei kas dingo per daug, galima atkurti.') ?></p>
    <?php foreach ($purged['parts'] as $k => $part): ?>
      <div class="kicker" style="margin-top:10px;"><?= e(TEST_DATA_PARTS[$k] ?? $k) ?> (<?= (int) $part['count'] ?>)</div>
      <ul class="small td-items"><?php foreach ($part['items'] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul>
    <?php endforeach; ?>
    <?php if (!empty($purged['kept'])): ?>
      <p class="small"><?= te('Palikta, nes tikri nariai jau naudojo:') ?> <?= e(implode(', ', array_map(function ($k) { return mb_strtolower(TEST_DATA_PARTS[$k] ?? $k); }, $purged['kept']))) ?>.</p>
    <?php endif; ?>
  </details>
<?php endif; ?>
<?php
page_end();
