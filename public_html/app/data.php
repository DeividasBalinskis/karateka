<?php
// Dažnos užklausos: tvarkaraštis, renginiai, naujienos

function group_schedule(int $groupId): array
{
    return q_all('SELECT * FROM schedule WHERE group_id = ? ORDER BY weekday, start_time', [$groupId]);
}

/**
 * Artimiausia nario grupės treniruotė per 14 dienų (atšauktos praleidžiamos; šiandien - jei dar neprasidėjo).
 * ['date' => Y-m-d, 'start' => '17:30', 'end' => '18:30', 'location' => ..., 'group' => ...] arba null.
 */
function member_next_training(array $member): ?array
{
    if (empty($member['group_id'])) {
        return null;
    }
    $gid = (int) $member['group_id'];
    $rows = group_schedule($gid);
    if (!$rows) {
        return null;
    }
    $g = q_one('SELECT name, location FROM training_groups WHERE id = ?', [$gid]);
    for ($i = 0; $i < 14; $i++) {
        $date = date('Y-m-d', strtotime('+' . $i . ' day'));
        $wd = (int) date('N', strtotime($date));
        foreach ($rows as $r) {
            if ((int) $r['weekday'] !== $wd || ($i === 0 && $r['start_time'] && substr($r['start_time'], 0, 5) <= date('H:i'))) {
                continue;
            }
            if (function_exists('training_cancelled') && training_cancelled($gid, $date)) {
                continue;
            }
            return ['date' => $date, 'start' => $r['start_time'] ? substr($r['start_time'], 0, 5) : '', 'end' => $r['end_time'] ? substr($r['end_time'], 0, 5) : '',
                    'location' => (string) ($g['location'] ?? ''), 'group' => (string) ($g['name'] ?? '')];
        }
    }
    return null;
}

/** „Šiandien“, „Rytoj“ arba savaitės diena (trumpai vaikui) */
function day_word(string $date): string
{
    if ($date === date('Y-m-d')) {
        return t('Šiandien');
    }
    if ($date === date('Y-m-d', strtotime('+1 day'))) {
        return t('Rytoj');
    }
    return weekday_names()[(int) date('N', strtotime($date))];
}

/** Artėjantys (ar dar vykstantys) renginiai: skirti visiems arba nurodytai grupei. $groupId = null - visi renginiai. */
function upcoming_events(?int $groupId, int $limit = 20): array
{
    $sql = 'SELECT e.* FROM events e WHERE COALESCE(e.ends_on, e.starts_on) >= CURDATE()';
    $params = [];
    if ($groupId !== null) {
        $sql .= ' AND (NOT EXISTS (SELECT 1 FROM event_groups eg WHERE eg.event_id = e.id)
                    OR EXISTS (SELECT 1 FROM event_groups eg WHERE eg.event_id = e.id AND eg.group_id = ?))';
        $params[] = $groupId;
    }
    return q_all($sql . ' ORDER BY e.starts_on, e.start_time LIMIT ' . (int) $limit, $params);
}

/** Renginio grupių pavadinimai (tuščias masyvas = visiems) */
function event_group_names(int $eventId): array
{
    return q('SELECT g.name FROM event_groups eg JOIN training_groups g ON g.id = eg.group_id WHERE eg.event_id = ? ORDER BY g.sort_order', [$eventId])
        ->fetchAll(PDO::FETCH_COLUMN);
}

/** Kam skirtas renginys: grupių vardai, „pakviestiems“ arba „visiems“ */
function event_audience_label(array $ev): string
{
    $groups = event_group_names((int) $ev['id']);
    return $groups ? implode(', ', $groups) : (!empty($ev['invited_only']) ? 'pakviestiems' : 'visiems');
}

function render_schedule(array $rows): string
{
    if (!$rows) {
        return ('<p class="muted">' . t('Tvarkaraštis dar nesudarytas.') . '</p>');
    }
    $html = '';
    foreach ($rows as $r) {
        $time = $r['start_time'] ? fmt_time($r['start_time']) . '–' . fmt_time($r['end_time']) : '';
        $html .= '<div class="schedule-line"><span>' . e(weekday_names()[(int) $r['weekday']])
            . ($r['note'] ? ' <span class="muted small">· ' . e($r['note']) . '</span>' : '')
            . '</span><span class="schedule-time">' . e($time) . '</span></div>';
    }
    return $html;
}

/**
 * Renginio registracijos nuoroda (db/034): įrašyta renginyje arba rasta aprašyme (Google Forms, Microsoft Forms ir pan.) - mygtukas „📝 Registracija →“.
 * Praėjusiam renginiui ar pasibaigus registracijai - ''.
 */
function event_reg_url(array $ev): string
{
    if (event_last_day($ev) < date('Y-m-d') || (!empty($ev['registration_until']) && $ev['registration_until'] < date('Y-m-d'))) {
        return '';
    }
    $url = trim((string) ($ev['registration_url'] ?? ''));
    if ($url === '' && preg_match('~https?://(?:forms\.gle|docs\.google\.com/forms|forms\.office\.com|forms\.microsoft\.com|[\w-]+\.typeform\.com|form\.jotform\.com)/[^\s<>"\)]+~i',
            ($ev['summary'] ?? '') . ' ' . ($ev['description'] ?? ''), $m)) {
        $url = rtrim($m[0], '.,;');
    }
    return preg_match('~^https?://~i', $url) ? $url : '';
}

