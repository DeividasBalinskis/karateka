<?php
// Temos keitimas (assets/theme-pick.js): karateka.lt - adminas, Taika - treneriai (bandymo nuoroda - tik savo naršyklėje)
require __DIR__ . '/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
if (!is_post()) {
    http_response_code(405);
    exit(json_encode(['ok' => false]));
}
csrf_check();
$theme = (string) post('theme');
if (!isset(THEMES[$theme])) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'error' => 'Tokios temos nėra.']));
}
if (post('scope') === 'site') {
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
