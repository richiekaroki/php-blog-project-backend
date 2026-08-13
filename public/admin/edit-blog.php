<?php
// admin/edit-blog.php - Edit blog with PDO and CSRF protection

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Database\Connection;
use App\Middleware\Auth;
use App\Middleware\CSRF;

Auth::check();
$pdo = Connection::getInstance();
CSRF::init();

// Validate CSRF token
if (!isset($_POST['csrf_token']) || 
    $_POST['csrf_token'] !== $_SESSION['csrf_token'] ||
    hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']) === false) {
    die('Invalid CSRF token');
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
    $title = $_POST['title'];
    $content = $_POST['content'];

    $sql = "UPDATE blogs SET title = ?, content = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$title, $content, $blog_id]);
    header("Location: list-blogs.php");  // Redirect to blog listing
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Blog</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>
<body>
    <div class="container">
        <h2>Edit Blog</h2>
        <form method="POST" action="edit-blog.php?id=<?php echo $blog_id; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="form-group">
                <label for="title">Title:</label>
                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($blog['title']); ?>" required>
            </div>
            <div class="form-group">
                <label for="content">Content:</label>
                <textarea name="content" class="form-control" required><?php echo htmlspecialchars($blog['content']); ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</body>
</html>