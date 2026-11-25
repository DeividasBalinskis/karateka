# Handoff
Updated: 2026-11-25 · version v2.11 · main

## Pending asks (answer these first)
| Asked | By | What | Status |
|---|---|---|---|
| 2026-11-25 | Dev (C1) | Testers: give them the Atsiliepimai page and „Žiūrėti kaip“ (the standartai table says ✔, today they do not have them)? | waiting |
| 2026-11-25 | Dev (C1) | Excuse letters: while `just_notify` is unset, recipients = admins named Denis (a choice by name). Keep, or one chosen account? | waiting |
| 2026-11-25 | Dev (C5) | Extra CSS widths 1099, 860, 900, 1400, 520, 400, 1101-1280 → the standard four? (visible change at those widths) | waiting |
| 2026-11-25 | Dev (D3) → Denis | Budo rule (not changed, not invented): round robin - same wins → both 1st even if A beat B; elimination group with 1 participant never gets 1st / never finished. Intended? | waiting |
| 2026-11-25 | Dev | D1 done - pick the system that stays (D2): `docs/varzybos-palyginimas.md` | answered 2026-11-25: Budo stays. Varžybos code saved on branch `varzybos-kodas` (43 files, no config/.env/.sql). Next: Denis picks features to port (table in the doc), OK to remove the bridge |
| 2026-11-25 | Dev | Fixing agent in the cloud (routine „Karateka: Peržiūros eilė (kas valandą)“, trig_01KwSq8HaCyBeQzfAB2Wxmvg, disabled): Deividas puts the API key into the cloud environment „karateka“ as `KARATEKA_API_KEY` and allows network to `test.karateka.lt`, then it is switched on. Every 15 min is not possible in the cloud (minimum 1 hour) - now every hour 07-23; or every 15 min on the PC while the app is open? | waiting |
| 2026-11-25 | Dev | „okay darom kad 15 min dabar, bet jeigu …“ - the sentence was cut off | waiting |
| 2026-11-25 | Dev → Denis | Feedback questions on test: #179 delete only empty protocols? #181 delete „4 kyu“ protocol with its 1 participant? #184 Budo top bar default A light / B Budo red / C soft blue-grey (picture: side-session notes `ui-perziura/5-184-budo-virsus-variantai.png`) | waiting |
| 2026-11-25 | Denis | #182 Budo: auto-lock a category's draw when all its bouts are done (Budo files were locked by the streaming session) | planned |
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
- 2026-11-25 v1.99: Deividas's real phone joined and was assigned (stream_phones) but no video reached the PC: the vdo.ninja camera inside an iframe on the phone did not stream. Now the phone page only waits for the assignment, then a big link opens the vdo.ninja camera full screen (`varz_cam_push_url` = push + webcam + autostart + facing=rear + meshcast). OBS removed entirely (bar help, OBS links, „Visi tatamiai“, permanent bar key, `varz_current_tournament`); stream ids `kt` + sha256(key|t|N)[0:20] (`varz_cam_id`); viewers' JSON has `areas[].cam` for tatamis with an assigned phone seen in 12 h (`varz_cams_live`), viewer page „▶ Žiūrėti gyvai“ (iframe only after a tap); stream page = QR + waiting phones + a card per tatami (video + bar iframe `&valdymas=1` with ± next to the scores); bout video uses CFG.cams and has ± on the video (clicks the panel's own `[data-pt]` buttons). #182 `autoLock()` in all result paths; #184 header #4F5E73→#67788F; `.ico` rule in varzybos.css; tabs and schedule fixes. Includes the review session's 14 files (header/home/app CSS, translations). Tests: 07zh rewritten, 07zb OBS parts removed. Open: #179/#181 (delete exam protocols) - waiting for Deividas. 2026-11-25: Deividas confirmed on test with his own phone - the phone video shows on the PC (v1.99, split +/- button by the scores).
- 2026-11-25 v2.00 (released to main and test; Live kova checked on test; Valdymas menu max-height 100vh-180px; 💡 hidden in Live). Gotcha: `gh workflow run` right after `git push` may build the previous commit - wait ~6 s, then check the run's headSha. Was: „📹 Live kova“ window (varzybos.js `syncLive`/`setLive`/`liveNext`, `#live-win`, localStorage `<KEY>-live`; Tvarkaraštis button `#live-cam`, bout header `#bt-live`); its buttons click the bout panel's `[data-pt]`, `#bt-start`, `#bt-finish`, `[data-win]`; side video hidden while Live is open. Test in 07zh.
- 2026-11-25 v2.01 (side session, released to main and test): secretariat desk for one tatami (Deividas's ask). Budo → Tvarkaraštis with a tatami picked
  on the device (`myArea`, localStorage `<KEY>-myarea`) = desk `#desk` (admin/varzybu-sistema.php): left menu `.desk-side` („📋 Mano tatamis“, „📹 Live kova“ = setLive,
  „🏟 Visi tatamiai“ = sessionStorage `<KEY>-deskall` → old board + `#desk-back` / run-strip `[data-desk-back]`), `#dk-now` (now / next bout, „▶ Kovos skydelis“),
  rows `.dk-row` (renderDesk/deskRow from areaPlan; time input = setCatAt; ⠿ pointer drag → moveCat), a clicked row expands in place: `#run` is moved into `#dk-run`
  (class `in-desk` hides run-strip, back, title, prev/next, run-list, category select) - never re-created, so score inputs keep focus (#175).
  „✎ Keisti“ (#draw-swap, was „⇄ Keisti vietas“; also for score order: in swap mode moveOrder swaps). Live window: „🏅 Rezultatai“ `.lv-side` (pinTree of the group,
  toggleResult shared with pins; localStorage `vz-live-side`), close label „📋 Mano tatamis“ in desk mode. `?tatamis=N` (CFG.deskArea) opens that desk and is removed
  from the address; secretariat link `varzybos-sekretoriatas.php?k=…&t=N` names the tatami and lands there; 🧑‍💼 panel: „📋 Sekretoriato darbastalis“ buttons per tatami
  + copy links per tatami (renderSekrDesk). The one-column „only my tatami“ board is gone (shownAreas = all). CSS `--vz-head` = sticky header height (setHeadVar).
  Tests: new 07zk-sekretoriato-darbastalis; 06i (desk instead of one column) and 06k („✎ Keisti“) updated. CI run 45612854239 (Actions → Testai → spec, main c656196):
  07zk, 06i (4), 06k (4), 06q, 07zh „Live kova“ passed; 07zh „Budo streaming with phones only“ FAILS at line 55 - admin/varzybu-transliacija.php, ± in the bar iframe:
  „<html lang="lt"> intercepts pointer events“ (pages not touched by v2.01; this test was never run in CI since the v1.99 rewrite) - open, trace in the run's artifact.
  Local check without PHP: scratchpad harness (make.py renders the template + generated test competition, serve.py) at 1440 / 1024 / 375, dark theme, secretariat mode.
  Another session works in the same folder at the same time (review system: layout.php, header.css, app/review.php, db/060, 07zi-meniu).
  Mistake: commit aab1a85 („v2.01: Budo header at tablet width…“) also took that session's files, which were staged (git add) in the shared index at that moment -
  the whole review system + Valdymas menu v2.02 (VERSION 2.02, db/060_perziura.sql, layout.php, header.css, review.*, perziura*.php, 07zi/07zj tests). Content unchanged,
  only the commit message is wrong; it is on main and on test (deploy run 45609232401 OK, guest pages 200, db/060 runs when an admin opens a page). Not reverted
  (a revert would delete that session's files from the shared folder; no force push). Lesson: in a shared folder commit with `git commit -m … -- <paths>` (only those paths).
- 2026-11-25 (PC, not committed) exam protocol time: each protocol = its own `events.start_time` (no new db file).
  `admin/egzamino-protokolas.php`: „Pradžios laikas“ input #protTime in the header (saved at once: `?save=1` field `start_time`, staff, HH:MM or empty; print shows #protTimePrint).
  `admin/egzaminai.php`: copy_exam form has `start_time` (prefilled with that protocol's time; posted value wins, missing field = copy), list sorted by time within a day.
  `app/exam_protocol.php`: Excel/CSV header gets „Pradžios laikas: HH:MM“. CSS `.prot-time`. 3 texts LT/EN/RU. Test `tests/e2e/07zg-protokolo-laikas.spec.js` (new, not run - no php/node here).
  A changed time sends no e-mail to people already in the protocol (not asked).
- 2026-11-25 (PC, side session, not committed) feedback #179-#184 + UI review of the main screens (375 / 768 / 1000 / 1280 / 1440 / 2560, overflow script + eyes, guest view via a credentialless iframe, member view via „Žiūrėti kaip → Vaikas“, EN/RU via Test Junior; Deividas's session and language restored).
  Fixed (for the next version, after v1.98): `assets/header.css` - at ≤1100 px the free space goes after `.nav-prefs`, not after the logo (#183: ▾ was 27 px from the logo at 375, 224 px at 768, 641 px at 1000; now 6 / 10 / 20 px), `.site-switch` margin 0 there; bare header (exam link pages) language button at the right edge again (was floating mid-screen ≤900 px).
  `assets/home.css` - „✎ Redaguoti puslapį“ (.hb-fab) moves to 142 px when 🔔 is shown (`body:has(.ntf-pop:not([hidden]))`; both sat at bottom 84 px since v1.94).
  `assets/app.css` - `.event` overflow-wrap:anywhere + `.events-list` ≤760 `minmax(0,1fr)`: a long URL in an event summary pushed Renginiai and Paskyra 4 px past the phone screen.
  Translations: trial form note (app/home.php), 404 page (app/helpers.php), reaction groups + aria-label, event staff buttons (app/data.php), theme names (7 keys were missing), password 👁 aria-label (header.js + lang/js.php), home schedule kicker (home.js keeps the server label), Paskyra flashes/confirm, registration errors, event RSVP flashes („Denis“ → „treneris“, rule 7.4). New keys at the end of app/lang/en.php and ru.php. Python port of tests/i18n-keys.php: all t() keys have EN and RU.
  Feedback replies posted on test: #180 done (v1.96), #182 and #183 planned (mark „Padaryta (vX.YY)“ after the upload), #179 / #181 / #184 questions.
  Not run: checks.sh, PHP lint, Playwright (no php/node). Screenshots and the full list: side-session notes folder `ui-perziura/` + report.
  Found, not fixed (locked Budo/LTKF files): Budo `.ico` missing in varzybos.css (giant ▼ on „CSV (Excel)“ and „Kontaktai (Excel)“); Budo schedule at 1440 with 4 tatamis - finished cards' names squeezed to 49 px; Budo 375 - active sub-tab „3 Dalyviai“ tall white box, main tabs cut; LTKF EN missing „dienų iki pradžios“, calendar and judges notes (ltkf/inc/lang). Not translated yet: testas.php quiz JS (~12 texts), staff confirm() texts in admin pages. Content: news text „tradicinio karatė do“ (should be „karate-do“).

- 2026-11-25 v2.02 (main session): Valdymas menu groups Kasdien / Darbai / Nustatymai + admin editor „✎“ (app/layout.php `admin_menu_items` /
  `admin_menu_default` / `admin_menu_layout` / `admin_menu_groups` (static memo) / `admin_menu_editor_data`; site setting `admin_menu` JSON {groups:[{id,name,items}],names};
  save = admin/meniu.php (JSON, X-CSRF-Token, is_admin); assets/admin-menu.js (HTML5 drag + ↑↓, rename inputs, + group, delete → first group, reset); CSS `.am-ed*` in header.css).
  Review system: db/060 (review_jobs, review_marks), app/review.php (manifest app/review.json: rev, version, items {id,page,href,sel,title,note}; page patterns with * and |;
  overlay only when env ≠ live and is_admin), assets/review.js + review.css (gold `.rv-box`, `.rv-ctl` □/✓/✗ + 💬, `.rv-bar` with ☰ list, 🎯 pick → feedback with
  context {pick, selector, text}, „Pateikti“ → job `review`), perziura-zymos.php (mark / submit / pick; the old perziura.php = view switch, untouched),
  admin/perziura.php (queue, test changes + „⬆ Įkelti į test“, what to fix + „▶ Paleisti taisymą“), perziura-api.php (agent: GET jobs + feedback texts + marks,
  POST status/result, POST {new:…} for its own work). The overlay is injected by feedback_widget_html(), so Budo pages have it too.
  Cloud agent instructions: docs/planai/taisymo-agentas.md (branch `claude/taisymai`, deploy = empty commit with „[į test]“). tests.yml: input `spec`, timeout 45 min
  (full runs were cancelled at 20 min). Tests 07zi-meniu, 07zj-perziura (run in CI with spec).
- 2026-11-25 v2.03-v2.06 (main session, on main and test): the overlay was not printed when 💡 is visible (feedback_widget_html now returns $rv . …);
  overlay classes renamed `rv-` → `pzr-` (app.css already has `.rv-bar` etc. for the quiz review page, testo-perziura.php - it squashed the bar to 12 px);
  mark buttons sit above the gold outline, side by side when they would overlap (never over ✎); phone/tablet bar centred between 💡 and 💬;
  menu rows drag only by ⠿ / icon (the name field is for typing); side menu full height while editing. Quiz page (testas.php) JS texts through t().
  CI (workflow_dispatch with spec): 07zi-meniu ✓, 07zj-perziura ✓, 07-pages ✓ (27), 07za ✓, 07x ✓. Checked on test.karateka.lt at 2560 and 375 (iframe):
  db/060 applied by itself on the first admin page, Peržiūra page, gold marks, bar.
  Gotcha: two sessions in one folder share one git index - `git add` + another session's `git commit` took my files into its commit (aab1a85).
  Always `git commit -- <paths>`.
- 2026-11-25 v2.07: Budo stream bar ± / ✕ were not clickable (`.gv-ovl` has pointer-events:none - now auto on `.ov-pm`, `.ov-end`);
  viewers' JSON gets `cams` (tatamis with a phone that are not yet in the saved summary, from state) - renderVideos adds them.
  Test 07zh fixed (the loop reopened a finished bout: „▶“ is shown on finished bouts too → `.match:not(.is-done) .bout-open`). 07zh ✓ in CI (both tests).
- 2026-11-25 C1 permissions (side session, branch `claude/c1-teises-61f4c7`, not in main, VERSION not bumped): `PERMISSIONS` const (39 actions × roles guest/member/parent/coach/tester/admin)
  + `can()` / `require_can()` / `account_role()` in app/auth.php; the same table in docs/standartai.md §2. All ~230 `is_admin()` / `is_staff()` / `require_staff()` / `require_admin()` /
  `is_tester()` calls and `role = "admin"` SQL outside auth.php replaced, behaviour kept (wrappers stay inside auth.php only). LTKF `karateka_adminas()` loads auth.php (bootstrap now `require_once`).
  Homepage editors: accounts marked in Paskyros „🏠 Redaguoja pradžią“ (setting `home_editors`), no longer names in the instructors block - instructors lose editing until an admin marks them (a hint shows the matching card).
  db/061 `account_log` (created/role/status/is_tester/game_access/home_editor/password, who + old → new, 1 year) shown in Paskyros → „Pakeitimų žurnalas“; `accounts.session_ver`:
  password change, reset by link and a child's new PIN sign out other devices (`account_password_set()`, `session_account_set()`; „Žiūrėti kaip“ keeps the admin's version).
  CSRF: `csrf_ok()` added; awards tablet and phone camera POSTs now send X-CSRF-Token; exceptions = the 3 key APIs. checks.sh: no raw role checks, every action in the docs table, every POST page has csrf_check/csrf_ok.
  Test 07zm-paskyros-gyvenimas (life cycle + page access per role); 07zh sends the token. Open questions: testers lack Atsiliepimai and „Žiūrėti kaip“ (table says ✔);
  excuse-letter recipients default to admins named Denis while `just_notify` is unset. Gotcha for merges: new code with `is_admin(` etc. outside auth.php now fails checks.sh - use `can()`.
  CI on the branch (main v2.09 merged in): checks.sh ✓; 07zm (2), 07-pages, 07b, 07zh, 06i ✓ (run 45623054023); 06r updated to the new editor rule ✓ (run 45630694117).
  Full suite (run 45623035547): 158 passed, 21 failed - 20 of them fail on main too (main run 45622864810: 01, 06h, 06n ×4, 06x ×3, 06y, 06z, 07h, 07i, 07o, 07q, 07v, 07zd, 07ze, 07zf, 08); the 21st was 06r, now fixed.

- 2026-11-25 v2.08: #179 exam protocol delete (admin/egzaminai.php `delete_protocol`, `exam_protocol_people()` in app/exam_protocol.php; with people → `?trinti=ID#trinti`
  confirm box; children of a deleted main protocol: first one becomes main). Test 07zl ✓. Agent: perziura-api.php „working“ = atomic claim (409 taken; stale after 90 min).
  PC scheduled task `karateka-perziuros-eile` (*/15 7-22, Auto mode, no notifications) + cloud routine trig_01KwSq8HaCyBeQzfAB2Wxmvg (disabled until the key is set).
  Both need `KARATEKA_API_KEY` (cloud env „karateka“ + Windows user variable). PC copy works only in the worktree `..\Karateka-agentas` on `claude/taisymai`.
  Plan steps started in their own worktree sessions (Deividas: „tiesiog daryk“): C1 `c1-teises`, C5 `c5-css` (header.css), C6+D3 `d3-budo-moduliai`, D1 `d1-palyginimas`.
  C3 waits for Denis's answers (secretariat link lifetime, one link per competition or tatami, awards tablet names).
- 2026-11-25 v2.09: Deividas used Peržiūra on test (jobs #1, #2 „review“, all good, one comment on the side menu) - done by hand in the main session:
  side menu max-height calc(100vh - 108px) + padding-bottom 64px (widgets.css); admin/perziura.php action `close` („✓“ + reply) for queued/working jobs;
  app/review.json → rev 2.09-1. Test 07zj now writes its own manifest (beforeAll / afterAll restore), so review.json can change freely.
- 2026-11-25 C3 (side session, branch `claude/c3-nuorodos-1bb636`, not merged, VERSION not bumped): secret links - one model. db/062 `access_links` (+ `tournaments.saved_by`,
  `tournament_versions.saved_by`, `tournament_awards.by_name`), `app/links.php` (LINK_KINDS: sekr, awards, live, reg, demo, cam, exam_signup, exam_waitlist, exam_row, quiz, just,
  ltkf_editor, ltkf_review; fingerprint + locked copy, `links_key` in config.example.php; `link_find` / `link_alive` / `link_box` / `link_dead`), `admin/nuorodos.php` „Visos nuorodos“
  (menu key `nuorodos`, also Nustatymai → Sistema). Old tokens move on first use (`links_import` once, mark `links_imported`; `link_legacy_find` per key) - old columns/settings untouched.
  Every link page shows one „Nuoroda neveikia …“ page. Secretariat: one link per competition (`&t=N` = tatami hint), 2 days after (LINK_SEKR_DAYS), no history restore /
  registration / stream settings, „Baigti darbą“; awards tablet asks a name (saved as `by_name`); Budo history shows „👤 name“ per copy. Tests: new `tests/e2e/links.spec.js`;
  `tests/setup/link.php` + helpers `linkToken` / `linkNew` (tests read keys through PHP now); 17 specs updated (06d/e/f/i/l/p/q/s/t/u, 07i/k/z/zb/zd/ze/zh/zk), 07-pages has the new page.
  CI on the branch: run 45640461052 ✓ 38/38 (links, 06d, 06q, 06t, 07-pages, 07k); run 45638183610 73/75 (06e/f/i/l/p/s/u, 07z/zb/zg/zh/zk ✓; 07i fails on main the same way; links fixed after).
  07v, 07zd, 07ze, 07zf (and flaky 06z:155) fail on main v2.10 with the same errors - not from C3. Screenshots not taken (no PHP on the PC) - after the release on test.
  Merge notes: db file is 062 (C1 branch has 061_paskyru_zurnalas.sql - DB_REQUIRED takes the higher one). Touches `app/tournaments.php`, `admin/varzybu-sistema.php` (link panels), one line in `assets/varzybos.js` (history row), `app/layout.php` menu item, `app/bootstrap.php` require.
- 2026-11-25 v2.10: Peržiūra off (`review_enabled()` = site setting `review_on` === '1'; off → no overlay, no menu item, admin/perziura.php shows a link
  to the switch, perziura-api.php answers {ok:false, off:true}). Switch on admin/testavimas.php (#perziura). app/review.json items emptied (all approved).
  PC task `karateka-perziuros-eile` disabled; cloud routine disabled. 07zj turns review on in beforeAll and has an „off“ test.
- 2026-11-25 C5 file 1/5 `public_html/assets/header.css` (side session, branch `claude/c5-css-header-e5d689`, main v2.10 merged in; VERSION not bumped, not on main):
  Before → after: 428 → 396 lines, 309 → 262 rules, 1089 → 957 declarations, `!important` 16 → 6, @media blocks 20 → 9 (distinct widths 9 → 9),
  distinct colours 92 → 75, „vX.YY“ blocks 3 → 0. All @media now at the end, one block per width.
  Folded into the rule they change: #104 logo ▾, #107 „Žiūrėti kaip“, #113 (the „›“ rule and its `content:none` both gone), #114 „Valdymas“ button
  (one rule; `body[data-theme]` copies and `.is-current` rules removed - body always has data-theme, colour themes decide .is-current in themes.css),
  #97 „Grįžti į valdymą“ into `.site-header .adm-sw`, „Apie klubą ▾“ button, duplicates.
  Deleted (no page or script makes them): phone menu variants pm-burger / pm-lapas / pm-eilute with .pm-admin / .pm-site (`phone_menu_variant()` is always
  'apacia'), old Admin slider (.adm-knob, .adm-sw.is-on), .lang-li, .lang-opt, .lang-caret, .sub-nav-2; rules that never win (.lang-switch base and
  `.site-header nav.lang-switch` → merged into `.lang-dd > nav.lang-switch`; ≤1100 `.am-line .am-more-panel`; ≤860 `.ver-tag`, `.sub-nav` padding and
  justify-content; ≤1100 `.lang-switch a` padding); two invalid `font: … inherit` lines (never applied). Kept: `.sub-nav a.sub-last` (PHP still names it).
  Tokens used: --cream, --pink, --border, --border-soft, --admin-gradient(-hover), --radius-sm/md/pill, --tap, --font-mono, --sp-1..3 (only where the
  whole value is on the scale). Not used on purpose: --ink / --muted / --accent (themes.css and varzybos.css redefine them - the header would change
  colour in themes), --font-body ('Inter' stays written out: sriftas.php looks for it), --fs-* (rem; the header is px on purpose).
  `!important` kept (6): `[hidden]`; „Valdymas“ text colour (else themes.css dark menu colour wins); `.am-row` / `.am-row.active` + their 2 dark copies
  („Daugiau ▾“ rows sit inside `.sub-nav a`, which is stronger; themes.css colour themes also use !important there).
  New global rule (standartai 8.9): `[hidden]:not(.g-stat .plus){display:none !important}` - exception: Dojo „+“ keeps its place
  (game.css `display:block; visibility:hidden`, D7). The same rule was already in home.css and varzybos.css; Budo and LTKF pages do not load header.css.
  Widths left as they are (moving them = a visible change at those widths, needs a decision): 1099 (D5 tap sizes; at exactly 1100 px not applied),
  860, 900, 1400, 520, 400, 1101-1280. Merging 1099 into 1100 would only change the view at exactly 1100 px.
  Proof - new workflow `.github/workflows/visual.yml` (Actions → „Vaizdo palyginimas“; runs by itself on a push that changes CSS or tests/visual on any branch
  except main; by hand: `ref_base`, `css`). Base = where the branch left main. (1) tests/visual screenshots compared with 0 px tolerance; base taken twice
  (noise column) + largest colour step; (2) `tests/visual/styles.spec.js`: computed styles (~100 properties) of every element matched by any selector of
  the old or new file, 22 widths (both sides of every breakpoint), states: loaded / all menus open / open + dark theme / ✎ menu editor / admin mode off /
  viewing as test parent / bare header; (3) the normal `bash tests/visual.sh`. `checks.sh` passes in every run.
  Run 45625923605: styles 109 240 / 109 250 identical (the 10 = chat widget's place among body children differs between runs, not a style);
  `visual.sh` 55/55 passed; strict pixels 48/55 identical - the rest is noise: Budo and LTKF (no header.css) text anti-aliasing (colour step ≤ 58),
  celebration confetti on the parent page, and 3 files that differ the same way between two runs of unchanged main. Every header.css page was
  pixel-identical in at least one of runs 45623723381 / 45625923605, except paskyra-tevai-1440 (3 px, colour step 1 - same 3 px between two base runs).
  Tool changes: page list in tests/visual/pages.js; visual.spec.js loads lazy images and waits for fonts, masks the LTKF map (`.leaflet-container`).
  Not covered by CI: hover colours (checked by hand: same values, same winner), the font test (selectors with 'Inter' unchanged).
  Run 45628720983 (branch with main v2.10 merged in vs main v2.10): styles 106 277 / 106 282, 0 property differences (5 = chat hint present or not);
  `visual.sh` 55/55; strict 46/55 - all differing files are Budo/LTKF, equal to the base-base noise, or anti-aliasing (colour step ≤ 50) on pages that were
  pixel-identical in runs 2-3. Before/after pictures: side-session notes `c5-header/pries-po-375.png`, `pries-po-1440.png`.
  Full suite (push) times out at 45 min on main too (pre-existing). Header specs by hand (run 45628539751: 07zi-meniu, 07-pages, 06w, 07n, 07zf, 07f,
  07l, 07j): 42 passed, 1 failed - 07zf „side menu ends above 💡“ (955 / 836), which fails the same way on main (run 45620671478, 1027 / 836) - open.
  Next C5 file: app.css (one file per session; the workflow picks the changed .css files by itself).
- 2026-11-25 C6 engine tests + D3 (side session, branch `claude/d3-budo-moduliai-d818a1`, not released, VERSION not bumped):
  `assets/varzybos.js` (3,890 lines) is gone; the same code is in `assets/varzybos/` as ES modules. Page: `asset_module('assets/varzybos/main.js')`
  (app/layout.php) = import map with every module's own `?v=` + `<script type="module">` (admin/varzybu-sistema.php, the only page that loaded it).
  Module map:
  | Module | What is in it |
  |---|---|
  | `main.js` | start: load, unsaved changes from this device (A1), auto categories / quick / simulate, `?tatamis=N` desk, first tab |
  | `util.js` | CFG (window.VARZ), KEY, POPUP, BELTS, YEAR, esc, belts, newId, toast, hhmm / toMin / pad2 |
  | `rules.js` | Budo rules: DISC, SYSTEMS, MEDALS, cfg (group defaults), defaultSystem, methodList / sysName, boutMode, person |
  | `engine/draw.js` | seedOrder, shuffle, seededRnd, drawSeed(c, system, **tid**), makeDraw |
  | `engine/bracket.js` | roundName, play, bracket (byes, bronze bout, WKF repechage, places), allMatches |
  | `engine/rr.js` | rrMatches, parseScore, rrStandings |
  | `engine/score.js` | scoreTotal, fmtScore, scoreRank, scoreFinalists, scoreDone |
  | `engine/category.js` | matchCount, doneCount, prune, drawStale, catPlaces, catFinished, autoLock (#182) |
  | `store.js` | state S, load, save / flush, pull every 8 s, merge on conflict (rebase), pending copy, history restore state, awards state AW + sync |
  | `chrome.js` | tabs and Back, openStartTab, render / renderAll / renderSoon, theme and menu, history panel, share panel, scroll keep |
  | `views/categories.js` `views/clubs.js` `views/people.js` `views/teams.js` | the Administracija tabs (people also: members, link registrations) |
  | `views/draw.js` | group run: bracket, round robin, score tables, simple view, swap by drag, reset |
  | `bout.js` | bout panel, timer and gong, hall screen push, phone video, 📹 Live kova |
  | `schedule.js` `views/live.js` `views/desk.js` | schedule plan and moves; detached and pinned groups, board, run header; my tatami + secretariat desk |
  | `results.js` `views/results.js` `views/draws.js` `test.js` | results for the server and viewers; Rezultatai tab and diplomas; Tinklai; +100 test people and 🎲 |
  Rules for the next change: a module changes only its own variables (others call its setter: `resetSwap`, `setBoutHist`, `setPplClub`, `setDragId`,
  `setRunView`, `setRestored`); the engine and `rules.js` use no browser; no module reads another module's state while loading - `main.js` starts everything.
  Tests: `tests/engine/*.test.mjs` (`node --experimental-vm-modules --test tests/engine/*.test.mjs`, 36 tests + every module parses and links),
  CI step „Budo variklio testai“ before Playwright, also in `tests/smoke.sh`. Proof: the split was made by a script (scope analysis, nothing moved twice);
  old file vs modules in a browser without PHP gave the same DOM, storage and no JS errors in 31 steps at 1440 / 1280 / 375 (tools and report:
  side-session notes `d3-moduliai/`).
  CI on 0aa5502: engine tests ✓; full suite (run 45623818133) 155 passed, 21 failed - the same tests fail on main's full runs with the old file
  (fb44527 / 40aa9db / 2ff6940: 01, 06c, 06h, 06n ×4, 06x ×3, 06y, 07h, 07i, 07o, 07q, 07v, 07zd, 07ze, 07zf, 08); Budo set (run 45623899106) 77 passed,
  11 failed - all in that list. 06z „A1 tab closed while saving“ is flaky on both: 5 repeats - branch 4/5, main 3/5. 07zh and 07zk ✓.
  Questions found (old behaviour, unchanged): round robin places use wins + score difference only, so two with the same wins share 1st place even when
  one beat the other (the table shows the winner above); an elimination group with one participant gets no 1st place and never counts as finished.
- 2026-11-25 v2.11: merged into main by the main session - C5 (header.css), C1 (can() table, db/061), C6+D3 (Budo modules), C3 (links, db/062);
  DB_REQUIRED = 062_nuorodos.sql. Merge fixes: Peržiūra switch kept with can('review'); C3's chrome.js „👤 by“ line moved into
  assets/varzybos/chrome.js (varzybos.js gone); C3 role checks → can(): new action `links` (admin) for „Visos nuorodos“, LINK_KINDS `manage` is now
  a PERMISSIONS action per kind (sekr/awards/live/reg → budo, demo/cam → budo_admin, exam_* → exam_protocols, quiz → quizzes, just → justifications,
  ltkf_* → ltkf - same people as before). Open questions from the side sessions: see „Pending asks“.
## Open
- C5 CSS cleanup: header.css done (v2.11), next app.css (one file per session). D4 same group on two tablets, D5 Budo live polish, D7 arena left hand UI, D8 pixel characters (PC + LibreSprite).
- Parent payments for Budo registration need a payment provider (later).

## Next
- Apply the picked variants (A, B, C). Kumite Technology style (video replay + marking scores on the video) only after a pick: it needs a streaming server or phone-to-browser upload (costs, plan first).
- B8: confirm that Nustatymai is enough as the „Paskyra“ page. Then C4 design tokens (plan first) or D5 Budo live page.

