<?php
// Vienkartinės nuorodos laiškuose. Duomenų bazėje saugomas tik žetono hash.

const TOKEN_TTL = [
    'verify_email'   => '+3 days',
    'parent_consent' => '+14 days',
    'password_reset' => '+1 hour',
    'invite'         => '+14 days',
    'change_email'   => '+1 day',
];

/** Sukuria žetoną ir grąžina jo reikšmę (dedama į nuorodą) */
function token_create(string $purpose, string $email, ?int $accountId = null, ?int $memberId = null): string
{
    // Ankstesni to paties tipo nepanaudoti žetonai nebegalioja
    q('UPDATE email_tokens SET used_at = NOW() WHERE purpose = ? AND used_at IS NULL AND email = ? AND account_id <=> ? AND member_id <=> ?',
        [$purpose, $email, $accountId, $memberId]);

    $token = bin2hex(random_bytes(32));
    q('INSERT INTO email_tokens (purpose, token_hash, email, account_id, member_id, expires_at) VALUES (?, ?, ?, ?, ?, ?)', [
        $purpose,
        hash('sha256', $token),
        $email,
        $accountId,
        $memberId,
        date('Y-m-d H:i:s', strtotime(TOKEN_TTL[$purpose])),
    ]);
    return $token;
}

/** Randa galiojantį (nepanaudotą, nepasibaigusį) žetoną */
function token_find(string $purpose, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    return q_one('SELECT * FROM email_tokens WHERE purpose = ? AND token_hash = ? AND used_at IS NULL AND expires_at > NOW()',
        [$purpose, hash('sha256', $token)]);
}

function token_use(int $id): void
{
    q('UPDATE email_tokens SET used_at = NOW() WHERE id = ?', [$id]);
}
