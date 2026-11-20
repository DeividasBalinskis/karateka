<?php
// Federacijos klubai. 'lat'/'lng' - žemėlapiui (be jų klubas rodomas tik sąraše).
// LTKF klubai (Denis, 2026-11): Karateka, Sanrei, Takami, Vilniaus karate mokykla, Karate akademija. Karateka - tikri duomenys, kitų - miestą ir trenerį patikslins Denis.
// Šie pavadinimai naudojami ir varžybų sistemoje (karateka.lt: app/tournaments.php) kaip klubų sąrašas.
return [
    // Karateka: narių skaičius ir treniruočių vietos - gyvai iš karateka.lt (inc/pagalbines.php: karateka_klubas)
    ['id' => 'karateka', 'pavadinimas' => 'Karateka', 'juridinis' => 'VšĮ Karate Ateitis', 'miestas' => 'Vilnius', 'svetaine' => 'https://karateka.lt',
     'treneris' => 'Denis Balinskis, 3 Dan', 'el_pastas' => 'info@karateka.lt', 'telefonas' => '+370 601 44392', 'nuo' => 2017, 'lat' => 54.7187, 'lng' => 25.2203],
    ['id' => 'sanrei', 'pavadinimas' => 'Sanrei', 'miestas' => '[miestas]', 'svetaine' => '', 'treneris' => '[vyr. treneris]', 'pavyzdys' => true],
    ['id' => 'takami', 'pavadinimas' => 'Takami', 'miestas' => '[miestas]', 'svetaine' => '', 'treneris' => '[vyr. treneris]', 'pavyzdys' => true],
    ['id' => 'vilniaus-karate-mokykla', 'pavadinimas' => 'Vilniaus karate mokykla', 'miestas' => 'Vilnius', 'svetaine' => '', 'treneris' => '[vyr. treneris]', 'lat' => 54.7050, 'lng' => 25.2500, 'pavyzdys' => true],
    ['id' => 'karate-akademija', 'pavadinimas' => 'Karate akademija', 'miestas' => '[miestas]', 'svetaine' => '', 'treneris' => '[vyr. treneris]', 'pavyzdys' => true],
];
