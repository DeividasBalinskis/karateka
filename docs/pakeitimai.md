# Pakeitimai pagal versijas

Versija rodoma šalia logotipo (tik test svetainėje ir kompiuteryje, oficialioje - ne).
Numeris saugomas faile `public_html/app/VERSION`. Kiekvienas įkėlimas į test = nauja versija.
Jei svetainėje matai senesnį numerį nei čia viršuje - naujausia versija dar neįkelta.

## v1.31 - 2026-11-16 (žaidimas „Mano dojo“: herojai, gyvūnėliai, 4 žaidimai; Admin režimas; Denio atsiliepimai #18-#29)
**Duomenų bazė:** `db/035_zaidimas.sql` - Valdymas → Šiandien → „Atnaujinkite duomenų bazę“.
Zip'o turinį išpakuokite **tiesiai į svetainės aplanką** (ten, kur `index.php`).

**🏯 Mano dojo** (paskyros pradžia vietoj „Apžvalga“)
- Kiekvienas vaikas turi **herojų**: 10 gyvūnų ir žmogus, keičiama lytis ir spalvos. Diržas - tikras iš nario duomenų, lygis - iš tikro XP.
  **Mistiniai**: Raudonoji panda (8 lygis), Kitsunė (14), Jetis (20); Tengu, Vienaragis, Feniksas - reti, iš dovanų dėžių.
- Viduryje - **herojaus pasaulis**: aplinka, namas, dekoracijos (tempiamos), laksto gyvūnėliai, naktį - žvaigždės, sezono dalelės.
  Apačioje XP juosta, dešinėje savybės (Jėga, Vikrumas, Nuojauta, Ištvermė) - 3 taškai už lygį paskirstomi patiems.
- Išskleidžiama: **Apranga**, **Inventorius** (144 daiktai: ginklai, kimono, diržo puošmenos, šukuosenos, aksesuarai, auros, spalvos, aplinkos,
  namai, dekoracijos), **Gyvūnėliai** (6 pradiniai + 4 iš kiaušinių, maitinimas, į kovą), **Namai ir aplinka**, **📖 Kolekcija** (rinkiniai X / Y).
- **🎁 Dovanų dėžės** (be pinigų): už kiekvieną lygį, renginį, 3 pergales per dieną, S įvertinimą ritme. Atsitiktinis daiktas, retumas spalva.
- **🧪 Testavimas (adminui)**: savo herojui nustatyti lygį, diržą, gauti visus daiktus, +5 dėžės, pradėti iš naujo.

**🎮 Žaidimai** (paskyroje skirtukas „Žaidimai“)
- **⚔️ Karate kova** (kaip combats.ru): abu slapta renkasi techniką (jodan / chudan / gedan) ir gynybą; teisingas blokas - kontrataka,
  Waza-ari, IPPON. **🏆 Jėgos** (savybės, daiktai, gyvūnėlis) ir **🎯 Gudrumo** (visi lygūs + 3 kortos) režimai, botai 3 sunkumų, reitingai.
- **⚡ Greita kova** - realiu laiku: matai, kur smūgiuos, blokuok laiku; tobulas blokas = kontrataka, nutraukimai, kombo, reakcija ms.
- **🔴 Keturi iš eilės** su gyvūnėlių kortomis (💣 🧱 🔄 ⏩) - su draugu ar botu.
- **🥁 Kata ritmas** - būgnų ritmo žaidimas, 3 katos, savaitės top.
- Kviesti galima bet kurį klubo žaidėją (kvietimas iššoka bet kuriame puslapyje). Rašyti vieni kitiems negalima - tik reakcijos 👏🔥 kovos metu.
  Kitų dojo galima žiūrėti.

**🛠 Admin režimas**
- Adminai ir treneriai svetainę mato **kaip narys** (Mano dojo, žaidimai). Dešinėje viršuje jungiklis **„Admin“** - įjungus atsiranda
  „Valdymas“ ir visi redagavimo mygtukai. Paskyroje atskiri langai: **🛠 Valdymas** (adminui) ir **👪 Tėvams** (tėvams).

**Denio atsiliepimai #18-#29** (v1.30 pataisos)
- Taikos tvarkaraštis kompaktiškesnis, 4 tatamiai, grupes galima tempti ir telefone; pradėti grupę dabar / perkelti iš grupės.
- Prisegamas langas - keičiamo dydžio, rodo pilną tinklą, galima pažymėti nugalėtoją. Kumite +4 taškai.
- Atsiliepimų langas fiksuoto pločio, rodomi 2 paskutiniai; mygtukas - tik treneriams. Naujienų reakcijos nebeuždengiamos.
- Vienodi vaikų vardai rodomi su pavardės raide; pamokas galima ištrinti iš sąrašo ir peržiūros.
- Treniruotėje - „+ Lankytojas, kurio dar nėra sąraše“. Valdyme - „+ Nauja paskyra“ (ir be pokalbių lango).
- Antraštė ir skirtukai nusidažo pagal temą.

## v1.30 - 2026-11-15 (renginių registracija ir kalendorius, nauja gamta ir 3 naujos temos)
**Duomenų bazė:** `db/034_renginiu_registracija.sql` - Valdymas → Šiandien → „Atnaujinkite duomenų bazę“.

**Svarbu įkeliant:** zip'o turinį išpakuokite **tiesiai į svetainės aplanką** (ten, kur `index.php`), ne į naują `public_html` aplanką.
v1.29 buvo išpakuota į `test.karateka.lt/public_html/...`, todėl svetainė liko v1.27 - tą papildomą `public_html` aplanką galima ištrinti.

**Denio atsiliepimai #12-#17**
- **#12 📝 Registracija**: renginio formoje (4 žingsnis) - **„Registracijos nuoroda“** (pvz. Google Forms). Prie renginio ir jo puslapyje atsiranda
  mygtukas **„📝 Registracija →“**. Jei nuoroda jau įrašyta aprašyme (forms.gle, Google / Microsoft Forms) - mygtukas atsiranda pats.
  Praėjus renginiui ar registracijos terminui - mygtukas dingsta.
- **#13 📅 Renginių kalendorius**: Renginiai → **„☰ Sąrašas | 📅 Kalendorius“** - mėnesio tinklelis, visi renginiai savo dienose (ir kelių dienų),
  ← → kiti mėnesiai, filtras pagal tipą veikia. **„📲 Į telefono kalendorių“** - visi vieši renginiai atsiranda telefono / Google kalendoriuje
  ir atsinaujina patys.
- **#14** Taikoje „mano tatamis“ jau yra nuo v1.28 (Denis matė v1.27): Live tvarkaraštyje **„▶ Start: prie kurio tatamio dirbate?“** - pasirinkus
  šiame kompiuteryje rodomas tik tas tatamis ir jo rungtys.
- **#15-#17 🎨 Temos**: pasirinkimas karateka.lt viršuje (šalia LT | EN | RU), Taikos pradžioje ir varžybose - nuo v1.29.
  **Gamta perpiešta tikroviškiau** (eglių miškas rūke saulėtekyje, ežeras su atspindžiu) ir **3 naujos temos**: 🏜️ Kopos (saulėlydis prie jūros),
  🥋 Dojo (shoji sienos, tatami), 🌌 Pašvaistė (tamsi, šiaurės pašvaistė virš miško). Iš viso 13.

## v1.29 - 2026-11-15 (Taika telefone, vardų tempimas, prisegamas langelis, temos visur, EN / RU; visų langų peržiūra)
**Duomenų bazė:** naujų pakeitimų nėra (jei v1.28 dar neįkelta - Valdymas → Šiandien → „Atnaujinkite duomenų bazę“).

**Taika - atsiliepimai (💡)**
- **Telefone** viršus kompaktiškas (ženkliukai vienoje eilutėje), dalyviai - kortelėmis, niekas neišeina už ekrano.
- „**⋯**“ prie varžybų atsidaro **kortelės viduje** - informacija nebedingsta.

