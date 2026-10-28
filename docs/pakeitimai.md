# Pakeitimai pagal versijas

Versija rodoma šalia logotipo (tik test svetainėje ir kompiuteryje, oficialioje - ne).
Numeris saugomas faile `public_html/app/VERSION`. Kiekvienas įkėlimas į test = nauja versija.
Jei svetainėje matai senesnį numerį nei čia viršuje - naujausia versija dar neįkelta.

## v1.9 - 2026-10-28 (pokalbis kaip Messenger, komentarai su vieta, instruktorių langas, lankomumas su spalvotomis grupėmis)
**Duomenų bazė:** importuoti `db/022_pokalbiai_grupiu_spalvos.sql`. Failą galima importuoti ir antrą kartą.
**Kompiuteryje:** atnaujinti su `tools\lokali-atnaujinti.ps1` - duomenys ir nuotraukos lieka. `tools\lokali-svetaine.ps1` viską ištrina ir kuria iš naujo (dabar prieš tai paklausia).

Pokalbis ir komentarai:
- Pokalbio langelis kaip Messenger: atsinaujina kas 2,5 s, Enter - siųsti. Nariai rašo tik Deniui.
- Treneriui: kairėje visi pokalbiai su paieška (rasti galima ir tą, su kuriuo dar nerašyta), dešinėje pokalbis.
  „Žinutės“ ir „Atsakyti“ atidaro langelį - į kitą puslapį nebeperkelia. Telefone - sąrašas, paspaudus - pokalbis (‹ atgal).
- Prie kiekvienos žinutės matyti, kur parašyta (renginys, naujiena, pamoka ar puslapis), kas ir kada. Galima „↩ Atsakyti čia“ ir „👁 Rodyti visiems“.
- Anoniminis komentaras: Denis mato, kas parašė, gali atsakyti ir paviešinti - kitiems nariams vardas nerodomas.
  Prie renginių, naujienų ir pamokų - varnelė „Anonimiškai“.
- Pranešimas apie komentarą („Svarbu“ apžvalgoje) veda tiesiai į įrašą, kur komentaras paliktas; ten matyti kas ir kada, atsakoma čia pat.
- Naujas puslapis „Klausimai ir atsakymai“ (nuoroda apačioje) - Denio paviešinti bendri klausimai.

Treneriams:
- Apžvalga: kairėje - ką reikia padaryti (pranešimai ir darbai kartu), šiandienos treniruotės, artimiausi renginiai;
  dešinėje - greiti veiksmai, visi nustatymai grupėmis ir „Mano paskyra“. Skirtukai „Grupės“, „Tvarkaraštis“, „Taškai“, „Paskyros“ sujungti į vieną - „Nustatymai“.
- Lankomumas: „✓ Buvo“ dešinėje (patogiau dešine ranka). Grupė renkama iš spalvotų kvadratėlių (šiandienos - pirmos).
  Grupės spalvą galima pakeisti grupės nustatymuose.
- Reakcijos: visų diržų spalvos ir teisėjo vėliavėlės (balta ir raudona) - Nustatymai → Reakcijų emoji.

Pagrindinis puslapis:
- Instruktoriai: paspaudus nuotrauką ar vardą - langas su daugiau informacijos. Tekstą keisti redagavimo režime, po kortele - „Plačiau (rodoma paspaudus)“.
- „2 treniruotės nemokamai“ perkeltas į dešinę meniu dalį - prisijungus dingsta ir tuščios vietos nebelieka.
- Meniu „Kontaktai“ ir kitos nuorodos nuslenka taip, kad visa skiltis matyti. Kontaktų ir treniruočių vietų blokai - vienodo aukščio.

Kita:
- Lokalūs pavyzdžiai pažymėti „TEST:“, aprašyme - ką patikrinti. Atnaujinant niekas neištrinama ir nekeičiama.

## v1.8 - 2026-10-27 (pranešimai „Svarbu“, renginio puslapis, pokalbis su Deniu, XP ir lygiai)
**Duomenų bazė:** importuoti `db/021_pranesimai_renginiai_xp.sql`. Failą galima importuoti ir antrą kartą.

Mano paskyra:
- Viršuje skiltis „Svarbu“ - viskas, ką reikia padaryti: kvietimai į renginius (su laikmačiu ir „Dalyvausiu / Negalėsiu“), egzamino testas,
  naujos trenerio pastabos, užduotys ir atsiųstos pamokos, trenerio atsakymai, pranešimai. Paspaudus „Perskaičiau“, „Peržiūrėjau“, „Atlikau“ ar „Supratau“ - dingsta.
  Išsaugotos pamokos čia nerodomos.
