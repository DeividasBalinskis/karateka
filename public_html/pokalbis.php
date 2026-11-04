<?php
// Pokalbio langelio API (assets/chat.js). Narys: savo pokalbis su Deniu, užuomina po prisijungimo, savo žinutės ištrynimas.
// Treneris: pokalbių sąrašas su paieška, vieno nario pokalbis, atsakymas (ir prie renginio / naujienos / pamokos), paviešinimas.
// Nario žinutė siunčiama per zinute.php (ajax=1). Formos be JS (atsakymas prie įrašo) - grąžinama atgal (back).
require __DIR__ . '/app/bootstrap.php';

$ajax = post('ajax') === '1' || !is_post();
if ($ajax) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
}

function chat_out(array $data, int $code = 200): void
{
    http_response_code($code);
    exit(json_encode($data, JSON_UNESCAPED_UNICODE));
}

/** Klaida: JSON arba (formai) pranešimas ir atgal */
function chat_fail(bool $ajax, string $msg, string $back, int $code = 400): void
{
    if ($ajax) {
        chat_out(['ok' => false, 'error' => $msg], $code);
    }
    flash('err', $msg);
    redirect($back);
}

$a = current_account();
if (!$a || !is_active_account($a)) {
    $ajax ? chat_out(['ok' => false, 'error' => 'Prisijunkite.'], 403) : redirect('prisijungti.php');
}
$aid = (int) $a['id'];
$isStaff = is_staff($a);
$back = preg_match('~^(renginys|naujienos|pamokos|klausimai)\.php(\?[\w=&%.\-]*)?(#[\w-]*)?$~', post('back')) ? post('back') : 'index.php';

// Skaičiukas ant mygtuko (kol langelis uždarytas)
if (!is_post() && get('badge') !== '') {
    chat_out(['ok' => true, 'unread' => chat_unread_count($a)]);
}

if (is_post()) {
    csrf_check();
    $action = post('action');

    if ($action === 'hint') {
        q('UPDATE accounts SET chat_hint_at = NOW() WHERE id = ? AND chat_hint_at IS NULL', [$aid]);
        chat_out(['ok' => true]);
    }

    // Savo žinutę ar komentarą galima ištrinti (narys ar tėvai - savo, treneris - savo atsakymą)
    if ($action === 'delete') {
        $msg = q_one('SELECT * FROM member_messages WHERE id = ? AND author_id = ?', [(int) post('message_id'), $aid]);
        if (!$msg) {
            chat_fail($ajax, 'Žinutė nerasta.', $back, 404);
        }
        q('DELETE FROM member_messages WHERE id = ?', [(int) $msg['id']]);
        q('DELETE FROM notifications WHERE type = "comment" AND ref_id = ?', [(int) $msg['id']]);
        if (!$ajax) {
            flash('ok', 'Ištrinta.');
            redirect($back);
        }
        chat_out(['ok' => true]);
    }

    if (!$isStaff) {
        chat_out(['ok' => false, 'error' => 'Nežinomas veiksmas.'], 400);
    }

    // ---------- Treneris ----------
    $mid = (int) post('member_id');
    $member = $mid ? q_one('SELECT * FROM members WHERE id = ?', [$mid]) : null;
    if (!$member) {
        chat_fail($ajax, 'Narys nerastas.', $back, 404);
    }
    if ($action === 'reply') {
        $body = trim(str_replace("\r\n", "\n", post('body')));
        if ($body === '') {
            chat_fail($ajax, 'Įrašykite atsakymą.', $back);
        }
        chat_staff_reply($mid, $a, $body, post('ctx'));
        if (!$ajax) {
            flash('ok', 'Atsakyta - ' . $member['first_name'] . ' matys atsakymą čia ir pokalbyje.');
            redirect($back);
        }
        chat_out(['ok' => true]);
    }
    if ($action === 'publish') {
        $msg = q_one('SELECT * FROM member_messages WHERE id = ? AND member_id = ? AND from_staff = 0', [(int) post('message_id'), $mid]);
        if ($msg) {
            q('UPDATE member_messages SET is_public = 1 - is_public WHERE id = ?', [(int) $msg['id']]);
        }
        if (!$ajax) {
            redirect($back);
        }
        chat_out(['ok' => true]);
    }
    chat_out(['ok' => false, 'error' => 'Nežinomas veiksmas.'], 400);
}

// ---------- Skaitymas ----------
if ($isStaff) {
    // Apibendrintas nario profilis (langas paspaudus vardą)
    if ($pid = (int) get('profile')) {
        $html = member_profile_html($pid);
        $html === '' ? chat_out(['ok' => false, 'error' => 'Narys nerastas.'], 404) : chat_out(['ok' => true, 'html' => $html]);
    }
    $out = ['ok' => true];
    if (get('list') !== '') {
        $out += chat_conversations(mb_substr(trim(get('q')), 0, 60));
    }
    if (get('all') !== '') {
        $out['all'] = chat_all_members(mb_substr(trim(get('q')), 0, 60));
    }
    if ($mid = (int) get('m')) {
        $info = chat_member_info($mid);
        if (!$info) {
            chat_out(['ok' => false, 'error' => 'Narys nerastas.'], 404);
        }
        mark_member_messages_read($mid);
        $out['member'] = $info;
        $out['messages'] = chat_thread($mid, true);
    }
    chat_out($out);
}

$mid = (int) get('m');
if (!$mid || !account_relation($aid, $mid)) {
    chat_out(['ok' => false, 'error' => 'Narys nerastas.'], 404);
}
$messages = chat_thread($mid, false);
q('UPDATE member_messages SET read_at = NOW() WHERE member_id = ? AND from_staff = 1 AND read_at IS NULL', [$mid]);
chat_out(['ok' => true, 'messages' => $messages]);
