# Kaip įkelti į test.karateka.lt

Reikia: Windows kompiuterio su projekto aplanku, FileZilla (arba hostingo failų tvarkyklės) ir phpMyAdmin prisijungimo.

0. **Ar naujausia versija jau `main` šakoje?** Skriptas ima tik iš `main`.
   Jei ne - parašykite „į main“, arba GitHub'e: Pull requests → New → `main` ← `dev` → Create → Merge.
1. Projekto aplanke: `tools\atnaujinti-is-github.ps1` → dešiniu pelės klavišu → **Run with PowerShell**.
   Skriptas atsisiunčia naujausią versiją ir paruošia aplanką `ikelimui\svetaine` (ir `ikelimui\svetaine-v….zip`).
2. **Failai:** FileZilla → prisijungti prie serverio → atidaryti test svetainės aplanką →
   nutempti visą `ikelimui\svetaine` turinį ir pasirinkti „perrašyti“. Arba įkelti zip ir išarchyvuoti.
   Serverio `config.php` liks savo vietoje - jo įkėlimo pakete nėra.
3. **Duomenų bazė - be phpMyAdmin:** atidaryti **test.karateka.lt/admin** → prisijungti → „Šiandien“ → **„Atnaujinkite duomenų bazę“**
   (arba Nustatymai → Atsarginės kopijos ir sistema → **„Atnaujinti duomenų bazę“**). Prieš tai automatiškai pasidaro kopija.
   (Jei kada prireiktų rankomis - phpMyAdmin → `aus15792_test` → Import → trūkstami `db\0xx` failai iš eilės.)
4. Atidaryti **test.karateka.lt/admin** → prisijungti → „Šiandien“.
   Seni pavyzdžiai išsivalo patys (prieš tai - atsarginė kopija). Viršuje kairėje matysite versiją, pvz. `v1.18`.
5. Patikrinti: Nustatymai → **Atsarginės kopijos ir sistema** - „Importuoti db failai“ turi būti žalia.

Jei kas nepavyko - nukopijuokite klaidą ir parašykite (arba palikite per mygtuką „💡 Atsiliepimas“).
