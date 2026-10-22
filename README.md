# karateka.lt

Website and member system for **VšĮ Karate Ateitis**, a traditional karate-do club in Vilnius, Lithuania.

Live site: [karateka.lt](https://karateka.lt). The member system is in staging and will replace the current site.

## Features

**Public site**
- Groups, prices and weekly schedules for kids, youth and adults, edited from the admin panel
- Event calendar (exams, competitions, seminars, camps) with type filters
- News with photos and YouTube videos, emoji reactions and sharing
- Trial training sign-up form

**Members**
- Family accounts: one parent account can manage several children; children under 14 are activated only after a parent confirms by e-mail (GDPR)
- Every new account is approved by a coach, who assigns the training group
- Season points and ranking (exams, competitions, seminars, attendance, refereeing) with a Top 5 per age category
- Belt tracking from 9 kyu to 9 dan; lessons are shown by belt and group
- Coach notes and homework after each training, with read / done status

**Coaches** (mobile-first, used in the gym)
- Attendance in one tap per member, notes for the whole group on one screen
- Event results that award points automatically
- Approvals, members, groups, events, news, lessons and point values
- WTKU referee knowledge test: question bank, timed exam that coaches open and close, practice mode,
  results per section; question count, pass mark, time limit, attempts and who may take it are set in the admin panel

## Tech

- PHP 8 without a framework, server-rendered pages
- MySQL / MariaDB through PDO; schema changes as numbered SQL migrations in `db/`
- Plain JavaScript and CSS, no build step
- Runs on standard shared hosting (Apache); Docker for local development

Security and privacy:
- `password_hash` / `password_verify`, prepared statements for all queries
- CSRF tokens on every form, `HttpOnly` / `SameSite` session cookies, HTTPS only
- Login throttling per e-mail and IP
- One-time links (e-mail confirmation, password reset, invites) stored as SHA-256 hashes
- Uploaded images are re-encoded and renamed; scripts can't run from the uploads folder
- Unfinished registrations, used tokens and old login attempts are deleted automatically (no cron needed on shared hosting)

## Data model

Accounts (people who log in) are kept separate from members (people who train).
`account_members` links them with a relation of `self` or `parent`, so a parent can manage several
children and a child can later get their own login without creating a duplicate member.

## Project structure

```
public_html/        web root
  app/              shared code: bootstrap, auth, data access, layout (blocked from the web)
  admin/            coach panel
  assets/           CSS and JS
db/                 schema and migrations (001_schema.sql ...)
docker/             local PHP + Apache image
tools/              deploy package and local setup scripts
docs/               changelog (Lithuanian)
```

## Running locally

Requires Docker.

```bash
cp .env.example .env
cp config.example.php public_html/config.php   # set env => local, db host => db
docker compose up -d
docker compose exec web php app/cli/dev_seed.php
```

| URL | |
|---|---|
| http://localhost:8088 | Website |
| http://localhost:8081 | phpMyAdmin |
| http://localhost:8025 | Mailpit (catches outgoing e-mail) |

On Windows, `tools/lokali-svetaine.ps1` does all of the above in one step and also loads demo data.
To start over with an empty database: `docker compose down -v`.

## Deployment

`tools/paruosti-ikelimui.ps1` builds a versioned upload package. New migrations from `db/` are imported once
per environment. Each environment (local, staging, live) has its own `config.php`, which is never committed.

---

Developed by [Deividas Balinskis](https://github.com/DeividasBalinskis). Content and photos © VšĮ Karate Ateitis.
