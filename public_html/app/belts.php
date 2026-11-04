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
    return $level && isset(BELTS[$level]) ? BELTS[$level][0] . ' · ' . t(BELTS[$level][1]) : t('Diržas nenurodytas');
}

/** Spalvotas diržo ženkliukas */
function belt_chip(?int $level): string
{
    if (!$level || !isset(BELTS[$level])) {
        return ('<span class="belt-chip belt-none">' . t('diržas nenurodytas') . '</span>');
    }
    [$kyu, $name, $color] = BELTS[$level];
    $name = t($name);
    return '<span class="belt-chip"><span class="belt-swatch" style="background:' . $color . '"></span>' . e($kyu) . ' · ' . e($name) . '</span>';
}

function belt_options(?int $selected, string $emptyLabel = '— nenurodytas —'): string
{
    $html = '<option value="">' . e($emptyLabel) . '</option>';
    foreach (BELTS as $lvl => [$kyu, $name]) {
        $html .= '<option value="' . $lvl . '"' . ($lvl === $selected ? ' selected' : '') . '>' . e($kyu . ' · ' . t($name)) . '</option>';
    }
    return $html;
}

function belt_from_post(string $field): ?int
{
    $v = (int) post($field);
    return isset(BELTS[$v]) ? $v : null;
}

/** Nario paskyroje (viršuje): diržas - spalvota juostelė su mazgu - ir kelias iki kito diržo (programos punktai) */
function belt_strip_html(array $m): string
{
    $lvl = $m['belt_level'] !== null ? (int) $m['belt_level'] : null;
    if (!$lvl || !isset(BELTS[$lvl])) {
        return '';
    }
    [$kyu, $name, $color] = BELTS[$lvl];
    $name = t($name);
    $next = isset(BELTS[$lvl + 1]) ? $lvl + 1 : null;
    $link = url('egzaminas.php?m=' . (int) $m['id']);
    $progress = '';
    if ($next) {
        $reqs = array_merge([], ...array_values(belt_requirements($next)));
        if ($reqs) {
            $done = requirement_done_ids((int) $m['id']);
            $ok = count(array_filter($reqs, function ($r) use ($done) { return ($done[(int) $r['id']] ?? '') === 'approved'; }));
            $progress = '<a class="bs-next" href="' . e($link) . '" title="' . e(t('Egzamino programa')) . '">' . e(t('Iki {belt}: {ok} iš {all}', ['belt' => BELTS[$next][0], 'ok' => $ok, 'all' => count($reqs)]))
                . '<span class="bs-bar" aria-hidden="true"><i style="width:' . (int) round(100 * $ok / count($reqs)) . '%; background:' . BELTS[$next][2] . '"></i></span></a>';
        } else {
            $progress = '<a class="bs-next" href="' . e($link) . '">' . e(t('Kitas -') . ' ' . BELTS[$next][0] . ' · ' . t(BELTS[$next][1])) . ' →</a>';
        }
    }
    return '<div class="belt-strip' . ($lvl === 1 ? ' is-white' : '') . ($lvl >= 10 ? ' is-dan' : '') . '" style="--belt:' . $color . '">'
        . '<span class="bs-belt" aria-hidden="true"><i></i></span><span class="bs-label"><strong>' . e($kyu) . '</strong> · ' . e($name) . (' ' . t('diržas') . '</span>')
        . $progress . '</div>';
}
