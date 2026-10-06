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
<nav class="admin-tabs">
  <a href="<?= url('admin/') ?>">Pradžia</a>
  <a href="<?= url('admin/patvirtinimai.php') ?>">Patvirtinimai</a>
  <a href="<?= url('admin/nariai.php') ?>">Nariai</a>
  <a href="<?= url('admin/grupes.php') ?>">Grupės</a>
  <a href="<?= url('admin/renginiai.php') ?>">Renginiai</a>
  <a href="<?= url('admin/taskai.php') ?>">Taškai</a>
  <a href="<?= url('admin/naujienos.php') ?>">Naujienos</a>
  <?php if (is_admin()): ?><a href="<?= url('admin/paskyros.php') ?>">Paskyros</a><?php endif; ?>
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
    <a class="logo" href="<?= $home ? '#' : url('index.php') ?>"><img src="<?= url('LogoColor.png') ?>" alt="Karateka logotipas"></a>
    <ul class="nav-list" id="navList">
      <li><a class="nav-link nl-pink" href="<?= $h ?>#apie">Apie klubą</a></li>
      <li class="dropdown">
        <span class="dropdown-trigger nl-gray">Treniruotės</span>
        <ul class="dropdown-menu">
          <li><a href="<?= $h ?>#vaikams">Vaikams</a></li>
          <li><a href="<?= $h ?>#jaunimui">Jaunimui</a></li>
          <li><a href="<?= $h ?>#suaugusiems">Suaugusiems</a></li>
        </ul>
      </li>
      <li><a class="nav-link nl-blue" href="<?= $h ?>#kontaktai">Kontaktai</a></li>
      <li><a class="nav-link nl-green" href="<?= url('naujienos.php') ?>">Naujienos</a></li>
      <?php if ($a && is_staff($a)): ?>
        <li><a class="nav-link nl-green" href="<?= url('admin/') ?>">Treneriams</a></li>
      <?php endif; ?>
      <?php if ($a): ?>
        <li class="mobile-only"><a class="nav-link" href="<?= url('paskyra.php') ?>">Mano paskyra</a></li>
        <li class="mobile-only">
          <form method="post" action="<?= url('atsijungti.php') ?>" class="inline-form"><?= csrf_field() ?>
            <button type="submit" class="nav-link nl-plain">Atsijungti</button>
          </form>
        </li>
      <?php else: ?>
        <li class="mobile-only"><a class="nav-link" href="<?= url('prisijungti.php') ?>">Prisijungti</a></li>
      <?php endif; ?>
    </ul>
    <?php if ($a): ?>
      <a href="<?= url('paskyra.php') ?>" class="nav-login">Mano paskyra</a>
    <?php else: ?>
      <a href="<?= url('prisijungti.php') ?>" class="nav-login">Prisijungti</a>
    <?php endif; ?>
    <a href="<?= $h ?>#registracija" class="nav-cta">2 treniruotės nemokamai</a>
    <button class="burger" id="burgerBtn" type="button" aria-label="Meniu"><span></span><span></span><span></span></button>
  </nav>
</header>
<?php
}
