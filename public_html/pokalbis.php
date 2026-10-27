<?php
// Pokalbio langelis apačioje dešinėje (assets/site.js): bendros nario / tėvų žinutės su Deniu,
// anoniminis komentaras (kas parašė - nesaugoma) ir vienkartinė užuomina po prisijungimo.
// Siunčiama žinutė - per zinute.php (ajax=1).
require __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function chat_out(array $data, int $code = 200): void
{
    http_response_code($code);
    exit(json_encode($data, JSON_UNESCAPED_UNICODE));
}

$a = current_account();
if (!$a || !is_active_account($a)) {
    chat_out(['ok' => false, 'error' => 'Prisijunkite.'], 403);
}
$aid = (int) $a['id'];

if (is_post()) {
    csrf_check();
    if (post('action') === 'hint') {
        try {
            q('UPDATE accounts SET chat_hint_at = NOW() WHERE id = ? AND chat_hint_at IS NULL', [$aid]);
        } catch (PDOException $ex) {
            // dar neimportuotas db/021
        }
        chat_out(['ok' => true]);
    }
    if (post('action') === 'anon') {
        $body = trim(str_replace("\r\n", "\n", post('body')));
        if ($body === '') {
            chat_out(['ok' => false, 'error' => 'Įrašykite komentarą.']);
        }
        // Ne daugiau kaip 5 per valandą iš vienos sesijos
        $sent = array_filter((array) ($_SESSION['anon_sent'] ?? []), function ($t) { return $t > time() - 3600; });
        if (count($sent) >= 5) {
            chat_out(['ok' => false, 'error' => 'Per daug komentarų - pabandykite vėliau.']);
        }
        $page = preg_replace('~[^\w/.\-?=&%#]~u', '', mb_substr(post('page'), 0, 190));
        q('INSERT INTO anon_feedback (body, page) VALUES (?, ?)', [mb_substr($body, 0, MESSAGE_MAX), $page !== '' ? $page : null]);
        $sent[] = time();
        $_SESSION['anon_sent'] = array_values($sent);
        chat_out(['ok' => true]);
    }
    chat_out(['ok' => false, 'error' => 'Nežinomas veiksmas.'], 400);
}

// Bendros žinutės (ne prie pastabų, pamokų, renginių ar naujienų) vienam paskyros nariui
$mid = (int) get('m');
if (!$mid || !account_relation($aid, $mid)) {
    chat_out(['ok' => false, 'error' => 'Narys nerastas.'], 404);
}
$general = 'mm.member_id = ? AND mm.note_id IS NULL AND mm.lesson_id IS NULL AND mm.event_id IS NULL AND mm.news_id IS NULL';
$rows = array_slice(message_rows($general, [$mid]), -60);
q('UPDATE member_messages mm SET mm.read_at = NOW() WHERE ' . $general . ' AND mm.from_staff = 1 AND mm.read_at IS NULL', [$mid]);
$out = [];
foreach ($rows as $r) {
    $out[] = [
        'id'    => (int) $r['id'],
        'staff' => (bool) $r['from_staff'],
        'who'   => message_author($r, false),
        'when'  => notification_when($r['created_at']),
        'body'  => $r['body'],
    ];
}
chat_out(['ok' => true, 'messages' => $out]);
