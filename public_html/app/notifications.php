<?php
// Pranešimai paskyroje (db/021): nariui ir tėvams (lygis, patvirtinti punktai, pranešimai visiems...)
// ir administratoriui (registracija į egzaminą, pavėlavę, „Moku“ tvirtinimas, atšauktas sutikimas dėl nuotraukų).
// Svarbūs pranešimai skaičiuojami raudoname skaičiuke, kol neperskaityti ar neišspręsti.

// Tipas => ženkliukas
const NOTIFY_ICONS = [
    'level_up'          => '⭐',
    'exam_passed'       => '🎉',
    'skill_approved'    => '✅',
    'skill_rejected'    => '↩️',
    'skill_request'     => '🥋',
    'late_request'      => '⏰',
    'late_approved'     => '✅',
    'exam_registration' => '📝',
    'consent_withdrawn' => '📷',
    'broadcast'         => '📣',
    'training'          => '🥋',
];
// Šventė ekrane (vieną kartą, kol neperskaityta)
const NOTIFY_CELEBRATE = ['level_up', 'exam_passed'];

function notify_account(int $accountId, string $type, string $title, ?string $body = null, ?string $link = null,
                        ?int $memberId = null, ?int $refId = null, bool $important = true): void
{
    q('INSERT INTO notifications (account_id, member_id, type, title, body, link, ref_id, important) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$accountId, $memberId, $type, mb_substr($title, 0, 190), $body !== null && $body !== '' ? mb_substr($body, 0, 4000) : null,
         $link, $refId, $important ? 1 : 0]);
}

/** Visoms aktyvioms nario paskyroms (pats ir tėvai). Grąžina, kiek paskyrų gavo. */
function notify_member(int $memberId, string $type, string $title, ?string $body = null, ?string $link = null, ?int $refId = null, bool $important = true): int
{
    $ids = q('SELECT a.id FROM accounts a JOIN account_members am ON am.account_id = a.id WHERE am.member_id = ? AND a.status = "active"', [$memberId])
        ->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $aid) {
        notify_account((int) $aid, $type, $title, $body, $link, $memberId, $refId, $important);
    }
    return count($ids);
}

/** Administratoriams (Deniui ir kitiems adminams) */
function notify_admins(string $type, string $title, ?string $body = null, ?string $link = null, ?int $memberId = null, ?int $refId = null): void
{
    foreach (q('SELECT id FROM accounts WHERE role = "admin" AND status = "active"')->fetchAll(PDO::FETCH_COLUMN) as $aid) {
        notify_account((int) $aid, $type, $title, $body, $link, $memberId, $refId);
    }
}

/**
 * Neišspręsti pranešimai (naujausi viršuje); $memberId - tik apie šį narį (ir bendri paskyrai).
 * Nesvarbūs (pvz. „treneris patvirtino“) po 14 d. nebe rodomi, kad sąrašas neaugtų.
 */
function account_notifications(int $accountId, ?int $memberId = null, int $limit = 30): array
{
    try {
        return q_all('SELECT * FROM notifications WHERE account_id = ? AND done_at IS NULL
                        AND (important = 1 OR created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY))'
            . ($memberId !== null ? ' AND (member_id IS NULL OR member_id = ' . (int) $memberId . ')' : '')
            . ' ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limit, [$accountId]);
    } catch (PDOException $ex) {
        return [];   // dar neimportuotas db/021
    }
}

/** Kiek svarbių neperskaitytų / neišspręstų pranešimų */
function notification_count(int $accountId): int
{
    static $cache = [];
    if (!isset($cache[$accountId])) {
        try {
            $cache[$accountId] = (int) q_value('SELECT COUNT(*) FROM notifications WHERE account_id = ? AND important = 1 AND done_at IS NULL AND read_at IS NULL', [$accountId]);
        } catch (PDOException $ex) {
            $cache[$accountId] = 0;
        }
    }
    return $cache[$accountId];
}

/** Kiek svarbių neperskaitytų pranešimų apie konkretų narį (skaičiukas prie nario vardo) */
function member_notification_count(int $accountId, int $memberId): int
{
    try {
        return (int) q_value('SELECT COUNT(*) FROM notifications WHERE account_id = ? AND member_id = ? AND important = 1 AND done_at IS NULL AND read_at IS NULL',
            [$accountId, $memberId]);
    } catch (PDOException $ex) {
        return 0;
    }
}

