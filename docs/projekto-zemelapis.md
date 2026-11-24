# Projekto žemėlapis

Atnaujinta: 2026-11-24 · v1.95. Visas projektas viename puslapyje: kas veikia, kas dalinai, kas neprasidėjo.
Smulkiau apie žingsnius - `docs/darbo-planas.md` (žingsnių kodai A1…E1 skliaustuose).

✅ veikia · 🟡 veikia dalinai / reikia poliravimo · ⬜ nepradėta · ⏸ laukia jūsų ar Denio atsakymo

## 1. Karateka.lt - klubo svetainė

| Dalis | Kas joje | Būsena |
|---|---|---|
| **1.1 Viešas puslapis** | Pradžia iš blokų (redaguojama vietoje), naujienos su reakcijomis, renginiai ir kalendorius, „2 treniruotės nemokamai“, kontaktai, privatumas | ✅ |
| | Kalbos LT / EN / RU (Denio tekstų vertimai - Valdymas → Vertimai) | ✅ |
| | Temos (13) | ✅ v1.95 gražesnės; ⏸ pažymėkite 👍 kurios tinka |
| | Šriftai | 🟡 v1.95 bandymas „Aa“; ⏸ pažymėkite 👍, tada vieną padarysime visiems |
| | Meniu išvaizda kompiuteryje ir telefone | ⏸ variantai A1–A3, B1–B3 (nuotraukos siųstos) |
| **1.2 Paskyros** | Registracija (tėvai, 14+), tėvų sutikimas, prisijungimas su kodu, slaptažodžio atkūrimas | ✅ |
| | Šeimos paskyros: vaiko prisijungimas vardu + PIN, tėvai treniruojasi patys | ✅ |
| | Kodas kitam įrenginiui (adminui) | ✅ |
| **1.3 Nario zona** | Apžvalga / „Mano diena“ (vaikams), užduotys, pamokos pagal diržą, egzamino kelias („Moku“), testai, klausimai, renginiai ir „Dalyvausiu“, pokalbis su treneriu | ✅ |
| | Telefono juosta viršuje, 🎨 tema | ✅ |
| **1.4 Egzaminai** | Laukiantieji + trumpa registracijos nuoroda, protokolai pagal diržus, balai, rezultatai, federacijos registras, programa | ✅ |
| | Asmeninis egzamino puslapis | 🟡 mokėjimas ir pažymėjimas - tik vieta (reikia mokėjimų tiekėjo) |
| **1.5 Pateisinimai** | Nuoroda tėvams, prašymas, peržiūra, redagavimas, savo Word / PDF, siuntimas, pranešimai tik Deniui | ✅; ⏸ klubo Word šablonas |
| **1.6 Valdymas** | Šiandien, treniruotė ir lankomumas, nariai, patvirtinimai, aktyvumo taškai (XP), testai, renginiai ir dalyviai, naujienos, pamokos, grupės ir tvarkaraštis, atšaukimai, žinutės, nustatymai, kopijos, atsiliepimai | ✅ |
| **1.7 Pranešimai ir laiškai** | 🔔 sąrašas, „Reikia padaryti“, laiškų šablonai trimis kalbomis, siuntimo eilė | ✅ |
| | SMS | 🟡 veikia bandymo režimu; tikram siuntimui reikia SMS tiekėjo (kainuoja) |

## 2. Budo - varžybų sistema

| Dalis | Kas joje | Būsena |
|---|---|---|
| **2.1 Pasiruošimas** | Varžybos, klubų registracija su rungtimis, kategorijos, burtai, tvarkaraštis, tatamiai | ✅ |
| **2.2 Varžybų diena** | Kumite (taškai, laikas), kata (vėliavėlės ir balai), sekretoriatas, keli planšetiniai | ✅ |
| | Ta pati grupė dviejose planšetėse vienu metu | 🟡 laimi paskutinis (D4) |
| **2.3 Žiūrovams** | Gyvai, TV ekranas, OBS juosta, YouTube nuoroda kiekvienam tatamiui | ✅; ⏸ video variantai C2 (planšetė) / C3 (salės TV) |
| **2.4 Po varžybų** | Apdovanojimai, rezultatai, taškai nariams | ✅ |
| **2.5 Kodas** | `varzybos.js` išskaidymas į dalis (D3), Budo gyvai poliravimas (D5) | ⬜ / 🟡 |
| **2.6 Išorinė Varžybų programa** | Palyginti ir nuspręsti, kurią palikti (D1, D2) | ⏸ reikia jos kodo šakoje `varzybos-kodas` |
| **2.7 Mokėjimai** | Tėvų apmokėjimas už registraciją | ⬜ reikia mokėjimų tiekėjo |

