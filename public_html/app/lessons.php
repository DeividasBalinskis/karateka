<?php
// Pamokos: aplankai (medis), turinys blokais, matomumas pagal aplanką ir diržą

const LESSON_DIR = PUBLIC_DIR . '/uploads/lessons';

// Bloko tipas => [pavadinimas, ženkliukas]
const LESSON_BLOCK_TYPES = [
    'heading' => ['Antraštė', 'H'],
    'text'    => ['Tekstas', '¶'],
    'video'   => ['Video', '▶'],
    'image'   => ['Nuotrauka', '🖼'],
    'note'    => ['Trenerio pastaba', '💬'],
    'link'    => ['Nuoroda', '↗'],
];
const FOLDER_ICONS = ['📁', '🥋', '🥊', '🦶', '🧘', '🎯', '📜', '⭐', '🏆', '🧠', '💪', '🎥', '👶', '⚖️', '📝'];
// Diržų ženkliukai - po vieną kiekvienai spalvai (saugoma kaip "belt:N"); teisėjams - "teisejas" (balta ir raudona vėliavėlės)
const FOLDER_BELT_ICONS = [1, 2, 3, 4, 5, 7, 10];

/** Visi aplankai: [id => aplankas], surikiuoti pagal tvarką */
function lesson_folders(bool $fresh = false): array
{
    static $cache = null;
    if ($cache === null || $fresh) {
        $cache = [];
        foreach (q_all('SELECT * FROM lesson_folders ORDER BY sort_order, title') as $f) {
            $cache[(int) $f['id']] = $f;
        }
    }
    return $cache;
}

/** Aplanko kelias nuo viršaus: [aplankas, poaplankis, ...] */
function folder_path(array $folders, ?int $id): array
{
    $path = [];
    $guard = 0;
    while ($id && isset($folders[$id]) && $guard++ < 50) {
        array_unshift($path, $folders[$id]);
        $id = $folders[$id]['parent_id'] !== null ? (int) $folders[$id]['parent_id'] : null;
    }
    return $path;
}

/** Aplanko ir visų jo poaplankių id */
function folder_descendant_ids(array $folders, int $id): array
{
    $out = [$id];
    foreach ($folders as $f) {
        if ((int) $f['parent_id'] === $id && $f['parent_id'] !== null) {
            $out = array_merge($out, folder_descendant_ids($folders, (int) $f['id']));
        }
    }
    return $out;
}

/** Tiesioginiai poaplankiai (tvarka - kaip medyje) */
function folder_children(array $folders, ?int $parent): array
{
    return array_values(array_filter($folders, function ($f) use ($parent) {
        return ($f['parent_id'] !== null ? (int) $f['parent_id'] : null) === $parent;
    }));
}

/** Aplanko pasirinkimas (select) su įtraukomis; $exclude - aplankai, į kuriuos negalima (pats ir jo vidus) */
function folder_options(array $folders, ?int $selected, array $exclude = [], string $rootLabel = '- pagrindinis lygis -'): string
{
    $html = '<option value="">' . e($rootLabel) . '</option>';
    $walk = function (?int $parent, int $depth) use (&$walk, $folders, $selected, $exclude, &$html) {
        foreach (folder_children($folders, $parent) as $f) {
            if (in_array((int) $f['id'], $exclude, true)) {
                continue;
            }
            $html .= '<option value="' . (int) $f['id'] . '"' . ((int) $f['id'] === $selected ? ' selected' : '') . '>'
                . str_repeat("\u{00A0}\u{00A0}\u{00A0}", $depth) . ($depth ? '└ ' : '') . e(folder_icon_text($f) . $f['title']) . '</option>';
            $walk((int) $f['id'], $depth + 1);
        }
    };
    $walk(null, 0);
    return $html;
}

// ---------- Ženkliukai ----------

