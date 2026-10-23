<?php
// Puslapio rėmas: viršus su meniu ir apačia. puslapis_pradzia('Klubai', 'klubai') ... puslapis_pabaiga()

require_once __DIR__ . '/pagalbines.php';

const MENIU = [
    'index'       => ['Pradžia', 'index.php'],
    'apie'        => ['Apie', 'apie.php'],
    'klubai'      => ['Klubai', 'klubai.php'],
    'kalendorius' => ['Kalendorius', 'kalendorius.php'],
    'rezultatai'  => ['Rezultatai', 'rezultatai.php'],
    'teisejai'    => ['Teisėjai', 'teisejai.php'],
    'dokumentai'  => ['Dokumentai', 'dokumentai.php'],
    'naujienos'   => ['Naujienos', 'naujienos.php'],
    'kontaktai'   => ['Kontaktai', 'kontaktai.php'],
];

function logotipas(int $size = 44): string
{
    // Laikinas ženklas - tikras federacijos logotipas bus įkeltas vėliau
    return '<svg class="logo-mark" width="' . $size . '" height="' . $size . '" viewBox="0 0 48 48" aria-hidden="true">'
        . '<circle cx="24" cy="24" r="22.5" fill="none" stroke="currentColor" stroke-width="2"/>'
        . '<circle cx="24" cy="24" r="17" fill="none" stroke="currentColor" stroke-width="1" opacity=".55"/>'
        . '<path d="M14 31 L24 13 L34 31" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"/>'
        . '<path d="M18 26 H30" stroke="currentColor" stroke-width="2.4"/>'
        . '<rect x="11" y="34" width="8.6" height="2.6" fill="#F2B705"/><rect x="19.7" y="34" width="8.6" height="2.6" fill="#00704A"/><rect x="28.4" y="34" width="8.6" height="2.6" fill="#C1272D"/>'
        . '</svg>';
}

function puslapis_pradzia(string $title, string $active = '', array $opt = []): void
{
    $full = $title === '' ? n('pavadinimas') : $title . ' – ' . n('trumpas');
    $lang = $opt['lang'] ?? 'lt';
    ?><!doctype html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($full) ?></title>
<meta name="description" content="<?= e($opt['description'] ?? 'Lietuvos tradicinio karatė federacija: klubai, varžybų kalendorius, rezultatai, teisėjai ir dokumentai.') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700&family=Source+Sans+3:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="assets/ltkf.css?v=1">
<?= $opt['head'] ?? '' ?>
</head>
<body>
<div class="tricolor" aria-hidden="true"><span></span><span></span><span></span></div>
<?php if (n('demo')): ?>
<div class="demo-bar">Juodraštis susitikimui. Vietos, pažymėtos <span class="chip chip-demo">Pavyzdys</span> ar [laužtiniuose skliaustuose], laukia tikrų duomenų.</div>
<?php endif; ?>
<header class="site-header">
  <div class="wrap header-row">
    <a class="brand" href="index.php"><?= logotipas() ?><span class="brand-text"><b><?= e(n('trumpas')) ?></b><small><?= e(n('pavadinimas')) ?></small></span></a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="main-nav">Meniu</button>
    <nav id="main-nav" class="main-nav" aria-label="Pagrindinis meniu">
      <?php foreach (MENIU as $key => [$label, $href]): if ($key === 'index') continue; ?>
        <a href="<?= e($href) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <a class="lang" href="en.php"<?= $active === 'en' ? ' aria-current="page"' : '' ?> lang="en">EN</a>
    </nav>
  </div>
</header>
<main>
<?php
}

function puslapis_pabaiga(): void
{
    ?>
</main>
<footer class="site-footer">
  <div class="wrap footer-grid">
    <div class="footer-brand"><?= logotipas(40) ?><div><b><?= e(n('pavadinimas')) ?></b><p>Vienija tradicinio karatė klubus Lietuvoje: varžybos, teisėjai, egzaminai, rinktinė.</p></div></div>
    <div><h3>Federacija</h3><a href="apie.php">Apie mus</a><a href="klubams.php">Klubams: narystė</a><a href="dokumentai.php">Dokumentai</a></div>
    <div><h3>Sportas</h3><a href="kalendorius.php">Kalendorius</a><a href="rezultatai.php">Rezultatai</a><a href="teisejai.php">Teisėjai</a></div>
    <div><h3>Kontaktai</h3><span><?= e(n('el_pastas')) ?></span><span><?= e(n('telefonas')) ?></span><a href="kontaktai.php">Rekvizitai</a></div>
  </div>
  <div class="wrap footer-bottom">© <?= date('Y') ?> <?= e(n('pavadinimas')) ?> · <a href="en.php" lang="en">English</a></div>
</footer>
<script src="assets/ltkf.js?v=1" defer></script>
</body>
</html>
<?php
}

/** Renginio kortelė: datos ženklas, tipas, pavadinimas, vieta, veiksmas */
function renginio_kortele(array $r): string
{
    $t = strtotime($r['nuo']);
    $tipas = RENGINIU_TIPAI[$r['tipas']] ?? 'Renginys';
    $praejo = ($r['iki'] ?: $r['nuo']) < date('Y-m-d');
    $href = !empty($r['is_sistemos']) ? $r['nuoroda'] : 'renginys.php?id=' . urlencode($r['id']);
    $ext = !empty($r['is_sistemos']) ? ' target="_blank" rel="noopener"' : '';
    $veiksmas = '';
    if (!$praejo && !empty($r['registracija'])) {
        $veiksmas = '<span class="chip chip-open">Registracija atidaryta</span>';
    } elseif ($praejo && (!empty($r['rezultatai']) || !empty($r['is_sistemos']))) {
        $veiksmas = '<span class="chip">Rezultatai</span>';
    }
    $sist = !empty($r['is_sistemos']) ? ' <span class="chip chip-sys" title="Duomenys iš varžybų sistemos">Iš varžybų sistemos</span>' : '';
    return '<a class="event-card tipas-' . e($r['tipas']) . ($praejo ? ' is-past' : '') . '" href="' . e($href) . '"' . $ext . '>'
        . '<span class="date-badge"><b>' . (int) date('j', $t) . '</b><small>' . MENESIAI_TRUMP[(int) date('n', $t) - 1] . '</small></span>'
        . '<span class="event-body"><span class="event-type">' . e($tipas) . '</span>'
        . '<span class="event-title">' . e($r['pavadinimas']) . pavyzdys($r) . $sist . '</span>'
        . '<span class="event-meta">' . e(datos_lt($r['nuo'], $r['iki'] ?? null)) . ($r['vieta'] ? ' · ' . e($r['vieta']) : '') . '</span></span>'
        . ($veiksmas ? '<span class="event-action">' . $veiksmas . '</span>' : '')
        . '</a>';
}

function naujienos_kortele(array $a): string
{
    return '<a class="news-card" href="naujiena.php?id=' . urlencode($a['id']) . '">'
        . '<span class="news-date">' . e(data_lt($a['data'])) . '</span>'
        . '<span class="news-title">' . e($a['pavadinimas']) . pavyzdys($a) . '</span>'
        . '<span class="news-sum">' . e($a['santrauka']) . '</span></a>';
}
