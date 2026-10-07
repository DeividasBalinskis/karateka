# Įkėlimas į test.karateka.lt

Reikia: PHP 7.3+ (rekomenduojama 8.2) su `pdo_mysql` ir `gd`, MySQL / MariaDB.

## 1. Duomenų bazė (vieną kartą)
1. DirectAdmin → MySQL Management → sukurkite test DB ir vartotoją.
2. phpMyAdmin → pasirinkite tą DB → Import → `db/001_schema.sql`, tada `db/002_groups.sql`, tada `db/003_points.sql`.

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

## DB pakeitimai (migracijos)
SQL failai `db/` aplanke numeruoti. Jei DB jau sukurta, importuokite tik naujus failus, kurių dar nebuvo:
- `003_points.sql` - 2 etapas: taškai ir reitingas.

## Svarbu: pagrindinis puslapis dabar `index.php`
Buvęs pradinis puslapis `index.html` sujungtas į `index.php`. Serveryje **ištrinkite `index.html`**, kitaip jis bus rodomas vietoj naujo puslapio.
`page1.html` palikite - jis tik nukreipia senas nuorodas į naują puslapį.
- `004_change_email.sql` - el. pašto keitimas paskyroje.
- `005_public_site.sql` - kainos ir tvarkaraštis pagrindiniame puslapyje iš DB.
- `006_coach_notes.sql` - trenerio pastabos nariams.
- `007_lessons.sql` - pamokos nariams.

## Demo duomenys (laikini)
Kad svetainė neatrodytų tuščia testuojant:
1. File Manager → test `public_html` → įkelkite `deploy/demo-uploads.zip` → Extract (naujienų nuotraukos į `uploads/news/`).
2. phpMyAdmin → test DB → Import → `deploy/demo-data.sql`. (Pirma sukurkite administratorių - demo naujienos ir pastabos priskiriamos jam.)
3. Demo paskyros: `vardas.pavarde@demo.karateka.lt`, slaptažodis nurodytas `demo-data.sql` pirmoje eilutėje.

Ištrinti viską demo: phpMyAdmin → Import → `deploy/demo-remove.sql`. Jūsų sukurti duomenys lieka.
Demo duomenys generuojami iš naujo: `docker compose exec -T web php` ... arba `tools/demo_data.php` (žr. failo viršų).
- `008_belts_tasks_visibility.sql` - diržai, užduotys pastabose, renginiai/naujienos tik nariams.

## Įkėlimas su WinSCP (rekomenduojama vietoj ZIP)
Įkelia tik pasikeitusius failus, seni perrašomi (dublikatų nebūna). `config.php` ir `uploads/news/` niekada neliečiami.

**Vienkartinis paruošimas**
1. DirectAdmin → **FTP Management** → **Create FTP Account**: vartotojas pvz. `testdeploy`, slaptažodis, kelias **Custom** → `/home/aus15792/domains/test.karateka.lt/public_html`. (Taip šis vartotojas negali paliesti gyvos svetainės.)
2. Įdiekite **WinSCP** (https://winscp.net).
3. WinSCP → **New Site**: protokolas **FTP**, šifravimas **TLS/SSL Explicit**, Host - `karateka.lt` (arba serverio vardas, kurį rodo DirectAdmin FTP puslapis), vartotojas `testdeploy@karateka.lt` (tikslų vardą rodo DirectAdmin), slaptažodis → **Save** → pavadinimas **`karateka-test`**, pažymėkite „Save password“.
4. Prisijunkite vieną kartą ranka, kad patvirtintumėte sertifikatą.

**Kiekvieną kartą**
- Dešiniu pelės klavišu ant `tools\deploy-test.ps1` → **Run with PowerShell** → parodo, kas bus įkelta → `t` → Enter.
- Jei pasikeitė DB - importuokite naują `db\0xx_*.sql` per phpMyAdmin.

Pastaba: skriptas failų serveryje netrina. Jei kada failą ištrinsime iš projekto - serveryje jį reikės ištrinti ranka.
