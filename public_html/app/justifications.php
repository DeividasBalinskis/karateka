<?php
// Pateisinimų sistema (Deividas, v1.93): žmogus pagal mūsų atsiųstą nuorodą (karateka.lt/p/?kodas) užpildo prašymą
// pateisinti praleistas pamokas (mokinys, mokykla, direktorius, klasė, laikotarpis, el. paštas). Administratoriai gauna laišką,
// duomenys įrašomi į pateisinimo šabloną (įkeltas Word .docx su {vardas}, {mokykla}, {direktorius}, {klase}, {laikotarpis}, {data}
// arba tekstas Valdyme). Adminas peržiūri ir patvirtina - pateisinimas (.docx) išsiunčiamas nurodytu el. paštu. Lentelė - db/056.
// v1.94: prašymą galima pataisyti (duomenys ir tekstas) arba įkelti galutinį failą (Word arba PDF) - jis siunčiamas vietoj sugeneruoto (db/057);
// apie naujus prašymus pranešama tik pasirinktiems administratoriams (nustatymas just_notify; numatyta - Denis).

const JUST_DIR = PUBLIC_DIR . '/uploads/pateisinimai';
const JUST_TEMPLATE = JUST_DIR . '/sablonas.docx';
/** Šablono žymės → kas įrašoma (raidžių dydis nesvarbu) */
const JUST_KEYS = ['vardas' => 'student', 'mokinys' => 'student', 'mokykla' => 'school', 'direktorius' => 'director', 'klase' => 'class', 'klasė' => 'class',
                   'laikotarpis' => 'period', 'nuo' => 'from', 'iki' => 'to', 'data' => 'today', 'el_pastas' => 'email', 'elpastas' => 'email'];

function just_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT 1 FROM justifications LIMIT 1');
            $ok = true;
        } catch (PDOException $ex) {
            $ok = false;   // dar neimportuotas db/056
        }
    }
    return $ok;
}

/** v1.94 stulpeliai (db/057): pataisytas tekstas ir įkeltas failas */
function just_v94(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT custom_text FROM justifications LIMIT 1');
            $ok = true;
        } catch (PDOException $ex) {
            $ok = false;
        }
    }
    return $ok;
}

/** Kam pranešti apie naujus prašymus: pasirinkti administratoriai (numatyta - Denis; jei jo nėra - visi) */
function just_recipients(): array
{
    $admins = active_admins();
    $ids = json_decode((string) site_setting('just_notify', ''), true);
    if (is_array($ids)) {
        $ids = array_map('intval', $ids);
        return array_values(array_filter($admins, function ($a) use ($ids) { return in_array((int) $a['id'], $ids, true); }));
    }
    $denis = array_values(array_filter($admins, function ($a) { return mb_strtolower((string) $a['first_name']) === 'denis'; }));
    return $denis ?: $admins;
}

function just_is_recipient(?array $acc): bool
{
    if (!$acc) {
        return false;
    }
    foreach (just_recipients() as $a) {
        if ((int) $a['id'] === (int) $acc['id']) {
            return true;
        }
    }
    return false;
}

/** Nuorodos kodas (trumpas, kaip egzaminų; '' - nėra); $new - naujas (senas nebeveiks). C3: app/links.php (rūšis 'just'). */
function just_token(bool $new = false): string
{
    if (!links_ready()) {   // db/062 dar neimportuotas - kaip anksčiau
        $t = (string) site_setting('just_token', '');
        if ($new || ($t !== '' && !exam_code_ok($t))) {
            $t = exam_short_code();
            set_site_setting('just_token', $t);
        }
        return $t;
    }
    $l = link_current('just');
    if ($new) {
        $l = $l ? link_renew((int) $l['id']) : link_create('just');
    }
    return (string) link_token($l);
}

function just_url(): string
{
    $t = just_token();
    return $t !== '' ? abs_url('p/?' . $t) : '';
}

function just_is_open(): bool
{
    return site_setting('just_open', '1') !== '0';
}

function just_new_count(): int
{
    return just_ready() ? (int) q_value('SELECT COUNT(*) FROM justifications WHERE status = "new"') : 0;
}

