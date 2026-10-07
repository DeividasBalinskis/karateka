# Sąskaitos ir apmokėjimai (vietoj Edufi): planas

Būsena: **planas, dar nieko nedaryta.** Laukia Davido patvirtinimo ir kelių atsakymų (8 skyrius).

## 1. Tikslas
Tai, ką dabar daro Edufi (~200 €/mėn.), daryti pačioje karateka.lt:
- kas mėnesį **sąskaitos tėvams** (ir suaugusiems nariams);
- mygtukas **„Apmokėti“** → Paysera (visi Lietuvos bankai ir kortelės), apmokėjus sąskaita **pažymima automatiškai**;
- **priminimai** apie neapmokėtas sąskaitas;
- treneriui - **skolų sąrašas** ir eksportas buhalterei.

## 2. Kaip tai veiktų

**Treneriui / administratoriui**
1. Mėnesio pradžioje: **Treneriams → Sąskaitos → „Sugeneruoti spalio sąskaitas“**.
2. Sistema parodo **peržiūrą** (kam, kiek, už ką) - galima pataisyti ar išimti narį.
3. „Patvirtinti ir išsiųsti“ - sąskaitos sukuriamos su numeriais ir išsiunčiamos el. paštu.
4. Sąrašas: kas apmokėjo, kas ne, kiek skolų. Mygtukai „Pažymėti apmokėta“ (grynais / pavedimu), „Anuliuoti“.
5. „Eksportas buhalterei“ - CSV / Excel failas už mėnesį.

Vėliau galima, kad mėnesio 1 d. sąskaitos susigeneruotų pačios (DirectAdmin „Cron Jobs“).

**Tėvams**
1. Gauna laišką: „Spalio sąskaita - 65 €. Apmokėti iki spalio 10 d.“ su nuoroda.
2. „Mano paskyroje“ - skiltis **Sąskaitos**: sąrašas, būsena, „Apmokėti“, „Atsisiųsti / spausdinti“.
3. „Apmokėti“ → Paysera puslapis → pasirenka savo banką → sumoka → grįžta į svetainę - sąskaita „Apmokėta“.
4. Arba gali sumokėti įprastu pavedimu su **įmokos kodu** iš sąskaitos (tada treneris pažymi ranka, vėliau - automatiškai iš banko išrašo).
5. Neapmokėjus - priminimas el. paštu (pvz. 3 ir 10 d. po termino) ir raudonas ženkliukas paskyroje (kaip pastaboms).

## 3. Kiek kainuoja suma
- Kaina imama iš grupės kategorijos (jau yra: Vaikams 60 €/90 €, Jaunimui 70 €/100 €, Suaugusiems 75 €/100 €).
- Nariui nurodoma: **ar pasirašyta metinė sutartis** (pigesnė kaina ar ne).
- Papildomai (jei reikia): nuolaida broliams/seserims, individuali kaina, nemokamas mėnuo.
- Kelis vaikus turintys tėvai gauna **vieną sąskaitą** su keliomis eilutėmis.

## 4. Mokėjimai per Paysera
- Reikia **Paysera verslo paskyros** VšĮ vardu ir joje sukurto „projekto“ (mokėjimų surinkimui). Tai daro brolis (reikės įmonės dokumentų).
- Iš Paysera gaunami 2 dalykai: **projekto ID** ir **slaptažodis** - jie įrašomi į `config.php` (ne į Git).
- Paysera turi **testavimo režimą** - viską išbandome be tikrų pinigų.
- Paysera ima **mokestį už kiekvieną mokėjimą** (priklauso nuo būdo - bankas ar kortelė). **Tikslų kainyną reikia pasitikrinti** Paysera puslapyje ir palyginti su Edufi 200 €/mėn.
- Alternatyva - **Montonio** (panašiai veikia). Jei kainos geresnės - galima rinktis jį.

## 5. Sąskaitos dokumentas
- Numeracija: pvz. `KA-2026-0001` (serija ir numeris iš eilės, be tarpų - to reikalauja apskaita).
- Rekvizitai: VšĮ Karate Ateitis, įmonės kodas 305105392, adresas, banko sąskaita; mokėtojas (tėvai), vaikas, laikotarpis, suma, apmokėti iki, įmokos kodas.
- **Jei VšĮ yra PVM mokėtoja** - reikia PVM eilučių ir kitokios formos; jei ne - užrašas „PVM nemokėtojas“ arba be jo (pasitikslinti su buhaltere).
- Pradžiai sąskaita - gražus puslapis su mygtuku „Spausdinti / išsaugoti PDF“. PDF prisegimas prie laiško - vėliau, jei reikės.
- Apskaitos dokumentai saugomi ilgai (paprastai 10 m.) - jie **nebus trinami** automatiniu valymu.

## 6. Sutartys
- Edufi greičiausiai tvarko ir sutartis. Pradžiai: sutarties tekstas svetainėje + tėvų „Susipažinau ir sutinku“ (išsaugoma kas, kada, iš kokio IP).
- Smart-ID parašas (Dokobit) - vėliau, jei reikės (mokama).
- Reikia **sutarties teksto** iš brolio.

## 7. Darbų eiga
1. Narių sutarties tipas, kainos, mokėtojas (tėvai) + sąskaitų generavimas su peržiūra + sąskaitų puslapis + rankinis „apmokėta“ + skolų sąrašas + eksportas.
2. Tėvų skiltis „Sąskaitos“ + laiškai + priminimai + ženkliukas.
3. Paysera (pirmiausia testavimo režimu).
4. Sutarčių priėmimas svetainėje.
5. (Vėliau) automatinis generavimas kas mėnesį, banko išrašo įkėlimas, PDF laiške.

Pereiti nuo Edufi geriausia nuo **naujo mėnesio ar sezono pradžios**, kad nebūtų dviejų sistemų vienu metu.

## 8. Ką reikia sužinoti (iš brolio / buhalterės)
- [ ] Ar VšĮ yra **PVM mokėtoja**?
- [ ] **Kas veda buhalteriją** ir kokia programa? Kokio formato eksporto jai reikia?
- [ ] Ar per Edufi gaunamas **NVŠ krepšelis** (valstybės finansavimas vaikams)? Jei taip - kaip jis administruojamas be Edufi? **Labai svarbu prieš atsisakant Edufi.**
- [ ] Ar yra **nuolaidos** (broliams/seserims, kitos)?
- [ ] Kaip mokama, kai prisijungiama **mėnesio viduryje**? Ar mokama **vasarą**?
- [ ] Iki kurios dienos turi būti apmokėta (pvz. iki 10 d.)?
- [ ] **Sutarties tekstas** (dabartinė Edufi sutartis).
- [ ] Ar sutinka atsidaryti **Paysera verslo paskyrą** (ar jau turi)?
