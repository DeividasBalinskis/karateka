# Dojo progresija (patvirtinta 2026-11-25, v1.96)

Skaičiuoklė derinimui: claude.ai artifact „Dojo progresija“ (privatus; išsaugoti skaičiai jo db `nustatymai/pasirinkta`).
Deividas patvirtino 2026-11-25 („Taip, daryk“), sprendimas - `docs/decisions.md`. Kode: `app/xp.php` (`xp_level_cost`, `xp_level`),
`app/game.php` (`game_week_win_box`, `GAME_EGG_LEVELS`). Personažas - kas 5 lygius (atsitiktinis, atskira dovana, `GAME_CHAR_START` = Kitsunė).

## Kaip yra dabar (kodas, 2026-11-24)
- XP = aktyvumo taškai (`point_categories`): treniruotė 1, egzaminas 10, varžybos LT 5 (+ vieta 15/10/7), užsienyje 10 (+30/20/15),
  seminaras 5, renginys 3, testas 2 + išlaikytas 5, pagalba treneriui 5, teisėjavimas 5/10, kita 5.
- Lygio kaina: `xp_level_base` + `xp_level_step` × floor((n−1)/5) XP (nuo v1.96; senas `xp_per_level` ištrintas db/063). Vaikas, kuris treniruojasi 2 kartus per savaitę, po metų yra 2 lygio.
- Dovanų dėžė: už kiekvieną lygį (2-60), už renginį su „Buvo“, už 3 pergales per dieną (`game_daily_win_box`, gali būti kasdien).
- Dėžės svoriai: paprastas 50, retas 32, epinis 14, legendinis 4; neturimas ×3; tik iš dėžių ×2 (`game_box_open`).
- Apranga ir ginklai: 81 (kimono 19, ginklai 15, galva 8, kairė ranka 6, kaklas 6, diržas 6, nugara 6, aura 6, rankos 5, veidas 4).
  Dar 9 spalvos, 4 kiaušiniai, 8 namai, 26 dekoracijos, 11 fonų. Personažų 23 žaidime (+3 lape). Gyvūnėlių 10, daugiausia 6.

## Siūloma sistema
1. XP = aktyvumo taškai, kaip dabar. Taškų vertes nustato treneris, žaidimas jų nekeičia.
2. Lygiai: lygis n → n+1 kainuoja `5 + 2 × floor((n-1) / 5)` XP (pirmi 5 lygiai po 5, toliau 7, 9, 11 …). Daugiausia 60.
   Keičia `xp_level()` (`app/xp.php`), todėl pasikeis ir lygis vaikų pradžios puslapyje. Lygis neribojamas, dovanos - iki 60 lygio.
3. Dovanų dėžė: kiekvienas lygis (iki 60), kiekvienas renginys su „Buvo“, pergalės žaidime - 3 pergalės per savaitę (pirmadienis-sekmadienis) = 1 dėžė, ne daugiau.
4. Tikri prizai: kiaušinis 3, 8 ir 15 lygyje; naujas atsitiktinis personažas kas 5 lygius (atskira dovana su animacija; pradžioje - Kitsunė).
5. Dėžės svoriai kaip dabar.
6. Daiktų dėžėse bent 3 × tiek, kiek įprastas vaikas gauna dovanų per metus.

## Ką rodo skaičiuoklė (numatyti skaičiai)
| Vaikas | XP/mėn. | Lygis po metų | Dovanų per metus | 3 gyvūnai po |
|---|---|---|---|---|
| Ramus (1×/sav.) | 5,5 | 11 | 25 | 8 mėn. |
| Įprastas (2×/sav., 2 varžybos) | 10,9 | 18 | 47 | 4 mėn. |
| Aktyvus (3×/sav., 5 varžybos) | 18,3 | 25 | 82 | 3 mėn. |

Dabartinė sistema (100 XP lygiui) tiems patiems vaikams po metų: 1, 2 ir 3 lygis.
Paleidimui: ~15 personažų, 12 gyvūnų, ~140 daiktų dėžėse (3 × 47).
