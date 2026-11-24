# GitHub → domenai.lt: pakeitimai į test.karateka.lt įkeliami automatiškai

Būsena: **veikia nuo 2026-11-24** (FTP paskyra `ikelimas@karateka.lt`, pirmas įkėlimas pavyko).

Kaip veikia: pakeitimai išsaugomi GitHub'e → GitHub (Actions) per FTP įkelia tik pasikeitusius failus į **test** aplanką →
duomenų bazė atsinaujina pati (nuo v1.93: kai administratorius atidaro bet kurį Valdymo puslapį).
Gyva karateka.lt **neliečiama**: FTP paskyra mato tik test aplanką. Slaptažodis lieka tik GitHub paslaptyse.

## Ką padaryti vieną kartą (Deividas)

1. **domenai.lt** → prisijungti → *Paslaugos* / *Hostingas* → **Valdymo panelė (cPanel)**.
2. cPanel → skiltis *Failai* → **FTP paskyros** (*FTP Accounts*).
3. Laukai:
   - *Log In*: `ikelimas`
   - *Password*: paspausti *Password Generator* → nukopijuoti slaptažodį (prireiks 6 žingsnyje).
   - *Directory*: **test svetainės aplankas**: `/domains/test.karateka.lt/public_html` (panelė pati prideda `/home/aus15792`).
     Svarbu: ne `domains/karateka.lt` (gyva svetainė) ir ne šakninis aplankas.
   - *Quota*: *Unlimited* → **Create FTP Account**.
4. Žemiau, prie naujos paskyros → *Configure FTP Client* → užsirašyti **FTP Server** (pvz. `ftp.karateka.lt`) ir pilną vartotojo vardą
   (pvz. `ikelimas@karateka.lt`).
5. **github.com/DeividasBalinskis/karateka** → *Settings* → *Secrets and variables* → *Actions* → **New repository secret**.
6. Sukurti 3 paslaptis (*Name* → *Secret* → *Add secret*):
   - `TEST_FTP_SERVER` - FTP serveris iš 4 žingsnio;
   - `TEST_FTP_USER` - pilnas vartotojo vardas iš 4 žingsnio;
   - `TEST_FTP_PASSWORD` - slaptažodis iš 3 žingsnio.
7. Pokalbyje parašyti: **„FTP paruoštas“**.

## Kas vyksta toliau

1. Įrašas su žyme `[į test]` (bet kurioje šakoje, išskyrus `main`) → GitHub *Actions* → „Įkelti į test.karateka.lt“
   (patikros → paketas → FTP). Rankomis: *Actions* → „Įkelti į test.karateka.lt“ → *Run workflow*.
2. Test svetainėje patikrinama versija ir duomenų bazė (`diegimas-api.php`, raktas - Valdymas → Atsiliepimai → „API prieiga“).
3. Jei FTPS neveikia (domenai.lt kartais leidžia tik paprastą FTP) - pridėti paslaptį `TEST_FTP_PROTOCOL` = `ftp`.

## Atšaukti prieigą bet kada

cPanel → *FTP Accounts* → `ikelimas` → *Delete* (arba GitHub → *Secrets* → ištrinti paslaptis).
