<?php
// Egzamino protokolas (Renginiai → egzaminas → „📋 Protokolas“): egzaminatorius pildo balus ar komentarus (Kihon, Kata, Kumite, Testas),
// pažymi „Išlaikė / Neišlaikė“; keičiama išsaugoma iš karto. Spausdinimas (kaip popierinis protokolas), Excel (CSV) ir siuntimas el. paštu.
// Kiekvienas dalyvis - unikalus numeris MM-mm-dd-eilė; neatvykusį ištrynus, jo numeris daugiau niekam nepriskiriamas.
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

$id = (int) (get('id') ?: post('id'));
$event = q_one('SELECT * FROM events WHERE id = ?', [$id]);
if (!$event || $event['type'] !== 'exam' || !exam_protocol_ready()) {
    not_found();
}
$p = exam_protocol($event);

// Excel (CSV)
if (get('csv')) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . exam_protocol_filename($event) . '"');
    exit(exam_protocol_csv($event));
}

// Vieno laukelio išsaugojimas iš karto (fetch)
if (is_post() && get('save')) {
    csrf_check();
    header('Content-Type: application/json; charset=utf-8');
    $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
    $field = (string) ($in['field'] ?? '');
    $row = q_one('SELECT * FROM exam_protocol_rows WHERE id = ? AND event_id = ?', [(int) ($in['row'] ?? 0), $id]);
    if (!$row || !in_array($field, EXAM_ROW_FIELDS, true)) {
        http_response_code(400);
        exit(json_encode(['ok' => false]));
    }
    $v = trim((string) ($in['value'] ?? ''));
    if ($field === 'result') {
        $v = in_array($v, ['passed', 'failed'], true) ? $v : '';
    } elseif ($field === 'birth_date') {
        $v = preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    } elseif ($field === 'name') {
        $v = mb_substr($v, 0, 160) ?: $row['name'];
    } else {
        $v = $field === 'notes' ? mb_substr($v, 0, 2000) : mb_substr($v, 0, $field === 'seminars' ? 255 : 60);
        $v = $v === '' ? null : $v;
    }
    q("UPDATE exam_protocol_rows SET $field = ? WHERE id = ?", [$v, (int) $row['id']]);
    q('UPDATE exam_protocols SET updated_at = NOW() WHERE event_id = ?', [$id]);
    exit(json_encode(['ok' => true]));
}

if (is_post()) {
    csrf_check();
    switch (post('action')) {
        case 'meta':
            q('UPDATE exam_protocols SET org_name = ?, kyu_label = ?, place_text = ?, examiner_name = ?, examiner_license = ?, updated_at = NOW() WHERE event_id = ?',
                [mb_substr(post('org_name') ?: EXAM_ORG_DEFAULT, 0, 190), mb_substr(post('kyu_label'), 0, 60) ?: null, mb_substr(post('place_text'), 0, 190) ?: null,
                 mb_substr(post('examiner_name'), 0, 120) ?: null, mb_substr(post('examiner_license'), 0, 60) ?: null, $id]);
            // Kitam protokolui - ta pati organizacija ir egzaminatorius
            set_site_setting('exam_protocol_defaults', json_encode(['org' => post('org_name') ?: EXAM_ORG_DEFAULT, 'examiner' => post('examiner_name'), 'license' => post('examiner_license')], JSON_UNESCAPED_UNICODE));
            flash('ok', t('Protokolo antraštė išsaugota.'));
            break;
        case 'add_members':
            $n = exam_protocol_add_participants($event);
            flash('ok', $n ? t('Įtraukta dalyvių: {n}.', ['n' => $n]) : t('Naujų dalyvių nėra - visi užsiregistravę jau protokole.'));
            break;
        case 'add_row':
            if (trim(post('name')) === '') {
                flash('err', t('Įrašykite vardą ir pavardę.'));
                break;
            }
            exam_protocol_add($event, ['name' => trim(post('name')), 'birth_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', post('birth_date')) ? post('birth_date') : null, 'kyu' => post('kyu') ?: null]);
            flash('ok', t('Dalyvis įtrauktas.'));
            break;
        case 'delete_row':
            q('UPDATE exam_protocol_rows SET deleted_at = NOW() WHERE id = ? AND event_id = ?', [(int) post('row'), $id]);
            flash('ok', t('Ištrinta. Šis numeris daugiau niekam nebus priskirtas.'));
            break;
        case 'restore_row':
            q('UPDATE exam_protocol_rows SET deleted_at = NULL WHERE id = ? AND event_id = ?', [(int) post('row'), $id]);
            break;
        case 'to_results':
            if (!is_admin($me)) {
                forbidden();
            }
            $res = event_results($event);
            foreach (exam_protocol_rows($id) as $r) {
                if ($r['member_id']) {
                    $res[(int) $r['member_id']] = $r['result'] === 'passed' ? 'yes' : '';
                }
            }
            $n = save_event_results($event, $res, (int) $me['id']);
            flash('ok', t('Perkelta į renginio rezultatus (XP).') . ($n ? '' : ' ' . t('Pakeitimų nebuvo.')));
            break;
        case 'email':
            $to = trim(post('to'));
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                flash('err', t('Patikrinkite el. pašto adresą.'));
                break;
            }
            $ok = send_mail($to, t('Egzamino protokolas') . ': ' . $event['title'], t('Egzamino protokolas prisegtas (atidaroma su Excel).') . "\n\n" . $event['title'] . "\n" . (string) $p['place_text'],
                [['name' => exam_protocol_filename($event), 'type' => 'text/csv; charset=utf-8', 'data' => exam_protocol_csv($event)]]);
            flash($ok ? 'ok' : 'err', $ok ? t('Protokolas išsiųstas: {to}.', ['to' => $to]) : t('Nepavyko išsiųsti laiško. Atsisiųskite Excel ir išsiųskite patys.'));
            break;
    }
    redirect('admin/egzamino-protokolas.php?id=' . $id);
}

