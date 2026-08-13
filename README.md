# WAM Blog

[![CI](https://github.com/richiekaroki/php-blog-project-backend/actions/workflows/php.yml/badge.svg)](https://github.com/richiekaroki/php-blog-project-backend/actions/workflows/php.yml)
[![Tests](https://img.shields.io/badge/tests-51%20passed-brightgreen)](#testing)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php)](#)
[![Vue](https://img.shields.io/badge/Vue-3-42b883?logo=vue.js)](#)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-17-4169E1?logo=postgresql)](#)
[![Docker](https://img.shields.io/badge/Docker-multi--stage-2496ED?logo=docker)](#)

A secure, passwordless blog platform: **PHP 8.4 backend**, **Vue 3 public SPA**, **Neon PostgreSQL**, deployed on **Render** as a single Docker container.

**Live:** https://php-blog-backend.onrender.com

| URL | Description |
|-----|-------------|
| [Homepage](https://php-blog-backend.onrender.com) | Public blog — landing page + sign-in |
| [Admin Panel](https://php-blog-backend.onrender.com/admin/login.php) | Passwordless sign-in → manage blogs, categories, profile & sessions |
| [API Health](https://php-blog-backend.onrender.com/api/index.php?action=health) | Status check |

---

## Features

- 🔑 **Passwordless magic links** — HMAC-SHA256 signed tokens, **single-use** (atomic `INSERT … ON CONFLICT`), 10-minute expiry, no account-leaking responses.
- 🛡️ **Optional TOTP 2FA** — RFC 6238, no external dependencies; enrollment requires proof-of-possession, disable requires the current code.
- 🧾 **Server-side session registry** — every login recorded in `auth_sessions`; logout & "sign out other devices" revoke sessions instantly.
- 🚦 **DB-backed IP rate limiting** — magic-link requests, 2FA attempts, and API requests all capped per-minute, keyed on hashed IP (cookies can't reset it).
- 👥 **Role-based access control** — `admin` (full), `editor` (create/edit), `viewer` (read-only), enforced server-side on the API **and** PHP admin pages.
- 🐘 **PostgreSQL + PDO** — parameterized queries throughout, search/filter/pagination, image uploads with validation.
- 🕷️ **SEO-friendly** — crawlers get the fully server-rendered landing page (nginx bot UA detection); humans get the Vue SPA.
- 🔎 **Activity feed** — `activity.php` surfaces sign-ins, 2FA enable/disable, session revocations, and content edits with human-readable labels.
- 🚀 **One-container deploy** — multi-stage Dockerfile builds the Vue SPA and serves it alongside PHP-FPM behind Nginx (same-origin, no CORS in production).
- 📝 **Audit logging** — `activity_log` records sign-ins, 2FA changes, and session revocations.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | Vue 3, TypeScript, Vite, Tailwind CSS 4, shadcn-vue-style UI |
| Backend | PHP 8.4, Nginx + PHP-FPM (Docker) |
| Database | PostgreSQL 17 (Neon, managed) |
| Email | Brevo SMTP (magic links) |
| Testing | PHPUnit (53 tests / 109 assertions) |
| Hosting | Render (docker runtime) |

---

## Project Structure

```
├── frontend/              # Vue 3 + TS public SPA (landing + login)
│   └── src/
│       ├── api/           # Axios client (same-origin)
│       ├── components/ui/ # Button, Card*, Input, Toast
│       ├── composables/   # useToast, useDarkMode
│       ├── features/      # landing GetStartedModal
│       ├── views/         # LandingView, LoginView
│       └── router/        # /, /login, catch-all
├── src/                   # PHP: Auth, Middleware, Mail, Models, Database
│   ├── Auth/              # MagicLink (HMAC tokens), Totp (RFC 6238)
│   └── Middleware/        # Auth, CSRF, CORS, SecurityHeaders
├── public/
│   ├── admin/             # login, blogs, categories, edit-blog, profile
│   ├── api/index.php      # REST router + write gate
│   ├── index.php / post.php
│   └── uploads/           # blog images
├── sql/                   # schema + migrations (Neon)
├── tests/                 # PHPUnit (51 tests)
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

Create the database and apply migrations in order:

```bash
createdb mizzle_backend   # or: psql -U postgres -c "CREATE DATABASE mizzle_backend;"
psql -U postgres -d mizzle_backend \
  -f sql/ruru_schema.sql \
  -f sql/migrations/2026_add_admin_email.sql \
  -f sql/migrations/2026_08_13_create_invitations.sql \
  -f sql/migrations/2026_08_13_magic_link_security.sql
```

Set a real email on an admin row so magic links can be sent:

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

Render links a **Neon** Postgres via `DATABASE_URL`. Schema changes are **manual** — they do not run on deploy. Use the **direct (unpooled)** connection for DDL.

**Option A — Neon SQL Editor (easiest):**
1. [console.neon.tech](https://console.neon.tech) → your project → **SQL Editor**.
2. Paste `sql/migrations/2026_08_13_magic_link_security.sql` → **Run**.
3. Repeat for `2026_08_14_drop_admins_password.sql` and `2026_08_14_login_rate_limits.sql`.

**Option B — `psql` (local):**
```bash
psql "postgresql://user:password@host:dbname?sslmode=require" \
  -f sql/migrations/2026_08_13_magic_link_security.sql
psql "postgresql://user:password@host:dbname?sslmode=require" \
  -f sql/migrations/2026_08_14_drop_admins_password.sql
psql "postgresql://user:password@host:dbname?sslmode=require" \
  -f sql/migrations/2026_08_14_login_rate_limits.sql
```
Neon requires `sslmode=require`. Use the port **5432** (direct) connection string, not the `-pooler` one.

**Option C — Render Shell:**
Render dashboard → service → **Shell** → `psql "$DATABASE_URL"` and paste the SQL.

Verify: `SELECT count(*) FROM auth_sessions;` and `SELECT count(*) FROM login_rate_limits;` (both must succeed without error).

---

## Testing

```bash
vendor/bin/phpunit --testdox
```

51 tests / 99 assertions (3 skipped are live-API tests that need a running server). Covers CRUD lifecycles, SQL-injection safety, escaping, CSRF/session hardening, upload validation, and the auth-security suite: single-use magic links, tampered-token rejection, RFC 6238 TOTP vectors, `auth_sessions` revocation.

---

## Deployment

Pushing to `main` triggers an auto-rebuild on Render. Set in the dashboard (never commit secrets):

| Variable | Value |
|----------|-------|
| `DATABASE_URL` | Auto-set by the Neon integration |
| `APP_KEY` | `base64:<32 random bytes>` (Render can auto-generate) |
| `MAIL_PASSWORD` | Brevo SMTP key |
| `APP_URL` | `https://<your-service>.onrender.com` (used to build magic-link URLs) |

`render.yaml` pins the rest (`MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_FROM_*`, `MAGIC_LINK_TTL=600`, health check path).

**Order of operations for a fresh deploy:** deploy code → apply the latest SQL migration to Neon → set secrets → sign in via magic link.

---

## Auth & Security Model

1. **Magic link** → `POST /api/magic/request` (or the PHP form) sends a signed, 10-minute link. The same generic success message is returned whether or not the email is registered (no account enumeration).
2. **Single-use consume** → clicking the link atomically marks it used; a second click gets **401 "already used"**.
3. **2FA (optional)** → if `totp_secret` is set, a TOTP challenge page appears *before* any session is created.
4. **Server-side session** → `Auth::registerSession` rotates the PHP session ID and records `auth_sessions`; `Auth::check` validates every request against it (revoked/expired sessions fail closed).
5. **Roles** → enforced per-endpoint (API write gate) and per-page (PHP admin).

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
| `/api/index.php?action=signup-request` | POST | Public | Request an account |
| `/api/index.php?action=profile` | GET, PUT | Authenticated | Read / update profile |

Path-style routing (`/api/blogs`, `/api/blogs/1`) is supported too.

---

## License

Private project — see the repository owner for usage terms.

---

## Documentation

- [DESIGN.md](DESIGN.md) — System design & architecture, security model, data model, design system.
- [IMPLEMENTATION.md](IMPLEMENTATION.md) — Implementation details of auth, roles, and migrations.