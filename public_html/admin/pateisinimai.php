<?php
// Pateisinimai (v1.93, adminui): nuoroda prašymams (karateka.lt/p/?kodas), gauti prašymai - peržiūra (užpildytas šablonas),
// „Patvirtinti ir išsiųsti“ (pateisinimas .docx priede nurodytu el. paštu) arba „Atmesti“; šablonas - Word .docx su žymėmis arba tekstas.
// v1.94: „✎ Redaguoti“ - prašymo duomenys ir tekstas; savo galutinis failas (Word arba PDF) siunčiamas vietoj parengto; kam pranešti.
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_can('justifications');

$filter = in_array(get('b'), ['new', 'sent', 'rejected', 'all'], true) ? (string) get('b') : 'new';

if (get('docx') && just_ready()) {   // užpildytas pateisinimas (.docx) - patikrinti prieš siunčiant
    $r = q_one('SELECT * FROM justifications WHERE id = ?', [(int) get('docx')]);
    $data = $r ? just_docx($r) : null;
    if ($data === null) {
        not_found();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . just_filename($r) . '"');
    exit($data);
}
if (get('failas') && just_ready()) {   // įkeltas galutinis failas (Word arba PDF)
    $r = q_one('SELECT * FROM justifications WHERE id = ?', [(int) get('failas')]);
    $p = $r ? just_file_path($r) : null;
    if ($p === null) {
        not_found();
    }
    header('Content-Type: ' . just_file_type($p));
    header('Content-Disposition: attachment; filename="' . preg_replace('/\.docx$/', '.' . pathinfo($p, PATHINFO_EXTENSION), just_filename($r)) . '"');
    exit((string) file_get_contents($p));
}
if (get('pavyzdys')) {   // pavyzdinis Word šablonas su žymėmis (iš dabartinio teksto) - pasidabinti Word'e ir įkelti atgal
    $data = just_simple_docx(just_text());
    if ($data === null) {
        not_found();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="pateisinimo-sablonas.docx"');
    exit($data);
}

if (is_post() && just_ready()) {
    csrf_check();
    $id = (int) post('id');
    $r = $id ? q_one('SELECT * FROM justifications WHERE id = ?', [$id]) : null;
    switch (post('action')) {
        case 'link':
            just_token(true);
            flash('ok', t('Nuoroda sukurta. Sena (jei buvo) nebeveikia.'));
            break;
        case 'open':
            set_site_setting('just_open', post('open') === '1' ? '1' : '0');
            flash('ok', post('open') === '1' ? t('Prašymai priimami.') : t('Prašymai nepriimami.'));
            break;
        case 'send':
            if ($r) {
                just_send($r, $me) ? flash('ok', t('Pateisinimas išsiųstas: {e}', ['e' => $r['email']])) : flash('err', t('Nepavyko išsiųsti - patikrinkite el. pašto nustatymus.'));
            }
            break;
        case 'reject':
            if ($r && $r['status'] === 'new') {
                q('UPDATE justifications SET status = "rejected" WHERE id = ?', [$id]);
                q('UPDATE notifications SET done_at = NOW() WHERE type = "justification" AND ref_id = ? AND done_at IS NULL', [$id]);
                flash('ok', t('Prašymas atmestas (laiškas nesiųstas).'));
            }
            break;
        case 'edit':   // v1.94: prašymo duomenys ir tekstas
            if ($r) {
                $d = [];
                foreach (['student', 'school', 'director', 'class_name', 'date_from', 'date_to', 'email'] as $f) {
                    $d[$f] = trim(preg_replace('/\s+/u', ' ', (string) post($f)));
                }
                $isDate = function ($x) { return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $x) && strtotime($x) !== false; };
                if (in_array('', [$d['student'], $d['school'], $d['director'], $d['class_name']], true)) {
                    flash('err', t('Užpildykite visus laukus.'));
                } elseif (!$isDate($d['date_from']) || !$isDate($d['date_to']) || $d['date_to'] < $d['date_from']) {
                    flash('err', t('Nurodykite laikotarpį: nuo ir iki (iki - ne anksčiau nei nuo).'));
                } elseif (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
                    flash('err', t('Neteisingas el. pašto adresas.'));
                } else {
                    just_update($r, $d, post('text') !== null ? (string) post('text') : null);
                    flash('ok', t('Pataisyta.'));
                }
            }
            break;
        case 'text_reset':
            if ($r && just_v94()) {
                q('UPDATE justifications SET custom_text = NULL WHERE id = ?', [$id]);
                flash('ok', t('Tekstas vėl pagal šabloną.'));
            }
            break;
        case 'file':   // v1.94: savo galutinis failas (Word arba PDF)
            if ($r) {
                $err = just_file_save($r, $_FILES['file'] ?? []);
                $err !== '' ? flash('err', $err) : flash('ok', t('Failas įkeltas - bus išsiųstas jis.'));
            }
            break;
        case 'file_delete':
            if ($r) {
                just_file_delete($r);
                flash('ok', t('Failas išimtas - bus išsiųstas parengtas pateisinimas.'));
            }
            break;
        case 'notify':   // kam pranešti apie naujus prašymus
            set_site_setting('just_notify', json_encode(array_values(array_map('intval', (array) ($_POST['who'] ?? [])))));
            flash('ok', t('Išsaugota.'));
            redirect('admin/pateisinimai.php?b=' . $filter . '#pranesimai');
        case 'text':
            $txt = trim(str_replace("\r\n", "\n", (string) post('text')));
            set_site_setting('just_text', $txt === just_default_text() ? '' : mb_substr($txt, 0, 5000));
            flash('ok', t('Tekstas išsaugotas.'));
            redirect('admin/pateisinimai.php?b=' . $filter . '#sablonas');
        case 'template':
            $err = just_template_save($_FILES['file'] ?? []);
            $err !== '' ? flash('err', $err) : flash('ok', t('Word šablonas įkeltas - pateisinimai bus pildomi pagal jį.'));
            redirect('admin/pateisinimai.php?b=' . $filter . '#sablonas');
        case 'template_delete':
            just_template_delete();
            flash('ok', t('Word šablonas išimtas - naudojamas tekstas.'));
            redirect('admin/pateisinimai.php?b=' . $filter . '#sablonas');
    }
    redirect('admin/pateisinimai.php?b=' . $filter . ($id ? '#p' . $id : ''));
}

