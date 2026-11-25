# Standards (standartai)

**5-line summary**
1. One rule set for each back system. If a system had no rules, this file creates them.
2. A rule marked **today** is already true in the code. A rule marked **target** becomes true in the named step of `docs/darbo-planas.md`.
3. New code follows the target rules from now on, even before the step that cleans up old code.
4. Karate, competition and exam rules are never decided here. They come from Denis.
5. If a rule here and a decision in section 14 disagree, the decision wins.

Paths are relative to `public_html/` unless they start with `db/`, `tools/`, `tests/` or `docs/`.

---

## 1. Names

| Thing | Rule |
|---|---|
| Competition system | **Budo**. Never "Taika" in visible text. Old internal keys (`taika_*`) keep working until step C2 renames them. |
| Game | **Dojo**. |
| Points | One system (v1.49, Deividas): **XP = aktyvumo taškai**. Shown as "45 XP · aktyvumo taškai"; menus and pages keep "Aktyvumo taškai". Levels come from XP; every new level gives a gift box in the Dojo. |
| Sport | **karate**, never "karatė". **karate-do** with a hyphen. |
| Call to action for guests | **Išbandyti** (the header still says "2 treniruotės nemokamai" — ask Denis, step B9). |
| Members | "nariai", not "karatekai". |
| Federation name | One constant, used by LTKF, Budo, the footer and the exam protocol. The official spelling comes from Denis (question 30). |
| Staff area | "Valdymas"; its start page has one name in the menu, the title and EN/RU (decision 2). |
| Code | English `snake_case` for setting names, link kinds, notification types, table and column names. Lithuanian file names that exist stay; routes are not renamed without permission. |
| One name per place | A destination has one name; a name leads to one destination. No second "Nustatymai" or "Mano paskyra". |
| Icons | One icon per destination. ▾ only on things that open. Links have no arrow. |

## 2. Roles and permissions

**Model (today since C1).** Three roles stay in the database: `member`, `coach`, `admin`. "Testuotojas" is a flag on an admin account, not a fourth role. Real admin = admin without the flag. Link roles (secretariat, awards, LTKF editor, reviewer, trial) are not accounts.
For the permission table the code reads six roles from an account (`account_role()` in `app/auth.php`): **guest** (not logged in, or the account is not active yet),
**member** (also a child with own login), **parent** (a member with children), **coach**, **tester** (admin or coach role with the flag), **admin** (real admin).

**Rules**
1. **Today (C1):** one source: `can('action', $account)` in `app/auth.php` (const `PERMISSIONS`). Pages call `require_can('action')`. No raw role checks elsewhere — `tests/checks.sh` fails on `is_admin(` / `is_staff(` / `['role'] === …` / `role = "admin"` outside `app/auth.php`, and on an action that is in the code but not in the table below (or the other way round).
2. **Today (C1):** never allow something by a person's name. Lists of people hold account ids chosen in the screen. The homepage editors are accounts marked in Paskyros („🏠 Redaguoja pradžią“, setting `home_editors`), not names in the instructors block. Open: the excuse-letter recipients default to admins named Denis while `just_notify` is unset (decision 2026-11-24).
3. "Admin mode" and "Žiūrėti kaip" are display choices. They never grant or remove a right.
4. A missing visibility setting means **closed**.
5. **Today (C1):** role, status, tester, game-access, homepage-editor and password changes are written to `account_log` (db/061): who, when, account, old → new (no password values). Real admins read it in Valdymas → Nustatymai → Paskyros ir rolės → „Pakeitimų žurnalas“. Kept 1 year. Consent changes: not logged yet.
6. Dangerous changes need a button and a confirm that names the person and the new value. No saving on touch.
7. **Today (C1):** every POST checks the CSRF token: `csrf_check()` (HTML pages) or `csrf_ok()` (JSON). Exceptions are listed in this file only — APIs with a key in a header: `atsiliepimai-api.php` (feedback API), `diegimas-api.php`, `perziura-api.php`. `tests/checks.sh` fails on any other page that reads a POST and has neither.
8. **Code for another device** (v1.53, `app/device_codes.php`): only a real admin creates it; 8 signs, entered within 10 minutes, once;
   the device works with that admin's rights for 4 h / 12 h / 2 days and shows the „🔑“ bar; any real admin can disconnect it;
   only the hash is stored; every code and use stays in the log for 1 year. No shared "dev code" exists or may be added.
