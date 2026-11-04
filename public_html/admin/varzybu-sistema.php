<?php
// Varžybų valdymas (Valdymas → Varžybos → Atidaryti): klubai, dalyviai, komandos, kategorijos, lentelės, tvarkaraštis, rezultatai.
// Viskas veikia naršyklėje (assets/varzybos.js), pakeitimai išsaugomi automatiškai (POST JSON į šį puslapį).
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();
current_lang('lt');   // varžybų valdymas kol kas lietuviškai

$t = tournament_get((int) get('id'));
if (!$t) {
    flash('err', t('Varžybos nerastos.'));
    redirect('admin/varzybos.php');
}

// Išsaugojimas (fetch iš assets/varzybos.js)
if (is_post()) {
    csrf_check();
    header('Content-Type: application/json; charset=utf-8');
    $in = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($in) || !is_array($in['state'] ?? null) || !is_array($in['results'] ?? null)) {
        http_response_code(400);
        exit(json_encode(['ok' => false, 'error' => 'Netinkami duomenys.']));
    }
    $raw = json_encode($in['state'], JSON_UNESCAPED_UNICODE);
    if (strlen($raw) > 8 * 1024 * 1024) {
        http_response_code(413);
        exit(json_encode(['ok' => false, 'error' => 'Per daug duomenų.']));
    }
    $v = tournament_save_state((int) $t['id'], $in['state'], $in['results'], (int) ($in['version'] ?? -1));
    if ($v === null) {
        http_response_code(409);
        exit(json_encode(['ok' => false, 'error' => 'Kitas žmogus jau pakeitė varžybas.']));
    }
    exit(json_encode(['ok' => true, 'version' => $v]));
}

$belts = [];
foreach (BELTS as $lvl => $b) {
    $belts[$lvl] = [$b[0], $b[1], $b[2]];
}
$cfg = [
    'id' => (int) $t['id'], 'title' => $t['title'], 'version' => (int) $t['version'], 'isTest' => (bool) $t['is_test'],
    'api' => url('admin/varzybu-sistema.php?id=' . (int) $t['id']), 'csrf' => csrf_token(), 'year' => (int) substr($t['starts_on'], 0, 4),
    'homeClub' => VARZ_HOME_CLUB, 'belts' => $belts, 'members' => varz_club_members(), 'state' => tournament_state($t),
];
$dates = fmt_date($t['starts_on'], true) . ($t['ends_on'] ? ' – ' . fmt_date($t['ends_on'], true) : '');
?><!doctype html>
<html lang="lt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($t['title']) ?> · <?= e(VARZ_NAME) ?></title>
<link rel="icon" type="image/png" sizes="32x32" href="<?= url('favicon-32.png?v=2') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('assets/varzybos.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('assets/widgets.css')) ?>">
</head>
<body class="vz">
<header class="vz-top">
  <div class="vz-top-row">
    <a class="vz-back" href="<?= url('admin/varzybos.php') ?>" title="Visos varžybos">←</a>
    <a class="vz-brand" href="<?= url('admin/varzybos.php') ?>"><span class="vz-logo" aria-hidden="true"></span><?= e(VARZ_NAME) ?></a>
    <div class="vz-title">
      <h1 id="t-name"><?= e($t['title']) ?></h1>
      <div class="sub"><?= e($dates) ?><?= $t['location'] ? ' · ' . e($t['location']) : '' ?><?= $t['is_test'] ? ' <span class="vz-test">TEST</span>' : '' ?></div>
    </div>
    <div class="vz-top-right">
      <span class="save-state ok" id="save-state" role="status">Išsaugota ✓</span>
      <a class="vz-link" href="<?= url('varzybu-rezultatai.php?id=' . (int) $t['id']) ?>" target="_blank" rel="noopener">Viešas puslapis ↗</a>
    </div>
  </div>
  <nav class="tabs" role="tablist" aria-label="Dalys">
    <button role="tab" id="tab-dalyviai" aria-selected="false" aria-controls="v-dalyviai"><span class="tab-n">1</span>Dalyviai</button>
    <button role="tab" id="tab-klubai" aria-selected="false" aria-controls="v-klubai"><span class="tab-n">2</span>Klubai</button>
    <button role="tab" id="tab-komandos" aria-selected="false" aria-controls="v-komandos"><span class="tab-n">3</span>Komandos</button>
    <button role="tab" id="tab-kategorijos" aria-selected="false" aria-controls="v-kategorijos"><span class="tab-n">4</span>Kategorijos</button>
    <button role="tab" id="tab-lenteles" aria-selected="false" aria-controls="v-lenteles"><span class="tab-n">5</span>Lentelės</button>
    <button role="tab" id="tab-tvarkarastis" aria-selected="false" aria-controls="v-tvarkarastis"><span class="tab-n">6</span>Tvarkaraštis</button>
    <button role="tab" id="tab-rezultatai" aria-selected="false" aria-controls="v-rezultatai"><span class="tab-n">7</span>Rezultatai</button>
  </nav>
