<?php
// v2.02: Peržiūros darbų eilė taisymo agentui (debesyje, kas valandą). Prieiga - tik su „API prieigos“ raktu (Valdymas → Atsiliepimai), antraštė X-Api-Key.
//   GET                       - laukiantys ir daromi darbai (fix / deploy / review) su atsiliepimų tekstais, dabartinis pakeitimų sąrašas ir jo įvertinimai
//   GET ?all=1                - ir paskutiniai 30 baigtų darbų
//   POST {"id":..,"status":"working|done|failed","result":".."}  - darbo būsena ir atsakymas (matomas Valdymas → 🔎 Peržiūra)
//   POST {"new":{"kind":"fix","items":[{"id":..}],"note":".."},"status":"done","result":".."} - agento paties pradėtas darbas (be prašymo)
// Atsiliepimų būsenos ir atsakymai autoriams - per atsiliepimai-api.php, db atnaujinimas po įkėlimo - per diegimas-api.php.
require __DIR__ . '/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!feedback_ready() || !feedback_api_check((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''))) {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'error' => 'Netinkamas raktas.']));
}
if (!review_ready()) {
    exit(json_encode(['ok' => false, 'error' => 'Dar neimportuotas db/060 (POST diegimas-api.php {"action":"dbupdate"}).', 'db_required' => DB_REQUIRED]));
}

if (is_post()) {
    $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
    // Agento paties pradėtas darbas (be prašymo - taiso aiškius atsiliepimus): įrašomas į eilę, kad matytųsi Peržiūroje
    if (isset($in['new']) && is_array($in['new']) && in_array($in['new']['kind'] ?? '', ['fix', 'deploy', 'review'], true)) {
        $id = review_job_add((string) $in['new']['kind'], (array) ($in['new']['items'] ?? []), (string) ($in['new']['note'] ?? ''), null, (string) ($in['new']['rev'] ?? ''));
        $in['id'] = $id;
        $in['status'] = in_array($in['status'] ?? '', ['working', 'done', 'failed'], true) ? $in['status'] : 'working';
    }
    $job = q_one('SELECT * FROM review_jobs WHERE id = ?', [(int) ($in['id'] ?? 0)]);
    $status = (string) ($in['status'] ?? '');
    if (!$job || !in_array($status, ['working', 'done', 'failed'], true)) {
        http_response_code(400);
        exit(json_encode(['ok' => false, 'error' => 'Nėra tokio darbo arba netinkama būsena.']));
    }
    $result = array_key_exists('result', $in) ? mb_substr(trim((string) $in['result']), 0, 4000) : (string) $job['result'];
    q('UPDATE review_jobs SET status = ?, result = ?, started_at = IF(? = "working", NOW(), IFNULL(started_at, NOW())),
       finished_at = IF(? IN ("done", "failed"), NOW(), NULL) WHERE id = ?', [$status, $result !== '' ? $result : null, $status, $status, (int) $job['id']]);
    exit(json_encode(['ok' => true, 'id' => (int) $job['id']]));
}

$jobs = q_all('SELECT j.*, a.first_name, a.last_name FROM review_jobs j LEFT JOIN accounts a ON a.id = j.account_id
               WHERE j.status IN ("queued", "working")' . (get('all') ? ' OR j.id > (SELECT IFNULL(MAX(id), 0) - 30 FROM review_jobs)' : '') . ' ORDER BY j.id');
$fbIds = [];
foreach ($jobs as $j) {
    if ($j['kind'] === 'fix') {
        foreach ((array) json_decode((string) $j['items'], true) as $it) {
            $fbIds[] = (int) ($it['id'] ?? 0);
        }
    }
}
$fb = [];
if ($fbIds) {
    foreach (q_all('SELECT id, status, page, site, body, reply, context FROM feedback WHERE id IN (' . implode(',', array_map('intval', array_unique($fbIds))) . ')') as $f) {
        $fb[(int) $f['id']] = ['status' => $f['status'], 'page' => $f['page'], 'site' => $f['site'], 'body' => $f['body'], 'reply' => $f['reply'],
                               'context' => json_decode((string) $f['context'], true)];
    }
}
$m = review_manifest();
exit(json_encode([
    'ok' => true, 'version' => site_version(), 'db_required' => DB_REQUIRED,
    'jobs' => array_map(function ($j) use ($fb) {
        $items = json_decode((string) $j['items'], true) ?: [];
        if ($j['kind'] === 'fix') {
            $items = array_map(function ($it) use ($fb) { return $it + ['feedback' => $fb[(int) ($it['id'] ?? 0)] ?? null]; }, $items);
        }
        return ['id' => (int) $j['id'], 'kind' => $j['kind'], 'status' => $j['status'], 'rev' => $j['rev'], 'note' => $j['note'], 'result' => $j['result'],
                'by' => trim($j['first_name'] . ' ' . $j['last_name']), 'created' => $j['created_at'], 'started' => $j['started_at'], 'finished' => $j['finished_at'],
                'items' => $items];
    }, $jobs),
    'review' => ['rev' => $m['rev'], 'version' => $m['version'], 'items' => $m['items'], 'marks' => $m['rev'] !== '' ? array_map(function ($r) {
        return ['id' => $r['change_id'], 'by' => trim($r['first_name'] . ' ' . $r['last_name']), 'mark' => $r['mark'], 'comment' => $r['comment'],
                'submitted' => $r['job_id'] !== null, 'at' => $r['updated_at']];
    }, review_marks_all($m['rev'])) : []],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
