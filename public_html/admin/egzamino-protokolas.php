<?php
// Egzamino protokolas (Egzaminai → „📋 Protokolas“): pagrindinė - lentelė. Balai su − / + po 0,1 (ar įrašius; vidurkio stulpelio nebėra - #124),
// komentaras, „Sumokėjo“, „Išlaikė / Neišlaikė“; keičiama išsaugoma iš karto. Visa kita (antraštė, stulpeliai, registracijos nuoroda,
// siuntimas) - „⚙ Daugiau“ apačioje. Spausdinimas (kaip popierinis LTKA protokolas), Excel ir siuntimas el. paštu.
// Kiekvienas dalyvis - unikalus numeris MM-mm-dd-eilė; neatvykusį ištrynus, jo numeris daugiau niekam nepriskiriamas.
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

$id = (int) (get('id') ?: post('id'));
$event = q_one('SELECT * FROM events WHERE id = ?', [$id]);
if (!$event || $event['type'] !== 'exam' || !exam_protocol_ready()) {
    not_found();
}
$p = exam_protocol($event);

// Excel (.xlsx; be ZipArchive - CSV)
if (get('xlsx')) {
    xlsx_download(preg_replace('/\.csv$/', '', exam_protocol_filename($event)), exam_protocol_table($event), t('Protokolas'), 0);
}
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
    if ($row && preg_match('/^x\d{1,3}$/', $field)) {   // papildomas stulpelis
        exam_set_extra($row, $field, mb_substr(trim((string) ($in['value'] ?? '')), 0, 60));
        q('UPDATE exam_protocols SET updated_at = NOW() WHERE event_id = ?', [$id]);
        exit(json_encode(['ok' => true]));
    }
    if ($row && in_array($field, ['phone', 'paid'], true) && exam_v40_ready()) {
        if ($field === 'paid') {
            q('UPDATE exam_protocol_rows SET paid_at = ? WHERE id = ?', [($in['value'] ?? '') === '1' ? date('Y-m-d H:i:s') : null, (int) $row['id']]);
        } else {
            q('UPDATE exam_protocol_rows SET phone = ? WHERE id = ?', [mb_substr(trim((string) ($in['value'] ?? '')), 0, 40) ?: null, (int) $row['id']]);
        }
        exit(json_encode(['ok' => true]));
    }
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
        $v = $field === 'notes' ? mb_substr($v, 0, 2000) : mb_substr($v, 0, $field === 'seminars' ? 255 : ($field === 'club' ? 120 : 60));
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
            if (exam_v40_ready() && is_numeric(str_replace(',', '.', (string) post('score_base')))) {
                q('UPDATE exam_protocols SET score_base = ? WHERE event_id = ?', [max(0, min(10, round((float) str_replace(',', '.', (string) post('score_base')), 1))), $id]);
            }
            flash('ok', t('Protokolo antraštė išsaugota.'));
            break;
        case 'cols':
            // Įvertinimo stulpeliai: pervadinti, išimti (✕), pridėti naują
            $cols = [];
            foreach ((array) ($_POST['col_key'] ?? []) as $i => $k) {
                $k = (string) $k;
                if (!empty($_POST['col_del'][$i]) || !preg_match('/^(kihon|kata|kumite|test|x\d{1,3})$/', $k)) {
                    continue;
                }
                $cols[] = [$k, mb_substr(trim((string) ($_POST['col_label'][$i] ?? '')), 0, 40) ?: $k];
            }
            if (trim(post('col_new')) !== '') {
                $used = array_column($cols, 0);
                foreach (exam_cols($p) as $c) {
                    $used[] = $c[0];
                }
                $n = 1;
                while (in_array('x' . $n, $used, true)) {
                    $n++;
                }
                $cols[] = ['x' . $n, mb_substr(trim(post('col_new')), 0, 40)];
            }
            flash($cols && exam_cols_save($id, $cols) ? 'ok' : 'err', $cols ? t('Stulpeliai išsaugoti.') : t('Turi likti bent vienas įvertinimo stulpelis.'));
            break;
        case 'fed_send':
            if (!fed_ready()) {
                flash('err', t('Federacijos registras veiks, kai bus atnaujinta duomenų bazė (Šiandien → „Atnaujinkite duomenų bazę“).'));
                break;
            }
            [$new, $old] = fed_send_protocol($event);
            flash('ok', t('Protokolas išsiųstas Federacijai: naujų žmonių {n}, jau registre {o}. Numeriai - stulpelyje „Nr.“', ['n' => $new, 'o' => $old]));
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
            $nrid = exam_protocol_add($event, ['name' => trim(post('name')), 'birth_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', post('birth_date')) ? post('birth_date') : null, 'kyu' => post('kyu') ?: null, 'club' => trim(post('club')) ?: null]);
            if (trim(post('phone')) !== '' && exam_v40_ready()) {
                q('UPDATE exam_protocol_rows SET phone = ? WHERE id = ?', [mb_substr(trim(post('phone')), 0, 40), $nrid]);
            }
            flash('ok', t('Dalyvis įtrauktas.'));
            break;
        case 'new_link':
            // A3: ↻ nauja asmeninė nuoroda - senoji nebeveikia
            $nr = q_one('SELECT id FROM exam_protocol_rows WHERE id = ? AND event_id = ?', [(int) post('row'), $id]);
            if ($nr) {
                exam_row_new_token((int) $nr['id']);
                flash('ok', t('Nauja asmeninė nuoroda sukurta - senoji nebeveikia. Nukopijuokite 🔗 ir nusiųskite.'));
            }
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
            flash('ok', t('Perkelta į renginio rezultatus (aktyvumo taškai).') . ($n ? '' : ' ' . t('Pakeitimų nebuvo.')));
            break;
        case 'su_create':
            flash(exam_signup_link($id, true) ? 'ok' : 'err', exam_signup_ready() ? t('Registracijos nuoroda sukurta - nukopijuokite ir išsiųskite.') : t('Atnaujinkite duomenų bazę (Šiandien → „Atnaujinkite duomenų bazę“).'));
            break;
        case 'su_short':   // v1.92: ilgą (seną) nuorodą pakeisti trumpa - sena nebeveiks
            if (exam_signup_link($id)) {
                q('UPDATE exam_signup_links SET token = ? WHERE event_id = ?', [exam_short_code(), $id]);
                flash('ok', t('Nauja trumpa nuoroda sukurta. Sena nebeveikia.'));
            }
            break;
        case 'su_toggle':
            if ($l = exam_signup_link($id)) {
                q('UPDATE exam_signup_links SET is_open = ? WHERE event_id = ?', [(int) $l['is_open'] ? 0 : 1, $id]);
                flash('ok', (int) $l['is_open'] ? t('Registracija uždaryta.') : t('Registracija atidaryta.'));
            }
            break;
        case 'su_invite':
            $l = exam_signup_link($id, true);
            if (!$l) {
                break;
            }
            [$sent, $fail, $bad] = exam_signup_invite($event, $l, (string) post('people'), trim((string) post('note')), trim($me['first_name'] . ' ' . $me['last_name']) . "\nKarateka");
            flash($sent && !$fail && !$bad ? 'ok' : 'err', t('Kvietimų išsiųsta: {n}.', ['n' => $sent]) . ($fail ? ' ' . t('Nepavyko išsiųsti: {n}.', ['n' => $fail]) : '')
                . ($bad ? ' ' . t('Be el. pašto (praleista): {l}', ['l' => implode('; ', array_slice($bad, 0, 5))]) : ''));
            break;
        case 'email':
            $to = trim(post('to'));
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                flash('err', t('Patikrinkite el. pašto adresą.'));
                break;
            }
            $ok = send_mail($to, t('Egzamino protokolas') . ': ' . $event['title'], t('Egzamino protokolas prisegtas (atidaroma su Excel).') . "\n\n" . $event['title'] . "\n" . (string) $p['place_text'],
                [($xl = xlsx_build(exam_protocol_table($event), t('Protokolas'), 0)) !== null
                    ? ['name' => preg_replace('/\.csv$/', '.xlsx', exam_protocol_filename($event)), 'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'data' => $xl]
                    : ['name' => exam_protocol_filename($event), 'type' => 'text/csv; charset=utf-8', 'data' => exam_protocol_csv($event)]]);
            flash($ok ? 'ok' : 'err', $ok ? t('Protokolas išsiųstas: {to}.', ['to' => $to]) : t('Nepavyko išsiųsti laiško. Atsisiųskite Excel ir išsiųskite patys.'));
            break;
    }
    redirect('admin/egzamino-protokolas.php?id=' . $id);
}


