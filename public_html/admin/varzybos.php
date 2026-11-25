<?php
// Taikos pradžia (Valdymas → Varžybos): visos varžybos vienoje vietoje - vykstančios ir artimiausios viršuje, praėjusios žemiau,
// naujos varžybos, test varžybos, varžybų duomenys, rezultatai → narių taškai; apačioje - partneriai (kaip karateka.lt).
// Pačių varžybų valdymas (klubai, komandos, dalyviai, kategorijos, live tvarkaraštis, rezultatai) - admin/varzybu-sistema.php.
require dirname(__DIR__) . '/app/bootstrap.php';
$me = varz_require_access();
// Sekretoriato nuoroda duoda tik vienas varžybas: šis puslapis (visų varžybų sąrašas ir trenerio veiksmai) jai neskirtas
if (!empty($me['sekr'])) {
    redirect('admin/varzybu-sistema.php?id=' . (int) $me['sekr']);
}
$demo = varz_is_demo($me);   // bandymo nuoroda: tik bandymo varžybos, be tikrų narių
$ready = tournaments_ready();

// Bandymo nuoroda draugams: sukurti / pakeisti / išjungti (tik adminui)
if (is_post() && in_array(post('action'), ['demo_on', 'demo_off'], true) && is_admin()) {
    csrf_check();
    post('action') === 'demo_on' ? link_create('demo') : link_off_kind('demo');   // C3: app/links.php (seni mygtukai)
    flash('ok', post('action') === 'demo_on' ? t('Bandymo nuoroda sukurta - nukopijuokite ir nusiųskite.') : t('Bandymo nuoroda išjungta - su ja nebeprisijungs.'));
    redirect('admin/varzybos.php#bandymas');
}

