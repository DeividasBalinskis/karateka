<?php
// Karate kova (kaip combats.ru, tik karate): abu žaidėjai tuo pačiu metu pasirenka techniką (kur smūgiuoja) ir gynybą (kurį lygį blokuoja).
// Lygiai: jodan (galva), chudan (kūnas), gedan (kojos). Teisingai užblokavus - kontrataka. Tai sabaki - išsisukimas nuo bet kurio smūgio (su tikimybe).
// Režimai: „power“ (jėgos) - savybės, daiktai ir gyvūnėlis svarbūs, stipresnis dažniau laimi; „fair“ (draugiška) - visi lygūs, 3 kortos
// (Kiai, Zanshin, Kime), laimi tas, kuris geriau perpranta priešininką. Botas - treniruočių partneris (be reitingo).

const FIGHT_ZONES = ['jodan' => 'galvą', 'chudan' => 'kūną', 'gedan' => 'kojas'];
// technika => [pavadinimas, lygis, galia, taiklumas (+/- %)]
const FIGHT_ATTACKS = [
    'kizami'  => ['Kizami tsuki', 'jodan', 0.8, 15],
    'gyaku'   => ['Gyaku tsuki', 'chudan', 1.0, 5],
    'maegeri' => ['Mae geri', 'chudan', 1.25, -5],
    'mawashi' => ['Mawashi geri', 'jodan', 1.5, -15],
    'barai'   => ['Ashi barai', 'gedan', 0.6, 5],
];
const FIGHT_DEFENSES = [
    'age'    => ['Age uke', 'jodan'],
    'soto'   => ['Soto uke', 'chudan'],
    'gedan'  => ['Gedan barai', 'gedan'],
    'sabaki' => ['Tai sabaki', ''],
];
const FIGHT_CARDS = ['kiai' => ['Kiai!', 'Šio ėjimo smūgis - dvigubas'], 'zanshin' => ['Zanshin', 'Šį ėjimą blokuojami visi lygiai'], 'kime' => ['Kime', 'Smūgio neįmanoma išvengti išsisukant, didesnė Waza-ari tikimybė']];
const FIGHT_REACTIONS = ['👏', '🔥', '😮', '😂', '💪', '🙏', '🥋', '👍'];
const FIGHT_TURN_SEC = 30;
const FIGHT_MAX_TURNS = 30;

/** Kovotojo nuotrauka būsenai: išvaizda (piešti), savybės (skaičiuoti), gyvūnėlis */
function fight_side(array $hero, string $mode): array
{
    $st = game_stats($hero);
    // Iki 2 gyvūnėlių kovoje (pet_id ir pet2_id); 'pet' - pirmasis (senesniam rodymui)
    $pets = [];
    foreach (array_unique(array_filter([(int) $hero['pet_id'], (int) ($hero['pet2_id'] ?? 0)])) as $pid) {
        $p = q_one('SELECT * FROM game_pets WHERE id = ? AND hero_id = ?', [$pid, (int) $hero['id']]);
        if ($p) {
            $p = game_pet_decorate($p);
            $pets[] = ['species' => $p['species'], 'name' => $p['name'], 'color' => $p['color'], 'lvl' => $p['level'], 'mood' => $p['mood'], 'ab' => GAME_PETS[$p['species']][2], 'id' => (int) $p['id'], 'used' => 0];
        }
    }
    $pet = $pets[0] ?? null;
    $sk = game_skill($hero);
    if ($mode === 'fair') {
        $s = ['str' => 6, 'agi' => 6, 'int' => 6, 'end' => 6, 'hp' => 70, 'dmg' => [5, 8], 'arm' => 0];
    } else {
        $s = $st['total'] + ['hp' => $st['hp'], 'dmg' => $st['dmg'], 'arm' => $st['arm']];
    }
    return ['hero' => (int) $hero['id'], 'name' => game_public_name($hero), 'art' => ['species' => $hero['species'], 'gender' => $hero['gender'], 'look' => $hero['look'],
            'equipped' => $hero['equipped'], 'belt' => game_belt($hero), 'name' => $hero['name']], 'level' => game_level($hero), 's' => $s, 'pet' => $pet, 'pets' => $pets,
            'hands' => game_hands($hero, $mode),
            'skill' => $mode === 'fair' ? null : ['code' => $sk['code'], 'n' => $sk['n'], 'p' => $sk['p']]];
}

