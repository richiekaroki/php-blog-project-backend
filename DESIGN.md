# System Design & Architecture

> **WAM Blog** — a passwordless, role-aware blog platform: PHP 8.4 backend (Nginx + PHP-FPM, Docker), Vue 3 public SPA, Neon PostgreSQL, Brevo SMTP, hosted on Render.

---

## 1. System Overview

A single Docker container serves both the public Vue SPA and the PHP admin/API from one Nginx instance, so everything is same-origin (no CORS in production). The database is a managed Neon PostgreSQL instance; magic-link emails go through Brevo SMTP.

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
                          ┌──────────────┐           ┌────────────┐  ┌──────────┐
                          │ Neon         │           │ Brevo SMTP │  │ APP_KEY  │
                          │ PostgreSQL   │           │ (magic     │  │ signing  │
                          │ (pgsql)      │           │  links)    │  │ HMAC)    │
                          └──────────────┘           └────────────┘  └──────────┘
```

**Key properties:**
- **One origin, one deployable** — nginx.conf maps `/` to the built SPA, `/api/`, `/admin/`, `/uploads/` and `.php` to the PHP backend. No CORS headers needed in production.
- **Single-container app** — multi-stage Dockerfile: Node builds `frontend/dist`, PHP-FPM runs the backend, Nginx fronts both.

---

## 2. Request Lifecycle

| Path | Flow |
|------|------|
| `GET /` (public site) | Nginx `try_files` → `frontend/dist/index.html` → Vue Router → `LandingView` fetches blogs/categories from `/api/index.php?action=blogs`. |
| `POST /api/magic/request` | Public → validate email → if admin exists, create signed token + send Brevo email. Same response either way (no account leak). |
| `GET /admin/login.php?action=magic&token=…` | Verify HMAC → **single-use consume** → optional TOTP challenge → `Auth::registerSession` → redirect to `blogs.php`. |
| `GET /api/index.php?action=blogs` (public) | Read path — no auth. Paginated, searchable, filtered. |
| `POST/PUT/DELETE /api/*` | **Write gate**: `Auth::startSession` + `isSessionValid` + per-endpoint role (`admin`/`editor`/`viewer`). |
| `GET /admin/*.php` | `Auth::check()` → server-side session registry validation → role-cached; CRUD pages render role-appropriate UI. |

---

## 3. Component Architecture

### 3.1 Frontend (Vue 3 + TypeScript + Vite)

Public-only SPA (the admin SPA views were removed; admin is PHP-rendered).

| Path | Role |
|------|------|
| `frontend/src/router/index.ts` | `/` (landing), `/login`, catch-all → redirect `/`. |
| `views/LandingView.vue` | Public blog feed: hero, featured posts, categories, dark-mode toggle, **Enter the blog** CTA. |
| `views/LoginView.vue` | Passwordless email form → `POST /api/magic/request` → "Check your inbox" (10 min). |
| `features/landing/GetStartedModal.vue` | Sign-up request modal. |
| `components/ui/*` | shadcn-vue-style primitives (Button, Card*, Input, Toast). |
| `api/client.ts` | Axios, `baseURL: ''`, `withCredentials: true` (same-origin). |
| `composables/` | `useToast`, `useDarkMode`. |

**No Pinia, no Vue-admin pages, no radix-vue** — the lean SPA talks to the PHP API only.

### 3.2 Backend (PHP 8.4, PSR-4 `App\`)

| Namespace / file | Responsibility |
|------------------|----------------|
| `src/Database/Connection.php` | Singleton PDO (pgsql), reads `DATABASE_URL` or `DB_*`. |
| `src/Auth/MagicLink.php` | Stateless HMAC-SHA256 token create/verify + atomic single-use consume. |
| `src/Auth/Totp.php` | RFC 6238 TOTP (no deps) — enroll, verify, provisioning URI. |
| `src/Middleware/Auth.php` | Hardened sessions, server-side registry, role guard, logout/revoke. |
| `src/Middleware/CSRF.php` | Per-session token init/verify. |
| `src/Middleware/CORS.php` | Dev-only CORS (local Vite). |
| `src/Middleware/SecurityHeaders.php` | Security headers on PHP responses. |
| `src/Mail/Mailer.php` | Brevo SMTP sender (HTML + plain text). |
| `src/Models/ActivityLog.php` | Append-only audit log. |
| `public/admin/*.php` | Server-rendered admin (login, blogs, categories, edit-blog, profile). |
| `public/api/index.php` | Single-file REST router with centralized write-gate + rate limit. |
| `public/index.php`, `public/post.php` | Public blog pages (PHP-rendered fallback / SSR). |

### 3.3 Infrastructure

| Layer | Technology |
|-------|-----------|
| Container | `Dockerfile` multi-stage (node:22-alpine → php:8.4-fpm-alpine + nginx) |
| Web server | Nginx (`nginx.conf`) — SPA, PHP-FPM `127.0.0.1:9000`, uploads, gzip, security headers |
| Runtime config | `php-fpm.conf` (`clear_env = no` so Nginx-injected env vars reach PHP) |
| Deploy | `render.yaml` (free web service, docker runtime, `healthCheckPath=/api/index.php?action=health`) |
| Secrets | `APP_KEY` (auto-generated on Render), `MAIL_PASSWORD` (`sync: false` — set manually, never committed) |

---

## 4. Security Architecture

Layered defense-in-depth:

### 4.1 Identity & sessions
- **Passwordless magic links** signed with `APP_KEY` (HMAC-SHA256), 10-min TTL.
- **Single-use links** — `magic_link_uses(token_hash PRIMARY KEY)` + `INSERT … ON CONFLICT DO NOTHING` makes redemption atomic. Stored hash, never raw token.
- **Optional TOTP 2FA** — enforced before any session exists; enrollment requires proof-of-possession (confirm code) before secret persistence; disable requires current code.
- **Server-side session registry** (`auth_sessions`) — a row per login keyed on `sha256(session_id())`; revoked/expired rows fail closed. `registerSession` rotates the PHP session ID (fixation defense).
- **Session hardening** — `HttpOnly`, `SameSite=Lax`, `use_strict_mode=1`, `Secure` on HTTPS.

### 4.2 Request defenses
- **CSRF** — per-session token on every state-changing form and API action.
- **Rate limiting** — DB-backed, keyed on a SHA-256 hash of the client IP: magic-request 5/15 min and 2FA 5/15 min (`login_rate_limits`) + global API 100 req/min/IP (temp file) with `X-RateLimit-*` headers.
- **Content-Type enforcement** — POST/PUT must be `application/json` (415 otherwise).
- **Input validation** — email `FILTER_VALIDATE_EMAIL`, parameterized PDO everywhere (SQLi-safe), output `htmlspecialchars` (XSS-safe), `hash_equals` for all secret comparisons.

### 4.3 Transport & config
- Nginx: gzip, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, deny `/.env`, `/.git`, `/composer.*`.
- Uploads: 10 MB cap, `getimagesize` validation, random filenames.
- No secrets in the repo; `.env.example` documents placeholders; `render.yaml` only embeds non-secret values.

---

## 5. Data Model

### 5.1 Core tables
| Table | Purpose | Notable columns |
|-------|---------|-----------------|
| `admins` | Accounts | `username`, `email`, `role` (`admin`/`editor`/`viewer`), `totp_secret` |
| `roles` / `user_roles` | Legacy role join | Reserved for future RBAC migration |
| `blogs` | Posts | `title`, `slug`, `content`, `category_id`, `image`, `published`, timestamps |
| `categories` | Taxonomy | `name`, `slug` |
| `invitations` | Sign-up requests | `email` UNIQUE, `token`, `role`, `expires_at`, `accepted_at` |
| `activity_log` | Audit log | `actor_id`, `event`, `category`, `metadata` |

### 5.2 Auth/session tables (migration `2026_08_13_magic_link_security`)
```sql
magic_link_uses ( token_hash PK, email, used_at )      -- single-use tokens
auth_sessions   ( id, admin_id FK→admins ON DELETE CASCADE,
                  session_token_hash UNIQUE, ip, user_agent,
                  expires_at, revoked_at, created_at )
login_rate_limits ( bucket PK-part, ip_hash PK-part,     -- IP rate limiting
                    attempt_count, window_start )
```
- `magic_link_uses` is append-only; rows are never reused.
- `auth_sessions` enables global device list + "sign out other devices" + instant revocation.
- `login_rate_limits` (migration `2026_08_14_login_rate_limits`) stores attempt counters keyed on `sha256(bucket|ip)` so cookies can't reset them; IPs are not stored raw.

### 5.3 Migrations
Apply in order: `ruru_schema.sql` → `2026_add_admin_email.sql` → `2026_08_13_create_invitations.sql` → `2026_08_13_magic_link_security.sql` → `2026_08_14_drop_admins_password.sql` → `2026_08_14_login_rate_limits.sql`. All new objects are idempotent (`IF NOT EXISTS`), safe to re-run.

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
| `/api/index.php?action=health` | GET | Public | DB + uptime status |
| `/api/index.php?action=upload` | POST | admin/editor | Image upload (multipart) |
| `/api/index.php?action=activity` | GET | — | Activity feed |
| `/api/index.php?action=magic/request` | POST | Public | Send magic link |
| `/api/index.php?action=signup-request` | POST | Public | Request an account |
| `/api/index.php?action=profile` | GET/PUT | Authenticated | Profile read/update |

Path routing (`/api/blogs`, `/api/blogs/1`) and query routing (`?action=blogs&id=1`) are both supported.

---

## 7. Deployment Architecture

- **Render** (docker runtime, free plan) rebuilds from `main` automatically.
- **Multi-stage Docker build**: `npm ci && npm run build` → image copies `frontend/dist` into the PHP-FPM image → nginx serves it.
- **Neon** PostgreSQL is linked via Render's database integration (`DATABASE_URL` env var).
- **Migrations are manual**: apply schema to Neon using the direct (unpooled) connection; they do not run on deploy.
- **Env config**: `render.yaml` pins non-secret values (`APP_URL`, SMTP host/port/user, `MAGIC_LINK_TTL=600`); `APP_KEY` is generated and `MAIL_PASSWORD` is set in the dashboard.
- **Health check**: `/api/index.php?action=health` → 200 + `database: connected`.

---

## 8. Design System

The design system is shared between the Vue SPA and the server-rendered PHP admin/login pages (custom CSS variables in `<style>` blocks mirror the SPA tokens).

### 8.1 Color Palette

Light theme:

| Variable | Hex | Purpose |
|---------|------|---------|
| `--color-background` | `#FBF9F1` | Page background |
| `--color-foreground` | `#2E2910` | Primary text |
| `--color-card` | `#FFFFFF` | Card background |
| `--color-primary` | `#2C5745` | Green brand |
| `--color-secondary` / `--color-warm-cream` | `#EBE3A7` | Warm cream |
| `--color-accent` | `#EB7D00` | Warm orange accent |
| `--color-destructive` | `#C53030` | Red error |
| `--color-muted` | `#F5F0DC` | Muted parchment bg |
| `--color-forest-green` | `#2C5745` | Alias for primary |
| `--color-dark-olive` | `#2E2910` | Alias for foreground |

Dark theme: `#1A1708` bg, `#EBE3A7` fg, `#252010` card, `#3D7A63` primary, `#FF9A2E` accent, `#E53E3E` destructive.

### 8.2 Typography
- **Headings:** Lora (serif), 400–700, clamp-scaled headlines.
- **Body:** Source Sans 3 (sans), 300–700, base 16px.
- Scale: 10–28px body/headings; `clamp(2rem, 4vw, 2.75rem)` large titles.

### 8.3 Spacing & layout
- Radius tokens: `--radius-sm/md/lg/xl` (4/6/8/12) + `2xl` (16px).
- Layout: admin sidebar 260px, content max-width 1100px, header 64px.
- Shadows: card `0 1px 3px rgba(0,0,0,.08)`, hover `0 12px 24px rgba(0,0,0,.1)`, modal `0 20px 60px rgba(0,0,0,.2)`.

### 8.4 Dark mode
- Vue: `useDarkMode` composable + localStorage + `prefers-color-scheme`.
- PHP login: `.dark` class on `<html>` with inline overrides + persisted toggle button.

### 8.5 Component design
- Vue primitives from **shadcn-vue**-style components (`Button`, `Card*`, `Input`, `Toast`).
- PHP admin pages use hand-rolled CSS mirroring the same tokens.
- Full token reference: `frontend/src/style.css`.