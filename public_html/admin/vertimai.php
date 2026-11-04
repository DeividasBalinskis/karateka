<?php
// Vertimai: Denio tekstai anglų ir rusų kalbomis. Denis įklijuoja vertimą pats (pvz. išsivertęs su AI) - lankytojai skaito pasirinkta kalba.
// ?e=news|event|lesson|home&id=N - vieno įrašo tekstai; be parametrų - sąrašas, kas išversta.
require dirname(__DIR__) . '/app/bootstrap.php';
current_lang('lt');   // originalai rodomi lietuviškai
$me = require_staff();

const TR_ENTITIES = ['home' => 'Pagrindinis puslapis', 'news' => 'Naujiena', 'event' => 'Renginys', 'lesson' => 'Pamoka'];
$entity = isset(TR_ENTITIES[get('e')]) ? get('e') : (isset(TR_ENTITIES[post('e')]) ? post('e') : '');
$id = (int) (get('id') ?: post('id'));
$back = 'admin/vertimai.php' . ($entity ? '?e=' . $entity . ($id ? '&id=' . $id : '') : '');

if (is_post() && $entity && post('action') === 'auto') {
    csrf_check();
    $n = tr_auto_entity($entity, $id, post('overwrite') === '1');
    flash($n || !tr_auto_enabled() ? 'ok' : 'err', tr_auto_enabled() ? ($n ? 'Išversta automatiškai: ' . $n . '. Peržiūrėkite ir, jei reikia, pataisykite.' : 'Nieko neišversta - viskas jau išversta arba vertimo paslauga neatsakė.') : 'Automatinis vertimas neįjungtas.');
    redirect($back);
}

if (is_post() && $entity) {
    csrf_check();
    $items = tr_items($entity, $id);
    $saved = 0;
    foreach ($items as $i => $it) {
        foreach (['en', 'ru'] as $lang) {
            $val = (string) ($_POST[$lang][$i] ?? '');
            tr_save($it['text'], $lang, $val);
            $saved += trim($val) !== '' ? 1 : 0;
        }
    }
    flash('ok', 'Vertimai išsaugoti (' . $saved . ').');
    redirect($back);
}

/** Kiek tekstų išversta: [išversta abiem kalbomis, iš viso] */
function tr_progress(string $entity, int $id): array
{
    $items = tr_items($entity, $id);
    $done = 0;
    foreach ($items as $it) {
        $h = md5($it['text']);
        $done += isset(tr_map('en')[$h], tr_map('ru')[$h]) ? 1 : 0;
    }
    return [$done, count($items)];
}

function tr_badge(array $p): string
{
    [$done, $all] = $p;
    return '<span class="badge ' . ($all && $done === $all ? 'badge-ok' : '') . '">' . $done . ' / ' . $all . '</span>';
}

$ready = tr_ready();
page_start('Vertimai', ['admin' => true]);
?>
<a class="small" href="<?= url($entity ? 'admin/vertimai.php' : 'admin/nustatymai.php') ?>"><?= $entity ? '← Visi vertimai' : '← Nustatymai' ?></a>
<h1 class="styled" style="margin-top:8px;">Vertimai (EN / RU)</h1>
<?php if (!$ready): ?>
  <div class="flash flash-err">Duomenų bazė neatnaujinta - importuokite <code>db/024_vertimai.sql</code>.</div>
<?php endif; ?>

