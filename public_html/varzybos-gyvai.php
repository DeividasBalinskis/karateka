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

// v1.98-v1.99: bandomoji kova (transliacijos peržiūrai ir žiūrovų vaizdui) - balus ± keičia treneris ar šių varžybų sekretoriatas.
// Įrašoma kaip tatamio „gyva“ būsena su žyma test (žiūrovų puslapis ir salės ekranas jos nerodo). Kai vyksta tikra kova - neliečiama.
$canTest = is_staff() || varz_sekr((int) $t['id']) !== null;
if (is_post() && get('bandymas')) {
    header('Content-Type: application/json; charset=utf-8');
    if (!$canTest) {
        http_response_code(403);
        exit(json_encode(['ok' => false]));
    }
    csrf_check();
    $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
    $area = (int) ($in['area'] ?? 0);
    $name = '';
    foreach ((array) (tournament_state($t)['areas'] ?? []) as $a) {
        if ((int) ($a['id'] ?? 0) === $area) {
            $name = (string) ($a['name'] ?? '');
        }
    }
    if ($name === '') {
        http_response_code(400);
        exit(json_encode(['ok' => false]));
    }
    $cur = tournament_live_get((int) $t['id'])[$area] ?? null;
    $now = (int) round(microtime(true) * 1000);
    if ($cur && empty($cur['data']['idle']) && empty($cur['data']['test']) && $now - $cur['ms'] < 30 * 60000) {
        exit(json_encode(['ok' => false, 'busy' => true, 'error' => 'Vyksta tikra kova - balai rašomi kovos skydelyje.'], JSON_UNESCAPED_UNICODE));
    }
    $data = !empty($in['end']) ? ['area' => $area, 'areaName' => $name, 'idle' => true, 'at' => $now]
        : ['area' => $area, 'areaName' => $name, 'test' => true, 'mode' => 'kumite', 'cat' => 'Bandomoji kova', 'round' => '', 'aka' => ['name' => 'Jonas Jonaitis', 'club' => ''],
           'shiro' => ['name' => 'Petras Petraitis', 'club' => ''], 'sa' => max(0, min(99, (int) ($in['sa'] ?? 0))), 'sb' => max(0, min(99, (int) ($in['sb'] ?? 0))), 'at' => $now,
           'timer' => ['total' => 90000, 'remaining' => 90000, 'running' => false]];
    tournament_live_set((int) $t['id'], $area, $data);
    exit(json_encode(['ok' => true, 'data' => $data, 'ms' => $now], JSON_UNESCAPED_UNICODE));
}

