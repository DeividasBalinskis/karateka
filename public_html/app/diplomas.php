<?php
// Taikos diplomai (Denio pageidavimas #7): prizininkams - pagal įkeltą Excel šabloną arba paprastas spausdinamas dizainas.
// Šablone (.xlsx, pirmas lapas - vienas diplomas su dizainu) langeliuose ar teksto laukuose rašoma {vardas}, {vieta}, {kategorija}, {klubas},
// {varzybos}, {data}, {rungtis}, {vietaR} (I, II, III), {vietove}. Rezultatas - vienas Excel failas, kiekvienas diplomas atskirame lape (spausdinti visus).
// Šablonas bendras visoms varžyboms: uploads/diplomai (užrakintas naršyklei), pavadinimas ir data - site_settings „taika_diploma“.

const DIPLOMA_DIR = PUBLIC_DIR . '/uploads/diplomai';
const DIPLOMA_FILE = DIPLOMA_DIR . '/sablonas.xlsx';
const DIPLOMA_KEYS = ['vardas' => 'name', 'vardaspavarde' => 'name', 'dalyvis' => 'name', 'vieta' => 'place', 'vietar' => 'roman', 'vietaromeniskai' => 'roman',
                      'kategorija' => 'category', 'klubas' => 'club', 'varzybos' => 'title', 'data' => 'date', 'rungtis' => 'discipline', 'vietove' => 'location',
                      'name' => 'name', 'place' => 'place', 'category' => 'category', 'club' => 'club'];

/** Įkeltas šablonas: ['name' => originalus pavadinimas, 'at' => data] arba null */
function diploma_template(): ?array
{
    if (!is_file(DIPLOMA_FILE)) {
        return null;
    }
    $meta = json_decode((string) site_setting('taika_diploma', '{}'), true) ?: [];
    return ['name' => (string) ($meta['name'] ?? 'sablonas.xlsx'), 'at' => (string) ($meta['at'] ?? date('Y-m-d', (int) filemtime(DIPLOMA_FILE))),
            'keys' => (array) ($meta['keys'] ?? [])];
}

/** Įkelti šabloną; grąžina klaidos tekstą arba '' */
function diploma_template_save(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return t('Failas neįkeltas.');
    }
    if ($file['size'] > 15 * 1024 * 1024) {
        return t('Failas per didelis (daugiausia 15 MB).');
    }
    if (!preg_match('/\.xlsx$/i', (string) $file['name'])) {
        return t('Reikia Excel failo .xlsx (Excel: Failas → Įrašyti kaip → Excel darbaknygė).');
    }
    if (!class_exists('ZipArchive')) {
        return t('Serveryje nėra ZipArchive - Excel šablono naudoti negalima.');
    }
    $z = new ZipArchive();
    if ($z->open($file['tmp_name']) !== true || $z->locateName('xl/workbook.xml') === false) {
        return t('Tai ne Excel .xlsx failas.');
    }
    $z->close();
    $keys = diploma_found_keys($file['tmp_name']);
    if (!is_dir(DIPLOMA_DIR)) {
        @mkdir(DIPLOMA_DIR, 0755, true);
    }
    if (!is_file(DIPLOMA_DIR . '/.htaccess')) {
        @file_put_contents(DIPLOMA_DIR . '/.htaccess', "Require all denied\n");
    }
    if (!move_uploaded_file($file['tmp_name'], DIPLOMA_FILE)) {
        return t('Nepavyko išsaugoti failo.');
    }
    set_site_setting('taika_diploma', json_encode(['name' => mb_substr(basename((string) $file['name']), 0, 120), 'at' => date('Y-m-d'), 'keys' => $keys], JSON_UNESCAPED_UNICODE));
    return '';
}

function diploma_template_delete(): void
{
    @unlink(DIPLOMA_FILE);
    set_site_setting('taika_diploma', '{}');
}

/** Žymos pavadinimas be lietuviškų raidžių ir tarpų: „{Vardas Pavardė}“ → „vardaspavarde“ */
function diploma_key(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i', 'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z']);
    return preg_replace('/[^a-z0-9]/', '', $s) ?? '';
}

/** Failo pavadinimui: mažosios raidės be lietuviškų ženklų */
function diploma_key_slug(string $s): string
{
    $s = mb_strtolower($s);
    return strtr($s, ['ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i', 'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z']);
}

