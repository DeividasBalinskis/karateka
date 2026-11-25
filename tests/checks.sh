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

# „karate“, ne „karatė“ (taisyklė 6): ekrano tekstuose ir vertimų raktuose (komentarai ir taisyklės aprašas - išimtis)
bad=$(grep -rn "karatė" public_html tools/vertimai --include=*.php --include=*.js --include=*.tsv | grep -v "/lang/tl/\|ltkf/inc/lang/" | grep -vE "^[^:]+:[0-9]+:\s*(//|\*|#)" | grep -v "ne „karatė“" || true)
[ -n "$bad" ] && err "rašykite „karate“, ne „karatė“: $(echo "$bad" | head -3 | cut -c1-120)"

# Pranešimų tipai (B5): kiekvienas naudojamas tipas turi būti NOTIFY_TYPES sąraše (app/notifications.php)
known=$(grep -oP "^\s+'\K[a-z_]+(?=' +=> \[)" public_html/app/notifications.php | sort -u)
used=$( (grep -rhoP "notify_(admins|staff)\(\s*'\K[a-z_]+" public_html --include=*.php; grep -rhoP "notify_(account|member)\([^,()]+(\([^()]*\))?[^,()]*,\s*'\K[a-z_]+" public_html --include=*.php) | sort -u)
for ty in $used; do echo "$known" | grep -qx "$ty" || err "pranešimo tipas '$ty' nėra NOTIFY_TYPES sąraše (app/notifications.php)"; done

# C1: teisės tikrinamos tik per can() / require_can() (app/auth.php) - jokių tiesioginių rolių patikrų kitur
roles_re=$(cat <<'RE'
\b(is_admin|is_staff|is_tester|require_staff|require_admin)\(|\['role'\] *(===|!==|==|!=) *['"](admin|coach|member)['"]|['"](admin|coach|member)['"] *(===|!==|==|!=) *\$[a-zA-Z_]+\['role'\]|in_array\( *\$[a-zA-Z_]+\['role'\]|role *= *\\?['"](admin|coach)['"]
RE
)
bad=$(grep -rnE --include=*.php "$roles_re" public_html | grep -v "^public_html/app/auth.php:\|^public_html/app/cli/" || true)
[ -n "$bad" ] && err "teisės - tik per can('veiksmas') (app/auth.php PERMISSIONS): $(echo "$bad" | head -3 | cut -c1-140)"
# C1: kiekvienas PERMISSIONS veiksmas aprašytas docs/standartai.md lentelėje (ir atvirkščiai)
acts=$(awk '/^const PERMISSIONS = \[/{on=1; next} on && /^\];/{on=0} on' public_html/app/auth.php | grep -oP "^\s+'\K[a-z_]+(?=')")
for act in $acts; do grep -q "^| \`$act\` |" docs/standartai.md || err "veiksmas '$act' (app/auth.php) neaprašytas docs/standartai.md teisių lentelėje"; done
for act in $(grep -oP "^\| \`\K[a-z_]+(?=\` \| )" docs/standartai.md); do echo "$acts" | grep -qx "$act" || err "docs/standartai.md: veiksmo '$act' nėra app/auth.php PERMISSIONS"; done
# C1: kiekvienas POST tikrina CSRF žetoną (csrf_check / csrf_ok); išimtys - tik API su raktu antraštėje (docs/standartai.md, 2 skyrius)
csrf_free=" public_html/atsiliepimai-api.php public_html/diegimas-api.php public_html/perziura-api.php "
while IFS= read -r f; do
  case "$csrf_free" in *" $f "*) continue;; esac
  grep -qE "csrf_check\(\)|csrf_ok\(" "$f" || err "$f: priima POST, bet netikrina CSRF (csrf_check / csrf_ok)"
done < <(grep -rlE --include=*.php 'is_post\(\)|REQUEST_METHOD|php://input|\$_POST\[' public_html | grep -v "^public_html/app/")

# C2: kiekvienas nustatymas - registre (public_html/app/settings.php)
php tests/settings-check.php public_html || err "nustatymas ne registre (app/settings.php)"

# Vertimai (LT/EN/RU): tik praneša, kurių naujų tekstų dar neišversta
php tests/i18n-keys.php public_html | head -8

[ $fail -eq 0 ] && echo "Patikros: viskas gerai"
exit $fail