$rows = exam_protocol_rows($id);
$gone = exam_protocol_rows($id, true);
$passed = count(array_filter($rows, function ($r) { return $r['result'] === 'passed'; }));
$failed = count(array_filter($rows, function ($r) { return $r['result'] === 'failed'; }));
page_start(t('Egzamino protokolas') . ': ' . $event['title'], ['admin' => true, 'section' => 'renginiai']);
?>
<div class="no-print">
  <a class="small" href="<?= url('renginiai.php#e' . $id) ?>"><?= te('← Renginiai') ?></a> · <a class="small" href="<?= url('admin/renginio-rezultatai.php?id=' . $id) ?>"><?= te('Rezultatai ir XP') ?></a>
</div>

<div class="prot-sheet">
  <div class="prot-title">
    <h1><?= te('EGZAMINO PROTOKOLAS') ?></h1>
    <div class="prot-org"><?= e($p['org_name']) ?></div>
    <div class="prot-meta"><span><?= te('Egzaminas') ?> <b><?= e($p['kyu_label'] ?: '_____') ?></b> KYU</span><span><?= te('Egzamino data ir vieta') ?>: <b><?= e((string) $p['place_text']) ?></b></span></div>
  </div>

  <details class="panel card no-print prot-head-form">
    <summary><?= te('✎ Antraštė: organizacija, kyu, data ir vieta, egzaminatorius') ?></summary>
    <form method="post" class="form">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="meta">
      <div class="form-row">
        <label><?= te('Organizacija') ?> <input type="text" name="org_name" value="<?= e($p['org_name']) ?>" maxlength="190"></label>
        <label><?= te('Egzaminas (kyu)') ?> <span class="hint"><?= te('pvz. 8-5') ?></span><input type="text" name="kyu_label" value="<?= e((string) $p['kyu_label']) ?>" maxlength="60"></label>
      </div>
      <label><?= te('Egzamino data ir vieta') ?> <input type="text" name="place_text" value="<?= e((string) $p['place_text']) ?>" maxlength="190"></label>
      <div class="form-row">
        <label><?= te('Egzaminatorius (vardas, pavardė)') ?> <input type="text" name="examiner_name" value="<?= e((string) $p['examiner_name']) ?>" maxlength="120"></label>
        <label><?= te('Licencijos Nr.') ?> <input type="text" name="examiner_license" value="<?= e((string) $p['examiner_license']) ?>" maxlength="60"></label>
      </div>
      <div><button class="btn btn-primary btn-sm" type="submit"><?= te('Išsaugoti') ?></button> <span class="small muted"><?= te('Organizacija ir egzaminatorius bus įrašyti ir kitame protokole.') ?></span></div>
    </form>
  </details>

  <div class="row no-print prot-tools">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="add_members">
      <button class="btn btn-primary btn-sm" type="submit"><?= te('+ Įtraukti užsiregistravusius') ?></button></form>
    <details class="prot-add">
      <summary class="btn btn-ghost btn-sm"><?= te('+ Kitas dalyvis') ?></summary>
      <form method="post" class="row" style="gap:6px; margin-top:6px;">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="add_row">
        <input type="text" name="name" placeholder="<?= te('Vardas Pavardė') ?>" required>
        <input type="date" name="birth_date" aria-label="<?= te('Gimimo data') ?>">
        <input type="text" name="kyu" placeholder="kyu" style="width:90px;">
        <button class="btn btn-sm" type="submit"><?= te('Įtraukti') ?></button>
      </form>
    </details>
    <span class="spacer"></span>
    <span class="small muted" id="protSaved" aria-live="polite"></span>
    <button class="btn btn-ghost btn-sm" type="button" onclick="window.print()"><?= te('🖨 Spausdinti') ?></button>
    <a class="btn btn-ghost btn-sm" href="?id=<?= $id ?>&amp;csv=1"><?= te('⬇ Excel') ?></a>
    <details class="prot-mail">
      <summary class="btn btn-ghost btn-sm"><?= te('✉ Siųsti el. paštu') ?></summary>
      <form method="post" class="row" style="gap:6px; margin-top:6px;">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="email">
        <input type="email" name="to" value="<?= e((string) $me['email']) ?>" required aria-label="<?= te('El. paštas') ?>">
        <button class="btn btn-sm" type="submit"><?= te('Siųsti') ?></button>
      </form>
    </details>
  </div>

  <div class="table-scroll">
  <table class="prot-table" id="protTable">
    <thead>
      <tr><th rowspan="2">Nr.</th><th rowspan="2"><?= te('Vardas Pavardė') ?></th><th rowspan="2"><?= te('Gimimo data') ?></th><th rowspan="2">Kyu</th>
        <th colspan="4"><?= te('Įvertinimas') ?></th><th rowspan="2"><?= te('Dalyvavimas seminaruose, stovyklose') ?></th><th rowspan="2"><?= te('Pastabos') ?></th><th rowspan="2"><?= te('Išlaikė / Neišlaikė') ?></th><th rowspan="2" class="no-print"></th></tr>
      <tr><th>Kihon</th><th>Kata</th><th>Kumite</th><th><?= te('Testas') ?></th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr data-row="<?= (int) $r['id'] ?>" class="res-<?= e($r['result']) ?>">
        <td class="prot-nr"><?= e($r['number']) ?></td>
        <td><input class="pi" data-f="name" value="<?= e($r['name']) ?>" aria-label="<?= te('Vardas Pavardė') ?>"></td>
        <td><input class="pi" type="date" data-f="birth_date" value="<?= e((string) $r['birth_date']) ?>" aria-label="<?= te('Gimimo data') ?>"></td>
        <td><input class="pi pi-s" data-f="kyu" value="<?= e((string) $r['kyu']) ?>" aria-label="Kyu"></td>
        <?php foreach (['kihon' => 'Kihon', 'kata' => 'Kata', 'kumite' => 'Kumite', 'test' => t('Testas')] as $f => $l): ?>
          <td><input class="pi pi-s" data-f="<?= $f ?>" value="<?= e((string) $r[$f]) ?>" aria-label="<?= e($l) ?>"></td>
        <?php endforeach; ?>
        <td><input class="pi" data-f="seminars" value="<?= e((string) $r['seminars']) ?>" aria-label="<?= te('Seminarai, stovyklos') ?>"></td>
        <td><textarea class="pi" data-f="notes" rows="1" aria-label="<?= te('Pastabos') ?>"><?= e((string) $r['notes']) ?></textarea></td>
        <td class="prot-res">
          <button type="button" class="res-btn ok<?= $r['result'] === 'passed' ? ' on' : '' ?>" data-res="passed"><?= te('Išlaikė') ?></button>
          <button type="button" class="res-btn no<?= $r['result'] === 'failed' ? ' on' : '' ?>" data-res="failed"><?= te('Neišlaikė') ?></button>
          <span class="print-only"><?= $r['result'] === 'passed' ? te('Išlaikė') : ($r['result'] === 'failed' ? te('Neišlaikė') : '') ?></span>
        </td>
        <td class="no-print">
          <form method="post" onsubmit="return confirm('<?= e(t('Neatvyko - ištrinti iš protokolo? Numeris daugiau niekam nebus priskirtas.')) ?>')">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="delete_row"><input type="hidden" name="row" value="<?= (int) $r['id'] ?>">
            <button class="linklike small muted" type="submit" title="<?= te('Neatvyko - ištrinti') ?>">✕</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="12" class="muted"><?= te('Dalyvių dar nėra - spauskite „+ Įtraukti užsiregistravusius“ arba „+ Kitas dalyvis“.') ?></td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>

  <div class="prot-sign">
    <div><?= te('Egzaminatorius') ?>: <b><?= e((string) $p['examiner_name'] ?: '______________________') ?></b> <span class="muted">(<?= te('vardas, pavardė') ?>)</span></div>
    <div><?= te('Licencijos Nr.') ?>: <b><?= e((string) $p['examiner_license'] ?: '__________') ?></b></div>
    <div><?= te('Egzaminatoriaus parašas') ?>: ______________________</div>
  </div>
  <p class="small muted no-print"><?= te('Išlaikė') ?>: <b><?= $passed ?></b> · <?= te('Neišlaikė') ?>: <b><?= $failed ?></b> · <?= te('iš viso') ?> <?= count($rows) ?></p>

  <?php if (is_admin($me) && $rows): ?>
    <form method="post" class="no-print"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="to_results">
      <button class="btn btn-ghost btn-sm" type="submit"><?= te('🏅 „Išlaikė“ → renginio rezultatai ir XP') ?></button>
      <span class="small muted"><?= te('Diržą pakelti galima „Rezultatai ir XP“ puslapyje.') ?></span></form>
  <?php endif; ?>

  <?php if ($gone): ?>
    <details class="no-print" style="margin-top:14px;">
      <summary class="small"><?= te('Neatvykę (ištrinti)') ?>: <?= count($gone) ?></summary>
      <ul class="list small">
        <?php foreach ($gone as $r): ?>
          <li class="row between"><span><s><?= e($r['number']) ?></s> <?= e($r['name']) ?></span>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="restore_row"><input type="hidden" name="row" value="<?= (int) $r['id'] ?>">
              <button class="btn btn-ghost btn-sm" type="submit"><?= te('Grąžinti') ?></button></form></li>
        <?php endforeach; ?>
      </ul>
    </details>
  <?php endif; ?>