/** Išsprendžia pranešimą visiems gavėjams (pvz. vienas adminas patvirtino - kitų kopijos irgi dingsta) */
function notifications_done(string $type, ?int $refId, ?int $memberId = null): void
{
    q('UPDATE notifications SET done_at = NOW(), read_at = COALESCE(read_at, NOW()) WHERE type = ? AND ref_id <=> ? AND member_id <=> ? AND done_at IS NULL',
        [$type, $refId, $memberId]);
}

/** Pažymi vieną pranešimą (tik savo paskyros): perskaitytas arba išspręstas („Supratau“) */
function notification_mark(int $id, int $accountId, bool $done): void
{
    q('UPDATE notifications SET read_at = COALESCE(read_at, NOW())' . ($done ? ', done_at = NOW()' : '') . ' WHERE id = ? AND account_id = ?', [$id, $accountId]);
}

function notification_when(string $dt): string
{
    $d = substr($dt, 0, 10);
    return ($d === date('Y-m-d') ? 'šiandien' : fmt_date($d)) . ', ' . substr($dt, 11, 5);
}

/** Mygtukas pranešimo veiksmui (pranesimas.php) */
function notification_button(array $n, string $action, string $text, string $cls, string $back): string
{
    return '<form method="post" action="' . e(url('pranesimas.php')) . '" class="inline-form">' . csrf_field()
        . '<input type="hidden" name="id" value="' . (int) $n['id'] . '"><input type="hidden" name="action" value="' . e($action) . '">'
        . '<input type="hidden" name="back" value="' . e($back) . '"><button class="btn btn-sm ' . $cls . '" type="submit">' . e($text) . '</button></form>';
}

/**
 * Pranešimų sąrašas su veiksmais. Admino pranešimai išsprendžiami čia pat:
 * „Moku“ - patvirtinti / atmesti, pavėlavęs - patvirtinti dalyvavimą, kiti - „Supratau“.
 */
function render_notifications(array $list, string $back): string
{
    if (!$list) {
        return '';
    }
    $html = '<ul class="notif-list">';
    foreach ($list as $n) {
        $actions = '';
        switch ($n['type']) {
            case 'skill_request':
                $actions = notification_button($n, 'skill_approve', '✓ Patvirtinti', 'btn-primary', $back)
                    . notification_button($n, 'skill_reject', 'Dar ne', 'btn-ghost', $back);
                break;
            case 'late_request':
                $actions = notification_button($n, 'late_approve', '✓ Patvirtinti dalyvavimą', 'btn-primary', $back)
                    . notification_button($n, 'done', 'Palikti „vėlavo“', 'btn-ghost', $back);
                break;
            default:
                $actions = notification_button($n, 'done', $n['read_at'] ? 'Paslėpti' : 'Supratau', 'btn-ghost', $back);
        }
        $new = !$n['read_at'] && $n['important'];
        $html .= '<li class="notif' . ($new ? ' is-new' : '') . ' t-' . e($n['type']) . '"><span class="notif-icon" aria-hidden="true">' . (NOTIFY_ICONS[$n['type']] ?? '🔔') . '</span>'
            . '<div class="notif-body"><div class="notif-title">' . ($n['link'] ? '<a href="' . e(url($n['link'])) . '">' . e($n['title']) . '</a>' : e($n['title']))
            . ($new ? ' <span class="badge badge-new">Nauja</span>' : '') . '</div>'
            . ($n['body'] ? '<div class="notif-text">' . nl2br(e($n['body'])) . '</div>' : '')
            . '<div class="notif-meta">' . e(notification_when($n['created_at'])) . '</div></div>'
            . '<div class="notif-actions">' . $actions . '</div></li>';
    }
    return $html . '</ul>';
}

/**
 * Šventė ekrane: naujas lygis arba išlaikytas egzaminas (fejerverkai). Rodoma vieną kartą -
 * uždarius pranešimas pažymimas perskaitytu.
 */
