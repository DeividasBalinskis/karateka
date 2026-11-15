<?php
// Taikos bandymo nuoroda (be paskyros): admin → Taika → „👀 Duoti išbandyti“. Atidaręs įrašo vardą ir patenka į Taiką:
// gali daryti viską, ką adminas, bet tik bandymo varžybose (be tikrų klubo narių); atsiliepimus palieka per 💡.
require __DIR__ . '/app/bootstrap.php';

$token = (string) (get('k') ?: post('k'));
$valid = $token !== '' && varz_demo_token() !== '' && hash_equals(varz_demo_token(), $token);
if ($valid && (is_staff() || varz_demo())) {
    redirect('admin/varzybos.php');
}
$error = '';
if (is_post() && $valid) {
    csrf_check();
    $name = trim((string) post('name'));
    if (mb_strlen($name) < 2) {
        $error = 'Įrašykite savo vardą - kad žinotume, kieno atsiliepimai.';
    } elseif (varz_demo_start($token, $name)) {
        redirect('admin/varzybos.php');
    }
}
varz_html_head('Išbandyti', 'vz-public vz-demo-start');
?>
<header class="vz-top">
  <div class="vz-top-row">
    <span class="vz-brand"><span class="vz-logo" aria-hidden="true"></span><?= e(VARZ_NAME) ?></span>
    <div class="vz-title"><h1>Varžybų sistemos bandymas</h1><div class="sub">Karateka · Taika</div></div>
  <div class="vz-top-right"><?= render_lang_switch(tl_lang()) ?></div>
  </div>
</header>
<main class="vzh demo-start">
  <?php if (!$valid): ?>
    <div class="panel demo-card"><h2>Nuoroda neveikia</h2><p class="muted">Ji išjungta arba pakeista. Paprašykite naujos nuorodos to, kas jums ją davė.</p></div>
  <?php else: ?>
    <form method="post" class="panel demo-card">
      <?= csrf_field() ?><input type="hidden" name="k" value="<?= e($token) ?>">
      <h2>Sveiki! 👋</h2>
      <p>Čia galite išbandyti varžybų sistemą kaip sekretoriatas ar organizatorius: kurti varžybas, dalyvius ir kategorijas, vesti kovas, rašyti balus, siųsti apdovanojimams.
        Dirbate tik su bandymo varžybomis - nieko nesugadinsite.</p>
      <p>Pastebėjimus rašykite per <b>💡 Atsiliepimas</b> (apačioje kairėje) - galima įklijuoti ekrano nuotrauką (Ctrl+V).</p>
      <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
      <label>Jūsų vardas <input class="sel" type="text" name="name" maxlength="60" required autofocus value="<?= e((string) post('name')) ?>" placeholder="pvz. Tomas"></label>
      <button class="btn btn-primary" type="submit">Pradėti →</button>
    </form>
  <?php endif; ?>
</main>
</body>
</html>
