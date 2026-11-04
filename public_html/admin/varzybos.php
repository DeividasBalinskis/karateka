<?php
// Varžybos (Valdymas → Varžybos): svetainės varžybų sistema. Sąrašas, naujos varžybos, test varžybos (sukurti / ištrinti),
// varžybų duomenys, rezultatai → narių taškai. Pats valdymas (klubai, dalyviai, kategorijos, lentelės...) - admin/varzybu-sistema.php.
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();
$ready = tournaments_ready();

if (is_post() && $ready) {
    csrf_check();
    $id = (int) post('id');
    $t = $id ? tournament_get($id) : null;
    switch (post('action')) {
        case 'create':
            $title = trim((string) post('title'));
            $start = (string) post('starts_on');
            if ($title === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
                flash('err', t('Įrašykite pavadinimą ir datą.'));
                redirect('admin/varzybos.php#nauja');
            }
            $end = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) post('ends_on')) && post('ends_on') > $start ? post('ends_on') : null;
            $newId = tournament_create($title, $start, $end, trim((string) post('location')) ?: null, false, (int) $me['id']);
            if (post('event') === '1') {
                q('INSERT INTO events (type, title, starts_on, ends_on, location, description) VALUES ("competition", ?, ?, ?, ?, ?)',
                    [mb_substr($title, 0, 190), $start, $end, trim((string) post('location')) ?: null, t('Lentelės ir rezultatai - varžybų sistemoje.')]);
                q('UPDATE tournaments SET event_id = ? WHERE id = ?', [(int) db()->lastInsertId(), $newId]);
            }
            flash('ok', t('Varžybos sukurtos. Pradėkite nuo dalyvių: „+ Karateka nariai“ arba pridėkite kitų klubų dalyvius.'));
            redirect('admin/varzybu-sistema.php?id=' . $newId . '#dalyviai');
        case 'test':
            $newId = tournament_generate_test((int) $me['id']);
            flash('ok', t('Test varžybos sukurtos: Karateka nariai ir kitų klubų išgalvoti dalyviai, kategorijos sudarytos, dalis kovų jau įvyko.'));
            redirect('admin/varzybu-sistema.php?id=' . $newId . '#lenteles');
        case 'delete_test':
            $n = tournaments_delete_test();
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
                flash('ok', t('Išsaugota.'));
            }
            break;
        case 'points':
            if ($t && !$t['is_test']) {
                [$eventId, $members, $changes] = tournament_results_to_points($t, (int) $me['id']);
                flash('ok', t('Rezultatai perkelti į renginį ir narių taškus:') . ' ' . $members . ' ' . t('nar.') . ($changes ? '' : ' ' . t('(nieko naujo)')));
            }
            break;
    }
    redirect('admin/varzybos.php' . ($id ? '#t' . $id : ''));
}

$list = tournaments_list();
$hasTest = (bool) array_filter($list, function ($t) { return (int) $t['is_test'] === 1; });

page_start(t('Varžybos'), ['admin' => true]);
?>
<div class="page-head varz-head">
  <div>
    <div class="eyebrow"><?= e(VARZ_NAME) ?> · <?= te('varžybų sistema') ?></div>
    <h1 class="styled"><?= te('Varžybos') ?></h1>
    <p class="muted" style="margin:0;"><?= te('Klubai, dalyviai, kategorijos, lentelės, tvarkaraštis ir rezultatai vienoje vietoje. Rezultatai keliauja į narių taškus ir LTKF svetainę.') ?></p>
  </div>
  <?php if ($ready): ?>
    <div class="varz-head-btns">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="test">
        <button class="btn btn-ghost" type="submit"><?= te('🎲 Sukurti test varžybas') ?></button></form>
      <a class="btn btn-primary" href="#nauja" onclick="document.getElementById('nauja').open = true;"><?= te('+ Naujos varžybos') ?></a>
    </div>
  <?php endif; ?>
</div>
<?php if (!$ready): ?>
  <div class="flash flash-err"><?= te('Duomenų bazė neatnaujinta - importuokite') ?> <code>db/025_varzybos_atsiliepimai.sql</code>.</div>
