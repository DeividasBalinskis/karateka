<?php
// Varžybos: ryšys su varžybų sistema - varžybos, susieti renginiai, dalyviai ir rezultatai vienoje vietoje
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

if (is_post()) {
    csrf_check();
    $tid = (int) post('tournament_id');
    if (post('action') === 'create_event' && $tid) {
        try {
            $t = varzybos_api('GET', 'tournaments/' . $tid)['tournament'];
            if (varzybos_linked_events($tid)) {
                flash('info', 'Šios varžybos jau susietos su renginiu.');
            } else {
                q('INSERT INTO events (type, title, starts_on, ends_on, location, is_abroad, description, registration, tournament_id) VALUES ("competition", ?, ?, ?, ?, ?, ?, 1, ?)',
                    [mb_substr($t['title'], 0, 190), $t['starts_on'], $t['ends_on'] ?: null, $t['location'] ?: null, !empty($t['is_abroad']) ? 1 : 0,
                     'Kategorijos, lentelės ir rezultatai - varžybų sistemoje. Registracija - per karateka.lt.', $tid]);
                flash('ok', 'Renginys „' . $t['title'] . '“ sukurtas ir susietas. Jį matys nariai Renginiuose.');
            }
        } catch (RuntimeException $ex) {
            flash('err', $ex->getMessage());
        }
    }
    if (post('action') === 'link' && $tid && ($eid = (int) post('event_id'))) {
        q('UPDATE events SET tournament_id = ? WHERE id = ? AND type = "competition"', [$tid, $eid]);
        flash('ok', 'Renginys susietas su varžybomis.');
    }
    redirect('admin/varzybos.php');
}

$error = null;
$tournaments = [];
$connected = varzybos_enabled();
if ($connected) {
    try {
        $tournaments = varzybos_api('GET', 'tournaments')['tournaments'] ?? [];
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage() . (config('env') === 'local' ? ' Kompiuteryje: paleiskite Varzybos\\tools\\lokali-svetaine.ps1.' : '');
    }
}
// Varžybų renginiai karateka.lt, dar nesusieti - kad būtų galima susieti su esamomis varžybomis
$unlinked = q_all('SELECT id, title, starts_on FROM events WHERE type = "competition" AND tournament_id IS NULL AND starts_on >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) ORDER BY starts_on');

page_start('Varžybos', ['admin' => true]);
?>
<div class="page-head row between" style="flex-wrap:wrap; gap:10px; align-items:flex-end;">
  <div>
    <h1 class="styled">Varžybos</h1>
    <p class="muted" style="margin:0;">Varžybų sistema: kategorijos, lentelės, tvarkaraštis ir rezultatai gyvai. Nariai registruojasi per karateka.lt, rezultatai grįžta į taškus.</p>
  </div>
  <?php if (varzybos_enabled()): ?>
    <div class="row" style="gap:8px;">
      <a class="btn btn-ghost" href="<?= e(varzybos_public_url('/')) ?>" target="_blank" rel="noopener">Viešas puslapis ↗</a>
      <a class="btn btn-primary" href="<?= url('admin/varzybos-sso.php') ?>" target="_blank" rel="noopener">Atidaryti varžybų valdymą ↗</a>
    </div>
  <?php endif; ?>
</div>
<?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>

<?php if (!$connected): ?>
  <div class="panel card">
    <div class="kicker">Varžybų sistema dar neprijungta</div>
    <p>Varžybų sistema (kategorijos, lentelės, rezultatai gyvai) jau paruošta, bet dar neįkelta į serverį. Kai bus įkelta, čia matysite visas varžybas, o mygtukas „Atidaryti varžybų valdymą“ ją atidarys be atskiro slaptažodžio.</p>
    <p class="small muted" style="margin:10px 0 0;">Kol kas varžybas kurkite kaip įprastą renginį (Renginiai → „+ Naujas renginys“, tipas „Varžybos“), o rezultatus įrašykite renginio rezultatuose.</p>
    <?php if (is_admin()): ?><p class="small muted" style="margin:8px 0 0;">Techniškai: serverio config.php trūksta „varzybos“ dalies (adresas ir raktas) - žr. config.example.php.</p><?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($connected && !$error && !$tournaments): ?>
  <div class="panel card"><p class="muted">Varžybų sistemoje paskelbtų varžybų nėra. Sukurkite jas varžybų valdyme - čia atsiras automatiškai.</p></div>
