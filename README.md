# WAM Blog

[![CI](https://github.com/richiekaroki/php-blog-project-backend/actions/workflows/php.yml/badge.svg)](https://github.com/richiekaroki/php-blog-project-backend/actions/workflows/php.yml)
[![Tests](https://img.shields.io/badge/tests-41%20passed-brightgreen)](#testing)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php)](#)
[![Vue](https://img.shields.io/badge/Vue-3-42b883?logo=vue.js)](#)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-17-4169E1?logo=postgresql)](#)

A secure PHP blog backend with admin panel, REST API, and PostgreSQL. Features role-based access control, image uploads, search/filter pagination, and hardened security (CSRF, XSS prevention, rate limiting, session hardening). Runs on Laravel Herd.

**Live Demo:** https://php-blog-backend.onrender.com

| URL | Description |
|-----|-------------|
| [Homepage](https://php-blog-backend.onrender.com) | Blog with search, filter, pagination |
| [Admin Panel](https://php-blog-backend.onrender.com/admin/login.php) | Sign in to manage blogs, categories & profile |
| [API Health](https://php-blog-backend.onrender.com/api/index.php?action=health) | API status check |

**Admin login:** `admin` / `password` (set a real email in Profile to enable magic-link sign-in)

## Stack

| Layer | Technology |
|-------|-----------|
| Frontend | Vue 3, TypeScript, Vite, Pinia, Vue Router, Tailwind, shadcn-vue |
| Backend | PHP 8.4, Nginx + PHP-FPM (Docker) |
| Database | PostgreSQL 17 (Neon) |
| Email | Brevo SMTP (magic links) |
| Testing | PHPUnit (41 tests) |
| Hosting | Render |

## Project Structure

```
├── frontend/             # Vue 3 + TS admin SPA
│   └── src/
│       ├── api/          # Axios client
│       ├── components/   # blog, category, layout, ui (shadcn-vue)
│       ├── features/     # activity log, landing modal
│       ├── stores/       # Pinia (auth, blog)
│       ├── views/        # landing, login, admin/
│       └── router/       # Auth-guarded routes
├── src/                  # PHP app (Auth, Database, Mail, Middleware, Models)
├── public/               # admin/, api/, index.php, post.php
├── sql/                  # schema + migrations
├── tests/                # 41 PHPUnit tests
├── Dockerfile / nginx.conf / render.yaml
└── .env.example
```

## Auth

- **Passwordless-first**: Default login uses HMAC-SHA256 magic links via Brevo SMTP.
- Password (bcrypt) is a fallback for existing admins; not required for daily use.
- Session-based, CSRF-protected, rate-limited (5/15 min).
- Magic links are stateless HMAC-SHA256 tokens signed with `APP_KEY` (15-min expiry).
- **Sign-up request**: `POST /api/signup-request` allows new users to request access; stored in the `invitations` table; an admin approves and the user is invited via magic link.
- **"Enter the blog"** CTA on the public landing page routes to `/login` (Vue) or `/admin/login.php` (PHP).

## Design System

Explore the unified design system used throughout the project based on Tailwind CSS 4 and custom CSS variables.  Check `frontend/src/style.css` for the full token definitions and the design system artifacts.  The design system shares a common color palette, typography, spacing, and component pattern set shared between the server‑rendered admin pages and the Vue admin SPA.

See `DESIGN.md` for the complete token definitions and usage guidelines.

## Local Development

```bash
# Backend
composer install && cp .env.example .env   # add DB + SMTP keys
psql -U postgres -c "CREATE DATABASE mizzle_backend;"
psql -U postgres -d mizzle_backend -f sql/ruru_schema.sql -f sql/migrations/2026_add_admin_email.sql -f sql/migrations/2026_08_13_create_invitations.sql

# Frontend (Vue 3 SPA)
cd frontend && npm install && npm run dev   # http://localhost:3000, proxies /api

# PHP site
http://php-blog-backend-project.test
```

## Testing

```bash
vendor/bin/phpunit --testdox
```

## Deployment

Deployed on Render (Docker) + Neon PostgreSQL; pushing to `main` auto-rebuilds. Set in the Render dashboard (never commit secrets): `DATABASE_URL` (Neon integration), `APP_KEY` (32 random bytes, `base64:` prefix), `MAIL_PASSWORD` (Brevo SMTP key).