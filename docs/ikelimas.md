# Įkėlimas į test.karateka.lt

Viskas, ko reikia, yra aplanke **`ikelimui/`** (jį sukuria `tools/paruosti-ikelimui.ps1`):

| Failas | Kam |
|---|---|
| `svetaine.zip` | Visi svetainės failai → išskleisti į serverio `public_html` |
| `config.php` | Serverio nustatymai (DB, paštas) → į serverio `public_html` |
| `duomenu-baze.sql` | Visa duomenų bazė → tik **naujai, tuščiai** DB (phpMyAdmin → Import) |
| `demo/` | Nebūtina: demo duomenys testavimui (žr. apačioje) |

Serveryje: DirectAdmin → File Manager → `domains/test.karateka.lt/public_html`.

## Atnaujinant svetainę
1. Serveryje ištrinkite **viską, išskyrus `config.php` ir aplanką `uploads`**.
2. Įkelkite `svetaine.zip` → dešiniu pelės klavišu **Išskleisti** (į tą patį aplanką) → ZIP ištrinkite.
3. Jei pasikeitė duomenų bazė - phpMyAdmin → `aus15792_test` → Import → tik **naują** `db/0xx_...sql` failą.

Kodėl 1 žingsnis: išskleidžiant ZIP ant senų failų, failų tvarkyklė gali sukurti kopijas.

## Duomenų bazės pakeitimai (db/)
Numeruoti failai. Nauja DB → `ikelimui/duomenu-baze.sql` (visi kartu). Esama DB → importuokite tik tuos, kurių dar nebuvo.
`aus15792_test` jau turi: 001-007. Trūksta: **008** ir **009** (lankomumas).

## Demo duomenys (nebūtina)
1. `demo/demo-nuotraukos.zip` → išskleisti serverio `public_html` (naujienų nuotraukos).
2. phpMyAdmin → Import → `demo/demo-duomenys.sql`. (Pirma turi būti sukurtas administratorius.)
3. Demo paskyros: `vardas.pavarde@demo.karateka.lt`, slaptažodis - `demo-duomenys.sql` pirmoje eilutėje.
4. Ištrinti viską demo: Import → `demo/demo-istrinti.sql` (jūsų pačių sukurti duomenys lieka).

## Pirmas administratorius (tik naujame serveryje)
`https://test.karateka.lt/admin/pirmas-adminas.php?key=<setup_key iš config.php>` - veikia, kol nėra nė vieno administratoriaus.
Kitus trenerius: užsiregistruoja kaip įprasta → patvirtinate → **Treneriams → Paskyros** → rolė „Treneris“.
