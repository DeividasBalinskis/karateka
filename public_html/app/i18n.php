<?php
// Kalbos: lietuvių (pagrindinė), anglų, rusų. Sąsajos tekstai rašomi t('Lietuviškas tekstas') - vertimas iš app/lang/en.php, ru.php
// (raktas - lietuviškas tekstas; jei vertimo nėra - rodomas lietuviškas). Kalba pasirenkama viršuje (slapukas „kalba“, 1 metai).
// Denio rašomas turinys (naujienos, renginiai, pamokos, pagrindinis puslapis) verčiamas atskirai - app/translations.php.

const LANGS = ['lt' => 'Lietuvių', 'en' => 'English', 'ru' => 'Русский'];

/** Dabartinė kalba. current_lang('lt') - šiai užklausai priverstinai lietuvių (pvz. redaguojant turinį) */
function current_lang(?string $force = null): string
{
    static $lang = null;
    if ($force !== null && isset(LANGS[$force])) {
        $lang = $force;
    }
    if ($lang === null) {
        $c = (string) ($_COOKIE['kalba'] ?? '');
        $lang = isset(LANGS[$c]) ? $c : 'lt';
    }
    return $lang;
}

/** Vertimų žodynas dabartine kalba: [lietuviškas tekstas => vertimas] */
function lang_dict(): array
{
    static $dicts = [];
    $l = current_lang();
    if (!isset($dicts[$l])) {
        $file = APP_DIR . '/lang/' . $l . '.php';
        $dicts[$l] = $l !== 'lt' && is_file($file) ? (require $file) : [];
    }
    return $dicts[$l];
}

/** Sąsajos tekstas dabartine kalba. {vardas} pakeičiamas iš $vars: t('Sveiki, {name}!', ['name' => $a['first_name']]) */
function t(string $lt, array $vars = []): string
{
    $s = lang_dict()[$lt] ?? $lt;
    if ($vars) {
        $map = [];
        foreach ($vars as $k => $v) {
            $map['{' . $k . '}'] = (string) $v;
        }
        $s = strtr($s, $map);
    }
    return $s;
}

/** t() HTML'ui (su e()) */
function te(string $lt, array $vars = []): string
{
    return e(t($lt, $vars));
}

/**
 * Skaičius su tinkama žodžio forma: tn(5, 'narys', 'nariai', 'narių') → „5 nariai“ / „5 members“ / „5 участников“.
 * Žodyne raktas „narys|nariai|narių“, vertimas „member|members“ (en) arba „участник|участника|участников“ (ru).
 */
function tn(int $n, string $one, string $few, string $many, bool $withNumber = true): string
{
    $forms = explode('|', t("$one|$few|$many"));
    $l = current_lang();
    $m10 = $n % 10;
    $m100 = $n % 100;
    if ($l === 'en') {
        $w = $n === 1 ? $forms[0] : ($forms[1] ?? $forms[0]);
    } elseif ($l === 'ru') {
        $w = $m10 === 1 && $m100 !== 11 ? $forms[0] : ($m10 >= 2 && $m10 <= 4 && ($m100 < 12 || $m100 > 14) ? ($forms[1] ?? $forms[0]) : ($forms[2] ?? $forms[0]));
    } else {
        $w = $m10 === 1 && $m100 !== 11 ? $forms[0] : ($m10 >= 2 && ($m100 < 10 || $m100 >= 20) ? $forms[1] : $forms[2]);
    }
    return $withNumber ? $n . ' ' . $w : $w;
}

/** JavaScript'ui: tr('Lietuviškas tekstas', {vardas: ...}) - vertimai tik tiems tekstams, kurie išvardyti app/lang/js.php */
function i18n_js(): string
{
    $out = [];
    if (current_lang() !== 'lt') {
        foreach (require APP_DIR . '/lang/js.php' as $k) {
            $out[$k] = t($k);
        }
    }
    return '<script>window.I18N=' . json_encode((object) $out, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';window.LANG=' . json_encode(current_lang())
        . ';window.tr=function(s,v){s=window.I18N[s]||s;if(v)for(var k in v)s=s.split("{"+k+"}").join(v[k]);return s;};</script>';
}

/** Kalbos pasirinkimas: „LT ▾“ su sąrašu (viršutinėje juostoje) */
function render_lang_switch(): string
{
    $cur = current_lang();
    $back = rawurlencode((string) ($_SERVER['REQUEST_URI'] ?? '/'));
    $html = '<details class="lang-switch"><summary aria-label="' . e(t('Kalba')) . '">' . strtoupper($cur) . '</summary><div class="lang-menu">';
    foreach (LANGS as $code => $name) {
        $html .= '<a href="' . e(url('kalba.php?l=' . $code . '&b=' . $back)) . '" lang="' . $code . '"' . ($code === $cur ? ' aria-current="true" class="active"' : '') . '>'
            . '<b>' . strtoupper($code) . '</b> ' . e($name) . '</a>';
    }
    return $html . '</div></details>';
}

// ---------- Datos ----------

/** Savaitės dienos, mėnesių pavadinimai (kilmininkas - datai: „spalio 6 d.“) ir vardininkas */
function weekday_names(): array
{
    switch (current_lang()) {
        case 'en': return [1 => 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        case 'ru': return [1 => 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'];
    }
    return [1 => 'Pirmadienis', 'Antradienis', 'Trečiadienis', 'Ketvirtadienis', 'Penktadienis', 'Šeštadienis', 'Sekmadienis'];
}

/** „pirmadieniais“ (tvarkaraštyje) */
function weekday_names_plural(): array
{
    switch (current_lang()) {
        case 'en': return [1 => 'Mondays', 'Tuesdays', 'Wednesdays', 'Thursdays', 'Fridays', 'Saturdays', 'Sundays'];
        case 'ru': return [1 => 'по понедельникам', 'по вторникам', 'по средам', 'по четвергам', 'по пятницам', 'по субботам', 'по воскресеньям'];
    }
    return [1 => 'pirmadieniais', 'antradieniais', 'trečiadieniais', 'ketvirtadieniais', 'penktadieniais', 'šeštadieniais', 'sekmadieniais'];
}

function month_names_gen(): array
{
    switch (current_lang()) {
        case 'en': return [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        case 'ru': return [1 => 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    }
    return [1 => 'sausio', 'vasario', 'kovo', 'balandžio', 'gegužės', 'birželio', 'liepos', 'rugpjūčio', 'rugsėjo', 'spalio', 'lapkričio', 'gruodžio'];
}

function month_names(): array
{
    switch (current_lang()) {
        case 'en': return [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        case 'ru': return [1 => 'январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'];
    }
    return [1 => 'sausis', 'vasaris', 'kovas', 'balandis', 'gegužė', 'birželis', 'liepa', 'rugpjūtis', 'rugsėjis', 'spalis', 'lapkritis', 'gruodis'];
}

/** Data: „spalio 6 d.“ / „6 October“ / „6 октября“ (kitais metais - su metais) */
function fmt_date(?string $date, bool $forceYear = false): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    $month = month_names_gen()[(int) date('n', $ts)];
    $year = $forceYear || date('Y', $ts) !== date('Y');
    switch (current_lang()) {
        case 'en': return date('j', $ts) . ' ' . $month . ($year ? ' ' . date('Y', $ts) : '');
        case 'ru': return date('j', $ts) . ' ' . $month . ($year ? ' ' . date('Y', $ts) . ' г.' : '');
    }
    return ($year ? date('Y', $ts) . ' m. ' : '') . $month . ' ' . date('j', $ts) . ' d.';
}
