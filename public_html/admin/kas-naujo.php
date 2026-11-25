<?php
// „Kas naujo“ (planas C9): 3 naujausios versijos iš pakeitimų sąrašo - trumpai, su skyriais. Atsidaro paspaudus versijos numerį (tik treneriams ir adminams).
// Sąrašas: app/pakeitimai.md (į paketą įdedamas iš docs/pakeitimai.md); kompiuteryje - tiesiai iš docs/.
require dirname(__DIR__) . '/app/bootstrap.php';
require_can('valdymas');

$file = is_file(APP_DIR . '/pakeitimai.md') ? APP_DIR . '/pakeitimai.md' : dirname(PUBLIC_DIR) . '/docs/pakeitimai.md';
$text = is_file($file) ? (string) file_get_contents($file) : '';
$versions = [];
foreach (preg_split('/^## /m', $text) as $i => $chunk) {
    if ($i === 0 || !preg_match('/^v\d/', $chunk)) {
        continue;
    }
    $versions[] = $chunk;
    if (count($versions) >= 3) {
        break;
    }
}

/** Paprastas markdown → HTML: **paryškinta**, `kodas`, sąrašai, skyrių antraštės */
function kn_inline(string $s): string
{
    $s = e($s);
    $s = preg_replace('/\*\*(.+?)\*\*/u', '<b>$1</b>', $s);
    return preg_replace('/`([^`]+)`/u', '<code>$1</code>', $s);
}

page_start(t('Kas naujo'), ['admin' => true]);
?>
<div class="page-head"><h1 class="styled"><?= te('Kas naujo') ?></h1></div>
<p class="muted"><?= te('Trys naujausios versijos. Dabartinė:') ?> <b>v<?= e(site_version()) ?></b></p>
<?php if (!$versions): ?>
  <div class="panel card"><p class="muted"><?= te('Pakeitimų sąrašas nerastas.') ?></p></div>
<?php endif; ?>
<?php foreach ($versions as $chunk):
    $lines = explode("\n", rtrim($chunk));
    $title = array_shift($lines);
    $open = false; ?>
  <section class="panel card kn-ver">
    <?php $sub = '';
    if (preg_match('/^(v[\d.]+)\s*[-–]\s*(\d{4}-\d{2}-\d{2})\s*(?:\((.+)\))?\s*$/u', $title, $m)) {
        $title = $m[1] . ' · ' . $m[2];
        $sub = $m[3] ?? '';
    } ?>
    <h2><?= kn_inline($title) ?></h2>
    <?php if ($sub !== ''): ?><p class="kn-sub"><?= kn_inline($sub) ?></p><?php endif; ?>
    <?php foreach ($lines as $l):
        if (trim($l) === '') { continue; }
        if (preg_match('/^\*\*(.+)\*\*\s*$/u', trim($l), $m)) {
            echo ($open ? '</ul>' : '') . '<h3>' . kn_inline($m[1]) . '</h3><ul>';
            $open = true;
        } elseif (preg_match('/^- (.+)$/u', $l, $m)) {
            echo ($open ? '' : '<ul>') . '<li>' . kn_inline($m[1]);
            $open = true;
        } elseif (preg_match('/^\s{2,}(.+)$/u', $l, $m)) {
            echo ' ' . kn_inline($m[1]);
        } else {
            echo ($open ? '</ul>' : '') . '<p class="small">' . kn_inline($l) . '</p>';
            $open = false;
        }
    endforeach;
    echo $open ? '</ul>' : ''; ?>
  </section>
<?php endforeach; ?>
<?php page_end();
