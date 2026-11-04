<?php
// Atsarginės kopijos ir sistemos būsena (tik adminas): kopijų sąrašas, atsisiuntimas, „Sukurti dabar“,
// versija, kurie db failai importuoti. Kopijos daromos automatiškai kartą per dieną (app/backup.php).
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_admin();

// Atsisiųsti kopiją
if (($file = get('atsisiusti')) !== '') {
    $list = array_column(backup_list(), 'file');
    if (!preg_match(BACKUP_PATTERN, $file) || !in_array($file, $list, true)) {
        http_response_code(404);
        exit(t('Kopija nerasta.'));
    }
    $path = backup_dir() . '/' . $file;
    header('Content-Type: ' . (substr($file, -3) === '.gz' ? 'application/gzip' : 'application/sql'));
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    exit;
}

if (is_post()) {
    csrf_check();
    if (post('action') === 'create') {
        try {
            $name = backup_create();
            flash('ok', (t('Kopija sukurta:') . ' ') . $name);
        } catch (Throwable $ex) {
            error_log('backup failed: ' . $ex->getMessage());
            flash('err', (t('Kopijos sukurti nepavyko:') . ' ') . $ex->getMessage());
        }
    }
    redirect('admin/kopijos.php');
}

$backups = backup_list();
$last = backup_last_time();
$error = backup_error();
try {
    $dbFiles = q_all('SELECT failas, importuota FROM db_versija ORDER BY failas');
} catch (PDOException $ex) {
    $dbFiles = null;   // db/022 dar neimportuotas
}
$dbSize = (int) q_value('SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.TABLES WHERE table_schema = DATABASE()');

function size_label(int $bytes): string
{
    return $bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', ' ') . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
}

page_start(t('Atsarginės kopijos'), ['admin' => true]);
?>
<a class="small" href="<?= url('admin/nustatymai.php') ?>"><?= te('← Nustatymai') ?></a>
<h1 class="styled" style="margin-top:8px;"><?= te('Atsarginės kopijos ir sistema') ?></h1>

<div class="grid-2">
  <div class="panel card">
    <div class="kicker"><?= te('Duomenų bazės kopijos') ?></div>
    <?php if ($error): ?><div class="flash flash-err"><?= te('Paskutinė kopija nepavyko:') ?> <?= e($error) ?></div><?php endif; ?>
    <?php if (backup_dir_public() === true): ?>
      <div class="flash flash-err"><?= te('Kopijų aplankas pasiekiamas iš interneto! Hostingas nepaiso') ?> <code>.htaccess</code>.
        <code>config.php</code> <?= te('įrašykite') ?> <code>'backup' => ['dir' => '/kelias/už/svetainės/ribų']</code>.</div>
    <?php endif; ?>
    <p>
      <?php if (backup_auto()): ?>
        <?= te('Kopija daroma') ?> <strong><?= te('automatiškai kartą per dieną') ?></strong><?= te(', saugomos paskutinės') ?> <?= (int) config('backup.keep', 14) ?>.
      <?php else: ?>
        <?= te('Automatinės kopijos') ?> <strong><?= te('išjungtos') ?></strong> <?= te('(kompiuteryje - numatyta). Įjungti:') ?> <code>config.php</code> → <code>'backup' => ['auto' => true]</code>.
      <?php endif; ?>
    </p>
    <p class="muted small"><?= te('Paskutinė:') ?> <?= $last ? e(fmt_date(date('Y-m-d', $last))) . ', ' . date('H:i', $last) : 'dar nebuvo' ?>.
      Kopijose - visi narių duomenys, todėl atsisiųsti gali tik adminas. Kartą per mėnesį atsisiųskite vieną į kompiuterį (ne į bendrą aplanką).</p>
    <form method="post" style="margin:12px 0 16px;">
      <?= csrf_field() ?>
      <button class="btn btn-primary btn-sm" name="action" value="create"><?= te('Sukurti kopiją dabar') ?></button>
    </form>
    <?php if ($backups): ?>
      <ul class="list backup-list">
        <?php foreach ($backups as $i => $b): ?>
          <li class="row between">
            <span><strong><?= e(fmt_date(date('Y-m-d', $b['time']))) ?></strong> <span class="muted small"><?= date('H:i', $b['time']) ?> · <?= e(size_label($b['size'])) ?></span><?= $i === 0 ? ' <span class="badge">naujausia</span>' : '' ?></span>
            <a class="btn btn-ghost btn-sm" href="<?= url('admin/kopijos.php?atsisiusti=' . rawurlencode($b['file'])) ?>"><?= te('Atsisiųsti') ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="muted"><?= te('Kopijų dar nėra.') ?></p>
    <?php endif; ?>
    <details class="hint-box" style="margin-top:14px;">
      <summary><?= te('Kaip atkurti iš kopijos') ?></summary>
      <ol class="small">
        <li><?= te('Atsisiųskite kopiją (failas') ?> <code>.sql.gz</code>).</li>
        <li><?= te('Hostingo valdyme atidarykite') ?> <strong><?= te('phpMyAdmin') ?></strong> <?= te('→ kairėje pasirinkite svetainės duomenų bazę.') ?></li>
        <li><strong><?= te('Import') ?></strong> <?= te('→ pasirinkite failą →') ?> <strong><?= te('Import') ?></strong>. Visos lentelės pakeičiamos kopijos duomenimis.</li>
      </ol>
      <p class="small muted"><?= te('Nuotraukų (') ?><code>uploads</code><?= te(') kopija čia nedaroma - jas saugo hostingas. Plačiau:') ?> <code>docs/atsargines-kopijos.md</code>.</p>
    </details>
  </div>

  <div class="panel card">
    <div class="kicker"><?= te('Sistema') ?></div>
    <dl class="sys-list">
      <dt><?= te('Svetainės versija') ?></dt><dd><?= e(site_version()) ?> · <?= e((string) config('env')) ?></dd>
      <dt>PHP</dt><dd><?= e(PHP_VERSION) ?></dd>
      <dt><?= te('Duomenų bazė') ?></dt><dd><?= e((string) q_value('SELECT VERSION()')) ?> · <?= e(size_label($dbSize)) ?></dd>
      <dt><?= te('Importuoti db failai') ?></dt>
      <dd>
        <?php if ($dbFiles === null): ?>
          <span class="badge badge-new"><?= te('neimportuotas db/022') ?></span> <?= te('- importuokite trūkstamus failus (žr.') ?> <code>docs/pakeitimai.md</code>).
        <?php else: ?>
          <?= te('iki 021 ir:') ?> <?= e(implode(', ', array_map(function ($r) { return substr($r['failas'], 0, 3); }, $dbFiles))) ?>
          <?php if (!in_array(DB_REQUIRED, array_column($dbFiles, 'failas'), true)): ?>
            <br><span class="badge badge-new"><?= te('trūksta') ?> <?= e(DB_REQUIRED) ?></span>
          <?php endif; ?>
        <?php endif; ?>
      </dd>
    </dl>
    <p class="muted small"><?= te('Prieš keliant naują versiją į oficialią svetainę: pirma į test, patikrinti, tada - į oficialią ir importuoti tuos pačius db failus.') ?></p>
  </div>
</div>

<?php
page_end();
