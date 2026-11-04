<?php
// Apibendrintas nario profilis treneriams: viskas apie narį viename lange (iš pokalbio, lankomumo, sąrašų).
// Atidaroma paspaudus vardą su data-member-profile (assets/chat.js), turinys - pokalbis.php?profile=ID.

/** Profilio HTML arba '' (narys nerastas) */
function member_profile_html(int $memberId): string
{
    $m = q_one('SELECT m.*, g.name AS group_name, g.id AS gid FROM members m LEFT JOIN training_groups g ON g.id = m.group_id WHERE m.id = ?', [$memberId]);
    if (!$m) {
        return '';
    }
    $belt = $m['belt_level'] !== null ? (int) $m['belt_level'] : null;
    [$sFrom, $sTo] = season_bounds();
    $season = member_points_total($memberId, $sFrom, $sTo);
    $lvl = xp_level(member_points_total($memberId));
    $month = member_attendance($memberId, date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
    $last = q_value('SELECT MAX(training_date) FROM attendance WHERE member_id = ? AND present = 1', [$memberId]);

    // Kito diržo programa: kiek punktų patvirtinta
    $next = next_belt($belt);
    $reqTotal = $next ? (int) q_value('SELECT COUNT(*) FROM belt_requirements WHERE belt_level = ?', [$next]) : 0;
    $done = requirement_done_ids($memberId);
    $reqDone = $next && $done ? (int) q_value('SELECT COUNT(*) FROM belt_requirements WHERE belt_level = ? AND id IN (' . implode(',', array_map('intval', array_keys($done))) . ')', [$next]) : 0;

    $accounts = q_all('SELECT a.first_name, a.last_name, a.email, a.phone, a.status, am.relation FROM account_members am JOIN accounts a ON a.id = am.account_id
                        WHERE am.member_id = ? ORDER BY am.relation = "self" DESC, a.first_name', [$memberId]);
    $events = q_all('SELECT e.id, e.title, e.starts_on, ep.status FROM event_participants ep JOIN events e ON e.id = ep.event_id
                      WHERE ep.member_id = ? AND e.starts_on >= CURDATE() ORDER BY e.starts_on LIMIT 4', [$memberId]);
    $note = q_one('SELECT body, created_at FROM coach_notes WHERE member_id = ? ORDER BY created_at DESC LIMIT 1', [$memberId]);
    $tags = member_tags($memberId);

    $initials = mb_substr($m['first_name'], 0, 1) . mb_substr($m['last_name'], 0, 1);
    $facts = [];
    if ($m['birth_date']) {
        $facts[] = age_on($m['birth_date']) . ' m.';
    }
    $facts[] = $m['group_name'] ? group_short_name(['name' => $m['group_name']]) : t('be grupės');
    if ($m['status'] !== 'active') {
        $facts[] = $m['status'] === 'pending' ? 'laukia patvirtinimo' : 'neaktyvus';
    }

    $html = '<div class="mp"><div class="mp-head"><span class="mp-avatar">' . e($initials) . '</span><div>'
        . '<h3 id="mpTitle">' . e($m['first_name'] . ' ' . $m['last_name']) . '</h3>'
        . '<div class="mp-sub">' . e(implode(' · ', $facts)) . '</div>'
        . '<div class="mp-chips">' . belt_chip($belt)
        . implode('', array_map(function ($t) { return ' <span class="badge badge-tag">' . e(MEMBER_TAGS[$t] ?? $t) . '</span>'; }, $tags))
        . ($m['photo_consent'] ? '' : (' <span class="badge badge-warn" title="' . e(t('Nuotraukų su šiuo nariu neskelbti')) . '">' . t('📷 nesutinka') . '</span>')) . '</div></div></div>';

    $html .= '<div class="mp-stats">'
        . '<div><strong>' . $lvl['level'] . ('</strong><span>' . t('lygis ·') . ' ') . $lvl['xp'] . ' XP</span></div>'
        . '<div><strong>' . $season . ('</strong><span>' . t('tšk. šį sezoną') . '</span></div>')
        . '<div><strong>' . $month['buvo'] . ($month['buvo'] + $month['nebuvo'] ? '/' . ($month['buvo'] + $month['nebuvo']) : '') . ('</strong><span>' . t('treniruotės per 30 d.') . '</span></div>')
        . ($next ? '<div><strong>' . $reqDone . '/' . $reqTotal . '</strong><span>' . e(BELTS[$next][0]) . ' programa</span></div>' : '')
        . '</div>';
    if ($last) {
        $html .= ('<p class="mp-line">' . t('Paskutinį kartą treniruotėje:') . ' <strong>') . e(fmt_date($last)) . '</strong></p>';
    }

    $html .= ('<div class="mp-sec"><div class="kicker">' . t('Kontaktai') . '</div>');
    if (!$accounts) {
        $html .= ('<p class="muted small">' . t('Paskyros nėra.') . '</p>');
    }
    foreach ($accounts as $acc) {
        $html .= '<div class="mp-contact"><strong>' . e($acc['first_name'] . ' ' . $acc['last_name']) . '</strong> <span class="muted small">' . ($acc['relation'] === 'parent' ? t('tėvai') : 'pats')
            . ($acc['status'] !== 'active' ? (' ' . t('· paskyra nepatvirtinta')) : '') . '</span>'
            . '<div class="small"><a href="mailto:' . e($acc['email']) . '">' . e($acc['email']) . '</a>'
            . ($acc['phone'] ? ' · <a href="tel:' . e(preg_replace('/[^\d+]/', '', $acc['phone'])) . '">' . e($acc['phone']) . '</a>' : '') . '</div></div>';
    }
    $html .= '</div>';

    if ($events) {
        $html .= ('<div class="mp-sec"><div class="kicker">' . t('Renginiai') . '</div><ul class="mp-list">');
        foreach ($events as $ev) {
            $html .= '<li><a href="' . e(url('renginys.php?id=' . (int) $ev['id'])) . '">' . e($ev['title']) . '</a> <span class="muted small">· ' . e(fmt_date($ev['starts_on']))
                . ' · ' . e(PARTICIPANT_STATUS[$ev['status']] ?? $ev['status']) . '</span></li>';
        }
        $html .= '</ul></div>';
    }
    if ($note) {
        $html .= ('<div class="mp-sec"><div class="kicker">' . t('Paskutinė pastaba ·') . ' ') . e(fmt_date(substr($note['created_at'], 0, 10))) . '</div>'
            . '<p class="small mp-note">' . e(mb_strimwidth(preg_replace('~\s+~u', ' ', $note['body']), 0, 220, '…')) . '</p></div>';
    }

    $html .= '<div class="mp-actions">'
        . '<a class="btn btn-primary btn-sm" href="' . e(url('admin/nariai.php?id=' . $memberId)) . '">Visa kortelė →</a>'
        . '<button type="button" class="btn btn-ghost btn-sm" data-open-chat data-member="' . $memberId . '">💬 Pokalbis</button>'
        . ($m['gid'] ? '<a class="btn btn-ghost btn-sm" href="' . e(url('admin/treniruote.php?g=' . (int) $m['gid'])) . '">Lankomumas</a>' : '')
        . '</div></div>';
    return $html;
}
