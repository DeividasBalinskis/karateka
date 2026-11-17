# Pikselinis žaidimo menas

Viskas, ką žaidime matome pikseliais, sugeneruojama čia ir įrašoma į `public_html`:

| Skriptas | Ką daro | Kur rezultatas |
|---|---|---|
| `fox_build.py` | Lapė (kitsunė) iš `nat.npy` (originalas 59×71) ir `mask.npy` (kimono plotas): sritys (kailis, kimono, obi, liepsnos, kontūrai), ploni kontūrai (2× tinklelis), indeksuota paletė | `fox.json` |
| `pets_build.py` | Gyvūnėliai - pūkų gumulėliai (11 formų), perdažomi | `pets.json` |
| `icons_build.py` | Ikonos 16×16 iš ASCII žemėlapių (kova, greita, keturi, ritmas, smūgis, blokas, jodan/chudan/gedan, sabaki, kiai, zanshin, kime) | `icons/*.png` |
| `arena_bg.py` | Kovos arena - mėnesienos dojo 240×135 | `arena-dojo.png` |
| `scenes.js` | Kitos scenos (SVG) → rastrinės (reikia Playwright) | `raw-*.png` |
| `raster.js` + `raster_post.py` | Namai, dekoracijos, daiktų ikonos (SVG) → pikseliniai paveikslėliai (reikia veikiančios svetainės ir testinės paskyros) | `ras-px/*.png` |
| `export.py` | Surenka viską į svetainę | `assets/game-px-data.js`, `img/zaidimas/`, `img/temos/px/` |

Eilė: `python3 fox_build.py && python3 pets_build.py && python3 icons_build.py && python3 arena_bg.py && node scenes.js && node raster.js && python3 raster_post.py && python3 export.py`.

Naršyklėje piešia `public_html/assets/game-pixel.js`: paletės įrašas = sritis + tonas, todėl tas pats piešinys perdažomas pagal žaidėją
(kailis, liepsnos, kimono, tikras diržas).

**Naujas personažas:** pikselinis PNG (apie 60×70 „didelių“ taškų, baltas fonas, tamsus kontūras) → `fox_build.py` kopija su jo sritimis
(kimono kaukė, diržo ir apykaklės stačiakampiai) → naujas įrašas `game-px-data.js` ir `game-pixel.js` (`hero()` pagal `species`).

## Personažai (v1.40)
`herojai/personazai-5x5.jpg` - 25 personažai ant juodo fono (5×5). `python3 herojai/extract.py herojai/personazai-5x5.jpg` iškerpa kiekvieną
(fonas tampa permatomas, uždari tamsūs „langai“ - irgi; piešinys nekeičiamas) į `raw-RC.png`. Tada `.webp` → `public_html/img/zaidimas/herojai/<raktas>.webp`,
o raktas, vardas ir į kurią pusę žiūri - `GAME_PX_CHARS` faile `app/game.php`. Naujas personažas: paveikslėlis ant juodo fono → tas pats skriptas → eilutė `GAME_PX_CHARS`.

## Fonai (v1.40)
`fonai/kit.py` (gradientai juostomis, kalnai, medžiai, vanduo, toris, pagoda, žibintai) ir `fonai/scenes.py` - 10 vietų 320×180:
`python3 fonai/scenes.py <aplankas>` → kopijuoti į `public_html/img/temos/px/`. Dojo (su vaizdu pro langą) - `arena_bg.py`.