</header>
<div class="notice" id="conflict" hidden><p>Kitas žmogus ką tik pakeitė šias varžybas. Kad neperrašytumėte jo pakeitimų, <a href="">perkraukite puslapį</a>.</p></div>

<main>
  <!-- DALYVIAI -->
  <section id="v-dalyviai" role="tabpanel" aria-labelledby="tab-dalyviai" hidden>
    <div class="toolbar">
      <button class="btn btn-primary" id="ppl-members" type="button">+ Karateka nariai</button>
      <input type="search" class="sel" id="ppl-search" placeholder="Ieškoti dalyvio" aria-label="Ieškoti dalyvio">
      <select class="sel" id="ppl-club" aria-label="Klubas"></select>
      <label class="small"><input type="checkbox" id="ppl-nocat"> Tik be kategorijos</label>
      <span class="spacer"></span>
      <input class="sel" id="ppl-new-name" placeholder="Vardas Pavardė" aria-label="Naujo dalyvio vardas">
      <select class="sel" id="ppl-new-club" aria-label="Naujo dalyvio klubas"></select>
      <button class="btn" id="ppl-add" type="button">+ Dalyvis</button>
    </div>
    <div class="panel members-pick" id="members-pick" hidden>
      <h2>Karateka nariai <span class="spacer"></span><button class="btn" type="button" id="mp-close">Uždaryti</button></h2>
      <div class="mp-tools">
        <input type="search" class="sel" id="mp-search" placeholder="Ieškoti nario" aria-label="Ieškoti nario">
        <div class="mp-groups chips-row"></div>
      </div>
      <div class="mp-list"></div>
      <div class="mp-foot"><button class="btn" type="button" id="mp-all">Pažymėti visus</button><button class="btn btn-primary" type="button" id="mp-add">Pridėti pažymėtus</button>
        <span class="muted small">Gimimo metai, lytis ir diržas paimami iš nario kortelės - juos galima pataisyti lentelėje.</span></div>
    </div>
    <div class="panel" id="people-view"></div>
  </section>

  <!-- KLUBAI -->
  <section id="v-klubai" role="tabpanel" aria-labelledby="tab-klubai" hidden>
    <div class="toolbar">
      <input class="sel" id="club-new-name" placeholder="Naujo klubo pavadinimas" aria-label="Naujo klubo pavadinimas">
      <button class="btn btn-primary" id="club-add" type="button">+ Klubas</button>
      <span class="spacer"></span><span class="muted small">Klubai paimti iš LTKF sąrašo. Pavadinimą galima pataisyti lentelėje - pasikeis visur.</span>
    </div>
    <div class="panel" id="clubs-view"></div>
  </section>

  <!-- KOMANDOS -->
  <section id="v-komandos" role="tabpanel" aria-labelledby="tab-komandos" hidden>
    <div class="toolbar">
      <label>Klubas <select class="sel" id="team-club"></select></label>
      <label>Kategorija <select class="sel" id="team-cat"></select></label>
      <input class="sel" id="team-name" placeholder="Pavadinimas (nebūtina)" aria-label="Komandos pavadinimas">
      <button class="btn btn-primary" id="team-add" type="button">+ Komanda</button>
      <button class="btn" id="team-cat-new" type="button">+ Komandinė kategorija</button>
    </div>
    <div class="panel" id="team-pick" style="margin-bottom:14px;"></div>
    <div class="panel" id="teams-view"></div>
  </section>

  <!-- KATEGORIJOS -->
  <section id="v-kategorijos" role="tabpanel" aria-labelledby="tab-kategorijos" hidden>
    <div class="cat-layout">
      <div class="panel cat-side">
        <h2>Kategorijos <span class="spacer"></span><button class="btn" id="cat-auto" type="button" title="Pagal amžių, lytį ir diržą">⚡ Automatiškai</button><button class="btn" id="cat-new" type="button">+ Nauja</button></h2>
        <ul class="cat-list" id="cat-list"></ul>
      </div>
      <div class="panel cat-main" id="cat-detail"></div>
      <div class="panel cat-pool" id="cat-pool" data-drop-pool>
        <h2>Dalyviai <span class="spacer"></span><span class="muted small">tempkite ↔</span></h2>
        <div class="pool-tools">
          <input type="search" class="sel" id="pool-search" placeholder="Ieškoti" aria-label="Ieškoti dalyvio">
          <div class="chips-row"><button type="button" class="fchip" data-pool="fit">Tinka šiai</button><button type="button" class="fchip" data-pool="none">Be kategorijos</button><button type="button" class="fchip" data-pool="all">Visi</button></div>
        </div>
        <div class="pool-list"></div>
      </div>
    </div>
  </section>

  <!-- LENTELĖS -->
  <section id="v-lenteles" role="tabpanel" aria-labelledby="tab-lenteles" hidden>
    <div class="toolbar">
      <label>Kategorija <select class="sel" id="draw-cat"></select></label>
      <label>Sistema <select class="sel" id="draw-system">
        <option value="wkf">Olimpinė + paguoda (2 bronzos)</option>
        <option value="bronze">Olimpinė + kova dėl 3 vietos</option>
        <option value="plain">Olimpinė, 2 bronzos be kovos</option>
        <option value="rr">Ratų sistema (visi su visais)</option>
        <option value="score">Balai (teisėjų vertinimas)</option>
      </select></label>
      <button class="btn btn-primary" id="draw-new" type="button">Burti iš naujo</button>
      <button class="btn" id="draw-swap" type="button" aria-pressed="false">Sukeisti dalyvius</button>
      <button class="btn" id="draw-lock" type="button" aria-pressed="false">Užrakinti</button>
      <button class="btn" id="draw-undo" type="button">Atšaukti</button>
      <span class="spacer"></span>
      <?php if ($t['is_test']): ?><button class="btn btn-test" id="draw-sim" type="button" title="Tik test varžybose">🎲 Sužaisti kategoriją</button><?php endif; ?>
      <button class="btn" id="draw-print" type="button">Spausdinti</button>
      <button class="btn" id="draw-clear" type="button">Išvalyti rezultatus</button>
    </div>
    <div id="draw-view"></div>
  </section>

  <!-- TVARKARAŠTIS -->
  <section id="v-tvarkarastis" role="tabpanel" aria-labelledby="tab-tvarkarastis" hidden>
    <div class="toolbar settings">
      <button class="btn btn-primary" id="area-new" type="button">+ Tatamis</button>
      <span class="muted small">Kovos trukmė, min:</span>
      <label class="small"><span class="disc d-kata"></span>Kata <input type="number" min="1" max="15" step="0.5" id="m-kata"></label>
      <label class="small"><span class="disc d-kumite"></span>Kumite <input type="number" min="1" max="15" step="0.5" id="m-kumite"></label>
      <label class="small"><span class="disc d-fukugo"></span>Fukugo <input type="number" min="1" max="15" step="0.5" id="m-fukugo"></label>
      <label class="small"><span class="disc d-kihon"></span>Kihon <input type="number" min="1" max="15" step="0.5" id="m-kihon"></label>
      <label class="small"><span class="disc d-enbu"></span>Enbu <input type="number" min="1" max="15" step="0.5" id="m-enbu"></label>
      <span class="spacer"></span>
      <button class="btn" id="auto-balance" type="button">Paskirstyti tolygiai</button>
    </div>
    <div class="sched" id="sched"></div>
    <p class="hint">Tempkite kategorijas tarp tatamių arba naudokite ↑ ↓ →. Laikai persiskaičiuoja patys pagal kovų skaičių ir trukmę. Juosta rodo atliktas kovas.</p>
  </section>

  <!-- REZULTATAI -->
  <section id="v-rezultatai" role="tabpanel" aria-labelledby="tab-rezultatai" hidden>
    <div class="toolbar">
      <label class="small"><input type="checkbox" id="res-done"> Tik baigtos kategorijos</label>
      <span class="muted small" id="res-count"></span>
      <span class="spacer"></span>
      <?php if ($t['is_test']): ?><button class="btn btn-test" id="res-sim" type="button" title="Tik test varžybose">🎲 Užbaigti visas kovas</button><?php endif; ?>
      <button class="btn" id="res-csv" type="button">Atsisiųsti CSV (Excel)</button>
      <button class="btn btn-primary" id="res-print" type="button">Spausdinti</button>
    </div>
    <div class="res-layout">
      <div class="panel"><h2>Klubų medaliai</h2><div id="medals-view"></div></div>
      <div class="panel"><h2>Prizininkai pagal kategorijas</h2><div id="podium-view"></div></div>
    </div>
    <p class="hint">Taškai klubui: 1 vieta - 3, 2 vieta - 2, 3 vieta - 1. Rezultatai matomi viešame puslapyje ir LTKF svetainėje<?= $t['is_test'] ? '' : '; į narių taškus perkeliami mygtuku „Rezultatai → narių taškai“ varžybų sąraše' ?>.</p>
  </section>
</main>
<script>window.VARZ = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e(asset_url('assets/varzybos.js')) ?>"></script>
<?= feedback_widget_html() ?><script src="<?= e(asset_url('assets/site.js')) ?>" defer></script>
</body>
</html>
