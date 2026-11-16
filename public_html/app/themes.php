<?php
// Temos (vienas sąrašas karateka.lt ir Taikai): fonas su peizažu (img/temos/*.svg), spalvos. Tamsiosioms - šviesūs užrašai ant fono.
// karateka.lt tema - site_settings „site_theme“ (keičia adminas viršuje, 🎨), Taikos - „taika_ui“ (keičia treneriai Taikoje, 🎨 ir pradžioje, ir varžybose).

// id => [ženkliukas, pavadinimas, tamsi, peizažas]
const THEMES = [
    'light'     => ['☀️', 'Šviesi', false, ''],
    'nature'    => ['🌿', 'Gamta', false, 'gamta'],
    'mountains' => ['🏔️', 'Kalnai', false, 'kalnai'],
    'sea'       => ['🌊', 'Jūra', false, 'jura'],
    'dunes'     => ['🏜️', 'Kopos', false, 'kopos'],
    'sakura'    => ['🌸', 'Sakura', false, 'sakura'],
    'autumn'    => ['🍂', 'Ruduo', false, 'ruduo'],
    'winter'    => ['❄️', 'Žiema', false, 'ziema'],
    'dojo'      => ['🥋', 'Dojo', false, 'dojo'],
    'dark'      => ['🌙', 'Tamsi', true, ''],
    'aurora'    => ['🌌', 'Pašvaistė', true, 'pasvaiste'],
    'space'     => ['🪐', 'Kosmosas', true, 'kosmosas'],
    'volcano'   => ['🌋', 'Vulkanas', true, 'vulkanas'],
];

function site_theme(): string
{
    $t = (string) site_setting('site_theme', 'light');
    return isset(THEMES[$t]) ? $t : 'light';
}

/** Kokia tema rodoma: prisijungusiam - jo pasirinkta (išsaugota paskyrai), kitiems - svetainės */
function current_theme(): string
{
    $a = current_account();
    $t = (string) ($a['theme'] ?? '');
    return $a && isset(THEMES[$t]) ? $t : site_theme();
}

function theme_is_dark(string $t): bool
{
    return !empty(THEMES[$t][2]);
}

/** body atributai: data-theme ir tamsiai temai - klasė theme-dark */
function theme_body_attrs(string $t, string $class = ''): string
{
    $cls = trim($class . (theme_is_dark($t) ? ' theme-dark' : ''));
    return ' class="' . e($cls) . '" data-theme="' . e($t) . '"';
}

/**
 * Temos pasirinkimas: 🎨 mygtukas ir visos temos iš karto (paveikslėliai), be išskleidžiamo sąrašo.
 * $scope: 'site' (karateka.lt, adminas) arba 'taika' (Taika, treneriai).
 */
function theme_picker_html(string $scope, string $current, string $btnClass = ''): string
{
    $html = '<div class="theme-pick" data-scope="' . e($scope) . '" data-api="' . e(url('tema.php')) . '" data-csrf="' . e(csrf_token()) . '">'
        . '<button type="button" class="tp-btn ' . e($btnClass) . '" aria-expanded="false" title="' . e(t('Tema')) . '">🎨<span class="tp-now">' . e(THEMES[$current][0]) . '</span></button>'
        . '<div class="tp-panel" hidden role="group" aria-label="' . e(t('Tema')) . '"><div class="tp-title">' . e($scope === 'me' ? t('Mano tema (išsaugoma paskyrai)') : ($scope === 'site' ? t('Svetainės tema (visiems)') : t('Budo tema (visoms varžyboms)'))) . '</div><div class="tp-grid">';
    foreach (THEMES as $id => [$icon, $name, $dark, $scene]) {
        $bg = $scene !== '' ? "url('" . url('img/temos/' . $scene . '.svg') . "') center/cover" : ($dark ? '#15171D' : 'linear-gradient(135deg,#F3E8F1,#E4EAF7)');
        $html .= '<button type="button" class="tp-sw' . ($id === $current ? ' on' : '') . '" data-theme-id="' . $id . '" data-dark="' . ($dark ? 1 : 0) . '" aria-pressed="' . ($id === $current ? 'true' : 'false') . '">'
            . '<span class="tp-prev" style="background:' . e($bg) . '"></span><span class="tp-name">' . $icon . ' ' . e(t($name)) . '</span></button>';
    }
    if ($scope === 'me' && is_admin()) {
        $html .= '</div><label class="tp-all small"><input type="checkbox" class="tp-all-chk"> ' . e(t('Ir numatytoji visiems lankytojams')) . '</label><div>';
    }
    return $html . '</div></div></div>';
}
