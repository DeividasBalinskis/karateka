<?php
// Sekretoriato nuoroda (be paskyros): varžybų puslapyje → „🧑‍💼 Sekretoriatas“. Atidaręs įrašo vardą ir pavardę ir veda tik šias varžybas:
// dalyviai, kategorijos, kovos, balai, apdovanojimai. Kitų varžybų, klubo narių duomenų ir karateka.lt valdymo nemato.
// v2.01: nuoroda tatamiui (&t=N) - po vardo iškart atsidaro to tatamio sekretoriato darbastalis.
require __DIR__ . '/app/bootstrap.php';

// C3: viena nuoroda varžyboms (app/links.php, rūšis 'sekr'); veikia iki LINK_SEKR_DAYS dienų po varžybų. „Baigti darbą“ - action=end.
$token = (string) (get('k') ?: post('k'));
if (is_post() && post('action') === 'end') {
    csrf_check();
    varz_sekr_end((int) post('id'));
    varz_html_head('Sekretoriatas', 'vz-public vz-demo-start');
    echo '<main class="vzh demo-start"><div class="panel demo-card"><h2>Darbas baigtas</h2><p class="muted">Šiame įrenginyje sekretoriato prieiga baigta. Norėdami tęsti, vėl atidarykite nuorodą.</p></div></main></body></html>';
    exit;
}
$link = link_find('sekr', $token);
$tid = $link ? (int) $link['owner_id'] : 0;
$t = $tid ? tournament_get($tid) : null;
if (!$t) {
    link_dead();
}
$areaId = max(0, (int) (get('t') ?: post('t')));
$areaName = '';
if ($t && $areaId) {
    foreach ((array) (tournament_state($t)['areas'] ?? []) as $a) {
        if ((int) ($a['id'] ?? 0) === $areaId) {
            $areaName = (string) ($a['name'] ?? '');
        }
    }
    if ($areaName === '') {
        $areaId = 0;   // tokio tatamio nebėra - atsidarys įprastai (paklaus, prie kurio dirbate)
    }
}
$dest = 'admin/varzybu-sistema.php?id=' . $tid . ($areaId ? '&tatamis=' . $areaId : '') . '#live';
if ($t && (can('budo') || varz_sekr($tid))) {
    redirect($dest);
}
$error = '';
if (is_post() && $t) {
    csrf_check();
    $name = trim((string) post('name'));
    if (mb_strlen($name) < 2) {
        $error = 'Įrašykite savo vardą - kad būtų aišku, kas vedė varžybas.';   // vardas išsaugomas su kiekvienu pakeitimu (Budo istorija)
    } else {
        varz_sekr_start($link, $name);
        redirect($dest);
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
  <?php if ($t): ?>
    <form method="post" class="panel demo-card">
      <?= csrf_field() ?><input type="hidden" name="k" value="<?= e($token) ?>"><?php if ($areaId): ?><input type="hidden" name="t" value="<?= $areaId ?>"><?php endif; ?>
      <h2>🧑‍💼 Sekretoriatas</h2>
      <p>Su šia nuoroda vedate varžybas <b><?= e($t['title']) ?></b>: dalyviai, kategorijos, kovos, balai, apdovanojimai. Paskyros nereikia.</p>
      <?php if ($areaName !== ''): ?><p class="sekr-area"><b>📋 <?= e($areaName) ?></b> - atsidarys šio tatamio darbastalis: visos jo grupės, kovos ir Live kova.</p><?php endif; ?>
      <p class="muted small">Pastebėjimus rašykite per 💡 (apačioje kairėje).</p>
      <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
      <label>Jūsų vardas <span class="muted small">(pavardė - nebūtina)</span> <input class="sel" type="text" name="name" maxlength="80" required autofocus value="<?= e((string) post('name')) ?>" placeholder="pvz. Tomas"></label>
      <button class="btn btn-primary" type="submit">Pradėti →</button>
    </form>
  <?php endif; ?>
</main>
</body>
</html>
