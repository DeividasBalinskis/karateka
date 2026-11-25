<?php
// Duomenų bazė (PDO, tik paruošti užklausos sakiniai)

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config('db');
        $pdo = new PDO(
            "mysql:host={$c['host']};" . (!empty($c['port']) ? "port={$c['port']};" : '') . "dbname={$c['name']};charset=utf8mb4",
            $c['user'],
            $c['pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function q_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function q_value(string $sql, array $params = [])
{
    return q($sql, $params)->fetchColumn();
}

/** INSERT iš masyvo, grąžina naują id */
function insert(string $table, array $row): int
{
    $cols = array_keys($row);
    $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', array_map(fn ($c) => "`$c`", $cols)) . ')'
         . ' VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')';
    q($sql, array_values($row));
    return (int) db()->lastInsertId();
}

/** UPDATE iš masyvo: update('matches', ['status' => 'done'], 'id = ?', [5]) */
function update(string $table, array $row, string $where, array $params = []): void
{
    if (!$row) {
        return;
    }
    $set = implode(', ', array_map(fn ($c) => "`$c` = ?", array_keys($row)));
    q("UPDATE $table SET $set WHERE $where", array_merge(array_values($row), $params));
}

/** Transakcija. Įdėtinės transakcijos tiesiog vykdomos išorinėje. */
function tx(callable $fn)
{
    $pdo = db();
    if ($pdo->inTransaction()) {
        return $fn();
    }
    $pdo->beginTransaction();
    try {
        $r = $fn();
        $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
