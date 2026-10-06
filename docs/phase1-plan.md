# Phase 1 plan: accounts, groups, schedule, events, news

Status: **draft, nothing built yet.** Waiting for David's OK.

## 1. Code layout (plain PHP, no framework)

```
public_html/
  index.html, page1.html, send.php   live site, kept as they are
  config.php                         per environment, not in Git
  app/                               shared PHP code, blocked from the web by .htaccess
    bootstrap.php                    session, config, DB (PDO), helpers
    auth.php  csrf.php  mail.php  views/
  prisijungti.php  registracija.php  slaptazodis.php    login, register, password reset
  paskyra.php                        member page
  naujienos.php                      public news + reactions
  admin/                             coach panel, mobile-first
  uploads/news/                      resized photos, not in Git
db/001_schema.sql                    schema only, no member data
```

Locally everything runs from `public_html/`. On the server the same files go to `public_html/test/` until we move to live.
New pages reuse the CSS variables and fonts from `page1.html`, collected in one shared `app/views/style.css`.

## 2. Database tables

| Table | Purpose |
|---|---|
| `accounts` | Login: email, password_hash, role (`member` / `coach` / `admin`), status (`pending_email`, `pending_parent`, `pending_approval`, `active`, `disabled`) |
| `members` | A person who trains: name, birth date, group, photo consent, status |
| `account_members` | Links accounts to members, with relation `self` or `parent`. A parent links to many members, a kid account links to one |
| `groups` | Groups such as Vaikai Viršuliškės pažengę or Jaunimas |
| `schedule` | Weekly slots per group: day, start and end time, location |
| `events` | Exams, competitions, seminars and other events: date, place, type, which groups |
| `email_tokens` | One-time links for email confirmation, parent consent, password reset and kid invites (stored hashed, with expiry) |
| `news`, `news_media` | Posts with text, photos and YouTube links |
| `reactions` | One row per (post, account), with the emoji from a fixed set |
| `login_attempts` | Simple brute-force throttling |

## 3. Registration flows

1. **Parent:** creates an account, confirms their email, then adds one or more kids (name, birth date, photo consent).
2. **Self, 14 or older:** creates an account, confirms email, and the member is created with relation `self`.
3. **Under 14:** the kid fills in their own data plus a parent's email. The parent gets a consent link. Clicking it creates or links the parent's account. Only then does the kid's account go on to coach approval.
4. **Parent invites a kid:** from an existing kid member the parent sends an invite link, and the kid sets their own login on the **same** member, so no duplicate is created.
5. In every case the account ends as `pending_approval`. The coach approves it and assigns a group, then the account becomes active and the user gets an email.

The birth date decides the age rule (under 14 or 14+), so it is required.

## 4. Pages and features

- **Member page:** own group schedule, upcoming events for that group, and profile. A parent switches between their kids.
- **News:** public list and single post. Photos are resized on upload with GD (max ~1600px, plus a thumbnail). YouTube links are stored as video IDs and embedded via youtube-nocookie.
- **Reactions:** a fixed set (👍 ❤️ 🔥 👏 🥋). Counts are public, and clicking while logged out sends you to login. Reacting again replaces the emoji, and tapping the same one removes it.
- **Admin (mobile-first):** pending approvals with group picker; members and groups; schedule; events; news editor.

## 5. Security basics

`password_hash`/`password_verify`, PDO prepared statements only, a CSRF token on every form, session cookies with `Secure`, `HttpOnly` and `SameSite=Lax`, session ID regeneration on login, HTTPS redirect, login throttling, `app/` and `uploads/` protected from PHP execution, and output escaping everywhere.
Email goes through one `mail.php` helper that uses the existing SMTP code from `send.php`. Locally it goes to Mailpit, so no real emails are sent.

## 6. Build order

1. Base: `app/` bootstrap, DB schema, shared styles, layout.
2. Login, logout, password reset, email confirmation.
3. Registration flows 1 to 4 and the coach approval screen.
4. Groups and schedule (admin CRUD and display on the member page).
5. Events (admin CRUD and display).
6. News, image upload and reactions.
7. Test on `test.karateka.lt` and get your review, then plan the move to live.

I'll show you each step in the browser before moving on.

## 7. Need from you / your brother before or during phase 1

- Which coaches get admin access (accounts to create first)?
- The list of groups. Suggestion: build it from the locations and schedules already in `page1.html`.
- Should `page1.html` later read the schedule from the DB (one place to edit), or stay static for now? Suggest: static for phase 1.
- Where the login link goes in the `page1.html` nav (suggest "Prisijungti" on the right, next to the CTA).
- The PHP and MariaDB versions on domenai.lt (check in DirectAdmin) so local matches. Local is PHP 8.2 and MariaDB 10.11.
- The reaction emoji set (suggestion above).
