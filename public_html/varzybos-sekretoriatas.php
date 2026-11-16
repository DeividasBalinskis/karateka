<?php
// Sekretoriato nuoroda (be paskyros): varžybų puslapyje → „🧑‍💼 Sekretoriatas“. Atidaręs įrašo vardą ir pavardę ir veda tik šias varžybas:
// dalyviai, kategorijos, kovos, balai, apdovanojimai. Kitų varžybų, klubo narių duomenų ir karateka.lt valdymo nemato.
require __DIR__ . '/app/bootstrap.php';

$token = (string) (get('k') ?: post('k'));
$tid = varz_sekr_find($token);
$t = $tid ? tournament_get($tid) : null;
if ($t && (is_staff() || varz_sekr($tid))) {
    redirect('admin/varzybu-sistema.php?id=' . $tid);
}
$error = '';
if (is_post() && $t) {
    csrf_check();
    $name = trim((string) post('name'));
    if (mb_strlen($name) < 2) {
        $error = 'Įrašykite savo vardą - kad būtų aišku, kas vedė varžybas.';
    } else {
        varz_sekr_start($tid, $name);
        redirect('admin/varzybu-sistema.php?id=' . $tid . '#live');
    }
}
varz_html_head('Sekretoriatas', 'vz-public vz-demo-start');
?>
<header class="vz-top">
  <div class="vz-top-row">
    <span class="vz-brand"><span class="vz-logo" aria-hidden="true"></span><?= e(VARZ_NAME) ?></span>
    <div class="vz-title"><h1><?= $t ? e($t['title']) : 'Sekretoriatas' ?></h1><div class="sub"><?= $t ? e(fmt_date($t['starts_on'], true)) . ($t['location'] ? ' · ' . e($t['location']) : '') : 'Karateka · ' . e(VARZ_NAME) ?></div></div>
    <div class="vz-top-right"><?= render_lang_switch(tl_lang()) ?></div>
  </div>
</header>
<main class="vzh demo-start">
  <?php if (!$t): ?>
    <div class="panel demo-card"><h2>Nuoroda neveikia</h2><p class="muted">Ji išjungta arba pakeista. Paprašykite naujos nuorodos varžybų organizatoriaus.</p></div>
  <?php else: ?>
    <form method="post" class="panel demo-card">
      <?= csrf_field() ?><input type="hidden" name="k" value="<?= e($token) ?>">
      <h2>🧑‍💼 Sekretoriatas</h2>
      <p>Su šia nuoroda vedate varžybas <b><?= e($t['title']) ?></b>: dalyviai, kategorijos, kovos, balai, apdovanojimai. Paskyros nereikia.</p>
      <p class="muted small">Pastebėjimus rašykite per 💡 (apačioje kairėje).</p>
      <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
      <label>Jūsų vardas <span class="muted small">(pavardė - nebūtina)</span> <input class="sel" type="text" name="name" maxlength="80" required autofocus value="<?= e((string) post('name')) ?>" placeholder="pvz. Tomas"></label>
      <button class="btn btn-primary" type="submit">Pradėti →</button>
    </form>
  <?php endif; ?>
</main>
</body>
</html>
