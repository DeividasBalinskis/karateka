# Budo ir Varžybos: palyginimas (D1)

2026-11-25 · Budo v2.07 · Varžybų aplankas `..\Varzybos` perskaitytas (tik skaityta, niekas nekeista).
Palyginta **pagal kodą** - programos nebuvo paleistos.

**Siūlymas: palikti Budo, Varžybas nuimti.** Tai tik siūlymas - sprendžia Deividas (D2).

## Kas yra kas

| | Budo | Varžybos |
|---|---|---|
| Kur | karateka.lt dalis, ta pati duomenų bazė | atskira svetainė, sava duomenų bazė ir prisijungimai |
| Kodas | šiame repo | tik aplanke kompiuteryje (**be git**), paskutiniai pakeitimai 2026-10-20 |
| Serveryje | test.karateka.lt | pagal jos planą dar neįkelta (ar įkelta vėliau - neaišku) |
| Automatiniai testai | yra | nėra |

## Funkcijos

✓ yra · ◐ iš dalies · ✗ nėra

### Registracija ir paruošimas

| Funkcija | Budo | Varžybos |
|---|---|---|
| Dalyvius įrašo organizatorius | ✓ | ✓ |
| Klubo nariai paimami iš karateka.lt | ✓ „+ Karateka nariai“ | ◐ per tiltą |
| Narys ar tėvai registruojasi iš savo paskyros | ✗ tik per bendrą nuorodą | ✓ per tiltą |
| Kitų klubų vieša forma | ✓ tvirtina organizatorius; tėvų telefonas, sutikimas dėl nuotraukų | ✓ įrašo iškart į kategorijas, be patvirtinimo |
| Atšaukti registraciją | ◐ tame pačiame įrenginyje, kol nepridėta | ◐ nariui per tiltą, kol registracija atvira |
| Siūlomos tik tinkamos kategorijos | ◐ organizatorius mato „netinka“; registruojantis renkasi rungtis | ✓ |
| Svoris | ◐ tik tekstas pavadinime | ✓ tikrinamas |
| Amžius | ◐ pagal gimimo metus | ✓ pagal metus arba varžybų dieną |
| Kategorijos sudaromos greitai | ✓ „⚡ Automatiškai“, šablonas | ✓ „Greitai sukurti kelias“ |
| Sportininkai lieka kitoms varžyboms | ◐ tik Karateka nariai | ✓ visi |

### Rungtys, burtai, sistemos

| Funkcija | Budo | Varžybos |
|---|---|---|
| Kata, kumite, fukugo, kihon, enbu | ✓ | ✓ |
| Sumo | ✓ | ✗ |
| Komandos ir poros | ✓ | ✓ |
| Atkrintamosios: 2 bronzos / kova dėl 3 vietos / paguoda | ✓ | ✓ |
| Tik 1 ir 2 vieta | ✗ | ✓ |
| Ratų sistema | ✓ be lygiųjų | ✓ su lygiosiomis |
| Teisėjų balai, kraštiniai atmetami | ✓ 1-2 ratai, 1-15 teisėjų | ✓ keli ratai (pvz. „16,8“), 3 / 5 / 7 teisėjai |
| Balų ribos kategorijai | ✗ visada 0-10 | ✓ |
| Įspėjimas apie lygybę ties finalo riba | ✗ | ✓ |
| To paties klubo dalyviai išskiriami | ✓ | ✓ |
| Stipriausių išsėjimas | ◐ rankomis „✎ Keisti“ | ◐ tik serveryje, mygtuko nėra |

### Varžybų diena

| Funkcija | Budo | Varžybos |
|---|---|---|
| Kovos balai ir laikmatis | ✓ +1 / +2 / +4 / −1, laiko šablonai, gongai | ✓ ippon / waza-ari |
| Baudos, jogai, kiken, shikkaku, diskvalifikacija | ✗ | ✓ |
| Laimėtojas pagal taisykles | ◐ pagal balus arba mygtukas (hantei) | ✓ pasiūlo, sekretorius patvirtina |
| Tvarkaraštis pagal tatamius | ✓ tempimas, išlyginimas, vėlavimas | ✓ rodyklės, automatinis paskirstymas |
| Vieno tatamio darbastalis | ✓ | ✓ |
| Sekretorius be paskyros (nuoroda) | ✓ | ✗ |
| Atšaukimas ir istorija | ✓ ↶ ir kopijos serveryje | ◐ veiksmas kovoje, rezultatas, burtai |
| Kelios planšetės vienu metu | ◐ viena bendra būsena, sujungiama (D4 atviras) | ✓ kiekviena kova saugoma atskirai |

