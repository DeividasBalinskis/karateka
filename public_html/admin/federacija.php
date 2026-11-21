<?php
// Federacijos registras: visi laikę egzaminus (iš protokolų „📨 Siųsti protokolą Federacijai“). Kiekvienas žmogus turi nuolatinį numerį
// 26.11.15-1 (data - pirmo egzamino, po brūkšnio - tos dienos eilė), kuris niekada nesikartoja. Paieška, egzaminų istorija, Excel.
require dirname(__DIR__) . '/app/bootstrap.php';
require_once APP_DIR . '/exam_protocol.php';
require_once APP_DIR . '/federation.php';
$me = require_staff();
$tab = in_array(get('skiltis'), ['klubai', 'teisejai'], true) ? get('skiltis') : 'nariai';
$canEdit = is_admin($me);

// #160: klubų, teisėjų ir narių registrai - keičia administratorius
if (is_post() && $canEdit && ltkf_reg_ready()) {
    csrf_check();
    $act = post('action');
    $txt = function (string $k, int $len) { $v = trim((string) post($k)); return $v === '' ? null : mb_substr($v, 0, $len); };
    if ($act === 'club_save') {
        $name = $txt('name', 120);
        if ($name) {
            $id = (int) post('id');
            $vals = [$name, $txt('legal_name', 160), $txt('city', 80), $txt('coach', 120), $txt('email', 120), $txt('phone', 40), $txt('website', 200),
                     (int) post('since') ?: null, isset(LTKF_CLUB_STATUS[post('status')]) ? post('status') : 'member'];
            if ($id) {
                q('UPDATE ltkf_clubs SET name = ?, legal_name = ?, city = ?, coach = ?, email = ?, phone = ?, website = ?, since = ?, status = ? WHERE id = ?', array_merge($vals, [$id]));
            } else {
                $slug = ltkf_slugify($name);
                while (q_value('SELECT 1 FROM ltkf_clubs WHERE slug = ?', [$slug])) {
                    $slug .= '-2';
                }
                q('INSERT INTO ltkf_clubs (name, legal_name, city, coach, email, phone, website, since, status, slug, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 999)', array_merge($vals, [$slug]));
            }
            flash('ok', t('Klubas išsaugotas.'));
        }
        redirect('admin/federacija.php?skiltis=klubai');
    }
    if ($act === 'judge_save') {
        $name = $txt('name', 120);
        if ($name) {
            $id = (int) post('id');
            $until = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) post('valid_until')) ? post('valid_until') : null;
            $vals = [$name, $txt('club', 120), $txt('category', 60), $txt('license_no', 40), $until, $txt('note', 255)];
            $id ? q('UPDATE ltkf_judges SET name = ?, club = ?, category = ?, license_no = ?, valid_until = ?, note = ? WHERE id = ?', array_merge($vals, [$id]))
                : q('INSERT INTO ltkf_judges (name, club, category, license_no, valid_until, note) VALUES (?, ?, ?, ?, ?, ?)', $vals);
            flash('ok', t('Teisėjas išsaugotas.'));
        }
        redirect('admin/federacija.php?skiltis=teisejai');
    }
    if ($act === 'judge_delete') {
        q('DELETE FROM ltkf_judges WHERE id = ?', [(int) post('id')]);
        flash('ok', t('Teisėjas pašalintas iš registro.'));
        redirect('admin/federacija.php?skiltis=teisejai');
    }
    if ($act === 'person_save') {
        $name = $txt('name', 160);
        if ($name) {
            $id = (int) post('id');
            $birth = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) post('birth_date')) ? post('birth_date') : null;
            if ($id) {
                q('UPDATE federation_people SET name = ?, birth_date = ?, club = ?, grade = ? WHERE id = ?', [$name, $birth, $txt('club', 120), $txt('grade', 20), $id]);
            } else {
                $id = fed_person_create(date('Y-m-d'), ['name' => $name, 'birth_date' => $birth, 'club' => $txt('club', 120), 'member_id' => null]);
                q('UPDATE federation_people SET grade = ? WHERE id = ?', [$txt('grade', 20), $id]);
            }
            flash('ok', t('Narys išsaugotas registre.'));
        }
        redirect('admin/federacija.php?p=' . (int) ($id ?? 0) . '#p' . (int) ($id ?? 0));
    }
}

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
<nav class="top-tabs fed-tabs" aria-label="<?= te('Registrai') ?>">
  <a href="<?= url('admin/federacija.php') ?>" class="<?= $tab === 'nariai' ? 'active' : '' ?>"><?= te('👤 Nariai') ?></a>
  <a href="<?= url('admin/federacija.php?skiltis=klubai') ?>" class="<?= $tab === 'klubai' ? 'active' : '' ?>"><?= te('🏛️ Klubai') ?></a>
  <a href="<?= url('admin/federacija.php?skiltis=teisejai') ?>" class="<?= $tab === 'teisejai' ? 'active' : '' ?>"><?= te('⚖️ Teisėjai') ?></a>
