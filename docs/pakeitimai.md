# Pakeitimai pagal versijas

Versija rodoma šalia logotipo (tik test svetainėje ir kompiuteryje, oficialioje - ne).
Numeris saugomas faile `public_html/app/VERSION`. Kiekvienas įkėlimas į test = nauja versija.
Jei svetainėje matai senesnį numerį nei čia viršuje - naujausia versija dar neįkelta.

## v1.7 - 2026-10-23 (nuotraukų sutikimai, pataisymai po patikros, LTKF ir varžybų prototipai)
**Duomenų bazė:** importuoti `db/020_nuotrauku_sutikimai.sql`. Failą galima importuoti ir antrą kartą - nieko nepridės dvigubai.

Nuotraukų sutikimai (pasiūlymas susitikimui - Denio klausimas „svetainėje ne, varžybose taip“):
- Vietoj vieno klausimo - du: ar **klubas** gali skelbti nuotraukas (karateka.lt, klubo soc. tinklai) ir ar **varžybų organizatorius** gali skelbti varžybų nuotraukas. Klausiama registracijoje, tėvų sutikime, pridedant vaiką ir paskyros Nustatymuose.
- Kiekvienas atsakymas įrašomas į sutikimų žurnalą (`consent_log`): kas, kada ir ką atsakė. Paskyroje rodoma „Paskutinį kartą atsakyta …“.
- Esamiems nariams senas atsakymas lieka kaip „klubas“, o dėl varžybų paskyroje rodomas pranešimas „Naujas klausimas … Atsakyti →“.
- Treneriui: narių sąraše stulpelis „Nuotraukos: klubas · varžybos“, nario kortelėje abu atsakymai; lankomume ir renginio dalyviuose žymė „Be nuotraukų“.
- Varžybų sistemai kartu su dalyviu perduodama žymė `photo_ok` (ar organizatorius gali skelbti nuotraukas).
- Privatumo politikos skyrius „Nuotraukos ir vaizdo įrašai“ perrašytas pagal du klausimus.

Pataisymai po patikros (nepriklauso nuo susitikimo sprendimų):
- Skaičiai su teisingomis galūnėmis: „1 klausimas“, „14 narių“, „22 nariai“ (A6, A7). Egzamino žvaigždutės: „★ 3 žvaigždutės“ (A13).
- „Pasirinkite vietą sąraše“ vietoj „kairėje“ (A4), „Žinutė treneriui · Austėja“ (A5), „Visoms grupėms“ (A9), renginio „Pabaigos data“ (A10), „Tėvų sutikimas gautas 2026 m. spalio 22 d.“ (A11).
- Tėvų sutikime „Susikurti tėvų paskyrą“ nebežymima iš anksto - tėvai, kurie nori tik sutikti, nebegauna klaidos (B2).
- Trenerių skirtukai rodomi ir naujienų, pamokų bei renginių tvarkyme (B5); telefone skirtukų juosta dešinėje blunka - matosi, kad yra daugiau (B4).
- Atidarius narių puslapį neprisijungus - paaiškinimas „Šis puslapis matomas tik prisijungusiems nariams“ (B8).
- „Tėvų sutikimas gautas“ rodomas tik jaunesniems nei 14 m. (B11). Varžybų mygtukai slepiami, kai varžybų sistema nepasiekiama (B14).
- Nemokamos treniruotės forma siunčia per bendrą pašto funkciją (`config.php` → `mail`), turi apsaugą nuo šlamšto (paslėptas laukas, laikas, 5 per valandą iš vieno IP), o klubo laiške „Atsakyti“ nueina tiesiai tėvams (C2). **Patikrinkite test svetainėje, ar laiškas ateina.** Bloko `smtp` faile `config.php` nebereikia.
- SMS puslapyje treneriui - be techninių žodžių (C4). Ištrintas nenaudojamas `page1.html` (C3).
- Senesnių versijų zip nebeištrinami - lieka kaip atsarginės kopijos.

Nauji projektai tame pačiame aplanke (karateka.lt tai neįtakoja):
- `ltkf/` - LTKF svetainės juodraštis. Kompiuteryje: `docker compose up -d` → http://localhost:8089.
- `varzybos-prototipas/index.html` - varžybų lentelių ir tvarkaraščio prototipas (kaip Kihapp, bet su redagavimu). Atidaryti dukart spustelėjus.