function belt_icon_svg(int $level): string
{
    $color = BELTS[$level][2] ?? '#ccc';
    return '<svg class="ico" viewBox="0 0 32 32" aria-hidden="true"><g fill="' . $color . '" stroke="rgba(23,20,15,0.5)" stroke-width="1.2" stroke-linejoin="round">'
        . '<path d="M13.6 15.6 8.6 27.2l3.9 1 3.5-10.6z"/><path d="M18.4 15.6l5 11.6-3.9 1L16 17.6z"/>'
        . '<rect x="2" y="11" width="28" height="6.4" rx="1.6"/><rect x="12.4" y="8.6" width="7.2" height="11" rx="2.2"/></g></svg>';
}

/** Teisėjo vėliavėlės: balta ir raudona */
function referee_icon_svg(): string
{
    return '<svg class="ico" viewBox="0 0 32 32" aria-hidden="true">'
        . '<path d="M13 29 8 4M19 29l5-25" stroke="#6B4A34" stroke-width="2" stroke-linecap="round" fill="none"/>'
        . '<path d="M8.3 5.5 1.8 8.6l1.5 7.8 6.5-3.1z" fill="#fff" stroke="rgba(23,20,15,0.55)" stroke-width="1.1" stroke-linejoin="round"/>'
        . '<path d="m23.7 5.5 6.5 3.1-1.5 7.8-6.5-3.1z" fill="#D7262B" stroke="rgba(23,20,15,0.4)" stroke-width="1.1" stroke-linejoin="round"/></svg>';
}

/** Ženkliukas HTML: emoji, "belt:N" - diržas, "teisejas" - teisėjo vėliavėlės. Be ženkliuko diržo aplankui - jo diržas. */
function icon_html(?string $icon, ?int $belt = null, string $fallback = '📁'): string
{
    $icon = (string) $icon;
    if (preg_match('/^belt:(\d+)$/', $icon, $m) && isset(BELTS[(int) $m[1]])) {
        return belt_icon_svg((int) $m[1]);
    }
    if ($icon === 'teisejas') {
        return referee_icon_svg();
    }
    if ($icon === '' && $belt) {
        return belt_icon_svg($belt);
    }
    return e($icon !== '' ? $icon : $fallback);
}

function folder_icon(array $f): string
{
    return icon_html($f['icon'] ?? null, !empty($f['belt_level']) ? (int) $f['belt_level'] : null);
}

/** Ženkliukas tekste (pvz. <option>): emoji su tarpu; diržo ir teisėjo ženkliukai - tik paveikslėliai, tekste praleidžiami */
function folder_icon_text(array $f): string
{
    $icon = (string) ($f['icon'] ?? '');
    return $icon !== '' && !preg_match('/^(belt:\d+|teisejas)$/', $icon) ? $icon . ' ' : '';
}

/** Ženkliuko pasirinkimo mygtukai (įrašo reikšmę į laukelį $inputId); pirmas - dabartinis ženkliukas */
function icon_picker(string $inputId, ?string $current = null): string
{
    $html = '<span class="icon-pick" data-for="' . e($inputId) . '"><span class="icon-now" title="Dabar">' . icon_html($current) . '</span>';
    foreach (FOLDER_BELT_ICONS as $lvl) {
        $html .= '<button type="button" data-icon="belt:' . $lvl . '" title="' . e(BELTS[$lvl][1]) . ' diržas">' . belt_icon_svg($lvl) . '</button>';
    }
    $html .= '<button type="button" data-icon="teisejas" title="Teisėjams">' . referee_icon_svg() . '</button>';
    foreach (FOLDER_ICONS as $ic) {
        $html .= '<button type="button" data-icon="' . $ic . '">' . $ic . '</button>';
    }
    return $html . '</span>';
}

// ---------- Kas mato pamokas ----------

