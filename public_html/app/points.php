<?php
// Taškai, sezonai ir reitingas

const EVENT_RESULTS = [
    'competition' => ['part' => 'Dalyvavo', '1' => '1 vieta', '2' => '2 vieta', '3' => '3 vieta'],
    'exam'        => ['yes' => 'Išlaikė'],
    'seminar'     => ['yes' => 'Dalyvavo'],
    'camp'        => ['yes' => 'Dalyvavo'],
];

/** Sezonas: rugsėjo 1 - rugpjūčio 31. Grąžina [pradžia, pabaiga, pavadinimas]. */
function season_bounds(?string $date = null): array
{
    $ts = strtotime($date ?? 'today');
    $y = (int) date('Y', $ts);
    $startYear = (int) date('n', $ts) >= 9 ? $y : $y - 1;
    return ["$startYear-09-01", ($startYear + 1) . '-08-31', $startYear . '–' . ($startYear + 1) . ' m. sezonas'];
}

/** Kaip dalijamas reitingas: 'category' (vaikai/jaunimas/suaugę), 'group' arba 'club' */
function ranking_scope(): string
{
    $s = config('ranking.scope', 'category');
    return in_array($s, ['category', 'group', 'club'], true) ? $s : 'category';
}

/** Nario reitingo „lygos“ raktas ir pavadinimas (null, jei narys be grupės) */
function ranking_partition(array $member): ?array
{
    if (!$member['group_id']) {
        return null;
    }
    $g = q_one('SELECT * FROM training_groups WHERE id = ?', [$member['group_id']]);
    switch (ranking_scope()) {
        case 'club':
            return ['club', null, 'Visas klubas'];
        case 'group':
            return ['group', (int) $g['id'], $g['name']];
        default:
            return ['category', $g['category'], GROUP_CATEGORIES[$g['category']]];
    }
}

/** Reitingas: [['member_id', 'first_name', 'last_name', 'total', 'rank'], ...] */
function ranking(array $partition, string $from, string $to): array
{
    [$type, $key] = $partition;
    $sql = 'SELECT m.id AS member_id, m.first_name, m.last_name, SUM(pa.points) AS total
              FROM point_awards pa
              JOIN members m ON m.id = pa.member_id
              JOIN training_groups g ON g.id = m.group_id
             WHERE m.status = "active" AND pa.awarded_on BETWEEN ? AND ?';
    $params = [$from, $to];
    if ($type === 'category') {
        $sql .= ' AND g.category = ?';
        $params[] = $key;
    } elseif ($type === 'group') {
        $sql .= ' AND g.id = ?';
        $params[] = $key;
    }
    $rows = q_all($sql . ' GROUP BY m.id, m.first_name, m.last_name HAVING total > 0 ORDER BY total DESC, m.last_name, m.first_name', $params);

    // Vienodi taškai - ta pati vieta (1, 2, 2, 4)
    $rank = 0;
    $prev = null;
    foreach ($rows as $i => &$r) {
        $r['total'] = (int) $r['total'];
        if ($r['total'] !== $prev) {
            $rank = $i + 1;
            $prev = $r['total'];
        }
        $r['rank'] = $rank;
    }
    return $rows;
}

/** Trumpas vardas viešam sąrašui: „Jonas P.“ */
function short_name(array $r): string
{
    return $r['first_name'] . ' ' . mb_substr($r['last_name'], 0, 1) . '.';
}

function member_points_total(int $memberId, ?string $from = null, ?string $to = null): int
{
    if ($from) {
        return (int) q_value('SELECT COALESCE(SUM(points), 0) FROM point_awards WHERE member_id = ? AND awarded_on BETWEEN ? AND ?', [$memberId, $from, $to]);
    }
    return (int) q_value('SELECT COALESCE(SUM(points), 0) FROM point_awards WHERE member_id = ?', [$memberId]);
}

