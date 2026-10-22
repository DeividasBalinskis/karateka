<?php
// Pamokos: aplankai (medis), turinys blokais, matomumas pagal aplanką

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

/** Visi aplankai: [id => aplankas], surikiuoti pagal tvarką */
function lesson_folders(): array
{
    static $cache = null;
    if ($cache === null) {
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

/** Visos paskyros narių žymos (teisėjas, egzaminatorius) */
function account_tag_list(array $a): array
{
    $tags = [];
    foreach (account_member_tags((int) $a['id']) as $memberTags) {
        $tags = array_merge($tags, $memberTags);
    }
    return array_values(array_unique($tags));
}

/** Ar narys mato aplanką: jis ir visi aukštesni aplankai paskelbti ir skirti jam (pagal žymą) */
function folder_allowed(array $folders, ?int $id, array $tags): bool
{
    foreach (folder_path($folders, $id) as $f) {
        if (!$f['is_published'] || ($f['audience'] !== 'karateka' && !in_array($f['audience'], $tags, true))) {
            return false;
        }
    }
    return true;
}

/** Aplanko pasirinkimas (select) su įtraukomis; $exclude - aplankai, į kuriuos negalima (pats ir jo vidus) */
function folder_options(array $folders, ?int $selected, array $exclude = [], string $rootLabel = '- pagrindinis lygis -'): string
{
    $html = '<option value="">' . e($rootLabel) . '</option>';
    $walk = function (?int $parent, int $depth) use (&$walk, $folders, $selected, $exclude, &$html) {
        foreach ($folders as $f) {
            $p = $f['parent_id'] !== null ? (int) $f['parent_id'] : null;
            if ($p !== $parent || in_array((int) $f['id'], $exclude, true)) {
                continue;
            }
            $html .= '<option value="' . (int) $f['id'] . '"' . ((int) $f['id'] === $selected ? ' selected' : '') . '>'
                . str_repeat("\u{00A0}\u{00A0}\u{00A0}", $depth) . ($depth ? '└ ' : '') . e(($f['icon'] ? $f['icon'] . ' ' : '') . $f['title']) . '</option>';
            $walk((int) $f['id'], $depth + 1);
        }
    };
    $walk(null, 0);
    return $html;
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

/** Aplanko „vidus“ nariui: kiek matomų pamokų jame ir poaplankiuose */
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
