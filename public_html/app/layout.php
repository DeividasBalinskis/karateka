<?php
// Bendras puslapio karkasas (antraštė, navigacija, poraštė)

function page_start(string $title, array $opt = []): void
{
    $a = current_account();
    $admin = !empty($opt['admin']);
    if ($admin && can('valdymas', $a) && !admin_mode() && !headers_sent()) {
        admin_mode_set(true);   // atidarius valdymo puslapį - admin režimas įsijungia
    }
    $flashes = take_flashes();
    $css = url('assets/app.css') . '?v=' . @filemtime(PUBLIC_DIR . '/assets/app.css');
    ?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>"<?= !empty($opt['native']) ? ' data-native' : '' ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0" id="vp">
<script>/* Planšetėje - toks pat vaizdas kaip kompiuteryje (Deividas, 2026-11-22): jutiklinis 600-1100 px ekranas rodo 1280 px puslapį, sumažintą */
(function () { var s = screen, m = Math.min(s.width, s.height); if (m >= 600 && m <= 1100 && window.matchMedia && matchMedia('(pointer: coarse)').matches && !document.documentElement.hasAttribute('data-native')) document.getElementById('vp').setAttribute('content', 'width=1280'); })();</script>
<title><?= e(t($title)) ?> <?= te('— Karateka') ?></title>
<?php if (!empty($opt['description'])): ?><meta name="description" content="<?= e($opt['description']) ?>">
<?php endif; ?>
<?php if (!empty($opt['og'])): /* Facebook / Messenger nuorodos peržiūra */ ?>
<meta property="og:site_name" content="Karate Ateitis">
<meta property="og:locale" content="<?= ['lt' => t('lt_LT'), 'en' => t('en_GB'), 'ru' => t('ru_RU')][current_lang()] ?>">
<?php foreach ($opt['og'] as $k => $v): if ($v): ?><meta property="og:<?= e($k) ?>" content="<?= e($v) ?>">
<?php endif; endforeach; ?>
<?php endif; ?>
<?php if ($admin || !empty($opt['noindex'])): ?><meta name="robots" content="noindex">
<?php endif; ?>
<link rel="icon" type="image/png" sizes="32x32" href="<?= url('favicon-32.png?v=2') ?>">
<link rel="apple-touch-icon" href="<?= url('apple-touch-icon.png?v=2') ?>">
<link rel="manifest" href="<?= url('manifest.json') ?>">
<meta name="theme-color" content="#811517">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('assets/tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('assets/header.css')) ?>">
<link rel="stylesheet" href="<?= e($css) ?>">
<link rel="stylesheet" href="<?= e(asset_url('assets/widgets.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('assets/themes.css')) ?>">
<?= font_head_html() ?>
<?= i18n_js() ?>
</head>
<body<?= theme_body_attrs(current_theme(), $admin ? 'is-admin' : '') ?>>
<?php
    // Renginiai, Naujienos, Pamokos - viena vieta visiems (ir treneriui su redagavimo mygtukais), be trenerio skirtukų juostos
    $section = $opt['section'] ?? null;
    // 'bare' (egzamino registracijos nuoroda, asmeninis egzamino puslapis be paskyros): tik logotipas ir kalba - jokio meniu, kad nuoroda leistų tik užsiregistruoti
    $GLOBALS['page_bare'] = !empty($opt['bare']);
    if ($GLOBALS['page_bare']) {
        echo '<header class="site-header is-bare"><nav><span class="logo"><img src="' . e(url('img/logo.png')) . '" alt="' . e(t('Karateka logotipas')) . '"></span>'
            . '<div class="nav-prefs">' . render_lang_switch() . '</div></nav></header>';
    } else {
        site_header(false, $admin ? 'admin' : (!empty($opt['account']) ? 'account' : null), $opt['account'] ?? null, $section);
    } ?>
<main class="page <?= !empty($opt['narrow']) ? 'narrow' : '' ?><?= !empty($opt['wide']) ? ' wide' : '' ?><?= !empty($GLOBALS['adm_side']) ? ' has-side' : '' ?>">
<?php if (!empty($GLOBALS['adm_side'])): ?><div class="adm-shell"><?= $GLOBALS['adm_side'] ?><div class="adm-main"><?php endif; ?>
<?php foreach ($flashes as $f): ?>
  <div class="flash flash-<?= e($f['type']) ?>"><?= e(t($f['message'])) ?></div>
<?php endforeach; ?>
<?= device_code_bar() ?>
<?php
}

/** Juosta įrenginiui, prisijungusiam su administratoriaus kodu: kieno teisėmis, iki kada, „Atsijungti“ */
function device_code_bar(): string
{
    $dc = device_code_current();
    if (!$dc) {
        return '';
    }
    $a = current_account();
    return '<div class="dc-bar" role="status"><span>🔑 ' . e(t('Šis įrenginys prisijungęs su kodu')) . ' · ' . e($a['first_name']) . ' · ' . e(t('iki')) . ' '
        . e(date('m-d H:i', strtotime((string) $dc['session_until']))) . '</span><form method="post" action="' . url('atsijungti.php') . '">' . csrf_field()
        . '<button type="submit" class="btn btn-ghost btn-sm">' . e(t('Atsijungti')) . '</button></form></div>';
}

function page_end(): void
{
    ?>
<?php if (!empty($GLOBALS['adm_side'])): ?></div></div><?php endif; ?>
</main>
<?php if (!empty($GLOBALS['page_bare'])): ?>
<footer class="site-footer is-bare"><div class="footer-bottom"><span>© <?= date('Y') ?> <?= te('VšĮ Karate Ateitis') ?></span></div></footer>
<?= feedback_widget_html() /* tik 💡 (personalui - pastaboms apie šį puslapį), be žinučių ir kitų mygtukų */ ?>
<script src="<?= e(asset_url('assets/header.js')) ?>"></script>
</body>
</html>
<?php return; endif; ?>
<footer class="site-footer">
  <div class="footer-bottom">
    <span>© <?= date('Y') ?> <?= te('VšĮ Karate Ateitis ·') ?> <a href="<?= url('privatumas.php') ?>"><?= te('Privatumo politika') ?></a><?= current_account() ? ' · <a href="' . url('klausimai.php') . '">' . te('Klausimai ir atsakymai') . '</a>' : '' ?><?= footer_links_html() ?></span>
    <span><?= te('Nuo balto iki juodo diržo.') ?></span>
  </div>
</footer>
<?= page_widgets() ?>
<script src="<?= e(asset_url('assets/header.js')) ?>"></script>
<?php if (!empty($GLOBALS['adm_side']) && can('settings')): /* v2.02: meniu tvarkymas „✎“ */ ?>
<script src="<?= e(asset_url('assets/admin-menu.js')) ?>" defer></script>
<?php endif; ?>
<?php if (($acc = current_account()) && is_active_account($acc) && game_nav_ready()): /* kvietimai žaisti - mažas langelis bet kuriame puslapyje (tik kas mato žaidimą) */ ?>
<script src="<?= e(asset_url('assets/game-invites.js')) ?>" data-api="<?= e(url('zaidimas.php')) ?>" data-csrf="<?= e(csrf_token()) ?>" data-arena="<?= e(url('arena.php')) ?>" data-keturi="<?= e(url('keturi.php')) ?>" defer></script>
<?php endif; ?>
</body>
</html>
<?php
}

/** Klaidų sąrašas formos viršuje */
function form_errors(array $errors): string
{
    if (!$errors) {
        return '';
    }
    $html = '<div class="flash flash-err"><ul>';
    foreach ($errors as $err) {
        $html .= '<li>' . e(t($err)) . '</li>';
    }
    return $html . '</ul></div>';
}

/** <option> sąrašas grupėms */
function group_options(?int $selected, bool $withEmpty = true): string
{
    $html = $withEmpty ? ('<option value="">' . t('— pasirinkite grupę —') . '</option>') : '';
    $current = null;
    foreach (q_all('SELECT * FROM training_groups WHERE is_active = 1 ORDER BY sort_order, name') as $g) {
        if ($g['category'] !== $current) {
            $html .= ($current !== null ? '</optgroup>' : '') . '<optgroup label="' . e(t(GROUP_CATEGORIES[$g['category']])) . '">';
            $current = $g['category'];
        }
        $html .= '<option value="' . (int) $g['id'] . '"' . ((int) $g['id'] === $selected ? ' selected' : '') . '>' . e($g['name']) . '</option>';
    }
    return $html . ($current !== null ? '</optgroup>' : '');
}

// Grupių spalvos (jei grupei nepriskirta - parenkama pagal id), kad kvadratai „Treniruotė“ puslapyje skirtųsi
const GROUP_COLORS = ['#2F6B5A', '#A85C7E', '#5C68A0', '#E6842B', '#4C8353', '#B5281F', '#2E6FA8', '#8A6A0E', '#6B4A34', '#7860A0', '#1F7A8C', '#C2185B'];

function group_color(array $g): string
{
    static $order = null;
    if (preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($g['color'] ?? ''))) {
        return $g['color'];
    }
    if ($order === null) {   // be spalvos - pagal grupių eilę, kad gretimos grupės nesutaptų
        $order = array_flip(array_map('intval', q('SELECT id FROM training_groups ORDER BY sort_order, id')->fetchAll(PDO::FETCH_COLUMN)));
    }
    return GROUP_COLORS[($order[(int) $g['id']] ?? (int) $g['id']) % count(GROUP_COLORS)];
}

