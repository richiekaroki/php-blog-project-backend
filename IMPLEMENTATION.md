# Implementation Notes: Passwordless Auth, Roles, Security & Editorial Features

As of **17 Aug 2026**, WAM Blog is a passwordless, role-aware platform hardened with **single-use magic links**, **optional TOTP 2FA**, a **revocable server-side session registry**, **role-based access control** (admin / editor / viewer), and a full reader-engagement layer: **moderated comments**, a **newsletter** with token-based unsubscribe, **post analytics**, and an **editor live preview**.

This document describes how the pieces are wired together, the exact request flows, and the security model. See `DESIGN.md` for the architecture overview and `README.md` for quick start / deployment.

---

## 1. Deployment Status

| Layer | Status | Where |
|-------|--------|-------|
| Public site (Vue SPA) | **Served in production** | `frontend/dist` copied into the Docker image; Nginx serves it at `/`. |
| PHP admin | Live | `/admin/login.php`, `blogs.php`, `edit-blog.php`, `categories.php`, `comments.php`, `subscribers.php`, `analytics.php`, `preview.php`, `profile.php`, `users.php`, `activity.php` |
| REST API | Live | `/api/index.php?action=...` (public reads + guarded writes) |
| Database | Neon PostgreSQL 17 | Direct (unpooled) connection for DDL; pooler for app traffic. |
| Email | Brevo HTTP API | Magic links, admin notifications, comment alerts, new-post newsletters (SMTP fallback only when no `BREVO_API_KEY`). |
| CI | GitHub Actions | PHP job (Postgres service, migrate, PHPUnit, lint) + frontend job (ESLint, Prettier, Vitest, build). |

Live URLs: https://php-blog-backend.onrender.com

---

## 2. Authentication Overview

Login is **passwordless only** — there is no username/password form. Flow summary:

```
Email in -> signed magic link emailed -> click link (single-use consume)
         -> if 2FA enabled: TOTP challenge page
         -> server-side session row created (auth_sessions)
         -> redirected to /admin/blogs.php
```

The legacy `admins.password` column has been **dropped** (migration `2026_08_14_drop_admins_password.sql`) — no code path ever read or wrote it.

### 2.1 Key components

| Component | File | Responsibility |
|-----------|------|----------------|
| `App\Auth\MagicLink` | `src/Auth/MagicLink.php` | Create/verify HMAC-SHA256 tokens, atomic single-use consume. |
| `App\Auth\Totp` | `src/Auth/Totp.php` | RFC 6238 TOTP codes/verification (no external deps). |
| `App\Middleware\Auth` | `src/Middleware/Auth.php` | Hardened sessions, server-side validation, logout/revocation, role guard, dev auto-login. |
| `App\Middleware\CSRF` | `src/Middleware/CSRF.php` | Token init/verify for every state-changing form/request. |
| `App\Middleware\RateLimit` | `src/Middleware/RateLimit.php` | DB-backed per-IP / per-email buckets, hashed keys. |
| PHP admin login | `public/admin/login.php` | Magic request, verify, 2FA challenge, session creation, status, logout. |
| PHP admin profile | `public/admin/profile.php` | 2FA enroll/disable, session list, revoke other sessions. |
| API router | `public/api/index.php` | Endpoint routing + per-endpoint auth/role gates. |

---

## 3. Magic Link Tokens

### 3.1 Format

Stateless token, signed with `APP_KEY` using HMAC-SHA256:

```
base64url(JSON{email, exp}) . hex(HMAC-SHA256(base64url(payload), APP_KEY))
```

- **No token stored** at creation time — the link is self-verifying.
- **Lifetime**: `MAGIC_LINK_TTL` seconds. Default **600** (10 min).
- `APP_KEY` may be a raw string or `base64:`-prefixed (Laravel-style) — both decoded in the constructor.
- Links travel in the URL **fragment** (`#magic=...`) and are POSTed back, so they never appear in query strings, server logs, browser history, or Referer headers.

### 3.2 Verification (`MagicLink::verify`)

1. Split on `.` — must be exactly 2 parts.
2. Recompute HMAC and compare with `hash_equals`.
3. Base64-decode payload; must contain non-empty `email` and `exp`.
4. Reject if `exp < now()`.
5. Return lowercased/trimmed email.

### 3.3 Single-use consume (`MagicLink::consume`)

```sql
INSERT INTO magic_link_uses (token_hash, email) VALUES (?, ?)
ON CONFLICT (token_hash) DO NOTHING;
```

