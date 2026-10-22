<?php
// Grupių ir žmonių pasirinkimas medžiu: kategorija -> grupė (išskleidžiama) -> nariai

/**
 * Pažymėjus visą grupę siunčiama groups[] (ir jos nariai members[]), pažymėjus tik kelis narius - tik members[].
 * $opt: all_label, hint, locked (nariai, kurie jau sąraše - rodomi pažymėti ir nekeičiami), locked_title.
 */
function member_tree_field(array $selGroups, array $selMembers, array $opt = []): string
{
    $selGroups = array_map('intval', $selGroups);
    $selMembers = array_map('intval', $selMembers);
    $locked = array_map('intval', $opt['locked'] ?? []);
    $groups = q_all('SELECT * FROM training_groups WHERE is_active = 1 ORDER BY sort_order, name');
    $byGroup = [];
    $groupIds = array_map('intval', array_column($groups, 'id'));
    foreach (q_all("SELECT id, first_name, last_name, group_id, belt_level, birth_date FROM members WHERE status = 'active' ORDER BY first_name, last_name") as $m) {
        $gid = $m['group_id'] !== null && in_array((int) $m['group_id'], $groupIds, true) ? (int) $m['group_id'] : 0;
        $byGroup[$gid][] = $m;
    }
    $byCat = [];
    foreach ($groups as $g) {
        $byCat[$g['category']][] = $g;
    }

    $memberHtml = function (array $m, bool $groupOn) use ($selMembers, $locked, $opt): string {
        $id = (int) $m['id'];
        $isLocked = in_array($id, $locked, true);
        $on = $isLocked || $groupOn || in_array($id, $selMembers, true);
        $age = $m['birth_date'] ? (int) date_diff(date_create($m['birth_date']), date_create('today'))->y . ' m.' : '';
        $belt = $m['belt_level'] !== null && isset(BELTS[(int) $m['belt_level']]) ? BELTS[(int) $m['belt_level']][0] : '';
        return '<label class="check mt-member' . ($isLocked ? ' locked' : '') . '" data-name="' . e(mb_strtolower($m['first_name'] . ' ' . $m['last_name'])) . '"'
            . ($isLocked ? ' title="' . e($opt['locked_title'] ?? 'Jau sąraše') . '"' : '') . '>'
            . '<input type="checkbox" name="members[]" value="' . $id . '" data-mt="member"' . ($on ? ' checked' : '') . ($isLocked ? ' disabled' : '') . '>'
            . '<span>' . e($m['first_name'] . ' ' . $m['last_name']) . ' <span class="muted small">' . e(implode(' · ', array_filter([$age, $belt]))) . '</span>'
            . ($isLocked ? ' <span class="badge">' . e($opt['locked_title'] ?? 'jau sąraše') . '</span>' : '') . '</span></label>';
    };
    $groupHtml = function (array $g, string $label) use ($byGroup, $selGroups, $selMembers, $locked, $memberHtml): string {
        $gid = (int) $g['id'];
        $members = $byGroup[$gid] ?? [];
        $groupOn = in_array($gid, $selGroups, true);
        $some = false;
        foreach ($members as $m) {
            if (in_array((int) $m['id'], $selMembers, true) || in_array((int) $m['id'], $locked, true)) {
                $some = true;
            }
        }
        $html = '<div class="mt-group' . (!$groupOn && $some ? ' open' : '') . '">'
            . '<div class="mt-row">' . ($members ? '<button type="button" class="mt-toggle" aria-label="Rodyti narius">▸</button>' : '<span class="mt-toggle-space"></span>')
            . '<label class="check"><input type="checkbox" name="groups[]" value="' . $gid . '" data-mt="group"' . ($groupOn ? ' checked' : '') . '><span>' . e($label) . '</span></label>'
            . '<span class="mt-count"></span></div>';
        if ($members) {
            $html .= '<div class="mt-members">';
            foreach ($members as $m) {
                $html .= $memberHtml($m, $groupOn);
            }
            $html .= '</div>';
        }
        return $html . '</div>';
    };

    $html = '<div class="mtree" data-mtree>'
        . '<div class="mt-top"><input type="search" class="mt-search" placeholder="Ieškoti vaiko ar nario…" autocomplete="off" aria-label="Ieškoti">'
        . '<span class="mt-total small">Pažymėta: <b>0</b></span></div>'
        . '<label class="check mt-all"><input type="checkbox" data-mt="all"><span>' . e($opt['all_label'] ?? 'Visos grupės') . '</span></label>';
    foreach (GROUP_CATEGORIES as $cat => $catLabel) {
        if (empty($byCat[$cat])) {
            continue;
        }
        $list = $byCat[$cat];
        if (count($list) === 1) {
            // Kategorija su viena grupe (pvz. Jaunimas) - viena eilutė
            $html .= '<div class="mt-cat single">' . $groupHtml($list[0], $catLabel) . '</div>';
            continue;
        }
        $html .= '<div class="mt-cat"><div class="mt-row mt-cat-row"><label class="check"><input type="checkbox" data-mt="cat"><span><strong>' . e($catLabel)
            . '</strong> <span class="muted">(visos ' . count($list) . ')</span></span></label></div><div class="mt-children">';
        foreach ($list as $g) {
            $html .= $groupHtml($g, preg_replace('/^' . preg_quote($catLabel, '/') . ':\s*/u', '', $g['name']));
        }
        $html .= '</div></div>';
    }
    if (!empty($byGroup[0])) {
        $html .= '<div class="mt-cat single"><div class="mt-group"><div class="mt-row"><button type="button" class="mt-toggle">▸</button><span class="muted">Be grupės</span><span class="mt-count"></span></div><div class="mt-members">';
        foreach ($byGroup[0] as $m) {
            $html .= $memberHtml($m, false);
        }
        $html .= '</div></div></div>';
    }
    if (!empty($opt['hint'])) {
        $html .= '<p class="hint" style="margin-top:8px;">' . e($opt['hint']) . '</p>';
    }
    $html .= '</div>';

    static $js = false;
    if (!$js) {
        $js = true;
        $html .= <<<'JS'
<script>
(function () {
  function fold(t) { return t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
  document.querySelectorAll('[data-mtree]').forEach(function (root) {
    var all = root.querySelector('[data-mt=all]');
    function state(boxes) {
      var on = 0, part = false;
      boxes.forEach(function (b) { if (b.checked) on++; if (b.indeterminate) part = true; });
      return { all: boxes.length > 0 && on === boxes.length, some: on > 0 || part };
    }
    function refresh() {
      root.querySelectorAll('.mt-group').forEach(function (g) {
        var ms = g.querySelectorAll('input[data-mt=member]'), on = 0;
        ms.forEach(function (m) { if (m.checked) on++; });
        var gb = g.querySelector('input[data-mt=group]');
        if (gb && ms.length) { gb.checked = on === ms.length; gb.indeterminate = on > 0 && on < ms.length; }
        var c = g.querySelector('.mt-count'); if (c) c.textContent = ms.length ? on + ' iš ' + ms.length : '';
        g.classList.toggle('has-sel', on > 0);
      });
      root.querySelectorAll('.mt-cat:not(.single)').forEach(function (cat) {
        var s = state(cat.querySelectorAll('input[data-mt=group]')), cb = cat.querySelector('input[data-mt=cat]');
        cb.checked = s.all; cb.indeterminate = !s.all && s.some;
      });
      if (all) { var s = state(root.querySelectorAll('input[data-mt=group]')); all.checked = s.all; all.indeterminate = !s.all && s.some; }
      var seen = {};
      root.querySelectorAll('input[data-mt=member]:checked').forEach(function (m) { seen[m.value] = 1; });
      root.querySelector('.mt-total b').textContent = Object.keys(seen).length;
    }
    function setMembers(scope, on) { scope.querySelectorAll('input[data-mt=member]:not(:disabled)').forEach(function (m) { m.checked = on; }); }
    root.addEventListener('change', function (ev) {
      var t = ev.target, kind = t.dataset.mt;
      if (kind === 'group') { setMembers(t.closest('.mt-group'), t.checked); }
      if (kind === 'cat' || kind === 'all') {
        var scope = kind === 'cat' ? t.closest('.mt-cat') : root;
        scope.querySelectorAll('input[data-mt=group]').forEach(function (b) { b.checked = t.checked; b.indeterminate = false; });
        setMembers(scope, t.checked);
      }
      refresh();
    });
    root.addEventListener('click', function (ev) {
      var b = ev.target.closest('.mt-toggle'); if (b) { b.closest('.mt-group').classList.toggle('open'); }
    });
    var s = root.querySelector('.mt-search');
    s.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') ev.preventDefault(); });
    s.addEventListener('input', function () {
      var q = fold(s.value.trim());
      root.classList.toggle('searching', !!q);
      root.querySelectorAll('.mt-member').forEach(function (l) { l.hidden = !!q && fold(l.dataset.name).indexOf(q) === -1; });
      root.querySelectorAll('.mt-group').forEach(function (g) { var any = g.querySelector('.mt-member:not([hidden])'); g.hidden = !!q && !any; });
      root.querySelectorAll('.mt-cat').forEach(function (c) { c.hidden = !!q && !c.querySelector('.mt-group:not([hidden])'); });
    });
    refresh();
  });
})();
</script>
JS;
    }
    return $html;
}

/** Iš formos: pilnai pažymėtos grupės ir atskirai pažymėti nariai (ne iš tų grupių) */
function member_tree_from_post(): array
{
    $groups = array_values(array_unique(array_map('intval', (array) ($_POST['groups'] ?? []))));
    $members = array_values(array_unique(array_map('intval', (array) ($_POST['members'] ?? []))));
    if ($groups && $members) {
        $inGroups = array_map('intval', q('SELECT id FROM members WHERE group_id IN (' . implode(',', $groups) . ')')->fetchAll(PDO::FETCH_COLUMN));
        $members = array_values(array_diff($members, $inGroups));
    }
    return ['groups' => $groups, 'members' => $members];
}
