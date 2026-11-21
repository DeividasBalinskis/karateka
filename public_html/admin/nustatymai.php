<?php
// Nustatymai: tik tai, ko nėra meniu ir kitose skiltyse (kiekvienas dalykas - vienoje vietoje), ir trenerio paskyra
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

// Paskyros nustatymai (el. paštas, telefonas, slaptažodis, prisijungimas su kodu)
$errors = [];
if (is_post()) {
    csrf_check();
    if (post('action') === 'reg_toggles' && is_admin($me)) {
        // Registracijos mygtukai: pažymėta = rodomas; nepažymėtos vietos išjungiamos
        $on = array_map('strval', (array) ($_POST['reg'] ?? []));
        set_site_setting('reg_off', json_encode(array_values(array_diff(array_keys(REG_PLACES), $on))));
        flash('ok', t('Registracijos nustatymai išsaugoti.'));
        redirect('admin/nustatymai.php#registracija');
    }
    if (post('action') === 'game_access' && is_admin($me)) {
        // Kas mato žaidimą: visi arba tik pasirinktos paskyros (pagal nutylėjimą - Denio, db/043)
        require_once APP_DIR . '/game.php';
        $ids = game_access_ids() ?? [];
        $do = post('do');
        if ($do === 'all') {
            set_site_setting('game_access', 'all');
        } elseif ($do === 'me') {
            set_site_setting('game_access', json_encode([(int) $me['id']]));
        } elseif ($do === 'remove') {
            set_site_setting('game_access', json_encode(array_values(array_diff($ids, [(int) post('id')]))));
        } elseif ($do === 'add') {
            $acc = q_one('SELECT id FROM accounts WHERE email = ?', [mb_strtolower(trim((string) post('email')))]);
            if ($acc) {
                set_site_setting('game_access', json_encode(array_values(array_unique(array_merge($ids, [(int) $acc['id']])))));
            } else {
                flash('err', t('Tokios paskyros nėra - patikrinkite el. paštą.'));
                redirect('admin/nustatymai.php#zaidimas');
            }
        }
        flash('ok', t('Išsaugota: kas mato žaidimą.'));
        redirect('admin/nustatymai.php#zaidimas');
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
    ],
    'Sistema' => [
        [t('Paskyros ir rolės'), 'admin/paskyros.php', t('Kas yra treneris, kas administratorius.'), is_admin()],
        [t('Atsarginės kopijos ir sistema'), 'admin/kopijos.php', t('Duomenų bazės kopijos, nuotraukų kopija, versija, importuoti db failai.'), is_admin()],
        [t('Duomenų priežiūra'), 'admin/duomenys.php', t('Kas ir kiek laiko saugoma; ką galima ištrinti (pirmiau parodoma sąrašu).'), is_admin()],
        [t('Kodas kitam įrenginiui'), 'admin/kitas-irenginys.php', t('Vienkartinis kodas planšetei ar kitam telefonui: dirbti jūsų teisėmis be slaptažodžio. Atjungti, žurnalas.'), is_admin()],
        [t('Testavimas'), 'admin/testavimas.php', t('Testiniai nariai (tėvai, vaikas) ir ką jiems sukurti; peržiūra kaip tėvai ar vaikas.'), is_admin()],
    ],
    // LTKF ir Budo - per logotipo meniu ▾; Aktyvumo taškai, Nariai, Testai, Egzaminų programa (Egzaminai) ir kt. - meniu (#105, #120: be dublikatų)
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
    <?php if (is_admin($me) || is_tester($me)): $ro = !is_admin($me); ?>
      <?php if ($ro): ?><p class="panel card ro-note">🧪 <?= te('Jūs - testuotojas: nustatymus matote, bet keisti gali tik administratorius.') ?></p><fieldset disabled class="ro-set"><?php endif; ?>
      <?php /* B9 (2026-11-21): meniu variantų pasirinkimas išimtas - vienas išdėstymas visiems */ ?>
      <?php /* „🏯 Žaidimas“ skiltis išimta (#145): mato administratoriai, testuotojai - ne; Dojo - Testavimas puslapyje */ ?>
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
      <?php if ($ro): ?></fieldset><?php endif; ?>
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
