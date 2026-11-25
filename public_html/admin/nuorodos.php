<?php
// C3: Visos nuorodos (Valdymas → Nustatymai → „Visos nuorodos“, tik adminui): kiekviena slapta nuoroda - kam ji, kada sukurta ir naudota,
// Kopijuoti · ↻ Nauja nuoroda · Išjungti. Apačioje - kiti raktai (laiškų nuorodos, kodai kitam įrenginiui, API raktas), tik peržiūrai.
// POST - bendro bloko (link_box, app/links.php) veiksmai iš bet kurio puslapio: new / renew / off; grįžtama ten, iš kur paspausta.
require dirname(__DIR__) . '/app/bootstrap.php';

/** Grįžti tik į savo svetainės puslapį */
function links_back(string $back): string
{
    $base = (string) (config('base_path') ?: '');
    if ($base !== '' && strpos($back, $base . '/') === 0) {
        $back = substr($back, strlen($base));
    }
    return preg_match('~^/(?![/\\\\])[^\s]*$~', $back) ? ltrim($back, '/') : 'admin/nuorodos.php';
}

if (is_post()) {
    csrf_check();
    $me = require_staff();
    $kind = (string) post('kind');
    $back = links_back((string) post('back'));
    if (!isset(LINK_KINDS[$kind]) || !link_can_manage($kind, $me) || !links_ready()) {
        forbidden();
    }
    $owner = post('owner') !== '' ? (int) post('owner') : null;
    if ((LINK_KINDS[$kind]['owner'] ?? null) !== null && !$owner) {
        forbidden();
    }
    $row = post('id') !== '' ? link_get((int) post('id')) : null;
    if ($row && $row['kind'] !== $kind) {
        forbidden();
    }
    switch (post('action')) {
        case 'new':
            $label = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) post('label'))), 0, 80);
            if ($kind === 'ltkf_editor' && mb_strlen($label) < 2) {
                flash('err', t('Įrašykite redaktoriaus vardą.'));
                break;
            }
            if ($kind === 'exam_signup') {
                exam_signup_link((int) $owner, true);   // ir registracijos jungiklis (exam_signup_links)
            } else {
                link_create($kind, $owner, $label);
            }
            flash('ok', t('Nuoroda sukurta - nukopijuokite ir nusiųskite.'));
            break;
        case 'renew':
            if ($row && link_renew((int) $row['id'])) {
                flash('ok', t('Nauja nuoroda sukurta - senoji nebeveikia.'));
            }
            break;
        case 'off':
            if ($row) {
                link_off((int) $row['id']);
                flash('ok', t('Nuoroda išjungta - su ja nebeprisijungs.'));
            }
            break;
    }
    redirect($back);
}

$me = require_admin();
$all = get('visos') === '1';
$byKind = [];
if (links_ready()) {
    foreach (q_all('SELECT * FROM access_links' . ($all ? '' : ' WHERE revoked_at IS NULL') . ' ORDER BY kind, id DESC') as $r) {
        $byKind[$r['kind']][] = $r;
    }
}
$stateName = ['on' => t('veikia'), 'off' => t('išjungta'), 'replaced' => t('pakeista nauja'), 'expired' => t('pasibaigė')];
$stateCls = ['on' => 'badge-ok', 'off' => '', 'replaced' => '', 'expired' => 'badge-warn'];
// Bendros nuorodos (be savininko), kurias galima sukurti čia pat
$creatable = ['demo', 'exam_waitlist', 'just', 'ltkf_review'];

page_start(t('Visos nuorodos'), ['admin' => true]);
?>
<div class="page-head">
  <h1 class="styled"><?= te('Visos nuorodos') ?></h1>
  <p><?= te('Kiekviena slapta nuoroda: kam ji, kada sukurta ir paskutinį kartą naudota. „↻ Nauja nuoroda“ - senoji nebeveiks; „Išjungti“ - nebeveiks visai.') ?></p>
  <p class="small"><?= $all ? '<a href="' . e(url('admin/nuorodos.php')) . '">' . te('Rodyti tik veikiančias') . '</a>' : '<a href="' . e(url('admin/nuorodos.php?visos=1')) . '">' . te('Rodyti ir išjungtas bei pakeistas') . '</a>' ?></p>
</div>
<?php if (!links_ready()): ?>
  <div class="panel card"><p class="muted"><?= te('Atnaujinkite duomenų bazę (db/062).') ?></p></div>
<?php endif; ?>
<?php foreach (LINK_KINDS as $kind => $def):
    $rows = [];
    foreach ($byKind[$kind] ?? [] as $r) {
        $r['_owner'] = link_owner_name($r);
        if (!$all && $r['owner_id'] !== null && $r['_owner'] === '#' . (int) $r['owner_id']) {
            continue;   // savininkas ištrintas - nuoroda vis tiek neveikia
        }
        $rows[] = $r;
    }
    if (!$rows && !in_array($kind, $creatable, true) && !$all) { continue; } ?>