if (is_post() && $ready) {
    csrf_check();
    $id = (int) post('id');
    $t = $id ? tournament_get($id) : null;
    if ($demo && $t && empty($t['is_demo'])) {
        $t = null;   // bandantysis keičia tik bandymo varžybas
    }
    switch (post('action')) {
        case 'create':
            $title = trim((string) post('title'));
            $start = (string) post('starts_on');
            if ($title === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
                flash('err', t('Įrašykite pavadinimą ir datą.'));
                redirect('admin/varzybos.php#nauja');
            }
            $end = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) post('ends_on')) && post('ends_on') > $start ? post('ends_on') : null;
            // Dalyviai iškart kuriant: pažymėti klubo nariai ir nauji žmonės (po vieną eilutėje); nebūtina
            $state = tournament_default_state();
            $people = varz_people_from_form($demo ? [] : array_map('intval', (array) ($_POST['members'] ?? [])), (string) post('names'), trim((string) post('names_club')));
            if ($people) {
                $state['people'] = $people;
                if (!in_array(VARZ_HOME_CLUB, (array) $state['clubs'], true)) {
                    array_unshift($state['clubs'], VARZ_HOME_CLUB);
                }
                foreach ($people as $pp) {
                    if (!in_array($pp['club'], (array) $state['clubs'], true)) {
                        $state['clubs'][] = $pp['club'];
                    }
                }
                // Po vieną grupę kiekvienai pažymėtai rungčiai su visais dalyviais (sudaro naršyklė atidarius, kaip test varžyboms)
                $quick = array_values(array_intersect((array) ($_POST['quick'] ?? []), ['kata', 'kumite']));
                if ($quick) {
                    $state['quickCats'] = $quick;
                    $state['areas'] = [['id' => 1, 'name' => '1 TATAMIS', 'start' => date('H:i')]];   // mažoms varžyboms - vienas tatamis
                }
            }
            $newId = tournament_create($title, $start, $end, trim((string) post('location')) ?: null, false, (int) $me['id'], $state, $demo);
            if (post('event') === '1' && !$demo) {
                q('INSERT INTO events (type, title, starts_on, ends_on, location, description) VALUES ("competition", ?, ?, ?, ?, ?)',
                    [mb_substr($title, 0, 190), $start, $end, trim((string) post('location')) ?: null, t('Lentelės ir rezultatai - varžybų sistemoje.')]);
                q('UPDATE tournaments SET event_id = ? WHERE id = ?', [(int) db()->lastInsertId(), $newId]);
            }
            if (!empty($state['quickCats'])) {
                flash('ok', t('Varžybos sukurtos: {n} dalyv., grupės sudarytos. Galite pradėti.', ['n' => count($people)]));
                redirect('admin/varzybu-sistema.php?id=' . $newId . '#live');
            }
            flash('ok', $people ? t('Varžybos sukurtos: {n} dalyv. Toliau - kategorijos.', ['n' => count($people)])
                : t('Varžybos sukurtos. Pradėkite nuo dalyvių: „+ Karateka nariai“ arba pridėkite kitų klubų dalyvius.'));
            redirect('admin/varzybu-sistema.php?id=' . $newId . '#dalyviai');
        case 'test':
            $newId = tournament_generate_test((int) $me['id'], $demo);
            flash('ok', $demo ? t('Bandymo varžybos sukurtos: išgalvoti dalyviai, kategorijos sudarytos, dalis kovų jau įvyko.')
                : t('Test varžybos sukurtos: Karateka nariai ir kitų klubų išgalvoti dalyviai, kategorijos sudarytos, dalis kovų jau įvyko.'));
            redirect('admin/varzybu-sistema.php?id=' . $newId . '#live');
        case 'delete_test':
            $n = $demo ? (int) q('DELETE FROM tournaments WHERE is_demo = 1')->rowCount() : tournaments_delete_test();
            flash('ok', t('Ištrinta test varžybų:') . ' ' . $n . '.');
            break;
        case 'delete':
            if ($t && ($t['is_test'] || is_admin())) {
                tournament_delete($id);
                flash('ok', t('Varžybos „') . $t['title'] . t('“ ištrintos.'));
            }
            break;
        case 'save':
            if ($t) {
                $start = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) post('starts_on')) ? post('starts_on') : $t['starts_on'];
                $end = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) post('ends_on')) && post('ends_on') > $start ? post('ends_on') : null;
                q('UPDATE tournaments SET title = ?, starts_on = ?, ends_on = ?, location = ?, status = ?, is_public = ? WHERE id = ?',
                    [mb_substr(trim((string) post('title')) ?: $t['title'], 0, 190), $start, $end, trim((string) post('location')) ?: null,
                     isset(TOURNAMENT_STATUS[post('status')]) ? post('status') : $t['status'], post('is_public') === '1' ? 1 : 0, $id]);
                if (is_admin()) {   // LTKF kalendoriuje - tik federacijos patvirtintos (db/046)
                    try {
                        q('UPDATE tournaments SET ltkf_approved = ? WHERE id = ?', [post('ltkf_approved') === '1' ? 1 : 0, $id]);
                    } catch (PDOException $ex) {
                        // dar neimportuotas db/046
                    }
                }
                flash('ok', t('Išsaugota.'));
            }
            break;
        case 'points':
            if ($t && !$t['is_test'] && !$demo) {
                [$eventId, $members, $changes] = tournament_results_to_points($t, (int) $me['id']);
                flash('ok', t('Rezultatai perkelti į renginį ir narių taškus:') . ' ' . $members . ' ' . t('nar.') . ($changes ? '' : ' ' . t('(nieko naujo)')));
            }
            break;
    }
    redirect('admin/varzybos.php' . ($id ? '#t' . $id : ''));
}

$list = tournaments_list(true, $demo);
$hasTest = (bool) array_filter($list, function ($t) { return (int) $t['is_test'] === 1; });
$today = date('Y-m-d');
$soon = array_values(array_filter($list, function ($t) use ($today) { return $t['status'] === 'running' || ($t['ends_on'] ?: $t['starts_on']) >= $today; }));
$past = array_values(array_filter($list, function ($t) use ($soon) { return !in_array($t, $soon, true); }));
usort($soon, function ($a, $b) { return [$a['status'] !== 'running', $a['starts_on']] <=> [$b['status'] !== 'running', $b['starts_on']]; });

