<?php
// admin/blogs.php - Blog management with image upload

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/../inc/post-format.php';

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

// Create uploads directory if it doesn't exist
$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Handle DELETE (admin only, POST + CSRF â€” never via GET)
if (isset($_POST['delete'])) {
    if (!$canDelete) {
        http_response_code(403);
        die('Access denied: only admins can delete posts.');
    }

    if (!isset($_POST['csrf_token']) ||
        $_POST['csrf_token'] !== $_SESSION['csrf_token'] ||
        hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']) === false) {
        die('Invalid CSRF token');
    }

    $id = (int)$_POST['delete'];
    
    // Get image path before deleting
    $stmt = $pdo->prepare("SELECT image FROM blogs WHERE id = ?");
    $stmt->execute([$id]);
    $blog = $stmt->fetch();
    
    // Delete image file if exists
    if ($blog && !empty($blog['image'])) {
        $imagePath = __DIR__ . '/../' . $blog['image'];
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }
    
    // Delete blog from database
    $stmt = $pdo->prepare("DELETE FROM blogs WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: blogs.php");
    exit;
}

// Handle form submissions (create/edit â€” admin and editor)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!$canWrite) {
        http_response_code(403);
        die('Access denied: your role does not permit writing posts.');
    }

    if (!isset($_POST['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token'] ||
        hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']) === false) {
        die('Invalid CSRF token');
    }

    // Inline publish/unpublish toggle from the posts table
    if (isset($_POST['toggle_status']) && isset($_POST['blog_id'])) {
        $blogId = (int)$_POST['blog_id'];
        $stmt = $pdo->prepare("SELECT status FROM blogs WHERE id = ?");
        $stmt->execute([$blogId]);
        $row = $stmt->fetch();
        if ($row) {
            $newStatus = $row['status'] === 'published' ? 'draft' : 'published';
            $upd = $pdo->prepare("UPDATE blogs SET status = ? WHERE id = ?");
            $upd->execute([$newStatus, $blogId]);
        }
        $qs = [];
        if (!empty($_GET['q'])) $qs['q'] = $_GET['q'];
        if (!empty($_GET['page']) && (int)$_GET['page'] > 1) $qs['page'] = (int)$_GET['page'];
        header("Location: blogs.php" . ($qs ? '?' . http_build_query($qs) : ''));
        exit;
    }

    $title = trim((string)($_POST['title'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));
    $category_id = $_POST['category_id'] ?? null;
    $status = in_array($_POST['status'] ?? 'published', ['published', 'draft'], true) ? $_POST['status'] : 'published';

    // Server-side validation (mirrors DB constraints: title VARCHAR(255), content TEXT)
    if ($title === '' || mb_strlen($title) > 255) {
        die('Title is required and must be 255 characters or fewer.');
    }
    if ($content === '') {
        die('Content is required.');
    }
    $imagePath = null;

    // Handle image upload â€” HIGH-1: validate actual content, not just MIME
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        // Validate file size
        if ($_FILES['image']['size'] > $maxSize) {
            die('File too large. Maximum size is 5MB.');
        }
        
        // HIGH-1: Validate actual image content (not just MIME type)
        $imageInfo = getimagesize($_FILES['image']['tmp_name']);
        if ($imageInfo === false) {
            die('Invalid image file.');
        }
        
        // Map MIME to extension (no user-controlled extension)
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
        
        // Generate cryptographically random filename
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $filepath = $uploadDir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
            $imagePath = 'uploads/' . $filename;
        } else {
            die('Failed to upload image.');
        }
    }

    if (isset($_POST['blog_id'])) {
        // Edit blog
        $id = (int)$_POST['blog_id'];
        
        // Get old image path
        $stmt = $pdo->prepare("SELECT image FROM blogs WHERE id = ?");
        $stmt->execute([$id]);
        $oldBlog = $stmt->fetch();
        
        // Delete old image if new one uploaded
        if ($imagePath && $oldBlog && !empty($oldBlog['image'])) {
            $oldImagePath = __DIR__ . '/../' . $oldBlog['image'];
            if (file_exists($oldImagePath)) {
                unlink($oldImagePath);
            }
        }
        
        // Update blog
        if ($imagePath) {
            $sql = "UPDATE blogs SET title = ?, content = ?, image = ?, category_id = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$title, $content, $imagePath, $category_id, $id]);
        } else {
            $sql = "UPDATE blogs SET title = ?, content = ?, category_id = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$title, $content, $category_id, $id]);
        }
    } else {
        // Create new blog
        $sql = "INSERT INTO blogs (title, content, image, category_id, status) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $content, $imagePath, $category_id, $status]);
    }
    header("Location: blogs.php");
    exit;
}