**Denio atsiliepimai #5-#11 (💡 test svetainėje)**
- **#5 Nario ištrynimas**: paspaudus vardą (Nariai) - langelyje **„🗑 Ištrinti“** (adminui; su patvirtinimu). Tas pats, kas kortelės apačioje.
- **#6 „Pašalinti tatamį“** - matomas tik pagrindiniam adminui (treneriai / sekretoriatas jo nemato).
- **#7 🎓 Diplomai**: prie kiekvienos baigtos kategorijos „Vietos“ - **„🎓 Diplomai“**; Rezultatai → **„🎓 Diplomai“** - visi iš karto.
  Ten pat **įkeliamas jūsų Excel šablonas** (diplomas su dizainu): į tuščias vietas įrašykite `{vardas}` `{vieta}` `{vietaR}` (I, II, III) `{kategorija}`
  `{klubas}` `{varzybos}` `{data}` `{rungtis}` - atsisiųstame Excel **kiekvienas diplomas atskirame lape** (su paveikslėliais), spausdinkite visus.
  Be šablono - gražūs paprasti diplomai spausdinimui (A4 gulsčias).
- **#8** Kategorijų struktūroje tuščia metų riba rodoma **„+“** (paspaudus įrašomi metai; tuščia - „ir jaunesni / ir vyresni“).
- **#9 📋 Šablonas** (Kategorijos → Struktūra): išsaugokite vienos rungties amžiaus kategorijas (pagal amžių - tinka ir kitais metais) ir
  **viena paspauda sukurkite tuos pačius blokus Kata, Kumite, Fukugo, Kihon**; jau esantys dalyviai persikelia į tinkamus blokus.
- **#10** Struktūroje prie kiekvienos kategorijos **„→“** - atidaro ją; ten **„← Grįžti į kategorijas“** - atgal į tą pačią vietą (paryškinta).
- **#11 🤼 Tinklai** (naujas skirtukas tarp Kategorijų ir Live): kairėje **amžiaus grupės**, paspaudus - visos tos grupės rungtys:
  **kas su kuo kovoja**, kas laimėjo, vietos. **Žiūrovams tas pats** - transliacijos nuorodoje skiltis „🤼 Tinklai“.

**Taika - tempimas ir prisegimas**
- Lentelėje **vardą galima nutempti ant kito** - apsikeis vietomis (pele ir pirštu). Mygtukas vadinasi **„⇄ Keisti vietas“**
  (paspaudus - galima ir dviem paspaudimais). Balų sistemoje pasirodymo eilė keičiama tempiant už „⋮⋮“.
- **📌 Prisegti**: tvarkaraščio kortelę nutempkite už tatamių (ar spauskite 📌) - kategorija prisisega mažu langeliu ekrane ir
  **matosi visuose skirtukuose** (kas kovoja dabar, kas toliau, kiek liko). Langelį galima tempti, sumažinti, uždaryti, atidaryti visą.

**Temos ir kalbos**
- **🎨 Temos** - vienas sąrašas karateka.lt ir Taikai: Šviesi, Tamsi, Gamta, Kalnai, Jūra, Sakura, Ruduo, Žiema, Kosmosas, Vulkanas
  (su peizažais). Pasirinkimas - karateka.lt viršuje (adminui), Taikos pradžioje ir varžybose; viešieji Taikos puslapiai seka temą.
- **Taika ir LTKF - LT | EN | RU** visuose puslapiuose (ir registracija, transliacija, apdovanojimų planšetė).

**Visų langų peržiūra (svoris, vieta, aiškumas, dublikatai)**
- Atidarytos grupės viršus: **„← Ankstesnė | Kita →“** - viena pora (nebe du raudoni „toliau“ į skirtingas grupes); raudonas tik „Sekanti grupė“,
  kai grupė baigta. „Atkabinti“ → **„Naujame lange ↗“**.
- Įrankių juosta vienoje eilutėje: „⇄ Keisti vietas · Burti iš naujo · ↶ Atšaukti · 🔓 Užrakinti … 🖨 Spausdinti · **↺ Atstatyti**“.
  „Išvalyti rezultatus“ darė tą patį, ką „Atstatyti → Visą rungtį“ - **paliktas vienas** (meniu „↺ Atstatyti“ vietoj iškrentančio sąrašo).
  „Burti iš naujo“ nebe raudonas (tai pavojingas veiksmas, ne pagrindinis).
- Nebesikartoja: dalyvių skaičius / sistema / kovos (buvo 3 vietose), „Grupė baigta“ + „Užbaigta“. Po lentele nebėra tuščio ploto.
- Tvarkaraščio kortelėse ✎ 📌 ↑ ↓ → rodomi užvedus pelę (planšetėje - visada) - sąrašas švaresnis. „Pašalinti tatamį“ - nebe raudonas mygtukas
  kiekviename stulpelyje, o tylus užrašas.
- „Prie kurio tatamio dirbate?“ - viena eilutė, ne didelis blokas.
- 💡 Taikoje - mažas apskritimas (užrašas išsiskleidžia užvedus pelę), nebemirga ir nebeuždengia lentelių.
- Dalyviai: filtrai kairėje, **„Naujas: vardas · klubas · + Pridėti“** atskiroje grupėje; tuščias „Užsiregistravę per nuorodą“ - viena eilutė;
  lentelė skaitoma kaip duomenys (laukelių rėmeliai matosi užvedus pelę).
- Rezultatai: kategorijos pavadinimas nebelaužomas į 3 eilutes, „🏅 Siųsti“ - vėl geltonas mygtukas.
- Telefone: tvarkaraščio juosta be paaiškinimo, mygtukai vienoje eilutėje.
- karateka.lt: kalbos jungiklis antraštėje buvo išsitempęs (200 px) - dabar kompaktiškas; „✎ Redaguoti puslapį“ nebeslepia pokalbio mygtuko;
  Nariai → „⬇ Kontaktai (Excel)“ šalia „+ Naujas narys“, grupė ir būsena pritaikomos iškart (be „Rodyti“).
- Taikos pradžia: „Išorinė varžybų sistema“ - tokia pati eilutė kaip „👀 Duoti išbandyti“.
- Trenerių iššokantys pranešimai (registracija į egzaminą ir pan.) **po 12 s susitraukia į „🔔 N“** - nebeuždengia lentelių; kituose puslapiuose
  jau matyti rodomi tik kaip „🔔 N“ (paspaudus - išsiskleidžia). Pataisyta: rodomi daugiausia 3 (anksčiau „dar N“ nepaslėpdavo kitų).

## v1.28 - 2026-11-15 (Denio pastabos: tatamio darbas, apdovanojimai, katos balai, svetainė; bandymo nuoroda draugui)
**Duomenų bazė:** `db/033_apdovanojimai.sql` - Valdymas → Šiandien → „Atnaujinkite duomenų bazę“.

**Taika - varžybų diena**
- **Mano tatamis**: Live tvarkaraštyje viršuje mygtukai „1 TATAMIS | 2 TATAMIS | … | Visi“. Pirmą kartą paklausiama, prie kurio dirbate -
  šiame kompiuteryje (planšetėje) rodomas tik jis, kitą pažiūrėti - vienu paspaudimu. „▶ Dabar: …“ atidaro dabartinę grupę.
