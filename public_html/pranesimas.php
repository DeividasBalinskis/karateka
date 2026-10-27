<?php
// Pranešimo veiksmai: „Supratau“ / perskaityta; administratoriui - patvirtinti „Moku“ (XP) ar pavėlavusio dalyvavimą
require __DIR__ . '/app/bootstrap.php';

$a = require_login();
if (!is_post()) {
    redirect(is_staff($a) ? 'admin/' : 'paskyra.php');
}
csrf_check();

$back = post('back');
if (!preg_match('~^(admin/)?[\w-]*(\.php)?(\?[\w=&%.\-]*)?(#[\w-]*)?$~', $back) || $back === '') {
    $back = is_staff($a) ? 'admin/' : 'paskyra.php';
}
$n = q_one('SELECT * FROM notifications WHERE id = ? AND account_id = ?', [(int) post('id'), (int) $a['id']]);
if (!$n) {
    redirect($back);
}

switch (post('action')) {
    case 'read':
        notification_mark((int) $n['id'], (int) $a['id'], false);
        break;
    case 'done':
        notification_mark((int) $n['id'], (int) $a['id'], true);
        break;
    case 'skill_approve':
    case 'skill_reject':
        if (!is_staff($a) || !$n['member_id'] || !$n['ref_id']) {
            forbidden();
        }
        if (post('action') === 'skill_approve') {
            flash('ok', approve_skill((int) $n['member_id'], (int) $n['ref_id'], (int) $a['id']) ? 'Patvirtinta, XP skirti.' : 'Jau buvo patvirtinta.');
        } else {
            reject_skill((int) $n['member_id'], (int) $n['ref_id']);
            flash('ok', 'Grąžinta - narys galės pažymėti dar kartą.');
        }
        break;
    case 'late_approve':
        if (!is_staff($a) || !$n['member_id'] || !$n['ref_id']) {
            forbidden();
        }
        $ev = q_one('SELECT * FROM events WHERE id = ?', [(int) $n['ref_id']]);
        if ($ev) {
            set_participation((int) $ev['id'], (int) $n['member_id'], 'going', (int) $a['id']);
            notify_member((int) $n['member_id'], 'late_approved', 'Dalyvavimas patvirtintas: ' . $ev['title'],
                'Denis patvirtino - lauksime ' . fmt_date($ev['starts_on'], true) . '.', 'renginys.php?id=' . (int) $ev['id'], (int) $ev['id'], false);
            flash('ok', 'Dalyvavimas patvirtintas.');
        }
        notifications_done('late_request', (int) $n['ref_id'], (int) $n['member_id']);
        break;
}
redirect($back);
