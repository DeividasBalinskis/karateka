<?php
// Nario (ar tėvų) žinutė treneriui: atsakymas į pastabą, klausimas prie pamokos, renginio ar naujienos, bendra žinutė
// (ir iš pokalbio langelio apačioje dešinėje - tada atsakoma JSON). Mato tik treneriai.
require __DIR__ . '/app/bootstrap.php';

$a = require_login();
if (!is_post()) {
    redirect('paskyra.php');
}
csrf_check();
$ajax = post('ajax') === '1';

$mid = (int) post('member_id');
$noteId = (int) post('note_id') ?: null;
$lessonId = (int) post('lesson_id') ?: null;
$eventId = (int) post('event_id') ?: null;
$newsId = (int) post('news_id') ?: null;
$body = trim(str_replace("\r\n", "\n", post('body')));
$back = post('back');
if (!preg_match('~^(pamokos|renginys|naujienos|paskyra)\.php(\?[\w=&%.\-]*)?$~', $back)) {
    $back = $lessonId && $back === 'pamoka' ? 'pamokos.php?id=' . $lessonId : 'paskyra.php?m=' . $mid;
}
$back .= $noteId ? '#n' . $noteId : ($lessonId || $eventId || $newsId ? '#klausimai' : '#zinutes');

function fail(string $msg, string $back, bool $ajax): void
{
    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE));
    }
    flash('err', $msg);
    redirect($back);
}

$m = q_one('SELECT * FROM members WHERE id = ? AND status = "active"', [$mid]);
if (!$m || !account_relation((int) $a['id'], $mid) || !is_active_account($a)) {
    fail('Žinutės išsiųsti nepavyko.', 'paskyra.php', $ajax);
}
if ($noteId && !q_value('SELECT 1 FROM coach_notes WHERE id = ? AND member_id = ?', [$noteId, $mid])) {
    $noteId = null;
}
if ($lessonId && !q_value('SELECT 1 FROM lessons WHERE id = ?', [$lessonId])) {
    $lessonId = null;
}
if ($eventId && !q_value('SELECT 1 FROM events WHERE id = ?', [$eventId])) {
    $eventId = null;
}
if ($newsId && !q_value('SELECT 1 FROM news WHERE id = ?', [$newsId])) {
    $newsId = null;
}
if ($body === '') {
    fail('Įrašykite žinutę.', $back, $ajax);
}
$msgId = add_member_message($mid, (int) $a['id'], false, $body, $noteId, $lessonId, $eventId, $newsId);
notify_staff_message($m, $a, $body, $noteId);
if ($ajax) {
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode(['ok' => true, 'id' => $msgId]));
}
flash('ok', 'Išsiųsta Deniui. Atsakymą matysite čia ir paskyroje.');
redirect($back);
