<?php
require_once __DIR__ . '/bendri.php';
// LTKF svetainės redagavimas: kas gali redaguoti (karateka.lt adminas arba redaktorius su nuoroda), puslapių duomenys
// (pakeisti tekstai, paslėptos skiltys, pridėti blokai), meniu ir išvaizda. Duomenys - karateka duomenų bazėje (db/038).
//
// Kaip veikia: puslapis sugeneruojamas kaip anksčiau, o prieš išsiunčiant naršyklei pritaikomi pakeitimai:
//  - tekstai: redaktorius spaudžia bet kurį tekstą puslapyje ir jį keičia; saugoma [pradinis tekstas => naujas];
//  - skiltys (<section>): galima paslėpti; tarp skilčių - zonos, į kurias dedami blokai (tekstas, nuotrauka, galerija, failai, mygtukas, vaizdo įrašas).

/** site_settings reikšmė iš karateka duomenų bazės */
function ltkf_setting(string $name, string $default = ''): string
{
    static $cache = [];
    if (!array_key_exists($name, $cache)) {
        $cache[$name] = $default;
        if ($db = karateka_db()) {
            try {
                $st = $db->prepare('SELECT value FROM site_settings WHERE name = ?');
                $st->execute([$name]);
                $v = $st->fetchColumn();
                $cache[$name] = $v === false ? $default : (string) $v;
            } catch (Throwable $ex) {
            }
        }
    }
    return $cache[$name];
}

function ltkf_json_setting(string $name): array
{
    $v = json_decode(ltkf_setting($name, ''), true);
    return is_array($v) ? $v : [];
}

/** Redaktoriai su nuorodomis [['t' => token, 'n' => vardas, 'on' => 1, 'c' => data], ...] */
function ltkf_editors(): array
{
    return array_values(array_filter(ltkf_json_setting('ltkf_editors'), 'is_array'));
}

/** Kas redaguoja: ['name' => ..., 'admin' => bool] arba null */
function ltkf_redaktorius(): ?array
{
    static $who = false;
    if ($who !== false) {
        return $who;
    }
    $who = null;
    $s = karateka_sesija();
    if (karateka_adminas()) {
        $name = 'Adminas';
        try {
            $st = karateka_db()->prepare('SELECT CONCAT(first_name, " ", last_name) FROM accounts WHERE id = ?');
            $st->execute([(int) ($s['account_id'] ?? 0)]);
            $name = trim((string) $st->fetchColumn()) ?: $name;
        } catch (Throwable $ex) {
        }
        $who = ['name' => $name, 'admin' => true];
    } elseif (!empty($s['ltkf_editor'])) {
        foreach (ltkf_editors() as $ed) {
            if (!empty($ed['on']) && hash_equals((string) $ed['t'], (string) $s['ltkf_editor'])) {
                $who = ['name' => (string) ($s['ltkf_editor_name'] ?? $ed['n']), 'admin' => false];
            }
        }
    }
    return $who;
}

function ltkf_edit_mode(): bool
{
    return isset($_GET['redaguoti']) && ltkf_redaktorius() !== null;
}

/** Puslapio raktas: failo vardas (apie, klubai...) arba „p:raktas“ naujiems puslapiams */
function ltkf_slug(): string
{
    $f = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'), '.php');
    if ($f === 'puslapis') {
        return 'p:' . preg_replace('/[^a-z0-9-]/', '', (string) ($_GET['p'] ?? ''));
    }
    return preg_replace('/[^a-z0-9_-]/', '', $f) ?: 'index';
}

/** Puslapio duomenys: ['texts' => [], 'hidden' => [], 'blocks' => [zona => [...]], 'title' => ..., 'custom' => bool] */
function ltkf_page(string $slug): array
{
    static $cache = [];
    if (!isset($cache[$slug])) {
        $cache[$slug] = ['texts' => [], 'hidden' => [], 'blocks' => []];
        if ($db = karateka_db()) {
            try {
                $st = $db->prepare('SELECT data FROM ltkf_pages WHERE slug = ?');
                $st->execute([$slug]);
                $d = json_decode((string) $st->fetchColumn(), true);
                if (is_array($d)) {
                    $cache[$slug] = $d + $cache[$slug];
                }
            } catch (Throwable $ex) {
                // dar be db/038
            }
        }
    }
    return $cache[$slug];
}

