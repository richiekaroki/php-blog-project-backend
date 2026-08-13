<?php
// admin/list-blogs.php - Blog list with PDO pagination

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Database\Connection;

$pdo = Connection::getInstance();

// Pagination settings
$limit = 5;  // Number of blogs per page
$page = max(1, (int)($_GET['page'] ?? 1));  // Validate page number
$offset = ($page - 1) * $limit;

// Fetch blogs with PDO prepared statement (LIMIT/OFFSET with validated integers)
$sql = "SELECT blogs.id, blogs.title, blogs.content, categories.name AS category_name
        FROM blogs
        JOIN categories ON blogs.category_id = categories.id
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$limit, $offset]);
$result = $stmt->fetchAll();

// Get total number of blogs for pagination
$count_sql = "SELECT COUNT(id) AS total FROM blogs";
$count_stmt = $pdo->query($count_sql);
$total_blogs = $count_stmt->fetch()['total'];
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
        <?php foreach ($result as $row): ?>
        <div class="blog-post">
            <h2><?php echo htmlspecialchars($row['title']); ?></h2>
            <p><strong>Category: </strong><?php echo htmlspecialchars($row['category_name']); ?></p>
            <p><?php echo htmlspecialchars($row['content']); ?></p>
        </div>
        <hr>
        <?php endforeach; ?>

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