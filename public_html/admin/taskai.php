<?php
// XP: skirti XP keliems nariams iš karto, už ką XP skiriama (automatiškai ir treneriui skiriant), reitingas ir paskutiniai skyrimai.
// XP ir taškai - tas pats: iš sezono XP skaičiuojamas reitingas, iš visų kada nors surinktų - nario lygis.
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

if (is_post()) {
    csrf_check();
    $action = post('action');

    // Skirti XP pažymėtiems (grupės ir atskiri nariai); kategoriją galima sukurti čia pat
    if ($action === 'give') {
        $picked = member_tree_from_post();
        $ids = $picked['members'];
        if ($picked['groups']) {
            $ids = array_merge($ids, array_map('intval', q('SELECT id FROM members WHERE status = "active" AND group_id IN (' . implode(',', $picked['groups']) . ')')->fetchAll(PDO::FETCH_COLUMN)));
        }
        $points = (int) post('points');
        $date = post('awarded_on') ?: date('Y-m-d');
        $cat = null;
        if (post('category_id') === 'new' && is_admin() && post('new_name') !== '') {
            q('INSERT INTO point_categories (name, points, sort_order) VALUES (?, ?, 100)', [mb_substr(post('new_name'), 0, 120), max(1, $points)]);
            $cat = q_one('SELECT * FROM point_categories WHERE id = ?', [(int) db()->lastInsertId()]);
        } elseif (post('category_id') !== 'new') {
            $cat = q_one('SELECT * FROM point_categories WHERE id = ? AND is_active = 1', [(int) post('category_id')]);
        }
        if (!$cat) {
            flash('err', t('Pasirinkite, už ką skiriate XP (arba įrašykite naujos kategorijos pavadinimą).'));
        } elseif ($points < 1 || $points > 1000) {
            flash('err', t('XP turi būti nuo 1 iki 1000.'));
        } elseif (!DateTime::createFromFormat('!Y-m-d', $date)) {
            flash('err', t('Netinkama data.'));
        } elseif (!$ids) {
            flash('err', t('Pažymėkite narius ar grupes, kuriems skiriate XP.'));
        } else {
            $n = award_xp_to_members($ids, $cat, $points, $date, post('note') ?: null, (int) $me['id']);
            flash('ok', t('Skirta +{xp} XP ({cat}): {n} nar.', ['xp' => $points, 'cat' => $cat['name'], 'n' => $n]));
        }
        redirect('admin/taskai.php#skirti');
    }

    if ($action === 'save_categories' && is_admin()) {
        foreach ((array) ($_POST['name'] ?? []) as $id => $name) {
            $name = is_string($name) ? trim($name) : '';
            if ($name === '') {
                continue;
            }
            q('UPDATE point_categories SET name = ?, points = ?, is_active = ? WHERE id = ?',
                [$name, (int) ($_POST['points'][$id] ?? 0), !empty($_POST['active'][$id]) ? 1 : 0, (int) $id]);
        }
        if (post('xp_per_level') !== '') {
            set_site_setting('xp_per_level', (string) max(10, min(10000, (int) post('xp_per_level'))));
        }
        flash('ok', t('XP vertės išsaugotos. Jos galioja naujiems skyrimams.'));
    }

    if ($action === 'add_category' && is_admin() && post('new_name') !== '') {
        q('INSERT INTO point_categories (name, points, sort_order) VALUES (?, ?, 100)', [post('new_name'), (int) post('new_points')]);
        flash('ok', t('Kategorija pridėta.'));
    }

    if ($action === 'delete_award') {
        q('DELETE FROM point_awards WHERE id = ?', [(int) post('award_id')]);
        flash('ok', t('XP pašalinti.'));
    }
    redirect('admin/taskai.php');
}

[$from, $to, $seasonLabel] = season_bounds();
$categories = q_all('SELECT * FROM point_categories ORDER BY sort_order, id');
$autoCats = array_values(array_filter($categories, function ($c) { return isset(XP_AUTO_RULES[(string) $c['code']]); }));
$handCats = array_values(array_filter($categories, function ($c) { return !isset(XP_AUTO_RULES[(string) $c['code']]); }));
$giveCats = manual_xp_categories();

