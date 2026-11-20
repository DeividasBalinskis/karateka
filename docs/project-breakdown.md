# karateka.lt: project breakdown

Written 2026-11-20 from the code on branch `claude/festive-curie-01jgx2` (site version 1.47, DB `043`).
Anything not provable from the code is marked **unclear**.

## 1. Overview

**What it is.** A monorepo with three websites that share one PHP codebase and one database:

| Part | What it does | Users |
|---|---|---|
| **karateka.lt** (`public_html/`) | Public club site plus member system for VšĮ Karate Ateitis, a traditional karate-do club in Vilnius: groups and schedules, events, news, lessons, exams, XP and points, chat with the head coach, quizzes | visitors, members, parents, coaches, the admin (the head coach, "Denis" in the code) |
| **Budo** (`admin/varzybu-sistema.php`, `VARZ_NAME = 'Budo'`) | Competition-day system: clubs, entries, categories, draws (elimination, round robin, kata scores), live schedule per tatami, bout panel, broadcast screen, results, diplomas, awards tablet | coaches, secretariat (link), other clubs (registration link), spectators (live link) |
| **LTKF** (`public_html/ltkf/`) | Draft site of the Lithuanian Traditional Karate Federation (no database for most content) | federation editors (link), the public |

Also inside karateka.lt: a kids' game ("Dojo", arena, connect-four, rhythm game). Since `db/043` only Denis's account can see it.

**Current state**
- **Working, in staging** (test.karateka.lt); the README says it "will replace the current site". Which code runs on live karateka.lt is **unclear**.
- **Done:** member system, coach tools, exams, Budo, feedback widget, EN/RU translations.
- **Half-done:**
  - Game: hidden; the new arena is still a prototype in `kova-bandymai.php`, and no variant has been chosen yet.
  - Exam payments, certificates and prices: UI placeholders marked "reikia info".
  - LTKF site: `ltkf/inc/nustatymai.php` has `'demo' => true`, and the content files hold "Pavyzdys" placeholders.
- **Planned:** see section 8 (`docs/veliau.md`).

## 2. Setup

**Stack**
- PHP 8 with no framework; pages are server-rendered.
- MariaDB 10.11 or MySQL, accessed through PDO.
- Plain JS and CSS with no build step. The only vendored library is `assets/vendor/sortable.min.js`.
- Hosting: Apache shared hosting (`.htaccess`, no cron).
- Tests: Playwright (Chromium), plus `tests/checks.sh`, run by GitHub Actions.

**Folder tree (2 levels)**
```
.github/workflows/      CI: tests.yml (checks + clean DB + Playwright on every push)
db/                     43 numbered SQL migrations (001…043) + README
docker/                 Dockerfile + php.ini for the local web container
docs/                   changelog (pakeitimai.md), later list (veliau.md), deploy + backup guides, img/
ltkf/data/              empty runtime folder for the LTKF site (gitignored data)
public_html/            web root
  admin/                coach / admin panel (36 pages) incl. Budo
  app/                  shared PHP (auth, data, layout, game, tournaments, mail, sms…), lang/, cli/ (blocked by .htaccess)
  assets/               CSS + JS (varzybos.js 3.2k lines, app.css 1.7k lines, game-*.js…), vendor/
  img/                  photos, partner logos, theme art (temos/), game art (zaidimas/, ~0.9 MB)
  ltkf/                 LTKF site: pages, inc/ (layout, settings, editing), turinys/ (content files), assets/
  uploads/              runtime uploads (news, lessons, events, feedback, diplomai, kopijos, ltkf); PHP denied
tests/                  Playwright suite (e2e/, 33 specs, 81 tests), checks.sh, setup/ (reset-db.sh, config.ci.php), fixtures/
tools/                  Windows PowerShell deploy/local scripts, demo data, translation and pixel-art tooling
```

**Run locally:**
1. Copy `.env.example` to `.env`.
2. Copy `config.example.php` to `public_html/config.php`.
3. Run `docker compose up -d`, then `docker compose exec web php app/cli/dev_seed.php`.

