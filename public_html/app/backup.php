<?php
// Atsarginės duomenų bazės kopijos. Serveryje nėra cron, todėl kopija daroma automatiškai kartą per dieną -
// po pirmo tos dienos apsilankymo (jau išsiuntus puslapį). Saugoma paskutinės N kopijų (config.php -> backup).
// Kopijos - aplanke, į kurį iš interneto patekti negalima (.htaccess), atsisiųsti gali tik adminas (admin/kopijos.php).

const BACKUP_PATTERN = '/^karateka-[a-z]+-\d{4}-\d{2}-\d{2}-\d{6}-[a-f0-9]{16}\.sql(\.gz)?$/';

function backup_dir(): string
{
    $dir = rtrim((string) (config('backup.dir') ?: PUBLIC_DIR . '/uploads/kopijos'), '/\\');
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    // Jei aplankas svetainės viduje - iš interneto neprieinamas
    if (is_dir($dir) && !is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

/** Automatinės kopijos: numatyta įjungtos test ir oficialioje svetainėje, kompiuteryje - ne */
function backup_auto(): bool
{
    return (bool) config('backup.auto', config('env') !== 'local');
}

/** Kopijos, naujausia pirma: [['file' => ..., 'size' => ..., 'time' => ...], ...] */
function backup_list(): array
{
    $dir = backup_dir();
    $out = [];
    foreach ((array) @scandir($dir) as $f) {
        if (is_string($f) && preg_match(BACKUP_PATTERN, $f)) {
            $out[] = ['file' => $f, 'size' => (int) filesize("$dir/$f"), 'time' => (int) filemtime("$dir/$f")];
        }
    }
    usort($out, function ($a, $b) { return $b['time'] <=> $a['time'] ?: strcmp($b['file'], $a['file']); });
    return $out;
}

/** Visa duomenų bazė į vieną .sql(.gz) failą. Grąžina failo pavadinimą. */
function backup_create(): string
{
    $dir = backup_dir();
    if (!is_dir($dir) || !is_writable($dir)) {
        throw new RuntimeException('Nepavyko įrašyti į aplanką ' . $dir);
    }
    $pdo = db();   // pirma prisijungiama - jei DB nepasiekiama, failas nekuriamas
    $gz = function_exists('gzopen');
    $name = 'karateka-' . preg_replace('/[^a-z]/', '', (string) config('env', 'test')) . '-' . date('Y-m-d-His') . '-' . bin2hex(random_bytes(8)) . '.sql' . ($gz ? '.gz' : '');
    $tmp = "$dir/.$name.tmp";
    $fh = $gz ? gzopen($tmp, 'wb6') : fopen($tmp, 'wb');
    if (!$fh) {
        throw new RuntimeException('Nepavyko sukurti failo ' . $tmp);
    }
    $write = function (string $s) use ($fh, $gz) {
        if (($gz ? gzwrite($fh, $s) : fwrite($fh, $s)) === false) {
            throw new RuntimeException('Nepavyko rašyti (gal baigėsi vieta diske?)');
        }
    };

    try {
        backup_dump($pdo, $write);
    } catch (Throwable $ex) {
        $gz ? gzclose($fh) : fclose($fh);
        @unlink($tmp);
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        throw $ex;
    }
    $gz ? gzclose($fh) : fclose($fh);
    rename($tmp, "$dir/$name");

    // Seniausios ištrinamos (privatumo politika: kopijos saugomos ribotai)
    foreach (array_slice(backup_list(), max(1, (int) config('backup.keep', 14))) as $old) {
        @unlink("$dir/{$old['file']}");
    }
    @file_put_contents("$dir/.paskutine", date('Y-m-d H:i:s'));
    @unlink("$dir/.klaida");
    return $name;
}

/** SQL tekstas visai bazei: kiekvienai lentelei DROP + CREATE + INSERT po 200 eilučių */
function backup_dump(PDO $pdo, callable $write): void
{
    $write("-- karateka.lt duomenų bazės kopija, " . date('Y-m-d H:i:s') . ', versija ' . site_version() . "\n"
        . "-- Atkūrimas: phpMyAdmin -> pasirinkti duomenų bazę -> Import -> šis failas (žr. docs/atsargines-kopijos.md)\n\n"
        . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");
    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
    foreach ($tables as [$table]) {
        $t = '`' . str_replace('`', '``', $table) . '`';
        $create = $pdo->query("SHOW CREATE TABLE $t")->fetch(PDO::FETCH_NUM)[1];
        $write("DROP TABLE IF EXISTS $t;\n$create;\n");
        // Skaičiuojami (GENERATED) stulpeliai nerašomi - juos duomenų bazė atkurs pati
        $cols = [];
        foreach ($pdo->query("SHOW COLUMNS FROM $t")->fetchAll() as $c) {
            // „STORED GENERATED“, „VIRTUAL GENERATED“, senesnėse MariaDB - „PERSISTENT“ (MySQL „DEFAULT_GENERATED“ - paprastas stulpelis)
            if (!preg_match('/\b(VIRTUAL|STORED|PERSISTENT)\b/i', (string) $c['Extra'])) {
                $cols[] = '`' . str_replace('`', '``', $c['Field']) . '`';
            }
        }
        $insert = "INSERT INTO $t (" . implode(', ', $cols) . ") VALUES\n";
        // Eilutės skaitomos po vieną (nebuferizuotai) - atminties užtenka ir didelei lentelei
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $st = $pdo->query('SELECT ' . implode(', ', $cols) . " FROM $t");
        $rows = [];
        while ($row = $st->fetch(PDO::FETCH_NUM)) {
            $rows[] = '(' . implode(',', array_map(function ($v) use ($pdo) { return $v === null ? 'NULL' : $pdo->quote((string) $v); }, $row)) . ')';
            if (count($rows) === 200) {
                $write($insert . implode(",\n", $rows) . ";\n");
                $rows = [];
            }
        }
        $st->closeCursor();
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        if ($rows) {
            $write($insert . implode(",\n", $rows) . ";\n");
        }
        $write("\n");
    }
    $write("SET FOREIGN_KEY_CHECKS = 1;\n-- pabaiga\n");
}

/**
 * Ar kopijų aplankas pasiekiamas iš interneto (turi būti ne). true - pasiekiamas, false - ne, null - nepavyko patikrinti.
 * Tikrinama tik serveryje: kompiuteryje (php -S) .htaccess neveikia.
 */
function backup_dir_public(): ?bool
{
    $dir = realpath(backup_dir());
    $root = realpath(PUBLIC_DIR);
    if (!$dir || !$root) {
        return null;
    }
    if (strpos($dir, $root . DIRECTORY_SEPARATOR) !== 0) {
        return false;   // už svetainės ribų
    }
    if (config('env') === 'local' || !is_file($dir . '/.paskutine')) {
        return null;
    }
    $path = str_replace(DIRECTORY_SEPARATOR, '/', substr($dir, strlen($root) + 1));
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 3, 'ignore_errors' => true], 'ssl' => ['verify_peer' => false]]);
    $body = @file_get_contents(abs_url($path . '/.paskutine'), false, $ctx);
    $status = isset($http_response_header[0]) && preg_match('~\s(\d{3})\s~', $http_response_header[0], $m) ? (int) $m[1] : 0;
    if (!$status) {
        return null;
    }
    return $status === 200 && $body !== false && trim($body) === trim((string) file_get_contents($dir . '/.paskutine'));
}

