<?php
// Taikos bandymo nuoroda (be paskyros): admin → Taika → „👀 Duoti išbandyti“. Atidaręs įrašo vardą ir patenka į Taiką:
// gali daryti viską, ką adminas, bet tik bandymo varžybose (be tikrų klubo narių); atsiliepimus palieka per 💡.
require __DIR__ . '/app/bootstrap.php';

// C3: nuoroda - app/links.php (rūšis 'demo'). „Baigti darbą“ - action=end.
$token = (string) (get('k') ?: post('k'));
if (is_post() && post('action') === 'end') {
    csrf_check();
    unset($_SESSION['taika_demo']);
    varz_html_head('Išbandyti', 'vz-public vz-demo-start');
    echo '<main class="vzh demo-start"><div class="panel demo-card"><h2>Darbas baigtas</h2><p class="muted">Ačiū! Norėdami tęsti, vėl atidarykite nuorodą.</p></div></main></body></html>';
    exit;
}
$link = link_find('demo', $token);
if (!$link) {
    link_dead();
}
$valid = true;
if (can('budo') || varz_demo()) {
    redirect('admin/varzybos.php');
}
$error = '';
if (is_post() && $valid) {
    csrf_check();
    $name = trim((string) post('name'));
    if (mb_strlen($name) < 2) {
        $error = 'Įrašykite savo vardą - kad žinotume, kieno atsiliepimai.';
    } else {
        varz_demo_start($link, $name);
        redirect('admin/varzybos.php');
    }
}
varz_html_head('Išbandyti', 'vz-public vz-demo-start');
?>
<header class="vz-top">
  <div class="vz-top-row">
    <span class="vz-brand"><span class="vz-logo" aria-hidden="true"></span><?= e(VARZ_NAME) ?></span>
    <div class="vz-title"><h1>Varžybų sistemos bandymas</h1><div class="sub">Karateka · <?= e(VARZ_NAME) ?></div></div>
  <div class="vz-top-right"><?= render_lang_switch(tl_lang()) ?></div>
  </div>
</header>
<main class="vzh demo-start">
  <?php if ($valid): ?>
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
