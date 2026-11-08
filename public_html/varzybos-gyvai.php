<?php
// Varžybos gyvai (prieiga tik su nuorodos raktu ?k=):
//  - žiūrovams telefone: kas vyksta ant kiekvieno tatamio, kas eina toliau, kategorijų laikai, rezultatai;
//  - ?ekranas=1&tatami=N - tatamio ekranas salėje (televizorius, antras monitorius): kova, balai, laikrodis.
// Duomenys: kovos skydelis (tournament_live) ir varžybų santrauka (tournaments.results), atnaujinama kas 1-3 s (?json=1).
require __DIR__ . '/app/bootstrap.php';

$t = tournament_by_token((string) get('k'), 'share');
if (!$t) {
    http_response_code(404);
    exit('<!doctype html><meta charset="utf-8"><title>Nuoroda netinkama</title><p style="font-family:system-ui;padding:40px;">Nuoroda netinkama arba varžybos ištrintos.</p>');
}

if (get('json')) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $r = tournament_results($t);
    $cats = [];
    foreach ((array) ($r['categories'] ?? []) as $c) {
        if (empty($c['count'])) {
            continue;
        }
        $cats[] = ['name' => $c['name'], 'disc' => $c['discipline'], 'done' => (int) $c['done'], 'total' => (int) $c['total'], 'finished' => !empty($c['finished']),
                   'places' => array_map(function ($p) { return ['place' => (int) $p['place'], 'name' => varz_public_name($p), 'club' => $p['club']]; }, (array) ($c['places'] ?? []))];
    }
    exit(json_encode(['ok' => true, 'now' => (int) round(microtime(true) * 1000), 'title' => $t['title'], 'areas' => $r['areas'] ?? [],
        'live' => (object) tournament_live_get((int) $t['id']), 'medals' => $r['medals'] ?? [], 'cats' => $cats], JSON_UNESCAPED_UNICODE));
}

$screen = (bool) get('ekranas');
$cfg = ['json' => url('varzybos-gyvai.php?k=' . $t['share_token'] . '&json=1'), 'id' => (int) $t['id'], 'screen' => $screen, 'area' => (int) get('tatami'),
        'title' => $t['title']];
$dates = fmt_date($t['starts_on'], true) . ($t['ends_on'] ? ' – ' . fmt_date($t['ends_on'], true) : '');
?><!doctype html>
<html lang="lt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($t['title']) ?> · <?= $screen ? 'Ekranas' : 'Gyvai' ?> · <?= e(VARZ_NAME) ?></title>
<link rel="icon" type="image/png" sizes="32x32" href="<?= url('favicon-32.png?v=2') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('assets/varzybos.css')) ?>">
</head>
<body class="vz <?= $screen ? 'scr' : 'vz-public gyvai' ?>">
<?php if (!$screen): ?>
<header class="vz-top">
  <div class="vz-top-row">
    <span class="vz-brand"><span class="vz-logo" aria-hidden="true"></span><?= e(VARZ_NAME) ?></span>
    <div class="vz-title">
      <h1><?= e($t['title']) ?></h1>
      <div class="sub"><?= e($dates) ?><?= $t['location'] ? ' · ' . e($t['location']) : '' ?> · <span class="live-dot" aria-hidden="true"></span> gyvai</div>
    </div>
  </div>
</header>
<?php endif; ?>
<main id="gyvai" aria-live="polite"><p class="muted">Kraunama…</p></main>
<script>window.GYVAI = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e(asset_url('assets/varzybos-gyvai.js')) ?>"></script>
</body>
</html>