/** Data dokumentui - visada lietuviškai (mokyklai), nepriklausomai nuo administratoriaus kalbos: „2026 m. lapkričio 25 d.“ */
function just_date(string $date, bool $year = true): string
{
    $m = ['', 'sausio', 'vasario', 'kovo', 'balandžio', 'gegužės', 'birželio', 'liepos', 'rugpjūčio', 'rugsėjo', 'spalio', 'lapkričio', 'gruodžio'];
    $ts = strtotime($date);
    return ($year ? date('Y', $ts) . ' m. ' : '') . $m[(int) date('n', $ts)] . ' ' . date('j', $ts) . ' d.';
}

/** Laikotarpis tekstu: „2026 m. lapkričio 25 d. – lapkričio 27 d.“ (viena diena - viena data) */
function just_period(string $from, string $to): string
{
    if ($to === '' || $to === $from) {
        return just_date($from);
    }
    return just_date($from) . ' – ' . just_date($to, substr($from, 0, 4) !== substr($to, 0, 4));
}

/** Reikšmės šablono žymėms */
function just_values(array $r): array
{
    return ['student' => (string) $r['student'], 'school' => (string) $r['school'], 'director' => (string) $r['director'], 'class' => (string) $r['class_name'],
            'period' => just_period((string) $r['date_from'], (string) $r['date_to']), 'from' => just_date((string) $r['date_from']),
            'to' => just_date((string) $r['date_to']), 'today' => just_date(date('Y-m-d')), 'email' => (string) $r['email']];
}

/** Numatytas tekstas (kol neįkeltas Word šablonas) - Valdyme keičiamas */
function just_default_text(): string
{
    return "{mokykla}\nDirektoriui (-ei) {direktorius}\n\nPRAŠYMAS PATEISINTI PRALEISTAS PAMOKAS\n{data}\n\n"
        . "Prašome pateisinti {klase} klasės mokinio (-ės) {vardas} praleistas pamokas {laikotarpis}, "
        . "nes mokinys (-ė) dalyvavo VšĮ Karate Ateitis karate klubo veikloje.\n\nPagarbiai\nVšĮ Karate Ateitis";
}

function just_text(): string
{
    $t = trim((string) site_setting('just_text', ''));
    return $t !== '' ? $t : just_default_text();
}

/** {žymė} → reikšmė tekste ($esc - XML/HTML saugiai) */
function just_fill(string $tpl, array $v, bool $esc = false): string
{
    return (string) preg_replace_callback('/\{\s*([\p{L}_]+)\s*\}(\.?)/u', function ($m) use ($v, $esc) {
        $k = JUST_KEYS[mb_strtolower($m[1])] ?? null;
        if ($k === null) {
            return $m[0];
        }
        // data baigiasi „d.“ - jei šablone po žymės taškas, antro nededame („d..“)
        $val = $v[$k] . ($m[2] !== '' && substr($v[$k], -1) !== '.' ? '.' : '');
        return $esc ? htmlspecialchars($val, ENT_XML1 | ENT_QUOTES, 'UTF-8') : $val;
    }, $tpl);
}

/** Įkeltas Word šablonas: ['name' => ..., 'at' => ...] arba null */
function just_template(): ?array
{
    if (!is_file(JUST_TEMPLATE)) {
        return null;
    }
    $meta = json_decode((string) site_setting('just_template', '{}'), true) ?: [];
    return ['name' => (string) ($meta['name'] ?? 'sablonas.docx'), 'at' => (string) ($meta['at'] ?? date('Y-m-d', (int) filemtime(JUST_TEMPLATE)))];
}

