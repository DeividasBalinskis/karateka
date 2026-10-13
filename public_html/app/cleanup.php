<?php
// Automatinis senų duomenų valymas (žr. privatumo politiką, 5 skyrius).
// Serveryje nėra cron, todėl paleidžiama retkarčiais apsilankius svetainėje (~1 iš 100 užklausų).

function run_cleanup(): void
{
    $days = (int) config('retention.unfinished_days', 30);

    // Nebaigtos registracijos: el. paštas nepatvirtintas arba negautas tėvų sutikimas
    $ids = q("SELECT id FROM accounts WHERE status IN ('pending_email', 'pending_parent') AND created_at < NOW() - INTERVAL $days DAY")
        ->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        // Nariai, kurie priklausė tik šiai paskyrai, ištrinami kartu
        $memberIds = q('SELECT member_id FROM account_members WHERE account_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN);
        q('DELETE FROM accounts WHERE id = ?', [$id]);   // account_members ir žetonai ištrinami kaskadiškai
        foreach ($memberIds as $mid) {
            q('DELETE FROM members WHERE id = ? AND status = "pending" AND NOT EXISTS (SELECT 1 FROM account_members WHERE member_id = ?)', [$mid, $mid]);
        }
    }

    q('DELETE FROM email_tokens WHERE (used_at IS NOT NULL OR expires_at < NOW()) AND created_at < NOW() - INTERVAL 30 DAY');
    q('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 30 DAY');
}

function maybe_run_cleanup(): void
{
    if (PHP_SAPI !== 'cli' && random_int(1, 100) === 1) {
        try {
            run_cleanup();
        } catch (Throwable $ex) {
            error_log('cleanup failed: ' . $ex->getMessage());
        }
    }
}
