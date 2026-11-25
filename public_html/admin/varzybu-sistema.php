<?php
// Varžybų valdymas (Valdymas → Varžybos → Atidaryti): klubai, komandos, dalyviai, kategorijos (organizacinė pusė), live tvarkaraštis
// (tatamiai, laikai ir kiekvienos rungties valdymas - lentelė, kovos, balai), rezultatai. Viršuje - perjungimas į kitas varžybas.
// Viskas veikia naršyklėje (assets/varzybos/ - ES moduliai, pradžia main.js), pakeitimai išsaugomi automatiškai (POST JSON į šį puslapį).
require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/diplomas.php';
$me = varz_require_access();
$demo = varz_is_demo($me);   // bandymo nuoroda: tik bandymo varžybos, be tikrų narių ir bendrų nustatymų
$sekr = !empty($me['sekr']);   // sekretoriatas per nuorodą: tik šios varžybos, be klubo narių duomenų
$preview = !$sekr && !$demo && is_staff() && get('kaip') === 'sekretoriatas';   // adminas žiūri, kaip mato sekretoriatas
$asSekr = $sekr || $preview;
current_lang('lt');   // varžybų valdymas kol kas lietuviškai

$t = tournament_get((int) get('id'));
if ($t && $demo && empty($t['is_demo'])) {
    $t = null;
}
if (!$t) {
    flash('err', t('Varžybos nerastos.'));
    redirect('admin/varzybos.php');
}

// Sekretoriato nuoroda: sukurti / pakeisti / išjungti (treneris ar adminas)
if (is_post() && in_array(post('action'), ['sekr_on', 'sekr_off'], true)) {
    csrf_check();
    if ($sekr || $demo) {
        forbidden();
    }
    varz_sekr_set((int) $t['id'], post('action') === 'sekr_on');
    flash('ok', post('action') === 'sekr_on' ? t('Sekretoriato nuoroda sukurta - nukopijuokite ir nusiųskite.') : t('Sekretoriato nuoroda išjungta - su ja nebeprisijungs.'));
    redirect('admin/varzybu-sistema.php?id=' . (int) $t['id'] . '&sekr=1');
}

// Nauji užsiregistravę per nuorodą (JSON) - mygtukas „↻“ Dalyvių skirtuke
if (get('entries') && !is_post()) {
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode(['ok' => true, 'entries' => tournament_new_entries((int) $t['id'])], JSON_UNESCAPED_UNICODE));
}

// Diplomai prizininkams: Excel pagal įkeltą šabloną arba paprasti spausdinami (?html=1); POST - šablono įkėlimas / ištrynimas
if (get('diplomai')) {
    if (is_post()) {
        csrf_check();
        header('Content-Type: application/json; charset=utf-8');
        if ($demo || $sekr) {
            exit(json_encode(['ok' => false, 'error' => t('Bandymo nuorodoje šablono keisti negalima - jis bendras visoms varžyboms.')], JSON_UNESCAPED_UNICODE));
        }
        if (post('action') === 'delete') {
            diploma_template_delete();
            exit(json_encode(['ok' => true, 'tpl' => null]));
        }
        $err = diploma_template_save((array) ($_FILES['file'] ?? []));
        exit(json_encode(['ok' => $err === '', 'error' => $err, 'tpl' => diploma_template()], JSON_UNESCAPED_UNICODE));
    }
    $rows = diploma_rows($t, get('cat') !== '' ? get('cat') : null);
    if ($rows && get('html') !== '1' && diploma_template()) {
        $bin = diplomas_xlsx(DIPLOMA_FILE, $rows);
        if ($bin !== null) {
            $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', diploma_key_slug($t['title'] . (count($rows) <= 4 ? ' ' . $rows[0]['category'] : ''))), '-');
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="diplomai-' . ($slug ?: 'varzybos') . '.xlsx"');
            header('Content-Length: ' . strlen($bin));
            exit($bin);
        }
    }
    diplomas_html($t, $rows);
    exit;
}

// Kovos skydelis: kas vyksta ant tatamio (ekranams ir žiūrovams); užsiregistravusių žymėjimas; registracijos atidarymas
if (is_post() && (get('live') || get('entries') || get('reg'))) {
    csrf_check();
    header('Content-Type: application/json; charset=utf-8');
    $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
    if (get('live')) {
        $data = is_array($in['data'] ?? null) ? $in['data'] : [];
        if (strlen(json_encode($data)) > 20000) {
            http_response_code(413);
            exit(json_encode(['ok' => false]));
        }
        tournament_live_set((int) $t['id'], max(0, (int) ($in['area'] ?? 0)), $data);
    } elseif (get('entries')) {
        $ids = array_map('intval', (array) ($in['ids'] ?? []));
        if ($ids) {
            q('UPDATE tournament_entries SET status = ? WHERE tournament_id = ? AND id IN (' . implode(',', $ids) . ')',
                [($in['status'] ?? '') === 'rejected' ? 'rejected' : 'added', (int) $t['id']]);
        }
    } elseif (array_key_exists('stream_url', $in)) {
        // YouTube transliacija: nuoroda ir pradžios laikas (tuščia - nerodoma)
        $su = trim((string) $in['stream_url']);
        if ($su !== '' && !preg_match('~^https?://~i', $su)) {
            $su = 'https://' . $su;
        }
        $sa = trim((string) ($in['stream_at'] ?? ''));
        $sa = preg_match('/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2})?$/', $sa) ? str_replace('T', ' ', strlen($sa) === 10 ? $sa . 'T10:00' : $sa) . ':00' : null;
        try {
            q('UPDATE tournaments SET stream_url = ?, stream_at = ? WHERE id = ?', [$su !== '' ? mb_substr($su, 0, 255) : null, $sa, (int) $t['id']]);
        } catch (PDOException $ex) {
            exit(json_encode(['ok' => false, 'error' => 'Atnaujinkite duomenų bazę (db/038).']));
        }
    } else {
        q('UPDATE tournaments SET reg_open = ? WHERE id = ?', [!empty($in['open']) ? 1 : 0, (int) $t['id']]);
    }
    exit(json_encode(['ok' => true]));
}

