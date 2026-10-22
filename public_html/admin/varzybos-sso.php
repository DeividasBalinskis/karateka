<?php
// Į varžybų sistemą be atskiro prisijungimo (treneriui / adminui)
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();
if (!varzybos_enabled()) {
    flash('err', 'Varžybų sistema neprijungta (config.php: varzybos).');
    redirect('admin/varzybos.php');
}
$to = (string) get('to');
if (!preg_match('#^/(admin(/t/\d+)?|t/\d+)$#', $to)) {
    $to = '/admin';
}
header('Location: ' . varzybos_sso_url($me, $to), true, 302);
exit;