- Atidarius grupę - **pačioje apačioje visos to tatamio grupės**: laikas, kiek kovų liko, kas toliau, būsena. Paspaudus - atsidaro.
- Baigus grupę iššoka: **„🏅 Siųsti apdovanojimams“** ir **„Sekanti grupė: … →“**.
- Būsenos prie grupių (sekretoriatui ir žiūrovams): **Laukiama → Vyksta → Užbaigta → Užbaigta · apdovanojama → Užbaigta ir apdovanota**.
- **Apdovanojimų planšetė** („📡 Transliacija ir registracija“ → „🏅 Apdovanojimų planšetė“, slapta nuoroda): amžiaus grupės (tie patys metai -
  ir kata, ir kumite) **pilkos, kol kuri nors rungtis vyksta ar neišsiųsta, žalios - kai visos išsiųstos**. Matosi prizininkai (pilni vardai),
  naujai žalia grupė pyptelėja. Apdovanojus - „✓ Apdovanota - užbaigti“.
- Telefone tatamio ekranas **nebešokinėja į viršų**; viršuje - kas vyksta dabar, apačioje - grupių sąrašas.

**Taika - katos balai ir kumite**
- Balai rašomi be taško: **75 → 7.5** (taškas įdedamas pats). Paspaudus kitą langelį, žymeklis lieka ten.
- **Kata kiekvienam dalyviui kiekviename rate** - pasirinkti iš sąrašo (Heian, Tekki, Bassai, Kanku, Enpi, Jion…) ar įrašyti; finale ta pati kata
  kaip pusfinalyje pažymima raudonai.
- Balų sistemoje rato balas - **teisėjų balų vidurkis** (be aukščiausio ir žemiausio); galima perjungti į sumą. 2 ratai: **„Pusfinalis“ ir „Finalas“,
  finalininkų vietos - pusfinalio ir finalo vidurkių suma** (ne tik finalo).
- Kumite kovos skydelyje - **laiko šablonai**: Kova (kategorijos laikas), Jiyu ippon 0:10, Ko-Go 0:10, Jiyu pratęsimas 1:30.
- Kategorijų rikiavimas mygtukais (Kaip yra | Metai | Diržas | Dalyviai) ir **didėjančia / mažėjančia** tvarka.
- Klubai → **„⬇ Kontaktai (Excel)“** - visų klubų atsakingi asmenys, telefonai, el. paštai, dalyvių skaičius.

**Taika - bandymo nuoroda (be paskyros)**
- Taikos pradžioje (adminui) **„👀 Duoti išbandyti“** → nuoroda. Draugas įrašo vardą ir gali daryti viską, ką adminas, bet **tik bandymo varžybose**:
  išgalvoti dalyviai, tikrų klubo narių ir jų duomenų nemato, karateka.lt valdymo neatidaro. Atsiliepimus palieka per 💡 (matosi su jo vardu).
  „Išjungti“ - prieiga baigiasi.

**karateka.lt**
- Instruktoriai: **„Klubo treneriai“** (Denis, Lukas) ir **„Pagalbiniai instruktoriai“** atskirai. Redaguojant - kortelėje „Pagalbinis instruktorius“.
- „Kur mus rasti“ → **„Kontaktai“**; treniruočių vietos - nuorodos į žemėlapį.
- Nario pokalbis (Valdymas → Žinutės) **atsinaujina pats** - naujos žinutės atsiranda be perkrovimo, atsakymas išsiunčiamas be perkrovimo.
- Kai kas nors užsiregistruoja į egzaminą - **iššoka pranešimas** (apačioje dešinėje) ir treneriams, ne tik adminui.
- Nariai → **„⬇ Kontaktai (Excel)“** (nario ir tėvų telefonai, el. paštai); egzamino protokolas - tikras Excel (.xlsx) failas.
- Kalba - **LT | EN | RU visada matomi** viršuje (grįžti į lietuvių - vienu paspaudimu).
- Privatumo politikoje: „Varžybų sistemą prižiūri administratorius Deividas Balinskis“.

## v1.27 - 2026-11-15 (Atsiliepimai su nuotraukomis visose svetainėse + Claude juos skaito pats)
**Duomenų bazė:** `db/032_atsiliepimai_nuotraukos.sql` - Valdymas → Šiandien → „Atnaujinkite duomenų bazę“.

- **💡 Atsiliepimas** - ryškus oranžinis mygtukas apačioje kairėje **visose svetainėse: karateka.lt, Taika ir LTKF**.
- Lange - didelis laukelis: **rašai, ir išsaugoma pati** (be mygtuko). **Ctrl+V** - įklijuoja ekrano nuotrauką (galima daug, iki 40),
  taip pat galima **nutempti** paveikslėlius ar paspausti „📎 Nuotraukos“. Galima pradėti ir nuo nuotraukos, tekstą parašyti po to.
- **Enter** - išsaugota ir iš karto naujas atsiliepimas (Shift+Enter - nauja eilutė). Taip galima vieną po kito, nesustojant.
- Apačioje - **ankstesni atsiliepimai**: pavadinimas (pirma eilutė), 🖼 nuotraukų skaičius, būsena; paspaudus - atsidaro taisyti
  (tekstą, nuotraukas pridėti ar pašalinti, 🗑 ištrinti).
- Kartu išsaugoma, kurioje svetainėje ir puslapyje parašyta, ekrano dydis - kad būtų aišku, ką taisyti.
- Valdymas → **Atsiliepimai**: nuotraukos prie kiekvieno atsiliepimo (paspaudus - didelė), svetainės žymė.
  Nuotraukas mato tik autorius, treneriai ir adminai.
- **Claude prieiga** (Valdymas → Atsiliepimai, apačioje): „Sukurti raktą Claude“ - Claude pats skaito naujus atsiliepimus su nuotraukomis,
  daro pakeitimus, pažymi „Pataisyta“ ir praneša, kai yra ką peržiūrėti. Nebereikia persiųsti per Messenger.

## v1.26 - 2026-11-15 (Egzamino protokolas internete - pagal Denio failą)
**Duomenų bazė:** `db/031_egzamino_protokolas.sql` - Valdymas → Šiandien → „Atnaujinkite duomenų bazę“.

- Prie egzamino renginio (Renginiai → „📋 Protokolas“, ar „Rezultatai“ → „📋 Egzamino protokolas“) - **protokolas kaip popierinis**:
  „EGZAMINO PROTOKOLAS“, organizacija (numatyta - LIETUVOS TRADICINIO KARATE ASOCIACIJA, galima pakeisti), „Egzaminas ___ KYU“, data ir vieta,
  lentelė **Nr., vardas pavardė, gimimo data, kyu, Kihon, Kata, Kumite, Testas, seminarai ir stovyklos, pastabos, Išlaikė / Neišlaikė**,
  apačioje egzaminatorius, licencijos Nr. ir parašas.
- **„+ Įtraukti užsiregistravusius“** - užpildo pats: vardas, gimimo data, laikomas kyu, testo rezultatas, seminarai ir stovyklos per paskutinius 12 mėn.
  „+ Kitas dalyvis“ - pvz. iš kito klubo.
- Kiekvienas dalyvis gauna **unikalų numerį** metai-mėnuo-diena-eilė, pvz. **26-11-11-01**. **Neatvykusį ištrynus (✕), jo numeris daugiau niekam nepriskiriamas**
  (ištrintus galima grąžinti).
- Egzaminatorius pildo balus ar komentarus ir spaudžia **„Išlaikė“ / „Neišlaikė“** - viskas išsaugoma iš karto (Enter - į kitą laukelį).
- **🖨 Spausdinti** (gulsčias A4, kaip popierinis), **⬇ Excel**, **✉ Siųsti el. paštu** (Excel priede).
- Adminui: **„Išlaikė“ → renginio rezultatai ir XP**. Organizacija ir egzaminatorius įrašomi ir kitame protokole.

## v1.25 - 2026-11-15 (Taikos pertvarka, 5-6 etapai: apsauga nuo klaidų, dizaino temos, meniu)
**Duomenų bazė:** `db/030_varzybu_istorija.sql` - Valdymas → Šiandien → „Atnaujinkite duomenų bazę“.

