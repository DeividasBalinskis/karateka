# Work plan (darbo planas)

**5-line summary**
1. Budo can silently lose competition results after a page reload. That is step A1 and comes before everything else.
2. Six blockers (phase A) are about lost data, open doors and your own access. They are small and come first.
3. Phase B is what Denis meets every day: phone screens, quick competition, notifications, exams, family accounts.
4. Phase C gives every back system one standard (permissions, settings, links, design tokens, tests, e-mail).
5. Phase D is the big work (Budo vs Varžybos, splitting the big JS file, multi-tablet saving). Phase E is go-live.

Based on your 12-step plan. Nothing from it was dropped; the table in "Old step → new step" shows where each one went.
Paths are relative to `public_html/` unless they start with `db/`, `tools/`, `tests/` or `docs/`.
Evidence for every claim is in `docs/sistemos-apzvalga.md`.

---

## Start here (direction)

Do these in order. Each one is small.

1. **Get your admin login (5 minutes, no code).** Ask Denis: *Valdymas → Nustatymai → Paskyros ir rolės → „+ Nauja paskyra“*. He types your e-mail, name, a password you agree on, role *Administratorius*, then *Sukurti*. You log in at `test.karateka.lt/prisijungti.php`. If Denis is not around, use the prompt in step A4.
2. **Answer the 8 decisions** at the end of this file ("Decisions for Deividas"). One line is enough: `1a, 2 ok, 3b ...`.
3. **Send Denis his questions.** They are written in Lithuanian in "Klausimai Deniui". Copy, send, paste his answers back.
4. **Run the steps in order: A1, A2, A3 ...** For each step, copy its prompt, fill the blanks (`____`), paste it into a new session.
5. After each step you get a checklist and screenshots at 375 and 1440 px. Nothing is committed until you say so.

**How to run any step (general prompt)**

```
Do step ____ from docs/darbo-planas.md. Do only that step.
Read CLAUDE.md, docs/standartai.md and docs/darbo-planas.md first.
My answers for this step: ____
Do not commit. Finish with: checklist, screenshots at 375 and 1440 px, what is still open.
```

**Which model**
- Default: **Opus 5.5**. Effort **high** for building, **medium** for small fixes.
- **Fable 5.2** only for the few steps marked "Fable": they decide a structure that is expensive to get wrong.
- **Ultracode** is not an effort level. It lets the session start many helper agents at once. Use it only for whole-project reviews. It burns the usage limit fast: this review used up one 5-hour window.

---

## All steps on one screen

Status: ☐ not started · ◐ partly done · ☑ done

