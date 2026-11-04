<?php
// Peržiūra (tik adminui): persijungti į Test Parent / Test Junior ir atgal, pakeisti Test Junior diržą.
// Testinės paskyros sukuriamos pirmą kartą persijungus (app/test_people.php).
require __DIR__ . '/app/bootstrap.php';

if (!is_post()) {
    redirect('index.php');
}
csrf_check();
if (!view_admin()) {
    forbidden();
}

// Grįžtama ten pat (tik šios svetainės puslapis), į admino dalį iš testinės - į paskyrą
$back = (string) post('back');
$base = (string) (config('base_path') ?: '');
if ($base !== '' && strpos($back, $base . '/') === 0) {
    $back = substr($back, strlen($base));
}
$back = preg_match('~^/[\w\-./]*\.php(\?[\w=&%.\-]*)?$|^/$|^/admin/$~', $back) ? ltrim($back, '/') : '';

$mode = post('mode');
if (in_array($mode, ['admin', 'parent', 'junior'], true)) {
    view_switch($mode);
    if ($mode === 'admin') {
        redirect($back !== '' && strpos($back, 'paskyra.php') !== 0 ? $back : 'admin/');
    }
    redirect($back !== '' && strpos($back, 'admin/') !== 0 ? $back : 'paskyra.php');
}

// Test Junior diržas - kad matytų, kaip keičiasi pamokų aplankai, programa, diržo spalva
if (post('belt') !== '' || array_key_exists('belt', $_POST)) {
    $belt = belt_from_post('belt');
    $junior = q_value('SELECT am.member_id FROM account_members am JOIN accounts a ON a.id = am.account_id WHERE a.email = ? AND am.relation = "self" LIMIT 1', [TEST_JUNIOR_EMAIL]);
    if ($junior) {
        q('UPDATE members SET belt_level = ? WHERE id = ?', [$belt, (int) $junior]);
        flash('ok', 'Test Junior diržas: ' . ($belt ? BELTS[$belt][0] . ' (' . BELTS[$belt][1] . ')' : 'be diržo') . '.');
    }
}
redirect($back !== '' ? $back : 'paskyra.php');
