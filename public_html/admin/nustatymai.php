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
        ['Grupės ir jų spalvos', 'admin/grupes.php', 'Pavadinimai, vietos, spalvos, kas kurioje grupėje.', true],
        ['Tvarkaraštis ir kainos', 'admin/tvarkarastis.php', 'Visų grupių laikai ir kainos - rodoma ir pagrindiniame puslapyje.', true],
        ['Atšauktos treniruotės ir atostogos', 'admin/atsaukimai.php', 'Dienos be treniruočių, pranešimas nariams.', true],
        ['Taškai ir XP', 'admin/taskai.php', 'Kiek taškų už ką, kiek XP vienam lygiui.', true],
    ],
    'Egzaminai' => [
        ['Egzaminų programa', 'admin/programa.php', 'Ką reikia mokėti kiekvienam diržui ir kiek XP už punktą.', true],
    ],
    'Sistema' => [
        ['Paskyros ir rolės', 'admin/paskyros.php', 'Kas yra treneris, kas administratorius.', is_admin()],
        ['Atsarginės kopijos ir sistema', 'admin/kopijos.php', 'Duomenų bazės kopijos, versija, importuoti db failai.', is_admin()],
        ['Testavimas', 'admin/testavimas.php', 'Testiniai nariai (tėvai, vaikas) ir ką jiems sukurti; peržiūra kaip tėvai ar vaikas.', is_admin()],
    ],
];

page_start('Nustatymai', ['admin' => true]);
?>
<h1 class="styled">Nustatymai</h1>
<?= form_errors($errors) ?>
<div class="settings-layout">
  <div class="settings-groups">
    <?php foreach ($settings as $groupName => $links): $links = array_filter($links, function ($l) { return $l[3]; }); if (!$links) { continue; } ?>
      <section class="panel card settings-group">
        <div class="kicker"><?= e($groupName) ?></div>
        <?php foreach ($links as [$label, $path, $desc]): ?>
          <a class="settings-link" href="<?= url($path) ?>"><strong><?= e($label) ?></strong><span><?= e($desc) ?></span></a>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
  </div>
  <aside class="settings-side">
    <?= render_account_settings($me, 'paskyra', 'Mano paskyra') ?>
    <?php if ($ownMembers): ?>
      <a class="panel card settings-link family-link" href="<?= url('paskyra.php') ?>"><strong>Mano šeimos nariai</strong><span>Jūsų ir vaikų taškai, pastabos, egzaminas - kaip mato narys.</span></a>
    <?php endif; ?>
    <?= render_app_install_card() ?>
    <form method="post" action="<?= url('atsijungti.php') ?>" class="dash-logout"><?= csrf_field() ?><button type="submit" class="btn btn-ghost btn-sm btn-block">Atsijungti</button></form>
  </aside>
</div>
<?php
page_end();