Apsauga nuo klaidų:
- **⟲ Istorija** (viršuje): serveryje kas minutę daroma kopija, kai kas nors keičiama. Mygtukai **„Kaip prieš 5 min.“, „prieš 15 min.“,
  „prieš 1 val.“** arba bet kuri kopija iš sąrašo (laikas, kiek kategorijų, kiek baigta). Prieš atkuriant dabartinė būsena irgi išsaugoma
  („Prieš atkūrimą“) - atkūrimą galima atšaukti. Vienoms varžyboms - iki 400 kopijų.
- Rungties valdyme **„Atstatyti…“**: visą rungtį arba **nuo pasirinkto rato** (pvz. nuo pusfinalio) ar tik paguodą / kovą dėl 3 vietos.
  Kaip anksčiau - ir „Atšaukti“ (paskutinis veiksmas).

Išvaizda (bendra visoms varžyboms):
- **Dizaino temos** (viršuje): ☀️ Šviesi, 🌙 Tamsi, 🪐 Kosmosas, 🌿 Gamta, 🏔️ Kalnai, 🌋 Vulkanas. Fonas keičiasi, o lentelės ir kortelės lieka
  nepermatomos ir aiškios; kosmose ir vulkane vykstančios rungties kraštas švelniai keičia spalvą.
- **⚙ Meniu** (skirtukų eilutės dešinėje): skirtukus galima **pervadinti** ir **sukeisti vietomis**; „Numatytasis“ - grąžina kaip buvo.
- Laikas prie rungties - visada 24 val. formatu (pvz. 13:45).

## v1.24 - 2026-11-15 (Taikos pertvarka, 3-4 etapai: Live tvarkaraštis ir paprastas kovos vaizdas)
Duomenų bazės keisti nereikia.

Live tvarkaraštis:
- **Laikas** prie rungties - paspaudus galima pakeisti: rungtis prasidės ne anksčiau (geltonas laikas = nustatytas ranka; ištrynus - vėl skaičiuojasi pats).
  Pakeisti laikai matosi ir ekranuose bei žiūrovų nuorodoje.
- **✎** - pervadinti rungtį tiesiai tvarkaraštyje; **👥 N** - parodo dalyvius.
- Atidarius rungtį - **„Atkabinti ↗“**: ji atsidaro **atskirame lange** (pvz. kitame ekrane), tvarkaraštyje tampa **pilka**;
  uždarius langą - grįžta. Pakeitimai abiejuose languose išsaugomi ir matosi kitame.

Kovos vaizdas (rungties valdyme, viršuje „Lentelė / Paprastas“):
- **Lentelė** - kaip anksčiau, su linijomis.
- **Paprastas** (kaip Kihapp): **kairėje AKA, dešinėje SHIRO** - vardas, klubas, gimimo metai, **diržo spalva ir kyu**;
  dideli mygtukai „Laimėjo AKA / SHIRO“ (vėliavėlių kovose - kiek vėliavėlių), kumite - „▶ Kovos skydelis“;
  apačioje - **kas ruošiasi**. Balų sistemoje - vienas dalyvis ir teisėjų balai. Baigus - prizininkai ir „Toliau: …“.

## v1.23 - 2026-11-15 (Taikos pertvarka, 2 etapas: kategorijų struktūra kaip Kihapp)
Duomenų bazės keisti nereikia.

- **Kategorijos → „▦ Struktūra“** (numatytas vaizdas): visos rungtys stulpeliais (Kata, Kumite, Fukugo...), kiekviena kategorija - vienas blokas:
  **gimę (jauniausi–vyriausi), lytis, kyu nuo–iki, dalyvių skaičius**. Mažiau teksto - pilnas pavadinimas rodomas tik jei pakeistas ranka.
  - Metus, lytį ir kyu **keiskite tiesiai bloke** - pavadinimas atsinaujina pats.
  - **⋮⋮ tempimas** - blokas perkeliamas aukščiau / žemiau ar į kitą rungtį.
  - Paspaudus **dalyvių skaičių** - dalyviai; juos galima **tempti į kitą bloką** (su Ctrl - nukopijuoti). Netinkantys pagal kriterijus - geltoni.
  - Stulpelio mygtukai: **„+“** naujas blokas, **„⟳“** netinkančius perkelti į tinkamus, **„⧉ →“** nukopijuoti visą struktūrą į kitą rungtį
    (pvz. Kata → Kumite; tada pakeisti tik tai, kas skiriasi). „+ Rungtis…“ - nauja rungtis.
  - Viršuje: **paieška** (kategorija ar dalyvis), **lytis** (Visi / M / F / Mix), **„Tik su problemomis“** (mažiau nei 2 dalyviai ar netinkantys),
    **rikiavimas** (kaip sudėliota, pagal metus, diržą, dalyvių skaičių); suvestinė - kiek kategorijų, įrašų, problemų ir dalyvių be kategorijos.
  - Raudonas kraštas - per mažai dalyvių, geltonas - yra netinkančių, žalsvas fonas - kategorija jau prasidėjo.
- **„✎ Viena kategorija“** - kaip anksčiau: sistema, kova, dalyviai su sąrašu dešinėje; ✎ bloke atidaro būtent tą kategoriją.

## v1.22 - 2026-11-15 (Taikos pertvarka, 1 etapas: pagrindinis meniu ir Live tvarkaraštis)
**Duomenų bazė:** `db/029_testo_svecio_el_pastas.sql` - Valdymas → Šiandien → „Atnaujinkite duomenų bazę“.

- **Taikos pradžia** (Valdymas → Varžybos): visos varžybos kortelėmis - „Vyksta ir artimiausios“ viršuje, „Praėjusios“ žemiau;
  „+ Naujos varžybos“, test varžybos; apačioje - **partneriai** (tie patys, kaip karateka.lt).
- **Varžybų viduje** - paspaudus pavadinimą viršuje (▾) galima pereiti į kitas varžybas ar į visų sąrašą.
- **Meniu iš kairės:** Klubai → Komandos → Dalyviai → Kategorijos (organizacinė pusė) | **Live tvarkaraštis** → Rezultatai.
- **Live tvarkaraštis** (sujungti „Lentelės“ ir „Tvarkaraštis“):
  - tatamių lenta: kiekvienoje - rungtys su laikais; vykstanti pažymėta **DABAR**, kita - „toliau“, baigtos - pritemdytos ir sutrauktos;
  - varžybų dieną prie tatamio: **„laiku“, „vėluoja ~X min“ (raudonai) ar „lenkia X min“** - pagal tai, kiek kovų liko;
  - paspaudus rungtį atsidaro jos **valdymas** (lentelė, kovos, balai), viršuje - to tatamio rungčių juostelė,
    **„← Ankstesnė“ ir „Toliau: … →“**, „← Visas tvarkaraštis“; tatamių ir kovų trukmės nustatymai - po „⚙“.
  - Kategorijose mygtukas „Valdyti →“ atidaro rungtį Live tvarkaraštyje.
- **Teisėjų skaičius** - bet koks (įrašomas skaičius, iki 15).
- **Testas be paskyros:** privalomas tik vardas ir pavardė; klubas ir **el. paštas** nebūtini - įrašius el. paštą, rezultatas
  (ir klaidos su teisingais atsakymais) išsiunčiamas laišku. Treneris mato el. paštą prie rezultato.

## v1.21 - 2026-11-15 (Denio pastabos lapkričio 11-15: testai, klausimai iš ChatGPT, klubų kontaktai, reglamentas prie renginio)
**Duomenų bazė:** `db/028_testai_nuoroda_renginio_failas.sql` - įkėlus failus, Valdymas → Šiandien → „Atnaujinkite duomenų bazę“.

Varžybų sistema „Taika“:
- **Registracijos nuoroda - ir Karatekai**: klubų sąraše yra ir Karateka. Forma prasideda nuo **vieno dalyvio**,
  „+ Pridėti dar vieną dalyvį“ prideda po vieną, × - pašalina; telefone kiekvienas dalyvis - atskira kortelė „Dalyvis 1, 2…“.