function celebration_html(): string
{
    $a = current_account();
    if (!$a || is_staff($a) || basename($_SERVER['SCRIPT_NAME'] ?? '') === 'pranesimas.php') {
        return '';
    }
    try {
        $n = q_one('SELECT * FROM notifications WHERE account_id = ? AND read_at IS NULL AND type IN ("' . implode('","', NOTIFY_CELEBRATE) . '")
                     ORDER BY created_at LIMIT 1', [(int) $a['id']]);
    } catch (PDOException $ex) {
        return '';
    }
    if (!$n) {
        return '';
    }
    $isExam = $n['type'] === 'exam_passed';
    $member = $n['member_id'] ? q_one('SELECT * FROM members WHERE id = ?', [(int) $n['member_id']]) : null;
    $belt = $member && $member['belt_level'] !== null ? (int) $member['belt_level'] : null;
    $color = $belt > 1 && isset(BELTS[$belt]) ? BELTS[$belt][2] : '#F0CB4E';   // baltas diržas ant šviesaus fono nesimato - auksinė
    $level = $member ? xp_level(member_points_total((int) $member['id'])) : null;
    return '<div class="celebrate" id="celebrate" role="dialog" aria-modal="true" aria-labelledby="celebrateTitle" style="--belt:' . e($color) . ';">'
        . '<div class="celebrate-card"><div class="celebrate-burst" aria-hidden="true"></div>'
        . '<div class="celebrate-kicker">' . ($isExam ? 'Egzaminas išlaikytas' : 'Naujas lygis') . '</div>'
        . '<div class="celebrate-big" id="celebrateTitle">' . ($isExam ? e($belt ? BELTS[$belt][0] : 'Sveikiname!') : 'Lygis ' . (int) ($level['level'] ?? $n['ref_id'])) . '</div>'
        . ($isExam && $belt ? '<div class="celebrate-belt">' . belt_icon_svg($belt) . '<span>' . e(BELTS[$belt][1]) . ' diržas</span></div>' : '')
        . '<p class="celebrate-text">' . e($n['title']) . ($n['body'] ? '<br><span>' . e($n['body']) . '</span>' : '') . '</p>'
        . '<form method="post" action="' . e(url('pranesimas.php')) . '" id="celebrateForm">' . csrf_field()
        . '<input type="hidden" name="id" value="' . (int) $n['id'] . '"><input type="hidden" name="action" value="read">'
        . '<input type="hidden" name="back" value="paskyra.php">'
        . '<button class="btn btn-primary" type="submit">Valio! 🥋</button></form></div></div>'
        . '<script>' . confetti_js() . '
(function () {
  var f = document.getElementById("celebrateForm");
  f.addEventListener("submit", function (ev) {
    ev.preventDefault();
    // f.action netinka - formoje yra laukas name="action"
    fetch(f.getAttribute("action"), { method: "POST", body: new FormData(f), credentials: "same-origin" }).catch(function () {});
    document.getElementById("celebrate").remove();
  });
})();</script>';
}

/** Puslapio apačia: šventė (naujas lygis / egzaminas), pokalbio langelis ir bendras JS (laikmačiai, pokalbis) */
function page_widgets(): string
{
    return celebration_html() . chat_widget_html() . '<script src="' . e(asset_url('assets/site.js')) . '" defer></script>';
}

/**
 * Kas nesutinka, kad būtų skelbiamos jų nuotraukos (tik adminui, prie nuotraukų įkėlimo): aktyvūs nariai be sutikimo.
 * Vaikams sutikimą duoda tėvai, nuo 14 m. - ir patys.
 */
function no_photo_consent_html(): string
{
    if (!is_admin()) {
        return '';
    }
    $rows = q_all('SELECT m.id, m.first_name, m.last_name, g.name AS group_name FROM members m LEFT JOIN training_groups g ON g.id = m.group_id
                    WHERE m.status = "active" AND m.photo_consent = 0 ORDER BY g.sort_order, m.last_name, m.first_name');
    if (!$rows) {
        return '<p class="consent-list ok small">✓ Visi aktyvūs nariai sutinka, kad būtų skelbiamos jų nuotraukos.</p>';
    }
    $by = [];
    foreach ($rows as $r) {
        $by[$r['group_name'] ?: 'Be grupės'][] = '<a href="' . e(url('admin/nariai.php?id=' . (int) $r['id'])) . '">' . e($r['first_name'] . ' ' . $r['last_name']) . '</a>';
    }
    $html = '<details class="consent-list"><summary>📷 Nesutinka, kad būtų skelbiamos nuotraukos: <strong>' . count($rows) . '</strong> <span class="hint">(mato tik administratorius)</span></summary><ul>';
    foreach ($by as $group => $names) {
        $html .= '<li><span class="muted">' . e($group) . ':</span> ' . implode(', ', $names) . '</li>';
    }
    return $html . '</ul></details>';
}
