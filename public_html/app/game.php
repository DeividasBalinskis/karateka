<?php
// Žaidimas „Mano dojo“ (db/035): kiekvienas narys (vaikas) ir treneris / adminas turi personažą - vieną iš 10 gyvūnų arba žmogų.
// Lygis - iš tikro XP (treniruotės, renginiai, egzaminai), diržo spalva - tikras diržas. Keliant lygį atsirakina daiktai, už kiekvieną lygį
// ir renginį - dovanų dėžė (atsitiktinis daiktas). Pinigų nėra. Daiktai, aplinka, namas ir dekoracijos prideda savybių (jėga, vikrumas,
// nuojauta, ištvermė), kurios svarbios „Arenoje“ (jėgos kovose). Draugiškose kovose visi lygūs - laimi gudrumas.
// Paveikslėliai piešiami naršyklėje (assets/game-art.js) pagal tuos pačius raktus.

const GAME_SPECIES = [
    'tiger'  => ['Tigras', '🐯', '#E8892B'],
    'lion'   => ['Liūtas', '🦁', '#D9A441'],
    'panda'  => ['Panda', '🐼', '#F4F2EE'],
    'fox'    => ['Lapė', '🦊', '#E0712C'],
    'wolf'   => ['Vilkas', '🐺', '#8A929C'],
    'cat'    => ['Katė', '🐱', '#C98A55'],
    'rabbit' => ['Kiškis', '🐰', '#E9E2D8'],
    'monkey' => ['Beždžionė', '🐵', '#8C5A3C'],
    'eagle'  => ['Erelis', '🦅', '#6E4B2E'],
    'dragon' => ['Drakonas', '🐲', '#3FA37A'],
    'human'  => ['Žmogus', '🧑', '#F1C7A5'],
    // Mistiniai ir reti: atsirakina pasiekus lygį arba randami dovanų dėžėse (žetonas „sp_…“)
    'redpanda'    => ['Raudonoji panda', '🦝', '#C8562A'],
    'kitsune'     => ['Kitsunė (dvasių lapė)', '🦊', '#F7F1E8'],
    'yeti'        => ['Jetis', '❄️', '#F2F4F7'],
    'tengu'       => ['Tengu (kalnų dvasia)', '🪶', '#2F3142'],
    'unicorn'     => ['Vienaragis', '🦄', '#FBF8FF'],
    'phoenixbird' => ['Feniksas', '🔥', '#E8501A'],
];
// Pikseliniai personažai (žaidime rodomi šie): [vardas, į kurią pusę žiūri: r - dešinėn, l - kairėn, c - į priekį].
// 'kitsune' - perdažoma pikselinė lapė (kailis, liepsnos, kimono, diržas); kiti - paveikslėliai img/zaidimas/herojai/<raktas>.webp
const GAME_PX_CHARS = [
    'kitsune' => ['Kitsunė (perdažoma)', 'l'],
    'crane' => ['Šventoji gervė', 'r'],
    'swolf' => ['Dvasių vilkas', 'r'],
    'gdragon' => ['Auksinis drakonas', 'r'],
    'kitsune9' => ['Devynuodegė kitsunė', 'r'],
    'frog' => ['Varlė vienuolė', 'l'],
    'ram' => ['Kalnų išminčius', 'c'],
    'jelly' => ['Medūza', 'c'],
    'deer' => ['Dvasių elnias', 'r'],
    'scat' => ['Šešėlių katė', 'r'],
    'turtle' => ['Vėžlys meistras', 'r'],
    'phoenix' => ['Feniksas', 'r'],
    'shark' => ['Ryklys', 'r'],
    'ghost' => ['Dvasia', 'c'],
    'lava' => ['Lavos golemas', 'c'],
    'wdragon' => ['Vandens drakonas', 'r'],
    'moonwolf' => ['Mėnulio vilkė', 'c'],
    'moth' => ['Naktinis drugys', 'c'],
    'tree' => ['Medžio dvasia', 'c'],
    'stone' => ['Akmens golemas', 'c'],
    'oni' => ['Oni', 'c'],
    'weasel' => ['Sniego šeškas', 'r'],
    'whale' => ['Kosminis banginis', 'r'],
];
// Kurie personažai užrakinti: lygis, nuo kurio atsirakina, arba 0 - tik iš dovanų dėžės (retas). [lygis, retumas]
const GAME_SPECIES_LOCK = ['redpanda' => [8, 2], 'kitsune' => [14, 3], 'yeti' => [20, 3], 'tengu' => [0, 3], 'unicorn' => [0, 4], 'phoenixbird' => [0, 4]];
// Laisvai pasirenkamos spalvos (kailis / oda, akys, plaukai); ypatingos - daiktai „paint“
const GAME_FUR = ['#E8892B', '#D9A441', '#F4F2EE', '#E0712C', '#8A929C', '#C98A55', '#E9E2D8', '#8C5A3C', '#6E4B2E', '#3FA37A', '#3B3A3F', '#B8B2A7', '#F0C9A0', '#7A5BA6', '#4F86C6'];
// Pikselinės lapės liepsnų spalvos (pirma - originali žydra)
const GAME_FLAME = ['#62C6DA', '#6EDC8C', '#FFD65C', '#FF9A3C', '#FF5A6E', '#F27FD0', '#B46CFF', '#F2F2F2'];
const GAME_SKIN = ['#F7D7C0', '#F1C7A5', '#E0AC86', '#C68A62', '#9C6644', '#6E4630'];
const GAME_EYES = ['#5B3A1E', '#2F6FA8', '#3E8E5A', '#7A5BA6', '#C88A1E', '#2B2B2B', '#C2410C'];
const GAME_HAIR = ['#2B1D14', '#5A3A22', '#8C5A2B', '#D9A441', '#E8D9B0', '#B8432F', '#1F2A44', '#F2F2F2'];
const GAME_STATS = ['str' => 'Jėga', 'agi' => 'Vikrumas', 'int' => 'Nuojauta', 'end' => 'Ištvermė'];
const GAME_RARITY = [1 => 'Paprastas', 2 => 'Retas', 3 => 'Epinis', 4 => 'Legendinis'];
const GAME_SLOTS = ['weapon' => 'Ginklas', 'kimono' => 'Kimono', 'belt' => 'Diržo puošmena', 'head' => 'Galva', 'face' => 'Veidas', 'neck' => 'Kaklas',
                    'hands' => 'Rankos', 'back' => 'Nugara', 'aura' => 'Aura', 'hair' => 'Šukuosena', 'paint' => 'Spalva', 'bg' => 'Aplinka', 'house' => 'Namas',
                    'decor' => 'Dekoracija', 'egg' => 'Kiaušinis', 'species' => 'Personažas'];
// Personažų gebėjimai (tik jėgos kovose): kiekvienas personažas turi savo. Tikimybė suveikti per ėjimą - 10 %,
// ir +1 % už kas 2 pergales su tuo personažu (iki 25 %) - kuo daugiau kovoji su juo, tuo jis stipresnis.
const GAME_SKILLS = [
    'pounce'  => ['Šuolis', 'Smūgis 60 % stipresnis'],
    'counter' => ['Atkirtis', 'Užblokavęs atsako dvigubai stipriau'],
    'evade'   => ['Šešėlis', 'Visiškai išvengia smūgio'],
    'heal'    => ['Gydymas', 'Atgauna 4–8 gyvybių'],
    'pierce'  => ['Kiaurai', 'Smūgio negalima užblokuoti'],
    'drain'   => ['Siurbimas', 'Atgauna pusę padarytos žalos'],
    'burn'    => ['Ugnis', 'Papildomai 3–7 žalos'],
    'thorns'  => ['Spygliai', 'Grąžina pusę gautos žalos'],
    'focus'   => ['Susikaupimas', 'Smūgis tikrai Waza-ari'],
    'stone'   => ['Akmuo', 'Gauta žala perpus mažesnė'],
];
const GAME_CHAR_SKILL = [
    'kitsune' => 'focus', 'wtiger' => 'pounce', 'raven' => 'evade', 'crane' => 'heal', 'swolf' => 'drain', 'gdragon' => 'burn',
    'kitsune9' => 'evade', 'frog' => 'counter', 'ram' => 'stone', 'jelly' => 'thorns', 'deer' => 'heal', 'scat' => 'evade',
    'turtle' => 'stone', 'phoenix' => 'burn', 'panda' => 'counter', 'shark' => 'drain', 'ghost' => 'pierce', 'lava' => 'burn',
    'wdragon' => 'pierce', 'moonwolf' => 'pounce', 'moth' => 'drain', 'tree' => 'thorns', 'stone' => 'stone', 'oni' => 'counter',
    'weasel' => 'pierce', 'whale' => 'heal',
];
const GAME_SKILL_BASE = 10;
const GAME_SKILL_MAX = 25;

