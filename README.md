# karateka.lt

Website of **VšĮ Karate Ateitis**, a traditional karate-do club in Vilnius led by Denis Balinskis (3 Dan).

🌐 Live site: **[karateka.lt](https://karateka.lt)**

## What's here

- `public_html/`: the website (static HTML, CSS inline, plus PHP for forms)
  - `index.html`: landing page with group choice (Vaikams / Jaunimui / Suaugusiems)
  - `page1.html`: main page with info about the club, instructors, groups, schedule, contacts and registration
  - `send.php`: sends the registration form by email
- `docker/`, `docker-compose.yml`: local PHP and MariaDB environment
- `db/`: database schema (no member data)
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

## Secrets and data

`config.php`, `.env`, uploads, DB dumps and any member data are listed in `.gitignore` and must never be committed.
Each environment (local, test, live) has its own `config.php`.

## Hosting

domenai.lt (DirectAdmin, PHP and MySQL). Test site: `test.karateka.lt` → `public_html/test/`.
