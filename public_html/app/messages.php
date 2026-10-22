<?php
// Nario ir trenerio žinutės: atsakymai į pastabas, klausimai prie pamokų, bendros žinutės.
// Nario žinutes mato tik treneriai; trenerio atsakymus - tik tas narys ir jo tėvai.

const MESSAGE_MAX = 2000;

function add_member_message(int $memberId, int $authorId, bool $fromStaff, string $body, ?int $noteId = null, ?int $lessonId = null): int
{
    q('INSERT INTO member_messages (member_id, note_id, lesson_id, author_id, from_staff, body) VALUES (?, ?, ?, ?, ?, ?)',
        [$memberId, $noteId, $lessonId, $authorId, $fromStaff ? 1 : 0, mb_substr($body, 0, MESSAGE_MAX)]);
    return (int) db()->lastInsertId();
}

function message_rows(string $where, array $params): array
{
    return q_all('SELECT mm.*, a.first_name AS author_first, a.last_name AS author_last, l.title AS lesson_title
                    FROM member_messages mm LEFT JOIN accounts a ON a.id = mm.author_id LEFT JOIN lessons l ON l.id = mm.lesson_id
                   WHERE ' . $where . ' ORDER BY mm.created_at, mm.id', $params);
}

/** Atsakymai į pastabas: [note_id => [žinutės]] */
function note_messages(array $noteIds): array
{
    $out = [];
    $noteIds = array_map('intval', $noteIds);
    if ($noteIds) {
        foreach (message_rows('mm.note_id IN (' . implode(',', $noteIds) . ')', []) as $r) {
            $out[(int) $r['note_id']][] = $r;
        }
    }
    return $out;
}

/** Žinutės ne prie pastabų (prie pamokų ir bendros), grupuotos: ['l12' => [...], 'general' => [...]] */
function member_free_threads(int $memberId): array
{
    $out = [];
    foreach (message_rows('mm.member_id = ? AND mm.note_id IS NULL', [$memberId]) as $r) {
        $out[$r['lesson_id'] ? 'l' . $r['lesson_id'] : 'general'][] = $r;
    }
    return $out;
}

/** Trenerio žinutės nariui perskaitytos (atidarius paskyrą ar pamoką) */
function mark_staff_messages_read(int $memberId, ?int $lessonId = null): void
{
    q('UPDATE member_messages SET read_at = NOW() WHERE member_id = ? AND from_staff = 1 AND read_at IS NULL' . ($lessonId ? ' AND lesson_id = ' . (int) $lessonId : ''), [$memberId]);
}

function mark_member_messages_read(int $memberId): void
{
    q('UPDATE member_messages SET read_at = NOW() WHERE member_id = ? AND from_staff = 0 AND read_at IS NULL', [$memberId]);
}

/** Kiek naujų narių žinučių laukia trenerio */
function staff_unread_messages(): int
{
    static $n = null;
    if ($n === null) {
        try {
            $n = (int) q_value('SELECT COUNT(*) FROM member_messages WHERE from_staff = 0 AND read_at IS NULL');
        } catch (PDOException $ex) {
            $n = 0;   // dar neimportuotas db/018
        }
    }
    return $n;
}

/** Kiek neperskaitytų trenerio atsakymų nariui / visai paskyrai */
function member_unread_replies(int $memberId): int
{
    try {
        return (int) q_value('SELECT COUNT(*) FROM member_messages WHERE member_id = ? AND from_staff = 1 AND read_at IS NULL', [$memberId]);
    } catch (PDOException $ex) {
        return 0;
    }
}

function account_unread_replies(int $accountId): int
{
    try {
        return (int) q_value('SELECT COUNT(*) FROM member_messages mm JOIN account_members am ON am.member_id = mm.member_id
                               WHERE am.account_id = ? AND mm.from_staff = 1 AND mm.read_at IS NULL', [$accountId]);
    } catch (PDOException $ex) {
        return 0;
    }
}

/** Kam rodyti žinutę: „Jūs“, „Treneris Denis“, „Rasa (mama)“ */
function message_author(array $r, bool $staffView): string
{
    if ($r['from_staff']) {
        return 'Treneris' . ($r['author_first'] ? ' ' . $r['author_first'] : '');
    }
    return $staffView ? (trim(($r['author_first'] ?? '') . ' ' . ($r['author_last'] ?? '')) ?: 'Narys') : ($r['author_first'] ?: 'Jūs');
}

function render_message(array $r, bool $staffView): string
{
    $when = fmt_date(substr($r['created_at'], 0, 10)) . ', ' . substr($r['created_at'], 11, 5);
    $new = ($staffView ? !$r['from_staff'] : $r['from_staff']) && $r['read_at'] === null;
    return '<div class="msg ' . ($r['from_staff'] ? 'from-staff' : 'from-member') . ($new ? ' is-new' : '') . '">'
        . '<div class="msg-meta">' . e(message_author($r, $staffView)) . ' · ' . e($when) . ($new ? ' <span class="badge badge-new">Nauja</span>' : '') . '</div>'
        . '<div class="msg-body">' . nl2br(e($r['body'])) . '</div></div>';
}

/** Atsakymo forma. $hidden - paslėpti laukai (member_id, note_id, lesson_id...) */
function message_form(string $action, array $hidden, string $placeholder, string $button, string $actionUrl = ''): string
{
    $h = '<form method="post"' . ($actionUrl ? ' action="' . e($actionUrl) . '"' : '') . ' class="msg-form">' . csrf_field()
        . '<input type="hidden" name="action" value="' . e($action) . '">';
    foreach ($hidden as $k => $v) {
        $h .= '<input type="hidden" name="' . e($k) . '" value="' . e((string) $v) . '">';
    }
    return $h . '<textarea name="body" rows="2" maxlength="' . MESSAGE_MAX . '" placeholder="' . e($placeholder) . '" required></textarea>'
        . '<button class="btn btn-primary btn-sm" type="submit">' . e($button) . '</button></form>';
}

/** Laiškas treneriui apie nario žinutę: pastabos autoriui, kitaip - klubo paštu */
function notify_staff_message(array $member, array $from, string $body, ?int $noteId): void
{
    $to = null;
    if ($noteId) {
        $to = q_value('SELECT a.email FROM coach_notes cn JOIN accounts a ON a.id = cn.author_id WHERE cn.id = ? AND a.status = "active"', [$noteId]) ?: null;
    }
    $to = $to ?: config('notify_email');
    if ($to) {
        send_mail($to, 'Žinutė nuo ' . $from['first_name'] . ' (' . $member['first_name'] . ' ' . $member['last_name'] . ')',
            $from['first_name'] . ' ' . $from['last_name'] . " rašo:\n\n" . $body . "\n\nAtsakyti: " . abs_url('admin/zinutes.php?m=' . (int) $member['id']));
    }
}

/** Laiškas nariui (ir tėvams) apie trenerio atsakymą */
function notify_member_reply(array $member, array $coach, string $body): void
{
    foreach (q_all('SELECT a.* FROM accounts a JOIN account_members am ON am.account_id = a.id WHERE am.member_id = ? AND a.status = "active"', [(int) $member['id']]) as $acc) {
        send_mail($acc['email'], 'Treneris atsakė: ' . $member['first_name'],
            "Sveiki,\n\ntreneris {$coach['first_name']} atsakė ({$member['first_name']}):\n\n$body\n\nPaskyroje: " . abs_url('paskyra.php?m=' . (int) $member['id']) . '#zinutes');
    }
}

/**
 * Nauja trenerio pastaba nariui (atsiranda paskyroje „Trenerio pastabos“): su pamoka, kaip užduotis, su laišku.
 * Grąžina pastabos id.
 */
function send_coach_note(array $member, array $coach, string $body, ?int $lessonId, bool $isTask, bool $email): int
{
    if ($body === '') {
        $body = $lessonId ? 'Pažiūrėk prisegtą pamoką.' : 'Pastaba.';
    }
    q('INSERT INTO coach_notes (member_id, author_id, group_id, note_date, body, lesson_id, is_task) VALUES (?, ?, ?, CURDATE(), ?, ?, ?)',
        [(int) $member['id'], (int) $coach['id'], $member['group_id'] ? (int) $member['group_id'] : null, mb_substr($body, 0, MESSAGE_MAX), $lessonId, $isTask ? 1 : 0]);
    $id = (int) db()->lastInsertId();
    if ($email) {
        $lesson = $lessonId ? q_value('SELECT title FROM lessons WHERE id = ?', [$lessonId]) : null;
        foreach (q_all('SELECT a.* FROM accounts a JOIN account_members am ON am.account_id = a.id WHERE am.member_id = ? AND a.status = "active"', [(int) $member['id']]) as $acc) {
            send_mail($acc['email'], ($isTask ? 'Trenerio užduotis: ' : 'Trenerio pastaba: ') . $member['first_name'],
                "Sveiki,\n\ntreneris {$coach['first_name']} paliko " . ($isTask ? 'užduotį' : 'pastabą') . " ({$member['first_name']}):\n\n$body\n"
                . ($lesson ? "\nPamoka: $lesson - " . abs_url('pamokos.php?id=' . $lessonId) . "\n" : '')
                . "\nPaskyroje galite atsakyti treneriui: " . abs_url('paskyra.php?m=' . (int) $member['id']) . '#n' . $id);
        }
    }
    return $id;
}