/** Grupės pavadinimas be kategorijos priešdėlio („Vaikai: Viršuliškės“ -> „Viršuliškės“) kvadratams */
function group_short_name(array $g): string
{
    return preg_replace('/^(Vaikai|Jaunimas|Suaugusieji)\s*:\s*/u', '', (string) $g['name']);
}

/** Mygtukas "Redaguoti" viešuose puslapiuose - matomas tik treneriams */
function staff_link(string $path, string $label = '✎ Redaguoti'): string
{
    if (!staff_ui()) {
        return '';
    }
    return '<a class="staff-link" href="' . e(url($path)) . '">' . e(t($label)) . '</a>';
}

/**
 * "Kam skirta?" varnelės: "Visos grupės", kiekviena kategorija (Vaikai / Jaunimas / Suaugusieji) ir jos grupės.
 * $selected tuščias = visoms grupėms (tada pažymėta viskas).
 */
function audience_field(array $selected, string $allLabel = 'Visos grupės', bool $emptyMeansAll = true): string
{
    $groups = q_all('SELECT * FROM training_groups WHERE is_active = 1 ORDER BY sort_order, name');
    $sel = array_map('intval', $selected);
    $all = !$sel && $emptyMeansAll;
    $byCat = [];
    foreach ($groups as $g) {
        $byCat[$g['category']][] = $g;
    }
    $box = function (array $g, string $label, string $cls) use ($sel, $all): string {
        $on = $all || in_array((int) $g['id'], $sel, true);
        return '<label class="check ' . $cls . '"><input type="checkbox" name="groups[]" value="' . (int) $g['id'] . '" data-cat="' . e($g['category']) . '"'
            . ($on ? ' checked' : '') . '><span>' . e($label) . '</span></label>';
    };
    $html = '<div class="audience">'
        . '<label class="check aud-all"><input type="checkbox" data-aud="all"' . ($all ? ' checked' : '') . '><span>' . e($allLabel) . '</span></label>';
    foreach (array_map('t', GROUP_CATEGORIES) as $cat => $catLabel) {
        if (empty($byCat[$cat])) {
            continue;
        }
        $list = $byCat[$cat];
        if (count($list) === 1) {
            // Kategorija su viena grupe (pvz. Jaunimas) - viena varnelė
            $html .= $box($list[0], $catLabel, 'aud-cat');
            continue;
        }
        $html .= '<label class="check aud-cat"><input type="checkbox" data-aud="cat" data-cat="' . e($cat) . '"><span>' . e($catLabel)
            . (' <span class="muted">' . t('(visos') . ' ') . count($list) . ')</span></span></label><div class="aud-sub">';
        foreach ($list as $g) {
            // "Vaikai: Viršuliškės" -> "Viršuliškės" (kategorija jau parašyta virš sąrašo)
            $label = preg_replace('/^' . preg_quote($catLabel, '/') . ':\s*/u', '', $g['name']);
            $html .= $box($g, $label, 'aud-group');
        }
        $html .= '</div>';
    }
    $html .= ($emptyMeansAll ? ('<p class="hint" style="margin-top:6px;">' . t('Nieko nepažymėjus - visiems.') . '</p>') : '') . '</div>';
    static $js = false;
    if (!$js) {
        $js = true;
        $html .= <<<'JS'
<script>
document.querySelectorAll('.audience').forEach(function (box) {
  var all = box.querySelector('[data-aud="all"]');
  var groups = [].slice.call(box.querySelectorAll('input[name="groups[]"]'));
  var cats = [].slice.call(box.querySelectorAll('[data-aud="cat"]'));
  function inCat(c) { return groups.filter(function (g) { return g.dataset.cat === c.dataset.cat; }); }
  function sync() {
    cats.forEach(function (c) {
      var n = inCat(c).filter(function (g) { return g.checked; }).length;
      c.checked = n === inCat(c).length; c.indeterminate = n > 0 && !c.checked;
    });
    var n = groups.filter(function (g) { return g.checked; }).length;
    all.checked = n === groups.length; all.indeterminate = n > 0 && !all.checked;
  }
  all.addEventListener('change', function () { groups.forEach(function (g) { g.checked = all.checked; }); sync(); });
  cats.forEach(function (c) { c.addEventListener('change', function () { inCat(c).forEach(function (g) { g.checked = c.checked; }); sync(); }); });
  groups.forEach(function (g) { g.addEventListener('change', sync); });
  sync();
});
</script>
JS;
    }
    return $html;
}

/** Pažymėtos grupės iš "Kam skirta?". Pažymėtos visos (arba nė viena) = [] (visiems, taip pat ir vėliau sukurtoms grupėms) */
function audience_from_post(): array
{
    $ids = array_values(array_unique(array_map('intval', (array) ($_POST['groups'] ?? []))));
    $active = array_map('intval', q('SELECT id FROM training_groups WHERE is_active = 1')->fetchAll(PDO::FETCH_COLUMN));
    return array_diff($active, $ids) ? $ids : [];
}

/** Visos treniruočių vietos (iš grupių) - pasirinkimui iš sąrašo */
function training_locations(): array
{
    return q('SELECT DISTINCT location FROM training_groups WHERE location IS NOT NULL AND location <> "" ORDER BY location')
        ->fetchAll(PDO::FETCH_COLUMN);
}

/** Vietos laukas: sąrašas iš treniruočių vietų + "Kita vieta..." su įrašymu ranka */
function location_field(string $name, ?string $value, bool $required = false): string
{
    $locations = training_locations();
    $isOther = $value !== null && $value !== '' && !in_array($value, $locations, true);
    $id = 'loc_' . $name;
    $html = '<label>Vieta <select name="' . e($name) . '_pick" id="' . $id . '" ' . ($required ? 'required' : '') . '>'
        . ('<option value="">' . t('— pasirinkite vietą —') . '</option>');
    foreach ($locations as $loc) {
        $html .= '<option value="' . e($loc) . '"' . ($loc === $value ? ' selected' : '') . '>' . e($loc) . '</option>';
    }
    $html .= '<option value="__other"' . ($isOther ? ' selected' : '') . '>Kita vieta…</option></select></label>'
        . '<label id="' . $id . '_other" ' . ($isOther ? '' : 'style="display:none;"') . '>Kita vieta <input type="text" name="' . e($name) . '_other" value="' . ($isOther ? e($value) : '') . '" placeholder="' . e(t('pvz. Kaunas, Žalgirio arena')) . '"></label>'
        . '<script>(function(){var s=document.getElementById("' . $id . '"),o=document.getElementById("' . $id . '_other");'
        . 's.addEventListener("change",function(){o.style.display=s.value==="__other"?"":"none";if(s.value==="__other")o.querySelector("input").focus();});})();</script>';
    return $html;
}

/** Vietos reikšmė iš formos (žr. location_field) */
function location_from_post(string $name): string
{
    $pick = post($name . '_pick');
    return $pick === '__other' ? post($name . '_other') : $pick;
}

/** Statinio failo adresas su versija (kad naršyklė paimtų naują po pakeitimo) */
function asset_url(string $path): string
{
    return url($path) . '?v=' . @filemtime(PUBLIC_DIR . '/' . $path);
}

/**
 * ES moduliai (D3, pvz. assets/varzybos/main.js): import map - kiekvienas to aplanko .js failas su savo ?v= (kaip asset_url),
 * kad naršyklė po pakeitimo paimtų naują; tada pradinis modulis. Naršyklė be import map ima tuos pačius failus be ?v=.
 */
function asset_module(string $entry): string
{
    $dir = trim(str_replace('\\', '/', dirname($entry)), '/');
    $base = PUBLIC_DIR . '/' . $dir;
    $map = [];
    if (is_dir($base)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && $f->getExtension() === 'js') {
                $rel = $dir . '/' . str_replace('\\', '/', substr($f->getPathname(), strlen($base) + 1));
                $map[url($rel)] = asset_url($rel);
            }
        }
    }
    ksort($map);
    return '<script type="importmap">' . json_encode(['imports' => $map], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>'
        . '<script type="module" src="' . e(asset_url($entry)) . '"></script>';
}