/** Treniruočių partneris (botas) pagal žaidėjo lygį: lengvas / vidutinis / sunkus */
function fight_bot_side(array $me, string $mode, string $lvl): array
{
    $k = ['easy' => 0.75, 'normal' => 0.95, 'hard' => 1.15][$lvl] ?? 0.95;
    $s = $me['s'];
    foreach (['str', 'agi', 'int', 'end'] as $f) {
        $s[$f] = max(2, (int) round($s[$f] * $k));
    }
    $s['hp'] = (int) round($s['hp'] * $k);
    $s['dmg'] = [max(1, (int) round($s['dmg'][0] * $k)), max(2, (int) round($s['dmg'][1] * $k))];
    $names = ['easy' => t('Senpai Bambukas'), 'normal' => t('Sensei Makivara'), 'hard' => t('Meistras Drakonas')];
    $species = ['easy' => 'turtle', 'normal' => 'frog', 'hard' => 'gdragon'][$lvl] ?? 'frog';   // #115: be pandos
    return ['hero' => 0, 'bot' => $lvl, 'name' => $names[$lvl] ?? $names['normal'], 'level' => $me['level'], 's' => $s, 'pet' => null,
            // Pikselinės lapės: lengvas - smėlinė su žaliomis liepsnomis, vidutinis - ruda su oranžinėmis, sunkus - šešėlių lapė su violetinėmis
            'art' => ['species' => $species, 'gender' => 'm', 'look' => ['fur' => ['easy' => '#E9D2A8', 'normal' => '#C8703A', 'hard' => '#3B3A4A'][$lvl] ?? '#C8703A',
                                                                          'flame' => ['easy' => '#6EDC8C', 'normal' => '#FF9A3C', 'hard' => '#B46CFF'][$lvl] ?? '#FF9A3C', 'eyes' => '#2B2B2B'],
                      'equipped' => ['kimono' => $lvl === 'hard' ? 'k_black_red' : ($lvl === 'normal' ? 'k_white' : 'k_emerald'), 'belt' => 'b_plain', 'head' => $lvl === 'easy' ? 'h_band_white' : 'h_band_red'],
                      'belt' => $lvl === 'hard' ? 12 : ($lvl === 'normal' ? 7 : 3), 'name' => $names[$lvl] ?? '']];
}

function fight_new_state(array $a, array $b, string $mode): array
{
    $cards = $mode === 'fair' ? ['kiai' => 1, 'zanshin' => 1, 'kime' => 1] : [];
    return ['v' => 1, 'mode' => $mode, 'turn' => 1, 'sides' => ['a' => $a, 'b' => $b], 'hp' => ['a' => $a['s']['hp'], 'b' => $b['s']['hp']],
            'max' => ['a' => $a['s']['hp'], 'b' => $b['s']['hp']], 'picks' => ['a' => null, 'b' => null], 'cards' => ['a' => $cards, 'b' => $cards],
            'off' => ['a' => 0, 'b' => 0], 'hist' => ['a' => [], 'b' => []], 'log' => [], 'last' => null, 'reactions' => [], 'deadline' => time() + FIGHT_TURN_SEC, 'end' => null];
}

function fight_rand(int $min, int $max): int
{
    return $max <= $min ? $min : random_int($min, $max);
}