/** Paskutinė renginio diena (Y-m-d) */
function event_last_day(array $ev): string
{
    return (string) ($ev['ends_on'] ?: $ev['starts_on']);
}

// Registracijos mygtukai: Nustatymai → „Registracija“ - kiekvieną vietą galima išjungti (mygtukas dingsta, puslapis sako „išjungta“)
const REG_PLACES = [
    'events' => ['Renginiai', 'Mygtukai „Registruotis“ ir „Dalyvausiu“ renginiuose ir kalendoriuje.'],
    'exam'   => ['Egzaminai', 'Registracija į egzaminą per nuorodą (be paskyros).'],
    'join'   => ['Naujos paskyros', 'Mygtukas „Sukurti paskyrą“ ir registracijos puslapis.'],
    'budo'   => ['Varžybos (Budo)', 'Registracija į varžybas: kitų klubų nuoroda ir narių registracija.'],
];

/** Ar registracija toje vietoje įjungta (numatytai - taip) */
function reg_on(string $place): bool
{
    $off = json_decode((string) site_setting('reg_off', '[]'), true);
    return !(is_array($off) && in_array($place, $off, true));
}

function event_reg_button(array $ev, string $class = 'btn btn-primary btn-sm', bool $onPage = false): string
{
    // Kiekvienas būsimas renginys: „Registruotis“ (išorinė forma, egzamino nuoroda žmonėms be paskyros arba „Dalyvausiu“ renginio puslapyje),
    // o kai registracija uždaryta (rankiniu būdu ar praėjus terminui) - „Registracija uždaryta“
    if (event_last_day($ev) < date('Y-m-d')) {
        return '';
    }
    $url = event_reg_url($ev);
    if ($url !== '' && reg_on('events')) {
        return '<a class="' . e($class) . ' event-reg" href="' . e($url) . '" target="_blank" rel="noopener">' . e(t('📝 Registracija →')) . '</a>';
    }
    $acc = current_account();
    $member = $acc && is_active_account($acc) && !is_staff($acc);
    if ($ev['type'] === 'exam' && !$member && reg_on('exam') && ($l = exam_signup_link((int) $ev['id'])) && exam_signup_open($ev, $l)) {
        return '<a class="' . e($class) . ' event-reg" href="' . e(exam_signup_url($l)) . '">' . e(t('📝 Registruotis į egzaminą')) . '</a>';
    }
    if (!reg_on('events')) {
        return '';
    }
    if (!empty($ev['registration']) && date('Y-m-d') <= event_deadline($ev)) {
        return $onPage ? '' : '<a class="' . e($class) . ' event-reg" href="' . e(url('renginys.php?id=' . (int) $ev['id'] . '#registracija')) . '">' . e(t('📝 Registruotis')) . '</a>';
    }
    return '<span class="' . e(str_replace('btn-primary', 'btn-ghost', $class)) . ' event-reg reg-closed" aria-disabled="true">' . e(t('Registracija uždaryta')) . '</span>';
}

function render_event(array $ev): string
{
    $ts = strtotime($ev['starts_on']);
    $when = fmt_date($ev['starts_on']);
    if ($ev['ends_on'] && $ev['ends_on'] !== $ev['starts_on']) {
        $when .= ' – ' . fmt_date($ev['ends_on']);
    }
    if ($ev['start_time']) {
        $when .= ', ' . fmt_time($ev['start_time']);
    }
    // Sąraše - trumpas tekstas (arba aprašymo pradžia), visas aprašymas - atidarius renginį
    $short = trim(tr_text($ev['summary'] ?? ''));
    if ($short === '' && $ev['description']) {
        $plain = trim(preg_replace('/\s+/u', ' ', tr_text($ev['description'])));
        $short = mb_strlen($plain) > 220 ? mb_substr($plain, 0, 220) . '…' : $plain;
    }
    $link = url('renginys.php?id=' . (int) $ev['id']);
    $ph = event_cover($ev);
    return '<div class="event' . ($ph ? ' has-ph' : '') . '">'
        . ($ph ? '<a class="event-ph" href="' . e($link) . '" tabindex="-1" aria-hidden="true"><img src="' . e($ph) . '" alt="" loading="lazy"></a>' : '')
        . '<div class="event-date"><span class="d">' . date('j', $ts) . '</span><span class="m">' . e(mb_substr(month_names_gen()[(int) date('n', $ts)], 0, 3)) . '</span></div>'
        . '<div><span class="badge type-badge t-' . e($ev['type']) . '">' . e(t(EVENT_TYPES[$ev['type']])) . '</span>'
        . '<h3 style="margin-top:6px;"><a class="event-title" href="' . e($link) . '">' . e(tr_text($ev['title'])) . '</a></h3>'
        . '<div class="muted small">' . e($when) . ($ev['location'] ? ' · ' . e(tr_text($ev['location'])) : '') . '</div>'
        . ($short !== '' ? '<div class="small" style="margin-top:6px;">' . e($short) . '</div>' : '')
        . (!empty($ev['file_path']) ? '<div class="small" style="margin-top:6px;">' . event_file_link($ev) . '</div>' : '')
        . '<div class="event-links"><a class="small event-more" href="' . e($link) . '">' . e(t('Plačiau →')) . '</a>' . event_reg_button($ev) . '</div>'
        . '</div></div>';
}

