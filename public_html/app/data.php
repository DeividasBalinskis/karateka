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
    // Neatliktos užduotys - viršuje
    return q_all('SELECT cn.*, a.first_name AS author, l.title AS lesson_title FROM coach_notes cn
                    LEFT JOIN accounts a ON a.id = cn.author_id LEFT JOIN lessons l ON l.id = cn.lesson_id
                   WHERE cn.member_id = ? ORDER BY (cn.is_task = 1 AND cn.done_at IS NULL) DESC, cn.note_date DESC, cn.id DESC LIMIT ' . (int) $limit, [$memberId]);
}

function member_unread_notes(int $memberId): int
{
    return (int) q_value('SELECT COUNT(*) FROM coach_notes WHERE member_id = ? AND read_at IS NULL', [$memberId]);
}

// ---------- Pamokos ----------

/** Ar paskyra gali matyti pamokas (aktyvus narys arba treneris) */
function can_see_lessons(?array $a = null): bool
{
    return is_active_account($a ?? current_account());
}

/**
 * Pamokos, matomos šiai paskyrai. Pamoka matoma, jei bent vienas paskyros narys (pats ar vaikas):
 *  - yra pamokos grupėje (arba pamoka skirta visoms grupėms), IR
 *  - turi tą diržą arba vienu žemesnį (baltas mato ir geltono pamokas), arba pamoka skirta visiems diržams.
 * Diržo nenurodžius laikoma, kad narys baltas (9 kyu). Treneriai mato viską (ir nepaskelbtas).
 */
function visible_lessons(array $a, ?string $topic = null): array
{
    $params = [];
    $sql = 'SELECT l.* FROM lessons l WHERE 1';
    if (!is_staff($a)) {
        $sql .= ' AND l.is_published = 1 AND (
                    (l.belt_level IS NULL AND NOT EXISTS (SELECT 1 FROM lesson_groups lg WHERE lg.lesson_id = l.id))
                    OR EXISTS (SELECT 1 FROM members m JOIN account_members am ON am.member_id = m.id
                                WHERE am.account_id = ? AND m.status = "active"
                                  AND (NOT EXISTS (SELECT 1 FROM lesson_groups lg WHERE lg.lesson_id = l.id)
                                       OR EXISTS (SELECT 1 FROM lesson_groups lg WHERE lg.lesson_id = l.id AND lg.group_id = m.group_id))
                                  AND (l.belt_level IS NULL OR l.belt_level BETWEEN COALESCE(m.belt_level, 1) AND COALESCE(m.belt_level, 1) + 1)))';
        $params[] = $a['id'];
    }
    if ($topic !== null) {
        $sql .= ' AND l.topic <=> ?';
        $params[] = $topic === '' ? null : $topic;
    }
    return q_all($sql . ' ORDER BY l.belt_level IS NOT NULL, l.belt_level, l.topic, l.created_at DESC', $params);
}

function lesson_videos(array $lesson): array
{
    return array_values(array_filter(explode(',', (string) $lesson['videos'])));
}

function lesson_topics(): array
{
    return q('SELECT DISTINCT topic FROM lessons WHERE topic IS NOT NULL AND topic <> "" ORDER BY topic')->fetchAll(PDO::FETCH_COLUMN);
}

// ---------- Matomumas: „tik nariams“ ----------

/** Ar žiūrintysis yra patvirtintas narys (mato „tik nariams“ renginius ir naujienas) */
function viewer_is_member(): bool
{
    return is_active_account(current_account());
}

/** SQL sąlyga naujienoms/renginiams pagal žiūrintįjį (stulpelis members_only) */
function visibility_sql(string $alias = ''): string
{
    return viewer_is_member() ? '1' : ($alias ? "$alias." : '') . 'members_only = 0';
}

/** Artėjantys renginiai viešam pagrindiniam puslapiui */
function public_upcoming_events(int $limit = 4): array
{
    return q_all('SELECT e.* FROM events e WHERE COALESCE(e.ends_on, e.starts_on) >= CURDATE() AND ' . visibility_sql('e')
        . ' ORDER BY e.starts_on, e.start_time LIMIT ' . (int) $limit);
}

function members_only_badge(array $row): string
{
    return !empty($row['members_only']) ? ' <span class="badge badge-members">Tik nariams</span>' : '';
}

// ---------- Pranešimai apie pastabas ir užduotis ----------

/** Kiek nario pastabų reikia dėmesio: neperskaitytos + neatliktos užduotys */
function member_attention_count(int $memberId): int
{
    return (int) q_value('SELECT COUNT(*) FROM coach_notes WHERE member_id = ? AND (read_at IS NULL OR (is_task = 1 AND done_at IS NULL))', [$memberId]);
}

/** Tas pats visiems paskyros nariams (pats + vaikai) - ženkliukas prie „Mano paskyra“ */
function account_attention_count(int $accountId): int
{
    static $cache = [];
    if (!isset($cache[$accountId])) {
        $cache[$accountId] = (int) q_value('SELECT COUNT(*) FROM coach_notes cn JOIN account_members am ON am.member_id = cn.member_id
                                             WHERE am.account_id = ? AND (cn.read_at IS NULL OR (cn.is_task = 1 AND cn.done_at IS NULL))', [$accountId]);
    }
    return $cache[$accountId];
}

/** Pirmas paskyros narys, kuriam yra neperskaitytų pastabų / neatliktų užduočių (nuorodai į jo paskyrą) */
function account_attention_member(int $accountId): ?int
{
    $id = q_value('SELECT cn.member_id FROM coach_notes cn JOIN account_members am ON am.member_id = cn.member_id
                    WHERE am.account_id = ? AND ((cn.is_task = 0 AND cn.read_at IS NULL) OR (cn.is_task = 1 AND cn.done_at IS NULL))
                    ORDER BY cn.note_date DESC LIMIT 1', [$accountId]);
    return $id === false ? null : (int) $id;
}