- **Klubai → „☎ Kontaktai“**: atsakingas asmuo, telefonas, el. paštas, pastaba (Karatekos - iš LTKF sąrašo);
  žemiau - telefonai, kuriuos klubas nurodė registruodamasis per nuorodą.
- **Balai**: Enter - kitas teisėjas, po paskutinio - kito dalyvio pirmas teisėjas (Shift+Enter - atgal). Teisėjų: 3, 5, **6** arba 7.
- Klubų sąraše - tik LTKF klubai (Karateka, Sanrei, Takami, Vilniaus karate mokykla, Karate akademija); senieji pavyzdžiai be dalyvių išimti.

LTKF svetainė:
- **Klubai** - tik tie penki.
- **Teisėjai** - sąrašas iš karateka.lt: nariai su žyma „Teisėjas“ ir ar galioja teisėjų testas (vaikų - „Vardas P.“).

Testai:
- Pildant klausimą ir ką nors pamiršus - **niekas neišsitrina**: neleidžiama pateikti, kol trūksta (klausimas, 2 atsakymai, pažymėtas teisingas),
  o jei serveris atmeta - forma grįžta su tuo, kas įvesta.
- **Enter** ar **↓** - į kitą atsakymo laukelį (↑ - atgal).
- Atsakymų tvarka **kaskart kita** ir treniruotės režime.
- **Nuoroda be paskyros**: testo puslapyje „Leisti spręsti per nuorodą“ - bet kas įrašo vardą (ir klubą), sprendžia ir iš karto mato rezultatą
  su teisingais atsakymais. Treneris mato, kas sprendė ir kiek surinko (ištrinama po metų).
- **Klausimų įkėlimas** (pvz. 500 iš ChatGPT): „📥 Įkelti klausimus“ - įklijuoti tekstą ar pasirinkti failą (.txt, Excel CSV, .json) →
  peržiūra (matosi, kurie netinka) → „Įkelti“. Ten pat - paruoštas prašymas ChatGPT, kad klausimai būtų tinkamu formatu.
- **Keli klausimai iš karto**: varnelės kairėje ir „Ištrinti pažymėtus“.

Svetainė:
- **Renginio priedas** (pvz. reglamentas): renginio formoje - PDF, Word ar Excel iki 10 MB; renginio puslapyje ir sąraše - nuoroda atsisiųsti.
- **Vaikų tvarkaraštis telefone**: iš karto rodoma pirmos vietos tvarkaraštis, paspaudus kitą - pasikeičia ir ekranas nuslenka iki jo;
  vietoje „← kairėje“ telefone rašoma „↑ Paspauskite vietą viršuje“.

## v1.20 - 2026-11-10 (Denio ir Nikos pastabos: balų įrašymas, ekranas su grupėmis, grupės iš tvarkaraščio, registracija su sutikimais)
**Duomenų bazė:** `db/027_varzybu_registracijos_sutikimai.sql` - įkėlus failus, Valdymas → Šiandien → „Atnaujinkite duomenų bazę“.

Varžybų sistema „Taika“:
- **Kovos skydelis**: didelį balą galima **paspausti ir įrašyti skaičių** (ne tik +1 / +2 / +3 / −1); **laiką** irgi - paspaudus laikrodį
  įrašoma pvz. `1:30`, `90` (sekundės) arba `2` (minutės).
- **Ekranas (televizorius)**: kairėje - **visos to tatamio grupės** iš eilės, dabartinė **padidinta** ir pažymėta „dabar“;
  dešinėje - **gyvai kas su kuo kovoja** (balai, laikrodis), o be skydelio - dabartinė pora ir kas toliau.
- **Nuoroda žiūrovams**: prie kiekvieno tatamio - visų grupių sąrašas; paspaudus bet kurią grupę atsidaro jos kovos ir vietos (atsinaujina pačios).
- **Tvarkaraštis**: paspaudus grupės pavadinimą atsidaro tos grupės lentelė.
- **Pakeitus gimimo metus, lytį ar diržą** - žmogus pats perkeliamas į tinkamą tos pačios rungties grupę (jei ji dar neprasidėjo);
  parodoma, kas perkelta, arba kad tinkamos grupės nėra.
- Dalyviai, **nesutinkantys būti fotografuojami**, pažymėti „be nuotraukų“ (Dalyviuose ir grupės sąraše).

Registracija kitiems klubams (pagal Denį ir Niką):
- **Vienas klubo laukelis**: renkiesi iš sąrašo arba įrašai savo.
- **Kontaktinis tel. Nr.** (treneris ar vienas iš tėvų); prie kiekvieno vaiko iki 18 m. - **vieno iš tėvų tel. Nr.** (privalomas).
- Prie kiekvieno dalyvio - **sutinka / nesutinka** būti fotografuojamas ir filmuojamas.
- Privalomi varnelės: **sutikimas dėl duomenų naudojimo** ir **susipažinau su privatumo politika**; trumpas tekstas „Asmens duomenų apsauga“.
- Telefonai **ištrinami praėjus mėnesiui** po varžybų; privatumo politikoje - sakinys apie kitų klubų registraciją.

## v1.19 - 2026-11-08 (Denio pastabos: renginių lankomumas, XP, varžybų balai, laikrodis, ekranai, registracija)
**Duomenų bazė:** `db/026_lankomumas_xp_varzybos.sql`. Nuo šios versijos - **be phpMyAdmin**: įkėlus failus, Valdymas → Šiandien →
„Atnaujinkite duomenų bazę“ (arba Nustatymai → Atsarginės kopijos ir sistema → „Atnaujinti duomenų bazę“). Prieš tai pasidaro kopija.
Jei test svetainėje dar nebuvo 025 - mygtukas įkels ir jį.

Renginiai ir lankomumas:
- **Treniruotė**: šalia grupių - **renginiai** pasirinktą dieną (pvz. rinktinės treniruotė) ir „Neseniai vyko - pažymėkite, kas buvo“.
  Atidarius - renginio dalyviai su „Buvo / Nebuvo“, „✓ Visi dalyvaujantys buvo“, pastabos ir „Atėjo, nors nebuvo sąraše“.
  Pažymėjus „Buvo“ skiriamas renginio XP (seminaras, stovykla, kita, varžybų dalyvavimas); egzamino XP - kaip anksčiau, už „Išlaikė“.
- Bet kuri diena, ir praėjusi: „← Diena atgal“, data. Prie praėjusių renginių - mygtukas „✓ Kas buvo“; „Šiandien“ primena, kur dar nepažymėta.
- Be „atsitiktinių“ draudimų adminui: registracija gali baigtis ir renginio dieną; atšauktą treniruotę galima įrašyti ir praėjusiai dienai (nariams tada nepranešama).

XP (vietoj „taškų“):
- Naujas skirtukas **XP** (Valdymas): **„✨ Skirti XP“** - pažymite kelis vaikus ar visą grupę, kategoriją (pvz. „Pagalba treneriui“), XP ir komentarą;
  kategoriją galima sukurti čia pat. Kiekvienas gauna pranešimą paskyroje („+5 XP: Pagalba treneriui“).
- **„+XP“** prie kiekvieno vardo Treniruotės puslapyje; „✨ XP keliems“ - tos grupės ar renginio dalyviai jau pažymėti.
- Aiškiai: kas skiriama **automatiškai** (ir kada) ir ką **skiria treneris**. Nario kortelėje ir paskyroje - „XP“, ne „taškai“.

Varžybų sistema „Taika“ (pagal Denio sąrašą):
- **Rungtis → sistema** pati: Kata, Kumite, Kihon, Sumo - olimpinė, 2 trečios; Team Kata ir Team Kumite - olimpinė, 1 trečia (kova dėl 3 vietos);
  Fukugo - fukugo sistema; Enbu - balai. Rungtys papildytos **Sumo**.
