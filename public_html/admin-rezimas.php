<?php
// Admin režimo jungiklis (viršutinėje juostoje): įjungia / išjungia redagavimo įrankius ir grįžta į tą patį puslapį
require __DIR__ . '/app/bootstrap.php';

$a = require_login();
if (!can('valdymas', $a) || !is_post()) {
    redirect('index.php');
}
csrf_check();
admin_mode_set(post('on') === '1');
$back = (string) post('back');
$base = (string) (config('base_path') ?: '');
if ($base !== '' && strpos($back, $base . '/') === 0) {
    $back = substr($back, strlen($base));
}
// Išjungus admin skiltyje - į savo dojo; kitur - tas pats puslapis
if (post('on') !== '1' && preg_match('~^/admin/~', $back)) {
    redirect('paskyra.php');
}
redirect(preg_match('~^/[\w\-./]*(\?[\w=&%.\-]*)?$~', $back) ? ltrim($back, '/') : (post('on') === '1' ? 'admin/' : 'paskyra.php'));
