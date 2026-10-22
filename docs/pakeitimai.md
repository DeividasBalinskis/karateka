# Pakeitimai pagal versijas

Versija rodoma šalia logotipo (tik test svetainėje ir kompiuteryje, oficialioje - ne).
Numeris saugomas faile `public_html/app/VERSION`. Kiekvienas įkėlimas į test = nauja versija.
Jei svetainėje matai senesnį numerį nei čia viršuje - naujausia versija dar neįkelta.

## v1.2 - 2026-10-22 (teisėjų testas)
**Duomenų bazė:** importuoti `db/012_referee_test.sql` (lentelės + 300 klausimų).

- Naujas **Teisėjų testas** (WTKU taisyklės): 300 klausimų banke - 100 temų po 3 formuluotes, viename teste iš temos tik vienas klausimas, atsakymų tvarka kaskart sumaišoma.
- Nariams: meniu „Teisėjams“ (rodoma tik tiems, kas gali laikyti). Egzaminas - po vieną klausimą, laikmatis, atsakymai išsaugomi iš karto (dingus internetui ar uždarius puslapį galima tęsti). Mokomasis testas - pasirinktas skyrius ir 10 / 20 / 50 / visi klausimai, baigus rodomi teisingi atsakymai ir vieta taisyklėse.
- Treneriams → „Teisėjų testas“: egzamino atidarymas / uždarymas, rezultatai (ir kas dabar sprendžia), kiekvieno atsakymai, rezultato ištrynimas (tada galima laikyti iš naujo), kas gali laikyti, klausimų taisymas / išjungimas / pridėjimas su statistika.
- Nustatymai (keičia administratorius): klausimų skaičius egzamine (numatyta 50), išlaikymo riba % (80), laikas min. (60, 0 - be limito), kiek kartų galima laikyti (1, 0 - neribota), klausimai iš skyrių proporcingai, rodyti ar ne teisingus atsakymus po egzamino, mokomasis testas įjungtas / išjungtas, kas gali laikyti (tik pažymėti / visi nariai).
- Privatumo politikoje paminėti testo rezultatai (saugojimo laikas dar pažymėtas geltonai).

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
