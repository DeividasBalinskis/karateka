<?php
// Dalyviai -> varžybų sistema: „Dalyvaus“ pažymėtus narius užregistruoti į tinkamas kategorijas; užsiregistravusius ten - pažymėti čia
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

$id = (int) (get('id') ?: post('id'));
$event = q_one('SELECT * FROM events WHERE id = ? AND type = "competition"', [$id]);
if (!$event) {
    not_found();
}
if (!$event['tournament_id']) {
    redirect('admin/varzybu-rezultatai.php?id=' . $id);   // pirma susieti
}
$tid = (int) $event['tournament_id'];
$back = 'admin/varzybu-dalyviai.php?id=' . $id;

if (is_post()) {
    csrf_check();
    if (post('action') === 'gender') {
        foreach ((array) ($_POST['gender'] ?? []) as $mid => $g) {
            if (in_array($g, ['M', 'F'], true)) {
                q('UPDATE members SET gender = ? WHERE id = ?', [$g, (int) $mid]);
            }
        }
        flash('ok', 'Lytis išsaugota - dabar matysite tinkamas kategorijas.');
    }
    if (post('action') === 'register') {
        $done = 0;
        $errs = [];
        foreach ((array) ($_POST['cats'] ?? []) as $mid => $cats) {
            $cats = array_map('intval', (array) $cats);
            $m = q_one('SELECT * FROM members WHERE id = ? AND status = "active"', [(int) $mid]);
            if (!$m || !$cats) {
                continue;
            }
            try {
                varzybos_register($m, $tid, $cats);
                varzybos_mark_going($tid, (int) $mid, (int) $me['id']);
                $done++;
            } catch (RuntimeException $ex) {
                $errs[] = $m['first_name'] . ': ' . $ex->getMessage();
            }
        }
        if ($done) {
            flash('ok', "Užregistruota varžybų sistemoje: $done nar.");
        }
        if ($errs) {
            flash('err', implode(' ', $errs));
        }
        if (!$done && !$errs) {
            flash('err', 'Pažymėkite bent vieną kategoriją.');
        }
    }
    if (post('action') === 'withdraw') {
        try {
            varzybos_api('DELETE', 'registrations', ['tournament_id' => $tid, 'external_id' => (string) (int) post('member_id'), 'category_id' => (int) post('category_id')]);
            flash('ok', 'Registracija atšaukta.');
        } catch (RuntimeException $ex) {
            flash('err', $ex->getMessage());
        }
    }
    redirect($back);
}

$error = null;
$tour = null;
$regs = [];
$synced = 0;
try {
    $tour = varzybos_api('GET', 'tournaments/' . $tid)['tournament'];
    // Visi klubo nariai, užsiregistravę varžybų sistemoje (ir patys, ir per trenerį)
    $all = varzybos_api('GET', 'registrations', null, ['tournament_id' => $tid])['registrations'] ?? [];
    foreach ($all as $r) {
        if (ctype_digit((string) $r['external_id'])) {
            $regs[(int) $r['external_id']][] = $r;
        }
    }
    // Užsiregistravę ten - pažymimi „Dalyvaus“ čia
    foreach (array_keys($regs) as $mid) {
        if (q_value('SELECT 1 FROM members WHERE id = ?', [$mid])) {
            $synced += varzybos_mark_going($tid, $mid, (int) $me['id']);
        }
    }
} catch (RuntimeException $ex) {
    $error = $ex->getMessage();
}

