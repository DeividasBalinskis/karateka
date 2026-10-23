# LTKF svetainė (juodraštis)

Lietuvos tradicinio karatė federacijos svetainė. Paprastas PHP, be duomenų bazės.

- Paleidimas kompiuteryje: `docker compose up -d` projekto šaknyje → http://localhost:8089
  (arba be Docker: `php -S localhost:8089 -t ltkf`)
- Tekstai ir sąrašai - aplanke `turinys/` (klubai, renginiai, naujienos, dokumentai, rezultatai, federacija).
  Vėliau juos keis redaguojamo turinio sistema, kaip karateka.lt.
- Varžybos: jei paleista varžybų sistema (http://localhost:8090), kalendorius ir rezultatai jas parodo automatiškai.
  Serveryje adresas ir raktas - `inc/nustatymai.local.php` (neįkeliamas į GitHub).
- Žymė „Pavyzdys“ ir [laužtiniai skliaustai] - vietos, kurioms reikia tikrų duomenų.
  Kai viskas įrašyta, `inc/nustatymai.php` → `'demo' => false`.