/** Kokios žymos rastos šablone (rodoma nustatymuose) */
function diploma_found_keys(string $path): array
{
    $found = [];
    $z = new ZipArchive();
    if ($z->open($path) !== true) {
        return [];
    }
    for ($i = 0; $i < $z->numFiles; $i++) {
        $n = (string) $z->getNameIndex($i);
        if (preg_match('~^xl/(sharedStrings|worksheets/[^/]+|drawings/[^/]+)\.xml$~', $n)) {
            $text = html_entity_decode(strip_tags((string) $z->getFromIndex($i)), ENT_QUOTES | ENT_XML1, 'UTF-8');
            if (preg_match_all('/\{([^{}]{2,30})\}/u', $text, $m)) {
                foreach ($m[1] as $k) {
                    if (isset(DIPLOMA_KEYS[diploma_key($k)])) {
                        $found['{' . trim($k) . '}'] = true;
                    }
                }
            }
        }
    }
    $z->close();
    return array_keys($found);
}

/** Prizininkai (vieta ≤ 3) iš varžybų santraukos; $catId - tik viena kategorija */
function diploma_rows(array $t, ?string $catId = null): array
{
    $r = tournament_results($t);
    $disc = ['kata' => 'Kata', 'kumite' => 'Kumite', 'fukugo' => 'Fukugo', 'kihon' => 'Kihon', 'enbu' => 'Enbu', 'sumo' => 'Sumo'];
    $rows = [];
    foreach ((array) ($r['categories'] ?? []) as $c) {
        if ($catId !== null && (string) ($c['id'] ?? '') !== $catId) {
            continue;
        }
        $places = (array) ($c['places'] ?? []);
        usort($places, function ($a, $b) { return (int) $a['place'] <=> (int) $b['place']; });
        foreach ($places as $p) {
            $place = (int) ($p['place'] ?? 0);
            if ($place < 1 || $place > 3) {
                continue;
            }
            $rows[] = ['name' => (string) $p['name'], 'club' => (string) ($p['club'] ?? ''), 'place' => (string) $place, 'roman' => ['', 'I', 'II', 'III'][$place],
                       'category' => (string) $c['name'], 'discipline' => $disc[$c['discipline'] ?? ''] ?? (string) ($c['discipline'] ?? ''),
                       'title' => (string) $t['title'], 'date' => fmt_date((string) $t['starts_on'], true), 'location' => (string) ($t['location'] ?? '')];
        }
    }
    return $rows;
}

/** Tekste žymas {…} pakeičia prizininko duomenimis */
function diploma_fill(string $text, array $row): string
{
    return preg_replace_callback('/\{([^{}]{2,30})\}/u', function ($m) use ($row) {
        $k = DIPLOMA_KEYS[diploma_key($m[1])] ?? null;
        return $k === null ? $m[0] : (string) ($row[$k] ?? '');
    }, $text) ?? $text;
}

/**
 * Užpildytas Excel: šablono pirmas lapas nukopijuojamas kiekvienam prizininkui (su paveikslėliais), žymos pakeičiamos.
 * Grąžina .xlsx turinį arba null (nėra ZipArchive / šablonas sugadintas).
 */
