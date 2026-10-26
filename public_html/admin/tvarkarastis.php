<?php
// Visų grupių tvarkaraštis ir kainos vienoje vietoje. Keičia tik adminas, išsaugoma iš karto (be „Išsaugoti“).
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();
$canEdit = is_admin($me);

/** "18:00" -> "18:00:00"; tuščia - null; netinkamas - false */
function time_from_post(string $field)
{
    $v = post($field);
    if ($v === '') {
        return null;
    }
    return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v . ':00' : false;
}

function json_out(array $data): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// Pagrindiniam puslapiui (redagavimo režime): atnaujinta kaina ir tvarkaraštis
if (($cat = get('fragment')) !== '' && isset(GROUP_CATEGORIES[$cat])) {
    json_out(['ok' => true, 'price' => render_price($cat), 'schedule' => $cat === 'vaikai' ? render_kids_locations() : render_category_schedule($cat),
        'locations' => render_category_locations($cat)]);
}

if (is_post()) {
    csrf_check();
    if (!$canEdit) {
        json_out(['ok' => false, 'error' => 'Tvarkaraštį keičia tik administratorius.']);
    }
    $action = post('action');

    if ($action === 'slot_save') {
        $id = (int) post('id');
        $group = q_one('SELECT * FROM training_groups WHERE id = ?', [(int) post('group_id')]);
        $wd = (int) post('weekday');
        $start = time_from_post('start_time');
        $end = time_from_post('end_time');
        $note = mb_substr(post('note'), 0, 190) ?: null;
        if (!$group || $wd < 1 || $wd > 7) {
            json_out(['ok' => false, 'error' => 'Netinkama grupė ar diena.']);
        }
        if ($start === false || $end === false || ($start === null) !== ($end === null)) {
            json_out(['ok' => false, 'error' => 'Įveskite abu laikus (arba palikite tuščius ir parašykite pastabą).']);
        }
        if ($start !== null && $end <= $start) {
            json_out(['ok' => false, 'error' => 'Pabaiga turi būti vėliau nei pradžia.']);
        }
        if ($start === null && !$note) {
            json_out(['ok' => false, 'error' => 'Be laiko reikia pastabos, pvz. „Laikas tikslinamas“.']);
        }
        if ($id) {
            q('UPDATE schedule SET weekday = ?, start_time = ?, end_time = ?, note = ? WHERE id = ? AND group_id = ?', [$wd, $start, $end, $note, $id, (int) $group['id']]);
        } else {
            q('INSERT INTO schedule (group_id, weekday, start_time, end_time, note) VALUES (?, ?, ?, ?, ?)', [(int) $group['id'], $wd, $start, $end, $note]);
            $id = (int) db()->lastInsertId();
        }
        // Tą pačią dieną grupė jau turi persidengiantį laiką - tik įspėjimas
        $warn = null;
        if ($start !== null && q_value('SELECT 1 FROM schedule WHERE group_id = ? AND weekday = ? AND id <> ? AND start_time < ? AND end_time > ?',
                [(int) $group['id'], $wd, $id, $end, $start])) {
            $warn = 'Tą dieną grupė jau turi treniruotę, kuri persidengia su šia.';
        }
        json_out(['ok' => true, 'id' => $id, 'warn' => $warn]);
    }

    if ($action === 'slot_delete') {
        q('DELETE FROM schedule WHERE id = ?', [(int) post('id')]);
        json_out(['ok' => true]);
    }

    if ($action === 'price_save' && isset(GROUP_CATEGORIES[post('category')])) {
        if (post('main') === '') {
            json_out(['ok' => false, 'error' => 'Įveskite kainą.']);
        }
        q('INSERT INTO category_prices (category, price_main, price_note, price_alt) VALUES (?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE price_main = VALUES(price_main), price_note = VALUES(price_note), price_alt = VALUES(price_alt)',
            [post('category'), mb_substr(post('main'), 0, 40), mb_substr(post('note'), 0, 120) ?: null, mb_substr(post('alt'), 0, 120) ?: null]);
        json_out(['ok' => true]);
    }
    json_out(['ok' => false, 'error' => 'Nežinomas veiksmas.']);
}

$groups = q_all('SELECT * FROM training_groups ORDER BY is_active DESC, sort_order, name');
$byCat = [];
$inactive = [];
foreach ($groups as $g) {
    if ($g['is_active']) {
        $byCat[$g['category']][] = $g;
    } else {
        $inactive[] = $g;
    }
}

page_start('Tvarkaraštis ir kainos', ['admin' => true]);
?>
<a class="small" href="<?= url('admin/grupes.php') ?>">← Grupės</a>
<div class="page-head row between" style="align-items:flex-end; flex-wrap:wrap; gap:12px; margin-top:8px;">
  <div>
    <h1 class="styled">Tvarkaraštis ir kainos</h1>
    <p class="muted" style="margin:0;"><?= $canEdit ? 'Keiskite tiesiog laukeliuose - išsaugoma iš karto (✓). Pakeitimai iškart matomi pagrindiniame puslapyje ir skiltyje „Šiandien“.' : 'Tvarkaraštį ir kainas keičia administratorius.' ?></p>
  </div>
  <a class="btn btn-ghost btn-sm" href="<?= url('index.php') ?>" target="_blank">Pagrindinis puslapis ↗</a>
</div>
<?php foreach (GROUP_CATEGORIES as $cat => $catLabel): ?>
  <div class="panel card sched-cat">
    <h2><?= e($catLabel) ?></h2>
    <?php if ($canEdit): ?>
      <?= price_editor($cat) ?>
      <?= schedule_editor($byCat[$cat] ?? []) ?>
    <?php else: ?>
      <?= render_price($cat) ?>
      <?php foreach ($byCat[$cat] ?? [] as $g): ?>
        <div class="sl-group"><div class="sl-head"><strong><?= e($g['name']) ?></strong><span class="small muted"><?= e((string) $g['location']) ?></span></div>
          <?php foreach (group_schedule((int) $g['id']) as $s): ?>
            <div class="schedule-line"><span><?= e(LT_WEEKDAYS[(int) $s['weekday']]) ?><?= $s['note'] ? ' <span class="muted small">· ' . e($s['note']) . '</span>' : '' ?></span>
              <span class="schedule-time"><?= $s['start_time'] ? e(fmt_time($s['start_time']) . '–' . fmt_time($s['end_time'])) : '' ?></span></div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php if (empty($byCat[$cat])): ?><p class="muted small">Aktyvių grupių nėra. <a href="<?= url('admin/grupes.php') ?>">Sukurti grupę →</a></p><?php endif; ?>
  </div>
<?php endforeach; ?>
<?php if ($inactive && $canEdit): ?>
  <details class="panel card">
    <summary><strong>Neaktyvios grupės (<?= count($inactive) ?>)</strong> <span class="small muted">- svetainėje nerodomos</span></summary>
    <?= schedule_editor($inactive) ?>
  </details>
<?php endif; ?>
<?php
page_end();
