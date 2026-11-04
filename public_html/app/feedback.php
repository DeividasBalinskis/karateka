<?php
// Atsiliepimai apie svetainę (db/025): mygtukas „💡 Atsiliepimas“ apačioje kairėje - kiekvienas gali parašyti, kas negerai ar ką pakeisti.
// Adminas mato visus: Valdymas → Atsiliepimai (būsena, atsakymas autoriui). Kas mato mygtuką - ten pat (visi / tik prisijungę / niekas).

const FEEDBACK_STATUS = [
    'new'      => ['Naujas', 'badge-pink'],
    'planned'  => ['Bus pataisyta', 'badge-warn'],
    'done'     => ['Pataisyta', 'badge-ok'],
    'declined' => ['Nedarysime', ''],
];
const FEEDBACK_MAX = 4000;
const FEEDBACK_SHOW = ['all' => 'Visiems (ir neprisijungusiems)', 'members' => 'Tik prisijungusiems', 'off' => 'Niekam (išjungta)'];

/** Kam rodomas mygtukas: all | members | off (numatyta: test svetainėje - visiems, oficialioje - prisijungusiems) */
function feedback_mode(): string
{
    $mode = (string) site_setting('feedback_button', '');
    return isset(FEEDBACK_SHOW[$mode]) ? $mode : (config('env') === 'live' ? 'members' : 'all');
}

function feedback_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT 1 FROM feedback LIMIT 1');
            $ok = true;
        } catch (PDOException $ex) {
            $ok = false;   // dar neimportuotas db/025
        }
    }
    return $ok;
}

/** Nauji (dar neperžiūrėti) atsiliepimai - adminui raudonas skaičiukas */
function feedback_new_count(): int
{
    return feedback_ready() ? (int) q_value('SELECT COUNT(*) FROM feedback WHERE status = "new"') : 0;
}

function feedback_status_badge(string $status): string
{
    [$label, $class] = FEEDBACK_STATUS[$status] ?? FEEDBACK_STATUS['new'];
    return '<span class="badge ' . $class . '">' . e(t($label)) . '</span>';
}

/** Įrašo atsiliepimą. Grąžina id arba klaidos tekstą (string). */
function feedback_add(?array $a, string $body, string $page, string $name = '')
{
    $body = trim(str_replace("\r\n", "\n", $body));
    if (mb_strlen($body) < 3) {
        return t('Parašykite bent kelis žodžius.');
    }
    // Saugiklis nuo šlamšto: ne daugiau 10 per 10 min. iš vienos sesijos, neprisijungusių - ne daugiau 60 per valandą iš viso
    $_SESSION['fb_times'] = array_values(array_filter((array) ($_SESSION['fb_times'] ?? []), function ($t) { return $t > time() - 600; }));
    if (count($_SESSION['fb_times']) >= 10
        || (!$a && (int) q_value('SELECT COUNT(*) FROM feedback WHERE account_id IS NULL AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)') >= 60)) {
        return t('Per daug atsiliepimų per trumpą laiką - pabandykite vėliau.');
    }
    $_SESSION['fb_times'][] = time();
    q('INSERT INTO feedback (account_id, name, page, body) VALUES (?, ?, ?, ?)',
        [$a ? (int) $a['id'] : null, $a ? null : (mb_substr(trim($name), 0, 120) ?: null), mb_substr($page, 0, 255) ?: null, mb_substr($body, 0, FEEDBACK_MAX)]);
    return (int) db()->lastInsertId();
}

/** Kuriame puslapyje rašoma (be svetainės adreso pradžios), pvz. „/paskyra.php?skiltis=nustatymai“ */
function feedback_current_page(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $base = (string) (config('base_path') ?: '');
    if ($base !== '' && strpos($uri, $base . '/') === 0) {
        $uri = substr($uri, strlen($base));
    }
    return mb_substr($uri, 0, 255);
}

