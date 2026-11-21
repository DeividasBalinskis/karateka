<?php
// C8: laiškų siuntimo eilė. Masiniai laiškai (Pranešimas nariams, Atšauktos treniruotės) pirmiausia įrašomi į mail_outbox (db/049),
// tada siunčiami po kelis (admin/siuntimas.php - puslapis pats tęsia, kol išsiųsta). Kiekvienam gavėjui matosi: išsiųsta / nepavyko.
// Be db/049 - siunčiama iš karto, kaip anksčiau.

const OUTBOX_STEP = 10;   // kiek laiškų vienu kartu (vienas puslapio užklausimas)

function outbox_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT 1 FROM mail_outbox LIMIT 1');
            $ok = true;
        } catch (PDOException $ex) {
            $ok = false;
        }
    }
    return $ok;
}

function outbox_new_batch(): string
{
    return date('ymdHis') . bin2hex(random_bytes(3));
}

/** Į eilę (arba iš karto, jei eilės lentelės dar nėra). Grąžina true, jei įrašyta į eilę. */
function outbox_add(string $batch, string $title, string $to, array $mail, ?int $accountId, ?int $by): bool
{
    [$subject, $body] = $mail;
    if (!outbox_ready()) {
        send_mail($to, $subject, $body);
        return false;
    }
    q('INSERT INTO mail_outbox (batch, title, account_id, to_email, subject, body, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$batch, mb_substr($title, 0, 190), $accountId, mb_substr($to, 0, 190), mb_substr($subject, 0, 255), $body, $by]);
    return true;
}

/** Išsiųsti kitus OUTBOX_STEP laiškus. Vienas laiškas nesiunčiamas dukart (pažymima „sending“ prieš siunčiant). */
function outbox_send(string $batch, int $limit = OUTBOX_STEP): array
{
    if (outbox_ready()) {
        foreach (q_all('SELECT * FROM mail_outbox WHERE batch = ? AND status = "queued" ORDER BY id LIMIT ' . max(1, $limit), [$batch]) as $r) {
            if (!q('UPDATE mail_outbox SET status = "sending", sent_at = NOW() WHERE id = ? AND status = "queued"', [(int) $r['id']])->rowCount()) {
                continue;   // kitas langas jau siunčia
            }
            $ok = send_mail($r['to_email'], $r['subject'], $r['body']);
            q('UPDATE mail_outbox SET status = ?, error = ?, sent_at = NOW() WHERE id = ?', [$ok ? 'sent' : 'failed', $ok ? null : 'Nepavyko išsiųsti', (int) $r['id']]);
        }
        // užstrigę „sending“ (puslapis nutrūko siunčiant) - po 10 min. laikomi nepavykusiais; „Pakartoti nepavykusius“ grąžina į eilę
        q('UPDATE mail_outbox SET status = "failed", error = "Nutrūko siunčiant" WHERE batch = ? AND status = "sending" AND sent_at < NOW() - INTERVAL 10 MINUTE', [$batch]);
    }
    return outbox_counts($batch);
}

/** Nepavykusius - vėl į eilę */
function outbox_retry(string $batch): void
{
    if (outbox_ready()) {
        q('UPDATE mail_outbox SET status = "queued", error = NULL, sent_at = NULL WHERE batch = ? AND status = "failed"', [$batch]);
    }
}

function outbox_counts(string $batch): array
{
    $c = ['queued' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0];
    if (outbox_ready()) {
        foreach (q_all('SELECT status, COUNT(*) AS n FROM mail_outbox WHERE batch = ? GROUP BY status', [$batch]) as $r) {
            $c[$r['status']] = (int) $r['n'];
        }
    }
    $c['total'] = array_sum($c);
    $c['left'] = $c['queued'] + $c['sending'];
    return $c;
}

/** Paskutinės siuntos (Pranešimas nariams → istorija; Valdymas - jei kas liko neišsiųsta) */
function outbox_batches(int $limit = 10, bool $onlyUnfinished = false): array
{
    if (!outbox_ready()) {
        return [];
    }
    return q_all('SELECT batch, MAX(title) AS title, MIN(created_at) AS created_at, COUNT(*) AS total, SUM(status = "sent") AS sent,
                         SUM(status = "failed") AS failed, SUM(status IN ("queued", "sending")) AS left_n
                    FROM mail_outbox GROUP BY batch' . ($onlyUnfinished ? ' HAVING left_n > 0' : '') . ' ORDER BY MIN(id) DESC LIMIT ' . max(1, $limit));
}