function diplomas_xlsx(string $tpl, array $rows): ?string
{
    if (!class_exists('ZipArchive') || !$rows || !is_file($tpl)) {
        return null;
    }
    $rows = array_slice($rows, 0, 400);
    $in = new ZipArchive();
    if ($in->open($tpl) !== true) {
        return null;
    }
    $f = [];
    for ($i = 0; $i < $in->numFiles; $i++) {
        $f[(string) $in->getNameIndex($i)] = (string) $in->getFromIndex($i);
    }
    $in->close();
    $wb = $f['xl/workbook.xml'] ?? '';
    $wbRels = $f['xl/_rels/workbook.xml.rels'] ?? '';
    if (!preg_match('~<sheet\b[^>]*/>~', $wb, $first) || !preg_match('~r:id="([^"]+)"~', $first[0], $rid)
        || !preg_match('~<Relationship\b[^>]*Id="' . preg_quote($rid[1], '~') . '"[^>]*/>~', $wbRels, $rel) || !preg_match('~Target="([^"]+)"~', $rel[0], $tg)) {
        return null;
    }
    $sheetPath = 'xl/' . preg_replace('~^/?(xl/)?~', '', $tg[1]);
    if (!isset($f[$sheetPath])) {
        return null;
    }
    $x = function (string $s): string { return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); };
    // Bendros eilutės (sharedStrings): tekstas su žymomis
    $shared = [];
    if (isset($f['xl/sharedStrings.xml']) && preg_match_all('~<si>(.*?)</si>~s', $f['xl/sharedStrings.xml'], $sm)) {
        foreach ($sm[1] as $i => $si) {
            preg_match_all('~<t\b[^>]*>(.*?)</t>~s', $si, $tm);
            $shared[$i] = html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
    }
    $fillSheet = function (string $xml, array $row) use ($shared, $x): string {
        // Langeliai su bendra eilute, kurioje yra žyma → tekstas pačiame langelyje (stilius lieka)
        $xml = preg_replace_callback('~<c\b([^>]*?)\bt="s"([^>]*)>\s*<v>(\d+)</v>\s*</c>~', function ($m) use ($shared, $row, $x) {
            $s = $shared[(int) $m[3]] ?? null;
            if ($s === null || strpos($s, '{') === false) {
                return $m[0];
            }
            return '<c' . $m[1] . 't="inlineStr"' . $m[2] . '><is><t xml:space="preserve">' . $x(diploma_fill($s, $row)) . '</t></is></c>';
        }, $xml) ?? $xml;
        return preg_replace_callback('~(<t\b[^>]*>)([^<]*\{[^<]*)(</t>)~', function ($m) use ($row, $x) {
            return $m[1] . $x(diploma_fill(html_entity_decode($m[2], ENT_QUOTES | ENT_XML1, 'UTF-8'), $row)) . $m[3];
        }, $xml) ?? $xml;
    };
    $fillText = function (string $xml, array $row) use ($x): string {   // paveikslėlių teksto laukai (<a:t>)
        return preg_replace_callback('~(<a:t>)([^<]*\{[^<]*)(</a:t>)~', function ($m) use ($row, $x) {
            return $m[1] . $x(diploma_fill(html_entity_decode($m[2], ENT_QUOTES | ENT_XML1, 'UTF-8'), $row)) . $m[3];
        }, $xml) ?? $xml;
    };
    $relsPath = function (string $part): string { return dirname($part) . '/_rels/' . basename($part) . '.rels'; };
    $sheetRels = $f[$relsPath($sheetPath)] ?? '';
    $drawingPath = null;
    if (preg_match('~<Relationship\b[^>]*Type="[^"]*/drawing"[^>]*/>~', $sheetRels, $dr) && preg_match('~Target="([^"]+)"~', $dr[0], $dt)) {
        $drawingPath = 'xl/' . preg_replace('~^(\.\./|/xl/)~', '', $dt[1]);
        $drawingPath = isset($f[$drawingPath]) ? $drawingPath : null;
    }
    $sheetName = function (array $row, int $i, array &$used): string {
        $n = trim(preg_replace('~[\[\]:*?/\\\\\']+~', ' ', $row['roman'] . ' ' . $row['name']) ?? '');
        $n = mb_substr($n !== '' ? $n : 'Diplomas ' . $i, 0, 28);
        $base = $n;
        for ($k = 2; isset($used[mb_strtolower($n)]); $k++) {
            $n = mb_substr($base, 0, 25) . ' ' . $k;
        }
        $used[mb_strtolower($n)] = true;
        return $n;
    };
    preg_match_all('~sheetId="(\d+)"~', $wb, $ids);
    $nextId = max(array_map('intval', $ids[1] ?: [1])) + 1;
    $used = [];
    $newSheets = '';
    $newRels = '';
    $types = '';
    $origName = preg_match('~name="([^"]*)"~', $first[0], $on) ? html_entity_decode($on[1], ENT_QUOTES | ENT_XML1, 'UTF-8') : '';
    $names = [];
    $tplSheet = $f[$sheetPath];   // tuščias šablonas - kopijos daromos iš jo, ne iš jau užpildyto pirmo lapo
    $tplDrawing = $drawingPath ? $f[$drawingPath] : '';
    foreach ($rows as $i => $row) {
        $name = $sheetName($row, $i + 1, $used);
        $names[] = $name;
        if ($i === 0) {
            $f[$sheetPath] = $fillSheet($tplSheet, $row);
            if ($drawingPath) {
                $f[$drawingPath] = $fillText($tplDrawing, $row);
            }
            continue;
        }
        $n = $i + 1;
        $part = 'xl/worksheets/diplomas' . $n . '.xml';
        // Kopija: be pažymėjimo, be lentelių / komentarų / spausdintuvo nustatymų failo (jie turi būti unikalūs)
        $sx = $fillSheet($tplSheet, $row);
        $sx = preg_replace(['~\stabSelected="1"~', '~<tableParts\b.*?(</tableParts>|/>)~s', '~<legacyDrawing\b[^>]*/>~', '~(<pageSetup\b[^>]*?)\sr:id="[^"]*"~'], ['', '', '', '$1'], $sx) ?? $sx;
        $f[$part] = $sx;
        $types .= '<Override PartName="/' . $part . '" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        if ($sheetRels !== '') {
            $rels = preg_replace('~<Relationship\b[^>]*Type="[^"]*/(table|comments|vmlDrawing|printerSettings)"[^>]*/>~', '', $sheetRels) ?? $sheetRels;
            if ($drawingPath) {
                $dPart = 'xl/drawings/diplomas' . $n . '.xml';
                $f[$dPart] = $fillText($tplDrawing, $row);
                if (isset($f[$relsPath($drawingPath)])) {
                    $f[$relsPath($dPart)] = $f[$relsPath($drawingPath)];
                }
                $rels = str_replace('Target="' . $dt[1] . '"', 'Target="../drawings/diplomas' . $n . '.xml"', $rels);
                $types .= '<Override PartName="/' . $dPart . '" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>';
            }
            $f[$relsPath($part)] = $rels;
        }
        $newRels .= '<Relationship Id="rIdDipl' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/diplomas' . $n . '.xml"/>';
        $newSheets .= '<sheet xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" name="' . $x($name) . '" sheetId="' . ($nextId++) . '" r:id="rIdDipl' . $n . '"/>';
    }
    // Pirmas lapas pervadinamas, kopijos - iškart po juo; spausdinimo sritis - kiekvienam lapui
    $firstNew = preg_replace('~name="[^"]*"~', 'name="' . $x($names[0]) . '"', $first[0]);
    $wb = str_replace($first[0], $firstNew . $newSheets, $wb);
    $count = count($rows);
    if (preg_match('~<definedNames>(.*?)</definedNames>~s', $wb, $dn)) {
        $defs = preg_replace_callback('~<definedName\b([^>]*)>(.*?)</definedName>~s', function ($m) use ($count, $names, $origName, $x) {
            if (!preg_match('~localSheetId="(\d+)"~', $m[1], $ls)) {
                return $m[0];
            }
            $id = (int) $ls[1];
            if ($id > 0) {
                return '<definedName' . str_replace('localSheetId="' . $id . '"', 'localSheetId="' . ($id + $count - 1) . '"', $m[1]) . '>' . $m[2] . '</definedName>';
            }
            $out = '';
            $q = function (string $n): string { return "'" . str_replace("'", "''", $n) . "'"; };
            foreach ($names as $k => $n) {
                $ref = str_replace([$q($origName) . '!', $origName . '!'], $q($n) . '!', html_entity_decode($m[2], ENT_QUOTES | ENT_XML1, 'UTF-8'));
                $out .= '<definedName' . str_replace('localSheetId="0"', 'localSheetId="' . $k . '"', $m[1]) . '>' . $x($ref) . '</definedName>';
            }
            return $out;
        }, $dn[1]) ?? $dn[1];
        $wb = str_replace($dn[1], $defs, $wb);
    }
    $f['xl/workbook.xml'] = $wb;
    $f['xl/_rels/workbook.xml.rels'] = str_replace('</Relationships>', $newRels . '</Relationships>', $wbRels);
    $f['[Content_Types].xml'] = str_replace('</Types>', $types . '</Types>', $f['[Content_Types].xml'] ?? '');
    // Skaičiavimų grandinė ir lapų sąrašas dokumento savybėse - Excel atkuria pats
    unset($f['xl/calcChain.xml']);
    $f['xl/_rels/workbook.xml.rels'] = preg_replace('~<Relationship\b[^>]*Target="calcChain\.xml"[^>]*/>~', '', $f['xl/_rels/workbook.xml.rels']) ?? $f['xl/_rels/workbook.xml.rels'];
    $f['[Content_Types].xml'] = preg_replace('~<Override PartName="/xl/calcChain\.xml"[^>]*/>~', '', $f['[Content_Types].xml']) ?? $f['[Content_Types].xml'];
    if (isset($f['docProps/app.xml'])) {
        $f['docProps/app.xml'] = preg_replace('~<(HeadingPairs|TitlesOfParts)>.*?</\1>~s', '', $f['docProps/app.xml']) ?? $f['docProps/app.xml'];
    }
    $tmp = tempnam(sys_get_temp_dir(), 'dipl');
    $out = new ZipArchive();
    if ($out->open($tmp, ZipArchive::OVERWRITE) !== true) {
        return null;
    }
    $out->addFromString('[Content_Types].xml', $f['[Content_Types].xml']);   // pirmas - kaip Excel
    foreach ($f as $name => $data) {
        if ($name !== '[Content_Types].xml') {
            $out->addFromString($name, $data);
        }
    }
    $out->close();
    $bin = (string) file_get_contents($tmp);
    @unlink($tmp);
    return $bin;
}

