SQL failai šiame aplanke (`001_*.sql`, `002_*.sql`, ...) automatiškai paleidžiami, kai lokali DB sukuriama pirmą kartą.
Čia laikoma tik schema, jokių narių duomenų.

Naujas failas: numeris didesnis už paskutinį, tik prideda (`ADD COLUMN IF NOT EXISTS`, `CREATE TABLE IF NOT EXISTS`, `INSERT IGNORE`),
kad būtų galima importuoti ir antrą kartą. Pabaigoje - `INSERT IGNORE INTO db_versija (failas) VALUES ('0xx_pavadinimas.sql');`
(lentelė `db_versija` - nuo 022; pagal ją `tools\lokali-atnaujinti.ps1` žino, ką dar reikia importuoti).
