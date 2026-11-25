<?php
// Apdovanojimų planšetė (prieiga tik su slapta nuoroda ?k=, admin → „📡 Transliacija ir registracija“ → „🏅 Apdovanojimų planšetė“).
// #122: kiekviena baigta rungtis atsiranda viršuje pati (anksčiausiai baigtos pirmos) su prizininkais; „✓ Apdovanota“ - nukeliama žemyn
// į „Jau apdovanota“ (klaidoms atsekti, „↺ Grąžinti“); sistemoje rungtis pažymima „Užbaigta ir apdovanota“.
// Duomenys: varžybų santrauka (tournaments.results - prizininkai su pilnais vardais) ir būsenos (db/033 tournament_awards).
require __DIR__ . '/app/bootstrap.php';

$t = tournament_by_token((string) get('k'), 'awards');
if (!$t) {
    http_response_code(404);
    exit('<!doctype html><meta charset="utf-8"><title>Nuoroda netinkama</title><p style="font-family:system-ui;padding:40px;">Nuoroda netinkama arba varžybos ištrintos.</p>');
}

if (get('json') || is_post()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (is_post()) {
        if (!csrf_ok()) {   // C1: kiekvienas POST - su sesijos žetonu (window.APD.csrf)
            http_response_code(400);
            exit(json_encode(['ok' => false, 'error' => 'Puslapis per ilgai buvo atidarytas - atnaujinkite jį.'], JSON_UNESCAPED_UNICODE));
        }
        // Planšetė: „Užbaigti“ (apdovanota) arba „Grąžinti“ (vėl laukia apdovanojimo). Siųsti gali tik sekretoriatas.
        $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
        $st = ($in['status'] ?? '') === 'done' ? 'done' : 'sent';
        $have = tournament_awards((int) $t['id']);
        $fin = [];   // baigtos rungtys (#122: jas galima apdovanoti ir be „Siųsti apdovanojimams“)
        foreach ((array) (tournament_results($t)['categories'] ?? []) as $c) {
            if (!empty($c['finished'])) {
                $fin[(string) ($c['id'] ?? '')] = true;
            }
        }
        foreach (array_slice((array) ($in['cats'] ?? []), 0, 100) as $cid) {
            if (isset($have[(string) $cid]) || isset($fin[(string) $cid])) {
                tournament_award_set((int) $t['id'], (string) $cid, $st);
            }
        }
    }
    $r = tournament_results($t);
    $cats = [];
    foreach ((array) ($r['categories'] ?? []) as $c) {
        if (empty($c['count'])) {
            continue;
        }
        $cats[] = ['id' => (string) ($c['id'] ?? ''), 'name' => (string) $c['name'], 'disc' => (string) $c['discipline'], 'done' => (int) $c['done'], 'total' => (int) $c['total'],
                   'finished' => !empty($c['finished']), 'group' => (string) ($c['group'] ?? ('c' . ($c['id'] ?? ''))), 'groupName' => (string) ($c['groupName'] ?? $c['name']),
                   'tatami' => (string) ($c['tatami'] ?? ''),
                   'places' => array_map(function ($p) { return ['place' => (int) $p['place'], 'name' => (string) $p['name'], 'club' => (string) $p['club']]; }, (array) ($c['places'] ?? []))];
    }
    exit(json_encode(['ok' => true, 'cats' => $cats, 'awards' => (object) tournament_awards((int) $t['id'])], JSON_UNESCAPED_UNICODE));
}

$cfg = ['api' => url('varzybos-apdovanojimai.php?k=' . $t['awards_token']), 'title' => $t['title'], 'csrf' => csrf_token()];
varz_html_head('Apdovanojimai · ' . $t['title'], 'vz-public apd');
?>
<header class="vz-top">
  <div class="vz-top-row">
    <span class="vz-brand"><span class="vz-logo" aria-hidden="true"></span><?= e(VARZ_NAME) ?></span>
    <div class="vz-title">
      <h1>🏅 Apdovanojimai</h1>
      <div class="sub"><?= e($t['title']) ?> · <?= e(fmt_date($t['starts_on'], true)) ?></div>
    </div>
  <div class="vz-top-right"><?= render_lang_switch(tl_lang()) ?></div>
  </div>
</header>
<main id="apd" aria-live="polite"><p class="muted">Kraunama…</p></main>
<script>window.APD = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e(asset_url('assets/varzybos-apdovanojimai.js')) ?>"></script>
</body>
</html>
