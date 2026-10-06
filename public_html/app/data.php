<?php
// Dažnos užklausos: tvarkaraštis, renginiai, naujienos

function group_schedule(int $groupId): array
{
    return q_all('SELECT * FROM schedule WHERE group_id = ? ORDER BY weekday, start_time', [$groupId]);
}

/** Artėjantys (ar dar vykstantys) renginiai: skirti visiems arba nurodytai grupei. $groupId = null - visi renginiai. */
function upcoming_events(?int $groupId, int $limit = 20): array
{
    $sql = 'SELECT e.* FROM events e WHERE COALESCE(e.ends_on, e.starts_on) >= CURDATE()';
    $params = [];
    if ($groupId !== null) {
        $sql .= ' AND (NOT EXISTS (SELECT 1 FROM event_groups eg WHERE eg.event_id = e.id)
                    OR EXISTS (SELECT 1 FROM event_groups eg WHERE eg.event_id = e.id AND eg.group_id = ?))';
        $params[] = $groupId;
    }
    return q_all($sql . ' ORDER BY e.starts_on, e.start_time LIMIT ' . (int) $limit, $params);
}

/** Renginio grupių pavadinimai (tuščias masyvas = visiems) */
function event_group_names(int $eventId): array
{
    return q('SELECT g.name FROM event_groups eg JOIN training_groups g ON g.id = eg.group_id WHERE eg.event_id = ? ORDER BY g.sort_order', [$eventId])
        ->fetchAll(PDO::FETCH_COLUMN);
}

function render_schedule(array $rows): string
{
    if (!$rows) {
        return '<p class="muted">Tvarkaraštis dar nesudarytas.</p>';
    }
    $html = '';
    foreach ($rows as $r) {
        $time = $r['start_time'] ? fmt_time($r['start_time']) . '–' . fmt_time($r['end_time']) : '';
        $html .= '<div class="schedule-line"><span>' . e(LT_WEEKDAYS[(int) $r['weekday']])
            . ($r['note'] ? ' <span class="muted small">· ' . e($r['note']) . '</span>' : '')
            . '</span><span class="schedule-time">' . e($time) . '</span></div>';
    }
    return $html;
}

function render_event(array $ev): string
{
    $ts = strtotime($ev['starts_on']);
    $when = fmt_date($ev['starts_on']);
    if ($ev['ends_on'] && $ev['ends_on'] !== $ev['starts_on']) {
        $when .= ' – ' . fmt_date($ev['ends_on']);
    }
    if ($ev['start_time']) {
        $when .= ', ' . fmt_time($ev['start_time']);
    }
    return '<div class="event">'
        . '<div class="event-date"><span class="d">' . date('j', $ts) . '</span><span class="m">' . e(mb_substr(LT_MONTHS_GEN[(int) date('n', $ts)], 0, 3)) . '</span></div>'
        . '<div><span class="badge badge-pink">' . e(EVENT_TYPES[$ev['type']]) . '</span>'
        . '<h3 style="margin-top:6px;">' . e($ev['title']) . '</h3>'
        . '<div class="muted small">' . e($when) . ($ev['location'] ? ' · ' . e($ev['location']) : '') . '</div>'
        . ($ev['description'] ? '<div class="small" style="margin-top:6px;">' . text_to_html($ev['description']) . '</div>' : '')
        . '</div></div>';
}

/** Reakcijų skaičiai: [emoji => count] */
function news_reaction_counts(int $newsId): array
{
    $counts = array_fill_keys(REACTIONS, 0);
    foreach (q_all('SELECT emoji, COUNT(*) c FROM reactions WHERE news_id = ? GROUP BY emoji', [$newsId]) as $r) {
        if (isset($counts[$r['emoji']])) {
            $counts[$r['emoji']] = (int) $r['c'];
        }
    }
    return $counts;
}

function render_reactions(int $newsId): string
{
    $a = current_account();
    $mine = $a ? q_value('SELECT emoji FROM reactions WHERE news_id = ? AND account_id = ?', [$newsId, $a['id']]) : false;
    $html = '<form class="reactions" method="post" action="' . url('reakcija.php') . '" data-news="' . $newsId . '">'
        . csrf_field() . '<input type="hidden" name="news_id" value="' . $newsId . '">';
    foreach (news_reaction_counts($newsId) as $emoji => $count) {
        $html .= '<button type="submit" name="emoji" value="' . e($emoji) . '" class="reaction' . ($mine === $emoji ? ' mine' : '') . '"'
            . ' aria-label="Reaguoti ' . e($emoji) . '">' . $emoji . '<span class="count">' . ($count ?: '') . '</span></button>';
    }
    return $html . '</form>';
}

/** Naujienos medija */
function news_media(int $newsId): array
{
    return q_all('SELECT * FROM news_media WHERE news_id = ? ORDER BY sort_order, id', [$newsId]);
}

function news_image_url(string $file, bool $thumb = false): string
{
    return url('uploads/news/' . ($thumb ? 'thumb_' : '') . $file);
}

/** Trenerio pastabos nariui (naujausios viršuje) */
function member_notes(int $memberId, int $limit = 10): array
{
    return q_all('SELECT cn.*, a.first_name AS author FROM coach_notes cn LEFT JOIN accounts a ON a.id = cn.author_id
                   WHERE cn.member_id = ? ORDER BY cn.note_date DESC, cn.id DESC LIMIT ' . (int) $limit, [$memberId]);
}

function member_unread_notes(int $memberId): int
{
    return (int) q_value('SELECT COUNT(*) FROM coach_notes WHERE member_id = ? AND read_at IS NULL', [$memberId]);
}
