# Handoff
Updated: 2026-11-25 · version v2.09 · main

## Pending asks (answer these first)
| Asked | By | What | Status |
|---|---|---|---|
| 2026-11-25 | Dev | D1 done - pick the system that stays (D2): `docs/varzybos-palyginimas.md` (proposal: Budo stays, Varžybos removed; code compared, apps not run) | waiting |
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

- 2026-11-25 v2.08: #179 exam protocol delete (admin/egzaminai.php `delete_protocol`, `exam_protocol_people()` in app/exam_protocol.php; with people → `?trinti=ID#trinti`
  confirm box; children of a deleted main protocol: first one becomes main). Test 07zl ✓. Agent: perziura-api.php „working“ = atomic claim (409 taken; stale after 90 min).
  PC scheduled task `karateka-perziuros-eile` (*/15 7-22, Auto mode, no notifications) + cloud routine trig_01KwSq8HaCyBeQzfAB2Wxmvg (disabled until the key is set).
  Both need `KARATEKA_API_KEY` (cloud env „karateka“ + Windows user variable). PC copy works only in the worktree `..\Karateka-agentas` on `claude/taisymai`.
  Plan steps started in their own worktree sessions (Deividas: „tiesiog daryk“): C1 `c1-teises`, C5 `c5-css` (header.css), C6+D3 `d3-budo-moduliai`, D1 `d1-palyginimas`.
  C3 waits for Denis's answers (secretariat link lifetime, one link per competition or tatami, awards tablet names).
- 2026-11-25 v2.09: Deividas used Peržiūra on test (jobs #1, #2 „review“, all good, one comment on the side menu) - done by hand in the main session:
  side menu max-height calc(100vh - 108px) + padding-bottom 64px (widgets.css); admin/perziura.php action `close` („✓“ + reply) for queued/working jobs;
  app/review.json → rev 2.09-1. Test 07zj now writes its own manifest (beforeAll / afterAll restore), so review.json can change freely.
## Open
- C1 permissions, C3 secret links, C5 CSS cleanup (safe now with tests/visual.sh), C6 engine unit tests, D3 split varzybos.js, D4 same group on two tablets, D5 Budo live polish, D7 arena left hand UI, D8 pixel characters (PC + LibreSprite).
- Parent payments for Budo registration need a payment provider (later).

## Next
- Apply the picked variants (A, B, C). Kumite Technology style (video replay + marking scores on the video) only after a pick: it needs a streaming server or phone-to-browser upload (costs, plan first).
- B8: confirm that Nustatymai is enough as the „Paskyra“ page. Then C4 design tokens (plan first) or D5 Budo live page.

