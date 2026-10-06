# Phase 2 plan: points, ranking, personal history

Status: **draft, nothing built yet.** Several decisions are needed from your brother (section 5).

## 1. New tables

| Table | Purpose |
|---|---|
| `point_categories` | What earns points and how many. The admin edits names and values in the panel |
| `point_awards` | One row per award: member, category, points, date, linked event (optional), note, who awarded it |
| `seasons` (or a date setting) | Which period the ranking counts. History is always kept |

Starting categories (values are placeholders; the admin sets them):

| Category | Example points |
|---|---|
| Passed exam | 10 |
| Competition, Lithuania: participation | 5 |
| Competition abroad: participation | 10 |
| Prize place, Lithuania: 1st / 2nd / 3rd | 15 / 10 / 7 |
| Prize place abroad: 1st / 2nd / 3rd | 30 / 20 / 15 |
| Seminar | 5 |
| Leading a training in the club | 5 |
| Helping organize an event | 5 |
| Refereeing | 5 |
| Referee qualification | 10 |

## 2. Coach panel (mobile-first, in the gym)

- **Award points from an event.** Open an exam or competition, and you get a list of members from the event's groups. Tick who took part and pick the result (participation, 1st, 2nd or 3rd place). One save covers everyone.
- **Award points to one person.** From the member's page: pick a category, date and note (for example "led the Tuesday kids' training").
- **Undo.** Every award can be deleted, in case of mistakes.
- **Point values.** Edit categories: name, points, turn on or off.

## 3. Member page

- **My points and rank:** total points this season and position in the ranking.
- **Top 5:** names and points of the top 5. Everyone else sees only their own position.
- **My history:** passed exams, competitions with results, seminars and so on, newest first.
- A parent sees this for each of their kids.

## 4. Rules I'd suggest (change any)

- **Changing a point value affects only new awards.** Old awards keep the points they were given with, so history doesn't silently change.
- **A prize place also counts as participation,** for example LT 1st place = participation + 1st place points.
- **Ranking is per age category** (Vaikai / Jaunimas / Suaugusieji), not per training location. Groups by location are small, and adults earn points kids can't (refereeing, leading trainings).
- **The season runs September 1 to August 31.** The ranking restarts each season, but history and the all-time total stay.
- **Top 5 is shown only to logged-in members,** because kids' names appear.

## 5. Decisions needed from your brother

1. The point values for each category (or "use your examples for now").
2. Does training attendance give points? If yes, the coach needs a quick attendance check-in for each training, which is a bigger feature.
3. Ranking: club-wide, per age category (suggested) or per group? Reset each season?
4. Top 5: public internet or members only (suggested)?
5. Should we track each member's belt (kyu/dan)? If yes, a passed exam can update the belt automatically.
6. Is there old data (past exams, competition results) to import? In what form: Excel, paper?

## 6. Build order

1. Categories and the points admin page
2. Awarding points (from an event, and to one person)
3. Member page: points, rank, top 5, history
4. Belt tracking and data import, if your brother wants them