</div>
<script>
(function () {
  // Keičiama išsaugoma iš karto: laukelis - pakeitus, „Išlaikė / Neišlaikė“ - paspaudus (dar kartą - nuima)
  var api = <?= json_encode(url('admin/egzamino-protokolas.php?id=' . $id . '&save=1')) ?>, csrf = <?= json_encode(csrf_token()) ?>, st = document.getElementById('protSaved');
  function send(row, field, value) {
    st.textContent = 'Saugoma…';
    return fetch(api, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, Accept: 'application/json' },
      body: JSON.stringify({ row: row, field: field, value: value }) }).then(function (r) { return r.json(); })
      .then(function (d) { st.textContent = d.ok ? 'Išsaugota ✓' : 'Neišsaugota!'; })
      .catch(function () { st.textContent = 'Nėra ryšio - neišsaugota!'; });
  }
  var t = document.getElementById('protTable');
  t.addEventListener('change', function (e) { var f = e.target.dataset.f, tr = e.target.closest('tr[data-row]'); if (f && tr) send(tr.dataset.row, f, e.target.value); });
  t.addEventListener('click', function (e) {
    var b = e.target.closest('[data-res]'); if (!b) return;
    var tr = b.closest('tr[data-row]'), on = !b.classList.contains('on');
    tr.querySelectorAll('[data-res]').forEach(function (x) { x.classList.remove('on'); });
    if (on) b.classList.add('on');
    tr.className = 'res-' + (on ? b.dataset.res : '');
    var po = tr.querySelector('.print-only'); if (po) po.textContent = on ? b.textContent : '';   // spausdinant
    send(tr.dataset.row, 'result', on ? b.dataset.res : '');
  });
  // Enter - į kitą laukelį (kaip lentelėje)
  t.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' || e.target.tagName === 'TEXTAREA' || !e.target.dataset.f) return;
    e.preventDefault();
    var all = Array.prototype.slice.call(t.querySelectorAll('.pi')), i = all.indexOf(e.target);
    if (all[i + 1]) all[i + 1].focus();
  });
})();
</script>
<?php
page_end();