/** Įkelti Word šabloną; grąžina klaidos tekstą arba '' */
function just_template_save(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return t('Failas neįkeltas.');
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        return t('Failas per didelis (daugiausia 10 MB).');
    }
    if (!preg_match('/\.docx$/i', (string) $file['name'])) {
        return t('Reikia Word failo .docx (Word: Failas → Įrašyti kaip → Word dokumentas).');
    }
    if (!class_exists('ZipArchive')) {
        return t('Serveryje nėra ZipArchive - Word šablono naudoti negalima.');
    }
    $z = new ZipArchive();
    if ($z->open($file['tmp_name']) !== true || $z->locateName('word/document.xml') === false) {
        return t('Tai ne Word dokumentas (.docx).');
    }
    $z->close();
    if (!is_dir(JUST_DIR)) {
        @mkdir(JUST_DIR, 0755, true);
        @file_put_contents(JUST_DIR . '/.htaccess', "Require all denied\n");
    }
    if (!move_uploaded_file($file['tmp_name'], JUST_TEMPLATE)) {
        return t('Nepavyko išsaugoti failo.');
    }
    set_site_setting('just_template', json_encode(['name' => mb_substr((string) $file['name'], 0, 120), 'at' => date('Y-m-d')], JSON_UNESCAPED_UNICODE));
    return '';
}

function just_template_delete(): void
{
    @unlink(JUST_TEMPLATE);
    set_site_setting('just_template', '');
}

/** Word XML: žymė gali būti suskaidyta į kelias dalis ({vard</w:t>...<w:t>as}) - sujungiama, tada įrašoma reikšmė */
function just_fill_xml(string $xml, array $v): string
{
    $xml = (string) preg_replace_callback('/\{(?:[^{}<]|<[^>]*>)*\}/u', function ($m) {
        return strpos($m[0], '<') === false ? $m[0] : strip_tags($m[0]);
    }, $xml);
    return just_fill($xml, $v, true);
}

/** Paprastas Word dokumentas iš teksto (kai šablonas neįkeltas; ir pavyzdinis šablonas atsisiųsti) */
function just_simple_docx(string $text): ?string
{
    if (!class_exists('ZipArchive')) {
        return null;
    }
    $paras = '';
    foreach (preg_split('/\R/u', $text) as $line) {
        $paras .= '<w:p><w:r><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman"/><w:sz w:val="24"/></w:rPr><w:t xml:space="preserve">'
            . htmlspecialchars($line, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</w:t></w:r></w:p>';
    }
    $doc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
        . $paras . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="567" w:bottom="1134" w:left="1701" w:header="567" w:footer="567" w:gutter="0"/></w:sectPr></w:body></w:document>';
    $tmp = tempnam(sys_get_temp_dir(), 'pat');
    $z = new ZipArchive();
    if ($tmp === false || $z->open($tmp, ZipArchive::OVERWRITE) !== true) {
        return null;
    }
    $z->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
    $z->addFromString('word/document.xml', $doc);
    $z->close();
    $data = (string) file_get_contents($tmp);
    @unlink($tmp);
    return $data;
}

/** Pataisytas šio prašymo tekstas (jei adminas jį keitė) arba null */
function just_custom(array $r): ?string
{
    $t = trim((string) ($r['custom_text'] ?? ''));
    return $t !== '' ? $t : null;
}

/** Užpildytas tekstas pagal bendrą tekstą (be Word šablono) */
function just_auto_text(array $r): string
{
    return just_fill(just_text(), just_values($r));
}

/** Užpildytas pateisinimas (.docx turinys): pataisytas tekstas, įkeltas Word šablonas arba bendras tekstas */
function just_docx(array $r): ?string
{
    $v = just_values($r);
    if (($c = just_custom($r)) !== null) {
        return just_simple_docx($c);
    }
    if (!just_template() || !class_exists('ZipArchive')) {
        return just_simple_docx(just_fill(just_text(), $v));
    }
    $tmp = tempnam(sys_get_temp_dir(), 'pat');
    if ($tmp === false || !copy(JUST_TEMPLATE, $tmp)) {
        return null;
    }
    $z = new ZipArchive();
    if ($z->open($tmp) !== true) {
        @unlink($tmp);
        return null;
    }
    for ($i = 0; $i < $z->numFiles; $i++) {
        $name = (string) $z->getNameIndex($i);
        if (preg_match('~^word/(document|header\d*|footer\d*)\.xml$~', $name)) {
            $z->addFromString($name, just_fill_xml((string) $z->getFromName($name), $v));
        }
    }
    $z->close();
    $data = (string) file_get_contents($tmp);
    @unlink($tmp);
    return $data;
}

/** Peržiūrai: užpildyto pateisinimo tekstas (pastraipomis) */
function just_preview(array $r): string
{
    $v = just_values($r);
    if (($c = just_custom($r)) !== null) {
        return $c;
    }
    if (!just_template() || !class_exists('ZipArchive')) {
        return just_fill(just_text(), $v);
    }
    $z = new ZipArchive();
    if ($z->open(JUST_TEMPLATE) !== true) {
        return just_fill(just_text(), $v);
    }
    $xml = just_fill_xml((string) $z->getFromName('word/document.xml'), $v);
    $z->close();
    $xml = (string) preg_replace(['~</w:p>~', '~<w:tab/>~', '~<w:br[^>]*/>~'], ["\n", "\t", "\n"], $xml);
    return trim(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
}

/** Failo pavadinimas: pateisinimas-vardas-pavarde.docx */
function just_filename(array $r): string
{
    $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', strtr((string) $r['student'],
        ['ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i', 'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z', 'Ą' => 'A', 'Č' => 'C', 'Ę' => 'E', 'Ė' => 'E', 'Į' => 'I', 'Š' => 'S', 'Ų' => 'U', 'Ū' => 'U', 'Ž' => 'Z'])), '-'));
    return 'pateisinimas-' . ($slug !== '' ? $slug : (int) $r['id']) . '.docx';
}

