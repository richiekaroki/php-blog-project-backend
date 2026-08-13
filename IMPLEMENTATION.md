# Implementation Notes: Passwordless Auth, Roles & Security Hardening

As of **13 Aug 2026**, the WAM Blog backend implements a passwordless magic-link flow hardened with **single-use tokens**, an **optional TOTP 2FA step**, a **revocable server-side session registry**, and **role-based access control** (admin / editor / viewer) enforced on both the PHP admin pages and the REST API.

This document describes how the pieces are wired together, the exact request flows, and the security model. See `DESIGN.md` for the architecture overview and `README.md` for quick start / deployment.

---

## 1. Deployment Status

| Layer | Status | Where |
|-------|--------|-------|
| Public site (Vue SPA) | **Served in production** | `frontend/dist` copied into the Docker image; Nginx serves it at `/`. |
| Vue admin SPA views | **Removed** | Admin is 100% PHP-rendered (`/admin/*.php`). The Vue app is only the public landing page + login. |
| PHP admin | Live | `/admin/login.php`, `/admin/blogs.php`, `/admin/categories.php`, `/admin/edit-blog.php`, `/admin/profile.php` |
| REST API | Live | `/api/index.php?action=...` |
| Database | Neon PostgreSQL 17 | Direct (unpooled) connection for DDL; pooler for app traffic. |
| Email | Brevo SMTP | Magic-link delivery. |

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

The `admins.password` column still exists in the schema for backward compatibility but is **not used** by any login path. See §8 for deprecation notes.

### 2.1 Key components

| Component | File | Responsibility |
|-----------|------|----------------|
| `App\Auth\MagicLink` | `src/Auth/MagicLink.php` | Create/verify HMAC-SHA256 tokens, atomic single-use consume. |
| `App\Auth\Totp` | `src/Auth/Totp.php` | RFC 6238 TOTP codes/verification (no external deps). |
| `App\Middleware\Auth` | `src/Middleware/Auth.php` | Hardened sessions, server-side validation, logout/revocation, role guard. |
| `App\Middleware\CSRF` | `src/Middleware/CSRF.php` | Token init/verify for every state-changing form/request. |
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
- **Lifetime**: `MAGIC_LINK_TTL` seconds. Default **600** (10 min). Read from env with fallback `600`.
- `APP_KEY` may be a raw string or `base64:`-prefixed (Laravel-style) — both decoded in the constructor.

### 3.2 Verification (`MagicLink::verify`)

1. Split on `.` — must be exactly 2 parts.
2. Recompute HMAC and compare with `hash_equals`.
3. Base64-decode payload, must contain non-empty `email` and `exp`.
4. Reject if `exp < now()`.
5. Return lowercased/trimmed email.

### 3.3 Single-use consume (`MagicLink::consume`)

Tokens are one-time. Replay protection is atomic via the DB:

```sql
INSERT INTO magic_link_uses (token_hash, email) VALUES (?, ?)
ON CONFLICT (token_hash) DO NOTHING;
```

- `token_hash` is `sha256(token)` — the raw token is **never** stored.
- The `PRIMARY KEY` on `token_hash` makes check-and-set atomic: concurrent redeems of the same link result in exactly one winner.
- `consume()` returns `rowCount() > 0`; the caller rejects with **HTTP 401 "This sign in link has already been used"** if it loses the race.
- Consumption happens **before** the 2FA challenge. If the user closes the 2FA page, the link is already spent — they must request a new one. This is intended: a captured link cannot be half-redeemed.

---

## 4. Two-Factor Authentication (TOTP)

`App\Auth\Totp` is a self-contained RFC 6238 implementation:

- HMAC-SHA1, 6 digits, 30-second period, base32 secrets (160-bit / 20 bytes).
- `verify($secret, $code, $window = 1)` accepts ±1 period (90s total) and strips whitespace.
- `provisioningUri()` builds a standard `otpauth://totp/...` URI shown as text on the profile page (no QR generation — manual entry only, no extra deps).
- Verified against all RFC 6238 test vectors in the test suite.

### 4.1 Enrollment (profile.php)

1. `POST action=generate_2fa` → `Totp::generateSecret()` stored in `$_SESSION['pending_totp_secret']`, secret + `otpauth://` URI shown.
2. User adds to their authenticator app and submits a code.
3. `POST action=confirm_2fa` → code must verify against the **pending** secret before it is written to `admins.totp_secret`.