$rows = exam_protocol_rows($id);
$gone = exam_protocol_rows($id, true);
$suLink = exam_signup_link($id);
$suList = $suLink ? exam_signups($id) : [];
$suOpen = exam_signup_open($event, $suLink);
$cols = exam_cols($p);
$fedNo = fed_numbers_for_event($id);
$v40 = exam_v40_ready();
$base = $v40 && $p['score_base'] !== null ? (float) $p['score_base'] : 5.0;
$prices = exam_prices();
$priceByKyu = [];
foreach ($prices as $lvl => $pr) {
    $priceByKyu[mb_strtolower(exam_grades()[$lvl][0])] = money_fmt($pr);
}
$passed = count(array_filter($rows, function ($r) { return $r['result'] === 'passed'; }));
$failed = count(array_filter($rows, function ($r) { return $r['result'] === 'failed'; }));
$signed = count(array_filter($suList, function ($x) { return $x['signed_at']; }));
$fmtBase = rtrim(rtrim(number_format($base, 1, '.', ''), '0'), '.');
page_start(t('Egzamino protokolas') . ': ' . $event['title'], ['admin' => true, 'wide' => true, 'native' => true]);   // planšetėje - savas vaizdas su dideliais mygtukais
?>
<div class="no-print prot-crumbs small">
  <a href="<?= url('admin/egzaminai.php') ?>"><?= te('← Egzaminai') ?></a> · <a href="<?= url('renginys.php?id=' . $id) ?>"><?= te('Renginys') ?></a>
</div>