/** Nario istorija: visi taškų skyrimai, naujausi viršuje (lankomumas rodomas atskirai - nesugrūda sąrašo) */
function member_history(int $memberId): array
{
    return q_all('SELECT pa.*, pc.name AS category_name, pc.code, e.title AS event_title, e.type AS event_type
                    FROM point_awards pa
                    JOIN point_categories pc ON pc.id = pa.category_id
                    LEFT JOIN events e ON e.id = pa.event_id
                   WHERE pa.member_id = ? AND (pc.code IS NULL OR pc.code <> "attendance")
                   ORDER BY pa.awarded_on DESC, pa.id DESC', [$memberId]);
}

/**
 * Taškai už lankomumą: pažymėjus „buvo“ - skiriami (jei kategorija įjungta ir verta > 0),
 * „nebuvo“ ar nuėmus žymą - nuimami. Vienas skyrimas nariui per dieną.
 */
function sync_attendance_points(int $memberId, string $date, bool $present, int $by): void
{
    $cat = q_one('SELECT * FROM point_categories WHERE code = "attendance"');
    if (!$cat) {
        return;
    }
    $has = q_value('SELECT 1 FROM point_awards WHERE member_id = ? AND category_id = ? AND awarded_on = ?', [$memberId, $cat['id'], $date]);
    if ($present && !$has && $cat['is_active'] && (int) $cat['points'] > 0) {
        award_points($memberId, $cat, $date, null, null, $by);
    } elseif (!$present && $has) {
        q('DELETE FROM point_awards WHERE member_id = ? AND category_id = ? AND awarded_on = ?', [$memberId, $cat['id'], $date]);
    }
}

function category_by_code(string $code): array
{
    $c = q_one('SELECT * FROM point_categories WHERE code = ?', [$code]);
    if (!$c) {
        throw new RuntimeException("Nerasta taškų kategorija: $code");
    }
    return $c;
}

function award_points(int $memberId, array $category, string $date, ?int $eventId, ?string $note, int $by): void
{
    q('INSERT INTO point_awards (member_id, category_id, points, awarded_on, event_id, note, awarded_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$memberId, $category['id'], $category['points'], $date, $eventId, $note ?: null, $by]);
}

/** Kurias kategorijas atitinka rezultatas renginyje. Prizinė vieta = dalyvavimas + vieta. */
function result_category_codes(array $event, string $result): array
{
    $where = $event['is_abroad'] ? 'abroad' : 'lt';
    switch ($event['type']) {
        case 'competition':
            if ($result === 'part') {
                return ["comp_$where"];
            }
            if (in_array($result, ['1', '2', '3'], true)) {
                return ["comp_$where", "place{$result}_$where"];
            }
            return [];
        case 'exam':
            return $result === 'yes' ? ['exam'] : [];
        case 'seminar':
        case 'camp':
            return $result === 'yes' ? ['seminar'] : [];
    }
    return [];
}

/** Atvirkščiai: iš esamų skyrimų nustato nario rezultatą renginyje (formos užpildymui) */
function event_results(array $event): array
{
    $out = [];
    $rows = q_all('SELECT pa.member_id, pc.code FROM point_awards pa JOIN point_categories pc ON pc.id = pa.category_id WHERE pa.event_id = ?', [$event['id']]);
    foreach ($rows as $r) {
        if (preg_match('/^place([123])_/', (string) $r['code'], $m)) {
            $out[$r['member_id']] = $m[1];
        } elseif (!isset($out[$r['member_id']])) {
            $out[$r['member_id']] = $event['type'] === 'competition' ? 'part' : 'yes';
        }
    }
    return $out;
}

/**
 * Išsaugo renginio rezultatus: $results = [member_id => rezultatas | ''].
 * Nepasikeitę skyrimai lieka kaip buvo (su tuo metu galiojusiais taškais).
 */
function save_event_results(array $event, array $results, int $by): int
{
    $date = $event['ends_on'] ?: $event['starts_on'];
    $changes = 0;
    db()->beginTransaction();
    try {
        foreach ($results as $memberId => $result) {
            $want = result_category_codes($event, (string) $result);
            $have = q('SELECT pa.id, pc.code FROM point_awards pa JOIN point_categories pc ON pc.id = pa.category_id WHERE pa.event_id = ? AND pa.member_id = ?',
                [$event['id'], $memberId])->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($have as $awardId => $code) {
                if (!in_array($code, $want, true)) {
                    q('DELETE FROM point_awards WHERE id = ?', [$awardId]);
                    $changes++;
                }
            }
            foreach ($want as $code) {
                if (!in_array($code, $have, true)) {
                    award_points((int) $memberId, category_by_code($code), $date, (int) $event['id'], null, $by);
                    $changes++;
                }
            }
        }
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }
    return $changes;
}

/**
 * Treneriams: visas sezono reitingas vienoje kortelėje - skirtukai (amžiaus grupės / visas klubas),
 * Top 5 ryškiau, kiti slenkami, paieška pagal vardą, vardas veda į nario puslapį.
 */
function render_staff_ranking(): string
{
    [$from, $to, $label] = season_bounds();
    $choices = [];
    if (ranking_scope() === 'group') {
        foreach (q_all('SELECT * FROM training_groups WHERE is_active = 1 ORDER BY sort_order') as $g) {
            $choices['g' . $g['id']] = ['group', (int) $g['id'], $g['name']];
        }
    } elseif (ranking_scope() === 'category') {
        foreach (GROUP_CATEGORIES as $key => $name) {
            $choices[$key] = ['category', $key, $name];
        }
    }
    $choices['klubas'] = ['club', null, 'Visas klubas'];
    $current = isset($choices[get('rt')]) ? get('rt') : array_key_first($choices);
    $rows = ranking($choices[$current], $from, $to);
    ob_start();
    ?>
<div class="panel card points-card">
  <div class="kicker">Reitingas · <?= e($label) ?></div>
  <div class="top-tabs">
    <?php foreach ($choices as $key => $p): ?>
      <a href="?rt=<?= e($key) ?>" class="<?= $key === $current ? 'active' : '' ?>"><?= e($p[2]) ?></a>
    <?php endforeach; ?>
  </div>
  <?php if (!$rows): ?>
    <p class="muted">Šį sezoną taškų dar niekas neturi.</p>
  <?php else: ?>
    <input type="search" class="rank-search" id="rankSearch" placeholder="Ieškoti pagal vardą ar pavardę" aria-label="Ieškoti reitinge">
    <ol class="ranking ranking-all" id="rankAll">
      <?php foreach ($rows as $r): ?>
        <li data-name="<?= e(mb_strtolower($r['first_name'] . ' ' . $r['last_name'])) ?>" class="<?= $r['rank'] <= 5 ? 'top5' : '' ?>">
          <span class="pos r<?= (int) $r['rank'] ?>"><?= (int) $r['rank'] ?></span><a href="<?= url('admin/nariai.php?id=' . (int) $r['member_id']) ?>"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></a><span class="pts"><?= (int) $r['total'] ?></span>
        </li>
      <?php endforeach; ?>
    </ol>
    <p class="hint" id="rankEmpty" style="display:none;">Nieko nerasta.</p>
    <p class="hint" style="margin-top:8px;">Su taškais: <?= count($rows) ?>. Paspaudę vardą atidarysite nario puslapį.</p>
    <script>
    (function () {
      // Paieška be lietuviškų raidžių skirtumo: „austeja“ randa „Austėja“
      var input = document.getElementById('rankSearch'), items = document.querySelectorAll('#rankAll li');
      var plain = function (s) { return s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };
      input.addEventListener('input', function () {
        var q = plain(input.value.trim()), shown = 0;
        items.forEach(function (li) {
          var ok = !q || plain(li.dataset.name).indexOf(q) !== -1;
          li.style.display = ok ? '' : 'none';
          if (ok) shown++;
        });
        document.getElementById('rankEmpty').style.display = shown ? 'none' : '';
      });
    })();
    </script>
  <?php endif; ?>
</div>
    <?php
    return ob_get_clean();
}
