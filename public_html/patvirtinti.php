<?php
// El. pašto patvirtinimas iš laiško nuorodos
require __DIR__ . '/app/bootstrap.php';

$token = token_find('verify_email', get('t'));
page_start(t('El. pašto patvirtinimas'), ['narrow' => true, 'noindex' => true]);

if (!$token) { ?>
  <div class="panel card">
    <h1><?= te('Nuoroda nebegalioja') ?></h1>
    <p class="muted"><?= te('Nuoroda jau panaudota arba pasibaigė. Pabandykite prisijungti - jei el. paštas dar nepatvirtintas, galėsite gauti naują nuorodą.') ?></p>
    <p style="margin-top:18px;"><a class="btn btn-primary" href="<?= url('prisijungti.php') ?>"><?= te('Prisijungti') ?></a></p>
  </div>
<?php
    page_end();
    exit;
}

token_use((int) $token['id']);
q('UPDATE accounts SET email_verified_at = NOW() WHERE id = ? AND email_verified_at IS NULL', [$token['account_id']]);
advance_account_status((int) $token['account_id']);
$a = q_one('SELECT * FROM accounts WHERE id = ?', [$token['account_id']]);
?>
<div class="panel card">
  <h1 class="styled"><?= te('El. paštas patvirtintas') ?></h1>
  <?php if ($a['status'] === 'pending_parent'): ?>
    <p><?= te('Ačiū! Dar laukiame tėvų sutikimo. Tėvams išsiuntėme laišką su nuoroda - kai jie patvirtins, paskyrą peržiūrės treneris.') ?></p>
  <?php else: ?>
    <?php if ($a['status'] === 'pending_approval'): ?>
      <p><?= te('Ačiū! Dabar paskyrą peržiūrės treneris ir priskirs grupę. Kai paskyra bus patvirtinta, gausite laišką.') ?></p>
    <?php else: ?>
      <p><?= te('Paskyra jau aktyvi.') ?></p>
    <?php endif; ?>
    <p style="margin-top:18px;"><a class="btn btn-primary" href="<?= url('prisijungti.php') ?>"><?= te('Prisijungti') ?></a></p>
  <?php endif; ?>
</div>
<?php
page_end();
