# GitHub → domenai.lt: Claude pats įkelia pakeitimus į test.karateka.lt

Kaip veikia: Claude išsaugo pakeitimus GitHub'e → GitHub (Actions) per FTP įkelia tik pasikeitusius failus į **test** aplanką →
duomenų bazė atsinaujina pati (v1.93: kai administratorius atidaro bet kurį puslapį; arba Claude per `diegimas-api.php`).
Gyva karateka.lt **neliečiama**: FTP paskyra mato tik test aplanką. Slaptažodis lieka GitHub paslaptyse - pokalbyje jo nėra.

## Ką padaryti vieną kartą (Deividas)

1. **domenai.lt** → prisijungti → *Paslaugos* / *Hostingas* → **Valdymo panelė (cPanel)**.
2. cPanel → skiltis *Failai* → **FTP paskyros** (*FTP Accounts*).
3. Laukai:
   - *Log In*: `claude-test`
   - *Password*: paspausti *Password Generator* → nukopijuoti slaptažodį (prireiks 6 žingsnyje).
   - *Directory*: **test svetainės aplankas** (tas pats, į kurį dabar keli failus su FileZilla, pvz. `test.karateka.lt`).
     Svarbu: ne `public_html` ir ne šakninis aplankas.
   - *Quota*: *Unlimited* → **Create FTP Account**.
4. Žemiau, prie naujos paskyros → *Configure FTP Client* → užsirašyti **FTP Server** (pvz. `ftp.karateka.lt`) ir pilną vartotojo vardą
   (pvz. `claude-test@karateka.lt`).
5. **github.com/DeividasBalinskis/karateka** → *Settings* → *Secrets and variables* → *Actions* → **New repository secret**.
6. Sukurti 3 paslaptis (*Name* → *Secret* → *Add secret*):
   - `TEST_FTP_SERVER` - FTP serveris iš 4 žingsnio;
   - `TEST_FTP_USER` - pilnas vartotojo vardas iš 4 žingsnio;
   - `TEST_FTP_PASSWORD` - slaptažodis iš 3 žingsnio.
7. Parašyti Claude: **„FTP paruoštas“**.

## Ką tada daro Claude

1. Išsaugo pakeitimą su žyme `[į test]` → GitHub *Actions* → „Įkelti į test.karateka.lt“ (patikros → paketas → FTP).
2. Patikrina test svetainėje versiją ir duomenų bazę (`diegimas-api.php`), jei reikia - atnaujina.
3. Jei FTPS neveikia (domenai.lt kartais leidžia tik paprastą FTP) - paprašys pridėti paslaptį `TEST_FTP_PROTOCOL` = `ftp`.

## Atšaukti prieigą bet kada

cPanel → *FTP Accounts* → `claude-test` → *Delete* (arba GitHub → *Secrets* → ištrinti paslaptis).
