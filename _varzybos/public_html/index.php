<?php
// Programos karkasas: visas turinys piešiamas assets/js/ (puslapiai keičiasi be perkrovimo, duomenys - per api.php)
require __DIR__ . '/app/bootstrap.php';

$v = fn (string $f) => $f . '?v=' . @filemtime(__DIR__ . '/' . $f);
$name = config('site_name', 'Varžybos');
?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($name) ?></title>
<meta name="description" content="Tradicinio karate varžybos: kategorijos, lentelės, tvarkaraštis ir rezultatai gyvai.">
<meta name="theme-color" content="#811517">
<link rel="icon" type="image/png" sizes="32x32" href="favicon-32.png">
<link rel="apple-touch-icon" href="favicon-192.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $v('assets/app.css') ?>">
</head>
<body>
<header class="top" id="top"></header>
<div id="app"><main class="page" id="page"><div class="loading"><div class="spinner"></div>Kraunama…</div></main></div>
<footer class="foot"><div><span>© <?= date('Y') ?> VšĮ Karate Ateitis · tradicinio karate varžybų sistema</span><span>Nuo balto iki juodo diržo.</span></div></footer>
<noscript><p style="padding:20px">Šiam puslapiui reikia įjungto JavaScript.</p></noscript>
<script type="module" src="<?= $v('assets/js/app.js') ?>"></script>
</body>
</html>
