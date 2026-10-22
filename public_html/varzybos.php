<?php
// Varžybos: narys (ar tėvai už vaiką) registruojasi į varžybas varžybų sistemoje.
// Kategorijos parenkamos pagal gimimo datą, lytį ir diržą iš paskyros.
require __DIR__ . '/app/bootstrap.php';

$a = require_login();
if (!is_active_account($a)) {
    flash('info', 'Registracija į varžybas atsiras, kai treneris patvirtins paskyrą.');
    redirect('paskyra.php');
}
$members = array_values(array_filter(account_members((int) $a['id']), function ($m) { return $m['status'] === 'active'; }));

if (is_post()) {
    csrf_check();
    $mid = (int) post('member_id');
    $tid = (int) post('tournament_id');
    if (!account_relation((int) $a['id'], $mid)) {
        forbidden();
    }
    $m = q_one('SELECT * FROM members WHERE id = ?', [$mid]);
    try {
        switch (post('action')) {
            case 'gender':
                if (in_array(post('gender'), ['M', 'F'], true)) {
                    q('UPDATE members SET gender = ? WHERE id = ?', [post('gender'), $mid]);
                }
                break;
            case 'register':
                $cats = array_map('intval', (array) ($_POST['category_ids'] ?? []));
                if (!$cats) {
                    throw new RuntimeException('Pažymėkite bent vieną kategoriją.');
                }
                varzybos_register($m, $tid, $cats);
                varzybos_mark_going($tid, $mid);   // ir karateka.lt renginyje - „Dalyvaus“
                flash('ok', $m['first_name'] . ' ' . $m['last_name'] . ': registracija išsaugota.');
                break;
            case 'withdraw':
                varzybos_api('DELETE', 'registrations', ['tournament_id' => $tid, 'external_id' => (string) $mid, 'category_id' => (int) post('category_id')]);
                flash('ok', 'Registracija atšaukta.');
                break;
        }
    } catch (RuntimeException $ex) {
        flash('err', $ex->getMessage());
    }
    redirect('varzybos.php#t' . $tid);
}

$error = null;
$tournaments = [];
try {
    $tournaments = varzybos_open_tournaments();
} catch (RuntimeException $ex) {
    $error = $ex->getMessage();
}

page_start('Varžybos');
?>
<div class="page-head">
  <div class="eyebrow">Registracija</div>
  <h1 class="styled">Varžybos</h1>
  <p>Užregistruokite save ar savo vaiką į varžybas. Tinkamos kategorijos parenkamos pagal amžių, lytį ir diržą.</p>
</div>

<?php if ($error): ?>
  <div class="flash flash-err"><?= e($error) ?></div>
<?php elseif (!$tournaments): ?>
  <div class="panel card"><p class="muted">Šiuo metu registracija į varžybas nevyksta. Artėjančios varžybos - <a href="<?= url('renginiai.php?tipas=varzybos') ?>">renginių kalendoriuje</a>.</p></div>
<?php elseif (!$members): ?>
  <div class="panel card"><p class="muted">Paskyroje nėra aktyvių narių.</p></div>
<?php endif; ?>

<?php foreach ($tournaments as $t):
    try {
        $regs = varzybos_registrations((int) $t['id'], array_map(function ($m) { return (int) $m['id']; }, $members));
    } catch (RuntimeException $ex) {
        $regs = [];
    } ?>
  <div class="panel card" id="t<?= (int) $t['id'] ?>">
    <div class="event">
      <div class="event-date"><span class="d"><?= date('j', strtotime($t['starts_on'])) ?></span><span class="m"><?= e(mb_substr(LT_MONTHS_GEN[(int) date('n', strtotime($t['starts_on']))], 0, 3)) ?></span></div>
      <div>
        <h2 style="margin-bottom:4px;"><?= e($t['title']) ?></h2>
        <p class="small muted"><?= e(fmt_date($t['starts_on'], true)) ?><?= $t['location'] ? ' · ' . e($t['location']) : '' ?>
          <?= $t['reg_deadline'] ? ' · registracija iki ' . e(fmt_date($t['reg_deadline'])) : '' ?></p>
        <p class="small"><a href="<?= e($t['url']) ?>" target="_blank" rel="noopener">Kategorijos, lentelės ir tvarkaraštis →</a></p>
      </div>
    </div>

    <?php foreach ($members as $m):
        $mine = $regs[(int) $m['id']] ?? []; ?>
      <div class="result-row" style="align-items:flex-start;">
        <div class="who" style="min-width:180px;"><?= e($m['first_name'] . ' ' . $m['last_name']) ?>
          <div class="small muted" style="font-weight:400;"><?= e(belt_label($m['belt_level'] !== null ? (int) $m['belt_level'] : null)) ?></div></div>
        <div style="flex:1; min-width:240px;">
        <?php if (empty($m['gender'])): ?>
          <form method="post" class="row" style="gap:8px;">
            <?= csrf_field() ?><input type="hidden" name="action" value="gender"><input type="hidden" name="member_id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="tournament_id" value="<?= (int) $t['id'] ?>">
            <span class="small">Kategorijoms reikia lyties:</span>
            <div class="seg"><label><input type="radio" name="gender" value="M" required><span>Vaikinas / vyras</span></label><label><input type="radio" name="gender" value="F"><span>Mergina / moteris</span></label></div>
            <button class="btn btn-sm btn-ghost" type="submit">Išsaugoti</button>
          </form>
        <?php else:
            try {
                $eligible = varzybos_eligible($m, (int) $t['id']);
            } catch (RuntimeException $ex) {
                $eligible = [];
            }
            $have = array_column($mine, 'category_id');
            $free = array_values(array_filter($eligible, function ($c) use ($have) { return !in_array($c['id'], $have, true); })); ?>
          <?php if ($mine): ?>
            <div class="small" style="margin-bottom:8px;"><strong>Užregistruota:</strong>
              <?php foreach ($mine as $r): ?>
                <span class="badge badge-ok" style="margin:2px 0;"><?= e($r['category']) ?></span>
                <?php if (!$r['locked']): ?>
                  <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="withdraw"><input type="hidden" name="member_id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="tournament_id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="category_id" value="<?= (int) $r['category_id'] ?>">
                    <button class="linklike small" style="border:none; color:var(--danger);" type="submit" title="Atšaukti">atšaukti</button></form>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php if ($free): ?>
            <form method="post">
              <?= csrf_field() ?><input type="hidden" name="action" value="register"><input type="hidden" name="member_id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="tournament_id" value="<?= (int) $t['id'] ?>">
              <div class="seg" style="margin-bottom:8px;">
                <?php foreach ($free as $c): ?>
                  <label><input type="checkbox" name="category_ids[]" value="<?= (int) $c['id'] ?>"><span><?= e($c['name']) ?></span></label>
                <?php endforeach; ?>
              </div>
              <button class="btn btn-sm btn-primary" type="submit">Registruoti</button>
            </form>
          <?php elseif (!$mine): ?>
            <p class="small muted">Tinkamų kategorijų šiose varžybose nėra.</p>
          <?php endif; ?>
        <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
<?php
page_end();
