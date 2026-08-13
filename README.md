# WAM Blog Backend

[![CI](https://github.com/richiekaroki/php-blog-project-backend/actions/workflows/php.yml/badge.svg)](https://github.com/richiekaroki/php-blog-project-backend/actions/workflows/php.yml)
[![Tests](https://img.shields.io/badge/tests-41%20passed-brightgreen)](#testing)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php)](#)
[![Vue](https://img.shields.io/badge/Vue-3-42b883?logo=vue.js)](#)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-17-4169E1?logo=postgresql)](#)

A secure blog with a **Vue 3 + TypeScript admin frontend**, PHP REST API, and PostgreSQL. Features passwordless magic-link sign-in, profile management, dark mode, and OWASP-minded security.

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

- Username + password (bcrypt), or passwordless magic-link via Brevo SMTP
- Session-based, CSRF-protected, rate-limited (5/15 min)
- Magic links are stateless HMAC-SHA256 tokens signed with `APP_KEY` (15-min expiry)

## Local Development

```bash
# Backend
composer install && cp .env.example .env   # add DB + SMTP keys
psql -U postgres -c "CREATE DATABASE mizzle_backend;"
psql -U postgres -d mizzle_backend -f sql/ruru_schema.sql -f sql/migrations/2026_add_admin_email.sql

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