#!/usr/bin/env bash
# C4: vaizdinė apsauga prieš CSS darbus (C5). Ta pati duomenų bazė ir ta pati diena - kitaip skiriasi datos ir skaičiai.
#   bash tests/visual.sh update   - padaryti pradines nuotraukas (prieš keičiant CSS)
#   bash tests/visual.sh          - palyginti su pradinėmis; skirtumai: tests/visual-report/index.html
set -e
cd "$(dirname "$0")"
if [ "${1:-}" = "update" ]; then
  npx playwright test -c visual/playwright.config.js --update-snapshots
else
  npx playwright test -c visual/playwright.config.js
fi