/** Personažo gebėjimas ir tikimybė pagal pergales su juo: ['code','n','d','p','wins'] */
function game_skill(array $hero): array
{
    $code = GAME_CHAR_SKILL[$hero['species']] ?? 'focus';
    $wins = (int) (($hero['char_wins'] ?? [])[$hero['species']] ?? 0);
    return ['code' => $code, 'n' => t(GAME_SKILLS[$code][0]), 'd' => t(GAME_SKILLS[$code][1]), 'wins' => $wins,
            'p' => min(GAME_SKILL_MAX, GAME_SKILL_BASE + intdiv($wins, 2))];
}

/** Pergalė su dabartiniu personažu - jo gebėjimas suveiks dažniau */
function game_char_win(int $heroId): void
{
    $h = game_hero_row($heroId);
    if (!$h || !array_key_exists('char_wins', $h)) {
        return;   // dar be db/039
    }
    $cw = $h['char_wins'];
    $cw[$h['species']] = (int) ($cw[$h['species']] ?? 0) + 1;
    q('UPDATE game_heroes SET char_wins = ? WHERE id = ?', [json_encode($cw), $heroId]);
}

// Gyvūnėliai: [vardas, ženkliukas, gebėjimas kovoje, galima pasirinkti pradžioje]
const GAME_PETS = [
    'kitten'     => ['Kačiukas', '🐱', 'scratch', true],
    'puppy'      => ['Šuniukas', '🐶', 'guard', true],
    'foxcub'     => ['Lapiukas', '🦊', 'trick', true],
    'bunny'      => ['Kiškutis', '🐰', 'dodge', true],
    'turtle'     => ['Vėžliukas', '🐢', 'shield', true],
    'pandacub'   => ['Pandukas', '🐼', 'heal', true],
    'owlet'      => ['Pelėdžiukas', '🦉', 'reveal', false],
    'dragonling' => ['Drakoniukas', '🐉', 'fire', false],
    'axolotl'    => ['Aksolotlis', '🦎', 'regen', false],
    'phoenix'    => ['Feniksiukas', '🔥', 'revive', false],
];
const GAME_PET_ABILITY = [
    'scratch' => 'Įdrėskia priešininkui (2-5 žalos)', 'guard' => 'Kartais pagauna smūgį už šeimininką', 'trick' => 'Suklaidina - priešininko smūgis gali nepataikyti',
    'dodge' => 'Šeimininkas dažniau išsisuka', 'shield' => 'Kartais sumažina žalą per pusę', 'heal' => 'Kartais pagydo šeimininką',
    'reveal' => 'Kartais išduoda, kur priešininkas smūgiuos', 'fire' => 'Spjauna ugnimi (3-7 žalos)', 'regen' => 'Kas ėjimą atgauna gyvybių',
    'revive' => 'Vieną kartą prikelia, kai gyvybės baigiasi',
];

