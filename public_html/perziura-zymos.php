<?php
// v2.02: Peržiūros (auksinių žymų) juostos veiksmai (assets/review.js) - tik tikram administratoriui, ne gyvoje svetainėje.
//   POST {"action":"mark","rev":..,"id":..,"mark":"good|bad|null","comment":".."}  - auksu pažymėto pakeitimo įvertinimas (juodraštis)
//   POST {"action":"submit","rev":..}                                               - pateikti: darbas „review“ taisymo agentui
//   POST {"action":"pick","comment":..,"selector":..,"text":..,"page":..,"site":..} - 🎯 pastaba apie bet kurį elementą (atsiliepimas)
require __DIR__ . '/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$a = current_account();
if (!is_post() || !review_can($a)) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => t('Tik administratoriui test svetainėje.')], JSON_UNESCAPED_UNICODE));
}
csrf_check();
$in = json_decode((string) file_get_contents('php://input'), true) ?: [];
$m = review_manifest();
$ids = array_column($m['items'], null, 'id');
$fail = function (string $msg) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE));
};

switch ((string) ($in['action'] ?? '')) {
    case 'mark':
        $rev = (string) ($in['rev'] ?? '');
        $id = (string) ($in['id'] ?? '');
        if ($rev !== $m['rev'] || !isset($ids[$id])) {
            $fail(t('Pakeitimų sąrašas atsinaujino - atnaujinkite puslapį.'));
        }
        $mark = in_array($in['mark'] ?? null, ['good', 'bad'], true) ? $in['mark'] : null;
        $comment = mb_substr(trim(str_replace("\r\n", "\n", (string) ($in['comment'] ?? ''))), 0, 2000);
        q('INSERT INTO review_marks (rev, change_id, account_id, mark, comment, job_id, updated_at) VALUES (?, ?, ?, ?, ?, NULL, NOW())
           ON DUPLICATE KEY UPDATE mark = VALUES(mark), comment = VALUES(comment), job_id = NULL, updated_at = NOW()',
            [$rev, $id, (int) $a['id'], $mark, $comment !== '' ? $comment : null]);
        exit(json_encode(['ok' => true]));

    case 'submit':
        if ((string) ($in['rev'] ?? '') !== $m['rev'] || !$m['items']) {
            $fail(t('Pakeitimų sąrašas atsinaujino - atnaujinkite puslapį.'));
        }
        $mine = review_marks_mine($m['rev'], (int) $a['id']);
        $rows = [];
        foreach ($m['items'] as $it) {
            $rows[] = ['id' => $it['id'], 'title' => $it['title'], 'page' => $it['page'], 'sel' => $it['sel'],
                       'mark' => $mine[$it['id']]['mark'] ?? null, 'comment' => $mine[$it['id']]['comment'] ?? ''];
        }
        $bad = count(array_filter($rows, function ($r) { return $r['mark'] === 'bad'; }));
        $good = count(array_filter($rows, function ($r) { return $r['mark'] === 'good'; }));
        $job = review_job_add('review', $rows, '', $a, $m['rev']);
        // Neįvertinti irgi pažymimi kaip pateikti (be įvertinimo), kad juosta rodytų „Pateikta“
        foreach ($m['items'] as $it) {
            q('INSERT INTO review_marks (rev, change_id, account_id, mark, comment, job_id, updated_at) VALUES (?, ?, ?, NULL, NULL, ?, NOW())
               ON DUPLICATE KEY UPDATE job_id = VALUES(job_id)', [$m['rev'], $it['id'], (int) $a['id'], $job]);
        }
        $msg = $bad ? t('Pateikta: {n} blogi - bus taisomi (agentas pasiims per valandą).', ['n' => $bad])
            : ($good === count($rows) ? t('Pateikta: viskas gerai - bus keliama į main.') : t('Pateikta - agentas pasiims per valandą'));
        exit(json_encode(['ok' => true, 'job' => $job, 'message' => $msg], JSON_UNESCAPED_UNICODE));

    case 'pick':
        $comment = trim((string) ($in['comment'] ?? ''));
        $sel = mb_substr(trim((string) ($in['selector'] ?? '')), 0, 300);
        $site = isset(FEEDBACK_SITES[$in['site'] ?? '']) ? (string) $in['site'] : 'karateka';
        $ctx = json_encode(['pick' => true, 'selector' => $sel, 'text' => mb_substr((string) ($in['text'] ?? ''), 0, 160),
                            'url' => mb_substr((string) ($in['url'] ?? ''), 0, 400), 'w' => (int) ($in['w'] ?? 0)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $r = feedback_add($a, $comment, mb_substr((string) ($in['page'] ?? ''), 0, 255), '', $site, $ctx);
        if (!is_int($r)) {
            $fail((string) $r);
        }
        exit(json_encode(['ok' => true, 'id' => $r]));
}
$fail(t('Nežinomas veiksmas.'));
