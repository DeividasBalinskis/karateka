# Varžybų sistemos prototipas (lentelės ir tvarkaraštis)

Vienas failas `index.html`. **Atidaryti: dukart spustelėti** (serverio nereikia).
Vardai ir klubai išgalvoti. Pakeitimai išsaugomi tik toje naršyklėje, „Atkurti pavyzdį“ grąžina pradžią.

Tikslas – parodyti kryptį: Kihapp funkcijos, kurios patiko, plius tai, ko trūko – **galimybė viską pakeisti ranka**.
Tikra varžybų sistema (atskiras projektas `Varzybos`) dar ne GitHub'e, todėl šis prototipas parašytas atskirai.
Kai `Varzybos` bus GitHub'e, šią logiką ir mygtukus perkelsime į ją.

## Kas veikia

| Dalis | Kaip Kihapp | Ko Kihapp trūko, o čia yra |
|---|---|---|
| Klubai | Klubų sąrašas su dalyvių ir kategorijų skaičiais, „Total“ eilutė | Klubo pavadinimą pataisyti tiesiog lentelėje (pasikeičia visiems dalyviams), pridėti/pašalinti klubą, „Dalyviai →“ atidaro to klubo dalyvius |
| Dalyviai | Dalyvių sąrašas su jų kategorijomis | Tas pats sportininkas keliose kategorijose; vardą ir klubą keisti lentelėje (pasikeičia visur), pridėti į kategoriją ar išimti vienu paspaudimu, paieška, klubo filtras, „Tik be kategorijos“ (kaip Kihapp „Division missing“) |
| Kategorijos | Kategorijos su dalyvių skaičiais | Perkelti dalyvį į kitą kategoriją vienu pasirinkimu, padalinti kategoriją, sujungti dvi mažas, pridėti/pašalinti, pervadinti, keisti rungtį. Pakeitus dalyvius – lentelė pažymima „pasenusi“ ir, jei kovų dar nebuvo, burtai traukiami iš naujo patys |
| Lentelės | Olimpinė lentelė, aka/shiro, numeruotos kovos, paguoda, vietos | Trys sistemos: paguoda (2 bronzos, WKF principu), kova dėl 3 vietos, 2 bronzos be kovos. **Sukeisti dalyvius** prieš pradedant, **užrakinti** lentelę, **atšaukti** (40 žingsnių), išvalyti rezultatus. Burtai atskiria to paties klubo sportininkus pirmame rate (patikrinta: 140 porų, 0 to paties klubo) |
| Tvarkaraštis | Tatamiai, kategorijos su laikais, juosta „atlikta / iš viso“ | **Tempti** kategorijas tarp tatamių ir eilėje (arba ↑ ↓ →), keisti pradžios laiką ir pavadinimą, pridėti/pašalinti tatamį, kovos trukmė pagal rungtį, **„Paskirstyti tolygiai“** – kad visi tatamiai baigtų panašiu metu. Laikai persiskaičiuoja patys |

## Ko dar nėra (ir kas bus vėliau)

- Kvietimai klubams ir mokėjimai (Kihapp „Payments“).
- Komandos (Kihapp „Teams“): sudaryti iš klubo dalyvių.
- Švieslentės: operatoriaus, žiūrovų, „green screen“; tiesioginiai rezultatai.
- Kihapp variantas su **kryžmine paguoda ir viena kova dėl bronzos** (matytas Budo Cup lentelėje: pusfinalio pralaimėtojas kovoja su kitos pusės ketvirtfinalio pralaimėtoju). Reikia patikslinti su Deniu, kurią sistemą naudojate – tada pridėsiu.

## Ką perkelti į tikrą sistemą

- `makeDraw()` – burtai: standartinė lentelės seka (`seedOrder`) + to paties klubo atskyrimas.
- `bracket()` – kovos apskaičiuojamos iš burtų ir rezultatų (niekas nesaugoma dvigubai), paguoda ir vietos.
- `prune()` – pakeitus rezultatą, nebegaliojantys tolimesni rezultatai išvalomi patys.
- Tvarkaraščio laikų skaičiavimas ir „Paskirstyti tolygiai“ (ilgiausios kategorijos – pirmos į laisviausią tatamį).
