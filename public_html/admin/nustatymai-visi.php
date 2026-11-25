<?php
// C2: Visi nustatymai - vienas žemėlapis: kiekvienas jungiklis su dabartine reikšme ir nuoroda, kur keisti; serverio nustatymai (tik skaityti);
// pakeitimų žurnalas su „Atkurti“ (visai svetainei skirtiems). Tik adminui.
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_can('settings');

if (is_post() && post('action') === 'restore') {
    csrf_check();
    $row = settings_log_ready() ? q_one('SELECT * FROM settings_log WHERE id = ?', [(int) post('id')]) : null;
    $def = $row ? setting_def((string) $row['name']) : null;
    if ($row && $def && !empty($def['restore'])) {
        if ($row['old_value'] === null) {
            $old = site_setting((string) $row['name']);
            q('DELETE FROM site_settings WHERE name = ?', [$row['name']]);
            settings_log_write((string) $row['name'], $old, '');
        } else {
            set_site_setting((string) $row['name'], (string) $row['old_value']);
        }
        flash('ok', t('Atkurta:') . ' ' . t($def['label']));
    }
    redirect('admin/nustatymai-visi.php#zurnalas');
}

$values = [];
foreach (q_all('SELECT name, value FROM site_settings ORDER BY name') as $r) {
    $values[$r['name']] = $r['value'];
}
$groups = [];
foreach (SETTINGS as $name => $d) {
    if (!empty($d['prefix'])) {
        $n = count(array_filter(array_keys($values), function ($k) use ($name) { return strpos($k, $name) === 0; }));
        $groups[$d['group']][] = $d + ['name' => $name, 'show' => $n ? t('{n} varžyboms', ['n' => $n]) : t('- (numatyta)')];
        continue;
    }
    $groups[$d['group']][] = $d + ['name' => $name, 'show' => setting_show($name, $values[$name] ?? null)];
}
$unknown = array_filter(array_keys($values), function ($k) { return setting_def($k) === null; });
$log = settings_log_ready() ? q_all('SELECT * FROM settings_log ORDER BY id DESC LIMIT 50') : [];
page_start(t('Visi nustatymai'), ['admin' => true]);
?>
<div class="page-head">
  <h1 class="styled"><?= te('Visi nustatymai') ?></h1>
  <p><?= te('Kiekvienas jungiklis su dabartine reikšme ir vieta, kur jį keisti. Apačioje - kas ir kada keitė.') ?></p>
</div>
<?php foreach ($groups as $g => $list): ?>
<div class="panel card">
  <div class="kicker"><?= te($g) ?></div>
  <ul class="set-map">
    <?php foreach ($list as $d): ?>
      <li><span class="set-l"><?= te($d['label']) ?> <code class="small muted"><?= e($d['name']) ?><?= !empty($d['prefix']) ? '…' : '' ?></code></span>
        <span class="set-v"><?= e($d['show']) ?></span>
        <span class="set-w"><?= $d['where'] !== '' ? '<a href="' . e(url($d['where'])) . '">' . te('Keisti →') . '</a>' : '<span class="muted small">' . te('automatiškai') . '</span>' ?></span></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endforeach; ?>
<div class="panel card">
  <div class="kicker"><?= te('Serveryje (config.php) - keičia Deividas') ?></div>
  <ul class="set-map">
    <?php foreach (settings_server() as [$l, $v]): ?><li><span class="set-l"><?= e($l) ?></span><span class="set-v"><?= e($v) ?></span><span class="set-w"></span></li><?php endforeach; ?>
  </ul>
  <?php if ($unknown): ?><p class="small warn-t"><?= te('Nežinomi nustatymai (ne registre):') ?> <?= e(implode(', ', $unknown)) ?></p><?php endif; ?>
</div>
<div class="panel card" id="zurnalas">
  <div class="kicker"><?= te('Pakeitimų žurnalas') ?> <span class="hint"><?= te('paskutiniai 50, saugoma 1 metus') ?></span></div>
  <?php if (!$log): ?><p class="muted small"><?= te('Pakeitimų dar nėra.') ?></p><?php endif; ?>
  <ul class="set-log">
    <?php foreach ($log as $r): $def = setting_def((string) $r['name']); ?>
      <li><span><b><?= e($def ? t($def['label']) : $r['name']) ?></b>
          <span class="small muted"><?= e(notification_when($r['changed_at'])) ?><?= $r['changed_by_name'] ? ' · ' . e($r['changed_by_name']) : '' ?></span><br>
          <span class="small"><?= ($def['type'] ?? '') === 'secret' ? te('pakeista (slapta reikšmė nerodoma)') : e(setting_show((string) $r['name'], $r['old_value'])) . ' → ' . e(setting_show((string) $r['name'], $r['new_value'])) ?></span></span>
        <?php if (!empty($def['restore'])): ?><form method="post" class="inline" onsubmit="return confirm(this.dataset.q)" data-q="<?= te('Grąžinti ankstesnę reikšmę visai svetainei?') ?>"><?= csrf_field() ?>
          <input type="hidden" name="action" value="restore"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-ghost btn-xs"><?= te('Atkurti') ?></button></form><?php endif; ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php page_end();
