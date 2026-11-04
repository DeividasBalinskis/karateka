<?php
// Narių sąrašas ir redagavimas
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

$id = (int) (get('id') ?: post('id'));

/** Paskyra pagal el. paštą susiejimui su nariu (null - tokios nėra) */
function account_by_email(string $email): ?array
{
    $acc = q_one('SELECT * FROM accounts WHERE email = ?', [normalize_email($email)]);
    return $acc ?: null;
}

// ---------- Naujas narys (treneris prideda pats, iškart aktyvus su grupe) ----------
if (get('new') !== '' || post('action') === 'create') {
    $errors = [];
    // Pamoka ar užduotis nariui (atsiranda jo paskyroje „Svarbu“)
    if (is_post() && post('action') === 'send_lesson') {
        csrf_check();
        $lessonId = (int) post('lesson_id');
        if ($m['status'] !== 'active' || !q_value('SELECT 1 FROM lessons WHERE id = ?', [$lessonId])) {
            flash('err', t('Pasirinkite pamoką.'));
        } else {
            send_coach_note($m, $me, post('body'), $lessonId, !empty($_POST['is_task']), !empty($_POST['email']));
            flash('ok', (t('Pamoka išsiųsta -') . ' ') . $m['first_name'] . (' ' . t('ją matys paskyroje.')));
        }
        redirect('admin/nariai.php?id=' . $id . '#pamokos');
    }
    // Egzamino programos punktas: patvirtinti (skiriami XP) arba grąžinti
    if (is_post() && post('action') === 'skill') {
        csrf_check();
        $reqId = (int) post('req_id');
        if (post('op') === 'approve') {
            flash('ok', approve_skill($id, $reqId, (int) $me['id']) ? t('Patvirtinta, XP skirti.') : t('Jau buvo patvirtinta.'));
        } elseif (post('op') === 'reject') {
            reject_skill($id, $reqId);
            flash('ok', t('Grąžinta - narys galės pažymėti dar kartą.'));
        }
        redirect('admin/nariai.php?id=' . $id . '#programa');
    }
    if (is_post()) {
        csrf_check();
        $first = post('first_name');
        $last = post('last_name');
        $birth = date_from_input($_POST['birth_date'] ?? '');
        $group = (int) post('group_id');
        $email = post('link_email');
        $acc = $email !== '' ? account_by_email($email) : null;
        if ($first === '' || $last === '' || !valid_birth_date($birth)) {
            $errors[] = t('Įveskite vardą, pavardę ir gimimo datą.');
        } elseif (!$group) {
            $errors[] = t('Pasirinkite grupę.');
        } elseif ($email !== '' && !$acc) {
            $errors[] = t('Paskyros su tokiu el. paštu nėra. Palikite tuščią - susieti galėsite vėliau nario puslapyje.');
        } else {
            $mid = create_member($first, $last, $birth, !empty($_POST['photo_consent']), belt_from_post('belt_level'));
            q('UPDATE members SET group_id = ?, status = "active" WHERE id = ?', [$group, $mid]);
            if ($acc) {
                link_member((int) $acc['id'], $mid, post('relation') === 'self' ? 'self' : 'parent');
            }
            flash('ok', "Pridėtas (-a): $first $last");
            redirect('admin/nariai.php?id=' . $mid);
        }
    }
    page_start(t('Naujas narys'), ['admin' => true]);
    ?>
    <a class="small" href="<?= url('admin/nariai.php') ?>"><?= te('← Visi nariai') ?></a>
    <h1 class="styled" style="margin-top:8px;"><?= te('Naujas narys') ?></h1>
    <?= form_errors($errors) ?>
    <form method="post" class="panel card form" style="max-width:720px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="form-row">
        <label><?= te('Vardas') ?> <input type="text" name="first_name" value="<?= e(post('first_name')) ?>" required></label>
        <label><?= te('Pavardė') ?> <input type="text" name="last_name" value="<?= e(post('last_name')) ?>" required></label>
      </div>
      <label><?= te('Gimimo data') ?> <?= date_parts_field('birth_date', $_POST['birth_date'] ?? null) ?></label>
      <div class="form-row">
        <label><?= te('Grupė') ?> <select name="group_id" required><?= group_options((int) post('group_id') ?: null) ?></select></label>
        <label><?= te('Diržas') ?> <select name="belt_level"><?= belt_options(belt_from_post('belt_level'), t('— nežinau / dar neturi —')) ?></select></label>
      </div>
      <label class="check"><input type="checkbox" name="photo_consent" value="1" <?= !empty($_POST['photo_consent']) ? 'checked' : '' ?>>
        <span><?= te('Tėvai (ar pats narys) sutiko, kad klubas skelbtų nuotraukas') ?></span></label>
      <hr class="divider">
      <div class="form-row">
        <label><?= te('Susieti su paskyra') ?> <span class="hint"><?= te('nebūtina - tėvų ar paties nario el. paštas, jei jau užsiregistravę') ?></span>
          <input type="email" name="link_email" value="<?= e(post('link_email')) ?>"></label>
        <label><?= te('Kas tai') ?> <select name="relation"><option value="parent"><?= te('tėvų paskyra') ?></option><option value="self" <?= post('relation') === 'self' ? 'selected' : '' ?>>paties nario paskyra</option></select></label>
      </div>
      <p class="hint"><?= te('Jei tėvai dar neturi paskyros - palikite tuščią. Kai užsiregistruos, susiekite nario puslapyje, kad tas pats vaikas nebūtų sukurtas du kartus.') ?></p>
      <div><button class="btn btn-primary" type="submit"><?= te('Pridėti narį') ?></button></div>
    </form>
    <?php
    page_end();
    exit;
}

