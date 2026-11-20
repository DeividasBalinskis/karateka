# Karateka — rules for Claude

Club site karateka.lt (PHP 8, MariaDB, no framework), the Budo competition system, the LTKF federation
site and the Dojo game. One repo, one database.

## People
- **Deividas** — developer and admin. Windows, PowerShell, often on a phone. Wants short, plain answers.
- **Denis** — head coach, club leader, main tester. His feedback is the spec. If his wish is unclear,
  ask Deividas first.
- **Testers** — everyone else with admin rights. They must not see Dojo or the game.

## Rules
1. Answer in the language of the question. Short and plain. Yes or no first.
2. One plan step per session (`docs/darbo-planas.md`). "(plan first)" = show the plan and wait.
3. Ask before anything that cannot be undone or changes structure: deleting files or page sections,
   renaming routes, data changes that can lose data, git tags.
4. Never invent a karate, competition, exam or grading rule. Write "unclear" and ask.
5. No secrets, passwords, e-mails or phone numbers in any file. Placeholders only.
   Never commit `config.php`.
6. All screen text is Lithuanian with correct letters, through `t()` / `te()` with EN and RU.
   Write "karate" (never "karatė"), "karate-do", "Budo" (never "Taika"),
   "XP = aktyvumo taškai" (one system: activity gives XP, each level gives a gift in the Dojo).
7. Check the state yourself (git, files, tests). Do not ask Deividas to paste output.
8. If something is impossible here, say so in one line and offer the nearest thing that works.
9. When Deividas must do something himself: numbered steps, exact clicks or PowerShell lines.
10. Follow `docs/standartai.md`. If it and `docs/decisions.md` disagree, the decision wins.

## Where things are
- Repo on Deividas's PC: `C:\Users\deivi\Documents\IT Projektai\Karateka`
- Test site: test.karateka.lt (database `aus15792_test`). Live karateka.lt is **not touched**.
- `public_html/` — the site. `app/` shared code, `admin/` staff pages (Valdymas), `assets/` CSS and JS,
  `ltkf/` federation site. Budo = `admin/varzybos*.php`, `varzybos-*.php`, `assets/varzybos*.js|css`,
  `app/tournaments.php`. Game = `dojo.php`, `arena.php`, `zaidimas.php`, `app/game*.php`, `assets/game*`.
- `db/` — numbered migrations; the newest name is set in `public_html/app/db.php`.
- `tests/` — `checks.sh` (fast checks), `e2e/` (Playwright). `tools/` — PowerShell scripts, translations,
  pixel art. `docs/` — plan, standards, changelog (`pakeitimai.md`), deploy guide (`ikelti-i-test.md`).
- Version: `public_html/app/VERSION`.

## Read first
- `docs/handoff.md` — what changed last, what is open, pending asks. **Answer pending asks first.**
- `docs/darbo-planas.md` — the steps and their order.
- `docs/standartai.md` — the rules for every back system.
- `docs/decisions.md` — what was agreed. Check it before any design or menu change.

## Feature flags
- Game (Dojo): hidden from all menus until it is opened to members (setting `game_access` = `all`; no switch on screen since v1.50, #145).
  Real admins open it from Valdymas → Testavimas → „Atidaryti Dojo →“. Testers never.
- Feedback button: staff only by default (`feedback_button`).
- Registration switches: `reg_off` (accounts, events, exams, Budo).
- SMS: provider `log` (test mode) unless `config.php` says otherwise; admins only.
- External Varžybos: hidden unless its `config.php` block is set.
- LTKF: draft; visible only on the test site.

## Start of a session
1. Read the four files above.
2. `git status`, `git log -3`, current version.
3. Say in 3 lines: what you will do, what you will not touch, what you need from Deividas.

## End of a session
1. `bash tests/checks.sh` and the tests for what changed. Say plainly what passed and what failed.
2. Screenshots of every changed screen at **375** and **1440** px (Valdymas and Budo also 1280 and
   2560 when layout changed), shown large in the chat, before and after.
3. A checklist: what was done, the click path to it, what is still open.
4. Update `docs/handoff.md`.
5. For a release, end with the closing template from `docs/standartai.md` (version, zip, files,
   database files in order, how to check, "live site not touched").

## Commits
- Messages are short and human. No "Co-Authored-By", no "Generated with" lines.
- **On Deividas's PC:** do not commit or push unless he says so. His habit: one commit at 23:00 on days
  with progress.
- **In a cloud session:** the session's machine is thrown away afterwards and it cannot finish with
  unsaved work, so it saves to its own `claude/…` branch as it goes. That is a backup, not a release.
- `main` moves only when Deividas writes **„į main“**. No pull requests unless asked. No force push.

## Feedback
- The 💡 box on the test site is the single inbox. "Pažiūrėk atsiliepimus" = read all new items,
  fix what needs no decision, list the rest as questions, re-check older open items.
- Reply to each item: "Padaryta (vX.YY)" + the click path, or one clear question.

## Tests (local)
- Fast checks: `bash tests/checks.sh`
- Browser tests: `cd tests && npx playwright test e2e/<file> --reporter=line`
- Never two runs at once. Do not change code while a run is going.
- Test logins are created by `tests/setup/reset-db.sh`; their password is in that script, not here.

## Notes for me (Deividas fills in)
- ____