/** „Žiūrėti kaip narys“: treneris pasirenka narį ir pamokas mato taip, kaip jis (saugoma sesijoje) */
function lesson_preview_member(): ?array
{
    $id = (int) ($_SESSION['lesson_preview'] ?? 0);
    if (!$id || !is_staff()) {
        return null;
    }
    return q_one('SELECT m.*, g.name AS group_name FROM members m LEFT JOIN training_groups g ON g.id = m.group_id WHERE m.id = ? AND m.status = "active"', [$id]);
}

/**
 * Kas žiūri pamokas. Treneris mato viską (ir nepaskelbtas). Narys (ar tėvai) - pagal savo narių grupę, diržą ir žymas:
 * 'own' - narių diržai [lygis => vardai], 'belts' - kurių diržų pamokas galima atsidaryti (savo, žemesnių ir kito).
 * Diržo nenurodžius laikoma, kad narys baltas (9 kyu).
 */
function lesson_viewer(array $a): array
{
    $preview = is_staff($a) ? lesson_preview_member() : null;
    if (is_staff($a) && !$preview) {
        return ['staff' => true, 'preview' => null, 'members' => [], 'tags' => array_keys(MEMBER_TAGS), 'own' => [], 'belts' => array_keys(BELTS)];
    }
    $members = $preview ? [$preview] : array_values(array_filter(account_members((int) $a['id']), function ($m) { return $m['status'] === 'active'; }));
    $own = [];
    foreach ($members as $m) {
        $own[$m['belt_level'] !== null ? (int) $m['belt_level'] : 1][] = $m['first_name'];
    }
    ksort($own);
    $tags = [];
    if ($members) {
        $tags = q('SELECT DISTINCT tag FROM member_tags WHERE member_id IN (' . implode(',', array_map(function ($m) { return (int) $m['id']; }, $members)) . ')')
            ->fetchAll(PDO::FETCH_COLUMN);
    }
    $max = $own ? max(array_keys($own)) : 0;
    return ['staff' => false, 'preview' => $preview, 'members' => $members, 'tags' => array_values(array_intersect(array_keys(MEMBER_TAGS), $tags)),
        'own' => $own, 'belts' => $max ? range(1, min($max + 1, max(array_keys(BELTS)))) : []];
}

/**
 * Ar aplankas (ir visi aukštesni) matomas: paskelbtas, skirtas žiūrinčiajam (pagal žymą) ir jo diržui.
 * $belt - pasirinktas diržas (null - visi): kito diržo aplankai tada nerodomi.
 */
function viewer_folder_ok(array $v, array $folders, ?int $id, ?int $belt = null): bool
{
    foreach (folder_path($folders, $id) as $f) {
        $fb = $f['belt_level'] !== null ? (int) $f['belt_level'] : null;
        if (!$v['staff'] && (!$f['is_published'] || ($f['audience'] !== 'karateka' && !in_array($f['audience'], $v['tags'], true))
                || ($fb !== null && !in_array($fb, $v['belts'], true)))) {
            return false;
        }
        if ($belt !== null && $fb !== null && $fb !== $belt) {
            return false;
        }
    }
    return true;
}

/**
 * Pamokos, kurias žiūrintysis gali atsidaryti. Pamoka matoma, jei bent vienas narys (pats ar vaikas)
 * yra pamokos grupėje (arba pamoka visoms grupėms) ir turi tą diržą, žemesnį ar vienu žemesnį (baltas mato ir geltono).
 * Teisėjų / egzaminatorių pamokas mato tik turintys tą žymą.
 */
