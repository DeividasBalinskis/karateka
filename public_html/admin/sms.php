<?php
// SMS žinutės (tik adminas): tekstas, gavėjai (grupės, renginio dalyviai, konkretūs žmonės), peržiūra, siuntimas, istorija
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_admin();

const SMS_MAX_CHARS = 600;
$errors = [];
$preview = null;

// Pasirinkti nariai pagal formą: grupės + renginio dalyviai + konkretūs žmonės
function sms_selected_members(array &$labelParts): array
{
    $ids = [];
    $groups = array_values(array_unique(array_map('intval', (array) ($_POST['groups'] ?? []))));
    if ($groups) {
        $in = implode(',', $groups);
        $ids = array_merge($ids, q("SELECT id FROM members WHERE status = 'active' AND group_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN));
        $names = q("SELECT name FROM training_groups WHERE id IN ($in) ORDER BY sort_order, name")->fetchAll(PDO::FETCH_COLUMN);
        $labelParts[] = 'Grupės: ' . implode(', ', $names);
    }
    $eventId = (int) post('event_id');
    $statuses = array_values(array_intersect(['going', 'invited'], (array) ($_POST['ev_status'] ?? [])));
    if ($eventId && $statuses) {
        $ev = q_one('SELECT title FROM events WHERE id = ?', [$eventId]);
        if ($ev) {
            $ids = array_merge($ids, q("SELECT ep.member_id FROM event_participants ep JOIN members m ON m.id = ep.member_id
                                         WHERE ep.event_id = ? AND m.status = 'active' AND ep.status IN ('" . implode("','", $statuses) . "')", [$eventId])
                ->fetchAll(PDO::FETCH_COLUMN));
            $labelParts[] = 'Renginys: ' . $ev['title'] . ' (' . implode(', ', array_map(function ($s) { return $s === 'going' ? 'dalyvaus' : 'pakviesti'; }, $statuses)) . ')';
        }
    }
    $people = member_tree_from_post()['members'];   // atskirai pažymėti (ne visa grupė)
    if ($people) {
        $ids = array_merge($ids, $people);
        $labelParts[] = count($people) . ' pasirinkt' . (count($people) === 1 ? 'as narys' : 'i nariai');
    }
    return array_values(array_unique(array_map('intval', $ids)));
}

if (is_post()) {
    csrf_check();
    $body = trim(str_replace("\r\n", "\n", (string) ($_POST['body'] ?? '')));
    if (!empty($_POST['translit'])) {
        $body = sms_transliterate($body);
    }
    if ($body === '') {
        $errors[] = 'Įrašykite žinutės tekstą.';
    } elseif (mb_strlen($body) > SMS_MAX_CHARS) {
        $errors[] = 'Žinutė per ilga (daugiausia ' . SMS_MAX_CHARS . ' simbolių).';
    }

    if (post('action') === 'send') {
        // Siunčiama tiems patiems, kurie buvo rodomi peržiūroje
        $memberIds = array_map('intval', array_filter(explode(',', post('member_ids'))));
        $res = sms_recipients_for_members($memberIds);
        if (!$res['recipients']) {
            $errors[] = 'Nėra gavėjų su telefono numeriu.';
        }
        if (!$errors) {
            $msgId = sms_send_message($body, array_values($res['recipients']), post('label'), (int) $me['id']);
            $msg = q_one('SELECT * FROM sms_messages WHERE id = ?', [$msgId]);
            if (!sms_live()) {
                flash('ok', 'Bandomasis režimas: SMS neišsiųstos, tik įrašytos į istoriją (' . (int) $msg['sent_count'] . ' gavėjai).');
            } elseif ($msg['failed_count']) {
                flash('err', 'Išsiųsta: ' . (int) $msg['sent_count'] . ', nepavyko: ' . (int) $msg['failed_count'] . '. Žr. sąrašą žemiau.');
            } else {
                flash('ok', 'SMS išsiųsta: ' . (int) $msg['sent_count'] . ' gavėjams.');
            }
            redirect('admin/sms.php?id=' . $msgId);
        }
    } else {
        $labelParts = [];
        $memberIds = sms_selected_members($labelParts);
        if (!$memberIds) {
            $errors[] = 'Pasirinkite gavėjus: grupę, renginio dalyvius arba konkrečius žmones.';
        }
        if (!$errors) {
            $res = sms_recipients_for_members($memberIds);
            $preview = ['body' => $body, 'parts' => sms_parts($body), 'members' => $memberIds, 'label' => implode(' · ', $labelParts)] + $res;
        }
    }
}

// Vienos žinutės informacija
$detailId = (int) get('id');
$detail = $detailId ? q_one('SELECT s.*, a.first_name, a.last_name FROM sms_messages s LEFT JOIN accounts a ON a.id = s.sent_by WHERE s.id = ?', [$detailId]) : null;
$detailRecipients = $detail ? q_all('SELECT * FROM sms_recipients WHERE message_id = ? ORDER BY status = "failed" DESC, name', [$detailId]) : [];

$history = q_all('SELECT s.*, a.first_name FROM sms_messages s LEFT JOIN accounts a ON a.id = s.sent_by ORDER BY s.created_at DESC LIMIT 30');
$events = q_all('SELECT e.id, e.title, e.starts_on,
                        (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.id AND ep.status = "going") AS going,
                        (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.id AND ep.status = "invited") AS invited
                   FROM events e WHERE e.starts_on >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) ORDER BY e.starts_on');
$selGroups = array_map('intval', (array) ($_POST['groups'] ?? []));
$selMembers = array_map('intval', (array) ($_POST['members'] ?? []));
$selStatuses = is_post() ? (array) ($_POST['ev_status'] ?? []) : ['going'];

page_start('SMS žinutės', ['admin' => true]);
?>
<div class="page-head">
  <h1 class="styled">SMS žinutės</h1>
  <p class="muted" style="margin:0;">Trumpi pranešimai telefonu, pvz. „Šiandien treniruotės nebus“. Vaikams SMS gauna tėvai, suaugusieji - patys. Numeriai imami iš paskyrų.</p>
</div>
<?php if (!sms_live()): ?>
  <div class="notice-test panel">
    <strong>Bandomasis režimas.</strong> SMS niekam nesiunčiamos - tik parodoma, kas jas gautų, ir įrašoma į istoriją.
    Tikros SMS dar neįjungtos – kreipkitės į svetainės administratorių.
  </div>
<?php endif; ?>
<?= form_errors($errors) ?>

<?php if ($detail): ?>
  <div class="panel card sms-detail">
    <div class="row between" style="align-items:flex-start;">
      <div>
        <div class="kicker"><?= e(fmt_date(substr($detail['created_at'], 0, 10))) ?>, <?= e(substr($detail['created_at'], 11, 5)) ?> · <?= e(trim(($detail['first_name'] ?? '') . ' ' . ($detail['last_name'] ?? ''))) ?></div>
        <p class="sms-bubble"><?= nl2br(e($detail['body'])) ?></p>
        <p class="small muted"><?= e($detail['label'] ?? '') ?></p>
      </div>
      <a class="small" href="<?= url('admin/sms.php') ?>">Uždaryti ✕</a>
    </div>
    <p class="small">
      <?= $detail['provider'] === 'log' ? '<span class="badge">bandomasis</span> ' : '' ?>
      Gavėjų: <strong><?= (int) $detail['sent_count'] + (int) $detail['failed_count'] ?></strong>
      <?= $detail['failed_count'] ? ' · <span class="test-no">nepavyko: ' . (int) $detail['failed_count'] . '</span>' : '' ?>
      · <?= (int) $detail['parts'] ?> SMS dal<?= (int) $detail['parts'] === 1 ? 'is' : 'ys' ?> kiekvienam
      <?= $detail['cost_eur'] !== null ? ' · ~' . number_format((float) $detail['cost_eur'], 2, ',', ' ') . ' €' : '' ?>
    </p>
    <ul class="list sms-list">
      <?php foreach ($detailRecipients as $r): ?>
        <li class="row between">
          <span><strong><?= e($r['name']) ?></strong> <span class="muted small"><?= e($r['phone']) ?><?= $r['for_members'] ? ' · dėl: ' . e($r['for_members']) : '' ?></span>
            <?php if ($r['error']): ?><div class="small test-no"><?= e($r['error']) ?></div><?php endif; ?></span>
          <span class="badge <?= $r['status'] === 'failed' ? 'badge-warn' : '' ?>"><?= ['sent' => '✓ išsiųsta', 'failed' => 'nepavyko', 'test' => 'bandomasis'][$r['status']] ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($preview): ?>
  <div class="panel card sms-preview">
    <h2>Patikrinkite prieš siunčiant</h2>
    <p class="sms-bubble"><?= nl2br(e($preview['body'])) ?></p>
    <p class="small muted"><?= mb_strlen($preview['body']) ?> simb. · <?= $preview['parts'] ?> SMS dal<?= $preview['parts'] === 1 ? 'is' : 'ys' ?> vienam gavėjui · <?= e($preview['label']) ?></p>
    <?php $n = count($preview['recipients']); $price = config('sms.price_eur'); ?>
    <p><strong><?= $n ?> gavėj<?= $n === 1 ? 'as' : 'ai' ?></strong><?= $price !== null && $n ? ' · apie ' . number_format($n * $preview['parts'] * (float) $price, 2, ',', ' ') . ' €' : '' ?></p>
    <?php if ($preview['missing']): ?>
      <p class="small test-no">Be telefono numerio (negaus): <?= e(implode(', ', $preview['missing'])) ?>. Numerį galima įrašyti paskyros nustatymuose.</p>
    <?php endif; ?>
    <?php if ($n): ?>
      <details class="sms-recipients">
        <summary class="small">Rodyti gavėjus</summary>
        <ul class="list sms-list">
          <?php foreach ($preview['recipients'] as $r): ?>
            <li><strong><?= e($r['name']) ?></strong> <span class="muted small"><?= e($r['phone']) ?> · dėl: <?= e(implode(', ', $r['for'])) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </details>
      <form method="post" class="row" style="gap:8px; margin-top:14px;" onsubmit="this.querySelector('button').disabled = true;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="send">
        <input type="hidden" name="body" value="<?= e($preview['body']) ?>">
        <input type="hidden" name="label" value="<?= e($preview['label']) ?>">
        <input type="hidden" name="member_ids" value="<?= e(implode(',', $preview['members'])) ?>">
        <button class="btn btn-primary" type="submit"><?= sms_live() ? 'Siųsti ' . $n . ' SMS' : 'Išbandyti (nesiunčiama)' ?></button>
        <span class="small muted">arba pakeiskite žemiau ir peržiūrėkite dar kartą</span>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<form method="post" class="form sms-form">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="preview">
  <div class="panel card">
    <h2>Žinutė</h2>
    <label>Tekstas
      <textarea name="body" id="smsBody" rows="4" maxlength="<?= SMS_MAX_CHARS ?>" placeholder="pvz. Šiandien treniruotės nebus. Treneris Denis" required><?= e((string) ($_POST['body'] ?? '')) ?></textarea></label>
    <div class="row between" style="flex-wrap:wrap; gap:8px;">
      <label class="check" style="margin:0;"><input type="checkbox" name="translit" id="smsTranslit" value="1" <?= !empty($_POST['translit']) ? 'checked' : '' ?>><span>Be lietuviškų raidžių (ą → a): pigiau, 160 simbolių vietoj 70</span></label>
      <span class="small muted" id="smsCount"></span>
    </div>
  </div>
  <div class="panel card">
    <h2>Kam siųsti?</h2>
    <p class="hint">Galima derinti: pvz. grupė ir dar keli žmonės. Tas pats numeris gaus vieną SMS.</p>
    <div class="grid-2 sms-to" style="margin-top:6px;">
      <div>
        <div class="kicker">Grupės ir žmonės</div>
        <?= member_tree_field($selGroups, $selMembers, ['hint' => 'Pažymėkite visą grupę arba išskleiskite ▸ ir pasirinkite tik norimus vaikus.']) ?>
      </div>
      <div>
        <div class="kicker">Renginio dalyviai</div>
        <select name="event_id">
          <option value="">-</option>
          <?php foreach ($events as $ev): ?>
            <option value="<?= (int) $ev['id'] ?>" <?= (int) post('event_id') === (int) $ev['id'] ? 'selected' : '' ?>><?= e(fmt_date($ev['starts_on']) . ' · ' . $ev['title']) ?> (<?= (int) $ev['going'] ?> dalyvaus<?= $ev['invited'] ? ', ' . (int) $ev['invited'] . ' pakviesti' : '' ?>)</option>
          <?php endforeach; ?>
        </select>
        <div class="seg-choice" style="margin-top:8px;">
          <label class="check"><input type="checkbox" name="ev_status[]" value="going" <?= in_array('going', $selStatuses, true) ? 'checked' : '' ?>><span>Dalyvaus</span></label>
          <label class="check"><input type="checkbox" name="ev_status[]" value="invited" <?= in_array('invited', $selStatuses, true) ? 'checked' : '' ?>><span>Pakviesti, neatsakė</span></label>
        </div>
      </div>
    </div>
    <div style="margin-top:16px;"><button class="btn btn-primary" type="submit">Peržiūrėti gavėjus →</button></div>
  </div>
</form>

<div class="panel card">
  <h2>Istorija</h2>
  <?php if (!$history): ?><p class="muted">Dar nesiųsta nė vienos žinutės.</p><?php endif; ?>
  <ul class="list sms-list">
    <?php foreach ($history as $h): ?>
      <li class="row between">
        <span><a href="?id=<?= (int) $h['id'] ?>"><strong><?= e(mb_strimwidth($h['body'], 0, 70, '…')) ?></strong></a>
          <div class="small muted"><?= e(fmt_date(substr($h['created_at'], 0, 10))) ?> <?= e(substr($h['created_at'], 11, 5)) ?> · <?= e($h['label'] ?? '') ?></div></span>
        <span class="small" style="white-space:nowrap;"><?= $h['provider'] === 'log' ? '<span class="badge">bandomasis</span> ' : '' ?><?= (int) $h['sent_count'] ?> gav.<?= $h['failed_count'] ? ' · <span class="test-no">' . (int) $h['failed_count'] . ' nepavyko</span>' : '' ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<script>
(function () {
  // Simbolių ir SMS dalių skaičius (kaip serveryje: GSM - 160/153, su ą, č... - 70/67)
  var body = document.getElementById('smsBody'), tr = document.getElementById('smsTranslit'), out = document.getElementById('smsCount');
  var basic = '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà', ext = '^{}\\[~]|€';
  var map = {'ą':'a','č':'c','ę':'e','ė':'e','į':'i','š':'s','ų':'u','ū':'u','ž':'z','Ą':'A','Č':'C','Ę':'E','Ė':'E','Į':'I','Š':'S','Ų':'U','Ū':'U','Ž':'Z','„':'"','“':'"','”':'"','‘':"'",'’':"'",'–':'-','—':'-','…':'...'};
  function count() {
    var t = body.value.replace(/\r\n/g, '\n').trim();
    if (tr.checked) t = t.replace(/[ąčęėįšųūžĄČĘĖĮŠŲŪŽ„“”‘’–—…]/g, function (c) { return map[c]; });
    var len = 0, gsm = true, chars = Array.from(t);
    for (var i = 0; i < chars.length; i++) {
      if (basic.indexOf(chars[i]) !== -1) len++; else if (ext.indexOf(chars[i]) !== -1) len += 2; else { gsm = false; break; }
    }
    var parts;
    if (!gsm) { len = chars.length; parts = len <= 70 ? 1 : Math.ceil(len / 67); }
    else { parts = len <= 160 ? 1 : Math.ceil(len / 153); }
    out.textContent = t ? len + ' simb. · ' + parts + ' SMS dal' + (parts === 1 ? 'is' : 'ys') + (gsm ? '' : ' (su lietuviškomis raidėmis)') : '';
  }
  body.addEventListener('input', count); tr.addEventListener('change', count); count();
})();
</script>
<?php
page_end();
