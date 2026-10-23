# Perkėlimas į karateka.lt

Žingsniai Deividui. Eiga „tuščia DB → pirmas adminas → Atnaujinti DB“ išbandyta su v1.7 (2026-10-23).
Perkeliame tik tada, kai pažymėti visi „Prieš“ punktai.

## Prieš (savaitę iki)

- [ ] Su Deniu nuspręsta: privatumo politikos tarpai, žodžiai, kaip pereina dabartiniai nariai, data.
- [ ] Naujausia versija (su privatumo politika be tarpų) įkelta į test svetainę ir patikrinta.
- [ ] Domenai.lt: karateka.lt ir www.karateka.lt turi SSL (https), PHP 8.x, sukurta nauja tuščia DB.
- [ ] Paštas: SPF, DKIM, DMARC karateka.lt domenui. mail-tester.com rodo ≥ 9/10.
- [ ] Serveris daro atsargines kopijas (failai ir DB) – žinai, kur jos ir kaip atkurti.
- [ ] Senos svetainės adresai: surašyk dažniausius (Google: `site:karateka.lt`, senos svetainės meniu,
      nuorodos Facebook įrašuose). Atsiųsk sąrašą Claude – paruoš peradresavimus (žr. žemiau).
- [ ] Laiškas nariams paruoštas (kur jungtis, kad reikia atsakyti į nuotraukų klausimus).
- [ ] Data – ne prieš varžybas ir ne atostogų metu.

## Perkėlimo diena (~1 val.)

1. **Senos svetainės kopija.** Atsisiųsk visus senus failus (ir DB, jei yra) į kompiuterį.
2. **Nauja DB.** phpMyAdmin → nauja DB → Import eilės tvarka tik `db/001` … `db/007`.
   Likusius įkels mygtukas (5 žingsnis). `dev_seed` **neleisti** – tikroje svetainėje jokių bandomųjų duomenų.
3. **Failai.** `tools\paruosti-ikelimui.ps1` → įkelk `svetaine-vX.Y.zip` į `public_html` ir išskleisk.
4. **config.php** iš `config.example.php`:
   - `'env' => 'live'`, `'site_url' => 'https://karateka.lt'`, `'base_path' => ''`, `'force_https' => true`
   - `db` – naujos DB duomenys; `mail` – tikras SMTP; `notify_email` – kam ateina užklausos
   - `setup_key` – ilgas atsitiktinis raktas
   - **be** `varzybos` bloko (kol varžybų sistema neprijungta, nariai jos nemato)
5. **Pirmas adminas.** Atidaryk `https://karateka.lt/admin/pirmas-adminas.php?key=<setup_key>`,
   sukurk paskyrą (slaptažodis ≥ 10 simbolių). Atsidarys „Reikia atnaujinti duomenų bazę“ → spausk
   **„Atnaujinti duomenų bazę“**. Turi rodyti „Duomenų bazė · Gerai“.
6. **config.php:** pakeisk `setup_key` į kitą reikšmę (kad pirmo admino puslapis nebeveiktų).
7. **.htaccess** `public_html` šaknyje (naujas failas):

   ```apache
   # Neegzistuojantys adresai -> draugiškas puslapis su nuorodomis
   ErrorDocument 404 /404.php

   # www -> be www (https daro force_https)
   RewriteEngine On
   RewriteCond %{HTTP_HOST} ^www\.karateka\.lt$ [NC]
   RewriteRule ^ https://karateka.lt%{REQUEST_URI} [R=301,L]

   # Seni adresai -> nauji (pavyzdžiai; tikrus surašys Claude pagal tavo sąrašą)
   # Redirect 301 /kontaktai /#kontaktai
   # Redirect 301 /naujienos.html /naujienos.php
   ```

8. **Treneriams → Sistema → „Siųsti bandomąjį laišką man“.** Laiškas turi ateiti ne į šlamštą.

## 10 min. patikra po perkėlimo

- [ ] `http://karateka.lt` ir `https://www.karateka.lt` veda į `https://karateka.lt`
- [ ] Registracija su tikru el. paštu → laiškas ateina → tėvų sutikimas → patvirtinimas „Patvirtinimai“
- [ ] Prisijungimas, lankomumo pažymėjimas, naujiena su nuotrauka
- [ ] Forma „2 treniruotės nemokamai“ → laiškas ateina į `notify_email`
- [ ] Neegzistuojantis adresas (pvz. `/senas-puslapis`) rodo „Puslapis nerastas“ su nuorodomis
- [ ] Klaidos puslapyje nesimato failų kelių ar SQL (tai užtikrina `'env' => 'live'`)

## Pirmą savaitę

- Kasdien užsuk į **Treneriams → Sistema**: DB „Gerai“, kopijos daromos.
- Serverio žurnale (domenai.lt → logs) peržiūrėk 404 adresus – atsiųsk Claude, papildys peradresavimus.
- Atnaujinimai kaip test svetainėje: zip → įkelti → Sistema → „Atnaujinti duomenų bazę“.
