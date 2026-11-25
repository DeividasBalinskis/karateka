<?php
// CSV failai (atsidaro Excel): export.php?t=1&type=results | entries
require __DIR__ . '/app/bootstrap.php';
start_session();

$t = q_one('SELECT * FROM tournaments WHERE id = ?', [(int) ($_GET['t'] ?? 0)]);
$type = $_GET['type'] ?? 'results';
if (!$t || ($t['status'] === 'draft' && !current_user())) {
    http_response_code(404);
    exit('Varžybos nerastos.');
}
if ($type === 'entries' && !current_user()) {
    http_response_code(403);
    exit('Reikia prisijungti.');
}

$slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $t['title']) ?: 'varzybos')), '-');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $slug . '-' . ($type === 'entries' ? 'dalyviai' : 'rezultatai') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");   // BOM - kad Excel teisingai rodytų lietuviškas raides
$put = fn (array $row) => fputcsv($out, $row, ';');

if ($type === 'entries') {
    $put(['Kategorija', 'Dalyvis', 'Klubas', 'Gimimo data', 'Lytis', 'Diržas', 'Svoris', 'Šaltinis', 'karateka.lt id']);
    foreach (q_all('SELECT * FROM categories WHERE tournament_id = ? ORDER BY sort_order, id', [$t['id']]) as $c) {
        foreach (category_entries((int) $c['id'], true) as $e) {
            $put([$c['name'], $e['name'], $e['club'], $e['birth_date'], $e['gender'], belt_short($e['belt_level']), $e['weight_kg'],
                ['admin' => 'organizatorius', 'public' => 'vieša forma', 'api' => 'karateka.lt'][$e['source']] ?? $e['source'],
                ($e['external']['source'] ?? null) === 'karateka' ? $e['external']['id'] : implode(',', array_filter(array_map(fn ($m) => $m['external_source'] === 'karateka' ? $m['external_id'] : null, $e['members'])))]);
        }
    }
} else {
    $put(['Kategorija', 'Vieta', 'Dalyvis', 'Klubas', 'Sportininkai']);
    foreach (q_all('SELECT * FROM categories WHERE tournament_id = ? ORDER BY sort_order, id', [$t['id']]) as $c) {
        foreach (category_results($c) as $r) {
            $put([$c['name'], $r['place'], $r['name'], $r['club'], implode(', ', array_map(fn ($a) => $a['name'], $r['athletes']))]);
        }
    }
}
fclose($out);
