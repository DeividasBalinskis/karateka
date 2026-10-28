# Atsarginės kopijos

**Kas daroma automatiškai:** visos duomenų bazės kopija kartą per dieną (po pirmo tos dienos apsilankymo svetainėje).
Saugomos paskutinės 14, senesnės ištrinamos. Kompiuteryje automatinės kopijos išjungtos.

**Kur:** `public_html/uploads/kopijos/` - iš interneto neprieinamas (`.htaccess`), failų pavadinimai atsitiktiniai.
Jei hostingas `.htaccess` nepaisytų, puslapis „Atsarginės kopijos ir sistema“ parodys įspėjimą.

**Ką daryti adminui:** Treneriams → Nustatymai → „Atsarginės kopijos ir sistema“.
- „Sukurti kopiją dabar“ - prieš didesnį atnaujinimą.
- „Atsisiųsti“ - kartą per mėnesį vieną kopiją į savo kompiuterį (ne į bendrą aplanką: joje visų narių duomenys).
- Jei kopija nepavyko ar nedaryta 2 dienas - pranešimas „Šiandien“ skiltyje „Reikia padaryti“.

**Nuotraukos** (`uploads/news`, `uploads/site`, ...) į šią kopiją neįeina - jas saugo hostingas.
Pasitikrinkite hostingo valdyme, ar įjungtos jo atsarginės kopijos.

## Atkūrimas

1. Atsisiųskite reikiamą kopiją (`karateka-...sql.gz`).
2. Hostingo valdyme atidarykite **phpMyAdmin** → kairėje pasirinkite svetainės duomenų bazę.
3. **Import** → pasirinkite failą → **Import**. Visos lentelės pakeičiamos kopijos duomenimis.
4. Jei kopija senesnė nei dabartinė svetainės versija - importuokite ir naujesnius `db/0xx` failus (žr. `docs/pakeitimai.md`).

Kompiuteryje: phpMyAdmin http://localhost:8081 → `karateka` → Import.

## Nustatymai (`config.php`)

```php
'backup' => [
    'auto' => true,                      // numatyta: test ir oficialioje - taip, kompiuteryje - ne
    'keep' => 14,                        // kiek kopijų saugoti
    'dir'  => '/home/vartotojas/kopijos', // jei norite laikyti už svetainės aplanko ribų
],
```
