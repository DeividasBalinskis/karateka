<?php
// Atsiliepimai (tik adminas): ką parašė nariai, treneriai ir lankytojai per mygtuką „💡 Atsiliepimas“.
// Būsena (naujas → bus pataisyta → pataisyta / nedarysime) ir atsakymas - autorius mato savo atsiliepimų sąraše ir gauna pranešimą.
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_admin();

$filter = isset(FEEDBACK_STATUS[get('b')]) || get('b') === 'visi' ? get('b') : 'atviri';
// v1.87: kur paliktas - grupė (Budo / Karateka / LTKF) ir jos skiltis; paieška tekste
// vieta: ?p=grupė|skiltis (išskleidžiamas sąrašas) arba ?g=…&s=… (nuorodos iš lango ir kortelių)
[$pg, $ps] = array_pad(explode('|', (string) get('p'), 2), 2, '');
$fg = isset(FEEDBACK_GROUPS[get('g')]) ? (string) get('g') : (isset(FEEDBACK_GROUPS[$pg]) ? $pg : '');
$fs = $fg !== '' ? mb_substr((string) (get('g') !== '' ? get('s') : $ps), 0, 80) : '';
$fq = trim(mb_substr((string) get('q'), 0, 80));
$qs = function (array $over = []) use ($filter, $fg, $fs, $fq): string {
    $p = array_filter(array_merge(['b' => $filter, 'g' => $fg, 's' => $fs, 'q' => $fq], $over), function ($v) { return $v !== '' && $v !== null; });
    return 'admin/atsiliepimai.php' . ($p ? '?' . http_build_query($p) : '');
};

if (is_post()) {
    csrf_check();
    $id = (int) post('id');
    $f = $id ? q_one('SELECT * FROM feedback WHERE id = ?', [$id]) : null;
    switch (post('action')) {
        case 'save':
            if ($f) {
                $status = isset(FEEDBACK_STATUS[post('status')]) ? post('status') : $f['status'];
                $reply = trim(str_replace("\r\n", "\n", (string) post('reply')));
                q('UPDATE feedback SET status = ?, reply = ?, updated_at = NOW() WHERE id = ?', [$status, $reply !== '' ? $reply : null, $id]);
                if ($f['account_id'] && (int) $f['account_id'] !== (int) $me['id'] && ($status !== $f['status'] || $reply !== (string) $f['reply'])) {
                    notify_account((int) $f['account_id'], 'feedback', ($status === 'question' ? '❓ ' : '') . t('Jūsų atsiliepimas:') . ' ' . mb_strtolower(t(FEEDBACK_STATUS[$status][0])),
                        $reply !== '' ? $reply : mb_strimwidth($f['body'], 0, 200, '…'), (ltrim((string) $f['page'], '/') ?: 'index.php') . ($status === 'question' ? '#atsiliepimas' : ''), null, $id, false);
                }
                flash('ok', t('Išsaugota.'));
            }
            break;
        case 'delete':
            if ($f) {
                q('DELETE FROM feedback WHERE id = ?', [$id]);
                flash('ok', t('Atsiliepimas ištrintas.'));
            }
            break;
        case 'api_key':   // API prieiga: naujas raktas (rodomas vieną kartą; saugomas tik hash)
            $key = bin2hex(random_bytes(20));
            set_site_setting('feedback_api_key', password_hash($key, PASSWORD_DEFAULT));
            $_SESSION['fb_api_key_once'] = $key;
            flash('ok', t('Raktas sukurtas - nukopijuokite jį dabar, vėliau jo nebematysite.'));
            redirect('admin/atsiliepimai.php#api');
        case 'api_off':
            set_site_setting('feedback_api_key', '');
            flash('ok', t('API prieiga išjungta.'));
            redirect('admin/atsiliepimai.php#api');
        case 'mode':
            if (isset(FEEDBACK_SHOW[post('mode')])) {
                set_site_setting('feedback_button', post('mode'));
                flash('ok', t('Išsaugota.'));
            }
            break;
    }
    redirect($qs() . ($id && post('action') === 'save' ? '#f' . $id : ''));
}

