<?php
// Bendras puslapio karkasas (antraštė, navigacija, poraštė)

function page_start(string $title, array $opt = []): void
{
    $a = current_account();
    $admin = !empty($opt['admin']);
    $flashes = take_flashes();
    $css = url('assets/app.css') . '?v=' . @filemtime(PUBLIC_DIR . '/assets/app.css');
    ?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> — Karateka</title>
<?php if (!empty($opt['description'])): ?><meta name="description" content="<?= e($opt['description']) ?>">
<?php endif; ?>
<?php if (!empty($opt['og'])): /* Facebook / Messenger nuorodos peržiūra */ ?>
<meta property="og:site_name" content="Karate Ateitis">
<meta property="og:locale" content="lt_LT">
<?php foreach ($opt['og'] as $k => $v): if ($v): ?><meta property="og:<?= e($k) ?>" content="<?= e($v) ?>">
<?php endif; endforeach; ?>
<?php endif; ?>
<?php if ($admin || !empty($opt['noindex'])): ?><meta name="robots" content="noindex">
<?php endif; ?>
<link rel="icon" type="image/png" sizes="32x32" href="<?= url('favicon-32.png?v=2') ?>">
<link rel="apple-touch-icon" href="<?= url('apple-touch-icon.png?v=2') ?>">
<meta name="theme-color" content="#811517">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('assets/header.css')) ?>">
<link rel="stylesheet" href="<?= e($css) ?>">
<link rel="stylesheet" href="<?= e(asset_url('assets/widgets.css')) ?>">
</head>
<body class="<?= $admin ? 'is-admin' : '' ?>">
<?php
    // Renginiai, Naujienos, Pamokos - viena vieta visiems (ir treneriui su redagavimo mygtukais), be trenerio skirtukų juostos
    $section = $opt['section'] ?? null;
    site_header(false, $admin && !$section ? 'admin' : (!empty($opt['account']) ? 'account' : null), $opt['account'] ?? null, $section); ?>
<main class="page <?= !empty($opt['narrow']) ? 'narrow' : '' ?>">
<?php foreach ($flashes as $f): ?>
  <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>
<?php
}

function page_end(): void
{
    ?>
</main>
<footer class="site-footer">
  <div class="footer-bottom">
    <span>© <?= date('Y') ?> VšĮ Karate Ateitis · <a href="<?= url('privatumas.php') ?>">Privatumo politika</a><?= current_account() ? ' · <a href="' . url('klausimai.php') . '">Klausimai ir atsakymai</a>' : '' ?></span>
    <span>Nuo balto iki juodo diržo.</span>
  </div>
</footer>
<?= page_widgets() ?>
<script src="<?= e(asset_url('assets/header.js')) ?>"></script>
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
        $html .= '<li>' . e($err) . '</li>';
    }
    return $html . '</ul></div>';
}