/** Vieno atsiliepimo eilutė autoriaus sąraše (mygtuko lange) */
function feedback_mine_item(array $f): string
{
    return '<li class="fb-item"><div class="fb-item-top"><span class="fb-when">' . e(fmt_date(substr($f['created_at'], 0, 10))) . '</span>' . feedback_status_badge($f['status']) . '</div>'
        . '<div class="fb-body">' . nl2br(e(mb_strimwidth($f['body'], 0, 400, '…'))) . '</div>'
        . ($f['reply'] ? '<div class="fb-reply"><strong>' . te('Atsakymas:') . '</strong> ' . nl2br(e($f['reply'])) . '</div>' : '') . '</li>';
}

/** Mygtukas ir langas (visuose puslapiuose, apačioje kairėje) */
function feedback_widget_html(): string
{
    $a = current_account();
    $mode = feedback_mode();
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (!feedback_ready() || $mode === 'off' || ($mode === 'members' && !$a) || in_array($script, ['testas.php', 'egzaminas.php'], true)) {
        return '';
    }
    $mine = $a ? q_all('SELECT * FROM feedback WHERE account_id = ? ORDER BY created_at DESC LIMIT 8', [(int) $a['id']]) : [];
    $page = feedback_current_page();
    $icon = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12 2a7 7 0 0 0-4 12.74V17a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-2.26A7 7 0 0 0 12 2Zm-3 18a1 1 0 0 0 1 1h4a1 1 0 0 0 0-2h-4a1 1 0 0 0-1 1Z"/></svg>';
    return '<div class="fb" id="fb">'
        . '<button type="button" class="fb-fab" id="fbFab" aria-expanded="false" aria-controls="fbPanel" title="' . e(t('Palikite komentarą - pataisysime')) . '">' . $icon
        . '<span class="fb-fab-label">' . te('Atsiliepimas') . '</span></button>'
        . '<div class="fb-panel" id="fbPanel" role="dialog" aria-labelledby="fbTitle" hidden>'
        . '<div class="fb-head"><strong id="fbTitle">' . te('Atsiliepimas') . '</strong><button type="button" class="fb-close" aria-label="' . e(t('Uždaryti')) . '">×</button></div>'
        . '<div class="fb-scroll">'
        . '<p class="fb-lead">' . te('Pastebėjote klaidą, kažkas nepatogu ar turite idėją? Palikite komentarą - pataisysime.') . '</p>'
        . '<form class="fb-form" method="post" action="' . e(url('atsiliepimas.php')) . '">' . csrf_field()
        . '<input type="hidden" name="page" value="' . e($page) . '">'
        . '<textarea name="body" rows="4" maxlength="' . FEEDBACK_MAX . '" required placeholder="' . e(t('Kas negerai arba ką pakeisti?')) . '" aria-label="' . e(t('Atsiliepimas')) . '"></textarea>'
        . ($a ? '' : '<input type="text" name="name" maxlength="120" placeholder="' . e(t('Vardas ar el. paštas (nebūtina)')) . '" aria-label="' . e(t('Vardas ar el. paštas (nebūtina)')) . '">')
        . '<input type="text" name="website" class="fb-hp" tabindex="-1" autocomplete="off" aria-hidden="true">'
        . '<div class="fb-actions"><span class="fb-page" title="' . e($page) . '">' . te('Puslapis:') . ' ' . e(mb_strimwidth($page, 0, 40, '…')) . '</span>'
        . '<button class="btn btn-primary btn-sm" type="submit">' . te('Siųsti') . '</button></div>'
        . '<p class="fb-msg" role="status" hidden></p></form>'
        . ($a ? '<div class="fb-mine"' . ($mine ? '' : ' hidden') . '><div class="fb-mine-title">' . te('Jūsų atsiliepimai') . '</div><ul class="fb-list">'
            . implode('', array_map('feedback_mine_item', $mine)) . '</ul></div>' : '')
        . (is_admin($a) ? '<a class="fb-admin" href="' . e(url('admin/atsiliepimai.php')) . '">' . te('Visi atsiliepimai →') . '</a>' : '')
        . '</div></div></div>';
}
