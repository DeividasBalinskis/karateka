<?php
// Taškai, sezonai ir reitingas

const EVENT_RESULTS = [
    'competition' => ['part' => 'Dalyvavo', '1' => '1 vieta', '2' => '2 vieta', '3' => '3 vieta'],
    'exam'        => ['yes' => 'Išlaikė'],
    'seminar'     => ['yes' => 'Dalyvavo'],
    'camp'        => ['yes' => 'Dalyvavo'],
    'other'       => ['yes' => 'Dalyvavo'],
];

/** Sezonas: rugsėjo 1 - rugpjūčio 31. Grąžina [pradžia, pabaiga, pavadinimas]. */
function season_bounds(?string $date = null): array
{
    $ts = strtotime($date ?? 'today');
    $y = (int) date('Y', $ts);
    $startYear = (int) date('n', $ts) >= 9 ? $y : $y - 1;
    return ["$startYear-09-01", ($startYear + 1) . '-08-31', $startYear . '–' . ($startYear + 1) . (' ' . t('m. sezonas'))];
}

/** Kaip dalijamas reitingas: 'category' (vaikai/jaunimas/suaugę), 'group' arba 'club' */
function ranking_scope(): string
{
    $s = config('ranking.scope', 'category');
    return in_array($s, ['category', 'group', 'club'], true) ? $s : 'category';
}