</nav>
<?php if ($tab === 'klubai'):   // #160: klubų registras (LTKF svetainė rodo iš čia)
    $clubs = ltkf_clubs(); $editC = (int) get('c'); ?>
  <div class="panel card">
    <p class="small muted" style="margin-top:0;"><?= te('LTKF svetainės „Klubai“ puslapis rodo šį sąrašą. „Narių“ - kiek žmonių iš to klubo yra narių registre.') ?></p>
    <table class="table cards">
      <thead><tr><th><?= te('Klubas') ?></th><th><?= te('Miestas') ?></th><th><?= te('Vyr. treneris') ?></th><th><?= te('Būsena') ?></th><th><?= te('Narių') ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($clubs as $c): ?>
        <tr><td><b><?= e($c['name']) ?></b><?= $c['legal_name'] ? '<br><span class="small muted">' . e($c['legal_name']) . '</span>' : '' ?></td><td><?= e((string) $c['city']) ?></td><td><?= e((string) $c['coach']) ?></td>
          <td><?= e(t(LTKF_CLUB_STATUS[$c['status']] ?? $c['status'])) ?></td><td><?= (int) $c['people'] ?></td>
          <td><?php if ($canEdit): ?><a class="btn btn-ghost btn-sm" href="?skiltis=klubai&amp;c=<?= (int) $c['id'] ?>#forma"><?= te('Keisti') ?></a><?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($canEdit): $c = $editC ? (array_values(array_filter($clubs, function ($x) use ($editC) { return (int) $x['id'] === $editC; }))[0] ?? []) : []; ?>
    <form method="post" class="panel card form" id="forma" style="margin-top:16px;">
      <?= csrf_field() ?><input type="hidden" name="action" value="club_save"><input type="hidden" name="id" value="<?= (int) ($c['id'] ?? 0) ?>">
      <div class="kicker"><?= $c ? te('Keisti klubą') : te('+ Naujas klubas') ?></div>
      <div class="form-row">
        <label><?= te('Pavadinimas') ?> <input name="name" required maxlength="120" value="<?= e((string) ($c['name'] ?? '')) ?>"></label>
        <label><?= te('Juridinis pavadinimas') ?> <input name="legal_name" maxlength="160" value="<?= e((string) ($c['legal_name'] ?? '')) ?>"></label>
      </div>
      <div class="form-row">
        <label><?= te('Miestas') ?> <input name="city" maxlength="80" value="<?= e((string) ($c['city'] ?? '')) ?>"></label>
        <label><?= te('Vyr. treneris') ?> <input name="coach" maxlength="120" value="<?= e((string) ($c['coach'] ?? '')) ?>"></label>
        <label><?= te('Veikia nuo (metai)') ?> <input name="since" type="number" min="1950" max="2100" value="<?= e((string) ($c['since'] ?? '')) ?>"></label>
      </div>
      <div class="form-row">
        <label><?= te('El. paštas') ?> <input name="email" type="email" maxlength="120" value="<?= e((string) ($c['email'] ?? '')) ?>"></label>
        <label><?= te('Telefonas') ?> <input name="phone" maxlength="40" value="<?= e((string) ($c['phone'] ?? '')) ?>"></label>
        <label><?= te('Svetainė') ?> <input name="website" maxlength="200" value="<?= e((string) ($c['website'] ?? '')) ?>"></label>
        <label><?= te('Būsena') ?> <select name="status"><?php foreach (LTKF_CLUB_STATUS as $k => $l): ?><option value="<?= $k ?>"<?= ($c['status'] ?? 'member') === $k ? ' selected' : '' ?>><?= e(t($l)) ?></option><?php endforeach; ?></select></label>
      </div>
      <button class="btn btn-primary" type="submit"><?= te('Išsaugoti') ?></button>
      <?php if ($c): ?><a class="btn btn-ghost" href="?skiltis=klubai"><?= te('Atšaukti') ?></a><?php endif; ?>
    </form>
  <?php endif; ?>
