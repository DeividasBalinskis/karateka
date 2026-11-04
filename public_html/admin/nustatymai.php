<?php
// Nustatymai: tik tai, ko nėra meniu ir kitose skiltyse (kiekvienas dalykas - vienoje vietoje), ir trenerio paskyra
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

// Paskyros nustatymai (el. paštas, telefonas, slaptažodis, prisijungimas su kodu)
$errors = [];
if (is_post()) {
    csrf_check();
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
        [t('Taškai ir XP'), 'admin/taskai.php', t('Kiek taškų už ką, kiek XP vienam lygiui.'), true],
    ],
    'Egzaminai' => [
        [t('Egzaminų programa'), 'admin/programa.php', t('Ką reikia mokėti kiekvienam diržui ir kiek XP už punktą.'), true],
    ],
    'Sistema' => [
        [t('Paskyros ir rolės'), 'admin/paskyros.php', t('Kas yra treneris, kas administratorius.'), is_admin()],
        [t('Atsarginės kopijos ir sistema'), 'admin/kopijos.php', t('Duomenų bazės kopijos, versija, importuoti db failai.'), is_admin()],
        [t('Testavimas'), 'admin/testavimas.php', t('Testiniai nariai (tėvai, vaikas) ir ką jiems sukurti; peržiūra kaip tėvai ar vaikas.'), is_admin()],
    ],
    // Juodraščiai - tik test svetainėje ir kompiuteryje; vėliau - atskiri subdomenai
    'Projektai (juodraščiai)' => [
        [t('LTKF svetainė'), 'ltkf/', t('Federacijos svetainės juodraštis: klubai, kalendorius, rezultatai, teisėjai, dokumentai.'), is_admin() && config('env') !== 'live'],
        [t('Varžybų rezultatai (viešai)'), 'varzybu-rezultatai.php', t('Kaip varžybų rezultatus mato lankytojai ir LTKF. Varžybos tvarkomos skirtuke „Varžybos“.'), is_admin() && config('env') !== 'live'],
    ],
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