Laukia susitikimo sprendimų: „Karatekams“ (A1), „Registruotis“ (A2), 1 ar 2 nemokamos treniruotės (A3), „žymė“ (A8), „tu“ ar „jūs“ (A12), slėpti reklamą prisijungusiems (B3), privatumo politikos tarpai.

## v1.6 - 2026-10-22 (WTKU teisėjų testas, laiko limitas testams)
**Duomenų bazė:** importuoti `db/019_wtku_teiseju_testas.sql`. Failą galima importuoti ir antrą kartą - nieko nepridės dvigubai.

- Treneriams → Testai: naujas testas „WTKU teisėjų testas“ (Kam: Teisėjams) - 300 klausimų iš brolio failo (WTKU Traditional Karate Competition Rules). Mato tik nariai su žyma „Teisėjas“.
- Numatyta: 50 klausimų, reikia 80 % (= 40 teisingų), 60 min. Viską galima keisti testo nustatymuose; prie „Reikia surinkti, %“ rodoma, kiek tai teisingų atsakymų.
- Visiems testams naujas nustatymas „Laikas, min.“ (0 - neribota): teste rodomas laikmatis, pasibaigus laikui testas išsiunčiamas automatiškai; vėliau atsiųsti atsakymai neįskaitomi.
- Klausimo „Tema“: to paties klausimo kitos formuluotės. Viename teste (ir treniruotėje) iš temos būna tik vienas klausimas - WTKU faile kiekvienas klausimas parašytas 3 būdais, todėl teste daugiausia 100 skirtingų.
- Po atsakymo ir rezultatuose rodoma vieta taisyklėse (pvz. „Šaltinis: WTKU taisyklės, II dalis, 1 str., p. 12 (Kumite)“).
- Teste pažymėti atsakymai nedingsta netyčia perkrovus puslapį.
- Įrankis `tools/atnaujinti-is-github.ps1`: vienu paspaudimu atsisiunčia naujausią versiją iš GitHub, paruošia zip ir parašo, kurį db failą importuoti.
- `tools/paruosti-ikelimui.ps1` langas nebeužsidaro iškart: parodo paruoštą zip (ar klaidą) ir laukia Enter.

## v1.5 - 2026-10-21 (privatumo politika, skirtukas „Varžybos“)
**Duomenų bazė:** nekeičiama.

- Treneriams → „Varžybos“ rodomas visada. Kol varžybų sistema neįkelta į serverį, puslapis paaiškina, kas ten bus ir ką daryti kol kas.
- Privatumo politika papildyta: lytis ir diržas, lankomumas, testų rezultatai, trenerio pastabos ir žinutės, duomenys varžybų sistemai, Top 5. Geltonos vietos laukia brolio atsakymo.
- Registracijoje vietoj „Sutinku… Duomenys neperduodami tretiesiems asmenims“ - „Susipažinau su privatumo politika“.

## v1.4 - 2026-10-20 (žinutės su treneriu, pamokų siuntimas, ryšys su varžybų sistema)
**Duomenų bazė:** importuoti `db/018_zinutes.sql`. Test svetainei - vienas failas `ikelimui/atnaujinti-db-v1.4.sql` (jame 008-018): galima importuoti kelis kartus, kas jau yra - praleidžiama, todėl nesvarbu, kurie failai jau buvo importuoti.