## 3. LTKF - federacijos svetainė (juodraštis, matoma tik test)

| Dalis | Kas joje | Būsena |
|---|---|---|
| **3.1 Puslapiai** | Apie, naujienos, renginiai, kalendorius, klubai, teisėjai, dokumentai, rezultatai, kontaktai, EN | 🟡 turinys - juodraštis |
| **3.2 Redaktorius** | Tekstai, versijos su atkūrimu, naujienos ir dokumentai, juodraštis / viešas | ✅ |
| **3.3 Registrai** | Nariai, klubai, teisėjai; teisėjų lankomumas ir diplomai | ✅ |
| **3.4 Savas domenas** | Perkėlimas į LTKF domeną (D6 4 dalis) | ⬜ laukia hostingo |

## 4. Dojo - žaidimas (paslėptas, mato tik administratoriai)

| Dalis | Kas joje | Būsena |
|---|---|---|
| **4.1 XP sistema** | XP = aktyvumo taškai: veikla (treniruotės, užduotys, egzaminai, renginiai) → XP → lygis → dovana Dojo | ✅ veikia; 🟡 dovanų balansas - D7 |
| **4.2 Herojus ir dojo** | Savas herojus, jo dojo, apranga, diržas; kitų žaidėjų dojo | 🟡 |
| **4.3 Arena (kovos)** | Kovos ėjimais, ginklas ir kairė ranka (skydas / antras ginklas) | 🟡 D7: vienas kovos modelis („3 kortos + gyvūnėlis“) |
| **4.4 Mini žaidimai** | Keturi iš eilės, Kata ritmas, kvietimai žaisti kartu | 🟡 |
| **4.5 Reitingas ir prizai** | Viena lentelė su skirtukais, „Tavo vieta“, prizus skiria tik serveris | ⬜ D7; ⏸ Denio atsakymai 35–38 |
| **4.6 Personažai** | Nauji pixel art personažai su animacija (dabar tikras tik lapinas) | ⬜ D8 (po D7; LibreSprite) |
| **4.7 Atidarymas nariams** | Kada rodyti visiems nariams | ⏸ jūsų sprendimas |

## 5. Technika (nematoma, bet būtina)

| Dalis | Būsena |
|---|---|
| Automatinis įkėlimas į test (GitHub → FTP), duomenų bazė atsinaujina pati | ✅ v1.94 |
| Testai (173 naršyklės scenarijai + greitos patikros) | ✅; 🟡 C6 variklio vienetų testai |
| Atsarginės kopijos, duomenų priežiūra, nustatymų registras, dizaino žetonai | ✅ |
| C1 leidimai viena sistema (`can()`) | ⬜ |
| C3 slaptos nuorodos vienoje vietoje („Visos nuorodos“) | ⬜ |
| C5 CSS sutvarkymas be vaizdo pokyčių | ⬜ |

## 6. Paleidimas (E1)

Gyva karateka.lt - ⏸ laukia jūsų „GO“, privatumo ir nuotraukų sutikimų. Patikros sąrašas: `docs/paleidimo-patikra.md`.

## Kas liko - siūloma eilė

1. ⏸ Jūsų pasirinkimai: 👍 temos ir šriftai, meniu variantai (A, B), video variantas (C2/C3), klubo Word šablonas pateisinimams.
2. D7 Dojo kovos ir reitingas (reikia Denio atsakymų 35–38), tada D8 personažai.
3. D4 ta pati grupė dviejose planšetėse, D5 Budo gyvai poliravimas.
4. C1, C3, C5, C6 - techninis tvarkymas (vartotojai nepastebės, bet bus lengviau ir saugiau).
5. D1/D2 išorinė Varžybų programa (kai bus jos kodas), D6 LTKF domenas (kai bus hostingas).
6. E1 paleidimas į gyvą karateka.lt.
