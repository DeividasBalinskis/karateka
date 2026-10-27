<?php
// Pranešimas nariams (tik adminas): kam (grupės ar konkretūs žmonės) ir kur siųsti - paskyroje, el. paštu, SMS (galima kelis).
// Paskyroje pranešimas rodomas „Svarbu“, kol narys ar tėvai paspaudžia „Supratau“. Kiekviena paskyra gauna vieną kartą.
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_admin();

const BROADCAST_CHANNELS = ['site' => 'Paskyroje', 'email' => 'El. paštu', 'sms' => 'SMS'];
const BROADCAST_SMS_MAX = 600;
$errors = [];

if (is_post()) {
    csrf_check();
    $title = trim(post('title'));
    $body = trim(str_replace("\r\n", "\n", (string) ($_POST['body'] ?? '')));
    $channels = array_values(array_intersect(array_keys(BROADCAST_CHANNELS), (array) ($_POST['channels'] ?? [])));
    $picked = member_tree_from_post();
    $memberIds = $picked['members'];
    if ($picked['groups']) {
        $memberIds = array_merge($memberIds, q('SELECT id FROM members WHERE status = "active" AND group_id IN (' . implode(',', $picked['groups']) . ')')->fetchAll(PDO::FETCH_COLUMN));
    }
    $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
    $smsText = sms_transliterate($title . ': ' . $body);

    if ($title === '' || $body === '') {
        $errors[] = 'Įrašykite pavadinimą ir tekstą.';
    }
    if (!$channels) {
        $errors[] = 'Pasirinkite, kur siųsti.';
    }
    if (!$memberIds) {
        $errors[] = 'Pasirinkite, kam siųsti (grupes ar žmones).';
    }
    if (in_array('sms', $channels, true) && mb_strlen($smsText) > BROADCAST_SMS_MAX) {
        $errors[] = 'SMS per ilga: pavadinimas ir tekstas kartu - daugiausia ' . BROADCAST_SMS_MAX . ' simbolių (dabar ' . mb_strlen($smsText) . '). Sutrumpinkite arba nesiųskite SMS.';
    }

    if (!$errors) {
        $accounts = q_all('SELECT a.id, a.email, a.first_name, GROUP_CONCAT(DISTINCT m.first_name ORDER BY m.first_name SEPARATOR ", ") AS kids
                             FROM accounts a JOIN account_members am ON am.account_id = a.id JOIN members m ON m.id = am.member_id
                            WHERE a.status = "active" AND am.member_id IN (' . implode(',', $memberIds) . ') GROUP BY a.id');
        q('INSERT INTO broadcasts (title, body, channels, recipients, sent_by) VALUES (?, ?, ?, ?, ?)',
            [mb_substr($title, 0, 190), $body, implode(',', $channels), count($accounts), (int) $me['id']]);
        $bcId = (int) db()->lastInsertId();
        $done = [];
        if (in_array('site', $channels, true)) {
            foreach ($accounts as $acc) {
                notify_account((int) $acc['id'], 'broadcast', $title, $body, null, null, $bcId);
            }
            $done[] = 'paskyroje: ' . count($accounts);
        }
        if (in_array('email', $channels, true)) {
            $mails = 0;
            foreach ($accounts as $acc) {
                $mails += send_mail($acc['email'], $title, "Sveiki, {$acc['first_name']},\n\n" . $body
                    . "\n\n--\nVšĮ Karate Ateitis\n" . abs_url('paskyra.php')) ? 1 : 0;
            }
            $done[] = 'el. paštu: ' . $mails;
        }
        if (in_array('sms', $channels, true)) {
            $rec = sms_recipients_for_members($memberIds)['recipients'];
            if ($rec) {
                sms_send_message($smsText, array_values($rec), 'Pranešimas: ' . $title, (int) $me['id']);
            }
            $done[] = 'SMS: ' . count($rec) . (sms_live() ? '' : ' (bandomasis režimas)');
        }
        flash('ok', 'Pranešimas išsiųstas (' . implode(', ', $done) . ').');
        redirect('admin/pranesimai.php');
    }
}

$history = q_all('SELECT b.*, a.first_name AS by_name FROM broadcasts b LEFT JOIN accounts a ON a.id = b.sent_by ORDER BY b.created_at DESC LIMIT 20');
$chosen = is_post() ? (array) ($_POST['channels'] ?? []) : ['site'];
page_start('Pranešimas nariams', ['admin' => true]);
?>
<div class="page-head">
  <h1 class="styled">Pranešimas nariams</h1>
  <p>Vienas tekstas pasirinktiems nariams ir jų tėvams: paskyroje (skiltyje „Svarbu“), el. paštu ir (ar) SMS.
    Tik SMS su peržiūra, kaina ir istorija - <a href="<?= url('admin/sms.php') ?>">SMS puslapyje →</a></p>
</div>
<?= form_errors($errors) ?>
<form method="post" class="form" onsubmit="return confirm('Siųsti pranešimą pasirinktiems?')">
  <?= csrf_field() ?>
  <div class="grid-2">
    <div class="panel card form">
      <h2 class="ef-step"><span>1</span> Ką pranešti</h2>
      <label>Pavadinimas <input type="text" name="title" maxlength="190" value="<?= e(post('title')) ?>" placeholder="pvz. Šeštadienio treniruotė atšaukta" required></label>
      <label>Tekstas <textarea name="body" rows="7" maxlength="4000" required placeholder="Ką svarbu žinoti..."><?= e((string) ($_POST['body'] ?? '')) ?></textarea></label>
    </div>
    <div class="panel card form">
      <h2 class="ef-step"><span>2</span> Kam</h2>
      <?= member_tree_field(is_post() ? array_map('intval', (array) ($_POST['groups'] ?? [])) : array_map('intval', q('SELECT id FROM training_groups WHERE is_active = 1')->fetchAll(PDO::FETCH_COLUMN)),
          is_post() ? array_map('intval', (array) ($_POST['members'] ?? [])) : [],
          ['hint' => 'Visos grupės pažymėtos - visiems. Išskleiskite grupę ▸, kad pasirinktumėte tik kelis.']) ?>
    </div>
  </div>
  <div class="panel card form" style="margin-top:20px;">
    <h2 class="ef-step"><span>3</span> Kur siųsti <span class="hint" style="font-family:Inter,sans-serif;">galima kelis</span></h2>
    <?php foreach (BROADCAST_CHANNELS as $k => $label): ?>
      <label class="check"><input type="checkbox" name="channels[]" value="<?= $k ?>" <?= in_array($k, $chosen, true) ? 'checked' : '' ?>>
        <span><?= e($label) ?><?= $k === 'site' ? ' <span class="hint">- rodoma „Svarbu“, kol paspaus „Supratau“</span>' : ($k === 'sms' ? ' <span class="hint">- tėvams (ar pačiam nariui) su telefono numeriu; be lietuviškų raidžių' . (sms_live() ? '' : ', bandomasis režimas - nesiunčiama') . '</span>' : '') ?></span></label>
    <?php endforeach; ?>
    <div style="margin-top:8px;"><button class="btn btn-primary" type="submit">📣 Siųsti</button></div>
  </div>
</form>

<div class="panel card">
  <h2>Išsiųsti pranešimai</h2>
  <?php if (!$history): ?><p class="muted small">Pranešimų dar nesiųsta.</p><?php endif; ?>
  <ul class="list">
    <?php foreach ($history as $b): ?>
      <li>
        <div class="row between" style="align-items:flex-start;">
          <strong><?= e($b['title']) ?></strong>
          <span class="small muted"><?= e(notification_when($b['created_at'])) ?></span>
        </div>
        <div class="small muted"><?= e(implode(', ', array_map(function ($c) { return BROADCAST_CHANNELS[$c] ?? $c; }, explode(',', $b['channels'])))) ?> · <?= (int) $b['recipients'] ?> paskyr.<?= $b['by_name'] ? ' · ' . e($b['by_name']) : '' ?></div>
        <details class="small" style="margin-top:4px;"><summary>Tekstas</summary><div style="margin-top:6px;"><?= nl2br(e($b['body'])) ?></div></details>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php
page_end();
