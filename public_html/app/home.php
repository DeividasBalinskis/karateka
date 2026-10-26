<?php
// Pagrindinis puslapis iš blokų. Adminas puslapyje (index.php?redaguoti=1) keičia tekstus, nuotraukas, sąrašus,
// blokus slepia, perstato ir prideda naujus. Saugoma site_settings 'home_page' (JSON); kol nieko nepakeista - numatytasis turinys.

const HOME_DIR = PUBLIC_DIR . '/uploads/site';

// Bloko tipas => [pavadinimas redagavimui, ar gali būti keli]
const HOME_BLOCK_TYPES = [
    'groups'      => ['Treniruotės: grupės, kainos, tvarkaraštis', false],
    'events'      => ['Artėjantys renginiai', false],
    'news'        => ['Naujausios naujienos', false],
    'about'       => ['Tekstas su nuotraukų skaidrėmis', true],
    'text'        => ['Tekstas (su nuotrauka ar be)', true],
    'values'      => ['Istorija ir vertybės', false],
    'instructors' => ['Instruktoriai', false],
    'banner'      => ['Juosta su tekstu', true],
    'contacts'    => ['Kontaktai', false],
    'trial'       => ['Išbandyti (registracijos forma)', false],
    'partners'    => ['Partneriai (logotipai)', false],
];

// Grupių skaidrės: kategorija => skaidrės id (nuorodos #vaikams ir pan.)
const HOME_GROUP_SLIDES = ['vaikai' => 'vaikams', 'jaunimas' => 'jaunimui', 'suauge' => 'suaugusiems'];