<div class="prot-sheet">
  <div class="prot-title">
    <h1><?= te('EGZAMINO PROTOKOLAS') ?></h1>
    <div class="prot-org"><?= e($p['org_name']) ?></div>
    <div class="prot-meta"><span><?= te('Egzaminas') ?> <b><?= e($p['kyu_label'] ?: '_____') ?></b> KYU</span><span><?= te('Egzamino data ir vieta') ?>: <b><?= e((string) $p['place_text']) ?></b></span></div>
  </div>

  <div class="row no-print prot-tools">
    <details class="prot-add">
      <summary class="btn btn-primary btn-sm"><?= te('+ Dalyvis') ?></summary>
      <form method="post" class="prot-add-form">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="add_row">
        <input type="text" name="name" placeholder="<?= te('Vardas Pavardė') ?>" required>
        <input type="date" name="birth_date" aria-label="<?= te('Gimimo data') ?>">
        <input type="tel" name="phone" placeholder="<?= te('Telefonas') ?>">
        <input type="text" name="club" placeholder="<?= te('Klubas') ?>" style="width:130px;">
        <input type="text" name="kyu" placeholder="kyu" list="kyuList" style="width:90px;">
        <button class="btn btn-sm" type="submit"><?= te('Įtraukti') ?></button>
      </form>
    </details>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="add_members">
      <button class="btn btn-ghost btn-sm" type="submit" title="<?= te('Klubo nariai, kurie renginyje pažymėjo „Dalyvausiu“') ?>"><?= te('+ Klubo nariai iš renginio') ?></button></form>
    <span class="spacer"></span>
    <span class="small muted" id="protSaved" aria-live="polite"></span>
    <button class="btn btn-ghost btn-sm" type="button" onclick="window.print()"><?= te('🖨 Spausdinti') ?></button>
    <a class="btn btn-ghost btn-sm" href="?id=<?= $id ?>&amp;xlsx=1"><?= icon('download') ?> <span>Excel</span></a>
    <a class="btn btn-ghost btn-sm" href="#daugiau" data-open-more><?= te('⚙ Daugiau') ?></a>
  </div>
  <p class="no-print small muted prot-hint"><?= te('Balai: − / + keičia po 0,1 (ar rodyklės ↑ ↓), galima ir įrašyti. Tuščias langelis prasideda nuo {b}. Viskas išsaugoma iš karto.', ['b' => $fmtBase]) ?></p>

  <div class="table-scroll prot-wrap">
  <?php $steppers = count(array_filter($cols, function ($c) { return $c[0] !== 'test'; })); ?>
  <table class="prot-table<?= $steppers >= 4 ? ' many-sc' : '' ?>" id="protTable" data-base="<?= e((string) $base) ?>">
    <thead>
      <tr><th rowspan="2" class="th-nr">Nr.</th><th rowspan="2"><?= te('Vardas Pavardė') ?></th><th rowspan="2" class="col-p"><?= te('Gimimo data') ?></th><th rowspan="2" class="col-p"><?= te('Klubas') ?></th><th rowspan="2">Kyu</th>
        <th colspan="<?= count($cols) ?>"><?= te('Įvertinimas') ?></th>
        <th rowspan="2" class="col-p"><?= te('Dalyvavimas seminaruose, stovyklose') ?></th><th rowspan="2"><?= te('Komentaras') ?></th>
        <?php if ($v40): ?><th rowspan="2" class="no-print" title="<?= te('Sumokėjo už egzaminą') ?>">€</th><?php endif; ?>
        <th rowspan="2"><?= te('Išlaikė / Neišlaikė') ?></th><th rowspan="2" class="no-print"></th></tr>
      <tr><?php foreach ($cols as [$k, $label]): ?><th><?= e($label) ?></th><?php endforeach; ?></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): $pu = $v40 ? exam_row_url($r) : null;
      $sub = array_filter([$r['birth_date'] ? fmt_date($r['birth_date']) : '', (string) ($r['phone'] ?? ''), (string) ($r['club'] ?? '')]); ?>
      <tr data-row="<?= (int) $r['id'] ?>" class="res-<?= e((string) $r['result']) ?>">
        <td class="prot-nr" data-l="Nr."><?= isset($fedNo[(int) $r['id']]) ? '<b title="' . te('Federacijos numeris') . '">' . e($fedNo[(int) $r['id']]) . '</b>' : '<span class="muted" title="' . te('Laikinas - Federacijos numeris bus suteiktas išsiuntus protokolą Federacijai') . '">' . e($r['number']) . '</span>' ?></td>
        <td class="prot-name">
          <input class="pi pi-name" data-f="name" value="<?= e($r['name']) ?>" aria-label="<?= te('Vardas Pavardė') ?>">
          <div class="prot-sub no-print">
            <span class="nr-in" title="Nr."><?= e($fedNo[(int) $r['id']] ?? $r['number']) ?></span>
            <button type="button" class="linklike pr-sub-t" data-subedit title="<?= te('Gimimo data, telefonas, klubas, seminarai') ?>"><span class="pr-sub-txt"><?= $sub ? e(implode(' · ', $sub)) : te('+ gimimo data, telefonas') ?></span> ✎</button>
            <?php if ($pu): ?><button type="button" class="linklike pr-link" data-copy="<?= e($pu) ?>" title="<?= te('Kopijuoti asmeninę nuorodą (mano egzaminas be paskyros) - nusiųsti SMS ar Messenger') ?>">🔗</button>
              <button type="submit" form="nl<?= (int) $r['id'] ?>" class="linklike pr-newlink" title="<?= te('Nauja asmeninė nuoroda - senoji nebeveiks (jei nuoroda pateko ne tam žmogui)') ?>">↻</button><?php endif; ?>
          </div>
          <div class="prot-subedit no-print" hidden>
            <label><?= te('Gimimo data') ?><input class="pi" type="date" data-f="birth_date" value="<?= e((string) $r['birth_date']) ?>"></label>
            <?php if ($v40): ?><label><?= te('Telefonas') ?><input class="pi" type="tel" data-f="phone" value="<?= e((string) ($r['phone'] ?? '')) ?>"></label><?php endif; ?>
            <label><?= te('Klubas') ?><input class="pi" data-f="club" value="<?= e((string) ($r['club'] ?? '')) ?>"></label>
            <label><?= te('Seminarai, stovyklos') ?><input class="pi" data-f="seminars" value="<?= e((string) $r['seminars']) ?>"></label>
          </div>
        </td>
        <td class="col-p" data-p="birth_date"><?= e((string) $r['birth_date']) ?></td>
        <td class="col-p" data-p="club"><?= e((string) ($r['club'] ?? '')) ?></td>
        <td data-l="Kyu"><input class="pi pi-s pi-kyu" data-f="kyu" value="<?= e((string) $r['kyu']) ?>" list="kyuList" aria-label="Kyu"></td>
        <?php foreach ($cols as [$f, $l]): ?>
          <?php $cv = str_replace(',', '.', exam_cell($r, $f)); $low = $f !== 'test' && is_numeric($cv) && (float) $cv <= EXAM_LOW_SCORE; ?>
          <td data-l="<?= e($l) ?>" class="<?= $f === 'test' ? 'sc-test' : 'sc' . ($low ? ' low' : '') ?>"><?php if ($f === 'test'): ?>
            <input class="pi pi-s" data-f="test" value="<?= e(exam_cell($r, $f)) ?>" aria-label="<?= e($l) ?>">
          <?php else: ?><div class="sc-box"><button type="button" class="sc-b no-print" data-d="-1" tabindex="-1" aria-label="<?= e($l) ?> −0,1">−</button><input class="pi pi-score" data-f="<?= e($f) ?>" value="<?= e(exam_cell($r, $f)) ?>" inputmode="decimal" aria-label="<?= e($l) ?>"><button type="button" class="sc-b no-print" data-d="1" tabindex="-1" aria-label="<?= e($l) ?> +0,1">+</button></div><?php endif; ?></td>
        <?php endforeach; ?>
        <td class="col-p" data-p="seminars"><?= e((string) $r['seminars']) ?></td>
        <td class="prot-note" data-l="<?= te('Komentaras') ?>"><textarea class="pi" data-f="notes" rows="1" placeholder="<?= te('komentaras…') ?>" aria-label="<?= te('Komentaras') ?>"><?= e((string) $r['notes']) ?></textarea></td>
        <?php if ($v40): $pr = $priceByKyu[mb_strtolower(trim((string) $r['kyu']))] ?? ''; ?>
          <td class="no-print prot-pay" data-l="€"><button type="button" class="pay-btn<?= $r['paid_at'] ? ' on' : '' ?>" data-pay title="<?= te('Sumokėjo (paspaudus - pažymima / nuimama)') ?>"><span class="pay-ok"><?= $r['paid_at'] ? '✓ ' : '' ?></span><span class="pay-sum"><?= e($pr ?: '€') ?></span></button></td>
        <?php endif; ?>
        <td class="prot-res">
          <button type="button" class="res-btn ok<?= $r['result'] === 'passed' ? ' on' : '' ?>" data-res="passed"><?= te('Išlaikė') ?></button>
          <button type="button" class="res-btn no<?= $r['result'] === 'failed' ? ' on' : '' ?>" data-res="failed"><?= te('Neišlaikė') ?></button>
          <span class="print-only"><?= $r['result'] === 'passed' ? te('Išlaikė') : ($r['result'] === 'failed' ? te('Neišlaikė') : '') ?></span>
        </td>
        <td class="no-print prot-del">
          <form method="post" id="nl<?= (int) $r['id'] ?>" hidden onsubmit="return confirm('<?= e(t('Sukurti naują asmeninę nuorodą? Senoji nebeveiks.')) ?>')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="new_link"><input type="hidden" name="row" value="<?= (int) $r['id'] ?>"></form>
          <form method="post" onsubmit="return confirm('<?= e(t('Neatvyko - ištrinti iš protokolo? Numeris daugiau niekam nebus priskirtas.')) ?>')">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="delete_row"><input type="hidden" name="row" value="<?= (int) $r['id'] ?>">
            <button class="linklike small muted" type="submit" title="<?= te('Neatvyko - ištrinti') ?>">✕</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="<?= 12 + count($cols) ?>" class="muted"><?= te('Dalyvių dar nėra. Nusiųskite registracijos nuorodą (⚙ Daugiau) arba spauskite „+ Dalyvis“.') ?></td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
  <datalist id="kyuList"><?php foreach (exam_grades() as $l => $b): ?><option value="<?= e($b[0]) ?>"><?php endforeach; ?></datalist>

  <div class="prot-sign">
    <div><?= te('Egzaminatorius') ?>: <b><?= e((string) $p['examiner_name'] ?: '______________________') ?></b> <span class="muted">(<?= te('vardas, pavardė') ?>)</span></div>
    <div><?= te('Licencijos Nr.') ?>: <b><?= e((string) $p['examiner_license'] ?: '__________') ?></b></div>
    <div><?= te('Egzaminatoriaus parašas') ?>: ______________________</div>
  </div>
  <p class="small muted no-print" id="protSum"><?= te('Išlaikė') ?>: <b id="sumPassed"><?= $passed ?></b> · <?= te('Neišlaikė') ?>: <b id="sumFailed"><?= $failed ?></b> · <?= te('iš viso') ?> <span id="sumAll"><?= count($rows) ?></span></p>

  <?php if ($rows): ?>
    <div class="row no-print prot-after">
      <form method="post" class="prot-fed"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="fed_send">
        <button class="btn btn-primary btn-sm" type="submit"><?= !empty($p['fed_sent_at']) ? te('📨 Atnaujinti Federacijoje') : te('📨 Siųsti protokolą Federacijai') ?></button></form>
      <?php if (is_admin($me)): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="to_results">
          <button class="btn btn-ghost btn-sm" type="submit" title="<?= te('Diržą pakelti galima „Rezultatai ir taškai“ puslapyje.') ?>"><?= te('🏅 „Išlaikė“ → renginio rezultatai ir taškai') ?></button></form>
      <?php endif; ?>
      <button class="btn btn-ghost btn-sm" type="button" disabled title="<?= te('Reikia sertifikato šablono') ?>"><?= te('📜 Sertifikatai išlaikiusiems') ?></button><span class="need-info"><?= te('reikia info') ?></span>
    </div>
    <p class="small muted no-print"><?= !empty($p['fed_sent_at']) ? te('Federacijai išsiųsta') . ' ' . e(fmt_date(substr((string) $p['fed_sent_at'], 0, 10))) . '. ' : '' ?><?= te('Visi laikę įrašomi į Federacijos registrą; kiekvienas gauna nuolatinį numerį (pvz. 26.11.15-1).') ?> <a href="<?= url('admin/federacija.php') ?>"><?= te('📒 Federacijos registras') ?></a></p>
  <?php endif; ?>

  <!-- ⚙ Daugiau: antraštė, stulpeliai, registracija per nuorodą, siuntimas el. paštu, neatvykę -->
  <details class="no-print prot-more" id="daugiau">
    <summary><b><?= te('⚙ Daugiau: registracijos nuoroda, antraštė, stulpeliai, siuntimas') ?></b>
      <?php if ($suLink): ?><span class="badge<?= $suOpen ? ' ok' : '' ?>"><?= $suOpen ? te('registracija atidaryta') : te('registracija uždaryta') ?></span> <span class="small muted"><?= te('užsiregistravo: {n}', ['n' => $signed]) ?></span><?php endif; ?></summary>
    <div class="prot-more-grid">
      <section class="panel card prot-su" id="registracija">
        <h3><?= te('🔗 Registracija per nuorodą') ?></h3>
        <?php if (!exam_signup_ready()): ?>
          <p class="muted small"><?= te('Veiks, kai bus atnaujinta duomenų bazė (Pradžia → „Atnaujinkite duomenų bazę“).') ?></p>
        <?php elseif (!$suLink): ?>
          <p class="small"><?= te('Vaikai ir kitų klubų nariai užsiregistruoja be paskyros: vardas, pavardė, gimimo data, telefonas. Iškart atsiranda šiame protokole ir gauna savo egzamino puslapį.') ?></p>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="su_create">
            <button class="btn btn-primary btn-sm" type="submit"><?= te('Sukurti registracijos nuorodą') ?></button></form>
        <?php else: $suUrl = exam_signup_url($suLink); ?>
          <p class="small"><?= te('Nusiųskite nuorodą (Messenger, SMS, el. paštu). Užsiregistravę iškart atsiranda protokole; kiekvienas gauna asmeninį egzamino puslapį (🔗 prie vardo).') ?></p>
          <div class="row" style="gap:6px; flex-wrap:wrap;">
            <input type="text" readonly value="<?= e($suUrl) ?>" class="su-url" aria-label="<?= te('Registracijos nuoroda') ?>" onclick="this.select()">
            <button type="button" class="btn btn-sm" data-copy="<?= e($suUrl) ?>"><?= te('Kopijuoti') ?></button>
            <a class="btn btn-ghost btn-sm" href="<?= e($suUrl) ?>" target="_blank" rel="noopener"><?= te('Atidaryti ↗') ?></a>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="su_toggle">
              <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $suLink['is_open'] ? te('Uždaryti registraciją') : te('Atidaryti registraciją') ?></button></form>
            <?php if (strlen((string) $suLink['token']) > 6): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="su_short">
              <button class="btn btn-ghost btn-sm" type="submit" onclick="return confirm('<?= e(t('Sukurti trumpą nuorodą? Sena (ilga) nebeveiks.')) ?>')"><?= te('↻ Trumpa nuoroda') ?></button></form><?php endif; ?>
          </div>
          <?php if (!$suOpen && (int) $suLink['is_open']): ?><p class="small muted"><?= reg_on('exam') ? te('Registracija nevyksta: praėjo registracijos terminas arba egzaminas jau įvyko.') : te('Egzaminų registracija išjungta: Nustatymai → Registracija.') ?></p><?php endif; ?>
          <?php if ($suList): ?>
            <div class="table-scroll"><table class="su-table">
              <thead><tr><th><?= te('Vardas Pavardė') ?></th><th><?= te('Gimimo data') ?></th><th><?= te('Telefonas') ?></th><th><?= te('Būsena') ?></th></tr></thead>
              <tbody>
              <?php foreach ($suList as $x): ?>
                <tr><td><?= e(trim($x['first_name'] . ' ' . $x['last_name'])) ?: '<span class="muted">—</span>' ?><?= $x['email'] ? '<div class="small muted">' . e($x['email']) . '</div>' : '' ?></td>
                  <td class="small"><?= $x['birth_date'] ? e(fmt_date($x['birth_date'])) : '' ?></td><td class="small"><?= e((string) $x['phone']) ?></td>
                  <td class="small"><?= $x['signed_at'] ? '✓ ' . te('užsiregistravo') . ' ' . e(substr((string) $x['signed_at'], 5, 11)) : '✉ ' . te('pakviestas') . ' ' . e(substr((string) $x['invited_at'], 5, 11)) ?></td></tr>
              <?php endforeach; ?>
              </tbody></table></div>
          <?php endif; ?>
          <details class="su-invite">
            <summary class="small"><?= te('✉ Pakviesti el. paštu') ?></summary>
            <form method="post" class="form" style="margin-top:8px;">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="su_invite">
              <label><?= te('Kam (po vieną eilutėje: Vardas Pavardė, el. paštas)') ?>
                <textarea name="people" rows="3" required placeholder="Jonas Jonaitis, jonas@pastas.lt&#10;petras@pastas.lt"></textarea></label>
              <label><?= te('Žinutė (nebūtina)') ?><textarea name="note" rows="2" maxlength="1000"></textarea></label>
              <button class="btn btn-primary btn-sm" type="submit"><?= te('Siųsti kvietimus') ?></button>
            </form>
          </details>
        <?php endif; ?>
      </section>

      <section class="panel card prot-head-form">
        <h3><?= te('✎ Antraštė ir balai') ?></h3>
        <form method="post" class="form">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="meta">
          <label><?= te('Organizacija') ?> <input type="text" name="org_name" value="<?= e($p['org_name']) ?>" maxlength="190"></label>
          <div class="form-row">
            <label><?= te('Egzaminas (kyu)') ?> <span class="hint"><?= te('pvz. 8-5') ?></span><input type="text" name="kyu_label" value="<?= e((string) $p['kyu_label']) ?>" maxlength="60"></label>
            <?php if ($v40): ?><label><?= te('Pradinis balas (− / +)') ?><input type="text" inputmode="decimal" name="score_base" value="<?= e($fmtBase) ?>" maxlength="4"></label><?php endif; ?>
          </div>
          <label><?= te('Egzamino data ir vieta') ?> <input type="text" name="place_text" value="<?= e((string) $p['place_text']) ?>" maxlength="190"></label>
          <div class="form-row">
            <label><?= te('Egzaminatorius (vardas, pavardė)') ?> <input type="text" name="examiner_name" value="<?= e((string) $p['examiner_name']) ?>" maxlength="120"></label>
            <label><?= te('Licencijos Nr.') ?> <input type="text" name="examiner_license" value="<?= e((string) $p['examiner_license']) ?>" maxlength="60"></label>
          </div>
          <div><button class="btn btn-primary btn-sm" type="submit"><?= te('Išsaugoti') ?></button> <span class="small muted"><?= te('Organizacija ir egzaminatorius bus įrašyti ir kitame protokole.') ?></span></div>
        </form>
      </section>

      <section class="panel card prot-cols">
        <h3><?= te('✎ Įvertinimo stulpeliai') ?></h3>
        <form method="post" class="prot-cols-form">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="cols">
          <?php foreach ($cols as $i => [$k, $label]): ?>
            <div class="row" style="gap:6px;"><input type="hidden" name="col_key[<?= $i ?>]" value="<?= e($k) ?>">
              <input type="text" name="col_label[<?= $i ?>]" value="<?= e($label) ?>" maxlength="40" aria-label="<?= te('Stulpelio pavadinimas') ?>">
              <label class="check small"><input type="checkbox" name="col_del[<?= $i ?>]" value="1"><span><?= te('išimti') ?></span></label></div>
          <?php endforeach; ?>
          <div class="row" style="gap:6px;"><input type="text" name="col_new" maxlength="40" placeholder="<?= te('+ naujas stulpelis, pvz. Bunkai') ?>"></div>
          <button class="btn btn-primary btn-sm" type="submit"><?= te('Išsaugoti stulpelius') ?></button>
        </form>
      </section>

      <section class="panel card prot-mail">
        <h3><?= te('✉ Siųsti protokolą el. paštu') ?></h3>
        <form method="post" class="row" style="gap:6px; flex-wrap:wrap;">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="email">
          <input type="email" name="to" value="<?= e((string) $me['email']) ?>" required aria-label="<?= te('El. paštas') ?>">
          <button class="btn btn-sm" type="submit"><?= te('Siųsti') ?></button>
        </form>
        <p class="small muted"><?= te('Prisegamas Excel failas.') ?> <a href="<?= url('admin/renginio-rezultatai.php?id=' . $id) ?>"><?= te('Rezultatai ir taškai') ?></a> · <a href="<?= url('admin/renginio-dalyviai.php?id=' . $id) ?>"><?= te('Renginio dalyviai') ?></a></p>
      </section>

      <?php if ($gone): ?>
        <section class="panel card">
          <h3><?= te('Neatvykę (ištrinti)') ?>: <?= count($gone) ?></h3>
          <ul class="list small">
            <?php foreach ($gone as $r): ?>
              <li class="row between"><span><s><?= e($r['number']) ?></s> <?= e($r['name']) ?></span>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="restore_row"><input type="hidden" name="row" value="<?= (int) $r['id'] ?>">
                  <button class="btn btn-ghost btn-sm" type="submit"><?= te('Grąžinti') ?></button></form></li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endif; ?>
    </div>
  </details>
