<?php
// admin/edit-blog.php - Edit blog with PDO and CSRF protection

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Database\Connection;
use App\Middleware\Auth;
use App\Middleware\CSRF;

Auth::check();
$pdo = Connection::getInstance();
CSRF::init();

// Role model: admin and editor may edit posts; viewer is read-only.
if (!in_array(Auth::getRole() ?? '', ['admin', 'editor'], true)) {
    http_response_code(403);
    die('Access denied: your role does not permit editing posts.');
}

// Existing code for blog editing...
if (!isset($_GET['id'])) {
    die("Blog ID is required.");
}

$blog_id = $_GET['id'];

// Fetch blog details using PDO prepared statement
$sql = "SELECT * FROM blogs WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$blog_id]);
$blog = $stmt->fetch();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token'] ||
        hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']) === false) {
        die('Invalid CSRF token');
    }

$title = trim((string)($_POST['title'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));
    $status = in_array($_POST['status'] ?? 'published', ['published', 'draft'], true) ? $_POST['status'] : 'published';

    // Server-side validation (title is VARCHAR(255) in the DB)
    if ($title === '' || mb_strlen($title) > 255) {
        die('Title is required and must be 255 characters or fewer.');
    }
    if ($content === '') {
        die('Content is required.');
    }

    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $maxSize = 5 * 1024 * 1024;
        if ($_FILES['image']['size'] > $maxSize) {
            die('File too large. Maximum size is 5MB.');
        }
        $imageInfo = getimagesize($_FILES['image']['tmp_name']);
        if ($imageInfo === false) {
            die('Invalid image file.');
        }
        $mimeToExt = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_GIF  => 'gif',
            IMAGETYPE_WEBP => 'webp',
        ];
        $ext = $mimeToExt[$imageInfo[2]] ?? null;
        if (!$ext) {
            die('Unsupported image type.');
        }
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
            $imagePath = 'uploads/' . $filename;
        } else {
            die('Failed to upload image.');
        }
        if (!empty($blog['image'])) {
            $oldImagePath = __DIR__ . '/../' . $blog['image'];
            if (file_exists($oldImagePath)) {
                unlink($oldImagePath);
            }
        }
    }

    if ($imagePath) {
        $sql = "UPDATE blogs SET title = ?, content = ?, status = ?, image = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $content, $status, $imagePath, $blog_id]);
    } else {
        $sql = "UPDATE blogs SET title = ?, content = ?, status = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $content, $status, $blog_id]);
    }

    // Newly published → tell the newsletter list (best-effort).
    $oldStatus = $blog['status'] ?? null;
    if ($status === 'published' && $oldStatus !== 'published') {
        \App\Models\Subscriber::notifyNewPost(['id' => (int)$blog_id, 'title' => $title, 'content' => $content]);
    }

    header("Location: blogs.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/svg+xml" href="/favicon-v2.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Post · WAM Blog</title>
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
                <a href="blogs.php" class="nav-item active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
                    Blogs
                </a>
                <a href="categories.php" class="nav-item">
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
                <a href="comments.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Comments
                    <?php if (\App\Models\Comment::countPending() > 0): ?><span class="nav-badge"><?php echo \App\Models\Comment::countPending(); ?></span><?php endif; ?>
                </a>
                <a href="subscribers.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                    Subscribers
                </a>
                <a href="analytics.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M7 13v3"/><path d="M11 9v7"/><path d="M15 5v11"/><path d="M19 9v7"/></svg>
                    Analytics
                </a>
<p class="nav-section-label" style="margin-top: 1rem;">Site</p>
                <a href="/" class="nav-item" target="_blank">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                    View Site
                </a>
            </nav>
        </aside>
        <div class="sidebar-backdrop" onclick="closeSidebar()"></div>

        <div class="main">
<header class="header">
                <button class="menu-btn" onclick="toggleSidebar()" aria-label="Open navigation menu" aria-expanded="false" id="menuBtn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
                <h1 class="header-title">Edit Post</h1>
                <div class="header-actions">
                    <button class="theme-toggle" onclick="toggleTheme()" title="Toggle theme" aria-label="Toggle theme">&#9681;</button>
                </div>
            </header>
            <div class="content">
                <div class="card">
<div class="card-header">
                        <h2><?php echo htmlspecialchars($blog['title']); ?></h2>
                        <div style="display: flex; gap: 0.5rem;">
                            <button type="button" class="btn btn-outline btn-sm" id="previewToggle">Live Preview</button>
                            <a href="blogs.php" class="btn btn-outline btn-sm">Back to Posts</a>
                        </div>
                    </div>
                    <div class="card-body">
<form method="POST" action="edit-blog.php?id=<?php echo $blog_id; ?>" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="form-group">
                                <label class="form-label">Title</label>
                                <input type="text" name="title" class="form-input" value="<?php echo htmlspecialchars($blog['title']); ?>" required>
                            </div>
<div class="form-group">
                                <label class="form-label">Content</label>
                                <textarea name="content" class="form-textarea" required><?php echo htmlspecialchars($blog['content']); ?></textarea>
                            </div>
                            <div class="form-group" id="previewWrap" style="display: none;">
                                <label class="form-label">Live Preview</label>
                                <iframe id="livePreview" class="live-preview" title="Live preview of your post"></iframe>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Featured Image</label>
                                <?php if (!empty($blog['image'])): ?>
                                    <img src="../<?php echo htmlspecialchars($blog['image']); ?>" id="imagePreview" class="upload-preview" alt="Current featured image">
                                <?php else: ?>
                                    <img id="imagePreview" class="upload-preview" alt="Preview">
                                <?php endif; ?>
                                <input type="file" name="image" class="form-input" accept="image/*" onchange="previewImage(this)">
                                <p class="form-help">Max 5MB. JPEG, PNG, GIF, WebP. Leave empty to keep the current image.</p>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="published" <?php echo ($blog['status'] ?? 'published') === 'published' ? 'selected' : ''; ?>>Published</option>
                                    <option value="draft" <?php echo ($blog['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                </select>
                            </div>
                            <div style="display: flex; gap: 0.75rem;">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                <a href="blogs.php" class="btn btn-outline">Cancel</a>
                            </div>
                        </form>
                    </div>
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
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        (function() {
            const wrap = document.getElementById('previewWrap');
            const toggle = document.getElementById('previewToggle');
            const frame = document.getElementById('livePreview');
            const csrf = '<?php echo $_SESSION['csrf_token']; ?>';
            let timer = null;

            function refresh() {
                const content = document.querySelector('textarea[name="content"]').value;
                const fd = new FormData();
                fd.append('csrf_token', csrf);
                fd.append('content', content);
                fetch('preview.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function(r) { return r.text(); })
                    .then(function(html) { frame.srcdoc = html; })
                    .catch(function() {});
            }

            if (toggle && wrap && frame) {
                toggle.addEventListener('click', function() {
                    const open = wrap.style.display !== 'none';
                    wrap.style.display = open ? 'none' : 'block';
                    toggle.textContent = open ? 'Live Preview' : 'Hide Preview';
                    if (!open) refresh();
                });
            }

            const ta = document.querySelector('textarea[name="content"]');
            if (ta) {
                ta.addEventListener('input', function() {
                    clearTimeout(timer);
                    timer = setTimeout(function() {
                        if (wrap && wrap.style.display !== 'none') refresh();
                    }, 400);
                });
            }
        })();
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
