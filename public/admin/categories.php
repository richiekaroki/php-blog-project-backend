<?php
// admin/categories.php - Category management with PDO and CSRF protection

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Database\Connection;
use App\Middleware\Auth;
use App\Middleware\CSRF;

Auth::check();
$pdo = Connection::getInstance();
CSRF::init();

// Role model:
//   admin  = full access (create, edit, delete)
//   editor = create + edit, but NO delete
//   viewer = read-only (no create/edit/delete)
$currentRole = Auth::getRole() ?? 'viewer';
$canWrite = in_array($currentRole, ['admin', 'editor'], true);
$canDelete = $currentRole === 'admin';

// Handle DELETE (admin only, POST + CSRF — never via GET)
if (isset($_POST['delete'])) {
    if (!$canDelete) {
        http_response_code(403);
        die('Access denied: only admins can delete categories.');
    }

    if (!isset($_POST['csrf_token']) ||
        $_POST['csrf_token'] !== $_SESSION['csrf_token'] ||
        hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']) === false) {
        die('Invalid CSRF token');
    }

    $id = (int)$_POST['delete'];
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: categories.php");
    exit;
}

// Handle form submission (add/edit — admin and editor)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!$canWrite) {
        http_response_code(403);
        die('Access denied: your role does not permit editing categories.');
    }

    if (!isset($_POST['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token'] ||
        hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']) === false) {
        die('Invalid CSRF token');
    }

    if (isset($_POST['category_id'])) {
        // Edit category
        $id = (int)$_POST['category_id'];
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));

        // Server-side validation (name is VARCHAR(50) in the DB)
        if ($name === '' || mb_strlen($name) > 50) {
            die('Category name is required and must be 50 characters or fewer.');
        }

        $sql = "UPDATE categories SET name = ?, description = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $description, $id]);
    } else {
        // Add new category
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));

        if ($name === '' || mb_strlen($name) > 50) {
            die('Category name is required and must be 50 characters or fewer.');
        }

        $sql = "INSERT INTO categories (name, description) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $description]);
    }
    header("Location: categories.php");
    exit;
}

// Fetch categories using PDO
$stmt = $pdo->query("SELECT * FROM categories");
$categories = $stmt->fetchAll();

// Quick stats for the welcome band
$statPosts = (int)$pdo->query("SELECT COUNT(*) FROM blogs")->fetchColumn();
$statCats = count($categories);
$statRecent = (int)$pdo->query("SELECT COUNT(*) FROM activity_log WHERE created_at >= NOW() - INTERVAL '7 days'")->fetchColumn();

$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$greetingName = Auth::currentUsername() ?? 'author';
$roleLabel = ucfirst($currentRole);