/** Nauji (sukurti) puslapiai [slug => pavadinimas] */
function ltkf_custom_pages(): array
{
    $out = [];
    if ($db = karateka_db()) {
        try {
            foreach ($db->query("SELECT slug, data FROM ltkf_pages WHERE slug LIKE 'p:%' ORDER BY slug")->fetchAll() as $r) {
                $d = json_decode((string) $r['data'], true);
                $out[substr($r['slug'], 2)] = (string) ($d['title'] ?? substr($r['slug'], 2));
            }
        } catch (Throwable $ex) {
        }
    }
    return $out;
}

/** Meniu [[pavadinimas, nuoroda], ...] - redaguojamas („☰ Meniu“), kitaip - numatytasis */
function ltkf_meniu(): array
{
    $m = ltkf_json_setting('ltkf_menu');
    if ($m) {
        return array_values(array_filter(array_map(function ($i) { return is_array($i) && ($i['l'] ?? '') !== '' ? [(string) $i['l'], (string) ($i['h'] ?? '#')] : null; }, $m)));
    }
    $out = [];
    foreach (MENIU as $key => [$label, $href]) {
        if ($key !== 'index') {
            $out[] = [$label, $href];
        }
    }
    return $out;
}

/** Išvaizda ir kontaktai: ['hero_img', 'hero_color', 'pavadinimas', 'el_pastas', 'telefonas'] */
function ltkf_nust(): array
{
    return ltkf_json_setting('ltkf_nust');
}

// ---------- Tekstų pritaikymas ----------

/** Pritaiko puslapio pakeitimus HTML fragmentui (main arba footer). $zones - ar dėti blokų zonas tarp skilčių. */
function ltkf_apply(string $html, array $page, bool $edit, bool $zones): string
{
    $texts = (array) ($page['texts'] ?? []);
    $hidden = array_map('strval', (array) ($page['hidden'] ?? []));
    $blocks = (array) ($page['blocks'] ?? []);
    if (!$edit && !$texts && !$hidden && !array_filter($blocks)) {
        return $html;
    }
    if (!class_exists('DOMDocument')) {
        return $html;
    }
    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="ltkf-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = $doc->getElementById('ltkf-root');
    if (!$root) {
        return $html;
    }
    // Tekstai: giliausias elementas, kurio visas tekstas sutampa su pradiniu
    if ($texts) {
        $xp = new DOMXPath($doc);
        foreach ($xp->query('.//*[not(self::script or self::style or self::svg)]', $root) as $el) {
            $t = ltkf_norm($el->textContent);
            if ($t === '' || !array_key_exists($t, $texts)) {
                continue;
            }
            $deeper = false;
            foreach ($el->childNodes as $ch) {
                if ($ch instanceof DOMElement && ltkf_norm($ch->textContent) === $t) {
                    $deeper = true;
                }
            }
            if ($deeper) {
                continue;
            }
            while ($el->firstChild) {
                $el->removeChild($el->firstChild);
            }
            foreach (explode("\n", (string) $texts[$t]) as $i => $line) {
                if ($i) {
                    $el->appendChild($doc->createElement('br'));
                }
                $el->appendChild($doc->createTextNode($line));
            }
            if ($edit) {
                $el->setAttribute('data-o', $t);
            }
        }
    }
    if ($zones) {
        $i = 0;
        $kids = [];
        foreach ($root->childNodes as $ch) {
            $kids[] = $ch;
        }
        $first = true;
        foreach ($kids as $ch) {
            if (!($ch instanceof DOMElement) || $ch->tagName !== 'section') {
                continue;
            }
            if ($first) {
                $first = false;
                ltkf_zone_insert($doc, $root, $ch, 'pradzia', $blocks, $edit, true);
            }
            $key = (string) $i;
            $ch->setAttribute('data-sec', $key);
            if (in_array($key, $hidden, true)) {
                if ($edit) {
                    $ch->setAttribute('class', trim($ch->getAttribute('class') . ' lb-hidden'));
                } else {
                    $root->removeChild($ch);
                    $i++;
                    continue;
                }
            }
            ltkf_zone_insert($doc, $root, $ch, 'po-' . $i, $blocks, $edit, false);
            $i++;
        }
        if ($first) {   // be skilčių (naujas puslapis) - viena zona
            $z = ltkf_zone_node($doc, 'po-0', (array) ($blocks['po-0'] ?? []), $edit);
            if ($z) {
                $root->appendChild($z);
            }
        }
    }
    $out = '';
    foreach ($root->childNodes as $ch) {
        $out .= $doc->saveHTML($ch);
    }
    return $out;
}

