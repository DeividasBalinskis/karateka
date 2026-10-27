<?php
// Žinutės su nariais: narių atsakymai į pastabas, klausimai prie pamokų, bendros žinutės. Trenerio atsakymą mato tik tas narys.
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_staff();

$mid = (int) (get('m') ?: post('member_id'));
$member = $mid ? q_one('SELECT m.*, g.name AS group_name FROM members m LEFT JOIN training_groups g ON g.id = m.group_id WHERE m.id = ?', [$mid]) : null;
if ($mid && !$member) {
    not_found();
}

// Anoniminiai komentarai: perskaityti / ištrinti
if (is_post() && in_array(post('action'), ['anon_read', 'anon_delete'], true)) {
    csrf_check();
    q(post('action') === 'anon_read' ? 'UPDATE anon_feedback SET read_at = COALESCE(read_at, NOW()) WHERE id = ?' : 'DELETE FROM anon_feedback WHERE id = ?', [(int) post('id')]);
    redirect('admin/zinutes.php#anoniminiai');
}

// Naujos žinutės (pokalbio langas atsinaujina pats): JSON su paskutinės žinutės id
if (get('naujausia') !== '') {
    header('Content-Type: application/json; charset=utf-8');
    $where = $mid ? 'member_id = ' . $mid : '1';
    exit(json_encode(['last' => (int) q_value("SELECT COALESCE(MAX(id), 0) FROM member_messages WHERE $where"),
        'anon' => (int) q_value('SELECT COALESCE(MAX(id), 0) FROM anon_feedback')]));
}

if (is_post() && $member) {
    csrf_check();
    // Klausimas viešas (rodomas visiems su atsakymu) / su vardu
    if (post('action') === 'publish') {
        $msg = q_one('SELECT * FROM member_messages WHERE id = ? AND member_id = ? AND from_staff = 0', [(int) post('message_id'), $mid]);
        if ($msg) {
            $field = post('field') === 'show_name' ? 'show_name' : 'is_public';
            q("UPDATE member_messages SET $field = 1 - $field WHERE id = ?", [(int) $msg['id']]);
            flash('ok', $field === 'is_public' ? ($msg['is_public'] ? 'Klausimas paslėptas - matys tik klausęs.' : 'Klausimas ir atsakymas rodomi visiems prisijungusiems.')
                : ($msg['show_name'] ? 'Vardas paslėptas.' : 'Rodomas klaususiojo vardas.'));
        }
        redirect('admin/zinutes.php?m=' . $mid . '#' . preg_replace('~[^a-z0-9]~', '', post('topic')));
    }
    $body = trim(str_replace("\r\n", "\n", post('body')));
    $email = !empty($_POST['email']);
    if ($body === '' && post('action') !== 'note') {
        flash('err', 'Įrašykite atsakymą.');
        redirect('admin/zinutes.php?m=' . $mid);
    }
    if (post('action') === 'reply') {
        $noteId = (int) post('note_id') ?: null;
        $lessonId = (int) post('lesson_id') ?: null;
        $eventId = (int) post('event_id') ?: null;
        $newsId = (int) post('news_id') ?: null;
        if ($noteId && !q_value('SELECT 1 FROM coach_notes WHERE id = ? AND member_id = ?', [$noteId, $mid])) {
            $noteId = null;
        }
        add_member_message($mid, (int) $me['id'], true, $body, $noteId, $lessonId, $eventId, $newsId);
        mark_member_messages_read($mid);
        if ($email) {
            notify_member_reply($member, $me, $body);
        }
        flash('ok', 'Atsakymas išsiųstas ' . $member['first_name'] . ($email ? ' (ir laiškas).' : '.'));
        redirect('admin/zinutes.php?m=' . $mid . '#' . ($noteId ? 'n' . $noteId : ($lessonId ? 'l' . $lessonId : ($eventId ? 'e' . $eventId : ($newsId ? 'w' . $newsId : 'general')))));
    }
    // Nauja trenerio pastaba (su pamoka, užduotis) - atsiranda nario paskyroje „Trenerio pastabos“
    if (post('action') === 'note') {
        $lessonId = (int) post('lesson_id') ?: null;
        if ($lessonId && !q_value('SELECT 1 FROM lessons WHERE id = ?', [$lessonId])) {
            $lessonId = null;
        }
        if ($body === '' && !$lessonId) {
            flash('err', 'Įrašykite pastabą arba pasirinkite pamoką.');
            redirect('admin/zinutes.php?m=' . $mid);
        }
        $n = send_coach_note($member, $me, $body, $lessonId, !empty($_POST['is_task']), $email);
        flash('ok', 'Pastaba išsiųsta ' . $member['first_name'] . '.');
        redirect('admin/zinutes.php?m=' . $mid . '#n' . $n);
    }
}

