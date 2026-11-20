<?php
// LTKF redagavimo bendros dalys be priklausomybių (naudoja ir LTKF puslapiai, ir redaguoti.php su karateka sistema)

const LTKF_PAGES = [
    'index' => 'Pradžia', 'apie' => 'Apie', 'klubai' => 'Klubai', 'klubams' => 'Klubams: narystė', 'kalendorius' => 'Kalendorius', 'rezultatai' => 'Rezultatai',
    'teisejai' => 'Teisėjai', 'dokumentai' => 'Dokumentai', 'naujienos' => 'Naujienos', 'kontaktai' => 'Kontaktai',
];
const LTKF_BLOCK_TYPES = ['h', 'text', 'img', 'gallery', 'files', 'btn', 'video', 'cards', 'sec'];
// Numatytasis meniu (kol redaktoriai jo nekeitė)
const LTKF_MENIU_NUMATYTAS = [['Apie', 'apie.php'], ['Klubai', 'klubai.php'], ['Kalendorius', 'kalendorius.php'], ['Rezultatai', 'rezultatai.php'], ['Teisėjai', 'teisejai.php'], ['Dokumentai', 'dokumentai.php'], ['Naujienos', 'naujienos.php'], ['Kontaktai', 'kontaktai.php']];

function ltkf_norm(string $s): string
{
    return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $s));
}

function ltkf_youtube_id(string $url): string
{
    return preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/))([A-Za-z0-9_-]{11})~', $url, $m) ? $m[1] : '';
}

function ltkf_safe_href(string $h): string
{
    $h = trim($h);
    if ($h === '' || preg_match('~^(https?://|mailto:|tel:|/|#|\.\./|[a-z0-9_-]+\.php)~i', $h)) {
        return $h;
    }
    return preg_match('~^[a-z0-9.-]+\.[a-z]{2,}(/|$)~i', $h) ? 'https://' . $h : '#';
}

/** Teksto bloko HTML: tik saugios žymos (pastraipos, paryškinimas, sąrašai, nuorodos, antraštės) */
function ltkf_clean_html(string $html): string
{
    if (trim($html) === '' || !class_exists('DOMDocument')) {
        return '';
    }
    $allowed = ['p', 'br', 'b', 'strong', 'i', 'em', 'u', 'a', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote'];
    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="c">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = $doc->getElementById('c');
    if (!$root) {
        return '';
    }
    $walk = function (DOMNode $n) use (&$walk, $allowed, $doc) {
        foreach (iterator_to_array($n->childNodes) as $ch) {
            if ($ch instanceof DOMElement) {
                $walk($ch);
                $tag = strtolower($ch->tagName);
                if ($tag === 'div') {   // naršyklės dažnai sukuria <div> vietoj <p>
                    $p = $doc->createElement('p');
                    while ($ch->firstChild) {
                        $p->appendChild($ch->firstChild);
                    }
                    $n->replaceChild($p, $ch);
                    continue;
                }
                if (!in_array($tag, $allowed, true)) {
                    while ($ch->firstChild) {
                        $n->insertBefore($ch->firstChild, $ch);
                    }
                    $n->removeChild($ch);
                    continue;
                }
                $href = $tag === 'a' ? ltkf_safe_href($ch->getAttribute('href')) : '';
                foreach (iterator_to_array($ch->attributes) as $at) {
                    $ch->removeAttribute($at->name);
                }
                if ($tag === 'a') {
                    $ch->setAttribute('href', $href ?: '#');
                    if (preg_match('~^https?://~', $href)) {
                        $ch->setAttribute('target', '_blank');
                        $ch->setAttribute('rel', 'noopener');
                    }
                }
            } elseif (!($ch instanceof DOMText)) {
                $n->removeChild($ch);
            }
        }
    };
    $walk($root);
    $out = '';
    foreach ($root->childNodes as $ch) {
        $out .= $doc->saveHTML($ch);
    }
    return $out;
}

/** Bloko duomenų valymas prieš saugant */
function ltkf_clean_block($b): ?array
{
    if (!is_array($b) || !in_array($b['t'] ?? '', LTKF_BLOCK_TYPES, true)) {
        return null;
    }
    $s = function ($v, int $max = 500) { return mb_substr(trim((string) $v), 0, $max); };
    $src = function ($v) { $v = trim((string) $v); return preg_match('~^(\.\./uploads/ltkf/|https?://)~', $v) ? mb_substr($v, 0, 500) : ''; };
    $out = ['t' => $b['t'], 'id' => preg_replace('/[^a-z0-9-]/', '', (string) ($b['id'] ?? '')) ?: bin2hex(random_bytes(4))];
    switch ($b['t']) {
        case 'h': $out['text'] = $s($b['text'] ?? '', 200); break;
        case 'text': $out['html'] = ltkf_clean_html(mb_substr((string) ($b['html'] ?? ''), 0, 60000)); break;
        case 'img': $out += ['src' => $src($b['src'] ?? ''), 'cap' => $s($b['cap'] ?? '', 300), 'w' => in_array($b['w'] ?? '', ['full', 'half', 'small'], true) ? $b['w'] : 'full']; break;
        case 'gallery': $out['imgs'] = array_values(array_filter(array_map(function ($i) use ($src) { return is_array($i) && ($u = $src($i['src'] ?? '')) ? ['src' => $u] : null; }, (array) ($b['imgs'] ?? [])))); break;
        case 'files':
            $out['title'] = $s($b['title'] ?? '', 200);
            $out['items'] = array_values(array_filter(array_map(function ($i) use ($s, $src) { return is_array($i) && ($u = $src($i['url'] ?? '')) ? ['url' => $u, 'name' => $s($i['name'] ?? 'Failas', 200), 'size' => (int) ($i['size'] ?? 0)] : null; }, (array) ($b['items'] ?? []))));
            break;
        case 'btn': $out += ['label' => $s($b['label'] ?? '', 120), 'href' => ltkf_safe_href($s($b['href'] ?? '', 500)), 'style' => ($b['style'] ?? '') === 'ghost' ? 'ghost' : '']; break;
        case 'video': $out['url'] = $s($b['url'] ?? '', 300); break;
        case 'sec':   // nauja skiltis su fonu: pavadinimas, tekstas, fono spalva ar nuotrauka, šviesus tekstas
            $out += ['title' => $s($b['title'] ?? '', 200), 'html' => ltkf_clean_html(mb_substr((string) ($b['html'] ?? ''), 0, 60000)),
                     'c' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($b['c'] ?? '')) ? $b['c'] : '', 'img' => $src($b['img'] ?? ''), 'light' => !empty($b['light']) ? 1 : 0];
            break;
        case 'cards': $out['items'] = array_values(array_filter(array_map(function ($i) use ($s, $src) { return is_array($i) ? ['title' => $s($i['title'] ?? '', 200), 'text' => $s($i['text'] ?? '', 2000), 'img' => $src($i['img'] ?? '')] : null; }, (array) ($b['items'] ?? [])))); break;
    }
    return $out;
}