function ltkf_zone_insert(DOMDocument $doc, DOMElement $root, DOMElement $sec, string $zone, array $blocks, bool $edit, bool $before): void
{
    $z = ltkf_zone_node($doc, $zone, (array) ($blocks[$zone] ?? []), $edit);
    if (!$z) {
        return;
    }
    if ($before) {
        $root->insertBefore($z, $sec);
    } elseif ($sec->nextSibling) {
        $root->insertBefore($z, $sec->nextSibling);
    } else {
        $root->appendChild($z);
    }
}

function ltkf_zone_node(DOMDocument $doc, string $zone, array $blocks, bool $edit): ?DOMElement
{
    if (!$blocks && !$edit) {
        return null;
    }
    $html = '<div class="wrap lb-zone" data-zone="' . e($zone) . '"' . ($edit ? ' data-blocks="' . e(json_encode(array_values($blocks), JSON_UNESCAPED_UNICODE)) . '"' : '') . '>'
        . ($edit ? '' : ltkf_blocks_html($blocks)) . '</div>';
    $frag = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $frag->loadHTML('<?xml encoding="utf-8"?><div id="z">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $node = $frag->getElementById('z');
    if (!$node || !$node->firstChild) {
        return null;
    }
    return $doc->importNode($node->firstChild, true);
}

// ---------- Blokai ----------

function ltkf_size(int $b): string
{
    return $b >= 1048576 ? number_format($b / 1048576, 1, ',', '') . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
}

function ltkf_blocks_html(array $blocks): string
{
    $h = '';
    foreach ($blocks as $b) {
        if (!is_array($b)) {
            continue;
        }
        switch ($b['t'] ?? '') {
            case 'h':
                $h .= '<h2 class="lb lb-h">' . e($b['text'] ?? '') . '</h2>';
                break;
            case 'text':
                $h .= '<div class="lb lb-text prose">' . ltkf_clean_html((string) ($b['html'] ?? '')) . '</div>';
                break;
            case 'img':
                if (!empty($b['src'])) {
                    $h .= '<figure class="lb lb-img w-' . e(in_array($b['w'] ?? '', ['full', 'half', 'small'], true) ? $b['w'] : 'full') . '"><a href="' . e($b['src']) . '" target="_blank" rel="noopener"><img src="' . e($b['src']) . '" alt="' . e($b['cap'] ?? '') . '" loading="lazy"></a>'
                        . (($b['cap'] ?? '') !== '' ? '<figcaption>' . e($b['cap']) . '</figcaption>' : '') . '</figure>';
                }
                break;
            case 'gallery':
                $imgs = array_filter((array) ($b['imgs'] ?? []), function ($i) { return is_array($i) && !empty($i['src']); });
                if ($imgs) {
                    $h .= '<div class="lb lb-gallery">' . implode('', array_map(function ($i) { return '<a href="' . e($i['src']) . '" target="_blank" rel="noopener"><img src="' . e($i['src']) . '" alt="" loading="lazy"></a>'; }, $imgs)) . '</div>';
                }
                break;
            case 'files':
                $items = array_filter((array) ($b['items'] ?? []), function ($i) { return is_array($i) && !empty($i['url']); });
                if ($items) {
                    $h .= (($b['title'] ?? '') !== '' ? '<h3 class="lb-files-h">' . e($b['title']) . '</h3>' : '') . '<ul class="lb lb-files doc-list">' . implode('', array_map(function ($i) {
                        $ext = strtoupper(pathinfo((string) $i['url'], PATHINFO_EXTENSION));
                        return '<li><span class="doc-icon" aria-hidden="true">' . e(substr($ext ?: 'FAILAS', 0, 4)) . '</span><span><a href="' . e($i['url']) . '" target="_blank" rel="noopener" download>' . e($i['name'] ?? 'Failas') . '</a>'
                            . (!empty($i['size']) ? ' <small class="muted">' . e(ltkf_size((int) $i['size'])) . '</small>' : '') . '</span></li>';
                    }, $items)) . '</ul>';
                }
                break;
            case 'btn':
                if (($b['label'] ?? '') !== '') {
                    $href = ltkf_safe_href((string) ($b['href'] ?? '#'));
                    $ext = preg_match('~^https?://~', $href) ? ' target="_blank" rel="noopener"' : '';
                    $h .= '<p class="lb lb-btn"><a class="btn ' . (($b['style'] ?? '') === 'ghost' ? 'btn-ghost-dark' : 'btn-primary') . '" href="' . e($href) . '"' . $ext . '>' . e($b['label']) . '</a></p>';
                }
                break;
            case 'video':
                if ($id = ltkf_youtube_id((string) ($b['url'] ?? ''))) {
                    $h .= '<div class="lb lb-video"><iframe src="https://www.youtube-nocookie.com/embed/' . e($id) . '" title="YouTube" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>';
                }
                break;
            case 'cards':
                $items = array_filter((array) ($b['items'] ?? []), function ($i) { return is_array($i) && (($i['title'] ?? '') !== '' || ($i['text'] ?? '') !== ''); });
                if ($items) {
                    $h .= '<div class="lb lb-cards benefits">' . implode('', array_map(function ($i) {
                        return '<div class="benefit">' . (!empty($i['img']) ? '<img src="' . e($i['img']) . '" alt="" loading="lazy">' : '') . '<h3>' . e($i['title'] ?? '') . '</h3><p>' . nl2br(e($i['text'] ?? '')) . '</p></div>';
                    }, $items)) . '</div>';
                }
                break;
        }
    }
    return $h;
}

/** Redaktoriaus juosta ir scenarijus (rodoma tik redaktoriams) */
function ltkf_editor_ui(string $slug): string
{
    $who = ltkf_redaktorius();
    if (!$who) {
        return '';
    }
    $q = $_GET;
    if (!ltkf_edit_mode()) {
        $q['redaguoti'] = 1;
        return '<a class="ed-fab" href="?' . e(http_build_query($q)) . '">✏️ Redaguoti puslapį</a><link rel="stylesheet" href="assets/redaktorius.css?v=' . (int) @filemtime(dirname(__DIR__) . '/assets/redaktorius.css') . '">';
    }
    unset($q['redaguoti']);
    $pages = [];
    foreach (LTKF_PAGES as $k => $n) {
        $pages[] = ['h' => $k . '.php', 'n' => $n];
    }
    foreach (ltkf_custom_pages() as $k => $n) {
        $pages[] = ['h' => 'puslapis.php?p=' . $k, 'n' => $n, 'custom' => 1];
    }
    $cfg = [
        'api' => 'redaguoti.php', 'csrf' => (string) (karateka_sesija()['csrf'] ?? ''), 'slug' => $slug, 'who' => $who['name'], 'admin' => $who['admin'],
        'menu' => array_map(function ($i) { return ['l' => $i[0], 'h' => $i[1]]; }, ltkf_meniu()), 'pages' => $pages, 'nust' => ltkf_nust() + ['pavadinimas' => n('pavadinimas'), 'el_pastas' => n('el_pastas'), 'telefonas' => n('telefonas')],
        'exit' => '?' . http_build_query($q), 'custom' => strpos($slug, 'p:') === 0,
    ];
    $v = (int) @filemtime(dirname(__DIR__) . '/assets/redaktorius.js');
    return '<link rel="stylesheet" href="assets/redaktorius.css?v=' . (int) @filemtime(dirname(__DIR__) . '/assets/redaktorius.css') . '">'
        . '<script>window.LTKF_ED=' . str_replace('</', '<\/', json_encode($cfg, JSON_UNESCAPED_UNICODE)) . ';</script><script src="assets/redaktorius.js?v=' . $v . '" defer></script>';
}
