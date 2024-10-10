<?php
// index.php - Display blogs with categories

require 'includes/connect.php';

$sql = "SELECT blogs.id, blogs.title, blogs.content, categories.name AS category_name
        FROM blogs
        JOIN categories ON blogs.category_id = categories.id";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>

<head>
    <title>Blogs</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
</head>

<body>
    <div class="container">
        <h1>Blog List</h1>
        <?php while ($row = $result->fetch_assoc()): ?>
        <div class="blog-post">
            <h2><?php echo $row['title']; ?></h2>
            <p><strong>Category: </strong><?php echo $row['category_name']; ?></p>
            <p><?php echo $row['content']; ?></p>
        </div>
        <hr>
        <?php endwhile; ?>
    </div>
</body>

</html>