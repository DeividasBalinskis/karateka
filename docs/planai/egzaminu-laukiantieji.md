# Planas: egzaminų laukiančiųjų sąrašas ir atskiri protokolai (#165, Denis)

Būsena: **padaryta v1.90** (pirmas variantas). Denio atsakymai: 1) vienas sąrašas; 2) egzamino data ir atskiri protokolai diržams; 3) kaina pagal diržą; 4) kol kas visi per laukiančiuosius, vėliau - patogiau.

## Ko prašo Denis
1. Užsiregistravę žmonės patenka į **laukiančiųjų sąrašą** (ne iš karto į protokolą).
2. Galima sukurti **atskirus protokolus** (kaip treniruočių grupės ar renginiai).
3. Iš laukiančiųjų sąrašo Denis nustato, į kurį protokolą žmogų įtraukti.
4. Įtraukus - žmogui išsiunčiamas **laiškas**: egzaminas tada ir tada, ten ir ten, kaina tokia.

## Kaip yra dabar
- Egzaminas = renginys (data, laikas, vieta); jam - vienas protokolas.
- Registracijos nuoroda - kiekvienam egzaminui atskira; užsiregistravęs iš karto įrašomas į to egzamino protokolą.
- Kainos - pagal diržą (Egzaminai → „Kainos“).

## Siūlymas
1. **Viena registracijos nuoroda visiems egzaminams** (Egzaminai → „🔗 Registracija“). Forma: vardas, pavardė, gimimo data, klubas, dabartinis diržas, el. paštas / telefonas.
2. **Egzaminai → „⏳ Laukiantieji (N)“**: sąrašas su filtrais (klubas, diržas). Prie kiekvieno - „Į protokolą ▾“ (pasirinkti egzaminą) arba pažymėti kelis ir perkelti kartu.
3. **Protokolas = egzaminas** kaip dabar (data, laikas, vieta, egzaminatorius); jų galima kurti kiek reikia („+ Naujas egzaminas“).
4. Įtraukus į protokolą - **laiškas** žmogui (jo kalba): data, laikas, vieta, kaina pagal diržą, ką atsinešti; laiškas matomas „Siuntimas“ žurnale.
5. Išėmus iš protokolo - grįžta į laukiančiuosius (ne ištrinamas).
6. Duomenų bazė: laukiančiųjų lentelė (arba registracija be egzamino) - `db/054`.

## Klausimai Deniui
1. Ar laukiančiųjų sąrašas **bendras visiems egzaminams** (viena nuoroda), ar kiekvienam egzaminui atskirai?
2. Ar protokolas = atskiras egzaminas (data, laikas, vieta) kaip dabar, ar **vienai egzaminų dienai reikia kelių protokolų** (pvz. 10:00 vaikai, 12:00 suaugę)?
3. Kaina laiške: **pagal diržą** iš kainų lentelės (tada registracijoje klausiame dabartinio diržo), ar viena kaina protokolui?
4. Ar **klubo nariai** (su paskyra, užsiregistravę Karateka svetainėje) irgi eina per laukiančiuosius, ar tik registracija per nuorodą?
