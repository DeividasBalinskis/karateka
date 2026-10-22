<?php
// Renginių dalyviai (registracija ir trenerio kvietimai) ir narių žymos

const MEMBER_TAGS = ['teisejas' => 'Teisėjas', 'egzaminatorius' => 'Egzaminatorius'];

const PARTICIPANT_STATUS = ['going' => 'Dalyvaus', 'invited' => 'Pakviestas, neatsakė', 'declined' => 'Negalės'];

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

/** Ar dar galima registruotis pačiam */
function event_registration_open(array $ev): bool
{
    $today = date('Y-m-d');
    if (empty($ev['registration']) || $ev['starts_on'] < $today) {
        return false;
    }
    return empty($ev['registration_until']) || $ev['registration_until'] >= $today;
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

/** ['going' => n, 'invited' => n, 'declined' => n] */
function event_participant_counts(int $eventId): array
{
    $out = ['going' => 0, 'invited' => 0, 'declined' => 0];
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
 * Pakviečia narius (jau atsakiusių ar pakviestų nekeičia). Laiškas - kiekvienai susietai paskyrai vieną kartą.
 * Grąžina, kiek naujų kvietimų sukurta.
 */
function invite_members(array $ev, array $memberIds, int $by, bool $sendEmail): int
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
                . 'Atsakykite „Dalyvausiu“ arba „Negalėsiu“ svetainėje (Renginiai): ' . abs_url('renginiai.php'));
        }
    }
    return count($new);
}

/** Ar narys gali pats atsakyti / registruotis (pakviestas arba renginys jo grupei ir registracija atvira) */
function member_can_respond(array $ev, array $m): bool
{
    if ($ev['starts_on'] < date('Y-m-d') || $m['status'] !== 'active') {
        return false;
    }
    $p = event_participant((int) $ev['id'], (int) $m['id']);
    if ($p) {
        return true;
    }
    return event_registration_open($ev) && (event_targets_member($ev, $m) || member_can_referee($ev, $m));
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
        $canChange = $ev['starts_on'] >= date('Y-m-d');
        $label = count($members) > 1 ? '<span class="rsvp-name">' . e($m['first_name']) . '</span>' : '';
        $state = $status === 'going' ? '<span class="rsvp-state ok">✓ ' . ($p['role'] === 'referee' ? 'Teisėjaus' : 'Dalyvaus') . '</span>'
            : ($status === 'declined' ? '<span class="rsvp-state no">Negalės</span>'
            : ($status === 'invited' ? '<span class="rsvp-state inv">Treneris kviečia' . ($p['role'] === 'referee' ? ' teisėjauti' : '') . '</span>' : ''));
        $buttons = '';
        if ($canChange) {
            $form = function (string $value, string $text, string $cls, string $role = '') use ($ev, $m, $back) {
                return '<form method="post" action="' . e(url('renginys-dalyvavimas.php')) . '" class="inline-form">' . csrf_field()
                    . '<input type="hidden" name="event_id" value="' . (int) $ev['id'] . '"><input type="hidden" name="member_id" value="' . (int) $m['id'] . '">'
                    . ($role !== '' ? '<input type="hidden" name="role" value="' . $role . '">' : '')
                    . '<input type="hidden" name="back" value="' . e($back) . '"><button class="btn btn-sm ' . $cls . '" name="status" value="' . $value . '">' . $text . '</button></form>';
            };
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
        }
        $rows .= '<div class="rsvp-row">' . $label . $state . '<span class="rsvp-btns">' . $buttons . '</span></div>';
    }
    return $rows ? '<div class="rsvp">' . $rows . '</div>' : '';
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
