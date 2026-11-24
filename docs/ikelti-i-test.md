# Kaip įkelti į test.karateka.lt

Reikia: Windows kompiuterio su projekto aplanku, FileZilla (arba hostingo failų tvarkyklės) ir phpMyAdmin prisijungimo.

**Kaip versija keliauja:** Claude dirba šakoje `claude/…` ir ten išsaugo kiekvieną etapą (tai atsarginė kopija, ne leidimas).
Kai parašote **„į main“**, ta versija perkeliama į `main`. Skriptas ima tik iš `main`.

0. **Ar naujausia versija jau `main` šakoje?** Patikrinkite GitHub'e `public_html/app/VERSION` (main šakoje).
   Jei ne - parašykite Claude „į main“.
   - Arba ZIP iš Claude pokalbio (`svetaine-vX.YY.zip`) - tada 1 žingsnio nereikia, iškart 2.
1. Projekto aplanke: `tools\atnaujinti-is-github.ps1` → dešiniu pelės klavišu → **Run with PowerShell**.
   Skriptas atsisiunčia `main` ir paruošia aplanką `ikelimui\svetaine` ir `ikelimui\svetaine-v….zip`.
   Seni zip paketai paliekami; jei tos pačios versijos zip jau yra - naujas gauna priesagą `-2`, `-3`.
2. **Failai:** FileZilla → prisijungti prie serverio → atidaryti test svetainės aplanką →
   nutempti visą `ikelimui\svetaine` turinį ir pasirinkti „perrašyti“. Arba įkelti zip ir išarchyvuoti.
   Serverio `config.php` liks savo vietoje - jo įkėlimo pakete nėra.
3. **Duomenų bazė - nuo v1.93 atsinaujina pati:** atidarius bet kurį puslapį administratoriumi viršuje pasirodo „Duomenų bazė atnaujinta automatiškai“.
   Jei nepavyko - **test.karateka.lt/admin** → prisijungti → „Šiandien“ → **„Atnaujinkite duomenų bazę“**
   (arba Nustatymai → Atsarginės kopijos ir sistema → **„Atnaujinti duomenų bazę“**). Prieš tai automatiškai pasidaro kopija.
   (Jei kada prireiktų rankomis - phpMyAdmin → `aus15792_test` → Import → trūkstami `db\0xx` failai iš eilės.)
4. Atidaryti **test.karateka.lt/admin** → prisijungti → „Šiandien“.
   Seni pavyzdžiai išsivalo patys (prieš tai - atsarginė kopija). Viršuje kairėje matysite versiją, pvz. `v1.73`.
5. Patikrinti: Nustatymai → **Atsarginės kopijos ir sistema** - „Importuoti db failai“ turi būti žalia.

Jei kas nepavyko - nukopijuokite klaidą ir parašykite (arba palikite per mygtuką „💡 Atsiliepimas“).