The secret is only persisted after proof-of-possession of the authenticator.

### 4.2 Sign-in with 2FA enabled

1. Magic link click → `MagicLink::verify` + `consume` succeed.
2. If `admins.totp_secret` is non-empty, the pending identity is stashed in session (`pending_2fa_email` / `_username` / `_role`) and `render2faChallenge()` renders a standalone code page.
3. `POST action=verify_2fa` (CSRF-checked) → `Totp::verify` against the stored secret → on success the session is created and pending keys are cleared. Wrong code re-renders the challenge with an error.
4. **No session is created until 2FA passes.** A session is only registered after successful TOTP verification.

### 4.3 Disabling

Requires the **current** TOTP code (`POST action=disable_2fa`) so an attacker who stole the session cookie cannot silently remove 2FA.

---

## 5. Server-side Session Registry

### 5.1 Model

Every successful login inserts a row into `auth_sessions`:

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
2. `session_regenerate_id(true)` — the pre-auth session is discarded and the fresh ID is what gets registered, preventing fixation.
3. Inserts the `auth_sessions` row keyed on the **new** `session_id()`.
4. Rotates `$_SESSION['csrf_token']`.

### 5.4 Validation (`Auth::check` / `isSessionValid`)

Every admin page calls `Auth::check()`, which:

1. Starts the hardened session.
2. Requires `$_SESSION['admin']` set.
3. `isSessionValid()` — `auth_sessions` must contain a matching row where `revoked_at IS NULL` and `expires_at` is in the future. Missing table or missing/revoked/expired row ⇒ **fail closed** (treated as logged out).
4. Reloads the admin row by username; unknown user ⇒ logout.
5. Caches `$_SESSION['user_role']`.

The token hash is bound to the **session ID**, so the login session and the registry row move together. `check()` deliberately does **not** regenerate the ID per-request (it would break the registry binding).

### 5.5 Logout & revocation

- `Auth::logout()` — sets `revoked_at = NOW()` on the current row, clears `$_SESSION`, expires the cookie, destroys the session.
- `Auth::revokeOtherSessions()` — revokes every row for this admin except the current `session_token_hash`. Used by the profile page's **"Sign out other devices"** button.
- Revoked sessions immediately die on the next `check()` — no client-side token to replay.

---

## 6. Roles & Access Control

Roles live on `admins.role` (`admin` | `editor` | `viewer`).

| Capability | admin | editor | viewer |
|-----------|:-----:|:------:|:------:|
| View blogs / categories / profile | ✅ | ✅ | ✅ |
| Create / edit blogs & categories | ✅ | ✅ | ❌ |
| Delete blogs & categories | ✅ | ❌ | ❌ |
| Upload images | ✅ | ✅ | ❌ |
| Manage 2FA / sessions (own) | ✅ | ✅ | ✅ |

### 6.1 PHP admin pages

- `Auth::check()` gates every page.
- `blogs.php` / `categories.php`: `$canWrite = role in (admin, editor)`, `$canDelete = role === admin`. Create/edit forms and delete buttons are **hidden** for viewers; POST/DELETE handlers independently guard with **403** (server-side enforcement, not just UI hiding).
- `edit-blog.php`: requires `admin|editor`, otherwise `403`.

### 6.2 REST API

The write gate at the top of `public/api/index.php`:

```php
$writeMethods = ['POST', 'PUT', 'DELETE'];
if (in_array($method, $writeMethods) && !$isMagicRequest) {
    Auth::startSession();
    if (!isset($_SESSION['admin']) || !Auth::isSessionValid()) {
        sendResponse(401, ['error' => 'Authentication required for write operations']);
    }
    loadApiRole($pdo);   // -> $_SESSION['user_role']
}
```

Per-endpoint (`requireApiRole(...)`):

| Endpoint | Method(s) | Role required |
|----------|-----------|---------------|
| `blogs` | POST / PUT | admin, editor |
| `blogs` | DELETE | admin |
| `categories` | POST / PUT | admin, editor |
| `categories` | DELETE | admin |
| `upload` | POST | admin, editor |
| `profile` | GET / PUT | any authenticated admin |
| `blogs`, `categories`, `activity`, `health`, `magic`, `signup-request` GETs | GET | public |

