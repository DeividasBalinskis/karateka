# System review (sistemos apžvalga)

## Deniui — 10 eilučių

1. Peržiūrėjau visą projektą: klubo svetainę, Budo, LTKF ir Dojo žaidimą. Nieko nekeičiau, tik surašiau planą.
2. **Svarbiausia:** Budo kartais „pamesdavo“ įvestą rezultatą po puslapio perkrovimo, nors rodė „Išsaugota“. **Pataisyta v1.48.** Senų varžybų dingę rezultatai patys neatsiras — svarbias peržiūrėk.
3. Greitos varžybos 4 sportininkams dabar užima apie 30 paspaudimų telefone. Planas — viena kortelė „⚡ Greitos varžybos“: vardai → rungtys → pradėti.
4. Telefone Budo kovos langas per žemai, nesimato „kas ruošiasi“, o „+ Tatamis“ atsidaro už ekrano krašto. Tai sutvarkoma kartu.
5. Tavo naujausi atsiliepimai #126–#137 visi įtraukti į planą. #30 ir #132 atėjo tušti — parašyk juos dar kartą.
6. Raudonas skaičius prie „Valdymas“ rodys tik tai, ką reikia padaryti. Naujienos jo nebedidins.
7. Testuotojai gaus ženkliuką „Testuotojas“ ir nebematys Dojo. Pavojingi mygtukai (rolės, kopijos, žaidimo jungiklis) liks tik tau ir Deividui.
8. Tėvai galės vienu mygtuku sukurti vaikui prisijungimą. Vaiko pirmas puslapis bus trumpas ir paprastas.
9. LTKF svetainėje dar nėra tikro turinio, o redaktorius negali atskirai įrašyti penkių valdybos narių. Tai atskiras darbas.
10. Tau paruošti 43 trumpi klausimai (taisyklės, egzaminai, pranešimai). Taisyklių pats nespėlioju — kur reikia tavo atsakymo, darbas jo laukia.

---

## 5-line summary

1. The product is wide and mostly works; the weak points are **data safety in Budo**, **who is allowed to do what**, and **phone screens**.
2. One critical bug: Budo results can be lost after a reload (verified in code and reproduced three times on screen).
3. Every back system (permissions, settings, secret links, notifications, e-mail, CSS, tests) exists, but each grew its own way. `docs/standartai.md` gives each one a single rule set.
4. The way of working needs three fixes: `main` is 92 commits behind, there is no `CLAUDE.md` in the repo, and asks get lost between sessions.
5. Every finding below points to a step in `docs/darbo-planas.md`.

**Already fixed in v1.48 (right after this review):** B-1, B-2, B-9, R-2, R-3, R-4, R-5, F-1, F-10, N-10, M-8 (wording), S-7, D-7 and the footer spelling. **In v1.49:** R-1 (testers), B-8, B-10, N-4, N-5, N-15, E-1, E-2, G-4, part of B-6 (participants and quick groups). The rows stay in the tables below as a record; `docs/darbo-planas.md` shows what is still open.

**How this was done.** Eleven areas were read by separate readers, each claim with `file:line` and a quoted line that a script then checked against the code: 1,538 references, none wrong. Two areas (Budo, Valdymas) were also walked in a browser at 375, 768, 1280/1440, 1920 and 2560 px, with about 200 screenshots. I re-checked the most serious claims myself in the code.

**What was not covered in full.** The usage limit stopped seven readers before they reported: feedback loop, exams, design/CSS, data, infrastructure, and the browser walks for members/kids and for LTKF/game. For these I did a lighter pass myself: counted and measured with commands, and looked at the screenshots those walks had already saved. Findings from that lighter pass are marked **(light)**. Anything I could not verify is marked **unclear**.

Paths are relative to `public_html/` unless they start with `db/`, `tools/`, `tests/` or `docs/`. Size: S = one short session, M = one session, L = several.

---

## Top 15 (ranked)

| # | Finding | Who is hurt | Step |
|---|---|---|---|
| 1 | Budo results can vanish after a reload while the screen says "Išsaugota ✓" | Denis, secretariat, athletes | A1 |
| 2 | A draw shown on screen is not saved; another tablet or a reload shows different pairs | secretariat, spectators | A1 |
| 3 | Every tester is a full admin: can demote Denis, open the game, restore backups | Denis, Deividas | A4 |
| 4 | The exam sign-up link gives a stranger a child's page with birth date and phone | kids, parents | A3 |
| 5 | A secretariat link reaches coach actions on all competitions | Denis | A2 |
| 6 | `main` is 92 commits behind; the PC update script would install v1.18 | Deividas | A6 |
| 7 | A quick 4-athlete competition takes 23 clicks (PC) or 32 taps (phone) | Denis, coaches | B2 |
| 8 | Phone admin has no "Žiūrėti kaip"; three floating buttons cover content | Denis, coaches | B4 |
| 9 | Budo phone: bout below the fold, no "who is next", "+ Tatamis" opens off-screen | Denis, judges | B3 |
| 10 | The red badge mixes "to do" with news; real requests can be pushed off the list | Denis | B5 |
| 11 | Parents cannot train themselves or create a child login in one step | parents, kids | B7 |
| 12 | 22 kinds of secret links, built six ways; most never expire, cannot be replaced | everyone who gets a link | C3 |
| 13 | Two tablets cannot work at once: the second save is refused and the action is lost | secretariat | D4 |
| 14 | Settings live in six places and ten screens; any admin can flip site-wide switches, no log | Denis | C2 |
| 15 | LTKF cannot be filled in by its editors and cannot run on its own domain | federation | D6 |

