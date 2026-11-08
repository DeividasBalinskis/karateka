<?php
// Treniruotė: pasirenki grupę ar renginį (bet kuriai dienai), pažymi, kas buvo, parašai pastabas, skiri XP
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

/** El. laiškas vaikui / tėvams apie naują pastabą */
function notify_note(array $m, string $text, string $date, array $me): void
{
    foreach (q_all('SELECT a.* FROM accounts a JOIN account_members am ON am.account_id = a.id WHERE am.member_id = ? AND a.status = "active"', [$m['id']]) as $acc) {
        send_mail($acc['email'], "Trenerio pastaba: {$m['first_name']}",
            "Sveiki,\n\ntreneris {$me['first_name']} paliko pastabą ({$m['first_name']}, " . fmt_date($date, true) . "):\n\n$text\n\n"
            . 'Visas pastabas matysite paskyroje: ' . abs_url('paskyra.php?m=' . $m['id']));
    }
}

/** Prisegtos pamokos ID (tik jei tokia pamoka yra) */
function note_lesson_id($raw): ?int
{
    $id = (int) $raw;
    return $id && q_value('SELECT 1 FROM lessons WHERE id = ?', [$id]) ? $id : null;
}

function save_coach_note(array $m, array $me, string $date, string $text, ?string $yt, ?int $lessonId, bool $isTask, ?int $groupId = null, ?int $eventId = null): void
{
    if ($text === '') {
        $text = $lessonId ? t('Pažiūrėk prisegtą pamoką.') : t('Pažiūrėk video.');
    }
    q('INSERT INTO coach_notes (member_id, author_id, group_id, event_id, note_date, body, youtube_id, lesson_id, is_task) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$m['id'], $me['id'], $groupId, $eventId, $date, $text, $yt, $lessonId, $isTask ? 1 : 0]);
}

// Paskutinė pasirinkta grupė įsimenama, kad salėje nereikėtų rinktis kiekvieną kartą
$groupId = (int) (get('g') ?: post('g') ?: ($_SESSION['notes_group'] ?? 0));
$date = get('d') ?: post('d') ?: date('Y-m-d');
if (!DateTime::createFromFormat('!Y-m-d', $date)) {
    $date = date('Y-m-d');
}
// Renginys (pvz. rinktinės treniruotė, seminaras) vietoj grupės: lankomumas - renginio dalyviams
$eventId = (int) (get('e') ?: post('e'));
$event = $eventId ? q_one('SELECT * FROM events WHERE id = ?', [$eventId]) : null;
if ($event) {
    $groupId = 0;
    if (!get('d') && !post('d')) {
        $evEnd = $event['ends_on'] ?: $event['starts_on'];
        $date = date('Y-m-d') >= $event['starts_on'] && date('Y-m-d') <= $evEnd ? date('Y-m-d') : $event['starts_on'];
    }
}
$group = $groupId ? q_one('SELECT * FROM training_groups WHERE id = ?', [$groupId]) : null;
if ($group) {
    $_SESSION['notes_group'] = $groupId;
}
$self = 'admin/treniruote.php?' . ($event ? 'e=' . $eventId : 'g=' . $groupId) . '&d=' . $date;

if (is_post()) {
    csrf_check();

    if (post('action') === 'delete') {
        q('DELETE FROM coach_notes WHERE id = ?', [(int) post('note_id')]);
        flash('ok', t('Pastaba ištrinta.'));
        redirect($self);
    }

    // XP vienam nariui („+XP“ prie vardo). Atsakymas JSON.
    if (post('action') === 'xp') {
        header('Content-Type: application/json; charset=utf-8');
        $cat = q_one('SELECT * FROM point_categories WHERE id = ? AND is_active = 1', [(int) post('category_id')]);
        $pts = (int) post('points');
        $mid = (int) post('member_id');
        if (!$cat || $pts < 1 || $pts > 1000) {
            exit(json_encode(['ok' => false, 'error' => t('Pasirinkite, už ką, ir kiek XP (1-1000).')], JSON_UNESCAPED_UNICODE));
        }
        if (!award_xp_to_members([$mid], $cat, $pts, $date, post('note') ?: null, (int) $me['id'])) {
            exit(json_encode(['ok' => false, 'error' => t('Narys nerastas.')], JSON_UNESCAPED_UNICODE));
        }
        exit(json_encode(['ok' => true, 'line' => '✨ +' . $pts . ' XP · ' . $cat['name'] . (post('note') !== '' ? ' · ' . mb_strimwidth(post('note'), 0, 80, '…') : ''),
            'total' => member_points_total($mid)], JSON_UNESCAPED_UNICODE));
    }

    // Renginio lankomumas: vienas dalyvis arba visi nepažymėti dalyvaujantys - „buvo“. Atsakymas JSON.
    if (in_array(post('action'), ['ev_attendance', 'ev_attendance_all'], true) && $event) {
        header('Content-Type: application/json; charset=utf-8');
        if (post('action') === 'ev_attendance') {
            $mid = (int) post('member_id');
            if (q_value('SELECT 1 FROM members WHERE id = ? AND status = "active"', [$mid])) {
                set_event_attendance($event, $mid, post('present') === '' ? null : (post('present') === '1' ? 1 : 0), (int) $me['id']);
            }
        } else {
            foreach (q('SELECT member_id FROM event_participants WHERE event_id = ? AND status IN ("going", "late") AND attended IS NULL', [$eventId])->fetchAll(PDO::FETCH_COLUMN) as $mid) {
                set_event_attendance($event, (int) $mid, 1, (int) $me['id']);
            }
        }
        $rows = q('SELECT member_id, attended FROM event_participants WHERE event_id = ? AND attended IS NOT NULL', [$eventId])->fetchAll(PDO::FETCH_KEY_PAIR);
        exit(json_encode(['ok' => true, 'marks' => $rows, 'present' => count(array_filter($rows)),
            'total' => (int) q_value('SELECT COUNT(*) FROM event_participants WHERE event_id = ? AND status <> "declined"', [$eventId])]));
    }

    // Atėjo, nors nebuvo sąraše - pridedamas ir pažymimas „Buvo“
    if (post('action') === 'ev_add' && $event) {
        $mid = (int) post('member_id');
        if (q_value('SELECT 1 FROM members WHERE id = ? AND status = "active"', [$mid])) {
            set_event_attendance($event, $mid, 1, (int) $me['id']);
            flash('ok', t('Pridėta ir pažymėta „Buvo“.'));
        }
        redirect($self);
    }

    // Lankomumas: vienas narys (buvo / nebuvo / nuimti žymą) arba visi nepažymėti - "buvo". Atsakymas JSON.
    if (in_array(post('action'), ['attendance', 'attendance_all'], true) && $group) {
        header('Content-Type: application/json; charset=utf-8');
        $mark = function (int $mid, int $present) use ($date, $groupId, $me) {
            q('INSERT INTO attendance (member_id, training_date, group_id, present, marked_by) VALUES (?, ?, ?, ?, ?)
               ON DUPLICATE KEY UPDATE present = VALUES(present), group_id = VALUES(group_id), marked_by = VALUES(marked_by)',
                [$mid, $date, $groupId, $present, $me['id']]);
        };
        $ids = q('SELECT id FROM members WHERE group_id = ? AND status = "active"', [$groupId])->fetchAll(PDO::FETCH_COLUMN);
        // Kartu skiriami / nuimami taškai už lankomumą (Treneriams -> Taškai: "Treniruotės lankymas")
        if (post('action') === 'attendance') {
            $mid = (int) post('member_id');
            if (in_array((string) $mid, array_map('strval', $ids), true)) {
                if (post('present') === '') {
                    q('DELETE FROM attendance WHERE member_id = ? AND training_date = ?', [$mid, $date]);
                } else {
                    $mark($mid, post('present') === '1' ? 1 : 0);
                }
                sync_attendance_points($mid, $date, post('present') === '1', (int) $me['id']);
            }
        } else {
            foreach ($ids as $mid) {
                q('INSERT IGNORE INTO attendance (member_id, training_date, group_id, present, marked_by) VALUES (?, ?, ?, 1, ?)',
                    [$mid, $date, $groupId, $me['id']]);
            }
            foreach (q('SELECT member_id FROM attendance WHERE training_date = ? AND group_id = ? AND present = 1', [$date, $groupId])->fetchAll(PDO::FETCH_COLUMN) as $mid) {
                sync_attendance_points((int) $mid, $date, true, (int) $me['id']);
            }
        }
        $rows = q('SELECT member_id, present FROM attendance WHERE training_date = ? AND member_id IN (SELECT id FROM members WHERE group_id = ? AND status = "active")',
            [$date, $groupId])->fetchAll(PDO::FETCH_KEY_PAIR);
        exit(json_encode(['ok' => true, 'marks' => $rows, 'present' => count(array_filter($rows)), 'total' => count($ids)]));
    }

    // Viena pastaba (paspaudus Enter) - atsakymas JSON, puslapis neperkraunamas
    if (post('action') === 'save_one' && ($group || $event)) {
        header('Content-Type: application/json; charset=utf-8');
        $text = post('text');
        $link = post('video');
        $yt = $link !== '' ? youtube_id($link) : null;
        $lessonId = note_lesson_id(post('lesson_id'));
        $isTask = !empty($_POST['is_task']);
        $m = q_one('SELECT * FROM members WHERE id = ? AND status = "active"', [(int) post('member_id')]);
        if ($link !== '' && !$yt) {
            exit(json_encode(['ok' => false, 'error' => 'Neatpažinta YouTube nuoroda']));
        }
        if (!$m || ($text === '' && !$yt && !$lessonId)) {
            exit(json_encode(['ok' => false, 'error' => 'Tuščia pastaba']));
        }
        save_coach_note($m, $me, $date, $text, $yt, $lessonId, $isTask, $groupId ?: null, $event ? $eventId : null);
        if (!empty($_POST['notify'])) {
            notify_note($m, $text, $date, $me);
        }
        exit(json_encode(['ok' => true, 'preview' => mb_strimwidth($text, 0, 120, '…') . ($yt ? ' ▶' : '') . ($lessonId ? ' · pamoka' : '') . ($isTask ? ' · užduotis' : '')], JSON_UNESCAPED_UNICODE));
    }

    if (post('action') === 'save' && ($group || $event)) {
        $saved = 0;
        $badLinks = [];
        $notify = !empty($_POST['notify']);
        foreach ((array) ($_POST['note'] ?? []) as $mid => $text) {
            $text = is_string($text) ? trim($text) : '';
            $link = trim((string) ($_POST['video'][$mid] ?? ''));
            $lessonId = note_lesson_id($_POST['lesson'][$mid] ?? '');
            $isTask = !empty($_POST['task'][$mid]);
            if ($text === '' && $link === '' && !$lessonId) {
                continue;
            }
            $yt = null;
            if ($link !== '') {
                $yt = youtube_id($link);
                if (!$yt) {
                    $badLinks[] = $link;
                }
            }
            $m = q_one('SELECT * FROM members WHERE id = ? AND status = "active"', [(int) $mid]);
            if (!$m) {
                continue;
            }
            save_coach_note($m, $me, $date, $text, $yt, $lessonId, $isTask, $groupId ?: null, $event ? $eventId : null);
            $saved++;

            if ($notify) {
                notify_note($m, $text, $date, $me);
            }
        }
        if ($badLinks) {
            flash('err', (t('Neatpažintos YouTube nuorodos (pastabos išsaugotos be video):') . ' ') . implode(', ', $badLinks));
        }
        flash('ok', $saved ? "Išsaugota pastabų: $saved" : t('Nieko neįrašyta.'));
        redirect($self);
    }
}

$members = $group ? q_all('SELECT * FROM members WHERE group_id = ? AND status = "active" ORDER BY last_name, first_name', [$groupId]) : [];
if ($event) {
    // Renginio dalyviai: dalyvaujantys ir pavėlavę, po jų - neatsakę ir negalintys (gal vis tiek atėjo)
    $members = q_all('SELECT m.*, ep.status AS ep_status, ep.attended FROM event_participants ep JOIN members m ON m.id = ep.member_id
                       WHERE ep.event_id = ? AND m.status = "active"
                       ORDER BY FIELD(ep.status, "going", "late", "invited", "declined"), m.last_name, m.first_name', [$eventId]);
}
// Pamokos prisegimui (pagal diržą)
$lessonOptions = ('<option value="">' . t('+ Prisegti pamoką') . '</option>');
foreach (q_all('SELECT id, title, belt_level FROM lessons ORDER BY belt_level IS NOT NULL, belt_level, title') as $ls) {
    $lessonOptions .= '<option value="' . (int) $ls['id'] . '">' . e(($ls['belt_level'] ? BELTS[(int) $ls['belt_level']][0] . ' · ' : '') . $ls['title']) . '</option>';
}
// Šios treniruotės lankomumo žymos: [member_id => 1 buvo / 0 nebuvo]
$att = $group ? q('SELECT member_id, present FROM attendance WHERE training_date = ? AND group_id = ?', [$date, $groupId])->fetchAll(PDO::FETCH_KEY_PAIR) : [];
if ($event) {
    $att = [];
    foreach ($members as $m) {
        if ($m['attended'] !== null) {
            $att[$m['id']] = (int) $m['attended'];
        }
    }
}
// Ar šią dieną grupė turi treniruotę pagal tvarkaraštį
$hasTraining = $group ? (bool) q_value('SELECT 1 FROM schedule WHERE group_id = ? AND weekday = ?', [$groupId, (int) date('N', strtotime($date))]) : false;
$todayNotes = [];
if ($group || $event) {
    $notesSql = $event
        ? ['SELECT cn.*, a.first_name AS author FROM coach_notes cn LEFT JOIN accounts a ON a.id = cn.author_id WHERE cn.event_id = ? ORDER BY cn.created_at', [$eventId]]
        : ['SELECT cn.*, a.first_name AS author FROM coach_notes cn JOIN members m ON m.id = cn.member_id LEFT JOIN accounts a ON a.id = cn.author_id
             WHERE m.group_id = ? AND cn.note_date = ? ORDER BY cn.created_at', [$groupId, $date]];
    foreach (q_all($notesSql[0], $notesSql[1]) as $n) {
        $todayNotes[$n['member_id']][] = $n;
    }
}
// Šią dieną treneriu skirtas XP (ne lankomumo ir ne renginių) - matosi prie vardo
$todayXp = [];
if ($members) {
    foreach (q_all('SELECT pa.*, pc.name AS category_name FROM point_awards pa JOIN point_categories pc ON pc.id = pa.category_id
                     WHERE pa.awarded_on = ? AND pa.event_id IS NULL AND (pc.code IS NULL OR pc.code NOT IN ("attendance", "skill", "test_attempt", "test_pass"))
                       AND pa.member_id IN (' . implode(',', array_map('intval', array_column($members, 'id'))) . ') ORDER BY pa.id', [$date]) as $x) {
        $todayXp[$x['member_id']][] = $x;
    }
}
$xpCats = manual_xp_categories();
// Renginiai šią dieną ir neseniai vykę, kuriuose dar nepažymėta, kas buvo
$dayEvents = events_on_date($date);
$markEvents = array_values(array_filter(events_to_mark(), function ($ev) use ($dayEvents) {
    return !in_array((int) $ev['id'], array_map('intval', array_column($dayEvents, 'id')), true);
}));
$recent = $group && !$event ? q_all('SELECT cn.*, m.first_name, m.last_name, a.first_name AS author FROM coach_notes cn JOIN members m ON m.id = cn.member_id LEFT JOIN accounts a ON a.id = cn.author_id
                           WHERE m.group_id = ? AND cn.note_date <> ? ORDER BY cn.note_date DESC, cn.id DESC LIMIT 20', [$groupId, $date]) : [];

page_start(t('Treniruotė'), ['admin' => true]);
?>
<div class="page-head">
  <h1 class="styled"><?= te('Treniruotė') ?></h1>
  <p><?= te('Pasirinkite grupę ar renginį ir dieną (galima ir praėjusią), pažymėkite, kas buvo, parašykite pastabą ar skirkite XP.') ?></p>
</div>

<?php
// Grupės - spalvoti kvadratai: pirma tos, kurios treniruojasi pasirinktą dieną (su laiku), po to visos pagal kategoriją
$tileGroups = q_all('SELECT g.*, (SELECT COUNT(*) FROM members m WHERE m.group_id = g.id AND m.status = "active") AS members_count,
                            (SELECT MIN(s.start_time) FROM schedule s WHERE s.group_id = g.id AND s.weekday = ?) AS day_time
                       FROM training_groups g WHERE g.is_active = 1 ORDER BY g.sort_order, g.name', [(int) date('N', strtotime($date))]);
$tile = function (array $g) use ($group, $date): string {
    $on = $group && (int) $group['id'] === (int) $g['id'];
    return '<a class="group-tile' . ($on ? ' on' : '') . '" style="--gc:' . e(group_color($g)) . '" href="' . e(url('admin/treniruote.php?g=' . (int) $g['id'] . '&d=' . $date)) . '"'
        . ($on ? ' aria-current="true"' : '') . '><span class="gt-name">' . e(group_short_name($g)) . '</span>'
        . '<span class="gt-meta">' . ($g['day_time'] ? '<b>' . e(fmt_time($g['day_time'])) . '</b> · ' : '') . (int) $g['members_count'] . ' nar.</span>'
        . (training_cancelled((int) $g['id'], $date) ? ('<span class="gt-off">' . t('atšaukta') . '</span>') : '') . '</a>';
};
$todayTiles = array_filter($tileGroups, function ($g) { return $g['day_time'] !== null; });
usort($todayTiles, function ($x, $y) { return strcmp((string) $x['day_time'], (string) $y['day_time']); });
// Renginio plytelė: renginio tipo spalva, data, kiek dalyvių pažymėta
$evColors = ['exam' => '#2F6B5A', 'competition' => '#7A3558', 'seminar' => '#1F527F', 'camp' => '#8A4A0E', 'other' => '#4A453A'];
$evTile = function (array $ev, bool $showDate) use ($event, $evColors): string {
    $on = $event && (int) $event['id'] === (int) $ev['id'];
    $pc = event_participant_counts((int) $ev['id']);
    $marked = (int) q_value('SELECT COUNT(*) FROM event_participants WHERE event_id = ? AND attended IS NOT NULL', [(int) $ev['id']]);
    $meta = ($showDate ? '<b>' . e(fmt_date($ev['starts_on'])) . '</b> · ' : ($ev['start_time'] ? '<b>' . e(fmt_time($ev['start_time'])) . '</b> · ' : ''))
        . ($pc['going'] + $pc['late']) . ' ' . t('dalyv.') . ($marked ? ' · ' . t('pažymėta') . ' ' . $marked : '');
    return '<a class="group-tile event-tile' . ($on ? ' on' : '') . '" style="--gc:' . e($evColors[$ev['type']] ?? '#4A453A') . '" href="'
        . e(url('admin/treniruote.php?e=' . (int) $ev['id'])) . '"' . ($on ? ' aria-current="true"' : '') . '>'
        . '<span class="gt-type">' . e(t(EVENT_TYPES[$ev['type']] ?? '')) . '</span><span class="gt-name">' . e($ev['title']) . '</span>'
        . '<span class="gt-meta">' . $meta . '</span></a>';
};
?>
<details class="panel card group-picker"<?= $group || $event ? '' : ' open' ?>>
  <summary>
    <?php if ($event): ?>
      <span class="gp-current"><span class="badge type-badge t-<?= e($event['type']) ?>"><?= e(t(EVENT_TYPES[$event['type']])) ?></span> <strong><?= e($event['title']) ?></strong></span>
      <span class="btn btn-ghost btn-sm"><?= te('Keisti') ?></span>
    <?php elseif ($group): ?>
      <span class="gp-current"><span class="group-dot" style="background:<?= e(group_color($group)) ?>"></span><strong><?= e($group['name']) ?></strong>
        <?php if ($off = training_cancelled((int) $group['id'], $date)): ?><span class="cancel-tag"><?= te('atšaukta ·') ?> <?= e($off['reason']) ?></span><?php endif; ?></span>
      <span class="btn btn-ghost btn-sm"><?= te('Keisti grupę') ?></span>
    <?php else: ?>
      <strong><?= te('Pasirinkite grupę ar renginį') ?></strong>
    <?php endif; ?>
  </summary>
  <form method="get" class="gp-date">
    <?php if ($group): ?><input type="hidden" name="g" value="<?= (int) $group['id'] ?>"><?php endif; ?>
    <label><?= te('Data') ?> <input type="date" name="d" value="<?= e($date) ?>" onchange="this.form.submit()"></label>
    <?php if ($date !== date('Y-m-d')): ?><a class="small" href="<?= url('admin/treniruote.php' . ($group ? '?g=' . (int) $group['id'] : '')) ?>"><?= te('Šiandien') ?></a><?php endif; ?>
    <a class="small" href="<?= url('admin/treniruote.php?' . ($group ? 'g=' . (int) $group['id'] . '&' : '') . 'd=' . date('Y-m-d', strtotime($date . ' -1 day'))) ?>"><?= te('← Diena atgal') ?></a>
    <?php if (is_admin()): ?><a class="small gp-edit" href="<?= url('admin/grupes.php') ?>"><?= te('✎ Grupių pavadinimai ir spalvos') ?></a><?php endif; ?>
  </form>
  <?php if ($dayEvents): ?>
    <div class="kicker"><?= $date === date('Y-m-d') ? t('Šiandien renginiai') : t('Renginiai šią dieną') ?></div>
    <div class="group-tiles"><?php foreach ($dayEvents as $ev) { echo $evTile($ev, false); } ?></div>
  <?php endif; ?>
  <?php if ($markEvents): ?>
    <div class="kicker"><?= te('Neseniai vyko - pažymėkite, kas buvo') ?></div>
    <div class="group-tiles"><?php foreach ($markEvents as $ev) { echo $evTile($ev, true); } ?></div>
  <?php endif; ?>
  <?php if ($todayTiles): ?>
    <div class="kicker"><?= $date === date('Y-m-d') ? t('Šiandien treniruojasi') : (t('Treniruojasi') . ' ') . e(mb_strtolower(weekday_names()[(int) date('N', strtotime($date))])) ?></div>
    <div class="group-tiles"><?php foreach ($todayTiles as $g) { echo $tile($g); } ?></div>
  <?php endif; ?>
  <?php foreach (array_map('t', GROUP_CATEGORIES) as $cat => $catLabel): $list = array_filter($tileGroups, function ($g) use ($cat) { return $g['category'] === $cat; }); if (!$list) { continue; } ?>
    <div class="kicker"><?= e($catLabel) ?></div>
    <div class="group-tiles"><?php foreach ($list as $g) { echo $tile($g); } ?></div>
  <?php endforeach; ?>
</details>

<?php if ($group && !$members): ?>
  <div class="panel card"><p class="muted"><?= te('Šioje grupėje aktyvių narių nėra.') ?></p></div>
<?php endif; ?>
<?php if ($event && !$members): ?>
  <div class="panel card"><p class="muted"><?= te('Šiame renginyje dalyvių sąrašas tuščias. Žemiau pridėkite, kas atėjo.') ?></p></div>
<?php endif; ?>

<?php if ($members): ?>
  <form method="post" class="panel card notes-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="g" value="<?= (int) $groupId ?>">
    <input type="hidden" name="e" value="<?= (int) $eventId ?>">
    <input type="hidden" name="d" value="<?= e($date) ?>">
    <h2><?= e($event ? $event['title'] : $group['name']) ?> · <?= e(fmt_date($date, true)) ?></h2>
    <div class="att-bar">
      <span><?= te('Buvo:') ?> <strong id="attPresent"><?= count(array_filter($att)) ?></strong> <?= te('iš') ?> <span id="attTotal"><?= $event ? count(array_filter($members, function ($m) { return $m['ep_status'] !== 'declined'; })) : count($members) ?></span></span>
      <button type="button" class="btn btn-primary btn-sm" id="attAll" data-action="<?= $event ? 'ev_attendance_all' : 'attendance_all' ?>"><?= $event ? t('✓ Visi dalyvaujantys buvo') : t('✓ Visi buvo') ?></button>
      <a class="btn btn-ghost btn-sm" href="<?= url('admin/taskai.php?' . ($event ? 'e=' . $eventId : 'g=' . $groupId) . '&d=' . $date . '#skirti') ?>"><?= te('✨ XP keliems') ?></a>
      <?php if ($group && !$hasTraining && !training_cancelled($groupId, $date)): ?><span class="hint"><?= te('Pagal tvarkaraštį šią dieną šios grupės treniruotės nėra - patikrinkite datą.') ?></span><?php endif; ?>
      <?php if ($event): ?><a class="small" href="<?= url('admin/renginio-dalyviai.php?id=' . $eventId) ?>"><?= te('Dalyvių sąrašas →') ?></a><?php endif; ?>
    </div>
    <p class="hint" style="margin-bottom:10px;"><?= te('Lankomumas išsaugomas iškart paspaudus „Nebuvo“ / „Buvo“ (paspaudus dar kartą - žyma nuimama). Pastaba: parašykite ir spauskite') ?> <strong><?= te('Enter') ?></strong> <?= te('-
      vaikas ir tėvai ją iškart mato paskyroje („Svarbu“). „Užduotis“ - vaikas pažymės „Atlikau“.') ?></p>

    <?php foreach ($members as $m): ?>
      <div class="note-row">
        <div class="note-who" id="who<?= (int) $m['id'] ?>">
          <strong><button type="button" class="profile-link" data-member-profile="<?= (int) $m['id'] ?>"><?= e($m['first_name'] . ' ' . $m['last_name']) ?></button><?php if ($event && $m['ep_status'] !== 'going'): ?> <span class="badge"><?= e(t(PARTICIPANT_STATUS[$m['ep_status']] ?? $m['ep_status'])) ?></span><?php endif; ?></strong>
          <?php $a1 = $att[$m['id']] ?? null; ?>
          <div class="att" data-member="<?= (int) $m['id'] ?>">
            <?php // „Buvo“ - dešinėje: laikant telefoną dešine ranka, nykščiu lengviau pasiekti ?>
            <button type="button" class="att-btn no<?= $a1 === 0 || $a1 === '0' ? ' on' : '' ?>" data-v="0"><?= te('✗ Nebuvo') ?></button>
            <button type="button" class="att-btn yes<?= $a1 === 1 || $a1 === '1' ? ' on' : '' ?>" data-v="1"><?= te('✓ Buvo') ?></button>
            <button type="button" class="xp-btn" data-member="<?= (int) $m['id'] ?>" title="<?= te('Skirti XP') ?>"><?= te('+XP') ?></button>
          </div>
          <?php foreach ($todayXp[$m['id']] ?? [] as $x): ?>
            <div class="note-existing xp-line">✨ +<?= (int) $x['points'] ?> XP · <?= e($x['category_name']) ?><?= $x['note'] ? ' · ' . e(mb_strimwidth($x['note'], 0, 80, '…')) : '' ?></div>
          <?php endforeach; ?>
          <?php foreach ($todayNotes[$m['id']] ?? [] as $n): ?>
            <div class="note-existing">
              <span>✓ <?= e(mb_strimwidth($n['body'], 0, 120, '…')) ?><?= $n['youtube_id'] ? ' ▶' : '' ?><?= $n['lesson_id'] ? (' ' . t('· pamoka')) : '' ?><?= $n['is_task'] ? ($n['done_at'] ? (' ' . t('· užduotis ✓')) : (' ' . t('· užduotis'))) : '' ?></span>
              <button class="linklike danger-link" type="submit" form="del<?= (int) $n['id'] ?>" onclick="return confirm('Ištrinti šią pastabą?')"><?= te('ištrinti') ?></button>
            </div>
          <?php endforeach; ?>
        </div>
        <textarea name="note[<?= (int) $m['id'] ?>]" data-member="<?= (int) $m['id'] ?>" rows="2" enterkeyhint="send" placeholder="<?= te('Parašykite ir spauskite Enter') ?>"></textarea>
        <div class="note-extras">
          <select name="lesson[<?= (int) $m['id'] ?>]" aria-label="<?= te('Prisegti pamoką') ?>"><?= $lessonOptions ?></select>
          <label class="note-task" title="<?= te('Tai užduotis: narys paskyroje matys mygtuką „Atlikau“ ir pažymės, kai atliks') ?>"><input type="checkbox" name="task[<?= (int) $m['id'] ?>]" value="1"> <?= te('Užduotis') ?></label>
          <details class="note-video">
            <summary><?= te('+ YouTube nuoroda') ?></summary>
            <input type="url" name="video[<?= (int) $m['id'] ?>]" placeholder="https://youtu.be/...">
          </details>
        </div>
      </div>
    <?php endforeach; ?>

    <label class="check small notify"><input type="checkbox" name="notify" value="1"><span><?= te('Taip pat išsiųsti el. laišką (vaikui / tėvams)') ?></span></label>
    <div class="sticky-save">
      <button class="btn btn-primary btn-block" type="submit"><?= te('Išsaugoti pastabas') ?></button>
    </div>
  </form>

  <?php foreach ($todayNotes as $list): foreach ($list as $n): ?>
    <form method="post" id="del<?= (int) $n['id'] ?>" style="display:none;">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="note_id" value="<?= (int) $n['id'] ?>">
      <input type="hidden" name="g" value="<?= (int) $groupId ?>"><input type="hidden" name="e" value="<?= (int) $eventId ?>"><input type="hidden" name="d" value="<?= e($date) ?>">
    </form>
  <?php endforeach; endforeach; ?>
<?php endif; ?>

<?php if ($event):
    $inEvent = array_map('intval', array_column($members, 'id'));
    $addOptions = [];
    foreach (q_all('SELECT m.id, m.first_name, m.last_name, g.name AS group_name FROM members m LEFT JOIN training_groups g ON g.id = m.group_id
                     WHERE m.status = "active" ORDER BY g.sort_order IS NULL, g.sort_order, m.first_name, m.last_name') as $am) {
        if (!in_array((int) $am['id'], $inEvent, true)) {
            $addOptions[$am['group_name'] ?: t('Be grupės')][] = $am;
        }
    } ?>
  <?php if ($addOptions): ?>
  <form method="post" class="panel card form row ev-add">
    <?= csrf_field() ?><input type="hidden" name="action" value="ev_add"><input type="hidden" name="e" value="<?= (int) $eventId ?>"><input type="hidden" name="d" value="<?= e($date) ?>">
    <label style="flex:1;"><?= te('Atėjo, nors nebuvo sąraše') ?>
      <select name="member_id" required>
        <option value=""><?= te('— pasirinkite narį —') ?></option>
        <?php foreach ($addOptions as $gname => $list): ?>
          <optgroup label="<?= e($gname) ?>"><?php foreach ($list as $am): ?><option value="<?= (int) $am['id'] ?>"><?= e($am['first_name'] . ' ' . $am['last_name']) ?></option><?php endforeach; ?></optgroup>
        <?php endforeach; ?>
      </select></label>
    <button class="btn btn-primary" type="submit"><?= te('+ Pridėti ir pažymėti „Buvo“') ?></button>
  </form>
  <?php endif; ?>
<?php endif; ?>

<?php if ($members && $xpCats): ?>
  <div class="xp-quick" id="xpQuick" hidden>
    <label><?= te('Už ką') ?>
      <select id="xpCat"><?php foreach ($xpCats as $c): ?><option value="<?= (int) $c['id'] ?>" data-points="<?= (int) $c['points'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
    <label class="xp-pts"><?= te('XP') ?> <input type="number" id="xpPts" min="1" max="1000" value="<?= (int) $xpCats[0]['points'] ?>"></label>
    <label class="xp-note"><?= te('Komentaras') ?> <span class="hint"><?= te('nebūtina, mato narys') ?></span><input type="text" id="xpNote" maxlength="190"></label>
    <div class="row"><button type="button" class="btn btn-primary btn-sm" id="xpGive"><?= te('Skirti') ?></button><button type="button" class="btn btn-ghost btn-sm" id="xpCancel"><?= te('Atšaukti') ?></button>
      <a class="small" href="<?= url('admin/taskai.php#kategorijos') ?>"><?= te('+ nauja kategorija') ?></a></div>
  </div>
<?php endif; ?>

<?php if ($recent): ?>
  <div class="panel card">
    <h2><?= te('Ankstesnės pastabos šiai grupei') ?></h2>
    <ul class="list small">
      <?php foreach ($recent as $n): ?>
        <li class="row between">
          <span>
            <strong><?= e($n['first_name'] . ' ' . $n['last_name']) ?></strong> · <?= e(fmt_date($n['note_date'], true)) ?>
            <?= $n['read_at'] ? '<span class="badge badge-ok">perskaityta</span>' : '<span class="badge">neperskaityta</span>' ?>
            <?php if ($n['is_task']): ?><?= $n['done_at'] ? ('<span class="badge badge-ok">' . t('✓ atlikta') . '</span>') : ('<span class="badge badge-warn">' . t('užduotis neatlikta') . '</span>') ?><?php endif; ?>
            <div class="muted"><?= e($n['body']) ?><?= $n['youtube_id'] ? (' ' . t('▶ video')) : '' ?></div>
          </span>
          <form method="post" class="inline-form" onsubmit="return confirm('Ištrinti šią pastabą?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="note_id" value="<?= (int) $n['id'] ?>">
            <input type="hidden" name="g" value="<?= (int) $groupId ?>"><input type="hidden" name="d" value="<?= e($date) ?>">
            <button class="btn btn-danger btn-sm" type="submit" aria-label="<?= te('Ištrinti') ?>">✕</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
<script>
// Enter - iškart išsaugo to vaiko pastabą; Shift+Enter - nauja eilutė
(function () {
  var form = document.querySelector('.notes-form');
  if (!form) return;
  form.querySelectorAll('textarea[data-member]').forEach(function (ta) {
    ta.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' || e.shiftKey || e.isComposing) return;
      e.preventDefault();
      var mid = ta.dataset.member, text = ta.value.trim();
      var video = form.querySelector('[name="video[' + mid + ']"]');
      var lsel = form.querySelector('[name="lesson[' + mid + ']"]');
      if (!text && !(video && video.value.trim()) && !(lsel && lsel.value)) return;
      var data = new FormData();
      data.append('_csrf', form.querySelector('[name=_csrf]').value);
      data.append('action', 'save_one');
      data.append('g', form.querySelector('[name=g]').value);
      data.append('e', form.querySelector('[name=e]').value);
      data.append('d', form.querySelector('[name=d]').value);
      data.append('member_id', mid);
      data.append('text', text);
      data.append('video', video ? video.value.trim() : '');
      var lesson = form.querySelector('[name="lesson[' + mid + ']"]'), task = form.querySelector('[name="task[' + mid + ']"]');
      if (lesson && lesson.value) data.append('lesson_id', lesson.value);
      if (task && task.checked) data.append('is_task', '1');
      if (form.querySelector('[name=notify]').checked) data.append('notify', '1');
      ta.disabled = true;
      fetch(location.pathname, { method: 'POST', body: data, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          ta.disabled = false;
          if (!res.ok) { alert(res.error || 'Nepavyko išsaugoti'); ta.focus(); return; }
          var line = document.createElement('div');
          line.className = 'note-existing just-saved';
          line.textContent = '✓ ' + res.preview + ' — išsaugota';
          document.getElementById('who' + mid).appendChild(line);
          ta.value = '';
          if (video) { video.value = ''; video.closest('details').open = false; }
          if (lesson) lesson.value = ''; if (task) task.checked = false;
          // pereiname prie kito vaiko laukelio
          var all = Array.prototype.slice.call(form.querySelectorAll('textarea[data-member]'));
          var next = all[all.indexOf(ta) + 1];
          if (next) next.focus();
        })
        .catch(function () { ta.disabled = false; alert('Nepavyko išsaugoti - patikrinkite internetą.'); });
    });
  });
})();
</script>
<script>
// Lankomumas: "Buvo" / "Nebuvo" išsaugoma iškart; tas pats mygtukas dar kartą - žyma nuimama
(function () {
  var form = document.querySelector('.notes-form');
  if (!form) return;
  function send(params) {
    var data = new FormData();
    data.append('_csrf', form.querySelector('[name=_csrf]').value);
    data.append('g', form.querySelector('[name=g]').value);
    data.append('e', form.querySelector('[name=e]').value);
    data.append('d', form.querySelector('[name=d]').value);
    Object.keys(params).forEach(function (k) { data.append(k, params[k]); });
    return fetch(location.pathname, { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok) throw new Error();
        document.querySelectorAll('.att').forEach(function (box) {
          var v = res.marks[box.dataset.member];
          box.querySelector('.yes').classList.toggle('on', String(v) === '1');
          box.querySelector('.no').classList.toggle('on', String(v) === '0');
        });
        document.getElementById('attPresent').textContent = res.present;
        if (res.total !== undefined && document.getElementById('attTotal')) document.getElementById('attTotal').textContent = res.total;
      })
      .catch(function () { alert('Nepavyko išsaugoti lankomumo - patikrinkite internetą.'); });
  }
  document.querySelectorAll('.att-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var box = btn.closest('.att');
      send({ action: form.querySelector('[name=e]').value !== '0' ? 'ev_attendance' : 'attendance', member_id: box.dataset.member, present: btn.classList.contains('on') ? '' : btn.dataset.v });
    });
  });
  var all = document.getElementById('attAll');
  if (all) all.addEventListener('click', function () { send({ action: all.dataset.action }); });
})();
</script>
<script>
// „+XP“ prie vardo: mažas langelis po to nario eilute; skirta - matosi eilutė „✨ +5 XP · …“
(function () {
  var box = document.getElementById('xpQuick'), form = document.querySelector('.notes-form');
  if (!box || !form) return;
  var cat = document.getElementById('xpCat'), pts = document.getElementById('xpPts'), note = document.getElementById('xpNote'), current = null;
  cat.addEventListener('change', function () { pts.value = cat.selectedOptions[0].dataset.points || 5; });
  document.querySelectorAll('.xp-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      current = btn.dataset.member;
      document.getElementById('who' + current).appendChild(box);
      box.hidden = false; note.value = ''; cat.focus();
    });
  });
  document.getElementById('xpCancel').addEventListener('click', function () { box.hidden = true; });
  document.getElementById('xpGive').addEventListener('click', function () {
    var data = new FormData();
    data.append('_csrf', form.querySelector('[name=_csrf]').value);
    ['g', 'e', 'd'].forEach(function (k) { data.append(k, form.querySelector('[name=' + k + ']').value); });
    data.append('action', 'xp'); data.append('member_id', current);
    data.append('category_id', cat.value); data.append('points', pts.value); data.append('note', note.value.trim());
    fetch(location.pathname, { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok) { alert(res.error || 'Nepavyko'); return; }
        var line = document.createElement('div');
        line.className = 'note-existing xp-line just-saved';
        line.textContent = res.line;
        box.hidden = true;
        document.getElementById('who' + current).appendChild(line);
      })
      .catch(function () { alert('Nepavyko išsaugoti - patikrinkite internetą.'); });
  });
})();
</script>
<?php
page_end();