Local addresses: site **http://localhost:8088**, phpMyAdmin :8081, Mailpit :8025, MariaDB :3307, LTKF alone :8089, external Varžybos (if running) :8090.

Without Docker, `php -S 127.0.0.1:8088 -t public_html` works. Tests reset the DB with `tests/setup/reset-db.sh`.

**Deploy** (`docs/ikelti-i-test.md`, `tools/*.ps1`):
1. On Windows, `tools/atnaujinti-is-github.ps1` pulls `main` and builds `ikelimui/svetaine-vX.zip`. The migrations are copied to `app/db/`.
2. Upload the files by FTP or the hosting file manager. The server's `config.php` stays in place.
3. Admin clicks **"Atnaujinti duomenų bazę"** (`app/db_update.php`). It takes a backup first, then runs the missing migrations from 022 onward.

There is no automatic deploy. Release order is staging first, then live.

**Config files (names only):**
- `public_html/config.php` (per environment, gitignored; template `config.example.php`; sections env, site_url, base_path, force_https, notify_email, setup_key, retention, ranking, translate, ltkf_url, smtp, mail, sms, db, optional varzybos/backup).
- `.env` / `.env.example`, `tests/setup/config.ci.php`, `ltkf/inc/nustatymai.php` + `nustatymai.local.php` (gitignored), `docker/php.ini`, `tests/playwright.config.js`, `app/VERSION`.
- `site_settings` table: runtime switches (menu variant, phone menu, `reg_off`, `game_access`, exam prices, feedback API key hash).

## 3. Pages and features

**Roles:** public = anyone · member = logged-in active account · parent = account linked to children · coach = `require_staff()` (coach or admin) · admin = `require_admin()` · link = no account, secret token · club = other clubs via a link.

**Status:** done = works and is covered by tests or verified this session. partial = placeholders or a prototype. **unclear** = depends on a service that is not in this repo.

**Public site (karateka.lt root)**

| Route | Purpose | Who | Status |
|---|---|---|---|
| index.php | Home page made of blocks. Admin edits it in place (`?redaguoti=1`). | public (admin edits) | done |
| naujienos.php | News list with events column on the right; `?id=` for one post; reactions | public | done |
| renginiai.php / renginys.php | All events by month and type filter / one event with registration and countdown | public (members-only events hidden) | done |
| kalendorius.php | iCal feed (webcal) of public events | public | done |
| prisijungti.php, kodas.php, slaptazodis.php, atsijungti.php | Login, e-mail code (2FA), password reset, logout | public | done |
| registracija.php, patvirtinti.php, tevu-sutikimas.php, el-pastas.php | Sign-up (parent or self 14+), e-mail confirm, parent consent for under-14, e-mail change | public | done |
| jungtis.php, pakvietimas.php | Invite link for a member added by a coach / child's own login to an existing member | link | done |
| uzklausa.php | "2 free trainings" form → e-mail (JSON endpoint used by assets/home.js) | public | done, legacy standalone script |
| privatumas.php, kalba.php, tema.php | Privacy policy, language (lt/en/ru cookie), theme switch | public | done |
| egzamino-registracija.php | Exam sign-up without account (name, birth date, phone) | link | done |
| mano-egzaminas.php | Personal exam page by token: belt, programme, quiz link, fee | link | partial (payment/certificate placeholders) |
| testas-svecias.php | Quiz without account via share link | link | done |
| varzybu-rezultatai.php | Public competition results (club medals, podiums) | public | done |
| varzybos-gyvai.php | Live view per tatami for spectators; `ekranas=1` = TV screen | link | done |
| varzybos-registracija.php | Other clubs register athletes to a tournament | club / link | done |
| varzybos-apdovanojimai.php | Awards tablet: finished categories first, "✓ Apdovanota" | link | done |
| varzybos-sekretoriatas.php, varzybos-bandymas.php | Secretariat link (only that tournament) / Budo trial link for outsiders | link | done |
| ltkf-perziura.php, ltkf-redaktorius.php | LTKF preview link (feedback) / LTKF editor link (edit mode, name only) | link | done |
| atsiliepimas.php | Feedback widget backend ("💡 Atsiliepimas", screenshots) | public/member | done |
| atsiliepimai-api.php | Feedback API for automated review (X-Api-Key, hashed key) | key | done |