/** Numatytasis bloko turinys (dabartiniai pagrindinio puslapio tekstai) */
function home_block_defaults(string $type): array
{
    switch ($type) {
        case 'groups':
            return ['title' => 'Kam ieškote treniruočių?', 'cta' => 'Išbandyti', 'cats' => [
                'vaikai' => ['tab' => 'Vaikams', 'eyebrow' => 'VAIKAMS', 'title' => 'Žaismingas įvadas į discipliną ir pagarbą',
                    'text' => 'Vaikų ir priešmokyklinukų grupė, kur mokomasi per žaidimą — pagrindinės technikos, koordinacija ir pirmieji žingsniai karate kelyje.',
                    'image' => 'img/vaikai.jpg', 'pos' => '50% 25%'],
                'jaunimas' => ['tab' => 'Jaunimui', 'eyebrow' => 'JAUNIMUI', 'title' => 'Tempas, technika ir pasiruošimas varžyboms',
                    'text' => 'Paaugliams skirtos treniruotės, kuriose derinama technika, fizinis pasirengimas ir noras siekti rezultatų — nuo pirmų kata iki varžybų.',
                    'image' => 'img/jaunimas.jpg', 'pos' => '62% 20%'],
                'suauge' => ['tab' => 'Suaugusiems', 'eyebrow' => 'SUAUGUSIEMS', 'title' => 'Jėga, forma ir aiški galva po darbo dienos',
                    'text' => 'Treniruotės suaugusiems, pritaikytos tiek pradedantiesiems, tiek turintiems patirties. Realus fizinis krūvis kartu su savigynos pagrindais.',
                    'image' => 'img/suaugusieji.jpg', 'pos' => '24% 30%'],
            ]];
        case 'events':
            return ['title' => 'Artėjantys renginiai', 'link' => 'Visi renginiai →'];
        case 'news':
            return ['title' => 'Naujausios naujienos', 'link' => 'Visos naujienos →'];
        case 'about':
            return ['eyebrow' => 'Apie klubą', 'title' => 'Klubas, statantis charakterį',
                'text' => "„Karateka\" — tradicinio karate-do klubas Vilniuje, kuriame savo kelią gali pradėti ir tęsti vaikai, jaunimas ir suaugusieji.\n\n"
                    . 'Mus vienija ne tik technikos tobulinimas, bet ir noras augti kaip asmenybėms — stiprinti kūną, ugdyti charakterį ir mokytis pagarbos sau bei aplinkiniams.',
                'quote' => '„Tikras karate meistriškumas prasideda nuo darbo su savimi — kūno, proto ir dvasios darnos."',
                'slides' => [['image' => 'img/apie-1.jpg'], ['image' => 'img/apie-2.jpg'], ['image' => 'img/apie-3.jpg'], ['image' => 'img/apie-4.jpg'], ['image' => 'img/apie-5.jpg']]];
        case 'text':
            return ['eyebrow' => '', 'title' => 'Naujas tekstas', 'text' => 'Parašykite tekstą. Tuščia eilutė - nauja pastraipa, **žodis** - paryškinta.', 'image' => '', 'side' => 'right'];
        case 'values':
            return ['title' => 'Šaknys ir vertybės', 'kicker' => 'Mūsų istorija',
                'text' => "Klubo šaknys siekia **2013 metus**, kai treniravomės Tradicinio karate-do klubo „Sanrei\" komandoje. Įgiję patirties ir subrendę savo idėjai, **2017 metais** įkūrėme savarankišką klubą „Karateka\", kuriam vadovauja Denis Balinskis.\n\n"
                    . 'Denis savo karate kelią pradėjo būdamas vos 5 metų — ir šią vaikystės svajonę įgyvendino, sukurdamas bendruomenę, kurioje šiandien sportuoja žmonės nuo 2 iki 54 metų.',
                'items' => [
                    ['sign' => '道', 'tr' => 'kelias', 'title' => 'Saviugda', 'color' => 'rose'],
                    ['sign' => '力', 'tr' => 'jėga', 'title' => 'Charakterio ir valios stiprinimas', 'color' => 'indigo'],
                    ['sign' => '忍', 'tr' => 'kantrybė', 'title' => 'Savidisciplina', 'color' => 'green'],
                    ['sign' => '礼', 'tr' => 'pagarba', 'title' => 'Pagarba tradicijoms ir dojo etiketui', 'color' => 'rose'],
                    ['sign' => '守', 'tr' => 'apsauga', 'title' => 'Savigynos įgūdžiai', 'color' => 'indigo'],
                    ['sign' => '和', 'tr' => 'harmonija', 'title' => 'Vidinė pusiausvyra', 'color' => 'green'],
                ]];
        case 'instructors':
            return ['title' => 'Mūsų instruktoriai',
                'text' => 'Klubo instruktoriai nuolat gilina žinias, dalyvaudami stažuotėse Lietuvoje ir užsienyje — karate, fizioterapijos, psichologijos, sporto, medicinos, masažų, biomechanikos, sporto mitybos, kinesiologijos ir sporto pedagogikos srityse. Tai leidžia mums treniruotes kurti remiantis ne tik karate tradicijomis, bet ir šiuolaikiniais mokslo pasiekimais.',
                'items' => [
                    ['name' => 'Denis Balinskis', 'rank' => '3 Dan · klubo vadovas', 'photo' => '', 'lead' => true],
                    ['name' => 'Lukas Čiulkinas', 'rank' => '1 Dan', 'photo' => '', 'lead' => false],
                    ['name' => 'Joris Kėrys', 'rank' => '1 Dan', 'photo' => '', 'lead' => false],
                    ['name' => 'Esmilė Česevičiūtė', 'rank' => '1 Dan', 'photo' => '', 'lead' => false],
                    ['name' => 'Karolis Kiela', 'rank' => '1 Dan', 'photo' => '', 'lead' => false],
                    ['name' => 'Giedrius Li Tang', 'rank' => '1 Dan', 'photo' => '', 'lead' => false],
                ]];
        case 'banner':
            return ['text' => '„Karateka" — tai klubas, kuriame karate tampa **visos šeimos veikla**. Treniruotėse kartu sportuoja tėvai su vaikais — amžiaus riba mums nėra kliūtis, svarbiausia noras augti ir tobulėti.'];
        case 'contacts':
            return ['title' => 'Kontaktai', 'locations' => true, 'locations_title' => 'Treniruočių vietos', 'items' => [
                ['mark' => 'V', 'label' => 'VšĮ Karate Ateitis', 'value' => 'Vilnius, Lietuva'],
                ['mark' => '@', 'label' => 'El. paštas', 'value' => 'info@karateka.lt'],
                ['mark' => 'T', 'label' => 'Telefonas', 'value' => '+370 601 44392'],
                ['mark' => '#', 'label' => 'Įmonės kodas', 'value' => '305105392'],
                ['mark' => '$', 'label' => 'Sąskaita', 'value' => 'LT76 7300 0101 6008 0310'],
            ]];
        case 'trial':
            return ['title' => 'Išbandyti', 'subtitle' => 'Užpildykite formą ir išbandykite pirmąją treniruotę nemokamai',
                'heading' => 'Pradėkite nuo dviejų nemokamų treniruočių', 'text' => 'Užpildykite formą ir susisieksime, kad suderintume laiką jums tinkamiausiai grupei.',
                'button' => 'Siųsti'];
        case 'partners':
            return ['title' => 'Partneriai', 'items' => [
                ['image' => 'img/partneriai/ltkf.jpg', 'name' => 'Lietuvos tradicinio karate-do federacija', 'url' => ''],
                ['image' => 'img/partneriai/sanrei.jpg', 'name' => 'Sanrei karate klubas', 'url' => ''],
                ['image' => 'img/partneriai/vilniaus-karate-mokykla.jpg', 'name' => 'Vilniaus karate mokykla', 'url' => ''],
                ['image' => 'img/partneriai/karate-akademija.jpg', 'name' => 'Karate akademija', 'url' => ''],
                ['image' => 'img/partneriai/takami.jpg', 'name' => 'Takami tradicinio karate-do klubas', 'url' => ''],
                ['image' => 'img/partneriai/budo-cup.jpg', 'name' => 'Budo Cup Open', 'url' => ''],
            ]];
    }
    return [];
}

/** Numatytasis puslapis: [id, tipas, meniu pavadinimas] */
function home_default_page(): array
{
    $page = [];
    foreach ([['grupes', 'groups', 'Treniruotės'], ['renginiai', 'events', ''], ['naujienos', 'news', ''], ['apie', 'about', 'Apie klubą'],
              ['vertybes', 'values', ''], ['instruktoriai', 'instructors', ''], ['bendruomene', 'banner', ''], ['kontaktai', 'contacts', 'Kontaktai'],
              ['registracija', 'trial', '2 treniruotės nemokamai'], ['partneriai', 'partners', '']] as [$id, $type, $menu]) {
        $page[] = ['id' => $id, 'type' => $type, 'menu' => $menu, 'hidden' => false, 'd' => home_block_defaults($type)];
    }
    return $page;
}

