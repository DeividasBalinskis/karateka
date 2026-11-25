<?php
// v2.02: Valdymo meniu išdėstymas - išsaugo administratoriaus „✎“ pakeitimus (assets/admin-menu.js).
// Grupės (savo pavadinimai, tvarka), skyrių tvarka ir savi skyrių pavadinimai → site_setting admin_menu. Tuščias = numatytas meniu.
require dirname(__DIR__) . '/app/bootstrap.php';
require_can('settings');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!is_post()) {
    http_response_code(405);
    exit(json_encode(['ok' => false]));
}
csrf_check();
$in = json_decode((string) file_get_contents('php://input'), true) ?: [];

if (!empty($in['reset'])) {
    set_site_setting('admin_menu', '');
    exit(json_encode(['ok' => true]));
}

// Leidžiami tik žinomi skyriai (ir tie, kurių šiuo metu nesimato - pvz. LTKF be nuorodos)
$known = array_flip(['siandien', 'treniruote', 'egzaminai', 'renginiai', 'nariai', 'patvirtinimai', 'atsiliepimai', 'perziura', 'pateisinimai', 'pranesimai', 'xp', 'testai', 'federacija', 'ltkf', 'nuorodos', 'nustatymai']);
$clean = function ($v): string {
    return mb_substr(trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $v))), 0, 30);
};
$groups = [];
$seen = [];
$ids = [];
foreach (array_slice((array) ($in['groups'] ?? []), 0, 12) as $g) {
    if (!is_array($g)) {
        continue;
    }
    $id = (string) ($g['id'] ?? '');
    if (!preg_match('/^[a-z0-9-]{1,24}$/', $id) || isset($ids[$id])) {
        $id = 'g' . substr(bin2hex(random_bytes(4)), 0, 6);
    }
    $ids[$id] = true;
    $items = [];
    foreach ((array) ($g['items'] ?? []) as $k) {
        $k = (string) $k;
        if (isset($known[$k]) && !isset($seen[$k])) {
            $items[] = $k;
            $seen[$k] = true;
        }
    }
    $groups[] = ['id' => $id, 'name' => $clean($g['name'] ?? ''), 'items' => $items];
}
if (!$groups) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'error' => t('Reikia bent vienos grupės.')], JSON_UNESCAPED_UNICODE));
}
// Naujos grupės be pavadinimo - „Grupė“; numatytos (kasdien / darbai / nustatymai) be pavadinimo - verčiamas numatytas
foreach ($groups as &$g) {
    if ($g['name'] === '' && admin_menu_group_default_name($g['id']) === '') {
        $g['name'] = t('Grupė');
    }
}
unset($g);
$names = [];
foreach ((array) ($in['names'] ?? []) as $k => $v) {
    $v = $clean($v);
    if (isset($known[(string) $k]) && $v !== '') {
        $names[(string) $k] = $v;
    }
}
set_site_setting('admin_menu', json_encode(['groups' => $groups, 'names' => (object) $names], JSON_UNESCAPED_UNICODE));
exit(json_encode(['ok' => true]));