/** <option> sąrašas grupėms */
function group_options(?int $selected, bool $withEmpty = true): string
{
    $html = $withEmpty ? '<option value="">— pasirinkite grupę —</option>' : '';
    $current = null;
    foreach (q_all('SELECT * FROM training_groups WHERE is_active = 1 ORDER BY sort_order, name') as $g) {
        if ($g['category'] !== $current) {
            $html .= ($current !== null ? '</optgroup>' : '') . '<optgroup label="' . e(GROUP_CATEGORIES[$g['category']]) . '">';
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
    if (!is_staff()) {
        return '';
    }
    return '<a class="staff-link" href="' . e(url($path)) . '">' . e($label) . '</a>';
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
    foreach (GROUP_CATEGORIES as $cat => $catLabel) {
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
            . ' <span class="muted">(visos ' . count($list) . ')</span></span></label><div class="aud-sub">';
        foreach ($list as $g) {
            // "Vaikai: Viršuliškės" -> "Viršuliškės" (kategorija jau parašyta virš sąrašo)
            $label = preg_replace('/^' . preg_quote($catLabel, '/') . ':\s*/u', '', $g['name']);
            $html .= $box($g, $label, 'aud-group');
        }
        $html .= '</div>';
    }
    $html .= ($emptyMeansAll ? '<p class="hint" style="margin-top:6px;">Nieko nepažymėjus - visiems.</p>' : '') . '</div>';
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
        . '<option value="">— pasirinkite vietą —</option>';
    foreach ($locations as $loc) {
        $html .= '<option value="' . e($loc) . '"' . ($loc === $value ? ' selected' : '') . '>' . e($loc) . '</option>';
    }
    $html .= '<option value="__other"' . ($isOther ? ' selected' : '') . '>Kita vieta…</option></select></label>'
        . '<label id="' . $id . '_other" ' . ($isOther ? '' : 'style="display:none;"') . '>Kita vieta <input type="text" name="' . e($name) . '_other" value="' . ($isOther ? e($value) : '') . '" placeholder="pvz. Kaunas, Žalgirio arena"></label>'
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

/** Treneriams: skirtukai po viršutine juosta [raktas => [pavadinimas, kelias, skaičiukas]] */
function admin_nav_items(): array
{
    $items = [
        'siandien'   => ['Šiandien', 'admin/'],
        'treniruote' => ['Treniruotė', 'admin/treniruote.php'],
        'zinutes'    => ['Žinutės', 'admin/zinutes.php', staff_unread_messages()],
        'nariai'     => ['Nariai', 'admin/nariai.php'],
        // Grupės, tvarkaraštis, taškai, paskyros - „Nustatymai“ (Šiandien puslapio dešinėje), kad meniu nesikartotų
    ];
    $items['varzybos'] = ['Varžybos', 'admin/varzybos.php'];   // ir kai sistema dar neprijungta - puslapis paaiškina
    // Renginiai, naujienos ir pamokos tvarkomi savo skiltyse (viršutinėje juostoje), čia nedubliuojama
    foreach (ADMIN_NAV_EXTRA as $key => $item) {
        if (empty($item[2]) || is_admin()) {
            $items[$key] = [$item[0], $item[1]];
        }
    }
    $items['patvirtinimai'] = ['Patvirtinimai', 'admin/patvirtinimai.php', pending_approvals_count()];
    $items['nustatymai'] = ['Nustatymai', 'admin/#nustatymai'];
    return $items;
}

// Vėliau pridėti skirtukai: [raktas => [pavadinimas, kelias, tik adminui]]
const ADMIN_NAV_EXTRA = [
    'testai'     => ['Testai', 'admin/testai.php', false],
    'pranesimai' => ['Pranešimai', 'admin/pranesimai.php', true],   // ir SMS (admin/sms.php)
];

/** Kuris trenerio skirtukas aktyvus (pagal failą) */
function admin_nav_current(): string
{
    $file = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
    $map = ['index' => 'siandien', 'renginio-rezultatai' => 'renginiai', 'varzybu-rezultatai' => 'varzybos', 'renginio-dalyviai' => 'renginiai', 'programa' => 'testai',
            'varzybos-sso' => 'varzybos', 'varzybu-dalyviai' => 'varzybos', 'sms' => 'pranesimai',
            'grupes' => 'nustatymai', 'tvarkarastis' => 'nustatymai', 'taskai' => 'nustatymai', 'paskyros' => 'nustatymai',
            'pirmas-adminas' => 'siandien'];
    return $map[$file] ?? $file;
}

/** Nario paskyros skirtukai */
function account_nav_items(array $a): array
{
    $items = ['apzvalga' => ['Apžvalga', 'paskyra.php', account_attention_count((int) $a['id'])]];
    if ($a['status'] === 'active') {
        foreach (account_nav_extra($a) as $key => $item) {
            $items[$key] = $item;
        }
    }
    $items['nustatymai'] = ['Nustatymai', 'paskyra.php?skiltis=nustatymai'];
    return $items;
}

/** Skirtukai tarp „Apžvalga“ ir „Nustatymai“. Renginiai ir Pamokos - viršutinėje juostoje, čia nedubliuojama. */
function account_nav_extra(array $a): array
{
    return [
        'egzaminas' => ['Egzaminas', 'egzaminas.php'],
    ];
}

/** Treneriui skilties viršuje: ką čia galima sukurti ar sutvarkyti. $links = [[tekstas, kelias, pagrindinis?], ...] */
function staff_bar(array $links): string
{
    if (!is_staff()) {
        return '';
    }
    $html = '<div class="staff-bar"><span class="staff-bar-label">Treneriui</span>';
    foreach ($links as $l) {
        $html .= '<a class="btn btn-sm ' . (!empty($l[2]) ? 'btn-primary' : 'btn-ghost') . '" href="' . e(url($l[1])) . '">' . e($l[0]) . '</a>';
    }
    return $html . '</div>';
}

/** Skirtukų juosta po viršutine juosta (Treneriams arba Mano paskyra) */
function render_sub_nav(string $label, array $items, string $current): string
{
    $html = '<div class="sub-nav"><div class="sub-inner"><span class="sub-label">' . e($label) . '</span>';
    foreach ($items as $key => $it) {
        $badge = !empty($it[2]) ? ' <span class="count-badge">' . (int) $it[2] . '</span>' : '';
        $cls = ($key === $current ? 'active' : '') . ($key === 'patvirtinimai' ? ' sub-last' : '');
        // „Žinutės“ treneriui atidaro pokalbių langelį (be JS - puslapis admin/zinutes.php)
        $html .= '<a href="' . e(url($it[1])) . '" class="' . trim($cls) . '"' . ($key === $current ? ' aria-current="page"' : '') . ($key === 'zinutes' ? ' data-open-chat' : '') . '>' . e($it[0]) . $badge . '</a>';
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
    if ($a && $area === 'admin' && is_staff($a)) {
        $subNav = render_sub_nav('Treneriams', admin_nav_items(), admin_nav_current());
    } elseif ($a && $area === 'account') {
        $subNav = render_sub_nav('Mano paskyra', account_nav_items($a), (string) $tab);
    }
    ?>
<div class="progress-wrap"><div class="progress-bar" id="progressBar"></div></div>
<header class="site-header">
  <nav>
    <a class="logo" href="<?= $home ? '#' : url('index.php') ?>"><img src="<?= url('img/logo.png') ?>" alt="Karateka logotipas"><?php if (config('env') !== 'live'): ?><span class="ver-tag" title="Svetainės versija (rodoma tik testuojant)">v<?= e(site_version()) ?><?= config('env') === 'local' ? ' · local' : '' ?></span><?php endif; ?></a>
    <ul class="nav-list" id="navList">
      <!-- Pagrindinio puslapio skiltys: blokai, kuriems adminas įrašė meniu pavadinimą -->
      <li class="nav-section onpage-section">
        <ul>
          <?php $cta = null; $colors = ['nl-gray', 'nl-pink', 'nl-blue']; $n = 0;
          foreach (home_nav() as [$label, $anchor, $isCta]): if ($isCta) { $cta = [$label, $anchor]; continue; } ?>
            <li><a class="nav-link <?= $colors[$n++ % 3] ?>" href="<?= $h . e($anchor) ?>"><?= e($label) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </li>
      <!-- Kiti puslapiai -->
      <li class="nav-section page-section">
        <ul>
          <li><a class="nav-page<?= $cur('renginiai') ?>" href="<?= url('renginiai.php') ?>">Renginiai<?php if ($a && !is_staff($a) && ($inv = account_pending_invites((int) $a['id']))): ?> <span class="count-badge" title="Treneris kviečia - atsakykite"><?= $inv ?></span><?php endif; ?></a></li>
          <li><a class="nav-page<?= $cur('naujienos') ?>" href="<?= url('naujienos.php') ?>">Naujienos</a></li>
          <?php if ($a && can_see_lessons($a)): ?>
            <li><a class="nav-page<?= $cur('pamokos') ?>" href="<?= url('pamokos.php') ?>">Pamokos</a></li>
          <?php endif; ?>
          <?php if ($a && is_staff($a)): ?>
            <!-- Treneriui vienas mygtukas "Paskyra": trenerio panelė su paskyros nustatymais -->
            <li><a class="nav-login<?= $area === 'admin' ? ' is-current' : '' ?>" href="<?= url('admin/') ?>">Paskyra<?php if ($n = pending_approvals_count() + notification_count((int) $a['id'])): ?> <span class="count-badge" title="Laukia patvirtinimo ar jūsų atsakymo"><?= $n ?></span><?php endif; ?></a></li>
          <?php elseif ($a): ?>
            <li><a class="nav-login<?= $area === 'account' ? ' is-current' : '' ?>" href="<?= url('paskyra.php') ?>">Mano paskyra<?php if ($att = account_attention_count((int) $a['id'])): ?> <span class="count-badge" title="Naujos pastabos ar neatliktos užduotys"><?= $att ?></span><?php endif; ?></a></li>
          <?php endif; ?>
          <?php if ($a): ?>
            <li class="mobile-only">
              <form method="post" action="<?= url('atsijungti.php') ?>" class="inline-form"><?= csrf_field() ?>
                <button type="submit" class="logout-btn">Atsijungti</button>
              </form>
            </li>
          <?php else: ?>
            <?php // „Išbandyti“ - šalia „Prisijungti“ (ne pagrindinio meniu kapsulėje), kad prisijungus jam dingus niekas nepasislinktų ?>
            <?php if ($cta): ?><li class="desktop-only"><a class="nav-cta" href="<?= $h . e($cta[1]) ?>"><?= e($cta[0]) ?></a></li><?php endif; ?>
            <li><a class="nav-login" href="<?= url('prisijungti.php') ?>">Prisijungti</a></li>
          <?php endif; ?>
        </ul>
      </li>
    </ul>
    <?php if ($cta): ?><a class="nav-cta mobile-only" href="<?= $h . e($cta[1]) ?>"><?= e($cta[0]) ?></a><?php endif; ?>
    <button class="burger" id="burgerBtn" type="button" aria-label="Meniu"><span></span><span></span><span></span><?php if ($a && account_attention_count((int) $a['id'])): ?><i class="burger-dot"></i><?php endif; ?></button>
  </nav>
  <?= $subNav ?>
</header>
<?php
    // Kol yra neperskaitytų trenerio pastabų ar neatliktų užduočių - juosta po meniu visuose puslapiuose
    // Treneriui - į pranešimus „Paskyra“ puslapyje (jei jų yra), kitiems - į „Svarbu“ paskyroje
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $staffBar = $a && is_staff($a) && notification_count((int) $a['id']);
    if ($a && ($att = account_attention_count((int) $a['id'])) && basename($script) !== 'paskyra.php' && !($staffBar && preg_match('~/admin/(index\.php)?$~', $script))):
        $attMember = $staffBar ? null : account_attention_member((int) $a['id']); ?>
<a class="attention-bar" href="<?= url($staffBar ? 'admin/' : 'paskyra.php' . ($attMember ? '?m=' . $attMember : '')) ?>#svarbu">❗ Turite <?= $att ?> <?= $att % 10 === 1 && $att % 100 !== 11 ? 'naują pranešimą' : ($att % 10 >= 2 && ($att % 100 < 10 || $att % 100 >= 20) ? 'naujus pranešimus' : 'naujų pranešimų') ?> - <strong>peržiūrėti →</strong></a>
<?php endif; ?>
<?php
}

const LT_MONTHS = [1 => 'sausis', 'vasaris', 'kovas', 'balandis', 'gegužė', 'birželis', 'liepa', 'rugpjūtis', 'rugsėjis', 'spalis', 'lapkritis', 'gruodis'];

/**
 * Sutikimas dėl nuotraukų: aiškus pasirinkimas "Taip / Ne" (privaloma pasirinkti).
 * Iš anksto pažymėtas langelis pagal BDAR nelaikomas sutikimu, todėl numatytosios reikšmės nėra.
 */
function photo_consent_choice(string $name, $current, string $label): string
{
    $html = '<div class="consent-choice"><span class="consent-label">' . e($label) . '</span><div class="seg">';
    foreach (['1' => 'Taip, sutinku', '0' => 'Ne'] as $v => $text) {
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
    $html = '<div class="date-parts"><select name="' . e($name) . '[y]" aria-label="Metai"' . $req . '><option value="">Metai</option>';
    for ($i = (int) date('Y'); $i >= 1930; $i--) {
        $html .= '<option value="' . $i . '"' . ($i === $y ? ' selected' : '') . '>' . $i . '</option>';
    }
    $html .= '</select><select name="' . e($name) . '[m]" aria-label="Mėnuo"' . $req . '><option value="">Mėnuo</option>';
    foreach (LT_MONTHS as $i => $label) {
        $html .= '<option value="' . $i . '"' . ($i === $m ? ' selected' : '') . '>' . $label . '</option>';
    }
    $html .= '</select><select name="' . e($name) . '[d]" aria-label="Diena"' . $req . '><option value="">Diena</option>';
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
    $openSetting = in_array(post('action'), ['email', 'password', 'profile'], true) ? post('action') : '';
    ob_start();
    ?>
<div class="panel card" id="<?= e($id) ?>" style="margin-top:20px;">
  <h2><?= e($title) ?></h2>
  <p class="muted small"><?= e($a['first_name'] . ' ' . $a['last_name']) ?></p>

  <details class="setting" <?= $openSetting === 'email' ? 'open' : '' ?>>
    <summary><span class="setting-label">El. paštas</span><span class="setting-value"><?= e($a['email']) ?></span><span class="setting-btn">Keisti</span></summary>
    <form method="post" class="form setting-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="email">
      <div class="form-row">
        <label>Naujas el. paštas <input type="email" name="email" value="<?= $openSetting === 'email' ? e(post('email')) : '' ?>" autocomplete="email" required></label>
        <label>Slaptažodis <span class="hint">patvirtinimui</span><input type="password" name="current" autocomplete="current-password" required></label>
      </div>
      <p class="hint">Į naują adresą atsiųsime nuorodą. El. paštas pasikeis tik ją paspaudus.</p>
      <div><button class="btn btn-primary btn-sm" type="submit">Keisti el. paštą</button></div>
    </form>
  </details>

  <details class="setting" <?= $openSetting === 'profile' ? 'open' : '' ?>>
    <summary><span class="setting-label">Telefonas</span><span class="setting-value"><?= $a['phone'] ? e($a['phone']) : '<span class="muted">nenurodytas</span>' ?></span><span class="setting-btn">Keisti</span></summary>
    <form method="post" class="form setting-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="profile">
      <label>Telefonas <input type="tel" name="phone" value="<?= e($a['phone']) ?>" autocomplete="tel"></label>
      <div><button class="btn btn-primary btn-sm" type="submit">Išsaugoti</button></div>
    </form>
  </details>

  <details class="setting" <?= $openSetting === 'password' ? 'open' : '' ?>>
    <summary><span class="setting-label">Slaptažodis</span><span class="setting-value">••••••••</span><span class="setting-btn">Keisti</span></summary>
    <form method="post" class="form setting-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <div class="form-row">
        <label>Dabartinis slaptažodis <input type="password" name="current" autocomplete="current-password" required></label>
        <label>Naujas slaptažodis <span class="hint">bent <?= MIN_PASSWORD ?> simboliai</span><input type="password" name="password" autocomplete="new-password" minlength="<?= MIN_PASSWORD ?>" required></label>
      </div>
      <div><button class="btn btn-primary btn-sm" type="submit">Keisti slaptažodį</button></div>
    </form>
  </details>
</div>
    <?php
    return ob_get_clean();
}