/** Puslapio blokai: išsaugoti (su naujais numatytais laukais) arba numatytieji */
function home_page(bool $fresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$fresh) {
        return $cache;
    }
    $saved = json_decode((string) site_setting('home_page', ''), true);
    if (!is_array($saved) || !$saved) {
        return $cache = home_default_page();
    }
    $page = [];
    foreach ($saved as $b) {
        if (!isset(HOME_BLOCK_TYPES[$b['type'] ?? ''])) {
            continue;
        }
        $def = home_block_defaults($b['type']);
        $d = array_replace($def, is_array($b['d'] ?? null) ? $b['d'] : []);
        if ($b['type'] === 'groups') {
            foreach (HOME_GROUP_SLIDES as $cat => $_) {
                $d['cats'][$cat] = array_replace($def['cats'][$cat], (array) ($d['cats'][$cat] ?? []));
            }
        }
        $page[] = ['id' => (string) $b['id'], 'type' => $b['type'], 'menu' => (string) ($b['menu'] ?? ''), 'hidden' => !empty($b['hidden']), 'd' => $d];
    }
    return $cache = $page;
}

/** Ar rodyti „Išbandyti“ (registracijos formą ir mygtuką meniu): prisijungusiems nariams nerodoma */
function home_show_trial(): bool
{
    return current_account() === null || home_edit_mode();
}

function home_edit_mode(): bool
{
    static $on = null;
    if ($on === null) {
        $on = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php' && get('redaguoti') === '1' && is_admin();
    }
    return $on;
}

/** Meniu nuorodos iš blokų su „meniu pavadinimu“: [[tekstas, #id, ar mygtukas]] */
function home_nav(): array
{
    $out = [];
    foreach (home_page() as $b) {
        if ($b['hidden'] || $b['menu'] === '' || ($b['type'] === 'trial' && current_account() !== null)) {
            continue;
        }
        $out[] = [$b['menu'], '#' . $b['id'], $b['type'] === 'trial'];
    }
    return $out;
}

// ---------- Išsaugojimas ----------

/** Paveikslėlio kelias: tik svetainės img/ ar įkeltos nuotraukos */
function home_image_ok(string $path): bool
{
    return (bool) preg_match('~^(img/[\w/-]+\.(jpg|jpeg|png|webp)|uploads/site/[a-f0-9]{8,64}\.jpg)$~', $path);
}

/** Bloko duomenys iš redaktoriaus: tik tekstai, taip/ne ir sąrašai; nuotraukos - tik leistini keliai */
function home_clean($v, string $key = '', int $depth = 0)
{
    if (is_bool($v)) {
        return $v;
    }
    if (is_string($v) || is_numeric($v)) {
        $s = trim(str_replace(["\r\n", "\r"], "\n", (string) $v));
        if (in_array($key, ['image', 'photo'], true)) {
            return home_image_ok($s) ? $s : '';
        }
        if ($key === 'url') {
            return preg_match('~^https?://[^\s"<>]+$~i', $s) ? mb_substr($s, 0, 300) : '';
        }
        return mb_substr($s, 0, 4000);
    }
    if (is_array($v) && $depth < 4) {
        $out = [];
        $isList = array_keys($v) === range(0, count($v) - 1);
        foreach (array_slice($v, 0, 60, true) as $k => $x) {
            if (!$isList && !preg_match('/^[a-z_]{1,30}$/', (string) $k)) {
                continue;
            }
            $out[$isList ? count($out) : $k] = home_clean($x, $isList ? $key : (string) $k, $depth + 1);
        }
        return $out;
    }
    return '';
}

/** Išsaugo puslapį (ankstesnė versija lieka „Grąžinti ankstesnę“) */
function home_save(array $blocks): void
{
    $page = [];
    $ids = [];
    $singles = [];
    foreach ($blocks as $b) {
        $type = (string) ($b['type'] ?? '');
        if (!isset(HOME_BLOCK_TYPES[$type]) || (!HOME_BLOCK_TYPES[$type][1] && isset($singles[$type]))) {
            continue;
        }
        $singles[$type] = true;
        $id = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($b['id'] ?? '')));
        if ($id === '' || isset($ids[$id])) {
            $id = $type . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        }
        $ids[$id] = true;
        $page[] = ['id' => $id, 'type' => $type, 'menu' => mb_substr(trim((string) ($b['menu'] ?? '')), 0, 40), 'hidden' => !empty($b['hidden']),
            'd' => home_clean(is_array($b['d'] ?? null) ? $b['d'] : [])];
    }
    set_site_setting('home_page_prev', (string) site_setting('home_page', ''));
    set_site_setting('home_page', json_encode($page, JSON_UNESCAPED_UNICODE));
}

// ---------- Atvaizdavimas (su redagavimo žymėmis adminui) ----------

