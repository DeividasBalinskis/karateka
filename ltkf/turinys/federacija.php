<?php
// Federacijos informacija: valdyba, rekvizitai, teisėjų lygiai
return [
    'valdyba' => [
        ['pareigos' => 'Prezidentas', 'vardas' => '[vardas, pavardė]'],
        ['pareigos' => 'Viceprezidentas', 'vardas' => '[vardas, pavardė]'],
        ['pareigos' => 'Generalinis sekretorius', 'vardas' => '[vardas, pavardė]'],
        ['pareigos' => 'Teisėjų komisijos pirmininkas', 'vardas' => '[vardas, pavardė]'],
        ['pareigos' => 'Techninės komisijos pirmininkas', 'vardas' => '[vardas, pavardė]'],
    ],
    'rekvizitai' => [
        'Pavadinimas'         => 'Lietuvos tradicinio karatė-do federacija [patikslinti pagal registrą]',
        'Juridinio asmens kodas' => '[kodas]',
        'Adresas'             => '[adresas]',
        'Banko sąskaita'      => '[LT00 0000 0000 0000 0000]',
        'Tarptautinė narystė' => '[pvz. WTKU / ITKF - patikslinti]',
    ],
    'teiseju_lygiai' => [
        ['lygis' => 'Kandidatas', 'kas' => 'Baigė seminarą, teisėjauja kartu su patyrusiu teisėju.'],
        ['lygis' => 'Nacionalinė C', 'kas' => 'Išlaikė testą, teisėjauja vaikų ir jaunių kategorijose.'],
        ['lygis' => 'Nacionalinė B', 'kas' => 'Teisėjauja visose kategorijose, gali būti tatamio vyr. teisėjas.'],
        ['lygis' => 'Nacionalinė A', 'kas' => 'Vyriausiasis varžybų teisėjas, veda seminarus.'],
    ],
];