/** Visas daiktų katalogas [raktas => ['slot','n','r','lvl','s'=>[str,agi,int,end,arm],'d'=>[min,max],'a'=>[...]]]. lvl 0 - tik iš dovanų dėžių. */
function game_items(): array
{
    static $all = null;
    if ($all !== null) {
        return $all;
    }
    $all = [];
    $add = function (string $k, string $slot, string $name, int $r, int $lvl, array $s = [], array $a = [], ?array $d = null) use (&$all) {
        $all[$k] = ['slot' => $slot, 'n' => $name, 'r' => $r, 'lvl' => $lvl, 's' => array_replace([0, 0, 0, 0, 0], $s), 'a' => $a] + ($d ? ['d' => $d] : []);
    };
    // ---------- Ginklai (žala min-max) ----------
    $add('w_bo', 'weapon', 'Bo lazda', 1, 2, [], ['t' => 'bo', 'c' => '#A9743F'], [3, 6]);
    $add('w_tonfa', 'weapon', 'Tonfos', 1, 3, [0, 1], ['t' => 'tonfa', 'c' => '#8A5A33'], [3, 6]);
    $add('w_nunchaku', 'weapon', 'Nunčakai', 1, 4, [0, 1], ['t' => 'nunchaku', 'c' => '#5B3A22'], [3, 7]);
    $add('w_sai', 'weapon', 'Sai', 2, 5, [0, 1, 1], ['t' => 'sai', 'c' => '#B9C2CC'], [4, 7]);
    $add('w_bokken', 'weapon', 'Bokenas', 2, 6, [1], ['t' => 'bokken', 'c' => '#B7864F'], [4, 8]);
    $add('w_jo', 'weapon', 'Jo lazda', 2, 7, [0, 0, 0, 1], ['t' => 'jo', 'c' => '#9C6A3C'], [4, 7]);
    $add('w_kama', 'weapon', 'Kama', 2, 8, [1, 1], ['t' => 'kama', 'c' => '#C9D1D9'], [5, 8]);
    $add('w_tessen', 'weapon', 'Tessenas (vėduoklė)', 2, 9, [0, 0, 2], ['t' => 'tessen', 'c' => '#B4436F'], [3, 7]);
    $add('w_bo_iron', 'weapon', 'Plieninė bo', 2, 0, [1], ['t' => 'bo', 'c' => '#7D8894', 'm' => 1], [5, 9]);
    $add('w_bokken_sakura', 'weapon', 'Sakuros bokenas', 2, 0, [0, 0, 0, 1], ['t' => 'bokken', 'c' => '#E8A0B8'], [5, 8]);
    $add('w_nunchaku_flame', 'weapon', 'Liepsnos nunčakai', 3, 0, [0, 2], ['t' => 'nunchaku', 'c' => '#E8590C', 'fx' => 'fire'], [5, 9]);
    $add('w_sai_ice', 'weapon', 'Ledo sai', 3, 0, [0, 0, 2], ['t' => 'sai', 'c' => '#9BD8F2', 'fx' => 'ice'], [5, 9]);
    $add('w_kama_shadow', 'weapon', 'Šešėlių kama', 3, 0, [1, 1, 1], ['t' => 'kama', 'c' => '#5B3F8C', 'fx' => 'shadow'], [5, 10]);
    $add('w_katana_gold', 'weapon', 'Auksinė katana', 4, 0, [2, 0, 1], ['t' => 'katana', 'c' => '#E5B53A', 'fx' => 'gold'], [7, 11]);
    $add('w_dragon_staff', 'weapon', 'Drakono lazda', 4, 0, [1, 1, 1, 1], ['t' => 'bo', 'c' => '#2E8B57', 'fx' => 'dragon'], [6, 12]);
    // ---------- Kimono (spalva, kraštas, raštas; apsauga) ----------
    $add('k_white', 'kimono', 'Baltas kimono', 1, 1, [], ['c' => '#F7F5EF', 'tr' => '#E4DFD3']);
    $add('k_blue', 'kimono', 'Mėlynas kimono', 1, 2, [0, 0, 0, 0, 1], ['c' => '#2E5FA8', 'tr' => '#244C88']);
    $add('k_red', 'kimono', 'Raudonas kimono', 1, 3, [1, 0, 0, 0, 1], ['c' => '#C8372D', 'tr' => '#9E2A22']);
    $add('k_green', 'kimono', 'Žalias kimono', 1, 4, [0, 0, 0, 1, 1], ['c' => '#3E8E5A', 'tr' => '#2F6E45']);
    $add('k_black', 'kimono', 'Juodas kimono', 2, 5, [1, 0, 0, 0, 1], ['c' => '#24232A', 'tr' => '#111015']);
    $add('k_purple', 'kimono', 'Violetinis kimono', 2, 6, [0, 0, 1, 0, 1], ['c' => '#6B4BA0', 'tr' => '#523782']);
    $add('k_orange', 'kimono', 'Oranžinis kimono', 2, 7, [0, 1, 0, 0, 1], ['c' => '#E8822B', 'tr' => '#C2651A']);
    $add('k_white_gold', 'kimono', 'Baltas su auksu', 2, 0, [0, 0, 1, 0, 2], ['c' => '#F7F5EF', 'tr' => '#D4A62A']);
    $add('k_black_red', 'kimono', 'Juodas su raudonu', 2, 0, [1, 0, 0, 0, 2], ['c' => '#24232A', 'tr' => '#C8372D']);
    $add('k_sakura', 'kimono', 'Sakurų kimono', 2, 0, [0, 1, 0, 0, 1], ['c' => '#F9E1EA', 'tr' => '#D9708F', 'p' => 'sakura']);
    $add('k_emerald', 'kimono', 'Smaragdinis su sakuromis', 2, 0, [0, 1, 0, 1, 1], ['c' => '#3EB680', 'tr' => '#F0BA40', 'p' => 'sakura']);
    $add('k_tiger', 'kimono', 'Tigro dryžiai', 2, 0, [1, 0, 0, 0, 1], ['c' => '#E8892B', 'tr' => '#2B1D14', 'p' => 'stripes']);
    $add('k_camo', 'kimono', 'Kamufliažas', 2, 0, [0, 1, 0, 0, 2], ['c' => '#6B7A4B', 'tr' => '#4A5533', 'p' => 'camo']);
    $add('k_dragon', 'kimono', 'Drakono kimono', 3, 0, [1, 0, 1, 0, 2], ['c' => '#1F3B2E', 'tr' => '#D4A62A', 'p' => 'dragon']);
    $add('k_flame', 'kimono', 'Liepsnų kimono', 3, 0, [2, 0, 0, 0, 2], ['c' => '#24232A', 'tr' => '#E8590C', 'p' => 'flame']);
    $add('k_ocean', 'kimono', 'Vandenyno bangos', 3, 0, [0, 1, 1, 0, 2], ['c' => '#E6F2F8', 'tr' => '#1E6F9E', 'p' => 'waves']);
    $add('k_star', 'kimono', 'Žvaigždėtas', 3, 0, [0, 0, 2, 0, 2], ['c' => '#1B2350', 'tr' => '#F2C14E', 'p' => 'stars']);
    $add('k_gold', 'kimono', 'Auksinis kimono', 4, 0, [1, 1, 1, 1, 3], ['c' => '#E5B53A', 'tr' => '#B8861B', 'p' => 'shine']);
    $add('k_shadow', 'kimono', 'Šešėlių kimono', 4, 0, [1, 2, 1, 0, 3], ['c' => '#18151F', 'tr' => '#8A5CFF', 'p' => 'glow']);
    // ---------- Diržo puošmena (spalva - visada tikro diržo) ----------
    $add('b_plain', 'belt', 'Paprastas mazgas', 1, 1, [], ['t' => 'plain']);
    $add('b_stripes', 'belt', 'Auksinės juostelės', 2, 5, [0, 0, 1], ['t' => 'stripes']);
    $add('b_name', 'belt', 'Siuvinėtas vardas', 2, 8, [0, 0, 0, 1], ['t' => 'name']);
    $add('b_flame', 'belt', 'Liepsnos galai', 3, 0, [1, 1], ['t' => 'flame']);
    $add('b_stars', 'belt', 'Žvaigždžių kutai', 3, 0, [0, 1, 1], ['t' => 'stars']);
    $add('b_dragon', 'belt', 'Drakono sagtis', 4, 0, [1, 1, 1, 1], ['t' => 'dragon']);
    // ---------- Galva, veidas, kaklas, rankos, nugara ----------
    $add('h_band_white', 'head', 'Baltas hačimakis', 1, 2, [0, 0, 0, 1], ['t' => 'band', 'c' => '#F7F5EF', 'm' => '#C8372D']);
    $add('h_band_red', 'head', 'Raudonas hačimakis', 1, 3, [1], ['t' => 'band', 'c' => '#C8372D', 'm' => '']);
    $add('h_band_sun', 'head', 'Tekančios saulės raištis', 2, 6, [1, 0, 1], ['t' => 'band', 'c' => '#F7F5EF', 'm' => 'sun']);
    $add('h_cap', 'head', 'Kepurėlė', 1, 0, [0, 1], ['t' => 'cap', 'c' => '#2E5FA8']);
    $add('h_flower', 'head', 'Gėlių vainikas', 2, 0, [0, 0, 1, 1], ['t' => 'flowers']);
    $add('h_ninja', 'head', 'Ninjos gobtuvas', 2, 10, [0, 2], ['t' => 'ninja', 'c' => '#24232A']);
    $add('h_kabuto', 'head', 'Samurajaus šalmas', 3, 0, [0, 0, 0, 2, 1], ['t' => 'kabuto', 'c' => '#3A2B2A', 'm' => '#D4A62A']);
    $add('h_crown', 'head', 'Karūna', 4, 0, [1, 1, 1, 1], ['t' => 'crown', 'c' => '#E5B53A']);
    $add('f_glasses', 'face', 'Akiniai nuo saulės', 2, 0, [0, 1], ['t' => 'glasses']);
    $add('f_paint', 'face', 'Kario dažai', 2, 0, [1], ['t' => 'paint', 'c' => '#C8372D']);
    $add('f_oni', 'face', 'Oni kaukė', 3, 0, [1, 0, 1], ['t' => 'oni']);
    $add('f_star', 'face', 'Žvaigždutės skruostuose', 1, 0, [0, 0, 1], ['t' => 'stars']);
    $add('n_bronze', 'neck', 'Bronzos medalis', 1, 3, [0, 0, 0, 1], ['t' => 'medal', 'c' => '#C77B3B']);
    $add('n_silver', 'neck', 'Sidabro medalis', 2, 6, [0, 0, 1, 1], ['t' => 'medal', 'c' => '#C0C6CE']);
    $add('n_gold', 'neck', 'Aukso medalis', 3, 10, [1, 0, 1, 1], ['t' => 'medal', 'c' => '#E5B53A']);
    $add('n_scarf', 'neck', 'Raudonas šalikas', 1, 0, [0, 1], ['t' => 'scarf', 'c' => '#C8372D']);
    $add('n_beads', 'neck', 'Juzu karoliai', 2, 0, [0, 0, 2], ['t' => 'beads', 'c' => '#6B3F2A']);
    $add('n_amulet', 'neck', 'Drakono amuletas', 4, 0, [1, 1, 1, 1], ['t' => 'amulet', 'c' => '#2E8B57']);
    $add('g_wraps', 'hands', 'Riešų apvijos', 1, 2, [1], ['t' => 'wraps', 'c' => '#F7F5EF']);
    $add('g_red', 'hands', 'Raudonos pirštinės', 1, 4, [1, 0, 0, 0, 1], ['t' => 'gloves', 'c' => '#C8372D']);
    $add('g_blue', 'hands', 'Mėlynos pirštinės', 1, 0, [0, 1, 0, 0, 1], ['t' => 'gloves', 'c' => '#2E5FA8']);
    $add('g_bracers', 'hands', 'Šarvuoti antrankiai', 3, 0, [0, 0, 0, 1, 2], ['t' => 'bracers', 'c' => '#7D8894']);
    $add('g_gold', 'hands', 'Auksinės pirštinės', 4, 0, [2, 1, 0, 0, 1], ['t' => 'gloves', 'c' => '#E5B53A']);
    $add('bk_bag', 'back', 'Kuprinė', 1, 3, [0, 0, 0, 1], ['t' => 'bag', 'c' => '#2F6B5A']);
    $add('bk_cape_red', 'back', 'Raudonas apsiaustas', 2, 0, [1], ['t' => 'cape', 'c' => '#B42318']);
    $add('bk_cape_blue', 'back', 'Mėlynas apsiaustas', 2, 0, [0, 1], ['t' => 'cape', 'c' => '#1E4E8C']);
    $add('bk_flag', 'back', 'Sašimono vėliavėlė', 3, 0, [1, 0, 1], ['t' => 'flag', 'c' => '#F7F5EF', 'm' => '#C8372D']);
    $add('bk_royal', 'back', 'Karališkas apsiaustas', 3, 0, [1, 0, 1, 1], ['t' => 'cape', 'c' => '#5B2A86', 'm' => '#E5B53A']);
    $add('bk_wings', 'back', 'Drakono sparnai', 4, 0, [1, 2, 1], ['t' => 'wings', 'c' => '#2E8B57']);
    $add('au_stars', 'aura', 'Žvaigždžių aura', 3, 0, [0, 0, 2], ['t' => 'stars', 'c' => '#F2C14E']);
    $add('au_sakura', 'aura', 'Sakurų sūkurys', 3, 0, [0, 2], ['t' => 'petals', 'c' => '#F2A3BC']);
    $add('au_fire', 'aura', 'Ugnies aura', 4, 0, [2, 1], ['t' => 'flame', 'c' => '#FF6A1A']);
    $add('au_ice', 'aura', 'Ledo aura', 4, 0, [0, 1, 2], ['t' => 'flame', 'c' => '#7FD3FF']);
    $add('au_storm', 'aura', 'Žaibų aura', 4, 0, [1, 2], ['t' => 'bolts', 'c' => '#B48CFF']);
    $add('au_gold', 'aura', 'Meistro aura', 3, 15, [1, 1, 1, 1], ['t' => 'flame', 'c' => '#F2C14E']);
    // ---------- Šukuosenos (žmogui - plaukai, gyvūnams - kuokštas) ----------
    foreach ([['hr_short', 'Trumpi plaukai', 1, 1], ['hr_spiky', 'Spygliuota', 1, 2], ['hr_pony', 'Arklio uodega', 1, 3], ['hr_bun', 'Kuodas', 1, 4],
              ['hr_long', 'Ilgi plaukai', 1, 5], ['hr_braids', 'Kasos', 2, 6], ['hr_mohawk', 'Irokezas', 2, 8], ['hr_topknot', 'Samurajaus kuodas', 2, 10],
              ['hr_afro', 'Garbanos', 2, 0], ['hr_twin', 'Dvi uodegėlės', 2, 0]] as [$k, $n, $r, $l]) {
        $add($k, 'hair', $n, $r, $l, [], ['t' => substr($k, 3)]);
    }
    // ---------- Ypatingos spalvos (kailiui ir plaukams) ----------
    foreach ([['pc_snow', 'Sniego baltumo', 2, '#FBFBFD'], ['pc_rose', 'Rožinė', 2, '#F2A3BC'], ['pc_mint', 'Mėtinė', 2, '#7FD8B5'], ['pc_sky', 'Dangaus', 2, '#7FB8F2'],
              ['pc_silver', 'Sidabrinė', 3, '#C9D1D9'], ['pc_shadow', 'Šešėlio', 3, '#2A2533'], ['pc_lava', 'Lavos', 3, '#E2552B'], ['pc_gold', 'Auksinė', 4, '#E5B53A'],
              ['pc_galaxy', 'Galaktikos', 4, '#4B3FA8']] as [$k, $n, $r, $c]) {
        $add($k, 'paint', $n . ' spalva', $r, 0, [], ['c' => $c]);
    }
    // ---------- Aplinka (fonai), namai ir dekoracijos - irgi prideda savybių ----------
    foreach ([['bg_dojo', 'Mėnesienos dojo', 1, 1, 'dojo', []], ['bg_nature', 'Bambukų giraitė', 1, 2, 'gamta', [0, 0, 0, 1]], ['bg_mountains', 'Kalnų krioklys', 1, 3, 'kalnai', [0, 0, 0, 1]],
              ['bg_sakura', 'Sakurų pagoda', 1, 4, 'sakura', [0, 0, 1]], ['bg_sea', 'Toris jūroje', 2, 5, 'jura', [0, 1]], ['bg_autumn', 'Rudens tiltelis', 2, 6, 'ruduo', [1]],
              ['bg_winter', 'Žiemos šventykla', 2, 7, 'ziema', [0, 0, 0, 2]], ['bg_dunes', 'Kopų oazė', 2, 9, 'kopos', [0, 1, 1]], ['bg_aurora', 'Šiaurės pašvaistė', 3, 0, 'pasvaiste', [0, 0, 2]],
              ['bg_volcano', 'Ugnikalnis', 3, 0, 'vulkanas', [2]], ['bg_space', 'Dvasių pasaulis', 4, 0, 'kosmosas', [1, 1, 1, 1]]] as [$k, $n, $r, $l, $sc, $s]) {
        $add($k, 'bg', $n, $r, $l, $s, ['sc' => $sc]);
    }
    foreach ([['hs_tent', 'Palapinė', 1, 1, []], ['hs_hut', 'Medinė trobelė', 1, 3, [0, 0, 0, 1]], ['hs_dojo', 'Mažasis dojo', 2, 5, [1]], ['hs_pagoda', 'Pagoda', 2, 8, [0, 0, 1]],
              ['hs_treehouse', 'Medžio namelis', 2, 0, [0, 1]], ['hs_igloo', 'Iglu', 2, 0, [0, 0, 0, 1]], ['hs_temple', 'Kalnų šventykla', 3, 0, [0, 0, 1, 1]],
              ['hs_castle', 'Pilis', 4, 0, [1, 1, 1, 1]]] as [$k, $n, $r, $l, $s]) {
        $add($k, 'house', $n, $r, $l, $s, ['t' => substr($k, 3)]);
    }
    foreach ([['d_tatami', 'Tatami kilimėlis', 1, 2, [0, 1]], ['d_makiwara', 'Makivara', 1, 2, [1]], ['d_bag', 'Bokso kriaušė', 1, 3, [1]], ['d_banner', 'Klubo vėliava', 1, 4, [0, 0, 0, 1]],
              ['d_lantern', 'Akmens žibintas', 1, 4, [0, 0, 1]], ['d_bonsai', 'Bonsai', 2, 5, [0, 0, 1]], ['d_koi', 'Koi tvenkinys', 2, 6, [0, 0, 0, 1]],
              ['d_weapons', 'Ginklų stovas', 2, 7, [1]], ['d_torii', 'Torii vartai', 2, 8, [0, 1]], ['d_trophy', 'Trofėjų lentyna', 3, 10, [1, 1, 1, 1]],
              ['d_campfire', 'Laužas', 1, 0, [0, 0, 0, 1]], ['d_bench', 'Suolelis', 1, 0, [0, 0, 0, 1]], ['d_flowers', 'Gėlių lysvė', 1, 0, [0, 0, 1]],
              ['d_snowman', 'Senis besmegenis', 1, 0, [0, 0, 0, 1]], ['d_pumpkin', 'Moliūgas', 1, 0, [0, 1]], ['d_shishi', 'Bambuko fontanas', 2, 0, [0, 1]],
              ['d_rocks', 'Zen akmenys', 2, 0, [0, 0, 0, 1]], ['d_tea', 'Arbatos stalelis', 2, 0, [0, 0, 1]], ['d_sakura_tree', 'Sakuros medelis', 2, 0, [0, 0, 1]],
              ['d_drum', 'Taiko būgnas', 2, 0, [0, 1]], ['d_crystal', 'Kristalas', 3, 0, [0, 0, 2]], ['d_statue', 'Auksinė statula', 4, 0, [1, 1, 1, 1]]] as [$k, $n, $r, $l, $s]) {
        $add($k, 'decor', $n, $r, $l, $s, ['t' => substr($k, 2)]);
    }
    // ---------- Mistiniai personažai (žetonas leidžia juo tapti) ----------
    foreach (GAME_SPECIES_LOCK as $sp => [$l, $r]) {
        $add('sp_' . $sp, 'species', GAME_SPECIES[$sp][0], $r, $l, [], ['sp' => $sp]);
    }
    // ---------- Kiaušiniai: išsiritę - retas gyvūnėlis ----------
    foreach (['owlet' => 3, 'dragonling' => 4, 'axolotl' => 3, 'phoenix' => 4] as $pet => $r) {
        $add('egg_' . $pet, 'egg', GAME_PETS[$pet][0] . ' (kiaušinis)', $r, 0, [], ['pet' => $pet]);
    }
    return $all;
}