- **Kovos skydelis** (▶ prie kovos): **laikrodis** (pradėti / sustabdyti tarpu, ±10 s, signalas pabaigoje), balai **+1 / +2 / +3 / −1** abiem pusėms,
  „Baigti kovą“ (laimi daugiau balų turintis) arba sprendimu „Laimėjo AKA / SHIRO“.
- **Komandinis kumite**: 3 kovos, po kiekvienos nugalėtojas neskelbiamas - kovoja kiti, balai sumuojasi; pasirenkama, kas kovoja; po 3 kovos - nugalėtoja komanda.
- **Fukugo**: finalas - kumite, pusfinalis - **Kitei** (vėliavėlės), toliau pakaitomis.
- **Sumo**: kas pirmas surenka nustatytą taškų skaičių (pvz. 3) - laimi ir keliauja toliau.
- **Komandinės katos finalas ir kova dėl bronzos**: Kata ir Pritaikymas balais, kraštiniai atmetami ir **lieka matomi perbraukti**.
- **Vėliavėlės** (kata, kihon, kitei, ratų sistema): ne „kas laimėjo“, o kiek vėliavėlių prieš kiek (pvz. 3:2).
- **📡 Transliacija**: tatamio **ekranas** (televizoriui ar antram monitoriui) - kova, balai, laikrodis, kas toliau;
  **nuoroda žiūrovams** telefone - kas vyksta ant kiekvieno tatamio, kas eina toliau, rezultatai. Vaikų vardai - „Vardas P.“.
- **Registracija kitiems klubams** per nuorodą (vardas, gimimo metai, lytis, diržas); „Dalyviuose“ - „Pridėti visus“.
- **Mišrios komandos**: komandą galima sudaryti iš skirtingų klubų žmonių.

## v1.18 - 2026-11-04 (varžybų sistema „Taika“, atsiliepimai, LTKF su tikrais duomenimis)
**Duomenų bazė:** importuoti `db/025_varzybos_atsiliepimai.sql` (galima importuoti ir antrą kartą).

Atsiliepimai:
- Visuose puslapiuose apačioje kairėje - mygtukas **„💡 Atsiliepimas“**: „Palikite komentarą - pataisysime“. Rašyti gali nariai, treneriai ir lankytojai;
  automatiškai įrašoma, kuriame puslapyje parašyta. Prisijungęs mato savo atsiliepimus ir atsakymus.
- Valdymas → **Atsiliepimai** (tik adminui): visi atsiliepimai, būsena (naujas / bus pataisyta / pataisyta / nedarysime), atsakymas autoriui (gauna pranešimą),
  „📋 Kopijuoti neišspręstus“ - visas sąrašas vienu paspaudimu. Naujų skaičius - raudonas burbuliukas ir eilutė „Šiandien“.
- Kam rodomas mygtukas - ten pat: visiems / tik prisijungusiems / niekam. Test svetainėje numatyta - visiems, oficialioje - prisijungusiems.

Varžybos (Valdymas → **Varžybos**, sistema pavadinta „Taika“; pavadinimą pakeisti - viena eilutė `app/tournaments.php`):
- Tikra varžybų sistema svetainėje (vietoj prototipo): **Dalyviai, Klubai, Komandos, Kategorijos, Lentelės, Tvarkaraštis, Rezultatai**. Viskas išsaugoma automatiškai.
- **+ Karateka nariai**: nariai pridedami iš sąrašo (su gimimo metais, lytimi ir diržu), grupės filtras. Kiti klubai - iš LTKF klubų sąrašo.
- **⚡ Automatiškai**: kategorijos pagal rungtį, lytį, amžių (gimimo metus) ir diržą; mažos sujungiamos su artimiausiomis (kaip Budo Cup).
- Kategorijoje paspaudus pasirenkama **rungtis** (kata, kumite, fukugo, kihon, enbu, komandinė) ir **sistema** kortelėmis:
  olimpinė + paguoda, olimpinė + kova dėl 3 vietos, olimpinė su 2 bronzomis, **ratų sistema** (visi su visais), **balai** (3/5/7 teisėjai, kraštinių atmetimas, 1 ar 2 ratai su finalu).
- **Kriterijai** (lytis, gimimo metai nuo-iki, diržas nuo-iki, svoris): „Pridėti tinkamus“, netinkantys pažymimi, pavadinimas sugeneruojamas.
- **Tempimas**: dalyviai iš dešinės į kategoriją, iš kategorijos į kitą (su Ctrl - nukopijuoti), atgal - pašalinti; lentelės pirmame rate dalyvius galima sukeisti tempiant.
- Kovose galima įrašyti **balus** (pvz. 3:1 ar vėliavėlės 2:1). Valdymas per visą ekraną.
- **🎲 Sukurti test varžybas**: Karateka nariai + kitų klubų išgalvoti dalyviai, komandos, kategorijos ir dalis įvykusių kovų. „🎲 Sužaisti kategoriją“ / „Užbaigti visas kovas“.
  **🗑 Ištrinti visas test varžybas** - tikros lieka. Test varžybų rezultatai į narių taškus neperkeliami.
- Tikros varžybos: „Rodyti nariams Renginiuose“ sukuria renginį; „🏅 Rezultatai → narių taškai“ - vietos ir dalyvavimas į narių istoriją ir taškus.
- Viešas rezultatų puslapis `varzybu-rezultatai.php`: klubų medaliai ir prizininkai; vaikų ir jaunių - vardas ir pavardės raidė. Vykstant atsinaujina kas minutę.
- Jei du žmonės redaguoja vienu metu - antrasis įspėjamas perkrauti (vienas kito neperrašo).

LTKF svetainė (test: `/ltkf/`):
- Karateka - tikri klubo duomenys (treneris, kontaktai, nuo 2017 m.) ir gyvai: aktyvių narių, grupių ir treniruočių vietų skaičius.
- Kalendoriuje ir rezultatuose - varžybos iš varžybų sistemos; pradžioje - „Naujausi rezultatai“ (klubų medaliai).
- **Partneriai** (tie patys, kaip karateka.lt apačioje, plius Karateka) - kiekvieno puslapio apačioje.
- Oficialesnis vaizdas: gilesnės spalvos, šešėliai, aiškesnės antraštės.

Karateka svetainė:
- Apačioje - nuorodos į **LTKF** ir **Varžybų rezultatus**; LTKF logotipas partneriuose veda į LTKF svetainę (oficialioje - kai `config.php` bus `ltkf_url`).

## v1.17 - 2026-11-04 (seni kompiuterio pavyzdžiai)
**Duomenų bazė:** nieko importuoti nereikia.

- Kompiuteryje po išvalymo galėjo likti seni pavyzdžiai, kurių senesnė pavyzdžių versija nepažymėjo
  (naujiena „Rudens sezonas prasidėjo!“, renginiai „Kyu egzaminas“, „Lietuvos karate taurė“, „Sakura Cup“ ir kt., pamoka „Tempimo pratimai vaikams“,
  nariai Lukas Vaitkus, Gabija Stankevičiūtė ir kt.). Dabar jie išsivalo patys, kai adminas atidaro „Šiandien“ (prieš tai - atsarginė kopija).
- Atpažįstami tik pagal tikslius pavadinimus, vietas, vardus ir gimimo datas ir tik ten, kur yra tų pavyzdžių vaikai - test ir tikroje svetainėje nieko nepaliečia.

## v1.16 - 2026-11-04 (Denio tekstų vertimai, Denio aprašymas)
**Duomenų bazė:** importuoti `db/024_vertimai.sql` (galima importuoti ir antrą kartą).

Vertimai:
- Nustatymai → **Vertimai (EN / RU)**: naujienos, renginiai, pamokos ir pagrindinis puslapis - lietuviškas tekstas ir laukeliai anglų bei rusų kalboms.
  Prie naujienos, renginio, pamokos ir pagrindinio puslapio redagavimo - mygtukas „🌐 Vertimai“.
