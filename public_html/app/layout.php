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
<?php if ($admin || !empty($opt['noindex'])): ?><meta name="robots" content="noindex">
<?php endif; ?>
<link rel="icon" type="image/png" sizes="32x32" href="<?= url('favicon-32.png?v=2') ?>">
<link rel="apple-touch-icon" href="<?= url('apple-touch-icon.png?v=2') ?>">
<meta name="theme-color" content="#811517">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('assets/header.css')) ?>">
<link rel="stylesheet" href="<?= e($css) ?>">
</head>
<body class="<?= $admin ? 'is-admin' : '' ?>">
<?php site_header(); ?>
<?php if ($admin): ?>
<?php $pendingCount = pending_approvals_count(); ?>
<nav class="admin-tabs">
  <a href="<?= url('admin/') ?>">Pradžia</a>
  <a href="<?= url('admin/treniruote.php') ?>">Treniruotė</a>
  <a href="<?= url('admin/nariai.php') ?>">Nariai</a>
  <a href="<?= url('admin/grupes.php') ?>">Grupės</a>
  <a href="<?= url('admin/renginiai.php') ?>">Renginiai</a>
  <a href="<?= url('admin/taskai.php') ?>">Taškai</a>
  <a href="<?= url('admin/naujienos.php') ?>">Naujienos</a>
  <a href="<?= url('admin/pamokos.php') ?>">Pamokos</a>
  <?php if (is_admin()): ?><a href="<?= url('admin/paskyros.php') ?>">Paskyros</a><?php endif; ?>
  <a href="<?= url('admin/patvirtinimai.php') ?>" class="tab-last">Patvirtinimai<?php if ($pendingCount): ?> <span class="count-badge"><?= $pendingCount ?></span><?php endif; ?></a>
</nav>
<?php endif; ?>
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
    <span>© <?= date('Y') ?> VšĮ Karate Ateitis · <a href="<?= url('privatumas.php') ?>">Privatumo politika</a></span>
    <span>Nuo balto iki juodo diržo.</span>
  </div>
</footer>
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

/** Mygtukas „Redaguoti“ viešuose puslapiuose - matomas tik treneriams */
function staff_link(string $path, string $label = '✎ Redaguoti'): string
{
    if (!is_staff()) {
        return '';
    }
    return '<a class="staff-link" href="' . e(url($path)) . '">' . e($label) . '</a>';
}

/** Visos treniruočių vietos (iš grupių) - pasirinkimui iš sąrašo */
function training_locations(): array
{
    return q('SELECT DISTINCT location FROM training_groups WHERE location IS NOT NULL AND location <> "" ORDER BY location')
        ->fetchAll(PDO::FETCH_COLUMN);
}

/** Vietos laukas: sąrašas iš treniruočių vietų + „Kita vieta…“ su įrašymu ranka */
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

/**
 * Bendra viršutinė juosta visiems puslapiams.
 * $home = true pagrindiniame puslapyje (nuorodos į skiltis be perkrovimo).
 */
function site_header(bool $home = false): void
{
    $a = current_account();
    $h = $home ? '' : url('index.php');
    ?>
<div class="progress-wrap"><div class="progress-bar" id="progressBar"></div></div>
<header class="site-header">
  <nav>
    <a class="logo" href="<?= $home ? '#' : url('index.php') ?>"><img src="<?= url('img/logo.png') ?>" alt="Karateka logotipas"></a>
    <ul class="nav-list" id="navList">
      <!-- Pagrindinio puslapio skiltys -->
      <li class="nav-section onpage-section">
        <ul>
          <li><a class="nav-link nl-gray" href="<?= $h ?>#grupes">Treniruotės</a></li>
          <li><a class="nav-link nl-pink" href="<?= $h ?>#apie">Apie klubą</a></li>
          <li><a class="nav-link nl-blue" href="<?= $h ?>#kontaktai">Kontaktai</a></li>
          <li class="desktop-only"><a class="nav-cta" href="<?= $h ?>#registracija">2 treniruotės nemokamai</a></li>
        </ul>
      </li>
      <!-- Kiti puslapiai -->
      <li class="nav-section page-section">
        <ul>
          <li><a class="nav-page" href="<?= url('renginiai.php') ?>">Renginiai</a></li>
          <li><a class="nav-page" href="<?= url('naujienos.php') ?>">Naujienos</a></li>
          <?php if ($a && can_see_lessons($a)): ?>
            <li><a class="nav-page" href="<?= url('pamokos.php') ?>">Pamokos</a></li>
          <?php endif; ?>
          <?php if ($a && is_staff($a)): ?>
            <li><a class="nav-page" href="<?= url('admin/') ?>">Treneriams<?php if ($n = pending_approvals_count()): ?> <span class="count-badge" title="Laukia patvirtinimo"><?= $n ?></span><?php endif; ?></a></li>
          <?php endif; ?>
          <?php if ($a): ?>
            <li><a class="nav-login" href="<?= url('paskyra.php') ?>">Mano paskyra<?php if ($att = account_attention_count((int) $a['id'])): ?> <span class="count-badge" title="Naujos pastabos ar neatliktos užduotys"><?= $att ?></span><?php endif; ?></a></li>
            <li class="mobile-only">
              <form method="post" action="<?= url('atsijungti.php') ?>" class="inline-form"><?= csrf_field() ?>
                <button type="submit" class="logout-btn">Atsijungti</button>
              </form>
            </li>
          <?php else: ?>
            <li><a class="nav-login" href="<?= url('prisijungti.php') ?>">Prisijungti</a></li>
          <?php endif; ?>
        </ul>
      </li>
    </ul>
    <a class="nav-cta mobile-only" href="<?= $h ?>#registracija">2 treniruotės nemokamai</a>
    <button class="burger" id="burgerBtn" type="button" aria-label="Meniu"><span></span><span></span><span></span><?php if ($a && account_attention_count((int) $a['id'])): ?><i class="burger-dot"></i><?php endif; ?></button>
  </nav>
</header>
<?php
    // Kol yra neperskaitytų trenerio pastabų ar neatliktų užduočių - juosta po meniu visuose puslapiuose
    if ($a && ($att = account_attention_count((int) $a['id'])) && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'paskyra.php'):
        $attMember = account_attention_member((int) $a['id']); ?>
<a class="attention-bar" href="<?= url('paskyra.php' . ($attMember ? '?m=' . $attMember : '')) ?>#pastabos">❗ Turite <?= $att ?> <?= $att === 1 ? 'trenerio pastabą ar užduotį' : 'trenerio pastabų ar užduočių' ?>, kurios dar nepažymėtos - <strong>peržiūrėti →</strong></a>
<?php endif; ?>
<?php
}

const LT_MONTHS = [1 => 'sausis', 'vasaris', 'kovas', 'balandis', 'gegužė', 'birželis', 'liepa', 'rugpjūtis', 'rugsėjis', 'spalis', 'lapkritis', 'gruodis'];

/**
 * Sutikimas dėl nuotraukų: aiškus pasirinkimas „Taip / Ne“ (privaloma pasirinkti).
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

/** „YYYY-MM-DD“ iš date_parts_field() (arba paprasto teksto); netinkama data - '' */
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
