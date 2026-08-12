<?php
// admin/blogs.php - Blog management with image upload

require '../includes/auth.php';
require '../includes/connect.php';
require '../includes/csrf.php';

// Role check: admin full access, editor can edit, viewer denied
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Create uploads directory if it doesn't exist
$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Handle DELETE
if (isset($_GET['delete'])) {
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

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token'] ||
        hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']) === false) {
        die('Invalid CSRF token');
    }

    $title = $_POST['title'];
    $content = $_POST['content'];
    $category_id = $_POST['category_id'] ?? null;
    $imagePath = null;

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        // Validate file type
        if (!in_array($_FILES['image']['type'], $allowedTypes)) {
            die('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.');
        }
        
        // Validate file size
        if ($_FILES['image']['size'] > $maxSize) {
            die('File too large. Maximum size is 5MB.');
        }
        
        // Generate unique filename
        $filename = uniqid('blog_', true) . '_' . basename($_FILES['image']['name']);
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
    <title>Blog Management</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        .blog-image { width: 80px; height: 60px; object-fit: cover; border-radius: 5px; }
        .upload-preview { max-width: 200px; max-height: 150px; border-radius: 5px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container py-4">
        <h1 class="mb-4">Blog Management</h1>

        <!-- Add Blog Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0">Add New Blog</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="blogs.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="blog_id" value="">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Title *</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Content *</label>
                                <textarea name="content" class="form-control" rows="6" required></textarea>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Category *</label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">-- Select Category --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Featured Image</label>
                                <input type="file" name="image" class="form-control" accept="image/*" onchange="previewImage(this)">
                                <small class="text-muted">Max 5MB. JPEG, PNG, GIF, WebP.</small>
                                <img id="imagePreview" class="upload-preview d-none" alt="Preview">
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Create Blog</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Blogs Table -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">All Blogs (<?php echo count($blogs); ?>)</h4>
            </div>
            <div class="card-body">
                <?php if (empty($blogs)): ?>
                    <div class="alert alert-info mb-0">No blogs yet. Create your first blog post above!</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($blogs as $blog): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($blog['image'])): ?>
                                            <img src="../<?php echo htmlspecialchars($blog['image']); ?>" class="blog-image" alt="Blog image">
                                        <?php else: ?>
                                            <div class="blog-image bg-light d-flex align-items-center justify-content-center">📄</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($blog['title']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($blog['content'], 0, 50)) . '...'; ?></small>
                                    </td>
                                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($blog['category_name'] ?? 'Uncategorized'); ?></span></td>
                                    <td>
                                        <a href="edit-blog.php?id=<?php echo $blog['id']; ?>" class="btn btn-sm btn-info">Edit</a>
                                        <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $blog['id']; ?>, '<?php echo htmlspecialchars($blog['title']); ?>')">Delete</button>
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

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete "<span id="deleteBlogTitle"></span>"?</p>
                    <p class="text-danger"><small>This action cannot be undone.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="deleteLink" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.min.js"></script>
    <script>
        // Image preview
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('d-none');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Delete confirmation
        function confirmDelete(id, title) {
            document.getElementById('deleteBlogTitle').textContent = title;
            document.getElementById('deleteLink').href = 'blogs.php?delete=' + id;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>