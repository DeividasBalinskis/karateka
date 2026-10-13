# Pakeitimai pagal versijas

Versija rodoma šalia logotipo (tik test svetainėje ir kompiuteryje, oficialioje - ne).
Numeris saugomas faile `public_html/app/VERSION`. Kiekvienas įkėlimas į test = nauja versija.
Jei svetainėje matai senesnį numerį nei čia viršuje - naujausia versija dar neįkelta.

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