- `token_hash` is `sha256(token)` — the raw token is **never** stored.
- The `PRIMARY KEY` on `token_hash` makes check-and-set atomic: concurrent redeems produce exactly one winner.
- `consume()` returns `rowCount() > 0`; the caller rejects with **HTTP 401 "This sign in link has already been used"** if it loses the race.
- Consumption happens **before** the 2FA challenge — a captured link cannot be half-redeemed.

---

## 4. Two-Factor Authentication (TOTP)

`App\Auth\Totp` is a self-contained RFC 6238 implementation:

- HMAC-SHA1, 6 digits, 30-second period, base32 secrets (160-bit / 20 bytes).
- `verify($secret, $code, $window = 1)` accepts ±1 period (90s total) and strips whitespace.
- `provisioningUri()` builds a standard `otpauth://totp/...` URI (manual entry on the profile page — no QR dependency).
- Verified against all RFC 6238 test vectors.

### 4.1 Enrollment (profile.php)

1. `POST action=generate_2fa` → `Totp::generateSecret()` held in `$_SESSION['pending_totp_secret']`; secret + URI shown.
2. User adds it to their authenticator app and submits a code.
3. `POST action=confirm_2fa` → code must verify against the **pending** secret before it is written to `admins.totp_secret`.

The secret is only persisted after proof-of-possession of the authenticator.

### 4.2 Sign-in with 2FA enabled

1. Magic link click → `MagicLink::verify` + `consume` succeed.
2. If `admins.totp_secret` is non-empty, pending identity is stashed (`pending_2fa_email` / `_username` / `_role`) and a standalone challenge page renders.
3. `POST action=verify_2fa` (CSRF-checked) → `Totp::verify` → on success a session is created and pending keys are cleared.
4. **No session is created until 2FA passes.**

### 4.3 Disabling

Requires the **current** TOTP code (`POST action=disable_2fa`), so a stolen session cookie cannot silently remove 2FA.

---

## 5. Server-side Session Registry

### 5.1 Model

| Column | Purpose |
|--------|---------|
| `admin_id` | FK to `admins` (CASCADE delete) |
| `session_token_hash` | `sha256(php session_id())`, `UNIQUE` — never the raw ID |
| `ip`, `user_agent` | Device metadata for the profile page |
| `expires_at` | Default now + 604800s (7 days) |
| `revoked_at` | NULL while active; set on logout/revoke |

### 5.2 Hardened PHP session (`Auth::startSession`)

```php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);      // rejects attacker-set session IDs
if (HTTPS) ini_set('session.cookie_secure', 1);
```

### 5.3 Registration (`Auth::registerSession`)

1. Looks up the admin id by username.
2. `session_regenerate_id(true)` — the pre-auth session is discarded; the fresh ID is what gets registered (fixation defense).
3. Inserts the `auth_sessions` row keyed on the **new** `session_id()`.
4. Rotates `$_SESSION['csrf_token']`.

### 5.4 Validation (`Auth::check` / `isSessionValid`)

Every admin page calls `Auth::check()`, which:

1. Starts the hardened session.
2. Requires `$_SESSION['admin']` set.
3. `isSessionValid()` — `auth_sessions` must contain a matching row where `revoked_at IS NULL` and `expires_at` is in the future. Missing table or missing/revoked/expired row ⇒ **fail closed**.
4. Reloads the admin row by username; unknown user ⇒ logout.
5. Caches `$_SESSION['user_role']`.

The token hash is bound to the **session ID**, so the login session and the registry row move together. `check()` deliberately does **not** regenerate the ID per-request (it would break the registry binding).

### 5.5 Logout & revocation

- `Auth::logout()` — `revoked_at = NOW()` on the current row, clears `$_SESSION`, expires the cookie, destroys the session.
- `Auth::revokeOtherSessions()` — revokes every row for this admin except the current `session_token_hash`.
- Revoked sessions die on the next `check()` — no client-side token to replay.

---

## 6. Roles & Access Control

Roles live on `admins.role` (`admin` | `editor` | `viewer`).

| Capability | admin | editor | viewer |
|-----------|:-----:|:------:|:------:|
| View blogs / categories / profile / analytics | ✅ | ✅ | ✅ |
| Create / edit blogs & categories | ✅ | ✅ | ❌ |
| Delete blogs & categories | ✅ | ❌ | ❌ |
| Moderate comments (approve / delete) | ✅ | ✅ | ❌ |
| Manage subscribers (delete) | ✅ | ✅ | ❌ |
| View subscriber CSV export | ✅ | ✅ | ✅ |
| Use the live preview | ✅ | ✅ | ❌ |
| Upload images | ✅ | ✅ | ❌ |
| Manage 2FA / sessions (own) | ✅ | ✅ | ✅ |