if (get('json')) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $r = tournament_results($t);
    // Tinklai: visa amžiaus grupė (visos jos rungtys) - kas su kuo kovoja
    if (get('group') !== '') {
        $list = [];
        foreach ((array) ($r['categories'] ?? []) as $c) {
            if (!empty($c['count']) && (string) ($c['group'] ?? '') === (string) get('group') && isset($r['brackets'][(string) $c['id']])) {
                $list[] = $r['brackets'][(string) $c['id']] + ['tatami' => (string) ($c['tatami'] ?? '')];
            }
        }
        exit(json_encode(['ok' => true, 'cats' => $list], JSON_UNESCAPED_UNICODE));
    }
    // Paieška „Rask save“: kuriose grupėse dalyvis, ant kurio tatamio ir kada grupė prasideda
    if (get('find') !== '') {
        $fold = function (string $x): string {
            $x = mb_strtolower($x);
            return strtr($x, ['ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i', 'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z']);
        };
        $q = $fold(trim((string) get('find')));
        $where = [];
        foreach ((array) ($r['areas'] ?? []) as $a) {
            foreach ((array) ($a['cats'] ?? []) as $c) {
                $where[(string) ($c['id'] ?? '')] = ['area' => (string) ($a['name'] ?? ''), 'start' => (string) ($c['start'] ?? '')];
            }
        }
        $found = [];
        // „Rask save“ ir pagal pavardę (sprendimas 2026-11-20): pilnas vardas iš varžybų būsenos → kaip jis rodomas viešai („Jonas P.“)
        $byFull = [];
        if (mb_strlen($q) >= 2) {
            foreach ((array) (tournament_state($t)['people'] ?? []) as $p) {
                $full = trim((string) ($p['name'] ?? ''));
                if ($full === '' || strpos($fold($full . ' ' . (string) ($p['club'] ?? '')), $q) === false) {
                    continue;
                }
                $parts = preg_split('/\s+/u', $full);
                $short = count($parts) > 1 ? $parts[0] . ' ' . mb_substr(end($parts), 0, 1) . '.' : $full;
                foreach ([$full, $short] as $shown) {
                    $byFull[$fold($shown . '|' . (string) ($p['club'] ?? ''))] = true;
                }
            }
        }
        if (mb_strlen($q) >= 2) {
            foreach ((array) ($r['categories'] ?? []) as $c) {
                $b = $r['brackets'][(string) ($c['id'] ?? '')] ?? null;
                if (!$b) {
                    continue;
                }
                $names = [];
                foreach ((array) ($b['people'] ?? []) as $p) {
                    $names[] = [(string) ($p['n'] ?? ''), (string) ($p['c'] ?? '')];
                }
                foreach ((array) ($b['groups'] ?? []) as $g) {
                    foreach ((array) ($g['rows'] ?? []) as $p) {
                        $names[] = [(string) ($p['n'] ?? ''), (string) ($p['c'] ?? '')];
                    }
                    foreach ((array) ($g['matches'] ?? []) as $m) {
                        $names[] = [(string) ($m['a'] ?? ''), (string) ($m['ac'] ?? '')];
                        $names[] = [(string) ($m['b'] ?? ''), (string) ($m['bc'] ?? '')];
                    }
                }
                foreach ($names as [$n, $club]) {
                    if ($n === '' || $n === '…' || (strpos($fold($n . ' ' . $club), $q) === false && !isset($byFull[$fold($n . '|' . $club)]))) {
                        continue;
                    }
                    $k = $n . '|' . $club;
                    $found[$k] = $found[$k] ?? ['name' => $n, 'club' => $club, 'cats' => []];
                    $w = $where[(string) $c['id']] ?? ['area' => (string) ($c['tatami'] ?? ''), 'start' => ''];
                    $found[$k]['cats'][(string) $c['id']] = ['id' => (string) $c['id'], 'name' => (string) $c['name'], 'disc' => (string) ($c['discipline'] ?? ''),
                        'area' => $w['area'], 'start' => $w['start'], 'finished' => !empty($c['finished'])];
                }
            }
        }
        $out = array_map(function ($f) { $f['cats'] = array_values($f['cats']); return $f; }, array_slice(array_values($found), 0, 30));
        exit(json_encode(['ok' => true, 'found' => $out], JSON_UNESCAPED_UNICODE));
    }
    // Viena grupė (atidarius iš sąrašo): kovos pagal ratus ir vietos
    if (get('cat') !== '') {
        $b = $r['brackets'][(string) get('cat')] ?? null;
        exit(json_encode(['ok' => (bool) $b, 'bracket' => $b], JSON_UNESCAPED_UNICODE));
    }
    $cats = [];
    foreach ((array) ($r['categories'] ?? []) as $c) {
        if (empty($c['count'])) {
            continue;
        }
        $cats[] = ['id' => (string) ($c['id'] ?? ''), 'name' => $c['name'], 'disc' => $c['discipline'], 'done' => (int) $c['done'], 'total' => (int) $c['total'], 'finished' => !empty($c['finished']),
                   'group' => (string) ($c['group'] ?? ''), 'groupName' => (string) ($c['groupName'] ?? $c['name']),
                   'places' => array_map(function ($p) { return ['place' => (int) $p['place'], 'name' => varz_public_name($p), 'club' => $p['club']]; }, (array) ($c['places'] ?? []))];
    }
    // Apdovanojimų būsenos (apdovanojama / apdovanota) - kad ir dalyviai matytų, kur renginio eiga
    $aw = tournament_awards((int) $t['id']);
    $areas = (array) ($r['areas'] ?? []);
    $cams = varz_cams_live();   // v1.99: tatamiai su priskirtu telefonu - žiūrovams „📹 Žiūrėti gyvai“
    foreach ($areas as &$a) {
        if (isset($cams[(int) ($a['id'] ?? 0)])) {
            $a['cam'] = varz_cam_view_url(varz_cam_id(varz_cam_key(), (int) $a['id']));
        }
        foreach ($a['cats'] as &$c) {
            $c['aw'] = $aw[(string) ($c['id'] ?? '')]['st'] ?? '';
        }
        unset($c);
    }
    unset($a);
    // v2.07: tatamis su telefonu, kurio dar nėra santraukoje (naujos varžybos, dar neišsaugotos) - žiūrovai vis tiek mato „📹 Žiūrėti gyvai“
    $extra = [];
    if ($cams) {
        $have = array_flip(array_map(function ($a) { return (int) ($a['id'] ?? 0); }, $areas));
        $st = json_decode((string) ($t['state'] ?? ''), true);
        foreach ((array) ($st['areas'] ?? []) as $sa) {
            $id = (int) ($sa['id'] ?? 0);
            if ($id && isset($cams[$id]) && !isset($have[$id])) {
                $extra[] = ['id' => $id, 'name' => (string) ($sa['name'] ?? ($id . ' TATAMIS')), 'cam' => varz_cam_view_url(varz_cam_id(varz_cam_key(), $id))];
            }
        }
    }
    exit(json_encode(['ok' => true, 'now' => (int) round(microtime(true) * 1000), 'title' => $t['title'], 'areas' => $areas, 'cams' => $extra,
        'live' => (object) tournament_live_get((int) $t['id']), 'medals' => $r['medals'] ?? [], 'cats' => $cats], JSON_UNESCAPED_UNICODE));
}