- Denio atsakymas rodomas „Svarbu“ su tekstu, kol paspaudžiama „Perskaičiau“ arba „Atsakyti“ (atidaro pokalbį).
- Raudona juosta viršuje (visuose puslapiuose) veda tiesiai į „Svarbu“.
- Atsiųstos pamokos puslapio viršuje - „✓ Peržiūrėjau“ (užduočiai „✓ Atlikau“): pamoka dingsta iš „Svarbu“.
- Taškų kortelėje - lygis ir XP juosta.

Renginiai:
- Kiekvienas renginys turi savo puslapį (paspaudus pavadinimą ar „Plačiau →“): visas aprašymas, vieta su žemėlapiu, registracija, kiek dalyvauja.
  Dalyvių sąrašą mato tik treneriai, keičia ir rezultatus suveda - tik adminas.
- Renginio forma - 4 žingsniai, naujas laukas „Trumpai“ (rodomas sąraše). Visos grupės pažymėtos (numatyta) - kviečiami visi;
  nieko nepažymėta - atviras renginys: kvietimų nėra, prisijungti gali visi.
- Registracija: numatyta iki dienos prieš renginį (galima pakeisti), rodomas laikmatis „liko 2 d. 5 val.“. Renginio dieną ir po jo - nebegalima.
- Po termino - mygtukas „Noriu dalyvauti (pavėlavau)“: Denis gauna pranešimą ir patvirtina. Nepatvirtinus narys lieka sąraše kaip „vėlavo“ ir skaičiuojamas dalyviu.
- Kvietimas visada matomas svetainėje; el. laiškas ir SMS - tik jei pažymėta kuriant renginį.
- Egzamino puslapyje nariui: testo būsena ir „Laikyti testą“, egzamino programa, pamokos egzaminui. Užsiregistravus į egzaminą - pranešimas Deniui paskyroje.
- Taškai skiriami tik už dalyvavimą; „Kita“ tipo renginiams - nauja kategorija „Renginys: dalyvavimas“ (3 tšk., keičiama).

Klausimai ir pokalbis su Deniu:
- Prie renginių, naujienų ir pamokų - „💬 Klausimas Deniui“ (aiškiai parašyta, kad mato tik Denis). Atsakymą mato tik klausęs,
  nebent Denis paspaudžia „👁 Rodyti visiems“ (ir pasirenka - su vardu ar be).
- Prisijungusiems apačioje dešinėje - „Rašyti Deniui“: pokalbis atsinaujina kas 10 s, tėvai pasirenka, dėl kurio vaiko rašo.
  Skirtukas „Anoniminis komentaras“ - kas parašė, neišsaugoma. Po prisijungimo trumpai parodoma užuomina apie pokalbį.
- Treneriams tas pats mygtukas veda į „Žinutės“: visi pokalbiai ir anoniminiai komentarai, puslapis atsinaujina pats.
  Laiškas apie naują nario žinutę - ne dažniau kaip kas 30 min.

XP ir lygiai:
- Taškai = XP. Iš sezono taškų skaičiuojamas reitingas, iš visų kada nors surinktų - paskyros lygis (lygis diržo nesuteikia).
  Kiek XP reikia vienam lygiui - Treneriams → Taškai (numatyta 100).
- Egzamino programos punktai turi XP (numatyta 20, keičiama programoje). Narys spaudžia ☆ „Moku“ → ⏳ laukia trenerio →
  Denis patvirtina pranešime (ar nario kortelėje) → ★ ir XP. „Dar ne“ - punktas grąžinamas.
- Testai: XP už pirmą bandymą ir už pirmą išlaikymą per sezoną (2 ir 5, keičiama Taškuose).
- Pakilus lygiui ar išlaikius egzaminą - šventė ekrane (vieną kartą). Programoje naujas skyrius „Fizinis pasiruošimas“.

Treneriams:
- „Šiandien“ viršuje „Pranešimai“: „Moku“ patvirtinimai, pavėlavusieji, registracijos į egzaminą, atšaukti sutikimai - sprendžiami čia pat.
- Naujas skirtukas „Pranešimai“ (tik adminas): vienas tekstas pasirinktoms grupėms ar žmonėms - paskyroje, el. paštu ir (ar) SMS. SMS puslapis - ten pat.
- Nario kortelėje: siųsti pamoką ar užduotį, atsiųstų pamokų sąrašas (peržiūrėta ar ne), kito diržo programa su „Patvirtinti“ / „Įskaityti“, lygis.
- Treniruotė: plačiame ekrane vienas vaikas - viena eilutė (vardas, buvo / nebuvo, pastaba, pamoka). Pastabą vaikas ir tėvai iškart mato „Svarbu“.
- Grupei galima įrašyti atskirą kainą (pvz. mokyklose) - pagrindiniame puslapyje rodoma prie tos vietos tvarkaraščio.
- Atšaukus sutikimą dėl nuotraukų - pranešimas Deniui. Rašant naujieną, šalia nuotraukų - kas nesutinka (mato tik adminas).