// Find category being edited (if any)
$editCat = null;
if (isset($_GET['edit'])) {
    $catId = (int)$_GET['edit'];
    foreach ($categories as $c) {
        if ($c['id'] == $catId) {
            $editCat = $c;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/svg+xml" href="/favicon-v2.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories · WAM Blog</title>
<link rel="stylesheet" href="../assets/admin.css">
    <script>
        (function() {
            if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <img class="sidebar-logo" src="/favicon-v2.svg" alt="WAM" width="64" height="64">
                <div><div class="sidebar-brand">WAM Blog</div><div class="sidebar-sub">Content Studio</div></div>
            </div>
            <nav class="sidebar-nav">
                <p class="nav-section-label">Manage</p>
                <a href="blogs.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
                    Blogs
                </a>
                <a href="categories.php" class="nav-item active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg>
                    Categories
                </a>
                <?php if (Auth::getRole() === 'admin'): ?>
                <a href="users.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Users
                </a>
                <?php endif; ?>
                <a href="activity.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                    Activity
                </a>
<p class="nav-section-label" style="margin-top: 1rem;">Site</p>
                <a href="/" class="nav-item" target="_blank">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                    View Site
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="login.php?action=logout" class="nav-item danger">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                    Logout
                </a>
            </div>
        </aside>
        <div class="sidebar-backdrop" onclick="closeSidebar()"></div>

        <div class="main">
<header class="header">
                <button class="menu-btn" onclick="toggleSidebar()" aria-label="Open navigation menu" aria-expanded="false" id="menuBtn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
                <h1 class="header-title">Categories</h1>
                <div class="header-actions">
                    <button class="theme-toggle" onclick="toggleTheme()" title="Toggle theme" aria-label="Toggle theme">&#9681;</button>
                </div>
            </header>
<div class="content">
                <!-- Welcome band -->
                <div class="welcome-band">
                    <div>
                        <h2 class="welcome-title"><?php echo htmlspecialchars($greeting); ?>, <?php echo htmlspecialchars($greetingName); ?></h2>
                        <p class="welcome-sub">Sections keep the journal tidy. A new folder is one careful name away.</p>
                    </div>
                    <span class="badge <?php echo htmlspecialchars($currentRole); ?>"><?php echo htmlspecialchars($roleLabel); ?></span>
                </div>

                <div class="stat-row">
                    <div class="stat-card">
                        <span class="stat-value"><?php echo $statPosts; ?></span>
                        <span class="stat-label">Published posts</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-value"><?php echo $statCats; ?></span>
                        <span class="stat-label">Categories</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-value"><?php echo $statRecent; ?></span>
                        <span class="stat-label">Events this week</span>
                    </div>
                </div>

                <!-- Add/Edit Category Form (admin + editor only) -->
                <?php if ($canWrite): ?>
                <div class="card">
                    <div class="card-header">
                        <h2><?php echo isset($_GET['edit']) ? 'Edit Category' : 'Create Category'; ?></h2>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_GET['edit']) && $editCat): ?>
                        <form method="POST" action="categories.php">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="category_id" value="<?php echo $editCat['id']; ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Name</label>
                                    <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($editCat['name']); ?>" required placeholder="Category name">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Description</label>
                                    <input type="text" name="description" class="form-input" value="<?php echo htmlspecialchars($editCat['description']); ?>" placeholder="Brief description">
                                </div>
                            </div>
                            <div style="display: flex; gap: 0.75rem;">
                                <button type="submit" class="btn btn-primary">Update Category</button>
                                <a href="categories.php" class="btn btn-outline">Cancel</a>
                            </div>
                        </form>
                        <?php else: ?>
                        <form method="POST" action="categories.php">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="category_id" value="">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Name</label>
                                    <input type="text" name="name" class="form-input" required placeholder="e.g. Technology, Design, Life">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Description</label>
                                    <input type="text" name="description" class="form-input" placeholder="What kind of stories go here?">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Add Category</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Categories List -->
                <div class="card">
                    <div class="card-header">
                        <h2>All Categories</h2>
                    </div>
                    <?php if (empty($categories)): ?>
                        <div class="empty-state">
                            <p>No categories yet. Create your first topic above!</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th style="width: 150px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td><span class="tag"><?php echo htmlspecialchars($cat['name']); ?></span></td>
                                    <td style="color: var(--muted-fg);"><?php echo htmlspecialchars($cat['description'] ?: '—'); ?></td>
                                    <td>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <?php if ($canWrite): ?>
                                            <a href="?edit=<?php echo $cat['id']; ?>" class="btn btn-outline btn-sm">Edit</a>
                                            <?php endif; ?>
                                            <?php if ($canDelete): ?>
                                            <form method="POST" action="categories.php" style="display: inline;" onsubmit="return confirm('Delete this category?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="delete" value="<?php echo $cat['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<script>
        function toggleTheme() {
            const root = document.documentElement;
            root.classList.toggle('dark');
localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light');
        }
    </script>
<script>
        function toggleSidebar() {
            var layout = document.querySelector('.layout');
            var btn = document.getElementById('menuBtn');
            layout.classList.toggle('sidebar-open');
            if (btn) btn.setAttribute('aria-expanded', layout.classList.contains('sidebar-open'));
        }
        function closeSidebar() {
            var layout = document.querySelector('.layout');
            layout.classList.remove('sidebar-open');
            var btn = document.getElementById('menuBtn');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeSidebar();
        });
    </script>
</body>
</html>