### Žiūrovai ir po varžybų

| Funkcija | Budo | Varžybos |
|---|---|---|
| Gyvai telefone | ✓ | ✓ |
| Salės ekranas | ◐ po vieną tatamį | ✓ visi tatamiai viename |
| Video iš telefono | ✓ | ✗ |
| Dalyvio paieška („Rask save“) | ✓ | ✗ |
| Vaikų vardai viešai sutrumpinti | ✓ | ✗ |
| Vietos ir klubų medaliai | ✓ | ✓ |
| Apdovanojimų planšetė, diplomai | ✓ | ✗ |
| CSV | ◐ rezultatai, klubų kontaktai | ✓ rezultatai ir dalyviai |
| Taškai nariams | ✓ persikelia patys (susietam renginiui) | ◐ treneris įkelia mygtuku |
| LTKF svetainėje | ✓ kalendorius ir rezultatai | ◐ tik kalendorius |
| LT / EN / RU, temos, 💡 atsiliepimai | ✓ | ✗ tik LT |
| Bandomosios varžybos mygtuku | ✓ | ◐ tik kompiuteryje |
| API kitoms sistemoms | ✗ | ✓ |

## Ką turi Varžybos, o Budo trūksta

Darbas: S - mažas, M - vidutinis, L - didelis. Eilė - nuo svarbiausio (siūloma).

| # | Ko trūksta Budo | Darbas | Pastaba |
|---|---|---|---|
| 1 | Narys ar tėvai registruojasi iš paskyros (tinkamos kategorijos); „Dalyvaus“ pažymėtus užregistruoti | M | dabar tai daro tik tiltas |
| 2 | Baudos, jogai, kiken, shikkaku kovoje; diskvalifikacija balų sistemoje | M | Denis pasako, ko reikia |
| 3 | Ippon / waza-ari taisyklės (Shobu Ippon, Sanbon, Nihon) ir laimėtojo pasiūlymas | M | pačiose Varžybose pažymėta: **nepatvirtinta** |
| 4 | Įspėjimas apie lygybę ties finalo riba, rankinė vieta | S | |
| 5 | Dalyvio svoris ir tikrinimas; amžius varžybų dieną | S | |
| 6 | Stipriausių išsėjimas | S | |
| 7 | Visi tatamiai viename salės ekrane | S | |
| 8 | Dalyvių sąrašo CSV | S | |
| 9 | Daugiau kaip 2 balų ratai, balų ribos | S | |
| 10 | Lygiosios ratų sistemoje; „tik 1 ir 2 vieta“ | S | ar reikia - Denis |
| 11 | Kitų klubų sportininkai lieka kitoms varžyboms | M | asmens duomenys: reikia saugojimo termino |
| 12 | Adresas kitoms sistemoms (tik skaityti) | S | reikalingas LTKF, žr. žemiau |
| 13 | Kiekviena kova duomenų bazėje atskirai, taisyklės serveryje | L | tik jei D4 neužteks |

**Neaišku (Denis):** fukugo abi sistemos veda skirtingai. Budo - kumite ir kitei pakaitomis pagal ratus
(finalas - kumite). Varžybos - kiekvienoje kovoje pirma kata, tada kumite. Kuri tvarka teisinga?

## Tiltas šiandien

Tiltas - tai karateka.lt jungtis su Varžybomis per jos API (su slaptu raktu) ir prisijungimas be slaptažodžio.
Serveryje veikia tik tada, kai `config.php` yra `varzybos` blokas; be jo mygtukai nerodomi. Kompiuteryje
(`env = local`) įsijungia pats.

**Tik tiltui (6 failai, `public_html/`):**

| Failas | Ką daro |
|---|---|
| `app/varzybos.php` | užklausos į Varžybas, prisijungimo nuoroda |
| `varzybos.php` | nario ir tėvų registracija |
| `admin/varzybos-isorine.php` | varžybų sąrašas, susiejimas su renginiais |
| `admin/varzybos-sso.php` | prisijungimas be slaptažodžio |
| `admin/varzybu-dalyviai.php` | „Dalyvaus“ → kategorijos |
| `admin/varzybu-rezultatai.php` | rezultatai → renginys ir taškai |

**Atsargiai su vardais:** `admin/varzybos.php` ir šakninis `varzybu-rezultatai.php` yra **Budo**, ne tiltas.