- **Automatiškai ir nemokamai (DeepL API Free, 500 000 simbolių per mėn.):** įrašius raktą į `config.php` (`'translate' => ['key' => '...']`),
  išsaugojus naujieną, renginį, pamoką ar pagrindinį puslapį vertimai atsiranda patys. Denis gali juos pataisyti.
- Be rakto - vertimus galima įklijuoti ranka („📋 Kopijuoti tekstus vertimui“ → ChatGPT / Claude → įklijuoti). Kol vertimo nėra - rodoma lietuviškai.
- Pakeitus lietuvišką tekstą, senas vertimas nebetinka (rodoma lietuviškai, kol išversite iš naujo).
- Privatumo politikoje - DeepL kaip paslaugų teikėjas (tik viešas klubo tekstas).

Pagrindinis puslapis:
- Denio aprašymas instruktorių bloke (nuo 5 metų, 3 Dan, „Sanrei“, klubas nuo 2017 m.) - lietuvių, anglų ir rusų kalbomis. Denis gali pakeisti.

## v1.15 - 2026-11-04 (lietuvių, anglų ir rusų kalbos; LTKF ir varžybų juodraščiai)
**Duomenų bazė:** nieko importuoti nereikia.

Kalbos:
- Viršuje, šalia „Valdymas“ / „Mano paskyra“ - **LT ▾**: lietuvių, English, Русский. Pasirinkimas įsimenamas (slapukas „kalba“, 1 metai).
- Išversta visa sąsaja (ir trenerio / admino dalis): meniu, mygtukai, formos, pranešimai, datos, diržų spalvos, renginių tipai.
- Denio rašomas turinys (naujienos, renginių aprašymai, pamokos, grupių pavadinimai) kol kas rodomas taip, kaip parašytas.
  Pagrindinio puslapio numatyti tekstai išversti; jei Denis tekstą pakeičia - rodomas jo tekstas.
- Redaguojant pagrindinį puslapį visada rodoma lietuviškai (turinys saugomas lietuviškai).
- Vertimai - `app/lang/en.php` ir `ru.php`. `tests/checks.sh` praneša, kuriems naujiems tekstams vertimo dar nėra.

Pavadinimai:
- Trenerio mygtukas viršuje - **„Valdymas“** (vietoj „Paskyra“); nariams - „Mano paskyra“.

Juodraščiai (tik test svetainėje ir kompiuteryje):
- Nustatymai → **Projektai (juodraščiai)**: **LTKF svetainė** (`/ltkf/`) ir **Varžybų sistemos prototipas** (`/varzybos-prototipas/`).
  Viršuje juosta „← Atgal į Karateka“. Oficialioje svetainėje (env = live) šie puslapiai neatsidaro.
  Vėliau - atskiri subdomenai.

## v1.14 - 2026-11-04 (testavimas, peržiūra kaip tėvai ar vaikas, švari svetainė, reakcijos, komentarai)
**Duomenų bazė:** nieko importuoti nereikia.

Švari svetainė:
- Seni pavyzdiniai (demo) duomenys išvalomi **vieną kartą automatiškai**, kai adminas atidaro „Šiandien“ (prieš tai - atsarginė kopija):
  demo paskyros ir jų vaikai, demo renginiai, naujienos, pamokos, „TEST:“ pavyzdžiai, demo reakcijos, senas pavyzdinis video.
  Tikri žmonės ir tai, ką sukūrė ar pakeitė Denis, lieka. Egzaminų programa ir testai paliekami, jei tikri nariai juos jau naudojo.
  Kas išvalyta - Nustatymai → Testavimas („Seni pavyzdžiai išvalyti“).
- Kompiuteryje `tools\lokali-svetaine.ps1` sukuria švarią svetainę tik su admino paskyra.

Testavimas (Nustatymai → Testavimas, tik adminui):
- „Sukurti“: Test Parent (tėvai su 2 vaikais), Test Junior (15 m., savo paskyra), Test Mažylis (8 m.) ir pažymėti scenarijai:
  nauja registracija, kvietimai, komentarai, „Moku“, lankomumas ir taškai, trenerio pastaba ir užduotis, reakcija, atšauktas sutikimas.
- „Ištrinti visus testinius“ - tik testinės paskyros (@test.karateka.lt) su viskuo, ką jos sukūrė ar gavo.
- Viršuje, šalia „Paskyra“ - **👁 Peržiūra: Adminas / Tėvai / Vaikas**. Svetainė rodoma taip, kaip ją mato tėvai ar vaikas
  (galima komentuoti, reaguoti, atsakyti į kvietimą). Vaiko režime - diržo keitimas: keičiasi pamokų aplankai, programa, diržo spalva.
  Pamokų „Žiūrėti kaip narys“ nebėra - tą daro peržiūra.

Naujienos ir komentarai:
- Reakcijoms - visi emoji (ir diržų spalvos, teisėjo vėliavėlės), sugrupuoti. Admino „Reakcijų emoji“ pasirinkimo nebėra. Savo reakciją - paspaudus dar kartą nuimama.
- Savo komentarą ar žinutę galima ištrinti vietoje (prie renginio, naujienos ir pokalbyje).
- „Anonimiškai“ nebėra (Denis vis tiek matė, kas parašė): paviešinti klausimai visada rodomi be vardo.
- Atskiro pranešimo apie kiekvieną komentarą nebėra: nauji - pokalbio mygtuko skaičiuke ir viena eilute „Šiandien“.

Paskyra:
- Pranešimai ir darbai - tik raudonas skaičiukas ant „Paskyra“ / „Mano paskyra“ (be juostos per ekraną). Treneriui jame - ir patvirtinimai, rezultatai, žinutės.
- Nario apžvalgoje - diržo juostelė (diržo spalva su mazgu) ir kiek liko iki kito diržo pagal programą. Diržas rodomas vienoje vietoje.
- Naujos pastabos ir neatliktos užduotys - tik „Svarbu“ (pilnu tekstu, su video); „Trenerio pastabos“ - ankstesnės.
- Registracija į varžybas - nuoroda renginių kortelėje (ne juosta).
- Pamokose: Tekki Nidan ir Tekki Sandan - atskiri mokomieji video.
- Nustatymų puslapio kolonos - viename lygyje.

## v1.13 - 2026-11-03 (testinių duomenų ištrynimas, katos pamokose, Nustatymai atskirai, be dublių)
**Duomenų bazė:** nieko importuoti nereikia.

Testiniai duomenys:
- Nustatymai → „Atsarginės kopijos ir sistema“ → „Testiniai duomenys“ (tik adminas, rodoma tik kol jų yra):
  sąrašas, kas bus ištrinta (demo paskyros `@demo.karateka.lt`, jų vaikai, demo renginiai, naujienos, pamokos, egzaminų programa, testai, „TEST:“ pavyzdžiai)
  ir kas lieka („Lieka (tikri)“ - pačių užsiregistravę ir Denio pridėti). Varnelėmis galima palikti dalį (pvz. egzaminų programą).
- Prieš trinant automatiškai sukuriama atsarginė kopija. Išvalomas ir senas pavyzdinis video (ne apie karate) bei pavyzdinis instruktoriaus aprašymas.
- Nario kortelės apačioje (tik adminui) - „Ištrinti narį“: visam laikui, su lankomumu, taškais ir žinutėmis;
  tėvų ar paties nario paskyra, kurioje neliko narių, ištrinama irgi. Reikia, kai žmogus paprašo ištrinti duomenis.
- Pavyzdžiuose kompiuteryje senas pavyzdinis video pakeistas tikrais katų video.