---

# Part 1 — People first

## Role × place × device

🟢 works well · 🟡 works with friction · 🔴 broken or blocked · ○ not checked in a browser in this review

| Role | Place | 375 phone | 768–1280 tablet | 1440 | 2560 | Main reason |
|---|---|---|---|---|---|---|
| Guest | Home page | 🟢 (light) | ○ | ○ | ○ | CTA says "2 treniruotės nemokamai"; the agreed word was "Išbandyti" |
| Guest | Trial form | 🟡 | ○ | ○ | ○ | works, but no spam protection (A5) |
| Parent | Paskyra (home) | 🟡 (light) | ○ | 🟢 (light) | 🟢 (light) | long page; tab row cut off on the phone; chat button over content |
| Parent | Add child / child login | 🔴 | 🔴 | 🔴 | 🔴 | no one-button child login; some accounts cannot add a child at all |
| Young child | Home after login | 🔴 (light) | ○ | 🟡 (light) | ○ | same adult page, 6,477 px tall on a phone, "Atsijungti" by the greeting |
| Older kid / member | Home, Užduotys, Egzaminas | 🟡 (light) | ○ | 🟢 (light) | ○ | works; burger menu only; small tabs (31 px) |
| Coach | Valdymas | 🟡 | 🟡 | 🟢 | 🟡 | same as admin; sees all club to-dos; could see Dojo when the setting is missing |
| Testuotojas | everything | 🔴 | 🔴 | 🔴 | 🔴 | the role does not exist; testers are full admins |
| Admin | Valdymas start page | 🟡 | 🟡 | 🟢 | 🟡 | floats overlap (phone); grid misaligned at 2560 (#129) |
| Admin | Header "Apie klubą" fold | – | – | 🔴 | 🔴 | covers the logo switch, LT and theme button (#131) |
| Admin | Phone menu "⋯ Daugiau" | 🔴 | 🟡 | – | – | no view-as; floats hide the last rows |
| Admin | Treniruotė (attendance) | 🟡 | ○ | 🟡 | ○ | fast; stray points form at the bottom; tiny points controls |
| Admin | Patvirtinimai | 🟡 | ○ | 🟢 | ○ | 💡 half covers "Patvirtinti" on a phone |
| Admin | Paskyros ir rolės | 🔴 | ○ | 🟡 | ○ | role changes on touch, new account is admin by default |
| Admin | Nustatymai | 🟢 | ○ | 🟢 | ○ | long but labelled |
| Admin | New event | ○ | ○ | 🟡 | ○ | opens outside Valdymas; default type "Egzaminas", everyone invited |
| Admin | Aktyvumo taškai | 🔴 | ○ | 🟢 | ○ | page scrolls sideways by 10 px |
| Examiner | Exam protocol | ○ | 🟡 (1280) | 🟡 | ○ | saves well; buttons 22–26 px; #133 and #136 open |
| Admin | Budo home, new competition | 🟡 | ○ | 🟢 | ○ | date not pre-filled; no "created" message |
| Admin | Budo Kategorijos | 🔴 | 🟡 | 🟡 | ○ | tab off-screen; drag wording on a phone; "+" on both open ends (#135) |
| Admin | Budo Tvarkaraštis | 🟡 | 🟢 | 🟡 | 🟢 | names wrap one word per line at 1440 |
| Admin | Budo "Tatamiai ir kovų trukmė" | 🔴 | 🔴 | 🔴 | 🟢 | opens off-screen; "+ Tatamis" unreachable |
| Admin / judge | Budo run view | 🔴 | 🔴 | 🟡 | ○ | lost result after reload; on a phone the bout is below the fold |
| Admin / judge | Budo bout panel | 🟡 | 🟢 | 🟢 | ○ | big buttons; no "who is next"; 💡 over "+2"; stopwatch jumps to 0:01 |
| Secretariat | Join by link | 🟢 | ○ | ○ | ○ | one field, clear |
| Secretariat | Working view | 🟡 | ○ | 🟡 | ○ | works; can restore the whole competition and change hall settings |
| Awards person | Awards tablet | 🟢 | 🟢 | ○ | ○ | best screen in Budo |
| Spectator | Live page, "Rask save" | 🟡 | ○ | 🟢 | ○ | a child cannot be found by surname |
| Spectator | TV screen with tatami | – | – | 🟢 (1920) | ○ | excellent |
| Spectator | TV screen without tatami | – | – | 🔴 (1920) | ○ | empty "TATAMIS" page |
| Other club | Registration link | 🟡 | ○ | ○ | ○ | works; shown inside the karateka site with a club ad |
| LTKF editor | Edit pages | ○ | ○ | 🔴 (code) | ○ | five identical placeholders share one text; cannot add list items |
| LTKF visitor | Read in EN / RU | ○ | ○ | 🔴 | ○ | title and footer stay Lithuanian after the "karate" fix |
| Denis | Dojo, arena | 🟡 (light) | ○ | 🟡 (light) | ○ | works; the test arena and the real arena follow different rules |

## Top journeys per role

| Role | Journey | Today | Verdict | Step |
|---|---|---|---|---|
| Denis | Attendance → note → points on a phone | 9 taps | works; points step is unstyled and says "XP" | B1 |
| Denis | Approve a waiting family | 6 taps | clear | B4 (overlap) |
| Denis | Competition for 4 athletes, now | 23 clicks / 32 taps, 6 screens | most likely give-up point: categories | B2 |
| Denis | Score a bout on a phone | 12 taps | good buttons; fold, overlap, lost result | A1, B3 |
| Denis | Create an event and invite one group | 10 clicks | wrong defaults, two layouts | B6, B9 |
| Denis | Make someone an admin | 2–3 taps | dangerously easy | A2, A4 |
| Denis | See what changed and replies to his feedback | no "what is new" page; replies tied to one browser | weak | C9 |
| Denis | Switch off a leaked spectator or awards link | impossible | dead end | C3 |
| Denis | Give an LTKF editor a link | 5 clicks, hidden behind "Peržiūros nuoroda" | hard to find | B9 |
| Coach | Daily work | same as admin minus 3 pages | consistent | C1 |
| Parent | Read the coach's reply and answer | e-mail link lands at the top; with 2 children the chat opens on the wrong child | works with friction | B5, C8 |
| Parent | Get back a lost exam page | 2 steps — and the same for any stranger | unsafe | A3 |
| Parent | Create the child's own login | needs a separate e-mail and a second approval | not "one button" | B7 |
| Kid | Open the game promised in the invite e-mail | no menu item, silent redirect | confusing | B1, D7 |
| Secretariat | Join and work, then finish | 2 actions to start; no way to end the session | good start, no end | C3 |
| Spectator | Find my child | first name works, surname does not | partial | B3 |
| Other club | Register athletes | 14 taps; errors only at the top | works | B3 |
| LTKF editor | Fill in board names | all five cards change together | blocked | D6 |

## Map of the sites — today

```
karateka.lt
├─ Public: Treniruotės · Apie klubą · Kontaktai · Naujienos ir renginiai · Pamokos · (Dojo)
├─ Member area "Mano paskyra": Apžvalga · Užduotys · Egzaminas · Nustatymai · (Tėvams)
│    one long page for parent, teen and small child alike
├─ Valdymas (staff)
│    Kasdien: Pradžia* · Treniruotė · Egzaminai · Nariai
│    Klubas: Aktyvumo taškai · Testai · Patvirtinimai
│    Tvarkyti: Atsiliepimai · Pranešimai · Nustatymai → 7 more pages (3 levels deep)
│    not in the menu: Renginiai, Naujienos, Pamokos (open in the public layout), Žinutės, LTKF
│    * called "Pradžia" in the menu, "Šiandien" in the title
├─ Logo ▾ → Budo · LTKF (staff only)
└─ Dojo (game): Dojo · Arena · Keturi iš eilės · Kata ritmas · test arena

Budo: Varžybos list → one competition: Administracija (Klubai · Komandos · Dalyviai · Kategorijos)
      · Tvarkaraštis · Rezultatai; links: secretariat, spectators, TV, awards, registration, trial
LTKF: 15 pages, visible only on the test site; editor by link
```

## Map of the sites — target

```
Top-right corner, the same idea on every width
├─ Guest:               [Išbandyti] [Prisijungti]
├─ Member/parent/kid:   [Paskyra •badge]            [🏯 Dojo] ← far corner, only if allowed
└─ Staff:               [Paskyra] [Valdymas ▾ •badge]  (▾: Žiūrėti kaip; Dojo inside Valdymas)

Kid home "Šiandien": 3 big cards (next training/event · one task · Dojo). Rest one tap away.
Parent home: child chips on top, then the same 3 cards per child, then details.
Paskyra page: profile · children · settings · logout.

Valdymas — at most 2 levels, every staff page inside it
  Kasdien: start page (one name) · Treniruotė · Renginiai · Egzaminai · Nariai
  Klubas:  Aktyvumo taškai · Testai · Patvirtinimai · Pamokos, Naujienos
  Tvarkyti: Atsiliepimai · Pranešimai · Nustatymai (with "Visi nustatymai" and "Visos nuorodos") · LTKF
Phone/tablet ≤ 1100 px: bottom bar with 4 tabs + "⋯ Daugiau" (first row: Žiūrėti kaip)

Budo home: [⚡ Greitos varžybos] [+ Naujos varžybos] · list
```

The target changes menus Denis agreed on only where he asked (#114, #131) or where a page is missing from the menu. Everything else needs his OK first (step B9).

---

# Part 2 — Findings by level

Each row: what is wrong · evidence · who is hurt · fix · size · risk · step.

## 2.1 Budo: data and competition day

| # | Finding | Evidence | Who | Fix | Size | Risk | Step |
|---|---|---|---|---|---|---|---|
| B-1 | Empty result maps `{}` come back from the server as `[]`; results written after a reload are dropped on save | `admin/varzybu-sistema.php:170`, `app/tournaments.php:310`, `assets/varzybos.js:66`, `:176`, `:200`, `:1323` | all | normalise on load and on the server; reload test; check existing competitions | S | low | A1 |
| B-2 | A draw made while rendering is not saved | `assets/varzybos.js:1322` | secretariat, spectators | save when a draw is created | S | low (save only when new) | A1 |
| B-3 | A change made while a save is running is lost when the tab closes | `assets/varzybos.js:100`, `:135` | secretariat | send the latest state on close | S | low | A1 |
| B-4 | Nothing fetches other devices' changes: the next save from another tablet is refused and the action is lost | `app/tournaments.php:306`, `admin/varzybu-sistema.php:180` | secretariat | step D4 options | L | high | D4 |
| B-5 | Whole competition is saved on every point; the closing save fails above 64 KB | `assets/varzybos.js:99` | secretariat | save per category | L | medium | D4 |
| B-6 | Quick competition: about 23 clicks, hidden entry, required date with no default, pre-ticked event, automatic categories skip people without a birth year | `admin/varzybos.php:183`, `:25`, `assets/varzybos.js:2827`, `:992` | Denis | "⚡ Greitos varžybos" | M | needs Denis's defaults | B2 |
| B-7 | Phone run view: bout at y=874 under 8 tool buttons; no "Ruošiasi" in the bout panel; 💡 over "+2" | `admin/varzybu-sistema.php:518`, `assets/varzybos.js:1778` | Denis, judges | tools under "⋯ Daugiau", sticky "Ruošiasi" | M | check 768 and 1440 | B3 |
| B-8 | "Tatamiai ir kovų trukmė" opens off-screen at 375, 768, 1440 | `assets/varzybos.css:697`, `admin/varzybu-sistema.php:488` | Denis | open inside the screen | S | none | B3 |
| B-9 | Stopwatch shows 0:01 at once (#134) | `assets/varzybos.js:1681` | judges, TV | round down when counting up | S | none | B1 |
| B-10 | Back with the bout panel open leaves a stuck window | `assets/varzybos.js:447` | Denis | Back closes the panel first | S | low | B3 |
| B-11 | "Sukurta" messages never show on the competition page | `admin/varzybos.php:40` | Denis | show them there | S | none | B3 |
| B-12 | Hand-typed athletes without a birth year are public with full names | `assets/varzybos.js:1625` | kids | unknown year = shortened | S | none | B3 |
| B-13 | "Rask save" cannot find a minor by surname | `varzybos-gyvai.php:50`, `app/tournaments.php:720` | parents | search full names on the server, show short ones | S | privacy choice (question 8) | B3 |
| B-14 | Secretariat sees history restore, tatami and registration settings | `admin/varzybu-sistema.php:262` | Denis | hide for secretariat | S | question 4 | A2 / C3 |
| B-15 | Budo theme is one value for everyone, TV and spectators included; any coach changes it | `tema.php:36`, `varzybos-gyvai.php:121` | spectators | owner-only or per device | S | question 9 | C2 |
| B-16 | `assets/varzybos.js` is one 3,200-line block with 237 functions; no tests of the engine without a browser | `assets/varzybos.js:1` | Deividas | engine tests, then modules | L | medium | C6, D3 |
| B-17 | Registration page for other clubs sits in the karateka layout with "2 treniruotės nemokamai" | `varzybos-registracija.php:84` | other clubs | Budo header | S | none | B3 |
| B-18 | Taps under 44 px: pool "+" 26 px, schedule actions 21 px, flag chips 29×25 | `assets/varzybos.css:159` | judges | 44 px on touch | S | desktop density | B3 |

Good and to be kept: the 768 px run and bout views, the TV tatami screen, the awards tablet and the awards queue.

## 2.2 Accounts, roles and permissions

| # | Finding | Evidence | Who | Fix | Size | Risk | Step |
|---|---|---|---|---|---|---|---|
| R-1 | Three roles only; testers are full admins | `db/001_schema.sql:14`, `app/auth.php:22` | Denis | tester flag, real-admin checks | M | who is who (decision 1) | A4 |
| R-2 | Role changes on select touch; new account defaults to admin | `admin/paskyros.php:113`, `:86` | Denis | button + confirm; default "Narys / tėvai" | S | none | A2 |
| R-3 | Secretariat session reaches `admin/varzybos.php?id=` and its coach actions | `app/tournaments.php:138`, `admin/varzybos.php:7`, `:61`, `:69` | Denis | redirect at the top; test | S | none | A2 |
| R-4 | Coaches can send SMS from the participants page | `admin/renginio-dalyviai.php:42`, `:142`; admin-only in `admin/renginiai.php:112` | parents | check inside `sms_send_message()` | S | none | A2 |
| R-5 | Game shown to everyone when its setting is missing; the test reset deletes it | `app/game.php:283`, `tests/setup/reset-db.sh:22` | kids, testers | default closed | S | game tests must set it | A2 |
| R-6 | Homepage editing is allowed by matching a person's **name** | `app/home.php:166` | Denis | pick accounts, not names | S | low | C1 |
| R-7 | A parent cannot be a trainee; a self-registered adult cannot add a child | `paskyra.php:167` | parents | every account can do both | M | approval rules | B7 |
| R-8 | Child login needs its own e-mail and a second approval | `paskyra.php:64`, `pakvietimas.php:34` | parents, kids | one button | M | questions 24–26 | B7 |
| R-9 | Join link makes any holder a "parent" at once, valid 60 days | `app/tokens.php:10`, `jungtis.php:30` | parents | tell the coach; 14 days | S | question | B7 |
| R-10 | Password reset keeps other devices and sessions signed in | `slaptazodis.php:19` | everyone | sign out everywhere on reset | S | none | C1 |
| R-11 | No record of role, status or game-access changes | `admin/paskyros.php:36` | Denis | change log | S | none | C1 |
| R-12 | Photo consent is one yes/no, enforced nowhere | `db/001_schema.sql:40` | parents | per context (question 29) | M | policy | C1 / E1 |
| R-13 | "Žiūrėti kaip" uses two shared test accounts that sit in real groups ("Test Parent", "Test Junior") | `app/test_people.php:226`, `:265` | Denis | Lithuanian names; keep test people out of lists | S | none | C1 |
| R-14 | First-admin page takes its key in the page address | `admin/pirmas-adminas.php:8` | Deividas | form field only | S | none | C1 |
| R-15 | No test covers register → confirm → approve, consent, invites or role changes | `tests/e2e/` | Deividas | life-cycle spec | M | none | C1 |

## 2.3 Navigation and screens (Valdymas, members)

| # | Finding | Evidence | Who | Fix | Size | Risk | Step |
|---|---|---|---|---|---|---|---|
| N-1 | Up to 1100 px there is no admin switch and no "Žiūrėti kaip" | `assets/header.css:314`, `app/layout.php:613` | Denis | first row of "⋯ Daugiau" | S | low | B4 |
| N-2 | 💡, chat and bell overlap each other, the sheet and main buttons | `assets/widgets.css:178`, `:248`, `assets/header.css:315` | everyone on a phone | one floating button; nothing above a sheet | M | test every page | B4 |
| N-3 | 🎨 is shown to all logged-in users, but its script loads only for admins | `app/layout.php:536`, `app/notifications.php:256` | members | load for everyone | S | none | B4 |
| N-4 | "Apie klubą" fold covers the logo switch, LT, theme (#131) | `assets/header.css:200` | everyone at ≥1101 px | open downward | S | check 1101–1440 | B1 |
| N-5 | Side menu top 123 px above the first card at 2560; header and page use different widths (#129) | `assets/header.css:262`, `admin/index.php:52` | Denis | one container; show 2 variants | S | low | B1 |
| N-6 | One screen has three names: Pradžia / Šiandien / Kasdien; two "Nustatymai"; two "Mano paskyra" | `app/layout.php:329`, `admin/index.php:49` | Denis | one name per place | S | #114 | B9 |
| N-7 | Events, news, lessons are not in the Valdymas menu and open in the public layout | `app/layout.php:346`, `admin/renginiai.php:140` | Denis | inside Valdymas | M | menu was agreed (#106) | B9 |
| N-8 | After login Denis lands on his member page, coaches on Valdymas | `app/helpers.php:90`, `paskyra.php:135` | Denis | one start page | S | question 40 | B9 |
| N-9 | Menu test variants (3 desktop, 5 phone) are still in the code and in cookies for 30 days | `app/layout.php:262`, `:269`, `:317` | coaches | remove after OK | M | reikia leidimo | B9 |
| N-10 | Stray points form always visible at the bottom of Treniruotė | `admin/treniruote.php:470`, `assets/app.css:1343` | coaches | hide when `hidden` | S | none | B1 |
| N-11 | Aktyvumo taškai page scrolls sideways at 375 | screenshot `taskai-overflow-375.png` | Denis | fix the wide child | S | none | B1 |
| N-12 | Kid sees the adult page; "Atsijungti" next to the greeting; tab row cut; page 6,477 px tall **(light)** | screenshots `j2-01-kid-landing-375.png`, measurement in the walk log | kids | kid home | M | decision 6 | B8 |
| N-13 | Guest button says "2 treniruotės nemokamai"; the agreed word was "Išbandyti" **(light)** | screenshot `home-top-375.png` | guests | ask Denis | S | wording | B9 |
| N-14 | No "what is new" page; version number is plain text | `app/layout.php:524` | Denis, testers | "Kas naujo" | S | none | C9 |
| N-15 | Dashboard says "Šiandien: 4", Treniruotė shows 3 groups | `admin/index.php:105` | Denis | one function for both | S | none | B1 |

## 2.4 Notifications, messages, e-mail, SMS

| # | Finding | Evidence | Who | Fix | Size | Risk | Step |
|---|---|---|---|---|---|---|---|
| M-1 | Red badge = unread notices + number of to-do **rows**; news counts fully; a weekly quiz row stays 7 days | `app/layout.php:645`, `app/data.php:641`, `app/notifications.php:116` | Denis | count actions only | M | number changes; tell Denis | B5 |
| M-2 | Feedback replies share a 30-row limit with real requests | `app/notifications.php:98`, `admin/index.php:40` | Denis | separate lists | S | low | B5 |
| M-3 | Pop-up "Supratau" removes an unanswered request from the badge | `pranesimas.php:19`, `assets/notify-popup.js:9` | Denis | count until done | S | low | B5 |
| M-4 | About 12 functions compute badges and disagree (parent header vs child tabs, chat counted twice) | `app/data.php:533`, `paskyra.php:230`, `app/chat.php:181` | parents | one source | M | many call sites | B5 |
| M-5 | Two staff inboxes with different e-mail rules; `admin/zinutes.php` is not in the menu | `admin/zinutes.php:106`, `app/chat.php:157` | parents, Denis | one reply function | M | removing a page needs OK | B5 / C8 |
| M-6 | Texts and e-mails ignore the reader's language; language is a browser cookie | `app/i18n.php:16`, `app/mail.php:12` | EN/RU parents | language on the account; templates | L | migration | C8 |
| M-7 | Bulk e-mail is sent inside the page request, one connection per person | `admin/pranesimai.php:54`, `app/mail.php:37` | parents | outbox in batches | M | new table | C8 |
| M-8 | "XP" shown to kids; invite e-mail promises the hidden game | `app/xp.php:96`, `app/auth.php:208` | kids, parents | wording | S | none | B1 |
| M-9 | E-mail links point to `#zinutes`, which does not exist; broadcast mail is signed twice | `app/messages.php:347`, `admin/pranesimai.php:55` | parents | fix | S | none | C8 |
| M-10 | Mail login can go over a plain connection; the trial form has its own mail code | `app/mail.php:36`, `uzklausa.php:11` | Deividas | refuse plain; one sender | S | low | A5 |
| M-11 | Dead parts: anonymous comments box (nothing writes to it), two unused notice types | `assets/chat.js:325`, `app/data.php:620` | Deividas | remove with OK | S | reikia leidimo | C10 |
| M-12 | Three pollers per staff tab (20 s, 60 s, 15 s); open chat reloads a 200-row list every 2.5 s | `assets/chat.js:11`, `assets/notify-popup.js:71`, `assets/game-invites.js:8` | hosting | one request | M | behaviour must stay | C7 |
| M-13 | Nothing is ever deleted: notices, messages, SMS phone log | `app/cleanup.php` | parents | keep periods (question 15) | S | deleting data | C10 |
| M-14 | No test reads an e-mail or the SMS log | `tests/e2e/06r-denio-atsiliepimai-45-53.spec.js:22` | Deividas | Mailpit tests | M | none | C6 |

## 2.5 Settings and switches

| # | Finding | Evidence | Who | Fix | Size | Risk | Step |
|---|---|---|---|---|---|---|---|
| S-1 | Six places hold settings; no list of names; defaults repeated in code | `app/data.php:304`, `app/layout.php:293` | Deividas | registry | M | low | C2 |
| S-2 | Edited on 10+ screens while Nustatymai says "one place" | `admin/nustatymai.php:2`, `admin/egzaminai.php:17` | Denis | "Visi nustatymai" map | S | none | C2 |
| S-3 | Any admin flips site-wide switches; they save on touch; no log, no undo | `app/auth.php:37`, `admin/nustatymai.php:30`, `:139` | Denis | owner-only, confirm, log | M | low | A4, C2 |
| S-4 | Missing `env`: karateka acts as "not live", LTKF as "live" | `app/bootstrap.php:25`, `app/tournaments.php:153` | everyone at go-live | one function, safe default | S | none | C2, E1 |
| S-5 | Exam prices are stored by position in the belt list; adding 10 kyu shifts them | `app/belts.php:5`, `admin/egzaminai.php:95` | parents | migrate with #136 | S | wrong prices if skipped | B6 |
| S-6 | One registration switch covers Budo and the external Varžybos | `app/data.php:79`, `varzybos.php:54` | members | split or decide in D2 | S | none | D2 |
| S-7 | "Ir numatytoji visiems" works only if ticked before picking a theme; the panel stays open (#127) | `assets/theme-pick.js:18`, `:11` | Denis | apply on change; close | S | none | B1 |
| S-8 | Launch switches live in code: everything unlocked in the game, LTKF "draft" | `app/game.php:273`, `ltkf/inc/nustatymai.php:11` | everyone at go-live | registry + checklist | S | none | C2, E1 |
| S-9 | Cookies set five different ways; the privacy page lists only three | `app/two_factor.php:99`, `kalba.php:10` | parents | one helper; full list | S | none | C10 |

## 2.6 Secret links and keys

| # | Finding | Evidence | Who | Fix | Size | Risk | Step |
|---|---|---|---|---|---|---|---|
| L-1 | Exam sign-up returns another person's personal page and overwrites their phone | `app/exam_signup.php:86`, `:97`, `:102` | kids, parents | new rows only; no overwrite | S | low | A3 |
| L-2 | "Jau užsiregistravai?" can be guessed by script; works after closing | `egzamino-registracija.php:21`, `:28` | kids | limit per IP; switch off | S | low | A3 |
| L-3 | Personal exam page shows birth date and phone forever | `mano-egzaminas.php:63`, `:21` | kids | mask; replace button; end date | M | questions 20–21 | A3 |
| L-4 | Awards tablet writes with no check, no name, no log; cannot be switched off; full names of children | `varzybos-apdovanojimai.php:19`, `:30`, `:5` | kids, Denis | name, log, buttons | S | question 7 | C3 |
| L-5 | Live, registration, awards, quiz and sign-up links never expire and cannot be replaced | `app/tournaments.php:546`, `app/exams.php:245` | everyone | three buttons on every link | M | low | C3 |
| L-6 | Secret parts of addresses are stored in feedback (item #85 holds a live exam link) | `app/feedback.php:238` | kids | strip before saving | S | editing #85 needs OK | A3 |
| L-7 | Nobody can tell which tablet entered a score; the secretariat's name is not saved | `varzybos-sekretoriatas.php:17`, `app/tournaments.php:314` | Denis | save the name with each version | S | none | C3 |
| L-8 | Link sessions never end: no "finish" button, 14-day cookie | `app/bootstrap.php:102`, `app/tournaments.php:121` | Denis | end button, idle limit | S | none | C3 |
| L-9 | Link secrets are stored readable in the database (trial, secretariat, LTKF) | `app/tournaments.php:91`, `:100`, `admin/ltkf-perziura.php:29` | everyone | store only a fingerprint | M | old links must keep working | C3 |
| L-10 | Tests cover switching off for 3 kinds of link only | `tests/e2e/06q-sekretoriatas.spec.js:58` | Deividas | one spec for all kinds | M | none | C3 |

## 2.7 Exams and tests (light)

| # | Finding | Evidence | Who | Fix | Size | Risk | Step |
|---|---|---|---|---|---|---|---|
| E-1 | #133: a 6.3 score in a passed row is not red (only the row result is coloured; no rule per cell) | `admin/egzamino-protokolas.php:278`–`279` | examiners | as Denis wrote it | S | print view | B1 |
| E-2 | #136: grade list runs 8 kyu … 1 dan | `admin/egzamino-protokolas.php:293` | examiners | add confirmed grades + prices move | S | S-5 | B6 |
| E-3 | Exam list says "dalyvių dar nėra" while the dashboard says "Dalyviai 8/11" | `admin/egzaminai.php:28`, `:41` | Denis | show both numbers | S | none | B6 |
| E-4 | Tablet controls 22–26 px; ✕ is 11×16 | walk at 1280×800 | examiners | 40 px | S | print | B6 |
| E-5 | The exam page hands out the quiz share link even when sharing is off; unlimited retries show the answers | `mano-egzaminas.php:91`, `testas-svecias.php:11` | examiners | start by exam link only | S | question 22 | A3 |
| E-6 | #137 export / import of the test bank does not exist as a round trip | — (not read in depth: **unclear** what the current import accepts) | Denis | build | M | none | B6 |

## 2.8 LTKF

| # | Finding | Evidence | Who | Fix | Size | Risk | Step |
|---|---|---|---|---|---|---|---|
| F-1 | Editors (link, no account) can upload `.svg` to the karateka address | `ltkf/redaguoti.php:9`, `:191`, `uploads/.htaccess:3` | admins | refuse svg; close the folder | S | none | A2 |
| F-2 | EN/RU broken by the "karate" fix: 9 translation keys still say "karatė" | `tools/vertimai/ltkf.tsv:117`, `ltkf/index.php:17` | foreign visitors | fix keys; test | S | none | B1 |
| F-3 | Text edits are keyed by the original text: 5 identical placeholders = 1 text | `ltkf/inc/redagavimas.php:174`, `ltkf/assets/redaktorius.js:52` | editors | stable ids | M | move saved edits | D6 |
| F-4 | Section settings are keyed by position; the home page has a section that comes and goes | `ltkf/inc/redagavimas.php:215`, `ltkf/index.php:51` | editors | named sections | M | move once | D6 |
| F-5 | One undo step, lost on the second save; menu and deletions have none | `ltkf/redaguoti.php:77`, `:253` | editors | versions | M | low | D6 |
| F-6 | Editors cannot add or remove clubs, events, news, documents, board members | `ltkf/inc/pagalbines.php:25` | editors | lists as records | L | Budo club list moves too | D6 |
| F-7 | Depends on karateka config, database, login cookie and `../` paths; hidden when `env` is live | `ltkf/inc/isdestymas.php:4`, `:146`, `ltkf/inc/pagalbines.php:121` | federation | own switch and addresses | L | rehearsal needed | D6 |
| F-8 | Every public Budo competition lands on the federation calendar | `ltkf/inc/pagalbines.php:155` | federation | per-competition switch | S | question 31 | D6 |
| F-9 | Federation name is written four ways across the sites | `ltkf/inc/nustatymai.php:7`, `app/exam_protocol.php:7`, `app/tournaments.php:529` | federation | one constant | S | question 30 | B1 |
| F-10 | `ltkf/kalba.php` can redirect to another site | `ltkf/kalba.php:8` | visitors | local paths only | S | none | A2 |
| F-11 | Draft has no `noindex`; the karateka bar shows to every visitor | `ltkf/inc/isdestymas.php:96` | federation | show to reviewers only | S | none | D6 |

Feedback about LTKF so far: five items in total, all handled — two from a Budo trial tester, three from Denis, none from federation members.

## 2.9 Dojo game

| # | Finding | Evidence | Who | Fix | Size | Risk | Step |
|---|---|---|---|---|---|---|---|
| G-1 | Extra gift boxes by sending made-up song names; "quick fight" wins trusted from the browser | `zaidimas.php:253`, `:260`, `:270` | kids | server decides rewards | S | low | D7 |
| G-2 | Fights saved with no lock: a pick can be lost, a finish applied twice | `app/game_fight.php:351`, `:410`, `:445` | kids | lock or version | M | core path | D7 |
| G-3 | The test arena and the real arena are two different rule sets; the test one drops agreed decisions | `assets/game-lab.js:77`, `:298`, `app/game_fight.php:7` | Denis | one model, built once | L | needs the #109 answer | D7 |
| G-4 | Ranking counts only fights between kids, so Denis sees "Dar niekas nekovojo" (#128) | `app/game_ui.php:48`, `app/game_fight.php:439` | Denis | one ranking with tabs | M | questions 35 | D7 |
| G-5 | New heroes can still get removed animals; picture and skill disagree | `app/game.php:368`, `assets/game-pixel.js:73` | kids | one character list | S | migration | D7 |
| G-6 | Children's real first name + initial and free-text hero names are visible to all players | `app/game.php:426`, `:739` | kids, parents | question 36 | S | policy | D7 |
| G-7 | Only the fox is real pixel art; 22 others are flat pictures, no gear, no frames | `assets/game-pixel.js:83`, `assets/game-dojo.js:206` | kids | sprite standard, new art | L | style first | D8 |
| G-8 | Art build steps need files that are not in the repo and would overwrite current backgrounds | `tools/pikseliai/README.md:15`, `tools/pikseliai/export.py:25` | Deividas | commit sources; split steps | S | low | D8 |
| G-9 | One game access test can never fail | `tests/e2e/06y-budo-telefonas-apdovanojimai-renginiai.spec.js:140` | Deividas | real check | S | none | C6 |

## 2.10 Design, CSS, JavaScript (light)

| # | Finding | Evidence | Who | Fix | Size | Step |
|---|---|---|---|---|---|---|
| D-1 | 81 different pixel widths in media queries (most used: 860, 760, 700, 560, 1100, 900, 640) | counted over `assets/*.css`, `ltkf/assets/*.css` | everyone (jumps between widths) | 4 breakpoints | L | C4, C5 |
| D-2 | 384 different colour values in the four main CSS files | counted | everyone | tokens | L | C4, C5 |
| D-3 | Later "vX.YY" blocks override earlier rules; `!important` 23× in `app.css`, 30× in `game-px.css`, 14× in `header.css` | counted | Deividas | fold into the base rules | L | C5 |
| D-4 | An element marked `hidden` can still show when a later rule sets `display` | `assets/app.css:1343` (N-10) | coaches | one global rule | S | C5 |
| D-5 | A Budo-only style class is used on a karateka page | `admin/paskyros.php:104`, `assets/varzybos.css:746` | Denis | shared class | S | B1 |
| D-6 | Three separate design systems (karateka, Budo, LTKF) plus the game | `ltkf/assets/ltkf.css:6` | everyone | shared base scale, colours per site | L | C4 |
| D-7 | Step heading on Pranešimai puts the hint into a black circle | `admin/pranesimai.php:97`, `assets/app.css:1076` | Denis | scope the circle | S | B1 |

## 2.11 Data, tests, deploy (light)

| # | Finding | Evidence | Who | Fix | Size | Step |
|---|---|---|---|---|---|---|
| T-1 | 289 browser tests in 33 files run one after another, sharing one database | `tests/playwright.config.js:9`–`10` | Deividas | independent, parallel | M | C6 |
| T-2 | No test reloads between a draw and the first result — that gap hid the lost-results bug | `tests/e2e/06*` | everyone | reload rule | S | A1, C6 |
| T-3 | Tests change settings by deleting rows, which puts the product into its most open state | `tests/setup/reset-db.sh:22` | Deividas | helpers with safe defaults | S | C6 |
| T-4 | Daily backup covers the database only; uploaded photos are not in it | `docs/atsargines-kopijos.md` | Denis | add uploads or document the host backup | S | C10 |
| T-5 | 65 tables, 79 foreign keys, 44 migration files; some links have no foreign key (messages → events, news) | `db/`, `db/021_pranesimai_renginiai_xp.sql:32` | Deividas | add with care | S | C10 |
| T-6 | CI runs the full suite on every push (20-minute cap) | `.github/workflows/tests.yml:4`–`7` | Deividas | smoke on push, full nightly | S | C6 |

---

# Part 3 — How the project is worked on

## The real loop today

1. Denis writes in the 💡 box on test.karateka.lt (the single inbox). Sometimes he writes to Deividas instead, and Deividas pastes it into the chat.
2. A cloud session reads the feedback through the feedback API, changes code on its work branch, runs tests, builds `svetaine-vX.YY.zip` and sends it in the chat.
3. Deividas uploads the zip to test.karateka.lt and presses "Atnaujinti duomenų bazę" in Valdymas.
4. The session answers each feedback item: "Padaryta (vX.YY)" with the click path.

This works. What is written down describes a different loop.

## Findings

| # | Finding | Evidence | Fix | Step |
|---|---|---|---|---|
| P-1 | `main` is at v1.18 (4 November), 92 commits behind the working branch (v1.47) | `git rev-list --count origin/main..HEAD` = 92 | merge on „į main“ | A6 |
| P-2 | The PC script takes only `main`, so it would build v1.18 | `tools/atnaujinti-is-github.ps1:27`–`32` | after P-1 it is right again | A6 |
| P-3 | The deploy guide says "main ← dev"; there is no `dev` branch | `docs/ikelti-i-test.md:5` | rewrite to the real loop | A6 |
| P-4 | The zip script deletes old zips; the rule is to keep them | `tools/paruosti-ikelimui.ps1:46` | keep; never overwrite | A6 |
| P-5 | No `CLAUDE.md` in the repo: rules, names and decisions are re-explained each session | repo root | `docs/CLAUDE-pasiulymas.md` | C9 |
| P-6 | No `docs/handoff.md` and no `docs/decisions.md`: asks resurface (your login was asked for twice), agreed designs come back wrong | `docs/` | create both | C9 |
| P-7 | "Never commit unless I say" and "commit at 23:00" cannot hold in a cloud session: it is stopped from finishing while work is unsaved, and its machine is thrown away afterwards | observed in every cloud session | cloud saves to its own branch; `main` only on „į main“ | decision 5 |
| P-8 | 87 of 155 commits carry "Co-Authored-By" lines, against your rule | `git log` | rule in `CLAUDE.md` | C9 |
| P-9 | "Done" was sometimes claimed with small or missing screenshots | chat history (digest) | screenshot standard | C9 |
| P-10 | The `release-check` skill still speaks of the "Varžybos app" and DirectAdmin | skill text | update after D2 | C9 |
| P-11 | The `pixel-art-sprites` skill draws 32×32 grids; the game's characters are about 116×130 | skill text vs `assets/game-pixel.js:83` | align with the sprite standard | D8 |
| P-12 | A cloud session cannot write to your PC, read other chats or run at a set hour on your PC | platform limit | say it in one line, hand over a file | C9 |
| P-13 | Whole-project reviews with many helpers hit the usage limit (this one did, at 11 of 18 areas) | this review | review one area per session | — |

## What to install or set up

1. Nothing new is needed for phases A–C.
2. Put `CLAUDE.md` into the repo root (step C9). It replaces most repeated explanations.
3. For step D8 only: Aseprite + aseprite-mcp on your PC (or the free LibreSprite + libresprite-mcp).
4. Optional: a Windows scheduled task that runs at 23:00 and commits your local folder — only if you start editing on the PC again. The cloud cannot do this for you.
5. Before go-live: check in the hosting panel that file backups are on.
