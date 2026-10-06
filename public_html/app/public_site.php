<?php
// Pagrindinio puslapio kainos ir tvarkaraštis iš duomenų bazės (tvarkoma: Treneriams → Grupės)

const PLACE_TYPES = [
    'sporto_centras' => 'Sporto centras',
    'mokykla'        => 'Mokyklos',
    'darzelis'       => 'Darželiai',
    'kita'           => 'Kitos vietos',
];

/** „Pirmadieniais“ ... „Sekmadieniais“ */
const LT_WEEKDAYS_PLURAL = [1 => 'pirmadieniais', 'antradieniais', 'trečiadieniais', 'ketvirtadieniais', 'penktadieniais', 'šeštadieniais', 'sekmadieniais'];

function category_price(string $category): array
{
    return q_one('SELECT * FROM category_prices WHERE category = ?', [$category])
        ?? ['price_main' => '', 'price_note' => '', 'price_alt' => ''];
}

function render_price(string $category): string
{
    $p = category_price($category);
    return '<div class="group-price"><span class="price-main">' . e($p['price_main']) . '</span>'
        . ($p['price_note'] ? '<span class="price-condition">' . e($p['price_note']) . '</span>' : '') . '</div>'
        . ($p['price_alt'] ? '<div class="group-price-alt">' . e($p['price_alt']) . '</div>' : '');
}

/**
 * Sujungia vienodo laiko dienas į vieną eilutę:
 * Pirm 18:00–19:15, Treč 18:00–19:15 -> „Pirmadieniais, trečiadieniais  18:00–19:15“
 */
function schedule_lines(array $slots, ?string $label = null): array
{
    $byTime = [];
    foreach ($slots as $s) {
        $key = ($s['start_time'] ?? '') . '|' . ($s['end_time'] ?? '') . '|' . ($s['note'] ?? '');
        $byTime[$key]['days'][] = (int) $s['weekday'];
        $byTime[$key]['slot'] = $s;
    }
    uasort($byTime, function ($x, $y) { return min($x['days']) <=> min($y['days']); });

    $lines = [];
    foreach ($byTime as $t) {
        $days = array_map(function ($d) { return LT_WEEKDAYS_PLURAL[$d]; }, $t['days']);
        $dayText = implode(', ', $days);
        $dayText = mb_strtoupper(mb_substr($dayText, 0, 1)) . mb_substr($dayText, 1);
        if ($label) {
            $dayText .= ' — ' . $label;
        }
        $s = $t['slot'];
        $time = $s['start_time'] ? fmt_time($s['start_time']) . '–' . fmt_time($s['end_time']) : 'tikslinama';
        if ($s['note'] && $s['start_time']) {
            $dayText .= ' (' . $s['note'] . ')';
        }
        $lines[] = [$dayText, $time];
    }
    return $lines;
}

function render_schedule_lines(array $lines): string
{
    if (!$lines) {
        return '<p class="schedule-placeholder" style="font-style:normal;">Tvarkaraštis tikslinamas.</p>';
    }
    $html = '';
    foreach ($lines as [$day, $time]) {
        $html .= '<div class="schedule-line"><span class="schedule-day">' . e($day) . '</span><span class="schedule-time">' . e($time) . '</span></div>';
    }
    return $html;
}

/** Aktyvios kategorijos grupės su tvarkaraščiu */
function public_groups(string $category): array
{
    $groups = q_all('SELECT * FROM training_groups WHERE is_active = 1 AND category = ? ORDER BY sort_order, name', [$category]);
    foreach ($groups as &$g) {
        $g['slots'] = group_schedule((int) $g['id']);
    }
    return $groups;
}

/** Jaunimui / suaugusiems: visų kategorijos grupių tvarkaraštis vienoje vietoje */
function render_category_schedule(string $category): string
{
    $groups = public_groups($category);
    $lines = [];
    foreach ($groups as $g) {
        $lines = array_merge($lines, schedule_lines($g['slots'], count($groups) > 1 ? ($g['label'] ?: null) : null));
    }
    return render_schedule_lines($lines);
}

function render_category_locations(string $category): string
{
    $locs = array_unique(array_filter(array_column(public_groups($category), 'location')));
    if (!$locs) {
        return '<p class="muted">Tikslinama.</p>';
    }
    $html = '<ul class="loc-list">';
    foreach ($locs as $l) {
        $html .= '<li>' . e($l) . '</li>';
    }
    return $html . '</ul>';
}

/** Vaikams: vietos pagal tipą (sporto centras / mokyklos / darželiai), paspaudus - tos vietos tvarkaraštis */
function render_kids_locations(): string
{
    $byType = [];
    foreach (public_groups('vaikai') as $g) {
        $loc = $g['location'] ?: $g['name'];
        $byType[$g['place_type']][$loc][] = $g;
    }
    $html = '';
    $first = true;
    foreach (PLACE_TYPES as $type => $typeLabel) {
        if (empty($byType[$type])) {
            continue;
        }
        $html .= '<details class="loc-group"' . ($first ? ' open' : '') . '><summary>' . e($typeLabel) . '</summary><ul>';
        foreach ($byType[$type] as $loc => $groups) {
            $lines = [];
            foreach ($groups as $g) {
                $lines = array_merge($lines, schedule_lines($g['slots'], count($groups) > 1 ? ($g['label'] ?: null) : null));
            }
            // „Sporto centras Viršuliškės (...)“ -> sąraše užtenka „Viršuliškės (...)“
            $short = $type === 'sporto_centras' ? preg_replace('/^Sporto centras\s+/u', '', $loc) : $loc;
            $html .= '<li data-name="' . e($loc) . '" data-schedule="' . e(render_schedule_lines($lines)) . '">' . e($short) . '</li>';
        }
        $html .= '</ul></details>';
        $first = false;
    }
    return $html;
}

/** Bandomosios treniruotės formos vietų sąrašas */
function render_trial_location_options(): string
{
    $html = '';
    foreach (GROUP_CATEGORIES as $cat => $catLabel) {
        $locs = array_unique(array_filter(array_column(public_groups($cat), 'location')));
        if (!$locs) {
            continue;
        }
        $html .= '<optgroup label="' . e($catLabel) . '">';
        foreach ($locs as $l) {
            $html .= '<option value="' . e($l . ' — ' . $catLabel) . '">' . e($l) . '</option>';
        }
        $html .= '</optgroup>';
    }
    return $html;
}
