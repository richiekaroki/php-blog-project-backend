# Blog Backend

PHP blog backend with admin panel, REST API, and PostgreSQL. Runs on Laravel Herd.

## Stack

PHP 8.4 · PostgreSQL 17 · Laravel Herd (NGINX) · Bootstrap 5 · PHPUnit

## Quick Start

```bash
# Install dependencies
composer install

# Create database and import schema
psql -U postgres -c "CREATE DATABASE mizzle_backend;"
psql -U postgres -d mizzle_backend -f sql/ruru_schema.sql

# Start Herd, then visit
http://php-blog-backend-project.test
```

## Environment

Copy `.env.example` to `.env` and update your PostgreSQL password:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_DATABASE=mizzle_backend
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

## Default Login

| | |
|---|---|
| URL | `http://php-blog-backend-project.test/admin/login.php` |
| Username | `admin` |
| Password | `password` |

## API

| Method | Endpoint | Auth Required |
|--------|----------|---------------|
| `GET` | `/api/index.php?action=blogs` | No |
| `GET` | `/api/index.php?action=blogs&id=1` | No |
| `POST` | `/api/index.php?action=blogs` | Yes |
| `PUT` | `/api/index.php?action=blogs&id=1` | Yes |
| `DELETE` | `/api/index.php?action=blogs&id=1` | Yes |
| `GET` | `/api/index.php?action=categories` | No |
| `GET` | `/api/index.php?action=health` | No |

Write operations (POST/PUT/DELETE) require an authenticated session via `/admin/login.php`.

## Project Structure

```
├── admin/          # Login, blog CRUD, category management
├── api/            # REST API (blogs, categories, health)
├── assets/         # Bootstrap CSS/JS
├── includes/       # DB connection, auth, CSRF, security headers
├── uploads/        # Image uploads
├── sql/            # PostgreSQL schema
├── tests/          # PHPUnit tests
├── index.php       # Homepage (search, filter, pagination)
├── post.php        # Single post view
└── .env            # Environment config (gitignored)
```

## Security

- **SQL Injection** — PDO prepared statements with `EMULATE_PREPARES=false`
- **XSS Prevention** — `htmlspecialchars()` with `ENT_QUOTES` on all output
- **CSRF Protection** — `random_bytes(32)` tokens on all forms, regenerated on login
- **Password Hashing** — `password_hash()` / `password_verify()` (bcrypt)
- **Session Security** — `httponly`, `samesite=Lax`, `strict_mode`, regenerated on login
- **API Authentication** — Write endpoints require authenticated session
- **Rate Limiting** — Login: 5 attempts / 15 min (IP-based); API: 100 req / min
- **Image Upload Validation** — `getimagesize()` content verification, random filenames
- **Security Headers** — CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy
- **Error Handling** — Generic error messages, details logged server-side only
- **Field Whitelisting** — API only accepts allowed fields (prevents mass assignment)
- **`.env`** — Credentials gitignored, never committed

## Testing

```bash
vendor/bin/phpunit
```
