<?php
// Atsiliepimai Claude (be naršyklės): kas kelias valandas Claude perskaito naujus atsiliepimus su nuotraukomis,
// padaro pakeitimus ir pažymi, kas padaryta. Prieiga - tik su raktu (Valdymas → Atsiliepimai → „Claude prieiga“), antraštė X-Api-Key.
//   GET  ?since=ID&status=open|all|revert[&group=budo|karateka|ltkf]  (revert - autorius prašo grąžinti kaip buvo)  - atsiliepimai (naujausi paskutiniai), su nuotraukų nuorodomis ir vieta (area)
//   GET  ?img=ID                    - nuotrauka (JPEG)
//   POST {"id":..,"status":"planned|question|done|declined","reply":"...","verdict":"reverted"|null}  (question - klausimas autoriui, reply = klausimas)  - būsena ir atsakymas autoriui (gauna pranešimą);
//        verdict - autoriaus įvertinimas: ok (patinka), revert (prašo grąžinti); Claude, atšaukęs pakeitimą, pažymi „reverted“ (null - išvalyti)
require __DIR__ . '/app/bootstrap.php';

header('Cache-Control: no-store');
if (!feedback_ready() || !feedback_api_check((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''))) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode(['ok' => false, 'error' => 'Netinkamas raktas.']));
}

if (get('img')) {
    $img = feedback_images_ready() ? q_one('SELECT * FROM feedback_images WHERE id = ?', [(int) get('img')]) : null;
    $path = $img ? FEEDBACK_IMG_DIR . '/' . basename($img['file']) : '';
    if (!$img || !is_file($path)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: image/jpeg');
    readfile($path);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
if (is_post()) {
    $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
    $f = q_one('SELECT * FROM feedback WHERE id = ?', [(int) ($in['id'] ?? 0)]);
    if (!$f) {
        http_response_code(404);
        exit(json_encode(['ok' => false, 'error' => 'Nerasta.']));
    }
    $status = isset(FEEDBACK_STATUS[$in['status'] ?? '']) ? $in['status'] : $f['status'];
    $reply = array_key_exists('reply', $in) ? trim((string) $in['reply']) : (string) $f['reply'];
    q('UPDATE feedback SET status = ?, reply = ?, updated_at = NOW() WHERE id = ?', [$status, $reply !== '' ? mb_substr($reply, 0, 2000) : null, (int) $f['id']]);
    if (array_key_exists('verdict', $in) && feedback_verdict_ready() && in_array($in['verdict'], [null, 'ok', 'revert', 'reverted'], true)) {
        q('UPDATE feedback SET verdict = ?, verdict_at = ' . ($in['verdict'] === null ? 'NULL' : 'NOW()') . ' WHERE id = ?', [$in['verdict'], (int) $f['id']]);
    }
    if ($f['account_id'] && ($status !== $f['status'] || $reply !== (string) $f['reply'])) {
        notify_account((int) $f['account_id'], 'feedback', ($status === 'question' ? '❓ ' : '') . t('Jūsų atsiliepimas:') . ' ' . mb_strtolower(t(FEEDBACK_STATUS[$status][0])),
            $reply !== '' ? $reply : mb_strimwidth($f['body'], 0, 200, '…'), (ltrim((string) $f['page'], '/') ?: 'index.php') . ($status === 'question' ? '#atsiliepimas' : ''), null, (int) $f['id'], false);
    }
    exit(json_encode(['ok' => true]));
}

$where = get('status') === 'all' ? '1' : (get('status') === 'revert' && feedback_verdict_ready() ? 'f.verdict = "revert"' : 'f.status IN ("new", "planned")');
$rows = q_all("SELECT f.*, a.first_name, a.last_name, a.role FROM feedback f LEFT JOIN accounts a ON a.id = f.account_id
                WHERE $where AND f.id > ? ORDER BY f.id LIMIT 200", [(int) get('since')]);
$base = rtrim((string) config('site_url'), '/');
$group = isset(FEEDBACK_GROUPS[get('group')]) ? (string) get('group') : '';
if ($group !== '') {
    $rows = array_values(array_filter($rows, function ($f) use ($group) { return feedback_row_area($f)['g'] === $group; }));
}
feedback_images_prefetch(array_column($rows, 'id'));
exit(json_encode(['ok' => true, 'version' => trim((string) @file_get_contents(APP_DIR . '/VERSION')), 'items' => array_map(function ($f) use ($base) {
    $ar = feedback_row_area($f);
    return ['id' => (int) $f['id'], 'created' => $f['created_at'], 'updated' => $f['updated_at'], 'status' => $f['status'], 'site' => $f['site'] ?? null,
            'group' => $ar['g'], 'area' => $ar['gl'] . ' › ' . $ar['s'] . ($ar['sec'] !== '' && $ar['sec'] !== $ar['s'] ? ' (' . $ar['sec'] . ')' : ''),
            'page' => $f['page'], 'author' => $f['account_id'] ? trim($f['first_name'] . ' ' . $f['last_name']) . ' (' . $f['role'] . ')' : ($f['name'] ?: 'lankytojas'),
            'body' => $f['body'], 'reply' => $f['reply'], 'verdict' => $f['verdict'] ?? null, 'verdict_at' => $f['verdict_at'] ?? null,
            'answer' => $f['answer'] ?? null, 'answered_at' => $f['answered_at'] ?? null, 'context' => json_decode((string) ($f['context'] ?? ''), true),
            'images' => array_map(function ($i) use ($base) { return $base . '/atsiliepimai-api.php?img=' . (int) $i['id']; }, feedback_images((int) $f['id']))];
}, $rows)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