/** Tekstas: tuščia eilutė - nauja pastraipa, **žodis** - paryškinta ($strong - kuo paryškinti) */
function home_text_html(string $text, string $strong = 'strong'): string
{
    $html = '';
    foreach (preg_split("/\n{2,}/u", trim($text)) as $para) {
        if ($para === '') {
            continue;
        }
        $p = preg_replace('~\*\*(.+?)\*\*~su', '<' . $strong . '>$1</' . $strong . '>', e($para));
        $html .= '<p>' . nl2br($p, false) . '</p>';
    }
    return $html;
}

/** Redaguojamas tekstas: $multi - keli sakiniai / pastraipos */
function hb_t(string $f, string $value, string $tag = 'span', string $class = '', bool $multi = false, string $strong = 'strong'): string
{
    $cls = $class !== '' ? ' class="' . e($class) . '"' : '';
    if (!home_edit_mode()) {
        return '<' . $tag . $cls . '>' . ($multi ? home_text_html($value, $strong) : e($value)) . '</' . $tag . '>';
    }
    return '<' . $tag . ' class="' . e(trim($class . ' hb-edit' . ($multi ? ' hb-ml' : ''))) . '" data-f="' . e($f) . '" contenteditable="true" spellcheck="true">'
        . e($value) . '</' . $tag . '>';
}

/** Nuotraukos laukas: atributai konteineriui ir mygtukai (tik redaguojant) */
function hb_img_attrs(string $f, string $value): string
{
    return home_edit_mode() ? ' data-img data-f="' . e($f) . '" data-value="' . e($value) . '"' : '';
}

function hb_img_tools(bool $clearable = false): string
{
    return home_edit_mode() ? '<span class="hb-img-tools"><button type="button" class="hb-img-btn" title="Keisti nuotrauką">📷 Keisti</button>'
        . ($clearable ? '<button type="button" class="hb-img-clear" title="Be nuotraukos">✕</button>' : '') . '</span>' : '';
}

/** Sąrašo atributai, elemento įrankiai ir „+ Pridėti“ */
function hb_list(string $key): string
{
    return home_edit_mode() ? ' data-list="' . e($key) . '"' : '';
}

function hb_item(): string
{
    return home_edit_mode() ? ' data-item' : '';
}

function hb_item_tools(): string
{
    return home_edit_mode() ? '<span class="hb-item-tools"><span class="hb-drag" title="Tempkite">⠿</span><button type="button" class="hb-item-del" title="Pašalinti">✕</button></span>' : '';
}

/** Redagavimo laukelis (taip/ne, pasirinkimas ar trumpas tekstas) - rodomas tik redaguojant */
function hb_input(string $f, $value, string $label, array $options = []): string
{
    if (!home_edit_mode()) {
        return '';
    }
    if (is_bool($value)) {
        return '<label class="hb-opt"><input type="checkbox" data-f="' . e($f) . '"' . ($value ? ' checked' : '') . '> ' . e($label) . '</label>';
    }
    if ($options) {
        $html = '<label class="hb-opt">' . e($label) . ' <select data-f="' . e($f) . '">';
        foreach ($options as $k => $l) {
            $html .= '<option value="' . e($k) . '"' . ((string) $value === (string) $k ? ' selected' : '') . '>' . e($l) . '</option>';
        }
        return $html . '</select></label>';
    }
    return '<label class="hb-opt">' . e($label) . ' <input type="text" data-f="' . e($f) . '" value="' . e((string) $value) . '"></label>';
}

/** Sąrašas: elementai, šablonas naujam elementui ir „+ Pridėti“ (redaguojant) */
function hb_list_items(array $items, callable $render, array $blank, string $addLabel): string
{
    $html = '';
    foreach ($items as $i => $it) {
        $html .= $render(is_array($it) ? array_replace($blank, $it) : $blank, $i);
    }
    if (home_edit_mode()) {
        $html .= '<template class="hb-item-tpl">' . $render($blank, 0) . '</template>';
    }
    return $html;
}

function hb_add_button(string $label): string
{
    return home_edit_mode() ? '<button type="button" class="hb-add-item">+ ' . e($label) . '</button>' : '';
}

/** Vardo raidės (avatarui be nuotraukos) */
function initials(string $name): string
{
    $out = '';
    foreach (preg_split('/\s+/u', trim($name)) as $w) {
        if ($w !== '' && mb_strlen($out) < 2) {
            $out .= mb_strtoupper(mb_substr($w, 0, 1));
        }
    }
    return $out;
}

