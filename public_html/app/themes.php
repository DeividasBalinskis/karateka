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

/** v1.95: temos spalva pasirinkimo paveikslėlyje (juostelė apačioje) */
const THEME_ACCENTS = ['light' => '#A85C7E', 'nature' => '#2F7D4F', 'mountains' => '#2F5D8A', 'sea' => '#1E6F9E', 'dunes' => '#B4572E', 'sakura' => '#B4436F',
    'autumn' => '#B5541F', 'winter' => '#2F5263', 'dojo' => '#B5281F', 'dark' => '#7E6BC4', 'aurora' => '#2E9C7A', 'space' => '#6B5BD6', 'volcano' => '#D9480F'];

/** v1.95: šriftų bandymas (tik administratoriams, „Aa“ šalia 🎨) - id => [pavadinimas, antraščių šriftas, teksto šriftas].
 *  Visi - Google Fonts su lietuviškomis ir rusiškomis raidėmis. Pasirinkimas - slapuke „sriftas“ (tik šiame įrenginyje). */
const FONTS = [
    'default'    => ['Dabartinis', 'Space Grotesk', 'Inter'],
    'inter'      => ['Inter visur', 'Inter', 'Inter'],
    'manrope'    => ['Manrope', 'Manrope', 'Manrope'],
    'montserrat' => ['Montserrat + Open Sans', 'Montserrat', 'Open Sans'],
    'nunito'     => ['Nunito (minkštas)', 'Nunito', 'Nunito'],
    'rubik'      => ['Rubik', 'Rubik', 'Rubik'],
    'unbounded'  => ['Unbounded + Golos', 'Unbounded', 'Golos Text'],
    'exo'        => ['Exo 2 (sportinis)', 'Exo 2', 'Exo 2'],
    'oswald'     => ['Oswald + Source Sans', 'Oswald', 'Source Sans 3'],
    'onest'      => ['Onest', 'Onest', 'Onest'],
    'fira'       => ['Fira Sans', 'Fira Sans', 'Fira Sans'],
    'playfair'   => ['Playfair + Lora (klasika)', 'Playfair Display', 'Lora'],
    'comfortaa'  => ['Comfortaa + Nunito (vaikams)', 'Comfortaa', 'Nunito'],
];

function font_current(): string
{
    $f = (string) ($_COOKIE['sriftas'] ?? 'default');
    return isset(FONTS[$f]) && can('design') ? $f : 'default';
}

/** Google Fonts adresas šriftams (be dabartinių - jie jau kraunami) */
function font_google_url(array $ids): string
{
    $fam = [];
    foreach ($ids as $id) {
        foreach ([FONTS[$id][1], FONTS[$id][2]] as $f) {
            if (!in_array($f, ['Space Grotesk', 'Inter'], true)) {
                $fam[$f] = 'family=' . str_replace(' ', '+', $f) . ':wght@400;500;600;700';
            }
        }
    }
    return $fam ? 'https://fonts.googleapis.com/css2?' . implode('&', $fam) . '&display=swap' : '';
}

/** <head>: pasirinktas šriftas - jo failai ir perrašymas (sriftas.php) */
function font_head_html(): string
{
    $f = font_current();
    if ($f === 'default') {
        return '';
    }
    $g = font_google_url([$f]);
    return ($g !== '' ? '<link href="' . e($g) . '" rel="stylesheet">' : '')
        . '<link rel="stylesheet" href="' . e(url('sriftas.php?f=' . $f . '&v=' . (string) @filemtime(PUBLIC_DIR . '/assets/app.css') . (string) @filemtime(PUBLIC_DIR . '/sriftas.php'))) . '">';
}

// ---------- v1.95: „👍 Patinka“ temoms ir šriftams (administratoriai; matoma ir diegimas-api.php) ----------
function design_likes(): array
{
    $d = json_decode((string) site_setting('design_likes', ''), true);
    return is_array($d) ? $d + ['theme' => [], 'font' => []] : ['theme' => [], 'font' => []];
}

function design_like_set(string $kind, string $id, int $accountId, bool $on): void
{
    $d = design_likes();
    $list = array_values(array_diff(array_map('intval', (array) ($d[$kind][$id] ?? [])), [$accountId]));
    if ($on) {
        $list[] = $accountId;
    }
    if ($list) {
        $d[$kind][$id] = $list;
    } else {
        unset($d[$kind][$id]);
    }
    set_site_setting('design_likes', json_encode($d));
}