$ready = just_ready();
$counts = $ready ? q('SELECT status, COUNT(*) FROM justifications GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR) : [];
$rows = $ready ? q_all('SELECT j.*, a.first_name AS by_first, a.last_name AS by_last FROM justifications j LEFT JOIN accounts a ON a.id = j.sent_by'
    . ($filter !== 'all' ? ' WHERE j.status = ' . db()->quote($filter) : '') . ' ORDER BY j.created_at DESC LIMIT 200') : [];
$url = $ready ? just_url() : '';
$tpl = just_template();

page_start(t('Pateisinimai'), ['admin' => true]);
?>
<div class="page-head">
  <h1 class="styled"><?= te('Pateisinimai') ?></h1>
  <p class="muted" style="margin:0;"><?= te('Prašymai pateisinti praleistas pamokas. Peržiūrėkite ir patvirtinkite - pateisinimas išsiunčiamas prašyme nurodytu el. paštu.') ?></p>
</div>
<?php if (!$ready): ?>
  <div class="flash flash-err"><?= te('Duomenų bazė neatnaujinta - importuokite') ?> <code>db/056_pateisinimai.sql</code>, <code>db/057_pateisinimu_failai.sql</code>.</div>
<?php else: ?>

<section class="panel card">
  <div class="kicker"><?= te('Nuoroda prašymams') ?></div>
  <div class="just-link-row" style="margin-top:6px;">
    <?php /* C3: Kopijuoti · ↻ Nauja nuoroda · Išjungti (app/links.php) */ ?>
    <?= $ready ? link_box('just', null, ['open' => true, 'back' => '/admin/pateisinimai.php']) : '' ?>
    <?php if ($url): ?>
      <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="open"><input type="hidden" name="open" value="<?= just_is_open() ? '0' : '1' ?>">
        <button class="btn btn-ghost btn-sm" type="submit"><?= just_is_open() ? te('Nebepriimti prašymų') : te('Priimti prašymus') ?></button></form>
    <?php endif; ?>
  </div>
  <?php if ($url && !just_is_open()): ?><p class="small muted" style="margin:6px 0 0;"><?= te('Prašymai nepriimami - nuoroda rodo „Prašymai nepriimami“.') ?></p><?php endif; ?>
</section>

<form method="get" class="fb-admin-filters">
  <select name="b" aria-label="<?= e(t('Būsena')) ?>" onchange="this.form.submit()">
    <?php foreach (['new' => t('Nauji'), 'sent' => t('Išsiųsti'), 'rejected' => t('Atmesti'), 'all' => t('Visi')] as $k => $l): $n = $k === 'all' ? array_sum(array_map('intval', $counts)) : (int) ($counts[$k] ?? 0); ?>
      <option value="<?= $k ?>"<?= $filter === $k ? ' selected' : '' ?>><?= e($l) ?> (<?= $n ?>)</option>
    <?php endforeach; ?>
  </select>
</form>

<?php if (!$rows): ?><div class="panel card"><p class="muted" style="margin:0;"><?= $filter === 'new' ? te('Naujų prašymų nėra.') : te('Prašymų nėra.') ?></p></div><?php endif; ?>
<?php foreach ($rows as $r): ?>
  <article class="panel card just-card<?= $r['status'] === 'new' ? ' is-new' : '' ?>" id="p<?= (int) $r['id'] ?>">
    <div class="row between" style="gap:8px; flex-wrap:wrap; align-items:center;">
      <h2 style="margin:0; font-size:1.15rem;"><?= e($r['student']) ?> <span class="muted small">· <?= e($r['class_name']) ?> <?= te('klasė') ?></span></h2>
      <span class="badge <?= ['new' => 'badge-warn', 'sent' => 'badge-ok', 'rejected' => ''][$r['status']] ?>"><?= e(['new' => t('Laukia patvirtinimo'), 'sent' => t('Išsiųsta'), 'rejected' => t('Atmesta')][$r['status']]) ?></span>
    </div>
    <div class="just-meta">
      <div><b><?= te('Mokykla') ?></b><?= e($r['school']) ?></div>
      <div><b><?= te('Direktorius (-ė)') ?></b><?= e($r['director']) ?></div>
      <div><b><?= te('Laikotarpis') ?></b><?= e(just_period($r['date_from'], $r['date_to'])) ?></div>
      <div><b><?= te('Siųsti') ?></b><?= e($r['email']) ?></div>
      <div><b><?= te('Gauta') ?></b><?= e(fmt_date(substr($r['created_at'], 0, 10), true) . ' ' . substr($r['created_at'], 11, 5)) ?></div>
      <?php if ($r['status'] === 'sent'): ?><div><b><?= te('Išsiuntė') ?></b><?= e(trim($r['by_first'] . ' ' . $r['by_last'])) ?>, <?= e(fmt_date(substr((string) $r['sent_at'], 0, 10), true)) ?></div><?php endif; ?>
    </div>
    <?php $file = just_file_path($r); $custom = just_custom($r); $rid = (int) $r['id']; ?>
    <?php if ($file): ?>
      <div class="just-file">📎 <?= te('Bus išsiųstas įkeltas failas:') ?> <a href="<?= url('admin/pateisinimai.php?failas=' . $rid) ?>"><b><?= e($r['file_name']) ?></b></a>
        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $rid ?>"><input type="hidden" name="action" value="file_delete">
          <button class="btn btn-ghost btn-sm" type="submit"><?= te('Išimti') ?></button></form></div>
    <?php endif; ?>
    <details class="just-prev"<?= $r['status'] === 'new' && !$file ? ' open' : '' ?>>
      <summary><b><?= $file ? te('👁 Parengtas pateisinimas (nesiunčiamas - siunčiamas įkeltas failas)') : te('👁 Parengtas pateisinimas') ?></b><?php if ($custom !== null): ?> <span class="badge badge-warn"><?= te('tekstas pataisytas') ?></span><?php endif; ?></summary>
      <div class="just-preview"><?= e(just_preview($r)) ?></div>
    </details>
    <div class="just-acts">
      <a class="btn btn-ghost btn-sm" href="<?= url('admin/pateisinimai.php?docx=' . $rid) ?>"><?= te('⬇ Word (.docx)') ?></a>
      <button type="button" class="btn btn-ghost btn-sm" data-toggle="e<?= $rid ?>" aria-expanded="false"><?= te('✎ Redaguoti') ?></button>
      <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $rid ?>"><input type="hidden" name="action" value="send">
        <button class="btn btn-<?= $r['status'] === 'sent' ? 'ghost' : 'primary' ?> btn-sm" type="submit" onclick="return confirm('<?= e(t('Išsiųsti pateisinimą adresu {e}?', ['e' => $r['email']])) ?>')"><?= $r['status'] === 'sent' ? te('↻ Siųsti dar kartą') : te('✓ Patvirtinti ir išsiųsti') ?></button></form>
      <?php if ($r['status'] === 'new'): ?>
        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="reject">
          <button class="btn btn-ghost btn-sm" type="submit" onclick="return confirm('<?= e(t('Atmesti prašymą? Laiškas nebus siunčiamas.')) ?>')"><?= te('✕ Atmesti') ?></button></form>
      <?php endif; ?>
    </div>
    <div class="just-edit" id="e<?= $rid ?>" hidden>
      <form method="post" class="form">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $rid ?>"><input type="hidden" name="action" value="edit">
        <div class="just-edit-grid">
          <label><?= te('Mokinio vardas ir pavardė') ?><input name="student" value="<?= e($r['student']) ?>" maxlength="160" required></label>
          <label><?= te('Klasė') ?><input name="class_name" value="<?= e($r['class_name']) ?>" maxlength="30" required></label>
          <label><?= te('Mokykla') ?><input name="school" value="<?= e($r['school']) ?>" maxlength="190" required></label>
          <label><?= te('Direktorius (-ė)') ?><input name="director" value="<?= e($r['director']) ?>" maxlength="160" required></label>
          <label><?= te('Nuo') ?><input type="date" name="date_from" value="<?= e($r['date_from']) ?>" required></label>
          <label><?= te('Iki') ?><input type="date" name="date_to" value="<?= e($r['date_to']) ?>" required></label>
          <label class="span2"><?= te('El. paštas (kur siųsti)') ?><input type="email" name="email" value="<?= e($r['email']) ?>" maxlength="190" required></label>
        </div>
        <?php if (!$tpl || $custom !== null): ?>
          <label><?= te('Pateisinimo tekstas (tik šiam prašymui)') ?><textarea name="text" rows="10" maxlength="8000"><?= e($custom ?? just_auto_text($r)) ?></textarea></label>
        <?php else: ?>
          <p class="small muted" style="margin:0;"><?= te('Tekstą keiskite Word\'e: atsisiųskite „⬇ Word“, pataisykite ir įkelkite žemiau (Word arba PDF).') ?></p>
        <?php endif; ?>
        <div class="row" style="gap:8px; flex-wrap:wrap;">
          <button class="btn btn-primary btn-sm" type="submit"><?= te('Išsaugoti') ?></button>
          <?php if ($custom !== null): ?><button class="btn btn-ghost btn-sm" type="submit" name="action" value="text_reset"><?= te('↺ Tekstas pagal šabloną') ?></button><?php endif; ?>
        </div>
      </form>
      <form method="post" enctype="multipart/form-data" class="form just-upload">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $rid ?>"><input type="hidden" name="action" value="file">
        <label><?= te('Savo galutinis failas (Word arba PDF) - bus išsiųstas vietoj parengto') ?><input type="file" name="file" accept=".docx,.pdf" required></label>
        <button class="btn btn-ghost btn-sm" type="submit"><?= te('📎 Įkelti failą') ?></button>
      </form>
    </div>
  </article>
<?php endforeach; ?>

<?php $whoIds = array_map(function ($a) { return (int) $a['id']; }, just_recipients()); ?>
<details class="panel card" id="pranesimai">
  <summary><b><?= te('🔔 Kam pranešti apie naujus prašymus') ?></b> <span class="small muted"><?= e(implode(', ', array_map(function ($a) { return trim($a['first_name'] . ' ' . $a['last_name']); }, just_recipients())) ?: t('niekam')) ?></span></summary>
  <form method="post" class="form" style="margin-top:8px;">
    <?= csrf_field() ?><input type="hidden" name="action" value="notify">
    <div class="just-who">
      <?php foreach (active_admins() as $a): ?>
        <label class="check"><input type="checkbox" name="who[]" value="<?= (int) $a['id'] ?>"<?= in_array((int) $a['id'], $whoIds, true) ? ' checked' : '' ?>> <?= e(trim($a['first_name'] . ' ' . $a['last_name'])) ?></label>
      <?php endforeach; ?>
    </div>
    <p class="small muted" style="margin:0;"><?= te('Pažymėtiems - laiškas ir pranešimas „Reikia padaryti“.') ?></p>
    <button class="btn btn-ghost btn-sm" type="submit"><?= te('Išsaugoti') ?></button>
  </form>
</details>

<details class="panel card" id="sablonas"<?= $tpl ? '' : ' open' ?>>
  <summary><b><?= te('📄 Pateisinimo šablonas') ?></b> <span class="small muted"><?= $tpl ? te('Word šablonas: {n} ({d})', ['n' => $tpl['name'], 'd' => fmt_date($tpl['at'], true)]) : te('dabar - tekstas (įkelkite savo Word šabloną)') ?></span></summary>
  <p class="small"><?= te('Žymės, kurios pakeičiamos prašymo duomenimis:') ?> <code>{vardas}</code> <code>{mokykla}</code> <code>{direktorius}</code> <code>{klase}</code> <code>{laikotarpis}</code> <code>{nuo}</code> <code>{iki}</code> <code>{data}</code> <code>{el_pastas}</code></p>
  <form method="post" enctype="multipart/form-data" class="form" style="margin-bottom:12px;">
    <?= csrf_field() ?><input type="hidden" name="action" value="template">
    <label><?= te('Word šablonas (.docx) - su logotipu, parašu, antspaudu; žymės rašomos tekste') ?><input type="file" name="file" accept=".docx" required></label>
    <div class="row" style="gap:8px; flex-wrap:wrap;">
      <button class="btn btn-primary btn-sm" type="submit"><?= $tpl ? te('Pakeisti Word šabloną') : te('Įkelti Word šabloną') ?></button>
      <a class="btn btn-ghost btn-sm" href="<?= url('admin/pateisinimai.php?pavyzdys=1') ?>"><?= te('⬇ Pavyzdinis šablonas (.docx)') ?></a>
    </div>
  </form>
  <?php if ($tpl): ?>
    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="template_delete">
      <button class="btn btn-ghost btn-sm" type="submit" onclick="return confirm('<?= e(t('Išimti Word šabloną? Bus naudojamas tekstas.')) ?>')"><?= te('Išimti Word šabloną') ?></button></form>
  <?php else: ?>
    <form method="post" class="form">
      <?= csrf_field() ?><input type="hidden" name="action" value="text">
      <label><?= te('Tekstas (kol neįkeltas Word šablonas)') ?><textarea name="text" rows="12" maxlength="5000"><?= e(just_text()) ?></textarea></label>
      <button class="btn btn-ghost btn-sm" type="submit"><?= te('Išsaugoti tekstą') ?></button>
    </form>
  <?php endif; ?>
</details>
<?php endif; ?>
<script>
document.querySelectorAll('[data-toggle]').forEach(function (b) {   // ✎ Redaguoti
  b.addEventListener('click', function () {
    var p = document.getElementById(b.dataset.toggle); if (!p) return;
    p.hidden = !p.hidden; b.setAttribute('aria-expanded', p.hidden ? 'false' : 'true');
    if (!p.hidden) { var i = p.querySelector('input:not([type=hidden])'); if (i) i.focus(); }
  });
});
document.querySelectorAll('[data-copy]').forEach(function (b) {
  b.addEventListener('click', function () {
    var o = b.textContent, done = function () { b.textContent = '✓ ' + <?= json_encode(t('Nukopijuota')) ?>; setTimeout(function () { b.textContent = o; }, 1500); };
    if (navigator.clipboard) navigator.clipboard.writeText(b.dataset.copy).then(done, function () {});
  });
});
</script>
<?php
page_end();