**Member / parent area (root, `require_login`)**

| Route | Purpose | Who | Status |
|---|---|---|---|
| paskyra.php | Account: "Svarbu" list, group, schedule, children, XP, settings | member, parent | done |
| uzduotys.php | All coach tasks, lessons and notes kept after "done/seen" | member, parent | done |
| egzaminas.php | Belt path, next belt programme ("Moku"), exam steps, quizzes | member | done |
| pamokos.php | Lessons in folders, blocks, search | approved member | done |
| testas.php | Quiz (training mode or real test) | member | done |
| klausimai.php | Public Q&A published by Denis | member | done |
| renginys-dalyvavimas.php, pranesimas.php, zinute.php, pokalbis.php, reakcija.php | RSVP; notification actions; messages to coach; chat API; news reaction | member (pokalbis also coach) | done |
| varzybos.php | Register for a tournament (categories by birth/gender/belt) | member, parent | done |
| mano-renginiai.php | Old URL → redirects to events | member | done (redirect) |
| perziura.php | Admin switches to Test Parent / Test Junior ("view as") | admin | done |
| dojo.php, arena.php, keturi.php, ritmas.php, zaidejas.php, zaidimas.php (API) | Game: hero world, turn-based fight, connect-four, rhythm game, other player's dojo | member **only if in `game_access`** (now: Denis) | done but hidden |
| kova-bandymai.php | New fight prototype with 5 switchable variants (browser only) | same as game | partial (prototype) |

**Coach / admin panel (`admin/`)**

| Route | Purpose | Who | Status |
|---|---|---|---|
| index.php ("Pradžia") | Today: to-dos, groups, events | coach | done |
| treniruote.php | Attendance per group/event, notes, homework, XP | coach | done |
| egzaminai.php, egzamino-protokolas.php, federacija.php, programa.php | Exam list; online protocol (±0.1 scores, results, print/Excel, send to federation); federation register; belt programme | coach | done (prices/payments/certificates partial) |
| nariai.php, patvirtinimai.php | Members (edit, belts, invite, delete); approvals of new accounts / "Moku" | coach | done |
| taskai.php, testai.php | XP awards, categories, ranking; quiz bank, share links, import | coach | done |
| renginiai.php, renginio-dalyviai.php, renginio-rezultatai.php | Events CRUD (attachment, photo), participants and invites, results → points | coach | done |
| naujienos.php, pamokos.php, puslapis.php | News editor; lesson folders/blocks; home-page edit actions | coach (puslapis: admin + instructors) | done |
| grupes.php, tvarkarastis.php, atsaukimai.php | Groups and colours; schedule and prices; cancelled trainings/holidays | coach (tvarkaraštis edits: admin) | done |
| zinutes.php | Coach inbox (replies, questions) | coach | done |
| nustatymai.php | Menu variants, phone menu, registration toggles, game access, own account | coach (most switches: admin) | done |
| vertimai.php | Paste EN/RU translations of Denis's texts | coach | done |
| pranesimai.php, sms.php | Announcements (account/e-mail/SMS), SMS log | admin | done (SMS live only with Twilio config) |
| paskyros.php, kopijos.php, testavimas.php, atsiliepimai.php, ltkf-perziura.php | Roles; backups + DB update; test people; feedback inbox; LTKF links | admin | done |
| pirmas-adminas.php | Create first admin on a new server (`setup_key`) | key | done |
| varzybos.php, varzybu-sistema.php | Budo: tournament list; full competition system | coach, secretariat link, trial link | done |
| varzybos-isorine.php, varzybos-sso.php, varzybu-dalyviai.php, varzybu-rezultatai.php | Bridge to an **external** Varžybos service via `config varzybos` | coach | **unclear** (needs external service not in repo) |

