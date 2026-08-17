# WAM Blog

[![CI](https://github.com/richiekaroki/php-blog-project-backend/actions/workflows/php.yml/badge.svg)](https://github.com/richiekaroki/php-blog-project-backend/actions/workflows/php.yml)
[![Tests](https://img.shields.io/badge/tests-76%20passed-brightgreen)](#testing)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php)](#)
[![Vue](https://img.shields.io/badge/Vue-3-42b883?logo=vue.js)](#)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-17-4169E1?logo=postgresql)](#)
[![Docker](https://img.shields.io/badge/Docker-multi--stage-2496ED?logo=docker)](#)

A secure, passwordless blog platform: **PHP 8.4 backend**, **Vue 3 public SPA**, **Neon PostgreSQL**, deployed on **Render** as a single Docker container.

**Live:** https://php-blog-backend.onrender.com

| URL | Description |
|-----|-------------|
| [Homepage](https://php-blog-backend.onrender.com) | Public blog — landing page + sign-in + newsletter |
| [Admin Panel](https://php-blog-backend.onrender.com/admin/login.php) | Passwordless sign-in → blogs, comments, subscribers, analytics, profile & sessions |
| [API Health](https://php-blog-backend.onrender.com/api/index.php?action=health) | Status check |

---

## Features

- 🔑 **Passwordless magic links** — HMAC-SHA256 signed tokens, **single-use** (atomic `INSERT … ON CONFLICT`), 10-minute expiry, no account-leaking responses.
- 🛡️ **Optional TOTP 2FA** — RFC 6238, no external dependencies; enrollment requires proof-of-possession, disable requires the current code.
- 🧾 **Server-side session registry** — every login recorded in `auth_sessions`; logout & "sign out other devices" revoke sessions instantly.
- 🚦 **DB-backed IP rate limiting** — magic-link requests, 2FA attempts, and API requests all capped per-minute, keyed on hashed IP (cookies can't reset it).
- 👥 **Role-based access control** — `admin` (full), `editor` (create/edit), `viewer` (read-only), enforced server-side on the API **and** PHP admin pages.
- 📨 **Instant sign-ups** — any email can create an account (starts as `editor`) at `/signup.php` or by requesting a sign-in link; a magic link arrives immediately. No admin approval required.
- 💬 **Moderated comments** — readers comment on any published post; comments land in a `pending` queue (spam-guarded by rate limits + a honeypot field) and only appear once an admin/editor approves them.
- 📬 **Newsletter** — one-click subscribe (public API + server-rendered forms + the SPA), token-based one-click unsubscribe, and a best-effort email to every subscriber whenever a post is first published.
- 📊 **Post analytics** — admin dashboard with totals, top posts by views, reads by category, and a 6-month trend.
- 👁️ **Editor live preview** — render markdown content as HTML side-by-side while editing, via a read-only preview endpoint.
- 🐘 **PostgreSQL + PDO** — parameterized queries throughout, search/filter/pagination, image uploads with validation.
- 🕷️ **SEO-friendly** — crawlers get the fully server-rendered landing page (nginx bot UA detection); humans get the Vue SPA.
- 🔎 **Activity feed** — `activity.php` surfaces sign-ins, 2FA enable/disable, session revocations, comment and subscriber events, and content edits with human-readable labels.
- 🚀 **One-container deploy** — multi-stage Dockerfile builds the Vue SPA and serves it alongside PHP-FPM behind Nginx (same-origin, no CORS in production).
- ✅ **Quality gate in CI** — PHPUnit against a disposable Postgres (migrations applied), plus frontend ESLint, Prettier, Vitest, and the production build on every push.
- 📝 **Audit logging** — `activity_log` records sign-ins, 2FA changes, session revocations, comment submissions, and subscriber events.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | Vue 3, TypeScript, Vite, Tailwind CSS 4, shadcn-vue-style UI |
| Backend | PHP 8.4, Nginx + PHP-FPM (Docker) |
| Database | PostgreSQL 17 (Neon, managed) |
| Email | Brevo HTTP API (magic links + admin notifications; SMTP fallback) |
| Testing | PHPUnit (76 tests / 190 assertions) + Vitest (6 tests) |
| Hosting | Render (docker runtime) |

---

## Project Structure

```
├── frontend/              # Vue 3 + TS public SPA (landing + login + newsletter)
│   └── src/
│       ├── api/           # Axios client (same-origin)
│       ├── components/ui/ # Button, Card*, Input, Toast
│       ├── composables/   # useToast, useDarkMode
│       ├── features/      # landing/, auth/ (LoginView), landing/LandingView
│       └── router/        # /, /login, catch-all
├── src/                   # PHP: Auth, Middleware, Mail, Models, Database, Support
│   ├── Auth/              # MagicLink (HMAC tokens), Totp (RFC 6238)
│   ├── Middleware/        # Auth, CSRF, CORS, RateLimit, SecurityHeaders
│   └── Models/            # Comment, Subscriber, Blog, Category, ActivityLog, ...
├── public/
│   ├── admin/             # login, blogs, edit-blog, categories, comments,
│   │                      # subscribers, analytics, preview, profile, users, activity
│   ├── api/index.php      # REST router + write gate
│   ├── index.php / post.php / subscribe.php / unsubscribe.php
│   └── uploads/           # blog images
├── sql/                   # schema + migrations (Neon)
├── tests/                 # PHPUnit (76 tests)
├── Dockerfile / nginx.conf / render.yaml / php-fpm.conf
└── .env.example
```

---

## Getting Started

### Prerequisites
- PHP 8.4 + Composer (or [Laravel Herd](https://herd.laravel.com))
- PostgreSQL (local) or a [Neon](https://neon.tech) account
- Node.js 20+ for the frontend

### 1. Backend

```bash
composer install
cp .env.example .env          # fill in DB + SMTP + APP_KEY
```

Generate an `APP_KEY` (32 random bytes, `base64:` prefix):

```bash
php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
```

Create the database and apply migrations (the `base_schema` migration creates every table — `ruru_schema.sql` is kept as reference only and is never executed):

```bash
createdb mizzle_backend       # or: psql -U postgres -c "CREATE DATABASE mizzle_backend;"
composer migrate              # runs sql/migrations/*.sql in order, tracked in schema_migrations
composer migrate:status       # list APPLIED / PENDING without applying
```

**Local auth without email:** set `APP_ENV=local` and `DEV_AUTOLOGIN=true` in `.env` — any `/admin/*` page then signs you in instantly as a dev admin (never set either in production). Set `DB_SSLMODE=prefer` in `.env` for local Postgres.

For real magic-link email, make sure an admin row has an email address:

```sql
UPDATE admins SET email = 'you@example.com' WHERE username = 'admin';
```

### 2. Frontend

```bash
cd frontend
npm install
npm run dev        # http://localhost:3000 — proxies /api + /admin to the PHP backend
```

### 3. PHP site

```bash
http://php-blog-backend-project.test   # Herd virtual host
```

---

## Neon + Render Migration

Render links a **Neon** Postgres via `DATABASE_URL`. **Migrations now run automatically** on every deploy: the Docker start command runs `php bin/migrate.php` before the app boots. Files in `sql/migrations/` are applied in filename order, each exactly once (tracked in `schema_migrations`), inside a transaction.

To add a new schema change: drop a file in `sql/migrations/` named `<YYYY_MM_DD>_<description>.sql`, then push to `main`. Render rebuilds and the migration applies itself before the new version goes live.

**Manual run against Neon (if you need to apply without a deploy):**

**Option A — Neon SQL Editor (easiest):**
1. [console.neon.tech](https://console.neon.tech) → your project → **SQL Editor**.
2. Paste the migration file's contents → **Run**.

**Option B — `composer migrate` pointed at Neon:**
```bash
DATABASE_URL="postgresql://user:password@host:dbname?sslmode=require" composer migrate
```
Use the port **5432** (direct) connection string, not the `-pooler` one, for DDL.

**Option C — Render Shell:**
Render dashboard → service → **Shell** → `psql "$DATABASE_URL"` and paste the SQL.

Verify: `SELECT count(*) FROM auth_sessions;` and `SELECT count(*) FROM login_rate_limits;` (both must succeed without error).

---

## Testing

```bash
vendor/bin/phpunit --testdox
```

76 tests / 190 assertions (3 skipped are live-API tests that need a running server). Covers CRUD lifecycles, SQL-injection safety, escaping, CSRF/session hardening, upload validation, the auth-security suite (single-use magic links, tampered-token rejection, RFC 6238 TOTP vectors, `auth_sessions` revocation), the invitations table (lifecycle, rejection, uniqueness), auto-provisioning (account creation, existing-account reuse, unique usernames), per-email/per-IP magic-link throttles, the **comment workflow** (pending → approve → delete, invalid input rejected), and the **subscriber workflow** (subscribe → dedupe → unsubscribe, invalid email rejected).

The frontend has its own gate:

```bash
cd frontend
npm test              # Vitest (6 tests)
npm run lint          # ESLint
npx prettier --check "src/**/*.{ts,vue}"
npm run build         # production bundle (also gated in CI)
```

---

## Deployment

Pushing to `main` triggers an auto-rebuild on Render. Set in the dashboard (never commit secrets):

| Variable | Value |
|----------|-------|
| `DATABASE_URL` | Auto-set by the Neon integration |
| `APP_KEY` | `base64:<32 random bytes>` (Render can auto-generate) |
| `BREVO_API_KEY` | Brevo Transactional Email API key (used for all mail; SMTP is blocked on Render's free tier) |
| `MAIL_FROM_ADDRESS` | A Brevo-**verified** sender (e.g. `karokirichard522@gmail.com`) |
| `MAIL_PASSWORD` | Brevo SMTP key (fallback only when no API key) |
| `APP_URL` | `https://<your-service>.onrender.com` (used to build magic-link URLs) |

`render.yaml` pins the rest (`MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_FROM_*`, `MAGIC_LINK_TTL=600`, `ADMIN_NOTIFICATION_EMAILS`, health check path). `MAIL_FROM_ADDRESS` **must be a sender verified in your Brevo account** — if it isn't, Brevo silently rejects every email while the API still returns 200 (set the app's default to a verified sender). When a brand-new account is auto-provisioned via magic-link sign-in, the app emails every address in `ADMIN_NOTIFICATION_EMAILS` (comma-separated) so you know about new users; leave it empty to disable. `DATABASE_URL` may include `?sslmode=require&channel_binding=require` — both params are now honoured.

**Order of operations for a fresh deploy:** deploy code → migrations auto-run on boot → set secrets → sign in via magic link.

---

## Auth & Security Model

1. **Magic link** → `POST /api/magic/request` (or the PHP form) sends a signed, 10-minute link. The same generic success message is returned whether or not the email is registered (no account enumeration).
2. **Single-use consume** → clicking the link atomically marks it used; a second click gets **401 "already used"**.
3. **2FA (optional)** → if `totp_secret` is set, a TOTP challenge page appears *before* any session is created.
4. **Server-side session** → `Auth::registerSession` rotates the PHP session ID and records `auth_sessions`; `Auth::check` validates every request against it (revoked/expired sessions fail closed).
5. **Roles** → enforced per-endpoint (API write gate) and per-page (PHP admin).
6. **Sign-up** → any email can create an account at `/signup.php` or by entering their email on the login page. If no account exists, one is auto-created (`editor` role) and a sign-in link is emailed immediately — no admin approval needed.

See [IMPLEMENTATION.md](IMPLEMENTATION.md) for the full flow and [DESIGN.md](DESIGN.md) for the architecture.

---

## API Reference (summary)

| Endpoint | Methods | Auth | Purpose |
|----------|---------|------|---------|
| `/api/index.php?action=blogs` | GET | Public | List / search / filter / paginate |
| `/api/index.php?action=blogs` | POST, PUT | admin, editor | Create / update |
| `/api/index.php?action=blogs` | DELETE | admin | Delete |
| `/api/index.php?action=categories` | GET | Public | List |
| `/api/index.php?action=categories` | POST, PUT | admin, editor | Create / update |
| `/api/index.php?action=categories` | DELETE | admin | Delete |
| `/api/index.php?action=health` | GET | Public | Status |
| `/api/index.php?action=upload` | POST | admin, editor | Image upload |
| `/api/index.php?action=magic/request` | POST | Public | Send magic link |
| `/api/index.php?action=signup-request` | POST | Public | Create an account & send sign-in link |
| `/api/index.php?action=newsletter` | POST | Public | Subscribe an email to the newsletter |
| `/api/index.php?action=profile` | GET, PUT | Authenticated | Read / update profile |

Path-style routing (`/api/blogs`, `/api/blogs/1`) is supported too.

---

## License

Private project — see the repository owner for usage terms.

---

## Documentation

- [DESIGN.md](DESIGN.md) — System design & architecture, security model, data model, design system.
- [IMPLEMENTATION.md](IMPLEMENTATION.md) — Implementation details of auth, roles, and migrations.