/** Kas pažymėjo „patinka“: [kind => [id => ['Vardas', ...]]] */
function design_likes_named(): array
{
    $d = design_likes();
    $ids = [];
    foreach ($d as $items) {
        foreach ((array) $items as $list) {
            $ids = array_merge($ids, array_map('intval', (array) $list));
        }
    }
    $names = $ids ? q('SELECT id, first_name FROM accounts WHERE id IN (' . implode(',', array_unique($ids)) . ')')->fetchAll(PDO::FETCH_KEY_PAIR) : [];
    $out = ['theme' => [], 'font' => []];
    foreach ($d as $kind => $items) {
        foreach ((array) $items as $id => $list) {
            $out[$kind][$id] = array_values(array_filter(array_map(function ($x) use ($names) { return $names[(int) $x] ?? null; }, (array) $list)));
        }
    }
    return $out;
}

/** „👍“ varnelė (tik administratoriui) */
function design_like_html(string $kind, string $id, array $named, int $me): string
{
    $who = $named[$kind][$id] ?? [];
    $mine = in_array($me, array_map('intval', (array) (design_likes()[$kind][$id] ?? [])), true);
    return '<label class="dl-like' . ($mine ? ' on' : '') . '" title="' . e(t('Patinka')) . ($who ? ': ' . e(implode(', ', $who)) : '') . '">'
        . '<input type="checkbox" class="dl-chk" data-kind="' . e($kind) . '" data-id="' . e($id) . '"' . ($mine ? ' checked' : '') . '> 👍'
        . ($who ? '<span class="dl-who">' . e(implode(', ', $who)) . '</span>' : '') . '</label>';
}

/** „Aa“ - šrifto pasirinkimas (tik administratoriams): paspaudus - pritaikoma visam puslapiui šiame įrenginyje */
function font_picker_html(): string
{
    if (!can('design')) {
        return '';
    }
    $cur = font_current();
    $named = design_likes_named();
    $me = (int) (current_account()['id'] ?? 0);
    $html = '<div class="font-pick" data-api="' . e(url('tema.php')) . '" data-csrf="' . e(csrf_token()) . '" data-fonts="' . e(font_google_url(array_keys(FONTS))) . '">'
        . '<button type="button" class="fp-btn" aria-expanded="false" title="' . e(t('Šriftas (bandymui)')) . '">Aa</button>'
        . '<div class="fp-panel" hidden role="group" aria-label="' . e(t('Šriftas')) . '"><div class="tp-title">' . e(t('Šriftas - bandymui (tik šiame įrenginyje)')) . '</div><div class="fp-list">';
    foreach (FONTS as $id => [$name, $head, $body]) {
        $html .= '<div class="fp-item' . ($id === $cur ? ' on' : '') . '"><button type="button" class="fp-sw" data-font-id="' . e($id) . '" aria-pressed="' . ($id === $cur ? 'true' : 'false') . '">'
            . '<span class="fp-name" style="font-family:\'' . e($head) . '\', sans-serif">' . e(t($name)) . '</span>'
            . '<span class="fp-sample" style="font-family:\'' . e($body) . '\', sans-serif">Karate-do · Ąčęėįšųūž · Каратэ 123</span></button>'
            . design_like_html('font', $id, $named, $me) . '</div>';
    }
    return $html . '</div></div></div>';
}

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
        . '<div class="tp-panel" hidden role="group" aria-label="' . e(t('Tema')) . '"><div class="tp-title">' . e($scope === 'me' ? t('Mano tema (išsaugoma paskyrai)') : ($scope === 'site' ? t('Svetainės tema (visiems)') : ($scope === 'device' ? t('Tema šiame įrenginyje') : t('Budo tema (visoms varžyboms)')))) . '</div><div class="tp-grid">';
    $likes = $scope === 'me' && can('design');   // v1.95: „👍 Patinka“ - administratoriams
    $named = $likes ? design_likes_named() : [];
    $me = (int) (current_account()['id'] ?? 0);
    foreach (THEMES as $id => [$icon, $name, $dark, $scene]) {
        $bg = $scene !== '' ? "url('" . url('img/temos/' . $scene . '.svg') . "') center/cover" : ($dark ? '#15171D' : 'linear-gradient(135deg,#F3E8F1,#E4EAF7)');
        $btn = '<button type="button" class="tp-sw' . ($id === $current ? ' on' : '') . '" data-theme-id="' . $id . '" data-dark="' . ($dark ? 1 : 0) . '" aria-pressed="' . ($id === $current ? 'true' : 'false') . '">'
            . '<span class="tp-prev" style="background:' . e($bg) . '; --sw:' . e(THEME_ACCENTS[$id] ?? 'transparent') . '"></span><span class="tp-name">' . $icon . ' ' . e(t($name)) . '</span></button>';
        $html .= $likes ? '<div class="tp-item">' . $btn . design_like_html('theme', $id, $named, $me) . '</div>' : $btn;
    }
    if ($scope === 'me' && can('design')) {
        $html .= '</div><label class="tp-all small"><input type="checkbox" class="tp-all-chk"> ' . e(t('Ir numatytoji visiems lankytojams')) . '</label><div>';
    }
    return $html . '</div></div></div>';
}
