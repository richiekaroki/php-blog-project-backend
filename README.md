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

Copy `.env` and update your PostgreSQL password:

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

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/index.php?action=blogs` | List posts |
| `GET` | `/api/index.php?action=blogs&id=1` | Single post |
| `POST` | `/api/index.php?action=blogs` | Create post |
| `PUT` | `/api/index.php?action=blogs&id=1` | Update post |
| `DELETE` | `/api/index.php?action=blogs&id=1` | Delete post |
| `GET` | `/api/index.php?action=categories` | List categories |
| `GET` | `/api/index.php?action=health` | Health check |

## Project Structure

```
├── admin/          # Login, blog CRUD, category management
├── api/            # REST API (blogs, categories, health)
├── assets/         # Bootstrap CSS/JS
├── includes/       # DB connection, auth, CSRF
├── uploads/        # Image uploads
├── sql/            # PostgreSQL schema
├── tests/          # PHPUnit tests
├── index.php       # Homepage (search, filter, pagination)
├── post.php        # Single post view
└── .env            # Environment config (gitignored)
```

## Security

- PDO prepared statements (SQL injection prevention)
- CSRF tokens on all forms
- `htmlspecialchars()` output escaping (XSS prevention)
- `password_hash()` / `password_verify()` (bcrypt)
- Session regeneration on login
- Rate limiting (5 attempts / 15 min)
- `.env` for credentials (gitignored)

## Testing

```bash
vendor/bin/phpunit
```
