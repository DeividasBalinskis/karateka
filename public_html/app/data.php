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

/** Kam skirtas renginys: grupių vardai, „pakviestiems“ arba „visiems“ */
function event_audience_label(array $ev): string
{
    $groups = event_group_names((int) $ev['id']);
    return $groups ? implode(', ', $groups) : (!empty($ev['invited_only']) ? 'pakviestiems' : 'visiems');
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
        . '<div><span class="badge type-badge t-' . e($ev['type']) . '">' . e(EVENT_TYPES[$ev['type']]) . '</span>'
        . '<h3 style="margin-top:6px;">' . e($ev['title']) . '</h3>'
        . '<div class="muted small">' . e($when) . ($ev['location'] ? ' · ' . e($ev['location']) : '') . '</div>'
        . ($ev['description'] ? '<div class="small" style="margin-top:6px;">' . text_to_html($ev['description']) . '</div>' : '')
        . '</div></div>';
}

/**
 * "Redaguoti" renginiui viešuose puslapiuose (matomas tik treneriams).
 * $back - kur grįžti išsaugojus: 'renginiai' arba 'index'.
 */
function event_edit_link(int $id, string $back): string
{
    return staff_link('admin/renginiai.php?edit=' . $id . '&back=' . $back);
}

/** Treneriui prie renginio: redaguoti, dalyviai (dalyvaus / visi), rezultatai (prasidėjusiam) */
function event_staff_actions(array $ev, string $back): string
{
    if (!is_staff()) {
        return '';
    }
    $id = (int) $ev['id'];
    $pc = event_participant_counts($id);
    $html = '<div class="event-staff no-print"><a class="btn btn-ghost btn-sm" href="' . e(url('admin/renginiai.php?edit=' . $id . '&back=' . $back)) . '">✎ Redaguoti</a>'
        . '<a class="btn btn-ghost btn-sm" href="' . e(url('admin/renginio-dalyviai.php?id=' . $id)) . '">Dalyviai' . ($pc['going'] || $pc['invited'] ? ' ' . $pc['going'] . '/' . ($pc['going'] + $pc['invited']) : '') . '</a>';
    if ($ev['type'] === 'competition' && varzybos_enabled()) {
        $html .= '<a class="btn btn-ghost btn-sm" href="' . e(url(!empty($ev['tournament_id']) ? 'admin/varzybu-dalyviai.php?id=' . $id : 'admin/varzybu-rezultatai.php?id=' . $id)) . '">'
            . (!empty($ev['tournament_id']) ? '🥋 Varžybų sistema' : '🔗 Susieti su varžybomis') . '</a>';
    }
    if (isset(EVENT_RESULTS[$ev['type']]) && $ev['starts_on'] <= date('Y-m-d')) {
        $html .= '<a class="btn btn-primary btn-sm" href="' . e(url('admin/renginio-rezultatai.php?id=' . $id)) . '">Rezultatai</a>';
    }
    return $html . '</div>';
}

// ---------- Svetainės nustatymai (db/011) ----------

/** Nustatymas iš site_settings; jei lentelės dar nėra (neimportuotas 011) - $default */
function site_setting(string $name, ?string $default = null): ?string
{
    static $cache = [];
    if (!array_key_exists($name, $cache)) {
        try {
            $v = q_value('SELECT value FROM site_settings WHERE name = ?', [$name]);
            $cache[$name] = $v === false || $v === null ? $default : (string) $v;
        } catch (PDOException $ex) {
            $cache[$name] = $default;
        }
    }
    return $cache[$name];
}

