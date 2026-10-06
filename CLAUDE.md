# karateka.lt: project context

## About
- Website for VšĮ Karate Ateitis, a traditional karate-do club in Vilnius, led by Denis Balinskis (3 Dan).
- David maintains the technical side and makes implementation decisions. His brother owns and runs the club and will post news.
- Hosting: domenai.lt (DirectAdmin) with PHP and MySQL available. Do not suggest WordPress (the site was migrated away from it).

## Working with David
- Keep explanations short and plain. He usually writes in Lithuanian; reply in the language he uses.
- He reviews changes visually before accepting them. Show the result before finalizing.
- Ask before big structural changes or anything hard to undo.
- This folder (projektai/karateka) is the master copy. David uploads it to the server himself.

## Current live site (server: public_html/)
- `index.html`: landing page with 3 buttons (Vaikams, Jaunimui, Suaugusiems) that link to `page1.html#vaikams`, `#jaunimui`, `#suaugusiems`.
- `page1.html`: main scrolling page with about, instructors, group carousel, contacts and the registration form.
- `send.php`: sends the registration form to info@karateka.lt.
- All CSS is inline. Fonts are Space Grotesk, Inter and JetBrains Mono. New pages must match the existing design, so reuse the CSS variables and styles from `page1.html`.

## Test environment
- `test.karateka.lt` maps to server folder `public_html/test/`. It is password-protected and has its own test MySQL database.
- Build everything there first. The live site must stay untouched until the move.
- Keep DB credentials in one config file (e.g. `config.php`). Test and live configs differ, so never overwrite the live config when deploying.
- Security basics: `password_hash`/`password_verify`, prepared statements, CSRF tokens, secure sessions, HTTPS only.

## What we're building: member system

### Accounts
- Both parents and kids can create accounts.
- A parent account can hold several kids.
- Kids 14+ can register themselves.
- Kids under 14 must enter a parent's email. The parent confirms via an email link before the account is activated (GDPR; Lithuania's digital consent age is 14).
- A parent can invite a kid they already added to create their own login, so the same member is never created twice.
- Keep **accounts** (who logs in) separate from **members** (who trains). A parent account links to many members; a kid account links to one.
- Every new account needs coach approval, and the coach assigns the group.
- Registration includes a consent checkbox for publishing the child's photos.

### Features
- **Member page:** own group's schedule, upcoming exams and competitions, own history (exams, competitions), points and rank.
- **Points:** earned for:
  - passed exams
  - competition participation (LT / abroad)
  - prize places (LT / abroad)
  - seminars
  - leading trainings in the club
  - helping organize events
  - refereeing and referee qualification
- **Point values:** set and edited by admin in the admin panel, not hardcoded.
- **Ranking:** top 5 visible to everyone; others see only their own rank and points.
- **News:** the brother posts text, photos (auto-resized on upload) and YouTube links.
  - News is public to everyone.
  - Reactions use a fixed emoji set, one per person per post, and only logged-in members can react. Reaction counts are visible to all; a click by a logged-out visitor prompts login.
  - No comments for now.
- **Coach notes / homework:** left for a specific person for a specific training, with easily attached YouTube links.
- **Video lessons:** members-only section using YouTube links.
- **Admin (coaches, mobile-first because it's used in the gym):** approve accounts, assign groups, manage schedule and events, award points, write notes, post news.

## Phases
1. Accounts and login, groups, schedule, events, news with reactions.
2. Points, ranking, personal history.
3. Coach notes with video links, video lessons.

## Open questions (waiting on the brother)
- Does training attendance give points? Earlier notes said yes, but the latest list doesn't include it.
- Ranking: club-wide or per group/age? Reset each season? (Suggested per group, since refereeing and leading trainings are adult activities. Not confirmed.)
- Top 5: public internet or logged-in members only? (Suggested members only because kids' names appear. Not confirmed.)
- Which coaches get admin access?
- Do parents see notes for under-14 kids? Can kids mark homework as done?
- Track each member's belt/kyu? Is there old data (exams, results) to import?
- Is unlisted YouTube acceptable for lessons?
