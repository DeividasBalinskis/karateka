<?php
// Atsiliepimų langas (app/feedback.php, assets/feedback.js): išsaugojimas iš karto, nuotraukos (Ctrl+V), sąrašas, taisymas.
// Atsakymai - JSON. ?widget=1 - lango HTML kitoms svetainėms (LTKF), ?img=ID - nuotrauka (tik autoriui ir adminui).
// Be JS (senas būdas) - forma su laukeliu „body“, grįžtama į tą patį puslapį.
require __DIR__ . '/app/bootstrap.php';

$a = current_account();
$json = function (array $d, int $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    exit(json_encode($d, JSON_UNESCAPED_UNICODE));
};

// Nuotrauka: autoriui, adminui (ar treneriui) ir Claude (su raktu)
if (get('img')) {
    $img = feedback_images_ready() ? q_one('SELECT i.*, f.account_id FROM feedback_images i JOIN feedback f ON f.id = i.feedback_id WHERE i.id = ?', [(int) get('img')]) : null;
    $f = $img ? q_one('SELECT * FROM feedback WHERE id = ?', [(int) $img['feedback_id']]) : null;
    if (!$img || !($f && (feedback_can_edit($a, $f) || is_staff($a) || ($a && (int) $f['account_id'] === (int) $a['id']) || feedback_api_check((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''))))) {
        not_found();
    }
    $path = FEEDBACK_IMG_DIR . '/' . (get('thumb') ? 'thumb_' : '') . basename($img['file']);
    if (!is_file($path)) {
        not_found();
    }
    header('Content-Type: image/jpeg');
    header('Cache-Control: private, max-age=86400');
    readfile($path);
    exit;
}

// Lango HTML kitai svetainei (LTKF tame pačiame serveryje - ta pati sesija)
if (get('widget')) {
    $site = isset(FEEDBACK_SITES[get('site')]) ? get('site') : 'karateka';
    $json(['ok' => true, 'html' => feedback_widget_html($site)]);
}

// Mano atsiliepimai (sąrašas lange)
if (get('list')) {
    if (!feedback_ready()) {
        $json(['ok' => true, 'items' => []]);
    }
    feedback_writer();   // žymė naršyklei (jei dar nėra)
    $rows = get('all') ? feedback_all($a) : feedback_mine($a);
    feedback_images_prefetch(array_column($rows, 'id'));
    // v1.87: kur žmogus yra dabar (pirmiausia rodomi šios vietos atsiliepimai) - tas pats skirstymas kaip atsiliepimams
    $hereSite = isset(FEEDBACK_SITES[get('site')]) ? (string) get('site') : 'karateka';
    $here = feedback_area($hereSite, mb_substr((string) get('here'), 0, 255), ['url' => (string) get('here'), 'sk' => (string) get('sk'), 'sec' => (string) get('sec')]);
    $json(['ok' => true, 'here' => $here, 'groups' => FEEDBACK_GROUPS, 'items' => array_map('feedback_json', $rows)]);
}

if (!is_post()) {
    redirect('index.php');
}
csrf_check();
$wantsJson = stripos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
if (!feedback_ready() || feedback_mode() === 'off' || (feedback_mode() === 'members' && !$a && !varz_demo() && !varz_sekr() && !(post('site') === 'ltkf' && ltkf_review()))) {
    $wantsJson ? $json(['ok' => false, 'error' => t('Atsiliepimai dar neįjungti.')], 403) : redirect('index.php');
}
$page = (string) post('page');
$page = preg_match('~^/[^\s<>"]*$~', $page) ? $page : '';
$site = isset(FEEDBACK_SITES[post('site')]) ? post('site') : 'karateka';
$action = post('action') ?: 'legacy';

/** Atsiliepimas, kurį šis žmogus gali taisyti (arba klaida) */
$own = function (int $id) use ($a, $json) {
    $f = $id ? q_one('SELECT * FROM feedback WHERE id = ?', [$id]) : null;
    if (!$f || !feedback_can_edit($a, $f)) {
        $json(['ok' => false, 'error' => t('Atsiliepimas nerastas.')], 404);
    }
    return $f;
};