<section class="panel card lk-kind" id="k-<?= e($kind) ?>">
  <div class="kicker"><?= e(link_kind_label($kind)) ?> <span class="muted">(<?= count(array_filter($rows, function ($r) { return empty($r['revoked_at']); })) ?>)</span>
    <?php if (!empty($def['write'])): ?><span class="badge badge-warn" title="<?= te('Atidaręs įrašo vardą - jis išsaugomas su kiekvienu pakeitimu') ?>"><?= te('gali keisti') ?></span><?php endif; ?></div>
  <?php if (!$rows): ?>
    <?= in_array($kind, $creatable, true) ? link_box($kind, null, ['row' => null, 'back' => '/admin/nuorodos.php#k-' . $kind]) : '<p class="muted small">' . te('Nėra.') . '</p>' ?>
  <?php endif; ?>
  <?php foreach ($rows as $i => $r): $st = link_state($r); $where = link_where($r); $owner = $r['_owner']; ?>
    <?php if ($i === 8): /* naujausios 8 - matomos, kitos suskleistos */ ?><details class="lk-more"><summary><?= te('Rodyti dar ({n})', ['n' => count($rows) - 8]) ?></summary><?php endif; ?>
    <div class="lk-item" data-link-row="<?= (int) $r['id'] ?>">
      <div class="lk-head"><b><?= e($owner !== '' ? $owner : link_kind_label($kind)) ?></b>
        <span class="badge <?= $stateCls[$st] ?>"><?= e($stateName[$st]) ?></span>
        <?php if ($where !== ''): ?><a class="small" href="<?= e(url($where)) ?>"><?= te('Kur ji naudojama →') ?></a><?php endif; ?></div>
      <?php if (empty($r['revoked_at'])): ?>
        <?= link_box($kind, $r['owner_id'] !== null ? (int) $r['owner_id'] : null, ['row' => $r, 'back' => '/admin/nuorodos.php' . ($all ? '?visos=1' : '') . '#k-' . $kind]) ?>
      <?php else: ?>
        <p class="muted small lk-meta"><?= e(link_meta_text($r, $st)) ?>, <?= te('išjungta') ?> <?= e(fmt_date(substr((string) $r['revoked_at'], 0, 10), true) . ' ' . substr((string) $r['revoked_at'], 11, 5)) ?></p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (count($rows) > 8): ?></details><?php endif; ?>
</section>
<?php endforeach; ?>

<?php
// Kiti raktai: jau saugūs (vienkartiniai, su pabaiga) - tik peržiūrai, tvarkomi savo vietose
$mail = [];
try {
    foreach (q_all('SELECT purpose, COUNT(*) n, MAX(expires_at) last_end FROM email_tokens WHERE used_at IS NULL AND expires_at > NOW() GROUP BY purpose') as $r) {
        $mail[$r['purpose']] = $r;
    }
} catch (PDOException $ex) {
}
$mailName = ['verify_email' => t('El. pašto patvirtinimas'), 'parent_consent' => t('Tėvų sutikimas'), 'password_reset' => t('Slaptažodžio keitimas'),
             'invite' => t('Vaiko kvietimas'), 'change_email' => t('El. pašto keitimas'), 'join' => t('Kvietimas nariui susikurti paskyrą')];
try {
    $devices = (int) q_value('SELECT COUNT(*) FROM device_codes WHERE revoked_at IS NULL AND ((used_at IS NULL AND expires_at > NOW()) OR session_until > NOW())');
} catch (PDOException $ex) {
    $devices = 0;
}
?>
<section class="panel card lk-kind" id="kiti">
  <div class="kicker"><?= te('Kiti raktai (tik peržiūrai)') ?></div>
  <ul class="set-map">
    <?php foreach ($mailName as $p => $n): ?>
      <li><span class="set-l"><?= e($n) ?> <span class="muted small"><?= te('- laiške, vienkartinė, galioja') ?> <?= e(str_replace(['+', ' days', ' day', ' hour'], ['', ' d.', ' d.', ' val.'], TOKEN_TTL[$p])) ?></span></span>
        <span class="set-v"><?= (int) ($mail[$p]['n'] ?? 0) ?> <?= te('veikia') ?></span><span class="set-w"></span></li>
    <?php endforeach; ?>
    <li><span class="set-l"><?= te('Kodas kitam įrenginiui') ?></span><span class="set-v"><?= $devices ?> <?= te('veikia') ?></span>
      <span class="set-w"><a href="<?= e(url('admin/kitas-irenginys.php')) ?>"><?= te('Keisti →') ?></a></span></li>
    <li><span class="set-l"><?= te('API prieigos raktas (atsiliepimai, peržiūra)') ?></span><span class="set-v"><?= site_setting('feedback_api_key', '') ? te('įjungta') : te('nėra') ?></span>
      <span class="set-w"><a href="<?= e(url('admin/atsiliepimai.php')) ?>"><?= te('Keisti →') ?></a></span></li>
  </ul>
</section>
<?php page_end(); ?>