**LTKF (`public_html/ltkf/`)**
- Pages: `index, apie, naujienos, naujiena, renginys, kalendorius, klubai, klubams, teisejai, dokumentai, rezultatai, kontaktai, en, puslapis, kalba`. They are public, and status is **partial**: the "Juodraštis" banner is on and the content is placeholder.
- `redaguoti.php` (JSON edit actions) and `failai.php` (file box): editors via link, admin.

## 4. Data

**Core relations:**
- `accounts` 1–n `account_members` n–1 `members`, with relation `self` or `parent`.
- `members.group_id` → `training_groups`.
- Most activity tables point to `members` (training) and/or `accounts` (login).

| Area | Tables (key columns → relations) |
|---|---|
| People | `accounts` (email, password_hash, role member/coach/admin, status, two_factor, theme) · `members` (names, birth_date, group_id→training_groups, belt_level, status) · `account_members` (account_id, member_id, relation) · `member_tags` |
| Auth | `email_tokens` (purpose, token_hash) · `login_attempts` · `login_codes` (code_hash) · `trusted_devices` (token_hash) |
| Groups | `training_groups` (category, colour) · `schedule` (group_id, weekday, times) · `category_prices` · `training_cancellations` + `training_cancellation_groups` · `attendance` (member_id, training_date, group_id, present) |
| Events | `events` (type exam/competition/seminar/camp/other, dates, registration, members_only, file_path, cover, tournament_id) · `event_groups` · `event_participants` (status, role) |
| News | `news` · `news_media` · `reactions` (news_id, account_id, emoji) |
| XP / points | `point_categories` (code, points) · `point_awards` (member_id, category_id, event_id, awarded_by) |
| Coaching | `coach_notes` (member_id, is_task, done_at, read_at, lesson_id) · `member_messages` · `notifications` (account_id, member_id, type, important) · `broadcasts` · `anon_feedback` |
| Lessons | `lessons` · `lesson_folders` (parent_id tree) · `lesson_blocks` · `lesson_groups` |
| Exams | `belt_requirements` + `requirement_progress` · `exam_protocols` (per event) · `exam_protocol_rows` (number, scores, result, token, paid_at, cert_at) · `exam_signup_links` · `exam_signups` · `federation_people` (fed_no) · `federation_exams` |
| Quizzes | `quizzes` (share_token) · `quiz_questions` · `quiz_answers` · `quiz_attempts` · `quiz_attempt_answers` · `quiz_guest_attempts` |
| Budo | `tournaments` (state JSON, results JSON, version, tokens share/reg/awards, is_test, event_id) · `tournament_live` · `tournament_entries` · `tournament_versions` (history) · `tournament_awards` (cat_id, status sent/done) |
| Game | `game_heroes` (member_id/account_id, species, wins, rating) · `game_items` · `game_pets` · `game_boxes` · `game_matches` (a_hero, b_hero, state) · `game_scores` |
| Site / misc | `site_settings` (name → value) · `content_translations` (hash, lang) · `feedback` + `feedback_images` · `sms_messages` + `sms_recipients` · `ltkf_pages` · `ltkf_files` · `db_versija` (applied migrations) |

**Migrations:**
- 001 schema · 002 groups · 003 points · 004 change e-mail · 005 public site · 006 coach notes · 007 lessons
- 008 belts/tasks/visibility · 009 attendance · 010 attendance points · 011 site settings · 012 Varžybos link
- 013 participants/tags · 014 exams+quizzes · 015 SMS · 016 lesson folders · 017 invite-only events · 018 messages
- 019 WTKU referee quiz · 020 lesson pages/home blocks · 021 notifications/registration/XP · 022 chat+group colours+`db_versija`
- 023 login codes/cancellations · 024 translations · 025 feedback+tournaments · 026 attendance XP/Varžybos
- 027 tournament registration consents · 028 quiz share link/event file · 029 guest quiz e-mail · 030 tournament history
- 031 exam protocol · 032 feedback images · 033 awards · 034 event registration URL · 035 game
- 036 quick members/invites · 037 federation/themes · 038 LTKF content+exam sign-up · 039 game abilities
- 040 exams without account · 041 remove panda/tiger/raven · 042 event photo · 043 game visible only to Denis

