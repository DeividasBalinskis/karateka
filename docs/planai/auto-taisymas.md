# Planas: atsiliepimas → pataisoma → test atsinaujina pats → Denis „Patinka“ / „Grąžinti“

Būsena (v1.89): **1-3 dalys padarytos** (įkėlimo workflow, `diegimas-api.php`, Denio mygtukai). Trūksta: FTP paskyros ir GitHub paslapčių, tada - 4 dalis (suplanuota užduotis).
Gyva svetainė karateka.lt šiame plane **neliečiama niekada**. `main` juda tik po „į main“.

## Kaip veiks (Denio akimis)
1. Denis test svetainėje palieka 💡 atsiliepimą.
2. Per ~1 val. (pagal grafiką) jis pataisomas, test svetainė atsinaujina pati, Denis gauna atsakymą „Padaryta (vX.YY) - kur pažiūrėti“.
3. Prie atsiliepimo Denis mato **👍 Patinka** ir **↩ Grąžinti kaip buvo**.
4. „Patinka“ - paliekama. „Grąžinti“ - kitame paleidime pakeitimas atšaukiamas, test vėl atnaujinamas, atsakymas „Grąžinta (vX.YY)“.
5. Neaiškus atsiliepimas - nespėliojama: atsako vienu klausimu (būsena „Bus pataisyta“).

## Dalys
1. **Įkėlimas į test be rankų** - GitHub Actions (`.github/workflows/ikelti-i-test.yml`):
   patikros (`tests/checks.sh`) → paketas (kaip zip: be `config.php`, be įkeltų failų) → FTP/FTPS į **tik test** aplanką.
   Įkeliami tik pasikeitę failai; serverio `config.php` ir `uploads/` neliečiami. Paleidžiama po kiekvieno pataisymo.
2. **Duomenų bazės atnaujinimas be paspaudimo** - adresas `diegimas-api.php` su tuo pačiu „API prieigos“ raktu:
   GET - kokia versija įkelta ir ar trūksta db failų; POST - atnaujina (prieš tai kopija, kaip mygtukas Valdyme).
3. **Denio mygtukai** - `db/053`: atsiliepimui 2 laukai (įvertinimas, kada). Mygtukai - lange „Jūsų atsiliepimai“ prie „Pataisyta“
   (matomi ir kitame Denio įrenginyje); Valdymas → Atsiliepimai rodo „👍 Patinka“ / „↩ Prašo grąžinti“ / „↩ Grąžinta“.
4. **Taisymo ciklas** - suplanuota užduotis (sena „Denio atsiliepimų peržiūra“ atnaujinama):
   nauji atsiliepimai → pataisymas (vienas atsiliepimas = vienas commit su „#ID“) → testai → darbo šaka → 1 dalis → 2 dalis →
   patikrina versiją test svetainėje → atsakymas Deniui. Prašymai „Grąžinti“ → `git revert` tų commit'ų → 1, 2 dalys → „Grąžinta“.
   Jei testai krenta - į test neįkeliama, Deividui - žinutė.

## Ribos
- „Iškart“ neįmanoma: svetainė negali pažadinti taisymo ciklo. Greičiausiai - kas valandą (grafikas).
- Kiekvienas paleidimas naudoja paslaugos limitą. Sena užduotis išsijungė, nes plane pritrūko kreditų.
- Karate, varžybų, egzaminų taisyklių negalvojama - klausiama.
- Dideli pakeitimai (meniu, struktūra, duomenų trynimas) - tik klausimas, ne automatinis taisymas.

## Ko reikės iš Deividui (kai planas patvirtintas)
1. domenai.lt valdymo skydelis → FTP paskyros → nauja paskyra, kurios aplankas - **tik test svetainės aplankas**.
2. GitHub → repo → Settings → Secrets and variables → Actions → 3 paslaptys: `TEST_FTP_SERVER`, `TEST_FTP_USER`, `TEST_FTP_PASSWORD`.
3. Parašyti „į main“ (GitHub mato rankiniu būdu paleidžiamus workflow tik iš `main`).
4. Patvirtinti grafiką ir kad suplanuota užduotis gali naudoti limitą.

## Klausimai
1. Kaip dažnai: (a) kas valandą 8-22 val., (b) 4 kartus per dieną kaip anksčiau, (c) kita.
2. Kas spaudžia „Patinka“ / „Grąžinti“: (a) tik atsiliepimo autorius, (b) autorius ir Deividas.
3. Ką taisyti be klausimo: (a) tik smulkmenas (tekstai, išdėstymas, klaidos), (b) viską, kas aiškiai suprantama.