$ready = feedback_ready();
// Visi atsiliepimai vienu kartu (klubui - šimtai, ne tūkstančiai): vieta apskaičiuojama čia, filtrai ir skaičiai - iš to paties sąrašo
$every = $ready ? q_all("SELECT f.*, a.first_name, a.last_name, a.email, a.role FROM feedback f LEFT JOIN accounts a ON a.id = f.account_id
                         ORDER BY f.status = 'new' DESC, f.created_at DESC LIMIT 2000") : [];
foreach ($every as $i => $f) {
    $every[$i]['area'] = feedback_row_area($f);
    if (!feedback_area_visible($every[$i]['area'], $me)) {
        unset($every[$i]);   // testuotojai Dojo atsiliepimų nemato
    }
}
$stOk = function (array $f, string $b): bool {
    return $b === 'visi' || ($b === 'atviri' ? in_array($f['status'], ['new', 'planned', 'question'], true) : $f['status'] === $b);
};
$qOk = function (array $f) use ($fq): bool {
    if ($fq === '') {
        return true;
    }
    // visi žodžiai, bet kuria tvarka; be lietuviškų raidžių skirtumo („zinute“ randa „žinutė“)
    $fold = function (string $x): string { return strtr(mb_strtolower($x), ['ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i', 'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z']); };
    $hay = $fold($f['body'] . ' ' . $f['reply'] . ' ' . $f['first_name'] . ' ' . $f['last_name'] . ' ' . $f['name'] . ' ' . $f['page'] . ' ' . $f['area']['gl'] . ' ' . $f['area']['s'] . ' ' . $f['area']['sec'] . ' #' . $f['id']);
    foreach (preg_split('/\s+/u', $fold($fq)) as $w) {
        if ($w !== '' && mb_strpos($hay, $w) === false) {
            return false;
        }
    }
    return true;
};
$inPlace = function (array $f) use ($fg, $fs): bool {
    return $fg === '' || ($f['area']['g'] === $fg && ($fs === '' || $f['area']['s'] === $fs));
};
$counts = [];
$gCounts = [];
$sCounts = [];
foreach ($every as $f) {
    if (!$qOk($f)) {
        continue;
    }
    if ($inPlace($f)) {
        $counts[$f['status']] = ($counts[$f['status']] ?? 0) + 1;
    }
    if ($stOk($f, $filter)) {
        $gCounts[$f['area']['g']] = ($gCounts[$f['area']['g']] ?? 0) + 1;
        $sCounts[$f['area']['g']][$f['area']['s']] = ($sCounts[$f['area']['g']][$f['area']['s']] ?? 0) + 1;
    }
}
foreach ($sCounts as &$sc) {
    arsort($sc);
}
unset($sc);
$rows = array_slice(array_values(array_filter($every, function ($f) use ($stOk, $qOk, $inPlace, $filter) { return $stOk($f, $filter) && $qOk($f) && $inPlace($f); })), 0, 300);
$open = array_values(array_filter($every, function ($f) use ($stOk, $inPlace) { return $stOk($f, 'atviri') && $inPlace($f); }));
usort($open, function ($a, $b) { return strcmp($a['created_at'], $b['created_at']); });
feedback_images_prefetch(array_merge(array_column($rows, 'id'), array_column($open, 'id')));

/** Kas parašė */
function fb_author(array $f): string
{
    if ($f['account_id'] && $f['first_name'] !== null) {
        return $f['first_name'] . ' ' . $f['last_name'];
    }
    return $f['name'] ? $f['name'] . ' (' . t('lankytojas') . ')' : t('Lankytojas');
}

page_start(t('Atsiliepimai'), ['admin' => true]);
?>
<div class="page-head row between" style="flex-wrap:wrap; gap:10px; align-items:flex-end;">
  <div>
    <h1 class="styled"><?= te('Atsiliepimai') ?></h1>
    <p class="muted" style="margin:0;"><?= te('Ką pastebėjo nariai, treneriai ir lankytojai (mygtukas „💡 Atsiliepimas“ apačioje kairėje). Pakeitus būseną ar atsakius, autorius gauna pranešimą.') ?></p>
  </div>
  <?php if ($open): ?>
    <button type="button" class="btn btn-ghost" id="fbCopy"><?= te('📋 Kopijuoti neišspręstus') ?> (<?= count($open) ?>)</button>
  <?php endif; ?>
</div>
<?php if (!$ready): ?>
  <div class="flash flash-err"><?= te('Duomenų bazė neatnaujinta - importuokite') ?> <code>db/025_varzybos_atsiliepimai.sql</code>.</div>
<?php endif; ?>

<?php
$tabs = ['atviri' => [t('Neišspręsti'), (int) ($counts['new'] ?? 0) + (int) ($counts['planned'] ?? 0) + (int) ($counts['question'] ?? 0)]];
foreach (FEEDBACK_STATUS as $k => $s) {
    $tabs[$k] = [t($s[0]), (int) ($counts[$k] ?? 0)];
}
$tabs['visi'] = [t('Visi'), array_sum(array_map('intval', $counts))];
?>
<?php /* v1.87: filtrai - išskleidžiami sąrašai (būsena, vieta su skiltimis) ir paieška vienoje eilutėje; pakeitus - iškart rodoma */ ?>
<form method="get" class="fb-admin-filters" role="search">
  <select name="b" aria-label="<?= e(t('Būsena')) ?>" onchange="this.form.submit()">
    <?php foreach ($tabs as $key => [$label, $n]): ?><option value="<?= e($key) ?>"<?= $filter === $key ? ' selected' : '' ?>><?= e($label) ?> (<?= (int) $n ?>)</option><?php endforeach; ?>
  </select>
  <select name="p" aria-label="<?= e(t('Kur paliktas')) ?>" onchange="this.form.submit()">
    <option value=""><?= te('Visur') ?> (<?= array_sum($gCounts) ?>)</option>
    <?php foreach (FEEDBACK_GROUPS as $gk => $gl): if (empty($gCounts[$gk]) && $fg !== $gk) { continue; } ?>
      <optgroup label="<?= e($gl) ?>">
        <option value="<?= e($gk) ?>|"<?= $fg === $gk && $fs === '' ? ' selected' : '' ?>><?= e(t('Visa {g}', ['g' => $gl])) ?> (<?= (int) ($gCounts[$gk] ?? 0) ?>)</option>
        <?php foreach ((array) ($sCounts[$gk] ?? []) as $sk => $n): ?><option value="<?= e($gk . '|' . $sk) ?>"<?= $fg === $gk && $fs === (string) $sk ? ' selected' : '' ?>>&nbsp;&nbsp;<?= e(t((string) $sk)) ?> (<?= (int) $n ?>)</option><?php endforeach; ?>
        <?php if ($fg === $gk && $fs !== '' && !isset($sCounts[$gk][$fs])): ?><option value="<?= e($gk . '|' . $fs) ?>" selected>&nbsp;&nbsp;<?= e(t($fs)) ?> (0)</option><?php endif; ?>
      </optgroup>
    <?php endforeach; ?>
  </select>
  <input type="search" name="q" value="<?= e($fq) ?>" placeholder="<?= e(t('🔎 Ieškoti')) ?>" aria-label="<?= e(t('Ieškoti atsiliepimų')) ?>">
  <button class="btn btn-ghost btn-sm" type="submit"><?= te('Rodyti') ?></button>
</form>

<?php if (!$rows): ?>
  <div class="panel card"><p class="muted" style="margin:0;"><?= $filter === 'atviri' && $fg === '' && $fq === '' ? te('Neišspręstų atsiliepimų nėra. 🎉') : te('Atsiliepimų nėra.') ?>
    <?php if ($fg !== '' || $fq !== ''): ?> <a href="<?= url($qs(['g' => '', 's' => '', 'q' => ''])) ?>"><?= te('Rodyti visus') ?> →</a><?php endif; ?></p></div>
<?php endif; ?>

<div class="fb-admin-list">
<?php foreach ($rows as $f): ?>
  <form method="post" class="panel card fb-card<?= $f['status'] === 'new' ? ' is-new' : '' ?>" id="f<?= (int) $f['id'] ?>" action="<?= url($qs()) ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
    <div class="fb-card-top">
      <div>
        <strong><?= e(fb_author($f)) ?></strong>
        <?php if ($f['role'] && $f['role'] !== 'member'): ?><span class="badge badge-tag"><?= e(t($f['role'] === 'admin' ? 'adminas' : 'treneris')) ?></span><?php endif; ?>
        <span class="muted small"> · <?= e(fmt_date(substr($f['created_at'], 0, 10))) ?> <?= e(substr($f['created_at'], 11, 5)) ?></span>
        <a class="badge fb-place" href="<?= url($qs(['g' => $f['area']['g'], 's' => $f['area']['s']])) ?>" title="<?= e(t('Rodyti visus iš šios vietos')) ?>"><?= e($f['area']['gl'] . ' › ' . ($f['area']['sec'] !== '' && mb_strpos($f['area']['sec'], $f['area']['sl']) === 0 ? $f['area']['sec'] : $f['area']['sl'])) ?></a>
        <span class="muted small">#<?= (int) $f['id'] ?></span>
        <?php if ($href = feedback_page_href($f)): ?><span class="small"> · <a href="<?= e($href) ?>" target="_blank" rel="noopener"><?= e(mb_strimwidth($f['page'], 0, 60, '…')) ?> ↗</a></span><?php endif; ?>
      </div>
      <span><?= feedback_status_badge($f['status']) ?>
        <?php if (!empty($f['verdict'])): ?><span class="badge <?= $f['verdict'] === 'ok' ? 'badge-ok' : 'badge-warn' ?>"><?= e(['ok' => t('👍 Patinka'), 'revert' => t('↩ Prašo grąžinti'), 'reverted' => t('↩ Grąžinta')][$f['verdict']] ?? $f['verdict']) ?></span><?php endif; ?></span>
    </div>
    <div class="fb-card-body"><?= $f['body'] !== '' ? nl2br(e($f['body'])) : '<span class="muted">' . te('(tik nuotraukos)') . '</span>' ?></div>
    <?php if (!empty($f['answer'])): ?><div class="fb-card-answer"><b><?= te('Autoriaus atsakymas į klausimą:') ?></b> <?= nl2br(e((string) $f['answer'])) ?></div><?php endif; ?>
    <?php $imgs = feedback_images((int) $f['id']); if ($imgs): ?>
      <div class="fb-card-imgs"><?php foreach ($imgs as $im): ?><a href="<?= e(feedback_image_url($im)) ?>" target="_blank" rel="noopener"><img src="<?= e(feedback_image_url($im, true)) ?>" alt="" loading="lazy"></a><?php endforeach; ?></div>
    <?php endif; ?>
    <div class="fb-card-edit">
      <label class="fb-status"><span class="small muted"><?= te('Būsena') ?></span>
        <select name="status"><?php foreach (FEEDBACK_STATUS as $k => $s): ?><option value="<?= $k ?>"<?= $k === $f['status'] ? ' selected' : '' ?>><?= e(t($s[0])) ?></option><?php endforeach; ?></select>
      </label>
      <label class="fb-reply-field"><span class="small muted"><?= $f['account_id'] ? te('Atsakymas (mato autorius)') : te('Pastaba sau (lankytojas atsakymo nemato)') ?></span>
        <textarea name="reply" rows="2" maxlength="2000"><?= e((string) $f['reply']) ?></textarea>
      </label>
      <div class="fb-card-btns">
        <button class="btn btn-primary btn-sm" type="submit" name="action" value="save"><?= te('Išsaugoti') ?></button>
        <button class="btn btn-ghost btn-sm" type="submit" name="action" value="delete" onclick="return confirm('<?= e(t('Ištrinti šį atsiliepimą?')) ?>')"><?= te('Ištrinti') ?></button>
      </div>
    </div>
  </form>
<?php endforeach; ?>
</div>

<div class="panel card fb-api" id="api">
  <div class="kicker"><?= te('API prieiga') ?></div>
  <p class="small" style="margin:4px 0 10px;"><?= te('Raktas automatiniam atsiliepimų skaitymui (su nuotraukomis) ir įkėlimui į test svetainę (atsiliepimai-api.php, diegimas-api.php). Sukurkite jį ir nukopijuokite ten, kur jo prašoma.') ?></p>
  <?php if (!empty($_SESSION['fb_api_key_once'])): $once = (string) $_SESSION['fb_api_key_once']; unset($_SESSION['fb_api_key_once']); ?>
    <div class="row" style="gap:8px; flex-wrap:wrap; margin-bottom:8px;"><input type="text" readonly value="<?= e($once) ?>" id="fbKey" onclick="this.select()" style="flex:1; min-width:260px; font-family:monospace;">
      <button class="btn btn-primary btn-sm" type="button" onclick="var i=document.getElementById('fbKey'); i.select(); try { navigator.clipboard.writeText(i.value); } catch (e) {} this.textContent='✓ Nukopijuota';"><?= te('Kopijuoti') ?></button></div>
  <?php endif; ?>
  <form method="post" class="row" style="gap:8px; flex-wrap:wrap;"><?= csrf_field() ?>
    <button class="btn btn-ghost btn-sm" type="submit" name="action" value="api_key" onclick="return <?= site_setting('feedback_api_key', '') ? "confirm('" . e(t('Sukurti naują raktą? Senas nustos veikti.')) . "')" : 'true' ?>"><?= site_setting('feedback_api_key', '') ? te('Sukurti naują raktą') : te('Sukurti raktą') ?></button>
    <?php if (site_setting('feedback_api_key', '')): ?><span class="badge badge-ok"><?= te('įjungta') ?></span>
      <button class="btn btn-ghost btn-sm" type="submit" name="action" value="api_off"><?= te('Išjungti') ?></button><?php endif; ?>
  </form>
</div>

<form method="post" class="panel card fb-mode">
  <?= csrf_field() ?><input type="hidden" name="action" value="mode">
  <div class="kicker"><?= te('Kam rodomas mygtukas „Atsiliepimas“') ?></div>
  <div class="row" style="gap:8px; flex-wrap:wrap; align-items:center;">
    <select name="mode"><?php foreach (FEEDBACK_SHOW as $k => $label): ?><option value="<?= $k ?>"<?= $k === feedback_mode() ? ' selected' : '' ?>><?= e(t($label)) ?></option><?php endforeach; ?></select>
    <button class="btn btn-ghost btn-sm" type="submit"><?= te('Išsaugoti') ?></button>
  </div>
  <p class="small muted" style="margin:8px 0 0;"><?= te('Test svetainėje numatyta - visiems, oficialioje - tik prisijungusiems.') ?></p>
</form>

<?php if ($open): ?>
<textarea id="fbCopyText" hidden><?= e("Atsiliepimai iš karateka.lt (" . count($open) . "):\n\n" . implode("\n\n", array_map(function ($f) {
    $n = count(feedback_images((int) $f['id']));
    return '#' . $f['id'] . ' · ' . substr($f['created_at'], 0, 16) . ' · ' . fb_author($f) . ' · ' . $f['area']['gl'] . ' › ' . $f['area']['s'] . ($f['page'] ? ' · ' . $f['page'] : '') . ($n ? ' · nuotraukų: ' . $n : '') . ($f['status'] === 'planned' ? ' · [bus pataisyta]' : '') . "\n" . $f['body'] . (!empty($f['answer']) ? "\n[atsakymas] " . $f['answer'] : '');
}, $open))) ?></textarea>
<script>
  document.getElementById('fbCopy').addEventListener('click', function () {
    var t = document.getElementById('fbCopyText'), b = this;
    (navigator.clipboard ? navigator.clipboard.writeText(t.value) : Promise.reject()).then(function () { b.textContent = '✓ <?= e(t('Nukopijuota')) ?>'; })
      .catch(function () { t.hidden = false; t.select(); });
  });
</script>
<?php endif; ?>
<?php
page_end();