/** Kaip įsidiegti svetainę telefone kaip programėlę (įdiegus - nerodoma) */
function render_app_install_card(): string
{
    return ('<div class="panel card app-card" id="programele"><div class="kicker">' . t('Programėlė telefone') . '</div>')
        . ('<p class="small">' . t('Karateka gali atsidaryti kaip programėlė - su ikona telefono ekrane, be naršyklės juostos.') . '</p>')
        . ('<button type="button" class="btn btn-primary btn-sm" data-install-app hidden>' . t('Įdiegti programėlę') . '</button>')
        . ('<ul class="app-steps small muted"><li><strong>' . t('Android (Chrome):') . '</strong> ' . t('meniu ⋮ → „Įdiegti programą“ (ar „Pridėti prie pradžios ekrano“).') . '</li>')
        . ('<li><strong>' . t('iPhone (Safari):') . '</strong> ' . t('„Bendrinti“ → „Į pradžios ekraną“.') . '</li>')
        . ('<li><strong>' . t('Jei rodo „Nesaugi programa užblokuota“:') . '</strong> ' . t('tai Google apsauga (Play Protect) taip įvertina svetainės ženkliuką. Spauskite „Supratau“, tada naršyklės meniu ⋮ → „Pridėti prie pradžios ekrano“ → „Sukurti nuorodą“ - veiks taip pat.') . '</li></ul></div>');
}

// ---------- Valdymo meniu išdėstymas (#106): ne daugiau 2 lygių ----------
// B9 (sprendimas 2026-11-21): bandomieji meniu variantai išimti - vienas išdėstymas visiems:
// kompiuteryje šoninis meniu (planšetėje - eilutė su „Daugiau“), telefone - apatinė juosta.
function phone_menu_variant(): string
{
    return 'apacia';
}

function menu_variant(): string
{
    return 'abu';
}

/** Visi valdymo skyriai, kuriuos mato ši paskyra: [raktas => [ikona, pavadinimas, kelias, skaičiukas, paaiškinimas]] */
function admin_menu_items(): array
{
    $it = [
        'siandien'   => ['🏠', t('Šiandien'), 'admin/', 0, t('Darbai, pranešimai, šiandienos grupės')],
        'treniruote' => ['🥋', t('Treniruotė'), 'admin/treniruote.php', 0, t('Lankomumas, pastabos, taškai')],
        'egzaminai'  => ['📋', t('Egzaminai'), 'admin/egzaminai.php', 0, t('Protokolai, registracija, kainos')],
        'renginiai'  => ['📅', t('Renginiai'), 'admin/renginiai.php', 0, t('Renginiai, kvietimai, dalyviai, rezultatai')],
        'nariai'     => ['👥', t('Nariai'), 'admin/nariai.php', 0, t('Visi nariai, diržai, kontaktai')],
        'patvirtinimai' => ['✅', t('Patvirtinimai'), 'admin/patvirtinimai.php', pending_approvals_count(), t('Nauji nariai ir „Moku“ punktai')],
    ];
    if (can('feedback')) {
        $it['atsiliepimai'] = ['💡', t('Atsiliepimai'), 'admin/atsiliepimai.php', feedback_new_count(), t('Pastabos apie svetainę')];
    }
    if (can('review') && function_exists('review_ready') && review_ready() && review_enabled()) {   // v2.02: peržiūra - ką taisyti, darbų eilė, auksinės žymos (v2.10: tik įjungus)
        $it['perziura'] = ['🔎', t('Peržiūra'), 'admin/perziura.php', review_todo_count(), t('Ką taisyti, darbų eilė, auksinės žymos')];
    }
    if (can('justifications') && function_exists('just_ready') && just_ready()) {   // v1.93: pamokų pateisinimai
        $it['pateisinimai'] = ['📄', t('Pateisinimai'), 'admin/pateisinimai.php', just_new_count(), t('Prašymai pateisinti praleistas pamokas')];
    }
    if (can('broadcast')) {   // #121
        $it['pranesimai'] = ['✉️', t('Pranešimai'), 'admin/pranesimai.php', 0, t('Laiškai ir SMS nariams')];
    }
    $it['xp'] = ['⭐', t('Aktyvumo taškai'), 'admin/taskai.php', 0, t('Taškai, lygiai, reitingas')];
    $it['testai'] = ['❓', t('Testai'), 'admin/testai.php', 0, t('Teorijos testai egzaminams')];
    if (can('ltkf')) {   // #160: federacijos registrai - nariai, klubai, teisėjai
        $it['federacija'] = ['📒', t('Federacijos registrai'), 'admin/federacija.php', 0, t('Nariai, klubai, teisėjai')];
    }
    if (can('ltkf') && function_exists('ltkf_url') && ltkf_url() !== '') {   // B9: LTKF svetainė - ir iš Valdymo
        $it['ltkf'] = ['🏛️', t('LTKF svetainė'), 'ltkf/', 0, t('Federacijos svetainės turinys')];
    }
    if (can('links')) {   // C3: visos slaptos nuorodos vienoje vietoje
        $it['nuorodos'] = ['🔗', t('Visos nuorodos'), 'admin/nuorodos.php', 0, t('Slaptos nuorodos: kopijuoti, pakeisti, išjungti')];
    }
    $it['nustatymai'] = ['⚙️', t('Nustatymai'), 'admin/nustatymai.php', 0, t('Grupės, tvarkaraštis, vertimai, paskyra')];
    return $it;
}

/** v2.02: numatytas išdėstymas - dažni darbai viršuje, nustatymai apačioje */
function admin_menu_default(): array
{
    return [
        ['id' => 'kasdien', 'name' => '', 'items' => ['siandien', 'treniruote', 'egzaminai', 'renginiai', 'nariai']],
        ['id' => 'darbai', 'name' => '', 'items' => ['patvirtinimai', 'atsiliepimai', 'perziura', 'pateisinimai', 'pranesimai']],
        ['id' => 'nustatymai', 'name' => '', 'items' => ['xp', 'testai', 'federacija', 'ltkf', 'nuorodos', 'nustatymai']],
    ];
}

/** Numatytų grupių pavadinimai (verčiami); savo pavadinimai - tokie, kokius įrašė administratorius */
function admin_menu_group_default_name(string $id): string
{
    $n = ['kasdien' => t('Kasdien'), 'darbai' => t('Darbai'), 'nustatymai' => t('Nustatymai')];
    return $n[$id] ?? '';
}

/** Administratoriaus išsaugotas išdėstymas (site_setting admin_menu): ['groups' => [...], 'names' => [raktas => pavadinimas]] */
function admin_menu_layout(): array
{
    $d = json_decode((string) site_setting('admin_menu', ''), true);
    $groups = [];
    if (is_array($d) && !empty($d['groups']) && is_array($d['groups'])) {
        foreach ($d['groups'] as $g) {
            if (is_array($g) && preg_match('/^[a-z0-9-]{1,24}$/', (string) ($g['id'] ?? ''))) {
                $groups[] = ['id' => (string) $g['id'], 'name' => mb_substr(trim((string) ($g['name'] ?? '')), 0, 30),
                    'items' => array_values(array_filter((array) ($g['items'] ?? []), 'is_string'))];
            }
        }
    }
    $names = is_array($d) && is_array($d['names'] ?? null) ? array_map(function ($v) { return mb_substr(trim((string) $v), 0, 30); }, $d['names']) : [];
    return ['groups' => $groups ?: admin_menu_default(), 'names' => array_filter($names, 'strlen'), 'custom' => (bool) $groups];
}

/** Valdymo skyriai grupėmis pagal išdėstymą: [grupė => [pavadinimas, [raktas => [ikona, pavadinimas, kelias, skaičiukas, paaiškinimas]]]] */
function admin_menu_groups(): array
{
    static $memo = null;
    if ($memo !== null) {
        return $memo;
    }
    $items = admin_menu_items();
    $lay = admin_menu_layout();
    $out = [];
    $placed = [];
    foreach ($lay['groups'] as $g) {
        $list = [];
        foreach ($g['items'] as $k) {
            if (isset($items[$k]) && !isset($placed[$k])) {
                $list[$k] = $items[$k];
                if (isset($lay['names'][$k])) {
                    $list[$k][1] = $lay['names'][$k];
                }
                $placed[$k] = true;
            }
        }
        $out[$g['id']] = [$g['name'] !== '' ? $g['name'] : (admin_menu_group_default_name($g['id']) ?: t('Grupė')), $list];
    }
    // Naujas skyrius, kurio dar nėra išsaugotame išdėstyme - į paskutinę grupę (niekas nedingsta)
    $last = array_key_last($out);
    foreach ($items as $k => $it) {
        if (!isset($placed[$k])) {
            $out[$last][1][$k] = $it;
        }
    }
    return $memo = $out;
}

