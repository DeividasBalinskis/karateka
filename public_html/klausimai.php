<?php
// Klausimai ir atsakymai: bendri narių klausimai (iš pokalbio su Deniu), kuriuos Denis paviešino. Matoma prisijungusiems.
require __DIR__ . '/app/bootstrap.php';

$a = require_login();
$public = public_questions(null);
page_start('Klausimai ir atsakymai', ['noindex' => true]);
?>
<div class="page-head">
  <h1 class="styled">Klausimai ir atsakymai</h1>
  <p>Narių klausimai Deniui ir jo atsakymai, kuriuos jis paviešino. Klaususiojo vardas rodomas tik jei jis to norėjo.</p>
</div>
<div class="panel card q-box">
  <?php if (!$public): ?>
    <p class="muted">Paviešintų klausimų dar nėra. Savo klausimą galite parašyti Deniui - mygtukas apačioje dešinėje.</p>
  <?php else: ?>
    <?= render_public_questions(array_reverse($public)) ?>
  <?php endif; ?>
</div>
<?php
page_end();