switch ($action) {
    case 'save':   // rašant - išsaugoma iš karto (naujas sukuriamas pirmą kartą)
        $body = trim(str_replace("\r\n", "\n", (string) post('body')));
        $id = (int) post('id');
        if ($id) {
            $f = $own($id);
            q('UPDATE feedback SET body = ?, updated_at = NOW() WHERE id = ?', [mb_substr($body, 0, FEEDBACK_MAX), $id]);
        } else {
            if (trim((string) post('website')) !== '') {
                $json(['ok' => true, 'item' => null]);   // robotas
            }
            if ($body === '') {
                $json(['ok' => true, 'item' => null]);
            }
            $id = feedback_add($a, $body, $page, (string) post('name'), $site, (string) post('context') ?: null, true);
            if (is_string($id)) {
                $json(['ok' => false, 'error' => $id], 429);
            }
        }
        $json(['ok' => true, 'item' => feedback_json(q_one('SELECT * FROM feedback WHERE id = ?', [$id]))]);

    case 'image':   // nuotrauka (įklijuota Ctrl+V, nutempta ar pasirinkta); be atsiliepimo - sukuriamas naujas
        if (!feedback_images_ready()) {
            $json(['ok' => false, 'error' => t('Nuotraukos dar neįjungtos - atnaujinkite duomenų bazę.')], 400);
        }
        $id = (int) post('id');
        if ($id) {
            $own($id);
        } else {
            $id = feedback_add($a, (string) post('body'), $page, (string) post('name'), $site, (string) post('context') ?: null, true);
            if (is_string($id)) {
                $json(['ok' => false, 'error' => $id], 429);
            }
        }
        try {
            feedback_add_image($id, $_FILES['file'] ?? []);
        } catch (RuntimeException $ex) {
            $json(['ok' => false, 'error' => $ex->getMessage(), 'item' => feedback_json(q_one('SELECT * FROM feedback WHERE id = ?', [$id]))], 400);
        }
        $json(['ok' => true, 'item' => feedback_json(q_one('SELECT * FROM feedback WHERE id = ?', [$id]))]);

    case 'verdict':   // v1.89: autorius įvertina pataisymą - „ok“ (paliekama) arba „revert“ (Claude grąžins kaip buvo)
        $f = q_one('SELECT * FROM feedback WHERE id = ?', [(int) post('id')]);
        $v = (string) post('verdict');
        if (!$f || !in_array($v, ['ok', 'revert'], true) || !feedback_can_verdict($a, $f)) {
            $json(['ok' => false, 'error' => t('Atsiliepimas nerastas.')], 404);
        }
        q('UPDATE feedback SET verdict = ?, verdict_at = NOW(), updated_at = NOW() WHERE id = ?', [$v, (int) $f['id']]);
        $json(['ok' => true, 'item' => feedback_json(q_one('SELECT * FROM feedback WHERE id = ?', [(int) $f['id']]))]);

    case 'image_delete':
        $f = $own((int) post('id'));
        feedback_delete_images((int) $f['id'], (int) post('image'));
        $json(['ok' => true, 'item' => feedback_json($f)]);

    case 'delete':
        $f = $own((int) post('id'));
        feedback_delete_images((int) $f['id']);
        q('DELETE FROM feedback WHERE id = ?', [(int) $f['id']]);
        $json(['ok' => true]);

    default:   // senas būdas (be JS): forma su „body“
        $result = trim((string) post('website')) !== '' ? 0 : feedback_add($a, (string) post('body'), $page, (string) post('name'), $site);
        if ($wantsJson) {
            is_string($result) ? $json(['ok' => false, 'error' => $result], 422) : $json(['ok' => true, 'message' => t('Ačiū! Gavome - pažiūrėsime ir pataisysime.')]);
        }
        flash(is_string($result) ? 'err' : 'ok', is_string($result) ? $result : t('Ačiū! Gavome - pažiūrėsime ir pataisysime.'));
        redirect($page !== '' ? ltrim($page, '/') : 'index.php');
}
