<?php
// Pagrindinio puslapio redagavimo veiksmai (tik adminui): išsaugoti, įkelti nuotrauką, grąžinti ankstesnę versiją ar pradinį bloką.
// Pats redagavimas - pagrindiniame puslapyje: index.php?redaguoti=1
require dirname(__DIR__) . '/app/bootstrap.php';
require_once APP_DIR . '/images.php';
$me = require_admin();

if (!is_post()) {
    redirect('index.php?redaguoti=1');
}
csrf_check();
header('Content-Type: application/json; charset=utf-8');

function out(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$action = post('action');

if ($action === 'save') {
    $blocks = json_decode((string) ($_POST['page'] ?? ''), true);
    if (!is_array($blocks) || !$blocks) {
        out(['ok' => false, 'error' => 'Nepavyko perskaityti puslapio - atnaujinkite ir bandykite dar kartą.']);
    }
    home_save($blocks);
    out(['ok' => true]);
}

if ($action === 'upload') {
    try {
        $name = save_uploaded_image($_FILES['image'] ?? [], HOME_DIR);
    } catch (RuntimeException $ex) {
        out(['ok' => false, 'error' => $ex->getMessage()]);
    }
    out(['ok' => true, 'path' => 'uploads/site/' . $name, 'url' => url('uploads/site/' . $name)]);
}

if ($action === 'undo') {
    $prev = (string) site_setting('home_page_prev', '');
    $cur = (string) site_setting('home_page', '');
    if ($prev === '' && $cur === '') {
        out(['ok' => false, 'error' => 'Ankstesnės versijos nėra.']);
    }
    // Sukeičiama: dabartinė tampa „ankstesne“, todėl paspaudus dar kartą grįžtama atgal
    set_site_setting('home_page', $prev);
    set_site_setting('home_page_prev', $cur);
    out(['ok' => true]);
}

if ($action === 'reset_block') {
    $page = home_page(true);
    $found = false;
    foreach ($page as &$b) {
        if ($b['id'] === post('id')) {
            $b['d'] = home_block_defaults($b['type']);
            $found = true;
        }
    }
    unset($b);
    if (!$found) {
        out(['ok' => false, 'error' => 'Blokas nerastas - pirmiausia išsaugokite puslapį.']);
    }
    home_save($page);
    out(['ok' => true]);
}

out(['ok' => false, 'error' => 'Nežinomas veiksmas.']);
