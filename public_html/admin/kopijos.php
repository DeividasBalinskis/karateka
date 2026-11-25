<?php
// Atsarginės kopijos ir sistemos būsena (tik adminas): kopijų sąrašas, atsisiuntimas, „Sukurti dabar“,
// versija, kurie db failai importuoti. Kopijos daromos automatiškai kartą per dieną (app/backup.php).
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_can('backups');

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

// C10: nuotraukų ir failų kopija (uploads be DB kopijų) - vienas .zip atsisiųsti į kompiuterį
if (get('failai') === '1') {
    if (!class_exists('ZipArchive')) {
        flash('err', t('Serveryje nėra ZIP palaikymo - nuotraukas atsisiųskite per hostingo failų tvarkyklę (aplankas uploads).'));
        redirect('admin/kopijos.php');
    }
    @set_time_limit(600);
    $tmp = tempnam(sys_get_temp_dir(), 'upl');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $root = PUBLIC_DIR . '/uploads';
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $f) {
        $rel = substr($f->getPathname(), strlen($root) + 1);
        if (strpos($rel, 'kopijos/') === 0 || $f->getFilename()[0] === '.') {
            continue;   // DB kopijos atsisiunčiamos atskirai
        }
        $zip->addFile($f->getPathname(), 'uploads/' . $rel);
        $zip->setCompressionName('uploads/' . $rel, ZipArchive::CM_STORE);   // nuotraukos jau suspaustos - greičiau
    }
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="karateka-nuotraukos-' . date('Y-m-d') . '.zip"');
    header('Content-Length: ' . filesize($tmp));
    header('Cache-Control: no-store');
    readfile($tmp);
    @unlink($tmp);
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
    if (post('action') === 'dbupdate') {
        try {
            $res = db_update_run();
            flash('ok', $res['files'] ? (t('Duomenų bazė atnaujinta:') . ' ' . implode(', ', $res['files']) . '. ' . t('Prieš tai padaryta kopija:') . ' ' . $res['backup'])
                : t('Duomenų bazė jau atnaujinta - nieko daryti nereikėjo.'));
        } catch (Throwable $ex) {
            error_log('db update failed: ' . $ex->getMessage());
            flash('err', (t('Atnaujinti nepavyko:') . ' ') . $ex->getMessage());
        }
    }
    redirect('admin/kopijos.php');
}

$backups = backup_list();
$pending = db_update_pending();
$last = backup_last_time();
$error = backup_error();
try {
    $dbFiles = q_all('SELECT failas, importuota FROM db_versija ORDER BY failas');
} catch (PDOException $ex) {
    $dbFiles = null;   // db/022 dar neimportuotas
}
$dbSize = (int) q_value('SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.TABLES WHERE table_schema = DATABASE()');

/** uploads dydis (be DB kopijų) */
function uploads_size(): array
{
    $n = 0;
    $bytes = 0;
    $root = PUBLIC_DIR . '/uploads';
    if (is_dir($root)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $f) {
            if (strpos(substr($f->getPathname(), strlen($root) + 1), 'kopijos/') !== 0) {
                $n++;
                $bytes += $f->getSize();
            }
        }
    }
    return ['n' => $n, 'bytes' => $bytes];
}

function size_label(int $bytes): string
{
    return $bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', ' ') . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
}

page_start(t('Atsarginės kopijos'), ['admin' => true]);
?>
<a class="small" href="<?= url('admin/nustatymai.php') ?>"><?= te('← Nustatymai') ?></a>
<h1 class="styled" style="margin-top:8px;"><?= te('Atsarginės kopijos ir sistema') ?></h1>

<?php if ($pending): ?>
  <form method="post" class="panel card db-update-box">
    <?= csrf_field() ?>
    <div>
      <strong><?= te('Reikia atnaujinti duomenų bazę') ?></strong>
      <p class="small"><?= te('Naujai įkeltai versijai trūksta:') ?> <code><?= e(implode(', ', $pending)) ?></code>. <?= te('Prieš atnaujinant automatiškai padaroma atsarginė kopija. phpMyAdmin nereikia.') ?></p>
    </div>
    <button class="btn btn-primary" name="action" value="dbupdate"><?= te('Atnaujinti duomenų bazę') ?></button>
  </form>
<?php endif; ?>

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
    </details>
    <?php $up = uploads_size(); ?>
    <div class="row between" style="gap:8px; flex-wrap:wrap; margin-top:14px; padding-top:12px; border-top:1px dashed rgba(23,20,15,0.12);">
      <span><strong><?= te('Nuotraukos ir failai') ?></strong> <span class="muted small"><?= (int) $up['n'] ?> <?= te('failų') ?> · <?= e(size_label($up['bytes'])) ?></span></span>
      <a class="btn btn-ghost btn-sm" href="<?= url('admin/kopijos.php?failai=1') ?>"><?= te('Atsisiųsti .zip') ?></a>
    </div>
    <p class="small muted"><?= te('Duomenų bazės kopija ir nuotraukų .zip kartu - visa svetainės informacija. Laikykite kompiuteryje, pvz. kartą per mėnesį.') ?></p>
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
