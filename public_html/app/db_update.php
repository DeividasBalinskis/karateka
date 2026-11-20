<?php
// Duomenų bazės atnaujinimas vienu mygtuku (Nustatymai → Atsarginės kopijos ir sistema), be phpMyAdmin.
// Įkėlimo pakete db/0xx failai yra app/db/ (tools/paruosti-ikelimui.ps1), kompiuteryje - projekto db/ aplanke.
// Vykdomi tik failai nuo 022, kurių dar nėra lentelėje db_versija (jie visi saugūs vykdyti ir antrą kartą).
// Prieš atnaujinant visada padaroma atsarginė kopija.

function db_update_dir(): string
{
    return is_dir(APP_DIR . '/db') ? APP_DIR . '/db' : dirname(PUBLIC_DIR) . '/db';
}

/** Trūkstami db failai (pavadinimai), eilės tvarka */
function db_update_pending(): array
{
    try {
        $done = q('SELECT failas FROM db_versija')->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $ex) {
        $done = [];   // dar nėra db/022 (db_versija lentelės)
    }
    $files = glob(db_update_dir() . '/[0-9][0-9][0-9]_*.sql') ?: [];
    sort($files);
    $out = [];
    foreach ($files as $f) {
        $name = basename($f);
        if ((int) substr($name, 0, 3) >= 22 && !in_array($name, $done, true)) {
            $out[] = $name;
        }
    }
    return $out;
}

/** SQL failas → atskiri sakiniai (kabliataškiai kabutėse ir komentaruose neskaidomi) */
function db_update_statements(string $sql): array
{
    $out = [];
    $cur = '';
    $len = strlen($sql);
    $quote = null;
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        if ($quote !== null) {
            $cur .= $ch;
            if ($ch === '\\' && $quote !== '`' && $i + 1 < $len) {
                $cur .= $sql[++$i];
            } elseif ($ch === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($ch === '-' && ($sql[$i + 1] ?? '') === '-') {
            $end = strpos($sql, "\n", $i);
            $i = $end === false ? $len : $end;
            $cur .= "\n";
            continue;
        }
        if ($ch === '/' && ($sql[$i + 1] ?? '') === '*') {
            $end = strpos($sql, '*/', $i + 2);
            $i = $end === false ? $len : $end + 1;
            continue;
        }
        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
        }
        if ($ch === ';') {
            if (trim($cur) !== '') {
                $out[] = trim($cur);
            }
            $cur = '';
            continue;
        }
        $cur .= $ch;
    }
    if (trim($cur) !== '') {
        $out[] = trim($cur);
    }
    return $out;
}

/**
 * Atnaujina: kopija, tada trūkstami failai iš eilės. Grąžina ['backup' => kopijos failas, 'files' => [importuoti]].
 * Klaidos atveju - RuntimeException su failo pavadinimu (jau importuoti failai lieka importuoti).
 */
function db_update_run(): array
{
    $pending = db_update_pending();
    if (!$pending) {
        return ['backup' => null, 'files' => []];
    }
    $backup = backup_create();
    $done = [];
    @set_time_limit(300);
    foreach ($pending as $name) {
        try {
            foreach (db_update_statements((string) file_get_contents(db_update_dir() . '/' . $name)) as $st) {
                db()->exec($st);
            }
        } catch (Throwable $ex) {
            throw new RuntimeException($name . ': ' . $ex->getMessage());
        }
        $done[] = $name;
    }
    return ['backup' => $backup, 'files' => $done];
}