/** Varžybų kortelė */
$card = function (array $t): string {
    $st = tournament_stats($t);
    $pct = $st['cats'] ? (int) round($st['finished'] / $st['cats'] * 100) : 0;
    $ts = strtotime($t['starts_on']);
    $open = url('admin/varzybu-sistema.php?id=' . (int) $t['id']);
    ob_start(); ?>
  <article class="varz-card s-<?= e($t['status']) ?><?= $t['is_test'] ? ' is-test' : '' ?>" id="t<?= (int) $t['id'] ?>">
    <a class="varz-card-link" href="<?= $open ?>" aria-label="<?= e($t['title']) ?>"></a>
    <div class="varz-card-top">
      <div class="varz-date" aria-hidden="true"><span class="d"><?= (int) date('j', $ts) ?></span><span class="m"><?= e(mb_substr(month_names()[(int) date('n', $ts)] ?? '', 0, 3)) ?></span><span class="y"><?= date('Y', $ts) ?></span></div>
      <div class="varz-tags">
        <span class="varz-status s-<?= e($t['status']) ?>"><?= e(t(TOURNAMENT_STATUS[$t['status']])) ?></span>
        <?php if ($t['is_test']): ?><span class="vz-test">TEST</span><?php endif; ?>
        <?php if (!$t['is_public']): ?><span class="varz-chip"><?= te('neviešos') ?></span><?php endif; ?>
      </div>
    </div>
    <h2><?= e($t['title']) ?></h2>
    <p class="varz-place"><?= e(fmt_date($t['starts_on'], true)) ?><?= $t['ends_on'] ? ' – ' . e(fmt_date($t['ends_on'], true)) : '' ?><?= $t['location'] ? ' · ' . e($t['location']) : '' ?></p>
    <ul class="varz-stats">
      <li><strong><?= $st['people'] ?></strong> <?= te('dalyviai') ?></li>
      <li><strong><?= $st['clubs'] ?></strong> <?= te('klubai') ?></li>
      <li><strong><?= $st['cats'] ?></strong> <?= te('kategorijos') ?></li>
    </ul>
    <?php if ($st['cats']): ?><div class="varz-prog" title="<?= e(t('Baigtos kategorijos')) ?>"><i style="width:<?= $pct ?>%"></i><span><?= te('Baigta') ?> <?= $st['finished'] ?> / <?= $st['cats'] ?></span></div><?php endif; ?>
    <div class="varz-actions">
      <a class="btn btn-primary" href="<?= $open ?>"><?= te('Atidaryti →') ?></a>
      <a class="btn" href="<?= url('varzybu-rezultatai.php?id=' . (int) $t['id']) ?>" target="_blank" rel="noopener"><?= te('Rezultatai ↗') ?></a>
      <details class="varz-more">
        <summary class="btn" title="<?= te('Duomenys, rezultatai → taškai, ištrinti') ?>">⋯</summary>
        <div class="varz-more-body">
          <form method="post" class="varz-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <label><?= te('Pavadinimas') ?> <input class="sel" type="text" name="title" value="<?= e($t['title']) ?>" maxlength="190" required></label>
            <div class="varz-2">
              <label><?= te('Data') ?> <input class="sel" type="date" name="starts_on" value="<?= e($t['starts_on']) ?>" required></label>
              <label><?= te('Iki') ?> <input class="sel" type="date" name="ends_on" value="<?= e((string) $t['ends_on']) ?>"></label>
            </div>
            <label><?= te('Vieta') ?> <input class="sel" type="text" name="location" value="<?= e((string) $t['location']) ?>" maxlength="190"></label>
            <div class="varz-2">
              <label><?= te('Būsena') ?> <select class="sel" name="status"><?php foreach (TOURNAMENT_STATUS as $k => $l): ?><option value="<?= $k ?>"<?= $k === $t['status'] ? ' selected' : '' ?>><?= e(t($l)) ?></option><?php endforeach; ?></select></label>
              <label class="small"><input type="checkbox" name="is_public" value="1"<?= $t['is_public'] ? ' checked' : '' ?>> <?= te('Rezultatai viešai (LTKF, karateka.lt)') ?></label>
              <?php if (is_admin() && array_key_exists('ltkf_approved', $t)): ?><label class="small"><input type="checkbox" name="ltkf_approved" value="1"<?= $t['ltkf_approved'] ? ' checked' : '' ?>> <?= te('Federacijos kalendoriuje (patvirtinta LTKF)') ?></label><?php endif; ?>
            </div>
            <button class="btn btn-primary" type="submit"><?= te('Išsaugoti') ?></button>
          </form>
          <div class="varz-more-row">
            <?php if (!$t['is_test']): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="points"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                <button class="btn" type="submit"><?= te('🏅 Rezultatai → narių taškai') ?></button></form>
              <?php if ($t['event_id']): ?><a class="small" href="<?= url('admin/renginio-rezultatai.php?id=' . (int) $t['event_id']) ?>"><?= te('Renginys karateka.lt →') ?></a><?php endif; ?>
            <?php else: ?>
              <span class="small muted"><?= te('Test varžybų rezultatai į narių taškus neperkeliami.') ?></span>
            <?php endif; ?>
            <?php if ($t['is_test'] || is_admin()): /* bandantysis mato tik bandymo varžybas */ ?>
              <form method="post" onsubmit="return confirm('<?= e(t('Ištrinti varžybas su visais dalyviais, lentelėmis ir rezultatais?')) ?>')"><?= csrf_field() ?>
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                <button class="btn btn-danger" type="submit"><?= te('Ištrinti') ?></button></form>
            <?php endif; ?>
          </div>
        </div>
      </details>
    </div>
  </article>
    <?php return (string) ob_get_clean();
};

