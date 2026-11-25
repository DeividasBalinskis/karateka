# karateka.lt ↔ varžybų sistema

**Nuo karateka.lt v1.4 integracija prižiūrima pačiame Karateka projekte** (Treneriams → Varžybos, prisijungimas be slaptažodžio per `sso.php`).
Šiame aplanke - pradinė (v1.2) failų kopija, iš naujo jos diegti nereikia.

Šie failai jau nukopijuoti į karateka.lt projektą (`Documents\IT Projektai\Karateka`). Čia laikoma kopija.

## Nauji failai
- `db/012_varzybos.sql` - nario lytis (`members.gender`) ir renginio ryšys su varžybomis (`events.tournament_id`)
- `public_html/app/varzybos.php` - užklausos į varžybų sistemos API
- `public_html/varzybos.php` - nariams: registracija į varžybas (save ar vaiką), atšaukimas
- `public_html/admin/varzybu-rezultatai.php` - treneriui: susieti renginį su varžybomis ir įkelti vietas (taškai skiriami per esamą `save_event_results`)

## Papildyti esami failai (po kelias eilutes)
- `public_html/app/bootstrap.php` - `require APP_DIR . '/varzybos.php';`
- `public_html/paskyra.php` - juosta „Registracija į varžybas →“
- `public_html/admin/renginio-rezultatai.php` - mygtukas „Įkelti iš varžybų sistemos →“ (tik varžybų renginiams)

Atšaukti: `git checkout -- public_html/app/bootstrap.php public_html/paskyra.php public_html/admin/renginio-rezultatai.php` ir ištrinti 4 naujus failus.
Įdiegti iš naujo: `python pritaikyti.py "<karateka aplankas>"` (jau įdiegtą praleidžia).

## Nustatymai
Lokaliai (`env = local`) nieko nustatyti nereikia - jungiasi prie `http://host.docker.internal:8090`.
Serveryje `config.php`:
```php
'varzybos' => [
    'url'       => 'https://varzybos.karateka.lt',
    'key'       => 'tas-pats-ilgas-raktas-kaip-varzybu-config-api_keys-karateka',
    'club_name' => 'Karate Ateitis',
    'club_city' => 'Vilnius',
],
```
Be šio nustatymo serveryje registracijos juosta ir mygtukas nerodomi.