/** Paprasti spausdinami diplomai (kai šablono nėra): A4 gulsčias, vienas diplomas - vienas lapas */
function diplomas_html(array $t, array $rows): void
{
    varz_html_head(t('Diplomai') . ' · ' . $t['title'], 'dipl-print');
    echo '<style>
      @page { size: A4 landscape; margin: 0; }
      body.dipl-print { background: #e9e6df !important; margin: 0; }
      .dipl-bar { position: sticky; top: 0; z-index: 2; display: flex; gap: 10px; align-items: center; padding: 10px 16px; background: #17140F; color: #fff; font: 14px Inter, system-ui, sans-serif; }
      .dipl-bar button { font: inherit; font-weight: 700; padding: 8px 16px; border-radius: 999px; border: 0; background: #F2B705; color: #2B2100; cursor: pointer; }
      .dipl { width: 297mm; height: 210mm; box-sizing: border-box; margin: 12px auto; background: #FFFDF8; position: relative; page-break-after: always; break-after: page;
              display: grid; align-content: center; justify-items: center; text-align: center; font-family: Georgia, "Times New Roman", serif; color: #1D1A14; padding: 22mm 26mm; }
      .dipl::before { content: ""; position: absolute; inset: 9mm; border: 2.4mm double #B08A2E; border-radius: 3mm; }
      .dipl::after { content: ""; position: absolute; inset: 13mm; border: .4mm solid #D9C38A; border-radius: 2mm; }
      .dipl > * { position: relative; z-index: 1; margin: 0; }
      .d-org { font: 600 13pt Inter, system-ui, sans-serif; letter-spacing: .28em; text-transform: uppercase; color: #8A6A1F; }
      .d-title { font-size: 54pt; letter-spacing: .12em; margin: 6mm 0 2mm; color: #2B2418; }
      .d-place { font-size: 30pt; font-weight: 700; color: #B08A2E; margin-bottom: 8mm; }
      .d-place.p1 { color: #B8860B; } .d-place.p2 { color: #7E8790; } .d-place.p3 { color: #A0612E; }
      .d-name { font-size: 40pt; font-weight: 700; border-bottom: .5mm solid #D9C38A; padding: 0 12mm 2mm; margin-bottom: 3mm; }
      .d-club { font: 500 15pt Inter, system-ui, sans-serif; color: #5A5448; margin-bottom: 8mm; }
      .d-cat { font-size: 18pt; }
      .d-ev { font: 500 13pt Inter, system-ui, sans-serif; color: #5A5448; margin-top: 10mm; }
      .d-sign { display: flex; gap: 40mm; margin-top: 16mm; font: 11pt Inter, system-ui, sans-serif; color: #5A5448; }
      .d-sign span { border-top: .3mm solid #8A8174; padding-top: 2mm; min-width: 60mm; }
      @media print { body.dipl-print { background: #fff; } .dipl-bar { display: none; } .dipl { margin: 0; } }
    </style>';
    echo '<div class="dipl-bar"><b>' . e(t('Diplomai')) . ': ' . count($rows) . '</b><span>' . e(t('Spausdinkite gulsčiai (A4), be paraščių.')) . '</span><span style="flex:1"></span>'
        . '<button type="button" onclick="window.print()">🖨 ' . e(t('Spausdinti')) . '</button></div>';
    if (!$rows) {
        echo '<p style="padding:40px; font:16px Inter, sans-serif;">' . e(t('Prizininkų dar nėra - diplomai atsiras, kai kategorija bus baigta.')) . '</p>';
    }
    foreach ($rows as $r) {
        echo '<section class="dipl" data-no-tl><p class="d-org">' . e($r['title']) . '</p><h1 class="d-title">' . e(t('DIPLOMAS')) . '</h1>'
            . '<p class="d-place p' . e($r['place']) . '">' . e($r['roman']) . ' ' . e(t('vieta')) . '</p>'
            . '<p class="d-name">' . e($r['name']) . '</p><p class="d-club">' . e($r['club']) . '</p>'
            . '<p class="d-cat">' . e($r['category']) . '</p>'
            . '<p class="d-ev">' . e($r['date']) . ($r['location'] ? ' · ' . e($r['location']) : '') . '</p>'
            . '<div class="d-sign"><span>' . e(t('Teisėjų kolegijos pirmininkas')) . '</span><span>' . e(t('Organizatorius')) . '</span></div></section>';
    }
    echo '</body></html>';
}
