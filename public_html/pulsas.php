<?php
// C7: viena bendra užklausa kas 30 s (assets/pulse.js) vietoj kelių atskirų: neperskaitytų žinučių skaičius (💬),
// iššokantys pranešimai treneriams, kvietimai žaisti. ETag: jei niekas nepasikeitė - 304 be turinio.
require __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-cache');
$a = current_account();
if (!$a || !is_active_account($a)) {
    http_response_code(403);
    exit(json_encode(['ok' => false]));
}
$out = ['ok' => true];
if (empty($a['chat_off'])) {
    try {
        $out['chat'] = chat_unread_count($a);
    } catch (PDOException $ex) {
        // dar neimportuotas db/022
    }
}
if (is_staff($a)) {
    $out['ntf'] = notifications_popup((int) $a['id']);
}
require_once APP_DIR . '/game.php';
if (game_nav_ready()) {
    $out['inv'] = game_invites_list(game_my_heroes($a));
}
$json = json_encode($out, JSON_UNESCAPED_UNICODE);
$etag = '"' . md5($json) . '"';
header('ETag: ' . $etag);
if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}
echo $json;
