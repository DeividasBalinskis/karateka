<?php
// Visi renginiai: artėjantys pagal mėnesius ir praėję (paskutiniai 12 mėn.). Filtras pagal tipą: ?tipas=egzaminai
require __DIR__ . '/app/bootstrap.php';

$filter = isset(EVENT_TYPE_FILTERS[get('tipas')]) ? get('tipas') : '';
$type = $filter ? EVENT_TYPE_FILTERS[$filter][0] : null;

$allUpcoming = public_upcoming_events(200);
$upcoming = $type ? array_values(array_filter($allUpcoming, function ($ev) use ($type) { return $ev['type'] === $type; })) : $allUpcoming;
$past = q_all('SELECT e.* FROM events e WHERE COALESCE(e.ends_on, e.starts_on) < CURDATE()
                 AND e.starts_on >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) AND ' . visibility_sql('e')
               . ($type ? ' AND e.type = ' . db()->quote($type) : '') . '
               ORDER BY e.starts_on DESC LIMIT 12');

// Prisijungusiems nariams - „Dalyvausiu / Negalėsiu“ prie renginių
$acc = current_account();
$rsvpMembers = $acc && is_active_account($acc)
    ? array_values(array_filter(account_members((int) $acc['id']), function ($m) { return $m['status'] === 'active'; })) : [];

// Treneris kviečia (neatsakyta) - rodoma viršuje
$invites = [];
if ($rsvpMembers) {
    $ids = implode(',', array_map('intval', array_column($rsvpMembers, 'id')));
    $invites = q_all("SELECT DISTINCT e.* FROM events e JOIN event_participants ep ON ep.event_id = e.id
                       WHERE ep.member_id IN ($ids) AND ep.status = 'invited' AND e.starts_on >= CURDATE() ORDER BY e.starts_on");
}

// Kiek artėjančių renginių kiekvieno tipo (skaičius ant filtro mygtuko)
$typeCounts = array_count_values(array_column($allUpcoming, 'type'));

$byMonth = [];
foreach ($upcoming as $ev) {
    $ts = strtotime($ev['starts_on']);
    $m = LT_MONTHS[(int) date('n', $ts)];
    $byMonth[mb_strtoupper(mb_substr($m, 0, 1)) . mb_substr($m, 1) . ' ' . date('Y', $ts)][] = $ev;
}

page_start('Renginiai', ['description' => 'VšĮ Karate Ateitis renginiai: egzaminai, varžybos, seminarai ir stovyklos.', 'section' => 'renginiai']);
$n = 0;   // eilės nr. animacijai (laipteliai)
?>
<div class="page-head">
  <div class="eyebrow">Kalendorius</div>
  <h1 class="styled">Renginiai</h1>
</div>
<?= staff_bar([['+ Naujas renginys', 'admin/renginiai.php?edit=new&back=renginiai', true]]) ?>

<?php if ($invites): ?>
  <div class="panel card invites-box rise" id="kvietimai">
    <div class="kicker">Treneris kviečia - atsakykite</div>
    <?php foreach ($invites as $ev): ?>
      <div class="invite-item">
        <?= render_event($ev) ?>
        <?= render_event_rsvp($ev, $rsvpMembers, 'renginiai.php#kvietimai') ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<nav class="type-filter rise" aria-label="Renginių tipas">
  <a href="<?= url('renginiai.php') ?>" class="<?= $filter === '' ? 'active' : '' ?>">Visi<span class="tf-count"><?= count($allUpcoming) ?></span></a>
  <?php foreach (EVENT_TYPE_FILTERS as $slug => [$t, $label]): ?>
    <a href="<?= url('renginiai.php?tipas=' . $slug) ?>" class="t-<?= e($t) ?><?= $filter === $slug ? ' active' : '' ?>"><i class="tf-dot"></i><?= e($label) ?><span class="tf-count"><?= (int) ($typeCounts[$t] ?? 0) ?></span></a>
  <?php endforeach; ?>
</nav>

<?php if (!$upcoming): ?>
  <div class="panel card rise"><p class="muted"><?= $type ? 'Artėjančių renginių šioje kategorijoje nėra.' : 'Artėjančių renginių šiuo metu nėra.' ?></p></div>
<?php endif; ?>

<?php foreach ($byMonth as $month => $events): ?>
  <h2 class="events-month rise" style="--i:<?= min($n++, 8) ?>;"><?= e($month) ?></h2>
  <div class="events-list">
    <?php foreach ($events as $ev): $groups = event_group_names((int) $ev['id']); ?>
      <div class="panel card rise" id="e<?= (int) $ev['id'] ?>" style="--i:<?= min($n++, 8) ?>;">
        <?= render_event($ev) ?>
        <div class="events-for small muted"><?= $groups ? (count($groups) > 2 ? '<span title="' . e(implode(', ', $groups)) . '">Grupėms: ' . e(groups_summary($groups)) . '</span>' : 'Grupėms: ' . e(implode(', ', $groups))) : (!empty($ev['invited_only']) ? 'Pakviestiems' : 'Visoms grupėms') ?><?= members_only_badge($ev) ?></div>
        <?= $rsvpMembers ? render_event_rsvp($ev, $rsvpMembers, 'renginiai.php' . ($filter ? '?tipas=' . $filter : '') . '#e' . (int) $ev['id']) : '' ?>
        <?php if ($rsvpMembers && $ev['type'] === 'competition' && varzybos_enabled()): ?>
          <p class="small" style="margin-top:8px;"><a href="<?= url('varzybos.php') ?>">Varžybų kategorijos ir registracija varžybų sistemoje →</a></p>
        <?php endif; ?>
        <?= event_staff_actions($ev, 'renginiai') ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>

<?php if ($past): ?>
  <h2 class="events-month rise" style="--i:<?= min($n++, 8) ?>;">Praėję renginiai</h2>
  <div class="events-list past">
    <?php foreach ($past as $ev): ?>
      <div class="panel card rise" id="e<?= (int) $ev['id'] ?>" style="--i:<?= min($n++, 8) ?>;"><?= render_event($ev) ?><?= event_staff_actions($ev, 'renginiai') ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php
page_end();
