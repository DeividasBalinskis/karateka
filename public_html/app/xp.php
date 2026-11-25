<?php
// XP ir lygiai. XP = aktyvumo taškai (viena sistema, v1.49): už treniruotes, renginius, testus gauni XP, nuo jų - lygis,
// kiekvienas naujas lygis - dovanų dėžė Dojo (app/game.php game_sync). XP = tie patys taškai, iš kurių skaičiuojamas reitingas. Lygis - nuo visų kada nors surinktų taškų
// (sezonas jo nenuima). Lygio kaina (v1.96, sprendimas 2026-11-25, docs/dojo-progresija.md): pirmi 5 lygiai po 5 XP, kas 5 lygius +2 XP
// (5, 7, 9, 11 …) - nustatoma Treneriams -> Taškai. Dovanos už lygius - iki 60 lygio.
// Egzamino programos punktai: narys pažymi „Moku“, Denis patvirtina pranešime - tada skiriami punkto XP.

const XP_LEVEL_EVERY = 5;   // kas kiek lygių kaina padidėja
const XP_GIFT_LEVELS = 60;  // dovanų dėžės už lygius - iki šio lygio

/** Pirmų lygių kaina (XP) */
function xp_level_base(): int
{
    return max(1, min(1000, (int) site_setting('xp_level_base', '5')));
}

/** Kiek XP daugiau kainuoja kas 5 lygius */
function xp_level_step(): int
{
    return max(0, min(1000, (int) site_setting('xp_level_step', '2')));
}

/** Kiek XP reikia pakilti iš lygio n į n+1 */
function xp_level_cost(int $n): int
{
    return xp_level_base() + xp_level_step() * intdiv(max(1, $n) - 1, XP_LEVEL_EVERY);
}

/** Kiek XP iš viso reikia pasiekti lygį n (1 lygis - 0 XP) */
function xp_level_start(int $n): int
{
    $sum = 0;
    for ($l = 1; $l < $n; $l++) {
        $sum += xp_level_cost($l);
    }
    return $sum;
}

/** ['level' => n, 'xp' => viso, 'into' => kiek surinkta šiame lygyje, 'need' => kiek trūksta iki kito, 'per' => šio lygio kaina, 'pct' => 0-100] */
function xp_level(int $xp): array
{
    $xp = max(0, $xp);
    $level = 1;
    $start = 0;
    while ($level < 1000 && $xp >= $start + xp_level_cost($level)) {
        $start += xp_level_cost($level);
        $level++;
    }
    $per = xp_level_cost($level);
    $into = $xp - $start;
    return ['level' => $level, 'xp' => $xp, 'into' => $into, 'need' => $per - $into, 'per' => $per, 'pct' => (int) floor(100 * $into / $per)];
}

/** Pakilo lygis po taškų skyrimo - pranešimas nariui ir tėvams (šventė ekrane) */
function xp_check_level_up(int $memberId, int $before): void
{
    $after = member_points_total($memberId);
    $old = xp_level($before);
    $new = xp_level($after);
    if ($new['level'] <= $old['level']) {
        return;
    }
    $m = q_one('SELECT first_name FROM members WHERE id = ?', [$memberId]);
    try {
        notify_member($memberId, 'level_up', ($m['first_name'] ?? 'Narys') . ' pasiekė ' . $new['level'] . ' lygį!',
            t('Surinkta {a} XP (aktyvumo taškų). Iki {l} lygio - {n} XP.', ['a' => $after, 'l' => $new['level'] + 1, 'n' => $new['need']])
            . (xp_gifts_open() && $new['level'] <= XP_GIFT_LEVELS ? ' ' . t('🎁 Dovanų dėžė laukia Dojo.') : ''), xp_gifts_open() ? 'dojo.php' : 'paskyra.php?m=' . $memberId, $new['level']);
    } catch (PDOException $ex) {
        // dar neimportuotas db/021 - taškai skirti, tik be pranešimo
    }
}