// ---------- Vieno nario pokalbis ----------
if ($member) {
    $notes = member_notes($mid, 30);
    $noteMsgs = note_messages(array_column($notes, 'id'));
    $free = member_free_threads($mid);
    // Temos: pastabos su atsakymais, pamokų klausimai, bendros. Naujausios viršuje.
    $topics = [];
    foreach ($notes as $n) {
        if (!empty($noteMsgs[(int) $n['id']])) {
            $topics[] = ['key' => 'n' . $n['id'], 'note' => $n, 'lesson_id' => null, 'msgs' => $noteMsgs[(int) $n['id']]];
        }
    }
    foreach ($free as $key => $msgs) {
        $topics[] = ['key' => $key, 'note' => null, 'msgs' => $msgs];
    }
    usort($topics, function ($x, $y) { return strcmp(end($y['msgs'])['created_at'], end($x['msgs'])['created_at']); });
    $plain = array_filter($notes, function ($n) use ($noteMsgs) { return empty($noteMsgs[(int) $n['id']]); });
    mark_member_messages_read($mid);
    $lessons = q_all('SELECT id, title, belt_level FROM lessons ORDER BY title');
    $accounts = q_all('SELECT a.first_name, a.last_name, am.relation FROM accounts a JOIN account_members am ON am.account_id = a.id WHERE am.member_id = ? AND a.status = "active"', [$mid]);

    $replyForm = function (?int $noteId, string $key) use ($mid): string {
        $hidden = '';
        foreach (message_topic_hidden($key) as $k => $v) {
            $hidden .= '<input type="hidden" name="' . $k . '" value="' . (int) $v . '">';
        }
        return '<form method="post" class="msg-form">' . csrf_field()
            . '<input type="hidden" name="action" value="reply"><input type="hidden" name="member_id" value="' . $mid . '">'
            . '<input type="hidden" name="note_id" value="' . (int) $noteId . '">' . $hidden
            . '<textarea name="body" rows="2" maxlength="' . MESSAGE_MAX . '" placeholder="Atsakymas - matys tik šis narys" required></textarea>'
            . '<label class="check small"><input type="checkbox" name="email" value="1" checked><span>ir laiškas</span></label>'
            . '<button class="btn btn-primary btn-sm" type="submit">Atsakyti</button></form>';
    };

    page_start('Žinutės: ' . $member['first_name'] . ' ' . $member['last_name'], ['admin' => true]);
    ?>
    <a class="small" href="<?= url('admin/zinutes.php') ?>">← Visos žinutės</a>
    <div class="page-head row between" style="margin-top:8px; flex-wrap:wrap; gap:10px; align-items:flex-end;">
      <div>
        <h1 class="styled"><?= e($member['first_name'] . ' ' . $member['last_name']) ?></h1>
        <p class="muted" style="margin:0;"><?= e($member['group_name'] ?? 'be grupės') ?> · <?= e(belt_label($member['belt_level'] !== null ? (int) $member['belt_level'] : null)) ?>
          <?= $accounts ? ' · paskyros: ' . e(implode(', ', array_map(function ($x) { return $x['first_name'] . ($x['relation'] === 'parent' ? ' (tėvai)' : ''); }, $accounts))) : ' · paskyros nėra - žinučių nematys' ?></p>
      </div>
      <a class="btn btn-ghost btn-sm" href="<?= url('admin/nariai.php?id=' . $mid) ?>">Nario kortelė</a>
    </div>

    <details class="panel card new-note"<?= !$topics ? ' open' : '' ?>>
      <summary><strong>✎ Nauja pastaba nariui</strong> <span class="small muted">- su pamoka ar kaip užduotis; atsiras nario paskyroje</span></summary>
      <form method="post" class="form" style="margin-top:12px;">
        <?= csrf_field() ?><input type="hidden" name="action" value="note"><input type="hidden" name="member_id" value="<?= $mid ?>">
        <textarea name="body" rows="3" maxlength="<?= MESSAGE_MAX ?>" placeholder="pvz. Šaunuolis šiandien! Pasikartok Heian Nidan pradžią."></textarea>
        <div class="form-row">
          <label>Prisegti pamoką <span class="hint">nebūtina</span>
            <select name="lesson_id"><option value="">—</option><?php foreach ($lessons as $l): ?><option value="<?= (int) $l['id'] ?>"><?= e(($l['belt_level'] ? BELTS[(int) $l['belt_level']][0] . ' · ' : '') . $l['title']) ?></option><?php endforeach; ?></select></label>
          <div class="seg-choice" style="align-self:end;">
            <label class="check"><input type="checkbox" name="is_task" value="1"><span>Užduotis (narys pažymi „Atlikau“)</span></label>
            <label class="check"><input type="checkbox" name="email" value="1" checked><span>Išsiųsti laišką</span></label>
          </div>
        </div>
        <div><button class="btn btn-primary" type="submit">Siųsti</button></div>
      </form>
    </details>

    <?php if (!$topics): ?>
      <div class="panel card"><p class="muted">Žinučių su šiuo nariu dar nėra.</p></div>
    <?php endif; ?>
    <?php foreach ($topics as $t): ?>
      <div class="panel card msg-topic" id="<?= e($t['key']) ?>">
        <div class="msg-topic-title">
          <?php if ($t['note']): $n = $t['note']; ?>
            Trenerio pastaba · <?= note_source($n) ?><?= $n['author'] ? ' · ' . e($n['author']) : '' ?>
            <div class="note-quote"><?= nl2br(e(mb_strimwidth($n['body'], 0, 300, '…'))) ?><?= $n['lesson_title'] ? ' <span class="small">📘 ' . e($n['lesson_title']) . '</span>' : '' ?></div>
          <?php else: ?>
            <?= message_topic_title($t['key'], $t['msgs'][0]) ?>
          <?php endif; ?>
        </div>
        <div class="msg-thread">
          <?php foreach ($t['msgs'] as $r): ?>
            <?= render_message($r, true) ?>
            <?php if (!$r['from_staff'] && !$t['note'] && $t['key'] !== 'general'): ?>
              <!-- Klausimas prie pamokos / renginio / naujienos: Denis gali parodyti visiems -->
              <form method="post" class="q-publish">
                <?= csrf_field() ?><input type="hidden" name="action" value="publish"><input type="hidden" name="member_id" value="<?= $mid ?>">
                <input type="hidden" name="message_id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="topic" value="<?= e($t['key']) ?>">
                <button class="linklike small" name="field" value="is_public"><?= !empty($r['is_public']) ? '🔒 Slėpti nuo kitų' : '👁 Rodyti visiems' ?></button>
                <?php if (!empty($r['is_public'])): ?> · <button class="linklike small" name="field" value="show_name"><?= !empty($r['show_name']) ? 'be vardo' : 'su vardu' ?></button>
                  <span class="small muted">· dabar rodoma <?= !empty($r['show_name']) ? 'su vardu' : 'be vardo' ?></span><?php endif; ?>
              </form>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
        <?= $replyForm($t['note'] ? (int) $t['note']['id'] : null, $t['key']) ?>
      </div>
    <?php endforeach; ?>

    <?php if ($plain): ?>
      <details class="panel card">
        <summary class="small"><strong>Kitos pastabos šiam nariui</strong> (<?= count($plain) ?>) - be atsakymų</summary>
        <ul class="list" style="margin-top:8px;">
          <?php foreach ($plain as $n): ?>
            <li><span class="small muted"><?= note_source($n) ?><?= $n['read_at'] ? ' · perskaityta' : ' · dar neperskaityta' ?><?= $n['is_task'] ? ($n['done_at'] ? ' · ✓ atlikta' : ' · užduotis') : '' ?></span><br><?= e(mb_strimwidth($n['body'], 0, 200, '…')) ?></li>
          <?php endforeach; ?>
        </ul>
      </details>
    <?php endif; ?>
    <?= live_refresh_js('admin/zinutes.php?m=' . $mid . '&naujausia=1') ?>
    <?php
    page_end();
    exit;
}

