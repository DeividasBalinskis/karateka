<?php
// Viešas varžybų rezultatų puslapis: klubų medaliai ir prizininkai pagal kategorijas (iš varžybų sistemos, tournaments.results).
// Be ?id - visų viešų varžybų sąrašas. Test varžybos rodomos tik test svetainėje ir kompiuteryje.
require __DIR__ . '/app/bootstrap.php';

$id = (int) get('id');
$t = $id ? tournament_get($id) : null;
$staff = is_staff(current_account());
if ($id && (!$t || (!tournament_is_visible($t) && !$staff))) {
    http_response_code(404);
}
$visible = $t && (tournament_is_visible($t) || $staff);
$all = !$id ? array_values(array_filter(tournaments_list(), 'tournament_is_visible')) : [];
$r = $visible ? tournament_results($t) : null;
$cats = $r ? array_values(array_filter((array) $r['categories'], function ($c) { return !empty($c['count']); })) : [];
$finished = count(array_filter($cats, function ($c) { return !empty($c['finished']); }));
// Vaikų ir jaunių (iki 18 m.) vardai viešai - „Vardas P.“ (treneriai mato pilnus)
$name = function (array $p) use ($staff): string {
    $parts = preg_split('/\s+/u', trim((string) $p['name']));
    return !$staff && !empty($p['minor']) && count($parts) > 1 ? $parts[0] . ' ' . mb_substr(end($parts), 0, 1) . '.' : (string) $p['name'];
};
$medal = function (int $p): string { return '<span class="medal ' . ['', 'g', 's', 'b'][$p] . '">' . $p . '</span>'; };
?><!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ($visible && $t['status'] === 'running'): ?><meta http-equiv="refresh" content="60"><?php endif; ?>
<title><?= $visible ? e($t['title']) . ' · ' : '' ?><?= te('Rezultatai') ?> · <?= e(VARZ_NAME) ?></title>
<link rel="icon" type="image/png" sizes="32x32" href="<?= url('favicon-32.png?v=2') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('assets/varzybos.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('assets/widgets.css')) ?>">
</head>
<body class="vz vz-public">
<header class="vz-top">
  <div class="vz-top-row">
    <a class="vz-brand" href="<?= url('varzybu-rezultatai.php') ?>"><span class="vz-logo" aria-hidden="true"></span><?= e(VARZ_NAME) ?></a>
    <div class="vz-title">
      <h1><?= $visible ? e($t['title']) : te('Varžybų rezultatai') ?></h1>
      <div class="sub"><?php if ($visible): ?><?= e(fmt_date($t['starts_on'], true)) ?><?= $t['ends_on'] ? ' – ' . e(fmt_date($t['ends_on'], true)) : '' ?><?= $t['location'] ? ' · ' . e($t['location']) : '' ?>
        <?= $t['is_test'] ? ' <span class="vz-test">TEST</span>' : '' ?><?php else: ?><?= te('Karateka ir LTKF varžybos') ?><?php endif; ?></div>
    </div>
    <div class="vz-top-right"><a class="vz-link" href="<?= url('index.php') ?>">karateka.lt</a></div>
  </div>
</header>
<main class="pub">
<?php if ($id && !$visible): ?>
  <div class="panel pub-empty"><h2><?= te('Varžybos nerastos') ?></h2><p class="muted"><a href="<?= url('varzybu-rezultatai.php') ?>"><?= te('Visos varžybos →') ?></a></p></div>
