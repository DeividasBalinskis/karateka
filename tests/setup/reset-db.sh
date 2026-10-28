#!/usr/bin/env bash
# Švari testų duomenų bazė: visi db/0xx failai, pavyzdiniai duomenys (dev_seed, demo-duomenys) ir TEST pavyzdžiai.
# DĖMESIO: ištrina duomenų bazę DB_NAME. Naudoja public_html/config.php (turi būti env = local).
# Kintamieji: DB_HOST (localhost), DB_USER (root), DB_PASS (tuščias), DB_NAME (karateka).
set -euo pipefail
cd "$(dirname "$0")/../.."

DB_NAME=${DB_NAME:-karateka}
MYSQL=(mysql --default-character-set=utf8mb4 -h"${DB_HOST:-localhost}" -u"${DB_USER:-root}")
if [ -n "${DB_PASS:-}" ]; then MYSQL+=(-p"$DB_PASS"); fi

"${MYSQL[@]}" -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
for f in db/[0-9][0-9][0-9]_*.sql; do
  "${MYSQL[@]}" "$DB_NAME" < "$f"
done
(cd public_html && php app/cli/dev_seed.php > /dev/null)
php tools/demo-duomenys.php | "${MYSQL[@]}" "$DB_NAME"
(cd public_html && php app/cli/dev_pavyzdziai.php > /dev/null)
echo "Testų duomenų bazė paruošta: $DB_NAME"