All of 022+ are re-runnable and record themselves in `db_versija`.

## 5. Design

**Three separate design systems plus the game:**

| Site | Colours (tokens) | Fonts | Radius |
|---|---|---|---|
| karateka.lt (`assets/app.css`, `header.css`, `themes.css`, `home.css`) | `--ink #17140F`, `--cream #F7F4EC`, `--accent #2F6B5A` (green), `--accent-dark #204B3F`, `--pink #A85C7E`, `--blue #5C68A0`, `--danger #B5281F`; "Valdymas" gradient `#5B5FDA→#8A4FC9→#C2569A` (hard-coded); 13 themes in `app/themes.php` (light, nature, mountains, sea, dunes, sakura, autumn, winter, dojo, dark, aurora, space, volcano) **Space Grotesk**: headings, buttons (41 rules); **Inter**: body, header/menu (≈18); **JetBrains Mono**: kickers, dates, numbers (≈38). All three loaded in `app/layout.php` | `--radius 16px`, but literal 3–20px used (12px ×38, 10px ×35, 8px ×28…) |
| Budo (`assets/varzybos.css`) | `--bg #F3F4F7`, `--surface #fff`, `--ink #12151C`, `--accent #D7263D` (red), `--green #1F9254`, `--amber #A86A0C`; discipline colours kata blue, kumite red, fukugo violet, kihon green, enbu orange, sumo teal | **Inter** only (loaded in `app/tournaments.php`, `admin/varzybu-sistema.php`) | `--radius 14px` |
| LTKF (`ltkf/assets/ltkf.css`) | `--navy #0E2A47`, `--paper #F4F5F2`, `--red #B3202E`, `--gold #F2B705`, `--green #00704A` + event-type colours | **Barlow Condensed**: headings; **Source Sans 3**: body | `--radius 10px` |
| Game (`game-px.css`, `game.css`, `game-lab.css`) | `--g-ink #1F1B3A`, indigo "night" and violet button gradients | **Pixelify Sans**: game UI | mixed |

**Spacing:** no spacing tokens in any stylesheet (literal paddings/gaps). Page width: `.page` max 1120px, `.page.wide` 1480px; Budo centred at max 1680px (public pages 1440px).

**Main components:** header (grouped nav, "Apie klubą ‹" fold, LT|EN|RU, theme picker); admin side menu (Kasdien / Klubas / Tvarkyti), phone row or bottom tab bar; `.panel.card`; `.btn` (`-primary`, `-ghost`, `-sm`); chip tabs; `.badge` / `.count-badge`; `.notif` list ("Svarbu"); event and news cards; floating 💡 feedback and chat buttons; celebration modal. Budo: toolbar, pills, bout panel, score cards, bracket, awards cards.

**Inconsistencies:**
1. `--accent` means green in karateka.lt and red in Budo. The admin "Valdymas" purple is outside both palettes.
2. Five font families across the sites (Space Grotesk, Inter, JetBrains Mono, Barlow Condensed/Source Sans 3, Pixelify Sans), each site with its own set.
3. 25 different media-query breakpoints (380 … 1500px, including 640/641, 860/861, 1099/1100/1101).
4. 127 distinct hex colours in `app.css` + `header.css` alone. `!important`: 30 in `game-px.css`, 23 in `app.css`, 14 in `header.css`.
5. Radii: tokens of 16, 14 and 10px, plus many literals.
6. `app.css` grows by appended "vX.YY" override blocks rather than edits to the original rules.
7. Federation name: the Budo footer (`app/tournaments.php:529`) says "karatė"; everywhere else it is "karate".

## 6. Integrations