/** Numatyti daiktai naujam personažui (visada turimi) */
const GAME_START_ITEMS = ['k_white', 'b_plain', 'hr_short', 'bg_dojo', 'hs_tent'];
const GAME_DECOR_MAX = 8;   // kiek dekoracijų skaičiuojasi savybėms
// Testavimo laikotarpis: visi personažai, daiktai, aplinkos, namai ir gyvūnėliai atrakinti visiems (be lygių ir dovanų dėžių).
// Kai dizainas bus baigtas - false, ir vėl veiks lygiai bei dovanų dėžės.
const GAME_UNLOCK_ALL = true;

// ---------- Personažas: kam priklauso, kas valdo ----------

/**
 * Kas mato žaidimą (Dojo, arena, žaidimai): site_setting game_access - „all“ (visi) arba JSON paskyrų id sąrašas.
 * Nenustatyta - niekas; db/043 nustato - tik Denio paskyra. Keičiama: Nustatymai → „🏯 Žaidimas“.
 */
function game_access_ids(): ?array
{
    $v = trim((string) site_setting('game_access', ''));
    if ($v === 'all') {
        return null;
    }
    if ($v === '') {
        return [];   // nenustatyta - nemato niekas (saugiau nei „visi“); atidaroma Nustatymuose
    }
    $ids = json_decode($v, true);
    return is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
}

