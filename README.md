# karateka.lt

Website of **VšĮ Karate Ateitis**, a traditional karate-do club in Vilnius led by Denis Balinskis (3 Dan).

🌐 Live site: **[karateka.lt](https://karateka.lt)**

## What's here

- `public_html/`: the website - upload its contents to the server
  - `index.php`: main page (groups and prices, events, about the club, contacts, trial registration form)
  - `uzklausa.php`: sends the "2 free trainings" form to info@karateka.lt
  - `page1.html`: old address, redirects to `index.php`
  - `img/`: photos and logo; `assets/`: styles; `admin/`: coach panel; `app/`: shared PHP code (blocked from the web)
- `config.example.php`: template for `public_html/config.php` (passwords, never in Git)
- `db/`: database schema, numbered `001`, `002`, ... (no member data)
- `docs/`: `ikelimas.md` (how to upload), `klausimai-broliui.md` (open questions), `saskaitu-planas.md` (billing plan)
- `tools/`: `paruosti-ikelimui.ps1` (builds the `ikelimui/` upload folder), `demo-duomenys.php` (demo data)
- `docker/`, `docker-compose.yml`: local PHP + MariaDB

## In progress: member system

A members area is being built in phases. It covers parent and kid accounts with coach approval, groups, schedule, events, news with reactions, then points and ranking, then coach notes and video lessons. Built so far: accounts and approvals, groups and schedule, events, news, points and ranking, belts, coach notes and tasks, attendance, lessons. Next: invoices and payments ([docs/saskaitu-planas.md](docs/saskaitu-planas.md)).

## Running locally

Requires [Docker Desktop](https://www.docker.com/products/docker-desktop/).

```bash
cp .env.example .env                                # set local DB passwords
cp config.example.php public_html/config.php
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

Uploading to the test site: [docs/ikelimas.md](docs/ikelimas.md).

## Secrets and data

`config.php`, `.env`, uploads, DB dumps and any member data are listed in `.gitignore` and must never be committed.
Each environment (local, test, live) has its own `config.php`.

## Hosting

domenai.lt (DirectAdmin, PHP and MySQL). Test site: `test.karateka.lt` → `public_html/test/`.
