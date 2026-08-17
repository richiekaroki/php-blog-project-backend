# System Design & Architecture

> **WAM Blog** — a passwordless, role-aware blog platform: PHP 8.4 backend (Nginx + PHP-FPM, Docker), Vue 3 public SPA, Neon PostgreSQL, Brevo HTTP API, hosted on Render. Readers comment and subscribe; staff moderate, analyse, and preview in a single origin.

---

## 1. System Overview

One Docker container serves the public Vue SPA **and** the PHP admin/API from a single Nginx instance — same origin, no CORS in production. The database is managed Neon PostgreSQL; all mail (magic links, admin alerts, new-post newsletters) goes through the Brevo HTTP API (SMTP is only a fallback — its ports are blocked on Render's free tier).

```
                         ┌────────────────────────────────────────────┐
   Browser ─────────────▶│  Render: php-blog-backend (Docker)         │
                         │                                            │
                         │  ┌──────────────┐   ┌──────────────────┐   │
                         │  │   Nginx :8080 │──▶│  PHP-FPM 8.4      │   │
                         │  │              │   │  (public/*.php)   │   │
                         │  │  /  SPA dist │   └─────────┬────────┘   │
                         │  │  /api/*      │             │            │
                         │  │  /admin/*    │             ▼            │
                         │  │  /uploads/*  │   ┌──────────────────┐   │
                         │  └──────────────┘   │ PDO (pgsql)      │   │
                         │                     └─────────┬────────┘   │
                         └────────────────────────────────┴───────────┘
                                                              │
                                   ┌──────────────────────────┼───────────┐
                                   ▼                          ▼           ▼
                          ┌──────────────┐            ┌────────────┐  ┌──────────┐
                          │ Neon         │            │ Brevo API  │  │ APP_KEY  │
                          │ PostgreSQL   │            │ (magic     │  │ signing  │
                          │ (pgsql)      │            │  links,    │  │ HMAC)    │
                          │              │            │  news-     │  │          │
                          │ blogs,       │            │  letters,  │  │          │
                          │ categories,  │            │  alerts)   │  │          │
                          │ comments,    │            └────────────┘  └──────────┘
                          │ subscribers, │
                          │ admins,      │
                          │ sessions,    │
                          │ audit log    │
                          └──────────────┘
```

**Key properties:**
- **One origin, one deployable** — `nginx.conf` maps `/` to the built SPA, `/api/`, `/admin/`, `/uploads/` and `.php` to the PHP backend.
- **Single-container app** — multi-stage Dockerfile: Node builds `frontend/dist`, PHP-FPM runs the backend, Nginx fronts both. Migrations run at boot, before traffic.
- **Reads public, writes guarded** — the API and PHP pages share one auth stack: session registry + role guard + CSRF + rate limiting.

---

## 2. Request Lifecycle

| Path | Flow |
|------|------|
| `GET /` (public site) | Nginx `try_files` → `frontend/dist/index.html` → Vue Router → `LandingView` fetches blogs/categories from `/api/index.php?action=blogs`. |
| `GET /post.php?id=N` | Server-rendered article + **approved** comments + comment form (honeypot `website`, CSRF, rate limit 5/15 min/IP). |
| `POST /subscribe.php` | Server-rendered newsletter form: CSRF → honeypot check → rate limit → `Subscriber::subscribe` → redirect back with `?subscribed=1` / `?subscribe_error=1`. |
| `GET /unsubscribe.php?token=…` | One-click opt-out: `token` removes the row, renders a confirmation page. |
| `POST /api/index.php?action=newsletter` | Public newsletter sign-up (JSON body) — `Subscriber::subscribe`, used by the SPA landing form. |
| `POST /api/magic/request` | Public → validate email → auto-provision an account (editor) if none exists → create signed token + send Brevo email. |
| `GET /admin/login.php?action=magic&token=…` | Verify HMAC → **single-use consume** → optional TOTP challenge → `Auth::registerSession` → redirect to `blogs.php`. |
| `GET /api/index.php?action=blogs` (public) | Read path — no auth. Paginated, searchable, filtered. |
| `POST/PUT/DELETE /api/*` | **Write gate**: `Auth::startSession` + `isSessionValid` + per-endpoint role (`admin`/`editor`/`viewer`). |
| `GET /admin/*.php` | `Auth::check()` → server-side session registry validation → role-cached; CRUD pages render role-appropriate UI. |
| `POST /admin/comments.php` | Approve/delete a comment — CSRF-checked, `admin`/`editor` only. |
| `POST /admin/preview.php` | Live-preview renderer — CSRF-checked, `admin`/`editor` only; renders markdown → HTML, persists nothing. |

---

## 3. Component Architecture

### 3.1 Frontend (Vue 3 + TypeScript + Vite)

Public-only SPA (admin is PHP-rendered).

| Path | Role |
|------|------|
| `frontend/src/router/index.ts` | `/` (landing), `/login`, catch-all → redirect `/`. |
| `features/landing/LandingView.vue` | Public blog feed: hero, featured posts, categories, dark-mode toggle, **newsletter form**, **Enter the blog** CTA. |
| `features/auth/LoginView.vue` | Passwordless email form → `POST /api/magic/request` → "Check your inbox" (10 min). |
| `features/landing/GetStartedModal.vue` | Sign-up request modal. |
| `components/ui/*` | shadcn-vue-style primitives (Button, Card*, Input, Toast). |
| `api/client.ts` | Axios, `baseURL: ''`, `withCredentials: true` (same-origin). |
| `composables/` | `useToast`, `useDarkMode`. |
| `test/`, `*.test.ts` | Vitest + @vue/test-utils: Button primitives + LandingView (newsletter success/error), wired into `npm test`. |

**No Pinia, no Vue-admin pages, no radix-vue** — the lean SPA talks to the PHP API only, and its quality gate (ESLint + Prettier + Vitest + build) runs in CI.

### 3.2 Backend (PHP 8.4, PSR-4 `App\`)

| Namespace / file | Responsibility |
|------------------|----------------|
| `src/Database/Connection.php` | Singleton PDO (pgsql); reads `DATABASE_URL` (honours `sslmode`/`channel_binding`) or `DB_*` + `DB_SSLMODE`. |
| `src/Auth/MagicLink.php` | Stateless HMAC-SHA256 token create/verify + atomic single-use consume. |
| `src/Auth/Totp.php` | RFC 6238 TOTP (no deps) — enroll, verify, provisioning URI. |
| `src/Middleware/Auth.php` | Hardened sessions, server-side registry, role guard, logout/revoke, dev auto-login. |
| `src/Middleware/CSRF.php` | Per-session token init/verify. |
| `src/Middleware/CORS.php` | Dev-only CORS (local Vite). |
| `src/Middleware/SecurityHeaders.php` | Security headers on PHP responses. |
| `src/Middleware/RateLimit.php` | DB-backed per-IP / per-email buckets (hashed keys). |
| `src/Mail/Mailer.php` | Brevo HTTP API sender (HTML + plain text); SMTP fallback when no `BREVO_API_KEY`. |
| `src/Models/ActivityLog.php` | Append-only audit log. |
| `src/Models/Invitation.php` | Auto-provision accounts + notify admins. |
| `src/Models/Comment.php` | Validated, moderated reader comments (pending → approved). |
| `src/Models/Subscriber.php` | Newsletter list, token-based unsubscribe, notify-on-publish. |
| `public/admin/*.php` | Server-rendered admin (login, blogs, edit-blog, categories, comments, subscribers, analytics, preview, profile, users, activity). |
| `public/api/index.php` | Single-file REST router with centralized write-gate + DB-backed rate limit. |
| `public/index.php`, `public/post.php`, `public/subscribe.php`, `public/unsubscribe.php` | Public PHP pages (bot-readable SSR, comment/newsletter forms). |

### 3.3 Infrastructure

| Layer | Technology |
|-------|-----------|
| Container | `Dockerfile` multi-stage (node:22-alpine → php:8.4-fpm-alpine + nginx) |
| Web server | Nginx (`nginx.conf`) — SPA, PHP-FPM `127.0.0.1:9000`, uploads, gzip, security headers, 30-day immutable asset cache |
| Runtime config | `php-fpm.conf` (`clear_env = no` so Nginx-injected env vars reach PHP) |
| Deploy | `render.yaml` (free web service, docker runtime, `healthCheckPath=/api/index.php?action=health`); auto-deploy from `main` |
| CI | GitHub Actions — job 1: Postgres 17 service → create DB → migrate → PHPUnit (`DB_SSLMODE=prefer`) → `php -l`; job 2: Node 22 → npm ci → ESLint → Prettier → Vitest → build |
| Secrets | `APP_KEY` (auto-generated on Render), `BREVO_API_KEY` (primary mail), `MAIL_PASSWORD` (SMTP fallback, `sync: false` — never committed) |

---

## 4. Security Architecture

Layered defense-in-depth:

### 4.1 Identity & sessions
- **Passwordless magic links** signed with `APP_KEY` (HMAC-SHA256), 10-min TTL, delivered in the URL **fragment** so they never hit logs/referrers.
- **Single-use links** — `magic_link_uses(token_hash PRIMARY KEY)` + `INSERT … ON CONFLICT DO NOTHING` makes redemption atomic. Stored hash, never raw token.
- **Optional TOTP 2FA** — enforced before any session exists; enrollment requires proof-of-possession; disable requires the current code.
- **Server-side session registry** (`auth_sessions`) — a row per login keyed on `sha256(session_id())`; revoked/expired rows fail closed; ID rotated on login (fixation defense).
- **Session hardening** — `HttpOnly`, `SameSite=Lax`, `use_strict_mode=1`, `Secure` on HTTPS.

### 4.2 Request defenses
- **CSRF** — per-session token on every state-changing form and API action.
- **Rate limiting** — DB-backed, keyed on a SHA-256 hash of client IP: magic-request 5/15 min, 2FA 5/15 min, comments 5/15 min, newsletter 5/15 min, global API 100 req/min/IP. Survives restarts; works across replicas.
- **Honeypot field** — every public comment/newsletter form carries a hidden `website` input; auto-filling bots are silently ignored.
- **Moderation quarantine** — comments land `pending` and never render publicly until an admin/editor approves them.
- **Content-Type enforcement** — POST/PUT must be `application/json` (415 otherwise).
- **Input validation** — `FILTER_VALIDATE_EMAIL`, parameterized PDO everywhere (SQLi-safe), output `htmlspecialchars` (XSS-safe), `hash_equals` for all secret comparisons.

### 4.3 Transport & config
- Nginx: gzip, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, deny `/.env`, `/.git`, `/composer.*`.
- Uploads: 10 MB cap, `getimagesize` validation, random filenames.
- No secrets in the repo; `render.yaml` only embeds non-secret values.

---

## 5. Data Model

### 5.1 Core tables
| Table | Purpose | Notable columns |
|-------|---------|-----------------|
| `admins` | Accounts | `username`, `email`, `role` (`admin`/`editor`/`viewer`), `totp_secret` |
| `blogs` | Posts | `title`, `content`, `image`, `category_id`, `status` (`published`/`draft`), `views`, `created_at` |
| `categories` | Taxonomy | `name` UNIQUE, `description` |
| `comments` | Reader comments | `blog_id` FK→blogs (CASCADE), `author_name`, `author_email`, `content`, `status` (`pending`/`approved`), `user_ip` |
| `subscribers` | Newsletter list | `email` UNIQUE, `token` (unsubscribe secret), `created_at` |
| `invitations` | Legacy sign-up requests | Kept for compatibility; new sign-ups auto-provision directly |
| `activity_log` | Audit log | `action`, `entity_type`, `entity_id`, `details` JSONB, `user_ip`, `user_agent` |

### 5.2 Auth / rate-limit tables (migrations `2026_08_13_magic_link_security`, `2026_08_14_login_rate_limits`)
```sql
magic_link_uses   ( token_hash PK, email, used_at )       -- single-use tokens, append-only
auth_sessions     ( id, admin_id FK→admins CASCADE,
                    session_token_hash UNIQUE, ip, user_agent,
                    expires_at, revoked_at, created_at )
login_rate_limits ( bucket, ip_hash, attempt_count,       -- keyed on sha256(bucket|ip);
                    window_start )                          -- raw IPs never stored
```
- `auth_sessions` powers the device list, "sign out other devices", and instant revocation.
- `comments` / `subscribers` are plain rows indexed for the hot paths: moderation (`blog_id, status`), the public approved list, and token lookups on unsubscribe.

### 5.3 Migrations
Applied in filename order by `bin/migrate.php` (auto-run on deploy and via `composer migrate`): `2026_08_12_base_schema` (core tables) → `2026_08_13_create_invitations` → `2026_08_13_magic_link_security` → `2026_08_14_drop_admins_password` → `2026_08_14_login_rate_limits` → `2026_08_15_add_blogs_created_at` → `2026_08_15_add_blogs_status` → `2026_08_16_add_blogs_views` → `2026_08_16_add_comments` → `2026_08_16_add_subscribers` → `2026_add_admin_email`. All idempotent (`IF NOT EXISTS`), each runs exactly once per database, tracked in `schema_migrations`.

---

## 6. API Surface

| Endpoint | Methods | Auth | Purpose |
|----------|---------|------|---------|
| `/api/index.php?action=blogs` | GET | Public | List/search/filter/paginate blogs |
| `/api/index.php?action=blogs` | POST/PUT | admin/editor | Create/update |
| `/api/index.php?action=blogs` | DELETE | admin | Delete |
| `/api/index.php?action=categories` | GET | Public | List categories |
| `/api/index.php?action=categories` | POST/PUT | admin/editor | Create/update |
| `/api/index.php?action=categories` | DELETE | admin | Delete |
| `/api/index.php?action=newsletter` | POST | Public | Subscribe an email (rate-limited 5/15 min; duplicates no-op) |
| `/api/index.php?action=health` | GET | Public | DB + uptime status (Render health check) |
| `/api/index.php?action=upload` | POST | admin/editor | Image upload (multipart) |
| `/api/index.php?action=activity` | GET | — | Activity feed |
| `/api/index.php?action=magic/request` | POST | Public | Send magic link |
| `/api/index.php?action=signup-request` | POST | Public | Create an account & send sign-in link |
| `/api/index.php?action=profile` | GET/PUT | Authenticated | Profile read/update |

Path routing (`/api/blogs/1`) and query routing (`?action=blogs&id=1`) are both supported. All responses carry a `X-Request-ID`.

---

## 7. Deployment Architecture

- **Render** (docker runtime, free plan) rebuilds from `main` automatically.
- **Multi-stage Docker build**: `npm ci && npm run build` → image copies `frontend/dist` into the PHP-FPM image → nginx serves it.
- **Neon** PostgreSQL linked via Render's database integration (`DATABASE_URL`).
- **Migrations auto-run on boot** — `bin/migrate.php` runs before the app starts on every deploy (Render free tier has no `preDeployCommand`).
- **CI guards every push** — the PHP job spins up Postgres 17, creates the database, applies migrations, then runs the full suite with `DB_SSLMODE=prefer` (CI Postgres has no SSL); the frontend job runs lint, format check, tests, and the build.
- **Env config**: `render.yaml` pins non-secret values; `APP_KEY` is generated; `BREVO_API_KEY` / `MAIL_PASSWORD` are set in the dashboard.
- **Health check**: `/api/index.php?action=health` → 200 + `database: connected`.

---

## 8. Design System

One token set shared between the Vue SPA and the server-rendered PHP pages (custom CSS variables mirror the SPA tokens).

### 8.1 Color Palette

Light theme:

| Variable | Hex | Purpose |
|---------|------|---------|
| `--color-background` | `#FBF9F1` | Page background |
| `--color-foreground` | `#2E2910` | Primary text |
| `--color-card` | `#FFFFFF` | Card background |
| `--color-primary` | `#2C5745` | Forest-green brand |
| `--color-secondary` / `--color-warm-cream` | `#EBE3A7` | Warm cream |
| `--color-accent` | `#EB7D00` | Warm orange accent |
| `--color-destructive` | `#C53030` | Red error |
| `--color-muted` | `#F5F0DC` | Muted parchment bg |

Dark theme: `#1A1708` bg, `#EBE3A7` fg, `#252010` card, `#3D7A63` primary, `#FF9A2E` accent, `#E53E3E` destructive.

### 8.2 Typography
- **Headings:** Fraunces (self-hosted, variable serif) — the editorial voice.
- **Body:** Source Sans 3 (self-hosted sans), 300–700, base 16px.
- Scale: 10–28px body/headings; `clamp(2rem, 4vw, 2.75rem)` large titles. No CDN — fonts ship in the image.

### 8.3 Spacing & layout
- Radius tokens: `--radius-sm/md/lg/xl` (4/6/8/12) + `2xl` (16px).
- Layout: admin sidebar 260px, content max-width 1100px, header 64px.
- Shadows: card `0 1px 3px rgba(0,0,0,.08)`, hover `0 12px 24px rgba(0,0,0,.1)`, modal `0 20px 60px rgba(0,0,0,.2)`.

### 8.4 Dark mode
- Vue: `useDarkMode` composable + localStorage + `prefers-color-scheme`.
- PHP pages: `.dark` class on `<html>` with inline overrides.

### 8.5 Component design
- Vue primitives from shadcn-vue-style components (`Button`, `Card*`, `Input`, `Toast`).
- PHP admin pages use hand-rolled CSS mirroring the same tokens (including comment cards, filter tabs, analytics bars, the live-preview iframe, and the pending-moderation badge).
- Full token reference: `frontend/src/style.css`.