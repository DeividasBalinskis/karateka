<?php
// Piktogramos (linijinės SVG, spalva - kaip teksto) ir failo laukas. Naudojimas: icon('download') mygtuke prieš tekstą.
// Vienas rinkinys visoms naujoms sąsajoms vietoj atsitiktinių simbolių (⬇, 🗑, ✕).

const ICONS = [
    'download' => '<path d="M12 4v11"/><path d="m7 10 5 5 5-5"/><path d="M5 20h14"/>',
    'upload'   => '<path d="M12 20V9"/><path d="m7 14 5-5 5 5"/><path d="M5 4h14"/>',
    'trash'    => '<path d="M4 7h16"/><path d="M10 11v6M14 11v6"/><path d="M6 7l1 13h10l1-13"/><path d="M9 7V4h6v3"/>',
    'plus'     => '<path d="M12 5v14M5 12h14"/>',
    'check'    => '<path d="m5 12 5 5 9-10"/>',
    'x'        => '<path d="M6 6l12 12M18 6 6 18"/>',
    'copy'     => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a1 1 0 0 1 1-1h10"/>',
    'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
    'image'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 17-5-5-9 8"/>',
    'comment'  => '<path d="M5 5h14a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1h-8l-5 4v-4H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z"/>',
    'file'     => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>',
    'video'    => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m10 9 5 3-5 3z"/>',
    'tag'      => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.5"/>',
    'bulb'     => '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2.1h5c0-.9.4-1.6 1-2.1A6 6 0 0 0 12 3z"/>',
    'chevron'  => '<path d="m6 9 6 6 6-6"/>',
    'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    'arrow-left'  => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
    'sparkle'  => '<path d="M12 3l1.8 4.7L18.5 9l-4.7 1.8L12 15.5l-1.8-4.7L5.5 9l4.7-1.3z"/><path d="M19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9z"/>',
    'eye'      => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    'edit'     => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13 7 4 4"/>',
];

/** Inline SVG piktograma; $label - tekstas ekrano skaitytuvui, kai mygtukas be teksto */
function icon(string $name, string $label = ''): string
{
    $paths = ICONS[$name] ?? ICONS['file'];
    return '<svg class="ico" viewBox="0 0 24 24" ' . ($label !== '' ? 'role="img" aria-label="' . e($label) . '"' : 'aria-hidden="true"') . ' focusable="false">' . $paths . '</svg>';
}

/** Failo laukas: rėmelis „Pasirinkite arba nutempkite čia“, vietoj naršyklės „Choose File“ (standartai 8.6) */
function file_drop(string $name, string $accept, string $title, string $hint = '', bool $multiple = false): string
{
    return '<label class="file-drop"><input type="file" name="' . e($name) . '" accept="' . e($accept) . '"' . ($multiple ? ' multiple' : '') . '>'
        . '<span class="fd-ico">' . icon('upload') . '</span><span class="fd-text"><b>' . e($title) . '</b>'
        . '<span class="fd-hint">' . e($hint !== '' ? $hint : t('arba nutempkite čia')) . '</span><span class="fd-name" aria-live="polite"></span></span></label>';
}