/** Atsitiktinis ar boto pasirinkimas: botas blokuoja ten, kur žaidėjas dažniausiai puola, puola ten, kur rečiausiai blokuoja */
function fight_auto_pick(array $st, string $side, bool $smart): array
{
    $att = array_keys(FIGHT_ATTACKS);
    $def = array_keys(FIGHT_DEFENSES);
    $pick = ['att' => $att[random_int(0, count($att) - 1)], 'def' => $def[random_int(0, 2)], 'card' => ''];
    if ($smart) {
        $other = $side === 'a' ? 'b' : 'a';
        $h = array_slice($st['hist'][$other], -6);
        if ($h && random_int(1, 100) <= 65) {
            $zones = array_count_values(array_map(function ($x) { return FIGHT_ATTACKS[$x['att']][1]; }, $h));
            arsort($zones);
            $z = array_key_first($zones);
            foreach (FIGHT_DEFENSES as $k => $d) {
                if ($d[1] === $z) {
                    $pick['def'] = $k;
                }
            }
            $blocked = array_count_values(array_map(function ($x) { return FIGHT_DEFENSES[$x['def']][1]; }, $h));
            $cands = array_values(array_filter($att, function ($k) use ($blocked) { return empty($blocked[FIGHT_ATTACKS[$k][1]]); }));
            if ($cands) {
                $pick['att'] = $cands[random_int(0, count($cands) - 1)];
            }
        }
        if (random_int(1, 100) <= 15) {
            $pick['def'] = 'sabaki';
        }
        foreach ($st['cards'][$side] as $c => $left) {
            if ($left && random_int(1, 100) <= 18) {
                $pick['card'] = $c;
                break;
            }
        }
    }
    return $pick;
}