/** Nario reitingo "lygos" raktas ir pavadinimas (null, jei narys be grupės) */
function ranking_partition(array $member): ?array
{
    if (!$member['group_id']) {
        return null;
    }
    $g = q_one('SELECT * FROM training_groups WHERE id = ?', [$member['group_id']]);
    switch (ranking_scope()) {
        case 'club':
            return ['club', null, t('Visas klubas')];
        case 'group':
            return ['group', (int) $g['id'], $g['name']];
        default:
            return ['category', $g['category'], t(GROUP_CATEGORIES[$g['category']])];
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

/** #141: visi aktyvūs nariai su šio sezono taškais (ir 0), gimimo metais ir diržu - rikiuojama puslapyje */
function all_members_points(string $from, string $to): array
{
    $rows = q_all('SELECT m.id AS member_id, m.first_name, m.last_name, YEAR(m.birth_date) AS year, m.belt_level AS belt,
                          COALESCE((SELECT SUM(pa.points) FROM point_awards pa WHERE pa.member_id = m.id AND pa.awarded_on BETWEEN ? AND ?), 0) AS total
                     FROM members m WHERE m.status = "active" ORDER BY m.last_name, m.first_name', [$from, $to]);
    foreach ($rows as $i => &$r) {
        $r['total'] = (int) $r['total'];
        $r['rank'] = $i + 1;
    }
    return $rows;
}

/** Ar narys priklauso šiai reitingo "lygai" (savo kategorija / grupė / visas klubas) */
function member_in_partition(array $member, array $partition): bool
{
    if (!$member['group_id']) {
        return false;
    }
    [$type, $key] = $partition;
    if ($type === 'club') {
        return true;
    }
    if ($type === 'group') {
        return (int) $member['group_id'] === (int) $key;
    }
    return q_value('SELECT category FROM training_groups WHERE id = ?', [$member['group_id']]) === $key;
}

/**
 * Nario eilutė reitinge. Jei taškų dar neturi - vis tiek grąžinama: 0 tšk.,
 * vieta po visų, kurie taškų turi (visi su 0 tšk. dalijasi ta pačia vieta).
 */
function member_rank_row(array $rows, array $member, array $partition): ?array
{
    foreach ($rows as $r) {
        if ((int) $r['member_id'] === (int) $member['id']) {
            return $r;
        }
    }
    if (!member_in_partition($member, $partition)) {
        return null;
    }
    return ['member_id' => (int) $member['id'], 'first_name' => $member['first_name'], 'last_name' => $member['last_name'],
            'total' => 0, 'rank' => count($rows) + 1];
}

/** Trumpas vardas viešam sąrašui: "Jonas P." */
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
 * Taškai už lankomumą: pažymėjus "buvo" - skiriami (jei kategorija įjungta ir verta > 0),
 * "nebuvo" ar nuėmus žymą - nuimami. Vienas skyrimas nariui per dieną.
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

/** Skiria taškus (XP). $points - kitokia vertė nei kategorijos (pvz. egzamino programos punkto XP). Pakilus lygiui - šventė nariui. */
function award_points(int $memberId, array $category, string $date, ?int $eventId, ?string $note, int $by, ?int $points = null): void
{
    $before = member_points_total($memberId);
    q('INSERT INTO point_awards (member_id, category_id, points, awarded_on, event_id, note, awarded_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$memberId, $category['id'], $points ?? $category['points'], $date, $eventId, $note ? mb_substr($note, 0, 190) : null, $by]);
    xp_check_level_up($memberId, $before);
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
        case 'other':
            return $result === 'yes' ? ['event_other'] : [];
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
/** Reitingas treneriams; $give - prie kiekvieno vardo „+XP“ (skirti XP iškart iš sąrašo) */
function render_staff_ranking(bool $give = false): string
{
    [$from, $to, $label] = season_bounds();
    $choices = [];
    if (ranking_scope() === 'group') {
        foreach (q_all('SELECT * FROM training_groups WHERE is_active = 1 ORDER BY sort_order') as $g) {
            $choices['g' . $g['id']] = ['group', (int) $g['id'], $g['name']];
        }
    } elseif (ranking_scope() === 'category') {
        foreach (array_map('t', GROUP_CATEGORIES) as $key => $name) {
            $choices[$key] = ['category', $key, $name];
        }
    }
    $choices['klubas'] = ['club', null, t('Visas klubas')];
    if ($give) {
        $choices['visi'] = ['all', null, t('👥 Visi nariai')];   // #141: visi nariai (ir be taškų), rikiuojami, taškai - paspaudus
    }
    $current = isset($choices[get('rt')]) ? get('rt') : array_key_first($choices);
    $rows = $current === 'visi' ? all_members_points($from, $to) : ranking($choices[$current], $from, $to);
    $xpCats = $give ? manual_xp_categories() : [];
    ob_start();
    ?>
<div class="panel card points-card">
  <div class="kicker"><?= te('Reitingas ·') ?> <?= e($label) ?></div>
  <div class="top-tabs">
    <?php foreach ($choices as $key => $p): ?>
      <a href="?rt=<?= e($key) ?>" class="<?= $key === $current ? 'active' : '' ?>"><?= e($p[2]) ?></a>
    <?php endforeach; ?>
  </div>
  <?php if (!$rows): ?>
    <p class="muted"><?= te('Šį sezoną aktyvumo taškų dar niekas neturi.') ?></p>
  <?php else: ?>
    <input type="search" class="rank-search" id="rankSearch" placeholder="<?= te('Ieškoti pagal vardą ar pavardę') ?>" aria-label="<?= te('Ieškoti reitinge') ?>">
    <?php if ($current === 'visi'): ?>
      <div class="rank-sort chips-row" role="group" aria-label="<?= te('Rikiuoti') ?>">
        <span class="small muted"><?= te('Rikiuoti:') ?></span>
        <button type="button" class="fchip" data-sort="name" aria-pressed="true"><?= te('A–Ž') ?></button>
        <button type="button" class="fchip" data-sort="year" aria-pressed="false"><?= te('Gimimo metai') ?></button>
        <button type="button" class="fchip" data-sort="belt" aria-pressed="false"><?= te('Kyu') ?></button>
        <button type="button" class="fchip" data-sort="pts" aria-pressed="false"><?= te('Taškai') ?></button>
      </div>
    <?php endif; ?>
    <ol class="ranking ranking-all<?= $current === 'visi' ? ' all-members' : '' ?>" id="rankAll">
      <?php foreach ($rows as $r): ?>
        <li data-name="<?= e(mb_strtolower($r['first_name'] . ' ' . $r['last_name'])) ?>" data-sname="<?= e(mb_strtolower($r['last_name'] . ' ' . $r['first_name'])) ?>" data-year="<?= (int) ($r['year'] ?? 0) ?>" data-belt="<?= (int) ($r['belt'] ?? 0) ?>" data-pts="<?= (int) $r['total'] ?>" class="<?= $current !== 'visi' && $r['rank'] <= 5 ? 'top5' : '' ?>">
          <?php if ($current !== 'visi'): ?><span class="pos r<?= (int) $r['rank'] ?>"><?= (int) $r['rank'] ?></span><?php endif; ?><a href="<?= url('admin/nariai.php?id=' . (int) $r['member_id']) ?>"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></a>
          <?php if ($current === 'visi'): ?><span class="am-meta"><?= $r['year'] ? (int) $r['year'] : '—' ?><?php if ($r['belt'] && isset(BELTS[(int) $r['belt']])): ?> · <i class="am-belt" style="background:<?= e(BELTS[(int) $r['belt']][2]) ?>"></i><?= e(BELTS[(int) $r['belt']][0]) ?><?php endif; ?></span><?php endif; ?>
          <span class="pts"><?= (int) $r['total'] ?></span>
          <?php if ($xpCats): ?><button type="button" class="xp-btn rank-xp" data-member="<?= (int) $r['member_id'] ?>" title="<?= te('Skirti taškus') ?>"><?= te('+ Taškai') ?></button><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
    <p class="hint" id="rankEmpty" style="display:none;"><?= te('Nieko nerasta.') ?></p>
    <p class="hint" style="margin-top:8px;"><?= $current === 'visi' ? te('Aktyvių narių:') : te('Su taškais:') ?> <?= count($rows) ?>. <?= te('Paspaudę vardą atidarysite nario puslapį.') ?><?php if ($xpCats): ?> <?= te('„+ Taškai“ prie vardo - skirti taškų iškart.') ?><?php endif; ?></p>
    <?php if ($xpCats): ?>
    <div class="xp-quick rank-quick" id="rankQuick" hidden>
      <label><?= te('Už ką') ?>
        <select id="rqCat"><?php foreach ($xpCats as $c): ?><option value="<?= (int) $c['id'] ?>" data-points="<?= (int) $c['points'] ?>"<?= $c['code'] === 'other' ? ' data-other="1"' : '' ?>><?= e($c['code'] === 'other' ? t('✎ Kita - įrašysiu pats (komentare)') : $c['name']) ?></option><?php endforeach; ?></select></label>
      <label class="xp-pts"><?= te('Aktyvumo taškai') ?> <input type="number" id="rqPts" min="1" max="1000" value="<?= (int) $xpCats[0]['points'] ?>"></label>
      <label class="xp-note"><?= te('Komentaras') ?> <span class="hint"><?= te('nebūtina, mato narys') ?></span><input type="text" id="rqNote" maxlength="190"></label>
      <div class="row"><button type="button" class="btn btn-primary btn-sm" id="rqGive"><?= te('Skirti') ?></button><button type="button" class="btn btn-ghost btn-sm" id="rqCancel"><?= te('Atšaukti') ?></button></div>
    </div>
    <script>
    // „+XP“ reitinge: langelis po nario eilute; skyrus - XP skaičius eilutėje padidėja ir matosi „✨ +5 XP · …“
    (function () {
      var box = document.getElementById('rankQuick'), cat = document.getElementById('rqCat'), pts = document.getElementById('rqPts'), note = document.getElementById('rqNote'), cur = null;
      cat.addEventListener('change', function () { pts.value = cat.selectedOptions[0].dataset.points || 5; });
      document.querySelectorAll('.rank-xp').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var li = btn.closest('li');
          if (cur === li && !box.hidden) { box.hidden = true; return; }
          cur = li; li.appendChild(box); box.hidden = false; note.value = ''; cat.focus();
        });
      });
      document.getElementById('rqCancel').addEventListener('click', function () { box.hidden = true; });
      document.getElementById('rqGive').addEventListener('click', function () {
        var li = cur, data = new FormData();
        data.append('_csrf', <?= json_encode(csrf_token()) ?>);
        data.append('action', 'xp_one'); data.append('member_id', li.querySelector('.rank-xp').dataset.member);
        if (cat.selectedOptions[0].dataset.other && !note.value.trim()) { note.focus(); note.placeholder = <?= json_encode(t('Parašykite, už ką')) ?>; return; }
        data.append('category_id', cat.value); data.append('points', pts.value); data.append('note', note.value.trim());
        fetch(<?= json_encode(url('admin/taskai.php')) ?>, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (!res.ok) { alert(res.error || 'Nepavyko'); return; }
            box.hidden = true;
            var p = li.querySelector('.pts'); p.textContent = (+p.textContent || 0) + res.points;
            var line = document.createElement('div'); line.className = 'xp-line just-saved'; line.textContent = res.line; li.appendChild(line);
          })
          .catch(function () { alert('Nepavyko išsaugoti - patikrinkite internetą.'); });
      });
    })();
    </script>
    <?php endif; ?>
    <script>
    (function () {
      // Paieška be lietuviškų raidžių skirtumo: "austeja" randa "Austėja"
      var input = document.getElementById('rankSearch'), items = document.querySelectorAll('#rankAll li');
      // #141: visi nariai - rikiavimas pagal abėcėlę, metus, kyu, taškus
      document.querySelectorAll('.rank-sort [data-sort]').forEach(function (b) {
        b.addEventListener('click', function () {
          var k = b.dataset.sort, list = document.getElementById('rankAll'), arr = Array.prototype.slice.call(list.children);
          document.querySelectorAll('.rank-sort [data-sort]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
          arr.sort(function (x, y) {
            if (k === 'name') return x.dataset.sname.localeCompare(y.dataset.sname, 'lt');
            if (k === 'year') return (+y.dataset.year || 0) - (+x.dataset.year || 0);
            if (k === 'belt') return (+y.dataset.belt || 0) - (+x.dataset.belt || 0);
            return (+y.dataset.pts) - (+x.dataset.pts);
          }).forEach(function (li) { list.appendChild(li); });
        });
      });
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

// ---------- XP: kas skiriama automatiškai ir ką skiria treneris ----------

/** Automatinės XP kategorijos: kodas => kada skiriama (rodoma Valdymas → XP). Kitos kategorijos - skiria treneris. */
const XP_AUTO_RULES = [
    'attendance'    => 'Pažymėjus „Buvo“ treniruotėje (kartą per dieną)',
    'event_other'   => 'Pažymėjus „Buvo“ renginyje (tipas „Kita“)',
    'seminar'       => 'Pažymėjus „Buvo“ seminare ar stovykloje',
    'comp_lt'       => 'Varžybos Lietuvoje: pažymėjus „Buvo“ arba įvedus rezultatus',
    'comp_abroad'   => 'Varžybos užsienyje: pažymėjus „Buvo“ arba įvedus rezultatus',
    'place1_lt'     => 'Įvedus varžybų rezultatus (prie dalyvavimo taškų)',
    'place2_lt'     => 'Įvedus varžybų rezultatus (prie dalyvavimo taškų)',
    'place3_lt'     => 'Įvedus varžybų rezultatus (prie dalyvavimo taškų)',
    'place1_abroad' => 'Įvedus varžybų rezultatus (prie dalyvavimo taškų)',
    'place2_abroad' => 'Įvedus varžybų rezultatus (prie dalyvavimo taškų)',
    'place3_abroad' => 'Įvedus varžybų rezultatus (prie dalyvavimo taškų)',
    'exam'          => 'Renginio rezultatuose pažymėjus „Išlaikė“',
    'skill'         => 'Treneriui patvirtinus „Moku“ - taškai nurodyti prie kiekvieno punkto',
    'test_attempt'  => 'Pirmas testo bandymas per sezoną',
    'test_pass'     => 'Pirmą kartą per sezoną išlaikius testą',
];

/** Kategorijos, kurias treneris skiria pats (ne automatinės), aktyvios */
function manual_xp_categories(): array
{
    return array_values(array_filter(q_all('SELECT * FROM point_categories WHERE is_active = 1 ORDER BY sort_order, id'),
        function ($c) { return !isset(XP_AUTO_RULES[(string) $c['code']]); }));
}

/**
 * Treneris skiria XP keliems nariams iš karto. Kiekvienas gauna pranešimą paskyroje (be raudono skaičiuko).
 * Grąžina, kiek narių gavo XP.
 */
function award_xp_to_members(array $memberIds, array $category, int $points, string $date, ?string $note, int $by): int
{
    $n = 0;
    foreach (array_unique(array_map('intval', $memberIds)) as $mid) {
        if (!q_value('SELECT 1 FROM members WHERE id = ? AND status = "active"', [$mid])) {
            continue;
        }
        award_points($mid, $category, $date, null, $note, $by, $points);
        notify_member($mid, 'xp_award', '+' . $points . ' ' . t('taškai') . ': ' . $category['name'], $note ?: null, 'paskyra.php?m=' . $mid, null, false);
        $n++;
    }
    return $n;
}

// ---------- Renginio lankomumas (db/026) ----------

/**
 * „Buvo“ (1), „Nebuvo“ (0) arba nuimta žyma (null). Žmogus, kurio nebuvo dalyvių sąraše, pridedamas kaip dalyvaujantis.
 * XP: seminaras, stovykla, kita - dalyvavimas; varžybos - dalyvavimas (vietos lieka, kaip įvestos). Egzaminas - XP tik už „Išlaikė“ (Rezultatai).
 */
function set_event_attendance(array $event, int $memberId, ?int $present, int $by): void
{
    $eventId = (int) $event['id'];
    $p = event_participant($eventId, $memberId);
    if (!$p && $present === null) {
        return;
    }
    if (!$p || ($present === 1 && $p['status'] !== 'going')) {
        set_participation($eventId, $memberId, 'going', $by);
    }
    q('UPDATE event_participants SET attended = ? WHERE event_id = ? AND member_id = ?', [$present, $eventId, $memberId]);
    if ($event['type'] === 'exam') {
        return;
    }
    $current = event_results($event)[$memberId] ?? '';
    if ($present === 1 && $current === '') {
        save_event_results($event, [$memberId => $event['type'] === 'competition' ? 'part' : 'yes'], $by);
    } elseif ($present !== 1 && $current !== '') {
        save_event_results($event, [$memberId => ''], $by);
    }
}

/** Renginiai, vykę ar vykstantys tą dieną (Treniruotės puslapiui) */
function events_on_date(string $date): array
{
    return q_all('SELECT * FROM events WHERE ? BETWEEN starts_on AND COALESCE(ends_on, starts_on) ORDER BY start_time, title', [$date]);
}

/** Neseniai (per 14 d.) vykę renginiai su dalyviais, kurių lankomumas dar nepažymėtas visiems */
function events_to_mark(): array
{
    return q_all('SELECT e.*, SUM(ep.attended IS NULL) AS unmarked, COUNT(ep.member_id) AS total
                    FROM events e JOIN event_participants ep ON ep.event_id = e.id AND ep.status IN ("going", "late")
                   WHERE COALESCE(e.ends_on, e.starts_on) BETWEEN CURDATE() - INTERVAL 14 DAY AND CURDATE()
                   GROUP BY e.id HAVING unmarked > 0 ORDER BY e.starts_on DESC');
}
