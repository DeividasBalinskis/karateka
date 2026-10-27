<?php
// XP ir lygiai. XP = tie patys taškai, iš kurių skaičiuojamas reitingas. Lygis - nuo visų kada nors surinktų taškų
// (sezonas jo nenuima), kiek XP reikia vienam lygiui - nustatoma Treneriams -> Taškai.
// Egzamino programos punktai: narys pažymi „Moku“, Denis patvirtina pranešime - tada skiriami punkto XP.

function xp_per_level(): int
{
    return max(10, (int) site_setting('xp_per_level', '100'));
}

/** ['level' => n, 'xp' => viso, 'into' => kiek surinkta šiame lygyje, 'need' => kiek trūksta iki kito, 'per' => lygio dydis, 'pct' => 0-100] */
function xp_level(int $xp): array
{
    $per = xp_per_level();
    $xp = max(0, $xp);
    $level = intdiv($xp, $per) + 1;
    $into = $xp - ($level - 1) * $per;
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
            'Surinkta ' . $after . ' XP. Iki ' . ($new['level'] + 1) . ' lygio - ' . $new['need'] . ' XP.', 'paskyra.php?m=' . $memberId, $new['level']);
    } catch (PDOException $ex) {
        // dar neimportuotas db/021 - taškai skirti, tik be pranešimo
    }
}

/** Lygio kortelė: lygis diržo spalva, XP juosta, kiek trūksta iki kito lygio */
function render_xp_card(array $member, bool $compact = false): string
{
    $total = member_points_total((int) $member['id']);
    $l = xp_level($total);
    $belt = current_belt($member['belt_level'] !== null ? (int) $member['belt_level'] : null);
    [, $beltName, $color] = BELTS[$belt];
    return '<div class="xp-card' . ($compact ? ' compact' : '') . ($belt === 1 ? ' belt-light' : '') . '" style="--belt:' . e($color) . ';">'
        . '<div class="xp-level" title="Lygis"><span class="xp-level-n">' . $l['level'] . '</span><span class="xp-level-l">lygis</span></div>'
        . '<div class="xp-main"><div class="xp-row"><strong>' . $l['xp'] . ' XP</strong><span class="xp-next">iki ' . ($l['level'] + 1) . ' lygio: ' . $l['need'] . ' XP</span></div>'
        . '<div class="xp-bar" role="progressbar" aria-valuemin="0" aria-valuemax="' . $l['per'] . '" aria-valuenow="' . $l['into'] . '"><span style="width:' . $l['pct'] . '%"></span></div>'
        . '<div class="xp-belt">' . belt_icon_svg($belt) . ' ' . e(BELTS[$belt][0] . ' · ' . $beltName) . '</div></div></div>';
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
    notify_admins('skill_request', $member['first_name'] . ' ' . $member['last_name'] . ': „' . $req['title'] . '“ (+' . (int) $req['xp'] . ' XP)',
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
    notify_member($memberId, 'skill_approved', '+' . (int) $req['xp'] . ' XP: ' . $req['title'], 'Treneris patvirtino - ' . ($m['first_name'] ?? '') . ' tai moka.',
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