| Step | What | Who it helps | Size | Flags | Status |
|---|---|---|---|---|---|
| **A1** | Budo: results must never disappear | Denis, secretariat, everyone at a competition | S | — | ☑ v1.48 + v1.76 (unsaved changes kept on the device and merged after reopening; past lost results: none known on test) |
| **A2** | Lock the doors (5 quick security fixes) | Denis, parents | S | — | ☑ v1.48 |
| **A3** | Exam links: no stranger gets a child's page | kids, parents, examiners | M | plan first | ☑ v1.52 + v1.56 (already-registered without link, no name-only match, no phone overwrite / un-delete, find limited per IP in DB and off after the exam, ↻ new link, feedback scrubs secrets; personal page without phone, birth year only) |
| **A4** | Real admins vs testers; your login | Deividas, Denis, testers | M | reikia leidimo | ☑ v1.49 (your login: still to create) |
| **A5** | Secure the trial form (old step 1) | guests, Denis | S | — | ☑ v1.50 |
| **A6** | One source of truth: main branch, deploy guide, zip script | Deividas | S | reikia leidimo | ☑ 2026-11-21 („į main“: main = v1.72, later v1.84; zip script keeps old zips, never overwrites; deploy guide = real loop; scripts wait for Enter) |
| **B1** | Small feedback batch + text fixes (old step 2) | Denis | S | — | ☑ v1.48–v1.58 (#138-#151; LTKF EN/RU keys, „karatė“ check, no sideways scroll at 375) |
| **B2** | "Greitos varžybos": 4 athletes in under a minute | Denis, coaches | M | plan first | ☑ v1.49 + v1.83 (decision: no separate „quick“ mode - the normal form is quick: names, tick Kata/Kumite → groups made, one tatami, opens the schedule; private, no member event; Enter adds in Dalyviai) |
| **B3** | Budo on a phone on competition day | Denis, secretariat, judges | M | — | ☑ v1.54 (bout fits 375×812, time section folds, floats hidden, header 224→146 px, tablet overflow, confirmations, TV tatami picker, no-birth-year names shortened) |
| **B4** | Phone admin: view-as, one floating button (old step 6) | Denis, coaches, members | M | — | ☑ v1.51–v1.52 (view-as in ⋯ Daugiau, one floating button, floats hide over sheet and while typing, 🎨 for all) |
| **B5** | Notifications: the red number means "to do" | Denis, coaches, parents | M | plan first | ✓ v1.55 + v1.70 (type registry, staff badge = to-dos until done, notices fold; v1.70 member/parent badge = tasks, unread notes/replies, open invitations only) |
| **B6** | Exams: counts, tablet, 10 kyu, test bank export/import | Denis, examiners | M | — | ☑ v1.49 (10 kyu, #133, #137), v1.50 (question review with ✕, comments, pictures, ChatGPT notes), v1.51 (editor redesign, file with a new quiz); v1.59 (list: registered · in protocol + import button; protocol controls ≥ 40 px on a tablet, names not cut); v1.60 (new event type „Kita“, „Bus pakviesta: N“) |
| **B7** | Family accounts: parent trains too, child login in one button | parents, kids | M | plan first | ☑ v1.53 (child login: username + PIN by parents, from 7, no coach approval; parent trains too; adult member adds a child; parent switches child chat) |
| **B8** | Kid and member home; Dojo in the corner; Paskyra page | kids, members | M | plan first | ☑ v1.71 kid home variant B (fits 375x812); v1.72 member tab bar on a phone, 🏯 Dojo corner button; Paskyra page = Mano paskyra → Nustatymai (developer's call 2026-11-21, see decisions) |
| **B9** | Valdymas: one shell, one start page, final names | Denis, coaches | M | reikia leidimo | ☑ v1.74 (one layout, variants removed, Renginiai list in Valdymas, editors with side menu, LTKF row, hints) |
| **C1** | Permissions: one `can()` and one table | everyone | M | plan first, Fable | ☐ |
| **C2** | Settings: one registry, one map, a change log | Denis, Deividas | M | plan first | ☑ v1.78 + v1.81 (language on the account; registry checked by checks.sh; „Visi nustatymai“ map; change log with „Atkurti“; LTKF draft is a setting; old names like `reg_off` kept - renaming needs your OK) |
| **C3** | Secret links: one model, one "Visos nuorodos" page | Denis, secretariat | L | plan first, Fable | ☑ branch `claude/c3-nuorodos-1bb636` (db/062 `access_links`, `app/links.php`, Valdymas → Visos nuorodos, 13 kinds, names in Budo history; not merged yet) |
| **C4** | Design tokens + visual safety net (old step 4) | everyone | M | plan first | ☑ v1.82 (tokens.css, zero visual change proven on 55 shots; tests/visual.sh baseline/compare at 375/768/1440 + 1280/2560) |
| **C5** | CSS cleanup, zero visual change (old step 5) | Deividas | L | plan first | ☐ |
| **C6** | Tests: faster, independent, engine unit tests (old step 10) | Deividas | M | — | ◐ v1.60 (smoke set `tests/smoke.sh` ~2 min; flaky Budo specs pick groups robustly; tests clean their own IP limits) |
| **C7** | One polling request (old step 11) | everyone (speed) | M | — | ☑ v1.57 (pulsas.php: chat count + staff pop-ups + game invites, ETag/304, one poller every 30 s paused when hidden; live/TV pause when hidden; open chat stays fast) |
| **C8** | E-mail: templates, outbox, the reader's language | parents | M | plan first | ☑ v1.75 + v1.78 (coach reply e-mail only when ticked; one template file in the reader's language, one footer; outbox with result per person; chat link fixed; no game mention) |
| **C9** | Project memory: CLAUDE.md, handoff, decisions, "Kas naujo" | Deividas, Denis | S | — | ☑ v1.52–v1.53 (CLAUDE.md, handoff, decisions, „Kas naujo“ from the version number) |
| **C10** | Data care: retention, orphans, backups of uploads | parents, Deividas | M | reikia leidimo | ☑ v1.79 (Duomenų priežiūra page: auto rules + delete list shown first, delete only on button; photo .zip; cookie list; retention 1 y since v1.52, outbox 90 d) |
| **D1** | Compare Budo with the external Varžybos app (old step 3a) | Deividas, Denis | M | plan first | ☐ |
| **D2** | Apply the decision (old step 3b) | Deividas | L | reikia leidimo | ☐ |
| **D3** | Split `assets/varzybos.js` into modules (old step 8) | Deividas | L | plan first | ☐ |
| **D4** | Multi-tablet saving (old step 9) | secretariat, judges | L | plan first, Fable | ◐ v1.52 (conflict = automatic merge by group, idle tablets pull every 8 s; same group on two tablets: last one wins) |
| **D5** | Polish one page (old step 7, repeat) | everyone | S each | — | ◐ v1.61 index, v1.62 renginiai/renginys, v1.63 prisijungti/registracija, v1.64 paskyra + all small buttons on touch, v1.65 naujienos + treniruotė, v1.66 egzaminas/mano-egzaminas, v1.67 admin index/nariai/taškai, v1.68 LTKF; next: Budo live (needs a competition with live data) |
| **D6** | LTKF: real content model, own domain | federation | L | plan first | ◐ v1.77 registers; v1.80 parts 1-3 (own key per repeated text, versions + restore, news/documents as records, off/draft/public with noindex); part 4 domain move waits for the hosting setup |
| **D7** | Dojo: one fight model, ranking, fair rewards | kids (later), Denis | L | plan first | ◐ v1.49 (calm look, #128 ranking); v1.50 hidden from menus until opened (#140) |
| **D8** | New pixel characters | kids (later) | L | plan first | ☐ |
| **E1** | Go live (old step 12) | everyone | M | waits for your GO | ◐ checklist ready: `docs/paleidimo-patikra.md` (old URLs: karateka.lt blocked in cloud) |

**Old step → new step:** 1 → A5 · 2 → B1 · 3a → D1 · 3b → D2 · 4 → C4 · 5 → C5 · 6 → B3 + B4 · 7 → D5 · 8 → D3 · 9 → D4 · 10 → C6 · 11 → C7 · 12 → E1.

---

# Phase A — Blockers

## A1 — Budo: results must never disappear

- **Why now:** A result can be shown as saved ("Išsaugota ✓") and be gone after a reload. On competition day that breaks trust in the whole system.
- **What is wrong:** PHP turns an empty `{}` into `[]` when it saves the state. After a reload the browser writes results into that array, and they are dropped on the next save. Also: a draw shown on screen is not saved until something else is saved, so a reload or a second tablet shows different pairs.
- **Who it helps:** Denis, secretariat, judges, awards person, spectators.
- **Likely files:** `assets/varzybos.js` (load, renderDraw, flush), `app/tournaments.php` (save), `admin/varzybu-sistema.php`, new test in `tests/e2e/`.
- **Done in v1.48:**
  - [x] Create draw → reload → enter result → reload: the result is still there (kata flags and the kumite panel are tested; the fix covers every result map).
  - [x] Reload and a second browser show the same pairs for a draw nobody has saved yet. (Done by making that first draw repeatable instead of saving on view: saving on view would make other tablets lose their next action until step D4.)
  - [x] The server stores empty maps correctly, so old and new competitions both work.
- **Still open:**
  - [ ] Closing the tab while a save is running can still lose the last change. This needs the new save model, so it moved to step D4.
  - [ ] Results that were lost before v1.48 do not come back by themselves. Ask Denis which past competitions matter and re-check them by hand. A lost kumite bout can sometimes be seen as "points entered, no winner".
- **Tests:** `tests/e2e/06z-budo-rezultatai-islieka.spec.js` (fails on 7 checks without the fix).
- **Screenshots:** run view before and after reload, 375 and 1440.
- **Depends on:** nothing. **Model:** Opus 5.5, high.

```
Finish step A1 from docs/darbo-planas.md: only the part "Still open".
Competitions I care most about (to check for lost results): ____ (or "all on test")
Do not commit. Finish with the checklist.
```

## A2 — Lock the doors (5 quick security fixes)

- **Why now:** Each one is a few lines, and each one lets the wrong person do something real.
- **What is wrong:**
  1. A person with a secretariat link can open `admin/varzybos.php?id=…` and act on **all** competitions.
  2. Any coach can tick "Ir SMS tėvams" on the participants page; SMS was decided as admin-only.
  3. The game is shown to **everyone** if its setting row is missing (it should be shown to nobody).
  4. A role changes the moment you touch the select, with no question; a new account is "Administratorius" by default.
  5. LTKF editors (people with a link, no account) can upload `.svg` files to the karateka.lt address; `ltkf/kalba.php` can redirect to another site.
- **Who it helps:** Denis, parents, the federation.
- **Likely files:** `admin/varzybos.php`, `app/sms.php`, `admin/renginio-dalyviai.php`, `app/game.php`, `tests/setup/reset-db.sh`, `admin/paskyros.php`, `ltkf/redaguoti.php`, `ltkf/kalba.php`, `uploads/ltkf/deze/.htaccess`.
- **Done in v1.48:**
  - [x] Secretariat session on `admin/varzybos.php` (with or without `?id=`) is sent back to its own competition.
  - [x] A coach cannot send SMS from any page; the check lives inside `sms_send_message()`; the checkbox is hidden for coaches.
  - [x] No `game_access` row → nobody sees Dojo. Tests set the access on purpose.
  - [x] Role change asks "Pakeisti rolę: Vardas → …?" first. New account default role is "Narys / tėvai".
  - [x] `.svg` is refused; the file box folder is closed to the web; the redirect accepts only local paths.
- **Left for later steps:** what else the secretariat may see (history restore, tatami settings) waits for Denis's answer 4 and is part of C3.
- **Tests:** extend `06q` (secretariat), add coach-SMS, game-default, role-confirm and upload-type checks.
- **Screenshots:** role change confirm, 375 and 1440.
- **Depends on:** nothing. **Model:** Opus 5.5, medium.

*(No prompt needed: done.)*

## A3 — Exam links: no stranger gets a child's page (plan first)

- **Why now:** The exam sign-up link is sent to whole groups. Today anyone holding it can type a child's name and birth date and receive that child's personal page with birth date and phone. It can also overwrite the stored phone.
- **Who it helps:** kids, parents, examiners.
- **Likely files:** `app/exam_signup.php`, `egzamino-registracija.php`, `mano-egzaminas.php`, `admin/egzamino-protokolas.php`, `testas-svecias.php`, `app/feedback.php`.
- **Done when:**
  - [ ] Signing up as an existing person shows "Jūs jau užregistruotas (-a)" and tells the coach. It never shows the other person's link, never overwrites the phone, never un-deletes a row.
  - [ ] No match on name alone (a row without a birth date does not match).
  - [ ] "Jau užsiregistravai?" is limited per IP in the database and switches off after the exam.
  - [ ] The personal page shows a masked phone and the birth year only. Each protocol row has "↻ Nauja nuoroda".
  - [ ] Feedback no longer stores secret parts of a page address (`k`, `t`, `e`, `key`).
- **Tests:** collision test, brute-force limit test, masked data test.
- **Screenshots:** sign-up, "already registered", personal page at 375 and 1440.
- **Depends on:** Denis's answers 20–22. **Model:** Opus 5.5, high.

```
Do step A3 from docs/darbo-planas.md. Show me the plan first and wait for my OK.
Denis's answers: phone on the exam page: ____ ; how long the page stays open after the exam: ____ ;
retries of the theory test: ____
Feedback #85 contains a live exam link. May it be blanked out? ____ (yes/no)
```

## A4 — Real admins vs testers; your login (reikia leidimo)

- **Why now:** Every tester is a full admin today. Any of them can remove Denis's admin role, open the game to everyone, restore a backup or create new admins. You still have no login.
- **What to build:** a flag `accounts.is_tester` (new migration `db/044_…`, adds a column, loses nothing). Real admin = admin and not tester. Testers get a "Testuotojas" badge, keep testing rights, never see Dojo. Only real admins change roles, flags, game access, backups, site-wide switches and SMS.
- **Who it helps:** Deividas, Denis, testers.
- **Likely files:** `db/044_testuotojai.sql`, `app/auth.php`, `app/game.php`, `admin/paskyros.php`, `admin/nustatymai.php`, `admin/kopijos.php`, `app/layout.php`.
- **Done in v1.49:**
  - [x] `db/044` adds the flag and, the first time only, marks every admin as a tester except the accounts in the game list (Denis) and the account without chat (#21).
  - [x] Paskyros shows a "🧪 Testuotojas" switch per coach/admin account; only a real admin can change it.
  - [x] A tester has the badge, coach rights, read-only Nustatymai, no Dojo; roles, backups, feedback inbox, broadcasts and site-wide switches are closed.
  - [x] Real admins always see the game. The last real admin cannot be demoted, disabled or made a tester.
  - [ ] Your account exists and is a real admin. **Still to do on the server:** Denis creates it (steps in "Start here").
- **Tests:** tester cannot change a role, open the game or restore a backup; view-as still works.
- **Screenshots:** Paskyros with the badge, tester's menu, 375 and 1440.
- **Depends on:** your decisions 1 and 8. **Model:** Opus 5.5, high.

```
Do step A4 from docs/darbo-planas.md. Do only that step.
Real admins (e-mails as they are in Paskyros): ____ and ____
What testers may still do: ____ (default: everything a coach can do, plus read-only settings)
Does Deividas see the Dojo game? ____ (default: yes)
I do not have a login yet: ____ (yes/no). If yes, give me the exact clicks for Denis and a phpMyAdmin
fallback with blanks for my e-mail. Never write a password into any file.
```

## A5 — Secure the trial form (old step 1)

Your text stands as written:

> `uzklausa.php` (called from `assets/home.js`) is a standalone script: no bootstrap, CSRF, rate limit or honeypot; hard-coded recipient; its own SMTP client and `smtp` config; it shows which SMTP step failed. Fix: load `app/bootstrap.php`, send through `app/mail.php`, recipient from config `notify_email`, CSRF + honeypot + minimum fill time (~3 s) + rate limit (max 3 per hour per IP and per e-mail), server-side validation with Lithuanian messages, the visitor sees only success or a generic error (details go to the log), keep the JSON shape `home.js` expects, remove `smtp` from `config.example.php` and tell me what to change in the server `config.php`.

- **Added by the review:** `app/mail.php` sends the password without encryption if `secure` is not `ssl`. Refuse to log in over a plain connection.
- **Done when:** a valid submit reaches Mailpit; honeypot, too-fast and 4th-in-an-hour submits are rejected; `smtp` is gone from the example config.
- **Status (v1.50): done.** `uzklausa.php` goes through bootstrap and `send_mail()` (Reply-To = visitor), recipient `notify_email`, CSRF + honeypot `website` +
  signed open time (≥ 3 s) + 3 per hour per IP or e-mail (`form_submissions`, db/045, cleaned after 7 days), Lithuanian messages, details only in the log.
  `send_mail()`: `secure = tls` does STARTTLS; a password is never sent unencrypted except to localhost (Mailpit). Test: `tests/e2e/07f-v150.spec.js`.
- **Screenshots:** empty, error and success states, 375 and 1440.
- **Depends on:** nothing. **Model:** Opus 5.5, medium.

```
Do step A5 from docs/darbo-planas.md. Do only that step.
Do not commit. Finish with the checklist, screenshots and the exact lines I must change in the server config.php.
```

## A6 — One source of truth: main branch, deploy guide, zip script (reikia leidimo)

- **Why now:** GitHub `main` is at v1.18 and 92 commits behind the working branch (v1.47). Your PC script `tools/atnaujinti-is-github.ps1` takes only `main`, so it would install a very old site. The deploy guide still says "main ← dev". `tools/paruosti-ikelimui.ps1` deletes old zips, against your rule to keep them.
- **Who it helps:** Deividas.
- **Likely files:** `tools/paruosti-ikelimui.ps1`, `tools/atnaujinti-is-github.ps1`, `docs/ikelti-i-test.md`, `README.md`.
- **Done when:**
  - [ ] `main` holds the current version (only after you write **„į main“**).
  - [ ] The zip script keeps old zips and never overwrites an existing one.
  - [ ] The deploy guide describes the real loop (see `docs/standartai.md`, "Release").
  - [ ] Scripts you start by double-click stay open and say what happened.
- **Depends on:** your decision 4. **Model:** Opus 5.5, medium.

```
Do step A6 from docs/darbo-planas.md. Do only that step.
į main: ____ (write "į main" to allow the merge, or "not yet")
```

---

# Phase B — Denis's daily pain

## B1 — Small feedback batch + text fixes (old step 2)

- **Why now:** Twelve small things Denis reported or would notice in a minute.
- **What:**
  - #127 theme picker closes after a choice. "Ir numatytoji visiems" works when ticked after choosing.
  - #129 Valdymas: the left menu and the first card start on one line (show two variants if unsure).
  - #130 no text cursor over labels in "Tatamiai ir kovų trukmė".
  - #131 "Apie klubą" opens downward under its button, like the logo menu. It no longer covers the logo switch.
  - #133 exam protocol: a score of 6.3 or lower is red in its cell; a failed row is fully red.
  - #134 stopwatch (Enbu ↑) starts at 0:00, also on the TV screen.
  - #135 open-ended age boxes: different signs for youngest and oldest (needs answer 10).
  - Found in the review: the quick-points form is always visible at the bottom of Treniruotė; the "galima kelis" hint sits in a black circle on Pranešimai; Aktyvumo taškai scrolls sideways on a phone; "+5 XP" in the training line and the "+N XP" notification should say "Aktyvumo taškai".
  - Old step 2 as written: "karate" never "karatė" (footer in `app/tournaments.php` and its EN/RU keys; LTKF translation keys that broke EN/RU); "karate-do"; a proper no-access message in `zaidimas.php`; "Taika" leftovers → "Budo"; a `tests/checks.sh` rule that fails on "karatė".
- **Who it helps:** Denis, coaches, examiners.
- **Likely files:** `assets/theme-pick.js`, `assets/header.css`, `admin/index.php`, `assets/varzybos.js`, `assets/varzybos-gyvai.js`, `assets/varzybos.css`, `admin/egzamino-protokolas.php`, `assets/app.css`, `admin/pranesimai.php`, `admin/taskai.php`, `app/xp.php`, `app/points.php`, `app/tournaments.php`, `tools/vertimai/ltkf.tsv`, `tests/checks.sh`.
- **Done in v1.48:** #127 (picker closes; the "for all" tick works after choosing), #134 (stopwatch from 0:00, also on TV), the stray points form, the black-circle hint, the footer "karate", the no-access message of the game API.
- **Done in v1.49:** #126 (who is next in the bout panel), #129 (side menu starts with the first card), #130 (no text cursor; the panel stays on screen), #131 (fold opens downward), #133 (≤ 6.3 red), #135 (youngest "−", oldest "+": common age notation; flip it if Denis meant the other way).
- **Still open:** the sideways scroll on Aktyvumo taškai, "karate-do" and "Taika" leftovers in code, the LTKF EN/RU translation keys, the `checks.sh` rule for "karatė".
- **Done when:** each open line has a test or a screenshot; feedback items get the reply "Padaryta (vX.YY)" with the click path.
- **Depends on:** answer 10 for #135 (the rest does not wait). **Model:** Opus 5.5, medium.

```
Finish step B1 from docs/darbo-planas.md: only the part "Still open".
#135 signs: youngest box shows ____ , oldest box shows ____ (Denis's answer; if blank, skip #135)
#129: I prefer ____ (a: heading across the full width / b: menu starts level with the first card / show both)
Do not commit. Finish with the checklist and screenshots.
```

## B2 — "Greitos varžybos": 4 athletes in under a minute (plan first)

- **Why now:** Denis tried to run a 4-athlete competition during training and could not find his way. Today it is about 23 clicks on a PC and 32 taps on a phone, with several traps.
- **What to build:** one card "⚡ Greitos varžybos" on the Budo home: type names (one per line) → tick Kata / Kumite → "Pradėti". It creates the competition, one group per discipline, one tatami, saves the draw, hides it from the public, makes no event, and opens the group ready to score.
- **Who it helps:** Denis, coaches.
- **Likely files:** `admin/varzybos.php` (new action), `app/tournaments.php`, `assets/varzybos.js`, `assets/varzybos.css`.
- **Done when:**
  - [ ] From the Budo home to the first scored bout: at most 10 taps after typing the names, on a 375 px phone.
  - [ ] Enter adds a name in the normal participants list too.
  - [ ] People without a birth year are never dropped silently.
  - [ ] The quick competition is private by default and easy to delete.
- **Tests:** phone walk-through spec; reload keeps everything.
- **Screenshots:** the card, the name step, the running group, 375 and 1440.
- **Depends on:** A1; Denis's answers 1–3 (defaults are rules, do not guess them). **Model:** Opus 5.5, high.

```
Do step B2 from docs/darbo-planas.md. Show me the plan first and wait for my OK.
Denis's defaults for a quick competition:
- kata is judged by ____ (flags / points) with ____ judges
- medals: ____ (two bronzes without a bout / bronze bout / other)
- kumite system: ____
```

## B3 — Budo on a phone on competition day

- **Why now:** Phone and tablet are where Budo is used. The top pain point in all feedback is content off-screen and jumps.
- **What:**
  - #126 "Ruošiasi: A – B" inside the bout panel and at the top of the simple view.
  - "⚙ Tatamiai ir kovų trukmė" opens inside the screen (today "+ Tatamis" cannot be reached at 375, 768 and 1440).
  - Run view on a phone starts with the pair; the 7–8 tools go under "⋯ Daugiau".
  - Back closes the bout panel first.
  - "Sukurta" and other confirmations are shown on the competition page (today they are swallowed).
  - The 💡 button does not cover "+2"; keyboard hints are hidden on touch; taps are at least 44 px.
  - The TV address without a tatami number shows big tatami buttons.
  - Athletes typed by hand without a birth year are shown shortened in public, like minors.
  - Old step 6 for Budo: header + tab rows on a phone ≤ 120 px, one scrollable tab row, score buttons ≥ 48 px.
- **Who it helps:** Denis, secretariat, judges, spectators.
- **Likely files:** `assets/varzybos.js`, `assets/varzybos.css`, `admin/varzybu-sistema.php`, `assets/varzybos-gyvai.js`, `assets/widgets.css`.
- **Done when:** on 375×812 the pair and the score buttons are visible without scrolling; no control opens off-screen; all Budo specs pass.
- **Screenshots:** before/after of run view, bout panel, settings popover at 375, 768, 1440.
- **Depends on:** A1. **Model:** Opus 5.5, high.

```
Do step B3 from docs/darbo-planas.md. Do only that step.
Do not commit. Finish with the checklist and before/after screenshots at 375, 768 and 1440.
```

## B4 — Phone admin: view-as and one floating button (old step 6)

- **Why now:** On a phone or tablet (up to 1100 px) there is no way to reach "Žiūrėti kaip" or "Kaip narys". The 💡, chat and bell buttons sit on top of each other, on the menu sheet and on main buttons such as "Patvirtinti".
- **What:**
  - "Žiūrėti kaip" (Adminas / Tėvai / Vaikas / Kaip narys) is the first row of the "⋯ Daugiau" sheet. Same component as on desktop.
  - Old step 6 as written: 💡 and chat become one button with a mini menu, hidden while an input is focused, with bottom padding and safe-area insets.
  - The bell pop-up never covers a button; nothing floats above an open sheet.
  - The 🎨 button works for every logged-in user (today its script loads only for admins).
- **Who it helps:** Denis, coaches, members.
- **Likely files:** `app/layout.php`, `assets/header.css`, `assets/header.js`, `assets/widgets.css`, `assets/chat.js`, `assets/feedback.js`, `assets/notify-popup.js`.
- **Done when:** a test at 375 and 768 checks every admin page: no fixed element covers a primary button; view-as is reachable in 2 taps.
- **Screenshots:** Daugiau sheet, Patvirtinimai, Treniruotė at 375 and 768; before/after.
- **Depends on:** nothing. **Model:** Opus 5.5, high.

```
Do step B4 from docs/darbo-planas.md. Do only that step.
Do not commit. Finish with the checklist and before/after screenshots at 375 and 768.
```

## B5 — Notifications: the red number means "to do" (plan first)

- **Why now:** Denis asked for less noise (#47, #59). That was done only for feedback replies. The red number still mixes things to do with news, and counts rows instead of items.
- **What:** one list of notification types in code. Each type is **action** (counts until it is done), **notice** (never counts; 2 newest shown, the rest folded, "Supratau visus") or **celebration**. One function gives every badge its number. Feedback replies no longer push real requests off the list. "Supratau" in the pop-up does not hide an unanswered request.
- **Who it helps:** Denis, coaches, parents.
- **Likely files:** `app/notifications.php`, `app/data.php`, `app/layout.php`, `admin/index.php`, `pranesimas.php`, `assets/notify-popup.js`, `paskyra.php`.
- **Done when:** the standard in `docs/standartai.md` ("Notifications") holds; a test with 35 feedback replies + 1 request still shows the request; the header number equals the sum of what is listed.
- **Screenshots:** Pradžia and the member "Svarbu" list, 375 and 1440.
- **Depends on:** Denis's answers 13–14. **Model:** Opus 5.5, high.

```
Do step B5 from docs/darbo-planas.md. Show me the plan first and wait for my OK.
Denis's answers: the red number goes up for ____ ; coaches also get ____ ;
when one coach handles an item it disappears for ____ (everyone / only that coach)
```

## B6 — Exams: counts, tablet, 10 kyu, test bank export/import

- **What:**
  - #136 add the grades Denis confirms. **Careful:** exam prices are stored by position in the belt list; adding a grade shifts every price unless the same migration moves them.
  - The Egzaminai list shows both numbers: "užsiregistravo 8 · protokole 0", with the import button right there.
  - Protocol on a tablet: Išlaikė / Neišlaikė, €, ✕ and ± at least 40 px; names not cut.
  - #137 export the whole test bank with answers to a file, edit it, import it back (update, add, mark removed; show a preview before saving).
  - New event defaults: type "Kita" (not "Egzaminas"); show "Bus pakviesta: N" before saving.
- **Who it helps:** Denis, examiners.
- **Likely files:** `app/belts.php`, `db/04x_…sql`, `admin/egzaminai.php`, `admin/egzamino-protokolas.php`, `app/exam_protocol.php`, `admin/testai.php`, `app/quiz*.php`, `admin/renginiai.php`.
- **Done when:** prices stay on the right belts after the migration (test); export → import of an unchanged file changes nothing; tablet screenshot shows 40 px controls.
- **Screenshots:** protocol at 1280×800 and 1440; Egzaminai list at 375.
- **Depends on:** Denis's answers 17–19 and 23. **Model:** Opus 5.5, high.

```
Do step B6 from docs/darbo-planas.md. Do only that step.
Denis's answers: grades to add: ____ ; which grades have a fee: ____ ;
does the red rule (6.3 and lower) apply to the "Testas" column: ____
```

## B7 — Family accounts (plan first)

- **Why now:** Your rule: parents register first, add the child, then create the child's login with one button. Today a parent cannot register as a trainee, an adult who registered for themselves cannot add a child, and a child login needs its own e-mail plus a second approval by the coach.
- **What:** every account can add a child and can add itself as a trainee. "Sukurti vaiko prisijungimą" makes a login in one step from the parent's account; an approved child is not approved a second time. The join link tells the coach when someone links as a parent.
- **Who it helps:** parents, kids.
- **Likely files:** `paskyra.php`, `registracija.php`, `pakvietimas.php`, `jungtis.php`, `app/auth.php`, `admin/patvirtinimai.php`.
- **Done when:** the 3 journeys work on a 375 px phone and are covered by tests: (1) parent registers, adds 2 kids; (2) parent adds themselves as a trainee; (3) parent creates the child's login in one button and the child logs in.
- **Screenshots:** each journey step at 375.
- **Depends on:** A4; Denis's answers 24–26. **Model:** Opus 5.5, high.

```
Do step B7 from docs/darbo-planas.md. Show me the plan first and wait for my OK.
Denis's answers: a child may have their own login from age ____ ;
a child without an e-mail logs in with ____ ; coach approval for a child login made by a parent: ____
```

## B8 — Kid and member home; Dojo in the corner; Paskyra page (plan first)

- **Why now:** A child lands on the same long adult page as a parent (6,477 px tall on a phone), with "Atsijungti" next to the greeting and a tab row that is cut off. You asked for a simple kid view, Dojo as a special item in the corner, and maybe a separate Paskyra page.
- **What (proposal, see the map in `docs/sistemos-apzvalga.md`):**
  - Top-right corner by role: guest = "Išbandyti" + "Prisijungti"; member, parent, kid = Paskyra (+ one badge) and **🏯 Dojo** in the far corner; staff = Paskyra + "Valdymas ▾" (Dojo sits inside Valdymas).
  - Kid home: "Šiandien" with at most 3 big cards (next training or event, one task, Dojo). Everything else one tap away. No prices, no "Atsijungti" on the first screen.
  - Paskyra becomes its own page: profile, children, settings, logout.
  - Members get the same bottom tab bar idea as admins on a phone (4 tabs + Daugiau).
- **Who it helps:** kids, members, parents.
- **Likely files:** `paskyra.php`, `app/layout.php`, `assets/header.css`, `assets/app.css`, `uzduotys.php`.
- **Done when:** a child's first screen fits on 375×812 without scrolling; the corner matches the table; Dojo shows only for accounts that may see the game.
- **Screenshots:** kid, parent and member home at 375 and 1440, before/after. Show 2 variants of the kid home, large, before building.
- **Depends on:** your decision 6; Denis's answer 27. **Model:** Opus 5.5, high.

```
Do step B8 from docs/darbo-planas.md. Show me 2 variants as large screenshots first and wait for my OK.
Bottom tab bar for members on a phone: ____ (yes / no)
Separate Paskyra page: ____ (yes / no)
Words Denis wants for young kids instead of "Apžvalga", "Aktyvumo taškai": ____
```

## B9 — Valdymas: one shell, one start page, final names (reikia leidimo)

- **What:**
  - Events, news and lesson editors open inside Valdymas with the side menu (today they jump to the public layout).
  - After login, staff land on the Valdymas start page (today Denis lands on his member page).
  - One name for the start page everywhere (#114). Today it is "Pradžia" in the menu, "Šiandien" in the title, "Kasdien" as the group.
  - The menu test variants are removed: desktop B+C and the phone bottom bar are the only layout. *(reikia leidimo: removes settings and code paths)*
  - Stale hints such as "Treneriams → Paskyros" are rebuilt from the real menu.
  - "LTKF svetainė" gets a row under Tvarkyti (today the page is reachable only from the LTKF top bar).
- **Who it helps:** Denis, coaches.
- **Likely files:** `app/layout.php`, `admin/renginiai.php`, `admin/naujienos.php`, `admin/pamokos*.php`, `app/helpers.php`, `paskyra.php`, `admin/nustatymai.php`.
- **Done when:** every admin page has the side menu at 1440 and the tab bar at 375; one name in menu, title and EN/RU; a test walks every menu item.
- **Depends on:** your decision 2. **Model:** Opus 5.5, high.

```
Do step B9 from docs/darbo-planas.md. Do only that step.
Names: "Valdymas" stays as ____ ; the start page is called ____
Remove the menu test variants: ____ (yes / not yet)
Add Renginiai to the Valdymas menu: ____ (yes / no)
```

---

# Phase C — Foundations (one system each)

The rules these steps build are written in `docs/standartai.md`. Each step makes the code follow one section of it.

## C1 — Permissions: one `can()` and one table (plan first, Fable)

- **What:** `can('action', $account)` in `app/auth.php`; pages call `require_can()`. The table (action × role) lives in `docs/standartai.md` and is the spec. No permission by a person's name (today the homepage editor matches people by name). Role, status, tester and game-access changes are logged. Every POST checks CSRF. Password reset signs out other devices. Tests for the whole account life cycle.
- **Done when:** a grep finds no raw role checks outside `app/auth.php`; the log shows who changed what; the life-cycle spec passes.
- **Depends on:** A4. **Model:** Fable 5.2 for the plan, Opus 5.5 high to build.

```
Do step C1 from docs/darbo-planas.md. Show me the plan first and wait for my OK.
Changes to the permission table in docs/standartai.md that I want: ____ (or "none")
```

## C2 — Settings: one registry, one map, a change log (plan first)

- **What:** `app/settings.php` lists every setting (type, safe default, who may change it, where it is edited). Nustatymai gets "Visi nustatymai": every switch with its current value and a link. A log shows the last changes with "Atkurti". Site-wide switches ask before saving. Visibility switches default to closed. `env` missing means live, on all three sites.
- **Done when:** `tests/checks.sh` fails on a setting name that is not in the registry; the map page lists everything in `docs/standartai.md` ("Settings").
- **Depends on:** A4. **Model:** Opus 5.5, high.

```
Do step C2 from docs/darbo-planas.md. Show me the plan first and wait for my OK.
Denis's answers: Budo theme is ____ (one for all / per tablet); language saved on the account: ____ (yes / no)
```

## C3 — Secret links: one model, one page (plan first, Fable)

- **What:** today there are 22 kinds of secret links and keys built in about six different ways; most never expire and cannot be replaced. One table `access_links`, one file `app/links.php`. Every link has three buttons: **Kopijuoti · ↻ Nauja nuoroda · Išjungti**, and shows when it was last used. One admin page "Visos nuorodos". Links that can write ask for a name, and the name is saved with each change ("who entered this score"). Old links keep working during the move.
- **Done when:** `links.spec.js` checks every kind (valid, wrong, switched off, replaced, expired); the Budo history shows a name next to each saved version.
- **Depends on:** A2, A3; Denis's answers 4–7. **Model:** Fable 5.2 for the plan, Opus 5.5 high to build. *(reikia leidimo: new table and moving stored links)*

```
Do step C3 from docs/darbo-planas.md. Show me the plan first and wait for my OK.
Denis's answers: a secretariat link works for ____ after the competition; one link per ____ (competition / tatami);
the awards tablet shows ____ (full names / "Vardas P.")
```

## C4 — Design tokens + visual safety net (old step 4, plan first)

Your text stands as written (`assets/tokens.css` with spacing, radius, breakpoints 480/768/1100/1440, type scale, colours per site, the "Valdymas" gradient; `tests/visual/` with ~15 pages at 375/768/1440; zero visual change).

- **Added by the review:** today the CSS uses 81 different pixel widths in media queries and 384 different colour values in the four main files. Add 1280 and 2560 to the visual pages for Valdymas and Budo (exam tablet and ultrawide are where things broke). LTKF and the game read the same base scale.
- **Depends on:** nothing. **Model:** Opus 5.5, high.

```
Do step C4 from docs/darbo-planas.md. Show me the plan first and wait for my OK.
```

## C5 — CSS cleanup, zero visual change (old step 5, plan first)

Your text stands as written (order `header.css → app.css → varzybos.css → ltkf.css → widgets.css`; fold the "vX.YY" blocks; tokens; 4 breakpoints; remove `!important`; delete unused selectors; run `tests/visual` after each file; report before → after counts).

- **Added by the review:** add one global rule so an element with `hidden` is never shown by a later `display:` rule (that is how the stray points form appeared). Game CSS stays for D7.
- **Depends on:** C4. **Model:** Opus 5.5, high. One file per session.

```
Do step C5 from docs/darbo-planas.md on the file ____ . Show me the plan first and wait for my OK.
```

## C6 — Tests: faster, independent, engine unit tests (old step 10)

Your text stands as written (each spec seeds its own data; a global fixture closes celebration windows; parallel workers with their own DB; a smoke set under 3 minutes on every push, the full suite nightly; report time before/after).

- **Added by the review:**
  - Small Node tests for the Budo engine (draw, bracket, round robin, scores) with no browser.
  - A rule in every Budo spec: reload after the first action of a flow.
  - Page checks at 375 / 768 / 1280 / 1440 / 2560: no sideways scroll, nothing with `hidden` visible, no fixed element over the main button, taps ≥ 44 px on touch widths.
  - E-mail tests through Mailpit (who gets it, subject, where the link leads).
  - The game access test that cannot fail today is fixed.
- **Depends on:** nothing. **Model:** Opus 5.5, high.

```
Do step C6 from docs/darbo-planas.md. Do only that step.
Do not commit. Finish with the time before/after.
```

## C7 — One polling request (old step 11)

Your text stands as written (one endpoint for chat count + notifications + game invites, ETag/304, one shared poller every 30 s, paused in a hidden tab; Budo live pages keep fast polling).

- **Added by the review:** spectator and TV pages also pause in a hidden tab; `admin/zinutes.php` stops its own 4-second polling.
- **Depends on:** B5. **Model:** Opus 5.5, medium.

```
Do step C7 from docs/darbo-planas.md. Do only that step.
Do not commit. Finish with requests per minute per tab, before/after.
```

## C8 — E-mail: templates, outbox, the reader's language (plan first)

- **What:** about 21 e-mails are written inline, mostly in Lithuanian only, and sent one connection per person inside the page request. One file of templates; language saved on the account and used for e-mails and notifications; bulk mail goes through an outbox in batches with a result per person; one footer; no broken links (`#zinutes` does not exist); no mention of the game while it is hidden.
- **Depends on:** A5, C2. **Model:** Opus 5.5, high. *(reikia leidimo: new outbox table)*

```
Do step C8 from docs/darbo-planas.md. Show me the plan first and wait for my OK.
Denis's answers: e-mail on every coach reply: ____ (always / at most once per 20 min / never)
```

## C9 — Project memory: CLAUDE.md, handoff, decisions, "Kas naujo"

- **What:** put the proposed rules file into the repo root as `CLAUDE.md` (after your edits; done in v1.52). Create `docs/handoff.md` and `docs/decisions.md` from the outlines in it. Add a staff-only "Kas naujo" page opened from the version number (3 newest versions, one line each with the click path). Feedback replies follow the account, not the browser. Fix the outdated `release-check` skill text ("Varžybos app", DirectAdmin).
- **Depends on:** nothing. **Model:** Opus 5.5, medium.

```
Do step C9 from docs/darbo-planas.md. Do only that step.
My edits to the proposed rules file: ____ (or "use it as it is")
```

## C10 — Data care (reikia leidimo)

- **What:** how long to keep notifications, messages, the SMS phone log and old link sessions (nothing is ever deleted today); remove dead rows and dead code paths (anonymous comments that nothing writes, unused notification types) — **only with your OK**; backups today cover the database only, not uploaded photos; a full list of cookies on the privacy page.
- **Depends on:** Denis's answer 15. **Model:** Opus 5.5, medium.

```
Do step C10 from docs/darbo-planas.md. Show me the list of what would be deleted first and wait for my OK.
Keep periods: notifications ____ , messages ____ , SMS log ____
```

---

# Phase D — Larger work

## D1 — Compare Budo with the external Varžybos app (old step 3a, plan first, no code changes)

Your text stands as written (write `docs/varzybos-palyginimas.md`: feature table, what Varžybos has that Budo lacks with effort S/M/L, how LTKF could read Budo, a recommendation and a removal plan; say so if `../Varzybos` cannot be seen).

- **Added by the review:** the bridge is about 12 files. One registration switch ("Varžybos (Budo)") controls both systems today. LTKF reads Budo straight from the shared database, which stops working the day LTKF moves to its own hosting.

```
Do step D1 from docs/darbo-planas.md. No code changes.
```

## D2 — Apply the decision (old step 3b, reikia leidimo)

Your text stands as written. Waits for your decision 3.

```
Do step D2 from docs/darbo-planas.md.
The system that stays: ____ . Port these features: ____ . Git tag before removal: ____ (yes / no)
```

## D3 — Split `assets/varzybos.js` into ES modules (old step 8, plan first)

Your text stands as written. The review already mapped the file (3,200 lines, 237 functions) and proposed the module list; see `docs/standartai.md` ("JavaScript").

- **Depends on:** A1, C6 (engine tests first, then move code).

```
Do step D3 from docs/darbo-planas.md. Show me the module list first and wait for my OK.
```

## D4 — Multi-tablet saving (old step 9, plan first, Fable)

Your text stands as written (4 tatami + secretariat at once, no forced reloads, no lost scores; 2–3 options; offline queue with "Neišsaugota (n)"; test with 5 browsers for 2 minutes).

- **Added by the review:** today nothing fetches other tablets' changes. After one tablet saves, the next save from any other tablet is refused and its last action is lost. Each point saves the whole competition (66 KB with 54 athletes, 292 KB with 450). The save on closing the tab fails above 64 KB.
- **Depends on:** A1, D3. **Model:** Fable 5.2 for the plan, Opus 5.5 high to build.

```
Do step D4 from docs/darbo-planas.md. Show me 2-3 options first and wait for my OK.
```

## D5 — Polish one page (old step 7, repeat)

Your text stands as written (use the `ui-polish-review` skill; order: index → renginiai/renginys → registracija/prisijungti/kodas → paskyra → naujienos → egzaminas/mano-egzaminas → admin/treniruote → Budo bout panel + live → admin index/nariai → LTKF).

```
Do step D5 from docs/darbo-planas.md on the page ____ .
```

## D6 — LTKF: real content model, own domain (plan first)

- **What:** no LTKF page has fully real content yet. The editor cannot fill the five board names separately (identical placeholders share one text), cannot add a club, news item or document, and loses its only undo on the second save. The site depends on karateka's config, database, login cookie and `../` paths, so it cannot run on ltkf.lt as it is. Every public Budo competition (test ones too) appears on the federation calendar.
- **Order:** (1) stable ids for texts and sections + versions with restore; (2) lists as records with add / edit / remove; (3) a switch off / draft / public with `noindex` while draft; (4) the domain move, after you choose the hosting.
- **Depends on:** your hosting choice; Denis's LTKF answers 30–34.

```
Do step D6 from docs/darbo-planas.md, part ____ (1 / 2 / 3 / 4). Show me the plan first and wait for my OK.
ltkf.lt will be ____ (an extra domain on the karateka hosting / separate hosting at Verskis)
```

## D7 — Dojo: one fight model, ranking, fair rewards (plan first)

- **Arena choice (you delegated it):** **variant 2, "3 kortos + gyvūnėlis".** Reasons: the weapon and the left hand decide how many strikes and blocks you get, exactly as Denis described in #109; one pet with a visible bonus; 3 cards that light up when they work; fewer slot types than variants 1 and 5, so the screen stays clean; it is the one you leaned towards earlier. It is built **once on the server**, keeping what was already agreed for the arena: Japanese technique names with a Lithuanian explanation, blocks on the left and attacks on the right, light panels with gold. The browser-only test page is deleted afterwards. *(reikia leidimo for the deletion)*
- **Also:** #128 one ranking with tabs (Lygis / Kova / Keturi iš eilės / Kata ritmas) and "Tavo vieta"; rewards decided by the server only (today extra gift boxes can be claimed with made-up song names, and "quick fight" wins are trusted from the browser); fights saved safely when two players act in the same second; one list of characters (new heroes can still get removed animals).
- **Depends on:** A4; Denis's answers 35–38.

```
Do step D7 from docs/darbo-planas.md. Show me the plan first and wait for my OK.
Denis's answers: ranking counts wins against bots: ____ ; tabs: ____ ; period: ____ ;
other kids see ____ (real first name + initial / nickname only)
```

## D8 — New pixel characters (plan first)

- **Finding:** only the fox is real pixel art that can change kimono and belt colour. The other 22 characters are flat pictures cut from one sheet: no gear is drawn on them and they have no animation frames.
- **Tool (best fit):** **Aseprite** (about 20 USD once, or free if compiled from source) with the **aseprite-mcp** connector, so frames can be drawn and exported directly. Free alternative: **LibreSprite** with **libresprite-mcp**. For touch-ups by hand: Piskel or Pixelorama (free).
- **Rule:** show 2–3 sample characters large, get your OK on the style, then draw the rest. Characters are unique: not bound to one body shape or stance. One size grid and one outline colour for all (see `docs/standartai.md`, "Game art").
- **Depends on:** D7 (so the art matches the fight model); the tool installed on your PC.

```
Do step D8 from docs/darbo-planas.md. Show me 3 sample characters large first and wait for my OK.
Tool installed: ____ (Aseprite + aseprite-mcp / LibreSprite + libresprite-mcp / none)
```

---

# Phase E — Launch

## E1 — Go live (old step 12, waits for your GO)

Your text stands as written (use the `release-check` skill; list what the old site has that must be kept or redirected; 301s in `.htaccess`; cut-over and rollback under 10 minutes; prepare, show the checklist, wait).

- **Added by the review — must be true before GO:**
  - Phase A is done.
  - `env` is `live` in the server config, and a missing `env` means live in code.
  - Things that are switched in code today are on the checklist: everything-unlocked in the game, the LTKF "draft" flag.
  - Privacy: photo consent per context, the cookie list, what LTKF may publish about referees and children.
  - Test people and test competitions are not visible in public lists.
  - The test database has every `db/` file applied (Valdymas → Nustatymai → Atsarginės kopijos ir sistema shows green), and the test bank is not empty.

```
Do step E1 from docs/darbo-planas.md. Prepare everything, show me the checklist, then wait for my GO.
```

---

# Open questions

## Feedback items that need a word from you or Denis

- **#30** — the message is cut off at "Kai b…". Ask Denis to send it again.
- **#132** — empty item on the exam protocol page, no picture. Ask Denis to send it again.
- **#131** — the screenshot shows the open "Apie klubą" list on top of the logo switch. I treat that as the problem (step B1). If Denis meant something else, he should write again.
- **#136** — "10 kyu": which grades exactly, and which have a fee (questions 17–18).

## Klausimai Deniui (copy and send; answers go back into the step prompts)

*Written in Lithuanian for Denis. Rules of karate, competitions and exams are never guessed: a step that needs an answer waits for it.*

**Varžybos (Budo)**
1. Greitos varžybos treniruotėje: kata vertinama vėliavėlėmis ar balais? Kiek teisėjų?
2. Greitos varžybos: kokie medaliai — dvi bronzos be kovos, kova dėl bronzos ar kitaip?
3. Greitos varžybos: kokia kumite sistema, kai dalyviai tik 3–4?
4. Ką gali sekretoriato nuoroda: tik vesti rezultatus ir siųsti apdovanojimams, ar ir keisti tatamius, perskirstyti grupes, atkurti istoriją, atidaryti registraciją?
5. Kiek laiko po varžybų turi veikti sekretoriato nuoroda: tą pačią dieną, 3 dienas, kol pats išjungsi?
6. Ar kiekvienam tatamiui reikia atskiros nuorodos ir vardo, kad matytųsi, kas įvedė kurį balą?
7. Apdovanojimų planšetė: reikia pilnų vaikų vardų ir pavardžių (skaityti garsiai), ar užtenka „Vardas P.“?
8. Ar tėvai gali rasti vaiką „Rask save“ pagal pavardę (rezultate vis tiek rodoma „Jonas P.“)?
9. Budo tema (fonas): viena visiems, įskaitant žiūrovų telefonus ir TV ekraną, ar kiekviena planšetė savo?
10. #135: kokį ženklą rodyti jauniausių langelyje ir kokį vyriausių (pvz., „−“ ir „+“, ar „ir jaun.“ / „ir vyr.“)?
11. Ar Budo diržų sąraše irgi reikia 10 kyu?
12. Ar kitų klubų treneriai registruodami patys renkasi rungtis, ar visada į kategorijas dėlioji tu?

**Pranešimai ir žinutės**
13. Kas turi didinti raudoną skaičių prie „Valdymas“: tik tai, ką reikia padaryti (patvirtinti, atsakyti), ar ir naujienos („užsiregistravo į egzaminą“)?
14. Ar treneriai (ne tik tu) turi gauti „Moku“ prašymus, pavėluotas registracijas, kitų klubų registracijas? Kai vienas sutvarko — ar dingsta visiems?
15. Kiek laiko saugoti senus pranešimus, žinutes ir SMS telefonų žurnalą?
16. Ar treneriai gali siųsti SMS tėvams, ar tik tu?

**Egzaminai ir testai**
17. Kokius laipsnius pridėti prie egzaminų (10 kyu; ar reikia ir 9 kyu)?
18. Kurie laipsniai turi mokestį? Ar mokestis priklauso tik nuo diržo?
19. Ar raudona taisyklė (6,3 ir mažiau) galioja ir stulpeliui „Testas“?
20. Asmeniniame egzamino puslapyje: ar reikia rodyti telefono numerį? Ar užtenka gimimo metų vietoj visos datos?
21. Kiek laiko po egzamino asmeninis puslapis turi veikti?
22. Teorijos testas prieš egzaminą: ar galima laikyti kiek nori kartų (po kiekvieno bandymo rodomi teisingi atsakymai)?
23. Egzaminų sąraše „dalyvių“ — tai užsiregistravę, ar jau įrašyti į protokolą?

**Paskyros, tėvai, vaikai**
24. Nuo kokio amžiaus vaikas gali turėti savo prisijungimą?
25. Vaikas be el. pašto: ar gali jungtis su vardu ar kodu, kurį sukuria tėvai?
26. Kai tėvai sukuria vaikui prisijungimą — ar treneris turi tvirtinti dar kartą?
27. Kokie žodžiai tinka 7–10 metų vaikui vietoj „Apžvalga“, „Aktyvumo taškai“, „Top 5“?
28. Ar vaikas savo paskyroje turi matyti mokesčius ir kainas, ar tik tėvai?
29. Nuotraukų sutikimas: kokiems atvejams reikia atskiro „Taip / Ne“ (klubo svetainė, socialiniai tinklai, varžybos)?

**LTKF**
30. Koks oficialus federacijos pavadinimas ir rašyba? (Dabar svetainėse yra 4 variantai.)
31. Kurios varžybos turi būti federacijos kalendoriuje: visos viešos Budo varžybos ar tik federacijos patvirtintos?
32. Ar teisėjų vardai, pavardės ir testo galiojimas gali būti vieši federacijos svetainėje?
33. Ar federacijos redaktoriai gali keisti svetainės pavadinimą, kontaktus ir meniu, ar tik puslapių turinį?
34. Valdyba, rekvizitai, klubai, dokumentai, kontaktai — kas atsiųs tikrus duomenis?

**Žaidimas (Dojo)**
35. Reitingas: ar skaičiuoti pergales prieš botus? Kokios skiltys (Lygis, Kova, Keturi iš eilės, Kata ritmas)? Viso laiko, sezono ar savaitės?
36. Ar kiti vaikai gali matyti tikrą vardą ir pavardės raidę, ar tik slapyvardį?
37. Dovanų dėžutė už renginį: tik kai dalyvavimas pažymėtas, ar užtenka „Dalyvausiu“?
38. Ar kovos arenoje kairėje rankoje gali būti skydas ar antras ginklas, ar „2 gynybos“ turi atsirasti kitaip?

**Valdymas**
39. #114: kaip vadinti „Valdymas“ ir „Pradžia“?
40. Prisijungus — iškart į „Valdymas“ pradžią, ar į savo nario puslapį kaip dabar?
41. Kokias Aktyvumo taškų kategorijas treneriai skiria vaikams per treniruotę? (Dabar greitame sąraše tik trenerių kategorijos.)
42. Ar būtų naudingas trumpas puslapis „Kas naujo“ (3 naujausios versijos, po eilutę)?
43. Ar WTKU teisėjų testui reikia atskirų klausimų rinkinių vaikams ir egzaminuotojams?

## Decisions for Deividas (answer in one line, e.g. `1a, 2 ok, 3b`)

| # | Decision | Recommendation | If you say nothing |
|---|---|---|---|
| 1 | Testers: (a) keep testing rights but lose the dangerous buttons (roles, game, backups, site-wide switches); (b) become coaches; (c) stay full admins | **a** | a |
| 2 | Names: (a) keep "Valdymas", call the start page "Šiandien"; (b) other names from Denis | **a** | wait for Denis (#114) |
| 3 | Competition system: (a) Budo stays, the external Varžybos bridge is removed after D1; (b) decide after D1 | **b** | b |
| 4 | Merge the working branch to `main` now (write „į main“): (a) yes; (b) after phase A | **b** | b |
| 5 | Commits in cloud sessions: (a) the cloud saves to its work branch as it works (the cloud forces this), `main` moves only on „į main“, your 23:00 rule applies on your PC; (b) something else | **a** | a |
| 6 | Members and kids on a phone get a bottom tab bar like admins: (a) yes, show variants first; (b) keep the burger menu | **a** | a |
| 7 | Automatic feedback review (the scheduled check that reads new feedback): (a) switch it back on; (b) keep it off | **b** until phase A is done | b |
| 8 | Does Deividas's account see the Dojo game? (a) yes; (b) only Denis | **a** | a |