function set_site_setting(string $name, string $value): void
{
    q('INSERT INTO site_settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$name, $value]);
}

// ---------- Reakcijos į naujienas ----------

/** Emoji, kuriuos nariai gali pasirinkti (nustato adminas) */
function allowed_reactions(): array
{
    $list = array_values(array_filter(explode(' ', (string) site_setting('reactions', implode(' ', REACTIONS)))));
    return $list ?: REACTIONS;
}

/** Panaudoti emoji su skaičiais: [emoji => count], daugiausiai paspaustas pirmas. Rodomi ir vėliau išjungti emoji. */
function news_reaction_counts(int $newsId): array
{
    $order = array_flip(allowed_reactions());
    $rows = q_all('SELECT emoji, COUNT(*) c FROM reactions WHERE news_id = ? GROUP BY emoji', [$newsId]);
    usort($rows, function ($x, $y) use ($order) {
        return [(int) $y['c'], $order[$x['emoji']] ?? 99] <=> [(int) $x['c'], $order[$y['emoji']] ?? 99];
    });
    $counts = [];
    foreach ($rows as $r) {
        $counts[$r['emoji']] = (int) $r['c'];
    }
    return $counts;
}

/** Jau panaudotų emoji mygtukai. Paspaudus - prisijungęs narys prideda / nuima savo reakciją. */
function render_reaction_chips(int $newsId, ?string $mine): string
{
    $allowed = allowed_reactions();
    $html = '';
    foreach (news_reaction_counts($newsId) as $emoji => $count) {
        $label = e($emoji) . '<span class="count">' . $count . '</span>';
        if (in_array($emoji, $allowed, true) || $mine === $emoji) {
            $html .= '<button type="submit" name="emoji" value="' . e($emoji) . '" class="reaction' . ($mine === $emoji ? ' mine' : '') . '"'
                . ' aria-label="' . e($emoji) . ' ' . $count . ($mine === $emoji ? ' (jūsų - paspaudus nuimama)' : '') . '">' . $label . '</button>';
        } else {
            // Adminas šį emoji išjungė: reakcijos lieka matomos, bet naujų pridėti nebegalima
            $html .= '<span class="reaction off" title="Šis emoji nebenaudojamas">' . $label . '</span>';
        }
    }
    return $html;
}

/** Reakcijų juosta: panaudoti emoji su skaičiais ir mygtukas leidžiamų emoji sąrašui atidaryti */
function render_reactions(int $newsId): string
{
    $a = current_account();
    $mine = $a ? q_value('SELECT emoji FROM reactions WHERE news_id = ? AND account_id = ?', [$newsId, $a['id']]) : false;
    $mine = $mine === false || $mine === null ? null : (string) $mine;
    $picker = '';
    foreach (allowed_reactions() as $emoji) {
        $picker .= '<button type="submit" name="emoji" value="' . e($emoji) . '" class="pick' . ($mine === $emoji ? ' mine' : '') . '" role="menuitem" aria-label="Reaguoti ' . e($emoji) . '">' . e($emoji) . '</button>';
    }
    return '<form class="reactions" method="post" action="' . url('reakcija.php') . '" data-news="' . $newsId . '">'
        . csrf_field() . '<input type="hidden" name="news_id" value="' . $newsId . '">'
        . '<span class="r-used">' . render_reaction_chips($newsId, $mine) . '</span>'
        . '<span class="r-add">'
        . '<button type="button" class="reaction-add" aria-haspopup="true" aria-expanded="false" aria-label="Pridėti reakciją" title="Pridėti reakciją">'
        . '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8"/>'
        . '<circle cx="9" cy="10" r="1.2" fill="currentColor"/><circle cx="15" cy="10" r="1.2" fill="currentColor"/>'
        . '<path d="M8.5 14.5c1 1.2 2.1 1.8 3.5 1.8s2.5-.6 3.5-1.8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>'
        . '<span class="plus" aria-hidden="true">+</span></button>'
        . '<span class="r-picker" role="menu">' . $picker . '</span>'
        . '</span></form>';
}

/**
 * "Dalintis": kompiuteryje atidaro Facebook dalinimosi langą, telefone - telefono dalinimosi meniu (Messenger, Viber, Facebook...).
 * Tik viešoms naujienoms (naujienų "tik nariams" Facebook nemato).
 */
function share_button(array $n): string
{
    if (!empty($n['members_only'])) {
        return '';
    }
    $link = abs_url('naujienos.php?id=' . (int) $n['id']);
    return '<a class="share-btn" href="https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($link) . '" target="_blank" rel="noopener"'
        . ' data-share-url="' . e($link) . '" data-share-title="' . e($n['title']) . '" title="Dalintis Facebook\'e ar kitur">'
        . '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M12 3v12M7.5 7.5 12 3l4.5 4.5M5 13v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
        . 'Dalintis</a>';
}

/** Naujienos viršelis: pirma nuotrauka arba YouTube paveikslėlis ($absolute - pilnas adresas Facebook'ui) */
function news_cover(int $newsId, bool $absolute = false): ?string
{
    $cover = null;
    foreach (news_media($newsId) as $m) {
        if ($m['type'] === 'image') {
            return $absolute ? abs_url('uploads/news/' . $m['file']) : news_image_url($m['file'], true);
        }
        if (!$cover && $m['type'] === 'youtube') {
            $cover = 'https://i.ytimg.com/vi/' . $m['youtube_id'] . '/hqdefault.jpg';
        }
    }
    return $cover;
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
    // Neatliktos užduotys - viršuje. Kartu: iš kur pastaba (treniruotės grupė ar renginys)
    return q_all('SELECT cn.*, a.first_name AS author, l.title AS lesson_title, g.name AS source_group, e.title AS source_event FROM coach_notes cn
                    LEFT JOIN accounts a ON a.id = cn.author_id LEFT JOIN lessons l ON l.id = cn.lesson_id
                    LEFT JOIN training_groups g ON g.id = cn.group_id LEFT JOIN events e ON e.id = cn.event_id
                   WHERE cn.member_id = ? ORDER BY (cn.is_task = 1 AND cn.done_at IS NULL) DESC, cn.note_date DESC, cn.id DESC LIMIT ' . (int) $limit, [$memberId]);
}

/** Iš kur pastaba: "Renginys: ...", "Treniruotė (grupė)" arba tik data (senos pastabos) */
function note_source(array $n): string
{
    $date = fmt_date($n['note_date'], true);
    if (!empty($n['source_event'])) {
        return 'Renginys: ' . e($n['source_event']) . ' · ' . e($date);
    }
    if (!empty($n['source_group'])) {
        return 'Treniruotė ' . e($date) . ' · ' . e($n['source_group']);
    }
    return e($date);
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
        // Teisėjų / egzaminatorių pamokas mato tik turintys tą žymą (nepriklausomai nuo diržo ir grupės)
        $tags = [];
        foreach (account_member_tags((int) $a['id']) as $memberTags) {
            $tags = array_merge($tags, $memberTags);
        }
        $tags = array_values(array_intersect(array_keys(MEMBER_TAGS), $tags));
        $sql .= ' AND l.is_published = 1 AND ((l.audience = "karateka" AND (
                    (l.belt_level IS NULL AND NOT EXISTS (SELECT 1 FROM lesson_groups lg WHERE lg.lesson_id = l.id))
                    OR EXISTS (SELECT 1 FROM members m JOIN account_members am ON am.member_id = m.id
                                WHERE am.account_id = ? AND m.status = "active"
                                  AND (NOT EXISTS (SELECT 1 FROM lesson_groups lg WHERE lg.lesson_id = l.id)
                                       OR EXISTS (SELECT 1 FROM lesson_groups lg WHERE lg.lesson_id = l.id AND lg.group_id = m.group_id))
                                  AND (l.belt_level IS NULL OR l.belt_level <= COALESCE(m.belt_level, 1) + 1))))'
            . ($tags ? ' OR l.audience IN ("' . implode('","', $tags) . '")' : '') . ')';
        $params[] = $a['id'];
    }
    if ($topic !== null) {
        $sql .= ' AND l.topic <=> ?';
        $params[] = $topic === '' ? null : $topic;
    }
    $rows = q_all($sql . ' ORDER BY l.sort_order, l.id', $params);
    if (is_staff($a)) {
        return $rows;
    }
    // Aplankas (ar aukštesnis aplankas) nepaskelbtas ar skirtas kitai žymai - jo pamokų nematyti
    $folders = lesson_folders();
    $tags = account_tag_list($a);
    return array_values(array_filter($rows, function ($l) use ($folders, $tags) {
        return $l['folder_id'] === null || folder_allowed($folders, (int) $l['folder_id'], $tags);
    }));
}

