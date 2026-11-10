# Tests

Browser tests (Playwright) and quick static checks. They run on every push in GitHub Actions
(`.github/workflows/tests.yml`): MariaDB service, PHP built-in server, Chromium.

| File | What it covers |
|---|---|
| `checks.sh` | PHP syntax, migration order (`DB_REQUIRED`, `db_versija`), UTF-8 BOM in PowerShell scripts, no `config.php` in git |
| `e2e/01-scenarios.spec.js` | Main flows on demo data: parent → coach/admin → member → guest (notifications, invitations and deadlines, chat, comments, XP, attendance, broadcast, settings) |
| `e2e/02-two-factor.spec.js` | Login with an e-mail code: enabling, trusted devices, attempt and resend limits |
| `e2e/03-cancellations.spec.js` | Cancelled trainings and holidays: dashboard, attendance page, member notice, delete restores |
| `e2e/04-backups.spec.js` | Database backup: created, downloadable, complete; admin only |
| `e2e/05-lessons.spec.js` | Lessons: kyu and dan folders in belt colors, kata video in each belt folder, photo without a title, subfolders, what a member sees |
| `e2e/06-app.spec.js` | Installable phone app: manifest, icons, service worker, offline page without personal data |
| `e2e/06d-renginiai-xp-gyvai.spec.js` | Past event attendance on the training page, XP for several and +XP; competition bout panel (kumite points, timer), live viewer link and tatami screen, club registration link (one club field, parent phone, consents), editable score, schedule and viewer open any group, tatami screen group list, no-photo badge, one-click DB update |
| `e2e/07-pages.spec.js` | Every page for every role at desktop and phone width: no PHP/JS errors, no horizontal scroll |
| `e2e/08-test-data.spec.js` | One-time purge of old demo data (real people and content stay, backup first), test people generator, admin view switch (parent / child, belt), deleting test people and members (runs last) |

## Running locally

Needs PHP 8, MariaDB/MySQL client and Node.js. The reset script **drops and recreates** the database `DB_NAME`.

```bash
cp tests/setup/config.ci.php public_html/config.php   # or your own local config (env => local)
php -S 127.0.0.1:8088 -t public_html &
cd tests
npm ci && npx playwright install chromium
DB_HOST=127.0.0.1 DB_USER=root DB_PASS=root npm run test:clean
```

`npm test` runs the tests without resetting the database (the scenarios expect fresh demo data).