<?php if (!$entity): ?>
  <p class="muted">Mygtukai, meniu ir kiti svetainės tekstai jau išversti. Jūsų tekstai (naujienos, renginiai, pamokos, pagrindinis puslapis):</p>
  <?php if (tr_auto_enabled()): ?>
    <div class="flash flash-ok">✓ Automatinis vertimas įjungtas (DeepL): išsaugojus naujieną, renginį, pamoką ar pagrindinį puslapį, vertimai į EN ir RU atsiranda patys.
      Čia galite juos peržiūrėti ir pataisyti.</div>
  <?php else: ?>
    <div class="flash flash-info">Automatinis vertimas neįjungtas - vertimus įklijuokite patys (pvz. iš ChatGPT ar Claude). Kol vertimo nėra, rodoma lietuviškai.
      Įjungti nemokamai: DeepL API Free raktas į <code>config.php</code> (žr. <code>config.example.php</code>).</div>
  <?php endif; ?>
  <div class="grid-2 tr-lists">
    <div class="panel card">
      <div class="kicker">Pagrindinis puslapis</div>
      <ul class="list small"><li class="row between"><a href="<?= url('admin/vertimai.php?e=home') ?>">Visi pagrindinio puslapio tekstai</a><?= $ready ? tr_badge(tr_progress('home', 0)) : '' ?></li></ul>
      <div class="kicker" style="margin-top:14px;">Naujienos</div>
      <ul class="list small">
        <?php foreach (q_all('SELECT id, title FROM news ORDER BY published_at DESC LIMIT 15') as $n): ?>
          <li class="row between"><a href="<?= url('admin/vertimai.php?e=news&id=' . (int) $n['id']) ?>"><?= e($n['title']) ?></a><?= $ready ? tr_badge(tr_progress('news', (int) $n['id'])) : '' ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="panel card">
      <div class="kicker">Renginiai</div>
      <ul class="list small">
        <?php foreach (q_all('SELECT id, title, starts_on FROM events WHERE COALESCE(ends_on, starts_on) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) ORDER BY starts_on LIMIT 20') as $ev): ?>
          <li class="row between"><a href="<?= url('admin/vertimai.php?e=event&id=' . (int) $ev['id']) ?>"><?= e($ev['title']) ?> <span class="muted"><?= e(fmt_date($ev['starts_on'])) ?></span></a><?= $ready ? tr_badge(tr_progress('event', (int) $ev['id'])) : '' ?></li>
        <?php endforeach; ?>
      </ul>
      <div class="kicker" style="margin-top:14px;">Pamokos</div>
      <ul class="list small">
        <?php foreach (q_all('SELECT id, title FROM lessons WHERE is_published = 1 ORDER BY belt_level IS NULL, belt_level, sort_order, id LIMIT 40') as $l): ?>
          <li class="row between"><a href="<?= url('admin/vertimai.php?e=lesson&id=' . (int) $l['id']) ?>"><?= e($l['title']) ?></a><?= $ready ? tr_badge(tr_progress('lesson', (int) $l['id'])) : '' ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php else: $items = tr_items($entity, $id); ?>
  <p class="muted"><?= e(TR_ENTITIES[$entity]) ?>. Įklijuokite vertimus ir išsaugokite. Pakeitus lietuvišką tekstą, vertimą reikės atnaujinti.</p>
  <?php if (!$items): ?>
    <p class="muted">Verčiamų tekstų nėra.</p>
  <?php else: ?>
    <?php if (tr_auto_enabled()): ?>
      <form method="post" class="tr-ai panel card">
        <?= csrf_field() ?><input type="hidden" name="e" value="<?= e($entity) ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="auto">
        <strong>Automatinis vertimas:</strong>
        <button class="btn btn-ghost btn-sm" type="submit">🤖 Išversti trūkstamus</button>
        <button class="btn btn-ghost btn-sm" type="submit" name="overwrite" value="1" onclick="return confirm('Išversti iš naujo visus tekstus? Jūsų pataisymai bus pakeisti.')">↻ Išversti viską iš naujo</button>
      </form>
    <?php endif; ?>
    <div class="tr-ai panel card">
      <strong>Su ChatGPT ar Claude:</strong> paspauskite „Kopijuoti“, įklijuokite į ChatGPT ar Claude, tada vertimus sudėkite į laukelius.
      <button type="button" class="btn btn-ghost btn-sm" id="trCopy">📋 Kopijuoti tekstus vertimui</button>
      <textarea id="trPrompt" hidden><?= e("Išversk šiuos karate klubo svetainės tekstus į anglų ir rusų kalbas. Išlaikyk numeraciją ir eilučių skaidymą; vardus, vietovardžius ir japoniškus terminus palik.\n\n"
          . implode("\n\n", array_map(function ($it, $i) { return '[' . ($i + 1) . '] ' . $it['text']; }, $items, array_keys($items)))) ?></textarea>
    </div>
    <form method="post" class="tr-form">
      <?= csrf_field() ?><input type="hidden" name="e" value="<?= e($entity) ?>"><input type="hidden" name="id" value="<?= $id ?>">
      <?php foreach ($items as $i => $it): $h = md5($it['text']); ?>
        <div class="panel card tr-item">
          <div class="kicker">[<?= $i + 1 ?>] <?= e($it['label']) ?></div>
          <div class="tr-src"><?= nl2br(e($it['text'])) ?></div>
          <div class="tr-langs">
            <?php foreach (['en' => 'English', 'ru' => 'Русский'] as $lang => $name): $dict = t_in($lang, $it['text']); ?>
              <label><?= $name ?>
                <?php if ($it['multi']): ?>
                  <textarea name="<?= $lang ?>[<?= $i ?>]" rows="<?= min(12, max(3, substr_count($it['text'], "\n") + 3)) ?>" placeholder="<?= e($dict ?? '') ?>"><?= e(tr_map($lang)[$h] ?? '') ?></textarea>
                <?php else: ?>
                  <input type="text" name="<?= $lang ?>[<?= $i ?>]" value="<?= e(tr_map($lang)[$h] ?? '') ?>" placeholder="<?= e($dict ?? '') ?>">
                <?php endif; ?>
                <?php if ($dict !== null && !isset(tr_map($lang)[$h])): ?><span class="hint">išversta automatiškai (galite pakeisti)</span><?php endif; ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <div class="tr-save"><button class="btn btn-primary" type="submit">Išsaugoti vertimus</button></div>
    </form>
    <script>
      document.getElementById('trCopy').addEventListener('click', function () {
        var t = document.getElementById('trPrompt').value, b = this;
        (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(function () { b.textContent = '✓ Nukopijuota'; })
          .catch(function () { var a = document.getElementById('trPrompt'); a.hidden = false; a.select(); });
      });
    </script>
  <?php endif; ?>
<?php endif; ?>
<?php
page_end();