// ---------- Renginio priedas (db/028): vienas failas, pvz. reglamentas ----------
const EVENT_FILE_DIR = PUBLIC_DIR . '/uploads/events';
const EVENT_FILE_TYPES = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods', 'ppt', 'pptx', 'jpg', 'jpeg', 'png'];
const EVENT_FILE_MAX = 10 * 1024 * 1024;

/** Įkelto priedo patikra: null - tinka (ar nėra), kitaip - klaidos tekstas */
function event_file_error(?array $f): ?string
{
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > EVENT_FILE_MAX) {
        return t('Priedo nepavyko įkelti: failas turi būti iki 10 MB.');
    }
    if (!in_array(strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION)), EVENT_FILE_TYPES, true)) {
        return t('Priedas turi būti PDF, Word, Excel ar paveikslėlis.');
    }
    return null;
}

/** Išsaugo priedą renginiui (senas ištrinamas); $remove - tik pašalinti */
function event_file_save(int $eventId, ?array $f, bool $remove = false): void
{
    $has = $f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name']);
    if (!$has && !$remove) {
        return;
    }
    $old = q_value('SELECT file_path FROM events WHERE id = ?', [$eventId]);
    if ($old) {
        @unlink(EVENT_FILE_DIR . '/' . basename($old));
    }
    if (!$has) {
        q('UPDATE events SET file_path = NULL, file_name = NULL WHERE id = ?', [$eventId]);
        return;
    }
    if (!is_dir(EVENT_FILE_DIR)) {
        @mkdir(EVENT_FILE_DIR, 0755, true);
    }
    $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
    $file = bin2hex(random_bytes(10)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], EVENT_FILE_DIR . '/' . $file)) {
        throw new RuntimeException(t('Priedo nepavyko išsaugoti.'));
    }
    // Pradinis pavadinimas atsisiuntimui (be kelio ir keistų simbolių)
    $name = trim(preg_replace('~[\\\\/:*?"<>|\x00-\x1F]+~u', ' ', basename((string) $f['name'])));
    q('UPDATE events SET file_path = ?, file_name = ? WHERE id = ?', [$file, mb_substr($name ?: ('priedas.' . $ext), 0, 190), $eventId]);
}

// ---------- Renginio nuotrauka (db/042, #119): rodoma „Naujienos ir renginiai“ ir renginių sąraše ----------
const EVENT_COVER_DIR = PUBLIC_DIR . '/uploads/events/covers';
const EVENT_ICONS = ['exam' => '📋', 'competition' => '🏆', 'seminar' => '🎓', 'camp' => '⛺', 'other' => '🥋'];

function event_cover_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT cover FROM events LIMIT 1');
            $ok = true;
        } catch (PDOException $ex) {
            $ok = false;
        }
    }
    return $ok;
}

/** Renginio nuotrauka: įkelta nuotrauka, o jei jos nėra - priedas-paveikslėlis (JPG, PNG); null - nuotraukos nėra */
function event_cover(array $ev, bool $thumb = true): ?string
{
    if (!empty($ev['cover'])) {
        return url('uploads/events/covers/' . ($thumb ? 'thumb_' : '') . basename($ev['cover']));
    }
    if (!empty($ev['file_path']) && preg_match('~\.(jpe?g|png|webp)$~i', (string) $ev['file_path'])) {
        return url('uploads/events/' . basename($ev['file_path']));
    }
    return null;
}

/** Nuotraukos vieta kortelėje: nuotrauka arba spalvotas fonas su renginio tipo ženklu */
function event_cover_html(array $ev, string $class = 'ev-ph'): string
{
    $src = event_cover($ev);
    return $src
        ? '<span class="' . e($class) . '"><img src="' . e($src) . '" alt="" loading="lazy"></span>'
        : '<span class="' . e($class) . ' no-ph t-' . e($ev['type']) . '" aria-hidden="true"><i>' . (EVENT_ICONS[$ev['type']] ?? '🥋') . '</i></span>';
}

/** Išsaugo renginio nuotrauką (sena ištrinama); $remove - tik pašalinti */
function event_cover_save(int $eventId, ?array $f, bool $remove = false): void
{
    if (!event_cover_ready()) {
        return;
    }
    $has = $f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name']);
    if (!$has && !$remove) {
        return;
    }
    $old = q_value('SELECT cover FROM events WHERE id = ?', [$eventId]);
    if ($old) {
        @unlink(EVENT_COVER_DIR . '/' . basename($old));
        @unlink(EVENT_COVER_DIR . '/thumb_' . basename($old));
    }
    $name = null;
    if ($has) {
        require_once __DIR__ . '/images.php';
        $name = save_uploaded_image($f, EVENT_COVER_DIR);
    }
    q('UPDATE events SET cover = ? WHERE id = ?', [$name, $eventId]);
}