API responses use the shared `sendResponse` helper (adds `request_id`).

---

## 7. Click Paths

### A. Vue public site (dev & production)

1. Landing page → **Enter the blog** → `/login` (`LoginView.vue`).
2. Email submitted → `POST /api/magic/request`.
3. "Check your inbox — the link expires in 10 minutes."
4. User clicks the emailed link → `https://<app>/admin/login.php?action=magic&token=...` → PHP flow takes over (single-use, 2FA, session) → redirected to `blogs.php`.

### B. PHP admin

1. `/admin/login.php` → email form.
2. `POST magic_email` → CSRF-verified, admin looked up by email (same generic success message either way — no account leaking), token created (TTL 600) and emailed.
3. Click link → `?action=magic&token=` → verify + consume → 2FA challenge if enabled → `Auth::registerSession` → `Location: blogs.php`.
4. `?action=status` returns JSON auth state (for the Vue frontend). `?action=logout` runs `Auth::logout()`.

---

## 8. Database Migrations

Apply in order:

| File | Purpose |
|------|---------|
| `sql/ruru_schema.sql` | Base schema: `admins`, `roles`, `user_roles`, `blogs`, `activity_log` |
| `sql/migrations/2026_add_admin_email.sql` | Adds `admins.email` |
| `sql/migrations/2026_08_13_create_invitations.sql` | `invitations` table for sign-up requests |
| `sql/migrations/2026_08_13_magic_link_security.sql` | `magic_link_uses`, `auth_sessions`, `admins.totp_secret` |

**Neon / Render note:** migrations do not run automatically on deploy. Apply `2026_08_13_magic_link_security.sql` to the Neon database using the direct (non-pooler) connection — see README §Neon Migration.

---

## 9. Environment Variables

| Variable | Required | Default | Notes |
|----------|----------|---------|-------|
| `APP_KEY` | ✅ | — | Signs magic links (32 random bytes, `base64:` prefix). |
| `DATABASE_URL` | Render | — | Auto-set by Neon integration. |
| `DB_*` | local | — | Local pgsql config (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`). |
| `APP_URL` | — | `http://127.0.0.1` | Base URL used to build magic-link URLs (must be the public origin). |
| `MAGIC_LINK_TTL` | — | `600` | Link lifetime in seconds. |
| `MAIL_HOST/PORT/USERNAME/PASSWORD` | ✅ | Brevo defaults | SMTP creds. Never commit `MAIL_PASSWORD`. |
| `MAIL_FROM_ADDRESS/NAME` | — | — | Sender identity. |

---

## 10. Testing

`tests/BlogTest.php` — **51 tests / 99 assertions** (3 skipped: API tests needing a live server). Coverage includes: table existence & columns, bcrypt hashes, SQL-injection-prepared statements, escaping, CSRF token shape/uniqueness, session cookie hardening, image upload validation, blog/category CRUD lifecycle, pagination math, and the new auth security suite: `magic_link_uses` exists + unique constraint, `auth_sessions` + revocation, single-use consume, tampered-token rejection, `totp_secret` column, RFC 6238 vectors, verify accept/reject, provisioning URI.

```bash
vendor/bin/phpunit --testdox
```

---

## 11. Recommended Future Changes

| Issue | Recommendation |
|-------|----------------|
| `admins.password` still in schema but unused | Drop the column in a future migration once confident no legacy path exists. |
| Token travels in the URL (`?token=`) | Acceptable for 10-minute links; if logs are a concern, switch verify to a POST + short-lived pre-token. |
| `render2faChallenge()` renders a separate standalone HTML page | Merge into the shared admin layout or a proper template if styling drift matters. |
| Session lifetime is fixed (7 days) in `registerSession` | Make it configurable (`AUTH_SESSION_TTL`). |
| No per-admin rate limit on 2FA attempts | Add a small attempt counter keyed on IP + pending email. |
| `CSRF::init()` calls `session_start()` unconditionally | Guard with a `session_status()` check to avoid the notice when Auth already started the session. |
| Activity log events for 2FA | Already logged (`2fa_enabled`, `2fa_disabled`, `magic_link_used` with `2fa` flag) — surface them in the UI when the admin feed returns. |
