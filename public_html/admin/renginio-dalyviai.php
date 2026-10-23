<?php
// Renginio dalyviai: kas dalyvaus, kas pakviestas ir neatsakė, kas negalės. Kvietimai grupėms ar konkretiems nariams.
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

$id = (int) (get('id') ?: post('id'));
$event = q_one('SELECT * FROM events WHERE id = ?', [$id]);
if (!$event) {
    not_found();
}
$back = 'admin/renginio-dalyviai.php?id=' . $id;

if (is_post()) {
    csrf_check();
    $action = post('action');
    $mid = (int) post('member_id');

    if ($action === 'invite') {
        $groupIds = array_map('intval', (array) ($_POST['groups'] ?? []));
        $ids = array_map('intval', (array) ($_POST['members'] ?? []));
        if ($groupIds) {
            $ids = array_merge($ids, array_map('intval', q('SELECT id FROM members WHERE status = "active" AND group_id IN (' . implode(',', $groupIds) . ')')->fetchAll(PDO::FETCH_COLUMN)));
        }
        if (!$ids) {
            flash('err', 'Pažymėkite grupes arba narius, kuriuos kviečiate.');
        } else {
            $n = invite_members($event, $ids, (int) $me['id'], !empty($_POST['send_email']));
            flash('ok', $n ? "Pakviesta: $n" . (!empty($_POST['send_email']) ? ' (išsiųsti laiškai)' : '') : 'Visi pažymėti jau yra sąraše.');
        }
    } elseif ($mid && in_array($action, ['going', 'declined', 'invited'], true)) {
        set_participation($id, $mid, $action, (int) $me['id']);
    } elseif ($mid && $action === 'remove') {
        remove_participation($id, $mid);
    } elseif ($mid && $action === 'role') {
        $p = event_participant($id, $mid);
        if ($p) {
            q('UPDATE event_participants SET role = ? WHERE event_id = ? AND member_id = ?', [$p['role'] === 'referee' ? 'participant' : 'referee', $id, $mid]);
        }
    }
    redirect($back);
}