function viewer_lessons(array $v): array
{
    if ($v['staff']) {
        return q_all('SELECT * FROM lessons ORDER BY sort_order, id');
    }
    $ids = implode(',', array_map(function ($m) { return (int) $m['id']; }, $v['members'])) ?: '0';
    $rows = q_all('SELECT l.* FROM lessons l WHERE l.is_published = 1 AND ((l.audience = "karateka" AND (
                     (l.belt_level IS NULL AND NOT EXISTS (SELECT 1 FROM lesson_groups lg WHERE lg.lesson_id = l.id))
                     OR EXISTS (SELECT 1 FROM members m WHERE m.id IN (' . $ids . ')
                                  AND (NOT EXISTS (SELECT 1 FROM lesson_groups lg WHERE lg.lesson_id = l.id)
                                       OR EXISTS (SELECT 1 FROM lesson_groups lg WHERE lg.lesson_id = l.id AND lg.group_id = m.group_id))
                                  AND (l.belt_level IS NULL OR l.belt_level <= COALESCE(m.belt_level, 1) + 1))))'
        . ($v['tags'] ? ' OR l.audience IN ("' . implode('","', $v['tags']) . '")' : '') . ') ORDER BY l.sort_order, l.id');
    $folders = lesson_folders();
    return array_values(array_filter($rows, function ($l) use ($v, $folders) {
        return $l['folder_id'] === null || viewer_folder_ok($v, $folders, (int) $l['folder_id']);
    }));
}

/** Artimiausio aplanko diržas (pats aplankas ar aukštesnis); null - aplankai be diržo */
function folder_belt(array $folders, ?int $id): ?int
{
    foreach (array_reverse(folder_path($folders, $id)) as $f) {
        if ($f['belt_level'] !== null) {
            return (int) $f['belt_level'];
        }
    }
    return null;
}

/**
 * Tik pasirinkto diržo pamokos: be diržo arba to diržo. Pamokai diržo aplanke (pvz. „8 kyu“)
 * galioja aplanko diržas, net jei pačiai pamokai nurodytas kitas.
 */
function lessons_for_belt(array $v, array $lessons, ?int $belt): array
{
    if ($belt === null) {
        return $lessons;
    }
    $folders = lesson_folders();
    return array_values(array_filter($lessons, function ($l) use ($v, $folders, $belt) {
        $fid = $l['folder_id'] !== null ? (int) $l['folder_id'] : null;
        $own = folder_belt($folders, $fid) === null && $l['belt_level'] !== null ? (int) $l['belt_level'] : null;
        return ($own === null || $own === $belt) && ($fid === null || viewer_folder_ok($v, $folders, $fid, $belt));
    }));
}