- Žinutės: narys (ar tėvai) gali atsakyti į trenerio pastabą, paklausti prie pamokos ar parašyti treneriui paskyroje. Mato tik treneriai; trenerio atsakymą - tik tas narys ir jo tėvai.
- Treneriams → „Žinutės“: visi pokalbiai (nauji viršuje, „Laukia atsakymo“), atsakymas su laišku, nauja pastaba su pamoka ar užduotimi.
- Nariui: apie trenerio atsakymą praneša skaičiukas prie „Mano paskyra“ ir juosta viršuje, ateina laiškas.
- Pamokos: „📨 Siųsti nariams“ - pasirenki grupes ar konkrečius vaikus, parašai komentarą; kiekvienas gauna pastabą su pamoka paskyroje.
- Treneriams → „Varžybos“: varžybos iš varžybų sistemos, „Atidaryti varžybų valdymą ↗“ (be atskiro slaptažodžio), „+ Sukurti renginį karateka.lt“.
- Varžybų renginyje: „Dalyviai → varžybos“ - „Dalyvaus“ pažymėtus narius užregistruoti į tinkamas kategorijas; kas užsiregistravo varžybų sistemoje, pažymimas „Dalyvaus“. „Rezultatai → taškai“ - kaip anksčiau.
- Varžybų sistemai reikia jos `db/002_sso.sql` ir PHP 8.1+ (atskiras projektas `Varzybos`).

## v1.3 - 2026-10-20 (pamokos aplankuose, kvietimai konkretiems vaikams, mažiau dubliavimo)
**Duomenų bazė:** importuoti `db/016_pamoku_aplankai.sql` ir `db/017_renginiai_pakviestiems.sql` (po v1.2 failų). Test svetainei visi nuo 011 iki 017 sudėti į vieną failą `ikelimui/atnaujinti-db-v1.3.sql`. Esamos pamokos lieka - jų temos tampa aplankais.

- Pamokos: aplankai aplankuose (pvz. Kata → Heian katos), su ženkliuku, aprašymu, „tik teisėjams / egzaminatoriams“, paskelbta ar ne.
- Pamokos: turinys blokais - antraštė, tekstas (paryškinimas, sąrašai), YouTube video su prierašu, nuotrauka, trenerio pastaba, nuoroda.
- Treneriams → Pamokos: medis - aplankus ir pamokas galima tempti pele (ir mygtukais ↑ ↓), perkelti į kitą aplanką; išsaugoma iš karto.
- Pamokos kopijavimas, „Išsaugoti ir likti“, įspėjimas apie neišsaugotus pakeitimus. Ištrinant aplanką jo turinys perkeliamas lygiu aukščiau.
- Nariams: aplankų kortelės, kelias (Pamokos › Kata › Heian katos), paieška, „Ankstesnė / Kita“ pamoka.
- Narys mato savo, visų žemesnių ir kito diržo pamokas (anksčiau - tik savo ir kito).
- Renginio forma: „Kam skirta?“ - grupes galima išskleisti ir pažymėti tik kelis vaikus; jie pakviečiami. Renginys gali būti tik pakviestiems.
- Tas pats pasirinkimas - SMS gavėjams ir „Dalyviai → Pakviesti“; paieška pagal vardą, „Pažymėta: N“.
- Mažiau dubliavimo: renginiai, naujienos ir pamokos tvarkomi pačiose skiltyse (viršutinė juosta) - treneris ten mato „Treneriui“ juostą ir mygtukus prie kiekvieno įrašo (✎ Redaguoti, Dalyviai, Rezultatai). Iš „Treneriams“ skirtukų šios trys dingo.
- Naujienos: treneris skiltyje mato ir juodraščius bei suplanuotas; reakcijų emoji - mygtukas „🙂 Reakcijų emoji“.
- Nario paskyra: liko Apžvalga · Egzaminas · Nustatymai. Kvietimai į renginius - Renginių viršuje („Treneris kviečia“), skaičius prie „Renginiai“.
- Jei duomenų bazė neatnaujinta (neimportuotas db failas), vietoj klaidos rodomas aiškus pranešimas, ką daryti.
- Pataisyta: tekste raidė „ą“ eilutės gale galėjo sugadinti naujienos ar pamokos tekstą.

## v1.2 - 2026-10-19 (2–5 ir 8 etapai, dar neįkelta į test)
**Duomenų bazė:** importuoti iš eilės `db/012_varzybos.sql`, `db/013_dalyviai_zymos.sql`, `db/014_egzaminai_testai.sql`, `db/015_sms.sql`
(jei v1.1 dar neįkelta - pirma ir `db/011_site_settings.sql`). Failai tik prideda naujų lentelių ir stulpelių; esamos naujienos ir renginiai nekeičiami.