/** Ar ši paskyra mato žaidimą (žiūrint „kaip vaikas“ - pagal tikrąją, administratoriaus, paskyrą) */
function game_allowed(?array $a = null): bool
{
    if (!game_ready()) {
        return false;
    }
    $a = $a ?? current_account();
    if (!$a) {
        return false;
    }
    $ids = game_access_ids();
    if ($ids === null) {
        return true;
    }
    $real = !empty($_SESSION['view_admin']) ? (int) $_SESSION['view_admin'] : (int) $a['id'];
    return in_array((int) $a['id'], $ids, true) || in_array($real, $ids, true);
}

function game_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT 1 FROM game_heroes LIMIT 1');
            $ok = true;
        } catch (PDOException $ex) {
            $ok = false;
        }
    }
    return $ok;
}

function game_hero_row(int $id): ?array
{
    $h = q_one('SELECT * FROM game_heroes WHERE id = ?', [$id]);
    return $h ? game_hero_decode($h) : null;
}

function game_hero_decode(array $h): array
{
    foreach (['look', 'equipped', 'home', 'alloc'] as $f) {
        $h[$f] = json_decode((string) $h[$f], true) ?: [];
    }
    if (array_key_exists('char_wins', $h)) {
        $h['char_wins'] = json_decode((string) $h['char_wins'], true) ?: [];
    }
    return $h;
}

/** Nario personažas (sukuriamas pirmą kartą) */
function game_hero_for_member(int $memberId): ?array
{
    $h = q_one('SELECT * FROM game_heroes WHERE member_id = ?', [$memberId]);
    if (!$h) {
        $m = q_one('SELECT * FROM members WHERE id = ?', [$memberId]);
        if (!$m) {
            return null;
        }
        $id = game_hero_create($m['first_name'], ['member_id' => $memberId], $m['gender'] ?? null);
        $h = q_one('SELECT * FROM game_heroes WHERE id = ?', [$id]);
    }
    return game_hero_decode($h);
}

/** Paskyros (trenerio, admino ar tėvų be savo nario) personažas */
function game_hero_for_account(array $a): array
{
    $h = q_one('SELECT * FROM game_heroes WHERE account_id = ?', [(int) $a['id']]);
    if (!$h) {
        $id = game_hero_create($a['first_name'], ['account_id' => (int) $a['id']], null);
        $h = q_one('SELECT * FROM game_heroes WHERE id = ?', [$id]);
    }
    return game_hero_decode($h);
}

function game_hero_create(string $name, array $owner, ?string $gender): int
{
    $free = array_values(array_diff(array_keys(GAME_SPECIES), array_keys(GAME_SPECIES_LOCK), ['human']));
    $species = $free[random_int(0, count($free) - 1)];   // atsitiktinis gyvūnas (ne žmogus ir ne mistinis) - pasirinks pats
    // Lytis: iš nario duomenų, o jei nežinoma - pagal vardo galūnę (-a, -ė - mergaitė); pakeisti galima redaktoriuje
    $g = $gender ? (strtoupper($gender[0]) === 'F' ? 'f' : 'm') : (preg_match('/(a|ė)$/u', mb_strtolower(trim($name))) ? 'f' : 'm');
    $look = ['fur' => GAME_SPECIES[$species][2], 'eyes' => GAME_EYES[0], 'hair' => GAME_HAIR[0], 'skin' => GAME_SKIN[1], 'setup' => 0];
    $eq = ['kimono' => 'k_white', 'belt' => 'b_plain', 'hair' => 'hr_short'];
    $home = ['bg' => 'bg_dojo', 'house' => 'hs_tent', 'decor' => []];
    q('INSERT INTO game_heroes (member_id, account_id, name, species, gender, look, equipped, home, alloc) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$owner['member_id'] ?? null, $owner['account_id'] ?? null, mb_substr($name ?: 'Herojus', 0, 40), $species, $g,
         json_encode($look), json_encode($eq), json_encode($home), json_encode(['str' => 0, 'agi' => 0, 'int' => 0, 'end' => 0])]);
    $id = (int) db()->lastInsertId();
    foreach (GAME_START_ITEMS as $k) {
        q('INSERT IGNORE INTO game_items (hero_id, item_key, source) VALUES (?, ?, "start")', [$id, $k]);
    }
    return $id;
}

/** Personažai, kuriuos valdo ši paskyra: savo / vaikų (nariai) ir paskyros personažas (treneriams ir adminams - visada) */
function game_my_heroes(array $a): array
{
    $out = [];
    $self = false;
    foreach (account_members((int) $a['id']) as $m) {
        if ($m['status'] === 'active') {
            $h = game_hero_for_member((int) $m['id']);
            if ($h) {
                $out[] = $h;
                $self = $self || $m['relation'] === 'self';
            }
        }
    }
    // Treneriui - savas herojus, nebent jis pats yra narys (tada jo nario herojus ir yra jo)
    if ((is_staff($a) && !$self) || !$out) {
        array_unshift($out, game_hero_for_account($a));
    }
    return $out;
}

function game_can_control(array $hero, ?array $a): bool
{
    if (!$a) {
        return false;
    }
    if ((int) ($hero['account_id'] ?? 0) === (int) $a['id']) {
        return true;
    }
    if ($hero['member_id'] && q_value('SELECT 1 FROM account_members WHERE account_id = ? AND member_id = ?', [(int) $a['id'], (int) $hero['member_id']])) {
        return true;
    }
    return false;
}

/** Rodomas vardas kitiems: vaikams - vardas ir pavardės raidė */
function game_public_name(array $hero): string
{
    if ($hero['member_id']) {
        $m = q_one('SELECT first_name, last_name, birth_date FROM members WHERE id = ?', [(int) $hero['member_id']]);
        if ($m) {
            $real = $m['first_name'] . ' ' . mb_substr($m['last_name'], 0, 1) . '.';
            return $hero['name'] === $m['first_name'] ? $real : $hero['name'] . ' (' . $real . ')';
        }
    }
    return $hero['name'];
}

// ---------- Lygis, diržas, savybės ----------

function game_level(array $hero): int
{
    if ($hero['test_level'] !== null && $hero['test_level'] !== '') {
        return max(1, (int) $hero['test_level']);
    }
    if ($hero['member_id']) {
        return xp_level(member_points_total((int) $hero['member_id']))['level'];
    }
    return 5;   // treneriai ir adminai - jau buvę renginiuose
}

