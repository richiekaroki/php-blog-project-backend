# Blog Backend Project - Implementation Guide

## Overview
This document provides comprehensive recommendations to fix security issues, improve code quality, and enhance the project's database design.

---

## 1. Critical Security Fixes

### 1.1 Fix Database Credentials (`includes/connect.php`)
**Problem**: Hardcoded credentials expose sensitive connection info.

**Current code** (`includes/connect.php:4-7`):
```php
$servername = "localhost";
$username = "root";
$password = "123456789";
$dbname = "ruru_db";
```

**Fix**: Use environment variables or config file outside web root

```php
<?php
// config.php - Move this outside web root or use .env
return [
    'host' => 'localhost',
    'username' => 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'dbname' => 'mizzle_backend',
];
```

**Usage** (`includes/connect.php`):
```php
require __DIR__.'/config.php';
$conn = new mysqli($db['host'], $db['username'], $db['password'], $db['dbname']);
```

---

### 1.2 Fix Insecure Password Hashing (`sql/ruru_schema.sql:25`)
**Problem**: MD5 is cryptographically broken for password storage.

**Current code**:
```sql
INSERT INTO admins (username, password)
VALUES ('admin', MD5('password'));
```

**Fix**: Use PHP `password_hash()` and verify with `password_verify()`

```php
// When creating admin (run once):
$hashedPassword = password_hash('password', PASSWORD_DEFAULT);
$sql = "INSERT INTO admins (username, password) VALUES (?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", 'admin', $hashedPassword);
$stmt->execute();

// To verify login (in login.php):
$sql = "SELECT * FROM admins WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user && password_verify($password, $user['password'])) {
    // Login success
}
```

---

### 1.3 Fix Missing `db.php` Reference (`admin/login.php:5`)
**Problem**: References non-existent `db.php` file.

**Current code** (`admin/login.php:5`):
```php
require 'db.php';
```

**Fix**: Use correct relative path:
```php
require '../includes/connect.php';
```

---

### 1.4 Add CSRF Protection
**Problem**: Forms vulnerable to Cross-Site Request Forgery.

**Fix**: Add CSRF token generation and validation

**Add to `includes/connect.php` or create `csrf.php`**:
```php
<?php
// csrf.php - Add to includes
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
```

**In each form file** (e.g., `admin/edit-blog.php`):
```php
<?php require '../includes/csrf.php'; ?>
<form method="POST" action="edit-blog.php?id=<?php echo $blog_id; ?>">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <!-- ... rest of form -->
</form>
```

**Validate CSRF token** at the top of form handlers:
```php
if (!isset($_POST['csrf_token']) || 
    $_POST['csrf_token'] !== $_SESSION['csrf_token'] ||
    hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']) === false) {
    die('Invalid CSRF token');
}
```

---

### 1.5 Fix Session Security (`includes/auth.php`)
**Problem**: No session regeneration after login.

**Current code** (`includes/auth.php`):
```php
if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit;
}
```

**Fix**: Regenerate session ID on login and check session fixation:

```php
<?php
// auth.php - Enhanced
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit;
}

// Regenerate session ID to prevent fixation
session_regenerate_id(true);
$_SESSION['admin'] = $username; // Set after regenerate
```

---

### 1.6 Add Output Escaping
**Problem**: XSS vulnerability - content echoed directly without escaping.

**Current code** (`index.php:28`):
```php
<p><?php echo $row['content']; ?></p>
```

**Fix**: Use `htmlspecialchars()` on all output:

```php
<p><?php echo htmlspecialchars($row['content']); ?></p>
```

Apply to all echo statements:
- `index.php:27`: `htmlspecialchars($row['category_name'])`
- `list-blogs.php:38`: `htmlspecialchars($row['category_name'])`
- `edit-blog.php:47`: `htmlspecialchars($blog['title'])`
- etc.

---

## 2. Code Quality Improvements

### 2.1 Fix Pagination SQL Injection (`admin/list-blogs.php:14`)
**Problem**: Unvalidated `$_GET['page']` used in LIMIT/OFFSET.

**Current code** (`admin/list-blogs.php:7-14`):
```php
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$sql = "SELECT ... LIMIT $limit OFFSET $offset";
```

**Fix**: Add validation and maximum limit:

```php
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(50, max(1, (int)($_GET['limit'] ?? 5))); // Max 50 per page
$offset = ($page - 1) * $limit;

// Use prepared statement approach with bound parameters
// Since LIMIT/OFFSET don't support parameters directly, validate strictly
if (!is_int($page) || $page < 1 || $page > $total_pages) {
    header("Location: list-blogs.php");
    exit;
}
```

---

### 2.2 Add Blog Creation (`admin/blogs.php`)
**Problem**: `blogs.php` referenced in README but doesn't exist.

**Create `admin/blogs.php`** with blog creation functionality:

```php
<?php
require '../includes/auth.php';
require '../includes/connect.php';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    
    $title = $_POST['title'];
    $content = $_POST['content'];
    $category_id = $_POST['category_id'] ?? null;
    
    $sql = "INSERT INTO blogs (title, content, category_id) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $title, $content, $category_id);
    $stmt->execute();
    
    header("Location: list-blogs.php");
    exit;
}

// Fetch categories for form
$categories = $conn->query("SELECT * FROM categories")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head><title>Add Blog</title><link rel="stylesheet" href="../assets/css/bootstrap.min.css"></head>
<body>
<div class="container">
    <h2>Add New Blog</h2>
    <form method="POST" action="blogs.php">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <div class="form-group">
            <label>Title:</label>
            <input type="text" name="title" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Content:</label>
            <textarea name="content" class="form-control" required></textarea>
        </div>
        <div class="form-group">
            <label>Category:</label>
            <select name="category_id" class="form-control" required>
                <option value="">-- Select Category --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Create Blog</button>
    </form>
</div>
</body>
</html>
```