<?php endif; ?>

<?php if ($ready && !$list): ?>
  <div class="panel card varz-empty">
    <div class="varz-empty-mark" aria-hidden="true"></div>
    <h2><?= te('Varžybų dar nėra') ?></h2>
    <p class="muted"><?= te('Norite pamatyti, kaip viskas veikia? Paspauskite „🎲 Sukurti test varžybas“ - bus sukurtos varžybos su Karateka nariais ir kitų klubų dalyviais, kategorijomis ir dalimi įvykusių kovų. Vėliau jas ištrinsite vienu mygtuku.') ?></p>
  </div>
<?php endif; ?>

<div class="varz-list">
<?php foreach ($list as $t): $st = tournament_stats($t); $pct = $st['cats'] ? round($st['finished'] / $st['cats'] * 100) : 0; ?>
  <article class="panel varz-card<?= $t['is_test'] ? ' is-test' : '' ?>" id="t<?= (int) $t['id'] ?>">
    <div class="varz-date" aria-hidden="true">
      <span class="d"><?= (int) date('j', strtotime($t['starts_on'])) ?></span>
      <span class="m"><?= e(mb_substr(month_names()[(int) date('n', strtotime($t['starts_on']))] ?? '', 0, 3)) ?></span>
      <span class="y"><?= e(date('Y', strtotime($t['starts_on']))) ?></span>
    </div>
    <div class="varz-main">
      <div class="varz-tags">
        <span class="varz-status s-<?= e($t['status']) ?>"><?= e(t(TOURNAMENT_STATUS[$t['status']])) ?></span>
        <?php if ($t['is_test']): ?><span class="badge badge-warn">TEST</span><?php endif; ?>
        <?php if (!$t['is_public']): ?><span class="badge"><?= te('neviešos') ?></span><?php endif; ?>
      </div>
      <h2><a href="<?= url('admin/varzybu-sistema.php?id=' . (int) $t['id']) ?>"><?= e($t['title']) ?></a></h2>
      <p class="small muted" style="margin:0;"><?= e(fmt_date($t['starts_on'], true)) ?><?= $t['ends_on'] ? ' – ' . e(fmt_date($t['ends_on'], true)) : '' ?><?= $t['location'] ? ' · ' . e($t['location']) : '' ?></p>
      <ul class="varz-stats">
        <li><strong><?= $st['people'] ?></strong> <?= te('dalyviai') ?></li>
        <li><strong><?= $st['home'] ?></strong> <?= te('iš Karateka') ?></li>
        <li><strong><?= $st['clubs'] ?></strong> <?= te('klubai') ?></li>
        <li><strong><?= $st['cats'] ?></strong> <?= te('kategorijos') ?></li>
      </ul>
      <?php if ($st['cats']): ?>
        <div class="varz-prog" title="<?= e(t('Baigtos kategorijos')) ?>"><i style="width:<?= $pct ?>%"></i><span><?= te('Baigta') ?> <?= $st['finished'] ?> / <?= $st['cats'] ?></span></div>
      <?php endif; ?>
    </div>
    <div class="varz-actions">
      <a class="btn btn-primary btn-sm" href="<?= url('admin/varzybu-sistema.php?id=' . (int) $t['id']) ?>"><?= te('Atidaryti') ?></a>
      <a class="btn btn-ghost btn-sm" href="<?= url('varzybu-rezultatai.php?id=' . (int) $t['id']) ?>" target="_blank" rel="noopener"><?= te('Rezultatai ↗') ?></a>
      <details class="varz-more">
        <summary class="btn btn-ghost btn-sm"><?= te('Daugiau') ?></summary>
        <div class="varz-more-body panel">
          <form method="post" class="varz-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <label><?= te('Pavadinimas') ?> <input type="text" name="title" value="<?= e($t['title']) ?>" maxlength="190" required></label>
            <div class="grid-2">
              <label><?= te('Data') ?> <input type="date" name="starts_on" value="<?= e($t['starts_on']) ?>" required></label>
              <label><?= te('Iki') ?> <span class="hint"><?= te('nebūtina') ?></span><input type="date" name="ends_on" value="<?= e((string) $t['ends_on']) ?>"></label>
            </div>
            <label><?= te('Vieta') ?> <input type="text" name="location" value="<?= e((string) $t['location']) ?>" maxlength="190"></label>
            <div class="grid-2">
              <label><?= te('Būsena') ?> <select name="status"><?php foreach (TOURNAMENT_STATUS as $k => $l): ?><option value="<?= $k ?>"<?= $k === $t['status'] ? ' selected' : '' ?>><?= e(t($l)) ?></option><?php endforeach; ?></select></label>
              <label class="check"><input type="checkbox" name="is_public" value="1"<?= $t['is_public'] ? ' checked' : '' ?>> <?= te('Rezultatai viešai (LTKF, karateka.lt)') ?></label>
            </div>
            <button class="btn btn-primary btn-sm" type="submit"><?= te('Išsaugoti') ?></button>
          </form>
          <div class="varz-more-row">
            <?php if (!$t['is_test']): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="points"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit"><?= te('🏅 Rezultatai → narių taškai') ?></button></form>
              <?php if ($t['event_id']): ?><a class="small" href="<?= url('admin/renginio-rezultatai.php?id=' . (int) $t['event_id']) ?>"><?= te('Renginys karateka.lt →') ?></a><?php endif; ?>
            <?php else: ?>
              <span class="small muted"><?= te('Test varžybų rezultatai į narių taškus neperkeliami.') ?></span>
            <?php endif; ?>
            <?php if ($t['is_test'] || is_admin()): ?>
              <form method="post" onsubmit="return confirm('<?= e(t('Ištrinti varžybas su visais dalyviais, lentelėmis ir rezultatais?')) ?>')"><?= csrf_field() ?>
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                <button class="btn btn-ghost btn-sm btn-danger-text" type="submit"><?= te('Ištrinti') ?></button></form>
            <?php endif; ?>
          </div>
        </div>
      </details>
    </div>
  </article>