/** Priedo nuoroda (atsisiųsti) arba '' */
function event_file_link(array $ev, string $class = ''): string
{
    if (empty($ev['file_path'])) {
        return '';
    }
    $ext = strtoupper(pathinfo((string) $ev['file_path'], PATHINFO_EXTENSION));
    return '<a class="event-file ' . e($class) . '" href="' . e(url('uploads/events/' . basename($ev['file_path']))) . '" download="' . e($ev['file_name'] ?: basename($ev['file_path'])) . '" target="_blank" rel="noopener">'
        . '📄 ' . e($ev['file_name'] ?: t('Priedas')) . ' <span class="muted small">' . e($ext) . '</span></a>';
}

/**
 * "Redaguoti" renginiui viešuose puslapiuose (matomas tik treneriams).
 * $back - kur grįžti išsaugojus: 'renginiai' arba 'index'.
 */
function event_edit_link(int $id, string $back): string
{
    return staff_link('admin/renginiai.php?edit=' . $id . '&back=' . $back);
}

/** Treneriui prie renginio: redaguoti, dalyviai (dalyvaus / visi), rezultatai (prasidėjusiam) */
function event_staff_actions(array $ev, string $back): string
{
    if (!staff_ui()) {
        return '';
    }
    $id = (int) $ev['id'];
    $pc = event_participant_counts($id);
    $html = '<div class="event-staff no-print"><a class="btn btn-ghost btn-sm" href="' . e(url('admin/renginiai.php?edit=' . $id . '&back=' . $back)) . '">✎ Redaguoti</a>'
        . '<a class="btn btn-ghost btn-sm" href="' . e(url('admin/renginio-dalyviai.php?id=' . $id)) . '">Dalyviai' . ($pc['going'] || $pc['invited'] ? ' ' . $pc['going'] . '/' . ($pc['going'] + $pc['invited']) : '') . '</a>';
    if ($ev['type'] === 'competition' && varzybos_enabled()) {
        $html .= '<a class="btn btn-ghost btn-sm" href="' . e(url(!empty($ev['tournament_id']) ? 'admin/varzybu-dalyviai.php?id=' . $id : 'admin/varzybu-rezultatai.php?id=' . $id)) . '">'
            . (!empty($ev['tournament_id']) ? t('🥋 Varžybų sistema') : t('🔗 Susieti su varžybomis')) . '</a>';
    }
    if ($ev['type'] === 'exam' && exam_protocol_ready()) {
        $html .= '<a class="btn btn-ghost btn-sm" href="' . e(url('admin/egzamino-protokolas.php?id=' . $id)) . '">' . e(t('📋 Protokolas')) . '</a>';
    }
    if ($ev['starts_on'] <= date('Y-m-d')) {
        // Prasidėjęs ar praėjęs renginys: kas buvo (Treniruotės puslapyje); egzaminams ir varžyboms - ir rezultatai
        $html .= '<a class="btn btn-ghost btn-sm" href="' . e(url('admin/treniruote.php?e=' . $id)) . '">' . e(t('✓ Kas buvo')) . '</a>';
        if (in_array($ev['type'], ['exam', 'competition'], true)) {
            $html .= '<a class="btn btn-primary btn-sm" href="' . e(url('admin/renginio-rezultatai.php?id=' . $id)) . '">' . e(t('Rezultatai')) . '</a>';
        }
    }
    return $html . '</div>';
}

// ---------- Svetainės nustatymai (db/011) ----------

/** Nustatymas iš site_settings; jei lentelės dar nėra (neimportuotas 011) - $default */
function site_setting(string $name, ?string $default = null): ?string
{
    static $cache = [];
    if (!array_key_exists($name, $cache)) {
        try {
            $v = q_value('SELECT value FROM site_settings WHERE name = ?', [$name]);
            $cache[$name] = $v === false || $v === null ? $default : (string) $v;
        } catch (PDOException $ex) {
            $cache[$name] = $default;
        }
    }
    return $cache[$name];
}

