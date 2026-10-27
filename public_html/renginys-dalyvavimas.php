<?php
// „Dalyvausiu“ / „Negalėsiu“: narys arba tėvai už vaiką
require __DIR__ . '/app/bootstrap.php';

$a = require_login();
if (!is_post()) {
    redirect('renginiai.php');
}
csrf_check();

$back = post('back');
$back = preg_match('#^(mano-renginiai|renginiai|renginys|egzaminas|paskyra)\.php(\?[\w=&-]*)?(\#[\w-]+)?$#', $back) ? $back : 'renginiai.php';
$eventId = (int) post('event_id');
$memberId = (int) post('member_id');
$status = post('status');

$ev = q_one('SELECT * FROM events WHERE id = ? AND ' . visibility_sql(), [$eventId]);
$m = q_one('SELECT * FROM members WHERE id = ?', [$memberId]);
if (!$ev || !$m || !account_relation((int) $a['id'], $memberId) || !in_array($status, ['going', 'declined'], true)) {
    flash('err', 'Nepavyko - renginys ar narys nerastas.');
    redirect($back);
}
if (!is_active_account($a) || !member_can_respond($ev, $m)) {
    flash('err', 'Į šį renginį registruotis nebegalima. Jei reikia - parašykite treneriui.');
    redirect($back);
}

// Teisėjauti gali tik žymą „Teisėjas“ turintys ir tik varžybose; dalyvauti - tik jei renginys skirtas nario grupei (ar pakviestas)
$role = post('role') === 'referee' && member_can_referee($ev, $m) ? 'referee' : 'participant';
if ($status === 'going' && $role === 'participant' && !event_participant($eventId, $memberId) && !event_targets_member($ev, $m)) {
    flash('err', 'Šis renginys skirtas kitoms grupėms.');
    redirect($back);
}
$who = $m['first_name'] . ' ' . $m['last_name'];
$current = event_participant($eventId, $memberId);
// Po termino „Dalyvausiu“ - prašymas Deniui: narys sąraše kaip „vėlavo“, kol Denis patvirtins
if ($status === 'going' && date('Y-m-d') > event_deadline($ev) && ($current['status'] ?? '') !== 'going') {
    set_participation($eventId, $memberId, 'late', null, $role);
    notify_admins('late_request', 'Pavėlavo užsiregistruoti: ' . $who, $ev['title'] . ' (' . fmt_date($ev['starts_on'], true) . '). Registracija baigėsi ' . fmt_date(event_deadline($ev)),
        'admin/renginio-dalyviai.php?id=' . $eventId, $memberId, $eventId);
    flash('ok', "{$m['first_name']}: registracija jau baigėsi, todėl Denis gavo prašymą. Kol jis patvirtins - sąraše pažymėta „vėlavo“.");
    redirect($back);
}
set_participation($eventId, $memberId, $status, null, $status === 'going' ? $role : null);
if ($ev['type'] === 'exam') {
    if ($status === 'going') {
        notify_admins('exam_registration', 'Užsiregistravo į egzaminą: ' . $who, $ev['title'] . ' · ' . fmt_date($ev['starts_on'], true)
            . ($m['belt_level'] !== null ? ' · dabar ' . BELTS[(int) $m['belt_level']][0] : ''), 'admin/renginio-dalyviai.php?id=' . $eventId, $memberId, $eventId);
    } else {
        notifications_done('exam_registration', $eventId, $memberId);
    }
}
if ($status === 'declined') {
    notifications_done('late_request', $eventId, $memberId);
}
flash('ok', $status === 'going'
    ? "{$m['first_name']}: " . ($role === 'referee' ? 'teisėjaus' : 'dalyvaus') . " - {$ev['title']}."
    : "{$m['first_name']}: pažymėta, kad negalės - {$ev['title']}.");
redirect($back);