- Meniu: trenerio ir nario skirtukai - po viršutine juosta. Nario paskyra: Apžvalga · Renginiai · Egzaminas · Pamokos · Nustatymai.
- Treneriams → „Šiandien“ (vietoj „Pradžia“): šiandienos treniruotės su lankomumu, kas laukia patvirtinimo, renginiai be rezultatų, artimiausi renginiai su dalyvių skaičiumi, nauji testų rezultatai.
- Renginiai: „Dalyvausiu“ / „Negalėsiu“ (tėvai - už vaiką). Kiekvienam renginiui galima išjungti registraciją ar nustatyti terminą.
- Renginiai: treneris kviečia grupes ar konkrečius žmones (kvietimas paskyroje ir el. paštu); dalyvių sąrašas su spausdinimu.
- Renginio rezultatuose rodomi tik užsiregistravę dalyviai (galima parodyti visus). Egzamine - varnelė „pakelti diržą“ išlaikiusiems.
- Žymos „Teisėjas“ ir „Egzaminatorius“ (Nariai → žmogus). Teisėjai gali registruotis į varžybas kaip teisėjai.
- Egzaminas: diržų kelias, egzamino kortelė su 3 žingsniais, kito diržo programa su žvaigždutėmis „Moku“.
- Testai kaip Regitroje: atsitiktiniai klausimai, riba 83 % (keičiama), treniruotės režimas, vaikams - didesni mygtukai ir konfeti.
- Treneriams → „Testai“: testai ir klausimai (su paveikslėliu ar video), rezultatai; „Egzaminų programa“ pagal diržus.
- Pamokos „Teisėjams“ ir „Egzaminatoriams“ - mato tik su žyma. Išlaikiusiems testą prie žymos rodoma ✓ (galioja 12 mėn.).
- Treneriams → „SMS“ (tik adminui): žinutė grupei, renginio dalyviams ar žmonėms; vaikams - tėvų numeriai; istorija. Kol nėra SMS teikėjo - bandomasis režimas (niekas nesiunčiama).
- Varžybų sistemos ryšys (`db/012`): lytis nario kortelėje, registracija į varžybas. Veikia tik nustačius `varzybos` faile `config.php`.

## v1.1 - 2026-10-13 (0 ir 1 etapai, dar neįkelta į test)
**Duomenų bazė:** importuoti `db/011_site_settings.sql`.

- Versijos numeris šalia logotipo; įkėlimo failas su versija: `svetaine-v1.1.zip`.
- Reitingas: po Top 5 visada rodoma tavo eilutė (vieta ir taškai), net su 0 tšk.
- „Kam skirta?“ (renginiai ir pamokos): „Visos grupės“, „Vaikai (visos 11)“ ir kitos kategorijos vienu paspaudimu.
- Renginiai: treneris mato „✎ Redaguoti“ prie kiekvieno renginio (renginių puslapyje ir pagrindiniame); išsaugojus grįžta ten pat.
- Renginiai: filtras Visi · Egzaminai · Varžybos · Seminarai · Stovyklos · Kita; kiekvienas tipas savo spalvos.
- Pagrindinis puslapis: po renginiais - 3 naujausios naujienos ir „Visos naujienos →“.
- Naujienos: mygtukas „Dalintis“ (kompiuteryje - Facebook, telefone - telefono dalinimosi meniu); Facebook'e matosi nuotrauka ir pavadinimas.
- Naujienos: reakcijos - rodomi tik panaudoti emoji su skaičiais ir vienas mygtukas 🙂+ su emoji sąrašu.
- Treneriams → „Naujienos 🙂“: adminas pasirenka, kuriuos emoji leisti (iš ~75). Išjungus emoji, senos reakcijos lieka.
- Įrankis `tools/lokali-svetaine.ps1`: lokali svetainė iš naujo vienu paspaudimu (švari DB + demo duomenys).

## v1.0 - 2026-10-08 (šiuo metu test.karateka.lt)
Viskas, kas padaryta iki spalio 8 d.: paskyros ir patvirtinimai, grupės ir tvarkaraštis, renginiai, naujienos su reakcijomis,
taškai ir reitingas, diržai, trenerio pastabos ir užduotys, lankomumas, pamokos. Paskutinis pakeitimas - trenerio mygtukas „Paskyra“.
