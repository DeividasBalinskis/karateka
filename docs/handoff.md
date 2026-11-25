# Handoff
Updated: 2026-11-25 · version v1.98 · work branch

## Pending asks (answer these first)
| Asked | By | What | Status |
|---|---|---|---|
| 2026-11-25 | Dev | Dojo: a new character every 10 levels - which are free at the start, chosen or random? | answered 2026-11-25: every 5 levels, random, gift with animation - done in v1.96 (start = Kitsunė, developer's pick) |
| 2026-11-25 | Denis | OBS bar „link does not work“ (it worked, but was empty without a bout) | answered: help + idle bar + „Pavyzdys“ - done in v1.96, Denis to try on test |
| 2026-11-24 | Dev | D8 pixel concept: mark existing art „Gerai / Netinka“ on the private review page (claude.ai artifact „Dojo pikselių dizainas“, marks saved in its db `zymos`, group notes `pastabos`) + 4 questions: style OK for all characters? how 3 pets help (every turn or chance)? does the bot have pets? whole Dojo at once or backpack + battle first? | waiting |
| 2026-11-20 | Denis | #30 and #147: messages cut off - ask Denis to write them again | waiting |
| 2026-11-22 | Dev | Pick variants: menu on a computer A1/A2/A3, phone menu B1/B2/B3, Budo video C1 (done) + C2 tablet / C3 hall TV (screenshots sent in chat) | waiting |
| 2026-11-22 | Dev | „į main“ for v1.85-v1.93 (main = v1.84) | answered 2026-11-24 („į main viską“) - main = v1.94 |
| 2026-11-24 | Deividas | Pick fonts and themes with 👍 (Aa and 🎨 at the top) - read them from diegimas-api.php `design_likes` | waiting |
| 2026-11-24 | Dev | Excuse letters: the club's Word template (upload in Valdymas → Pateisinimai) | waiting (answer: both Word and PDF - own final file upload done in v1.94; notify only Denis - done) |
| 2026-11-24 | Dev | Old commit authors on GitHub | done 2026-11-24: history rewritten (all commits under Deividas's GitHub identity, files and dates unchanged), main force-pushed with his OK. The session branch could not be deleted from here (same history now) - Deividas deletes it in GitHub → Branches. PC: fetch + reset to origin/main. Rules file name stays; old proposal deleted. |
| 2026-11-22 | Dev | GitHub → domenai.lt: FTP account for the test folder + 3 GitHub secrets (steps: docs/github-domenai.md) | done 2026-11-24: FTP `ikelimas@karateka.lt` → /domains/test.karateka.lt/public_html; first run (Actions → „Įkelti į test.karateka.lt“) OK, test = v1.94, db up to date |
| 2026-11-22 | Dev → Denis | #165 exams waiting list: 4 questions | answered 2026-11-22, done in v1.90 |
| 2026-11-21 | Dev | Answers for not-started steps | answered 2026-11-21 (see decisions.md); waiting: Varžybos code on branch `varzybos-kodas` (D1), LibreSprite on PC (D8) |

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
- v1.86: Denis's 244-question quiz (file imports live at once, „Pirmiau peržiūrėti“ to hold them), #161 answer confirm step in practice mode,
  #162/#163 feedback panel header always visible; phone tab bar moved to the top (inside the header), scroll progress bar removed;
  tablet = computer layout at 1280 px (viewport script in layout.php and index.php, `native` page option for the exam protocol);
  LTKF judges attendance + diplomas (db/052, files in uploads/teisejai, staff only); Budo tatami video (YouTube Live link per tatami,
  viewers see it with the live bout overlay - varzybos-gyvai.js renderVideos); review quick wins (compact member filters on a phone,
  „+N grupės“ fold on events, floating buttons do not cover menus). Variant screenshots for menus and video sent for a pick.
- v1.87: feedback by place - `feedback_area()` in app/feedback.php (page + Budo tab → Budo / Karateka / LTKF + section; FEEDBACK_PLACES map),
  box: group/section chips with 📍 current place, search, status; state in sessionStorage `fb-state-v1` (open, tab, filters, text, editing id);
  admin page: place chips, search, place badge; API: `group`, `area`, `?group=`. Testers do not see Dojo items. Tests: 07za, 06h updated, helpers.openFeedback opens only when closed.
- v1.88: Deividas did not like the chip rows - the box is for writing (+ „Jūsų atsiliepimai“, 3 newest); staff open „📋 Visi atsiliepimai“
  (#fbAllBtn → #fbAll: #fbPlace grouped dropdown g|s, #fbSt, #fbQ, #fbAllList). Admin page: status + place dropdowns (?b=, ?p=g|s; ?g=&s= still work).
  Fixed a race: „+ Naujas“ / opening another item no longer waits for the save (typed text was lost). Tests 06u, 07za updated.
- v1.89: feedback #164 (bigger box, 1 latest own), #166 (protocol rows start 6.4 - EXAM_SCORE_START), #167 (box fits the visual viewport - fit() in feedback.js),
  #168 (LTKF editor: nav click renames, ↗ opens), #169-#171 (Budo: texts out, category name opens run, site_switch_html('budo') in the header);
  verdict buttons (db/053 feedback.verdict ok|revert|reverted; API ?status=revert, POST verdict), diegimas-api.php (GET state / POST dbupdate, API key),
  .github/workflows/ikelti-i-test.yml (FTP to test on „[į test]“ commit or manual run; needs TEST_FTP_* secrets), OBS bar (varzybos-gyvai.php?juosta=1&tatami=N). Test 07zb.
- v1.90: feedback status „question“ (db/054: feedback.answer, answered_at) - highlighted card with answer field, ❓N on 💡 (cfg.questions), top of „Reikia padaryti“, #atsiliepimas opens the box;
  Rules file rule 11 „Questions first“. #165 exam waiting list (db/055 exam_waitlist; settings exam_waitlist_token / exam_waitlist_open; egzamino-registracija.php?k=<waitlist token>;
  admin/egzaminai.php #laukiantieji: assign = exam_protocol_add + e-mail with price by belt; „+ Protokolas šiai datai“ copies the exam event). Tests 07zc, 07zd.
- v1.91: page_start option 'bare' (logo + language only, footer without links, only the 💡 widget) on egzamino-registracija.php (both modes),
  mano-egzaminas.php and testas-svecias.php for guests, privatumas.php?be_meniu=1. Test 07zd checks the registration link has no site links.
- v1.92: short exam links - public_html/e/index.php (code = query string), exam_short_code() (6 chars), exam_code_ok() accepts 4-32;
  exam_signup_url / exam_waitlist_url → e/?code; „↻ Trumpa nuoroda“ (su_short, wl_link); feedback_safe_page scrubs /e/?code, feedback_add scrubs context url.
- v1.93: excuse letters - app/justifications.php (docx fill via ZipArchive, split {tags} merged, LT dates), pateisinimas.php + p/index.php (bare),
  admin/pateisinimai.php (link, list, preview, .docx download, approve and send / reject, Word template or text), db/056, notify type „justification“ (action),
  menu + „Reikia padaryti“. db_auto_update() in db_update.php, called from bootstrap for admins. docs/github-domenai.md - FTP steps. Test 07ze.
- v1.94: excuses - just_update / custom_text (db/057), own final file Word or PDF (uploads/pateisinimai/p{id}.docx|pdf, ?failas=id), „↻ Siųsti dar kartą“,
  recipients setting `just_notify` (JSON ids; unset = admins named Denis, else all) - just_recipients(), to-do only for them; public text „Kai patvirtinsime“.
  #172/#176 💡 bottom-left everywhere (the v1.86 right-side rule removed; .adm-side max-height so it ends above 💡), 🔔 = bell + dropdown (notify-popup.js rewritten, .ntf-bell/.ntf-menu/.ntf-row).
  #173 own list shows one latest (questions always). #174 events.parent_id (db/058) for extra protocols of one exam day; event_main_sql() hides them in public/member lists.
  #175 Budo score table updated in place (morphHtml in varzybos.js renderScore) and moveJ moves focus without blur. Repo wording neutral („API prieiga“). Tests: 07zf new; 06j, 07ze updated.
- v1.95: font test - FONTS in app/themes.php (cookie `sriftas`, admins only), sriftas.php builds overrides from every CSS rule that names Space Grotesk / Inter;
  likes - site_settings `design_likes` {theme|font: {id: [account ids]}}, tema.php action=like, shown in the pickers and in diegimas-api.php GET (`design_likes` with first names);
  themes.css v1.95 block: per-theme --t-panel/--t-border/--t-shadow/--t-glow/--t-chip/--t-2, body::before glow, themed panels (3 px strip), h1, buttons, side menu.
  docs/projekto-zemelapis.md = project map. Test 07zg.
- Committed and pushed to the work branch after each stage.
- 2026-11-24 (PC, not committed): LibreSprite + libresprite-mcp works (Scripts → mcp.js → Connect first, else run_script hangs); test fox head `tools/pikseliai/bandymas/lapinas-32.png`.
  D8 concept (no site code changed): `tools/pikseliai/koncepcija/` - pxkit.py (shapes → shaded layers, one navy outline), veikejai.py (Kitsunė, Meistras Drakonas, Dvasia 48×48 with idle 4 frames + punch/block/hit; pets Vėžliukas, Lapiukas, Drakoniukas 32×32),
  aplinka.py (dojo kiemas 320×180), ui.py (5×7 pixel font with ĄČĘĖĮŠŲŪŽ, panels, buttons, HP bars, hit/block marks), ginklai.py (15 weapons 24×24), koncepcija.py / meniu.py / animacijos.py → `out/` (battle GIF, Dojo home, backpack, animations).
  Review of existing art (contact sheets + second opinion): characters are 150-330 px generated pictures (mixels → dragon looks pasted), weapons 9×7..33×32 (→ backpack uneven), 4 fonts + emoji. Research: Pixelify Sans / Tiny5 / Press Start 2P have latin-ext + Cyrillic; Silkscreen, VT323, Jersey 10, Micro 5 lack Cyrillic.
  `bash tests/checks.sh` cannot run on this PC (no php on PATH).
- 2026-11-25 (PC, committed, not pushed) v1.96: Dojo progression approved („Taip, daryk“). `app/xp.php`: level cost 5 + 2 × floor((n-1)/5) (settings `xp_level_base` 5, `xp_level_step` 2 replace `xp_per_level`; old row 100 left in site_settings, unused),
  `xp_level_cost()`, `xp_level_start()`, `XP_GIFT_LEVELS` 60. `app/game.php`: `game_week_win_box()` (3 wins Mon-Sun = 1 box, reason `wk:YYYYWW`) replaces `game_daily_win_box()`; `GAME_EGG_LEVELS` [3, 8, 15] → egg-only box.
  Admin Treneriams → Taškai: two inputs + current prices. Texts LT/EN/RU. Test 01-scenarios updated. Calculator: claude.ai artifact „Dojo progresija“; doc `docs/dojo-progresija.md`.
  Not run: checks.sh and Playwright (no php/node on this PC). Existing kids jump many levels at once (e.g. 150 XP → level 20) and get those boxes when they open Dojo.
  Exam protocol time (Deividas 2026-11-25: „protokoluose rašyti laiką, kada, keisti“) - done by a separate session in the same folder (start time on the protocol page and in „+ Protokolas šiai datai“; test 07zg).
  Character every 5 levels: `GAME_CHAR_START`, `GAME_CHAR_EVERY`, `game_chars_owned()` (start + current + opened „ch:“ boxes, item_key `ch_<key>`), picker shows locked as 🔒 shadows when GAME_UNLOCK_ALL is false; box reveal animation (game-px.css charRise). Test 06n checks the ch:5 box.
  OBS bar: idle bar (title + tatami), `&pavyzdys=1` sample, help card only when `window.obsstudio` is missing; Budo texts in tools/vertimai/taika.tsv (build.py run). Test 07zb updated (OBS simulated with addInitScript).
  JS syntax of the changed files checked with the Windows script engine; PHP not checked (no php).
- 2026-11-25 v1.97 (PC): Budo streaming page `admin/varzybu-transliacija.php` (auth as Budo: staff, secretariat of that tournament, demo), `tournament_cam_key()`, `varz_cam_id()`, `varz_cam_push_url()`, `varz_cam_view_url()` in app/tournaments.php; CFG.camKey/streamUrl; bout video window `#bout-vid` (varzybos.js syncVid/setVid, localStorage `vz-bout-vid`); bar `&obs=1` = no help. Test 07zh. Translations in tools/vertimai/taika.tsv.
  Released to main and test (CI checks passed). On test: „Test varžybos #1 · Budo Cup pavyzdys“ (id 21) created for Deividas to try; stream page, QR, preview bar and bout video checked at desktop width; phone width (375) not checked (Chrome window could not be resized).
- 2026-11-25 v1.98: phones join by one club QR `varzybos-kamera.php?c=<varz_cam_key>` (ping every 8 s, localStorage id/name, Wake Lock, vdo.ninja push in an iframe: unassigned `kt<key>p<phone>`, assigned `kt<key>t<tatami>`); stream page `admin/varzybu-transliacija.php` lists phones (`&telefonai=1` JSON, assign/forget with CSRF, staff+secretariat, not demo) and gives one OBS link per tatami `varzybos-gyvai.php?b=<bar key>&juosta=1&vaizdas=1&tatami=N` and `&visi=1` (2×2 grid of those, `&tyliai=1` no sound); permanent bar follows `varz_current_tournament()`; test bout `?k=..&bandymas=1`; previews and bout video `&noaudio`. db/059 `stream_phones`. Test 07zh. Translations in tools/vertimai/taika.tsv.
  Released to main and test (CI checks passed, db/059 applied). Checked on test: stream page, one QR, a simulated phone (ping) appears in the list, assigned to 2 TATAMIS → it streams as tatami 2, wrong key → 404, „Visi tatamiai“ 2×2 with the same competition in every cell (all permanent bars use `varz_current_tournament(0)` = latest activity on any tatami), test bout ± shown in the cell; test phone forgotten and test bout ended afterwards. Not checked with a real phone camera (needs Deividas's phone).
- 2026-11-25 (PC, not committed) exam protocol time: each protocol = its own `events.start_time` (no new db file).
  `admin/egzamino-protokolas.php`: „Pradžios laikas“ input #protTime in the header (saved at once: `?save=1` field `start_time`, staff, HH:MM or empty; print shows #protTimePrint).
  `admin/egzaminai.php`: copy_exam form has `start_time` (prefilled with that protocol's time; posted value wins, missing field = copy), list sorted by time within a day.
  `app/exam_protocol.php`: Excel/CSV header gets „Pradžios laikas: HH:MM“. CSS `.prot-time`. 3 texts LT/EN/RU. Test `tests/e2e/07zg-protokolo-laikas.spec.js` (new, not run - no php/node here).
  A changed time sends no e-mail to people already in the protocol (not asked).

## Open
- C1 permissions, C3 secret links, C5 CSS cleanup (safe now with tests/visual.sh), C6 engine unit tests, D3 split varzybos.js, D4 same group on two tablets, D5 Budo live polish, D7 arena left hand UI, D8 pixel characters (PC + LibreSprite).
- Parent payments for Budo registration need a payment provider (later).

## Next
- Apply the picked variants (A, B, C). Kumite Technology style (video replay + marking scores on the video) only after a pick: it needs a streaming server or phone-to-browser upload (costs, plan first).
- B8: confirm that Nustatymai is enough as the „Paskyra“ page. Then C4 design tokens (plan first) or D5 Budo live page.
