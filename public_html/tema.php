<?php
// Temos keitimas (assets/theme-pick.js): karateka.lt - adminas, Taika - treneriai (bandymo nuoroda - tik savo naršyklėje)
require __DIR__ . '/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
if (!is_post()) {
    http_response_code(405);
    exit(json_encode(['ok' => false]));
}
csrf_check();
if (post('action') === 'like') {   // v1.95: „👍 Patinka“ temai ar šriftui (administratoriai)
    $kind = (string) post('kind');
    $id = (string) post('id');
    if (!is_admin() || !in_array($kind, ['theme', 'font'], true) || !($kind === 'theme' ? isset(THEMES[$id]) : isset(FONTS[$id]))) {
        http_response_code(403);
        exit(json_encode(['ok' => false]));
    }
    design_like_set($kind, $id, (int) current_account()['id'], post('on') === '1');
    exit(json_encode(['ok' => true]));
}
$theme = (string) post('theme');
if (!isset(THEMES[$theme])) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'error' => 'Tokios temos nėra.']));
}
if (post('scope') === 'me') {
    // Savo tema - išsaugoma paskyrai (prisijungus vėl - tokia pati); adminas gali ir visiems lankytojams
    $a = current_account();
    if (!$a) {
        exit(json_encode(['ok' => true, 'local' => true]));
    }
    try {
        q('UPDATE accounts SET theme = ? WHERE id = ?', [$theme, (int) $a['id']]);
    } catch (PDOException $ex) {
        // dar neimportuotas db/037
    }
    if (post('all') === '1' && is_admin()) {
        set_site_setting('site_theme', $theme);
    }
} elseif (post('scope') === 'site') {
    if (!is_admin()) {
        http_response_code(403);
        exit(json_encode(['ok' => false, 'error' => t('Temą keičia administratorius.')]));
    }
    set_site_setting('site_theme', $theme);
} else {
    if (!is_staff()) {
        exit(json_encode(['ok' => true, 'local' => true]));   // bandantysis - tik savo lange
    }
    varz_ui_save(['theme' => $theme]);
}
exit(json_encode(['ok' => true]));