Pamokos:
- Kiekvieno diržo aplanke (8 kyu ... 7 dan) - katos pamoka su mokomuoju YouTube video, kaip egzaminų programoje:
  8 kyu Heian Shodan, 7 kyu Heian Nidan ... 3 kyu Tekki Shodan, 2 kyu Bassai Dai, 1 kyu Kanku Dai, dan - po 1-2 katas.
  Narys mato ir kito diržo aplanką - t. y. katą kitam egzaminui. Pamokas galima keisti ar ištrinti kaip įprasta.

Treneriams:
- „Šiandien“ ir „Nustatymai“ - atskiri puslapiai. „Šiandien“: kas reikia padaryti, šiandienos treniruotės, artimiausi renginiai
  ir greiti mygtukai „+ Naujiena“, „+ Renginys“, „+ Narys“. „Nustatymai“: klubo nustatymai, egzaminų programa, sistema, mano paskyra.
- Skirtukų eilutė - per vidurį.
- Be dublių - kiekvienas dalykas vienoje vietoje: kainos ir tvarkaraštis - tik „Tvarkaraštis ir kainos“ (iš pagrindinio puslapio ir grupės - nuoroda ten),
  žinutės - tik pokalbio lange, egzaminų programa - tik Nustatymuose, nauja pamoka - aplanke „Pridėti“.

Svetainė:
- Instruktoriaus langas atsidaro tik jei įrašytas aprašymas ar pareigos (be „netrukus“ teksto).
- Bandomosios treniruotės formoje - nuoroda į privatumo politiką.
- Privatumo politika papildyta: komentarai ir klausimai prie renginių / naujienų, lygiai, prisijungimas su kodu ir „patikimo įrenginio“ slapukas.

## v1.12 - 2026-11-03 (meniu ir skirtukai be dublių, dan aplankai)
**Duomenų bazė:** nieko importuoti nereikia.

- Meniu kapsulė - per vidurį tarp logotipo ir paskyros (vienodi tarpai). Versija (test / kompiuteryje) - po logotipu, dešinėje.
- Skirtukų eilutė - „Paskyra“ mygtuko tęsinys: ta pati rožinė-violetinė spalva, skirtukai - tokie pat „lustai“ kaip meniu. Be užrašo „Treneriams“.
- Skirtuko „Žinutės“ nebėra - visos žinutės pokalbio lange (mygtukas apačioje dešinėje).
- Nustatymuose - tik tai, ko nėra meniu: be Testų, Pranešimų, Varžybų, pamokų medžio ir puslapio redagavimo (jie pasiekiami savo vietose).
- Pamokose ir dan aplankai: 1 dan ... 9 dan (juodas diržas). Jau sukurti kyu aplankai ir jų turinys nekeičiami.

## v1.11 - 2026-11-03 (vientisas meniu, nario profilis, pokalbis su visais nariais, pamokos pagal kyu)
**Duomenų bazė:** nieko importuoti nereikia.

Meniu:
- Vienas meniu: pagrindinio puslapio skiltys (Treniruotės, Apie klubą, Kontaktai) ir kiti puslapiai (Renginiai ›, Naujienos ›, Pamokos ›)
  vienoje kapsulėje, atskirti plonu brūkšniu. Paskyra (ar „Prisijungti“) - dešinėje.
- „Treneriams“ ir „Mano paskyra“ skirtukai - tų pačių kraštų kaip viršutinė juosta. Telefone - tos pačios grupės viena po kita.

Pokalbiai ir nario profilis:
- Pokalbio lange skirtukas „Visi nariai“ (pagal grupes) ir paieška pagal vardą, pavardę ar grupę - galima pradėti pokalbį su bet kuriuo nariu.
- Paspaudus vardą pokalbio viršuje (ar „Profilis“) - nario profilis: amžius, grupė, diržas, lygis, sezono taškai, lankomumas per 30 d.,
  kito diržo programa, kontaktai (tėvai, el. paštas, telefonas), renginiai, paskutinė pastaba. Iš jo - „Visa kortelė“, „Pokalbis“, „Lankomumas“.
- Profilis atsidaro ir paspaudus vardą lankomumo, narių ir renginio dalyvių sąrašuose.

Pamokos:
- Pamokos - aplankuose pagal kyu (9 kyu ... 1 kyu) su diržo spalva. Narys mato savo, žemesnių ir kito diržo aplankus (savo - pažymėtas).
- Aplanke treneriui: „Pridėti: Tekstas / Nuotrauka / Video / Aplankas“. Pavadinimo įrašyti nebūtina - paimamas iš teksto ar prierašo.
- Pirmą kartą atidarius pamokas: sukuriami kyu aplankai, pamokos su diržu ir diržo aplankai perkeliami į savo kyu aplanką, tušti seni aplankai ištrinami.
  Nė viena pamoka nedingsta: aplankai su pamokomis be diržo lieka po kyu aplankų - juos galima perkelti ar ištrinti („✎ Tvarkyti“).

## v1.10 - 2026-10-28 (atsarginės kopijos, prisijungimas su kodu, atšauktos treniruotės, programėlė telefone, testai)
**Duomenų bazė:** importuoti `db/023_kodas_atsauktos_treniruotes.sql`. Failą galima importuoti ir antrą kartą.
**Kompiuteryje:** `tools\lokali-atnaujinti.ps1` - importuoja pats.

Atsarginės kopijos:
- Visa duomenų bazė kopijuojama automatiškai kartą per dieną, saugomos 14 paskutinių (test ir oficialioje svetainėje).
- Treneriams → Nustatymai → „Atsarginės kopijos ir sistema“ (tik adminas): „Sukurti kopiją dabar“, „Atsisiųsti“, kaip atkurti.
  Ten pat - svetainės versija ir kurie db failai importuoti. Jei kopija nepavyko ar nedaryta 2 d. - pranešimas „Reikia padaryti“.
- Instrukcija: `docs/atsargines-kopijos.md`. Privatumo politikoje - kiek laiko saugomos kopijos.

Prisijungimas su kodu el. paštu (rekomenduojama Deniui):
- Mano paskyra → „Kodas el. paštu“ → „Siųsti kodą“ → įvesti kodą iš laiško. Įjungiama tik patvirtinus kodą (taip žinome, kad laiškai ateina).
- Naujame įrenginyje po slaptažodžio prašomas 6 skaitmenų kodas. „Pasitikėti šiuo įrenginiu“ - 30 d. kodo neprašoma.
  Patikimų įrenginių sąrašas ir „Pamiršti visus įrenginius“ - nustatymuose.
- Jei laiškai nustotų eiti: phpMyAdmin → `UPDATE accounts SET two_factor = 0 WHERE email = '...'`.

Atšauktos treniruotės ir atostogos:
- Nustatymai → „Atšauktos treniruotės ir atostogos“ (ar „Šiandien“ prie treniruotės - „atšaukti“): nuo-iki, priežastis, grupės.
  Galima iškart pranešti nariams paskyroje („Svarbu“) ir el. paštu.
- Atšaukta treniruotė pažymima „Šiandien“ ir „Treniruotė“; nariai mato įspėjimą tvarkaraštyje, pagrindiniame puslapyje - 3 sav. prieš.
- Ištrynus atšaukimą - viskas vėl kaip įprastai, pranešimai dingsta.

Programėlė telefone:
- Svetainę galima įsidiegti kaip programėlę (ikona telefono ekrane, be naršyklės juostos). Kaip - paskyros nustatymuose.
- Be interneto rodoma „Nėra interneto ryšio“. Puslapiai su narių duomenimis telefone nesaugomi.

Kita:
- Automatiniai testai (`tests/`): kiekvienas įkėlimas į GitHub patikrinamas pats (GitHub → Actions → „Testai“).
- README atnaujintas su nuotraukomis. Pagrindinio puslapio CSS ir JS - atskiruose failuose (greičiau krauna).
- Lokaliai yra trenerio (ne admino) paskyra: `treneris@karateka.test` / `testas123`.

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