/** Meniu redaktoriui (assets/admin-menu.js): grupės su visais skyriais, numatyti ir savi pavadinimai */
function admin_menu_editor_data(): array
{
    $items = admin_menu_items();
    $lay = admin_menu_layout();
    $own = [];
    foreach ($lay['groups'] as $g) {
        $own[$g['id']] = $g['name'];
    }
    $groups = [];
    foreach (admin_menu_groups() as $gid => [$gname, $list]) {
        $row = ['id' => (string) $gid, 'name' => $own[$gid] ?? '', 'def' => admin_menu_group_default_name((string) $gid), 'items' => []];
        foreach ($list as $k => $it) {
            $row['items'][] = ['k' => $k, 'ic' => $it[0], 'name' => $lay['names'][$k] ?? '', 'def' => $items[$k][1]];
        }
        $groups[] = $row;
    }
    return ['groups' => $groups, 'custom' => $lay['custom'], 't' => [
        'title' => t('Meniu tvarkymas'), 'hint' => t('Tempkite už ⠿ į kitą vietą ar grupę (arba ↑ ↓). Pavadinimus galima keisti.'),
        'add' => t('+ Nauja grupė'), 'newg' => t('Nauja grupė'), 'del' => t('Ištrinti grupę'), 'save' => t('Išsaugoti'), 'cancel' => t('Atšaukti'),
        'reset' => t('Numatytas meniu'), 'resetq' => t('Grąžinti numatytą meniu? Jūsų grupės ir pavadinimai bus pamiršti.'),
        'delq' => t('Ištrinti grupę? Jos skyriai pereis į pirmą grupę.'), 'empty' => t('Tempkite skyrių čia'), 'err' => t('Nepavyko išsaugoti. Atnaujinkite puslapį ir bandykite dar kartą.'),
        'up' => t('Aukštyn'), 'down' => t('Žemyn'), 'gname' => t('Grupės pavadinimas'), 'iname' => t('Skyriaus pavadinimas'),
    ]];
}

/** Kuris valdymo skyrius atidarytas (raktas iš admin_menu_groups) */
function admin_menu_key(): string
{
    $file = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
    $map = ['index' => 'siandien', 'pirmas-adminas' => 'siandien', 'treniruote' => 'treniruote', 'egzaminai' => 'egzaminai', 'egzamino-protokolas' => 'egzaminai', 'federacija' => 'federacija',
            'programa' => 'egzaminai', 'pranesimai' => 'pranesimai', 'sms' => 'pranesimai', 'siuntimas' => 'pranesimai', 'nariai' => 'nariai', 'taskai' => 'xp', 'testai' => 'testai',
            'patvirtinimai' => 'patvirtinimai', 'atsiliepimai' => 'atsiliepimai', 'pateisinimai' => 'pateisinimai', 'perziura' => 'perziura',
            'renginiai' => 'renginiai', 'renginio-dalyviai' => 'renginiai', 'renginio-rezultatai' => 'renginiai'];
    if (isset($map[$file])) {
        return $map[$file];
    }
    return in_array($file, ['nustatymai', 'grupes', 'tvarkarastis', 'paskyros', 'kopijos', 'duomenys', 'nustatymai-visi', 'atsaukimai', 'testavimas', 'vertimai'], true) ? 'nustatymai' : '';
}

/** Visi skyriai vienu sąrašu: [raktas => [ikona, pavadinimas, kelias, skaičiukas, paaiškinimas]] */
function admin_menu_flat(): array
{
    $out = [];
    foreach (admin_menu_groups() as [$g, $items]) {
        $out += $items;
    }
    return $out;
}

function admin_menu_link(string $key, array $it, string $cur, string $cls = ''): string
{
    return '<a href="' . e(url($it[2])) . '" class="' . trim($cls . ($key === $cur ? ' active' : '')) . '"' . ($key === $cur ? ' aria-current="page"' : '') . '>'
        . '<span class="am-ic" aria-hidden="true">' . $it[0] . '</span><span class="am-t">' . e($it[1]) . '</span>' . (!empty($it[3]) ? '<span class="count-badge">' . (int) $it[3] . '</span>' : '') . '</a>';
}

/** „⚙ Daugiau ▾“: reti skyriai sąraše (eilutės variantui) */
function admin_menu_more(array $keys, string $cur): string
{
    $all = admin_menu_flat();
    $rest = array_diff_key($all, array_flip($keys));
    $badge = array_sum(array_map(function ($i) { return (int) $i[3]; }, $rest));
    $isCur = isset($rest[$cur]);
    $html = '<details class="am-more"><summary class="' . ($isCur ? 'active' : '') . '">' . ($isCur ? '<span class="am-ic" aria-hidden="true">' . $rest[$cur][0] . '</span>' . e($rest[$cur][1]) : '⚙ ' . e(t('Daugiau')))
        . ($badge ? ' <span class="count-badge">' . $badge . '</span>' : '') . '<svg width="12" height="12" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></summary><div class="am-more-panel">';
    foreach ($rest as $k => $it) {
        $html .= admin_menu_link($k, $it, $cur, 'am-row') ;
    }
    return $html . '</div></details>';
}

/** Telefono meniu: svetainės skiltys (sąrašui ar plytelėms) */
function phone_site_links(string $h, array $onpage, ?array $a): string
{
    $html = '';
    foreach ($onpage as [$label, $anchor]) {
        $html .= '<a class="am-row" href="' . $h . e($anchor) . '"><span class="am-ic" aria-hidden="true">🏠</span><span class="am-t">' . e(t($label)) . '</span></a>';
    }
    $html .= '<a class="am-row" href="' . e(url('naujienos.php')) . '"><span class="am-ic" aria-hidden="true">📰</span><span class="am-t">' . e(t('Naujienos ir renginiai')) . '</span></a>';
    if ($a && can_see_lessons($a)) {
        $html .= '<a class="am-row" href="' . e(url('pamokos.php')) . '"><span class="am-ic" aria-hidden="true">📘</span><span class="am-t">' . e(t('Pamokos')) . '</span></a>';
    }
    if ($a && is_active_account($a) && game_nav_ready()) {
        $html .= '<a class="am-row" href="' . e(url('dojo.php')) . '"><span class="am-ic" aria-hidden="true">🏯</span><span class="am-t">' . e(t('Dojo')) . '</span></a>';
    }
    return $html;
}

/** Šoninis meniu: visos grupės (su paaiškinimais - jei $withDesc) */
function admin_menu_groups_html(string $cur, bool $withDesc): string
{
    $html = '';
    foreach (admin_menu_groups() as $gid => [$gname, $items]) {
        if (!$items) {
            continue;   // tuščia grupė (pvz. po redagavimo) - nerodoma
        }
        $html .= '<div class="am-group" data-g="' . e((string) $gid) . '"><div class="am-gh">' . e($gname) . '</div>';
        foreach ($items as $k => $it) {
            $html .= '<a href="' . e(url($it[2])) . '" data-k="' . e($k) . '" class="am-row' . ($k === $cur ? ' active' : '') . '"' . ($k === $cur ? ' aria-current="page"' : '') . '><span class="am-ic" aria-hidden="true">' . $it[0] . '</span>'
                . '<span class="am-t">' . e($it[1]) . ($withDesc ? '<small>' . e($it[4]) . '</small>' : '') . '</span>' . (!empty($it[3]) ? '<span class="count-badge">' . (int) $it[3] . '</span>' : '') . '</a>';
        }
        $html .= '</div>';
    }
    return $html;
}

/** Ar rodyti „🏯 Dojo“ meniu: žaidimo lentelės sukurtos ir ši paskyra mato žaidimą (Nustatymai → „🏯 Žaidimas“) */
function game_nav_ready(): bool
{
    require_once APP_DIR . '/game.php';
    // #140 + sprendimas 2026-11-21: kol žaidimas neatidarytas nariams - meniu jį mato tik tikri administratoriai (ne testuotojai, ne nariai)
    return can('dojo_menu');
}

/** Nario paskyros skirtukai */
function account_nav_items(array $a): array
{
    // Sprendimas (27): 7-10 m. vaikui, kuris jungiasi pats - „Mano diena“ vietoj „Apžvalga“
    $selfBirth = (string) q_value('SELECT m.birth_date FROM account_members am JOIN members m ON m.id = am.member_id WHERE am.account_id = ? AND am.relation = "self"', [(int) $a['id']]);
    $little = $selfBirth !== '' && age_on($selfBirth) <= 10;
    $items = ['apzvalga' => [$little ? t('Mano diena') : t('Apžvalga'), 'paskyra.php', account_attention_count((int) $a['id'])]];
    if ($a['status'] === 'active') {
        if (!can('valdymas', $a) || account_members((int) $a['id'])) {
            foreach (account_nav_extra($a) as $key => $item) {
                $items[$key] = $item;
            }
        }
    }
    $items['nustatymai'] = [t('Nustatymai'), 'paskyra.php?skiltis=nustatymai'];
    // Skirtumai - atskiruose languose: tėvams (vaikai), treneriui / adminui (valdymas)
    if (can('valdymas', $a)) {
        $items['valdymas'] = [t('🛠 Valdymas'), 'admin/', staff_attention_count((int) $a['id'])];
    } elseif (array_filter(account_members((int) $a['id']), function ($m) { return $m['relation'] === 'parent'; })) {
        $items['vaikai'] = [t('👪 Tėvams'), 'paskyra.php?skiltis=nustatymai#vaikai'];
    }
    return $items;
}

/**
 * B8: nario apatinės juostos skirtukai: [ženkliukas, pavadinimas, adresas, raudonas skaičius, ar dabartinis].
 * Mažam vaikui (7-10) - „Mano diena“ vietoj „Apžvalga“.
 */
