# Tournament manager for traditional karate

Web app for running traditional karate (ITKF-style) competitions: registration, categories, draws, brackets,
mat schedules, live scoring and results. Built for **VšĮ Karate Ateitis** and connected to the club's member
system, [karateka.lt](https://karateka.lt), so members can sign up from their account and results flow back
into their competition history and season points.

## Features

**Before the event**
- Categories by discipline, gender, age, belt and weight; a template creates a full set in one step
- Kata, kumite, fuku-go, en-bu, kihon, team kata and team kumite
- Registration three ways: karateka.lt members from their account, other clubs through a public form, or the organizer
- Each athlete is offered only the categories they are eligible for (age is counted by birth year, as in ITKF events)
- Athletes are kept between events, so returning competitors don't have to be entered again

**Draws**
- Single-elimination brackets with byes given to the top seeds
- Athletes from the same club are placed to meet as late as possible
- Third place: two bronzes, a bronze match, or repechage
- Round robin pools (2 points for a win, 1 for a draw; ties by head-to-head, ippon, waza-ari)
- Score rounds for kata and en-bu: judges' scores, highest and lowest dropped with 5+ judges,
  top N advance (e.g. "16,8"), tie detection at the cut line

**On the day**
- Schedule per mat with estimated start times, auto-assignment and manual reordering
- Mobile scoring for table officials: ippon / waza-ari, keikoku / chui / hansoku / shikkaku, jogai, kiken,
  match clock, judges' flags, fuku-go (kata + kumite), team matches; the app suggests the winner by the rules
- Undo for the last action and for a confirmed result, as long as the next match hasn't started
- Live views for spectators on their phones and a full-screen display for the hall

**After**
- Placings, medal table by club, CSV export (opens in Excel)
- karateka.lt imports placings and awards points automatically

## Tech

- PHP 8 without a framework, JSON API (`api.php`), plain JavaScript modules, no build step
- MySQL / MariaDB through PDO with prepared statements
- Runs on standard shared hosting (Apache); Docker for local development

Security:
- `password_hash` / `password_verify`, login throttling per e-mail and IP
- CSRF token on every state-changing request, `HttpOnly` / `SameSite` session cookie
- Partner API protected with per-system keys (`X-Api-Key`)
- Personal data (birth dates, weight, contacts) only visible to signed-in organizers

## Partner API

Used by karateka.lt; any club system can integrate the same way. All routes take `X-Api-Key`.

| Method | Route | |
|---|---|---|
| GET | `api.php?r=v1/tournaments` | Published tournaments |
| GET | `api.php?r=v1/tournaments/{id}` | Tournament with categories |
| POST | `api.php?r=v1/eligible` | Categories an athlete can enter |
| POST | `api.php?r=v1/registrations` | Register an athlete (`category_ids` or `"auto"`) |
| GET | `api.php?r=v1/registrations&tournament_id=&external_ids=` | Current registrations |
| DELETE | `api.php?r=v1/registrations` | Withdraw (while registration is open) |
| GET | `api.php?r=v1/tournaments/{id}/results` | Placings with external athlete ids |

Athletes are matched by `external_id` (the member id in the partner system).

Single sign-on: the partner links its staff to `sso.php?p=...&s=...`, a short-lived, one-time payload signed with the
same API key (HMAC-SHA256). The user is created on first sign-in (partner admins become organizers, others secretaries).

## Project structure

```
public_html/          web root
  index.php           app shell
  api.php             JSON API
  export.php          CSV export
  app/                rules, draws, matches, scoring, results, schedule (blocked from the web)
  assets/             CSS and JavaScript modules
db/                   schema (001_schema.sql ...)
docker/               local PHP + Apache image
karateka-integracija/ files added to karateka.lt for registration and results import
tools/                local setup script
```

## Running locally

Requires Docker.

```bash
cp config.example.php public_html/config.php
docker compose up -d
docker compose exec web php app/cli/demo.php
```

| URL | |
|---|---|
| http://localhost:8090 | App |
| http://localhost:8083 | phpMyAdmin |

On Windows, `tools/lokali-svetaine.ps1` does all of the above in one step. Demo login: `admin@varzybos.test` / `testas123`.

## Deployment

Requires PHP 8.1+. Upload `public_html/` to the server, import the files in `db/` in order, create `config.php` from `config.example.php`
(database, `site_url`, a long random `setup_key` and API key), then open the site and create the first
administrator with the setup key.

---

Developed by [Deividas Balinskis](https://github.com/DeividasBalinskis).