varz_html_head(t('Varžybos'), 'vz-home');
?>
<header class="vz-top">
  <div class="vz-top-row">
    <?php if (!$demo): ?><a class="vz-back" href="<?= url('admin/index.php') ?>" title="<?= te('Į Karateka valdymą') ?>">←</a><?php endif; ?>
    <a class="vz-brand" href="<?= url('admin/varzybos.php') ?>"><span class="vz-logo" aria-hidden="true"></span><?= e(VARZ_NAME) ?></a>
    <?php if (!$demo && is_staff()): ?><?= site_switch_html('budo') ?><?php endif; /* #171: į Karateka ir LTKF - kaip Karateka logotipo meniu */ ?>
    <div class="vz-title">
      <h1><?= te('Varžybos') ?></h1>
      <div class="sub"><?= te('Visos varžybos vienoje vietoje: klubai, komandos, dalyviai, kategorijos, live tvarkaraštis ir rezultatai.') ?></div>
    </div>
    <?php if ($ready): ?>
      <div class="vz-top-right">
        <?= render_lang_switch(tl_lang()) ?>
        <?= theme_picker_html('taika', varz_ui()['theme'], 'vz-link') ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="test">
          <button class="vz-link" type="submit"><?= te('🎲 Sukurti test varžybas') ?></button></form>
      </div>
    <?php endif; ?>
  </div>