function member_tab_items(array $a, ?string $area, string $tab, ?string $section): array
{
    $selfBirth = (string) q_value('SELECT m.birth_date FROM account_members am JOIN members m ON m.id = am.member_id WHERE am.account_id = ? AND am.relation = "self"', [(int) $a['id']]);
    $little = $selfBirth !== '' && age_on($selfBirth) <= 10;
    $acc = $area === 'account';
    return [
        ['🏠', $little ? t('Mano diena') : t('Apžvalga'), 'paskyra.php', account_attention_count((int) $a['id']), $acc && $tab === 'apzvalga'],
        ['📅', t('Renginiai'), 'naujienos.php', account_pending_invites((int) $a['id']), in_array($section, ['naujienos', 'renginiai'], true)],
        ['📋', t('Užduotys'), 'uzduotys.php', 0, $acc && $tab === 'uzduotys'],
        ['🥋', t('Egzaminas'), 'egzaminas.php', 0, $acc && $tab === 'egzaminas'],
    ];
}

/** Skirtukai tarp „Apžvalga“ ir „Nustatymai“. Renginiai ir Pamokos - viršutinėje juostoje, čia nedubliuojama. */
function account_nav_extra(array $a): array
{
    return [
        'uzduotys'  => [t('Užduotys'), 'uzduotys.php'],
        'egzaminas' => [t('Egzaminas'), 'egzaminas.php'],
    ];
}

/** Treneriui skilties viršuje: ką čia galima sukurti ar sutvarkyti. $links = [[tekstas, kelias, pagrindinis?], ...] */
function staff_bar(array $links): string
{
    if (!staff_ui()) {
        return '';
    }
    $html = ('<div class="staff-bar"><span class="staff-bar-label">' . t('Treneriui') . '</span>');
    foreach ($links as $l) {
        $html .= '<a class="btn btn-sm ' . (!empty($l[2]) ? 'btn-primary' : 'btn-ghost') . '" href="' . e(url($l[1])) . '">' . e(t($l[0])) . '</a>';
    }
    return $html . '</div>';
}

/** Skirtukų juosta po viršutine juosta (Treneriams arba Mano paskyra) */
function render_sub_nav(string $label, array $items, string $current): string
{
    $html = '<div class="sub-nav" role="navigation" aria-label="' . e($label) . '"><div class="sub-inner">';
    foreach ($items as $key => $it) {
        $badge = !empty($it[2]) ? ' <span class="count-badge">' . (int) $it[2] . '</span>' : '';
        $cls = ($key === $current ? 'active' : '') . ($key === 'patvirtinimai' ? ' sub-last' : '');
        $html .= '<a href="' . e(url($it[1])) . '" class="' . trim($cls) . '"' . ($key === $current ? ' aria-current="page"' : '') . '>' . e(t($it[0])) . $badge . '</a>';
    }
    return $html . '</div></div>';
}

/**
 * Bendra viršutinė juosta visiems puslapiams.
 * $home = true pagrindiniame puslapyje (nuorodos į skiltis be perkrovimo).
 * $area - 'admin' (Treneriams) arba 'account' (Mano paskyra): po juosta rodomi tos dalies skirtukai.
 */
function site_header(bool $home = false, ?string $area = null, ?string $tab = null, ?string $section = null): void
{
    $cur = function (string $s) use ($section) { return $section === $s ? ' is-current' : ''; };
    $a = current_account();
    $h = $home ? '' : url('index.php');
    $subNav = '';
    $mv = menu_variant();
    $mk = admin_menu_key();
    if ($a && $area === 'admin' && can('valdymas', $a)) {
        // B+C (#106): kompiuteryje - šoninis meniu, telefone ir planšetėje - viena eilutė su „⚙ Daugiau ▾“
        if ($mv === 'eilute' || $mv === 'abu') {
            $keys = ['siandien', 'treniruote', 'egzaminai', 'nariai'];
            $flat = admin_menu_flat();
            $subNav = '<div class="sub-nav am-line' . ($mv === 'abu' ? ' am-narrow' : '') . '" role="navigation" aria-label="' . e(t('Valdymas')) . '"><div class="sub-inner">';
            foreach ($keys as $k) {
                if (isset($flat[$k])) {
                    $subNav .= admin_menu_link($k, $flat[$k], $mk);
                }
            }
            $subNav .= admin_menu_more($keys, $mk) . '</div></div>';
        }
        if ($mv === 'sonas' || $mv === 'abu') {
            // v2.02: administratorius „✎“ - tempia skyrius, pervadina, kuria ir trina grupes (admin/meniu.php)
            $GLOBALS['adm_side'] = '<aside class="adm-side' . ($mv === 'abu' ? ' am-wide' : '') . '" aria-label="' . e(t('Valdymas')) . '"'
                . (can('settings', $a) ? ' data-menu-edit="' . e(json_encode(admin_menu_editor_data(), JSON_UNESCAPED_UNICODE)) . '" data-save="' . e(url('admin/meniu.php')) . '" data-csrf="' . e(csrf_token()) . '"' : '') . '>'
                . '<div class="adm-side-top"><a class="adm-side-h" href="' . e(url('admin/')) . '">🛠 ' . e(t('Valdymas')) . '</a>'
                . (can('settings', $a) ? '<button type="button" class="am-edit-btn" id="am-edit" title="' . e(t('Tvarkyti meniu: tempti, pervadinti, grupės')) . '" aria-label="' . e(t('Tvarkyti meniu')) . '">✎</button>' : '') . '</div>'
                . admin_menu_groups_html($mk, false) . '</aside>';
        }
    } elseif ($a && $area === 'account') {
        $subNav = render_sub_nav(t('Mano paskyra'), account_nav_items($a), (string) $tab);
    }
    ?>
<?php $pm = $a && can('valdymas', $a) && admin_mode() ? phone_menu_variant() : '';
// B8 (sprendimas 6a): nariams, tėvams ir vaikams telefone - apatinė juosta kaip programėlėje (be žaidimų ir testo langų)
$mt = !$pm && $a && !can('valdymas', $a) && is_active_account($a) && $a['status'] === 'active'
    && !in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), ['testas.php', 'testas-svecias.php', 'arena.php', 'keturi.php', 'ritmas.php', 'kova-bandymai.php'], true); ?>