<?php page_end(); exit; endif; ?>
<?php if ($tab === 'teisejai'):   // #160: teisėjų registras (kitų klubų teisėjai; Karateka teisėjai - iš narių su žyme „teisėjas“)
    $judges = ltkf_judges(); $editJ = (int) get('j'); ?>
  <div class="panel card">
    <p class="small muted" style="margin-top:0;"><?= te('LTKF svetainės „Teisėjai“ puslapis rodo šiuos teisėjus kartu su Karateka teisėjais (nariai su žyme „teisėjas“ ir išlaikytu teisėjų testu).') ?></p>
    <?php if (!$judges): ?><p class="muted"><?= te('Registre dar nėra teisėjų.') ?></p><?php endif; ?>
    <table class="table cards">
      <thead><tr><th><?= te('Vardas, pavardė') ?></th><th><?= te('Klubas') ?></th><th><?= te('Kategorija') ?></th><th><?= te('Licencijos Nr.') ?></th><th><?= te('Galioja iki') ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($judges as $j): $exp = $j['valid_until'] && $j['valid_until'] < date('Y-m-d'); ?>
        <tr><td><b><?= e($j['name']) ?></b><?= $j['note'] ? '<br><span class="small muted">' . e($j['note']) . '</span>' : '' ?></td><td><?= e((string) $j['club']) ?></td><td><?= e((string) $j['category']) ?></td>
          <td><?= e((string) $j['license_no']) ?></td><td<?= $exp ? ' class="danger-text"' : '' ?>><?= $j['valid_until'] ? e(fmt_date($j['valid_until'], true)) . ($exp ? ' · ' . te('baigėsi') : '') : '—' ?></td>
          <td><?php if ($canEdit): ?><a class="btn btn-ghost btn-sm" href="?skiltis=teisejai&amp;j=<?= (int) $j['id'] ?>#forma"><?= te('Keisti') ?></a><?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($canEdit): $j = $editJ ? (array_values(array_filter($judges, function ($x) use ($editJ) { return (int) $x['id'] === $editJ; }))[0] ?? []) : []; $clubNames = array_column(ltkf_clubs(), 'name'); ?>
    <form method="post" class="panel card form" id="forma" style="margin-top:16px;">
      <?= csrf_field() ?><input type="hidden" name="action" value="judge_save"><input type="hidden" name="id" value="<?= (int) ($j['id'] ?? 0) ?>">
      <div class="kicker"><?= $j ? te('Keisti teisėją') : te('+ Naujas teisėjas') ?></div>
      <div class="form-row">
        <label><?= te('Vardas, pavardė') ?> <input name="name" required maxlength="120" value="<?= e((string) ($j['name'] ?? '')) ?>"></label>
        <label><?= te('Klubas') ?> <input name="club" maxlength="120" list="fedClubs" value="<?= e((string) ($j['club'] ?? '')) ?>"></label>
        <datalist id="fedClubs"><?php foreach ($clubNames as $cn): ?><option value="<?= e($cn) ?>"><?php endforeach; ?></datalist>
      </div>
      <div class="form-row">
        <label><?= te('Kategorija') ?> <input name="category" maxlength="60" value="<?= e((string) ($j['category'] ?? '')) ?>" placeholder="<?= te('pvz. nacionalinė') ?>"></label>
        <label><?= te('Licencijos Nr.') ?> <input name="license_no" maxlength="40" value="<?= e((string) ($j['license_no'] ?? '')) ?>"></label>
        <label><?= te('Galioja iki') ?> <input name="valid_until" type="date" value="<?= e((string) ($j['valid_until'] ?? '')) ?>"></label>
      </div>
      <label><?= te('Pastaba') ?> <input name="note" maxlength="255" value="<?= e((string) ($j['note'] ?? '')) ?>"></label>
      <button class="btn btn-primary" type="submit"><?= te('Išsaugoti') ?></button>
      <?php if ($j): ?><a class="btn btn-ghost" href="?skiltis=teisejai"><?= te('Atšaukti') ?></a>
        <button class="btn btn-danger" type="submit" name="action" value="judge_delete" formnovalidate onclick="return confirm('<?= e(t('Pašalinti teisėją iš registro?')) ?>')"><?= te('Pašalinti') ?></button><?php endif; ?>
    </form>
  <?php endif; ?>
