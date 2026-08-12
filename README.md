# Blog Backend

[![CI](https://github.com/richiekaroki/php-blog-project-backend/actions/workflows/php.yml/badge.svg)](https://github.com/richiekaroki/php-blog-project-backend/actions/workflows/php.yml)
[![Tests](https://img.shields.io/badge/tests-41%20passed-brightgreen)](#testing)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php)](#)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-17-4169E1?logo=postgresql)](#)
[![License](https://img.shields.io/badge/license-MIT-blue)](#)

A secure PHP blog backend with admin panel, REST API, and PostgreSQL. Built to demonstrate modern security practices, clean API design, and automated testing.

**Live Demo:** https://php-blog-backend.onrender.com

| URL | Description |
|-----|-------------|
| [Homepage](https://php-blog-backend.onrender.com) | Blog with search, filter, pagination |
| [Admin Panel](https://php-blog-backend.onrender.com/admin/login.php) | Blog & category management |
| [API Health](https://php-blog-backend.onrender.com/api/index.php?action=health) | API status check |

**Login:** `admin` / `password`

## What I Built

This project is a secure blog backend I built to demonstrate application security, API design, and testing practices. It implements OWASP Top 10 mitigations including CSRF protection, XSS prevention, rate limiting, session hardening, and input validation. The REST API supports full CRUD with authentication, field whitelisting, and IP-based rate limiting.

The project is deployed on Render with Neon PostgreSQL, using Docker for containerization and GitHub Actions for CI/CD. It includes 41 automated tests covering database operations, security features, and API endpoints.

## Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.4 |
| Database | PostgreSQL 17 (Neon) |
| Server | Nginx + PHP-FPM (Docker) |
| Frontend | Bootstrap 5 |
| Testing | PHPUnit |
| CI/CD | GitHub Actions |
| Hosting | Render |

## Security

| OWASP Category | Implementation |
|----------------|----------------|
| **A01: Broken Access Control** | API auth required for writes, role-based admin access |
| **A02: Cryptographic Failures** | bcrypt password hashing, HTTPS support |
| **A03: Injection** | PDO prepared statements with `EMULATE_PREPARES=false` |
| **A05: Security Misconfiguration** | CSP, X-Frame-Options, X-Content-Type-Options headers |
| **A07: Auth Failures** | Rate limiting (5 attempts/15 min), session regeneration |
| **A08: Data Integrity** | CSRF tokens on all forms, field whitelisting on API |

Additional protections:
- **Session Security** — `httponly`, `samesite=Lax`, `strict_mode`
- **Image Validation** — `getimagesize()` content verification, random filenames
- **Error Handling** — Generic messages, details logged server-side only
- **Rate Limiting** — Login: 5 attempts/15 min (IP-based); API: 100 req/min

## API

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/api/index.php?action=blogs` | No | List all posts |
| `GET` | `/api/index.php?action=blogs&id=1` | No | Single post |
| `POST` | `/api/index.php?action=blogs` | Yes | Create post |
| `PUT` | `/api/index.php?action=blogs&id=1` | Yes | Update post |
| `DELETE` | `/api/index.php?action=blogs&id=1` | Yes | Delete post |
| `GET` | `/api/index.php?action=categories` | No | List categories |
| `GET` | `/api/index.php?action=health` | No | Health check |

## Project Structure

```
├── admin/              # Blog & category CRUD
├── api/                # REST API (authenticated writes)
├── includes/           # Auth, CSRF, DB connection, security headers
├── sql/                # PostgreSQL schema
├── tests/              # 41 PHPUnit tests
├── index.php           # Homepage (search, filter, pagination)
├── post.php            # Single post view
├── Dockerfile          # PHP-FPM + Nginx container
├── nginx.conf          # Web server config
├── render.yaml         # Deployment blueprint
└── .env.example        # Environment template
```

## Testing

```bash
vendor/bin/phpunit --testdox
```

41 tests covering:
- Database schema validation
- Password hashing (bcrypt)
- SQL injection prevention
- XSS prevention
- CSRF token generation
- Image upload validation
- Blog CRUD operations
- Category constraints
- Pagination logic
- Input validation
- API authentication & field whitelisting

## Local Development

```bash
# Install dependencies
composer install

# Create database
psql -U postgres -c "CREATE DATABASE mizzle_backend;"
psql -U postgres -d mizzle_backend -f sql/ruru_schema.sql

# Start Herd and visit
http://php-blog-backend-project.test
```

## Deployment

This project is deployed on **Render** using Docker with **Neon PostgreSQL**. The deployment is automated via GitHub Actions — pushing to `main` triggers a rebuild.

To deploy your own copy:
1. Fork this repository
2. Create a Neon PostgreSQL database
3. Set `DATABASE_URL` environment variable in Render
4. Deploy using the included `render.yaml` blueprint