**Kituose failuose yra nuorodos į tiltą (10):** `app/bootstrap.php`, `app/data.php`, `app/settings.php`,
`paskyra.php`, `renginys.php`, `renginiai.php`, `admin/renginio-rezultatai.php`, `admin/varzybos.php`,
`ltkf/inc/pagalbines.php`, `ltkf/inc/nustatymai.php`.
Smulkmenos: puslapių pavadinimai (`app/feedback.php`, `app/chat.php`), `ltkf/renginys.php`,
`config.example.php`, EN / RU tekstai.

**Vienas jungiklis abiem:** Nustatymai → Registracija → „Varžybos (Budo)“ išjungia ir Budo nuorodą kitiems
klubams, ir narių registraciją per tiltą (atšaukti per tiltą vis tiek galima).

**Pastebėta:** `ltkf/inc/nustatymai.php` numatytasis Varžybų adresas - vietinis (kompiuterio). Jei serveryje
nėra `inc/nustatymai.local.php`, LTKF vis tiek bando juo jungtis.

## Kaip LTKF skaitytų Budo rezultatus be bendros duomenų bazės

Šiandien LTKF skaito varžybų lentelę tiesiai iš karateka.lt duomenų bazės (viešas varžybas; kalendoriuje - tik
patvirtintas). LTKF perkėlus į savo hostingą, tai nustos veikti.

| | A. LTKF pasiima (siūloma) | B. Budo nusiunčia |
|---|---|---|
| Kaip | Budo turi viešą adresą (JSON, tik skaityti); LTKF jį užklausia ir laiko kopiją | baigus ar paspaudus „Paskelbti LTKF“, Budo išsiunčia rezultatus; LTKF juos išsaugo pas save |
| Darbas | S-M | M |
| Gerai | paprasta; visada naujausia; LTKF jau turi panašų kodą (Varžybų API) | LTKF nepriklauso nuo karateka.lt; lieka archyvas; išeina tik patvirtinta |
| Blogai | karateka.lt neveikiant reikia paskutinės geros kopijos (dabar jos nelaiko) | reikia priėmimo adreso, slapto rakto, saugojimo ir pakartotinio siuntimo po taisymo |

Abiem atvejais vaikų vardus reikia sutrumpinti **dar Budo pusėje** (dabar LTKF gauna pilnus ir trumpina pati).

Tai tik apie varžybas. LTKF iš tos pačios bazės dar ima tekstus, registrus, teisėjus, klubo duomenis ir
prisijungimą - tai atskiras D6 darbas.

## Siūlymas

**Palikti Budo.**
- Budo daugiau padaryta varžybų dienai: sekretoriatas be paskyros, video, apdovanojimai, diplomai, istorija.
- Ji jau sujungta su nariais, taškais, LTKF ir atsiliepimais; yra testai; trys kalbos.
- Viena sistema - viena duomenų bazė, vienas prisijungimas, vienas hostingas.
- Iš Varžybų verta perimti tik tai, ką Denis pasirinks iš lentelės aukščiau.

## Kaip nuimti Varžybas (D2 planas, vykdyti tik leidus)

1. Deividas patvirtina, kad lieka Budo. Patikrinti, ar serveriuose `config.php` turi `varzybos` bloką ir ar
   kur nors yra tikrų Varžybų duomenų.
2. Išsaugoti Varžybų kodą: šaka `varzybos-kodas`, aplankas `_varzybos`, be `config.php`, `.env`, `.sql`
   (sprendimas 2026-11-21). Šios šakos dar nėra, o aplankas neturi git - tai gali būti vienintelė kopija.
3. Denis išsirenka, ką perkelti į Budo. Ko reikia iš karto (greičiausiai 1 eilutė - registracija iš
   paskyros), padaryti **prieš** nuimant tiltą.
4. LTKF perjungti į A arba B būdą ir išimti Varžybų adresą iš LTKF nustatymų.
5. Išimti nuorodas į tiltą iš 10 failų (dingsta mygtukai; duomenys netrinami).
6. Gavus atskirą leidimą - ištrinti 6 tilto failus, `config` bloką ir nebenaudojamus vertimus.
7. Duomenų bazės nekeisti: `events.tournament_id` lieka nenaudojamas, `members.gender` reikalingas Budo.
8. Patikrinti: `checks.sh`, `smoke.sh`, renginio puslapis, Mano paskyra, Valdymas → Varžybos, LTKF
   kalendorius ir rezultatai.
9. Atnaujinti `docs/decisions.md` ir `docs/projekto-zemelapis.md`. Aplanką `..\Varzybos` ištrina tik pats
   Deividas, jei norės.

Jei Deividas pasirinktų palikti Varžybas, tektų perrašyti sekretoriatą, video, apdovanojimus, diplomus ir
vertimus - tai daug didesnis darbas.
