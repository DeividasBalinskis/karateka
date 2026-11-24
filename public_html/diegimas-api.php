<?php
// Be naršyklės, po automatinio įkėlimo į test svetainę (.github/workflows/ikelti-i-test.yml):
//   GET                          - kokia versija įkelta ir ar trūksta duomenų bazės failų; „👍“ temos ir šriftai
//   POST {"action":"dbupdate"}   - duomenų bazės atnaujinimas (kaip Valdymas → Nustatymai → „Atnaujinti duomenų bazę“; prieš tai - kopija)
// Prieiga - tik su „API prieigos“ raktu (Valdymas → Atsiliepimai), antraštė X-Api-Key. Oficialioje svetainėje rakto nėra - neveikia.
require __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!feedback_ready() || !feedback_api_check((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''))) {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'error' => 'Netinkamas raktas.']));
}
$state = function (): array {
    return ['version' => trim((string) @file_get_contents(APP_DIR . '/VERSION')), 'db_required' => DB_REQUIRED, 'pending' => db_update_pending()];
};
if (is_post()) {
    $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
    if (($in['action'] ?? '') !== 'dbupdate') {
        http_response_code(400);
        exit(json_encode(['ok' => false, 'error' => 'Nežinomas veiksmas.']));
    }
    try {
        $res = db_update_run();
    } catch (Throwable $ex) {
        error_log('db update (api) failed: ' . $ex->getMessage());
        http_response_code(500);
        exit(json_encode(['ok' => false, 'error' => $ex->getMessage()] + $state(), JSON_UNESCAPED_UNICODE));
    }
    exit(json_encode(['ok' => true, 'applied' => $res['files'], 'backup' => $res['backup'] ? basename((string) $res['backup']) : null] + $state(), JSON_UNESCAPED_UNICODE));
}
// v1.95: kurios temos ir šriftai pažymėti „👍 Patinka“ (kas pažymėjo)
exit(json_encode(['ok' => true] + $state() + ['design_likes' => design_likes_named()], JSON_UNESCAPED_UNICODE));
