<?php
// Vienas renginys: visas aprašymas, registracija su laikmačiu, dalyvių skaičius,
// egzaminui - testas, programa ir pamokos; klausimai Deniui (mato tik jis)
require __DIR__ . '/app/bootstrap.php';

$id = (int) get('id');
$ev = $id ? q_one('SELECT * FROM events WHERE id = ? AND ' . visibility_sql(), [$id]) : null;
if (!$ev) {
    not_found();
}
$acc = current_account();
$isStaff = is_staff();
$members = $acc && is_active_account($acc) && !$isStaff
    ? array_values(array_filter(account_members((int) $acc['id']), function ($m) { return $m['status'] === 'active'; })) : [];
$counts = event_participant_counts($id);
$going = $counts['going'] + $counts['late'];   // pavėlavę (net ir Deniui nepatvirtinus) - irgi dalyviai
$ts = strtotime($ev['starts_on']);
$when = fmt_date($ev['starts_on'], true) . ($ev['ends_on'] && $ev['ends_on'] !== $ev['starts_on'] ? ' – ' . fmt_date($ev['ends_on'], true) : '')
    . ($ev['start_time'] ? ', ' . fmt_time($ev['start_time']) : '');
$past = ($ev['ends_on'] ?: $ev['starts_on']) < date('Y-m-d');
$back = 'renginys.php?id=' . $id;

page_start($ev['title'], ['section' => 'renginiai', 'description' => $ev['summary'] ?: mb_strimwidth((string) $ev['description'], 0, 160, '…'),
    'og' => $ev['members_only'] ? null : ['type' => 'website', 'title' => $ev['title'], 'description' => $ev['summary'] ?: mb_strimwidth((string) $ev['description'], 0, 160, '…'),
        'url' => abs_url('renginys.php?id=' . $id), 'image' => abs_url('img/apie-1.jpg')]]);
?>
<a class="small" href="<?= url('renginiai.php#e' . $id) ?>">← Visi renginiai</a>
<article class="event-page">
  <header class="panel card ep-head">
    <div class="ep-date"><span class="d"><?= date('j', $ts) ?></span><span class="m"><?= e(mb_substr(LT_MONTHS_GEN[(int) date('n', $ts)], 0, 3)) ?></span></div>
    <div class="ep-title">
      <div class="row" style="gap:6px; flex-wrap:wrap;"><span class="badge type-badge t-<?= e($ev['type']) ?>"><?= e(EVENT_TYPES[$ev['type']]) ?></span><?= members_only_badge($ev) ?><?= $past ? ' <span class="badge">įvyko</span>' : '' ?></div>
      <h1><?= e($ev['title']) ?></h1>
      <div class="muted"><?= e($when) ?><?= $ev['location'] ? ' · ' . e($ev['location']) : '' ?></div>
      <div class="small muted" style="margin-top:4px;">Kam: <?= e(event_audience_label($ev)) ?><?= !$past && $going ? ' · Dalyvauja: <strong>' . (int) $going . '</strong>' : '' ?></div>
    </div>
  </header>

  <div class="ep-grid">
    <div class="stack">
      <div class="panel card">
        <?php if ($ev['summary']): ?><p class="ep-summary"><?= e($ev['summary']) ?></p><?php endif; ?>
        <?php if ($ev['description']): ?>
          <div class="news-body"><?= text_to_html($ev['description']) ?></div>
        <?php elseif (!$ev['summary']): ?>
          <p class="muted">Daugiau informacijos - netrukus.</p>
        <?php endif; ?>
        <?php if ($ev['location']): ?>
          <p class="ep-place">📍 <?= e($ev['location']) ?> · <a href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(rawurlencode($ev['location'])) ?>" target="_blank" rel="noopener">Žemėlapis ↗</a></p>
        <?php endif; ?>
      </div>

      <?php if ($ev['type'] === 'exam' && $members && !$past): ?>
        <!-- Egzaminui: testas, kito diržo programa, pamokos -->
        <div class="panel card ep-exam">
          <div class="kicker">Pasiruošimas egzaminui</div>
          <?php foreach ($members as $m): $st = exam_test_status($m); $next = next_belt($m['belt_level'] !== null ? (int) $m['belt_level'] : null); ?>
            <div class="ep-exam-row">
              <?php if (count($members) > 1): ?><strong><?= e($m['first_name']) ?></strong><?php endif; ?>
              <?php if ($next): ?><span class="small">Laiko: <?= belt_chip($next) ?></span><?php endif; ?>
              <span class="small"><?php if (!$st): ?>Testo šiam diržui nėra.<?php elseif ($st['passed']): ?>✓ Testas išlaikytas<?php else: ?>Testas: <?= $st['best'] !== null ? 'geriausias ' . (int) $st['best'] . ' %, reikia ' . (int) $st['quiz']['pass_percent'] . ' %' : 'dar nelaikytas' ?><?php endif; ?></span>
              <span class="row" style="gap:6px; flex-wrap:wrap;">
                <?php if ($st && !$st['passed']): ?><a class="btn btn-primary btn-sm" href="<?= url('testas.php?q=' . (int) $st['quiz']['id'] . '&m=' . (int) $m['id']) ?>">Laikyti testą</a><?php endif; ?>
                <a class="btn btn-ghost btn-sm" href="<?= url('egzaminas.php?m=' . (int) $m['id']) ?>">Egzamino programa</a>
                <?php if ($next): ?><a class="btn btn-ghost btn-sm" href="<?= url('pamokos.php?dirzas=' . $next) ?>">Pamokos egzaminui</a><?php endif; ?>
              </span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?= questions_box('event', $id, $back) ?>
    </div>

    <aside class="stack">
      <div class="panel card ep-reg">
        <div class="kicker">Registracija</div>
        <?php if ($past): ?>
          <p class="muted">Renginys jau įvyko.</p>
        <?php elseif ($members): ?>
          <?php $rsvp = render_event_rsvp($ev, $members, $back); ?>
          <?= $rsvp ?: '<p class="muted small">Šis renginys skirtas kitoms grupėms' . (!empty($ev['invited_only']) ? ' (tik pakviestiems)' : '') . '.</p>' . event_deadline_html($ev) ?>
        <?php elseif ($isStaff): ?>
          <?= event_deadline_html($ev) ?>
          <p class="small">Dalyvauja: <strong><?= (int) $going ?></strong><?= $counts['late'] ? ' (iš jų pavėlavę: ' . (int) $counts['late'] . ')' : '' ?> · pakviesti, neatsakė: <?= (int) $counts['invited'] ?> · negalės: <?= (int) $counts['declined'] ?></p>
        <?php elseif ($acc): ?>
          <p class="muted small">Registruotis galės patvirtinti nariai.</p>
        <?php else: ?>
          <?= event_deadline_html($ev) ?>
          <p class="small"><a href="<?= url('prisijungti.php?r=' . rawurlencode(url($back))) ?>">Prisijunkite</a>, kad užsiregistruotumėte.</p>
        <?php endif; ?>
      </div>
      <?php if ($ev['type'] === 'competition' && $members && varzybos_enabled()): ?>
        <a class="panel card small" href="<?= url('varzybos.php') ?>" style="display:block;">🥋 Varžybų kategorijos ir registracija varžybų sistemoje →</a>
      <?php endif; ?>
      <?= event_staff_actions($ev, 'renginiai') ?>
    </aside>
  </div>
</article>
<?php
page_end();
