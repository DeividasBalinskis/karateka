<?php
// Visi renginiai: artėjantys pagal mėnesius ir praėję (paskutiniai 12 mėn.). Filtras pagal tipą: ?tipas=egzaminai
require __DIR__ . '/app/bootstrap.php';

$filter = isset(EVENT_TYPE_FILTERS[get('tipas')]) ? get('tipas') : '';
$type = $filter ? EVENT_TYPE_FILTERS[$filter][0] : null;
// Vaizdas: sąrašas (numatytas) arba mėnesio kalendorius (?vaizdas=kalendorius&men=2026-11)
$cal = get('vaizdas') === 'kalendorius';
$ym = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', get('men')) ? get('men') : date('Y-m');
$qs = function (array $p) use ($filter, $cal, $ym) {
    $q = array_filter(['tipas' => $filter, 'vaizdas' => $cal ? 'kalendorius' : '', 'men' => $cal && $ym !== date('Y-m') ? $ym : ''] + [], 'strlen');
    $q = array_filter(array_merge($q, $p), 'strlen');
    return url('renginiai.php' . ($q ? '?' . http_build_query($q) : ''));
};

$allUpcoming = public_upcoming_events(200);
$upcoming = $type ? array_values(array_filter($allUpcoming, function ($ev) use ($type) { return $ev['type'] === $type; })) : $allUpcoming;
$past = q_all('SELECT e.* FROM events e WHERE COALESCE(e.ends_on, e.starts_on) < CURDATE()
                 AND e.starts_on >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) AND ' . event_main_sql('e') . ' AND ' . visibility_sql('e')
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
    $m = month_names()[(int) date('n', $ts)];
    $byMonth[mb_strtoupper(mb_substr($m, 0, 1)) . mb_substr($m, 1) . ' ' . date('Y', $ts)][] = $ev;
}

page_start(t('Renginiai'), ['description' => t('VšĮ Karate Ateitis renginiai: egzaminai, varžybos, seminarai ir stovyklos.'), 'section' => 'renginiai']);
$n = 0;   // eilės nr. animacijai (laipteliai)
?>
<nav class="crumbs"><a href="<?= url('naujienos.php') ?>"><?= te('← Naujienos ir renginiai') ?></a></nav>
<div class="page-head">
  <div class="eyebrow"><?= te('Kalendorius') ?></div>
  <h1 class="styled"><?= te('Renginiai') ?></h1>
</div>
<?= staff_bar([[t('+ Naujas renginys'), 'admin/renginiai.php?edit=new&back=renginiai', true]]) ?>
<div class="ev-views rise">
  <nav class="seg-links" aria-label="<?= te('Vaizdas') ?>">
    <a href="<?= e($qs(['vaizdas' => '', 'men' => ''])) ?>" class="<?= $cal ? '' : 'active' ?>"><?= te('☰ Sąrašas') ?></a>
    <a href="<?= e($qs(['vaizdas' => 'kalendorius'])) ?>" class="<?= $cal ? 'active' : '' ?>"><?= te('📅 Kalendorius') ?></a>
  </nav>
  <?php $webcal = preg_replace('~^https?://~', 'webcal://', abs_url('kalendorius.php')); ?>
  <!-- Ne visi telefonai supranta webcal:// (pvz. Samsung) - todėl pasirinkimas: Google, iPhone, failas -->
  <details class="ev-ics small">
    <summary title="<?= te('Visi renginiai atsiras jūsų telefono ar Google kalendoriuje ir atsinaujins patys') ?>"><?= te('📲 Į telefono kalendorių') ?></summary>
    <div class="ev-ics-menu">
      <a href="https://calendar.google.com/calendar/render?cid=<?= e(rawurlencode($webcal)) ?>" target="_blank" rel="noopener"><?= te('Google kalendorius (Android, Samsung)') ?></a>
      <a href="<?= e($webcal) ?>"><?= te('iPhone / Apple kalendorius') ?></a>
      <a href="<?= e(url('kalendorius.php?failas=1')) ?>"><?= te('Atsisiųsti failą (.ics)') ?></a>
      <span class="muted"><?= te('Google kalendoriuje renginiai atsinaujins patys; atsisiųstas failas - tik dabartiniai renginiai.') ?></span>
    </div>
  </details>