// Kandidatai: „Dalyvaus“ (sportininkai, ne teisėjai) ir jau užsiregistravę varžybose
$rows = q_all('SELECT m.*, g.name AS group_name, ep.role FROM event_participants ep JOIN members m ON m.id = ep.member_id LEFT JOIN training_groups g ON g.id = m.group_id
                WHERE ep.event_id = ? AND ep.status = "going" ORDER BY ep.role = "referee", m.last_name, m.first_name', [$id]);
$open = $tour && !empty($tour['registration_open']);
$eligible = [];
if ($tour && $open) {
    foreach ($rows as $m) {
        if ($m['role'] !== 'referee' && $m['gender']) {
            try {
                $eligible[(int) $m['id']] = varzybos_eligible($m, $tid);
            } catch (RuntimeException $ex) {
                $eligible[(int) $m['id']] = [];
            }
        }
    }
}
$noGender = array_filter($rows, function ($m) { return $m['role'] !== 'referee' && !$m['gender']; });

page_start('Varžybos: ' . $event['title'], ['admin' => true]);
?>
<a class="small" href="<?= url('admin/varzybos.php') ?>">← Varžybos</a> · <a class="small" href="<?= url('admin/renginio-dalyviai.php?id=' . $id) ?>">Pakviesti dalyvius</a> · <a class="small" href="<?= url('admin/varzybu-rezultatai.php?id=' . $id) ?>">Rezultatai</a>
<div class="page-head row between" style="margin-top:8px; flex-wrap:wrap; gap:10px; align-items:flex-end;">
  <div>
    <h1 class="styled"><?= e($event['title']) ?></h1>
    <p class="muted" style="margin:0;"><?= e(fmt_date($event['starts_on'], true)) ?><?= $tour ? ' · varžybų sistemoje: ' . e(VARZYBOS_STATUS[$tour['status']] ?? $tour['status']) . ($open ? '' : ' (registracija uždaryta - keisti gali tik organizatorius)') : '' ?></p>
  </div>
  <a class="btn btn-ghost btn-sm" href="<?= url('admin/varzybos-sso.php?to=' . rawurlencode('/admin/t/' . $tid)) ?>" target="_blank" rel="noopener">Valdyti varžybų sistemoje ↗</a>
</div>
<?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
<?php if ($synced): ?><div class="flash flash-info">Užsiregistravę varžybų sistemoje pažymėti „Dalyvaus“ šiame renginyje: <?= $synced ?>.</div><?php endif; ?>

<?php if (!$rows): ?>
  <div class="panel card"><p class="muted">Dar niekas nepažymėtas „Dalyvaus“. <a href="<?= url('admin/renginio-dalyviai.php?id=' . $id) ?>">Pakvieskite narius →</a> arba palaukite, kol užsiregistruos patys.</p></div>
<?php endif; ?>

<?php if ($noGender && $open): ?>
  <form method="post" class="panel card">
    <?= csrf_field() ?><input type="hidden" name="action" value="gender"><input type="hidden" name="id" value="<?= $id ?>">
    <div class="kicker">Kategorijoms trūksta lyties</div>
    <?php foreach ($noGender as $m): ?>
      <div class="result-row"><span class="who"><?= e($m['first_name'] . ' ' . $m['last_name']) ?></span>
        <div class="seg"><label><input type="radio" name="gender[<?= (int) $m['id'] ?>]" value="M"><span>Vaikinas / vyras</span></label><label><input type="radio" name="gender[<?= (int) $m['id'] ?>]" value="F"><span>Mergina / moteris</span></label></div></div>
    <?php endforeach; ?>
    <div style="margin-top:10px;"><button class="btn btn-primary btn-sm" type="submit">Išsaugoti</button></div>
  </form>
<?php endif; ?>

<?php if ($rows): ?>
  <form method="post" class="panel card">
    <?= csrf_field() ?><input type="hidden" name="action" value="register"><input type="hidden" name="id" value="<?= $id ?>">
    <div class="row between" style="flex-wrap:wrap; gap:8px;">
      <h2 style="margin:0;">Dalyvaus (<?= count($rows) ?>)</h2>
      <?php if ($open): ?><span class="small muted">Pažymėkite kategorijas ir spauskite „Registruoti“. Jau užregistruoti - žalios.</span><?php endif; ?>
    </div>
    <table class="table cards" style="margin-top:12px;">
      <thead><tr><th>Narys</th><th>Užregistruota</th><th><?= $open ? 'Galima registruoti' : '' ?></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $m): $mid = (int) $m['id']; $mine = $regs[$mid] ?? []; $have = array_column($mine, 'category_id'); ?>
        <tr>
          <td><strong><?= e($m['first_name'] . ' ' . $m['last_name']) ?></strong>
            <div class="small muted"><?= e(implode(' · ', array_filter([$m['group_name'], belt_label($m['belt_level'] !== null ? (int) $m['belt_level'] : null), $m['gender'] === 'M' ? 'vyr.' : ($m['gender'] === 'F' ? 'mot.' : '')]))) ?></div>
            <?= $m['role'] === 'referee' ? '<span class="badge badge-tag">teisėjas</span>' : '' ?></td>
          <td>
            <?php foreach ($mine as $r): ?>
              <span class="badge badge-ok" style="margin:2px 0;"><?= e($r['category']) ?></span>
              <?php if (!$r['locked'] && $open): ?><button class="linklike small" style="border:none; color:var(--danger);" type="submit" form="w<?= $mid ?>-<?= (int) $r['category_id'] ?>" title="Atšaukti">✕</button><?php endif; ?>
            <?php endforeach; ?>
            <?= !$mine ? '<span class="small muted">-</span>' : '' ?>
          </td>
          <td>
            <?php if ($m['role'] === 'referee'): ?><span class="small muted">teisėjauja</span>
            <?php elseif ($open && !$m['gender']): ?><span class="small muted">nurodykite lytį ↑</span>
            <?php elseif ($open): $free = array_values(array_filter($eligible[$mid] ?? [], function ($c) use ($have) { return !in_array($c['id'], $have, true); })); ?>
              <?php if ($free): ?>
                <div class="seg cat-pick"><?php foreach ($free as $c): ?><label><input type="checkbox" name="cats[<?= $mid ?>][]" value="<?= (int) $c['id'] ?>"><span><?= e($c['name']) ?></span></label><?php endforeach; ?></div>
              <?php else: ?><span class="small muted"><?= $mine ? 'visos tinkamos jau pažymėtos' : 'tinkamų kategorijų nėra' ?></span><?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if ($open && $eligible): ?><div style="margin-top:12px;"><button class="btn btn-primary" type="submit">Registruoti pažymėtus</button></div><?php endif; ?>
  </form>
  <?php foreach ($rows as $m): foreach ($regs[(int) $m['id']] ?? [] as $r): ?>
    <form method="post" id="w<?= (int) $m['id'] ?>-<?= (int) $r['category_id'] ?>" onsubmit="return confirm('Atšaukti registraciją?')" hidden><?= csrf_field() ?><input type="hidden" name="action" value="withdraw"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="member_id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="category_id" value="<?= (int) $r['category_id'] ?>"></form>
  <?php endforeach; endforeach; ?>
<?php endif; ?>
<?php
page_end();