function game_xp(array $hero): array
{
    if ($hero['member_id'] && ($hero['test_level'] === null || $hero['test_level'] === '')) {
        return xp_level(member_points_total((int) $hero['member_id']));
    }
    $lvl = game_level($hero);
    return ['level' => $lvl, 'xp' => ($lvl - 1) * xp_per_level(), 'into' => 0, 'need' => xp_per_level(), 'per' => xp_per_level(), 'pct' => 0];
}

function game_belt(array $hero): int
{
    if ($hero['test_belt'] !== null && $hero['test_belt'] !== '') {
        return max(1, min(18, (int) $hero['test_belt']));
    }
    if ($hero['member_id']) {
        $b = q_value('SELECT belt_level FROM members WHERE id = ?', [(int) $hero['member_id']]);
        return $b ? (int) $b : 1;
    }
    return 10;
}

/** Turimi daiktai [raktas => kiekis] */
function game_owned(int $heroId): array
{
    $own = array_map('intval', q('SELECT item_key, qty FROM game_items WHERE hero_id = ?', [$heroId])->fetchAll(PDO::FETCH_KEY_PAIR));
    if (GAME_UNLOCK_ALL) {
        // Viskas atrakinta (kiaušiniai - ne: gyvūnėlius galima priimti tiesiai)
        foreach (game_items() as $k => $it) {
            if ($it['slot'] !== 'egg') {
                $own[$k] = max($own[$k] ?? 0, $it['slot'] === 'decor' ? 3 : 1);
            }
        }
    }
    return $own;
}

/** Laisvi savybių taškai: 3 už kiekvieną lygį nuo 2-o */
function game_points(array $hero): int
{
    return max(0, (game_level($hero) - 1) * 3 - array_sum(array_map('intval', $hero['alloc'])));
}

/**
 * Savybės: bazė 3 + paskirstyti taškai + daiktai (užsidėti), aplinka, namas, dekoracijos (iki 8). Išvestinės: gyvybės, žala, apsauga.
 * Grąžina ['base' => [...], 'items' => [...], 'total' => [...], 'hp', 'dmg' => [min, max], 'arm', 'sources' => [...]]
 */
function game_stats(array $hero): array
{
    $cat = game_items();
    $owned = game_owned((int) $hero['id']);
    $keys = ['str', 'agi', 'int', 'end'];
    $tot = ['str' => 3, 'agi' => 3, 'int' => 3, 'end' => 3];
    foreach ($keys as $k) {
        $tot[$k] += max(0, (int) ($hero['alloc'][$k] ?? 0));
    }
    $base = $tot;
    $arm = 0;
    $dmg = [2, 4];
    $add = function (?string $key) use (&$tot, &$arm, $cat, $owned, $keys) {
        if (!$key || !isset($cat[$key]) || empty($owned[$key])) {
            return;
        }
        foreach ($keys as $i => $k) {
            $tot[$k] += $cat[$key]['s'][$i];
        }
        $arm += $cat[$key]['s'][4];
    };
    foreach (['weapon', 'kimono', 'belt', 'head', 'face', 'neck', 'hands', 'back', 'aura'] as $slot) {
        $add($hero['equipped'][$slot] ?? null);
    }
    $w = $hero['equipped']['weapon'] ?? null;
    if ($w && isset($cat[$w]['d']) && !empty($owned[$w])) {
        $dmg = $cat[$w]['d'];
    }
    $add($hero['home']['bg'] ?? null);
    $add($hero['home']['house'] ?? null);
    $seen = [];
    foreach (array_slice((array) ($hero['home']['decor'] ?? []), 0, GAME_DECOR_MAX) as $d) {
        $k = (string) ($d['k'] ?? '');
        if (!isset($seen[$k])) {   // tos pačios dekoracijos kelis kartus - savybės skaičiuojamos vieną
            $seen[$k] = true;
            $add($k);
        }
    }
    $lvl = game_level($hero);
    $belt = game_belt($hero);
    // Žemuose lygiuose - trumpos kovos (1 lygis ~25 gyvybės), aukštesniuose - daugiau gyvybių ir stipresni smūgiai
    $hp = 10 + $lvl * 4 + $tot['end'] * 3 + $belt;
    return ['base' => $base, 'total' => $tot, 'hp' => $hp, 'dmg' => [$dmg[0] + intdiv($tot['str'], 4) + intdiv($lvl, 6), $dmg[1] + intdiv($tot['str'], 3) + intdiv($lvl, 4)],
            'arm' => $arm + intdiv($tot['end'], 5), 'level' => $lvl, 'belt' => $belt];
}

// ---------- Atsirakinimai, dovanų dėžės ----------

/**
 * Sinchronizacija atidarius: daiktai, atsirakinę pagal lygį; dėžės už kiekvieną pasiektą lygį ir renginį (vieną kartą).
 * Grąžina naujų dalykų sąrašą (rodomas pranešimas „Naujas lygis! Atsirakino …“).
 */