/** Ar už lygius jau gaunamos dovanos (Dojo atidarytas visiems nariams) - tada apie jas rašoma pranešimuose */
/** Kortelėje rodyti dovaną už kitą lygį - tik tiems, kurie mato Dojo */
function xp_card_gift(): bool
{
    if (!function_exists('game_allowed')) {
        require_once APP_DIR . '/game.php';
    }
    return game_ready() && game_public() && game_allowed();
}

function xp_gifts_open(): bool
{
    if (!function_exists('game_access_ids')) {
        require_once APP_DIR . '/game.php';
    }
    return game_ready() && game_access_ids() === null;
}

/** Lygio kortelė: lygis diržo spalva, XP juosta, kiek trūksta iki kito lygio, dovana už kitą lygį */
function render_xp_card(array $member, bool $compact = false, bool $showBelt = true): string
{
    $total = member_points_total((int) $member['id']);
    $l = xp_level($total);
    $belt = current_belt($member['belt_level'] !== null ? (int) $member['belt_level'] : null);
    [, $beltName, $color] = BELTS[$belt];
    $beltName = t($beltName);
    return '<div class="xp-card' . ($compact ? ' compact' : '') . ($belt === 1 ? ' belt-light' : '') . '" style="--belt:' . e($color) . ';">'
        . '<div class="xp-level" title="' . e(t('Lygis')) . '"><span class="xp-level-n">' . $l['level'] . '</span><span class="xp-level-l">' . e(t('lygis')) . '</span></div>'
        . '<div class="xp-main"><div class="xp-row"><strong title="' . e(t('XP - aktyvumo taškai: už treniruotes, renginius, testus ir egzaminus')) . '">' . $l['xp'] . ' XP</strong> <span class="xp-what">' . e(t('aktyvumo taškai')) . '</span>'
        . '<span class="xp-next">' . e(t('iki {n} lygio: {xp} XP', ['n' => $l['level'] + 1, 'xp' => $l['need']])) . (xp_card_gift() ? ' · 🎁' : '') . '</span></div>'
        . '<div class="xp-bar" role="progressbar" aria-valuemin="0" aria-valuemax="' . $l['per'] . '" aria-valuenow="' . $l['into'] . '"><span style="width:' . $l['pct'] . '%"></span></div>'
        . ($showBelt ? '<div class="xp-belt">' . belt_icon_svg($belt) . ' ' . e(BELTS[$belt][0] . ' · ' . $beltName) . '</div>' : '') . '</div></div>';
}

// ---------- Egzamino programos punktai ----------

/** Narys pažymėjo „Moku“: laukia Denio patvirtinimo (pranešimas adminams) */
function request_skill(array $member, array $req, array $byAccount): void
{
    $cur = q_one('SELECT * FROM requirement_progress WHERE member_id = ? AND requirement_id = ?', [(int) $member['id'], (int) $req['id']]);
    if ($cur) {
        return;
    }
    q('INSERT INTO requirement_progress (member_id, requirement_id, status) VALUES (?, ?, "pending")', [(int) $member['id'], (int) $req['id']]);
    notify_admins('skill_request', $member['first_name'] . ' ' . $member['last_name'] . ': „' . $req['title'] . '“ (+' . (int) $req['xp'] . ' ' . t('taškai') . ')',
        'Pažymėjo „Moku“ (' . BELTS[(int) $req['belt_level']][0] . ', ' . (REQ_SECTIONS[$req['section']] ?? $req['section']) . ')'
        . ((int) $byAccount['id'] && $byAccount['first_name'] !== $member['first_name'] ? ' · pažymėjo ' . $byAccount['first_name'] : '') . '.',
        'admin/nariai.php?id=' . (int) $member['id'], (int) $member['id'], (int) $req['id']);
}

/** Narys atžymėjo dar nepatvirtintą punktą */
function cancel_skill(int $memberId, int $reqId): void
{
    q('DELETE FROM requirement_progress WHERE member_id = ? AND requirement_id = ? AND status = "pending"', [$memberId, $reqId]);
    notifications_done('skill_request', $reqId, $memberId);
}