/** Ėjimo rezultatas: abu smūgiai, kontratakos, gyvūnėliai. Rašomas žurnalas su karate terminais. */
function fight_resolve(array $st): array
{
    $sides = ['a', 'b'];
    $log = [];
    $dmgTo = ['a' => 0, 'b' => 0];
    $fair = $st['mode'] === 'fair';
    $picks = $st['picks'];
    $name = function ($x) use ($st) { return $st['sides'][$x]['name']; };
    // Gyvūnėlių veiksmai (tik jėgos kovose, iki 2 gyvūnėlių), nuspręsti iš anksto: [šalis => [gebėjimas => gyvūnėlio indeksas]]
    $petList = function ($x) use ($st) { return $st['sides'][$x]['pets'] ?? (!empty($st['sides'][$x]['pet']) ? [$st['sides'][$x]['pet']] : []); };
    $acts = ['a' => [], 'b' => []];
    foreach ($sides as $x) {
        foreach ($petList($x) as $i => $p) {
            if (!$fair && $p && $p['mood'] >= 30 && !isset($acts[$x][$p['ab']])) {
                if ($p['ab'] === 'regen' || random_int(1, 100) <= 18 + $p['lvl'] * 2) {
                    $acts[$x][$p['ab']] = $i;
                }
            }
        }
    }
    $has = function ($x, $ab) use ($acts) { return isset($acts[$x][$ab]); };
    $petName = function ($x, $ab) use ($acts, $petList) { $l = $petList($x); return $l[$acts[$x][$ab] ?? 0]['name'] ?? ''; };
    // Personažo gebėjimas (tik jėgos kovose): suveikia su tikimybe, kuri auga laimint su tuo personažu
    $sk = ['a' => null, 'b' => null];
    foreach ($sides as $x) {
        $s0 = $st['sides'][$x]['skill'] ?? null;
        if (!$fair && $s0 && random_int(1, 100) <= (int) $s0['p']) {
            $sk[$x] = $s0['code'];
        }
    }
    $skLog = function ($x, $extra = '') use ($st, &$log) {
        $log[] = ['s' => $x, 'k' => 'skill', 't' => '✨ ' . $st['sides'][$x]['name'] . ': ' . $st['sides'][$x]['skill']['n'] . ($extra !== '' ? ' - ' . $extra : '')];
    };
    $card = function ($x) use ($picks, $st) { $c = $picks[$x]['card'] ?? ''; return $c && !empty($st['cards'][$x][$c]) ? $c : ''; };
    $newOff = ['a' => 0, 'b' => 0];   // po Ashi barai - kitą ėjimą be pusiausvyros (smūgis silpnesnis)
    // #109: kiekvienas smūgis atskirai (du ginklai - 2 smūgiai, antras 25 % silpnesnis); gynybos - 1 ar 2 (skydas)
    $blows = [];
    foreach ($sides as $x) {
        $blows[] = [$x, $picks[$x]['att'], 1.0];
        if (!empty($picks[$x]['att2'])) {
            $blows[] = [$x, $picks[$x]['att2'], 0.75];
        }
    }
    foreach ($blows as [$x, $attKey, $mult]) {
        $y = $x === 'a' ? 'b' : 'a';
        $A = FIGHT_ATTACKS[$attKey];
        $D = FIGHT_DEFENSES[$picks[$y]['def']];
        $D2 = !empty($picks[$y]['def2']) ? FIGHT_DEFENSES[$picks[$y]['def2']] : null;
        if ($D2 && $D2[1] !== '' && $D2[1] === $A[1] && !($D[1] !== '' && $D[1] === $A[1])) {
            $D = $D2;   // šį smūgį užblokavo antra gynyba (skydas)
        }
        $sx = $st['sides'][$x]['s'];
        $sy = $st['sides'][$y]['s'];
        $cx = $card($x);
        $cy = $card($y);
        $tech = $A[0] . ' ' . t('į') . ' ' . t(FIGHT_ZONES[$A[1]]);
        $blocked = $cy === 'zanshin' || ($D[1] !== '' && $D[1] === $A[1]);
        if ($blocked && $cy !== 'zanshin' && $sk[$x] === 'pierce') {
            $blocked = false;
            $skLog($x, t('smūgio negalima užblokuoti'));
        }
        if ($has($y, 'trick') && !$blocked && random_int(1, 2) === 1) {
            $log[] = ['s' => $y, 'k' => 'pet', 't' => '🦊 ' . $petName($y, 'trick') . ' ' . t('suklaidino') . ' ' . $name($x) . ' - ' . $tech . ' ' . t('nepataikė!')];
            continue;
        }
        if ($blocked) {
            $counter = (int) round(fight_rand($sy['dmg'][0], $sy['dmg'][1]) * 0.5);
            if ($sk[$y] === 'counter') {
                $counter *= 2;
                $skLog($y, t('atkirtis dvigubai stipresnis'));
            }
            $dmgTo[$x] += $counter;
            $log[] = ['s' => $y, 'k' => 'block', 't' => $name($y) . ': ' . ($cy === 'zanshin' ? 'Zanshin' : $D[0]) . ' - ' . t('užblokavo') . ' ' . $tech . ' ' . t('ir kontratakavo') . ' −' . $counter];
            continue;
        }
        if ($sk[$y] === 'evade') {
            $skLog($y, t('išvengė') . ' ' . $tech);
            continue;
        }
        $dodge = $fair ? 5 : max(0, min(22, 3 + ($sy['agi'] - $sx['int']) * 2));
        if ($picks[$y]['def'] === 'sabaki' || ($picks[$y]['def2'] ?? '') === 'sabaki') {
            $dodge = $fair ? 33 : max(15, min(55, 30 + ($sy['agi'] - $sx['int']) * 2));
        }
        if ($has($y, 'dodge')) {
            $dodge += 20;
        }
        $dodge -= $A[3];
        if ($cx === 'kime') {
            $dodge = 0;
        }
        if (random_int(1, 100) <= max(0, $dodge)) {
            $log[] = ['s' => $y, 'k' => 'miss', 't' => $name($y) . ' ' . ($picks[$y]['def'] === 'sabaki' || ($picks[$y]['def2'] ?? '') === 'sabaki' ? t('išsisuko (Tai sabaki) nuo') : t('išvengė')) . ' ' . $tech];
            continue;
        }
        $dmg = fight_rand($sx['dmg'][0], $sx['dmg'][1]) * $A[2] * $mult;
        if (!empty($st['off'][$x])) {
            $dmg *= 0.6;   // po Ashi barai - be pusiausvyros
        }
        $critP = $fair ? 10 : max(3, min(30, 6 + ($sx['int'] - $sy['agi']) * 2));
        if ($cx === 'kime') {
            $critP += 25;
        }
        $crit = random_int(1, 100) <= $critP || $sk[$x] === 'focus';
        if ($crit) {
            $dmg *= 1.8;
        }
        if ($sk[$x] === 'pounce') {
            $dmg *= 1.6;
        }
        if ($cx === 'kiai') {
            $dmg *= 2;
        }
        $dmg = max(1, (int) round($dmg - ($fair ? 0 : $sy['arm'] * 0.6)));
        if ($has($y, 'shield')) {
            $dmg = (int) ceil($dmg / 2);
        }
        if ($sk[$y] === 'stone') {
            $dmg = (int) ceil($dmg / 2);
            $skLog($y, t('žala perpus mažesnė'));
        }
        if ($has($y, 'guard') && random_int(1, 2) === 1) {
            $log[] = ['s' => $y, 'k' => 'pet', 't' => '🐶 ' . $petName($y, 'guard') . ' ' . t('užstojo šeimininką - smūgis nepasiekė!')];
            continue;
        }
        $dmgTo[$y] += $dmg;
        if ($sk[$x] === 'pounce' || $sk[$x] === 'focus') {
            $skLog($x);
        }
        if ($sk[$x] === 'drain') {
            $h = (int) ceil($dmg / 2);
            $dmgTo[$x] -= $h;
            $skLog($x, '+' . $h);
        }
        if ($sk[$y] === 'thorns') {
            $r = (int) ceil($dmg / 2);
            $dmgTo[$x] += $r;
            $skLog($y, '−' . $r . ' ' . $name($x));
        }
        $txt = $name($x) . ': ' . $tech . ($cx === 'kiai' ? ' + KIAI!' : '') . ' −' . $dmg . ($crit ? ' · Waza-ari!' : '');
        if ($A[1] === 'gedan') {
            $newOff[$y] = 1;
            $txt .= ' · ' . $name($y) . ' ' . t('neteko pusiausvyros');
        }
        $log[] = ['s' => $x, 'k' => $crit ? 'crit' : 'hit', 't' => $txt];
    }
    $st['off'] = $newOff;
    // Gyvūnėliai, kurie veikia patys (kiekvienas iš 2 - atskirai)
    foreach ($sides as $x) {
        $y = $x === 'a' ? 'b' : 'a';
        foreach ($acts[$x] as $ab => $i) {
            $p = $petList($x)[$i];
            switch ($ab) {
                case 'scratch':
                case 'fire':
                    $d = $ab === 'fire' ? fight_rand(3, 7) : fight_rand(2, 5);
                    $dmgTo[$y] += $d;
                    $log[] = ['s' => $x, 'k' => 'pet', 't' => ($ab === 'fire' ? '🐉 ' : '🐱 ') . $p['name'] . ' ' . ($ab === 'fire' ? t('spjovė ugnimi') : t('įdrėskė')) . ' −' . $d];
                    break;
                case 'heal':
                case 'regen':
                    $h = $ab === 'regen' ? 2 : fight_rand(4, 8);
                    $dmgTo[$x] -= $h;
                    $log[] = ['s' => $x, 'k' => 'heal', 't' => ($ab === 'regen' ? '🦎 ' : '🐼 ') . $p['name'] . ': ' . t('šeimininkas atgavo') . ' +' . $h];
                    break;
                case 'reveal':
                    $h = array_count_values(array_map(function ($v) { return FIGHT_ATTACKS[$v['att']][1]; }, array_slice($st['hist'][$y], -5)));
                    arsort($h);
                    if ($h) {
                        $log[] = ['s' => $x, 'k' => 'pet', 't' => '🦉 ' . $p['name'] . ': ' . t('priešininkas dažniausiai puola į') . ' ' . t(FIGHT_ZONES[array_key_first($h)])];
                    }
                    break;
            }
        }
        // Personažo gebėjimai, kurie veikia ėjimo pabaigoje
        if ($sk[$x] === 'heal') {
            $h = fight_rand(4, 8);
            $dmgTo[$x] -= $h;
            $skLog($x, '+' . $h);
        } elseif ($sk[$x] === 'burn') {
            $d = fight_rand(3, 7);
            $dmgTo[$y] += $d;
            $skLog($x, '−' . $d . ' ' . $name($y));
        }
    }
    foreach ($sides as $x) {
        $st['hp'][$x] = max(0, min($st['max'][$x], $st['hp'][$x] - $dmgTo[$x]));
        $c = $card($x);
        if ($c) {
            $st['cards'][$x][$c] = 0;
        }
        $st['hist'][$x][] = $picks[$x];
        // Feniksiukas - prikelia vieną kartą (bet kuris iš 2 gyvūnėlių)
        foreach ($petList($x) as $i => $p) {
            if ($st['hp'][$x] <= 0 && $p['ab'] === 'revive' && empty($p['used']) && !$fair) {
                $st['hp'][$x] = (int) round($st['max'][$x] * 0.25);
                if (isset($st['sides'][$x]['pets'][$i])) {
                    $st['sides'][$x]['pets'][$i]['used'] = 1;
                }
                if (!empty($st['sides'][$x]['pet']) && (int) $st['sides'][$x]['pet']['id'] === (int) $p['id']) {
                    $st['sides'][$x]['pet']['used'] = 1;
                }
                $log[] = ['s' => $x, 'k' => 'heal', 't' => '🔥 ' . $p['name'] . ': ' . t('prikėlė šeimininką!') . ' +' . $st['hp'][$x]];
            }
        }
    }
    $st['last'] = ['a' => $picks['a'], 'b' => $picks['b'], 'turn' => $st['turn']];
    foreach ($log as &$l) {
        $l['n'] = $st['turn'];
    }
    unset($l);
    $st['log'] = array_slice(array_merge($st['log'], $log), -60);
    $st['picks'] = ['a' => null, 'b' => null];
    $st['turn']++;
    $st['deadline'] = time() + FIGHT_TURN_SEC;
    // Pabaiga: gyvybės baigėsi arba per daug ėjimų (laimi turintis daugiau gyvybių dalies)
    $ka = $st['hp']['a'] <= 0;
    $kb = $st['hp']['b'] <= 0;
    if ($ka || $kb || $st['turn'] > FIGHT_MAX_TURNS) {
        $pa = $st['hp']['a'] / max(1, $st['max']['a']);
        $pb = $st['hp']['b'] / max(1, $st['max']['b']);
        $w = $ka && $kb ? ($pa === $pb ? 0 : ($pa > $pb ? 1 : 2)) : ($ka ? 2 : ($kb ? 1 : ($pa === $pb ? 0 : ($pa > $pb ? 1 : 2))));
        $st['end'] = ['winner' => $w];
        $st['log'][] = ['s' => '', 'k' => 'end', 'n' => $st['turn'] - 1, 't' => $w === 0 ? t('Lygiosios!') : 'IPPON! ' . t('Laimėjo') . ' ' . $st['sides'][$w === 1 ? 'a' : 'b']['name']];
    }
    return $st;
}

