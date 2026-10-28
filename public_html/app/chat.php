<?php
// Pokalbiai su Deniu (langelis apačioje dešinėje, assets/chat.js). Nariui - vienas pokalbis su Deniu (ir treneriais),
// treneriams - visi pokalbiai: kairėje žmonės su paieška, dešinėje pokalbis. Komentarai prie renginių, naujienų ir pamokų
// matosi tame pačiame pokalbyje su nuoroda, kur palikti; Denis gauna pranešimą ir gali atsakyti ten pat (prie įrašo) arba pokalbyje.
// Anoniminis komentaras: Denis mato, kas parašė, bet paviešinus kitiems vardas nerodomas.

// Puslapių pavadinimai: kur paliktas komentaras iš pokalbio langelio
const CHAT_PAGE_NAMES = [
    'index.php' => 'Pagrindinis puslapis', 'renginiai.php' => 'Renginiai', 'naujienos.php' => 'Naujienos', 'pamokos.php' => 'Pamokos',
    'paskyra.php' => 'Mano paskyra', 'egzaminas.php' => 'Egzaminas', 'mano-renginiai.php' => 'Renginiai', 'testas.php' => 'Testas',
    'varzybos.php' => 'Varžybos', 'privatumas.php' => 'Privatumo politika',
];

/** Iš puslapio adreso: renginio / naujienos / pamokos kontekstas ['event_id' => 5] arba [] */
function chat_page_context(string $page): array
{
    if (preg_match('~(renginys|naujienos|pamokos)\.php\?(?:.*&)?id=(\d+)~', $page, $m)) {
        return [['renginys' => 'event_id', 'naujienos' => 'news_id', 'pamokos' => 'lesson_id'][$m[1]] => (int) $m[2]];
    }
    return [];
}

/** Kur parašyta žinutė: ['key' => 'e12' | 'l3' | 'w5' | 'n7' | 'general', 'label' => 'Renginys: X', 'url' => santykinis adresas arba null] */
function message_context(array $r, bool $staffView = false): array
{
    if (!empty($r['note_id'])) {
        return ['key' => 'n' . $r['note_id'], 'label' => 'Atsakymas į trenerio pastabą',
            'url' => $staffView ? 'admin/zinutes.php?m=' . (int) $r['member_id'] . '#n' . $r['note_id'] : 'paskyra.php?m=' . (int) $r['member_id'] . '#n' . $r['note_id']];
    }
    if (!empty($r['lesson_id'])) {
        return ['key' => 'l' . $r['lesson_id'], 'label' => 'Pamoka: ' . ($r['lesson_title'] ?? ''), 'url' => 'pamokos.php?id=' . (int) $r['lesson_id'] . '#q-' . (int) $r['member_id']];
    }
    if (!empty($r['event_id'])) {
        return ['key' => 'e' . $r['event_id'], 'label' => 'Renginys: ' . ($r['event_title'] ?? ''), 'url' => 'renginys.php?id=' . (int) $r['event_id'] . '#q-' . (int) $r['member_id']];
    }
    if (!empty($r['news_id'])) {
        return ['key' => 'w' . $r['news_id'], 'label' => 'Naujiena: ' . ($r['news_title'] ?? ''), 'url' => 'naujienos.php?id=' . (int) $r['news_id'] . '#q-' . (int) $r['member_id']];
    }
    $page = (string) ($r['page'] ?? '');
    $file = basename((string) parse_url($page, PHP_URL_PATH)) ?: 'index.php';
    return ['key' => 'general', 'label' => $page !== '' ? 'Parašyta: ' . (CHAT_PAGE_NAMES[$file] ?? $file) : '', 'url' => null];
}

/** Kas rašė: treneriui - vardas, pavardė ir ryšys su nariu („tėvai“), nariui - „Jūs“ / vardas */
function chat_author(array $r, bool $staffView, array $relations = []): string
{
    $who = message_author($r, $staffView);
    if ($staffView && !$r['from_staff'] && ($relations[(int) $r['author_id']] ?? '') === 'parent') {
        $who .= ' (tėvai)';
    }
    return $who;
}