---

### 2.3 Add Category CRUD (`admin/categories.php`)
**Problem**: Current implementation only displays categories, no add/edit/delete.

**Enhanced `admin/categories.php`**:

```php
<?php
require '../includes/auth.php';
require '../includes/connect.php';
require '../includes/csrf.php';

// Handle DELETE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->prepare("DELETE FROM categories WHERE id = ?")->execute();
    header("Location: categories.php");
    exit;
}

// Handle form submission (add/edit)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    
    if (isset($_POST['category_id'])) {
        // Edit
        $id = (int)$_POST['category_id'];
        $name = $_POST['name'];
        $description = $_POST['description'];
        $conn->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?")
             ->bind_param("ssi", $name, $description, $id)->execute();
    } else {
        // Add new
        $name = $_POST['name'];
        $description = $_POST['description'] ?? '';
        $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)")
             ->bind_param("ss", $name, $description)->execute();
    }
    header("Location: categories.php");
    exit;
}
?>

<!-- ... rest of HTML with add form and table -->
```

---

## 3. Database Recommendations

### 3.1 Better Database Design Improvements

**Current issues**:
- `admins` table stores plain/MD5 passwords
- No `created_at`/`updated_at` timestamps
- No `is_active` flag for admin accounts
- No index optimization

**Recommended schema** (`sql/ruru_schema.sql`):

```sql
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE blogs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    category_id INT,
    author_id INT NULL, -- Optional: support multiple authors
    is_published TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (author_id) REFERENCES admins(id)
);

-- Indexes for performance
CREATE INDEX idx_blogs_category ON blogs(category_id);
CREATE INDEX idx_blogs_created ON blogs(created_at DESC);
CREATE INDEX idx_admins_username ON admins(username);
```

---

### 3.2 Recommended: Use MySQLi PDO Instead of MySQLi Native

**Why PDO is better**:
- Consistent API across different databases
- Named parameters for cleaner code
- Built-in prepared statement support
- Exception handling for errors

**PDO connection example**:
```php
// config.php
return [
    'dsn' => 'mysql:host=localhost;dbname=mizzle_backend;charset=utf8mb4',
    'username' => 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ],
];

// connect.php
$config = require __DIR__.'/config.php';
$pdo = new PDO($config['dsn'], $config['username'], $config['password'], $config['options']);

// Prepared statement example
$stmt = $pdo->prepare("SELECT * FROM blogs WHERE id = ?");
$stmt->execute([$blogId]);
$blog = $stmt->fetch();

// Insert example
$stmt = $pdo->prepare("INSERT INTO blogs (title, content, category_id) VALUES (:title, :content, :category_id)");
$stmt->execute([
    'title' => $title,
    'content' => $content,
    'category_id' => $categoryId,
]);
```

---

### 3.3 Migration Path

1. **Short-term**: Fix current MySQLi issues as outlined above
2. **Medium-term**: Consider switching to PDO for better maintainability
3. **Long-term**: If scaling needed, consider PostgreSQL for better JSON support and advanced features

---

## 4. Complete File List with Fixes

| File | Changes Required |
|------|-----------------|
| `includes/connect.php` | Use config/env for credentials |
| `includes/auth.php` | Add `session_regenerate_id()` |
| `includes/csrf.php` | Create new file for CSRF protection |
| `sql/ruru_schema.sql` | Use `password_hash()`, add timestamps, fix data types |
| `admin/login.php` | Fix `require` path, add CSRF validation |
| `admin/list-blogs.php` | Validate pagination parameters strictly |
| `admin/edit-blog.php` | Add CSRF token, escape output |
| `admin/categories.php` | Add add/edit/delete forms |
| `admin/blogs.php` | Create new file with CRUD operations |
| `index.php` | Add pagination, escape all output |

---

## 5. Quick Checklist for Implementation

- [ ] Move DB credentials to config/file outside web root or use `.env`
- [ ] Replace MD5 hashing with `password_hash()`/`password_verify()`
- [ ] Fix `admin/login.php` require path to `../includes/connect.php`
- [ ] Create `includes/csrf.php` and add token to all forms
- [ ] Add CSRF validation at top of all form handlers
- [ ] Add `htmlspecialchars()` to all echoed content
- [ ] Add `session_regenerate_id()` after login
- [ ] Validate/paginate parameters with strict type checking
- [ ] Create `admin/blogs.php` with create functionality
- [ ] Enhance `admin/categories.php` with CRUD operations
- [ ] Update SQL schema with proper timestamps and constraints
- [ ] Consider migrating to PDO for future maintainability

---

## 6. Suggested Tech Stack upgrade

If starting fresh or refactoring significantly:

```
PHP 8.1+ + MySQL 8.0 + PDO
│
├── Authentication: use password_hash/verify
├── Database: PDO with prepared statements
├── Validation: filter_input() + custom rules
├── CSRF: double-submit token pattern
├── Output: htmlspecialchars() with ENT_QUOTES
├── Sessions: regenerate_id + HTTP-only cookies
└── Framework consideration: Laminas (Zend) or Slim for larger apps
```