// ---------- Kovos įrašai (game_matches) ----------

function fight_match(int $id): ?array
{
    $m = q_one('SELECT * FROM game_matches WHERE id = ?', [$id]);
    if ($m) {
        $m['state'] = json_decode((string) $m['state'], true) ?: [];
    }
    return $m;
}

function fight_store(array $m): void
{
    q('UPDATE game_matches SET state = ?, status = ?, winner = ?, updated_at = NOW() WHERE id = ?',
        [json_encode($m['state'], JSON_UNESCAPED_UNICODE), $m['status'], $m['winner'], (int) $m['id']]);
}

/** Kurioje pusėje šis personažas (a / b) */
function fight_side_of(array $m, int $heroId): ?string
{
    return (int) $m['a_hero'] === $heroId ? 'a' : ((int) $m['b_hero'] === $heroId ? 'b' : null);
}

/** Kova prieš treniruočių partnerį - iškart vyksta */
function fight_start_bot(array $hero, string $mode, string $lvl): int
{
    $mode = $mode === 'fair' ? 'fair' : 'power';
    $a = fight_side($hero, $mode);
    $b = fight_bot_side($a, $mode, in_array($lvl, ['easy', 'normal', 'hard'], true) ? $lvl : 'normal');
    $st = fight_new_state($a, $b, $mode);
    $st['bot'] = $b['bot'];
    q('INSERT INTO game_matches (game, mode, a_hero, b_hero, status, state, updated_at) VALUES ("kova", ?, ?, NULL, "active", ?, NOW())',
        [$mode, (int) $hero['id'], json_encode($st, JSON_UNESCAPED_UNICODE)]);
    return (int) db()->lastInsertId();
}