$rows = q_all('SELECT ep.*, m.first_name, m.last_name, m.birth_date, m.belt_level, m.photo_consent, g.name AS group_name
                 FROM event_participants ep JOIN members m ON m.id = ep.member_id LEFT JOIN training_groups g ON g.id = m.group_id
                WHERE ep.event_id = ? ORDER BY g.sort_order, m.last_name, m.first_name', [$id]);
$byStatus = ['going' => [], 'invited' => [], 'declined' => []];
foreach ($rows as $r) {
    $byStatus[$r['status']][] = $r;
}
$tags = [];
foreach (q_all('SELECT * FROM member_tags') as $t) {
    $tags[(int) $t['member_id']][] = $t['tag'];
}
$inList = array_map('intval', array_column($rows, 'member_id'));
$eventGroups = array_map('intval', q('SELECT group_id FROM event_groups WHERE event_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN));

/** Mygtukas vienam dalyviui */
function p_btn(int $mid, string $action, string $text, string $cls = 'btn-ghost', string $title = ''): string
{
    return '<form method="post" class="inline-form">' . csrf_field() . '<input type="hidden" name="member_id" value="' . $mid . '">'
        . '<button class="btn btn-sm ' . $cls . '" name="action" value="' . $action . '"' . ($title ? ' title="' . e($title) . '"' : '') . '>' . $text . '</button></form>';
}

page_start('Dalyviai: ' . $event['title'], ['admin' => true, 'section' => 'renginiai']);
?>
<a class="small no-print" href="<?= url('renginiai.php#e' . $id) ?>">← Renginiai</a>
<div class="page-head row between" style="margin-top:8px; align-items:flex-end;">
  <div>
    <div><span class="badge type-badge t-<?= e($event['type']) ?>"><?= e(EVENT_TYPES[$event['type']]) ?></span></div>
    <h1 class="styled" style="margin-top:8px;"><?= e($event['title']) ?></h1>
    <p><?= e(fmt_date($event['starts_on'], true)) ?><?= $event['start_time'] ? ', ' . e(fmt_time($event['start_time'])) : '' ?><?= $event['location'] ? ' · ' . e($event['location']) : '' ?></p>
    <p class="small muted no-print"><?= !empty($event['registration']) ? 'Nariai gali registruotis patys' . ($event['registration_until'] ? ' iki ' . e(fmt_date($event['registration_until'])) : '') . '.' : 'Nariai patys registruotis negali - tik pagal kvietimą.' ?>
      <a href="<?= url('admin/renginiai.php?edit=' . $id) ?>">Keisti</a></p>
  </div>
  <div class="row no-print" style="gap:8px;">
    <?php if (isset(EVENT_RESULTS[$event['type']]) && $event['starts_on'] <= date('Y-m-d')): ?><a class="btn btn-primary btn-sm" href="<?= url('admin/renginio-rezultatai.php?id=' . $id) ?>">Rezultatai →</a><?php endif; ?>
    <button class="btn btn-ghost btn-sm" type="button" onclick="window.print()">Spausdinti</button>
  </div>
</div>

<div class="participants-grid">
  <?php foreach ($byStatus as $status => $list): ?>
    <div class="panel card p-col p-<?= $status ?>">
      <div class="kicker"><?= e(PARTICIPANT_STATUS[$status]) ?> · <?= count($list) ?></div>
      <?php if (!$list): ?><p class="muted small"><?= $status === 'going' ? 'Dar niekas.' : '-' ?></p><?php endif; ?>
      <ul class="list small">
        <?php foreach ($list as $r): $mid = (int) $r['member_id']; $mt = $tags[$mid] ?? []; ?>
          <li class="row">
            <span>
              <a href="<?= url('admin/nariai.php?id=' . $mid) ?>"><strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong></a><?= photo_badge($r) ?><?= $r['role'] === 'referee' ? ' <span class="badge badge-tag">teisėjas</span>' : '' ?>
              <div class="muted"><?= e($r['group_name'] ?: 'be grupės') ?> · <?= age_on($r['birth_date']) ?> m.<?= $r['belt_level'] !== null ? ' · ' . e(belt_label((int) $r['belt_level'])) : '' ?></div>
              <?= participant_extra_info($event, $r) ?>
            </span>
            <span class="p-actions no-print">
              <?php if ($status !== 'going'): ?><?= p_btn($mid, 'going', '✓', 'btn-ghost', 'Dalyvaus') ?><?php endif; ?>
              <?php if ($status !== 'declined'): ?><?= p_btn($mid, 'declined', '✕', 'btn-ghost', 'Negalės') ?><?php endif; ?>
              <?php if ($event['type'] === 'competition' && (in_array('teisejas', $mt, true) || $r['role'] === 'referee')): ?><?= p_btn($mid, 'role', $r['role'] === 'referee' ? 'Sportininkas' : 'Teisėjas', 'btn-ghost', 'Keisti vaidmenį') ?><?php endif; ?>
              <?= p_btn($mid, 'remove', 'Išimti', 'btn-ghost') ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
</div>

<form method="post" class="panel card form no-print" id="kviesti">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="invite">
  <h2>Pakviesti</h2>
  <p class="hint">Pakviesti gaus kvietimą paskyroje ir galės atsakyti „Dalyvausiu“ arba „Negalėsiu“. Jau esantys sąraše nekeičiami.</p>
  <div style="margin-top:6px;">
    <?= member_tree_field([], [], ['locked' => $inList, 'locked_title' => 'jau sąraše', 'hint' => 'Pažymėkite visą grupę arba išskleiskite ▸ ir pasirinkite tik norimus.']) ?>
  </div>
  <label class="check" style="margin-top:12px;"><input type="checkbox" name="send_email" value="1" checked><span>Išsiųsti kvietimą el. paštu</span></label>
  <div><button class="btn btn-primary" type="submit">Pakviesti</button></div>
</form>
<?php
page_end();