/** Vienas blokas */
function home_render_block(array $b): string
{
    $d = $b['d'];
    $edit = home_edit_mode();
    $id = e($b['id']);
    switch ($b['type']) {
        case 'groups':
            $slides = '';
            $tabs = '';
            $i = 0;
            $editors = '';
            foreach (HOME_GROUP_SLIDES as $cat => $sid) {
                $c = $d['cats'][$cat];
                $facts = $cat === 'vaikai'
                    ? '<div><div class="kicker">Kainos</div><div data-live="price-' . $cat . '">' . render_price($cat) . '</div></div>'
                      . '<div class="full"><div class="kicker">Kur treniruojamės</div><div class="schedule-picker"><div class="picker-locations loc-scroll loc-accordion" data-live="schedule-' . $cat . '">'
                      . render_kids_locations() . '</div><div class="picker-schedule" id="vaikamsSchedulePanel"><div class="kicker">Tvarkaraštis</div><p class="schedule-placeholder">← Pasirinkite vietą kairėje</p></div></div></div>'
                    : '<div><div class="kicker">Kainos</div><div data-live="price-' . $cat . '">' . render_price($cat) . '</div></div>'
                      . '<div><div class="kicker">Tvarkaraštis</div><div data-live="schedule-' . $cat . '">' . render_category_schedule($cat) . '</div></div>'
                      . '<div class="full"><div class="kicker">Kur treniruojamės</div><div data-live="locations-' . $cat . '">' . render_category_locations($cat) . '</div></div>';
                $slides .= '<div class="group-section" id="' . $sid . '" data-obj="' . $cat . '">'
                    . '<div class="group-photo-panel"' . hb_img_attrs('image', $c['image']) . '><img src="' . e(url($c['image'] ?: 'img/vaikai.jpg')) . '" alt="" style="object-position:' . e($c['pos'] ?: '50% 30%') . ';">' . hb_img_tools() . '</div>'
                    . '<div class="group-text-panel"><div class="group-content">'
                    . ($edit ? '<div class="hb-slide-tools"><input type="hidden" data-f="pos" value="' . e($c['pos']) . '">' . hb_input('tab', $c['tab'], 'Skirtukas:') . '<button type="button" class="hb-sched-btn" data-cat="' . $cat . '">✎ Kaina ir tvarkaraštis</button></div>' : '')
                    . hb_t('eyebrow', $c['eyebrow'], 'div', 'eyebrow') . hb_t('title', $c['title'], 'h2') . hb_t('text', $c['text'], 'p')
                    . '<div class="group-facts">' . $facts . '</div>'
                    . (home_show_trial() ? '<a href="#registracija" class="btn btn-primary group-cta">' . e($d['cta']) . '</a>' : '')
                    . '</div></div></div>';
                $tabs .= '<button type="button" data-i="' . $i . '"' . ($i === 0 ? ' class="active"' : '') . ' role="tab">' . e($c['tab']) . '</button>';
                $i++;
                if ($edit) {
                    $groups = q_all('SELECT * FROM training_groups WHERE is_active = 1 AND category = ? ORDER BY sort_order, name', [$cat]);
                    $editors .= '<dialog class="hb-sched" id="sched-' . $cat . '"><div class="hb-sched-head"><h3>' . e(GROUP_CATEGORIES[$cat]) . ': kaina ir tvarkaraštis</h3>'
                        . '<button type="button" class="btn btn-ghost btn-sm" data-close-dialog>Uždaryti</button></div>'
                        . '<p class="small muted">Išsaugoma iš karto (✓) - nereikia spausti „Išsaugoti“. Grupes ir vietas tvarkysite skiltyje <a href="' . e(url('admin/grupes.php')) . '" target="_blank">Grupės</a>.</p>'
                        . price_editor($cat) . schedule_editor($groups) . '</dialog>';
                }
            }
            return '<section style="padding:0; position:relative;" id="' . $id . '">'
                . '<div class="chooser">' . hb_t('title', $d['title'], 'h1', 'chooser-title') . ($edit ? hb_input('cta', $d['cta'], 'Mygtukas:') : '') . '</div>'
                . '<div class="carousel-wrap"><div class="group-scroll" id="groupScroll" data-obj="cats">' . $slides . '</div>'
                . '<button class="group-arrow prev" id="groupPrev" aria-label="Ankstesnė grupė">‹</button><button class="group-arrow next" id="groupNext" aria-label="Kita grupė">›</button>'
                . '<div class="chooser-buttons" id="groupTabs" role="tablist">' . $tabs . '</div></div>' . $editors . '</section>';

        case 'events':
            $events = public_upcoming_events(3);
            if (!$events && !$edit) {
                return '';
            }
            $html = '<section id="' . $id . '" class="home-events-section"><div class="events-wrap"><div class="events-head">' . hb_t('title', $d['title'], 'h2')
                . '<a class="events-all" href="renginiai.php">' . hb_t('link', $d['link']) . '</a></div><div class="events-grid">';
            foreach ($events as $ev) {
                $ts = strtotime($ev['starts_on']);
                $html .= '<div class="he-wrap reveal"><a class="panel home-event" href="renginiai.php#e' . (int) $ev['id'] . '">'
                    . '<div class="he-date"><span class="d">' . date('j', $ts) . '</span><span class="m">' . e(mb_substr(LT_MONTHS_GEN[(int) date('n', $ts)], 0, 3)) . '</span></div>'
                    . '<div class="he-body"><span class="he-type t-' . e($ev['type']) . '">' . e(EVENT_TYPES[$ev['type']]) . '</span><h3>' . e($ev['title']) . '</h3>'
                    . '<div class="he-meta">' . e(fmt_date($ev['starts_on'])) . ($ev['ends_on'] && $ev['ends_on'] !== $ev['starts_on'] ? ' – ' . e(fmt_date($ev['ends_on'])) : '')
                    . ($ev['start_time'] ? ', ' . e(fmt_time($ev['start_time'])) : '') . ($ev['location'] ? ' · ' . e($ev['location']) : '') . '</div></div></a>'
                    . (!$edit ? event_edit_link((int) $ev['id'], 'index') : '') . '</div>';
            }
            if (!$events) {
                $html .= '<p class="hb-note">Artėjančių renginių nėra - lankytojai šio bloko nemato.</p>';
            }
            return $html . '</div></div></section>';

        case 'news':
            $news = q_all('SELECT * FROM news WHERE is_published = 1 AND published_at <= NOW() AND ' . visibility_sql() . ' ORDER BY published_at DESC LIMIT 3');
            if (!$news && !$edit) {
                return '';
            }
            $html = '<section id="' . $id . '" class="home-news-section"><div class="events-wrap"><div class="events-head">' . hb_t('title', $d['title'], 'h2')
                . '<a class="events-all" href="naujienos.php">' . hb_t('link', $d['link']) . '</a></div><div class="news-grid">';
            foreach ($news as $n) {
                $cover = news_cover((int) $n['id']);
                $body = trim(preg_replace('/\s+/', ' ', $n['body']));
                $html .= '<a class="panel home-news reveal" href="naujienos.php?id=' . (int) $n['id'] . '"><div class="hn-cover' . ($cover ? '' : ' empty') . '">'
                    . ($cover ? '<img src="' . e($cover) . '" alt="" loading="lazy">' : '<img class="hn-logo" src="img/logo.png" alt="">') . '</div>'
                    . '<div class="hn-body"><div class="hn-date">' . e(fmt_date($n['published_at'])) . '</div><h3>' . e($n['title']) . '</h3>'
                    . '<p>' . e(mb_strlen($body) > 160 ? mb_substr($body, 0, 160) . '…' : $body) . '</p></div></a>';
            }
            if (!$news) {
                $html .= '<p class="hb-note">Naujienų nėra - lankytojai šio bloko nemato.</p>';
            }
            return $html . '</div></div></section>';

        case 'about':
            $slides = array_values(array_filter((array) $d['slides'], function ($s) { return !empty($s['image']); }));
            $html = '<section id="' . $id . '" class="hb-about"><div class="wrap"><div class="about-grid"><div class="about-left reveal"><div class="section-head" style="margin-bottom:20px;">'
                . ($d['eyebrow'] !== '' || $edit ? hb_t('eyebrow', $d['eyebrow'], 'div', 'eyebrow') : '')
                . hb_t('title', $d['title'], 'h2', 'styled') . '</div><div class="panel about-panel">' . hb_t('text', $d['text'], 'div', 'about-text', true)
                . ($d['quote'] !== '' || $edit ? hb_t('quote', $d['quote'], 'div', 'about-quote') : '') . '</div></div>';
            if ($edit) {
                $html .= '<div class="about-photo hb-slides-edit reveal"><div class="hb-slides" data-list="slides">'
                    . hb_list_items((array) $d['slides'], function ($s) {
                        return '<div class="hb-slide" data-item><div class="hb-slide-img"' . hb_img_attrs('image', $s['image']) . '><img src="' . e($s['image'] ? url($s['image']) : '') . '" alt="">' . hb_img_tools() . '</div>' . hb_item_tools() . '</div>';
                    }, ['image' => ''], 'Nuotrauka')
                    . '</div>' . hb_add_button('Pridėti nuotrauką') . '</div>';
            } elseif ($slides) {
                $html .= '<div class="about-photo reveal" data-slider>';
                foreach ($slides as $k => $s) {
                    $html .= '<div class="slide' . ($k === 0 ? ' active' : '') . '"><img src="' . e(url($s['image'])) . '" alt=""' . ($k ? ' loading="lazy"' : '') . '></div>';
                }
                $html .= '</div>';
            }
            return $html . '</div></div></section>';

        case 'text':
            $img = $d['image'] !== '' || $edit;
            return '<section id="' . $id . '" class="hb-text"><div class="wrap"><div class="hb-text-grid' . ($img ? ' with-img' : '') . ($d['side'] === 'left' ? ' img-left' : '') . '">'
                . '<div class="reveal"><div class="section-head" style="margin-bottom:20px;">' . ($d['eyebrow'] !== '' || $edit ? hb_t('eyebrow', $d['eyebrow'], 'div', 'eyebrow') : '')
                . hb_t('title', $d['title'], 'h2', 'styled') . '</div><div class="panel about-panel">' . hb_t('text', $d['text'], 'div', 'about-text', true) . '</div>'
                . ($edit ? '<div class="hb-opts">' . hb_input('side', $d['side'], 'Nuotrauka:', ['right' => 'dešinėje', 'left' => 'kairėje']) . '</div>' : '') . '</div>'
                . ($img ? '<div class="hb-text-photo reveal"' . hb_img_attrs('image', $d['image']) . '>' . ($d['image'] !== '' ? '<img src="' . e(url($d['image'])) . '" alt="">' : '<img alt="" hidden>')
                    . ($edit && $d['image'] === '' ? '<span class="hb-img-empty">Be nuotraukos</span>' : '') . hb_img_tools(true) . '</div>' : '')
                . '</div></div></section>';

        case 'values':
            $items = array_values((array) $d['items']);
            $half = (int) ceil(count($items) / 2);
            $item = function ($v, $i) use ($half) {
                $color = in_array($v['color'] ?? '', ['rose', 'indigo', 'green'], true) ? $v['color'] : 'green';
                return '<div class="value-item' . ($i < $half ? ' l' : '') . ' reveal"' . hb_item() . '>' . hb_t('sign', $v['sign'], 'span', 'value-icon ' . $color)
                    . '<span>' . hb_t('tr', $v['tr'], 'span', 'value-translation') . hb_t('title', $v['title'], 'strong') . '</span>'
                    . (home_edit_mode() ? hb_input('color', $color, '', ['rose' => 'rožinė', 'indigo' => 'mėlyna', 'green' => 'žalia']) : '') . hb_item_tools() . '</div>';
            };
            $blank = ['sign' => '道', 'tr' => 'vertimas', 'title' => 'Nauja vertybė', 'color' => 'green'];
            $col = function (array $list, int $offset, string $cls) use ($item, $edit) {
                $html = '<div class="roots-col ' . $cls . '"' . ($edit ? ' data-list="items"' : '') . '>';
                foreach ($list as $k => $v) {
                    $html .= $item(array_replace(['sign' => '', 'tr' => '', 'title' => '', 'color' => 'green'], (array) $v), $k + $offset);
                }
                return $html . '</div>';
            };
            return '<section id="' . $id . '" class="hb-values"><div class="wrap"><div class="about-sub-head about-sub-head-center">' . hb_t('title', $d['title'], 'h3') . '</div>'
                . '<div class="roots-row">' . $col(array_slice($items, 0, $half), 0, 'left')
                . '<div class="panel roots-history reveal">' . hb_t('kicker', $d['kicker'], 'div', 'kicker') . hb_t('text', $d['text'], 'div', 'roots-text', true) . '</div>'
                . $col(array_slice($items, $half), $half, 'right') . '</div>'
                . ($edit ? '<template class="hb-item-tpl">' . $item($blank, 99) . '</template><div class="hb-center">' . hb_add_button('Pridėti vertybę') . '</div>' : '')
                . '</div></section>';

        case 'instructors':
            $card = function ($p) {
                $photo = (string) ($p['photo'] ?? '');
                return '<div class="panel instructor-card' . (!empty($p['lead']) ? ' lead' : '') . ' reveal"' . hb_item() . '>'
                    . '<div class="avatar' . ($photo ? ' has-photo' : '') . '"' . hb_img_attrs('photo', $photo) . '>'
                    . ($photo || home_edit_mode() ? '<img src="' . e($photo ? url($photo) : '') . '" alt=""' . ($photo ? '' : ' hidden') . '>' : '')
                    . '<span class="hb-initials"' . ($photo ? ' hidden' : '') . '>' . e(initials((string) $p['name'])) . '</span>'
                    . hb_img_tools(true) . '</div>' . hb_t('name', (string) $p['name'], 'div', 'name') . hb_t('rank', (string) $p['rank'], 'div', 'rank')
                    . (home_edit_mode() ? hb_input('lead', !empty($p['lead']), 'Paryškinti') : '') . hb_item_tools() . '</div>';
            };
            return '<section id="' . $id . '" class="hb-instructors"><div class="wrap"><div class="about-sub-head about-sub-head-center">' . hb_t('title', $d['title'], 'h3') . '</div>'
                . '<div class="instructors-row"><div class="panel instructors-intro reveal">' . hb_t('text', $d['text'], 'div', '', true) . '</div>'
                . '<div class="instructor-grid"' . hb_list('items') . '>' . hb_list_items((array) $d['items'], $card, ['name' => 'Vardas Pavardė', 'rank' => '1 Dan', 'photo' => '', 'lead' => false], '') . '</div></div>'
                . ($edit ? '<div class="hb-center">' . hb_add_button('Pridėti instruktorių') . '</div>' : '') . '</div></section>';

        case 'banner':
            return '<section id="' . $id . '" class="hb-banner"><div class="wrap"><div class="panel community-banner reveal">' . hb_t('text', $d['text'], 'div', '', true, 'span') . '</div></div></section>';

        case 'contacts':
            $row = function ($c) {
                return '<li' . hb_item() . '>' . hb_t('mark', (string) $c['mark'], 'span', 'mark') . '<div>' . hb_t('label', (string) $c['label'], 'strong') . hb_t('value', (string) $c['value'], 'span') . '</div>' . hb_item_tools() . '</li>';
            };
            $locs = '';
            if ($d['locations']) {
                foreach (GROUP_CATEGORIES as $cat => $catLabel) {
                    $list = array_unique(array_filter(array_column(public_groups($cat), 'location')));
                    if ($list) {
                        $locs .= '<div class="contact-locs-cat"><strong>' . e($catLabel) . '</strong><ul>';
                        foreach ($list as $l) {
                            $locs .= '<li>' . e($l) . '</li>';
                        }
                        $locs .= '</ul></div>';
                    }
                }
            }
            return '<section id="' . $id . '" class="hb-contacts"><div class="wrap"><div class="section-head reveal">' . hb_t('title', $d['title'], 'h2', 'styled') . '</div>'
                . '<div class="contacts-grid' . ($locs || $edit ? ' two' : '') . '"><div class="panel contact-panel reveal"><ul class="contact-list"' . hb_list('items') . '>'
                . hb_list_items((array) $d['items'], $row, ['mark' => '•', 'label' => 'Pavadinimas', 'value' => 'Reikšmė'], '') . '</ul>' . hb_add_button('Pridėti kontaktą') . '</div>'
                . ($locs || $edit ? '<div class="panel contact-panel contact-locs reveal">' . hb_t('locations_title', $d['locations_title'], 'h3') . ($edit ? hb_input('locations', (bool) $d['locations'], 'Rodyti treniruočių vietas (iš grupių)') : '')
                    . ($locs ?: '<p class="muted small">Vietos nerodomos.</p>') . '</div>' : '')
                . '</div></div></section>';

        case 'trial':
            if (!home_show_trial()) {
                return '';
            }
            return '<section class="contact" id="' . $id . '"><div class="wrap"><div class="section-head reveal">' . hb_t('title', $d['title'], 'h2', 'styled') . hb_t('subtitle', $d['subtitle'], 'p') . '</div>'
                . '<div class="panel registration-grid reveal"><div class="registration-panel">' . hb_t('heading', $d['heading'], 'h3', 'registration-title') . hb_t('text', $d['text'], 'p') . '</div>'
                . '<div class="registration-panel"><form class="contact-form" id="registrationForm">'
                . '<input type="text" name="vardas" placeholder="Mokinio vardas, pavardė" required><input type="email" name="email" placeholder="El. paštas" required>'
                . '<input type="tel" name="telefonas" placeholder="Telefono numeris" required><select name="lokacija" required><option value="" disabled selected>Pasirinkite lokaciją</option>'
                . render_trial_location_options() . '</select><select name="gimimo_metai" id="gimimoMetai" class="is-empty"><option value="" disabled selected>Gimimo metai</option></select>'
                . '<textarea name="zinute" rows="3" placeholder="Žinutė (nebūtina)"></textarea>'
                . ($edit ? '<div class="btn btn-primary">' . hb_t('button', $d['button']) . '</div>' : '<button type="submit" class="btn btn-primary">' . e($d['button']) . '</button>')
                . '<div id="formStatus" class="form-status"></div></form></div></div></div></section>';

        case 'partners':
            $logo = function ($p) {
                $img = '<img src="' . e($p['image'] ? url($p['image']) : '') . '" alt="' . e((string) $p['name']) . '" loading="lazy">';
                if (home_edit_mode()) {
                    return '<div class="partner" data-item><div class="partner-logo"' . hb_img_attrs('image', (string) $p['image']) . '>' . $img . hb_img_tools() . '</div>'
                        . hb_input('name', (string) $p['name'], 'Pavadinimas') . hb_input('url', (string) $p['url'], 'Nuoroda') . hb_item_tools() . '</div>';
                }
                if (!$p['image']) {
                    return '';
                }
                $inner = '<div class="partner-logo">' . $img . '</div>';
                return $p['url'] ? '<a class="partner" href="' . e($p['url']) . '" target="_blank" rel="noopener" title="' . e((string) $p['name']) . '">' . $inner . '</a>'
                    : '<div class="partner" title="' . e((string) $p['name']) . '">' . $inner . '</div>';
            };
            return '<section id="' . $id . '" class="hb-partners"><div class="wrap"><div class="partners-head">' . hb_t('title', $d['title'], 'h3') . '</div>'
                . '<div class="partners-row"' . hb_list('items') . '>' . hb_list_items((array) $d['items'], $logo, ['image' => '', 'name' => '', 'url' => ''], '') . '</div>'
                . ($edit ? '<div class="hb-center">' . hb_add_button('Pridėti logotipą') . '</div>' : '') . '</div></section>';
    }
    return '';
}