// Klubų kontaktai Excel formatu: klubas, atsakingas asmuo, telefonas, el. paštas, pastaba, dalyvių sk., kas registravo per nuorodą
if (get('kontaktai') && !is_post()) {
    $st = tournament_state($t);
    $info = (array) ($st['clubInfo'] ?? []);
    $dir = $demo ? [] : varz_club_directory();
    $regs = tournament_club_contacts((int) $t['id']);
    $count = [];
    foreach ((array) ($st['people'] ?? []) as $p) {
        $k = (string) ($p['club'] ?? '');
        $count[$k] = ($count[$k] ?? 0) + 1;
    }
    $clubs = array_values(array_unique(array_merge((array) ($st['clubs'] ?? []), array_keys($count))));
    sort($clubs, SORT_LOCALE_STRING);
    $out = [['Klubas', 'Atsakingas asmuo (treneris)', 'Telefonas', 'El. paštas', 'Pastaba', 'Dalyvių', 'Registravo per nuorodą (telefonai)']];
    foreach ($clubs as $k) {
        $i = (array) ($info[$k] ?? []);
        $d = (array) ($dir[$k] ?? []);
        $v = function (string $f) use ($i, $d) { return trim((string) (array_key_exists($f, $i) ? $i[$f] : ($d[$f] ?? ''))); };
        $out[] = [$k, $v('person'), $v('phone'), $v('email'), $v('note'), $count[$k] ?? 0,
                  implode(', ', array_map(function ($r) { return $r['phone'] . ' (' . $r['n'] . ' dal.)'; }, $regs[$k] ?? []))];
    }
    xlsx_download('klubu-kontaktai-' . $t['starts_on'], $out, 'Klubų kontaktai');
}

// Apdovanojimai: būsenos (JSON); „Siųsti apdovanojimams“ / atšaukti / apdovanota
if (get('awards')) {
    header('Content-Type: application/json; charset=utf-8');
    if (is_post()) {
        csrf_check();
        $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
        $st = in_array($in['status'] ?? '', ['sent', 'done'], true) ? $in['status'] : '';
        foreach (array_slice((array) ($in['cats'] ?? [$in['cat'] ?? '']), 0, 100) as $cid) {
            tournament_award_set((int) $t['id'], (string) $cid, $st);
        }
    }
    exit(json_encode(['ok' => true, 'awards' => (object) tournament_awards((int) $t['id'])], JSON_UNESCAPED_UNICODE));
}

// Išvaizda: tema ir meniu (bendri visoms varžyboms)
if (get('ui') && is_post()) {
    csrf_check();
    header('Content-Type: application/json; charset=utf-8');
    // Tema ir meniu bendri visiems - bandantysis jų nekeičia (jam pakeitimas galioja tik šiame lange)
    exit(json_encode(['ok' => true, 'ui' => $demo || $sekr ? varz_ui() : varz_ui_save(json_decode((string) file_get_contents('php://input'), true) ?: [])], JSON_UNESCAPED_UNICODE));
}

// Kelios planšetės (D4): naujausia būsena - ?state=1&v=N (jei versija ta pati - tik „same“, kad būtų pigu)
if (get('state') && !is_post()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $cur = (int) q_value('SELECT version FROM tournaments WHERE id = ?', [(int) $t['id']]);
    if ($demo || (int) get('v') === $cur) {
        exit(json_encode(['ok' => true, 'version' => $cur, 'same' => true]));
    }
    $fresh = tournament_get((int) $t['id']);
    exit(json_encode(['ok' => true, 'version' => $cur, 'state' => varz_state_maps(varz_drop_example_clubs(tournament_state($fresh)))], JSON_UNESCAPED_UNICODE));
}

// Istorija: kopijų sąrašas (JSON) ir atkūrimas
if (get('history') && !is_post()) {
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode(['ok' => true, 'now' => date('Y-m-d H:i:s'), 'list' => tournament_history((int) $t['id'])], JSON_UNESCAPED_UNICODE));
}
if (get('restore') && is_post()) {
    csrf_check();
    header('Content-Type: application/json; charset=utf-8');
    $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
    $r = tournament_restore((int) $t['id'], (int) ($in['id'] ?? 0));
    exit(json_encode($r ? ['ok' => true] + $r : ['ok' => false, 'error' => 'Kopija nerasta.'], JSON_UNESCAPED_UNICODE));
}

// Išsaugojimas (fetch iš assets/varzybos/store.js)
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
    tournament_awards_sync((int) $t['id'], (array) $in['results']);
    exit(json_encode(['ok' => true, 'version' => $v, 'awards' => (object) tournament_awards((int) $t['id'])]));
}

