<?php
// Sistemos būklė: ar duomenų bazė atnaujinta, atsarginės kopijos ir DB atnaujinimas vienu mygtuku.
// app/db-atnaujinimas.sql - visi db/008... failai vienu, saugiu kartoti failu (generuoja tools/atnaujinimo-sql.py).

const DB_UPDATE_FILE = APP_DIR . '/db-atnaujinimas.sql';
const DB_BACKUP_DIR = APP_DIR . '/backups';

/**
 * Kiekvienas DB failas iš atnaujinimo failo ir ar jo lentelės bei stulpeliai jau yra duomenų bazėje.
 * [['file' => '020_...sql', 'missing' => ['members.photo_events', ...], 'checks' => 4], ...]
 */
function db_migration_status(): array
{
    if (!is_file(DB_UPDATE_FILE)) {
        return [];
    }
    $sql = (string) file_get_contents(DB_UPDATE_FILE);
    $parts = preg_split('/^-- ===== (\S+) =====$/m', $sql, -1, PREG_SPLIT_DELIM_CAPTURE);
    $tables = array_flip(q('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchAll(PDO::FETCH_COLUMN));
    $columns = [];
    foreach (q_all('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()') as $c) {
        $columns[$c['TABLE_NAME'] . '.' . $c['COLUMN_NAME']] = true;
    }
    $out = [];
    for ($i = 1; $i < count($parts); $i += 2) {
        $body = $parts[$i + 1] ?? '';
        $missing = [];
        $missingTables = [];
        $checks = 0;
        preg_match_all('/CREATE TABLE IF NOT EXISTS `?(\w+)`?/', $body, $m);
        foreach (array_unique($m[1]) as $t) {
            $checks++;
            if (!isset($tables[$t])) {
                $missing[] = $t;
                $missingTables[$t] = true;
            }
        }
        preg_match_all("/TABLE_NAME = '(\\w+)' AND COLUMN_NAME = '(\\w+)'/", $body, $m, PREG_SET_ORDER);
        foreach ($m as $c) {
            $checks++;
            if (!isset($columns[$c[1] . '.' . $c[2]]) && !isset($missingTables[$c[1]])) {
                $missing[] = $c[1] . '.' . $c[2];
            }
        }
        $out[] = ['file' => $parts[$i], 'missing' => $missing, 'checks' => $checks];
    }
    return $out;
}

/** Ar trūksta bent vieno DB pakeitimo (rodoma trenerio „Šiandien“ puslapyje) */
function db_needs_update(): bool
{
    static $needs = null;
    if ($needs === null) {
        try {
            $needs = (bool) array_filter(db_migration_status(), function ($m) { return $m['missing']; });
        } catch (Throwable $ex) {
            $needs = false;
        }
    }
    return $needs;
}

/** Visos duomenų bazės kopija į app/backups (suspausta). Grąžina failo kelią. */
function db_backup(): string
{
    if (!is_dir(DB_BACKUP_DIR) && !@mkdir(DB_BACKUP_DIR, 0750, true)) {
        throw new RuntimeException('Nepavyko sukurti aplanko atsarginėms kopijoms.');
    }
    if (!is_file(DB_BACKUP_DIR . '/.htaccess')) {
        file_put_contents(DB_BACKUP_DIR . '/.htaccess', "Require all denied\n");
    }
    $gz = function_exists('gzopen');
    $file = DB_BACKUP_DIR . '/karateka-' . date('Y-m-d-His') . '-' . bin2hex(random_bytes(4)) . ($gz ? '.sql.gz' : '.sql');
    $h = $gz ? gzopen($file, 'wb6') : fopen($file, 'wb');
    if (!$h) {
        throw new RuntimeException('Nepavyko įrašyti atsarginės kopijos.');
    }
    $write = function (string $s) use ($h, $gz) { $gz ? gzwrite($h, $s) : fwrite($h, $s); };
    $pdo = db();
    $write("-- karateka.lt duomenų bazės kopija, " . date('Y-m-d H:i:s') . ", versija " . site_version() . "\n"
        . "-- Atkurti: phpMyAdmin -> duomenų bazė -> Import (šį failą).\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
    foreach (q("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as [$table]) {
        $create = q('SHOW CREATE TABLE `' . str_replace('`', '', $table) . '`')->fetch(PDO::FETCH_NUM)[1];
        $write("DROP TABLE IF EXISTS `$table`;\n$create;\n");
        // Skaičiuojami (generated) stulpeliai neįrašomi - DB juos apskaičiuoja pati
        $cols = q("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                   AND EXTRA NOT LIKE '%GENERATED%' AND EXTRA NOT IN ('VIRTUAL', 'PERSISTENT', 'STORED') ORDER BY ORDINAL_POSITION", [$table])->fetchAll(PDO::FETCH_COLUMN);
        $colList = implode(', ', array_map(function ($c) { return '`' . str_replace('`', '', $c) . '`'; }, $cols));
        $st = $pdo->query('SELECT ' . $colList . ' FROM `' . str_replace('`', '', $table) . '`');
        $insert = "INSERT INTO `$table` ($colList) VALUES\n";
        $batch = [];
        while ($row = $st->fetch(PDO::FETCH_NUM)) {
            $batch[] = '(' . implode(',', array_map(function ($v) use ($pdo) { return $v === null ? 'NULL' : $pdo->quote((string) $v); }, $row)) . ')';
            if (count($batch) >= 200) {
                $write($insert . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }
        if ($batch) {
            $write($insert . implode(",\n", $batch) . ";\n");
        }
        $write("\n");
    }
    $write("SET FOREIGN_KEY_CHECKS = 1;\n");
    $gz ? gzclose($h) : fclose($h);
    return $file;
}

/** Paskutinės atsarginės kopijos (naujausia pirma) */
function db_backups(int $limit = 10): array
{
    $files = glob(DB_BACKUP_DIR . '/karateka-*.sql*') ?: [];
    rsort($files);
    return array_slice(array_map(function ($f) { return ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)]; }, $files), 0, $limit);
}

/** Paleidžia atnaujinimo failą (saugu kartoti: kas jau yra - praleidžiama) */
function db_run_update(): void
{
    $sql = (string) @file_get_contents(DB_UPDATE_FILE);
    if ($sql === '') {
        throw new RuntimeException('Nerastas atnaujinimo failas app/db-atnaujinimas.sql.');
    }
    $pdo = db();
    // Daug komandų vienu kartu veikia tik su emuliuojamais paruoštais sakiniais
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    try {
        $st = $pdo->query($sql);
        while ($st->nextRowset()) {
        }
        $st->closeCursor();
    } finally {
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }
}