<?php endforeach; ?>
</div>

<?php if ($hasTest): ?>
  <form method="post" class="varz-test-del" onsubmit="return confirm('<?= e(t('Ištrinti visas test varžybas? Tikros varžybos liks.')) ?>')">
    <?= csrf_field() ?><input type="hidden" name="action" value="delete_test">
    <button class="btn btn-ghost btn-sm" type="submit"><?= te('🗑 Ištrinti visas test varžybas') ?></button>
    <span class="small muted"><?= te('Tikros varžybos ir nariai nepaliečiami.') ?></span>
  </form>
<?php endif; ?>

<?php if ($ready): ?>
<details class="panel card varz-new" id="nauja">
  <summary><strong><?= te('+ Naujos varžybos') ?></strong></summary>
  <form method="post" class="varz-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <label><?= te('Pavadinimas') ?> <input type="text" name="title" maxlength="190" required placeholder="<?= e(t('pvz. Karateka taurė 2027')) ?>"></label>
    <div class="grid-2">
      <label><?= te('Data') ?> <input type="date" name="starts_on" required></label>
      <label><?= te('Iki') ?> <span class="hint"><?= te('nebūtina') ?></span><input type="date" name="ends_on"></label>
    </div>
    <label><?= te('Vieta') ?> <input type="text" name="location" maxlength="190" placeholder="<?= e(t('pvz. Vilnius, Sporto centras Viršuliškės')) ?>"></label>
    <label class="check"><input type="checkbox" name="event" value="1" checked> <?= te('Rodyti nariams Renginiuose (sukurti renginį)') ?></label>
    <button class="btn btn-primary" type="submit"><?= te('Sukurti ir pridėti dalyvius →') ?></button>
  </form>
</details>
<?php endif; ?>

<?php if (varzybos_enabled()): ?>
  <p class="small"><a href="<?= url('admin/varzybos-isorine.php') ?>"><?= te('Išorinė varžybų sistema (registracija per karateka.lt) →') ?></a></p>
<?php endif; ?>
<?php
page_end();
