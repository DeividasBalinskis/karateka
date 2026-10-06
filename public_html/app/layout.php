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
<link rel="stylesheet" href="<?= e($css) ?>">
</head>
<body class="<?= $admin ? 'is-admin' : '' ?>">
<header class="site-header">
  <nav class="site-nav">
    <a class="logo" href="<?= url('index.html') ?>"><img src="<?= url('LogoColor.png') ?>" alt="Karateka"></a>
    <button class="burger" type="button" aria-label="Meniu" onclick="document.body.classList.toggle('nav-open')"><span></span><span></span><span></span></button>
    <ul class="nav-list">
      <li><a class="nav-link" href="<?= url('page1.html') ?>">Treniruotės</a></li>
      <li><a class="nav-link" href="<?= url('naujienos.php') ?>">Naujienos</a></li>
      <?php if ($a): ?>
        <li><a class="nav-link" href="<?= url('paskyra.php') ?>">Mano paskyra</a></li>
        <?php if (is_staff($a)): ?><li><a class="nav-link nav-admin" href="<?= url('admin/') ?>">Treneriams</a></li><?php endif; ?>
        <li>
          <form method="post" action="<?= url('atsijungti.php') ?>" class="inline-form"><?= csrf_field() ?>
            <button type="submit" class="nav-link linklike">Atsijungti</button>
          </form>
        </li>
      <?php else: ?>
        <li><a class="nav-link" href="<?= url('prisijungti.php') ?>">Prisijungti</a></li>
        <li><a class="nav-cta" href="<?= url('registracija.php') ?>">Registruotis</a></li>
      <?php endif; ?>
    </ul>
  </nav>
</header>
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
