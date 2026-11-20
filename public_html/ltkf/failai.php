<?php
// Failų dėžė: redaktoriai įmeta failus (nuotraukas, dokumentus) su žinute, kur juos dėti; adminas peržiūri, atsisiunčia ir pažymi „Įdėta“.
require __DIR__ . '/inc/isdestymas.php';

$who = ltkf_redaktorius();
$files = [];
if ($who && ($db = karateka_db())) {
    try {
        $files = $db->query('SELECT * FROM ltkf_files ORDER BY done_at IS NOT NULL, id DESC LIMIT 300')->fetchAll();
    } catch (Throwable $ex) {
        $files = null;   // dar be db/038
    }
}
puslapis_pradzia('Failų dėžė');
?>
<section class="page-head"><div class="wrap">
  <p class="eyebrow">Redaktoriams</p>
  <h1>📥 Failų dėžė</h1>
  <p class="lead">Neradote, kur įdėti? Įmeskite failus čia ir parašykite, kam jie skirti - vėliau sudėsime į reikiamą vietą.</p>
</div></section>
<section class="wrap section">
<?php if (!$who): ?>
  <p class="empty">Failų dėžė - tik redaktoriams. Atidarykite savo redaktoriaus nuorodą.</p>
<?php elseif ($files === null): ?>
  <p class="empty">Failų dėžė veiks, kai bus atnaujinta duomenų bazė (karateka.lt → Valdymas → „Atnaujinkite duomenų bazę“).</p>
<?php else: ?>
  <form class="inbox-form side-box" id="inboxForm">
    <label class="inbox-drop" id="inboxDrop"><input type="file" name="files[]" multiple id="inboxFiles"><b>Pasirinkite failus</b> arba nutempkite juos čia<br><small class="muted">Nuotraukos, PDF, Word, Excel… galima kelis iš karto</small></label>
    <div id="inboxList" class="muted"></div>
    <label>Žinutė (kam skirta, kur dėti)<textarea name="note" rows="3" placeholder="pvz. „Nuotraukos iš 2025 m. čempionato - į naujienas“"></textarea></label>
    <button class="btn btn-primary" type="submit">Įmesti į dėžę</button> <span id="inboxStatus" class="muted"></span>
  </form>

  <h2 class="mt">Įmesta (<?= count($files) ?>)</h2>
  <?php if (!$files): ?><p class="empty">Kol kas tuščia.</p><?php endif; ?>
  <ul class="inbox-list">
    <?php foreach ($files as $f): ?>
      <li class="<?= $f['done_at'] ? 'is-done' : '' ?>">
        <?php if ($f['file'] !== ''): ?>
          <span class="doc-icon" aria-hidden="true"><?= e(strtoupper(substr(pathinfo((string) $f['orig'], PATHINFO_EXTENSION) ?: 'F', 0, 4))) ?></span>
          <span class="inbox-main"><a href="redaguoti.php?get=<?= (int) $f['id'] ?>"><?= e($f['orig']) ?></a> <small class="muted"><?= e(ltkf_size((int) $f['size'])) ?></small>
        <?php else: ?>
          <span class="doc-icon" aria-hidden="true">✉</span><span class="inbox-main"><b>Žinutė</b>
        <?php endif; ?>
          <?php if ($f['note']): ?><br><span><?= nl2br(e($f['note'])) ?></span><?php endif; ?>
          <br><small class="muted"><?= e((string) $f['uploaded_by']) ?> · <?= e(substr((string) $f['created_at'], 0, 16)) ?><?= $f['done_at'] ? ' · ✓ įdėta' : '' ?></small></span>
        <span class="inbox-acts">
          <button type="button" class="btn btn-ghost-dark" data-done="<?= (int) $f['id'] ?>"><?= $f['done_at'] ? '↺ Neįdėta' : '✓ Įdėta' ?></button>
          <button type="button" class="btn btn-ghost-dark" data-del="<?= (int) $f['id'] ?>">🗑</button>
        </span>
      </li>
    <?php endforeach; ?>
  </ul>
  <script>
  (function () {
    var csrf = <?= json_encode((string) (karateka_sesija()['csrf'] ?? '')) ?>, form = document.getElementById('inboxForm'), input = document.getElementById('inboxFiles'), drop = document.getElementById('inboxDrop');
    var picked = [];
    function list() { document.getElementById('inboxList').textContent = picked.length ? 'Pasirinkta: ' + picked.map(function (f) { return f.name; }).join(', ') : ''; }
    input.addEventListener('change', function () { picked = Array.prototype.slice.call(input.files); list(); });
    ['dragover', 'dragenter'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('over'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('over'); }); });
    drop.addEventListener('drop', function (e) { picked = picked.concat(Array.prototype.slice.call(e.dataTransfer.files)); list(); });
    function post(fd) { fd.append('_csrf', csrf); return fetch('redaguoti.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(); fd.append('action', 'inbox'); fd.append('note', form.note.value);
      picked.forEach(function (f) { fd.append('files[]', f); });
      document.getElementById('inboxStatus').textContent = 'Keliama…';
      post(fd).then(function (d) { document.getElementById('inboxStatus').textContent = d.ok ? '✓ Įmesta: ' + d.count + (d.error ? ' (' + d.error + ')' : '') : (d.error || 'Nepavyko'); if (d.ok) setTimeout(function () { location.reload(); }, 600); });
    });
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-done],[data-del]'); if (!b) return;
      if (b.dataset.del && !confirm('Ištrinti?')) return;
      var fd = new FormData(); fd.append('action', b.dataset.del ? 'inbox_delete' : 'inbox_done'); fd.append('id', b.dataset.del || b.dataset.done);
      post(fd).then(function () { location.reload(); });
    });
  })();
  </script>
<?php endif; ?>
</section>
<?php puslapis_pabaiga();