**Karateka ↔ Budo**
- Budo is **inside** this codebase (`app/tournaments.php`, `admin/varzybu-sistema.php`, `assets/varzybos.js`).
- Its state is saved as JSON with optimistic locking (`version`, HTTP 409 on conflict).
- Snapshots go to `tournament_versions`. Live data goes to `tournament_live`.
- When a category finishes, it is synced into `tournament_awards` on every save (#122).
- Members register from `varzybos.php`. Results can award points via events.

**Karateka ↔ external Varžybos service (`app/varzybos.php`):**
- REST calls with `X-Api-Key` to `config varzybos.url`. The comment names a hostname, varzybos.karateka.lt.
- SSO link: `admin/varzybos-sso.php`.
- Pages: `admin/varzybos-isorine.php`, `varzybu-dalyviai.php`, `varzybu-rezultatai.php`.
- LTKF also calls `{url}/api.php?r=v1/...` with a 60-second file cache (`ltkf/inc/pagalbines.php`).
- There is **no `api.php` in this repo**, so that service lives elsewhere. Whether it is still used is **unclear**.

**E-mail:**
- `app/mail.php`: a hand-written SMTP client (stream sockets), `config mail`. Locally the mail goes to Mailpit.
- Sends: verification, parent consent, password reset, login codes, invites, announcements, guest quiz results, exam protocol to the federation.
- `uzklausa.php` has its **own second SMTP client** and uses `config smtp`.

**SMS:** `app/sms.php` uses Twilio (curl) when `sms.provider = twilio`. Otherwise messages are only logged to `sms_messages`. The provider choice is still open in `docs/veliau.md`.

**Uploads:**
- Images are re-encoded to JPEG (1600px + 640px thumb) with random names (`app/images.php`).
- Folders: `uploads/news`, `lessons`, `events` (attachments plus `covers/`), `feedback`, `diplomai`, `ltkf`, `kopijos`.
- `uploads/.htaccess` denies scripts. `kopijos/` and `diplomai/` deny all.

**Logins:**
- Session cookie `karateka_sid` (HttpOnly, SameSite=Lax, Secure when `force_https`); `password_hash`; throttling per e-mail and IP (`login_throttled`); optional e-mail code + 30-day trusted devices (`app/two_factor.php`); hashed tokens (`app/tokens.php`); HSTS, X-Frame-Options, nosniff (`app/bootstrap.php`).
- No-account secret links: Budo secretariat/trial/live/registration/awards, LTKF editor/preview, exam sign-up and personal exam page, quiz share. "View as" test accounts use `$_SESSION['view_admin']`.

**Other:** daily backups (pure PHP + gzip, 14 kept, triggered on page load since there is no cron, `app/backup.php`); YouTube embeds; Google Fonts; iCal feed; feedback API for automated reviews.

## 7. Problems (top 10, ranked)

1. **Security / spam: unprotected trial form.** `public_html/uzklausa.php` is a standalone script:
   - no bootstrap, CSRF, rate limit or honeypot
   - a hard-coded recipient
   - its own SMTP client (a duplicate of `app/mail.php`) and a separate `smtp` config
   - it returns which SMTP step failed

   Called from `assets/home.js:120`.
2. **Data integrity on competition day.** All of a tournament is one JSON `state` saved whole with a version check (`app/tournaments.php` `tournament_save_state`; `admin/varzybu-sistema.php` save endpoint; conflict UI in `assets/varzybos.js`). Several tatami/secretariat devices editing at once can get "Kitas žmogus pakeitė varžybas" and must reload. How often this happens in practice is **unclear**. Every save also runs one query per finished category (`tournament_awards_sync`).
3. **Messy code / regressions risk.** `assets/varzybos.js` is a single 3,200-line closure. A duplicate function name (`hhmm`) silently broke the schedule time in v1.47 until the tests caught it. Other large files: `app/game.php` (874 lines), `app/layout.php` (804 lines, menu variants for desktop and phone).
4. **CSS sprawl.** `assets/app.css` (1.7k lines) with appended per-version overrides, 25 breakpoints, 127 hex colours, `!important` overrides (`game-px.css`, `app.css`, `header.css`) and no spacing scale. Hard to change one component safely.
5. **Mobile UX.**
   - The floating "💡 Atsiliepimas" and chat buttons cover content and inputs at the bottom of phone screens (`app/feedback.php`, `assets/widgets.css`, `assets/chat.js`).
   - The Budo header plus two tab rows take about 300px on a phone (`admin/varzybu-sistema.php`, `assets/varzybos.css`).
6. **Two competition integrations.** The built-in Budo exists alongside the bridge to an external Varžybos API (`app/varzybos.php`, `admin/varzybos-isorine.php`, `varzybos-sso.php`, `varzybu-dalyviai.php`, `varzybu-rezultatai.php`, `ltkf/inc/pagalbines.php`). LTKF calendar/results expect `api.php`, which is not in the repo. It is **unclear** which one is meant to be used.
7. **Tests depend on shared DB state and order.** One example: a level-up celebration left by `01-scenarios` blocked clicks in `06c` and `06n` when `01` failed early. The full suite takes about 32 minutes (`tests/e2e/*.spec.js`, `tests/setup/reset-db.sh`).
8. **Polling on every page** (shared hosting, no push): chat badge (`assets/chat.js`), notifications every 60 s (`assets/notify-popup.js`), game invites every 15 s (`assets/game-invites.js`), Budo awards every 10 s, awards tablet every 4 s, live views (`assets/varzybos*.js`).
9. **Game half-state.**
   - Hidden by `site_settings.game_access` (`db/043`), but all game code and art ship.
   - `zaidimas.php:18` answers logged-in users without access with "Prisijunkite." ("log in"), which is misleading.
   - The arena redesign (#109) waits for a variant choice (`kova-bandymai.php`, `assets/game-lab.js`).
10. **Naming leftovers.** "Taika", the old name of Budo, survives in test files (`06f-taika-…`, `06i-taika-…`, `06k-taika-…`) and comments. Lithuanian, English and mixed identifiers are used side by side (`varzybos`, `tournaments`, `exam_*`, `egzamino-*`).

## 8. TODOs and open questions

- **In code:** no `TODO`/`FIXME` markers were found. Open items live in `docs/veliau.md` and in UI placeholders marked "reikia info".
- **Exams:** online payment provider (Paysera, Montonio or bank link) and contract details; certificate template and who signs; whether the price depends on belt only or also on club (`admin/egzaminai.php`, `mano-egzaminas.php`, `app/exam_signup.php` `exam_prices`).
- **Game:** choose the arena variant (1–5), then move it to the server/real fights, translate EN/RU, cards and eggs for points; trading items and separate "artefacts" undecided; who should see the game (`game_access`).
- **Site:** summer schedule from a date; push notifications; what instructors may see; payments in "Svarbu"; deleting former members after 1 year (manual today); AI helper in the admin (idea only).
- **Open choices:** SMS provider (also add to the privacy policy); names for "Valdymas" / "Pradžia" (feedback #114); new themes (#112); feedback #30 is cut off ("Kai b…").
- **LTKF:** real content for the "Pavyzdys" items and `[brackets]`, then `'demo' => false`; own domain; club accounts and two-way sync with karateka.lt (`docs/veliau.md`, `public_html/ltkf/README.md`).
- **Deploy:** live karateka.lt still serves the old site. Cut-over date and process are **unclear** (there is a `release-check` skill; no script in the repo).

## 9. Spelling: "karatė" vs "karate"

- The site uses **"karate"**: about 600 occurrences in PHP/JS. Examples: "Karate Ateitis", "karate-do", "Lietuvos tradicinio karate federacija" (LTKF pages), "LIETUVOS TRADICINIO KARATE DO FEDERACIJA" (exam protocol).
- **"karatė" appears 9 times.**
  - The one **user-visible** case is the Budo footer link "Lietuvos tradicinio karatė federacija (LTKF)" (`app/tournaments.php:529`, plus its keys in `app/lang/en.php`/`ru.php`).
  - The rest are code comments (`arena.php`, `dojo.php`, `app/game_fight.php`, `assets/game-arena.js`).
- **Verdict:** almost consistent ("karate"), with **one inconsistent visible string** to fix. "karate-do" vs "karate do" also varies: "karate-do" in code, "karate do" in some event titles and the protocol header.
