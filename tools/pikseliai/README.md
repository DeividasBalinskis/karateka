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
