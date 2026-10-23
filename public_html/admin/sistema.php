<?php
// Sistemos būklė (tik administratoriui): duomenų bazė, atsarginės kopijos, paštas, varžybų sistema, SMS, serveris
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_admin();

// Atsarginės kopijos atsisiuntimas
if (($name = get('kopija')) !== '') {
    if (!preg_match('/^karateka-[\w-]+\.sql(\.gz)?$/', $name) || !is_file(DB_BACKUP_DIR . '/' . $name)) {
        http_response_code(404);
        exit('Kopija nerasta.');
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize(DB_BACKUP_DIR . '/' . $name));
    readfile(DB_BACKUP_DIR . '/' . $name);
    exit;
}

if (is_post()) {
    csrf_check();
    @set_time_limit(300);
    $action = post('action');
    try {
        if ($action === 'update') {
            $backup = db_backup();
            db_run_update();
            set_site_setting('db_version', site_version());
            flash('ok', 'Duomenų bazė atnaujinta. Prieš tai padaryta kopija: ' . basename($backup) . '.');
        } elseif ($action === 'backup') {
            flash('ok', 'Kopija padaryta: ' . basename(db_backup()) . '.');
        } elseif ($action === 'test_mail') {
            $ok = send_mail($me['email'], 'Bandomasis laiškas – karateka.lt', "Sveiki,\n\nšis laiškas patvirtina, kad svetainės paštas veikia.");
            flash($ok ? 'ok' : 'err', $ok ? 'Bandomasis laiškas išsiųstas į ' . $me['email'] . '. Patikrinkite paštą (ir „Šlamštą“).' : 'Laiško išsiųsti nepavyko. Patikrinkite pašto nustatymus (config.php → mail).');
        }
    } catch (Throwable $ex) {
        flash('err', 'Nepavyko: ' . $ex->getMessage());
    }
    redirect('admin/sistema.php');
}

$migrations = db_migration_status();
$missingCount = count(array_filter($migrations, function ($m) { return $m['missing']; }));
$backups = db_backups(8);
$mail = config('mail') ?: [];

$varzybos = ['state' => 'off', 'text' => 'Neprijungta (config.php → varzybos).'];
if (varzybos_enabled()) {
    try {
        $n = count(varzybos_api('GET', 'tournaments')['tournaments'] ?? []);
        $varzybos = ['state' => 'ok', 'text' => 'Veikia: ' . kiek($n, 'varžybos', 'varžybos', 'varžybų') . ' sistemoje.'];
    } catch (RuntimeException $ex) {
        $varzybos = ['state' => 'err', 'text' => $ex->getMessage()];
    }
}

function state_badge(string $state): string
{
    $map = ['ok' => ['badge-ok', 'Gerai'], 'err' => ['badge-nophoto', 'Reikia dėmesio'], 'off' => ['', 'Neįjungta']];
    [$cls, $label] = $map[$state] ?? $map['off'];
    return '<span class="badge ' . $cls . '">' . $label . '</span>';
}

// Kol DB neatnaujinta, įprastas išdėstymas gali lūžti (meniu skaito naujas lenteles),
// todėl rodomas paprastas puslapis tik su atnaujinimo mygtuku. Pvz. naujame serveryje po db/001-007.
if ($missingCount) {
    $css = url('assets/app.css') . '?v=' . @filemtime(PUBLIC_DIR . '/assets/app.css');
    ?>
<!DOCTYPE html>
<html lang="lt">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Duomenų bazės atnaujinimas — Karateka</title><link rel="stylesheet" href="<?= e($css) ?>"></head>
<body>
<main class="wrap" style="max-width:640px; padding:40px 20px;">
  <?php foreach (take_flashes() as $f): ?>
    <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
  <?php endforeach; ?>
  <div class="panel card">
    <h1 class="styled">Reikia atnaujinti duomenų bazę</h1>
    <p>Svetainės failai (versija <?= e(site_version()) ?>) naujesni nei duomenų bazė: trūksta <?= kiek($missingCount, 'DB pakeitimo', 'DB pakeitimų', 'DB pakeitimų') ?>. Kol neatnaujinta, dalis puslapių rodo klaidą.</p>
    <p>Paspauskite mygtuką – prieš tai automatiškai pasidarys atsarginė kopija. phpMyAdmin nereikia.</p>
    <form method="post" style="margin-top:14px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <button class="btn btn-primary" type="submit">Atnaujinti duomenų bazę</button>
    </form>
    <details style="margin-top:14px;">
      <summary class="summary-link">Ko trūksta</summary>
      <ul class="small" style="margin-top:8px;">
        <?php foreach ($migrations as $m): if (!$m['missing']) { continue; } ?>
          <li><code><?= e($m['file']) ?></code>: <?= e(implode(', ', array_slice($m['missing'], 0, 4))) ?><?= count($m['missing']) > 4 ? '…' : '' ?></li>
        <?php endforeach; ?>
      </ul>
    </details>
  </div>
</main>
</body>
</html>
    <?php
    exit;
}