if ($id) {
    $m = q_one('SELECT * FROM members WHERE id = ?', [$id]);
    if (!$m) {
        not_found();
    }
    $errors = [];
    // Ištrinti narį visam laikui (tik adminas; pvz. paprašius ar praėjus 1 m. po narystės pabaigos - žr. privatumo politiką).
    // Kartu dingsta jo lankomumas, taškai, pastabos, žinutės; paskyros, kuriose neliko narių (ne trenerių), ištrinamos irgi.
    if (is_post() && post('action') === 'delete_member') {
        csrf_check();
        if (!is_admin($me)) {
            forbidden();
        }
        $accIds = array_map('intval', q('SELECT account_id FROM account_members WHERE member_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN));
        q('DELETE FROM members WHERE id = ?', [$id]);
        $gone = [];
        foreach ($accIds as $aid) {
            $acc = q_one('SELECT * FROM accounts a WHERE a.id = ? AND a.role = "member" AND NOT EXISTS (SELECT 1 FROM account_members am WHERE am.account_id = a.id)', [$aid]);
            if ($acc && $aid !== (int) $me['id']) {
                q('DELETE FROM accounts WHERE id = ?', [$aid]);
                $gone[] = $acc['email'];
            }
        }
        flash('ok', (t('Ištrinta:') . ' ') . $m['first_name'] . ' ' . $m['last_name'] . ($gone ? ' ir paskyra ' . implode(', ', $gone) : '') . '.');
        redirect('admin/nariai.php');
    }
    // Susieti esamą narį su tėvų / paties nario paskyra
    if (is_post() && post('action') === 'link_account') {
        csrf_check();
        $acc = account_by_email(post('link_email'));
        if ($acc) {
            link_member((int) $acc['id'], $id, post('relation') === 'self' ? 'self' : 'parent');
            flash('ok', "Susieta su paskyra: {$acc['email']}");
        } else {
            flash('err', t('Paskyros su tokiu el. paštu nėra.'));
        }
        redirect('admin/nariai.php?id=' . $id);
    }
    if (is_post() && in_array(post('action'), ['add_award', 'delete_award'], true)) {
        csrf_check();
        if (post('action') === 'add_award') {
            $cat = q_one('SELECT * FROM point_categories WHERE id = ? AND is_active = 1', [(int) post('category_id')]);
            $date = post('awarded_on');
            if ($cat && DateTime::createFromFormat('!Y-m-d', $date)) {
                award_points($id, $cat, $date, null, post('note'), (int) $me['id']);
                flash('ok', "Skirta +{$cat['points']} tšk.: {$cat['name']}");
            } else {
                flash('err', t('Pasirinkite kategoriją ir datą.'));
            }
        } else {
            q('DELETE FROM point_awards WHERE id = ? AND member_id = ?', [(int) post('award_id'), $id]);
            flash('ok', t('Taškai pašalinti.'));
        }
        redirect('admin/nariai.php?id=' . $id . '#taskai');
    }
    // Pamoka ar užduotis nariui (atsiranda jo paskyroje „Svarbu“)
    if (is_post() && post('action') === 'send_lesson') {
        csrf_check();
        $lessonId = (int) post('lesson_id');
        if ($m['status'] !== 'active' || !q_value('SELECT 1 FROM lessons WHERE id = ?', [$lessonId])) {
            flash('err', t('Pasirinkite pamoką.'));
        } else {
            send_coach_note($m, $me, post('body'), $lessonId, !empty($_POST['is_task']), !empty($_POST['email']));
            flash('ok', (t('Pamoka išsiųsta -') . ' ') . $m['first_name'] . (' ' . t('ją matys paskyroje.')));
        }
        redirect('admin/nariai.php?id=' . $id . '#pamokos');
    }
    // Egzamino programos punktas: patvirtinti (skiriami XP) arba grąžinti
    if (is_post() && post('action') === 'skill') {
        csrf_check();
        $reqId = (int) post('req_id');
        if (post('op') === 'approve') {
            flash('ok', approve_skill($id, $reqId, (int) $me['id']) ? t('Patvirtinta, XP skirti.') : t('Jau buvo patvirtinta.'));
        } elseif (post('op') === 'reject') {
            reject_skill($id, $reqId);
            flash('ok', t('Grąžinta - narys galės pažymėti dar kartą.'));
        }
        redirect('admin/nariai.php?id=' . $id . '#programa');
    }
    if (is_post()) {
        csrf_check();
        $first = post('first_name');
        $last = post('last_name');
        $birth = date_from_input($_POST['birth_date'] ?? '');
        $status = post('status');
        $group = (int) post('group_id') ?: null;
        if ($first === '' || $last === '' || !valid_birth_date($birth)) {
            $errors[] = t('Įveskite vardą, pavardę ir gimimo datą.');
        } elseif (!in_array($status, ['pending', 'active', 'inactive'], true)) {
            $errors[] = t('Netinkama būsena.');
        } elseif ($status === 'active' && !$group) {
            $errors[] = t('Aktyviam nariui reikia grupės.');
        } else {
            $gender = in_array(post('gender'), ['M', 'F'], true) ? post('gender') : null;
            q('UPDATE members SET first_name = ?, last_name = ?, birth_date = ?, gender = ?, group_id = ?, status = ?, photo_consent = ?, belt_level = ? WHERE id = ?',
                [$first, $last, $birth, $gender, $group, $status, !empty($_POST['photo_consent']) ? 1 : 0, belt_from_post('belt_level'), $id]);
            set_member_tags($id, (array) ($_POST['tags'] ?? []));
            flash('ok', t('Išsaugota.'));
            redirect('admin/nariai.php?id=' . $id);
        }
        $m = array_merge($m, ['first_name' => $first, 'last_name' => $last, 'birth_date' => $birth, 'status' => $status, 'group_id' => $group, 'belt_level' => belt_from_post('belt_level')]);
    }
    $accounts = q_all('SELECT a.*, am.relation FROM account_members am JOIN accounts a ON a.id = am.account_id WHERE am.member_id = ?', [$id]);

    page_start($m['first_name'] . ' ' . $m['last_name'], ['admin' => true]);
    ?>
    <a class="small" href="<?= url('admin/nariai.php') ?>"><?= te('← Visi nariai') ?></a>
    <h1 class="styled" style="margin-top:8px;"><?= e($m['first_name'] . ' ' . $m['last_name']) ?></h1>
    <?= form_errors($errors) ?>
    <div class="grid-2">
      <form method="post" class="panel card form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-row">
          <label><?= te('Vardas') ?> <input type="text" name="first_name" value="<?= e($m['first_name']) ?>" required></label>
          <label><?= te('Pavardė') ?> <input type="text" name="last_name" value="<?= e($m['last_name']) ?>" required></label>
        </div>
        <label><?= te('Gimimo data') ?> <span class="hint"><?= age_on($m['birth_date']) ?> m.</span>
          <?= date_parts_field('birth_date', $m['birth_date']) ?></label>
        <label><?= te('Grupė') ?> <select name="group_id"><?= group_options($m['group_id'] ? (int) $m['group_id'] : null) ?></select></label>
        <div class="form-row">
          <label><?= te('Diržas') ?> <select name="belt_level"><?= belt_options($m['belt_level'] !== null ? (int) $m['belt_level'] : null) ?></select></label>
          <label><?= te('Lytis') ?> <span class="hint"><?= te('varžybų kategorijoms') ?></span>
            <select name="gender">
              <?php foreach (['' => t('— nenurodyta —'), 'M' => t('Vyras / berniukas'), 'F' => t('Moteris / mergaitė')] as $k => $label): ?>
                <option value="<?= $k ?>" <?= ($m['gender'] ?? '') === $k ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select></label>
        </div>
        <label><?= te('Būsena') ?>
          <select name="status">
            <?php foreach (['active' => t('Aktyvus'), 'pending' => t('Laukia patvirtinimo'), 'inactive' => t('Neaktyvus')] as $k => $label): ?>
              <option value="<?= $k ?>" <?= $m['status'] === $k ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="check"><input type="checkbox" name="photo_consent" value="1" <?= $m['photo_consent'] ? 'checked' : '' ?>>
          <span><?= te('Sutikimas skelbti nuotraukas') ?></span></label>
        <fieldset class="tags-field">
          <legend><?= te('Žymos') ?> <span class="hint"><?= te('Karateka - visi nariai; žymos atveria papildomą medžiagą ir testus') ?></span></legend>
          <?php $mTags = member_tags($id); foreach (array_map('t', MEMBER_TAGS) as $k => $label): ?>
            <?php $qt = in_array($k, $mTags, true) ? tag_qualification_text($id, $k) : ''; ?>
            <label class="check"><input type="checkbox" name="tags[]" value="<?= $k ?>" <?= in_array($k, $mTags, true) ? 'checked' : '' ?>><span><?= e($label) ?><?= $qt ? ' <span class="small ' . (strpos($qt, '✓') === 0 ? 'test-ok' : 'muted') . '">' . e($qt) . '</span>' : '' ?></span></label>
          <?php endforeach; ?>
          <p class="hint"><?= te('Treneris - tai paskyros rolė: Treneriams → Paskyros.') ?></p>
        </fieldset>
        <div><button class="btn btn-primary" type="submit"><?= te('Išsaugoti') ?></button></div>
      </form>
      <div class="panel card">
        <h2><?= te('Paskyros') ?></h2>
        <?php if (!$accounts): ?><p class="muted small"><?= te('Nesusieta su jokia paskyra.') ?></p><?php endif; ?>
        <ul class="list small">
          <?php foreach ($accounts as $acc): ?>
            <li>
              <strong><?= e($acc['first_name'] . ' ' . $acc['last_name']) ?></strong>
              <span class="badge"><?= $acc['relation'] === 'self' ? 'pats' : t('tėvai') ?></span>
              <div class="muted"><?= e($acc['email']) ?><?= $acc['phone'] ? ' · <a href="tel:' . e($acc['phone']) . '">' . e($acc['phone']) . '</a>' : '' ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if ($m['parent_consent_at']): ?><p class="small muted" style="margin-top:10px;"><?= te('Tėvų sutikimas:') ?> <?= e($m['parent_consent_at']) ?></p><?php endif; ?>
        <details style="margin-top:12px;">
          <summary class="summary-link"><?= te('+ Susieti su paskyra') ?></summary>
          <form method="post" class="form" style="margin-top:10px;">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="action" value="link_account">
            <label><?= te('Tėvų ar paties nario el. paštas') ?> <input type="email" name="link_email" required></label>
            <label><?= te('Kas tai') ?> <select name="relation"><option value="parent"><?= te('tėvų paskyra') ?></option><option value="self">paties nario paskyra</option></select></label>
            <div><button class="btn btn-ghost btn-sm" type="submit"><?= te('Susieti') ?></button></div>
          </form>
        </details>
      </div>
    </div>

    <?php [$from, $to, $seasonLabel] = season_bounds(); $history = member_history($id); ?>
    <div class="grid-2" id="taskai" style="margin-top:20px;">
      <form method="post" class="panel card form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="action" value="add_award">
        <h2><?= te('Skirti taškų') ?></h2>
        <p class="hint"><?= te('Egzaminų ir varžybų taškus patogiau skirti per Renginiai → Rezultatai.') ?></p>
        <label><?= te('Už ką') ?>
          <select name="category_id" required>
            <option value=""><?= te('— pasirinkite —') ?></option>
            <?php foreach (q_all('SELECT * FROM point_categories WHERE is_active = 1 ORDER BY sort_order, id') as $c): ?>
              <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?> (+<?= (int) $c['points'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </label>
        <div class="form-row">
          <label><?= te('Data') ?> <input type="date" name="awarded_on" value="<?= date('Y-m-d') ?>" required></label>
          <label><?= te('Pastaba') ?> <span class="hint"><?= te('nebūtina') ?></span><input type="text" name="note" maxlength="190" placeholder="<?= te('pvz. vedė antradienio treniruotę') ?>"></label>
        </div>
        <div><button class="btn btn-primary" type="submit"><?= te('Skirti') ?></button></div>
      </form>
      <div class="panel card">
        <h2><?= te('Lankomumas') ?></h2>
        <?= render_attendance($id) ?>
        <hr class="divider">
        <h2><?= te('Taškai ir lygis') ?></h2>
        <?= render_xp_card($m) ?>
        <p class="small muted"><?= e($seasonLabel) ?>: <strong><?= member_points_total($id, $from, $to) ?></strong> <?= te('· iš viso (XP):') ?> <strong><?= member_points_total($id) ?></strong></p>
        <?php if (!$history): ?><p class="muted small" style="margin-top:8px;"><?= te('Taškų dar nėra.') ?></p><?php endif; ?>
        <ul class="list small">
          <?php foreach ($history as $h): ?>
            <li class="row between">
              <span>+<?= (int) $h['points'] ?> · <?= e($h['category_name']) ?>
                <div class="muted"><?= e(fmt_date($h['awarded_on'], true)) ?><?= $h['event_title'] ? ' · ' . e($h['event_title']) : '' ?><?= $h['note'] ? ' · ' . e($h['note']) : '' ?></div>
              </span>
              <form method="post" class="inline-form" onsubmit="return confirm('Pašalinti šiuos taškus?')">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="delete_award"><input type="hidden" name="award_id" value="<?= (int) $h['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit" aria-label="<?= te('Pašalinti') ?>">✕</button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <?php
    // Pamokos ir pastabos nariui; kito diržo programa su patvirtinimais
    $notes = member_notes($id, 12);
    $nextBelt = next_belt($m['belt_level'] !== null ? (int) $m['belt_level'] : null);
    $reqs = $nextBelt ? belt_requirements($nextBelt) : [];
    $progress = requirement_done_ids($id);
    $lessons = q_all('SELECT id, title, belt_level FROM lessons WHERE is_published = 1 ORDER BY belt_level IS NULL, belt_level, title');
    ?>
    <div class="grid-2" style="margin-top:20px;">
      <div class="panel card" id="pamokos">
        <h2><?= te('Pamokos ir pastabos') ?></h2>
        <?php if ($m['status'] === 'active'): ?>
          <form method="post" class="form send-lesson">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="send_lesson">
            <label><?= te('Pamoka') ?> <select name="lesson_id" required><option value=""><?= te('— pasirinkite —') ?></option>
              <?php foreach ($lessons as $l): ?><option value="<?= (int) $l['id'] ?>" <?= $nextBelt && (int) $l['belt_level'] === $nextBelt ? 'class="next"' : '' ?>><?= e(($l['belt_level'] ? BELTS[(int) $l['belt_level']][0] . ' · ' : '') . $l['title']) ?></option><?php endforeach; ?>
            </select></label>
            <label><?= te('Komentaras') ?> <span class="hint"><?= te('nebūtina') ?></span><input type="text" name="body" maxlength="500" placeholder="<?= te('pvz. Pasikartok prieš egzaminą') ?>"></label>
            <div class="row" style="gap:6px 16px;">
              <label class="check"><input type="checkbox" name="is_task" value="1"><span><?= te('Užduotis („Atlikau“)') ?></span></label>
              <label class="check"><input type="checkbox" name="email" value="1"><span><?= te('ir el. laiškas') ?></span></label>
              <button class="btn btn-primary btn-sm" type="submit" style="margin-left:auto;"><?= te('📨 Siųsti') ?></button>
            </div>
          </form>
        <?php endif; ?>
        <?php if (!$notes): ?><p class="muted small"><?= te('Pastabų ir atsiųstų pamokų dar nėra.') ?></p><?php endif; ?>
        <ul class="list small">
          <?php foreach ($notes as $n): ?>
            <li>
              <?= $n['lesson_title'] ? '📘 <a href="' . e(url('pamokos.php?id=' . (int) $n['lesson_id'])) . '">' . e($n['lesson_title']) . '</a> · ' : '' ?><?= e(mb_strimwidth($n['body'], 0, 110, '…')) ?>
              <div class="muted"><?= e(fmt_date($n['note_date'])) ?><?= $n['author'] ? ' · ' . e($n['author']) : '' ?> ·
                <?= $n['is_task'] ? ($n['done_at'] ? ('<span class="test-ok">' . t('✓ atlikta') . '</span>') : t('užduotis neatlikta')) : ($n['read_at'] ? t('peržiūrėta') : t('dar neperžiūrėta')) ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="small" style="margin-top:8px;"><button type="button" class="linklike" style="color:var(--accent); text-decoration:underline;" data-open-chat data-member="<?= $id ?>"><?= te('💬 Pokalbis su nariu') ?></button></p>
      </div>
      <div class="panel card" id="programa">
        <h2><?= te('Egzamino programa') ?><?= $nextBelt ? ' · ' . e(belt_label($nextBelt)) : '' ?></h2>
        <?php if (!$reqs): ?><p class="muted small"><?= $nextBelt ? t('Šio diržo programos dar nėra.') : t('Aukščiausias diržas.') ?> <a href="<?= url('admin/programa.php' . ($nextBelt ? '?dirzas=' . $nextBelt : '')) ?>"><?= te('Egzaminų programa →') ?></a></p><?php endif; ?>
        <?php foreach ($reqs as $section => $items): ?>
          <h3 class="req-section"><?= e(t(REQ_SECTIONS[$section])) ?></h3>
          <ul class="list small req-admin-list">
            <?php foreach ($items as $r): $st = $progress[(int) $r['id']] ?? null; ?>
              <li class="row between">
                <span><?= $st === 'approved' ? '★' : ($st === 'pending' ? '⏳' : '☆') ?> <?= e($r['title']) ?> <span class="xp-chip<?= $st === 'approved' ? ' earned' : '' ?>">+<?= (int) $r['xp'] ?> XP</span></span>
                <?php if ($st !== 'approved'): ?>
                  <form method="post" class="inline-form">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="skill"><input type="hidden" name="req_id" value="<?= (int) $r['id'] ?>">
                    <button class="btn btn-sm <?= $st === 'pending' ? 'btn-primary' : 'btn-ghost' ?>" name="op" value="approve" title="<?= $st === 'pending' ? t('Narys pažymėjo „Moku“') : t('Įskaityti, nors narys nepažymėjo') ?>">✓ <?= $st === 'pending' ? t('Patvirtinti') : t('Įskaityti') ?></button>
                    <?php if ($st === 'pending'): ?><button class="btn btn-ghost btn-sm" name="op" value="reject"><?= te('Dar ne') ?></button><?php endif; ?>
                  </form>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if (is_admin($me)): ?>
      <details class="panel card danger-zone">
        <summary><?= te('Ištrinti narį') ?></summary>
        <p class="small"><?= te('Narys ištrinamas visam laikui su lankomumu, taškais, pastabomis ir žinutėmis. Tėvų ar paties nario paskyra, kurioje neliks narių, ištrinama irgi.
          Jei narys tik nebetreniruoja - geriau „Būsena: Neaktyvus“ (privatumo politikoje: ištriname praėjus 1 m.).') ?></p>
        <form method="post" onsubmit="return confirm('Ištrinti <?= e(addslashes($m['first_name'] . ' ' . $m['last_name'])) ?> visam laikui?')">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
          <button class="btn btn-danger btn-sm" name="action" value="delete_member"><?= te('Ištrinti visam laikui') ?></button>
        </form>
      </details>
    <?php endif; ?>
    <?php
    page_end();
    exit;
}

// ---------- Sąrašas ----------
$groupFilter = (int) get('g');
$search = get('q');
$status = get('s') ?: 'active';

$sql = 'SELECT m.*, g.name AS group_name FROM members m LEFT JOIN training_groups g ON g.id = m.group_id WHERE 1';
$params = [];
if ($status !== 'all') {
    $sql .= ' AND m.status = ?';
    $params[] = $status;
}
if ($groupFilter) {
    $sql .= ' AND m.group_id = ?';
    $params[] = $groupFilter;
}
if ($search !== '') {
    $sql .= ' AND CONCAT(m.first_name, " ", m.last_name) LIKE ?';
    $params[] = '%' . $search . '%';
}
$rows = q_all($sql . ' ORDER BY g.sort_order, m.last_name, m.first_name LIMIT 500', $params);
$allTags = [];
foreach (q_all('SELECT * FROM member_tags') as $t) {
    $allTags[(int) $t['member_id']][] = $t['tag'];
}

page_start(t('Nariai'), ['admin' => true]);
?>
<div class="page-head row between">
  <h1 class="styled"><?= te('Nariai') ?></h1>
  <a class="btn btn-primary" href="?new=1"><?= te('+ Naujas narys') ?></a>
</div>
<form method="get" class="panel card form filters">
  <label><?= te('Paieška') ?> <input type="search" name="q" value="<?= e($search) ?>" placeholder="<?= te('Vardas ar pavardė') ?>"></label>
  <label><?= te('Grupė') ?> <select name="g"><option value=""><?= te('Visos grupės') ?></option><?= str_replace('<option value="">— pasirinkite grupę —</option>', '', group_options($groupFilter ?: null)) ?></select></label>
  <label><?= te('Būsena') ?>
    <select name="s">
      <?php foreach (['active' => t('Aktyvūs'), 'pending' => t('Laukia'), 'inactive' => t('Neaktyvūs'), 'all' => t('Visi')] as $k => $label): ?>
        <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="btn btn-ghost" type="submit"><?= te('Rodyti') ?></button>
</form>

<div class="panel card">
  <p class="muted small" style="margin-bottom:8px;"><?= te('Rasta:') ?> <?= count($rows) ?></p>
  <table class="table cards">
    <thead><tr><th><?= te('Vardas, pavardė') ?></th><th><?= te('Amžius') ?></th><th><?= te('Grupė') ?></th><th><?= te('Diržas') ?></th><th><?= te('Nuotraukos') ?></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href="?id=<?= (int) $r['id'] ?>" data-member-profile="<?= (int) $r['id'] ?>"><strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong></a><?= tag_badges($allTags[(int) $r['id']] ?? [], (int) $r['id']) ?></td>
          <td><?= age_on($r['birth_date']) ?> m.</td>
          <td><?= e($r['group_name'] ?: '—') ?></td>
          <td><?= belt_chip($r['belt_level'] !== null ? (int) $r['belt_level'] : null) ?></td>
          <td><?= $r['photo_consent'] ? '<span class="badge badge-ok">sutinka</span>' : '<span class="badge">ne</span>' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
page_end();
