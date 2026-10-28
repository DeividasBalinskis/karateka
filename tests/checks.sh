#!/usr/bin/env bash
# Greitos patikros be naršyklės: PHP sintaksė, db failų tvarka, PowerShell skriptų koduotė, ar neįkelti slaptažodžiai.
set -uo pipefail
cd "$(dirname "$0")/.."
fail=0
err() { echo "KLAIDA: $*"; fail=1; }

# PHP sintaksė
while IFS= read -r -d '' f; do
  out=$(php -l "$f" 2>&1) || err "$out"
done < <(find public_html tools -name '*.php' -print0)

# Naujausias db failas = DB_REQUIRED (app/db.php); nuo 022 kiekvienas failas įsirašo į db_versija
latest=$(ls db/[0-9][0-9][0-9]_*.sql | sort | tail -1 | xargs basename)
grep -q "const DB_REQUIRED = '$latest';" public_html/app/db.php || err "app/db.php DB_REQUIRED turi būti '$latest'"
for f in db/[0-9][0-9][0-9]_*.sql; do
  n=$(basename "$f"); num=$((10#${n:0:3}))
  if [ "$num" -ge 22 ] && ! grep -q "INSERT IGNORE INTO db_versija (failas) VALUES ('$n');" "$f"; then
    err "$f: pabaigoje trūksta INSERT IGNORE INTO db_versija (failas) VALUES ('$n');"
  fi
done

# PowerShell 5 lietuviškas raides skaito teisingai tik su UTF-8 BOM
for f in tools/*.ps1; do
  [ "$(head -c 3 "$f" | od -An -tx1 | tr -d ' ')" = "efbbbf" ] || err "$f: be UTF-8 BOM"
done

# config.php su slaptažodžiais niekada nekeliamas
if git ls-files --error-unmatch public_html/config.php >/dev/null 2>&1; then err "public_html/config.php įkeltas į git"; fi

[ $fail -eq 0 ] && echo "Patikros: viskas gerai"
exit $fail