/** Redaguojant: bloko apvalkalas su įrankiais (tempti, aukštyn/žemyn, meniu, slėpti, ištrinti, pradinis) */
function home_block_wrap(array $b, string $html): string
{
    if (!home_edit_mode()) {
        return $html;
    }
    [$label] = HOME_BLOCK_TYPES[$b['type']];
    return '<div class="hb' . ($b['hidden'] ? ' is-hidden' : '') . '" data-block data-id="' . e($b['id']) . '" data-type="' . e($b['type']) . '">'
        . '<div class="hb-bar"><span class="hb-block-drag" title="Tempkite">⠿</span><strong>' . e($label) . '</strong>'
        . '<button type="button" class="hb-up" title="Aukštyn">↑</button><button type="button" class="hb-down" title="Žemyn">↓</button>'
        . '<label class="hb-menu" title="Jei įrašyta - rodoma viršutiniame meniu">Meniu: <input type="text" class="hb-menu-input" value="' . e($b['menu']) . '" maxlength="40" placeholder="nerodoma"></label>'
        . '<button type="button" class="hb-hide">' . ($b['hidden'] ? '👁 Rodyti' : '🙈 Slėpti') . '</button>'
        . '<button type="button" class="hb-reset" title="Grąžinti pradinį šio bloko turinį">↺ Pradinis</button>'
        . '<button type="button" class="hb-del" title="Ištrinti bloką">✕</button>'
        . '<button type="button" class="hb-insert" title="Pridėti bloką po šiuo">+ Blokas po šiuo</button></div>'
        . $html . '</div>';
}