$screen = (bool) get('ekranas');
// Juosta (?juosta=1&tatami=N): permatomas fonas, apačioje - kova, balai ir laikas. v1.99: naudojama Transliacijos puslapio peržiūroje ant telefono
// vaizdo; &valdymas=1 - treneriui ir sekretoriatui ± prie balų (bandomoji kova).
$bar = !$screen && (bool) get('juosta');
$shareTok = tournament_token($t, 'share');
$cfg = ['json' => url('varzybos-gyvai.php?k=' . $shareTok . '&json=1'), 'id' => (int) $t['id'], 'screen' => $screen, 'bar' => $bar, 'area' => (int) get('tatami'),
        'title' => $t['title']]
     + ($bar && $canTest && get('valdymas') ? ['test' => url('varzybos-gyvai.php?k=' . $shareTok . '&bandymas=1'), 'csrf' => csrf_token()] : []);
if ($bar): ?><!doctype html>
<html lang="lt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($t['title']) ?> · Juosta · <?= e(VARZ_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('assets/varzybos.css')) ?>">
<?= tl_script() ?>
</head>
<body class="gv-bar-body">
<main id="gyvai" aria-live="polite"></main>
<script>window.GYVAI = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e(asset_url('assets/varzybos-gyvai.js')) ?>"></script>
</body>
</html>
<?php exit; endif;
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
<?= $screen ? '' : tl_script() ?>
</head>
<body<?= $screen ? ' class="vz scr"' : theme_body_attrs(varz_ui()['theme'], 'vz vz-public gyvai') ?>>
<?php if (!$screen): ?>
<header class="vz-top">
  <div class="vz-top-row">
    <span class="vz-brand"><span class="vz-logo" aria-hidden="true"></span><?= e(VARZ_NAME) ?></span>
    <div class="vz-title">
      <h1><?= e($t['title']) ?></h1>
      <div class="sub"><?= e($dates) ?><?= $t['location'] ? ' · ' . e($t['location']) : '' ?> · <span class="live-dot" aria-hidden="true"></span> gyvai</div>
    </div>
    <div class="vz-top-right"><?= render_lang_switch(tl_lang()) ?></div>
  </div>
</header>
<?php endif; ?>
<?php if (!$screen):
    $stream = tournament_stream($t); ?>
<?php if ($stream): ?>
<section class="panel gv-stream" id="gv-stream">
  <h2>🎥 Transliacija<?php if ($stream['at']): ?> <span class="muted small"><?= $stream['soon'] ? 'prasidės ' : '' ?><?= e(fmt_date(date('Y-m-d', $stream['at']))) ?> <?= e(date('H:i', $stream['at'])) ?></span><?php endif; ?></h2>
  <?php if ($stream['embed']): ?>
    <div class="gv-video"><iframe src="<?= e($stream['embed']) ?>" title="Transliacija" loading="lazy" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe></div>
  <?php elseif ($stream['soon']): ?>
    <p class="gv-soon">Transliacija prasidės <b><?= e(fmt_date(date('Y-m-d', $stream['at']))) ?> <?= e(date('H:i', $stream['at'])) ?></b>.</p>
  <?php endif; ?>
  <p><a class="btn" href="<?= e($stream['url']) ?>" target="_blank" rel="noopener">▶ Žiūrėti YouTube ↗</a></p>
</section>
<?php endif; ?>
<section class="panel gv-find">
  <label class="gv-find-l" for="gv-q">🔎 Rask save</label>
  <input type="search" class="sel" id="gv-q" placeholder="Vardas, pavardė arba klubas" autocomplete="off" aria-label="Ieškoti dalyvio">
  <div id="gv-found" aria-live="polite"></div>
</section>
<?php endif; ?>
<main id="gyvai" aria-live="polite"><p class="muted">Kraunama…</p></main>
<script>window.GYVAI = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e(asset_url('assets/varzybos-gyvai.js')) ?>"></script>
</body>
</html>
