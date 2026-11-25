#!/usr/bin/env bash
# C6: greitas testų rinkinys (~3 min) - po kiekvieno pakeitimo; pilnas rinkinys (npx playwright test) - prieš įkėlimą.
# Paleidimas iš projekto šaknies:  bash tests/smoke.sh
# Pirmiausia - greitos patikros, tada duomenų bazė iš naujo ir svarbiausi scenarijai (prisijungimas, paskyra, egzaminai,
# pranešimai, Budo telefone, kodas kitam įrenginiui, vaiko prisijungimas).
set -e
cd "$(dirname "$0")/.."
bash tests/checks.sh
node --experimental-vm-modules --test tests/engine/*.test.mjs   # Budo variklis be naršyklės (C6)
bash tests/setup/reset-db.sh > /dev/null
cd tests
start=$(date +%s)
npx playwright test --reporter=line --retries=0 --trace off \
  e2e/02-two-factor.spec.js e2e/07h-v153.spec.js e2e/07i-v154.spec.js e2e/07j-b5-pranesimai.spec.js \
  e2e/07k-a3-egzamino-nuorodos.spec.js e2e/07f-v150.spec.js "$@"
echo "Greitas rinkinys: $(( $(date +%s) - start )) s"