<header class="site-header<?= $pm ? ' pm-' . $pm : '' ?><?= $mt ? ' pm-nariams' : '' ?>">
  <nav>
    <a class="logo" href="<?= $home ? '#' : url('index.php') ?>"><img src="<?= url('img/logo.png') ?>" alt="<?= te('Karateka logotipas') ?>"><?php if (config('env') !== 'live'): ?><span class="ver-tag" title="<?= can('valdymas') ? te('Kas naujo šioje versijoje') : te('Svetainės versija (rodoma tik testuojant)') ?>"<?= can('valdymas') ? ' role="link" tabindex="0" onclick="event.preventDefault(); event.stopPropagation(); location.href=' . e(json_encode(url('admin/kas-naujo.php'))) . '"' : '' ?>>v<?= e(site_version()) ?><?= config('env') === 'local' ? (' ' . t('· local')) : '' ?></span><?php endif; ?></a>
    <?php if ($a && can('valdymas', $a)): ?>
      <!-- Kitos sistemos: Budo varžybos ir LTKF - per logotipo meniu (#91/#92) -->
      <?= site_switch_html('karateka') ?>
    <?php endif; ?>
    <!-- Kalba ir tema - kairėje, visada matomos (tema išsaugoma paskyrai) -->
    <div class="nav-prefs"><?= render_lang_switch() ?><?php if ($a): ?><span class="theme-li"><?= theme_picker_html('me', current_theme()) ?><?= font_picker_html() ?></span><?php endif; ?></div>
    <ul class="nav-list" id="navList">
      <!-- Vienas meniu: kairėje - pagrindinio puslapio skiltys (blokai su meniu pavadinimu), po skirtuko - kiti puslapiai (su ›) -->
      <li class="nav-main">
        <?php $cta = null; $onpage = [];
        foreach (home_nav() as [$label, $anchor, $isCta]) { $isCta ? $cta = [$label, $anchor] : $onpage[] = [$label, $anchor]; } ?>
        <?php if ($onpage): ?>
          <?php // Prisijungusiems pagrindinio puslapio skiltys suskleistos į vieną „Apie klubą ‹“ - užvedus pelę išsiskleidžia į kairę (#78)
            $fold = $a && count($onpage) > 1;
            $mainIdx = 0;
            foreach ($onpage as $i => [$label, $anchor]) { if ($anchor === '#apie') { $mainIdx = $i; } } ?>
          <ul class="nav-group nav-onpage<?= $fold ? ' folded' : '' ?>" aria-label="<?= te('Pagrindinis puslapis') ?>">
            <?php if ($fold): ?><li class="fold-items"><ul class="fold-list"><?php endif; ?>
            <?php foreach ($onpage as $i => [$label, $anchor]): ?><li><a class="nav-link" href="<?= $h . e($anchor) ?>"><?= e(t($label)) ?></a></li><?php endforeach; ?>
            <?php if ($fold): // #117/#131: paspaudus atsiveria žemyn (virš turinio, niekas nepasislenka), paspaudus dar kartą - susiskleidžia ?></ul></li>
              <li class="fold-main"><button type="button" class="nav-link fold-btn" aria-expanded="false"><span class="fold-t"><?= e(t($onpage[$mainIdx][0])) ?></span><span class="fold-ic" aria-hidden="true">▾</span></button></li>
            <?php endif; ?>
          </ul>
        <?php endif; ?>
        <ul class="nav-group nav-pages" aria-label="<?= te('Puslapiai') ?>">
          <li><a class="nav-page<?= $cur('naujienos') . $cur('renginiai') ?>" href="<?= url('naujienos.php') ?>"><?= te('Naujienos ir renginiai') ?><?php if ($a && !can('valdymas', $a) && ($inv = account_pending_invites((int) $a['id']))): ?> <span class="count-badge" title="<?= te('Treneris kviečia - atsakykite') ?>"><?= $inv ?></span><?php endif; ?></a></li>
          <?php if ($a && can_see_lessons($a)): ?>
            <li><a class="nav-page<?= $cur('pamokos') ?>" href="<?= url('pamokos.php') ?>"><?= te('Pamokos') ?></a></li>
          <?php endif; ?>
          <?php if ($a && is_active_account($a) && game_nav_ready() && can('valdymas', $a)): // B8: nariams Dojo - atskiras mygtukas dešiniame kampe ?>
            <li><a class="nav-page nav-game<?= $cur('zaidimas') ?>" href="<?= url('dojo.php') ?>"><?= te('🏯 Dojo') ?></a></li>
          <?php endif; ?>
        </ul>
      </li>
      <?php if ($pm === 'burger' || $pm === 'lapas'): ?>
        <li class="pm-admin"><div class="pm-h"><?= te('🛠 Valdymas') ?></div><?= admin_menu_groups_html($mk, false) ?></li>
      <?php endif; ?>
      <!-- Dešinėje: paskyra (ar „Išbandyti“ ir „Prisijungti“) - prisijungus „Išbandyti“ dingsta, meniu nepasislenka -->
      <li class="nav-account">
        <ul>
          <?php if ($a && !can('valdymas', $a) && ($vs = render_view_switch())): ?><li class="vs-li"><?= $vs ?></li><?php endif; ?>
          <?php if ($a && can('valdymas', $a) && admin_mode()): ?>
            <li><a class="nav-login<?= $area === 'admin' ? ' is-current' : '' ?>" href="<?= url('admin/') ?>"><?= te('Valdymas') ?><?php if ($n = staff_attention_count((int) $a['id'])): ?><span class="count-badge nav-bubble" title="<?= te('Reikia padaryti: pranešimai, patvirtinimai ir kiti darbai') ?>"><?= $n ?></span><?php endif; ?></a></li>
          <?php elseif ($a): ?>
            <li><a class="nav-login<?= $area === 'account' ? ' is-current' : '' ?>" href="<?= url('paskyra.php' . (($am = account_attention_member((int) $a['id'])) ? '?m=' . $am : '')) ?>"><?= te('Mano paskyra') ?><?php if ($att = account_attention_count((int) $a['id'])): ?><span class="count-badge nav-bubble" title="<?= te('Reikia padaryti: užduotys, pastabos, kvietimai') ?>"><?= $att ?></span><?php endif; ?></a></li>
            <?php if (is_active_account($a) && game_nav_ready()): // B8: 🏯 Dojo - kampe, kaip ypatinga vieta ?>
              <li class="dojo-li"><a class="nav-game nav-dojo<?= $cur('zaidimas') ?>" href="<?= url('dojo.php') ?>" title="<?= te('Tavo personažas, gyvūnėliai, namas ir kovos') ?>"><?= te('🏯 Dojo') ?></a></li>
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($a && can('valdymas', $a)): ?>
            <li class="adm-li"><?= admin_mode_toggle() ?></li>
          <?php endif; ?>
          <?php if ($a): ?>
            <li class="mobile-only">
              <form method="post" action="<?= url('atsijungti.php') ?>" class="inline-form"><?= csrf_field() ?>
                <button type="submit" class="logout-btn"><?= te('Atsijungti') ?></button>
              </form>
            </li>
          <?php else: ?>
            <?php if ($cta): ?><li class="desktop-only"><a class="nav-cta" href="<?= $h . e($cta[1]) ?>"><?= e(t($cta[0])) ?></a></li><?php endif; ?>
            <li><a class="nav-login" href="<?= url('prisijungti.php') ?>"><?= te('Prisijungti') ?></a></li>
          <?php endif; ?>
        </ul>
      </li>
    </ul>
    <?php if ($cta): ?><a class="nav-cta mobile-only" href="<?= $h . e($cta[1]) ?>"><?= e(t($cta[0])) ?></a><?php endif; ?>
    <?php if ($pm === 'eilute'): ?>
      <details class="pm-site"><summary aria-label="<?= te('Svetainė') ?>">🌐<svg width="12" height="12" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></summary>
        <div class="am-more-panel"><?= phone_site_links($h, $onpage, $a) ?><?= $area !== 'admin' ? '<a class="am-row" href="' . e(url('admin/')) . '"><span class="am-ic">🛠</span><span class="am-t">' . te('Valdymas') . '</span></a>' : '' ?>
          <form method="post" action="<?= url('atsijungti.php') ?>" class="inline-form"><?= csrf_field() ?><button type="submit" class="am-row am-logout"><span class="am-ic">🚪</span><span class="am-t"><?= te('Atsijungti') ?></span></button></form></div></details>
    <?php endif; ?>
    <?php if ($mt && game_nav_ready()): ?><a class="m-dojo" href="<?= url('dojo.php') ?>" aria-label="<?= te('🏯 Dojo') ?>"><span aria-hidden="true">🏯</span></a><?php endif; ?>
    <button class="burger" id="burgerBtn" type="button" aria-label="<?= te('Meniu') ?>"><span></span><span></span><span></span><?php if ($a && (can('valdymas', $a) ? staff_attention_count((int) $a['id']) : account_attention_count((int) $a['id']))): ?><i class="burger-dot"></i><?php endif; ?></button>
  </nav>
  <?= $subNav ?>
<?php if ($pm === 'apacia'): $tflat = admin_menu_flat(); $tkeys = ['siandien', 'treniruote', 'egzaminai', 'nariai']; ?>
  <!-- Telefone: juosta antraštės apačioje (v1.86 - viršuje); ⋯ Daugiau - visa kita (valdymas ir svetainė) -->
  <nav class="m-tabbar" aria-label="<?= te('Valdymas') ?>">
    <?php foreach ($tkeys as $k): if (isset($tflat[$k])): $it = $tflat[$k]; ?>
      <a href="<?= url($it[2]) ?>" class="<?= $k === $mk ? 'active' : '' ?>"><span class="mt-ic" aria-hidden="true"><?= $it[0] ?></span><span><?= e($it[1]) ?></span></a>
    <?php endif; endforeach; ?>
    <?php $rest = array_diff_key($tflat, array_flip($tkeys)); $rb = array_sum(array_map(function ($i) { return (int) $i[3]; }, $rest)); ?>
    <details class="m-more"><summary class="<?= isset($rest[$mk]) ? 'active' : '' ?>"><span class="mt-ic" aria-hidden="true">⋯</span><span><?= te('Daugiau') ?></span><?= $rb ? '<em class="count-badge">' . $rb . '</em>' : '' ?></summary>
      <div class="m-sheet"><?php $mvs = admin_mode_toggle(); if (strpos($mvs, 'view-switch') !== false): /* B4: „Žiūrėti kaip“ telefone - pirma eilutė */ ?>
          <div class="pm-h"><?= te('Žiūrėti kaip') ?></div><div class="m-vs"><?= str_replace('<details class="view-switch', '<details open class="view-switch', $mvs) ?></div>
        <?php endif; ?><div class="pm-h"><?= te('🛠 Valdymas') ?></div>
        <?php foreach ($rest as $k => $it) { echo admin_menu_link($k, $it, $mk, 'am-row'); } ?>
        <div class="pm-h"><?= te('🌐 Svetainė') ?></div><?= phone_site_links($h, $onpage, $a) ?>
        <form method="post" action="<?= url('atsijungti.php') ?>" class="inline-form"><?= csrf_field() ?><button type="submit" class="am-row am-logout"><span class="am-ic">🚪</span><span class="am-t"><?= te('Atsijungti') ?></span></button></form>
      </div></details>
  </nav>
<?php endif; ?>
<?php if ($mt): $mi = member_tab_items($a, $area, (string) $tab, $section); ?>
  <!-- B8: nario juosta telefone (v1.86 - viršuje, antraštėje): 4 skirtukai + ⋯ Daugiau (nustatymai, svetainė, atsijungti) -->
  <nav class="m-tabbar mt-member" aria-label="<?= te('Mano paskyra') ?>">
    <?php foreach ($mi as $it): ?>
      <a href="<?= url($it[2]) ?>" class="<?= $it[4] ? 'active' : '' ?>"<?= $it[4] ? ' aria-current="page"' : '' ?>><span class="mt-ic" aria-hidden="true"><?= $it[0] ?></span><span><?= e($it[1]) ?></span><?= $it[3] ? '<em class="count-badge">' . (int) $it[3] . '</em>' : '' ?></a>
    <?php endforeach; ?>
    <details class="m-more"><summary class="<?= $area === 'account' && $tab === 'nustatymai' ? 'active' : '' ?>"><span class="mt-ic" aria-hidden="true">⋯</span><span><?= te('Daugiau') ?></span></summary>
      <div class="m-sheet">
        <div class="pm-h"><?= te('Mano paskyra') ?></div>
        <a class="am-row" href="<?= url('paskyra.php?skiltis=nustatymai') ?>"><span class="am-ic" aria-hidden="true">⚙️</span><span class="am-t"><?= te('Nustatymai') ?></span></a>
        <?php if (array_filter(account_members((int) $a['id']), function ($m) { return $m['relation'] === 'parent'; })): ?>
          <a class="am-row" href="<?= url('paskyra.php?skiltis=nustatymai#vaikai') ?>"><span class="am-ic" aria-hidden="true">👪</span><span class="am-t"><?= te('Tėvams') ?></span></a>
        <?php endif; ?>
        <div class="pm-h"><?= te('🌐 Svetainė') ?></div><?= phone_site_links($h, $onpage, $a) ?>
        <form method="post" action="<?= url('atsijungti.php') ?>" class="inline-form"><?= csrf_field() ?><button type="submit" class="am-row am-logout"><span class="am-ic">🚪</span><span class="am-t"><?= te('Atsijungti') ?></span></button></form>
      </div></details>
  </nav>
<?php endif; ?>
</header>
<?php
    // Pranešimai ir darbai - tik raudonas skaičiukas ant „Valdymas“ (be juostos per ekraną)
}

