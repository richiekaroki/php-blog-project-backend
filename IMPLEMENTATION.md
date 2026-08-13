# Implementation Notes: Passwordless Auth UI & Backend Wiring

## Overview

As of **13 Aug 2026**, the WAM Blog supports two login flows:

1. **Passwordless magic link** — stateless HMAC-SHA256 token signed with `APP_KEY` (default).
2. **Username + password** — bcrypt-based fallback retained for backward compatibility.

This document describes how the frontend and backend communicate during sign-in, how UI labels are wired, and what future backend changes are recommended to remove password storage permanently.

---

## Deployment Status

- **Backend**: Deployed on Render (PHP 8.4 + Nginx Docker container).
  - Root URL: `https://php-blog-backend.onrender.com`
- **Frontend (Vue 3 admin SPA)**:
  - **Not currently served in production.**
  - Used only in local dev via Vite on `http://localhost:3000`.

> Until the Vue build is deployed to Render, the live site continues to use the **PHP-rendered login flow** (`/admin/login.php`).

---

## UI Label Mapping

| Context | Old Text | New Text | Notes |
|--------|----------|----------|-------|
| Vue `LandingView.vue` | "Sign In" | **Enter the blog** | Points to `/login`. |
| Vue `LoginView.vue` tab | "Magic Link" | **Passwordless** | Always the active tab. |
| PHP `login.php` tab | "Get Started" | **Passwordless** | Always active when `?action=magic` is absent. |
| API healthcheck message | "If that email is registered…" | unchanged | Already matches no-leak principle. |

---

## Click Paths

### A. Vue Dev Environment

1. User clicks **Enter the blog** on the landing page (`http://localhost:3000`).
2. Router pushes to `/login`.
3. `LoginView.vue` shows the **Passwordless** form (email field).
4. On submit, the frontend POSTs to `/api/magic/request`, which:
   - Validates the email.
   - Looks up the admin by email.
   - Sends a signed magic link to the address via Brevo SMTP.
5. The user opens the magic link → `/api/actions/verify_magic` (or equivalent) validates the HMAC token and sets the session.

> If the user switches to **Password**, the same form field can be swapped in locally during dev, but production will ignore this and redirect to `/login` with the email pre-filled.

---

### B. Live PHP Admin

1. User navigates to `/admin/login.php`.
2. The **Passwordless** tab is selected by default.
3. User enters their email and clicks the submit button labeled **Send me a sign in link**.
4. The POST hits `/admin/login.php` with `magic_email=<email>`.
5. The backend validates the CSRF token, looks up the admin by email and attempts to send the magic link.
6. The user receives an email and clicks the link, which hits `/admin/login.php?action=magic&token=<token>`.
7. The session is established and the user is redirected to `/admin/blogs.php`.

---

## Backend ↔ Frontend Communication

### Requests

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/api/magic/request` | POST | Public | Send magic-link email |
| `/api/magic/verify` (planned) | POST | Public | Validate token and create session |
| `/api/profile` | GET | Session | Fetch current admin |
| `/api/profile` | PUT | Session | Update profile/email |

### Tokens

- **Stateless**: Tokens are signed with `APP_KEY` using HMAC-SHA256.
- **Lifetime**: Configurable via `MAGIC_LINK_TTL`. Default: **15 minutes**.
- **No leaks**: The same generic success message is shown regardless of whether the email belongs to an existing admin.

---

## Recommended Backend Changes (Future)

| Issue | Recommendation |
|-------|----------------|
| Passwords still stored in DB (`admins.password`) | Either delete the column eventually or keep it deprecated. Consider deprecating in the next schema version. |
| `GET`? magic links are sent via email links with `token=` in the URL string (visible in logs). | Switch to `POST` for verification as well for improved privacy. |
| No public registration | Add `POST /api/signup_request` endpoint that allows new users to request an invite. Admin dashboard would approve. |

---

## Summary

The passwordless flow is functional on the live site and the Vue SPA defaults to it in dev mode. The “Enter the blog” wording improves the public-facing UX consistency. The docs above should help future contributors understand the flow without having to dig through code.