/** Kada paskutinį kartą pavyko (arba null) */
function backup_last_time(): ?int
{
    $f = backup_dir() . '/.paskutine';
    return is_file($f) ? (int) filemtime($f) : null;
}

/** Paskutinės nepavykusios kopijos klaida (rodoma adminui) */
function backup_error(): ?string
{
    $f = backup_dir() . '/.klaida';
    return is_file($f) ? (string) file_get_contents($f) : null;
}

/** Kartą per dieną - jau išsiuntus puslapį lankytojui */
function maybe_run_backup(): void
{
    if (PHP_SAPI === 'cli' || !backup_auto()) {
        return;
    }
    $dir = rtrim((string) (config('backup.dir') ?: PUBLIC_DIR . '/uploads/kopijos'), '/\\');
    $stamp = @filemtime($dir . '/.paskutine');
    $tried = @filemtime($dir . '/.bandyta');
    if (($stamp && date('Y-m-d', $stamp) === date('Y-m-d')) || ($tried && time() - $tried < 3600)) {
        return;   // šiandien jau padaryta arba neseniai bandyta
    }
    register_shutdown_function(function () {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        $dir = backup_dir();
        $lock = @fopen($dir . '/.bandyta', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return;   // kitas lankytojas jau daro
        }
        touch($dir . '/.bandyta');
        try {
            ignore_user_abort(true);
            @set_time_limit(300);
            backup_create();
        } catch (Throwable $ex) {
            error_log('backup failed: ' . $ex->getMessage());
            @file_put_contents($dir . '/.klaida', date('Y-m-d H:i') . ': ' . $ex->getMessage());
        }
        flock($lock, LOCK_UN);
        fclose($lock);
    });
}
