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

// Kiek artėjančių renginių kiekvieno tipo (skaičius ant filtro mygtuko)
$typeCounts = array_count_values(array_column($allUpcoming, 'type'));

$byMonth = [];
foreach ($upcoming as $ev) {
    $ts = strtotime($ev['starts_on']);
    $m = LT_MONTHS[(int) date('n', $ts)];
    $byMonth[mb_strtoupper(mb_substr($m, 0, 1)) . mb_substr($m, 1) . ' ' . date('Y', $ts)][] = $ev;
}

page_start('Renginiai', ['description' => 'VšĮ Karate Ateitis renginiai: egzaminai, varžybos, seminarai ir stovyklos.']);
$n = 0;   // eilės nr. animacijai (laipteliai)
?>
<div class="page-head">
  <div class="eyebrow">Kalendorius</div>
  <h1 class="styled">Renginiai</h1> <?= staff_link('admin/renginiai.php?edit=new&back=renginiai', '+ Naujas renginys') ?>
</div>

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
      <div class="panel card rise has-edit" id="e<?= (int) $ev['id'] ?>" style="--i:<?= min($n++, 8) ?>;">
        <?= event_edit_link((int) $ev['id'], 'renginiai') ?>
        <?= render_event($ev) ?>
        <div class="events-for small muted"><?= $groups ? 'Grupėms: ' . e(implode(', ', $groups)) : 'Visiems' ?><?= members_only_badge($ev) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>

<?php if ($past): ?>
  <h2 class="events-month rise" style="--i:<?= min($n++, 8) ?>;">Praėję renginiai</h2>
  <div class="events-list past">
    <?php foreach ($past as $ev): ?>
      <div class="panel card rise has-edit" id="e<?= (int) $ev['id'] ?>" style="--i:<?= min($n++, 8) ?>;"><?= event_edit_link((int) $ev['id'], 'renginiai') ?><?= render_event($ev) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php
page_end();
