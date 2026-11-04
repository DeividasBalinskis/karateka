<?php
// Atsiliepimo siuntimas (mygtukas „💡 Atsiliepimas“ - app/feedback.php). Su JS - atsakymas JSON, be JS - grįžtama į tą patį puslapį.
require __DIR__ . '/app/bootstrap.php';

if (!is_post()) {
    redirect('index.php');
}
csrf_check();
$a = current_account();
$json = stripos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
$page = (string) post('page');
$page = preg_match('~^/[^\s<>"]*$~', $page) ? $page : '';

$result = t('Atsiliepimai dar neįjungti.');
if (feedback_ready() && feedback_mode() !== 'off' && ($a || feedback_mode() === 'all')) {
    // Robotai užpildo paslėptą lauką - jiems apsimetame, kad gavome
    $result = trim((string) post('website')) !== '' ? 0 : feedback_add($a, (string) post('body'), $page, (string) post('name'));
}

if ($json) {
    header('Content-Type: application/json; charset=utf-8');
    if (is_string($result)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => $result], JSON_UNESCAPED_UNICODE);
    } else {
        $item = $result ? q_one('SELECT * FROM feedback WHERE id = ?', [$result]) : null;
        echo json_encode(['ok' => true, 'message' => t('Ačiū! Gavome - pažiūrėsime ir pataisysime.'), 'html' => $item && $a ? feedback_mine_item($item) : ''], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
flash(is_string($result) ? 'err' : 'ok', is_string($result) ? $result : t('Ačiū! Gavome - pažiūrėsime ir pataisysime.'));
redirect($page !== '' ? ltrim($page, '/') : 'index.php');
