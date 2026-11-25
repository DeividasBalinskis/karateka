// C4/C5: vaizdinės apsaugos puslapiai - bendras sąrašas nuotraukoms (visual.spec.js) ir stilių palyginimui (styles.spec.js).
// [pavadinimas, adresas, kas prisijungęs (null - svečias), ar Valdymas / Budo (papildomi pločiai)]
const PAGES = [
  ['pagrindinis', 'index.php', null],
  ['tvarkarastis', 'tvarkarastis.php', null],
  ['kontaktai', 'kontaktai.php', null],
  ['prisijungti', 'prisijungti.php', null],
  ['naujienos', 'naujienos.php', null],
  ['privatumas', 'privatumas.php', null],
  ['paskyra-tevai', 'paskyra.php', 'tevai@karateka.test'],
  ['paskyra-narys', 'paskyra.php', 'narys@karateka.test'],
  ['valdymas', 'admin/', 'info@karateka.lt', true],
  ['valdymas-nariai', 'admin/nariai.php', 'info@karateka.lt', true],
  ['valdymas-nustatymai', 'admin/nustatymai.php', 'info@karateka.lt', true],
  ['egzaminai', 'admin/egzaminai.php', 'info@karateka.lt', true],
  ['budo', 'admin/varzybos.php', 'info@karateka.lt', true],
  ['ltkf', 'ltkf/index.php', null],
  ['ltkf-klubai', 'ltkf/klubai.php', null],
];
const WIDTHS = [375, 768, 1440];
const WIDE = [1280, 2560];

module.exports = { PAGES, WIDTHS, WIDE };
