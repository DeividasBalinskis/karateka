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
 * Klausimą viešu padaro Denis; vardas rodomas tik jei jis taip pasirinko (anoniminio - niekada).
 * $column = null - bendri klausimai (ne prie renginio, naujienos ar pamokos) puslapiui „Klausimai ir atsakymai“.
 */
function public_questions(?string $column, int $refId = 0): array
{
    try {
        $rows = $column ? message_rows("mm.$column = ? AND mm.note_id IS NULL", [$refId])
            : message_rows('mm.note_id IS NULL AND mm.lesson_id IS NULL AND mm.event_id IS NULL AND mm.news_id IS NULL', []);
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

/** Viešų klausimų sąrašas (renginio, naujienos, pamokos puslapyje ir „Klausimai ir atsakymai“) */
function render_public_questions(array $public): string
{
    $html = '';
    foreach ($public as $x) {
        $q = $x['q'];
        $who = !empty($q['show_name']) && empty($q['anonymous']) ? trim($q['member_first'] . ' ' . mb_substr((string) $q['member_last'], 0, 1) . '.') : 'Narys';
        $html .= '<div class="q-item"><div class="q-q"><span class="q-who">' . e($who) . ' klausė:</span> ' . nl2br(e($q['body'])) . '</div>';
        foreach ($x['answers'] as $ans) {
            $html .= '<div class="q-a"><span class="q-who">' . e(message_author($ans, false)) . ':</span> ' . nl2br(e($ans['body'])) . '</div>';
        }
        $html .= '</div>';
    }
    return $html;
}

/** Treneriui: žinutė su laiku, anoniminio žyma ir mygtukais „Rodyti visiems“ / „su vardu“ */
function render_staff_comment(array $r, string $back, array $relations): string
{
    $html = '<div class="msg ' . ($r['from_staff'] ? 'from-staff' : 'from-member') . (!$r['from_staff'] && $r['read_at'] === null ? ' is-new' : '') . '">'
        . '<div class="msg-meta">' . e(chat_author($r, true, $relations)) . ' · ' . e(notification_when($r['created_at']))
        . (!empty($r['anonymous']) ? ' <span class="badge badge-anon" title="Paviešinus vardas nerodomas">anoniminis</span>' : '')
        . (!$r['from_staff'] && $r['read_at'] === null ? ' <span class="badge badge-new">Nauja</span>' : '') . '</div>'
        . '<div class="msg-body">' . nl2br(e($r['body'])) . '</div>';
    if (!$r['from_staff']) {
        $form = function (string $field, string $text) use ($r, $back) {
            return '<form method="post" action="' . e(url('pokalbis.php')) . '" class="inline-form">' . csrf_field()
                . '<input type="hidden" name="action" value="publish"><input type="hidden" name="member_id" value="' . (int) $r['member_id'] . '">'
                . '<input type="hidden" name="message_id" value="' . (int) $r['id'] . '"><input type="hidden" name="field" value="' . $field . '">'
                . '<input type="hidden" name="back" value="' . e($back) . '"><button class="linklike small" type="submit">' . e($text) . '</button></form>';
        };
        $html .= '<div class="q-publish">' . $form('is_public', !empty($r['is_public']) ? '🔒 Slėpti nuo kitų' : '👁 Rodyti visiems')
            . (!empty($r['is_public']) && empty($r['anonymous']) ? ' · ' . $form('show_name', !empty($r['show_name']) ? 'be vardo' : 'su vardu') : '')
            . (!empty($r['is_public']) ? ' <span class="small muted">· matomas visiems ' . (!empty($r['show_name']) && empty($r['anonymous']) ? 'su vardu' : 'be vardo') . '</span>' : '') . '</div>';
    }
    return $html . '</div>';
}

/**
 * Komentarai Deniui prie renginio, naujienos ar pamokos. Narys mato tik savo komentarus ir atsakymus (ir viešai parodytus),
 * Denis - visų: kas, kada parašė, atsako čia pat ir gali parodyti visiems.
 */
function questions_box(string $kind, int $refId, string $back): string
{
    $column = MESSAGE_CONTEXTS[$kind] ?? null;
    $a = current_account();
    if (!$column) {
        return '';
    }
    if (!$a) {
        return '<div class="panel card q-box" id="klausimai"><div class="kicker">💬 Komentaras ar klausimas Deniui</div><p class="small muted"><a href="'
            . e(url('prisijungti.php?r=' . rawurlencode(url($back)))) . '">Prisijunkite</a> - komentarą mato tik Denis.</p></div>';
    }
    $ctxKey = ['lesson_id' => 'l', 'event_id' => 'e', 'news_id' => 'w'][$column] . $refId;
    $html = '';
    $public = public_questions($column, $refId);
    if ($public) {
        $html .= '<div class="q-public"><div class="kicker">Klausimai ir atsakymai</div>' . render_public_questions($public) . '</div>';
    }

    if (is_staff($a)) {
        // Treneriui: kiekvieno nario komentarai su atsakymo forma - atsakoma čia pat
        try {
            $rows = message_rows("mm.$column = ? AND mm.note_id IS NULL", [$refId]);
        } catch (PDOException $ex) {
            $rows = [];
        }
        $byMember = [];
        foreach ($rows as $r) {
            $byMember[(int) $r['member_id']][] = $r;
        }
        $html .= '<div class="kicker" style="margin-top:' . ($public ? '14px' : '0') . ';">Narių komentarai Deniui</div>';
        if (!$byMember) {
            $html .= '<p class="small muted">Komentarų dar nėra. Nariai juos palieka čia - matote tik jūs (treneriai).</p>';
        }
        foreach ($byMember as $mid => $list) {
            $rel = q('SELECT account_id, relation FROM account_members WHERE member_id = ?', [$mid])->fetchAll(PDO::FETCH_KEY_PAIR);
            $html .= '<div class="q-thread" id="q-' . $mid . '"><div class="q-thread-head"><strong>' . e($list[0]['member_first'] . ' ' . $list[0]['member_last']) . '</strong>'
                . '<a class="small" href="' . e(url('admin/nariai.php?id=' . $mid)) . '">kortelė</a><button type="button" class="linklike small" data-open-chat data-member="' . $mid . '">visas pokalbis</button></div>'
                . '<div class="msg-thread">' . implode('', array_map(function ($r) use ($back, $mid, $rel) { return render_staff_comment($r, $back . '#q-' . $mid, $rel); }, $list)) . '</div>'
                . '<form method="post" action="' . e(url('pokalbis.php')) . '" class="msg-form">' . csrf_field()
                . '<input type="hidden" name="action" value="reply"><input type="hidden" name="member_id" value="' . $mid . '"><input type="hidden" name="ctx" value="' . $ctxKey . '">'
                . '<input type="hidden" name="back" value="' . e($back . '#q-' . $mid) . '">'
                . '<textarea name="body" rows="2" maxlength="' . MESSAGE_MAX . '" placeholder="Atsakymas - matys ' . e($list[0]['member_first']) . ' (ir tėvai)" required></textarea>'
                . '<button class="btn btn-primary btn-sm" type="submit">Atsakyti</button></form></div>';
            mark_member_messages_read_ctx($mid, $column, $refId);
        }
        return '<div class="panel card q-box" id="klausimai">' . $html . '</div>';
    }

    $members = is_active_account($a) ? array_values(array_filter(account_members((int) $a['id']), function ($m) { return $m['status'] === 'active'; })) : [];
    if (!$members) {
        return $html ? '<div class="panel card q-box" id="klausimai">' . $html . '</div>' : '';
    }
    $html .= '<div class="kicker"' . ($public ? ' style="margin-top:14px;"' : '') . '>💬 Komentaras ar klausimas Deniui</div>'
        . '<p class="small muted" style="margin-top:-4px;">Mato tik Denis. Atsakymą matysite čia ir pokalbyje.</p>';
    foreach ($members as $m) {
        $rows = message_rows("mm.member_id = ? AND mm.$column = ? AND mm.note_id IS NULL", [(int) $m['id'], $refId]);
        if ($rows) {
            if (count($members) > 1) {
                $html .= '<div class="msg-topic-title">' . e($m['first_name']) . '</div>';
            }
            $html .= '<div class="msg-thread">' . implode('', array_map(function ($r) { return render_message($r, false); }, $rows)) . '</div>';
        }
        mark_staff_messages_read((int) $m['id'], $refId, $column);
    }
    $form = '<form method="post" action="' . e(url('zinute.php')) . '" class="msg-form q-form">' . csrf_field()
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
    $html .= $form . '<textarea name="body" rows="2" maxlength="' . MESSAGE_MAX . '" placeholder="Jūsų komentaras ar klausimas Deniui" required></textarea>'
        . '<button class="btn btn-primary btn-sm" type="submit">Siųsti Deniui</button>'
        . '<label class="check small q-anon"><input type="checkbox" name="anonymous" value="1"><span>Anonimiškai - jei Denis paviešins, mano vardas nebus rodomas</span></label></form>';
    return '<div class="panel card q-box" id="klausimai">' . $html . '</div>';
}

/** Nario komentarai vienoje vietoje (renginys, naujiena, pamoka) - treneris peržiūrėjo */
function mark_member_messages_read_ctx(int $memberId, string $column, int $refId): void
{
    $column = in_array($column, MESSAGE_CONTEXTS, true) ? $column : 'event_id';
    q("UPDATE member_messages SET read_at = NOW() WHERE member_id = ? AND from_staff = 0 AND read_at IS NULL AND $column = ?", [$memberId, $refId]);
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
