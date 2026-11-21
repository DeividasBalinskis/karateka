# Handoff
Updated: 2026-11-21 · version v1.85 · branch claude/festive-curie-01jgx2

## Pending asks (answer these first)
| Asked | By | What | Status |
|---|---|---|---|
| 2026-11-20 | Denis | #30 and #147: messages cut off - ask Denis to write them again | waiting |
| 2026-11-21 | Claude | Answers for not-started steps | answered 2026-11-21 (see decisions.md); waiting: Varžybos code on branch `varzybos-kodas` (D1), LibreSprite on PC (D8) |

## Last session
- v1.52: the 43 answers applied (Šiandien start page, to-do red number, 1-year retention, exam page privacy, 10 kyu, secretariat 2 days,
  multi-tablet merge D4, Rask save by surname, device theme, LTKF approval, Dojo ranking rules, kid words).
- v1.53: „Kas naujo“ (click the version number), code for another device (one-time, 10 min, revocable, logged; db/047),
  B7 done: child login with username + PIN by parents, parent trains too, adult member adds a child, child chat switch; „Mano diena“ tab for 7-10.
- v1.54: B3 Budo on a phone (bout fits one screen, „⏱ laikas ir gongas“ folds, floats hidden in a bout, lower header,
  tablet overflow fixed, confirmations shown, TV tatami picker, names without birth year shortened in public).
- v1.55: B5 notifications - one type list (action / notice / celebration), staff red number = to-dos until done, notices fold.
- v1.56: A3 exam links safe (already-registered message without link, no name-only match, find limited per IP, ↻ new link).
- v1.57: C7 one polling request (pulsas.php + assets/pulse.js; chat.js, notify-popup.js, game-invites.js listen to „karateka:pulse“).
- v1.58: B1 done (LTKF EN/RU keys fixed, „karatė“ rule in checks.sh).
- v1.59–v1.60: B6 done (exam list counts + import, protocol on a tablet, new event „Kita“ + „Bus pakviesta: N“).
- v1.61-v1.66: D5 polish of index, events, login, Paskyra, news, Treniruotė, exam pages, Valdymas lists, LTKF (tap targets; small buttons 40 px on touch); protocol fits 1100-1366 px again. C6 smoke set `tests/smoke.sh`.
- v1.67-v1.68: D5 Valdymas lists + LTKF on a phone.
- v1.69: quiz „Atsisiųsti klausimus“ in the page header; Dojo in the menu for real admins while hidden (decision 2026-11-21).
- v1.70: B5 done - member/parent red number = only to-dos (tasks, unread notes and replies, invitations waiting for an answer).
- v1.71: B8 kid home variant B (level ring + XP on top, next training + one task with „✓ Atlikau“, „Kas toliau“; fits 375x812; no logout/prices on the first screen).
- v1.72: member bottom tab bar on a phone (Apžvalga/Mano diena, Renginiai, Užduotys, Egzaminas, ⋯ Daugiau), 🏯 Dojo as a corner button for members, feedback #154-#156 (LTKF list site tags, compact to-do rows, side menu aligned with the first card).
- Feedback #153-#156 answered on the test site (#153 = test DB was behind, db/045). Exam protocol fits 1175 px with 4+ score columns. Tests: login() closes earlier tests' windows - full suite 140/140 in 13.7 min.
- v1.73: feedback #157 (Budo results transfer by themselves), #158 (name follows to member card and Dojo), #159 (arena chooser).
- v1.74: B9 done - one menu layout (variants removed), Renginiai list inside Valdymas, editors keep the side menu, LTKF row, stale hints fixed. A6 done (zip keeps old packages, deploy guide).
- v1.75: C8 part - coach reply e-mail only when ticked (chat and messages page).
- E1 checklist: `docs/paleidimo-patikra.md` (old karateka.lt addresses: host blocked in the cloud session). Full suite v1.75: 143/145 passed, 1 skipped, 1 flaky (06k Budo pin drag - passes alone on a clean DB, Budo code unchanged).
- Feedback #160 (LTKF clubs, members and judges registers) - planned as D6 part 2; asked who enters club members.
- v1.76: A1 done (unsaved Budo changes kept on the device, merged after reopening); #109 / decision 38 left hand (shield = 2 blocks, second weapon = 2 strikes, second strike 75 %).
- v1.77: #160 LTKF registers (members, clubs, judges) in Valdymas → Federacijos registrai; LTKF pages read clubs and judges from them; db/048.
- v1.78: Budo club registration with events per athlete + „Jūsų užregistruoti“ (decision 12); C2 language on the account; C8 e-mail templates in the reader's language, outbox with per-person result (admin/siuntimas.php); db/049.
- v1.79: C10 Duomenų priežiūra (list first, delete only on button + confirm), photo .zip backup, cookie list on the privacy page. Nothing deleted on the test site by me.
- v1.80: D6 parts 1-3 - repeated texts edited one by one, page versions with restore + multi-step undo, LTKF news and documents as records, LTKF state off/draft/public (noindex unless public); db/050. Tests: login() resets the account language (C2 keeps language on the account).
- v1.81: C2 - settings registry (`app/settings.php`, checked by checks.sh), „Visi nustatymai“ map, change log with restore (db/051). Old `reg_off` name kept (renaming = data change, ask first).
- v1.82: C4 - `assets/tokens.css` (loaded first, same values, 55/55 screenshots identical) + `tests/visual.sh` (baseline / compare; PW_CHROMIUM for a custom browser path).
- v1.83: B2 - quick competition (Kata/Kumite ticked) no longer creates a member event by default. B8 closed by decision (Nustatymai = Paskyra page). Full suite on v1.82: 154 passed, 1 skipped, 1 failed (07s - test login reset the language it checks; fixed, passes).
- v1.84: Deividas - on a phone 💡 is its own button again (left), 💬 stays messages (right); the B4 combined button is removed. „į main“ given 2026-11-21: main = v1.84.
- v1.85: #158 rest - an old account/member name mismatch (name changed before v1.73) heals on Paskyra, Dojo or login: the „self“ member and its hero take the account name (account_self_name_sync in app/auth.php). main is still v1.84 - v1.85 waits for „į main“.
- Committed and pushed to the claude branch after each stage.

## Open
- C1 permissions, C3 secret links, C5 CSS cleanup (safe now with tests/visual.sh), C6 engine unit tests, D3 split varzybos.js, D4 same group on two tablets, D5 Budo live polish, D7 arena left hand UI, D8 pixel characters (PC + LibreSprite).
- Parent payments for Budo registration need a payment provider (later).

## Next
- B8: confirm that Nustatymai is enough as the „Paskyra“ page. Then C4 design tokens (plan first) or D5 Budo live page.