/** Treneris atsiuntė (pastaba su prisegta pamoka), dar neperžiūrėtos: naujausios pirmos. Pažymėjus „Peržiūrėjau“ - dingsta. */
function sent_lessons(array $memberIds, int $limit = 8): array
{
    $ids = implode(',', array_map('intval', $memberIds));
    if ($ids === '') {
        return [];
    }
    return q_all('SELECT l.*, MAX(cn.created_at) AS sent_at, MAX(cn.is_task = 1 AND cn.done_at IS NULL) AS task_open
                    FROM coach_notes cn JOIN lessons l ON l.id = cn.lesson_id
                   WHERE cn.member_id IN (' . $ids . ') AND l.is_published = 1 AND (cn.read_at IS NULL OR (cn.is_task = 1 AND cn.done_at IS NULL))
                   GROUP BY l.id ORDER BY sent_at DESC LIMIT ' . (int) $limit);
}

/** Aplanko „vidus“: kiek pamokų jame ir poaplankiuose */
function folder_lesson_counts(array $folders, array $lessons): array
{
    $direct = [];
    foreach ($lessons as $l) {
        if ($l['folder_id'] !== null) {
            $direct[(int) $l['folder_id']] = ($direct[(int) $l['folder_id']] ?? 0) + 1;
        }
    }
    $total = [];
    foreach ($folders as $id => $f) {
        $sum = 0;
        foreach (folder_descendant_ids($folders, $id) as $d) {
            $sum += $direct[$d] ?? 0;
        }
        $total[$id] = $sum;
    }
    return $total;
}

/** Grįžimas po veiksmo pamokų puslapyje (tik pamokos.php adresai) */
function lessons_back(string $default): string
{
    $b = (string) post('back');
    return preg_match('~^pamokos\.php(\?[\w=&%.-]*)?(#[\w-]*)?$~', $b) ? $b : $default;
}

// ---------- Turinys ----------

/** Pamokos blokai. Senos pamokos (be blokų) - tekstas ir video iš senų laukų. */
function lesson_blocks(array $lesson): array
{
    $blocks = q_all('SELECT * FROM lesson_blocks WHERE lesson_id = ? ORDER BY sort_order, id', [(int) $lesson['id']]);
    if ($blocks || !$lesson['id']) {
        return $blocks;
    }
    $legacy = [];
    foreach (lesson_videos($lesson) as $v) {
        $legacy[] = ['type' => 'video', 'youtube_id' => $v, 'body' => null, 'image' => null, 'url' => null, 'caption' => null];
    }
    if ($lesson['body']) {
        $legacy[] = ['type' => 'text', 'body' => $lesson['body'], 'youtube_id' => null, 'image' => null, 'url' => null, 'caption' => null];
    }
    return $legacy;
}

/**
 * Tekstas pamokoje: tuščia eilutė - nauja pastraipa, "- " - sąrašas, "1. " - numeruotas sąrašas,
 * **žodis** - paryškinta, nuorodos tampa paspaudžiamos.
 */
function lesson_text_html(string $text): string
{
    $inline = function (string $s): string {
        $s = e($s);
        $s = preg_replace('~\*\*(.+?)\*\*~u', '<strong>$1</strong>', $s);
        return preg_replace('~(https?://[^\s<]+)~', '<a href="$1" rel="noopener" target="_blank">$1</a>', $s);
    };
    $html = '';
    foreach (preg_split("/\R{2,}/u", trim($text)) as $para) {
        $lines = preg_split('/\R/u', $para);
        $isList = function ($re) use ($lines) {
            foreach ($lines as $l) {
                if (!preg_match($re, $l)) {
                    return false;
                }
            }
            return true;
        };
        if ($isList('/^\s*[-•*]\s+/u')) {
            $html .= '<ul>' . implode('', array_map(function ($l) use ($inline) { return '<li>' . $inline(preg_replace('/^\s*[-•*]\s+/u', '', $l)) . '</li>'; }, $lines)) . '</ul>';
        } elseif ($isList('/^\s*\d+[.)]\s+/')) {
            $html .= '<ol>' . implode('', array_map(function ($l) use ($inline) { return '<li>' . $inline(preg_replace('/^\s*\d+[.)]\s+/', '', $l)) . '</li>'; }, $lines)) . '</ol>';
        } else {
            $html .= '<p>' . implode('<br>', array_map($inline, $lines)) . '</p>';
        }
    }
    return $html;
}

function lesson_image_url(string $file, bool $thumb = false): string
{
    return url('uploads/lessons/' . ($thumb ? 'thumb_' : '') . $file);
}

function render_lesson_block(array $b): string
{
    $cap = !empty($b['caption']) ? '<figcaption>' . e($b['caption']) . '</figcaption>' : '';
    switch ($b['type']) {
        case 'heading':
            return '<h2 class="lb-heading">' . e((string) $b['body']) . '</h2>';
        case 'text':
            return '<div class="lb-text news-body">' . lesson_text_html((string) $b['body']) . '</div>';
        case 'video':
            return $b['youtube_id'] ? '<figure class="lb-video">' . youtube_embed($b['youtube_id']) . $cap . '</figure>' : '';
        case 'image':
            return $b['image'] ? '<figure class="lb-image"><a href="' . e(lesson_image_url($b['image'])) . '" target="_blank"><img src="'
                . e(lesson_image_url($b['image'])) . '" alt="' . e((string) $b['caption']) . '" loading="lazy"></a>' . $cap . '</figure>' : '';
        case 'note':
            return '<aside class="lb-note"><div class="lb-note-label">💬 Trenerio pastaba</div>' . lesson_text_html((string) $b['body']) . '</aside>';
        case 'link':
            return $b['url'] ? '<p class="lb-link"><a href="' . e($b['url']) . '" target="_blank" rel="noopener">↗ ' . e($b['body'] ?: $b['url']) . '</a></p>' : '';
    }
    return '';
}

/** Kortelėms: [lesson_id => ['thumb' => url|null, 'video' => bool, 'videos' => n, 'images' => n]] */
function lesson_card_info(array $lessons): array
{
    $out = [];
    foreach ($lessons as $l) {
        $v = lesson_videos($l);
        $out[(int) $l['id']] = ['thumb' => $v ? 'https://i.ytimg.com/vi/' . $v[0] . '/hqdefault.jpg' : null, 'video' => (bool) $v, 'videos' => count($v), 'images' => 0, 'blocks' => 0];
    }
    if ($out) {
        foreach (q_all('SELECT lesson_id, type, youtube_id, image FROM lesson_blocks WHERE lesson_id IN (' . implode(',', array_keys($out)) . ') ORDER BY sort_order, id') as $b) {
            $i = &$out[(int) $b['lesson_id']];
            $i['blocks']++;
            if ($b['type'] === 'image' && $b['image']) {
                $i['images']++;
                if (!$i['thumb']) {
                    $i['thumb'] = lesson_image_url($b['image'], true);
                }
            }
            unset($i);
        }
    }
    return $out;
}

/**
 * Išsaugo pamokos blokus (tvarka - kaip formoje) ir atnaujina senus laukus (videos, body) -
 * jie naudojami kortelėse ir paieškoje.
 */
function save_lesson_blocks(int $lessonId, array $blocks): void
{
    q('DELETE FROM lesson_blocks WHERE lesson_id = ?', [$lessonId]);
    $videos = [];
    $texts = [];
    foreach (array_values($blocks) as $i => $b) {
        q('INSERT INTO lesson_blocks (lesson_id, type, body, youtube_id, image, url, caption, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$lessonId, $b['type'], $b['body'] ?? null, $b['youtube_id'] ?? null, $b['image'] ?? null, $b['url'] ?? null, $b['caption'] ?? null, $i + 1]);
        if ($b['type'] === 'video' && !empty($b['youtube_id'])) {
            $videos[] = $b['youtube_id'];
        }
        if (in_array($b['type'], ['text', 'note', 'heading'], true) && !empty($b['body'])) {
            $texts[] = $b['body'];
        }
    }
    q('UPDATE lessons SET videos = ?, body = ? WHERE id = ?', [$videos ? implode(',', array_unique($videos)) : null, $texts ? implode("\n\n", $texts) : null, $lessonId]);
}

// ---------- Redagavimas (pamokos puslapyje) ----------

/** Formos blokai -> blokai saugojimui. Klaidos įrašomos į $errors. Grąžina ir „žalius“ duomenis formai. */
function lesson_blocks_from_post(array &$errors): array
{
    require_once APP_DIR . '/images.php';
    $out = [];
    foreach ((array) ($_POST['blocks'] ?? []) as $key => $b) {
        $type = (string) ($b['type'] ?? '');
        if (!isset(LESSON_BLOCK_TYPES[$type]) || !is_array($b)) {
            continue;
        }
        $row = ['type' => $type, 'body' => null, 'youtube_id' => null, 'image' => null, 'url' => null,
            'caption' => trim((string) ($b['caption'] ?? '')) ?: null, 'raw_url' => trim((string) ($b['url'] ?? ''))];
        $body = trim(str_replace("\r\n", "\n", (string) ($b['body'] ?? '')));
        switch ($type) {
            case 'heading':
            case 'text':
            case 'note':
                if ($body === '') {
                    continue 2;
                }
                $row['body'] = $body;
                break;
            case 'video':
                if ($row['raw_url'] === '') {
                    continue 2;
                }
                $row['youtube_id'] = youtube_id($row['raw_url']);
                if (!$row['youtube_id']) {
                    $errors[] = 'Neatpažinta YouTube nuoroda: ' . $row['raw_url'];
                }
                break;
            case 'image':
                $existing = basename((string) ($b['image'] ?? ''));
                $row['image'] = preg_match('/^[a-f0-9]{8,64}\.jpg$/', $existing) ? $existing : null;
                $f = $_FILES['block_image'] ?? null;
                if ($f && isset($f['error'][$key]) && $f['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                    try {
                        $row['image'] = save_uploaded_image(['name' => $f['name'][$key], 'type' => $f['type'][$key], 'tmp_name' => $f['tmp_name'][$key],
                            'error' => $f['error'][$key], 'size' => $f['size'][$key]], LESSON_DIR);
                    } catch (RuntimeException $ex) {
                        $errors[] = $ex->getMessage();
                    }
                }
                if (!$row['image']) {
                    continue 2;
                }
                break;
            case 'link':
                if ($row['raw_url'] === '') {
                    continue 2;
                }
                if (!preg_match('~^https?://~i', $row['raw_url'])) {
                    $errors[] = 'Nuoroda turi prasidėti http:// arba https:// - ' . $row['raw_url'];
                }
                $row['url'] = $row['raw_url'];
                $row['body'] = $body !== '' ? $body : null;
                break;
        }
        $out[] = $row;
    }
    return $out;
}

/**
 * Pamokos išsaugojimas iš formos. Grąžina pamokos id arba null (klaidos - $errors, įvesti blokai - $posted).
 */
function lesson_save_from_post(array $me, array &$errors, ?array &$posted): ?int
{
    $folders = lesson_folders();
    $id = (int) post('id');
    $title = trim(post('title'));
    $folderId = (int) post('folder_id') ?: null;
    $belt = belt_from_post('belt_level');
    $audience = isset(QUIZ_AUDIENCE[post('audience')]) ? post('audience') : 'karateka';
    $published = !empty($_POST['is_published']) ? 1 : 0;
    $groupIds = $audience === 'karateka' ? audience_from_post() : [];
    $blocks = $posted = lesson_blocks_from_post($errors);
    if ($title === '') {
        $errors[] = 'Įveskite pamokos pavadinimą.';
    }
    if ($folderId !== null && !isset($folders[$folderId])) {
        $folderId = null;
    }
    if (!$blocks && $published) {
        $errors[] = 'Pamokoje dar nieko nėra - pridėkite tekstą, video ar nuotrauką (arba išsaugokite nepaskelbtą).';
    }
    if ($errors) {
        return null;
    }
    db()->beginTransaction();
    if ($id) {
        $old = q_one('SELECT folder_id FROM lessons WHERE id = ?', [$id]);
        $moved = $old && $old['folder_id'] != $folderId;
        q('UPDATE lessons SET title = ?, folder_id = ?, belt_level = ?, audience = ?, is_published = ?, updated_at = NOW()' . ($moved ? ', sort_order = 9999' : '') . ' WHERE id = ?',
            [mb_substr($title, 0, 190), $folderId, $belt, $audience, $published, $id]);
        q('DELETE FROM lesson_groups WHERE lesson_id = ?', [$id]);
    } else {
        $sort = (int) q_value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM lessons WHERE folder_id <=> ?', [$folderId]);
        q('INSERT INTO lessons (folder_id, sort_order, title, belt_level, audience, is_published, author_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$folderId, $sort, mb_substr($title, 0, 190), $belt, $audience, $published, $me['id']]);
        $id = (int) db()->lastInsertId();
    }
    foreach ($groupIds as $gid) {
        q('INSERT IGNORE INTO lesson_groups (lesson_id, group_id) VALUES (?, ?)', [$id, $gid]);
    }
    save_lesson_blocks($id, $blocks);
    db()->commit();
    return $id;
}

/** Vienas redaguojamas blokas (ir šablonas naujam blokui su raktu __K__) */
function lesson_block_editor(array $b, string $key): string
{
    [$label, $sign] = LESSON_BLOCK_TYPES[$b['type']];
    $n = 'blocks[' . $key . ']';
    $h = '<div class="lblock lb-' . $b['type'] . '" data-key="' . e($key) . '">'
        . '<div class="lblock-head"><span class="lb-drag" title="Tempkite">⠿</span><span class="lb-sign">' . $sign . '</span><strong>' . e($label) . '</strong>'
        . '<span class="lblock-tools"><button type="button" class="lb-up" title="Aukštyn">↑</button><button type="button" class="lb-down" title="Žemyn">↓</button>'
        . '<button type="button" class="lb-del" title="Pašalinti">✕</button></span></div>'
        . '<input type="hidden" name="' . $n . '[type]" value="' . $b['type'] . '">';
    $body = e((string) ($b['body'] ?? ''));
    switch ($b['type']) {
        case 'heading':
            $h .= '<input type="text" class="lb-heading-input" name="' . $n . '[body]" value="' . $body . '" placeholder="pvz. Pagrindiniai judesiai" maxlength="190">';
            break;
        case 'text':
        case 'note':
            $h .= '<div class="lb-toolbar"><button type="button" data-fmt="bold"><b>B</b> paryškinti</button><button type="button" data-fmt="list">• sąrašas</button><button type="button" data-fmt="num">1. sąrašas</button></div>'
                . '<textarea class="lb-grow" name="' . $n . '[body]" rows="' . ($b['type'] === 'note' ? 2 : 4) . '" placeholder="' . ($b['type'] === 'note' ? 'pvz. Atkreipkite dėmesį į kvėpavimą' : 'Tekstas. Tuščia eilutė - nauja pastraipa.') . '">' . $body . '</textarea>';
            break;
        case 'video':
            $url = $b['raw_url'] ?? (!empty($b['youtube_id']) ? 'https://youtu.be/' . $b['youtube_id'] : '');
            $h .= '<div class="lb-media"><div class="lb-fields"><input type="text" class="lb-yt" name="' . $n . '[url]" value="' . e($url) . '" placeholder="YouTube nuoroda, pvz. https://youtu.be/...">'
                . '<input type="text" name="' . $n . '[caption]" value="' . e((string) ($b['caption'] ?? '')) . '" placeholder="Prierašas po video (nebūtina)" maxlength="255"></div>'
                . '<img class="lb-preview" alt="" ' . (!empty($b['youtube_id']) ? 'src="https://i.ytimg.com/vi/' . e($b['youtube_id']) . '/hqdefault.jpg"' : 'hidden') . '></div>';
            break;
        case 'image':
            $h .= '<div class="lb-media"><div class="lb-fields"><input type="file" name="block_image[' . e($key) . ']" accept="image/jpeg,image/png,image/webp">'
                . '<input type="hidden" name="' . $n . '[image]" value="' . e((string) ($b['image'] ?? '')) . '">'
                . '<input type="text" name="' . $n . '[caption]" value="' . e((string) ($b['caption'] ?? '')) . '" placeholder="Prierašas po nuotrauka (nebūtina)" maxlength="255"></div>'
                . '<img class="lb-preview" alt="" ' . (!empty($b['image']) ? 'src="' . e(lesson_image_url($b['image'])) . '"' : 'hidden') . '></div>';
            break;
        case 'link':
            $h .= '<div class="form-row"><input type="text" name="' . $n . '[url]" value="' . e($b['raw_url'] ?? (string) ($b['url'] ?? '')) . '" placeholder="https://...">'
                . '<input type="text" name="' . $n . '[body]" value="' . $body . '" placeholder="Nuorodos tekstas, pvz. WKF taisyklės (PDF)"></div>';
            break;
    }
    return $h . '</div>';
}
