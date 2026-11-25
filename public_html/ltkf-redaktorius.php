<?php
// LTKF redaktoriaus nuoroda (admin → LTKF peržiūra → Redaktoriai): be paskyros, tik su vardu. Atidarius - LTKF svetainė redagavimo režimu.
// Nuoroda asmeninė - adminas gali ją išjungti, tada redaguoti nebegalima.
require __DIR__ . '/app/bootstrap.php';

// C3: nuoroda - app/links.php (rūšis 'ltkf_editor', vardas - label); sesijoje - tik pirštų atspaudas
$tok = (string) (get('k') ?: post('k'));
$ed = link_find('ltkf_editor', $tok);
if (!$ed) {
    link_dead();
}
$err = '';
if ($ed && is_post()) {
    csrf_check();
    $name = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) post('name'))), 0, 80);
    if (mb_strlen($name) < 2) {
        $err = 'Įrašykite savo vardą.';
    } else {
        session_regenerate_id(true);
        $_SESSION['ltkf_editor'] = (string) $ed['token_hash'];
        $_SESSION['ltkf_editor_name'] = $name;   // atsiliepimų langas LTKF svetainėje - ltkf_review() priima ir redaktorių
        csrf_token();
        redirect('ltkf/index.php?redaguoti=1');
    }
}
if (!$ed) {
    http_response_code(404);
}
?><!doctype html>
<html lang="lt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>LTKF redagavimas</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0F1B33;color:#F4F1EA;font:16px/1.5 system-ui,sans-serif;padding:20px}
.card{width:min(440px,100%);background:#17284A;border-radius:16px;padding:24px 26px} h1{margin:0 0 8px;font-size:22px}
input{width:100%;box-sizing:border-box;padding:12px 14px;border-radius:10px;border:0;font:inherit;margin:8px 0 12px} button{width:100%;padding:12px;border:0;border-radius:10px;background:#C1272D;color:#fff;font:700 16px system-ui;cursor:pointer}
.err{color:#FFB4A0} small{opacity:.75}</style></head>
<body><div class="card">
<?php if ($ed): ?>
  <h1>✏️ LTKF svetainės redagavimas</h1>
  <p>Įrašykite savo vardą - jis bus matomas prie jūsų pakeitimų. Paskyros nereikia.</p>
  <?php if ($err): ?><p class="err"><?= e($err) ?></p><?php endif; ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="k" value="<?= e($tok) ?>">
    <input type="text" name="name" value="<?= e((string) post('name') ?: (string) $ed['label']) ?>" placeholder="Vardas Pavardė" required autofocus>
    <button type="submit">Pradėti redaguoti</button></form>
  <p><small>Spaudžiate bet kurį tekstą ir rašote; nuotraukas, failus ir naujus puslapius pridedate apačios juostoje.</small></p>
<?php else: ?>
  <h1>Nuoroda neveikia</h1><p>Ji išjungta arba pakeista. Paprašykite naujos nuorodos to, kas jums ją atsiuntė.</p>
<?php endif; ?>
</div></body></html>
