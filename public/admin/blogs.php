<?php
// admin/blogs.php - Blog management with image upload

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

// Create uploads directory if it doesn't exist
$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Handle DELETE (admin only)
if (isset($_GET['delete'])) {
    if (!$canDelete) {
        http_response_code(403);
        die('Access denied: only admins can delete posts.');
    }

    $id = (int)$_GET['delete'];
    
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

// Handle form submissions (create/edit — admin and editor)
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

    $title = $_POST['title'];
    $content = $_POST['content'];
    $category_id = $_POST['category_id'] ?? null;
    $imagePath = null;

    // Handle image upload — HIGH-1: validate actual content, not just MIME
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
        $sql = "INSERT INTO blogs (title, content, image, category_id) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $content, $imagePath, $category_id]);
    }
    header("Location: blogs.php");
    exit;
}

// Fetch blogs with categories using PDO
$stmt = $pdo->query("SELECT b.*, c.name AS category_name FROM blogs b LEFT JOIN categories c ON b.category_id = c.id ORDER BY b.id DESC");
$blogs = $stmt->fetchAll();

// Fetch categories for form
$catStmt = $pdo->query("SELECT * FROM categories");
$categories = $catStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Management - WAM Blog</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Source+Sans+3:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #FBF9F1;
            --fg: #2E2910;
            --card: #FFFFFF;
            --primary: #2C5745;
            --primary-fg: #FFFFFF;
            --muted: #F5F0DC;
            --muted-fg: #5C5340;
            --border: #D4C9A8;
            --cream: #EBE3A7;
            --orange: #EB7D00;
            --green: #2C5745;
            --olive: #2E2910;
            --destructive: #C53030;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Source Sans 3', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--fg);
            line-height: 1.6;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Lora', Georgia, serif;
            color: var(--olive);
        }

        a { color: var(--green); text-decoration: none; transition: color 0.2s; }
        a:hover { color: var(--orange); }

        /* LAYOUT */
        .layout {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */
        .sidebar {
            width: 260px;
            background: var(--card);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
        }
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .sidebar-logo {
            width: 36px;
            height: 36px;
            background: var(--green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-family: 'Lora', serif;
            font-weight: 700;
            font-size: 1.1rem;
        }
        .sidebar-brand {
            font-family: 'Lora', serif;
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--olive);
        }
        .sidebar-sub {
            font-size: 0.75rem;
            color: var(--muted-fg);
            margin-top: 0.15rem;
        }
        .sidebar-nav {
            flex: 1;
            padding: 1rem;
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--muted-fg);
            transition: all 0.2s;
            margin-bottom: 0.25rem;
        }
        .nav-item:hover { background: var(--muted); color: var(--fg); }
        .nav-item.active {
            background: rgba(44,87,69,0.1);
            color: var(--green);
        }
        .nav-item svg { width: 20px; height: 20px; }

        /* MAIN */
        .main {
            flex: 1;
            margin-left: 260px;
        }
        .header {
            height: 64px;
            background: var(--card);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 40;
        }
        .header-title {
            font-family: 'Lora', serif;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .header-actions { display: flex; gap: 0.5rem; }
        .content { padding: 2rem; }

        /* BUTTONS */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.25rem;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }
        .btn-primary { background: var(--green); color: white; }
        .btn-primary:hover { background: #234a3a; }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--muted-fg);
        }
        .btn-outline:hover { border-color: var(--green); color: var(--green); }
        .btn-danger { background: var(--destructive); color: white; }
        .btn-danger:hover { background: #a02020; }
        .btn-sm { padding: 0.4rem 0.75rem; font-size: 0.8rem; }
        .btn-ghost {
            background: transparent;
            color: var(--muted-fg);
            padding: 0.5rem;
        }
        .btn-ghost:hover { color: var(--fg); background: var(--muted); }

        /* CARDS */
        .card {
            background: var(--card);
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            margin-bottom: 1.5rem;
        }
        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card-header h2 {
            font-size: 1.1rem;
            font-weight: 600;
        }
        .card-body { padding: 1.5rem; }

        /* FORMS */
        .form-group { margin-bottom: 1.25rem; }
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--olive);
            margin-bottom: 0.5rem;
        }
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 0.65rem 1rem;
            font-size: 0.95rem;
            font-family: inherit;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            background: var(--bg);
            color: var(--fg);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(44,87,69,0.1);
        }
        .form-textarea { resize: vertical; min-height: 120px; }
        .form-help {
            font-size: 0.8rem;
            color: var(--muted-fg);
            margin-top: 0.35rem;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 1.5rem;
        }

        /* TABLE */
        .table-wrap { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 0.85rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        th {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--muted-fg);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        tr:hover td { background: rgba(245, 240, 220, 0.5); }
        .blog-thumb {
            width: 64px;
            height: 48px;
            border-radius: 0.375rem;
            object-fit: cover;
            background: var(--muted);
        }
        .blog-thumb-placeholder {
            width: 64px;
            height: 48px;
            border-radius: 0.375rem;
            background: linear-gradient(135deg, var(--cream), var(--muted));
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .blog-thumb-placeholder svg { width: 20px; height: 20px; color: rgba(44,87,69,0.3); }
        .blog-title { font-weight: 600; color: var(--olive); }
        .blog-excerpt {
            font-size: 0.85rem;
            color: var(--muted-fg);
            margin-top: 0.2rem;
        }
        .tag {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            background: rgba(44,87,69,0.1);
            color: var(--green);
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 500;
        }

        /* PREVIEW */
        .upload-preview {
            max-width: 200px;
            max-height: 150px;
            border-radius: 0.5rem;
            margin-top: 0.75rem;
            display: none;
        }

        /* MODAL */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 100;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: var(--card);
            border-radius: 1rem;
            padding: 2rem;
            max-width: 420px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        .modal h3 { margin-bottom: 0.75rem; }
        .modal p { color: var(--muted-fg); margin-bottom: 0.5rem; }
        .modal-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--muted-fg);
        }
        .empty-state svg { width: 48px; height: 48px; color: rgba(44,87,69,0.2); margin: 0 auto 1rem; }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">W</div>
                <div>
                    <div class="sidebar-brand">WAM Blog</div>
                    <div class="sidebar-sub">Content Studio</div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a href="blogs.php" class="nav-item active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
                    Blogs
                </a>
                <a href="categories.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg>
                    Categories
                </a>
                <a href="/" class="nav-item" target="_blank">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                    View Site
                </a>
            </nav>
            <div style="padding: 1rem; border-top: 1px solid var(--border);">
                <a href="login.php?action=logout" class="nav-item" style="color: var(--destructive);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                    Logout
                </a>
            </div>
        </aside>

        <!-- Main -->
        <div class="main">
            <header class="header">
                <h1 class="header-title">Blog Management</h1>
                <div class="header-actions">
                    <span style="font-size: 0.85rem; color: var(--muted-fg);"><?php echo count($blogs); ?> posts</span>
                </div>
            </header>

            <div class="content">
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
                                            <div class="blog-excerpt"><?php echo htmlspecialchars(mb_strimwidth($blog['content'], 0, 60, '...')); ?></div>
                                        </td>
                                        <td><span class="tag"><?php echo htmlspecialchars($blog['category_name'] ?? 'Uncategorized'); ?></span></td>
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
                <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
                <a href="#" id="deleteLink" class="btn btn-danger">Delete</a>
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
            document.getElementById('deleteLink').href = 'blogs.php?delete=' + id;
            document.getElementById('deleteModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>
</html>