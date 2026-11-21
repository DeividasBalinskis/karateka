# Decisions
One line per decision. Newest on top inside each group. Never delete: mark "replaced by …".
Answers given by Deividas on 2026-11-20 (questions from `docs/darbo-planas.md`, numbers in brackets).

| Date | Area | Decision | Who decided | Source |
|---|---|---|---|---|
| 2026-11-21 | LTKF | Registers (Claude's call, #160): members, clubs and judges are kept by the federation admin in Valdymas → Federacijos registrai; members' personal data are not public (only clubs and judges with category and validity are shown). Can be changed: per-club accounts so club leaders enter their own members. | Claude (asked to decide) | chat |
| 2026-11-21 | Dojo | Left hand (Claude's call, #109): shield = 2 blocks per turn, second weapon = 2 strikes with the second at 75 % damage; „fair“ mode stays 1+1. Can be changed: e.g. second strike 100 % or shield also cutting damage. | Claude (asked to decide) | chat |
| 2026-11-21 | Budo | Club registration (Claude's call, decision 12): each athlete must tick at least one event; the events offered are the individual disciplines that already have groups in that competition (all four if none yet); teams are still made by the organizer. A club leader sees and can cancel what was registered from the same device (no club accounts). Can be changed: club accounts with a login, or events optional. | Claude (asked to decide) | chat |
| 2026-11-21 | E-mail | Templates and outbox (Claude's call, C8): member/parent e-mails in the reader's language from one file; staff e-mails stay Lithuanian; bulk e-mail sent 10 at a time while the „Laiškų siuntimas“ page is open (no cron on the host); queue rows kept 90 days. Can be changed: a cron job if the host offers one, or a different batch size. | Claude (asked to decide) | chat |
| 2026-11-21 | Data | Data care (Claude's call, C10): the „OK“ to delete is the admin's button + confirm on Valdymas → Nustatymai → Duomenų priežiūra, after the list is shown; candidates: former members inactive > 1 y with no attendance, pending > 90 d, disabled accounts > 1 y (not admins), files not named anywhere in the DB. Can be changed: other periods, or automatic deletion once trusted. |
| 2026-11-21 | LTKF | D6 (Claude's call): a repeated text gets its own key by its order on the page („tekstas #2“), old shared edits still apply until a single one is edited; 30 versions per page; news and documents are edited on the LTKF site by its editors (not in Valdymas); state switch lives in Valdymas → LTKF svetainė (admin only), default = draft on test, off on live. Can be changed: more versions, or news in Valdymas too. |
| 2026-11-21 | Accounts | B8 Paskyra page (Claude's call): no separate page - Mano paskyra → Nustatymai already holds profile, children, language, PIN logins, password and logout, and the phone tab bar reaches it in one tap. Can be changed: move Nustatymai to its own address with the same content. |
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
| 2026-11-21 | Launch | E1: Claude collects the old karateka.lt addresses itself (read only) for 301 redirects. | Deividas | questionnaire |
| 2026-11-21 | Dojo | „🏯 Dojo“ in the top menu for real admins only (Denis, Deividas) while the game is hidden from members; testers and members do not see it. (Replaces the menu part of #140.) | Deividas | chat |
| 2026-11-21 | Kids | Kid home (7-10, own login): variant B - level and XP on top, 2 small cards (training, task), „Kas toliau“ strip. | Deividas | chat |
| 2026-11-21 | Kids | A child with a username + PIN login may answer „Dalyvausiu / Negalėsiu“ like teens. | Deividas | chat |
| 2026-11-21 | Notifications | Members and parents: the red number counts only things to do (tasks, unread coach notes and replies, invitations waiting for an answer); news is listed but not counted. | Deividas | chat |
| 2026-11-20 | Workflow | Cloud session saves to `claude/…` branch after every finished stage; `main` only on „į main“; no PRs unless asked. | Deividas | chat |
| 2026-11-20 | Workflow | "Plan first" steps: do them straight away and show the result (screenshots); change later if needed. | Deividas | chat |
| 2026-11-20 | Workflow | Plan decisions accepted: 1a testers without dangerous buttons, 3b Budo vs external Varžybos decided after D1, 6a bottom tab bar for members and kids (variants first), 7b automatic feedback review stays off, C9 CLAUDE.md as proposed. | Deividas | chat |
| 2026-11-20 | Names | Keep „Valdymas“; its start page is „Šiandien“. (Replaces decision 2a's open part, #114.) | Deividas | chat |
| 2026-11-20 | Navigation | After login a coach or admin lands on Valdymas → Šiandien. (40) | Deividas | chat |
| 2026-11-20 | Budo rules | Kata judging is set per competition/category: flags or points, number of judges editable. (1) | Deividas | chat |
| 2026-11-20 | Budo rules | Bronze: configurable (two bronzes without a bout, or a bronze bout). (2) | Deividas | chat |
| 2026-11-20 | Budo rules | Small groups (3–4): system is configurable (round robin, elimination…); everything set up as we want, study Kihapp examples. (3) | Deividas | chat |
| 2026-11-20 | Budo | "Quick competitions" are not a separate thing: creating any competition must be simple enough to be quick. (2) | Deividas | chat |
| 2026-11-20 | Budo | Secretariat link: only results and awards; valid 2 days; a separate link with a name per tatami. (4–6) | Deividas | chat |
| 2026-11-20 | Accounts | Admin elevation from another device: NOT a universal dev code (security). Instead a real admin issues a one-time, time-limited, revocable code per device; every use is logged. (4, Claude's design, asked to "sugalvok") | Deividas + Claude | chat |
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
| 2026-11-20 | Feedback | Old items #109, #30, #147: Claude decides; ask Deividas only if something is truly needed. | Deividas | chat |

Groups: Workflow · Names · Accounts · Navigation · Budo rules · Exams and tests · Dojo · LTKF · Other
(Older decisions are in section 14 of `docs/standartai.md`.)

## Picks from variants
| Date | Question | Variants shown | Picked | Why |
|---|---|---|---|---|