<?php page_end(); exit; endif; ?>
<form method="get" class="panel card form inline-fields">
  <label><?= te('Paieška') ?> <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= te('Vardas, numeris ar klubas') ?>"></label>
  <button class="btn btn-ghost" type="submit"><?= te('Ieškoti') ?></button>
  <a class="btn btn-ghost" href="?<?= $q !== '' ? 'q=' . rawurlencode($q) . '&amp;' : '' ?>xlsx=1"><?= icon('download') ?> <span>Excel</span></a>
</form>
<?php if ($canEdit && ltkf_reg_ready()): ?>
  <details class="panel card fed-add"><summary class="btn btn-ghost btn-sm"><?= te('+ Naujas narys registre') ?></summary>
    <form method="post" class="form inline-fields" style="margin-top:10px;"><?= csrf_field() ?><input type="hidden" name="action" value="person_save">
      <label><?= te('Vardas Pavardė') ?> <input name="name" required maxlength="160"></label>
      <label><?= te('Gimimo data') ?> <input name="birth_date" type="date"></label>
      <label><?= te('Klubas') ?> <input name="club" list="fedClubsP" maxlength="120"></label>
      <label><?= te('Laipsnis') ?> <input name="grade" maxlength="20" placeholder="<?= te('pvz. 3 kyu, 1 dan') ?>"></label>
      <button class="btn btn-primary btn-sm" type="submit"><?= te('Pridėti') ?></button>
    </form>
    <p class="small muted"><?= te('Egzaminus laikę žmonės į registrą patenka patys iš egzamino protokolo. Čia - kitų klubų nariai ar senesni laipsniai.') ?></p>
  </details>
<?php endif; ?>
<datalist id="fedClubsP"><?php foreach (ltkf_clubs() as $cc): ?><option value="<?= e($cc['name']) ?>"><?php endforeach; ?></datalist>
<div class="panel card">
  <?php if (!$people): ?><p class="muted"><?= te('Registre dar nieko nėra - egzamino protokole spauskite „📨 Siųsti protokolą Federacijai“.') ?></p><?php endif; ?>
  <table class="table cards">
    <thead><tr><th>Nr.</th><th><?= te('Vardas Pavardė') ?></th><th><?= te('Gimimo data') ?></th><th><?= te('Klubas') ?></th><th><?= te('Egzaminų') ?></th><th><?= te('Laipsnis') ?></th></tr></thead>
    <tbody>
      <?php foreach ($people as $r): ?>
        <tr<?= $open === (int) $r['id'] ? ' class="is-open"' : '' ?>>
          <td><b><?= e($r['fed_no']) ?></b></td>
          <td><a href="?p=<?= (int) $r['id'] ?><?= $q !== '' ? '&amp;q=' . rawurlencode($q) : '' ?>#p<?= (int) $r['id'] ?>" id="p<?= (int) $r['id'] ?>"><?= e($r['name']) ?></a></td>
          <td><?= e((string) $r['birth_date']) ?></td>
          <td><?= e((string) $r['club']) ?></td>
          <td><?= (int) $r['exams'] ?><?= $r['last_exam'] ? ' <span class="muted small">(' . e(fmt_date($r['last_exam'])) . ')</span>' : '' ?></td>
          <td><?= e((string) ($r['grade'] ?? '') ?: ($r['last_kyu'] ? $r['last_kyu'] . ' kyu' : '')) ?></td>
        </tr>
        <?php if ($open === (int) $r['id'] && $canEdit && ltkf_reg_ready()): ?>
          <tr><td colspan="6"><form method="post" class="form inline-fields"><?= csrf_field() ?><input type="hidden" name="action" value="person_save"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <label><?= te('Vardas Pavardė') ?> <input name="name" required value="<?= e($r['name']) ?>"></label>
            <label><?= te('Gimimo data') ?> <input name="birth_date" type="date" value="<?= e((string) $r['birth_date']) ?>"></label>
            <label><?= te('Klubas') ?> <input name="club" list="fedClubsP" value="<?= e((string) $r['club']) ?>"></label>
            <label><?= te('Laipsnis') ?> <input name="grade" maxlength="20" placeholder="<?= te('pvz. 3 kyu, 1 dan') ?>" value="<?= e((string) ($r['grade'] ?? '')) ?>"></label>
            <button class="btn btn-primary btn-sm" type="submit"><?= te('Išsaugoti') ?></button></form></td></tr>
        <?php endif; ?>
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
