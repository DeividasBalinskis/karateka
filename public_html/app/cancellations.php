<?php
// Atšauktos treniruotės ir atostogos: nuo-iki, priežastis, grupės (nė vienos pažymėtos = visos grupės).
// Rodoma: „Šiandien“, „Treniruotė“, nario tvarkaraštis, pagrindinio puslapio tvarkaraštis. Tvarko adminas - admin/atsaukimai.php.

/** Atšaukimai, kurie dar nesibaigė ir prasideda per $days d., su grupių id ('groups' => [], jei visos) */
function cancellations_ahead(int $days = 60): array
{
    static $cache = [];
    if (!isset($cache[$days])) {
        try {
            $rows = q_all('SELECT * FROM training_cancellations WHERE ends_on >= CURDATE() AND starts_on <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                            ORDER BY starts_on, id', [$days]);
        } catch (PDOException $ex) {
            $rows = [];   // dar neimportuotas db/023
        }
        $cache[$days] = cancellations_with_groups($rows);
    }
    return $cache[$days];
}

function cancellations_with_groups(array $rows): array
{
    if (!$rows) {
        return [];
    }
    $ids = implode(',', array_map('intval', array_column($rows, 'id')));
    $groups = q("SELECT cancellation_id, group_id FROM training_cancellation_groups WHERE cancellation_id IN ($ids)")->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);
    foreach ($rows as &$r) {
        $r['groups'] = array_map('intval', $groups[$r['id']] ?? []);
    }
    unset($r);
    return $rows;
}

function cancellation_applies(array $c, int $groupId): bool
{
    return !$c['groups'] || in_array($groupId, $c['groups'], true);
}

/** Ar grupės treniruotė tą dieną atšaukta: atšaukimas arba null */
function training_cancelled(int $groupId, string $date): ?array
{
    static $byDate = [];
    if (!isset($byDate[$date])) {
        try {
            $byDate[$date] = cancellations_with_groups(q_all('SELECT * FROM training_cancellations WHERE ? BETWEEN starts_on AND ends_on ORDER BY id', [$date]));
        } catch (PDOException $ex) {
            $byDate[$date] = [];
        }
    }
    foreach ($byDate[$date] as $c) {
        if (cancellation_applies($c, $groupId)) {
            return $c;
        }
    }
    return null;
}

/** Artimiausi atšaukimai, liečiantys bent vieną iš grupių */
function cancellations_for_groups(array $groupIds, int $days = 30): array
{
    $groupIds = array_map('intval', $groupIds);
    return array_values(array_filter(cancellations_ahead($days), function ($c) use ($groupIds) {
        return !$c['groups'] || array_intersect($c['groups'], $groupIds);
    }));
}

/** „lapkričio 1 d.“ arba „gruodžio 24 d. – sausio 6 d.“ */
function cancellation_dates(array $c): string
{
    return $c['starts_on'] === $c['ends_on'] ? fmt_date($c['starts_on']) : fmt_date($c['starts_on']) . ' – ' . fmt_date($c['ends_on']);
}

/** Įspėjimai virš tvarkaraščio: „Lapkričio 1 d. treniruočių nebus · Visų šventų diena“ */
function render_cancellation_notes(array $list): string
{
    $html = '';
    foreach ($list as $c) {
        $dates = cancellation_dates($c);
        $html .= '<div class="cancel-note"><span aria-hidden="true">⚠</span> <span><strong>' . e(mb_strtoupper(mb_substr($dates, 0, 1)) . mb_substr($dates, 1)) . '</strong> treniruočių nebus'
            . ($c['reason'] !== '' ? ' · ' . e($c['reason']) : '') . '</span></div>';
    }
    return $html;
}