/** Admin režimo jungiklis dešinėje viršutinės juostos pusėje (raudonas taškas - laukia darbų) */
function admin_mode_toggle(): string
{
    $a = current_account();
    $on = admin_mode();
    $n = $on ? 0 : staff_attention_count((int) $a['id']);
    // Be slankiklio (#97): įjungus - tik ▾ „Žiūrėti kaip“ (ten ir „Narys - be redagavimo“); kaip narys - aiškus mygtukas grįžti
    $form = function (string $to, string $cls, string $label) {
        return '<form method="post" action="' . e(url('admin-rezimas.php')) . '" class="adm-toggle">' . csrf_field()
            . '<input type="hidden" name="back" value="' . e($_SERVER['REQUEST_URI'] ?? '') . '"><input type="hidden" name="on" value="' . $to . '">'
            . '<button type="submit" class="' . $cls . '">' . $label . '</button></form>';
    };
    if (!$on) {
        return $form('1', 'adm-sw adm-back', '🛠 ' . e(t('Grįžti į valdymą')) . ($n ? '<span class="count-badge nav-bubble">' . $n . '</span>' : ''));
    }
    $member = '<div class="vs-member">' . $form('0', 'vs-member-btn', '👤 ' . e(t('Kaip narys (be redagavimo)'))) . '<p class="vs-hint">' . e(t('Svetainė be trenerio mygtukų - kaip ją mato nariai. Grįžti - mygtuku „Grįžti į valdymą“.')) . '</p></div>'
        // #144: paskyra ir atsijungimas - čia pat, ne kažkur Nustatymuose
        . '<div class="vs-account"><a class="vs-acc-link" href="' . e(url('admin/nustatymai.php#paskyra')) . '">⚙️ ' . e(t('Mano paskyra')) . ' <small>' . e(trim($a['first_name'] . ' ' . $a['last_name'])) . '</small></a>'
        . '<form method="post" action="' . e(url('atsijungti.php')) . '">' . csrf_field() . '<button type="submit" class="vs-logout">' . e(t('Atsijungti')) . '</button></form></div>';
    $vs = render_view_switch(true, $member);
    return $vs !== '' ? $vs : '<details class="view-switch vs-compact"><summary aria-label="' . e(t('Žiūrėti kaip')) . '"><span aria-hidden="true">▾</span></summary><div class="vs-panel">' . $member . '</div></details>';
}

/** Treneriui: kiek laukia - pranešimai ir „Reikia padaryti“ punktai (patvirtinimai, rezultatai, žinutės...) */
function staff_attention_count(int $accountId): int
{
    return notification_todo_count($accountId) + count(admin_todo_items());   // tik darbai (sprendimas 2026-11-20)
}


/**
 * Sutikimas dėl nuotraukų: aiškus pasirinkimas "Taip / Ne" (privaloma pasirinkti).
 * Iš anksto pažymėtas langelis pagal BDAR nelaikomas sutikimu, todėl numatytosios reikšmės nėra.
 */
function photo_consent_choice(string $name, $current, string $label): string
{
    $html = '<div class="consent-choice"><span class="consent-label">' . e($label) . '</span><div class="seg">';
    foreach (['1' => t('Taip, sutinku'), '0' => t('Ne')] as $v => $text) {
        $html .= '<label><input type="radio" name="' . e($name) . '" value="' . $v . '"' . ((string) $current === (string) $v ? ' checked' : '') . ' required><span>' . $text . '</span></label>';
    }
    return $html . '</div></div>';
}

/**
 * Gimimo data trimis sąrašais (metai / mėnuo / diena) - patogiau nei kalendorius, kuriame reikia slinkti metus.
 * Formoje siunčiama kaip name[y], name[m], name[d]; nuskaitoma su date_from_input().
 */
function date_parts_field(string $name, $value, bool $required = true): string
{
    if (is_array($value)) {
        [$y, $m, $d] = [(int) ($value['y'] ?? 0), (int) ($value['m'] ?? 0), (int) ($value['d'] ?? 0)];
    } elseif (is_string($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $p)) {
        [$y, $m, $d] = [(int) $p[1], (int) $p[2], (int) $p[3]];
    } else {
        [$y, $m, $d] = [0, 0, 0];
    }
    $req = $required ? ' required' : '';
    $html = '<div class="date-parts"><select name="' . e($name) . '[y]" aria-label="' . e(t('Metai')) . '"' . $req . ('><option value="">' . t('Metai') . '</option>');
    for ($i = (int) date('Y'); $i >= 1930; $i--) {
        $html .= '<option value="' . $i . '"' . ($i === $y ? ' selected' : '') . '>' . $i . '</option>';
    }
    $html .= '</select><select name="' . e($name) . '[m]" aria-label="' . e(t('Mėnuo')) . '"' . $req . ('><option value="">' . t('Mėnuo') . '</option>');
    foreach (month_names() as $i => $label) {
        $html .= '<option value="' . $i . '"' . ($i === $m ? ' selected' : '') . '>' . $label . '</option>';
    }
    $html .= '</select><select name="' . e($name) . '[d]" aria-label="' . e(t('Diena')) . '"' . $req . ('><option value="">' . t('Diena') . '</option>');
    for ($i = 1; $i <= 31; $i++) {
        $html .= '<option value="' . $i . '"' . ($i === $d ? ' selected' : '') . '>' . $i . '</option>';
    }
    return $html . '</select></div>';
}

/** "YYYY-MM-DD" iš date_parts_field() (arba paprasto teksto); netinkama data - '' */
function date_from_input($v): string
{
    if (is_array($v)) {
        $y = (int) ($v['y'] ?? 0);
        $m = (int) ($v['m'] ?? 0);
        $d = (int) ($v['d'] ?? 0);
        return $y && $m && $d && checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : '';
    }
    return is_string($v) ? trim($v) : '';
}

