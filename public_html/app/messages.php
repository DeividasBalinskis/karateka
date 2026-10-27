<?php
// Nario ir trenerio žinutės: atsakymai į pastabas, klausimai prie pamokų, bendros žinutės.
// Nario žinutes mato tik treneriai; trenerio atsakymus - tik tas narys ir jo tėvai.

const MESSAGE_MAX = 2000;

// Prie ko klausiama: pamoka, renginys, naujiena => stulpelis
const MESSAGE_CONTEXTS = ['lesson' => 'lesson_id', 'event' => 'event_id', 'news' => 'news_id'];

function add_member_message(int $memberId, int $authorId, bool $fromStaff, string $body, ?int $noteId = null, ?int $lessonId = null,
                            ?int $eventId = null, ?int $newsId = null): int
{
    q('INSERT INTO member_messages (member_id, note_id, lesson_id, event_id, news_id, author_id, from_staff, body) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$memberId, $noteId, $lessonId, $eventId, $newsId, $authorId, $fromStaff ? 1 : 0, mb_substr($body, 0, MESSAGE_MAX)]);
    return (int) db()->lastInsertId();
}

function message_rows(string $where, array $params): array
{
    return q_all('SELECT mm.*, a.first_name AS author_first, a.last_name AS author_last, l.title AS lesson_title, ev.title AS event_title, nw.title AS news_title,
                         m.first_name AS member_first, m.last_name AS member_last
                    FROM member_messages mm LEFT JOIN accounts a ON a.id = mm.author_id LEFT JOIN lessons l ON l.id = mm.lesson_id
                    LEFT JOIN events ev ON ev.id = mm.event_id LEFT JOIN news nw ON nw.id = mm.news_id LEFT JOIN members m ON m.id = mm.member_id
                   WHERE ' . $where . ' ORDER BY mm.created_at, mm.id', $params);
}

/** Pokalbio tema: 'l12' pamoka, 'e5' renginys, 'w3' naujiena, 'general' - bendros žinutės */
function message_topic_key(array $r): string
{
    return $r['lesson_id'] ? 'l' . $r['lesson_id'] : ($r['event_id'] ?? null ? 'e' . $r['event_id'] : ($r['news_id'] ?? null ? 'w' . $r['news_id'] : 'general'));
}

/** Temos pavadinimas su nuoroda */
function message_topic_title(string $key, array $first): string
{
    switch ($key[0]) {
        case 'l':
            return '📘 Pamoka: <a href="' . e(url('pamokos.php?id=' . (int) substr($key, 1))) . '">' . e($first['lesson_title'] ?? 'pamoka') . '</a>';
        case 'e':
            return '📅 Renginys: <a href="' . e(url('renginys.php?id=' . (int) substr($key, 1))) . '">' . e($first['event_title'] ?? 'renginys') . '</a>';
        case 'w':
            return '📰 Naujiena: <a href="' . e(url('naujienos.php?id=' . (int) substr($key, 1))) . '">' . e($first['news_title'] ?? 'naujiena') . '</a>';
    }
    return 'Bendros žinutės';
}

/** Paslėpti laukai atsakymui toje pačioje temoje */
function message_topic_hidden(string $key): array
{
    $map = ['l' => 'lesson_id', 'e' => 'event_id', 'w' => 'news_id'];
    return $key !== 'general' && isset($map[$key[0]]) ? [$map[$key[0]] => (int) substr($key, 1)] : [];
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

/** Žinutės ne prie pastabų (prie pamokų, renginių, naujienų ir bendros), grupuotos: ['l12' => [...], 'e5' => [...], 'general' => [...]] */
function member_free_threads(int $memberId): array
{
    $out = [];
    foreach (message_rows('mm.member_id = ? AND mm.note_id IS NULL', [$memberId]) as $r) {
        $out[message_topic_key($r)][] = $r;
    }
    return $out;
}

/** Trenerio žinutės nariui perskaitytos (atidarius paskyrą, pamoką, renginį...). $column - tik tos temos (lesson_id / event_id / news_id) */
function mark_staff_messages_read(int $memberId, ?int $refId = null, string $column = 'lesson_id'): void
{
    $column = in_array($column, MESSAGE_CONTEXTS, true) ? $column : 'lesson_id';
    q('UPDATE member_messages SET read_at = NOW() WHERE member_id = ? AND from_staff = 1 AND read_at IS NULL' . ($refId ? " AND $column = " . (int) $refId : ''), [$memberId]);
}

/**
 * Viešai parodyti klausimai su atsakymais: [['q' => klausimas, 'answers' => [trenerio atsakymai]], ...].
 * Klausimą viešu padaro Denis; vardas rodomas tik jei jis taip pasirinko.
 */
function public_questions(string $column, int $refId): array
{
    try {
        $rows = message_rows("mm.$column = ?", [$refId]);
    } catch (PDOException $ex) {
        return [];   // dar neimportuotas db/021
    }
    $out = [];
    $open = [];   // member_id => indeksas paskutinio viešo klausimo, prie kurio dedami atsakymai
    foreach ($rows as $r) {
        $mid = (int) $r['member_id'];
        if (!$r['from_staff']) {
            if (!empty($r['is_public'])) {
                $out[] = ['q' => $r, 'answers' => []];
                $open[$mid] = count($out) - 1;
            } else {
                unset($open[$mid]);
            }
        } elseif (isset($open[$mid])) {
            $out[$open[$mid]]['answers'][] = $r;
        }
    }
    return array_values(array_filter($out, function ($x) { return $x['answers']; }));
}

/**
 * Klausimai Deniui prie renginio, naujienos ar pamokos. Narys mato tik savo klausimus ir atsakymus (ir viešai parodytus),
 * Denis - visų, ir gali atsakymą parodyti visiems.
 */
function questions_box(string $kind, int $refId, string $back): string
{
    $column = MESSAGE_CONTEXTS[$kind] ?? null;
    $a = current_account();
    if (!$column) {
        return '';
    }
    if (!$a) {
        return '<div class="panel card q-box" id="klausimai"><div class="kicker">💬 Klausimas Deniui</div><p class="small muted"><a href="'
            . e(url('prisijungti.php?r=' . rawurlencode(url($back)))) . '">Prisijunkite</a> - klausimą mato tik Denis.</p></div>';
    }
    $html = '';
    $public = public_questions($column, $refId);
    if ($public) {
        $html .= '<div class="q-public"><div class="kicker">Klausimai ir atsakymai</div>';
        foreach ($public as $x) {
            $html .= '<div class="q-item"><div class="q-q"><span class="q-who">' . e($x['q']['show_name'] ? trim($x['q']['member_first'] . ' ' . mb_substr((string) $x['q']['member_last'], 0, 1) . '.') : 'Narys') . ' klausė:</span> '
                . nl2br(e($x['q']['body'])) . '</div>';
            foreach ($x['answers'] as $ans) {
                $html .= '<div class="q-a"><span class="q-who">' . e(message_author($ans, false)) . ':</span> ' . nl2br(e($ans['body'])) . '</div>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';
    }

    if (is_staff($a)) {
        try {
            $threads = q_all("SELECT mm.member_id, m.first_name, m.last_name, COUNT(*) AS cnt, SUM(mm.from_staff = 0 AND mm.read_at IS NULL) AS unread
                                FROM member_messages mm JOIN members m ON m.id = mm.member_id WHERE mm.$column = ? GROUP BY mm.member_id ORDER BY MAX(mm.created_at) DESC", [$refId]);
        } catch (PDOException $ex) {
            $threads = [];
        }
        $html .= '<div class="kicker" style="margin-top:' . ($public ? '14px' : '0') . ';">Narių klausimai Deniui</div>';
        if (!$threads) {
            $html .= '<p class="small muted">Klausimų dar nėra.</p>';
        }
        $html .= '<ul class="list">';
        foreach ($threads as $t) {
            $html .= '<li class="row between"><span><strong>' . e($t['first_name'] . ' ' . $t['last_name']) . '</strong> <span class="small muted">' . (int) $t['cnt'] . ' žin.</span>'
                . ($t['unread'] ? ' <span class="badge badge-new">' . (int) $t['unread'] . ' nauj.</span>' : '') . '</span>'
                . '<a class="btn btn-ghost btn-sm" href="' . e(url('admin/zinutes.php?m=' . (int) $t['member_id'])) . '#' . ['lesson_id' => 'l', 'event_id' => 'e', 'news_id' => 'w'][$column] . $refId . '">Atsakyti →</a></li>';
        }
        $html .= '</ul><p class="hint">Atsakę galite atsakymą parodyti visiems (su vardu ar be) - pokalbyje spauskite „👁 Rodyti visiems“.</p>';
        return '<div class="panel card q-box" id="klausimai">' . $html . '</div>';
    }

    $members = is_active_account($a) ? array_values(array_filter(account_members((int) $a['id']), function ($m) { return $m['status'] === 'active'; })) : [];
    if (!$members) {
        return $html ? '<div class="panel card q-box" id="klausimai">' . $html . '</div>' : '';
    }
    $html .= '<div class="kicker"' . ($public ? ' style="margin-top:14px;"' : '') . '>💬 Klausimas Deniui</div>'
        . '<p class="small muted" style="margin-top:-4px;">Mato tik Denis. Atsakymą matysite čia ir paskyroje.</p>';
    foreach ($members as $m) {
        $rows = message_rows("mm.member_id = ? AND mm.$column = ?", [(int) $m['id'], $refId]);
        if ($rows) {
            if (count($members) > 1) {
                $html .= '<div class="msg-topic-title">' . e($m['first_name']) . '</div>';
            }
            $html .= '<div class="msg-thread">' . implode('', array_map(function ($r) { return render_message($r, false); }, $rows)) . '</div>';
        }
        mark_staff_messages_read((int) $m['id'], $refId, $column);
    }
    $form = '<form method="post" action="' . e(url('zinute.php')) . '" class="msg-form">' . csrf_field()
        . '<input type="hidden" name="' . $column . '" value="' . $refId . '"><input type="hidden" name="back" value="' . e($back) . '">';
    if (count($members) > 1) {
        $form .= '<select name="member_id" aria-label="Kieno vardu">';
        foreach ($members as $m) {
            $form .= '<option value="' . (int) $m['id'] . '">' . e($m['first_name']) . '</option>';
        }
        $form .= '</select>';
    } else {
        $form .= '<input type="hidden" name="member_id" value="' . (int) $members[0]['id'] . '">';
    }
    $html .= $form . '<textarea name="body" rows="2" maxlength="' . MESSAGE_MAX . '" placeholder="Jūsų klausimas ar komentaras Deniui" required></textarea>'
        . '<button class="btn btn-primary btn-sm" type="submit">Siųsti Deniui</button></form>';
    return '<div class="panel card q-box" id="klausimai">' . $html . '</div>';
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
    // Pokalbyje rašoma po kelias žinutes iš eilės - laiškas ne dažniau kaip kas 30 min. (kol ankstesnė neperskaityta)
    if ((int) q_value('SELECT COUNT(*) FROM member_messages WHERE member_id = ? AND from_staff = 0 AND read_at IS NULL
                         AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)', [(int) $member['id']]) > 1) {
        return;
    }
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

/**
 * Puslapis atsinaujina pats, kai atsiranda nauja žinutė (tikrinama kas 10 s, kol puslapis atidarytas).
 * Neperkrauna, jei pradėtas rašyti atsakymas.
 */
function live_refresh_js(string $path): string
{
    return '<script>(function () {
  var url = ' . json_encode(url($path)) . ', base = null;
  function typing() { return Array.prototype.some.call(document.querySelectorAll("textarea"), function (t) { return t.value.trim() !== ""; }); }
  function check() {
    if (document.hidden) return;
    fetch(url, { credentials: "same-origin" }).then(function (r) { return r.json(); }).then(function (d) {
      var key = d.last + ":" + d.anon;
      if (base === null) { base = key; return; }
      if (key !== base && !typing()) location.reload();
    }).catch(function () {});
  }
  check(); setInterval(check, 10000);
})();</script>';
}

/**
 * Pokalbio langelis apačioje dešinėje: prisijungę nariai ir tėvai rašo Deniui (bendros žinutės) arba palieka anoniminį komentarą.
 * Treneriams - nuoroda į visus pokalbius (Žinutės) su naujų skaičiumi. Logika - assets/site.js.
 */
function chat_widget_html(): string
{
    $a = current_account();
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (!$a || !is_active_account($a) || in_array($script, ['testas.php', 'zinutes.php', 'pranesimas.php'], true)) {
        return '';
    }
    $icon = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-4.6 3.6A.9.9 0 0 1 3 20.9V6a2 2 0 0 1 1-2Zm3 6.2a1.3 1.3 0 1 0 0 2.6 1.3 1.3 0 0 0 0-2.6Zm5 0a1.3 1.3 0 1 0 0 2.6 1.3 1.3 0 0 0 0-2.6Zm5 0a1.3 1.3 0 1 0 0 2.6 1.3 1.3 0 0 0 0-2.6Z"/></svg>';
    if (is_staff($a)) {
        $n = staff_unread_messages();
        return '<a class="chat-fab is-staff" href="' . e(url('admin/zinutes.php')) . '" title="Visi pokalbiai su nariais">' . $icon
            . '<span class="chat-fab-label">Žinutės</span>' . ($n ? '<span class="count-badge">' . $n . '</span>' : '') . '</a>';
    }
    $members = array_values(array_filter(account_members((int) $a['id']), function ($m) { return $m['status'] === 'active'; }));
    if (!$members) {
        return '';
    }
    try {
        $unread = (int) q_value('SELECT COUNT(*) FROM member_messages mm JOIN account_members am ON am.member_id = mm.member_id
                                  WHERE am.account_id = ? AND mm.from_staff = 1 AND mm.read_at IS NULL
                                    AND mm.note_id IS NULL AND mm.lesson_id IS NULL AND mm.event_id IS NULL AND mm.news_id IS NULL', [(int) $a['id']]);
        $hint = empty($a['chat_hint_at']);
    } catch (PDOException $ex) {
        return '';   // dar neimportuotas db/021
    }
    $select = '';
    if (count($members) > 1) {
        $select = '<label class="chat-member">Dėl <select>';
        foreach ($members as $m) {
            $select .= '<option value="' . (int) $m['id'] . '">' . e($m['first_name']) . ($m['relation'] === 'self' ? ' (aš)' : '') . '</option>';
        }
        $select .= '</select></label>';
    }
    $cfg = ['api' => url('pokalbis.php'), 'send' => url('zinute.php'), 'csrf' => csrf_token(), 'member' => (int) $members[0]['id'], 'hint' => $hint];
    return '<div class="chat" id="chat" data-chat="' . e(json_encode($cfg)) . '">'
        . '<button type="button" class="chat-fab" id="chatFab" aria-expanded="false" aria-controls="chatPanel" title="Parašyti Deniui">' . $icon
        . '<span class="chat-fab-label">Rašyti Deniui</span><span class="count-badge"' . ($unread ? '' : ' hidden') . '>' . $unread . '</span></button>'
        . '<div class="chat-hint" id="chatHint" hidden><span>👋 Turite klausimą? Čia galite parašyti Deniui - atsakymą matysite čia ir paskyroje.</span>'
        . '<button type="button" class="chat-hint-x" aria-label="Uždaryti">×</button></div>'
        . '<div class="chat-panel" id="chatPanel" role="dialog" aria-label="Žinutės Deniui" hidden>'
        . '<header class="chat-head"><span class="chat-avatar" aria-hidden="true">D</span><div><strong>Denis</strong><span>Klubo vadovas · atsakys, kai tik galės</span></div>'
        . '<button type="button" class="chat-close" aria-label="Uždaryti">×</button></header>'
        . '<div class="chat-tabs" role="tablist"><button type="button" role="tab" class="active" data-tab="msg" aria-selected="true">Žinutė</button>'
        . '<button type="button" role="tab" data-tab="anon" aria-selected="false">Anoniminis komentaras</button></div>'
        . '<div class="chat-pane" data-pane="msg">' . $select
        . '<div class="chat-thread" aria-live="polite"><p class="chat-empty">Parašykite - atsakymas atsiras čia.</p></div>'
        . '<form class="chat-form"><textarea name="body" rows="2" maxlength="' . MESSAGE_MAX . '" placeholder="Jūsų žinutė Deniui" required></textarea>'
        . '<button class="btn btn-primary btn-sm" type="submit">Siųsti</button></form>'
        . '<p class="chat-note">Mato tik Denis (ir kiti treneriai).</p></div>'
        . '<div class="chat-pane" data-pane="anon" hidden><p class="chat-note">Pasiūlymas, pastaba ar nusiskundimas. Kas parašė - neišsaugoma, todėl atsakyti negalėsime.</p>'
        . '<form class="chat-anon"><textarea name="body" rows="4" maxlength="' . MESSAGE_MAX . '" placeholder="Jūsų komentaras" required></textarea>'
        . '<button class="btn btn-primary btn-sm" type="submit">Siųsti anonimiškai</button></form>'
        . '<p class="chat-ok" hidden>Ačiū! Komentaras išsiųstas.</p></div>'
        . '</div></div>';
}