/** Denis patvirtino: punktas įskaitytas, skiriami XP. false - jau patvirtinta ar nerasta. */
function approve_skill(int $memberId, int $reqId, int $by): bool
{
    $req = q_one('SELECT * FROM belt_requirements WHERE id = ?', [$reqId]);
    $row = q_one('SELECT * FROM requirement_progress WHERE member_id = ? AND requirement_id = ?', [$memberId, $reqId]);
    if (!$req || ($row && $row['status'] === 'approved')) {
        notifications_done('skill_request', $reqId, $memberId);
        return false;
    }
    if ($row) {
        q('UPDATE requirement_progress SET status = "approved", approved_by = ? WHERE member_id = ? AND requirement_id = ?', [$by, $memberId, $reqId]);
    } else {
        q('INSERT INTO requirement_progress (member_id, requirement_id, status, approved_by) VALUES (?, ?, "approved", ?)', [$memberId, $reqId, $by]);
    }
    if ((int) $req['xp'] > 0) {
        award_points($memberId, category_by_code('skill'), date('Y-m-d'), null, $req['title'], $by, (int) $req['xp']);
    }
    notifications_done('skill_request', $reqId, $memberId);
    $m = q_one('SELECT first_name FROM members WHERE id = ?', [$memberId]);
    notify_member($memberId, 'skill_approved', '+' . (int) $req['xp'] . ' ' . t('taškai') . ': ' . $req['title'], 'Treneris patvirtino - ' . ($m['first_name'] ?? '') . ' tai moka.',
        'egzaminas.php?m=' . $memberId . '&dirzas=' . (int) $req['belt_level'] . '#r' . $reqId, $reqId, false);
    return true;
}

/** Dar ne: punktas grąžinamas į neatliktus, narys gali pažymėti vėliau */
function reject_skill(int $memberId, int $reqId): void
{
    $req = q_one('SELECT * FROM belt_requirements WHERE id = ?', [$reqId]);
    q('DELETE FROM requirement_progress WHERE member_id = ? AND requirement_id = ? AND status = "pending"', [$memberId, $reqId]);
    notifications_done('skill_request', $reqId, $memberId);
    if ($req) {
        notify_member($memberId, 'skill_rejected', 'Dar pasitreniruok: ' . $req['title'], 'Treneris dar nepatvirtino. Pasitreniruok ir pažymėk „Moku“ dar kartą.',
            'egzaminas.php?m=' . $memberId . '&dirzas=' . (int) $req['belt_level'] . '#r' . $reqId, $reqId, false);
    }
}

// ---------- Testai ----------

/**
 * Taškai už testą: už pirmą bandymą per sezoną ir už pirmą išlaikymą per sezoną (kad nebūtų „ūkininkaujama“ kartojant).
 * Grąžina, kiek XP skirta.
 */
function award_quiz_points(array $attempt, array $quiz, int $by): int
{
    [$from, $to] = season_bounds();
    $memberId = (int) $attempt['member_id'];
    $earlier = function (bool $passedOnly) use ($attempt, $memberId, $from, $to) {
        return (int) q_value('SELECT COUNT(*) FROM quiz_attempts WHERE member_id = ? AND quiz_id = ? AND id <> ? AND finished_at IS NOT NULL
                               AND DATE(finished_at) BETWEEN ? AND ?' . ($passedOnly ? ' AND passed = 1' : ''),
            [$memberId, (int) $attempt['quiz_id'], (int) $attempt['id'], $from, $to]);
    };
    $given = 0;
    foreach ([['test_attempt', !$earlier(false)], ['test_pass', $attempt['passed'] && !$earlier(true)]] as [$code, $ok]) {
        if (!$ok) {
            continue;
        }
        $cat = q_one('SELECT * FROM point_categories WHERE code = ? AND is_active = 1', [$code]);
        if ($cat && (int) $cat['points'] > 0) {
            award_points($memberId, $cat, date('Y-m-d'), null, 'Testas: ' . $quiz['title'], $by);
            $given += (int) $cat['points'];
        }
    }
    return $given;
}
