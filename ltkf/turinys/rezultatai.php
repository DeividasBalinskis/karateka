<?php
// Rezultatai renginiams, kurie vyko ne per varžybų sistemą (arba archyvas).
// Vardai - pavyzdiniai. Vaikų rezultatuose rodomas vardas ir pavardės raidė (sprendimas - susitikime).
return [
    'rudens-turnyras-2026' => [
        'pavadinimas' => 'LTKF rudens turnyras',
        'data'        => '2026-10-10',
        'pavyzdys'    => true,
        'klubai'      => [   // medalių lentelė: aukso, sidabro, bronzos
            ['klubas' => 'Klubas A', 'a' => 6, 's' => 4, 'b' => 5],
            ['klubas' => 'Klubas B', 'a' => 4, 's' => 5, 'b' => 3],
            ['klubas' => 'Klubas C', 'a' => 2, 's' => 2, 'b' => 6],
            ['klubas' => 'Klubas D', 'a' => 1, 's' => 2, 'b' => 2],
        ],
        'kategorijos' => [
            ['pavadinimas' => 'Kata M 8–7 kyu, 2016–2015 m.', 'vietos' => [['Vardenis P.', 'Klubas A'], ['Jonas J.', 'Klubas B'], ['Tomas T.', 'Klubas C'], ['Matas M.', 'Klubas A']]],
            ['pavadinimas' => 'Kumite F 6 kyu+, 2013–2011 m.', 'vietos' => [['Ona O.', 'Klubas B'], ['Ieva I.', 'Klubas A'], ['Rūta R.', 'Klubas D'], ['Gabija G.', 'Klubas C']]],
            ['pavadinimas' => 'Fukugo M 3 kyu+, 2009 m. ir vyresni', 'vietos' => [['Vardenis V.', 'Klubas A'], ['Paulius P.', 'Klubas C'], ['Lukas L.', 'Klubas B']]],
            ['pavadinimas' => 'Kumite komandos M 3 kyu+', 'vietos' => [['Klubas A', ''], ['Klubas B', ''], ['Klubas C', '']]],
        ],
    ],
];