/** Priimtas kvietimas - abiejų nuotraukos ir pradžia */
function fight_begin(array $m): array
{
    $ha = game_hero_row((int) $m['a_hero']);
    $hb = game_hero_row((int) $m['b_hero']);
    $m['state'] = fight_new_state(fight_side($ha, $m['mode']), fight_side($hb, $m['mode']), $m['mode']);
    $m['status'] = 'active';
    fight_store($m);
    return $m;
}

/** Ėjimas: pasirinkimas įrašomas; kai abu pasirinko (ar baigėsi laikas) - skaičiuojama */
function fight_turn(array $m, string $side, array $pick): array
{
    $st = $m['state'];
    if ($m['status'] !== 'active' || $st['end'] || !empty($st['picks'][$side])) {
        return $m;
    }
    $att = isset(FIGHT_ATTACKS[$pick['att'] ?? '']) ? $pick['att'] : 'gyaku';
    $def = isset(FIGHT_DEFENSES[$pick['def'] ?? '']) ? $pick['def'] : 'soto';
    $card = isset(FIGHT_CARDS[$pick['card'] ?? '']) && !empty($st['cards'][$side][$pick['card']]) ? $pick['card'] : '';
    // #109: antras smūgis (du ginklai) ar antra gynyba (skydas) - tik jei kairė ranka leidžia
    $hands = $st['sides'][$side]['hands'] ?? ['att' => 1, 'def' => 1];
    $att2 = (int) $hands['att'] > 1 && isset(FIGHT_ATTACKS[$pick['att2'] ?? '']) && $pick['att2'] !== $att ? $pick['att2'] : '';
    $def2 = (int) $hands['def'] > 1 && isset(FIGHT_DEFENSES[$pick['def2'] ?? '']) && $pick['def2'] !== $def ? $pick['def2'] : '';
    $st['picks'][$side] = ['att' => $att, 'def' => $def, 'card' => $card, 'att2' => $att2, 'def2' => $def2];
    if (!empty($st['bot']) && $side === 'a') {
        $st['picks']['b'] = fight_auto_pick($st, 'b', $st['bot'] !== 'easy');
    }
    $m['state'] = $st;
    return fight_tick($m);
}

