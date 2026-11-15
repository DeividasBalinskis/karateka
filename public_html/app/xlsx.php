<?php
// Paprastas Excel (.xlsx) failas be papildomų bibliotekų: vienas lapas, pirmoji eilutė paryškinta, stulpelių plotis pagal turinį.
// Jei serveryje nėra ZipArchive - atsisiunčiamas CSV (Excel jį irgi atidaro).

/** .xlsx turinys arba null (nėra ZipArchive) */
function xlsx_build(array $rows, string $sheet = 'Lapas1', int $boldRows = 1): ?string
{
    if (!class_exists('ZipArchive')) {
        return null;
    }
    $x = function (string $s): string {
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s) ?? '';
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    };
    $col = function (int $i): string {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26) . $s;
        }
        return $s;
    };
    $widths = [];
    $data = '';
    foreach (array_values($rows) as $r => $row) {
        $data .= '<row r="' . ($r + 1) . '">';
        foreach (array_values((array) $row) as $c => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $ref = $col($c) . ($r + 1);
            $style = $r < $boldRows ? ' s="1"' : '';
            if ((is_int($v) || is_float($v)) && !is_nan((float) $v)) {
                $data .= '<c r="' . $ref . '"' . $style . '><v>' . $v . '</v></c>';
            } else {
                $data .= '<c r="' . $ref . '"' . $style . ' t="inlineStr"><is><t xml:space="preserve">' . $x((string) $v) . '</t></is></c>';
            }
            $widths[$c] = max($widths[$c] ?? 8, min(60, mb_strlen((string) $v) + 2));
        }
        $data .= '</row>';
    }
    $cols = '';
    foreach ($widths as $c => $w) {
        $cols .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . $w . '" customWidth="1"/>';
    }
    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . ($boldRows ? '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $boldRows . '" topLeftCell="A' . ($boldRows + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' : '')
        . ($cols ? '<cols>' . $cols . '</cols>' : '') . '<sheetData>' . $data . '</sheetData></worksheet>';
    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $x(mb_substr(preg_replace('~[\[\]:*?/\\\\]~', ' ', $sheet), 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            . '</styleSheet>',
        'xl/worksheets/sheet1.xml' => $sheetXml,
    ];
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    if ($tmp === false || $zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        return null;
    }
    foreach ($files as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();
    $bin = (string) file_get_contents($tmp);
    @unlink($tmp);
    return $bin;
}

/** Atsisiųsti kaip Excel (.xlsx); be ZipArchive - CSV (UTF-8, kabliataškiai) */
function xlsx_download(string $filename, array $rows, string $sheet = 'Lapas1', int $boldRows = 1): void
{
    $base = preg_replace('/[^\w.-]+/u', '-', preg_replace('/\.(xlsx|csv)$/i', '', $filename)) ?: 'failas';
    $bin = xlsx_build($rows, $sheet, $boldRows);
    if ($bin !== null) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $base . '.xlsx"');
        header('Content-Length: ' . strlen($bin));
        exit($bin);
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $base . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    foreach ($rows as $r) {
        fputcsv($out, array_map('strval', (array) $r), ';', '"', '');
    }
    exit;
}