9. **Child login** (v1.53, B7): a parent creates it for a child aged 7+ (username + PIN 4–6 digits, no coach approval). The account
   is a normal member account whose e-mail is `username@vaikas.invalid` (never mailed). The child changes nothing in the account;
   the parent changes the PIN, chat and on/off. 5 wrong PINs in 15 min or 15 in a day lock it until the parent changes the PIN.

**Who may do what (target).** ✔ yes · – no · ◐ limited as noted

| Action | Guest | Parent | Young child (own login) | Older kid 14+ | Adult member | Coach | Testuotojas | Admin |
|---|---|---|---|---|---|---|---|---|
| See public pages, send the trial form | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Register, add a child, add self as trainee | ✔ | ✔ | – | ◐ self only | ✔ | ✔ | ✔ | ✔ |
| Create a child's login | – | ✔ | – | – | ✔ (own children) | – | – | ✔ |
| See own tasks, lessons, exam path, points | – | ✔ (children's) | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| See prices and fees | – | ✔ | – | ◐ ask Denis (question 28) | ✔ | ✔ | ✔ | ✔ |
| Answer invitations, register for events | – | ✔ | ◐ ask Denis | ✔ | ✔ | ✔ | ✔ | ✔ |
| Give or withdraw photo consent | – | ✔ | – | ✔ (own) | ✔ | – | – | ✔ |
| Write to the coach (chat) | – | ✔ | ◐ parent can switch off | ✔ | ✔ | ✔ | ✔ | ✔ |
| See Dojo (game) | – | per game access | per game access | per game access | per game access | per game access | **–** | per game access |
| Attendance, notes, Aktyvumo taškai | – | – | – | – | – | ✔ | ✔ | ✔ |
| Approve new accounts and children | – | – | – | – | – | ✔ | ✔ | ✔ |
| Create events; edit exam protocols | – | – | – | – | – | ✔ | ✔ | ✔ |
| Create exams; exam prices | – | – | – | – | – | – | ◐ read | ✔ |
| Run Budo competitions | – | – | – | – | – | ✔ | ✔ | ✔ |
| Create and switch off secret links | – | – | – | – | – | ◐ ask Denis | ◐ ask Denis | ✔ |
| Send a broadcast; send SMS | – | – | – | – | – | – | – | ✔ |
| Read and answer website feedback | – | – | – | – | – | – | ✔ | ✔ |
| "Žiūrėti kaip" | – | – | – | – | – | ◐ ask Denis | ✔ | ✔ |
| Site-wide switches (registration, menu, feedback button) | – | – | – | – | – | – | ◐ read | ✔ |
| Roles, tester flag, game access, backups and restore | – | – | – | – | – | – | **–** | ✔ |

Where today's code differs from the table above (kept as it was, waiting for an answer): testers do **not** open Valdymas → Atsiliepimai and do **not** have „Žiūrėti kaip“ (both real admin only); coaches see all feedback in the 💡 box.

**Permission table (today, the spec — `PERMISSIONS` in `app/auth.php`).** ✔ yes · – no. A new action is added in both places in the same change.

| Action | What it allows | guest | member | parent | coach | tester | admin |
|---|---|---|---|---|---|---|---|
| `member_self` | Paskyra: „+ Aš pats treniruojuosi“, „+ Pridėti vaiką“ (logged in, also before approval; never on a child's own login) | ✔ | ✔ | ✔ | – | – | – |
| `dojo` | Dojo, arena, games — only when the account is in „kas mato žaidimą“ (`game_access`); real admins always; the tester flag never | – | ✔ | ✔ | ✔ | – | ✔ |
| `dojo_menu` | „🏯 Dojo“ in the menu: as `dojo`, but while the game is not open to all — real admins only | – | ✔ | ✔ | ✔ | – | ✔ |
| `valdymas` | Valdymas, the staff header and menu, staff versions of pages (news drafts, lesson preview), admin mode, staff start page after login | – | – | – | ✔ | ✔ | ✔ |
| `members` | Nariai, Patvirtinimai (approve accounts and children), Grupės | – | – | – | ✔ | ✔ | ✔ |
| `training` | Treniruotė (attendance, notes), Pamokos, Programa, Tvarkaraštis and Atšaukimai (read) | – | – | – | ✔ | ✔ | ✔ |
| `xp` | Give Aktyvumo taškai | – | – | – | ✔ | ✔ | ✔ |
| `events` | Renginiai, invitations, participants, Naujienos | – | – | – | ✔ | ✔ | ✔ |
| `exam_protocols` | Egzaminai list, edit exam protocols | – | – | – | ✔ | ✔ | ✔ |
| `quizzes` | Testai (theory quizzes) | – | – | – | ✔ | ✔ | ✔ |
| `budo` | Run Budo competitions, stream page, secretariat preview | – | – | – | ✔ | ✔ | ✔ |
| `messages` | Chat and messages with members, Laiškų siuntimas | – | – | – | ✔ | ✔ | ✔ |
| `translations` | Vertimai (EN / RU) | – | – | – | ✔ | ✔ | ✔ |
| `federation` | Federacijos registrai — read | – | – | – | ✔ | ✔ | ✔ |
| `feedback_box` | All feedback items in the 💡 box | – | – | – | ✔ | ✔ | ✔ |
| `settings_view` | See the site switches in Nustatymai (tester: read only) | – | – | – | – | ✔ | ✔ |
| `accounts` | Paskyros ir rolės: roles, tester flag, „🏠 Redaguoja pradžią“, change log | – | – | – | – | – | ✔ |
| `game_access` | Who sees the game | – | – | – | – | – | ✔ |
| `settings` | Site-wide switches, „Visi nustatymai“, the Valdymas menu editor | – | – | – | – | – | ✔ |
| `backups` | Backups, Duomenų priežiūra, database update | – | – | – | – | – | ✔ |
| `device_codes` | Code for another device | – | – | – | – | – | ✔ |
| `testing` | Testavimas, „Žiūrėti kaip“ | – | – | – | – | – | ✔ |
| `feedback` | Valdymas → Atsiliepimai (read, answer, status) | – | – | – | – | – | ✔ |
| `review` | 🔎 Peržiūra | – | – | – | – | – | ✔ |
| `broadcast` | Pranešimai to all (e-mail and SMS) | – | – | – | – | – | ✔ |
| `sms` | Send SMS (also the „SMS tėvams“ boxes) | – | – | – | – | – | ✔ |
| `justifications` | Pateisinimai | – | – | – | – | – | ✔ |
| `exams` | Create and delete exams and their protocols, exam prices, waiting list, extra protocols | – | – | – | – | – | ✔ |
| `schedule` | Change the timetable, prices, group names and colours; cancel trainings | – | – | – | – | – | ✔ |
| `xp_settings` | Point categories and level cost | – | – | – | – | – | ✔ |
| `event_lists` | The final participant list and results of an event | – | – | – | – | – | ✔ |
| `members_admin` | Delete a member; the list of members without photo consent | – | – | – | – | – | ✔ |
| `budo_admin` | Budo: delete a real competition (coaches delete trial ones), trial link on/off, stream key, removing a tatami | – | – | – | – | – | ✔ |
| `ltkf` | LTKF site, changing the federation registers, federation approval of a competition | – | – | – | – | – | ✔ |
| `design` | Site theme, font test, „👍 Patinka“ | – | – | – | – | – | ✔ |
| `dojo_admin` | Dojo admin tools | – | – | – | – | – | ✔ |
| `home_edit` | Edit the homepage — also accounts marked „🏠 Redaguoja pradžią“ in Paskyros | – | – | – | – | – | ✔ |

**People who come by link (target, step C3)**

| Link role | May | May not |
|---|---|---|
| Secretariat | enter results and scores, send groups to awards, undo inside a group | reach other competitions, restore history, change tatami or registration settings (until Denis says otherwise, question 4) |
| Judge / tatami tablet | the same as secretariat, for the tatami chosen on that device | the same |
| Awards person | mark a group awarded, take it back | anything else |
| Examiner | a coach or admin account; no separate link | — |
| Spectator / TV | read the live page and the TV screen; search | see full names of minors |
| Other club | register its own athletes while registration is open | see other clubs' entries |
| Trial user | full Budo in trial competitions with made-up people | see members; delete other people's trial competitions |
| LTKF editor | edit page content, upload pictures and documents | change the federation name, contacts, menu deletions; delete for good (until Denis says otherwise, question 33) |
| LTKF reviewer | read the draft, leave feedback | edit |

**Accounts**
- States: account `pending_email → pending_parent → pending_approval → active | disabled`; member `pending → active → inactive`. A member is approved once.
- Every account may have 0–1 own member and any number of children.
- Minimum password length is one constant, also for accounts an admin creates. No password is ever shown again after it is typed.
- A password change or reset signs out all other devices — **today (C1)**: `accounts.session_ver` (db/061) grows on a change, a reset by link or a child's new PIN; sessions with the old number are signed out, the device that made the change stays in.
- Test people ("Žiūrėti kaip") have Lithuanian names and stay out of rankings, lists and exports.

## 3. Notifications, messages, e-mail, SMS

**Target: step B5 (badge and types), C8 (e-mail), C7 (polling).**

1. One list of types in `app/notifications.php`: `type → icon, class, audience, child_ok, popup, email`. A type that is not in the list is a bug; a check in `tests/checks.sh` looks for it.
2. Three classes:
   - **action** — someone must do something. Counts in the red badge until it is **done** (not until it is read). Has its button right there. When one staff member handles it, it is done for all.
   - **notice** — news. Never counts. Lists show the 2 newest; older ones fold under "Rodyti ankstesnius (N)"; one "Supratau visus" button. Hidden after 14 days.
   - **celebration** — shown once (level up, exam passed), then it is a notice.
3. One function gives every badge its number. A badge shows a real count of items, never a count of rows. The header number equals what the page lists.
4. Action items and notices are loaded separately. They never share a limit.
5. Stored as type + values + a relative link. The text is built when shown, in the reader's language. A link is never stored with the site prefix.
6. One channel between member and coach: the messages table, one staff inbox, one reply function, one e-mail rule.
7. E-mail: one file of templates; the reader's saved language; one footer; bulk mail goes through an outbox in batches with a result per person. No e-mail mentions the game while it is hidden.
8. SMS: only real admins, checked inside `sms_send_message()`. Every send shows who will get it and who has no number. Test mode ("log") is the default.
9. Kids: a type with `child_ok = no` is not shown on a child's own login. No money on a child's login.
10. Polling: one request for chat count, notices and game invites, every 30–60 s, paused in a hidden tab. Fast polling only for an open chat and for Budo live screens.
11. How long things are kept is decided by Denis (question 15) and written on the privacy page.

## 4. Settings and switches

**Target: step C2.**

1. One registry `app/settings.php`: `name → type, safe default, who may change it, where it is edited, label`. A name that is not in the registry is a bug (checked by `tests/checks.sh`).
2. Types: yes/no, number with limits, one of a list, list, small structured value, short text. **Not settings:** secret links (they go to the links table), page content, one-time migration marks.
3. Read through typed functions that know the default. Write through one function that checks the value, saves it, and logs who changed what.
4. Names: English `snake_case` with an area prefix (`site_`, `menu_`, `reg_`, `game_`, `exam_`, `xp_`, `feedback_`, `budo_`, `ltkf_`). Yes/no names are positive (`reg_join_open`, not `reg_off`).
5. Defaults are safe: visibility closed; a missing `env` means live.
6. Each setting has an owner: real admin, admin or coach. Site-wide switches ask before saving and can be undone from the log.
7. One map: Nustatymai → "Visi nustatymai" lists every switch with its current value and a link to where it is edited. Server-only items are shown read-only.
8. A person's choices (theme, language, admin mode, chat off) are saved on the account. A cookie is only a fallback for guests.
9. Cookies are set by one helper with the same path, `Secure`, `HttpOnly` and `SameSite` rules. The privacy page lists all of them.
10. `config.php` holds only server items and secrets. Every key is in `config.example.php` with a placeholder and a comment. No dead keys.
11. A switch that must change before go-live is never a constant in code. It is in the registry and on the launch checklist.

**Feature flags today**

| Flag | Where | Default today | Target default |
|---|---|---|---|
| Who sees the game | setting `game_access` (Nustatymai → „🏯 Žaidimas“) | missing = everyone | missing = nobody |
| Everything unlocked in the game | constant in `app/game.php` | on | a setting, off at launch |
| LTKF draft | setting `ltkf_mode` (Valdymas → LTKF svetainė → Būsena, v1.80) | missing = draft on test, off on live | off / draft / public |
| Registration open (accounts, events, exams, Budo) | setting `reg_off` | all open | same, positive names |
| Feedback button visible to | setting `feedback_button` | staff | same |
| External Varžybos | `config.php` block | hidden unless set | decided in D2 |
| SMS provider | `config.php` | `log` (test mode) | same |
| Tester | — | does not exist | flag on the account (A4) |

## 5. Secret links and keys

**Target: step C3. New links follow these rules from now on.**

1. Only `app/links.php` creates or checks a secret link.
2. A link's secret is long and random (32 bytes). The database keeps only its fingerprint. A link that must be copied again later also keeps a locked copy.
3. One address parameter for all link pages: `?k=`. Old `?t=` links keep working.
4. Every link has: kind, what it belongs to, a label, read or write, who made it, when, when it ends, how often and when it was last used, when it was switched off.
5. Every link shows three buttons in one shared block: **Kopijuoti · ↻ Nauja nuoroda · Išjungti**, and "sukurta …, paskutinį kartą naudota …".
6. A link that can write asks for a name first, gets a "Baigti darbą" button, and every change it makes is saved with that name.
7. No link is created just by opening a page. Creating is always a button.
8. A link never returns another person's existing link. Getting a lost link back needs a second proof or goes by e-mail or SMS.
9. Wrong guesses are counted per IP in the database, not only in the visitor's session.
10. On link pages minors are shown as "Vardas P.", phones are masked, birth dates show the year — unless Denis decides a role needs more.
11. Secret parts of addresses are never stored in feedback, logs or sessions.
12. One shared page for a link that does not work: "Nuoroda neveikia. Ji išjungta, pasibaigė arba pakeista. Paprašykite naujos nuorodos to, kas jums ją davė."
13. One admin page "Visos nuorodos" lists every active link.
14. Default lifetimes are proposed in the step and confirmed by Denis (questions 5, 21).

Kinds: `sekr`, `demo`, `live`, `reg`, `awards`, `quiz`, `exam_signup`, `exam_row`, `ltkf_editor`, `ltkf_review`, `join`, plus the e-mail kinds that already follow these rules (`app/tokens.php`).

## 6. Feedback loop

1. The 💡 box on the site is the single inbox. Something said in chat or by phone is typed into it (by whoever hears it) so it gets a number.
2. Every item ends in one of: **Padaryta (vX.YY)** + the click path · **Suplanuota** + the plan step · **Klausimas** + one question · **Nedarysime** + the reason.
3. An empty or unclear item gets one question or a best guess plus "parašyk dar kartą, jei turėjai omenyje ką kita".
4. "Pažiūrėk atsiliepimus" means: read all new items, fix what needs no decision, list the rest as questions, then re-check older open items.
5. Replies never flood: 2 newest shown, the rest folded, "Supratau visus", outside the red badge.
6. Replies follow the account, not the browser (target, step C9).
7. Karate, competition and exam rules in feedback are the spec. If a rule is unclear, ask Deividas first; do not guess.
8. A choice between designs is shown as 2–5 labelled variants, as large pictures, each with a one-line recommendation. The pick is written into `docs/decisions.md`.

## 7. Text on screen

1. Every visible string is Lithuanian with correct letters (ą č ę ė į š ų ū ž) and goes through `t()` / `te()` with EN and RU keys.
2. Plain words. No developer words, no keyboard hints on touch screens.
3. Buttons are verbs ("Išsaugoti", "Pažymėti: apdovanota"). States are short labels.
4. No person's name in hints ("administratorius", not "Denis").
5. Click paths in hints are built from the real menu, not typed by hand.
6. For kids: short sentences, no percentages on the first screen, no money.
7. A page title equals its menu name.
8. After every action the result is shown inside the visible area. An inline form closes after success.
9. `tests/checks.sh` fails on "karatė" in any visible string, translation file or template.

## 8. Layout, CSS and touch

**Target: steps C4 and C5. The limits below apply to new work now.**

1. Tokens in `assets/tokens.css`, loaded first on all three sites: spacing 4 / 8 / 12 / 16 / 24 / 32 / 48 / 64 px; one radius scale; a type scale; colours by meaning (`--brand`, `--brand-dark`, `--ink`, `--surface`, `--bg`, `--danger`, `--success`, `--warning`). Brand per site: karateka `#2F6B5A`, Budo `#D7263D`, LTKF `#0E2A47`.
2. Four breakpoints only: **480 / 768 / 1100 / 1440**. No new widths.
3. Every screen is checked at **375, 768, 1440**; Valdymas and Budo also at **1280** (exam tablet) and **2560**; TV screens at **1920**.
4. On a phone (375×812) the main action of the step is visible without scrolling. Header plus tabs take at most 160 px (Budo: 120 px).
5. No sideways scroll at any checked width.
6. Touch targets are at least 44 × 44 px up to 1366 px wide. Score buttons at least 48 px. Form fields use 16 px text.
7. Things that open (menus, panels) stay inside the screen, open downward from their button, close after a choice, on a second click, on a click outside and on Esc. Nothing else moves. A full-screen panel gets a Back step: Back closes it first.
8. Floating buttons: one per screen at most. Never on top of a main button or an open sheet. None on Budo working views.
9. An element marked `hidden` is never shown by another rule.
10. No new `!important`. No new "vX.YY" override blocks: change the original rule.
11. Header, side menu and content share one container width per breakpoint.
12. Content is centred and capped on wide screens (Budo: 1680 px).
13. Once a look is agreed, it is not replaced by a simpler one. Supplied or approved pictures are used as they are.
14. **Shared parts (v1.51), use them instead of new ones:**
    - Icons: `icon('download' | 'upload' | 'trash' | 'plus' | 'check' | 'x' | 'copy' | 'search' | 'image' | 'comment' | …)` from `app/icons.php`,
      placed before the button text. No emoji or ⬇ / 🗑 / ✕ characters as button icons.
    - File inputs: only `file_drop($name, $accept, $title, $hint, $multiple)` (Lithuanian text, drag and drop, chosen file names).
      Never the browser's own "Choose File".
    - A button with only an icon: `.icon-btn` (44 × 44, `title` + `icon($name, $label)` for screen readers); red only for delete (`.danger`).
    - Downloads: `.btn.btn-ghost` with `icon('download')`; copy buttons: `data-copy="<id of the text>"`.
    - Text inside a `.btn` goes in one `<span>` (otherwise the flex gap splits "Ištrinti (3)" into pieces).

**Game art (target, step D8)**
- One grid per kind: characters one fixed size, pets 32×32, items 24×24, icons 18×18, scenes 320×180. Whole-number scaling, sharp pixels.
- One outline colour and one shadow colour for all. Colours grouped by part (fur, kimono, belt) so they can be recoloured.
- File names: `<key>.png`; frames `<key>_<idle|attack|block|hit>_<n>.png`.
- Sources are in the repo under `tools/pikseliai/`. One build command per kind. A build never overwrites another kind's output.
- Light panels with gold, indigo frame, violet buttons. No wood, no brown.

## 9. JavaScript

1. No build step. Plain files; modules by `<script type="module">` when a file is split.
2. One shared state object per app; views read it, actions change it, one function saves it.
3. Drawing a screen never changes data.
4. "Išsaugota ✓" is shown only after the server confirmed.
5. Anything the user sees as a fact (a draw, a result) is saved the moment it is created.
6. Every map in saved data is an object when loaded, on both sides (an empty PHP array must not become a list).
7. Browser storage is read inside `try`, with a default.
8. `tests/checks.sh` fails on two top-level functions with the same name in one file.
9. Budo modules (D3, done): `assets/varzybos/` - `rules` (the Budo rules), `engine/draw`, `engine/bracket`, `engine/rr`, `engine/score`, `engine/category`, `store`, `util`, `results`, `schedule`, `views/*`, `bout`, `chrome`, `test`, `main`. Loaded by `asset_module()` (import map with `?v=` per file). A module changes only its own variables - others call its setter (`setDragId`, `resetSwap`…); the engine and `rules` use no browser and are tested in `tests/engine`; a module does not read another module's state while loading - `main.js` starts everything.

## 10. Data

1. Every schema change is a new numbered file in `db/`, safe to run twice, and the newest file name is set in `app/db.php`.
2. A migration never picks people by name or e-mail.
3. A migration that could lose data needs permission first and says so at the top of the file.
4. Nothing is deleted for good from a screen without a way back (undo, restore, soft delete).
5. Lists stored by position (belts, sections) are not reordered without moving what points at them.
6. Backups: the database daily (14 kept) — **today**. Uploaded files are not in that backup; the hosting backup must be on — check before go-live.
7. Personal data shown in public follows the "minor" rule: shortened name; an unknown birth year counts as a minor.

## 11. Tests

1. Every fix comes with a test that fails without it.
2. Each spec creates its own data and does not depend on another spec.
3. A flow test reloads the page after its first action and checks that the data is still there.
4. Every page has width checks at 375 / 768 / 1280 / 1440 / 2560: no sideways scroll, nothing `hidden` visible, no fixed element over the main button, touch targets ≥ 44 px.
5. Every secret link kind: valid, wrong, switched off, replaced, expired.
6. Every e-mail: who gets it, subject, where the link leads (through Mailpit).
7. The Budo engine (draw, bracket, round robin, scores) has small tests that run without a browser.
8. Tests change settings through helpers that restore safe defaults, never by deleting rows.
9. A smoke set under 3 minutes runs on every push; the full suite nightly and before a release.
10. Never run two test runs at once against one database. Do not change code while a run is going.

## 12. Release

**The loop**
1. Work happens in a session on its own branch. `main` moves only when Deividas writes **„į main“**.
2. Bump `app/VERSION`. Add the version to `docs/pakeitimai.md` in plain Lithuanian with click paths.
3. Run `tests/checks.sh` and the tests.
4. Build `svetaine-vX.YY.zip`. Old zips are never deleted or overwritten.
5. Deividas uploads to test.karateka.lt and presses "Atnaujinti duomenų bazę".
6. Answer the feedback items: "Padaryta (vX.YY)" + click path.
7. Update `docs/handoff.md`.

**Closing template (every release message ends with this)**

```
Versija: vX.YY
Paketas: svetaine-vX.YY.zip
Ką įkelti: visą paketo turinį (config.php serveryje lieka)
Duomenų bazė: nauji failai iš eilės: 0NN_….sql, 0NN_….sql  (arba „naujų nėra“)
  Patikrinti: Valdymas → Nustatymai → Atsarginės kopijos ir sistema → „Importuoti db failai“ žalia
Ką patikrinti (3 paspaudimai): …
Kas dar atvira: …
Gyva svetainė (karateka.lt): neliesta
```

**Screenshots as proof**
- Phone 375 and desktop 1440 for every change on screen; before and after when something changed.
- Shown large, in the chat, not as links. Never a link to a nearly empty page.

## 13. Working rules

1. Answers are short and plain, in the language of the question. Yes or no first.
2. One step per session. "(plan first)" means: show the plan and wait.
3. Ask before anything that cannot be undone or changes structure: deleting files or page sections, renaming routes, data changes that can lose data, git tags. Such proposals are marked **(reikia leidimo)**.
4. Never invent a karate, competition, exam or grading rule. Write "unclear" and ask.
5. No secrets, passwords, e-mails or phone numbers in any file. Placeholders only.
6. Limits are said in one line up front ("a cloud session cannot write to your PC") with the nearest thing that works.
7. Commit messages are short and human, with no "Co-Authored-By" or "Generated with" lines.
8. Whole-project reviews with many helpers are rare and planned; they use up the usage limit.

## 14. Decisions to keep

These were agreed. Do not change them without asking. (They move to `docs/decisions.md` in step C9.)

- **Workflow:** GitHub is the source of truth; the local folder and test.karateka.lt stay current. Nothing goes to live karateka.lt without an explicit GO and settled privacy and photo consent.
- **Names:** Budo (addresses and data unchanged); Dojo; XP = aktyvumo taškai (one system since v1.49; replaces "XP only in the game" from #89); "Išbandyti"; "nariai".
- **Accounts:** only Deividas and Denis are real admins; everyone else is a tester. The game is visible only to chosen accounts (today: Denis). The IT account has no chat. Parents register first.
- **Navigation:** admin menu B+C — desktop side menu grouped Kasdien / Klubas / Tvarkyti, at most 2 levels; phone and tablet: bottom tab bar with "⋯ Daugiau". Logo ▾ switches Karateka / Budo / LTKF. One admin switch with "Žiūrėti kaip". Arrows only on things that open; menus open on click, close on a second click or a click outside; nothing shifts. Theme and language per account with an admin default.
- **Budo:** default bracket per category type; Fukugo/KITEI alternation; team kumite carries points; team kata with 2 scores (dropped highs and lows stay visible); sumo first to N; round robin by flag count. Kata final starts with the lowest semi-final score. The scoring system is named after the discipline; medals are a separate setting; a final keeps its system. Kumite +4. AKA fully red, SHIRO fully white. Scores have one decimal and move on by themselves; no average anywhere. Timer: starts at 0:10, Enbu counts up, "Jiyu / Pratęsimas 1:30", a gong, reset after 3 s. Brackets are an admin-only tree; spectators use "Rask save". The schedule is the main page. "Group finished" card, then awards. Secretariat by link with a first name only. Only the main admin removes a tatami. Any number of judges.
- **Exams and tests:** random subset of questions with a pass mark the admin sets. Sign-up asks name, surname, birth date and phone, no account. Register numbers YY.MM.DD-N. The protocol fits the page and prints all columns; no average. Passing gives a notice, fireworks and a certificate.
- **Dojo:** pixel art from the side; the fox is the base; legendary gear glows; nature background with the screens inside the scene; no house editor. Arena: fighters left and right, blocks on the left, attacks on the right, textured icon buttons, live log on the right; moves named in Japanese with a Lithuanian explanation. Light panels with gold, indigo frame, violet buttons. Panda, white tiger and raven removed. Arena design: variant 2, 3 cards + pet (chosen in the November review; the choice was delegated by Deividas). Pets hatch from an egg. Gift box at each level and event; no money.
- **Other:** kindergarten groups 6 € per visit, at most 35 € a month. The homepage is edited by the admin and by listed instructors. The LTKF preview has no link back to karateka.lt. SMS uses "log" by default and is admin-only.
