<?php
// Federacijos registras: visi laikę egzaminus (iš protokolų „📨 Siųsti protokolą Federacijai“). Kiekvienas žmogus turi nuolatinį numerį
// 26.11.15-1 (data - pirmo egzamino, po brūkšnio - tos dienos eilė), kuris niekada nesikartoja. Paieška, egzaminų istorija, Excel.
require dirname(__DIR__) . '/app/bootstrap.php';
require_once APP_DIR . '/exam_protocol.php';
$me = require_staff();

if (!fed_ready()) {
    page_start(t('Federacijos registras'), ['admin' => true]);
    echo '<div class="panel card"><h1>' . te('Federacijos registras') . '</h1><p class="muted">' . te('Federacijos registras veiks, kai bus atnaujinta duomenų bazė (Šiandien → „Atnaujinkite duomenų bazę“).') . '</p></div>';
    page_end();
    exit;
}

$q = trim((string) get('q'));
$where = $q !== '' ? 'WHERE fp.name LIKE ? OR fp.fed_no LIKE ? OR fp.club LIKE ?' : '';
$args = $q !== '' ? ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%'] : [];
$people = q_all("SELECT fp.*, COUNT(fe.id) AS exams, MAX(fe.exam_date) AS last_exam,
                        (SELECT fe2.kyu FROM federation_exams fe2 WHERE fe2.person_id = fp.id AND fe2.result = 'passed' ORDER BY fe2.exam_date DESC, fe2.id DESC LIMIT 1) AS last_kyu
                   FROM federation_people fp LEFT JOIN federation_exams fe ON fe.person_id = fp.id
                   $where GROUP BY fp.id ORDER BY fp.id DESC LIMIT 500", $args);

if (get('xlsx')) {
    $rows = [[t('Federacijos registras')], [], ['Nr.', t('Vardas Pavardė'), t('Gimimo data'), t('Klubas'), t('Egzaminų'), t('Paskutinis egzaminas'), t('Paskutinis išlaikytas kyu')]];
    foreach ($people as $r) {
        $rows[] = [$r['fed_no'], $r['name'], (string) $r['birth_date'], (string) $r['club'], (int) $r['exams'], (string) $r['last_exam'], (string) $r['last_kyu']];
    }
    xlsx_download('federacijos-registras-' . date('Y-m-d'), $rows, t('Registras'), 2);
}

$open = (int) get('p');
$history = $open ? q_all('SELECT fe.*, e.title FROM federation_exams fe LEFT JOIN events e ON e.id = fe.event_id WHERE fe.person_id = ? ORDER BY fe.exam_date DESC, fe.id DESC', [$open]) : [];

page_start(t('Federacijos registras'), ['admin' => true]);
?>
<div class="page-head">
  <h1 class="styled"><?= te('Federacijos registras') ?></h1>
  <p><?= te('Visi laikę egzaminus. Kiekvienas žmogus turi nuolatinį numerį (pvz. 26.11.15-1): data - pirmo egzamino, po brūkšnio - tos dienos eilė. Numeris niekada nesikartoja.') ?></p>
</div>
<form method="get" class="panel card form inline-fields">
  <label><?= te('Paieška') ?> <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= te('Vardas, numeris ar klubas') ?>"></label>
  <button class="btn btn-ghost" type="submit"><?= te('Ieškoti') ?></button>
  <a class="btn btn-ghost" href="?<?= $q !== '' ? 'q=' . rawurlencode($q) . '&amp;' : '' ?>xlsx=1"><?= te('⬇ Excel') ?></a>
</form>
<div class="panel card">
  <?php if (!$people): ?><p class="muted"><?= te('Registre dar nieko nėra - egzamino protokole spauskite „📨 Siųsti protokolą Federacijai“.') ?></p><?php endif; ?>
  <table class="table cards">
    <thead><tr><th>Nr.</th><th><?= te('Vardas Pavardė') ?></th><th><?= te('Gimimo data') ?></th><th><?= te('Klubas') ?></th><th><?= te('Egzaminų') ?></th><th><?= te('Paskutinis išlaikytas kyu') ?></th></tr></thead>
    <tbody>
      <?php foreach ($people as $r): ?>
        <tr<?= $open === (int) $r['id'] ? ' class="is-open"' : '' ?>>
          <td><b><?= e($r['fed_no']) ?></b></td>
          <td><a href="?p=<?= (int) $r['id'] ?><?= $q !== '' ? '&amp;q=' . rawurlencode($q) : '' ?>#p<?= (int) $r['id'] ?>" id="p<?= (int) $r['id'] ?>"><?= e($r['name']) ?></a></td>
          <td><?= e((string) $r['birth_date']) ?></td>
          <td><?= e((string) $r['club']) ?></td>
          <td><?= (int) $r['exams'] ?><?= $r['last_exam'] ? ' <span class="muted small">(' . e(fmt_date($r['last_exam'])) . ')</span>' : '' ?></td>
          <td><?= e((string) $r['last_kyu']) ?></td>
        </tr>
        <?php if ($open === (int) $r['id'] && $history): ?>
          <tr><td colspan="6"><ul class="list small">
            <?php foreach ($history as $h): $sc = json_decode((string) $h['scores'], true) ?: []; ?>
              <li><b><?= e(fmt_date((string) $h['exam_date'])) ?></b> · <?= e((string) $h['title']) ?> · <?= e((string) $h['kyu']) ?> kyu ·
                <?= $h['result'] === 'passed' ? te('Išlaikė') : ($h['result'] === 'failed' ? te('Neišlaikė') : '—') ?>
                <?php if ($sc): ?><span class="muted"> · <?= e(implode(', ', array_map(function ($k, $v) { return $k . ' ' . ($v === '' ? '–' : $v); }, array_keys($sc), $sc))) ?></span><?php endif; ?>
                · <a href="<?= url('admin/egzamino-protokolas.php?id=' . (int) $h['event_id']) ?>"><?= te('Protokolas') ?></a></li>
            <?php endforeach; ?>
          </ul></td></tr>
        <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
page_end();