### 6.1 PHP admin pages

- `Auth::check()` gates every page.
- `blogs.php` / `categories.php`: `$canWrite = role in (admin, editor)`, `$canDelete = role === admin`. UI is hidden for viewers; POST/DELETE handlers independently **403** (server-side enforcement, not UI hiding).
- `edit-blog.php`, `comments.php` (approve/delete), `subscribers.php` (delete), `preview.php`: require `admin|editor`, else **403**.
- `analytics.php`: read-only for any signed-in staff.

### 6.2 REST API

The write gate at the top of `public/api/index.php`:

```php
$writeMethods = ['POST', 'PUT', 'DELETE'];
if (in_array($method, $writeMethods) && !$isPublicAction) {
    Auth::startSession();
    if (!isset($_SESSION['admin']) || !Auth::isSessionValid()) {
        sendResponse(401, ['error' => 'Authentication required for write operations']);
    }
    loadApiRole($pdo);   // -> $_SESSION['user_role']
}
```

`$publicActions = ['magic', 'signup-request', 'newsletter']` skip the gate entirely. Per-endpoint `requireApiRole(...)`:

| Endpoint | Method(s) | Role required |
|----------|-----------|---------------|
| `blogs` | POST / PUT | admin, editor |
| `blogs` | DELETE | admin |
| `categories` | POST / PUT | admin, editor |
| `categories` | DELETE | admin |
| `upload` | POST | admin, editor |
| `profile` | GET / PUT | any authenticated admin |
| `blogs`, `categories`, `activity`, `health`, `magic`, `signup-request`, `newsletter` GETs | GET/POST | public |

API responses use the shared `sendResponse` helper (adds `request_id`).

---

## 7. Comments (Moderated)

### 7.1 Submission (`public/post.php`)

The comment form posts to the same page (POST + CSRF). Guard chain:

1. **Honeypot** — hidden `website` field; if filled, the bot is silently dropped (no error, no log).
2. **CSRF** — per-session token via `CSRF::verify`.
3. **Rate limit** — bucket `comment`, 5 submissions per 15 min per IP.

`Comment::create()` validates before insert:

| Field | Rule |
|-------|------|
| `author_name` | required, ≤ 100 chars |
| `author_email` | optional; must be a valid email (≤ 255) if present |
| `content` | required, non-empty |

The comment is inserted as `status = 'pending'` with `user_ip` captured, `comment_created` logged, and every address in `ADMIN_NOTIFICATION_EMAILS` best-effort emailed (post title, commenter, text). The commenter sees a success message; **the comment does not render until approved**.

### 7.2 Moderation (`/admin/comments.php`)

- Tabs **All / Pending / Approved** with stat cards and a pending-count badge in the sidebar (`Comment::countPending()`).
- **Approve / Delete** are POST + CSRF, gated to `admin|editor`.
- Public rendering (`Comment::approvedFor`) filters strictly to `status = 'approved'` — pending rows are never exposed.

### 7.3 Schema

```sql
comments ( id PK, blog_id FK→blogs ON DELETE CASCADE,
           author_name VARCHAR(100) NOT NULL, author_email VARCHAR(255),
           content TEXT NOT NULL, status VARCHAR(20) DEFAULT 'pending',
           user_ip INET, created_at )
-- indexes: (blog_id, status) for moderation; (status, created_at) for the queue
```

---

## 8. Newsletter & Publish Notifications

### 8.1 Subscribe

Two entry points, one model method (`Subscriber::subscribe`):
- **SPA**: `LandingView.vue` → `POST /api/index.php?action=newsletter` (JSON; rate-limited 5/15 min/IP).
- **Server-rendered**: `public/index.php` / `public/post.php` forms → `POST /subscribe.php` (CSRF + honeypot + rate limit) → redirect with `?subscribed=1` or `?subscribe_error=1` for a banner.

`subscribe()` normalises to lowercase, validates with `FILTER_VALIDATE_EMAIL`, and:
- new email → insert with a random 64-char token → `['status' => 'added']`, logs `subscriber_added`;
- existing → `['status' => 'exists']` (idempotent, no duplicate rows — `email UNIQUE`);
- invalid → `null`.

### 8.2 Unsubscribe