$belts = [0 => ['10 kyu', 'baltas', '#FFFFFF']];   // 10 kyu (raktas 0) - kaip egzaminuose
foreach (BELTS as $lvl => $b) {
    $belts[$lvl] = [$b[0], $b[1], $b[2]];
}
// Nuorodos: žiūrovams ir ekranams, kitų klubų registracijai (kol neimportuotas db/026 - nerodomos)
try {
    $shareUrl = abs_url('varzybos-gyvai.php?k=' . tournament_token($t, 'share'));
    $regUrl = abs_url('varzybos-registracija.php?k=' . tournament_token($t, 'reg'));
    $regOpen = (bool) q_value('SELECT reg_open FROM tournaments WHERE id = ?', [(int) $t['id']]);
} catch (PDOException $ex) {
    $shareUrl = $regUrl = '';
    $regOpen = false;
}
try {
    $awardsUrl = abs_url('varzybos-apdovanojimai.php?k=' . tournament_token($t, 'awards'));
} catch (PDOException $ex) {
    $awardsUrl = '';   // dar neimportuotas db/033
}
// v1.97: telefono kamera transliacijai (vdo.ninja) - raktas srauto vardui; puslapis su paaiškinimu - admin/varzybu-transliacija.php
// v1.99: transliacija telefonais - kiekvieno tatamio telefono vaizdas (vdo.ninja) kovos skydeliui; telefonai jungiami Transliacijos puslapyje
$cams = [];
if ($shareUrl) {
    $camKey = varz_cam_key();
    $camIds = array_map('intval', array_column((array) (tournament_state($t)['areas'] ?? []), 'id'));
    foreach (array_unique(array_merge($camIds, range(1, 8))) as $aid) {
        $cams[(string) $aid] = varz_cam_view_url(varz_cam_id($camKey, $aid));
    }
}
$streamUrl = url('admin/varzybu-transliacija.php?id=' . (int) $t['id']);
$cfg = [
    'id' => (int) $t['id'], 'title' => $t['title'], 'version' => (int) $t['version'], 'isTest' => (bool) $t['is_test'],
    'liveApi' => $shareUrl ? url('admin/varzybu-sistema.php?id=' . (int) $t['id'] . '&live=1') : '',
    'entriesApi' => url('admin/varzybu-sistema.php?id=' . (int) $t['id'] . '&entries=1'),
    'historyApi' => url('admin/varzybu-sistema.php?id=' . (int) $t['id'] . '&history=1'),
    'uiApi' => url('admin/varzybu-sistema.php?id=' . (int) $t['id'] . '&ui=1'), 'ui' => varz_ui(),
    'restoreApi' => url('admin/varzybu-sistema.php?id=' . (int) $t['id'] . '&restore=1'),
    'regApi' => url('admin/varzybu-sistema.php?id=' . (int) $t['id'] . '&reg=1'),
    'awardsApi' => $awardsUrl ? url('admin/varzybu-sistema.php?id=' . (int) $t['id'] . '&awards=1') : '', 'awardsUrl' => $awardsUrl,
    'awards' => (object) tournament_awards((int) $t['id']),
    'shareUrl' => $shareUrl, 'screenUrl' => $shareUrl ? $shareUrl . '&ekranas=1' : '', 'regUrl' => $regUrl, 'regOpen' => $regOpen,
    'cams' => (object) $cams, 'streamUrl' => $streamUrl,
    'entries' => tournament_new_entries((int) $t['id']),
    'api' => url('admin/varzybu-sistema.php?id=' . (int) $t['id']), 'csrf' => csrf_token(), 'year' => (int) substr($t['starts_on'], 0, 4),
    'date' => $t['starts_on'], 'dateEnd' => $t['ends_on'] ?: $t['starts_on'],
    'popup' => get('langas') && get('cat') !== '' ? (string) get('cat') : null,   // atkabinta rungtis atskirame lange
    'homeClub' => VARZ_HOME_CLUB, 'belts' => $belts, 'members' => $demo || $asSekr ? [] : varz_club_members(), 'state' => varz_state_maps(varz_drop_example_clubs(tournament_state($t))),
    'clubContacts' => tournament_club_contacts((int) $t['id']), 'clubDirectory' => $demo || $asSekr ? [] : varz_club_directory(), 'demo' => $demo, 'sekr' => $asSekr,
    'admin' => (is_admin() && !$preview) || $demo,
    'diplomaApi' => url('admin/varzybu-sistema.php?id=' . (int) $t['id'] . '&diplomai=1'), 'diplomaTpl' => diploma_template(),   // tatamio šalinimas - tik pagrindiniam adminui (ir bandymo nuorodai)
    'deskArea' => max(0, (int) get('tatamis')) ?: null,   // v2.01: ?tatamis=N - šiame įrenginyje atsidaro to tatamio sekretoriato darbastalis
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
<?= tl_script() ?>
</head>
<body<?= theme_body_attrs(varz_ui()['theme'], 'vz' . (get('langas') ? ' vz-popup' : '')) ?>>
<header class="vz-top">
  <div class="vz-top-row">
    <?php if (!$asSekr): ?><a class="vz-back" href="<?= url('admin/varzybos.php') ?>" title="Visos varžybos">←</a><?php endif; ?>
    <a class="vz-brand" href="<?= url($asSekr ? 'admin/varzybu-sistema.php?id=' . (int) $t['id'] . ($preview ? '&kaip=sekretoriatas' : '') : 'admin/varzybos.php') ?>" title="<?= $asSekr ? 'Šios varžybos' : 'Visos varžybos' ?>"><span class="vz-logo" aria-hidden="true"></span><?= e(VARZ_NAME) ?></a>
    <?php if (!$asSekr && !$demo && is_staff()): ?><?= site_switch_html('budo') ?><?php endif; /* #171 */ ?>
    <div class="vz-title">
      <div class="vz-switch">
        <h1 id="t-name"><?= e($t['title']) ?></h1>
        <select id="t-switch" aria-label="Kitos varžybos" title="Pereiti į kitas varžybas"<?= $asSekr ? ' hidden' : '' ?>>
          <?php foreach (tournaments_list(true, $demo) as $o): ?><option value="<?= url('admin/varzybu-sistema.php?id=' . (int) $o['id']) ?>"<?= (int) $o['id'] === (int) $t['id'] ? ' selected' : '' ?>><?= e($o['title']) ?> · <?= e(fmt_date($o['starts_on'])) ?></option><?php endforeach; ?>
          <option value="<?= url('admin/varzybos.php') ?>">☰ Visos varžybos</option>
        </select>
      </div>
      <div class="sub"><?= e($dates) ?><?= $t['location'] ? ' · ' . e($t['location']) : '' ?><?= $t['is_test'] ? ' <span class="vz-test">TEST</span>' : '' ?></div>
    </div>
    <div class="vz-top-right">
      <?= render_lang_switch(tl_lang()) ?>
      <span class="save-state ok" id="save-state" role="status">Išsaugota ✓</span>
      <?= theme_picker_html($asSekr ? 'device' : 'taika', varz_ui()['theme'], 'vz-link') ?>
      <?php if ($sekr): ?><span class="vz-link vz-sekr-who" title="Prisijungta per sekretoriato nuorodą">🧑‍💼 <?= e($me['first_name']) ?></span>
        <a class="vz-link" href="<?= url('irenginys.php?r=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '')) ?>" title="Administratoriaus kodas: šiame įrenginyje įjungti visą valdymą">🔑<span class="hide-sm"> Admin kodas</span></a><?php endif; ?>
      <?php if (!$sekr && ($dc = device_code_current())): ?><span class="vz-link" title="Šis įrenginys prisijungęs su administratoriaus kodu (Nustatymai → Kodas kitam įrenginiui)">🔑<span class="hide-sm"> iki <?= e(date('H:i', strtotime((string) $dc['session_until']))) ?></span></span><?php endif; ?>
      <?php if (!$asSekr && !$demo): ?><button type="button" class="vz-link" id="sekr-toggle" aria-expanded="false" title="Nuoroda sekretoriatui be paskyros">🧑‍💼<span class="hide-sm"> Sekretoriatas</span></button><?php endif; ?>
      <button type="button" class="vz-link" id="hist-toggle" aria-expanded="false" title="Atkurti, kaip buvo anksčiau">⟲<span class="hide-sm"> Istorija</span></button>
      <?php if ($shareUrl): ?><button type="button" class="vz-link" id="share-toggle" aria-expanded="false" title="Transliacija, ekranai, apdovanojimai, registracija">📡<span class="hide-sm"> Transliacija ir registracija</span></button><?php endif; ?>
      <a class="vz-link" href="<?= url('varzybu-rezultatai.php?id=' . (int) $t['id']) ?>" target="_blank" rel="noopener" title="Viešas rezultatų puslapis"><span class="hide-sm">Viešas puslapis </span>↗</a>
    </div>
  </div>
  <!-- Pagrindinė vieta - tvarkaraštis (ten dirba ir sekretoriatas); paruošimas (klubai, komandos, dalyviai, kategorijos) - viename skirtuke „Administracija“ -->
  <nav class="tabs" id="tabs" role="tablist" aria-label="Dalys">
    <button role="tab" id="tab-live" aria-selected="false" aria-controls="v-live" class="tab-live"><span class="live-dot" aria-hidden="true"></span>Tvarkaraštis</button>
    <button role="tab" id="tab-rezultatai" aria-selected="false" aria-controls="v-rezultatai"><span class="tab-n">🏅</span>Rezultatai</button>
    <span class="tabs-sep" aria-hidden="true"></span>
    <button role="tab" id="tab-admin" aria-selected="false"<?= $asSekr ? ' hidden' : '' ?> class="tab-admin" title="Paruošimas: klubai, komandos, dalyviai, kategorijos, tinklai"><span class="tab-n">🗂</span>Administracija</button>
    <button type="button"<?= $asSekr ? ' hidden' : '' ?> class="tab-cfg" id="menu-cfg" aria-expanded="false" title="Meniu: pervadinti, sukeisti vietomis">⚙</button>
  </nav>
  <nav class="tabs tabs-sub" id="tabs-sub" role="tablist" aria-label="Administracija" hidden>
    <button role="tab" id="tab-klubai" aria-selected="false" aria-controls="v-klubai"><span class="tab-n">1</span>Klubai</button>
    <button role="tab" id="tab-komandos" aria-selected="false" aria-controls="v-komandos"><span class="tab-n">2</span>Komandos</button>
    <button role="tab" id="tab-dalyviai" aria-selected="false" aria-controls="v-dalyviai"><span class="tab-n">3</span>Dalyviai</button>
    <button role="tab" id="tab-kategorijos" aria-selected="false" aria-controls="v-kategorijos"><span class="tab-n">4</span>Kategorijos</button>
    <button role="tab" id="tab-tinklai" aria-selected="false" aria-controls="v-tinklai" title="Kiekvienos kategorijos burtų lentelė (medis): kas su kuo kovoja ir kas laimėjo"><span class="tab-n">🤼</span>Tinklai</button>
    <span class="tabs-sub-hint">Paruošimas prieš varžybas. Varžybų dieną dirbama „Tvarkaraštyje“.</span>
  </nav>
</header>
<div class="share-panel menu-panel" id="menu-panel" hidden>
  <div class="sp-block">
    <b>⚙ Meniu</b>
    <p class="muted small">Pervadinkite skirtukus ir sukeiskite juos vietomis (↑ ↓). Galioja visoms varžyboms.</p>
    <div id="menu-list" class="menu-list"></div>
    <div class="sp-row"><button type="button" class="btn btn-primary" id="menu-save">Išsaugoti</button><button type="button" class="btn" id="menu-reset">Numatytasis</button></div>
  </div>
</div>
<div class="share-panel hist-panel" id="hist-panel" hidden>
  <div class="sp-block">
    <b>⟲ Atkurti, kaip buvo anksčiau</b>
    <p class="muted small">Kopija serveryje daroma kas minutę, kai kas nors keičiama. Atkūrus - dabartinė būsena irgi išsaugoma, tad atkūrimą galima atšaukti.
      Vienos rungties klaidą paprasčiau taisyti rungties valdyme: „Atšaukti“ ar „Atstatyti…“.</p>
    <div class="sp-row"><button type="button" class="btn" data-hist-ago="5">Kaip prieš 5 min.</button><button type="button" class="btn" data-hist-ago="15">prieš 15 min.</button><button type="button" class="btn" data-hist-ago="60">prieš 1 val.</button></div>
    <div class="hist-list" id="hist-list"><p class="muted small">Kraunama…</p></div>
  </div>
</div>
<?php if ($preview): ?>
<div class="sekr-preview"><b>👁 Peržiūra: taip šias varžybas mato sekretoriatas</b> <span>be paskyros, tik šios varžybos, be klubo narių duomenų ir bendrų nustatymų</span>
  <a class="btn btn-sm" href="<?= url('admin/varzybu-sistema.php?id=' . (int) $t['id']) ?>">✕ Baigti peržiūrą</a></div>
<?php endif; ?>
<?php if (!$asSekr && !$demo): $sekrTok = varz_sekr_token((int) $t['id']); $sekrUrl = $sekrTok !== '' ? abs_url('varzybos-sekretoriatas.php?k=' . $sekrTok) : ''; ?>
<div class="share-panel" id="sekr-panel"<?= get('sekr') ? '' : ' hidden' ?>>
  <div class="sp-block">
    <?php /* #169: aprašymas - tik užvedus pelę ant pavadinimo */ ?>
    <b title="Atidaręs nuorodą žmogus įrašo savo vardą ir gali vesti šias varžybas: dalyviai, kategorijos, kovos, balai, apdovanojimai. Kitų varžybų, klubo narių duomenų ir karateka.lt valdymo nemato. Išjungus ar pakeitus nuorodą - prieiga baigiasi.">🧑‍💼 Sekretoriato nuoroda (be paskyros)</b>
    <?php if ($sekrUrl): ?>
      <div class="sp-row"><input class="sel sp-url" readonly value="<?= e($sekrUrl) ?>" id="sekr-url" aria-label="Sekretoriato nuoroda" onclick="this.select()"><button type="button" class="btn" data-copy="<?= e($sekrUrl) ?>">Kopijuoti</button></div>
      <form method="post" class="sp-row"><?= csrf_field() ?>
        <button class="btn" name="action" value="sekr_on" type="submit" onclick="return confirm('Sukurti naują nuorodą? Sena nebeveiks.')">↻ Nauja nuoroda</button>
        <button class="btn btn-danger" name="action" value="sekr_off" type="submit">Išjungti</button></form>
    <?php else: ?>
      <form method="post" class="sp-row"><?= csrf_field() ?><button class="btn btn-primary" name="action" value="sekr_on" type="submit">Sukurti sekretoriato nuorodą</button></form>
    <?php endif; ?>
    <div class="sp-row"><a class="btn" href="<?= url('admin/varzybu-sistema.php?id=' . (int) $t['id'] . '&kaip=sekretoriatas') ?>" id="sekr-preview">👁 Peržiūrėti kaip sekretoriatas</a></div>
  </div>
  <?php /* v2.01: sekretoriato darbastalis vienam tatamiui - atidaryti čia arba nuoroda tatamiui (mygtukus įdeda assets/varzybos/views/desk.js) */ ?>
  <div class="sp-block">
    <b title="Vienas tatamis: visos jo grupės su laikais, lentelės, kovos ir Live kova. Kitus tatamius galima pažiūrėti „🏟 Visi tatamiai“.">📋 Sekretoriato darbastalis</b>
    <div class="sp-row"><span class="small muted">Atidaryti šiame kompiuteryje:</span><span class="sekr-desk" id="sekr-desk"></span></div>
    <?php if ($sekrUrl): ?><div class="sp-row"><span class="small muted">Kopijuoti nuorodą tatamiui (atsidarys iškart jo darbastalis):</span><span class="sekr-desk" id="sekr-links" data-url="<?= e($sekrUrl) ?>"></span></div><?php endif; ?>
  </div>
</div>
<script>
(function () {
  var b = document.getElementById('sekr-toggle'), p = document.getElementById('sekr-panel'); if (!b || !p) return;
  b.setAttribute('aria-expanded', p.hidden ? 'false' : 'true');
  b.addEventListener('click', function () { p.hidden = !p.hidden; b.setAttribute('aria-expanded', p.hidden ? 'false' : 'true'); });
  p.addEventListener('click', function (e) {
    var c = e.target.closest('[data-copy]'); if (!c) return;
    var done = function () { var o = c.textContent; c.textContent = 'Nukopijuota ✓'; setTimeout(function () { c.textContent = o; }, 1500); };
    if (navigator.clipboard) navigator.clipboard.writeText(c.dataset.copy).then(done, function () { document.getElementById('sekr-url').select(); });
    else { document.getElementById('sekr-url').select(); document.execCommand('copy'); done(); }
  });
})();
</script>
<?php endif; ?>
<div class="share-panel" id="share-panel" hidden>
  <div class="sp-block">
    <b>📺 Ekranai salėje</b>
    <p class="muted small">Atidarykite tatamio ekraną antrame monitoriuje ar televizoriuje (naršyklėje, F11 - visas ekranas). Rodoma kova, balai, laikrodis ir kas eina toliau.</p>
    <div class="sp-row" id="sp-screens"></div>
  </div>
  <div class="sp-block">
    <b>🎥 Transliacija telefonais</b>
    <p class="muted small">Telefonai prisijungia per QR kodą ir filmuoja tatamius. Vaizdą su balais mato žiūrovai („📹 Žiūrėti gyvai“) ir sekretoriatas kovoje („📹 Vaizdas“).</p>
    <?php if ($cams): ?><div class="sp-row"><a class="btn btn-primary" href="<?= e($streamUrl) ?>">🎥 Prijungti telefonus →</a></div><?php endif; ?>
  </div>
  <?php if ($awardsUrl): ?>
  <div class="sp-block">
    <b>🏅 Apdovanojimų planšetė</b>
    <p class="muted small">Atidarykite planšetėje prie apdovanojimų. Amžiaus grupė užsidega žaliai, kai visos jos rungtys (ir kata, ir kumite) baigtos ir išsiųstos.
      Apdovanojęs žmogus paspaudžia „Užbaigti“. Nuoroda slapta - duokite tik apdovanojimus vedančiam.</p>
    <div class="sp-row"><input class="sel sp-url" readonly value="<?= e($awardsUrl) ?>" aria-label="Apdovanojimų nuoroda"><button type="button" class="btn" data-copy="<?= e($awardsUrl) ?>">Kopijuoti</button><a class="btn" href="<?= e($awardsUrl) ?>" target="_blank" rel="noopener">Atidaryti ↗</a></div>
  </div>
  <?php endif; ?>
  <div class="sp-block">
    <b>🎥 YouTube transliacija</b>
    <p class="muted small">Įklijuokite YouTube vaizdo įrašo (ar suplanuotos transliacijos) arba kanalo nuorodą ir datą. Žiūrovų puslapyje bus vaizdas, o iki pradžios - „Transliacija prasidės …“.</p>
    <div class="sp-row"><input class="sel sp-url" id="stream-url" type="url" placeholder="https://youtube.com/live/… arba kanalo nuoroda" value="<?= e((string) ($t['stream_url'] ?? '')) ?>" aria-label="YouTube nuoroda">
      <input class="sel" id="stream-at" type="datetime-local" value="<?= !empty($t['stream_at']) ? e(str_replace(' ', 'T', substr((string) $t['stream_at'], 0, 16))) : '' ?>" aria-label="Transliacijos pradžia">
      <button type="button" class="btn btn-primary" id="stream-save">Išsaugoti</button></div>
  </div>
  <div class="sp-block">
    <b>🔗 Nuoroda žiūrovams</b>
    <p class="muted small">Tėvai ir kiti klubai telefone mato, kas vyksta ant kiekvieno tatamio, kas eina toliau ir rezultatus.</p>
    <div class="sp-row"><input class="sel sp-url" readonly value="<?= e($shareUrl) ?>" aria-label="Nuoroda žiūrovams"><button type="button" class="btn" data-copy="<?= e($shareUrl) ?>">Kopijuoti</button><a class="btn" href="<?= e($shareUrl) ?>" target="_blank" rel="noopener">Atidaryti ↗</a></div>
  </div>
  <div class="sp-block">
    <b>📝 Registracija klubams</b>
    <p class="muted small">Kitų klubų treneriai ir Karatekos tėvai užpildo: klubas, vardas, pavardė, gimimo metai, lytis, diržas, telefonas. Jūs matote juos skirtuke „Dalyviai“ ir pridedate vienu mygtuku.</p>
    <div class="sp-row"><label class="small"><input type="checkbox" id="reg-open"<?= $regOpen ? ' checked' : '' ?>> Registracija atidaryta</label></div>
    <div class="sp-row"><input class="sel sp-url" readonly value="<?= e($regUrl) ?>" aria-label="Registracijos nuoroda"><button type="button" class="btn" data-copy="<?= e($regUrl) ?>">Kopijuoti</button><a class="btn" href="<?= e($regUrl) ?>" target="_blank" rel="noopener">Atidaryti ↗</a></div>
  </div>
</div>
<div class="notice" id="conflict" hidden><p>Kitas žmogus ką tik pakeitė šias varžybas. Kad neperrašytumėte jo pakeitimų, <a href="">perkraukite puslapį</a>.</p></div>

<main>
<?php // B3: patvirtinimai („Varžybos sukurtos“, „Nuoroda sukurta“) - rodomi ir čia (anksčiau dingdavo)
foreach (take_flashes() as $f): ?>
  <div class="vz-flash vz-flash-<?= e($f['type']) ?>" role="status"><span><?= e(t($f['message'])) ?></span><button type="button" class="vz-flash-x" aria-label="Uždaryti" onclick="this.parentNode.remove()">✕</button></div>
<?php endforeach; ?>
  <!-- DALYVIAI -->
  <section id="v-dalyviai" role="tabpanel" aria-labelledby="tab-dalyviai" hidden>
    <div class="toolbar">
      <button class="btn btn-primary" id="ppl-members" type="button"<?= $demo || $asSekr ? ' hidden' : '' ?>>+ Karateka nariai</button>
      <button class="btn btn-test" id="ppl-test100" type="button"<?= $asSekr ? ' hidden' : '' ?> title="Išgalvoti dalyviai iš kitų klubų - patikrinti, kaip veikia su daug dalyvių">🧪 +100 testinių</button>
      <button class="btn btn-xs" id="ppl-test-del" type="button" hidden>Ištrinti testinius</button>
      <input type="search" class="sel" id="ppl-search" placeholder="Ieškoti ar pridėti dalyvį" aria-label="Ieškoti ar pridėti dalyvį">
      <select class="sel" id="ppl-club" aria-label="Klubas"></select>
      <label class="small"><input type="checkbox" id="ppl-nocat"> Tik be kategorijos</label>
      <span class="spacer"></span>
      <span class="add-group" role="group" aria-label="Naujas dalyvis"><span class="add-label">Naujas:</span>
      <input class="sel" id="ppl-new-name" placeholder="Vardas Pavardė" aria-label="Naujo dalyvio vardas">
      <select class="sel" id="ppl-new-club" aria-label="Naujo dalyvio klubas"></select>
      <button class="btn" id="ppl-add" type="button">+ Pridėti</button></span>
    </div>
    <div class="panel entries-box" id="entries-box" hidden></div>
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
      <a class="btn" id="clubs-xlsx" href="<?= url('admin/varzybu-sistema.php?id=' . (int) $t['id'] . '&kontaktai=1') ?>" title="Visų klubų kontaktai - Excel failas"><?= icon('download') ?> <span>Kontaktai (Excel)</span></a>
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
    <div class="toolbar cat-views">
      <div class="seg" role="group" aria-label="Vaizdas">
        <button type="button" class="seg-btn" data-catview="ov" aria-pressed="false">▦ Struktūra</button>
        <button type="button" class="seg-btn" data-catview="edit" aria-pressed="false">✎ Viena kategorija</button>
      </div>
      <span class="ov-only">
        <input type="search" class="sel" id="ov-search" placeholder="Ieškoti kategorijos ar dalyvio" aria-label="Ieškoti">
        <span class="seg" role="group" aria-label="Lytis"><button type="button" class="seg-btn on" data-ovg="">Visi</button><button type="button" class="seg-btn" data-ovg="M">M</button><button type="button" class="seg-btn" data-ovg="F">F</button><button type="button" class="seg-btn" data-ovg="X">Mix</button></span>
        <label class="small"><input type="checkbox" id="ov-problems"> Tik su problemomis</label>
        <span class="small muted">Rikiuoti</span>
        <span class="seg" id="ov-sort" role="group" aria-label="Rikiuoti"><button type="button" class="seg-btn on" data-sort="order" title="Kaip sudėliota (galima tempti)">Kaip yra</button><button type="button" class="seg-btn" data-sort="years">Metai</button><button type="button" class="seg-btn" data-sort="belt">Diržas</button><button type="button" class="seg-btn" data-sort="count">Dalyviai</button></span>
        <button type="button" class="btn btn-sm" id="ov-dir" hidden title="Keisti tvarką: didėjančia / mažėjančia"></button>
      </span>
      <span class="spacer"></span>
      <span class="ov-only"><details class="tool-menu" id="ov-tpl"><summary class="btn" title="Amžiaus kategorijų šablonas: sukurti blokus iš karto visoms individualioms rungtims">📋 Šablonas</summary><div class="tool-menu-body tpl-body" id="ov-tpl-body"></div></details>
        <button class="btn" id="ov-auto" type="button" title="Pagal amžių, lytį ir diržą">⚡ Sudaryti automatiškai</button></span>
    </div>
    <div class="ov" id="cat-overview" hidden>
      <p class="ov-sum muted small" id="ov-sum"></p>
      <div class="ov-cols" id="ov-cols"></div>
      <p class="hint">Metus, lytį ir kyu keiskite tiesiai bloke - pavadinimas atsinaujins pats. Tempkite ⋮⋮ - blokas persikels (ir į kitą rungtį).
        Paspaudus dalyvių skaičių matosi dalyviai - juos irgi galima tempti į kitą bloką (su Ctrl - nukopijuoti). „⟳“ - perkelia netinkančius į tinkamus blokus.</p>
    </div>
    <div class="cat-layout" id="cat-edit">
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

  <!-- TINKLAI (Draws): amžiaus grupės → kas su kuo kovoja visose tos grupės rungtyse; žiūrovams tas pats - transliacijos nuorodoje -->
  <section id="v-tinklai" role="tabpanel" aria-labelledby="tab-tinklai" hidden>
    <div class="tk-layout">
      <div class="panel tk-side"><h2>Amžiaus grupės</h2><div class="tk-groups" id="tk-groups"></div></div>
      <div class="tk-view" id="tk-view"></div>
    </div>
  </section>

  <!-- LIVE TVARKARAŠTIS: tatamiai ir rungtys; atidarius rungtį - jos valdymas (lentelė, kovos, balai) -->
  <section id="v-live" role="tabpanel" aria-labelledby="tab-live" hidden>
    <!-- v2.01: sekretoriato darbastalis vienam tatamiui (pasirinktas šiame įrenginyje): kairėje meniu ir kas dabar / toliau,
         dešinėje - visos to tatamio grupės; paspaudus grupę ji išsiskleidžia čia pat (rungties valdymas #run perkeliamas į #dk-run) -->
    <div class="desk" id="desk" hidden>
      <nav class="desk-side" aria-label="Sekretoriatas">
        <label class="dk-who"><span class="dk-kicker">Mano tatamis</span><select class="sel dk-area" id="dk-area" title="Prie kurio tatamio dirbate (šiame įrenginyje)"></select></label>
        <div class="dk-navs">
          <button type="button" class="dk-nav on" data-dk="list" aria-current="page">📋 Mano tatamis</button>
          <button type="button" class="dk-nav" data-dk="live" hidden title="Tatamio telefono vaizdas per visą ekraną: kova, balai ir laikas ant vaizdo">📹 Live kova</button>
          <button type="button" class="dk-nav" data-dk="all" title="Visų tatamių tvarkaraštis - grįšite „📋 Mano tatamis“">🏟 Visi tatamiai</button>
        </div>
        <div class="dk-now" id="dk-now"></div>
      </nav>
      <div class="desk-main">
        <div class="dk-head">
          <div class="dk-title" id="dk-title"></div>
          <label class="dk-start small">Pradžia <input type="time" id="dk-start"></label>
          <input type="search" class="sel dk-q" id="dk-q" placeholder="🔍 Rask dalyvį, klubą ar kategoriją" aria-label="Paieška tvarkaraštyje" autocomplete="off">
          <b class="live-clock" id="dk-clock" title="Dabar"></b>
        </div>
        <div class="sched-found" id="dk-found" hidden></div>
        <ol class="dk-list" id="dk-list"></ol>
        <div class="dk-run" id="dk-run"></div>
        <ol class="dk-list dk-after" id="dk-after"></ol>
        <p class="hint dk-hint">Paspauskite grupę - atsiskleis jos lentelė: paspaudus vardą - laimėjo. „✎ Keisti“ - nutempkite vardą ant kito, jie susikeis vietomis. Grupių eilę keiskite tempdami už ⠿.</p>
      </div>
    </div>
    <div class="live-board" id="live-board">
      <div class="toolbar live-bar">
        <button type="button" class="btn btn-primary" id="desk-back" hidden title="Grįžti į savo tatamio darbastalį">📋 Mano tatamis</button>
        <b class="live-clock" id="live-clock" title="Dabar"></b>
        <input type="search" class="sel sched-q" id="sched-q" placeholder="🔍 Rask dalyvį, klubą ar kategoriją" aria-label="Paieška tvarkaraštyje" autocomplete="off">
        <span class="seg area-seg" id="area-seg" role="group" aria-label="Rodomas tatamis" title="Pasirinkus tatamį - jo darbastalis šiame kompiuteryje (planšetėje); „Visi“ - visų tatamių tvarkaraštis"></span>
        <button type="button" class="btn btn-primary" id="live-cam" hidden title="Tatamio telefono vaizdas per visą ekraną: kova, balai ir laikas ant vaizdo">📹 Live kova</button>
        <span class="muted small" id="live-legend">Paspauskite rungtį - atsidarys jos valdymas. Tempkite rungtis tarp tatamių.</span>
        <span class="spacer"></span>
        <button class="btn" id="auto-balance" type="button">Paskirstyti tolygiai</button>
        <details class="live-settings">
          <summary class="btn">⚙ Tatamiai ir kovų trukmė</summary>
      <div class="toolbar settings">
      <button class="btn btn-primary" id="area-new" type="button">+ Tatamis</button>
      <span class="muted small">Kovos trukmė, min:</span>
      <label class="small"><span class="disc d-kata"></span>Kata <input type="number" min="1" max="15" step="0.5" id="m-kata"></label>
      <label class="small"><span class="disc d-kumite"></span>Kumite <input type="number" min="1" max="15" step="0.5" id="m-kumite"></label>
      <label class="small"><span class="disc d-fukugo"></span>Fukugo <input type="number" min="1" max="15" step="0.5" id="m-fukugo"></label>
      <label class="small"><span class="disc d-kihon"></span>Kihon <input type="number" min="1" max="15" step="0.5" id="m-kihon"></label>
      <label class="small"><span class="disc d-enbu"></span>Enbu <input type="number" min="1" max="15" step="0.5" id="m-enbu"></label>
      <label class="small"><span class="disc d-sumo"></span>Sumo <input type="number" min="1" max="15" step="0.5" id="m-sumo"></label>
      </div>
        </details>
      </div>
      <div class="area-pick" id="area-pick" hidden></div>
      <div class="sched-found" id="sched-found" hidden></div>
      <div class="sched" id="sched"></div>
      <p class="hint">Laikai persiskaičiuoja patys pagal kovų skaičių ir trukmę. Varžybų dieną matosi, ar tatamis vėluoja.</p>
    </div>
    <div class="run" id="run" hidden>
      <div class="run-strip" id="run-strip"></div>
      <div class="run-head">
        <button class="btn" type="button" id="run-back"><?= get('langas') ? '✕ Uždaryti langą' : '← Visas tvarkaraštis' ?></button>
        <div class="run-title"><b id="run-name"></b><span class="muted small" id="run-meta"></span></div>
        <span class="run-move" id="run-move"></span>
        <span class="spacer"></span>
        <div class="seg run-views" role="group" aria-label="Vaizdas"><button type="button" class="seg-btn" data-runview="lines" title="Lentelė su linijomis">Lentelė</button><button type="button" class="seg-btn" data-runview="simple" title="Kas kovoja dabar ir kas ruošiasi">Paprastas</button></div>
        <?php if (!get('langas')): ?><button class="btn" type="button" id="run-pin" title="Prisegti mažą langelį ekrane - matysite ir kituose skirtukuose">📌 Prisegti</button><button class="btn" type="button" id="run-detach" title="Atidaryti atskirame lange (pvz. kitame ekrane); tvarkaraštyje ši rungtis bus pilka">Naujame lange ↗</button><?php else: ?><span id="run-detach" hidden></span><span id="run-pin" hidden></span><?php endif; ?>
        <span class="seg run-nav" role="group" aria-label="Kitos šio tatamio grupės"><button class="seg-btn" type="button" id="run-prev" title="Ankstesnė šio tatamio grupė">← Ankstesnė</button><button class="seg-btn" type="button" id="run-next" title="Kita šio tatamio grupė">Kita →</button></span>
      </div>
      <div class="run-done" id="run-done" hidden></div>
    <div class="toolbar run-tools">
      <label>Kategorija <select class="sel" id="draw-cat"></select></label>
      <label>Sistema <select class="sel" id="draw-system" title="Pagal rungtį: vėliavėlėmis, balais, ratų sistema"></select></label>
      <label>Medaliai <select class="sel" id="draw-medals" title="Kiek trečių vietų ir kaip">
        <option value="plain">2 bronzos be kovos</option>
        <option value="bronze">Kova dėl 3 vietos</option>
        <option value="wkf">Paguoda, 2 bronzos (WKF)</option>
      </select></label>
      <button class="btn" id="draw-swap" type="button" aria-pressed="false" title="Tempkite vardą ant kito - apsikeis vietomis">⇄ Keisti vietas</button>
      <button class="btn" id="draw-new" type="button" title="Naujas burtų traukimas (rezultatai dingsta)">Burti iš naujo</button>
      <button class="btn" id="draw-undo" type="button" title="Atšaukti paskutinį veiksmą">↶ Atšaukti</button>
      <button class="btn" id="draw-lock" type="button" aria-pressed="false" title="Užrakinus burtų ir vietų pakeisti nebegalima">🔓 Užrakinti</button>
      <span class="spacer"></span>
      <?php if ($t['is_test']): ?><button class="btn btn-test" id="draw-sim" type="button" title="Tik test varžybose">🎲 Sužaisti</button><?php endif; ?>
      <button class="btn" id="draw-print" type="button">🖨 Spausdinti</button>
      <details class="tool-menu" id="draw-reset-menu"><summary class="btn" title="Ištrinti rezultatus: visus arba nuo pasirinkto rato">↺ Atstatyti</summary><div class="tool-menu-body" id="draw-reset"></div></details>
    </div>
      <div id="draw-view"></div>
      <div class="run-list panel" id="run-list"></div>
    </div>
  </section>

  <!-- REZULTATAI -->
  <section id="v-rezultatai" role="tabpanel" aria-labelledby="tab-rezultatai" hidden>
    <div class="toolbar">
      <label class="small"><input type="checkbox" id="res-done"> Tik baigtos kategorijos</label>
      <span class="muted small" id="res-count"></span>
      <span class="spacer"></span>
      <?php if ($t['is_test']): ?><button class="btn btn-test" id="res-sim" type="button" title="Tik test varžybose">🎲 Užbaigti visas kovas</button><?php endif; ?>
      <details class="tool-menu" id="dipl-menu"><summary class="btn" title="Diplomai prizininkams: pagal jūsų Excel šabloną arba paprastas dizainas">🎓 Diplomai</summary><div class="tool-menu-body dipl-body" id="dipl-body"></div></details>
      <button class="btn" id="res-csv" type="button"><?= icon('download') ?> <span>CSV (Excel)</span></button>
      <button class="btn btn-primary" id="res-print" type="button">🖨 Spausdinti</button>
    </div>
    <div class="panel aw-panel" id="award-view"></div>
    <div class="res-layout">
      <div class="panel"><h2>Klubų medaliai</h2><div id="medals-view"></div></div>
      <div class="panel"><h2>Prizininkai pagal kategorijas</h2><div id="podium-view"></div></div>
    </div>
    <p class="hint">Taškai klubui: 1 vieta - 3, 2 vieta - 2, 3 vieta - 1. Rezultatai matomi viešame puslapyje ir LTKF svetainėje<?= $t['is_test'] ? '' : '; į narių taškus perkeliami mygtuku „Rezultatai → narių taškai“ varžybų sąraše' ?>.</p>
  </section>
</main>
<script>window.VARZ = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?= asset_module('assets/varzybos/main.js') /* D3: ES moduliai assets/varzybos/ */ ?>
<?= feedback_widget_html() ?><script src="<?= e(asset_url('assets/site.js')) ?>" defer></script><script src="<?= e(asset_url('assets/theme-pick.js')) ?>" defer></script>
</body>
</html>