function lesson_videos(array $lesson): array
{
    return array_values(array_filter(explode(',', (string) $lesson['videos'])));
}

function lesson_topics(): array
{
    return q('SELECT DISTINCT topic FROM lessons WHERE topic IS NOT NULL AND topic <> "" ORDER BY topic')->fetchAll(PDO::FETCH_COLUMN);
}

// ---------- Matomumas: "tik nariams" ----------

/** Ar žiūrintysis yra patvirtintas narys (mato "tik nariams" renginius ir naujienas) */
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
    return (int) q_value('SELECT COUNT(*) FROM coach_notes WHERE member_id = ? AND (read_at IS NULL OR (is_task = 1 AND done_at IS NULL))', [$memberId])
        + member_unread_replies($memberId);   // ir nauji trenerio atsakymai
}

/** Tas pats visiems paskyros nariams (pats + vaikai) - ženkliukas prie "Mano paskyra" */
function account_attention_count(int $accountId): int
{
    static $cache = [];
    if (!isset($cache[$accountId])) {
        $cache[$accountId] = (int) q_value('SELECT COUNT(*) FROM coach_notes cn JOIN account_members am ON am.member_id = cn.member_id
                                             WHERE am.account_id = ? AND (cn.read_at IS NULL OR (cn.is_task = 1 AND cn.done_at IS NULL))', [$accountId])
            + account_unread_replies($accountId);
    }
    return $cache[$accountId];
}

/** Pirmas paskyros narys, kuriam yra neperskaitytų pastabų / neatliktų užduočių (nuorodai į jo paskyrą) */
function account_attention_member(int $accountId): ?int
{
    $id = q_value('SELECT cn.member_id FROM coach_notes cn JOIN account_members am ON am.member_id = cn.member_id
                    WHERE am.account_id = ? AND ((cn.is_task = 0 AND cn.read_at IS NULL) OR (cn.is_task = 1 AND cn.done_at IS NULL))
                    ORDER BY cn.note_date DESC LIMIT 1', [$accountId]);
    if ($id === false) {
        try {
            $id = q_value('SELECT mm.member_id FROM member_messages mm JOIN account_members am ON am.member_id = mm.member_id
                            WHERE am.account_id = ? AND mm.from_staff = 1 AND mm.read_at IS NULL ORDER BY mm.created_at DESC LIMIT 1', [$accountId]);
        } catch (PDOException $ex) {
            $id = false;
        }
    }
    return $id === false ? null : (int) $id;
}

/** Lankomumas laikotarpyje: ['buvo' => n, 'nebuvo' => n] */
function member_attendance(int $memberId, string $from, string $to): array
{
    $r = q_one('SELECT COALESCE(SUM(present = 1), 0) AS buvo, COALESCE(SUM(present = 0), 0) AS nebuvo FROM attendance
                 WHERE member_id = ? AND training_date BETWEEN ? AND ?', [$memberId, $from, $to]);
    return ['buvo' => (int) $r['buvo'], 'nebuvo' => (int) $r['nebuvo']];
}

function render_attendance(int $memberId): string
{
    [$sFrom, $sTo] = season_bounds();
    $m = member_attendance($memberId, date('Y-m-01'), date('Y-m-t'));
    $s = member_attendance($memberId, $sFrom, $sTo);
    if (!$s['buvo'] && !$s['nebuvo']) {
        return '<p class="muted small">Lankomumas dar nežymėtas.</p>';
    }
    $n = $m['buvo'] % 100;
    $word = ($n % 10 === 0 || ($n > 10 && $n < 20)) ? 'treniruočių' : ($n % 10 === 1 ? 'treniruotė' : 'treniruotės');
    return '<div class="att-summary"><span><strong>' . $m['buvo'] . '</strong> ' . $word . ' šį mėnesį'
        . ($m['nebuvo'] ? ' <span class="muted">(praleista ' . $m['nebuvo'] . ')</span>' : '') . '</span>'
        . '<span class="muted">Sezone: ' . $s['buvo'] . ' iš ' . ($s['buvo'] + $s['nebuvo']) . '</span></div>';
}

// ---------- Trenerio „Šiandien“ ----------

/** Papildomi „Reikia padaryti“ punktai iš kitų dalių: [[tekstas, kelias, mygtukas], ...] */
function admin_today_extra_todo(): array
{
    $todo = [];
    if ($n = staff_unread_messages()) {
        $todo[] = ['Naujos narių žinutės: ' . $n, 'admin/zinutes.php', 'Atsakyti'];
    }
    // Nauji testų rezultatai per savaitę
    $r = q_one('SELECT COUNT(*) c, COALESCE(SUM(passed), 0) p FROM quiz_attempts WHERE finished_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
    if ($r && (int) $r['c']) {
        $todo[] = ['Nauji testų rezultatai per savaitę: ' . (int) $r['c'] . ' (išlaikė ' . (int) $r['p'] . ')', 'admin/testai.php', 'Peržiūrėti'];
    }
    return $todo;
}

/** Renginio eilutė trenerio sąraše: vieta, laikas, kam skirta */
function admin_event_summary(array $ev): string
{
    $parts = array_filter([fmt_time($ev['start_time']), $ev['location'], event_audience_label($ev)]);
    return e(implode(' · ', $parts));
}

function admin_event_link(array $ev): string
{
    return 'admin/renginio-dalyviai.php?id=' . (int) $ev['id'];
}

/** „Dalyviai 5/12“: dalyvaus / visi pakviesti ir užsiregistravę */
function admin_event_link_label(array $ev): string
{
    $c = event_participant_counts((int) $ev['id']);
    return 'Dalyviai' . ($c['going'] || $c['invited'] ? ' ' . $c['going'] . '/' . ($c['going'] + $c['invited']) : '');
}