<?php elseif (!$id): ?>
  <div class="pub-list">
    <?php foreach ($all as $x): $rx = tournament_results($x); ?>
      <a class="panel pub-item" href="<?= url('varzybu-rezultatai.php?id=' . (int) $x['id']) ?>">
        <span class="pub-date"><?= e(fmt_date($x['starts_on'], true)) ?></span>
        <strong><?= e($x['title']) ?><?= $x['is_test'] ? ' <span class="vz-test">TEST</span>' : '' ?></strong>
        <span class="muted small"><?= e((string) $x['location']) ?> · <?= count((array) ($rx['medals'] ?? [])) ?> <?= te('klubai su medaliais') ?></span>
      </a>
    <?php endforeach; ?>
    <?php if (!$all): ?><div class="panel pub-empty"><p class="muted"><?= te('Paskelbtų varžybų rezultatų dar nėra.') ?></p></div><?php endif; ?>
  </div>
<?php else: ?>
  <div class="pub-stats">
    <div><strong><?= (int) ($r['people'] ?? 0) ?></strong><span><?= te('dalyviai') ?></span></div>
    <div><strong><?= count((array) ($r['medals'] ?? [])) ?></strong><span><?= te('klubai su medaliais') ?></span></div>
    <div><strong><?= count($cats) ?></strong><span><?= te('kategorijos') ?></span></div>
    <div><strong><?= $finished ?></strong><span><?= te('baigtos') ?></span></div>
  </div>
  <div class="res-layout">
    <div class="panel">
      <h2><?= te('Klubų medaliai') ?></h2>
      <?php if (!empty($r['medals'])): ?>
        <table><thead><tr><th class="num">#</th><th><?= te('Klubas') ?></th><th class="num"><?= $medal(1) ?></th><th class="num"><?= $medal(2) ?></th><th class="num"><?= $medal(3) ?></th><th class="num"><?= te('Taškai') ?></th></tr></thead><tbody>
          <?php foreach ($r['medals'] as $i => $m): ?>
            <tr<?= $m['club'] === VARZ_HOME_CLUB ? ' class="home-row"' : '' ?>><td class="num"><?= $i + 1 ?></td><td><strong><?= e($m['club']) ?></strong></td><td class="num"><?= (int) $m['g'] ?></td><td class="num"><?= (int) $m['s'] ?></td><td class="num"><?= (int) $m['b'] ?></td><td class="num"><strong><?= (int) $m['pts'] ?></strong></td></tr>
          <?php endforeach; ?>
        </tbody></table>
      <?php else: ?><p class="muted" style="padding:14px 16px; margin:0;"><?= te('Prizininkų dar nėra.') ?></p><?php endif; ?>
    </div>
    <div class="panel">
      <h2><?= te('Prizininkai pagal kategorijas') ?></h2>
      <div class="res-cats">
        <?php foreach ($cats as $c): ?>
          <div class="res-cat">
            <div class="res-cat-top"><strong><span class="disc d-<?= e($c['discipline']) ?>"></span><?= e($c['name']) ?></strong>
              <?= !empty($c['finished']) ? '<span class="pill ok">' . te('Baigta') . '</span>' : (!empty($c['done']) ? '<span class="pill warn">' . te('Vyksta') . ' ' . (int) $c['done'] . '/' . (int) $c['total'] . '</span>' : '<span class="pill">' . te('Nepradėta') . '</span>') ?></div>
            <?php foreach ((array) $c['places'] as $p): ?>
              <div class="rp"><?= $medal((int) $p['place']) ?><span><?= e($name($p)) ?> <span class="club"><?= e($p['club']) ?></span></span></div>
            <?php endforeach; ?>
            <?php if (empty($c['places'])): ?><span class="muted small"><?= te('Prizininkų dar nėra') ?></span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <p class="hint"><?= te('Taškai klubui: 1 vieta - 3, 2 vieta - 2, 3 vieta - 1.') ?> <?= te('Vaikų ir jaunių iki 18 m. rodomas vardas ir pavardės pirmoji raidė.') ?><?= $t['status'] === 'running' ? ' ' . te('Puslapis atsinaujina kas minutę.') : '' ?></p>
<?php endif; ?>
</main>
<?= feedback_widget_html() ?><script src="<?= e(asset_url('assets/site.js')) ?>" defer></script>
</body>
</html>
