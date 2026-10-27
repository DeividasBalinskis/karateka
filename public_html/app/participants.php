<?php
// Renginių dalyviai (registracija ir trenerio kvietimai) ir narių žymos

const MEMBER_TAGS = ['teisejas' => 'Teisėjas', 'egzaminatorius' => 'Egzaminatorius'];

const PARTICIPANT_STATUS = ['going' => 'Dalyvaus', 'late' => 'Vėlavo - laukia patvirtinimo', 'invited' => 'Pakviestas, neatsakė', 'declined' => 'Negalės'];

// ---------- Žymos ----------

function member_tags(int $memberId): array
{
    return q('SELECT tag FROM member_tags WHERE member_id = ? ORDER BY tag', [$memberId])->fetchAll(PDO::FETCH_COLUMN);
}

function set_member_tags(int $memberId, array $tags): void
{
    q('DELETE FROM member_tags WHERE member_id = ?', [$memberId]);
    foreach (array_intersect(array_keys(MEMBER_TAGS), $tags) as $tag) {
        q('INSERT INTO member_tags (member_id, tag) VALUES (?, ?)', [$memberId, $tag]);
    }
}

/** Ar paskyra turi žymą: per savo narį (relation = self). Treneriai mato viską. */
function account_has_tag(array $a, string $tag): bool
{
    if (is_staff($a)) {
        return true;
    }
    return (bool) q_value('SELECT 1 FROM member_tags mt JOIN account_members am ON am.member_id = mt.member_id
                            WHERE am.account_id = ? AND am.relation = "self" AND mt.tag = ?', [(int) $a['id'], $tag]);
}

/** Paskyros narių žymos: [member_id => [tag, ...]] */
function account_member_tags(int $accountId): array
{
    $out = [];
    foreach (q_all('SELECT mt.* FROM member_tags mt JOIN account_members am ON am.member_id = mt.member_id WHERE am.account_id = ?', [$accountId]) as $r) {
        $out[(int) $r['member_id']][] = $r['tag'];
    }
    return $out;
}

/** Žymų ženkleliai; su $memberId - ir ar išlaikytas tos žymos testas (✓ galioja 12 mėn.) */
function tag_badges(array $tags, ?int $memberId = null): string
{
    $html = '';
    foreach ($tags as $t) {
        $q = $memberId ? tag_qualification($memberId, $t) : null;
        $title = $q ? ' title="Testas išlaikytas ' . e(fmt_date(substr($q['finished_at'], 0, 10))) . ', galioja iki '
            . e(fmt_date(date('Y-m-d', strtotime($q['finished_at'] . ' +' . QUIZ_VALID_MONTHS . ' months')))) . '"' : '';
        $html .= ' <span class="badge badge-tag' . ($q ? ' qualified' : '') . '"' . $title . '>' . e(MEMBER_TAGS[$t] ?? $t) . ($q ? ' ✓' : '') . '</span>';
    }
    return $html;
}

/** Kvalifikacijos tekstas: „Teisėjas ✓ iki 2027-10-19“ arba „testas neišlaikytas“ */
function tag_qualification_text(int $memberId, string $tag): string
{
    $q = tag_qualification($memberId, $tag);
    if ($q) {
        return '✓ testas išlaikytas, galioja iki ' . fmt_date(date('Y-m-d', strtotime($q['finished_at'] . ' +' . QUIZ_VALID_MONTHS . ' months')));
    }
    return q_value('SELECT 1 FROM quizzes WHERE audience = ? AND is_published = 1 LIMIT 1', [$tag]) ? 'testas dar neišlaikytas' : '';
}

// ---------- Dalyviai ----------

/** Ar renginys skirtas nario grupei (renginys be grupių - visiems) */
function event_targets_member(array $ev, array $m): bool
{
    if (!empty($ev['invited_only'])) {
        return false;   // tik pakviestiems - jie mato per kvietimą
    }
    $groups = array_map('intval', q('SELECT group_id FROM event_groups WHERE event_id = ?', [(int) $ev['id']])->fetchAll(PDO::FETCH_COLUMN));
    return !$groups || ($m['group_id'] && in_array((int) $m['group_id'], $groups, true));
}

/** Paskutinė registracijos diena: nurodyta arba diena prieš renginį (renginio dieną registruotis nebegalima) */
function event_deadline(array $ev): string
{
    $dayBefore = date('Y-m-d', strtotime($ev['starts_on'] . ' -1 day'));
    return !empty($ev['registration_until']) && $ev['registration_until'] < $ev['starts_on'] ? $ev['registration_until'] : $dayBefore;
}

/** Ar dar galima registruotis pačiam (iki termino imtinai) */
function event_registration_open(array $ev): bool
{
    return !empty($ev['registration']) && date('Y-m-d') <= event_deadline($ev);
}

/** Terminas praėjo, bet renginys dar nevyko: galima paprašyti Denio („pavėlavau“) */
function event_late_possible(array $ev): bool
{
    $today = date('Y-m-d');
    return !empty($ev['registration']) && $today > event_deadline($ev) && $today < $ev['starts_on'];
}

/** „Registracija iki spalio 30 d. · liko 2 d. 5 val.“ (laikas atnaujinamas naršyklėje) */
function event_deadline_html(array $ev): string
{
    if (date('Y-m-d') >= $ev['starts_on']) {
        return '';
    }
    $deadline = event_deadline($ev);
    $end = strtotime($deadline . ' 23:59:59');
    if (time() > $end) {
        return '<div class="deadline closed">Registracija baigėsi ' . e(fmt_date($deadline)) . '</div>';
    }
    return '<div class="deadline" data-deadline="' . date('c', $end) . '">⏳ Registracija iki ' . e(fmt_date($deadline)) . ' · <span class="deadline-left">' . e(time_left_text($end - time())) . '</span></div>';
}

/** „liko 2 d. 5 val.“, „liko 3 val. 20 min.“ */
function time_left_text(int $sec): string
{
    if ($sec <= 0) {
        return 'baigėsi';
    }
    $d = intdiv($sec, 86400);
    $h = intdiv($sec % 86400, 3600);
    $m = intdiv($sec % 3600, 60);
    return 'liko ' . ($d ? $d . ' d. ' . $h . ' val.' : ($h ? $h . ' val. ' . $m . ' min.' : max(1, $m) . ' min.'));
}

function event_participant(int $eventId, int $memberId): ?array
{
    return q_one('SELECT * FROM event_participants WHERE event_id = ? AND member_id = ?', [$eventId, $memberId]);
}

function set_participation(int $eventId, int $memberId, string $status, ?int $by = null, ?string $role = null): void
{
    $current = event_participant($eventId, $memberId);
    $role = $role ?? ($current['role'] ?? 'participant');
    q('INSERT INTO event_participants (event_id, member_id, status, role, invited_by, responded_at)
       VALUES (?, ?, ?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE status = VALUES(status), role = VALUES(role), responded_at = VALUES(responded_at)',
        [$eventId, $memberId, $status, $role, $by, $status === 'invited' ? null : date('Y-m-d H:i:s')]);
}

function remove_participation(int $eventId, int $memberId): void
{
    q('DELETE FROM event_participants WHERE event_id = ? AND member_id = ?', [$eventId, $memberId]);
}

/** ['going' => n, 'late' => n, 'invited' => n, 'declined' => n] */
function event_participant_counts(int $eventId): array
{
    $out = ['going' => 0, 'late' => 0, 'invited' => 0, 'declined' => 0];
    foreach (q_all('SELECT status, COUNT(*) c FROM event_participants WHERE event_id = ? GROUP BY status', [$eventId]) as $r) {
        $out[$r['status']] = (int) $r['c'];
    }
    return $out;
}

/** Kiek neatsakytų kvietimų turi paskyros nariai (artėjantys renginiai) */
function account_pending_invites(int $accountId): int
{
    return (int) q_value('SELECT COUNT(*) FROM event_participants ep
                            JOIN account_members am ON am.member_id = ep.member_id
                            JOIN events e ON e.id = ep.event_id
                           WHERE am.account_id = ? AND ep.status = "invited" AND COALESCE(e.ends_on, e.starts_on) >= CURDATE()', [$accountId]);
}

/**
 * Pakviečia narius (jau atsakiusių ar pakviestų nekeičia). Kvietimas visada matomas svetainėje (Renginiai, Mano paskyra);
 * laiškas ir SMS - jei pasirinkta (kiekvienai paskyrai vienas). Grąžina, kiek naujų kvietimų sukurta.
 */
function invite_members(array $ev, array $memberIds, int $by, bool $sendEmail, bool $sendSms = false): int
{
    $new = [];
    foreach (array_unique(array_map('intval', $memberIds)) as $mid) {
        if (!event_participant((int) $ev['id'], $mid)) {
            q('INSERT INTO event_participants (event_id, member_id, status, invited_by) VALUES (?, ?, "invited", ?)', [(int) $ev['id'], $mid, $by]);
            $new[] = $mid;
        }
    }
    if ($sendEmail && $new) {
        $byAccount = [];
        foreach (q_all('SELECT a.id, a.email, a.first_name, m.first_name AS member_name
                          FROM account_members am JOIN accounts a ON a.id = am.account_id JOIN members m ON m.id = am.member_id
                         WHERE a.status = "active" AND am.member_id IN (' . implode(',', $new) . ')') as $r) {
            $byAccount[$r['email']]['first_name'] = $r['first_name'];
            $byAccount[$r['email']]['members'][] = $r['member_name'];
        }
        $when = fmt_date($ev['starts_on'], true) . ($ev['start_time'] ? ', ' . fmt_time($ev['start_time']) : '');
        foreach ($byAccount as $email => $info) {
            send_mail($email, 'Kvietimas: ' . $ev['title'],
                "Sveiki, {$info['first_name']},\n\ntreneris kviečia " . implode(', ', array_unique($info['members'])) . " į renginį:\n\n"
                . $ev['title'] . "\n" . $when . ($ev['location'] ? "\n" . $ev['location'] : '') . "\n\n"
                . 'Atsakykite „Dalyvausiu“ arba „Negalėsiu“ iki ' . fmt_date(event_deadline($ev), true) . ': ' . abs_url('renginys.php?id=' . (int) $ev['id']));
        }
    }
    if ($sendSms && $new) {
        $rec = sms_recipients_for_members($new)['recipients'];
        if ($rec) {
            sms_send_message(sms_transliterate('Karateka: kvietimas - ' . $ev['title'] . ', ' . fmt_date($ev['starts_on'])
                . '. Atsakykite iki ' . fmt_date(event_deadline($ev)) . ': ' . abs_url('renginys.php?id=' . (int) $ev['id'])), $rec, 'Kvietimas: ' . $ev['title'], $by);
        }
    }
    return count($new);
}

/**
 * Ar narys gali pats atsakyti: pakviestas arba renginys jo grupei. Renginio dieną ir po jo - nebe.
 * Po termino „Dalyvausiu“ tampa prašymu Deniui (žr. renginys-dalyvavimas.php).
 */
function member_can_respond(array $ev, array $m): bool
{
    if ($ev['starts_on'] <= date('Y-m-d') || $m['status'] !== 'active') {
        return false;
    }
    if (event_participant((int) $ev['id'], (int) $m['id'])) {
        return true;
    }
    return (event_registration_open($ev) || event_late_possible($ev)) && (event_targets_member($ev, $m) || member_can_referee($ev, $m));
}

/** Teisėjas (žyma) gali registruotis į bet kurias varžybas kaip teisėjas */
function member_can_referee(array $ev, array $m): bool
{
    return $ev['type'] === 'competition' && in_array('teisejas', member_tags((int) $m['id']), true);
}

/**
 * „Dalyvausiu / Negalėsiu“ kiekvienam paskyros nariui, kuriam renginys aktualus.
 * $members - account_members(); $showNames - rodyti vardus (kai narių keli).
 */
function render_event_rsvp(array $ev, array $members, string $back): string
{
    $rows = '';
    foreach ($members as $m) {
        if ($m['status'] !== 'active') {
            continue;
        }
        $p = event_participant((int) $ev['id'], (int) $m['id']);
        if (!$p && !member_can_respond($ev, $m)) {
            continue;
        }
        $status = $p['status'] ?? null;
        $canChange = $ev['starts_on'] > date('Y-m-d');
        $late = date('Y-m-d') > event_deadline($ev);   // terminas praėjo - „Dalyvausiu“ tampa prašymu Deniui
        $label = count($members) > 1 ? '<span class="rsvp-name">' . e($m['first_name']) . '</span>' : '';
        $state = $status === 'going' ? '<span class="rsvp-state ok">✓ ' . ($p['role'] === 'referee' ? 'Teisėjaus' : 'Dalyvaus') . '</span>'
            : ($status === 'late' ? '<span class="rsvp-state late">⏰ Pavėlavo - laukia Denio patvirtinimo</span>'
            : ($status === 'declined' ? '<span class="rsvp-state no">Negalės</span>'
            : ($status === 'invited' ? '<span class="rsvp-state inv">Treneris kviečia' . ($p['role'] === 'referee' ? ' teisėjauti' : '') . '</span>' : '')));
        $form = function (string $value, string $text, string $cls, string $role = '', string $title = '') use ($ev, $m, $back) {
            return '<form method="post" action="' . e(url('renginys-dalyvavimas.php')) . '" class="inline-form">' . csrf_field()
                . '<input type="hidden" name="event_id" value="' . (int) $ev['id'] . '"><input type="hidden" name="member_id" value="' . (int) $m['id'] . '">'
                . ($role !== '' ? '<input type="hidden" name="role" value="' . $role . '">' : '')
                . '<input type="hidden" name="back" value="' . e($back) . '"><button class="btn btn-sm ' . $cls . '" name="status" value="' . $value . '"'
                . ($title !== '' ? ' title="' . e($title) . '"' : '') . '>' . $text . '</button></form>';
        };
        $buttons = '';
        if ($canChange && !$late) {
            if ($status !== 'going') {
                $asRole = $p['role'] ?? 'participant';
                if ($p || event_targets_member($ev, $m)) {
                    $buttons .= $form('going', $asRole === 'referee' ? 'Teisėjausiu' : 'Dalyvausiu', 'btn-primary', $asRole);
                }
                if ($asRole !== 'referee' && member_can_referee($ev, $m)) {
                    $buttons .= $form('going', 'Teisėjausiu', $buttons ? 'btn-ghost' : 'btn-primary', 'referee');
                }
            }
            if ($status === 'going' || $status === 'invited') {
                $buttons .= $form('declined', $status === 'going' ? 'Atšaukti' : 'Negalėsiu', 'btn-ghost');
            }
        } elseif ($canChange) {
            if ($status === 'going' || $status === 'late') {
                $buttons .= $form('declined', 'Atšaukti', 'btn-ghost');
            } elseif ($p || (!empty($ev['registration']) && event_targets_member($ev, $m))) {
                // Po termino: galima paprašyti - Denis gauna pranešimą ir patvirtina
                $buttons .= $form('going', 'Noriu dalyvauti (pavėlavau)', 'btn-ghost', '', 'Registracija baigėsi - Denis gaus pranešimą');
                if ($status === 'invited') {
                    $state = '';   // „Registracija baigėsi“ jau parašyta viršuje
                }
            }
        }
        $rows .= '<div class="rsvp-row">' . $label . $state . '<span class="rsvp-btns">' . $buttons . '</span></div>';
    }
    return $rows ? '<div class="rsvp">' . event_deadline_html($ev) . $rows . '</div>' : '';
}

/** Papildoma informacija dalyvių sąraše (pvz. egzamino testo būsena) */
function participant_extra_info(array $ev, array $row): string
{
    if ($ev['type'] !== 'exam') {
        return '';
    }
    $st = exam_test_status(['id' => $row['member_id'], 'belt_level' => $row['belt_level']]);
    if (!$st) {
        return '';
    }
    return $st['passed']
        ? '<div class="test-ok">✓ Testas išlaikytas · leidžiama laikyti praktiką</div>'
        : '<div class="test-no">Testas ' . ($st['best'] !== null ? 'neišlaikytas (geriausias ' . $st['best'] . ' %)' : 'dar nelaikytas') . '</div>';
}
