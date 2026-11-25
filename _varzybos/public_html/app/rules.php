<?php
// Tradicinio karate varžybų sąvokos ir numatytosios taisyklės

// Diržai - tokia pati skalė kaip karateka.lt (members.belt_level)
const BELTS = [
    1  => ['9 kyu', 'baltas',    '#F4F1EA'],
    2  => ['8 kyu', 'geltonas',  '#F0CB4E'],
    3  => ['7 kyu', 'oranžinis', '#E6842B'],
    4  => ['6 kyu', 'žalias',    '#4C8353'],
    5  => ['5 kyu', 'mėlynas',   '#2E6FA8'],
    6  => ['4 kyu', 'mėlynas',   '#2E6FA8'],
    7  => ['3 kyu', 'rudas',     '#6B4A34'],
    8  => ['2 kyu', 'rudas',     '#6B4A34'],
    9  => ['1 kyu', 'rudas',     '#6B4A34'],
    10 => ['1 dan', 'juodas',    '#15110D'],
    11 => ['2 dan', 'juodas',    '#15110D'],
    12 => ['3 dan', 'juodas',    '#15110D'],
    13 => ['4 dan', 'juodas',    '#15110D'],
    14 => ['5 dan', 'juodas',    '#15110D'],
    15 => ['6 dan', 'juodas',    '#15110D'],
    16 => ['7 dan', 'juodas',    '#15110D'],
    17 => ['8 dan', 'juodas',    '#15110D'],
    18 => ['9 dan', 'juodas',    '#15110D'],
];

const DISCIPLINES = [
    'kata'        => 'Kata',
    'kumite'      => 'Kumite',
    'fukugo'      => 'Fuku-go',
    'enbu'        => 'En-bu',
    'kihon'       => 'Kihon',
    'kata_team'   => 'Komandinė kata',
    'kumite_team' => 'Komandinis kumite',
];

// Kiek žmonių sudaro vieną dalyvį
const DISCIPLINE_ENTRY = [
    'kata'        => 'individual',
    'kumite'      => 'individual',
    'fukugo'      => 'individual',
    'kihon'       => 'individual',
    'enbu'        => 'pair',
    'kata_team'   => 'team',
    'kumite_team' => 'team',
];

const FORMATS = [
    'bracket'     => 'Atkrintamosios (lentelė)',
    'round_robin' => 'Kiekvienas su kiekvienu',
    'scores'      => 'Balai ratais',
];

const MATCH_TYPES = [
    'kumite' => 'Kumite (ippon / waza-ari)',
    'flags'  => 'Vėliavėlės',
    'fukugo' => 'Fuku-go (kata + kumite)',
    'team'   => 'Komandinė kova',
];

// Kumite: kiek waza-ari vienetų reikia pergalei (ippon = 2 waza-ari)
const KUMITE_RULES = [
    'ippon'  => ['label' => 'Shobu Ippon (1 ippon / 2 waza-ari)', 'target' => 2],
    'sanbon' => ['label' => 'Shobu Sanbon (3 ippon / 6 waza-ari)', 'target' => 6],
    'nihon'  => ['label' => 'Nihon (2 ippon / 4 waza-ari)', 'target' => 4],
];

const THIRD_PLACE = [
    'two_bronze' => 'Dvi bronzos (be kovos)',
    'match'      => 'Kova dėl 3 vietos',
    'repechage'  => 'Repechage (paguodos kovos)',
    'none'       => 'Tik 1 ir 2 vieta',
];

const RESULT_LABELS = [
    'points'   => 'Taškai',
    'hantei'   => 'Hantei',
    'hansoku'  => 'Hansoku',
    'shikkaku' => 'Shikkaku',
    'kiken'    => 'Kiken',
    'flags'    => 'Vėliavėlės',
    'bye'      => 'Be kovos',
    'draw'     => 'Lygiosios',
];

const TOURNAMENT_STATUSES = [
    'draft'        => 'Ruošiamos',
    'registration' => 'Vyksta registracija',
    'closed'       => 'Registracija baigta',
    'running'      => 'Vyksta',
    'finished'     => 'Baigtos',
];

/** Kiek vidutiniškai trunka vienas "darbas" kategorijoje (minutėmis) - tvarkaraščio skaičiavimui */
function minutes_per_item(array $cat): float
{
    if ($cat['format'] === 'scores') {
        return $cat['discipline'] === 'kata_team' ? 4.0 : ($cat['discipline'] === 'enbu' ? 3.0 : 2.0);
    }
    switch ($cat['match_type']) {
        case 'flags':
            return 3.0;
        case 'fukugo':
            return 4.0 + (int) $cat['match_seconds'] / 60;
        case 'team':
            return 3 * ((int) $cat['match_seconds'] / 60 + 1.5);
        default:
            return (int) $cat['match_seconds'] / 60 + 1.5;
    }
}

/** Pagal discipliną parenkamas formatas ir kovos tipas, jei nenurodyta */
function default_format_for(string $discipline): array
{
    switch ($discipline) {
        case 'kumite':
            return ['format' => 'bracket', 'match_type' => 'kumite'];
        case 'fukugo':
            return ['format' => 'bracket', 'match_type' => 'fukugo'];
        case 'kumite_team':
            return ['format' => 'bracket', 'match_type' => 'team'];
        case 'kihon':
            return ['format' => 'bracket', 'match_type' => 'flags'];
        default:   // kata, enbu, kata_team
            return ['format' => 'scores', 'match_type' => 'flags'];
    }
}
