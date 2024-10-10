<?php
// index.php - Display blogs with pagination

require 'includes/connect.php';

$limit = 5;  // Number of blogs per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch blogs with pagination
$sql = "SELECT blogs.id, blogs.title, blogs.content, categories.name AS category_name
        FROM blogs
        JOIN categories ON blogs.category_id = categories.id
        LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

// Get total number of blogs for pagination
$count_sql = "SELECT COUNT(id) AS total FROM blogs";
$count_result = $conn->query($count_sql);
$total_blogs = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_blogs / $limit);
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

        <!-- Pagination Links -->
        <nav aria-label="Page navigation">
            <ul class="pagination">
                <?php if ($page > 1): ?>
                <li class="page-item"><a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a></li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                <li class="page-item"><a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</body>

</html>