</div>

<?php if ($invites): ?>
  <div class="panel card invites-box rise" id="kvietimai">
    <div class="kicker"><?= te('Treneris kviečia - atsakykite') ?></div>
    <?php foreach ($invites as $ev): ?>
      <div class="invite-item">
        <?= render_event($ev) ?>
        <?= render_event_rsvp($ev, $rsvpMembers, 'renginiai.php#kvietimai') ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<nav class="type-filter rise" aria-label="<?= te('Renginių tipas') ?>">
  <a href="<?= e($qs(['tipas' => ''])) ?>" class="<?= $filter === '' ? 'active' : '' ?>"><?= te('Visi') ?><span class="tf-count"><?= count($allUpcoming) ?></span></a>
  <?php foreach (EVENT_TYPE_FILTERS as $slug => [$t, $label]): ?>
    <a href="<?= e($qs(['tipas' => $slug])) ?>" class="t-<?= e($t) ?><?= $filter === $slug ? ' active' : '' ?>"><i class="tf-dot"></i><?= e(t($label)) ?><span class="tf-count"><?= (int) ($typeCounts[$t] ?? 0) ?></span></a>
  <?php endforeach; ?>
</nav>

<?php if ($cal):
    // ---------- Mėnesio kalendorius: visi to mėnesio renginiai (ir kelių dienų) langeliuose; apačioje - tie patys sąrašu ----------
    $first = $ym . '-01';
    $last = date('Y-m-t', strtotime($first));
    $monthEvents = q_all('SELECT e.* FROM events e WHERE e.starts_on <= ? AND COALESCE(e.ends_on, e.starts_on) >= ? AND ' . event_main_sql('e') . ' AND ' . visibility_sql('e')
        . ($type ? ' AND e.type = ' . db()->quote($type) : '') . ' ORDER BY e.starts_on, e.start_time', [$last, $first]);
    $byDay = [];
    foreach ($monthEvents as $ev) {
        for ($d = max($ev['starts_on'], $first); $d <= min(event_last_day($ev), $last); $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            $byDay[$d][] = $ev;
        }
    }
    $ts = strtotime($first);
    $mName = month_names()[(int) date('n', $ts)];
    $lead = (int) date('N', $ts) - 1;   // pirmadienis - pirma diena
    $days = (int) date('t', $ts);
    $prev = date('Y-m', strtotime($first . ' -1 month'));
    $next = date('Y-m', strtotime($first . ' +1 month'));
    $wd = current_lang() === 'en' ? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] : (current_lang() === 'ru' ? ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'] : ['Pr', 'An', 'Tr', 'Kt', 'Pn', 'Št', 'Sk']);
?>
  <section class="panel card ev-cal rise" aria-label="<?= te('Renginių kalendorius') ?>">
    <div class="ev-cal-head">
      <a class="btn btn-ghost btn-sm" href="<?= e($qs(['men' => $prev])) ?>" aria-label="<?= te('Ankstesnis mėnuo') ?>">←</a>
      <h2><?= e(mb_strtoupper(mb_substr($mName, 0, 1)) . mb_substr($mName, 1) . ' ' . date('Y', $ts)) ?></h2>
      <a class="btn btn-ghost btn-sm" href="<?= e($qs(['men' => $next])) ?>" aria-label="<?= te('Kitas mėnuo') ?>">→</a>
      <?php if ($ym !== date('Y-m')): ?><a class="small" href="<?= e($qs(['men' => ''])) ?>"><?= te('Šiandien') ?></a><?php endif; ?>
    </div>
    <div class="ev-grid">
      <?php foreach ($wd as $w): ?><div class="ev-wd"><?= e($w) ?></div><?php endforeach; ?>
      <?php for ($i = 0; $i < $lead; $i++): ?><div class="ev-day out"></div><?php endfor; ?>
      <?php for ($d = 1; $d <= $days; $d++): $date = sprintf('%s-%02d', $ym, $d); $list = $byDay[$date] ?? []; ?>
        <div class="ev-day<?= $date === date('Y-m-d') ? ' today' : '' ?><?= $list ? ' has' : '' ?><?= $date < date('Y-m-d') ? ' past' : '' ?>">
          <span class="ev-dn"><?= $d ?></span>
          <?php foreach ($list as $ev): ?>
            <a class="ev-chip type-badge t-<?= e($ev['type']) ?>" href="<?= url('renginys.php?id=' . (int) $ev['id']) ?>" title="<?= e(tr_text($ev['title'])) ?>"><?= $ev['start_time'] && $ev['starts_on'] === $date ? '<b>' . e(fmt_time($ev['start_time'])) . '</b> ' : '' ?><?= e(tr_text($ev['title'])) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endfor; ?>
    </div>
  </section>
  <?php if ($monthEvents): ?>
    <div class="events-list ev-cal-list">
      <?php foreach ($monthEvents as $ev): ?>
        <div class="panel card rise" id="e<?= (int) $ev['id'] ?>"><?= render_event($ev) ?><?= event_staff_actions($ev, 'renginiai') ?></div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="panel card rise"><p class="muted"><?= te('Šį mėnesį renginių nėra.') ?></p></div>
  <?php endif; ?>
<?php page_end(); exit; endif; ?>

<?php if (!$upcoming): ?>
  <div class="panel card rise"><p class="muted"><?= $type ? t('Artėjančių renginių šioje kategorijoje nėra.') : t('Artėjančių renginių šiuo metu nėra.') ?></p></div>
<?php endif; ?>

<?php foreach ($byMonth as $month => $events): ?>
  <h2 class="events-month rise" style="--i:<?= min($n++, 8) ?>;"><?= e($month) ?></h2>
  <div class="events-list">
    <?php foreach ($events as $ev): $groups = event_group_names((int) $ev['id']); ?>
      <div class="panel card rise" id="e<?= (int) $ev['id'] ?>" style="--i:<?= min($n++, 8) ?>;">
        <?= render_event($ev) ?>
        <div class="events-for small muted"><?php if ($groups && count($groups) > 3): /* v1.86: ilgas sąrašas - suskleistas */ ?><?= te('Grupėms:') ?> <?= e(implode(', ', array_slice($groups, 0, 3))) ?><span class="eg-rest" hidden>, <?= e(implode(', ', array_slice($groups, 3))) ?></span> <button type="button" class="eg-more" onclick="this.previousElementSibling.hidden=false; this.remove();"><?= te('+{n} grupės', ['n' => count($groups) - 3]) ?></button><?php else: ?><?= $groups ? (t('Grupėms:') . ' ') . e(implode(', ', $groups)) : (!empty($ev['invited_only']) ? t('Pakviestiems') : t('Visiems')) ?><?php endif; ?><?= members_only_badge($ev) ?></div>
        <?= $rsvpMembers ? render_event_rsvp($ev, $rsvpMembers, 'renginiai.php' . ($filter ? '?tipas=' . $filter : '') . '#e' . (int) $ev['id']) : '' ?>
        <?php if ($rsvpMembers && $ev['type'] === 'competition' && varzybos_enabled()): ?>
          <p class="small" style="margin-top:8px;"><a href="<?= url('varzybos.php') ?>"><?= te('Varžybų kategorijos ir registracija varžybų sistemoje →') ?></a></p>
        <?php endif; ?>
        <?= event_staff_actions($ev, 'renginiai') ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>

<?php if ($past): ?>
  <h2 class="events-month rise" style="--i:<?= min($n++, 8) ?>;"><?= te('Praėję renginiai') ?></h2>
  <div class="events-list past">
    <?php foreach ($past as $ev): ?>
      <div class="panel card rise" id="e<?= (int) $ev['id'] ?>" style="--i:<?= min($n++, 8) ?>;"><?= render_event($ev) ?><?= event_staff_actions($ev, 'renginiai') ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php
page_end();
