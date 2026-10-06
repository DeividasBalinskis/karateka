# Įkėlimas į test.karateka.lt

Reikia: PHP 8.0+ su `pdo_mysql` ir `gd` (DirectAdmin → PHP nustatymai), MySQL / MariaDB.

## 1. Duomenų bazė (vieną kartą)
1. DirectAdmin → MySQL Management → sukurkite test DB ir vartotoją.
2. phpMyAdmin → pasirinkite tą DB → Import → `db/001_schema.sql`, tada `db/002_groups.sql`.

## 2. Failai
1. Įkelkite **viską iš `public_html/`** į serverio `public_html/test/`, **išskyrus** `config.php` ir `uploads/news/`.
2. Serveryje sukurkite `public_html/test/config.php` pagal `config.example.php`:
   - `env` → `test`, `site_url` → `https://test.karateka.lt`, `force_https` → `true`
   - `db` → test DB duomenys iš 1 žingsnio
   - `mail` → info@karateka.lt SMTP (host, port 465, `secure` = `ssl`, slaptažodis)
   - `setup_key` → ilgas atsitiktinis tekstas
3. Patikrinkite, kad `uploads/` aplankas turi rašymo teises (755).

## 3. Pirmas administratorius
Atsidarykite `https://test.karateka.lt/admin/pirmas-adminas.php?key=<setup_key>` ir susikurkite paskyrą.
Puslapis veikia tik kol nėra administratoriaus. Po to pakeiskite `setup_key` į kitą reikšmę.

Kitus trenerius: jie užsiregistruoja kaip įprasta, jūs patvirtinate, tada **Treneriams → Paskyros** pakeičiate rolę į „Treneris“.

## Atnaujinant
Įkelkite pakeistus failus. `config.php` ir `uploads/` niekada neperrašykite.
Jei pasikeitė `db/` schema - bus nurodyta, kokią SQL komandą paleisti.