page_start('Sistemos būklė', ['admin' => true]);
?>
<div class="page-head">
  <h1 class="styled">Sistemos būklė</h1>
  <p class="muted" style="margin:0;">Versija <?= e(site_version()) ?> · <?= e(config('env') === 'local' ? 'kompiuteris' : (string) config('site_url')) ?></p>
</div>

<div class="panel card">
  <div class="row between" style="flex-wrap:wrap; gap:10px;">
    <h2 style="margin:0;">Duomenų bazė <?= state_badge($missingCount ? 'err' : 'ok') ?></h2>
    <form method="post" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <button class="btn <?= $missingCount ? 'btn-primary' : 'btn-ghost' ?>" type="submit">Atnaujinti duomenų bazę</button>
    </form>
  </div>
  <?php if (!$migrations): ?>
    <p class="muted">Nerastas atnaujinimo failas (app/db-atnaujinimas.sql) – įkelkite visą svetainės zip.</p>
  <?php elseif ($missingCount): ?>
    <p>Trūksta <?= kiek($missingCount, 'DB pakeitimo', 'DB pakeitimų', 'DB pakeitimų') ?>. Spauskite „Atnaujinti duomenų bazę“ – prieš tai automatiškai pasidarys atsarginė kopija. phpMyAdmin nereikia.</p>
  <?php else: ?>
    <p class="muted">Visi DB pakeitimai įkelti. Mygtuką spausti galima ir dabar – kas jau yra, praleidžiama.</p>
  <?php endif; ?>
  <?php if ($migrations): ?>
  <details<?= $missingCount ? ' open' : '' ?>>
    <summary class="summary-link">DB failai (<?= count($migrations) ?>)</summary>
    <table class="table" style="margin-top:8px;">
      <thead><tr><th>Failas</th><th>Būsena</th></tr></thead>
      <tbody>
      <?php foreach ($migrations as $m): ?>
        <tr><td><code><?= e($m['file']) ?></code></td>
          <td><?= $m['missing'] ? '<span class="badge badge-nophoto">Trūksta: ' . e(implode(', ', array_slice($m['missing'], 0, 4))) . (count($m['missing']) > 4 ? '…' : '') . '</span>' : ($m['checks'] ? '<span class="badge badge-ok">✓ įkelta</span>' : '<span class="badge">tik duomenys</span>') ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </details>
  <?php endif; ?>
</div>

<div class="panel card">
  <div class="row between" style="flex-wrap:wrap; gap:10px;">
    <h2 style="margin:0;">Atsarginės kopijos</h2>
    <form method="post" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="backup">
      <button class="btn btn-ghost" type="submit">Padaryti kopiją dabar</button>
    </form>
  </div>
  <?php if ($backups): ?>
    <ul style="list-style:none; padding:0; margin:10px 0 0; display:grid; gap:4px;">
      <?php foreach ($backups as $b): ?>
        <li><a href="?kopija=<?= e(urlencode($b['name'])) ?>"><?= e(date('Y-m-d H:i', $b['time'])) ?></a> <span class="muted small">· <?= e(number_format($b['size'] / 1024, 0, ',', ' ')) ?> KB</span></li>
      <?php endforeach; ?>
    </ul>
    <p class="small muted">Kopija – visa duomenų bazė viename faile. Atkurti: phpMyAdmin → Import. Laikykite ir savo kompiuteryje.</p>
  <?php else: ?>
    <p class="muted">Kopijų dar nėra. Kopija pasidaro automatiškai prieš kiekvieną DB atnaujinimą.</p>
  <?php endif; ?>
</div>

<div class="grid-2">
  <div class="panel card">
    <h2>Paštas <?= state_badge(!empty($mail['host']) ? 'ok' : 'off') ?></h2>
    <p class="small muted">Siuntėjas: <?= e($mail['from'] ?? '—') ?> · serveris: <?= e($mail['host'] ?? '—') ?></p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="test_mail">
      <button class="btn btn-ghost btn-sm" type="submit">Siųsti bandomąjį laišką man</button></form>
  </div>
  <div class="panel card">
    <h2>Varžybų sistema <?= state_badge($varzybos['state']) ?></h2>
    <p class="small muted"><?= e($varzybos['text']) ?></p>
  </div>
  <div class="panel card">
    <h2>SMS <?= state_badge(sms_live() ? 'ok' : 'off') ?></h2>
    <p class="small muted"><?= sms_live() ? 'Tikros SMS siunčiamos.' : 'Bandomasis režimas: SMS tik įrašomos į istoriją.' ?></p>
  </div>
  <div class="panel card">
    <h2>Serveris</h2>
    <p class="small muted">PHP <?= e(PHP_VERSION) ?> · laiko riba <?= (int) ini_get('max_execution_time') ?> s · failai iki <?= e(ini_get('upload_max_filesize')) ?> · suspaudimas <?= function_exists('gzopen') ? 'yra' : 'nėra' ?> · cURL <?= function_exists('curl_init') ? 'yra' : 'nėra' ?></p>
  </div>
</div>
<?php
page_end();
