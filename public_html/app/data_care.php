<?php
// C10: duomenų priežiūra. Kas trinama automatiškai - app/cleanup.php. Čia - kas GALĖTŲ būti ištrinta:
// sąrašas parodomas pirmiausia (Valdymas → Nustatymai → Duomenų priežiūra), ištrinama tik administratoriui paspaudus ir patvirtinus.

const CARE_KEYS = ['former_members', 'old_pending', 'disabled_accounts', 'orphan_files'];
const CARE_FILE_DIRS = ['news', 'events', 'lessons', 'quiz', 'feedback', 'ltkf', 'diplomai'];   // uploads/kopijos (DB kopijos) - ne čia

/** Ką galima ištrinti: [raktas => ['title', 'why', 'items' => [['id', 'label']], 'count']] */
function care_candidates(?string $only = null): array
{
    $out = [];
    $want = function (string $k) use ($only) { return $only === null || $only === $k; };
    if ($want('former_members')) {
        $rows = q_all('SELECT m.id, CONCAT(m.first_name, " ", m.last_name) AS label FROM members m
                        WHERE m.status = "inactive" AND m.created_at < NOW() - INTERVAL 1 YEAR
                          AND NOT EXISTS (SELECT 1 FROM attendance a WHERE a.member_id = m.id AND a.training_date >= CURDATE() - INTERVAL 1 YEAR)
                        ORDER BY m.last_name, m.first_name');
        $out['former_members'] = ['title' => t('Buvę nariai (neaktyvūs ilgiau nei metus)'),
            'why' => t('Sprendimas: buvusių narių duomenys saugomi 1 metus. Ištrinamas narys su lankomumu, XP, pastabomis ir žinutėmis; egzaminų registras (federacija) lieka.'),
            'items' => $rows];
    }
    if ($want('old_pending')) {
        $rows = q_all('SELECT m.id, CONCAT(m.first_name, " ", m.last_name) AS label FROM members m
                        WHERE m.status = "pending" AND m.created_at < NOW() - INTERVAL 90 DAY ORDER BY m.created_at');
        $out['old_pending'] = ['title' => t('Nepatvirtinti nariai (laukia ilgiau nei 90 dienų)'),
            'why' => t('Užsiregistravo, bet treneris taip ir nepatvirtino. Jei reikia - pirmiau patvirtinkite (Valdymas → Patvirtinimai).'),
            'items' => $rows];
    }
    if ($want('disabled_accounts')) {
        $rows = q_all('SELECT id, CONCAT(first_name, " ", last_name, " (", email, ")") AS label FROM accounts
                        WHERE status = "disabled" AND role <> "admin" AND COALESCE(last_login_at, created_at) < NOW() - INTERVAL 1 YEAR ORDER BY last_name');
        $out['disabled_accounts'] = ['title' => t('Išjungtos paskyros (neprisijungta ilgiau nei metus)'),
            'why' => t('Paskyra išjungta ir nenaudojama. Ištrinama tik paskyra (prisijungimas); nariai lieka.'),
            'items' => $rows];
    }
    if ($want('orphan_files')) {
        $out['orphan_files'] = ['title' => t('Failai be įrašo (nuotraukos ir dokumentai, kurių niekas nerodo)'),
            'why' => t('Liko ištrynus naujieną, pamoką ar renginį. Tikrinama visa duomenų bazė; failai, įkelti per paskutinę parą, neliečiami.'),
            'items' => array_map(function ($f) { return ['id' => $f, 'label' => $f]; }, care_orphan_files())];
    }
    foreach ($out as $k => $v) {
        $out[$k]['count'] = count($v['items']);
    }
    return $out;
}

/** uploads failai, kurių vardas neminimas jokioje duomenų bazės teksto skiltyje (thumb_X - jei minimas X) */
function care_orphan_files(): array
{
    $files = [];
    $root = PUBLIC_DIR . '/uploads';
    foreach (CARE_FILE_DIRS as $d) {
        if (!is_dir("$root/$d")) {
            continue;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$d", FilesystemIterator::SKIP_DOTS)) as $f) {
            $name = $f->getFilename();
            if ($name[0] === '.' || $name === 'index.html' || $f->getMTime() > time() - 86400) {
                continue;
            }
            $files[substr($f->getPathname(), strlen($root) + 1)] = preg_replace('/^thumb_/', '', $name);
        }
    }
    if (!$files) {
        return [];
    }
    // Visų lentelių teksto skiltys - vienas didelis tekstas (klubo bazė nedidelė)
    $hay = '';
    $cols = q_all('SELECT TABLE_NAME AS t, GROUP_CONCAT(CONCAT("`", COLUMN_NAME, "`")) AS c FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE IN ("char", "varchar", "text", "mediumtext", "longtext", "json") GROUP BY TABLE_NAME');
    foreach ($cols as $r) {
        $st = db()->query('SELECT CONCAT_WS(" ", ' . $r['c'] . ') FROM `' . str_replace('`', '', $r['t']) . '`');
        while (($v = $st->fetchColumn()) !== false) {
            $hay .= $v . "\n";
        }
    }
    $orphans = [];
    foreach ($files as $path => $base) {
        if (strpos($hay, $base) === false) {
            $orphans[] = $path;
        }
    }
    sort($orphans);
    return $orphans;
}

/** Ištrinti vieną grupę (sąrašas skaičiuojamas iš naujo - trinama tik tai, kas dabar atitinka). Grąžina kiek ištrinta. */
function care_delete(string $key): int
{
    $c = care_candidates($key)[$key] ?? null;
    if (!$c || !$c['items']) {
        return 0;
    }
    $n = 0;
    foreach ($c['items'] as $it) {
        if ($key === 'orphan_files') {
            $p = PUBLIC_DIR . '/uploads/' . $it['id'];
            if (strpos(realpath($p) ?: '', realpath(PUBLIC_DIR . '/uploads') . '/') === 0 && @unlink($p)) {
                $n++;
            }
        } else {
            try {
                $n += $key === 'disabled_accounts'
                    ? q('DELETE FROM accounts WHERE id = ? AND status = "disabled" AND role <> "admin"', [(int) $it['id']])->rowCount()
                    : q('DELETE FROM members WHERE id = ? AND status IN ("inactive", "pending")', [(int) $it['id']])->rowCount();
            } catch (PDOException $ex) {
                error_log('data care: ' . $key . ' #' . (int) $it['id'] . ' kept - ' . $ex->getMessage());   // susiję įrašai neleidžia - paliekama
            }
        }
    }
    error_log('data care: ' . $key . ' deleted ' . $n . ' by account ' . (int) (current_account()['id'] ?? 0));
    return $n;
}

/** Kas trinama automatiškai (rodoma tame pačiame puslapyje; taisyklės - app/cleanup.php) */
function care_auto_rules(): array
{
    return [
        [t('Nebaigtos registracijos (nepatvirtintas el. paštas ar tėvų sutikimas)'), (int) config('retention.unfinished_days', 30) . ' ' . t('d.')],
        [t('Pranešimai paskyroje, privačios žinutės, SMS žurnalas'), t('1 metai')],
        [t('Kitų klubų registracijos telefonai (Budo)'), t('30 d. po varžybų')],
        [t('Testų rezultatai be paskyros'), t('1 metai')],
        [t('Laiškų siuntimo eilė (su adresais)'), t('90 d.')],
        [t('Prisijungimo kodai / bandymai / nuorodos el. paštu'), t('1 d. / 30 d. / 30 d.')],
        [t('Kodų kitam įrenginiui žurnalas'), t('1 metai')],
        [t('Duomenų bazės atsarginės kopijos'), (int) config('backup.keep', 14) . ' ' . t('d.')],
    ];
}