<?php endif; ?>

<?php foreach ($tournaments as $t): $linked = varzybos_linked_events((int) $t['id']); ?>
  <div class="panel card tour-card">
    <div class="row between" style="flex-wrap:wrap; gap:10px; align-items:flex-start;">
      <div>
        <span class="badge badge-pink"><?= e(VARZYBOS_STATUS[$t['status']] ?? $t['status']) ?></span>
        <h2 style="margin:6px 0 2px;"><?= e($t['title']) ?></h2>
        <p class="small muted" style="margin:0;"><?= e(fmt_date($t['starts_on'], true)) ?><?= $t['location'] ? ' · ' . e($t['location']) : '' ?><?= !empty($t['reg_deadline']) ? ' · registracija iki ' . e(fmt_date($t['reg_deadline'])) : '' ?></p>
      </div>
      <a class="btn btn-ghost btn-sm" href="<?= url('admin/varzybos-sso.php?to=' . rawurlencode('/admin/t/' . (int) $t['id'])) ?>" target="_blank" rel="noopener">Valdyti ↗</a>
    </div>
    <?php if ($linked): ?>
      <?php foreach ($linked as $ev): $pc = event_participant_counts((int) $ev['id']); ?>
        <div class="tour-link">
          <span>karateka.lt renginys: <a href="<?= url('renginiai.php#e' . (int) $ev['id']) ?>"><strong><?= e($ev['title']) ?></strong></a> · dalyvaus <?= (int) $pc['going'] ?></span>
          <span class="row" style="gap:6px;">
            <a class="btn btn-primary btn-sm" href="<?= url('admin/varzybu-dalyviai.php?id=' . (int) $ev['id']) ?>">Dalyviai → varžybos</a>
            <a class="btn btn-ghost btn-sm" href="<?= url('admin/varzybu-rezultatai.php?id=' . (int) $ev['id']) ?>">Rezultatai → taškai</a>
          </span>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="tour-link">
        <span class="small muted">Dar nesusieta su karateka.lt renginiu.</span>
        <span class="row" style="gap:6px; flex-wrap:wrap;">
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="create_event"><input type="hidden" name="tournament_id" value="<?= (int) $t['id'] ?>">
            <button class="btn btn-primary btn-sm" type="submit">+ Sukurti renginį karateka.lt</button></form>
          <?php if ($unlinked): ?>
            <form method="post" class="row" style="gap:6px;"><?= csrf_field() ?><input type="hidden" name="action" value="link"><input type="hidden" name="tournament_id" value="<?= (int) $t['id'] ?>">
              <select name="event_id" required><option value="">arba susieti su esamu…</option><?php foreach ($unlinked as $ev): ?><option value="<?= (int) $ev['id'] ?>"<?= $ev['starts_on'] === $t['starts_on'] ? ' selected' : '' ?>><?= e(fmt_date($ev['starts_on']) . ' · ' . $ev['title']) ?></option><?php endforeach; ?></select>
              <button class="btn btn-ghost btn-sm" type="submit">Susieti</button></form>
          <?php endif; ?>
        </span>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<div class="panel card">
  <h2>Kaip tai veikia</h2>
  <ol class="small" style="padding-left:18px; margin:0;">
    <li>Varžybas ir kategorijas sukuriate varžybų valdyme (mygtukas viršuje - prisijungiama automatiškai).</li>
    <li>Čia paspaudžiate „Sukurti renginį karateka.lt“ - nariai varžybas mato Renginiuose ir registruojasi patys (kategorijos parenkamos pagal amžių, lytį, diržą).</li>
    <li>Arba registruojate patys: „Dalyviai → varžybos“ - pažymėti „Dalyvaus“ nariai ir jiems tinkamos kategorijos.</li>
    <li>Po varžybų: „Rezultatai → taškai“ - vietos įkeliamos į narių istoriją ir taškus.</li>
  </ol>
</div>
<?php
page_end();
