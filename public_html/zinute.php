<?php
// Nario (ar tėvų) žinutė treneriui: atsakymas į pastabą, klausimas prie pamokos arba bendra žinutė. Mato tik treneriai.
require __DIR__ . '/app/bootstrap.php';

$a = require_login();
if (!is_post()) {
    redirect('paskyra.php');
}
csrf_check();

$mid = (int) post('member_id');
$noteId = (int) post('note_id') ?: null;
$lessonId = (int) post('lesson_id') ?: null;
$body = trim(str_replace("\r\n", "\n", post('body')));
$back = $lessonId && post('back') === 'pamoka' ? 'pamokos.php?id=' . $lessonId . '#klausimas' : 'paskyra.php?m=' . $mid . ($noteId ? '#n' . $noteId : '#zinutes');

$m = q_one('SELECT * FROM members WHERE id = ? AND status = "active"', [$mid]);
if (!$m || !account_relation((int) $a['id'], $mid) || !is_active_account($a)) {
    flash('err', 'Žinutės išsiųsti nepavyko.');
    redirect('paskyra.php');
}
if ($noteId && !q_value('SELECT 1 FROM coach_notes WHERE id = ? AND member_id = ?', [$noteId, $mid])) {
    $noteId = null;
}
if ($lessonId && !q_value('SELECT 1 FROM lessons WHERE id = ?', [$lessonId])) {
    $lessonId = null;
}
if ($body === '') {
    flash('err', 'Įrašykite žinutę.');
    redirect($back);
}
add_member_message($mid, (int) $a['id'], false, $body, $noteId, $lessonId);
notify_staff_message($m, $a, $body, $noteId);
flash('ok', 'Išsiųsta treneriui. Atsakymą matysite čia ir gausite laišką.');
redirect($back);