/** Laikas baigėsi - už nepasirinkusį pasirenkama atsitiktinai; abu pasirinkę - ėjimas skaičiuojamas */
function fight_tick(array $m): array
{
    $st = $m['state'];
    if ($m['status'] !== 'active' || !empty($st['end'])) {
        return $m;
    }
    if ((empty($st['picks']['a']) || empty($st['picks']['b'])) && time() > (int) $st['deadline']) {
        foreach (['a', 'b'] as $x) {
            if (empty($st['picks'][$x])) {
                $st['picks'][$x] = fight_auto_pick($st, $x, false);
                $st['auto'][$x] = ($st['auto'][$x] ?? 0) + 1;
            }
        }
    }
    if (!empty($st['picks']['a']) && !empty($st['picks']['b'])) {
        $st = fight_resolve($st);
        if ($st['end']) {
            $m['status'] = 'done';
            $m['winner'] = $st['end']['winner'];
            $m['state'] = $st;
            fight_finish($m);
            return $m;
        }
    }
    $m['state'] = $st;
    fight_store($m);
    return $m;
}

/** Pabaiga: reitingas (tik tarp žaidėjų), pergalės, gyvūnėlių XP, dienos dėžė */
function fight_finish(array $m): void
{
    $st = &$m['state'];
    $w = (int) $m['winner'];
    $col = $m['mode'] === 'fair' ? 'rating_fair' : 'rating_power';
    if ($m['b_hero']) {
        $ra = (int) q_value("SELECT $col FROM game_heroes WHERE id = ?", [(int) $m['a_hero']]);
        $rb = (int) q_value("SELECT $col FROM game_heroes WHERE id = ?", [(int) $m['b_hero']]);
        $ea = 1 / (1 + pow(10, ($rb - $ra) / 400));
        $sa = $w === 1 ? 1 : ($w === 2 ? 0 : 0.5);
        $d = (int) round(24 * ($sa - $ea));
        q("UPDATE game_heroes SET $col = $col + ?, wins = wins + ?, losses = losses + ? WHERE id = ?", [$d, $w === 1 ? 1 : 0, $w === 2 ? 1 : 0, (int) $m['a_hero']]);
        q("UPDATE game_heroes SET $col = $col - ?, wins = wins + ?, losses = losses + ? WHERE id = ?", [$d, $w === 2 ? 1 : 0, $w === 1 ? 1 : 0, (int) $m['b_hero']]);
        $st['rating'] = ['a' => $d, 'b' => -$d];
    }
    foreach (['a' => 1, 'b' => 2] as $x => $n) {
        $pl = $st['sides'][$x]['pets'] ?? (!empty($st['sides'][$x]['pet']) ? [$st['sides'][$x]['pet']] : []);
        foreach ($pl as $p) {
            q('UPDATE game_pets SET xp = xp + ? WHERE id = ?', [$w === $n ? 6 : 3, (int) $p['id']]);
        }
        $hid = $x === 'a' ? (int) $m['a_hero'] : (int) $m['b_hero'];
        if ($hid && $w === $n) {
            $h = game_hero_row($hid);
            if ($h) {
                game_week_win_box($h);
                game_char_win($hid);   // personažo meistriškumas - gebėjimas suveiks dažniau
            }
        }
    }
    unset($st);
    fight_store($m);
}

/** Būsena žiūrinčiajam: priešininko šio ėjimo pasirinkimas paslėptas */
function fight_view(array $m, ?string $side): array
{
    $st = $m['state'];
    $out = ['id' => (int) $m['id'], 'game' => $m['game'], 'mode' => $m['mode'], 'status' => $m['status'], 'winner' => $m['winner'] !== null ? (int) $m['winner'] : null,
            'side' => $side, 'now' => time()];
    if ($m['status'] === 'invited' || !$st) {
        return $out;
    }
    $picks = $st['picks'];
    foreach (['a', 'b'] as $x) {
        $picks[$x] = $picks[$x] ? ($x === $side ? $picks[$x] : true) : null;
    }
    $st['picks'] = $picks;
    unset($st['hist']);
    $out['state'] = $st;
    return $out;
}

function fight_react(array $m, string $side, string $emoji): array
{
    if (!in_array($emoji, FIGHT_REACTIONS, true)) {
        return $m;
    }
    $m['state']['reactions'] = array_slice(array_merge($m['state']['reactions'] ?? [], [['s' => $side, 'e' => $emoji, 't' => round(microtime(true), 2)]]), -12);
    fight_store($m);
    return $m;
}