// Iš Treniruotės: pažymėta grupė (?g=) arba renginio dalyviai (?e=), data (?d=)
$selGroups = get('g') ? [(int) get('g')] : [];
$selMembers = get('e') ? array_map('intval', q('SELECT member_id FROM event_participants WHERE event_id = ? AND (status IN ("going", "late") OR attended = 1)', [(int) get('e')])->fetchAll(PDO::FETCH_COLUMN)) : [];
$giveDate = DateTime::createFromFormat('!Y-m-d', (string) get('d')) ? get('d') : date('Y-m-d');

// Lankomumo XP būna labai daug - jų čia nerodome
$recent = q_all('SELECT pa.*, pc.name AS category_name, m.first_name, m.last_name, e.title AS event_title
                   FROM point_awards pa
                   JOIN point_categories pc ON pc.id = pa.category_id
                   JOIN members m ON m.id = pa.member_id
                   LEFT JOIN events e ON e.id = pa.event_id
                  WHERE pc.code IS NULL OR pc.code <> "attendance"
                  ORDER BY pa.created_at DESC, pa.id DESC LIMIT 30');

page_start(t('XP'), ['admin' => true]);
?>
<div class="page-head">
  <h1 class="styled"><?= te('XP ir reitingas') ?></h1>
  <p><?= e($seasonLabel) ?> (<?= e(fmt_date($from, true)) ?> – <?= e(fmt_date($to, true)) ?>).
    <?= te('XP ir taškai - tas pats: iš sezono XP skaičiuojamas reitingas, iš visų kada nors surinktų - nario lygis.') ?></p>
</div>

<?php if ($giveCats): ?>
<form method="post" class="panel card form xp-give" id="skirti">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="give">
  <h2><?= te('✨ Skirti XP') ?></h2>
  <p class="hint"><?= te('Už tai, kas nesiskaičiuoja automatiškai: pagalba treneriui, treniruotės vedimas, teisėjavimas... Pažymėkite kelis vaikus ar visą grupę - XP gaus kiekvienas, o paskyroje matys, už ką.') ?></p>
  <div class="form-row xp-give-row">
    <label><?= te('Už ką') ?>
      <select name="category_id" id="giveCat">
        <?php foreach ($giveCats as $c): ?><option value="<?= (int) $c['id'] ?>" data-points="<?= (int) $c['points'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
        <?php if (is_admin()): ?><option value="new" data-points="5"><?= te('+ Nauja kategorija…') ?></option><?php endif; ?>
      </select></label>
    <label class="xp-new" hidden><?= te('Naujos kategorijos pavadinimas') ?> <input type="text" name="new_name" maxlength="120" placeholder="<?= te('pvz. Pagalba renginyje') ?>"></label>
    <label><?= te('XP kiekvienam') ?> <input type="number" name="points" id="givePts" min="1" max="1000" value="<?= (int) $giveCats[0]['points'] ?>" required></label>
    <label><?= te('Data') ?> <input type="date" name="awarded_on" value="<?= e($giveDate) ?>"></label>
  </div>
  <label><?= te('Komentaras') ?> <span class="hint"><?= te('nebūtina; mato narys ir tėvai') ?></span><input type="text" name="note" maxlength="190" placeholder="<?= te('pvz. Padėjo vesti apšilimą') ?>"></label>
  <div class="kicker" style="margin-top:6px;"><?= te('Kam') ?></div>
  <?= member_tree_field($selGroups, $selMembers, ['hint' => t('Pažymėkite visą grupę arba išskleiskite ▸ ir pasirinkite tik kelis.')]) ?>
  <div><button class="btn btn-primary" type="submit"><?= te('Skirti XP') ?></button></div>
</form>
<script>
(function () {
  var sel = document.getElementById('giveCat'), pts = document.getElementById('givePts'), nw = document.querySelector('.xp-new');
  sel.addEventListener('change', function () {
    pts.value = sel.selectedOptions[0].dataset.points || 5;
    nw.hidden = sel.value !== 'new';
    nw.querySelector('input').required = sel.value === 'new';
  });
})();
</script>
<?php endif; ?>

<div class="grid-2">
  <div class="stack">
    <?= render_staff_ranking() ?>
    <div class="panel card">
      <h2><?= te('Paskutiniai skyrimai') ?></h2>
      <?php if (!$recent): ?><p class="muted small"><?= te('XP dar neskirta.') ?></p><?php endif; ?>
      <ul class="list small">
        <?php foreach ($recent as $r): ?>
          <li class="row between">
            <span>
              <strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong> +<?= (int) $r['points'] ?> XP
              <div class="muted"><?= e($r['category_name']) ?><?= $r['event_title'] ? ' · ' . e($r['event_title']) : '' ?><?= $r['note'] ? ' · ' . e($r['note']) : '' ?> · <?= e(fmt_date($r['awarded_on'])) ?></div>
            </span>
            <form method="post" class="inline-form" onsubmit="return confirm('Pašalinti šiuos XP?')">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete_award"><input type="hidden" name="award_id" value="<?= (int) $r['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit" aria-label="<?= te('Pašalinti') ?>">✕</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <div class="stack">
    <form method="post" class="panel card form" id="kategorijos">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_categories">
      <h2><?= te('Už ką skiriama XP') ?></h2>
      <p class="hint"><?= te('Pakeista vertė galioja tik naujiems skyrimams - anksčiau skirti XP nesikeičia.') ?><?= is_admin() ? '' : (' ' . t('Keisti gali administratorius.')) ?></p>
      <label class="xp-setting"><?= te('Vienam lygiui reikia') ?>
        <input type="number" name="xp_per_level" min="10" max="10000" value="<?= xp_per_level() ?>" <?= is_admin() ? '' : 'disabled' ?>> XP
      </label>
      <?php foreach ([[t('Skiriama automatiškai'), $autoCats, true], [t('Skiria treneris (✨ Skirti XP, „+XP“ treniruotėje, nario kortelėje)'), $handCats, false]] as [$title, $list, $isAuto]): ?>
        <div class="kicker" style="margin-top:10px;"><?= e($title) ?></div>
        <table class="table xp-rules">
          <thead><tr><th><?= te('Kategorija') ?></th><th style="width:96px;">XP</th><th style="width:40px;" title="<?= te('Aktyvi') ?>">✓</th></tr></thead>
          <tbody>
            <?php foreach ($list as $c): ?>
              <tr>
                <td><input type="text" name="name[<?= (int) $c['id'] ?>]" value="<?= e($c['name']) ?>" <?= is_admin() ? '' : 'disabled' ?>>
                  <?php if ($isAuto): ?><div class="hint xp-when"><?= e(t(XP_AUTO_RULES[$c['code']])) ?></div><?php endif; ?></td>
                <?php if ($c['code'] === 'skill'): ?>
                  <td><input type="hidden" name="points[<?= (int) $c['id'] ?>]" value="0"><a class="small" href="<?= url('admin/programa.php') ?>" title="<?= te('Kiekvienas programos punktas turi savo XP') ?>"><?= te('prie punkto') ?></a></td>
                <?php else: ?>
                  <td><input type="number" name="points[<?= (int) $c['id'] ?>]" value="<?= (int) $c['points'] ?>" <?= is_admin() ? '' : 'disabled' ?>></td>
                <?php endif; ?>
                <td><input type="checkbox" name="active[<?= (int) $c['id'] ?>]" value="1" <?= $c['is_active'] ? 'checked' : '' ?> <?= is_admin() ? '' : 'disabled' ?>></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endforeach; ?>
      <?php if (is_admin()): ?><div><button class="btn btn-primary" type="submit"><?= te('Išsaugoti vertes') ?></button></div><?php endif; ?>
    </form>

    <?php if (is_admin()): ?>
      <form method="post" class="panel card form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_category">
        <h2><?= te('Nauja kategorija') ?></h2>
        <div class="form-row" style="grid-template-columns:2fr 1fr;">
          <label><?= te('Pavadinimas') ?> <input type="text" name="new_name" required placeholder="<?= te('pvz. Pagalba renginyje') ?>"></label>
          <label>XP <input type="number" name="new_points" value="5" required></label>
        </div>
        <div><button class="btn btn-ghost" type="submit"><?= te('Pridėti') ?></button></div>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php
page_end();