/** Paskyros nustatymų kortelė (atidaroma ta dalis, kurioje buvo klaida) */
function render_account_settings(array $a, string $id = 'nustatymai', string $title = 'Paskyros nustatymai'): string
{
    if (is_kid_login($a)) {
        // Vaiko prisijungimas (vardas + PIN): nieko keisti pats negali - tvarko tėvai
        return '<div class="panel card" id="' . e($id) . '" style="margin-top:20px;"><h2>' . e(t('Mano prisijungimas')) . '</h2>'
            . '<p>' . e(t('Vardas')) . ': <code class="kl-user">' . e(login_name((string) $a['email'])) . '</code></p>'
            . '<p class="small muted">' . e(t('Pamiršai PIN ar nori jį pakeisti? Paprašyk tėvų - jie pakeis savo paskyroje.')) . '</p>'
            . '<form method="post" action="' . url('atsijungti.php') . '">' . csrf_field() . '<button type="submit" class="btn btn-ghost btn-sm">' . e(t('Atsijungti')) . '</button></form></div>';
    }
    $openSetting = in_array(post('action'), ['email', 'password', 'profile', 'name'], true) ? post('action') : '';
    if (strpos(post('action'), 'tfa_') === 0 || !empty($_SESSION['tfa_enable'])) {
        $openSetting = 'tfa';
    }
    // Dar neimportuotas db/023 (nėra accounts.two_factor) - nustatymas nerodomas
    $devices = !array_key_exists('two_factor', $a) ? null : (two_factor_on($a) ? trusted_devices((int) $a['id']) : []);
    ob_start();
    ?>
<div class="panel card" id="<?= e($id) ?>" style="margin-top:20px;">
  <h2><?= e($title) ?></h2>
  <details class="setting" <?= $openSetting === 'name' ? 'open' : '' ?>>
    <summary><span class="setting-label"><?= te('Vardas, pavardė') ?></span><span class="setting-value"><?= e(trim($a['first_name'] . ' ' . $a['last_name'])) ?></span><span class="setting-btn"><?= te('Keisti') ?></span></summary>
    <form method="post" class="form">
      <?= csrf_field() ?><input type="hidden" name="action" value="name">
      <div class="form-row">
        <label><?= te('Vardas') ?> <input name="first_name" maxlength="80" required value="<?= e($a['first_name']) ?>" autocomplete="given-name"></label>
        <label><?= te('Pavardė') ?> <input name="last_name" maxlength="80" value="<?= e($a['last_name']) ?>" autocomplete="family-name"></label>
      </div>
      <div><button class="btn btn-primary btn-sm" type="submit"><?= te('Išsaugoti') ?></button></div>
    </form>
  </details>

  <details class="setting" <?= $openSetting === 'email' ? 'open' : '' ?>>
    <summary><span class="setting-label"><?= te('El. paštas') ?></span><span class="setting-value"><?= e($a['email']) ?></span><span class="setting-btn"><?= te('Keisti') ?></span></summary>
    <form method="post" class="form setting-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="email">
      <div class="form-row">
        <label><?= te('Naujas el. paštas') ?> <input type="email" name="email" value="<?= $openSetting === 'email' ? e(post('email')) : '' ?>" autocomplete="email" required></label>
        <label><?= te('Slaptažodis') ?> <span class="hint">patvirtinimui</span><input type="password" name="current" autocomplete="current-password" required></label>
      </div>
      <p class="hint"><?= te('Į naują adresą atsiųsime nuorodą. El. paštas pasikeis tik ją paspaudus.') ?></p>
      <div><button class="btn btn-primary btn-sm" type="submit"><?= te('Keisti el. paštą') ?></button></div>
    </form>
  </details>

  <details class="setting" <?= $openSetting === 'profile' ? 'open' : '' ?>>
    <summary><span class="setting-label"><?= te('Telefonas') ?></span><span class="setting-value"><?= $a['phone'] ? e($a['phone']) : '<span class="muted">nenurodytas</span>' ?></span><span class="setting-btn"><?= te('Keisti') ?></span></summary>
    <form method="post" class="form setting-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="profile">
      <label><?= te('Telefonas') ?> <input type="tel" name="phone" value="<?= e($a['phone']) ?>" autocomplete="tel"></label>
      <div><button class="btn btn-primary btn-sm" type="submit"><?= te('Išsaugoti') ?></button></div>
    </form>
  </details>

  <details class="setting" <?= $openSetting === 'password' ? 'open' : '' ?>>
    <summary><span class="setting-label"><?= te('Slaptažodis') ?></span><span class="setting-value">••••••••</span><span class="setting-btn"><?= te('Keisti') ?></span></summary>
    <form method="post" class="form setting-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <div class="form-row">
        <label><?= te('Dabartinis slaptažodis') ?> <input type="password" name="current" autocomplete="current-password" required></label>
        <label><?= te('Naujas slaptažodis') ?> <span class="hint"><?= te('bent {n} simboliai', ['n' => MIN_PASSWORD]) ?></span><input type="password" name="password" autocomplete="new-password" minlength="<?= MIN_PASSWORD ?>" required></label>
        <label><?= te('Pakartokite naują slaptažodį') ?> <input type="password" name="password2" autocomplete="new-password" required></label>
      </div>
      <div><button class="btn btn-primary btn-sm" type="submit"><?= te('Keisti slaptažodį') ?></button></div>
    </form>
  </details>

  <?php if ($devices !== null): ?>
  <details class="setting" id="tfa" <?= $openSetting === 'tfa' ? 'open' : '' ?>>
    <summary><span class="setting-label"><?= te('Kodas el. paštu') ?></span><span class="setting-value"><?= two_factor_on($a) ? t('Įjungtas') : ('<span class="muted">' . t('išjungtas') . '</span>') ?></span><span class="setting-btn"><?= te('Keisti') ?></span></summary>
    <div class="setting-form">
      <?php if (two_factor_on($a)): ?>
        <p class="hint"><?= te('Prisijungiant naujame įrenginyje, be slaptažodžio, reikia kodo iš el. pašto. Patikimuose įrenginiuose kodo neprašoma') ?> <?= TRUSTED_DEVICE_DAYS ?> <?= te('dienų.') ?></p>
        <?php if ($devices): ?>
          <ul class="device-list">
            <?php foreach ($devices as $d): ?>
              <li><strong><?= e($d['label'] ?: t('Įrenginys')) ?></strong> <span class="muted small"><?= te('· paskutinį kartą') ?> <?= e(notification_when($d['last_used_at'] ?: $d['created_at'])) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <form method="post" class="inline-form" style="margin-bottom:12px;"><?= csrf_field() ?>
            <button class="btn btn-ghost btn-sm" name="action" value="tfa_forget"><?= te('Pamiršti visus įrenginius') ?></button></form>
        <?php endif; ?>
        <form method="post" class="form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="tfa_off">
          <label><?= te('Slaptažodis') ?> <span class="hint"><?= te('išjungimui') ?></span><input type="password" name="current" autocomplete="current-password" required></label>
          <div><button class="btn btn-ghost btn-sm" type="submit"><?= te('Išjungti') ?></button></div>
        </form>
      <?php elseif (!empty($_SESSION['tfa_enable'])): ?>
        <p class="hint"><?= te('Išsiuntėme kodą į') ?> <strong><?= e(masked_email($a['email'])) ?></strong>.</p>
        <?php if (config('env') === 'local' && !empty($_SESSION['dev_login_code'])): ?>
          <p class="hint"><?= te('Kompiuteryje laiškai nesiunčiami - kodas:') ?> <code class="dev-code"><?= e($_SESSION['dev_login_code']) ?></code></p>
        <?php endif; ?>
        <form method="post" class="form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="tfa_confirm">
          <label><?= te('Kodas iš laiško') ?> <input class="code-input" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required></label>
          <div class="row" style="gap:10px;"><button class="btn btn-primary btn-sm" type="submit"><?= te('Patvirtinti ir įjungti') ?></button>
            <button class="btn btn-ghost btn-sm" type="submit" name="action" value="tfa_cancel" formnovalidate><?= te('Atšaukti') ?></button></div>
        </form>
      <?php else: ?>
        <p class="hint"><?= te('Papildoma apsauga: prisijungiant naujame įrenginyje, be slaptažodžio, reikės kodo, kurį atsiųsime el. paštu.') ?>
          <?= can('valdymas', $a) ? t('Rekomenduojama - jūsų paskyra mato narių duomenis.') : '' ?> <?= te('Pirma atsiųsime kodą, kad įsitikintume, jog laiškai ateina.') ?></p>
        <form method="post" class="form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="tfa_start">
          <label><?= te('Slaptažodis') ?> <span class="hint">patvirtinimui</span><input type="password" name="current" autocomplete="current-password" required></label>
          <div><button class="btn btn-primary btn-sm" type="submit"><?= te('Siųsti kodą') ?></button></div>
        </form>
      <?php endif; ?>
    </div>
  </details>
  <?php endif; ?>
</div>
    <?php
    return ob_get_clean();
}

/** Kitos sistemos: Karateka, Budo varžybos, LTKF - logotipo meniu „⌄“ (#91/#92; Budo antraštėje - #171). $cur - kuri dabar atidaryta. */
function site_switch_html(string $cur): string
{
    $items = [
        'karateka' => [url('index.php'), '<img src="' . e(url('img/logo.png')) . '" alt="">', 'Karateka', t('klubo svetainė')],
        'budo' => [url('admin/varzybos.php'), '<span class="ss-budo" aria-hidden="true">武</span>', 'Budo', t('varžybų sistema')],
        'ltkf' => [url('ltkf/'), '<svg class="ss-ltkf" width="34" height="34" viewBox="0 0 48 48" aria-hidden="true"><circle cx="24" cy="24" r="22.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M14 31 L24 13 L34 31" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"/><path d="M18 26 H30" stroke="currentColor" stroke-width="2.4"/><rect x="11" y="34" width="8.6" height="2.6" fill="#F2B705"/><rect x="19.7" y="34" width="8.6" height="2.6" fill="#00704A"/><rect x="28.4" y="34" width="8.6" height="2.6" fill="#C1272D"/></svg>', 'LTKF', t('federacijos svetainė')],
    ];
    $html = '<details class="site-switch"><summary title="' . e(t('Kitos sistemos: Budo varžybos, LTKF')) . '" aria-label="' . e(t('Kitos sistemos')) . '"><svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></summary><div class="ss-panel">';
    foreach ($items as $k => [$href, $icon, $name, $sub]) {
        $html .= '<a href="' . e($href) . '" class="ss-item' . ($k === $cur ? ' is-cur' : '') . '">' . $icon . '<span><b>' . e($name) . '</b><small>' . e($sub) . '</small></span></a>';
    }
    return $html . '</div></details>';
}