</header>
<main class="vzh">
  <?php foreach (take_flashes() as $f): ?><div class="flash flash-<?= e($f['type']) ?>"><?= e(t($f['message'])) ?></div><?php endforeach; ?>
  <?php if ($demo): ?>
    <div class="varz-demo-note">
      <b><?= te('👋 Sveiki,') ?> <?= e($me['first_name']) ?>!</b> <?= te('Tai Budo varžybų sistemos bandymas: galite daryti viską, ką daro sekretoriatas ir organizatorius - kurti varžybas, dalyvius, kategorijas, vesti kovas, siųsti apdovanojimams.') ?>
      <?= te('Pradėkite nuo „🎲 Sukurti test varžybas“ - bus išgalvoti dalyviai ir dalis kovų. Pastebėjimus rašykite per') ?> <b><?= te('💡 Atsiliepimas') ?></b> <?= te('(apačioje kairėje) - galima įklijuoti ekrano nuotrauką (Ctrl+V).') ?>
      <form method="post" action="<?= url('varzybos-bandymas.php') ?>" class="inline-form"><?= csrf_field() ?><button class="btn btn-sm" type="submit" name="action" value="end" id="demo-end"><?= te('Baigti darbą') ?></button></form>
    </div>
  <?php endif; ?>
  <?php if (!$ready): ?>
    <div class="flash flash-err"><?= te('Duomenų bazė neatnaujinta - importuokite') ?> <code>db/025_varzybos_atsiliepimai.sql</code>.</div>
  <?php endif; ?>

  <?php if ($ready): ?>
  <details class="varz-new" id="nauja">
    <summary><span class="varz-new-plus">+</span> <?= te('Naujos varžybos') ?></summary>
    <form method="post" class="varz-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="create">
      <label><?= te('Pavadinimas') ?> <input class="sel" type="text" name="title" maxlength="190" required placeholder="<?= e(t('pvz. Karateka taurė 2027')) ?>"></label>
      <div class="varz-2">
        <label><?= te('Data') ?> <input class="sel" type="date" name="starts_on" required value="<?= e(date('Y-m-d')) ?>"></label>
        <label><?= te('Iki') ?> <span class="muted small"><?= te('nebūtina') ?></span><input class="sel" type="date" name="ends_on"></label>
      </div>
      <label><?= te('Vieta') ?> <input class="sel" type="text" name="location" maxlength="190" placeholder="<?= e(t('pvz. Vilnius, Sporto centras Viršuliškės')) ?>"></label>
      <fieldset class="vn-people">
        <legend><?= te('Dalyviai') ?> <span class="muted small"><?= te('nebūtina - galima pridėti ir vėliau') ?></span></legend>
        <?php $clubMembers = $demo ? [] : varz_club_members(); if ($clubMembers): $mGroups = array_values(array_unique(array_filter(array_column($clubMembers, 'group')))); ?>
          <div class="vn-pick">
            <input type="search" class="sel vn-search" placeholder="<?= te('Ieškoti nario…') ?>" aria-label="<?= te('Ieškoti nario') ?>">
            <div class="vn-groups chips-row"><?php foreach ($mGroups as $g): ?><button type="button" class="fchip" data-vn-group="<?= e($g) ?>"><?= e($g) ?></button><?php endforeach; ?></div>
            <div class="vn-list">
              <?php foreach ($clubMembers as $m): ?>
                <label class="vn-item" data-g="<?= e($m['group']) ?>"><input type="checkbox" name="members[]" value="<?= (int) $m['id'] ?>">
                  <span><?= e($m['name']) ?></span><small><?= $m['birth'] ? (int) $m['birth'] : '' ?><?= $m['group'] !== '' ? ' · ' . e($m['group']) : '' ?></small></label>
              <?php endforeach; ?>
            </div>
            <p class="small muted vn-count" aria-live="polite"><?= te('Pažymėta:') ?> <b>0</b></p>
          </div>
        <?php endif; ?>
        <label><?= te('Nauji žmonės') ?> <span class="muted small"><?= te('po vieną eilutėje: Vardas Pavardė') ?></span>
          <textarea class="sel" name="names" rows="3" placeholder="<?= e(t("Jonas Jonaitis\nOna Onaitė")) ?>"></textarea></label>
        <label class="vn-club"><?= te('Naujų žmonių klubas') ?> <input class="sel" type="text" name="names_club" maxlength="80" value="<?= e(VARZ_HOME_CLUB) ?>"></label>
        <div class="vn-quick">
          <span class="small"><b><?= te('Iškart sudaryti po vieną grupę visiems:') ?></b></span>
          <label class="small"><input type="checkbox" name="quick[]" value="kata"> Kata</label>
          <label class="small"><input type="checkbox" name="quick[]" value="kumite"> Kumite</label>
          <span class="small muted"><?= te('mažoms varžyboms (pvz. per treniruotę) - grupę vėliau galima keisti') ?></span>
        </div>
      </fieldset>
      <?php if (!$demo): ?><label class="small"><input type="checkbox" name="event" value="1" checked> <?= te('Rodyti nariams Renginiuose (sukurti renginį)') ?></label><?php endif; ?>
      <button class="btn btn-primary" type="submit"><?= te('Sukurti varžybas →') ?></button>
      <script>
      // B2: greitos varžybos per treniruotę (pažymėta Kata / Kumite) - renginys nariams nekuriamas, nebent pažymėsite patys
      (function (f) {
        var ev = f.querySelector('input[name=event]'); if (!ev) return;
        var touched = false;
        ev.addEventListener('change', function () { touched = true; });
        f.querySelectorAll('input[name="quick[]"]').forEach(function (q) {
          q.addEventListener('change', function () { if (!touched) ev.checked = !f.querySelector('input[name="quick[]"]:checked'); });
        });
      })(document.currentScript.closest('form'));
      </script>
    </form>
  </details>
  <?php endif; ?>

  <?php if ($ready && !$list): ?>
    <div class="varz-empty">
      <div class="varz-empty-mark" aria-hidden="true"></div>
      <h2><?= te('Varžybų dar nėra') ?></h2>
      <p class="muted"><?= te('Norite pamatyti, kaip viskas veikia? Paspauskite „🎲 Sukurti test varžybas“ - bus sukurtos varžybos su Karateka nariais ir kitų klubų dalyviais, kategorijomis ir dalimi įvykusių kovų. Vėliau jas ištrinsite vienu mygtuku.') ?></p>
    </div>
  <?php endif; ?>

  <?php if ($soon): ?>
    <h2 class="vzh-h"><?= te('Vyksta ir artimiausios') ?></h2>
    <div class="varz-list"><?php foreach ($soon as $t) { echo $card($t); } ?></div>
  <?php endif; ?>
  <?php if ($past): ?>
    <h2 class="vzh-h"><?= te('Praėjusios') ?></h2>
    <div class="varz-list is-past"><?php foreach ($past as $t) { echo $card($t); } ?></div>
  <?php endif; ?>

  <?php if ($hasTest): ?>
    <form method="post" class="varz-test-del" onsubmit="return confirm('<?= e(t('Ištrinti visas test varžybas? Tikros varžybos liks.')) ?>')">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete_test">
      <button class="btn" type="submit"><?= te('🗑 Ištrinti visas test varžybas') ?></button>
      <span class="small muted"><?= te('Tikros varžybos ir nariai nepaliečiami.') ?></span>
    </form>
  <?php endif; ?>
  <?php if (varzybos_enabled() && !$demo): ?>
    <a class="varz-share varz-ext" href="<?= url('admin/varzybos-isorine.php') ?>">🔗 <b><?= te('Išorinė varžybų sistema') ?></b> <span class="muted small"><?= te('- registracija per karateka.lt') ?></span> <span class="varz-ext-go">→</span></a>
  <?php endif; ?>
  <?php if (is_admin()): $demoLink = link_current('demo'); ?>
    <details class="varz-share" id="bandymas"<?= $demoLink ? ' open' : '' ?>>
      <summary><b><?= te('👀 Duoti išbandyti (be paskyros)') ?></b> <span class="small muted"><?= te('- draugui ar kito klubo sekretoriatui') ?></span></summary>
      <p class="small muted"><?= te('Su šia nuoroda žmogus įrašo savo vardą ir gali daryti viską, ką daro adminas, bet tik bandymo varžybose: tikrų klubo narių ir jų duomenų nemato, karateka.lt valdymo neatidaro. Atsiliepimus (💡) matysite Valdymas → Atsiliepimai.') ?></p>
      <?= link_box('demo', null, ['row' => $demoLink, 'create' => t('Sukurti bandymo nuorodą'), 'back' => '/admin/varzybos.php#bandymas']) ?>
    </details>
  <?php endif; ?>
