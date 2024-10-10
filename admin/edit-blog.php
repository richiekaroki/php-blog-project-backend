<?php
// edit-blog.php
require '../includes/auth.php';  // Ensure the user is authenticated
require '../includes/connect.php';

// Existing code for blog editing...
if (!isset($_GET['id'])) {
    die("Blog ID is required.");
}

$blog_id = $_GET['id'];

// Fetch blog details
$sql = "SELECT * FROM blogs WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $blog_id);
$stmt->execute();
$result = $stmt->get_result();
$blog = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $content = $_POST['content'];

    $sql = "UPDATE blogs SET title = ?, content = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $title, $content, $blog_id);
    $stmt->execute();
    header("Location: blogs.php");  // Redirect to blog listing
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
            <div class="form-group">
                <label for="title">Title:</label>
                <input type="text" name="title" class="form-control" value="<?php echo $blog['title']; ?>" required>
            </div>
            <div class="form-group">
                <label for="content">Content:</label>
                <textarea name="content" class="form-control" required><?php echo $blog['content']; ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</body>

</html>