</div>
<script>
(function () {
  // Keičiama išsaugoma iš karto: laukelis - pakeitus, balai - po − / + (truputį palaukus), „Išlaikė / Neišlaikė“ ir „€“ - paspaudus
  var api = <?= json_encode(url('admin/egzamino-protokolas.php?id=' . $id . '&save=1')) ?>, csrf = <?= json_encode(csrf_token()) ?>, st = document.getElementById('protSaved');
  var prices = <?= json_encode($priceByKyu, JSON_UNESCAPED_UNICODE) ?>, T = { saving: <?= json_encode(t('Saugoma…')) ?>, saved: <?= json_encode(t('Išsaugota ✓')) ?>, fail: <?= json_encode(t('Neišsaugota!')) ?>, offline: <?= json_encode(t('Nėra ryšio - neišsaugota!')) ?>, add: <?= json_encode(t('+ gimimo data, telefonas')) ?> };
  function send(row, field, value) {
    st.textContent = T.saving;
    return fetch(api, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, Accept: 'application/json' },
      body: JSON.stringify({ row: row, field: field, value: value }) }).then(function (r) { return r.json(); })
      .then(function (d) { st.textContent = d.ok ? T.saved : T.fail; })
      .catch(function () { st.textContent = T.offline; });
  }
  var t = document.getElementById('protTable'), base = parseFloat(t.dataset.base) || 5;
  function num(v) { v = String(v).replace(',', '.').trim(); return v !== '' && !isNaN(v) ? parseFloat(v) : null; }
  // #133: balas 6,3 ar mažesnis - langelis raudonas (neišlaikius - raudona visa eilutė)
  function markLow(inp) { if (inp.dataset.f === 'test') return; var v = num(inp.value), td = inp.closest('td'); if (td) td.classList.toggle('low', v !== null && v <= <?= json_encode(EXAM_LOW_SCORE) ?>); }   // „Testas“ - be raudonos taisyklės
  function fmt(n) { return (Math.round(n * 100) / 100).toString(); }
  // Pavardės eilutė apačioje: gimimo data · telefonas · klubas (+ spausdinimui - atskiri stulpeliai)
  function sub(tr) {
    var parts = [];
    ['birth_date', 'phone', 'club'].forEach(function (f) { var i = tr.querySelector('.prot-subedit [data-f="' + f + '"]'); if (i && i.value) parts.push(i.value); });
    tr.querySelector('.pr-sub-txt').textContent = parts.length ? parts.join(' · ') : T.add;
    ['birth_date', 'club', 'seminars'].forEach(function (f) { var c = tr.querySelector('[data-p="' + f + '"]'), i = tr.querySelector('.prot-subedit [data-f="' + f + '"]'); if (c && i) c.textContent = i.value; });
  }
  t.addEventListener('change', function (e) {
    var f = e.target.dataset.f, tr = e.target.closest('tr[data-row]'); if (!f || !tr) return;
    if (e.target.classList.contains('pi-score')) { var v = num(e.target.value); if (v !== null) e.target.value = fmt(v); markLow(e.target); }
    if (e.target.closest('.prot-subedit')) sub(tr);
    if (f === 'kyu') { var ps = tr.querySelector('.pay-sum'); if (ps) ps.textContent = prices[e.target.value.trim().toLowerCase()] || '€'; }
    send(tr.dataset.row, f, e.target.value.replace(',', '.'));
  });
  // − / +: po 0,1; tuščias - pradinis balas; ribos 0-10
  var timers = {};
  function bump(inp, d) {
    var v = num(inp.value), tr = inp.closest('tr[data-row]');
    v = v === null ? base : Math.min(10, Math.max(0, Math.round((v + d * 0.1) * 10) / 10));
    inp.value = fmt(v); markLow(inp);
    inp.classList.remove('bump'); void inp.offsetWidth; inp.classList.add('bump');
    var key = tr.dataset.row + inp.dataset.f;
    clearTimeout(timers[key]);
    timers[key] = setTimeout(function () { send(tr.dataset.row, inp.dataset.f, String(v)); }, 450);
  }
  t.addEventListener('click', function (e) {
    var sb = e.target.closest('.sc-b');
    if (sb) { bump(sb.parentNode.querySelector('.pi-score'), parseInt(sb.dataset.d, 10)); return; }
    var ed = e.target.closest('[data-subedit]');
    if (ed) { var box = ed.closest('td').querySelector('.prot-subedit'); box.hidden = !box.hidden; if (!box.hidden) box.querySelector('input').focus(); return; }
    var pay = e.target.closest('[data-pay]');
    if (pay) { var on = !pay.classList.contains('on'); pay.classList.toggle('on', on); pay.querySelector('.pay-ok').textContent = on ? '✓ ' : ''; send(pay.closest('tr').dataset.row, 'paid', on ? '1' : ''); return; }
    var b = e.target.closest('[data-res]'); if (!b) return;
    var tr = b.closest('tr[data-row]'), on2 = !b.classList.contains('on');
    tr.querySelectorAll('[data-res]').forEach(function (x) { x.classList.remove('on'); });
    if (on2) b.classList.add('on');
    tr.className = 'res-' + (on2 ? b.dataset.res : '');
    var po = tr.querySelector('.print-only'); if (po) po.textContent = on2 ? b.textContent : '';
    send(tr.dataset.row, 'result', on2 ? b.dataset.res : '');
    document.getElementById('sumPassed').textContent = t.querySelectorAll('tr.res-passed').length;
    document.getElementById('sumFailed').textContent = t.querySelectorAll('tr.res-failed').length;
  });
  // #166: balai iš karto 6.4 - paspaudus langelį visas skaičius pažymimas, rašant jis pakeičiamas (nereikia trinti)
  var scFocus = 0;
  t.addEventListener('focusin', function (e) { if (e.target.classList.contains('pi-score')) { scFocus = Date.now(); setTimeout(function () { try { e.target.select(); } catch (x) {} }, 0); } });
  t.addEventListener('mouseup', function (e) { if (e.target.classList.contains('pi-score') && Date.now() - scFocus < 600) { e.preventDefault(); e.target.select(); } });
  t.addEventListener('keydown', function (e) {
    if (e.target.classList.contains('pi-score') && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) { e.preventDefault(); bump(e.target, e.key === 'ArrowUp' ? 1 : -1); return; }
    if (e.key !== 'Enter' || e.target.tagName === 'TEXTAREA' || !e.target.dataset.f) return;
    e.preventDefault();   // Enter - į kitą laukelį
    var all = Array.prototype.slice.call(t.querySelectorAll('.pi')).filter(function (x) { return x.offsetParent !== null; }), i = all.indexOf(e.target);
    if (all[i + 1]) all[i + 1].focus();
  });
  // Balai be kablelio: įvedus du skaitmenis - taškas pats (71 → 7.1); 10 lieka 10
  t.addEventListener('input', function (e) {
    if (e.target.classList.contains('pi-score')) { var m = String(e.target.value).match(/^(\d)(\d)$/); if (m && e.target.value !== '10') e.target.value = m[1] + '.' + m[2]; markLow(e.target); }
    if (e.target.tagName === 'TEXTAREA') { e.target.style.height = 'auto'; e.target.style.height = e.target.scrollHeight + 'px'; }
  });
  t.querySelectorAll('textarea.pi').forEach(function (x) { if (x.value) { x.style.height = x.scrollHeight + 'px'; } });
  // Kopijuoti (registracijos ir asmeninės nuorodos)
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var done = function () { var o = b.textContent; b.textContent = '✓'; setTimeout(function () { b.textContent = o; }, 1500); };
      if (navigator.clipboard) navigator.clipboard.writeText(b.dataset.copy).then(done, function () {}); else { var i = document.createElement('input'); i.value = b.dataset.copy; document.body.appendChild(i); i.select(); document.execCommand('copy'); i.remove(); done(); }
    });
  });
  // „⚙ Daugiau“ / #registracija - atidaro skiltį
  var more = document.getElementById('daugiau');
  function openMore() { if (/^#(daugiau|registracija)$/.test(location.hash)) { more.open = true; } }
  openMore(); window.addEventListener('hashchange', openMore);
  document.querySelector('[data-open-more]').addEventListener('click', function () { more.open = true; });
})();
</script>
<?php
page_end();
