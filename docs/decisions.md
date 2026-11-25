# Decisions
One line per decision. Newest on top inside each group. Never delete: mark "replaced by …".
Answers given by Deividas on 2026-11-20 (questions from `docs/darbo-planas.md`, numbers in brackets).

| Date | Area | Decision | Who decided | Source |
|---|---|---|---|---|
| 2026-11-25 | Permissions | C1 (developer's call, „tiesiog daryk“): one `can()` table in app/auth.php = the spec table in docs/standartai.md; today's rights kept. Homepage editors = accounts an admin marks in Paskyros („🏠 Redaguoja pradžią“), not names in the instructors block. Role, status, tester, game access, homepage editor and password changes go to a log (1 year). A password change, reset or a child's new PIN signs out other devices. | Deividas | chat |
| 2026-11-25 | Valdymas | Menu groups (Deividas, variant 1): Kasdien (Šiandien, Treniruotė, Egzaminai, Renginiai, Nariai) · Darbai (Patvirtinimai, Atsiliepimai, Peržiūra, Pateisinimai, Pranešimai) · Nustatymai (Aktyvumo taškai, Testai, Federacijos registrai, LTKF, Nustatymai). Real admins arrange it with „✎“: drag items, rename items and groups, add and delete groups, reset; one layout for everyone (site setting `admin_menu`). | Deividas | chat |
| 2026-11-25 | Feedback | Review system (Deividas): Valdymas → 🔎 Peržiūra - mark feedback to fix + comment → „▶ Paleisti taisymą“ (job queue), „⬆ Įkelti į test“; on the test site every changed element (app/review.json) has a gold outline with □ → ✓ → ✗ and a comment, „Pateikti“ at the bottom; bad → next round, all good → main; 🎯 a note on any element. Only real admins (Deividas, Denis) start fixes and mark. Without requests the agent fixes clear feedback by itself. | Deividas | chat |
| 2026-11-25 | Feedback | The fixing agent runs in the cloud (Deividas). Wanted every 15 min; cloud routines allow at most once an hour → every hour 07-23 Vilnius. Its instructions: docs/planai/taisymo-agentas.md; it works on branch `claude/taisymai` and may merge into main after an all-good review. | Deividas | chat |
| 2026-11-25 | Budo | Secretariat desk for one tatami (Deividas): a tatami picked on a device turns Tvarkaraštis into its desk - left menu „📋 Mano tatamis“, „📹 Live kova“, „🏟 Visi tatamiai“ + now / next; all its groups with times, status and delay; a group opens in place with its whole bracket (name = winner); „✎ Keisti“ swaps two competitors by dragging one onto the other; groups reorder by dragging ⠿. „Visi tatamiai“ = the previous board (the tatami stays remembered). Secretariat link per tatami (`&t=N`) lands on that desk. Live kova gets „🏅 Rezultatai“ (the group's bouts next to the video). Replaces the one-column „only my tatami“ board. | Deividas | chat |
| 2026-11-25 | Budo | Secretariat „📹 Live kova“ window per tatami (Deividas): the phone video full screen with the bout, scores [+/−], timer and finish on it; chosen in Tvarkaraštis or from the bout panel („📹 Live“); no bout = next bout of that tatami + „▶ Pradėti kovą“. The buttons press the bout panel's own buttons. | Deividas | chat |
| 2026-11-25 | Budo | Streaming without OBS (Deividas: „jei be OBS veikia - ištrinti viską su OBS“): phones join by one club QR, are assigned to a tatami on the stream page, then „📹 Pradėti filmuoti“ opens the vdo.ninja camera full screen (an iframe on the phone did not stream) with &meshcast, so many viewers can watch. Viewers: „📹 Žiūrėti gyvai“ per tatami (video only after a tap, score bar on top); secretariat: „📹 Vaizdas“ with ± on the video; stream page: a card per tatami with ± next to the scores (sample bout). All OBS parts removed (bar help, OBS links, „Visi tatamiai“, permanent bar key). Stream ids are hashes, so the viewers' page does not reveal the QR key. | Deividas | chat |
| 2026-11-25 | Budo | #184: Budo header = soft blue-grey (variant C) always, in every theme. | Deividas | feedback #184 |
| 2026-11-25 | Budo | #182: a category locks itself when its last result is entered; „🔒 Užrakinta“ unlocks it to fix. | Denis | feedback #182 |
| 2026-11-25 | Budo | Streaming = phones only where possible (Deividas): one club QR for all phones (varzybos-kamera.php), each phone shows up on the stream page and is assigned to a tatami (one phone per tatami, stream_phones db/059); the assigned phone streams as that tatami, so the secretariat, preview and OBS see it. Links and QR are permanent (varz_cam_key, varz_bar_key). OBS needs one link: one tatami (video + bar) or „Visi tatamiai“ (2×2, one YouTube stream). Several tatamis on YouTube: all in one stream (recommended, 1 computer) or one computer with OBS per tatami. Test bout ± for staff and secretariat only, never during a real bout, hidden from viewers. | Deividas | chat · replaced by 2026-11-25 „Streaming without OBS“ |
| 2026-11-25 | Budo | Streaming with a phone (developer's design for Deividas's ask): phone camera via vdo.ninja, one secret stream per tatami (`kt<key>t<tatami>`, key from the viewer + awards tokens), QR code on a stream page (admin/varzybu-transliacija.php: preview = phone video + bar, OBS links, YouTube steps, when what works); the secretariat sees the phone video in the bout panel („📹 Vaizdas“). Works any time; test = sample bout or a test competition. QR library from cdnjs (qrcodejs 1.0.0). | Deividas | chat · replaced by 2026-11-25 „Streaming without OBS“ |
| 2026-11-25 | Budo | OBS bar works without a bout: no bout = competition and tatami name (connect the phone and check the picture in advance); „Pavyzdys“ = sample bout; opened in a normal browser = how-to (OBS Studio, phone via vdo.ninja, this bar, YouTube) on a chequered background; in OBS (window.obsstudio) only the transparent bar. Secretariat sees the same block. | Deividas | chat (Denis asked) · replaced by 2026-11-25 „Streaming without OBS“ |
| 2026-11-25 | Dojo | Levels: level n→n+1 costs 5 + 2 × floor((n-1)/5) XP (5, 7, 9, 11 …; admin can change 5 and 2 in Treneriams → Taškai). Point values stay as the coach set them. Gift box for every level up to 60 and every event marked „Buvo“; game wins give at most 1 box a week (3 wins in Mon-Sun). Level 3, 8 and 15 boxes always hold an egg. A new character every 5 levels (Deividas: random, as a gift with an animation): a separate gift box „ch:5“, „ch:10“ … holds a random character not yet owned; at the start only Kitsunė (recolourable) - developer's pick, one constant `GAME_CHAR_START`. Replaces „one level = 100 XP“ and „3 wins a day = box“. Details: docs/dojo-progresija.md. | Deividas | chat |
| 2026-11-24 | Design | Fonts are tested per device by admins („Aa“); themes and fonts get 👍 from admins; the site default changes only after a pick. | Deividas | chat |
| 2026-11-24 | Excuses | Excuses: admin can edit a request and its text, or upload the final file as Word or PDF (sent instead of the prepared one); new-request e-mail and to-do go only to chosen admins, default Denis. Replaces „e-mail to every active admin“. | Deividas | chat |
| 2026-11-24 | Layout | 💡 is bottom-left on every page (Valdymas too); right side = 💬 and a small 🔔 bell that opens a list (no side cards). | Deividas | feedback #172, #176 |
| 2026-11-24 | Events | Extra protocols of one exam day are parts of the main exam (events.parent_id): lists show only the main exam; protocols stay in Valdymas → Egzaminai. | Denis | feedback #174 |
| 2026-11-24 | Git | No AI tool names in the repository or commits: neutral wording, commits under Deividas's GitHub identity. | Deividas | chat |
| 2026-11-24 | Excuses | Excuse letters (developer's calls): one short link /p/?code; period = from-to dates; template = uploaded Word .docx with {tags} (or text until uploaded), sent as a .docx attachment (PDF would need a converter on the server); admins only; e-mail to every active admin. | Deividas | chat |
| 2026-11-24 | Data | Pending db files run by themselves when an admin opens any page (backup first, one at a time, retry after 1 h on failure). | Deividas | chat |
| 2026-11-22 | Exams | Exam registration links are short: /e/?code, 6 characters without look-alikes (developer's call: query form works on any server without rewrite rules; with Apache rewrite it could be /e/code). Old long links keep working until „↻ Trumpa nuoroda“. | Deividas | chat |
| 2026-11-22 | Exams | Exam registration links (waiting list and per exam), the guest exam page, guest test and the privacy page opened from them show no site menu or links („bare“ layout) - the link allows only registering. | Deividas | chat |
| 2026-11-22 | Workflow | Questions first: each reply starts with a numbered „❓ Klausimai“ block; in 💡 feedback a question = status „Reikia atsakymo“ (highlighted, answer field, ❓N on the button, top of „Reikia padaryti“). | Deividas | chat |
| 2026-11-22 | Exams | #165: one waiting list for all exams; an exam (date) has separate protocols per belt group; the e-mail price follows the belt; for now everyone (club members too) goes through the waiting list. | Denis | feedback |
| 2026-11-22 | Exams | A new protocol row starts with 6.4 in Kihon, Kata and Kumite. (#166) | Denis | feedback |
| 2026-11-22 | Feedback | A fixed item gets „👍 Patinka“ / „↩ Grąžinti kaip buvo“ for its author (any device of the account); „Grąžinti“ = that fix is reverted (on Deividas's ask). | Deividas | chat |
| 2026-11-22 | Budo | Long help texts out of the screen (tooltip on the title instead); category name opens control; „⌄“ next to BUDO for Karateka / LTKF. (#169-#171) | Denis | feedback |
| 2026-11-22 | Budo | Phone streaming: OBS on a computer + phone camera (e.g. VDO.Ninja) + our transparent score bar per tatami (?juosta=1&tatami=N). Kept: YouTube link per tatami for viewers. | Deividas | chat · replaced by 2026-11-25 „Streaming without OBS“ |
| 2026-11-22 | Feedback | The 💡 box is for writing: text, photos, save and „Jūsų atsiliepimai“ only. Staff get „📋 Visi atsiliepimai“ - a separate view with dropdowns (place, status) and search. Many buttons → dropdowns (also on Valdymas → Atsiliepimai). Replaces the v1.87 chip rows. | Deividas | chat |
| 2026-11-22 | Feedback | Feedback is grouped by the place it was left: Budo / Karateka / LTKF + sections (Budo sections = app tabs, Karateka admin = Valdymas menu items); the current place first. | Deividas | chat |
| 2026-11-22 | Feedback | The 💡 box keeps open state, text and filters across page changes in the same browser tab (3 h); on a phone it does not reopen by itself, ✎ on the button instead (developer's call). Can be changed: reopen on phones too. | Deividas | chat |
| 2026-11-22 | Feedback | Place is computed from the page (no new database column), so old items are grouped too (developer's call). | Developer | chat |
| 2026-11-22 | Tests | A quiz created with a question file is live at once (developer's call, Denis's 244 questions); review first only when „Pirmiau peržiūrėti“ is ticked. Can be changed: review by default again. | Developer | chat |
| 2026-11-22 | Layout | Tablet (touch screen 600-1100 px) shows the computer layout at 1280 px width, scaled; phone keeps its own; exam protocol keeps native size (developer's call on Deividas's ask). Can be changed: other width or native tablet layout. | Developer | chat |
| 2026-11-22 | Phone | The tab bar (Šiandien, Treniruotė…) sits at the top under the logo; the scroll progress bar is removed. | Deividas | chat |
| 2026-11-22 | Budo | Tatami video = YouTube Live link per tatami, shown to viewers with the live bout over it (developer's call). Can be changed: own streaming server (costs), or video also on the secretariat tablet (variant C2). | Developer | chat |
| 2026-11-22 | LTKF | Judges' attendance and diplomas (developer's call, Denis's ask): attendance = date + type (seminar / competition / exam / other) + title; diplomas are files seen by staff only (not public). | Developer | chat |
| 2026-11-21 | LTKF | Registers (developer's call, #160): members, clubs and judges are kept by the federation admin in Valdymas → Federacijos registrai; members' personal data are not public (only clubs and judges with category and validity are shown). Can be changed: per-club accounts so club leaders enter their own members. | Developer (asked to decide) | chat |
| 2026-11-21 | Dojo | Left hand (developer's call, #109): shield = 2 blocks per turn, second weapon = 2 strikes with the second at 75 % damage; „fair“ mode stays 1+1. Can be changed: e.g. second strike 100 % or shield also cutting damage. | Developer (asked to decide) | chat |
| 2026-11-21 | Budo | Club registration (developer's call, decision 12): each athlete must tick at least one event; the events offered are the individual disciplines that already have groups in that competition (all four if none yet); teams are still made by the organizer. A club leader sees and can cancel what was registered from the same device (no club accounts). Can be changed: club accounts with a login, or events optional. | Developer (asked to decide) | chat |
| 2026-11-21 | E-mail | Templates and outbox (developer's call, C8): member/parent e-mails in the reader's language from one file; staff e-mails stay Lithuanian; bulk e-mail sent 10 at a time while the „Laiškų siuntimas“ page is open (no cron on the host); queue rows kept 90 days. Can be changed: a cron job if the host offers one, or a different batch size. | Developer (asked to decide) | chat |
| 2026-11-21 | Data | Data care (developer's call, C10): the „OK“ to delete is the admin's button + confirm on Valdymas → Nustatymai → Duomenų priežiūra, after the list is shown; candidates: former members inactive > 1 y with no attendance, pending > 90 d, disabled accounts > 1 y (not admins), files not named anywhere in the DB. Can be changed: other periods, or automatic deletion once trusted. |
| 2026-11-21 | LTKF | D6 (developer's call): a repeated text gets its own key by its order on the page („tekstas #2“), old shared edits still apply until a single one is edited; 30 versions per page; news and documents are edited on the LTKF site by its editors (not in Valdymas); state switch lives in Valdymas → LTKF svetainė (admin only), default = draft on test, off on live. Can be changed: more versions, or news in Valdymas too. |
| 2026-11-21 | Names | Account and its „self“ member are one person; when they differ (left from before v1.73), the account name wins and the member and Dojo hero take it (developer's call). Can be changed: member name wins instead. |
| 2026-11-21 | Phone | 💡 feedback and 💬 messages are separate floating buttons on a phone too (replaces the B4 combined button). | Deividas | chat |
| 2026-11-21 | Accounts | B8 Paskyra page (developer's call): no separate page - Mano paskyra → Nustatymai already holds profile, children, language, PIN logins, password and logout, and the phone tab bar reaches it in one tap. Can be changed: move Nustatymai to its own address with the same content. |
| 2026-11-21 | Workflow | „į main“ given: `main` = v1.72 (A6). | Deividas | questionnaire |
| 2026-11-21 | Valdymas | Remove the menu test variants: desktop side menu + phone bottom bar only (B9). | Deividas | questionnaire |
| 2026-11-21 | Valdymas | Add „Renginiai“ to the Valdymas side menu; event editing opens inside Valdymas (B9). | Deividas | questionnaire |
| 2026-11-21 | Permissions | Keep the permission table in `docs/standartai.md` as it is (C1). | Deividas | questionnaire |
| 2026-11-21 | Settings | Save the chosen language (LT/EN/RU) on the account; e-mails and notifications use it (C2). | Deividas | questionnaire |
| 2026-11-21 | Links | One table for secret links + „Visos nuorodos“ page; old links keep working (C3, new table allowed). | Deividas | questionnaire |
| 2026-11-21 | E-mail | No automatic e-mail on a coach reply; the coach may tick „Siųsti ir el. paštu“ when needed (C8). | Deividas | questionnaire |
| 2026-11-21 | E-mail | Bulk e-mail through an outbox table, sent in batches, result per person (C8, new table allowed). | Deividas | questionnaire |
| 2026-11-21 | Data | Start data care: show the delete list first, delete only after OK (C10). | Deividas | questionnaire |
| 2026-11-21 | Data | Backups also include uploaded photos and files (C10). | Deividas | questionnaire |
| 2026-11-21 | Budo | D1: the external Varžybos code goes into the same repo for now (branch `varzybos-kodas`, folder `_varzybos`, no config/.env/.sql); may be split later. (Replaces „private repo“.) | Deividas | chat |
| 2026-11-21 | LTKF | ltkf.lt = an extra domain on the karateka hosting (D6). | Deividas | questionnaire |
| 2026-11-21 | LTKF | D6 order: 1 text versions + restore → 2 lists (clubs, news, documents) → 3 off/draft/public; 4 domain move later. | Deividas | questionnaire |
| 2026-11-21 | LTKF | Real LTKF data (board, details, clubs, documents, contacts) is entered by the federation editors themselves. | Deividas | questionnaire |
| 2026-11-21 | Game | D8 pixel characters: Deividas installs LibreSprite (+ libresprite-mcp); work happens in a PC session after D7. | Deividas | questionnaire |
| 2026-11-21 | Launch | E1: not yet - prepare the checklist and wait for „GO“. | Deividas | questionnaire |
| 2026-11-21 | Launch | E1: the developer collects the old karateka.lt addresses (read only) for 301 redirects. | Deividas | questionnaire |
| 2026-11-21 | Dojo | „🏯 Dojo“ in the top menu for real admins only (Denis, Deividas) while the game is hidden from members; testers and members do not see it. (Replaces the menu part of #140.) | Deividas | chat |
| 2026-11-21 | Kids | Kid home (7-10, own login): variant B - level and XP on top, 2 small cards (training, task), „Kas toliau“ strip. | Deividas | chat |
| 2026-11-21 | Kids | A child with a username + PIN login may answer „Dalyvausiu / Negalėsiu“ like teens. | Deividas | chat |
| 2026-11-21 | Notifications | Members and parents: the red number counts only things to do (tasks, unread coach notes and replies, invitations waiting for an answer); news is listed but not counted. | Deividas | chat |
| 2026-11-20 | Workflow | Cloud session saves to its work branch after every finished stage; `main` only on „į main“; no PRs unless asked. | Deividas | chat |
| 2026-11-20 | Workflow | "Plan first" steps: do them straight away and show the result (screenshots); change later if needed. | Deividas | chat |
| 2026-11-20 | Workflow | Plan decisions accepted: 1a testers without dangerous buttons, 3b Budo vs external Varžybos decided after D1, 6a bottom tab bar for members and kids (variants first), 7b automatic feedback review stays off, C9 rules file as proposed. | Deividas | chat |
| 2026-11-20 | Names | Keep „Valdymas“; its start page is „Šiandien“. (Replaces decision 2a's open part, #114.) | Deividas | chat |
| 2026-11-20 | Navigation | After login a coach or admin lands on Valdymas → Šiandien. (40) | Deividas | chat |
| 2026-11-20 | Budo rules | Kata judging is set per competition/category: flags or points, number of judges editable. (1) | Deividas | chat |
| 2026-11-20 | Budo rules | Bronze: configurable (two bronzes without a bout, or a bronze bout). (2) | Deividas | chat |
| 2026-11-20 | Budo rules | Small groups (3–4): system is configurable (round robin, elimination…); everything set up as we want, study Kihapp examples. (3) | Deividas | chat |
| 2026-11-20 | Budo | "Quick competitions" are not a separate thing: creating any competition must be simple enough to be quick. (2) | Deividas | chat |
| 2026-11-20 | Budo | Secretariat link: only results and awards; valid 2 days; a separate link with a name per tatami. (4–6) | Deividas | chat |
| 2026-11-20 | Accounts | Admin elevation from another device: NOT a universal dev code (security). Instead a real admin issues a one-time, time-limited, revocable code per device; every use is logged. (4, developer's design, asked to "sugalvok") | Deividas | chat |
| 2026-11-20 | Budo | Awards tablet shows full first and last names. (7) | Deividas | chat |
| 2026-11-20 | Budo | Parents can find a child in public results by surname ("Rask save"); results still show "Jonas P.". (8) | Deividas | chat |
| 2026-11-20 | Budo | Theme: admin sets the default; logged-in secretariat devices may choose their own, remembered on that device. (9) | Deividas | chat |
| 2026-11-20 | Budo | Saving must never lose or mix results between tablets on reload (multi-tablet safe saving, step D4). (9) | Deividas | chat |
| 2026-11-20 | Budo | 10 kyu in the Budo belt list. (11) | Deividas | chat |
| 2026-11-20 | Budo | Registration: participants say where they compete; a club leader can open their club, add their kids and choose events. Payments by parents should become possible (later, needs a provider). (12) | Deividas | chat |
| 2026-11-20 | Notifications | The red number counts only things to do (approve, answer), not news. (13) | Deividas | chat |
| 2026-11-20 | Notifications | "Moku" requests, late and other-club registrations go to the admin only. (14) | Deividas | chat |
| 2026-11-20 | Data | Old notifications, messages and the SMS log are kept 1 year, then deleted. (15) | Deividas | chat |
| 2026-11-20 | Notifications | Only the admin sends SMS. (16) | Deividas | chat |
| 2026-11-20 | Exams | 9 kyu and 10 kyu are exam grades; every grade has a fee, the price depends on the belt. (17–18) | Deividas | chat |
| 2026-11-20 | Exams | The red rule (6.3 and lower) does NOT apply to the "Testas" column. (19) | Deividas | chat |
| 2026-11-20 | Exams | Personal exam page: no phone number, birth year only; the link works 30 days after the exam. (20–21) | Deividas | chat |
| 2026-11-20 | Exams | The theory test can be taken any number of times. (22) | Deividas | chat |
| 2026-11-20 | Exams | "Dalyviai" in the exam list = registered people. (23) | Deividas | chat |
| 2026-11-20 | Accounts | A child may have own login from age 7: username + PIN created by parents; no coach approval needed. (24–26) | Deividas | chat |
| 2026-11-20 | Kids | Words for 7–10 year olds: „Mano diena“, „XP“, „Geriausi“ (instead of „Apžvalga“, „Aktyvumo taškai“, „Top 5“). (27) | Deividas | chat |
| 2026-11-20 | Kids | A child does not see fees and prices; parents do. (28) | Deividas | chat |
| 2026-11-20 | Accounts | One common photo consent (Taip / Ne). (29) | Deividas | chat |
| 2026-11-20 | LTKF | Official name: „Lietuvos tradicinio karate federacija“. (30) | Deividas | chat |
| 2026-11-20 | LTKF | Calendar shows only competitions approved by the federation. (31) | Deividas | chat |
| 2026-11-20 | LTKF | Judges' names and test validity may be public. (32) | Deividas | chat |
| 2026-11-20 | LTKF | Federation editors may change everything (name, contacts, menu, content). (33) | Deividas | chat |
| 2026-11-20 | Dojo | Ranking counts only wins against people (no bots), per season. (35) | Deividas | chat |
| 2026-11-20 | Dojo | Other kids see first name + surname initial („Jonas P.“). (36) | Deividas | chat |
| 2026-11-20 | Dojo | Gift box for an event only when attendance is marked „Buvo“. (37) | Deividas | chat |
| 2026-11-20 | Dojo | Left hand in the arena: a shield or a second weapon. (38) | Deividas | chat |
| 2026-11-20 | Points | Keep the quick award categories as they are (+ „Kita (su komentaru)“). (41) | Deividas | chat |
| 2026-11-20 | Tests | WTKU judges' test: one question set for everyone. (43) | Deividas | chat |
| 2026-11-20 | Feedback | Old items #109, #30, #147: the developer decides; ask Deividas only if something is truly needed. | Deividas | chat |

Groups: Workflow · Names · Accounts · Navigation · Budo rules · Exams and tests · Dojo · LTKF · Other
(Older decisions are in section 14 of `docs/standartai.md`.)

## Picks from variants
| Date | Question | Variants shown | Picked | Why |
|---|---|---|---|---|
| 2026-11-22 | Menu on a computer / phone menu / Budo video | A1 now, A2 clean bar, A3 side menu always · B1 now, B2 one row, B3 tiles · C1 viewers (done), C2 secretariat tablet, C3 hall TV | waiting | screenshots in chat |