Every newsletter email includes a link built by `Subscriber::unsubscribeUrl()` from the stored token: `GET /unsubscribe.php?token=…` → `removeByToken()` → confirmation page. Tokens are unguessable (64 hex chars) and indexed, so opt-out is instant and needs no login.

### 8.3 Notify on publish

Whenever a post is first published (draft → published) — via the blogs list toggle, a create/edit that publishes directly, or the editor's save — `Subscriber::notifyNewPost()` emails every subscriber a "new story" email (title + link + unsubscribe link). **Best-effort**: a send failure is logged (`newsletter_sent`) and never blocks the publish action.

---

## 9. Editor Live Preview

`/admin/preview.php` is a purpose-built render endpoint for the editor:

- **POST + CSRF only**, `admin|editor` only.
- Runs the **same** `renderPostContent()` used on the live site, so what you preview is exactly what readers see.
- Returns a self-contained HTML document linked to `/assets/site.css` (absolute path so it resolves inside the sandboxed iframe via `srcdoc`).
- Guards: content capped at 200 kB; returns 405 on GET and 403 for viewers.
- **Never persists anything** — it is a pure function of the submitted content.

`edit-blog.php` wires it up: a "Live Preview" toggle shows a side pane; the content field's input is debounced (400 ms) and fetched to `preview.php` with the CSRF token; the returned HTML is set as the iframe's `srcdoc`.

---

## 10. Click Paths

### A. Vue public site (dev & production)

1. Landing page → **Enter the blog** → `/login` (`features/auth/LoginView.vue`).
2. Email submitted → `POST /api/magic/request` → account auto-created (editor) if it doesn't exist, link emailed.
3. "Check your inbox — the link expires in 10 minutes."
4. Click the emailed link → `/admin/login.php?action=magic&token=...` → PHP flow takes over (single-use, 2FA, session) → `blogs.php`.
5. Newsletter form → `POST /api/index.php?action=newsletter` → inline success / error.

### B. Public reader

1. Reads a post at `/post.php?id=N`; sees approved comments + the comment form.
2. Submits a comment → pending queue → success banner (form hidden until approved).
3. Subscribes via the footer/sidebar form → `subscribe.php` → banner; or opens the unsubscribe link in any newsletter email → `unsubscribe.php` → confirmation.

### C. PHP admin

1. `/admin/login.php` → email form.
2. `POST magic_email` → CSRF-verified, account auto-created (editor) if new, token created (TTL 600) and emailed.
3. Click link → `?action=magic&token=` → verify + consume → 2FA challenge if enabled → `Auth::registerSession` → `Location: blogs.php`.
4. Moderate pending comments (`comments.php`), manage subscribers (`subscribers.php`), read dashboards (`analytics.php`), and live-preview content while editing (`edit-blog.php` ↔ `preview.php`).
5. `?action=status` returns JSON auth state (for the Vue frontend). `?action=logout` runs `Auth::logout()`.

---

## 11. Database Migrations

Migrations live in `sql/migrations/` and are applied in filename order by `bin/migrate.php`. Each runs exactly once (tracked in `schema_migrations`), inside a transaction, and mirrors `Connection.php`'s config resolution (`DATABASE_URL`, or `DB_*` vars + `DB_SSLMODE`, or a local `.env`).

| File | Purpose |
|------|---------|
| `sql/ruru_schema.sql` | Original hand-written schema (reference only — never executed) |
| `2026_08_12_base_schema.sql` | Core tables: `admins`, `categories`, `blogs`, `activity_log` (+ indexes) |
| `2026_08_13_create_invitations.sql` | `invitations` (legacy sign-up requests; kept for compatibility) |
| `2026_08_13_magic_link_security.sql` | `magic_link_uses`, `auth_sessions`, `admins.totp_secret` |
| `2026_08_14_drop_admins_password.sql` | Drops the legacy `admins.password` column |
| `2026_08_14_login_rate_limits.sql` | DB-backed `login_rate_limits` |
| `2026_08_15_add_blogs_created_at.sql` | `blogs.created_at` + descending index |
| `2026_08_15_add_blogs_status.sql` | `blogs.status` (draft/published) |
| `2026_08_16_add_blogs_views.sql` | `blogs.views` read counts |
| `2026_08_16_add_comments.sql` | `comments` (moderated reader comments) + indexes |
| `2026_08_16_add_subscribers.sql` | `subscribers` (email UNIQUE + unsubscribe token) + token index |
| `2026_add_admin_email.sql` | Adds `admins.email` (UNIQUE) that magic-link auth depends on |