Kita:
- Lokaliai (`tools/lokali-svetaine.ps1`) įkeliami ir naujų funkcijų pavyzdžiai: kvietimai su terminu, pranešimai, XP, pokalbis.
- Privatumo politika: įrašyta, kas prižiūri varžybų sistemą, kiek laiko saugomi duomenys (paskyros, sutartys ir pažymos - kol narys ir 1 metus po; užklausos - 6 mėn.), data. Geltonų vietų neliko.
- „Kam skirta?“: „Karatekams“ pervadinta į „Nariams“ (pamokos, testai).

## v1.7 - 2026-10-26 (redaguojamas pagrindinis puslapis, pamokos pagal diržą, tvarkaraštis)
**Duomenų bazė:** importuoti `db/020_pamokos_puslapis.sql`. Failą galima importuoti ir antrą kartą.

Pagrindinis puslapis (keičia tik adminas):
- Pagrindiniame puslapyje mygtukas „✎ Redaguoti puslapį“: tekstus rašai tiesiai puslapyje, nuotraukas keiti „📷 Keisti“, išsaugai „Išsaugoti“.
- Puslapis sudarytas iš blokų: juos galima tempti ⠿ (ar ↑ ↓), paslėpti, ištrinti, grąžinti pradinį turinį ir pridėti naujų („+ Blokas po šiuo“), pvz. „Tekstas (su nuotrauka ar be)“ - vieta atskiram tekstui „Apie klubą“.
- Kiekvienam blokui galima įrašyti „Meniu“ pavadinimą - jis atsiranda viršutiniame meniu (taip pakeičiama, kur veda „Apie klubą“, „Kontaktai“ ir pan.).
- Sąrašai: vertybės, instruktoriai (galima įkelti nuotrauką vietoj raidžių), kontaktai, „Apie“ nuotraukų skaidrės, partneriai - pridėti, pašalinti, perstatyti.
- „Kur mus rasti“ → „Kontaktai“, šalia - treniruočių vietų adresai (iš grupių; galima išjungti).
- „Registruokis“ → „Išbandyti“, formos mygtukas - „Siųsti“, grupių mygtukai - „Išbandyti“. Prisijungusiems nariams forma ir „2 treniruotės nemokamai“ nerodomi.
- Apačioje - partnerių logotipai (LTKF, Sanrei, Vilniaus karate mokykla, Karate akademija, Takami, Budo Cup Open).
- Grupių skaidrėje „✎ Kaina ir tvarkaraštis“ - kainą ir laikus pakeiti čia pat (išsaugoma iš karto).
- „↶ Ankstesnė versija“ grąžina puslapį, koks buvo prieš paskutinį išsaugojimą.

Tvarkaraštis:
- Treneriams → „Tvarkaraštis“: visų grupių laikai ir kainos vienoje vietoje. Laiką galima pakeisti (anksčiau tik ištrinti ir sukurti iš naujo), kopijuoti į kitą dieną; išsaugoma iš karto (✓).
- Įspėja, jei grupės laikai tą pačią dieną persidengia. Grupės puslapyje - tas pats redaktorius.
- Tvarkaraštį ir kainas keičia tik adminas (treneriai mato).

Pamokos:
- Pamokos skyriais (išskleidžiami): pvz. „Egzaminams“ (viduje aplankai „9 kyu“, „8 kyu“...), „Teisėjams“ (mato tik su žyma), „Fizinis pasiruošimas“.
- Aplankui galima nurodyti diržą. Narys iš karto mato savo diržo pamokas; viršuje gali perjungti į kitą (žemesnį ar kitą) diržą. Tėvai mato kiekvieno vaiko diržą.
- „📨 Treneris atsiuntė“ - pamokų puslapio viršuje pamokos, kurias treneris atsiuntė (ir aukštesnio diržo).
- Treneriui: „✎ Tvarkyti“ pamokų puslapyje - skyrius, aplankus ir pamokas tempi ⠿, aplanko pavadinimą, ženkliuką, diržą ir kam skirta keiti čia pat, kuri naujus.
- Pamoka redaguojama pačiame pamokos puslapyje („✎ Keisti“), tarp blokų „+“ - įterpti naują bloką. Medis (Treneriams → Pamokos) lieka.
- „👁 Žiūrėti kaip narys“: treneris pasirenka narį ir mato pamokas taip, kaip jis.
- Nauji ženkliukai aplankams: spalvoti diržai ir teisėjo vėliavėlės (balta ir raudona).

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
