# karateka.lt

Website of **VšĮ Karate Ateitis**, a traditional karate-do club in Vilnius led by Denis Balinskis (3 Dan).

🌐 Live site: **[karateka.lt](https://karateka.lt)**

## What's here

- `public_html/`: the website (static HTML, CSS inline, plus PHP for forms)
  - `index.php`: main page. Group choice (Vaikams / Jaunimui / Suaugusiems) at the top, then about the club, instructors, contacts and the trial registration form
  - `page1.html`: old address, now only redirects to `index.php` (keeps `#vaikams` etc.)
  - `assets/header.css`, `assets/header.js`: the shared top menu used by every page
  - `send.php`: sends the registration form by email
- `docker/`, `docker-compose.yml`: local PHP and MariaDB environment
- `public_html/app/`: shared PHP code for the member system (blocked from the web)
- `public_html/admin/`: coach panel (mobile-first)
- `db/`: database schema and initial groups (no member data)
- `docs/`: plans and notes

## In progress: member system

A members area is being built in phases. It covers parent and kid accounts with coach approval, groups, schedule, events, news with reactions, then points and ranking, then coach notes and video lessons. See [docs/phase1-plan.md](docs/phase1-plan.md).

## Running locally

Requires [Docker Desktop](https://www.docker.com/products/docker-desktop/).

```bash
cp .env.example .env                                # set local DB passwords
cp public_html/config.example.php public_html/config.php
docker compose up -d
```

| URL | What |
|---|---|
| http://localhost:8088 | Website |
| http://localhost:8081 | phpMyAdmin |
| http://localhost:8025 | Mailpit, which catches outgoing emails |

To fill the local database with sample accounts, events and news:

```bash
docker compose exec web php app/cli/dev_seed.php
```

It prints the local test accounts. All of them use the password from `app/cli/dev_seed.php`; they exist only on your PC.

To reset the local database (deletes all local data):

```bash
docker compose down -v && docker compose up -d
```

Deploying to the test site: [docs/deploy-test.md](docs/deploy-test.md).

## Secrets and data

`config.php`, `.env`, uploads, DB dumps and any member data are listed in `.gitignore` and must never be committed.
Each environment (local, test, live) has its own `config.php`.

## Hosting

domenai.lt (DirectAdmin, PHP and MySQL). Test site: `test.karateka.lt` → `public_html/test/`.