**Neon / Render note:** migrations run automatically on deploy — the Docker start command runs `php bin/migrate.php` before the app boots (Render's free tier has no `preDeployCommand`). Locally, use `composer migrate` / `composer migrate:status`. Add a new migration as `<YYYY_MM_DD>_<description>.sql` and push to `main`.

---

## 12. Environment Variables

| Variable | Required | Default | Notes |
|----------|----------|---------|-------|
| `APP_KEY` | ✅ | — | Signs magic links (32 random bytes, `base64:` prefix). |
| `DATABASE_URL` | Render | — | Auto-set by the Neon integration; `sslmode` / `channel_binding` query params are honoured. |
| `DB_*` | local | — | Local pgsql config (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`). |
| `DB_SSLMODE` | — | `require` | SSL mode for direct `DB_*` connections (`prefer` for local Postgres **and CI**). |
| `APP_ENV` | — | `local` | Must be non-`local` in production — gates the `DEV_AUTOLOGIN` bypass. |
| `DEV_AUTOLOGIN` | — | — | Local dev only: `true` + `APP_ENV=local` silently signs into admin without magic links. Never set in production. |
| `APP_URL` | — | `http://127.0.0.1` | Public origin used to build magic-link and newsletter URLs. |
| `MAGIC_LINK_TTL` | — | `600` | Link lifetime in seconds. |
| `MAIL_HOST/PORT/USERNAME/PASSWORD` | fallback | Brevo defaults | SMTP creds (fallback only; SMTP ports blocked on Render free tier). Never commit `MAIL_PASSWORD`. |
| `BREVO_API_KEY` | Render | — | Brevo Transactional Email API key — **primary** mail path (HTTP over 443). |
| `MAIL_FROM_ADDRESS/NAME` | — | — | Sender identity. **Must be a Brevo-verified sender**, or Brevo rejects sends while the API returns 200. |
| `ADMIN_NOTIFICATION_EMAILS` | — | — | Comma-separated addresses emailed on new-account provisioning **and new comment submissions** (empty = disabled). |

---

## 13. Testing

`tests/BlogTest.php` — **76 tests / 190 assertions** (3 skipped: API tests needing a live server). Coverage includes: table existence & columns (including `comments` and `subscribers`), SQL-injection-prepared statements, escaping, CSRF token shape/uniqueness, session cookie hardening, image upload validation, blog/category CRUD lifecycle, pagination math, DB-backed rate limiting (block/isolate/forwarded-IP/email-keyed), magic-link single-use + tamper rejection, TOTP (RFC 6238 vectors, verify, provisioning URI), invitation lifecycle + auto-provisioning, **comment workflow** (pending → approve → delete, invalid input rejected), and **subscriber workflow** (subscribe → dedupe → unsubscribe, invalid email rejected).

```bash
vendor/bin/phpunit --testdox     # PHP suite (needs a working DB)
cd frontend && npm test           # Vitest (6 tests: Button + LandingView)
cd frontend && npm run lint       # ESLint (clean)
cd frontend && npx prettier --check "src/**/*.{ts,vue}"   # formatting gate
```

**CI notes:** the GitHub Actions PHP job creates the database, runs migrations, then the suite — with `DB_SSLMODE=prefer`, because the CI Postgres service has no SSL. Migrations seed **no** admins, so tests assert table existence with `>= 0` rows on a fresh database.

---

## 14. Recommended Future Changes

| Issue | Recommendation |
|-------|----------------|
| Legacy `?action=magic&token=` GET redemption | Kept for backward compatibility; new links use the `#magic=` fragment + POST redeem. Remove once old links have expired. |
| `render2faChallenge()` renders a standalone page | Merge into the shared admin layout if styling drift matters. |
| Session lifetime is fixed (7 days) in `registerSession` | Make it configurable (`AUTH_SESSION_TTL`). |
| `CSRF::init()` calls `session_start()` unconditionally | Guard with a `session_status()` check to avoid the notice when Auth already started the session. |
| Comment moderation is manual | Optional later: per-post auto-approve toggle, or a spam-confidence score (Akismet-style) for non-interactive flagging. |
| Newsletter send is synchronous | For larger lists, queue sends (or rely on Brevo's transactional API batching) so publish latency stays flat. |
| Activity feed shows comment/subscriber events | They are already logged (`comment_created`, `subscriber_added`, `newsletter_sent`) — surface them when the admin feed UI returns. |