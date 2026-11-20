<?php
require __DIR__ . '/inc/isdestymas.php';

puslapis_pradzia('Klubams: narystė', 'klubai');
?>
<section class="page-head"><div class="wrap">
  <p class="eyebrow">Klubams</p>
  <h1>Narystė federacijoje</h1>
  <p class="lead">Federacija priklauso visiems jos klubams. Kiekvienas klubas turi balsą, o jo sportininkai – vienodas sąlygas varžybose.</p>
</div></section>

<section class="wrap section">
  <div class="benefits">
    <div class="benefit"><h3>Varžybos</h3><p>Sportininkai dalyvauja federacijos varžybose ir čempionate, registracija – per varžybų sistemą.</p></div>
    <div class="benefit"><h3>Teisėjai</h3><p>Klubo teisėjai mokosi seminaruose ir gauna nacionalinę kvalifikaciją.</p></div>
    <div class="benefit"><h3>Egzaminai</h3><p>Viena kyu ir dan egzaminų programa visoje Lietuvoje.</p></div>
    <div class="benefit"><h3>Rinktinė</h3><p>Geriausi sportininkai atrenkami į tarptautines varžybas.</p></div>
  </div>

  <h2 class="mt">Kaip įstoti</h2>
  <ol class="steps-list">
    <li><b>Parašykite mums</b><span>Klubo pavadinimas, miestas, vyr. treneris, narių skaičius – <?= e(n('el_pastas')) ?>.</span></li>
    <li><b>Užpildykite prašymą</b><span>Prašymo forma ir įstatai – skiltyje Dokumentai.</span></li>
    <li><b>Valdyba patvirtina</b><span>Sprendimas per [30] dienų.</span></li>
    <li><b>Metinis mokestis</b><span>[suma] € per metus. [patikslinti]</span></li>
    <li><b>Prieiga prie varžybų sistemos</b><span>Klubas gauna savo prisijungimą ir registruoja sportininkus pats.</span></li>
  </ol>
  <p class="note">Klubai, naudojantys narių sistemą (pvz. karateka.lt), registruoja sportininkus tiesiai iš savo narių sąrašo. Varžyboms perduodami tik būtini duomenys: vardas, pavardė, gimimo data, lytis, diržas, klubas.</p>
</section>
<?php puslapis_pabaiga();