</main>
<script>
// Naujos varžybos: narių paieška, visa grupė vienu paspaudimu, pažymėtų skaičius
(function () {
  var box = document.querySelector('.vn-pick'); if (!box) return;
  var items = [].slice.call(box.querySelectorAll('.vn-item')), cnt = box.querySelector('.vn-count b');
  function count() { cnt.textContent = items.filter(function (i) { return i.querySelector('input').checked; }).length; }
  box.querySelector('.vn-search').addEventListener('input', function () {
    var q = this.value.trim().toLowerCase();
    items.forEach(function (i) { i.hidden = q && i.textContent.toLowerCase().indexOf(q) === -1; });
  });
  box.addEventListener('change', count);
  box.addEventListener('click', function (e) {
    var g = e.target.closest('[data-vn-group]'); if (!g) return;
    var mine = items.filter(function (i) { return i.dataset.g === g.dataset.vnGroup; }), on = !mine.every(function (i) { return i.querySelector('input').checked; });
    mine.forEach(function (i) { i.querySelector('input').checked = on; });
    g.setAttribute('aria-pressed', on ? 'true' : 'false'); count();
  });
})();
</script>
<?= varz_partners_html() ?>
<?= feedback_widget_html() ?><script src="<?= e(asset_url('assets/site.js')) ?>" defer></script>
</body>
</html>