// Search + pagination for the posts table
$search = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$where = '';
$params = [];
if ($search !== '') {
    $where = " WHERE b.title ILIKE ? OR b.content ILIKE ?";
    $params = ['%' . $search . '%', '%' . $search . '%'];
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM blogs b" . $where);
$countStmt->execute($params);
$totalBlogs = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalBlogs / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT b.*, c.name AS category_name FROM blogs b LEFT JOIN categories c ON b.category_id = c.id" . $where . " ORDER BY b.id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$blogs = $stmt->fetchAll();

// Fetch categories for form
$catStmt = $pdo->query("SELECT * FROM categories");
$categories = $catStmt->fetchAll();

// Quick stats for the welcome band
$statPosts = (int)$pdo->query("SELECT COUNT(*) FROM blogs WHERE status = 'published'")->fetchColumn();
$statCats = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$statRecent = (int)$pdo->query("SELECT COUNT(*) FROM activity_log WHERE created_at >= NOW() - INTERVAL '7 days'")->fetchColumn();

$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$greetingName = Auth::currentUsername() ?? 'author';
$roleLabel = ucfirst($currentRole);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Management · WAM Blog</title>
    <link rel="stylesheet" href="../assets/admin.css">
    <script>
        (function() {
            if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>
</head>
<body>
    <div class="layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img class="sidebar-logo" src="/favicon.svg" alt="WAM" width="64" height="64">
                <div>
                    <div class="sidebar-brand">WAM Blog</div>
                    <div class="sidebar-sub">Content Studio</div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <p class="nav-section-label">Manage</p>
                <a href="blogs.php" class="nav-item active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
                    Blogs
                </a>
                <a href="categories.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg>
                    Categories
                </a>
                <?php if (Auth::getRole() === 'admin'): ?>
                <a href="users.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Users
                </a>
                <?php endif; ?>
                <a href="activity.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                    Activity
                </a>
                <p class="nav-section-label" style="margin-top: 1rem;">Site</p>
                <a href="/" class="nav-item" target="_blank">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                    View Site
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="login.php?action=logout" class="nav-item danger">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                    Logout
                </a>
            </div>
        </aside>
        <div class="sidebar-backdrop" onclick="closeSidebar()"></div>

        <!-- Main -->
        <div class="main">
            <header class="header">
                <button class="menu-btn" onclick="toggleSidebar()" aria-label="Open navigation menu" aria-expanded="false" id="menuBtn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
                <h1 class="header-title">Blog Management</h1>
                <div class="header-actions">
                    <span class="header-meta"><?php echo count($blogs); ?> posts</span>
                    <button class="theme-toggle" onclick="toggleTheme()" title="Toggle theme" aria-label="Toggle theme">&#9681;</button>
                </div>
            </header>

            <div class="content">
                <!-- Welcome band -->
                <div class="welcome-band">
                    <div>
                        <h2 class="welcome-title"><?php echo htmlspecialchars($greeting); ?>, <?php echo htmlspecialchars($greetingName); ?></h2>
                        <p class="welcome-sub">This is your writing desk. Everything here is kept the old way — one careful edit or commit at a time.</p>
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

                <!-- Add Blog Form (admin + editor only) -->
                <?php if ($canWrite): ?>
                <div class="card">
                    <div class="card-header">
                        <h2>Create New Post</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="blogs.php" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="blog_id" value="">
                            
                            <div class="form-row">
                                <div>
                                    <div class="form-group">
                                        <label class="form-label">Title *</label>
                                        <input type="text" name="title" class="form-input" required placeholder="Enter post title">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Content *</label>
                                        <textarea name="content" class="form-textarea" rows="8" required placeholder="Write your story..."></textarea>
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="form-group">
                                        <label class="form-label">Category *</label>
                                        <select name="category_id" class="form-select" required>
                                            <option value="">Select a topic</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Featured Image</label>
                                        <input type="file" name="image" class="form-input" accept="image/*" onchange="previewImage(this)">
                                        <p class="form-help">Max 5MB. JPEG, PNG, GIF, WebP.</p>
                                        <img id="imagePreview" class="upload-preview" alt="Preview">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select">
                                            <option value="published">Published</option>
                                            <option value="draft">Draft</option>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary" style="width: 100%;">Publish Post</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Blogs Table -->
                <div class="card">
                    <div class="card-header">
                        <h2>All Posts</h2>
                        <form method="GET" action="blogs.php" class="table-search" role="search">
                            <input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search posts…" class="form-input" aria-label="Search posts">
                            <button type="submit" class="btn btn-outline btn-sm">Search</button>
                            <?php if ($search !== ''): ?>
                                <a href="blogs.php" class="btn btn-outline btn-sm">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <?php if (empty($blogs)): ?>
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"/></svg>
                            <p>No posts yet. Create your first story above!</p>
                        </div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 80px;">Image</th>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th style="width: 120px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($blogs as $blog): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($blog['image'])): ?>
                                                <img src="../<?php echo htmlspecialchars($blog['image']); ?>" class="blog-thumb" alt="">
                                            <?php else: ?>
                                                <div class="blog-thumb-placeholder">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"/></svg>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="blog-title"><?php echo htmlspecialchars($blog['title']); ?></div>
                                            <div class="blog-excerpt"><?php echo htmlspecialchars(mb_strimwidth(stripMarkdown($blog['content']), 0, 60, '...')); ?></div>
                                        </td>
                                        <td><span class="tag"><?php echo htmlspecialchars($blog['category_name'] ?? 'Uncategorized'); ?></span></td>
                                        <td>
                                            <span class="badge <?php echo $blog['status'] === 'draft' ? 'draft' : 'admin'; ?>"><?php echo htmlspecialchars($blog['status'] ?? 'published'); ?></span>
                                            <?php if ($canWrite): ?>
                                            <form method="POST" action="blogs.php" style="display: inline-block; margin-left: 0.5rem;">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="toggle_status" value="1">
                                                <input type="hidden" name="blog_id" value="<?php echo $blog['id']; ?>">
                                                <button type="submit" class="btn btn-outline btn-sm" title="<?php echo $blog['status'] === 'draft' ? 'Publish this story' : 'Unpublish (save as draft)'; ?>">
                                                    <?php echo $blog['status'] === 'draft' ? 'Publish' : 'Unpublish'; ?>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <?php if ($canWrite): ?>
                                                <a href="edit-blog.php?id=<?php echo $blog['id']; ?>" class="btn btn-outline btn-sm">Edit</a>
                                                <?php endif; ?>
                                                <?php if ($canDelete): ?>
                                                <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $blog['id']; ?>, <?php echo htmlspecialchars(json_encode($blog['title']), ENT_QUOTES, 'UTF-8'); ?>)">Delete</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a class="page-link" href="blogs.php?q=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>">Prev</a>
                                <?php endif; ?>
                                <span class="page-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?> · <?php echo $totalBlogs; ?> posts</span>
                                <?php if ($page < $totalPages): ?>
                                    <a class="page-link" href="blogs.php?q=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>">Next</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <h3>Delete Post</h3>
            <p>Are you sure you want to delete "<span id="deleteBlogTitle"></span>"?</p>
            <p style="color: var(--destructive); font-size: 0.85rem;">This action cannot be undone.</p>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                <form method="POST" action="blogs.php" id="deleteForm" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="delete" id="deleteId" value="">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>

    <script>
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
        
        function confirmDelete(id, title) {
            document.getElementById('deleteBlogTitle').textContent = title;
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
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