/** Įkeltas galutinis failas (Word arba PDF): kelias diske arba null */
function just_file_path(array $r): ?string
{
    $name = (string) ($r['file_name'] ?? '');
    if ($name === '' || !preg_match('/\.(docx|pdf)$/i', $name, $m)) {
        return null;
    }
    $p = JUST_DIR . '/p' . (int) $r['id'] . '.' . strtolower($m[1]);
    return is_file($p) ? $p : null;
}

function just_file_type(string $path): string
{
    return preg_match('/\.pdf$/i', $path) ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
}

/** Įkelti galutinį failą (Word .docx arba PDF), kuris bus išsiųstas vietoj sugeneruoto; grąžina klaidos tekstą arba '' */
function just_file_save(array $r, array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return t('Failas neįkeltas.');
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        return t('Failas per didelis (daugiausia 10 MB).');
    }
    if (!preg_match('/\.(docx|pdf)$/i', (string) $file['name'], $m)) {
        return t('Reikia Word (.docx) arba PDF failo.');
    }
    $ext = strtolower($m[1]);
    $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 5);
    if ($ext === 'pdf' ? strpos($head, '%PDF') !== 0 : strpos($head, 'PK') !== 0) {
        return $ext === 'pdf' ? t('Tai ne PDF failas.') : t('Tai ne Word dokumentas (.docx).');
    }
    if (!is_dir(JUST_DIR)) {
        @mkdir(JUST_DIR, 0755, true);
        @file_put_contents(JUST_DIR . '/.htaccess', "Require all denied\n");
    }
    just_file_delete($r);
    if (!move_uploaded_file($file['tmp_name'], JUST_DIR . '/p' . (int) $r['id'] . '.' . $ext)) {
        return t('Nepavyko išsaugoti failo.');
    }
    q('UPDATE justifications SET file_name = ? WHERE id = ?', [mb_substr((string) $file['name'], 0, 190), (int) $r['id']]);
    return '';
}

function just_file_delete(array $r): void
{
    foreach (['docx', 'pdf'] as $ext) {
        @unlink(JUST_DIR . '/p' . (int) $r['id'] . '.' . $ext);
    }
    if (just_v94()) {
        q('UPDATE justifications SET file_name = NULL WHERE id = ?', [(int) $r['id']]);
    }
}

