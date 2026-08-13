<?php
// admin/categories.php - Category management with PDO and CSRF protection

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Database\Connection;
use App\Middleware\Auth;
use App\Middleware\CSRF;

Auth::check();
$pdo = Connection::getInstance();
CSRF::init();

// Role check: admin full access, editor can edit, viewer denied
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Handle DELETE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: categories.php");
    exit;
}

// Handle form submission (add/edit)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token'] ||
        hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']) === false) {
        die('Invalid CSRF token');
    }

    if (isset($_POST['category_id'])) {
        // Edit category
        $id = (int)$_POST['category_id'];
        $name = $_POST['name'];
        $description = $_POST['description'] ?? '';

        $sql = "UPDATE categories SET name = ?, description = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $description, $id]);
    } else {
        // Add new category
        $name = $_POST['name'];
        $description = $_POST['description'] ?? '';

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
    <meta charset="UTF-8">
    <title>Categories Management</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>
<body>
    <div class="container">
        <h1>Category Management</h1>

        <!-- Add Category Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h4>Add New Category</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="categories.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="category_id" value="">
                    <div class="form-group">
                        <label>Name:</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Description:</label>
                        <textarea name="description" class="form-control"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </form>
            </div>
        </div>

        <!-- Categories Table -->
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td><?= $cat['id']; ?></td>
                        <td><?= htmlspecialchars($cat['name']); ?></td>
                        <td><?= htmlspecialchars($cat['description']); ?></td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="window.location='?edit=<?= $cat['id']; ?>'">Edit</button>
                            <button class="btn btn-sm btn-danger" onclick="if(confirm('Delete category ID <?= $cat['id']?>?')) window.location='categories.php?delete=<?= $cat['id']; ?>'">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Edit Form Section -->
        <?php if (isset($_GET['edit'])): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h4>Edit Category</h4>
            </div>
            <div class="card-body">
                <?php if ($editCat): ?>
                <form method="POST" action="categories.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="category_id" value="<?= $editCat['id']; ?>">
                    <div class="form-group">
                        <label>Name:</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($editCat['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Description:</label>
                        <textarea name="description" class="form-control"><?= htmlspecialchars($editCat['description']); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Category</button>
                </form>
                <?php else: ?>
                <div class="alert alert-danger">Category not found.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="/assets/js/jquery.min.js"></script>
    <script src="/assets/js/bootstrap.min.js"></script>
</body>
</html>