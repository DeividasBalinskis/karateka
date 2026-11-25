<?php
// Varžybos: sukūrimas, redagavimas, vaizdas

function tournament_or_404(int $id): array
{
    $t = q_one('SELECT * FROM tournaments WHERE id = ?', [$id]);
    if (!$t) {
        fail('Varžybos nerastos.', 404);
    }
    return $t;
}

/** Po kiekvieno pakeitimo - kad atsinaujintų žiūrovų ekranai */
function bump_rev(int $tournamentId): void
{
    q('UPDATE tournaments SET rev = rev + 1 WHERE id = ?', [$tournamentId]);
}

function tournament_view(array $t, bool $withCounts = true): array
{
    $out = [
        'id'                  => (int) $t['id'],
        'title'               => $t['title'],
        'starts_on'           => $t['starts_on'],
        'ends_on'             => $t['ends_on'],
        'location'            => $t['location'],
        'description'         => $t['description'],
        'status'              => $t['status'],
        'status_label'        => TOURNAMENT_STATUSES[$t['status']] ?? $t['status'],
        'reg_deadline'        => $t['reg_deadline'],
        'age_basis'           => $t['age_basis'],
        'tatami_count'        => (int) $t['tatami_count'],
        'day_start'           => substr((string) $t['day_start'], 0, 5),
        'public_registration' => (bool) $t['public_registration'],
        'is_abroad'           => (bool) $t['is_abroad'],
        'registration_open'   => registration_open($t),
        'rev'                 => (int) $t['rev'],
    ];
    if ($withCounts) {
        $out['categories_count'] = (int) q_value('SELECT COUNT(*) FROM categories WHERE tournament_id = ?', [$t['id']]);
        $out['athletes_count'] = (int) q_value(
            'SELECT COUNT(DISTINCT COALESCE(em.athlete_id, e.athlete_id))
               FROM entries e JOIN categories c ON c.id = e.category_id
               LEFT JOIN entry_members em ON em.entry_id = e.id
              WHERE c.tournament_id = ? AND e.status = "active"',
            [$t['id']]
        );
        $out['clubs_count'] = (int) q_value(
            'SELECT COUNT(DISTINCT e.club_id) FROM entries e JOIN categories c ON c.id = e.category_id WHERE c.tournament_id = ? AND e.status = "active"',
            [$t['id']]
        );
    }
    return $out;
}

function registration_open(array $t): bool
{
    if ($t['status'] !== 'registration') {
        return false;
    }
    return !$t['reg_deadline'] || $t['reg_deadline'] >= date('Y-m-d');
}

function tournament_from_input(array $in, ?array $old = null): array
{
    $row = [];
    $title = str_or_null($in['title'] ?? $old['title'] ?? null);
    if (!$title) {
        fail('Įrašykite varžybų pavadinimą.');
    }
    $row['title'] = $title;
    $starts = $in['starts_on'] ?? $old['starts_on'] ?? null;
    if (!valid_date($starts)) {
        fail('Nurodykite varžybų datą.');
    }
    $row['starts_on'] = $starts;
    $ends = $in['ends_on'] ?? ($old['ends_on'] ?? null);
    $row['ends_on'] = valid_date($ends) && $ends >= $starts ? $ends : null;
    $row['location'] = str_or_null($in['location'] ?? $old['location'] ?? null);
    $row['description'] = str_or_null($in['description'] ?? $old['description'] ?? null, 5000);
    $status = $in['status'] ?? $old['status'] ?? 'draft';
    $row['status'] = isset(TOURNAMENT_STATUSES[$status]) ? $status : 'draft';
    $dl = $in['reg_deadline'] ?? ($old['reg_deadline'] ?? null);
    $row['reg_deadline'] = valid_date($dl) ? $dl : null;
    $basis = $in['age_basis'] ?? $old['age_basis'] ?? 'year';
    $row['age_basis'] = $basis === 'date' ? 'date' : 'year';
    $row['tatami_count'] = max(1, min(12, (int) ($in['tatami_count'] ?? $old['tatami_count'] ?? 2)));
    $start = $in['day_start'] ?? substr((string) ($old['day_start'] ?? '10:00'), 0, 5);
    $row['day_start'] = valid_time($start) ? $start : '10:00';
    $row['public_registration'] = !empty($in['public_registration'] ?? $old['public_registration'] ?? 0) ? 1 : 0;
    $row['is_abroad'] = !empty($in['is_abroad'] ?? $old['is_abroad'] ?? 0) ? 1 : 0;
    return $row;
}

function create_tournament(array $in): int
{
    return insert('tournaments', tournament_from_input($in));
}

function update_tournament(int $id, array $in): void
{
    $old = tournament_or_404($id);
    $row = tournament_from_input($in + $old, $old);
    update('tournaments', $row, 'id = ?', [$id]);
    // Sumažinus tatami skaičių - kategorijos iš "dingusių" tatami lieka nepriskirtos
    q('UPDATE categories SET tatami = NULL WHERE tournament_id = ? AND tatami > ?', [$id, $row['tatami_count']]);
    bump_rev($id);
}

/** Visi varžybų klubai su dalyvių skaičiumi */
function tournament_clubs(int $tid): array
{
    return q_all(
        'SELECT cl.id, cl.name, cl.city, cl.country, cl.contact_name, cl.contact_email, cl.contact_phone, cl.external_source,
                COUNT(DISTINCT COALESCE(em.athlete_id, e.athlete_id)) AS athletes, COUNT(DISTINCT e.id) AS entries
           FROM entries e
           JOIN categories c ON c.id = e.category_id
           JOIN clubs cl ON cl.id = e.club_id
           LEFT JOIN entry_members em ON em.entry_id = e.id
          WHERE c.tournament_id = ? AND e.status = "active"
          GROUP BY cl.id ORDER BY cl.name',
        [$tid]
    );
}
