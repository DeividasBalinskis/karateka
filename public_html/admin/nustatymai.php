<?php
// Nustatymai: tik tai, ko nėra meniu ir kitose skiltyse (kiekvienas dalykas - vienoje vietoje), ir trenerio paskyra
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

// Paskyros nustatymai (el. paštas, telefonas, slaptažodis, prisijungimas su kodu)
$errors = [];
if (is_post()) {
    csrf_check();
    if (post('action') === 'menu_variant' && is_admin($me) && isset(MENU_VARIANTS[post('v')])) {
        // Meniu išdėstymas visiems (#106); savo naršyklės peržiūra išvaloma
        set_site_setting('menu_variant', post('v'));
        setcookie('meniu', '', ['expires' => time() - 3600, 'path' => '/']);
        flash('ok', t('Meniu išdėstymas pakeistas visiems.'));
        redirect('admin/nustatymai.php#meniu');
    }
    if (post('action') === 'reg_toggles' && is_admin($me)) {
        // Registracijos mygtukai: pažymėta = rodomas; nepažymėtos vietos išjungiamos
        $on = array_map('strval', (array) ($_POST['reg'] ?? []));
        set_site_setting('reg_off', json_encode(array_values(array_diff(array_keys(REG_PLACES), $on))));
        flash('ok', t('Registracijos nustatymai išsaugoti.'));
        redirect('admin/nustatymai.php#registracija');
    }
    handle_account_settings($me, 'admin/nustatymai.php', $errors);
}
$ownMembers = (int) q_value('SELECT COUNT(*) FROM account_members am JOIN members m ON m.id = am.member_id WHERE am.account_id = ? AND m.status = "active"', [(int) $me['id']]);

// [pavadinimas, kelias, aprašymas, matomas]. Testai, Pranešimai, Varžybos - skirtukuose; reakcijų emoji - Naujienose;
// pamokų tvarkymas - Pamokose; pagrindinio puslapio redagavimas - pagrindiniame puslapyje.
$settings = [
    'Klubas' => [
        [t('Grupės ir jų spalvos'), 'admin/grupes.php', t('Pavadinimai, vietos, spalvos, kas kurioje grupėje.'), true],
        [t('Tvarkaraštis ir kainos'), 'admin/tvarkarastis.php', t('Visų grupių laikai ir kainos - rodoma ir pagrindiniame puslapyje.'), true],
        [t('Atšauktos treniruotės ir atostogos'), 'admin/atsaukimai.php', t('Dienos be treniruočių, pranešimas nariams.'), true],
        [t('Vertimai (EN / RU)'), 'admin/vertimai.php', t('Jūsų tekstai anglų ir rusų kalbomis: naujienos, renginiai, pamokos, pagrindinis puslapis.'), true],
        [t('Egzaminų programa'), 'admin/programa.php', t('Ką reikia mokėti kiekvienam diržui ir kiek aktyvumo taškų už punktą.'), true],
    ],
    'Sistema' => [
        [t('Paskyros ir rolės'), 'admin/paskyros.php', t('Kas yra treneris, kas administratorius.'), is_admin()],
        [t('Atsarginės kopijos ir sistema'), 'admin/kopijos.php', t('Duomenų bazės kopijos, versija, importuoti db failai.'), is_admin()],
        [t('Testavimas'), 'admin/testavimas.php', t('Testiniai nariai (tėvai, vaikas) ir ką jiems sukurti; peržiūra kaip tėvai ar vaikas.'), is_admin()],
    ],
    // LTKF ir Budo - per logotipo meniu ▾; Aktyvumo taškai, Nariai, Testai ir kt. - meniu (#105: be dublikatų)
];

page_start(t('Nustatymai'), ['admin' => true]);
?>
<h1 class="styled"><?= te('Nustatymai') ?></h1>
<?= form_errors($errors) ?>
<div class="settings-layout">
  <div class="settings-groups">
    <?php foreach ($settings as $groupName => $links): $links = array_filter($links, function ($l) { return $l[3]; }); if (!$links) { continue; } ?>
      <section class="panel card settings-group">
        <div class="kicker"><?= e(t($groupName)) ?></div>
        <?php foreach ($links as [$label, $path, $desc]): ?>
          <a class="settings-link" href="<?= url($path) ?>"><strong><?= e($label) ?></strong><span><?= e($desc) ?></span></a>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
    <?php if (is_admin($me)): $mvAll = (string) site_setting('menu_variant', 'dabar'); ?>
      <section class="panel card settings-group menu-pick" id="meniu">
        <div class="kicker"><?= te('Meniu išdėstymas') ?></div>
        <p class="small muted" style="margin:0 0 8px;"><?= te('„Peržiūrėti“ - tik jums (šioje naršyklėje), „Visiems“ - visiems treneriams.') ?></p>
        <?php foreach (MENU_VARIANTS as $k => [$label, $desc]): $on = menu_variant() === $k; ?>
          <div class="mp-row<?= $on ? ' on' : '' ?>"><span><b><?= e(t($label)) ?></b><?= $mvAll === $k ? ' <span class="badge ok">' . te('visiems') . '</span>' : '' ?><small><?= e(t($desc)) ?></small></span>
            <span class="mp-acts"><a class="btn btn-ghost btn-sm" href="<?= url('admin/nustatymai.php?meniu=' . $k . '#meniu') ?>"><?= $on ? '✓ ' . te('Dabar matote') : te('Peržiūrėti') ?></a>
              <?php if ($mvAll !== $k): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="menu_variant"><input type="hidden" name="v" value="<?= e($k) ?>"><button class="btn btn-sm" type="submit"><?= te('Visiems') ?></button></form><?php endif; ?></span></div>
        <?php endforeach; ?>
      </section>
      <section class="panel card settings-group reg-toggles" id="registracija">
        <div class="kicker"><?= te('Registracija') ?></div>
        <p class="small muted" style="margin:0 0 8px;"><?= te('Išjungus - registracijos mygtukas dingsta, o puslapis sako, kad registracija išjungta.') ?></p>
        <form method="post" class="reg-tg-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="reg_toggles">
          <?php foreach (REG_PLACES as $k => [$label, $desc]): ?>
            <label class="tg-row"><input type="checkbox" class="tg" name="reg[]" value="<?= e($k) ?>"<?= reg_on($k) ? ' checked' : '' ?> onchange="this.form.submit()">
              <span><b><?= e(t($label)) ?></b><small><?= e(t($desc)) ?></small></span></label>
          <?php endforeach; ?>
          <noscript><button class="btn btn-primary btn-sm" type="submit"><?= te('Išsaugoti') ?></button></noscript>
        </form>
      </section>
    <?php endif; ?>
  </div>
  <aside class="settings-side">
    <?= render_account_settings($me, 'paskyra', t('Mano paskyra')) ?>
    <?php if ($ownMembers): ?>
      <a class="panel card settings-link family-link" href="<?= url('paskyra.php') ?>"><strong><?= te('Mano šeimos nariai') ?></strong><span><?= te('Jūsų ir vaikų taškai, pastabos, egzaminas - kaip mato narys.') ?></span></a>
    <?php endif; ?>
    <?= render_app_install_card() ?>
    <form method="post" action="<?= url('atsijungti.php') ?>" class="dash-logout"><?= csrf_field() ?><button type="submit" class="btn btn-ghost btn-sm btn-block"><?= te('Atsijungti') ?></button></form>
  </aside>
</div>
<?php
page_end();
