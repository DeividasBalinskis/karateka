<?php
// LTKF peržiūros nuoroda federacijos nariams (admin → admin/ltkf-perziura.php). Atidarius - LTKF svetainė,
// kurioje be paskyros galima palikti atsiliepimus (💡); nuorodos į karateka.lt nerodomos.
require __DIR__ . '/app/bootstrap.php';

if (ltkf_review_start((string) get('k')) || is_admin()) {
    redirect('ltkf/index.php');
}
http_response_code(404);
?><!doctype html>
<html lang="lt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>LTKF peržiūra</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0F1B33;color:#F4F1EA;font:16px/1.5 system-ui,sans-serif;padding:20px}
.card{max-width:420px;background:#17284A;border-radius:16px;padding:24px 26px}</style></head>
<body><div class="card"><h1 style="margin:0 0 8px;font-size:22px">Nuoroda neveikia</h1>
<p style="margin:0;opacity:.85">Ji išjungta arba pakeista. Paprašykite naujos nuorodos to, kas jums ją atsiuntė.</p></div></body></html>