/** Žinutė pokalbio langeliui (JSON) */
function chat_payload(array $r, bool $staffView, array $relations = []): array
{
    $c = message_context($r, $staffView);
    return [
        'id'    => (int) $r['id'],
        'staff' => (bool) $r['from_staff'],
        'who'   => chat_author($r, $staffView, $relations),
        'when'  => notification_when($r['created_at']),
        'body'  => $r['body'],
        'ctx'   => $c['label'],
        'key'   => $c['key'],
        'url'   => $c['url'] ? url($c['url']) : null,
        'anon'  => !empty($r['anonymous']),
        'pub'   => !empty($r['is_public']),
        'name'  => !empty($r['show_name']),
        'new'   => ($staffView ? !$r['from_staff'] : (bool) $r['from_staff']) && $r['read_at'] === null,
    ];
}

/** Visos vieno nario žinutės (visos temos), paskutinės $limit */
function chat_thread(int $memberId, bool $staffView, int $limit = 120): array
{
    $rows = array_slice(message_rows('mm.member_id = ?', [$memberId]), -$limit);
    $rel = $staffView ? q('SELECT account_id, relation FROM account_members WHERE member_id = ?', [$memberId])->fetchAll(PDO::FETCH_KEY_PAIR) : [];
    return array_map(function ($r) use ($staffView, $rel) { return chat_payload($r, $staffView, $rel); }, $rows);
}