// ---------- Visi pokalbiai ----------
$onlyOpen = get('neatsakyta') === '1';
$rows = [];
try {
    $rows = q_all('SELECT m.id, m.first_name, m.last_name, g.name AS group_name,
                          SUM(mm.from_staff = 0 AND mm.read_at IS NULL) AS unread, MAX(mm.created_at) AS last_at,
                          (SELECT x.body FROM member_messages x WHERE x.member_id = m.id ORDER BY x.created_at DESC, x.id DESC LIMIT 1) AS last_body,
                          (SELECT x.from_staff FROM member_messages x WHERE x.member_id = m.id ORDER BY x.created_at DESC, x.id DESC LIMIT 1) AS last_from_staff
                     FROM member_messages mm JOIN members m ON m.id = mm.member_id LEFT JOIN training_groups g ON g.id = m.group_id
                    GROUP BY m.id, m.first_name, m.last_name, g.name ORDER BY SUM(mm.from_staff = 0 AND mm.read_at IS NULL) > 0 DESC, MAX(mm.created_at) DESC LIMIT 300');
} catch (PDOException $ex) {
    if ((string) $ex->getCode() !== '42S02') {
        throw $ex;
    }
    flash('err', 'Trūksta duomenų bazės lentelės - importuokite db/018_zinutes.sql.');
}
if ($onlyOpen) {
    $rows = array_values(array_filter($rows, function ($r) { return !$r['last_from_staff']; }));
}
$all = q_all("SELECT m.id, m.first_name, m.last_name, g.name AS group_name FROM members m LEFT JOIN training_groups g ON g.id = m.group_id WHERE m.status = 'active' ORDER BY m.first_name, m.last_name");

page_start('Žinutės', ['admin' => true]);
?>
<div class="page-head row between" style="flex-wrap:wrap; gap:10px; align-items:flex-end;">
  <div>
    <h1 class="styled">Žinutės</h1>
    <p class="muted" style="margin:0;">Pokalbiai su nariais, klausimai prie pamokų, renginių ir naujienų. Jūsų atsakymą mato tik tas narys (ir jo tėvai), nebent paspausite „Rodyti visiems“. Atsinaujina pats.</p>
  </div>
  <form method="get" class="row msg-start" style="gap:6px;">
    <select name="m" required aria-label="Narys"><option value="">Parašyti nariui…</option><?php foreach ($all as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['first_name'] . ' ' . $m['last_name'] . ($m['group_name'] ? ' · ' . $m['group_name'] : '')) ?></option><?php endforeach; ?></select>
    <button class="btn btn-primary btn-sm" type="submit">Atidaryti</button>
  </form>
</div>
<div class="top-tabs" style="margin-bottom:14px;">
  <a href="<?= url('admin/zinutes.php') ?>" class="<?= !$onlyOpen ? 'active' : '' ?>">Visos</a>
  <a href="<?= url('admin/zinutes.php?neatsakyta=1') ?>" class="<?= $onlyOpen ? 'active' : '' ?>">Laukia atsakymo</a>
</div>
<?php $anon = []; try { $anon = q_all('SELECT * FROM anon_feedback ORDER BY created_at DESC LIMIT 50'); } catch (PDOException $ex) {} ?>
<div class="panel card">
  <?php if (!$rows): ?><p class="muted"><?= $onlyOpen ? 'Visiems atsakyta ✓' : 'Žinučių dar nėra. Nariai gali atsakyti į jūsų pastabas paskyroje arba paklausti prie pamokos.' ?></p><?php endif; ?>
  <ul class="list inbox">
    <?php foreach ($rows as $r): ?>
      <li class="<?= $r['unread'] ? 'unread' : '' ?>">
        <a class="inbox-row" href="<?= url('admin/zinutes.php?m=' . (int) $r['id']) ?>">
          <span class="inbox-who"><strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong> <span class="small muted"><?= e($r['group_name'] ?? '') ?></span>
            <?= $r['unread'] ? '<span class="badge badge-new">' . (int) $r['unread'] . ' nauj.</span>' : (!$r['last_from_staff'] ? '<span class="badge badge-warn">laukia atsakymo</span>' : '') ?></span>
          <span class="inbox-last small muted"><?= $r['last_from_staff'] ? 'Jūs: ' : '' ?><?= e(mb_strimwidth($r['last_body'], 0, 110, '…')) ?></span>
          <span class="inbox-when small muted"><?= e(fmt_date(substr($r['last_at'], 0, 10))) ?> <?= e(substr($r['last_at'], 11, 5)) ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<div class="panel card" id="anoniminiai">
  <div class="kicker">Anoniminiai komentarai<?= ($an = count(array_filter($anon, function ($x) { return !$x['read_at']; }))) ? ' <span class="badge badge-new">' . $an . ' nauj.</span>' : '' ?></div>
  <p class="small muted" style="margin-top:-4px;">Kas parašė - nesaugoma, todėl atsakyti negalima.</p>
  <?php if (!$anon): ?><p class="muted small">Anoniminių komentarų dar nėra.</p><?php endif; ?>
  <ul class="list">
    <?php foreach ($anon as $x): ?>
      <li class="anon-item<?= $x['read_at'] ? '' : ' unread' ?>">
        <div><?= nl2br(e($x['body'])) ?></div>
        <div class="row between small muted" style="gap:8px; flex-wrap:wrap;">
          <span><?= e(notification_when($x['created_at'])) ?><?= $x['page'] ? ' · ' . e($x['page']) : '' ?></span>
          <span class="row" style="gap:6px;">
            <?php if (!$x['read_at']): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $x['id'] ?>"><button class="btn btn-ghost btn-sm" name="action" value="anon_read">✓ Perskaičiau</button></form><?php endif; ?>
            <form method="post" class="inline-form" onsubmit="return confirm('Ištrinti komentarą?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $x['id'] ?>"><button class="btn btn-ghost btn-sm" name="action" value="anon_delete">Ištrinti</button></form>
          </span>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?= live_refresh_js('admin/zinutes.php?naujausia=1') ?>
<?php
page_end();