function game_sync(array $hero): array
{
    $hid = (int) $hero['id'];
    $lvl = game_level($hero);
    $news = [];
    foreach (game_items() as $k => $it) {
        if ($it['lvl'] > 0 && $it['lvl'] <= $lvl) {
            if (q('INSERT IGNORE INTO game_items (hero_id, item_key, source) VALUES (?, ?, "level")', [$hid, $k])->rowCount()) {
                $news[] = $k;
            }
        }
    }
    for ($l = 2; $l <= min($lvl, 60); $l++) {
        q('INSERT IGNORE INTO game_boxes (hero_id, reason, title) VALUES (?, ?, ?)', [$hid, 'lvl:' . $l, t('Pasiektas') . ' ' . $l . ' ' . t('lygis')]);
    }
    if ($hero['member_id']) {
        try {
            foreach (q_all('SELECT e.id, e.title FROM event_participants ep JOIN events e ON e.id = ep.event_id
                             WHERE ep.member_id = ? AND (ep.attended = 1 OR (ep.attended IS NULL AND ep.status = "going")) AND COALESCE(e.ends_on, e.starts_on) < CURDATE()
                             ORDER BY e.starts_on DESC LIMIT 30', [(int) $hero['member_id']]) as $ev) {
                q('INSERT IGNORE INTO game_boxes (hero_id, reason, title) VALUES (?, ?, ?)', [$hid, 'ev:' . $ev['id'], t('Renginys:') . ' ' . mb_substr($ev['title'], 0, 120)]);
            }
        } catch (PDOException $ex) {
            // senesnė DB be „attended“
        }
    }
    return $news;
}

function game_box_count(int $heroId): int
{
    return (int) q_value('SELECT COUNT(*) FROM game_boxes WHERE hero_id = ? AND opened_at IS NULL', [$heroId]);
}

/** Atidaryti dėžę: atsitiktinis daiktas pagal retumą (dažniau - neturimas). Grąžina daikto raktą. */
function game_box_open(array $hero, int $boxId): ?string
{
    $hid = (int) $hero['id'];
    $box = q_one('SELECT * FROM game_boxes WHERE id = ? AND hero_id = ? AND opened_at IS NULL', [$boxId, $hid]);
    if (!$box) {
        return null;
    }
    $owned = game_owned($hid);
    $pets = (int) q_value('SELECT COUNT(*) FROM game_pets WHERE hero_id = ?', [$hid]);
    $weights = [1 => 50, 2 => 32, 3 => 14, 4 => 4];
    $pool = [];
    foreach (game_items() as $k => $it) {
        if ($it['slot'] === 'egg' && $pets >= 6) {
            continue;
        }
        if (in_array($k, GAME_START_ITEMS, true)) {
            continue;
        }
        $w = $weights[$it['r']] * (empty($owned[$k]) ? 3 : 1) * ($it['lvl'] === 0 ? 2 : 1);
        $pool[] = [$k, $w];
    }
    $sum = array_sum(array_column($pool, 1));
    $roll = random_int(1, $sum);
    $pick = $pool[0][0];
    foreach ($pool as [$k, $w]) {
        $roll -= $w;
        if ($roll <= 0) {
            $pick = $k;
            break;
        }
    }
    q('UPDATE game_boxes SET opened_at = NOW(), item_key = ? WHERE id = ?', [$pick, $boxId]);
    q('INSERT INTO game_items (hero_id, item_key, qty, source) VALUES (?, ?, 1, "box") ON DUPLICATE KEY UPDATE qty = qty + 1', [$hid, $pick]);
    return $pick;
}

/** Dienos prizas už pergales: 3 pergalės per dieną - viena dėžė */
function game_daily_win_box(array $hero): void
{
    $wins = (int) q_value('SELECT COUNT(*) FROM game_matches WHERE status = "done" AND DATE(updated_at) = CURDATE() AND
                            ((a_hero = ? AND winner = 1) OR (b_hero = ? AND winner = 2))', [(int) $hero['id'], (int) $hero['id']]);
    if ($wins >= 3) {
        q('INSERT IGNORE INTO game_boxes (hero_id, reason, title) VALUES (?, ?, ?)', [(int) $hero['id'], 'day:' . date('Y-m-d'), t('3 pergalės per dieną')]);
    }
}

// ---------- Gyvūnėliai ----------

function game_pets(int $heroId): array
{
    return array_map('game_pet_decorate', q_all('SELECT * FROM game_pets WHERE hero_id = ? ORDER BY id', [$heroId]));
}

/** Gyvūnėlio lygis ir nuotaika (alkanas po kelių valandų - nuotaika krenta 3 per valandą) */
function game_pet_decorate(array $p): array
{
    $p['level'] = min(20, 1 + intdiv((int) $p['xp'], 40));
    $hours = $p['fed_at'] ? max(0, (time() - strtotime($p['fed_at'])) / 3600) : 24;
    $p['mood'] = max(0, min(100, (int) round((int) $p['happy'] - $hours * 3)));
    $p['can_feed'] = !$p['fed_at'] || $hours >= 3;
    return $p;
}

function game_pet_adopt(array $hero, string $species, string $name, string $color = ''): ?int
{
    if (!isset(GAME_PETS[$species]) || (int) q_value('SELECT COUNT(*) FROM game_pets WHERE hero_id = ?', [(int) $hero['id']]) >= 6) {
        return null;
    }
    q('INSERT INTO game_pets (hero_id, species, name, color, fed_at) VALUES (?, ?, ?, ?, NOW())',
        [(int) $hero['id'], $species, mb_substr(trim($name) ?: GAME_PETS[$species][0], 0, 30), preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : null]);
    $id = (int) db()->lastInsertId();
    if (!$hero['pet_id']) {
        q('UPDATE game_heroes SET pet_id = ? WHERE id = ?', [$id, (int) $hero['id']]);
    }
    return $id;
}

/** Pamaitinti (kas 3 val.) - nuotaika 100, +5 XP */
function game_pet_feed(int $heroId, int $petId): bool
{
    $p = q_one('SELECT * FROM game_pets WHERE id = ? AND hero_id = ?', [$petId, $heroId]);
    if (!$p || !game_pet_decorate($p)['can_feed']) {
        return false;
    }
    q('UPDATE game_pets SET happy = 100, fed_at = NOW(), xp = xp + 5 WHERE id = ?', [$petId]);
    return true;
}

/** Visa informacija puslapiui (JSON į naršyklę) */
function game_hero_view(array $hero, bool $own = true): array
{
    $hid = (int) $hero['id'];
    $xp = game_xp($hero);
    $st = game_stats($hero);
    $pets = game_pets($hid);
    $active = $active2 = null;
    foreach ($pets as $p) {
        if ((int) $p['id'] === (int) $hero['pet_id']) {
            $active = $p;
        } elseif (!empty($hero['pet2_id']) && (int) $p['id'] === (int) $hero['pet2_id']) {
            $active2 = $p;
        }
    }
    if (!$active && $active2) {
        [$active, $active2] = [$active2, null];
    }
    $out = ['id' => $hid, 'name' => $hero['name'], 'publicName' => game_public_name($hero), 'species' => $hero['species'], 'gender' => $hero['gender'],
            'look' => $hero['look'], 'equipped' => $hero['equipped'], 'home' => $hero['home'], 'level' => $xp['level'], 'xp' => $xp, 'belt' => game_belt($hero),
            'stats' => $st, 'pet' => $active, 'pet2' => $active2, 'skill' => game_skill($hero), 'pets' => $pets, 'ratingPower' => (int) $hero['rating_power'], 'ratingFair' => (int) $hero['rating_fair'],
            'wins' => (int) $hero['wins'], 'losses' => (int) $hero['losses'], 'invites' => (bool) $hero['invites']];
    if ($own) {
        // #110: XP = aktyvumo taškai - už ką gauta (paskutiniai) ir už ką galima gauti
        if ($hero['member_id']) {
            $out['xpLog'] = array_map(function ($r) {
                return ['d' => fmt_date($r['awarded_on']), 'n' => t((string) $r['category_name']), 'p' => (int) $r['points'], 'e' => $r['event_title'] ? tr_text((string) $r['event_title']) : ''];
            }, q_all('SELECT pa.awarded_on, pa.points, pc.name AS category_name, e.title AS event_title FROM point_awards pa JOIN point_categories pc ON pc.id = pa.category_id
                      LEFT JOIN events e ON e.id = pa.event_id WHERE pa.member_id = ? ORDER BY pa.awarded_on DESC, pa.id DESC LIMIT 10', [(int) $hero['member_id']]));
        }
        $out['xpWays'] = array_map(function ($c) { return ['n' => t((string) $c['name']), 'p' => (int) $c['points']]; },
            q_all('SELECT name, points FROM point_categories WHERE is_active = 1 AND points > 0 ORDER BY code = "attendance" DESC, points DESC, sort_order LIMIT 10'));
        $out['owned'] = game_owned($hid);
        $out['points'] = game_points($hero);
        $out['alloc'] = $hero['alloc'];
        $out['boxes'] = q_all('SELECT id, title, created_at FROM game_boxes WHERE hero_id = ? AND opened_at IS NULL ORDER BY id', [$hid]);
        $out['testLevel'] = $hero['test_level'];
        $out['testBelt'] = $hero['test_belt'];
    }
    return $out;
}

/** Katalogas naršyklei (pavadinimai išversti) */
function game_catalog_json(): array
{
    $items = [];
    foreach (game_items() as $k => $it) {
        $items[$k] = ['slot' => $it['slot'], 'n' => t($it['n']), 'r' => $it['r'], 'lvl' => $it['lvl'], 's' => $it['s'], 'a' => $it['a']] + (isset($it['d']) ? ['d' => $it['d']] : []);
    }
    $species = [];
    foreach (GAME_SPECIES as $k => $s) {
        $species[$k] = ['n' => t($s[0]), 'e' => $s[1], 'c' => $s[2]] + (isset(GAME_SPECIES_LOCK[$k]) ? ['lock' => GAME_SPECIES_LOCK[$k][0], 'r' => GAME_SPECIES_LOCK[$k][1]] : []);
    }
    $pets = [];
    foreach (GAME_PETS as $k => $p) {
        $pets[$k] = ['n' => t($p[0]), 'e' => $p[1], 'ab' => $p[2], 'abn' => t(GAME_PET_ABILITY[$p[2]]), 'start' => $p[3] || GAME_UNLOCK_ALL];
    }
    return ['items' => $items, 'species' => $species, 'pets' => $pets, 'fur' => GAME_FUR, 'flame' => GAME_FLAME, 'skin' => GAME_SKIN, 'eyes' => GAME_EYES, 'hair' => GAME_HAIR,
            'stats' => array_map('t', GAME_STATS), 'rarity' => array_map('t', GAME_RARITY), 'slots' => array_map('t', GAME_SLOTS), 'decorMax' => GAME_DECOR_MAX,
            'belts' => array_map(function ($b) { return [$b[0], t($b[1]), $b[2]]; }, BELTS), 'scenes' => url('img/temos/'), 'unlockAll' => GAME_UNLOCK_ALL,
            'chars' => array_combine(array_keys(GAME_PX_CHARS), array_map(function ($c, $k) { $sk = GAME_CHAR_SKILL[$k] ?? 'focus'; return ['n' => t($c[0]), 'f' => $c[1], 's' => t(GAME_SKILLS[$sk][0]), 'sd' => t(GAME_SKILLS[$sk][1])]; }, GAME_PX_CHARS, array_keys(GAME_PX_CHARS))), 'charImg' => url('img/zaidimas/herojai/')];
}

/** Išsaugoti išvaizdą / daiktus / namus (tik tai, ką turi) */
function game_save_hero(array $hero, array $in): array
{
    $cat = game_items();
    $owned = game_owned((int) $hero['id']);
    $upd = [];
    if (isset($in['name'])) {
        $n = trim(preg_replace('/\s+/u', ' ', (string) $in['name']));
        if ($n !== '') {
            $upd['name'] = mb_substr($n, 0, 24);
        }
    }
    if (isset($in['species']) && isset(GAME_PX_CHARS[$in['species']]) && GAME_UNLOCK_ALL) {
        $upd['species'] = $in['species'];
    } elseif (isset($in['species']) && isset(GAME_SPECIES[$in['species']]) && (!isset(GAME_SPECIES_LOCK[$in['species']]) || !empty($owned['sp_' . $in['species']]))) {
        $upd['species'] = $in['species'];
    }
    if (isset($in['gender']) && in_array($in['gender'], ['m', 'f'], true)) {
        $upd['gender'] = $in['gender'];
    }
    if (isset($in['look']) && is_array($in['look'])) {
        $paints = [];
        foreach ($owned as $k => $q) {
            if (($cat[$k]['slot'] ?? '') === 'paint') {
                $paints[] = $cat[$k]['a']['c'];
            }
        }
        $ok = function ($c, array $list) use ($paints) { return is_string($c) && (in_array($c, $list, true) || in_array($c, $paints, true)); };
        $look = $hero['look'];
        foreach (['fur' => GAME_FUR, 'eyes' => GAME_EYES, 'hair' => GAME_HAIR, 'skin' => GAME_SKIN, 'flame' => GAME_FLAME] as $f => $list) {
            if (isset($in['look'][$f]) && $ok($in['look'][$f], $list)) {
                $look[$f] = $in['look'][$f];
            }
        }
        $look['setup'] = 1;
        $upd['look'] = json_encode($look);
    }
    if (isset($in['equipped']) && is_array($in['equipped'])) {
        $eq = $hero['equipped'];
        foreach (['weapon', 'kimono', 'belt', 'head', 'face', 'neck', 'hands', 'back', 'aura', 'hair'] as $slot) {
            if (!array_key_exists($slot, $in['equipped'])) {
                continue;
            }
            $k = $in['equipped'][$slot];
            if ($k === null || $k === '') {
                if (!in_array($slot, ['kimono', 'belt', 'hair'], true)) {
                    unset($eq[$slot]);
                }
            } elseif (isset($cat[$k]) && $cat[$k]['slot'] === $slot && !empty($owned[$k])) {
                $eq[$slot] = $k;
            }
        }
        $upd['equipped'] = json_encode($eq);
    }
    if (isset($in['home']) && is_array($in['home'])) {
        $home = $hero['home'];
        foreach (['bg', 'house'] as $f) {
            $k = $in['home'][$f] ?? null;
            if ($k && isset($cat[$k]) && $cat[$k]['slot'] === $f && !empty($owned[$k])) {
                $home[$f] = $k;
            }
        }
        if (isset($in['home']['decor']) && is_array($in['home']['decor'])) {
            $count = [];
            $decor = [];
            foreach (array_slice($in['home']['decor'], 0, 24) as $d) {
                $k = (string) ($d['k'] ?? '');
                if (!isset($cat[$k]) || $cat[$k]['slot'] !== 'decor' || empty($owned[$k])) {
                    continue;
                }
                $count[$k] = ($count[$k] ?? 0) + 1;
                if ($count[$k] > $owned[$k]) {
                    continue;   // tiek, kiek turi
                }
                $decor[] = ['k' => $k, 'x' => max(0, min(100, round((float) ($d['x'] ?? 50), 1))), 'y' => max(0, min(100, round((float) ($d['y'] ?? 80), 1))), 'f' => !empty($d['f']) ? 1 : 0];
            }
            $home['decor'] = $decor;
        }
        $upd['home'] = json_encode($home);
    }
    if (isset($in['alloc']) && is_array($in['alloc'])) {
        $alloc = [];
        foreach (array_keys(GAME_STATS) as $k) {
            $alloc[$k] = max(0, (int) ($in['alloc'][$k] ?? 0));
        }
        if (array_sum($alloc) <= (game_level($hero) - 1) * 3) {
            $upd['alloc'] = json_encode($alloc);
        }
    }
    if (isset($in['pet_id'])) {
        $pid = (int) $in['pet_id'];
        if (!$pid || q_value('SELECT 1 FROM game_pets WHERE id = ? AND hero_id = ?', [$pid, (int) $hero['id']])) {
            $upd['pet_id'] = $pid ?: null;
        }
    }
    // Į kovą - iki 2 gyvūnėlių: paspaudus imamas (jei abi vietos užimtos - pakeičia antrą), dar kartą - paliekamas namie
    if (isset($in['pet_toggle']) && array_key_exists('pet2_id', $hero)) {
        $pid = (int) $in['pet_toggle'];
        if ($pid && q_value('SELECT 1 FROM game_pets WHERE id = ? AND hero_id = ?', [$pid, (int) $hero['id']])) {
            $p1 = (int) $hero['pet_id'];
            $p2 = (int) $hero['pet2_id'];
            if ($pid === $p1) {
                [$p1, $p2] = [$p2, 0];
            } elseif ($pid === $p2) {
                $p2 = 0;
            } elseif (!$p1) {
                $p1 = $pid;
            } else {
                $p2 = $pid;
            }
            $upd['pet_id'] = $p1 ?: null;
            $upd['pet2_id'] = $p2 ?: null;
        }
    }
    if (isset($in['invites'])) {
        $upd['invites'] = $in['invites'] ? 1 : 0;
    }
    if ($upd) {
        $sets = implode(', ', array_map(function ($k) { return $k . ' = ?'; }, array_keys($upd)));
        q('UPDATE game_heroes SET ' . $sets . ', updated_at = NOW() WHERE id = ?', array_merge(array_values($upd), [(int) $hero['id']]));
    }
    return game_hero_row((int) $hero['id']);
}

/** Klubo personažai (arenai ir lankymuisi): vardas, lygis, diržas, išvaizda - be asmens duomenų */
/** Herojai visiems aktyviems nariams (kad būtų galima pakviesti ir tuos, kurie dar neatsidarė žaidimo) */
function game_ensure_heroes(int $limit = 400): void
{
    foreach (q_all('SELECT m.id, m.first_name, m.gender FROM members m LEFT JOIN game_heroes h ON h.member_id = m.id
                     WHERE m.status = "active" AND h.id IS NULL LIMIT ' . (int) $limit) as $m) {
        game_hero_create($m['first_name'], ['member_id' => (int) $m['id']], $m['gender'] ?? null);
    }
}

function game_club_heroes(int $exceptId = 0, int $limit = 200): array
{
    $rows = q_all('SELECT h.* FROM game_heroes h LEFT JOIN members m ON m.id = h.member_id LEFT JOIN accounts a ON a.id = h.account_id
                    WHERE h.id <> ? AND (m.status = "active" OR a.status = "active") ORDER BY h.updated_at IS NULL, h.updated_at DESC, h.id DESC LIMIT ' . (int) $limit, [$exceptId]);
    return array_map(function ($h) {
        $h = game_hero_decode($h);
        return ['id' => (int) $h['id'], 'name' => game_public_name($h), 'species' => $h['species'], 'gender' => $h['gender'], 'look' => $h['look'],
                'equipped' => $h['equipped'], 'level' => game_level($h), 'belt' => game_belt($h), 'ratingPower' => (int) $h['rating_power'],
                'ratingFair' => (int) $h['rating_fair'], 'invites' => (bool) $h['invites'], 'wins' => (int) $h['wins']];
    }, $rows);
}