/** Treneriui: pokalbiai (naujausi viršuje) + paieška; rasti nariai be pokalbio - „Pradėti pokalbį“ */
function chat_conversations(string $search = ''): array
{
    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
    $where = $search !== '' ? ' AND CONCAT(m.first_name, " ", m.last_name) LIKE ?' : '';
    $rows = q_all('SELECT m.id, m.first_name, m.last_name, g.name AS group_name, MAX(mm.created_at) AS last_at,
                          SUM(mm.from_staff = 0 AND mm.read_at IS NULL) AS unread,
                          (SELECT x.body FROM member_messages x WHERE x.member_id = m.id ORDER BY x.created_at DESC, x.id DESC LIMIT 1) AS last_body,
                          (SELECT x.from_staff FROM member_messages x WHERE x.member_id = m.id ORDER BY x.created_at DESC, x.id DESC LIMIT 1) AS last_staff
                     FROM member_messages mm JOIN members m ON m.id = mm.member_id LEFT JOIN training_groups g ON g.id = m.group_id
                    WHERE 1' . $where . ' GROUP BY m.id ORDER BY last_at DESC LIMIT 200', $search !== '' ? [$like] : []);
    $out = [];
    foreach ($rows as $r) {
        $out[] = ['id' => (int) $r['id'], 'name' => $r['first_name'] . ' ' . $r['last_name'], 'group' => group_short_name(['name' => (string) $r['group_name']]),
            'last' => ($r['last_staff'] ? 'Jūs: ' : '') . mb_strimwidth(preg_replace('~\s+~u', ' ', (string) $r['last_body']), 0, 70, '…'),
            'when' => notification_when($r['last_at']), 'unread' => (int) $r['unread']];
    }
    $start = [];
    if ($search !== '') {
        $has = array_column($out, 'id');
        foreach (q_all('SELECT m.id, m.first_name, m.last_name, g.name AS group_name FROM members m LEFT JOIN training_groups g ON g.id = m.group_id
                         WHERE m.status = "active" AND CONCAT(m.first_name, " ", m.last_name) LIKE ? ORDER BY m.first_name, m.last_name LIMIT 15', [$like]) as $r) {
            if (!in_array((int) $r['id'], $has, true)) {
                $start[] = ['id' => (int) $r['id'], 'name' => $r['first_name'] . ' ' . $r['last_name'], 'group' => group_short_name(['name' => (string) $r['group_name']])];
            }
        }
    }
    return ['list' => $out, 'start' => $start];
}

/** Kas rašo nariui (pokalbio antraštei): grupė ir paskyros („Rasa (tėvai)“) */
function chat_member_info(int $memberId): array
{
    $m = q_one('SELECT m.*, g.name AS group_name FROM members m LEFT JOIN training_groups g ON g.id = m.group_id WHERE m.id = ?', [$memberId]);
    if (!$m) {
        return [];
    }
    $accs = array_map(function ($a) { return $a['first_name'] . ($a['relation'] === 'parent' ? ' (tėvai)' : ''); },
        q_all('SELECT a.first_name, am.relation FROM account_members am JOIN accounts a ON a.id = am.account_id WHERE am.member_id = ? AND a.status = "active"', [$memberId]));
    $parts = [$m['group_name'] ? group_short_name(['name' => $m['group_name']]) : 'be grupės', $accs ? 'rašo: ' . implode(', ', $accs) : 'paskyros nėra'];
    return ['id' => (int) $m['id'], 'name' => $m['first_name'] . ' ' . $m['last_name'], 'sub' => implode(' · ', $parts),
        'card' => url('admin/nariai.php?id=' . (int) $m['id'])];
}

/** Trenerio atsakymas: į bendrą pokalbį arba toje pačioje vietoje (renginys, naujiena, pamoka, pastaba) */
function chat_staff_reply(int $memberId, array $coach, string $body, string $ctx): int
{
    $ids = ['note_id' => null, 'lesson_id' => null, 'event_id' => null, 'news_id' => null];
    if (preg_match('/^([nlew])(\d+)$/', $ctx, $m)) {
        $ids[['n' => 'note_id', 'l' => 'lesson_id', 'e' => 'event_id', 'w' => 'news_id'][$m[1]]] = (int) $m[2];
    }
    if ($ids['note_id'] && !q_value('SELECT 1 FROM coach_notes WHERE id = ? AND member_id = ?', [$ids['note_id'], $memberId])) {
        $ids['note_id'] = null;
    }
    // Ar narys gavo kitą atsakymą per pastarąsias 20 min. - tada laiško nebesiunčiame (pokalbyje rašoma po kelias žinutes)
    $recent = (int) q_value('SELECT COUNT(*) FROM member_messages WHERE member_id = ? AND from_staff = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 20 MINUTE)', [$memberId]);
    $id = add_member_message($memberId, (int) $coach['id'], true, $body, $ids['note_id'], $ids['lesson_id'], $ids['event_id'], $ids['news_id']);
    mark_member_messages_read($memberId);
    comment_notifications_done($memberId);
    if (!$recent) {
        $member = q_one('SELECT * FROM members WHERE id = ?', [$memberId]);
        if ($member) {
            notify_member_reply($member, $coach, $body);
        }
    }
    return $id;
}

/** Naujas komentaras prie renginio / naujienos / pamokos (ar anoniminis) - pranešimas Deniui su nuoroda ten, kur paliktas */
function notify_comment(array $member, array $from, int $messageId): void
{
    $r = message_rows('mm.id = ?', [$messageId])[0] ?? null;
    if (!$r) {
        return;
    }
    $c = message_context($r, true);
    $who = $from['first_name'] . ' ' . $from['last_name'] . ((int) $from['id'] && account_relation((int) $from['id'], (int) $member['id']) === 'parent' ? ' (' . $member['first_name'] . ' tėvai)' : '');
    $title = ($r['anonymous'] ? 'Anoniminis komentaras' : 'Komentaras') . ($c['key'] !== 'general' ? ' · ' . $c['label'] : ($c['label'] ? ' · ' . str_replace('Parašyta: ', '', $c['label']) : ''));
    try {
        notify_admins('comment', $title, $who . ', ' . notification_when($r['created_at']) . ': ' . mb_strimwidth(preg_replace('~\s+~u', ' ', $r['body']), 0, 220, '…'),
            $c['url'] ?: 'admin/zinutes.php?m=' . (int) $member['id'], (int) $member['id'], $messageId);
    } catch (PDOException $ex) {
        // dar neimportuotas db/021
    }
}

/** Atsakius nariui - jo komentarų pranešimai išsprendžiami */
function comment_notifications_done(int $memberId): void
{
    try {
        q('UPDATE notifications SET done_at = NOW(), read_at = COALESCE(read_at, NOW()) WHERE type = "comment" AND member_id = ? AND done_at IS NULL', [$memberId]);
    } catch (PDOException $ex) {
        // dar neimportuotas db/021
    }
}

/** Kiek naujų žinučių (skaičiukas ant pokalbio mygtuko): treneriui - nuo narių, nariui - nuo trenerių */
function chat_unread_count(array $a): int
{
    if (is_staff($a)) {
        return staff_unread_messages();
    }
    try {
        return (int) q_value('SELECT COUNT(*) FROM member_messages mm JOIN account_members am ON am.member_id = mm.member_id
                               WHERE am.account_id = ? AND mm.from_staff = 1 AND mm.read_at IS NULL', [(int) $a['id']]);
    } catch (PDOException $ex) {
        return 0;
    }
}

/**
 * Pokalbio langelis apačioje dešinėje. Nariams - pokalbis su Deniu ir anoniminis komentaras,
 * treneriams - visi pokalbiai (Messenger stiliumi). Logika - assets/chat.js.
 */
function chat_widget_html(): string
{
    $a = current_account();
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (!$a || !is_active_account($a) || in_array($script, ['testas.php', 'pranesimas.php'], true)) {
        return '';
    }
    try {
        $unread = chat_unread_count($a);
        q_value('SELECT anonymous FROM member_messages LIMIT 1');
    } catch (PDOException $ex) {
        return '';   // dar neimportuotas db/022
    }
    $icon = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-4.6 3.6A.9.9 0 0 1 3 20.9V6a2 2 0 0 1 1-2Zm3 6.2a1.3 1.3 0 1 0 0 2.6 1.3 1.3 0 0 0 0-2.6Zm5 0a1.3 1.3 0 1 0 0 2.6 1.3 1.3 0 0 0 0-2.6Zm5 0a1.3 1.3 0 1 0 0 2.6 1.3 1.3 0 0 0 0-2.6Z"/></svg>';
    $badge = '<span class="count-badge"' . ($unread ? '' : ' hidden') . '>' . $unread . '</span>';
    $form = '<form class="chat-form"><textarea name="body" rows="1" maxlength="' . MESSAGE_MAX . '" placeholder="Rašykite žinutę…" required></textarea>'
        . '<button class="chat-send" type="submit" aria-label="Siųsti"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M3.4 20.4 21 12 3.4 3.6 3.3 10l12.6 2-12.6 2z"/></svg></button></form>';

    if (is_staff($a)) {
        $cfg = ['api' => url('pokalbis.php'), 'csrf' => csrf_token(), 'staff' => true, 'pages' => url('klausimai.php')];
        return '<div class="chat staff" id="chat" data-chat="' . e(json_encode($cfg)) . '">'
            . '<button type="button" class="chat-fab" id="chatFab" aria-expanded="false" aria-controls="chatPanel" title="Pokalbiai su nariais">' . $icon
            . '<span class="chat-fab-label">Žinutės</span>' . $badge . '</button>'
            . '<div class="chat-panel wide" id="chatPanel" role="dialog" aria-label="Pokalbiai su nariais" hidden>'
            . '<aside class="chat-side"><div class="chat-side-head"><strong>Pokalbiai</strong><button type="button" class="chat-close" aria-label="Uždaryti">×</button></div>'
            . '<input type="search" class="chat-search" placeholder="Ieškoti žmogaus…" aria-label="Ieškoti žmogaus" autocomplete="off">'
            . '<div class="chat-list" role="list"><p class="chat-empty">Kraunama…</p></div></aside>'
            . '<section class="chat-main"><header class="chat-head"><button type="button" class="chat-back" aria-label="Atgal į pokalbius">‹</button>'
            . '<span class="chat-avatar" aria-hidden="true">?</span><div><strong class="chat-title">Pasirinkite pokalbį</strong><span class="chat-sub"></span></div>'
            . '<a class="chat-card" href="#" hidden>Kortelė</a><button type="button" class="chat-close" aria-label="Uždaryti">×</button></header>'
            . '<div class="chat-thread" aria-live="polite"><p class="chat-empty">Kairėje pasirinkite žmogų arba suraskite paieškoje.</p></div>'
            . '<div class="chat-reply-ctx" hidden><span></span><button type="button" aria-label="Atsakyti bendrai">×</button></div>'
            . $form . '</section></div></div>';
    }

    $members = array_values(array_filter(account_members((int) $a['id']), function ($m) { return $m['status'] === 'active'; }));
    if (!$members) {
        return '';
    }
    $select = '';
    if (count($members) > 1) {
        $select = '<label class="chat-member">Dėl <select>';
        foreach ($members as $m) {
            $select .= '<option value="' . (int) $m['id'] . '">' . e($m['first_name']) . ($m['relation'] === 'self' ? ' (aš)' : '') . '</option>';
        }
        $select .= '</select></label>';
    }
    $cfg = ['api' => url('pokalbis.php'), 'send' => url('zinute.php'), 'csrf' => csrf_token(), 'member' => (int) $members[0]['id'], 'hint' => empty($a['chat_hint_at'])];
    return '<div class="chat" id="chat" data-chat="' . e(json_encode($cfg)) . '">'
        . '<button type="button" class="chat-fab" id="chatFab" aria-expanded="false" aria-controls="chatPanel" title="Parašyti Deniui">' . $icon
        . '<span class="chat-fab-label">Rašyti Deniui</span>' . $badge . '</button>'
        . '<div class="chat-hint" id="chatHint" hidden><span>👋 Turite klausimą? Čia galite parašyti Deniui - jis atsakys čia pat.</span>'
        . '<button type="button" class="chat-hint-x" aria-label="Uždaryti">×</button></div>'
        . '<div class="chat-panel" id="chatPanel" role="dialog" aria-label="Pokalbis su Deniu" hidden>'
        . '<header class="chat-head"><span class="chat-avatar" aria-hidden="true">D</span><div><strong>Denis</strong><span class="chat-sub">Klubo vadovas · atsakys, kai tik galės</span></div>'
        . '<button type="button" class="chat-close" aria-label="Uždaryti">×</button></header>'
        . '<div class="chat-tabs" role="tablist"><button type="button" role="tab" class="active" data-tab="msg" aria-selected="true">Žinutė</button>'
        . '<button type="button" role="tab" data-tab="anon" aria-selected="false">Anoniminis komentaras</button></div>'
        . '<div class="chat-pane" data-pane="msg">' . $select
        . '<div class="chat-thread" aria-live="polite"><p class="chat-empty">Parašykite - atsakymas atsiras čia.</p></div>' . $form
        . '<p class="chat-note">Mato tik Denis (ir kiti treneriai).</p></div>'
        . '<div class="chat-pane" data-pane="anon" hidden><p class="chat-note">Pasiūlymas ar pastaba. Denis matys, kas parašė, ir galės atsakyti. '
        . 'Jei paviešins - kitiems nariams jūsų vardas nebus rodomas.</p>'
        . '<form class="chat-anon"><textarea name="body" rows="4" maxlength="' . MESSAGE_MAX . '" placeholder="Jūsų komentaras" required></textarea>'
        . '<button class="btn btn-primary btn-sm" type="submit">Siųsti anonimiškai</button></form>'
        . '<p class="chat-ok" hidden>Ačiū! Komentaras išsiųstas - atsakymą matysite pokalbyje.</p></div>'
        . '</div></div>';
}
