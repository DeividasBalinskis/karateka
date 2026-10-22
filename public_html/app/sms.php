<?php
// SMS žinutės: numeriai iš paskyrų, ilgio skaičiavimas, siuntimas per teikėją (config.php -> sms)

/** Ar SMS tikrai siunčiamos (kitaip - bandomasis režimas, tik įrašoma į istoriją) */
function sms_live(): bool
{
    return config('sms.provider', 'log') === 'twilio';
}

/** Lietuviškas numeris į tarptautinį formatą: 8 612 34567 / 612 34567 / 370... -> +37061234567. null - netinkamas */
function sms_normalize_phone(?string $phone): ?string
{
    $p = preg_replace('/[\s\-().\/]/', '', (string) $phone);
    if ($p === '') {
        return null;
    }
    if (strpos($p, '00') === 0) {
        $p = '+' . substr($p, 2);
    } elseif (preg_match('/^8(\d{8})$/', $p, $m)) {
        $p = '+370' . $m[1];
    } elseif (preg_match('/^6\d{7}$/', $p)) {
        $p = '+370' . $p;
    } elseif (preg_match('/^370\d{8}$/', $p)) {
        $p = '+' . $p;
    }
    return preg_match('/^\+\d{8,15}$/', $p) ? $p : null;
}

/** Be lietuviškų raidžių: SMS tada telpa 160 simbolių vietoj 70 */
function sms_transliterate(string $text): string
{
    return strtr($text, [
        'ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i', 'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z',
        'Ą' => 'A', 'Č' => 'C', 'Ę' => 'E', 'Ė' => 'E', 'Į' => 'I', 'Š' => 'S', 'Ų' => 'U', 'Ū' => 'U', 'Ž' => 'Z',
        '„' => '"', '“' => '"', '”' => '"', '‘' => "'", '’' => "'", '–' => '-', '—' => '-', '…' => '...',
    ]);
}

/** Kiek SMS dalių: GSM abėcėlė - 160 (dalimis po 153), su kitomis raidėmis (ą, č, ...) - 70 (po 67) */
function sms_parts(string $text): int
{
    $basic = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
    $ext = '^{}\\[~]|€';
    $gsm = true;
    $len = 0;
    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
        if (mb_strpos($basic, $ch) !== false) {
            $len++;
        } elseif (mb_strpos($ext, $ch) !== false) {
            $len += 2;
        } else {
            $gsm = false;
            break;
        }
    }
    if (!$gsm) {
        $len = mb_strlen($text);
        return $len <= 70 ? 1 : (int) ceil($len / 67);
    }
    return $len <= 160 ? 1 : (int) ceil($len / 153);
}

/**
 * Gavėjai pagal narius: vaikams (kurie turi tėvų paskyrą) - tėvų numeriai, kitiems - savo.
 * Vienas numeris gauna vieną SMS, net jei tėvai turi kelis vaikus.
 * Grąžina ['recipients' => [phone => [...]], 'missing' => [narių vardai be numerio]]
 */
function sms_recipients_for_members(array $memberIds): array
{
    $memberIds = array_values(array_unique(array_filter(array_map('intval', $memberIds))));
    if (!$memberIds) {
        return ['recipients' => [], 'missing' => []];
    }
    $rows = q_all('SELECT m.id AS member_id, m.first_name AS m_first, m.last_name AS m_last, am.relation,
                          a.id AS account_id, a.first_name, a.last_name, a.phone
                     FROM members m
                     LEFT JOIN account_members am ON am.member_id = m.id
                     LEFT JOIN accounts a ON a.id = am.account_id AND a.status = "active"
                    WHERE m.id IN (' . implode(',', $memberIds) . ')
                    ORDER BY m.last_name, m.first_name');
    $byMember = [];
    foreach ($rows as $r) {
        $mid = (int) $r['member_id'];
        $byMember[$mid]['name'] = $r['m_first'] . ' ' . $r['m_last'];
        $byMember[$mid]['first'] = $r['m_first'];
        if ($r['account_id'] && ($phone = sms_normalize_phone($r['phone']))) {
            $byMember[$mid][$r['relation'] === 'parent' ? 'parents' : 'self'][] = $r + ['norm' => $phone];
        }
    }
    $out = [];
    $missing = [];
    foreach ($byMember as $m) {
        $targets = !empty($m['parents']) ? $m['parents'] : ($m['self'] ?? []);
        if (!$targets) {
            $missing[] = $m['name'];
            continue;
        }
        foreach ($targets as $t) {
            if (!isset($out[$t['norm']])) {
                $out[$t['norm']] = ['phone' => $t['norm'], 'account_id' => (int) $t['account_id'], 'name' => $t['first_name'] . ' ' . $t['last_name'], 'for' => []];
            }
            $out[$t['norm']]['for'][] = $m['first'];
        }
    }
    uasort($out, function ($x, $y) { return strcmp($x['name'], $y['name']); });
    return ['recipients' => $out, 'missing' => $missing];
}

/** Siunčia vieną SMS. ['ok' => bool, 'id' => teikėjo id, 'error' => tekstas] */
function sms_send_one(string $phone, string $text): array
{
    if (!sms_live()) {
        return ['ok' => true, 'id' => null, 'error' => null];
    }
    $sid = (string) config('sms.twilio_sid', '');
    $token = (string) config('sms.twilio_token', '');
    if ($sid === '' || $token === '' || !function_exists('curl_init')) {
        return ['ok' => false, 'id' => null, 'error' => 'SMS teikėjas nesukonfigūruotas (config.php -> sms).'];
    }
    $ch = curl_init('https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['To' => $phone, 'From' => (string) config('sms.from', 'Karateka'), 'Body' => $text]),
        CURLOPT_USERPWD => $sid . ':' . $token,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $data = is_string($resp) ? json_decode($resp, true) : null;
    if ($code >= 200 && $code < 300 && !empty($data['sid'])) {
        return ['ok' => true, 'id' => $data['sid'], 'error' => null];
    }
    return ['ok' => false, 'id' => null, 'error' => mb_substr($data['message'] ?? ($err ?: 'HTTP ' . $code), 0, 250)];
}

/** Išsiunčia žinutę visiems gavėjams ir įrašo į istoriją. Grąžina sms_messages.id */
function sms_send_message(string $text, array $recipients, string $label, int $byAccountId): int
{
    $parts = sms_parts($text);
    q('INSERT INTO sms_messages (body, parts, provider, label, sent_by) VALUES (?, ?, ?, ?, ?)',
        [$text, $parts, sms_live() ? 'twilio' : 'log', mb_substr($label, 0, 190), $byAccountId]);
    $msgId = (int) db()->lastInsertId();
    @set_time_limit(300);
    $sent = 0;
    $failed = 0;
    foreach ($recipients as $r) {
        $res = sms_send_one($r['phone'], $text);
        $status = !$res['ok'] ? 'failed' : (sms_live() ? 'sent' : 'test');
        $res['ok'] ? $sent++ : $failed++;
        q('INSERT INTO sms_recipients (message_id, account_id, name, phone, for_members, status, provider_id, error) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$msgId, $r['account_id'] ?: null, $r['name'], $r['phone'], mb_substr(implode(', ', $r['for']), 0, 255), $status, $res['id'], $res['error']]);
    }
    $price = config('sms.price_eur');
    q('UPDATE sms_messages SET sent_count = ?, failed_count = ?, cost_eur = ? WHERE id = ?',
        [$sent, $failed, $price !== null && sms_live() ? round($sent * $parts * (float) $price, 3) : null, $msgId]);
    return $msgId;
}
