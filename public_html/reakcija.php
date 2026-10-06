<?php
// Reakcija į naujieną: viena žmogui, tą pačią paspaudus dar kartą - nuimama
require __DIR__ . '/app/bootstrap.php';

$json = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
$newsId = (int) post('news_id');
$back = 'naujienos.php?id=' . $newsId;

function respond(bool $json, array $data, string $back): void
{
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    } else {
        if (!empty($data['error'])) {
            flash('err', $data['error']);
        }
        redirect(!empty($data['login']) ? $data['login'] : $back);
    }
    exit;
}

if (!is_post()) {
    redirect('naujienos.php');
}
csrf_check();

$a = current_account();
if (!$a) {
    respond($json, ['login' => url('prisijungti.php?r=' . rawurlencode(url($back)))], $back);
}
if (!is_active_account($a)) {
    respond($json, ['error' => 'Reaguoti galės patvirtinti nariai - jūsų paskyra dar laukia trenerio patvirtinimo.'], $back);
}

$emoji = post('emoji');
if (!in_array($emoji, REACTIONS, true) || !q_value('SELECT 1 FROM news WHERE id = ? AND is_published = 1', [$newsId])) {
    respond($json, ['error' => 'Netinkama reakcija.'], $back);
}

$current = q_value('SELECT emoji FROM reactions WHERE news_id = ? AND account_id = ?', [$newsId, $a['id']]);
if ($current === $emoji) {
    q('DELETE FROM reactions WHERE news_id = ? AND account_id = ?', [$newsId, $a['id']]);
    $mine = null;
} else {
    q('INSERT INTO reactions (news_id, account_id, emoji) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE emoji = VALUES(emoji), created_at = NOW()',
        [$newsId, $a['id'], $emoji]);
    $mine = $emoji;
}

respond($json, ['counts' => news_reaction_counts($newsId), 'mine' => $mine], $back);