/** Vienintelis rašymas į site_settings: išsaugo ir įrašo į pakeitimų žurnalą (C2, app/settings.php) */
function set_site_setting(string $name, string $value): void
{
    $old = null;
    try {
        $o = q_value('SELECT value FROM site_settings WHERE name = ?', [$name]);
        $old = $o === false || $o === null ? null : (string) $o;
    } catch (PDOException $ex) {
    }
    q('INSERT INTO site_settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$name, $value]);
    if (function_exists('settings_log_write')) {
        settings_log_write($name, $old, $value);
    }
}

// ---------- Reakcijos į naujienas ----------

/** Emoji, kuriuos nariai gali pasirinkti - visas sąrašas (REACTION_CHOICES) */
function allowed_reactions(): array
{
    return array_merge(...array_values(REACTION_CHOICES));
}

/** Reakcija HTML: emoji arba diržo / teisėjo vėliavėlių ženkliukas ("belt:N", "teisejas") */
function reaction_html(string $emoji): string
{
    return preg_match('/^(belt:\d+|teisejas)$/', $emoji) ? icon_html($emoji) : e($emoji);
}

/** Reakcijos pavadinimas ekrano skaitytuvams ir užuominai */
function reaction_label(string $emoji): string
{
    if (preg_match('/^belt:(\d+)$/', $emoji, $m) && isset(BELTS[(int) $m[1]])) {
        return t(BELTS[(int) $m[1]][1]) . (' ' . t('diržas'));
    }
    return $emoji === 'teisejas' ? t('teisėjo vėliavėlės') : $emoji;
}

/** Panaudoti emoji su skaičiais: [emoji => count], daugiausiai paspaustas pirmas. Rodomi ir vėliau išjungti emoji. */
function news_reaction_counts(int $newsId): array
{
    $order = array_flip(allowed_reactions());
    $rows = q_all('SELECT emoji, COUNT(*) c FROM reactions WHERE news_id = ? GROUP BY emoji', [$newsId]);
    usort($rows, function ($x, $y) use ($order) {
        return [(int) $y['c'], $order[$x['emoji']] ?? 99] <=> [(int) $x['c'], $order[$y['emoji']] ?? 99];
    });
    $counts = [];
    foreach ($rows as $r) {
        $counts[$r['emoji']] = (int) $r['c'];
    }
    return $counts;
}

/** Jau panaudotų emoji mygtukai. Paspaudus - prisijungęs narys prideda / nuima savo reakciją. */
function render_reaction_chips(int $newsId, ?string $mine): string
{
    $allowed = allowed_reactions();
    $html = '';
    foreach (news_reaction_counts($newsId) as $emoji => $count) {
        $label = reaction_html((string) $emoji) . '<span class="count">' . $count . '</span>';
        if (in_array($emoji, $allowed, true) || $mine === $emoji) {
            $html .= '<button type="submit" name="emoji" value="' . e($emoji) . '" class="reaction' . ($mine === $emoji ? ' mine' : '') . '"'
                . ($mine === $emoji ? ' title="' . e(t('Jūsų reakcija - paspaudus nuimama')) . '"' : '')
                . ' aria-label="' . e(reaction_label((string) $emoji)) . ' ' . $count . ($mine === $emoji ? (' ' . t('(jūsų - paspaudus nuimama)')) : '') . '">' . $label . '</button>';
        } else {
            // Emoji, kurio nebėra sąraše: reakcijos lieka matomos, bet naujų pridėti nebegalima
            $html .= '<span class="reaction off" title="' . e(t('Šis emoji nebenaudojamas')) . '">' . $label . '</span>';
        }
    }
    return $html;
}

/** Reakcijų juosta: panaudoti emoji su skaičiais ir mygtukas leidžiamų emoji sąrašui atidaryti */
function render_reactions(int $newsId): string
{
    $a = current_account();
    $mine = $a ? q_value('SELECT emoji FROM reactions WHERE news_id = ? AND account_id = ?', [$newsId, $a['id']]) : false;
    $mine = $mine === false || $mine === null ? null : (string) $mine;
    // Visi emoji pagal grupes; paspaudus savo (pažymėtą) - reakcija nuimama
    $picker = '';
    foreach (REACTION_CHOICES as $group => $list) {
        $picker .= '<span class="r-group" role="presentation">' . e($group) . '</span>';
        foreach ($list as $emoji) {
            $picker .= '<button type="submit" name="emoji" value="' . e($emoji) . '" class="pick' . ($mine === $emoji ? ' mine' : '') . '" role="menuitem" aria-label="Reaguoti: ' . e(reaction_label($emoji)) . '" title="' . e(reaction_label($emoji)) . '">' . reaction_html($emoji) . '</button>';
        }
    }
    return '<form class="reactions" method="post" action="' . url('reakcija.php') . '" data-news="' . $newsId . '">'
        . csrf_field() . '<input type="hidden" name="news_id" value="' . $newsId . '">'
        . '<span class="r-used">' . render_reaction_chips($newsId, $mine) . '</span>'
        . '<span class="r-add">'
        . '<button type="button" class="reaction-add" aria-haspopup="true" aria-expanded="false" aria-label="' . e(t('Pridėti reakciją')) . '" title="' . e(t('Pridėti reakciją')) . '">'
        . '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8"/>'
        . '<circle cx="9" cy="10" r="1.2" fill="currentColor"/><circle cx="15" cy="10" r="1.2" fill="currentColor"/>'
        . '<path d="M8.5 14.5c1 1.2 2.1 1.8 3.5 1.8s2.5-.6 3.5-1.8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>'
        . '<span class="plus" aria-hidden="true">+</span></button>'
        . '<span class="r-picker" role="menu">' . $picker . '</span>'
        . '</span></form>';
}

/**
 * "Dalintis": kompiuteryje atidaro Facebook dalinimosi langą, telefone - telefono dalinimosi meniu (Messenger, Viber, Facebook...).
 * Tik viešoms naujienoms (naujienų "tik nariams" Facebook nemato).
 */
function share_button(array $n): string
{
    if (!empty($n['members_only'])) {
        return '';
    }
    $link = abs_url('naujienos.php?id=' . (int) $n['id']);
    return '<a class="share-btn" href="https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($link) . '" target="_blank" rel="noopener"'
        . ' data-share-url="' . e($link) . '" data-share-title="' . e($n['title']) . '" title="Dalintis Facebook\'e ar kitur">'
        . '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M12 3v12M7.5 7.5 12 3l4.5 4.5M5 13v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
        . (t('Dalintis') . '</a>');
}

/** Naujienos viršelis: pirma nuotrauka arba YouTube paveikslėlis ($absolute - pilnas adresas Facebook'ui) */
function news_cover(int $newsId, bool $absolute = false): ?string
{
    $cover = null;
    foreach (news_media($newsId) as $m) {
        if ($m['type'] === 'image') {
            return $absolute ? abs_url('uploads/news/' . $m['file']) : news_image_url($m['file'], true);
        }
        if (!$cover && $m['type'] === 'youtube') {
            $cover = 'https://i.ytimg.com/vi/' . $m['youtube_id'] . '/hqdefault.jpg';
        }
    }
    return $cover;
}

/** Naujienos medija */
function news_media(int $newsId): array
{
    return q_all('SELECT * FROM news_media WHERE news_id = ? ORDER BY sort_order, id', [$newsId]);
}

function news_image_url(string $file, bool $thumb = false): string
{
    return url('uploads/news/' . ($thumb ? 'thumb_' : '') . $file);
}

/** Trenerio pastabos nariui (naujausios viršuje) */
function member_notes(int $memberId, int $limit = 10): array
{
    // Neatliktos užduotys - viršuje. Kartu: iš kur pastaba (treniruotės grupė ar renginys)
    return q_all('SELECT cn.*, a.first_name AS author, l.title AS lesson_title, g.name AS source_group, e.title AS source_event FROM coach_notes cn
                    LEFT JOIN accounts a ON a.id = cn.author_id LEFT JOIN lessons l ON l.id = cn.lesson_id
                    LEFT JOIN training_groups g ON g.id = cn.group_id LEFT JOIN events e ON e.id = cn.event_id
                   WHERE cn.member_id = ? ORDER BY (cn.is_task = 1 AND cn.done_at IS NULL) DESC, cn.note_date DESC, cn.id DESC LIMIT ' . (int) $limit, [$memberId]);
}

/** Iš kur pastaba: "Renginys: ...", "Treniruotė (grupė)" arba tik data (senos pastabos) */
function note_source(array $n): string
{
    $date = fmt_date($n['note_date'], true);
    if (!empty($n['source_event'])) {
        return (t('Renginys:') . ' ') . e($n['source_event']) . ' · ' . e($date);
    }
    if (!empty($n['source_group'])) {
        return (t('Treniruotė') . ' ') . e($date) . ' · ' . e($n['source_group']);
    }
    return e($date);
}

function member_unread_notes(int $memberId): int
{
    return (int) q_value('SELECT COUNT(*) FROM coach_notes WHERE member_id = ? AND read_at IS NULL', [$memberId]);
}

// ---------- Pamokos ----------

/** Ar paskyra gali matyti pamokas (aktyvus narys arba treneris) */
function can_see_lessons(?array $a = null): bool
{
    return is_active_account($a ?? current_account());
}

function lesson_videos(array $lesson): array
{
    return array_values(array_filter(explode(',', (string) $lesson['videos'])));
}

// ---------- Matomumas: "tik nariams" ----------

/** Ar žiūrintysis yra patvirtintas narys (mato "tik nariams" renginius ir naujienas) */
function viewer_is_member(): bool
{
    return is_active_account(current_account());
}

/** SQL sąlyga naujienoms/renginiams pagal žiūrintįjį (stulpelis members_only) */
function visibility_sql(string $alias = ''): string
{
    return viewer_is_member() ? '1' : ($alias ? "$alias." : '') . 'members_only = 0';
}

/** Artėjantys renginiai viešam pagrindiniam puslapiui */
function public_upcoming_events(int $limit = 4): array
{
    return q_all('SELECT e.* FROM events e WHERE COALESCE(e.ends_on, e.starts_on) >= CURDATE() AND ' . visibility_sql('e')
        . ' ORDER BY e.starts_on, e.start_time LIMIT ' . (int) $limit);
}

function members_only_badge(array $row): string
{
    return !empty($row['members_only']) ? (' <span class="badge badge-members">' . t('Tik nariams') . '</span>') : '';
}

// ---------- Pranešimai apie pastabas ir užduotis ----------

/** Kiek nario pastabų reikia dėmesio: neperskaitytos + neatliktos užduotys */
function member_attention_count(int $memberId): int
{
    return (int) q_value('SELECT COUNT(*) FROM coach_notes WHERE member_id = ? AND (read_at IS NULL OR (is_task = 1 AND done_at IS NULL))', [$memberId])
        + member_unread_replies($memberId);   // ir nauji trenerio atsakymai
}

/** Raudonas skaičius prie „Mano paskyra“ (pats + vaikai): tik darbai - neperskaitytos pastabos, neatliktos užduotys,
 *  nauji trenerio atsakymai ir kvietimai, laukiantys atsakymo. Naujienos (lygis, patvirtinti punktai, pranešimai visiems)
 *  rodomos sąraše, bet neskaičiuojamos (sprendimas 2026-11-21). */
function account_attention_count(int $accountId): int
{
    static $cache = [];
    if (!isset($cache[$accountId])) {
        $cache[$accountId] = (int) q_value('SELECT COUNT(*) FROM coach_notes cn JOIN account_members am ON am.member_id = cn.member_id
                                             WHERE am.account_id = ? AND (cn.read_at IS NULL OR (cn.is_task = 1 AND cn.done_at IS NULL))', [$accountId])
            + account_unread_replies($accountId)
            + account_pending_invites($accountId);
    }
    return $cache[$accountId];
}

/** Pirmas paskyros narys, kuriam yra neperskaitytų pastabų / neatliktų užduočių (nuorodai į jo paskyrą) */
function account_attention_member(int $accountId): ?int
{
    $id = q_value('SELECT cn.member_id FROM coach_notes cn JOIN account_members am ON am.member_id = cn.member_id
                    WHERE am.account_id = ? AND ((cn.is_task = 0 AND cn.read_at IS NULL) OR (cn.is_task = 1 AND cn.done_at IS NULL))
                    ORDER BY cn.note_date DESC LIMIT 1', [$accountId]);
    if ($id === false) {
        try {
            $id = q_value('SELECT mm.member_id FROM member_messages mm JOIN account_members am ON am.member_id = mm.member_id
                            WHERE am.account_id = ? AND mm.from_staff = 1 AND mm.read_at IS NULL ORDER BY mm.created_at DESC LIMIT 1', [$accountId]);
        } catch (PDOException $ex) {
            $id = false;
        }
    }
    if ($id === false && account_pending_invites($accountId)) {   // kvietimas, laukiantis atsakymo
        $id = q_value('SELECT ep.member_id FROM event_participants ep JOIN account_members am ON am.member_id = ep.member_id
                        JOIN events e ON e.id = ep.event_id
                        WHERE am.account_id = ? AND ep.status = "invited" AND e.starts_on > CURDATE() ORDER BY e.starts_on LIMIT 1', [$accountId]);
    }
    return $id === false || $id === null ? null : (int) $id;
}

/** Lankomumas laikotarpyje: ['buvo' => n, 'nebuvo' => n] */
function member_attendance(int $memberId, string $from, string $to): array
{
    $r = q_one('SELECT COALESCE(SUM(present = 1), 0) AS buvo, COALESCE(SUM(present = 0), 0) AS nebuvo FROM attendance
                 WHERE member_id = ? AND training_date BETWEEN ? AND ?', [$memberId, $from, $to]);
    return ['buvo' => (int) $r['buvo'], 'nebuvo' => (int) $r['nebuvo']];
}

function render_attendance(int $memberId): string
{
    [$sFrom, $sTo] = season_bounds();
    $m = member_attendance($memberId, date('Y-m-01'), date('Y-m-t'));
    $s = member_attendance($memberId, $sFrom, $sTo);
    if (!$s['buvo'] && !$s['nebuvo']) {
        return ('<p class="muted small">' . t('Lankomumas dar nežymėtas.') . '</p>');
    }
    $n = $m['buvo'] % 100;
    $word = ($n % 10 === 0 || ($n > 10 && $n < 20)) ? t('treniruočių') : ($n % 10 === 1 ? t('treniruotė') : t('treniruotės'));
    return '<div class="att-summary"><span><strong>' . $m['buvo'] . '</strong> ' . $word . (' ' . t('šį mėnesį'))
        . ($m['nebuvo'] ? (' <span class="muted">' . t('(praleista') . ' ') . $m['nebuvo'] . ')</span>' : '') . '</span>'
        . ('<span class="muted">' . t('Sezone:') . ' ') . $s['buvo'] . (' ' . t('iš') . ' ') . ($s['buvo'] + $s['nebuvo']) . '</span></div>';
}

// ---------- Trenerio „Šiandien“ ----------

/**
 * #157: renginys susietas su Budo varžybomis - rezultatai ateina iš Budo. Pasibaigus varžyboms (kitą dieną) narių vietos ir taškai
 * perkeliami automatiškai vieną kartą (tas pats, kas „→ Taškai“ Budo sąraše). true - renginys valdomas per Budo (rankiniu būdu vesti nereikia).
 */
function budo_results_auto_transfer(array $ev): bool
{
    try {
        $t = q_one('SELECT * FROM tournaments WHERE event_id = ? AND is_test = 0 ORDER BY id DESC LIMIT 1', [(int) $ev['id']]);
    } catch (PDOException $ex) {
        return false;   // dar neimportuotas db/025
    }
    if (!$t) {
        return false;
    }
    $a = current_account();
    if ($a && is_staff($a) && !empty(tournament_results($t)['categories']) && (string) ($t['ends_on'] ?: $t['starts_on']) < date('Y-m-d')) {
        try {
            tournament_results_to_points($t, (int) $a['id']);
        } catch (Throwable $ex) {
            // nepavyko - lieka rankinis „→ Taškai“ Budo sąraše; punkto vis tiek nerodome
        }
    }
    return true;
}

/** „Reikia padaryti“ punktai treneriui: [[tekstas, kelias, mygtukas], ...]. Skaičius rodomas ir ant „Valdymas“ mygtuko. */
function admin_todo_items(): array
{
    static $todo = null;
    if ($todo !== null) {
        return $todo;
    }
    $todo = [];
    if ($n = pending_approvals_count()) {
        $todo[] = [(t('Laukia patvirtinimo:') . ' ') . $n . ($n === 1 ? ' paskyra ar vaikas' : ' paskyros ar vaikai'), 'admin/patvirtinimai.php', t('Patvirtinti')];
    }
    // Praėję renginiai (paskutinės 30 d.): egzaminams ir varžyboms - rezultatai, kitiems - kas buvo (Treniruotė)
    $types = "'" . implode("','", array_keys(EVENT_RESULTS)) . "'";
    $sql = "SELECT e.* FROM events e
             WHERE e.type IN ($types) AND COALESCE(e.ends_on, e.starts_on) BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND DATE_SUB(CURDATE(), INTERVAL 1 DAY)
               AND NOT EXISTS (SELECT 1 FROM point_awards pa WHERE pa.event_id = e.id)";
    try {
        $past = q_all($sql . ' AND NOT EXISTS (SELECT 1 FROM event_participants ep WHERE ep.event_id = e.id AND ep.attended IS NOT NULL) ORDER BY e.starts_on DESC');
    } catch (PDOException $ex) {
        $past = q_all($sql . ' ORDER BY e.starts_on DESC');   // dar neimportuotas db/026
    }
    foreach ($past as $ev) {
        // #157: varžybos per Budo - rezultatai ir taškai persikelia patys (kitą dieną po varžybų); vesti nieko nereikia
        if ($ev['type'] === 'competition' && budo_results_auto_transfer($ev)) {
            continue;
        }
        $todo[] = $ev['type'] === 'competition'
            ? [(t('Įrašyk mūsų narių vietas:') . ' ') . $ev['title'] . ' (' . fmt_date($ev['starts_on']) . ')', 'admin/renginio-rezultatai.php?id=' . (int) $ev['id'], t('Rezultatai')]
            : ($ev['type'] === 'exam'
                ? [(t('Įvesk rezultatus:') . ' ') . $ev['title'] . ' (' . fmt_date($ev['starts_on']) . ')', 'admin/renginio-rezultatai.php?id=' . (int) $ev['id'], t('Rezultatai')]
                : [(t('Pažymėk, kas buvo:') . ' ') . $ev['title'] . ' (' . fmt_date($ev['starts_on']) . ')', 'admin/treniruote.php?e=' . (int) $ev['id'], t('Kas buvo')]);
    }
    // Naujos žinutės ir komentarai (prie renginių, naujienų, pamokų) - viena eilutė, atsidaro pokalbis
    if ($n = staff_unread_messages()) {
        $todo[] = [(t('Naujos žinutės ir komentarai:') . ' ') . $n, 'admin/zinutes.php', t('Atsakyti')];
    }
    try {
        if ($n = (int) q_value('SELECT COUNT(*) FROM anon_feedback WHERE read_at IS NULL')) {
            $todo[] = [(t('Nauji anoniminiai komentarai:') . ' ') . $n, 'admin/zinutes.php#anoniminiai', t('Skaityti')];
        }
    } catch (PDOException $ex) {
        // dar neimportuotas db/021
    }
    if (is_admin() && ($n = feedback_new_count())) {
        $todo[] = [(t('Nauji atsiliepimai apie svetainę:') . ' ') . $n, 'admin/atsiliepimai.php', t('Peržiūrėti')];
    }
    // Įkelta nauja versija, o duomenų bazė dar neatnaujinta - vienu mygtuku
    if (is_admin() && db_update_pending()) {
        array_unshift($todo, [t('Atnaujinkite duomenų bazę naujai versijai'), 'admin/kopijos.php', t('Atnaujinti')]);   // visada viršuje
    }
    // Atsarginė kopija: nepavyko arba nedaryta ilgiau nei 2 d.
    if (is_admin() && backup_auto()) {
        $last = backup_last_time();
        $tried = @filemtime(backup_dir() . '/.bandyta');   // pirmą kartą kopija daroma dabar - nerodoma
        if (backup_error() || ($last && $last < time() - 2 * 86400) || (!$last && $tried && $tried < time() - 3600)) {
            $todo[] = [backup_error() ? t('Atsarginė duomenų bazės kopija nepavyko') : (t('Atsarginė duomenų bazės kopija nedaryta') . ' ') . ($last ? 'nuo ' . fmt_date(date('Y-m-d', $last)) : t('nė karto')), 'admin/kopijos.php', t('Peržiūrėti')];
        }
    }
    // Nauji testų rezultatai per savaitę
    $r = q_one('SELECT COUNT(*) c, COALESCE(SUM(passed), 0) p FROM quiz_attempts WHERE finished_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
    if ($r && (int) $r['c']) {
        $todo[] = [(t('Nauji testų rezultatai per savaitę:') . ' ') . (int) $r['c'] . (' ' . t('(išlaikė') . ' ') . (int) $r['p'] . ')', 'admin/testai.php', t('Peržiūrėti')];
    }
    return $todo;
}

/** Renginio eilutė trenerio sąraše: vieta, laikas, kam skirta */
function admin_event_summary(array $ev): string
{
    $parts = array_filter([fmt_time($ev['start_time']), $ev['location'], event_audience_label($ev)]);
    return e(implode(' · ', $parts));
}

function admin_event_link(array $ev): string
{
    return 'admin/renginio-dalyviai.php?id=' . (int) $ev['id'];
}

/** „Dalyviai 5/12“: dalyvaus / visi pakviesti ir užsiregistravę */
function admin_event_link_label(array $ev): string
{
    $c = event_participant_counts((int) $ev['id']);
    return t('Dalyviai') . ($c['going'] || $c['invited'] ? ' ' . $c['going'] . '/' . ($c['going'] + $c['invited']) : '');
}