/** Kas bus išsiųsta: įkeltas failas arba sugeneruotas .docx - ['name', 'type', 'data'] arba null */
function just_attachment(array $r): ?array
{
    if (($p = just_file_path($r)) !== null) {
        $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
        return ['name' => preg_replace('/\.docx$/', '.' . $ext, just_filename($r)), 'type' => just_file_type($p), 'data' => (string) file_get_contents($p)];
    }
    $docx = just_docx($r);
    return $docx === null ? null : ['name' => just_filename($r), 'type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'data' => $docx];
}

/** Pataisyti prašymo duomenis ir tekstą ($text === tekstas pagal šabloną → pataisymas išvalomas) */
function just_update(array $r, array $d, ?string $text): void
{
    q('UPDATE justifications SET student = ?, school = ?, director = ?, class_name = ?, date_from = ?, date_to = ?, email = ? WHERE id = ?',
        [mb_substr($d['student'], 0, 160), mb_substr($d['school'], 0, 190), mb_substr($d['director'], 0, 160), mb_substr($d['class_name'], 0, 30),
         $d['date_from'], $d['date_to'], mb_strtolower(mb_substr($d['email'], 0, 190)), (int) $r['id']]);
    if ($text !== null && just_v94()) {
        $r2 = q_one('SELECT * FROM justifications WHERE id = ?', [(int) $r['id']]);
        $text = trim(str_replace("\r\n", "\n", $text));
        $old = just_custom($r) ?? just_auto_text($r);   // ką adminas matė redaguodamas
        $custom = ($text === '' || $text === trim($old) && just_custom($r) === null) ? null : mb_substr($text, 0, 8000);
        if ($custom !== null && $custom === trim(just_auto_text($r2))) {
            $custom = null;
        }
        q('UPDATE justifications SET custom_text = ? WHERE id = ?', [$custom, (int) $r['id']]);
    }
}

/** Naujas prašymas: įrašomas, pasirinktiems administratoriams - laiškas ir pranešimas. Grąžina id. */
function just_add(array $d): int
{
    q('INSERT INTO justifications (student, school, director, class_name, date_from, date_to, email) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [mb_substr($d['student'], 0, 160), mb_substr($d['school'], 0, 190), mb_substr($d['director'], 0, 160), mb_substr($d['class_name'], 0, 30),
         $d['date_from'], $d['date_to'], mb_strtolower(mb_substr($d['email'], 0, 190))]);
    $id = (int) db()->lastInsertId();
    $r = q_one('SELECT * FROM justifications WHERE id = ?', [$id]);
    $title = t('Naujas pateisinimo prašymas') . ': ' . $r['student'];
    $body = $r['student'] . ', ' . $r['class_name'] . ' ' . t('klasė') . "\n" . $r['school'] . "\n" . just_period($r['date_from'], $r['date_to']) . "\n" . $r['email'];
    foreach (just_recipients() as $adm) {
        notify_account((int) $adm['id'], 'justification', $title, $body, 'admin/pateisinimai.php#p' . $id, null, $id);
        if ($adm['email'] && filter_var($adm['email'], FILTER_VALIDATE_EMAIL)) {
            send_mail($adm['email'], $title, $body . "\n\n" . t('Peržiūrėti ir patvirtinti:') . ' ' . abs_url('admin/pateisinimai.php#p' . $id));
        }
    }
    return $id;
}

/** Patvirtinta - pateisinimas (.docx priede) išsiunčiamas prašyme nurodytu el. paštu */
function just_send(array $r, array $me): bool
{
    $att = just_attachment($r);
    if ($att === null) {
        return false;
    }
    $body = t('Sveiki,') . "\n\n" . t('siunčiame pamokų pateisinimą: {n}, {k} klasė, {p}.', ['n' => $r['student'], 'k' => $r['class_name'], 'p' => just_period($r['date_from'], $r['date_to'])])
        . "\n" . t('Pateisinimas - prisegtame faile.') . "\n\n" . t('Pagarbiai') . "\nVšĮ Karate Ateitis";
    $ok = send_mail((string) $r['email'], t('Pamokų pateisinimas') . ': ' . $r['student'], $body, [$att]);
    if ($ok) {
        q('UPDATE justifications SET status = "sent", sent_at = NOW(), sent_by = ? WHERE id = ?', [(int) $me['id'], (int) $r['id']]);
        q('UPDATE notifications SET done_at = NOW() WHERE type = "justification" AND ref_id = ? AND done_at IS NULL', [(int) $r['id']]);
    }
    return $ok;
}
