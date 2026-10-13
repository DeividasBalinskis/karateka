<?php
// Diržai: 1 = 9 kyu (baltas) ... 9 = 1 kyu (rudas), 10 = 1 dan ... 18 = 9 dan (juodi)

const BELTS = [
    1  => ['9 kyu', 'baltas',    '#F4F1EA'],
    2  => ['8 kyu', 'geltonas',  '#F0CB4E'],
    3  => ['7 kyu', 'oranžinis', '#E6842B'],
    4  => ['6 kyu', 'žalias',    '#4C8353'],
    5  => ['5 kyu', 'mėlynas',   '#2E6FA8'],
    6  => ['4 kyu', 'mėlynas',   '#2E6FA8'],
    7  => ['3 kyu', 'rudas',     '#6B4A34'],
    8  => ['2 kyu', 'rudas',     '#6B4A34'],
    9  => ['1 kyu', 'rudas',     '#6B4A34'],
    10 => ['1 dan', 'juodas',    '#15110D'],
    11 => ['2 dan', 'juodas',    '#15110D'],
    12 => ['3 dan', 'juodas',    '#15110D'],
    13 => ['4 dan', 'juodas',    '#15110D'],
    14 => ['5 dan', 'juodas',    '#15110D'],
    15 => ['6 dan', 'juodas',    '#15110D'],
    16 => ['7 dan', 'juodas',    '#15110D'],
    17 => ['8 dan', 'juodas',    '#15110D'],
    18 => ['9 dan', 'juodas',    '#15110D'],
];

function belt_label(?int $level): string
{
    return $level && isset(BELTS[$level]) ? BELTS[$level][0] . ' · ' . BELTS[$level][1] : 'Diržas nenurodytas';
}

/** Spalvotas diržo ženkliukas */
function belt_chip(?int $level): string
{
    if (!$level || !isset(BELTS[$level])) {
        return '<span class="belt-chip belt-none">diržas nenurodytas</span>';
    }
    [$kyu, $name, $color] = BELTS[$level];
    return '<span class="belt-chip"><span class="belt-swatch" style="background:' . $color . '"></span>' . e($kyu) . ' · ' . e($name) . '</span>';
}

function belt_options(?int $selected, string $emptyLabel = '— nenurodytas —'): string
{
    $html = '<option value="">' . e($emptyLabel) . '</option>';
    foreach (BELTS as $lvl => [$kyu, $name]) {
        $html .= '<option value="' . $lvl . '"' . ($lvl === $selected ? ' selected' : '') . '>' . e("$kyu · $name") . '</option>';
    }
    return $html;
}

function belt_from_post(string $field): ?int
{
    $v = (int) post($field);
    return isset(BELTS[$v]) ? $v : null;
}
