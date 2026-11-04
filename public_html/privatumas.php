<?php
// Privatumo politika. Pakeitus - atnaujinti datą viršuje (ir, jei pakeitimas esminis, pranešti nariams).
require __DIR__ . '/app/bootstrap.php';

page_start(t('Privatumo politika'), ['description' => t('Kaip VšĮ Karate Ateitis tvarko asmens duomenis.')]);
?>
<article class="panel card legal">
  <div class="eyebrow"><?= te('Asmens duomenų apsauga') ?></div>
  <h1 class="styled"><?= te('Privatumo politika') ?></h1>
  <p class="muted small"><?= te('Atnaujinta: 2026 m. lapkričio 3 d.') ?></p>

  <h2><?= te('1. Kas mes') ?></h2>
  <p><?= te('Jūsų asmens duomenis tvarko') ?> <strong><?= te('VšĮ Karate Ateitis') ?></strong> <?= te('(įmonės kodas 305105392, Girulių g. 5, Vilnius).
     Kontaktai:') ?> <a href="mailto:info@karateka.lt">info@karateka.lt</a>, +370 601 44392.</p>

  <h2><?= te('2. Kokius duomenis renkame ir kodėl') ?></h2>
  <h3><?= te('Bandomoji treniruotė') ?></h3>
  <p><?= te('Renkame vardą, pavardę, el. paštą, telefoną, treniruočių vietą, gimimo metus ir jūsų žinutę. Užklausa ateina klubui el. laišku.
     Ją naudojame tik tam, kad susisiektume ir suderintume treniruotę. Pagrindas - jūsų prašymas prieš sudarant sutartį.') ?></p>

  <h3><?= te('Narystės sutartis') ?></h3>
  <p><?= te('Ją tėvai (ar suaugęs narys) pasirašo elektroniniu parašu per Edufi. Sutartyje yra nario vardas, pavardė ir gimimo data, tėvų (globėjų) vardas, pavardė, telefonas ir el. paštas.
     Šiuo el. paštu siunčiame informaciją apie treniruotes, renginius ir tvarkaraščio pakeitimus.
     Klubui taip pat pateikiate gydytojo pažymą, kad narys gali sportuoti, į svetainę ji nekeliama. Pagrindas - narystės sutartis.') ?></p>

  <h3><?= te('Narių paskyros') ?></h3>
  <ul>
    <li><strong><?= te('Paskyra:') ?></strong> <?= te('vardas, pavardė, el. paštas, telefonas ir slaptažodis (saugomas tik užšifruotas, jo nematome).') ?></li>
    <li><strong><?= te('Narys:') ?></strong> <?= te('vardas, pavardė, gimimo data, lytis, grupė ir diržas.') ?></li>
    <li><strong><?= te('Klubo veikla:') ?></strong> <?= te('lankomumas, dalyvavimas renginiuose, egzaminų ir varžybų rezultatai, taškai ir lygiai, pažymėti egzamino programos punktai, testų rezultatai.') ?></li>
    <li><strong><?= te('Trenerio pastabos, užduotys ir žinutės') ?></strong> <?= te('tarp nario (ar tėvų) ir trenerių.') ?></li>
    <li><strong><?= te('Komentarai ir klausimai') ?></strong> <?= te('prie renginių ir naujienų. Juos gauna treneriai. Treneris gali klausimą su atsakymu paskelbti prie renginio ar naujienos -
        be jūsų vardo. Savo komentarą ar žinutę galite ištrinti patys.') ?></li>
    <li><strong><?= te('Sutikimai:') ?></strong> <?= te('ar atsisakyta nuotraukų skelbimo, tėvų patvirtinimas vaiko paskyrai ir kada tai padaryta.') ?></li>
    <li><strong><?= te('Reakcijos') ?></strong> <?= te('į naujienas.') ?></li>
  </ul>
  <p><?= te('Šiuos duomenis naudojame narystės administravimui. Pagal juos priskiriame grupę, rodome tvarkaraštį, ruošiame egzaminams ir varžyboms, skaičiuojame taškus.
     Taip pat siunčiame pranešimus apie treniruotes ir renginius el. paštu, vėliau galbūt ir SMS. Pagrindas - narystė klube.') ?></p>

  <h3><?= te('Nuotraukos ir vaizdo įrašai') ?></h3>
  <p><?= te('Pasirašydami narystės sutartį sutinkate, kad treniruočių ir renginių nuotraukos bei vaizdo įrašai būtų skelbiami klubo svetainėje ir socialiniuose tinkluose - tik klubo pristatymui.') ?>
     <strong><?= te('Šio sutikimo galite bet kada atsisakyti savo paskyroje') ?></strong> <?= te('arba parašę mums.
     Tada naujų nuotraukų neskelbsime, o paprašius pašalinsime ir jau paskelbtas mūsų kanaluose. Treneriai mato, kurie nariai atsisakė.') ?></p>

  <h2><?= te('3. Vaikų duomenys') ?></h2>
  <p><?= te('Vaiko iki 14 metų paskyra aktyvuojama tik gavus tėvų ar globėjų sutikimą el. paštu. Lietuvoje savarankiškai sutikimą vaikas gali duoti nuo 14 metų.
     Tėvai gali užregistruoti vaiką patys. Savo paskyroje jie mato vaiko informaciją, trenerio pastabas ir žinutes.') ?></p>

  <h2><?= te('4. Kas mato jūsų duomenis') ?></h2>
  <ul>
    <li><strong><?= te('Klubo treneriai') ?></strong> <?= te('- tiek, kiek reikia narystės administravimui.') ?></li>
    <li><strong><?= te('Kiti nariai') ?></strong> <?= te('nemato jūsų kontaktų, gimimo datos, pastabų ar žinučių. Prisijungę nariai mato reitingo Top 5: vardą, pavardės pirmąją raidę ir taškus.') ?></li>
    <li><strong><?= te('Varžybų sistema.') ?></strong> <?= te('Registruojant į varžybas jai perduodame vardą, pavardę, gimimo datą, lytį, diržą ir klubą. Pagal juos parenkama kategorija.
        Varžybų lentelėse ir rezultatuose viešai rodomas dalyvio vardas, pavardė, klubas ir kategorija, kaip įprasta varžybose.
        Varžybų sistemą prižiūri Deividas Balinskis. Ja gali naudotis ir kiti klubai: kurti ir vesti varžybas, gauti rezultatus.') ?></li>
    <li><strong><?= te('Paslaugų teikėjai.') ?></strong> <?= te('Svetainę ir el. paštą talpina domenai.lt (Lietuva). Narystės sutartys pasirašomos per Edufi. Jie duomenų nenaudoja savo tikslais.
        SMS žinučių kol kas nesiunčiame - pasirinkus SMS teikėją, jis bus nurodytas čia.') ?>
        <?= te('Klubo naujienas, renginius ir pamokas į anglų ir rusų kalbas automatiškai verčia DeepL (Vokietija) - jam perduodamas tik viešas klubo tekstas, ne narių duomenys.') ?></li>
  </ul>
  <p><?= te('Duomenų neparduodame ir neperduodame reklamos tikslais.') ?></p>

  <h2><?= te('5. Kiek laiko saugome') ?></h2>
  <ul>
    <li><?= te('Paskyros ir nario duomenis - kol esate narys, ir 1 metus po narystės pabaigos. Vėliau ištriname.') ?></li>
    <li><?= te('Narystės sutartis ir sveikatos pažymas - kol esate narys, ir 1 metus po narystės pabaigos.') ?></li>
    <li><?= te('Bandomosios treniruotės užklausas (el. laiškus) - 6 mėnesius, jei narystė neprasideda.') ?></li>
    <li><?= te('Nebaigtas registracijas (nepatvirtintas el. paštas ar negautas tėvų sutikimas) -') ?> <?= (int) config('retention.unfinished_days', 30) ?> <?= te('dienų. Vėliau jos ištrinamos automatiškai.') ?></li>
    <li><?= te('Prisijungimo bandymų įrašus (el. paštas, IP adresas) apsaugai nuo įsilaužimų - 30 dienų.') ?></li>
    <li><?= te('Prisijungimo kodus el. paštu - 1 parą, „patikimo įrenginio“ įrašą -') ?> <?= (int) TRUSTED_DEVICE_DAYS ?> <?= te('dienų arba kol jį išjungsite.') ?></li>
    <li><?= te('Atsargines duomenų bazės kopijas (kad duomenys nedingtų dėl gedimo) -') ?> <?= (int) config('backup.keep', 14) ?> <?= te('dienų. Jas pasiekti gali tik administratorius.') ?></li>
  </ul>

  <h2><?= te('6. Slapukai ir trečiųjų šalių turinys') ?></h2>
  <p><?= te('Naudojame tik būtinus slapukus: prisijungimo sesijai išlaikyti ir, jei įjungėte prisijungimą su kodu ir pažymėjote „Pasitikėti šiuo įrenginiu“,
     kad tame įrenginyje') ?> <?= (int) TRUSTED_DEVICE_DAYS ?> <?= te('dienų nereikėtų kodo. Reklamos ar sekimo slapukų nenaudojame.') ?></p>
  <p><?= te('Pasirinkta svetainės kalba (LT / EN / RU) įsimenama slapuke „kalba“ 1 metams.') ?></p>
  <p><?= te('Įdiegus svetainę kaip programėlę telefone, naršyklė įsimena tik svetainės dizaino failus (be jūsų duomenų), kad ji greičiau atsidarytų.') ?></p>
  <p><?= te('Svetainės šriftai įkeliami iš „Google Fonts“, o vaizdo įrašai rodomi per „YouTube“ privatumo režimu (youtube-nocookie.com).
     Įkeliant šį turinį jūsų naršyklė susisiekia su „Google“ serveriais ir perduoda jūsų IP adresą.') ?></p>

  <h2><?= te('7. Jūsų teisės') ?></h2>
  <p><?= te('Turite teisę:') ?></p>
  <ul>
    <li><?= te('sužinoti, kokius duomenis apie jus tvarkome, ir gauti jų kopiją;') ?></li>
    <li><?= te('prašyti ištaisyti netikslius duomenis;') ?></li>
    <li><?= te('prašyti ištrinti duomenis, pavyzdžiui, nutraukus narystę;') ?></li>
    <li><?= te('atšaukti sutikimą, pavyzdžiui, dėl nuotraukų;') ?></li>
    <li><?= te('nesutikti su duomenų tvarkymu arba prašyti jį apriboti.') ?></li>
  </ul>
  <p><?= te('Rašykite') ?> <a href="mailto:info@karateka.lt">info@karateka.lt</a> <?= te('- atsakysime per 30 dienų.
     Jei manote, kad jūsų teisės pažeistos, galite kreiptis į Valstybinę duomenų apsaugos inspekciją (') ?><a href="https://vdai.lrv.lt" target="_blank" rel="noopener">vdai.lrv.lt</a>).</p>

  <h2><?= te('8. Kaip saugome duomenis') ?></h2>
  <p><?= te('Svetainė veikia per šifruotą HTTPS ryšį. Slaptažodžiai saugomi tik užšifruoti. Narių duomenis mato tik prisijungę treneriai,
     jų paskyroms galima įjungti prisijungimą su papildomu kodu el. paštu. Kasdien daroma atsarginė duomenų bazės kopija.') ?></p>

  <h2><?= te('9. Pakeitimai') ?></h2>
  <p><?= te('Pasikeitus šiai politikai, atnaujinsime datą viršuje. Apie esminius pakeitimus pranešime nariams el. paštu.') ?></p>
</article>
<?php
page_end();
