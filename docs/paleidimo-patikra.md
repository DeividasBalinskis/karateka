# Paleidimo patikra (E1) - karateka.lt

Paruošta 2026-11-21. Paleidžiame tik po tavo „GO“ (sprendimas: dar ne). Pildoma prieš paleidimą; ☐ = dar nepadaryta.

## 1. Kas turi būti padaryta prieš „GO“
- ☐ A fazė baigta: A1 (Budo rezultatai niekada nedingsta) - dar iš dalies; A2-A6 - padaryta.
- ☐ `main` = paskutinė patikrinta versija (rašai „į main“).
- ☐ Test svetainėje visi `db/0xx` failai importuoti: Valdymas → Nustatymai → Atsarginės kopijos ir sistema - žalia.
- ☐ Visi naršyklės testai žali (paskutinis pilnas paleidimas - `docs/handoff.md`).
- ☐ Denis peržiūrėjo test svetainę ir neturi atvirų atsiliepimų, kurie stabdo paleidimą.

## 2. Serverio nustatymai (live `config.php`, į git nekeliama)
- ☐ `'env' => 'live'` (kode: jei `env` nėra - laikoma live).
- ☐ Duomenų bazė - nauja live bazė (ne `aus15792_test`), slaptažodžiai tik serveryje.
- ☐ Paštas: `mail.secure` = `ssl` arba `tls`, siuntėjas - klubo adresas (įrašo Deividas serveryje).
- ☐ SMS: teikėjas pasirinktas arba paliekamas `log` (tada SMS nesiunčiami).
- ☐ `ltkf_url` - tuščias, kol LTKF neperkeltas į savo domeną (juodraštis live svetainėje nerodomas).

## 3. Kas kode įjungta tik testavimui - patikrinti live
- ☐ Žaidimas (Dojo): `game_access` - ar atidaromas nariams (dabar test svetainėje - „all“). Live - kaip nuspręsi.
- ☐ Žaidimo „viskas atrakinta“ testavimo jungikliai išjungti.
- ☐ LTKF „juodraštis“ - live nerodomas.
- ☐ Testiniai žmonės, testinės varžybos ir pavyzdžiai nerodomi viešuose sąrašuose (live bazėje jų neturi būti).
- ☐ Atsiliepimų mygtukas (💡) - tik personalui (`feedback_button`).

## 4. Privatumas
- ☐ Nuotraukų sutikimas (vienas „Taip / Ne“) - privatumo politikoje aprašyta.
- ☐ Slapukų sąrašas privatumo puslapyje (C10 dalis).
- ☐ Ką LTKF gali skelbti apie teisėjus ir vaikus (sprendimas: teisėjų vardai ir testo galiojimas - vieši).
- ☐ Buvusių narių duomenys - 1 metai (dabar trinama ranka; C10).

## 5. Seni adresai → nauji (301 nukreipimai `.htaccess`)
- ☐ Surinkti senos karateka.lt adresus. **Kliūtis:** ši debesies sesija negali pasiekti karateka.lt (tinklo taisyklės).
  Kai ateis E1: arba leisi pridėti `karateka.lt` prie sesijos tinklo, arba sąrašą surinksiu tavo PC sesijoje.
- ☐ Kiekvienam senam adresui - naujas (pagrindinis, tvarkaraštis, kontaktai, kainos, nuotraukos...).
- ☐ `.htaccess` su 301 - patikrinti test svetainėje prieš live.

## 6. Perjungimas (iki 10 min.) ir grįžimas atgal
1. Atsarginė senos svetainės kopija (failai + bazė) - atsisiųsti į PC.
2. Įkelti `svetaine-vX.YY.zip` į live aplanką (be `config.php` - jis jau serveryje).
3. Atidaryti `karateka.lt/admin` → „Atnaujinti duomenų bazę“ (pasidaro kopija).
4. Patikrinti: pagrindinis, prisijungimas, Mano paskyra, Valdymas → Šiandien, registracija, kontaktų forma, telefone.
5. **Grįžimas atgal** (jei kas negerai): įkelti senos svetainės failus iš 1 žingsnio ir atkurti bazę - iki 10 min.

## 7. Po paleidimo (pirma savaitė)
- ☐ Kasdien peržiūrėti 💡 atsiliepimus ir serverio klaidų žurnalą.
- ☐ Patikrinti, ar laiškai ateina (registracija, slaptažodžio priminimas).
- ☐ Google Search Console - naujas sitemap (jei naudojama).
