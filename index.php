<?php
// index.php - Blog homepage with pagination, search, category filtering

require 'includes/connect.php';
require 'includes/headers.php';

// --- Pagination, Search, Category Filter ---
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 9;
$offset = ($page - 1) * $perPage;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Build query conditions
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(blogs.title ILIKE ? OR blogs.content ILIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($categoryId > 0) {
    $where[] = "blogs.category_id = ?";
    $params[] = $categoryId;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total posts
$countSql = "SELECT COUNT(*) FROM blogs $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalPosts = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalPosts / $perPage));

// Fetch posts
$sql = "SELECT blogs.id, blogs.title, blogs.content, blogs.image, categories.name AS category_name
        FROM blogs
        LEFT JOIN categories ON blogs.category_id = categories.id
        $whereClause
        ORDER BY blogs.id DESC
        LIMIT ? OFFSET ?";
$allParams = array_merge($params, [$perPage, $offset]);
$stmt = $pdo->prepare($sql);
$stmt->execute($allParams);
$posts = $stmt->fetchAll();

// Fetch all categories for filter dropdown
$catStmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
$categories = $catStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog - All Posts</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <style>
        .hero { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 60px 0; margin-bottom: 40px; }
        .blog-card { transition: transform 0.2s; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .blog-card:hover { transform: translateY(-4px); }
        .blog-card .card-img-top { height: 200px; object-fit: cover; }
        .blog-card .card-placeholder { height: 200px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; }
        .filter-bar { background: #f8f9fa; padding: 15px 0; margin-bottom: 30px; }
    </style>
</head>
<body>
    <!-- Hero -->
    <div class="hero">
        <div class="container">
            <h1 class="display-4">Blog</h1>
            <p class="lead">Read our latest articles and stories</p>
        </div>
    </div>

    <div class="container">
        <!-- Search & Filter Bar -->
        <div class="filter-bar">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="Search posts..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-4">
                    <select name="category" class="form-select">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $categoryId == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <?php if ($search !== '' || $categoryId > 0): ?>
                        <a href="index.php" class="btn btn-outline-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Results Info -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <p class="text-muted mb-0">
                <?php echo $totalPosts; ?> post<?php echo $totalPosts !== 1 ? 's' : ''; ?> found
                <?php if ($search !== ''): ?>
                    for "<strong><?php echo htmlspecialchars($search); ?></strong>"
                <?php endif; ?>
            </p>
        </div>

        <!-- Blog Grid -->
        <?php if (empty($posts)): ?>
            <div class="text-center py-5">
                <h4 class="text-muted">No posts found</h4>
                <p>Try adjusting your search or filter criteria.</p>
                <a href="index.php" class="btn btn-primary">View All Posts</a>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-5">
                <?php foreach ($posts as $post): ?>
                    <div class="col">
                        <div class="card blog-card h-100">
                            <?php if (!empty($post['image'])): ?>
                                <img src="<?php echo htmlspecialchars($post['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($post['title']); ?>">
                            <?php else: ?>
                                <div class="card-placeholder">
                                    <span class="text-muted fs-1">&#128221;</span>
                                </div>
                            <?php endif; ?>
                            <div class="card-body d-flex flex-column">
                                <?php if (!empty($post['category_name'])): ?>
                                    <span class="badge bg-primary mb-2 align-self-start"><?php echo htmlspecialchars($post['category_name']); ?></span>
                                <?php endif; ?>
                                <h5 class="card-title"><?php echo htmlspecialchars($post['title']); ?></h5>
                                <p class="card-text text-muted flex-grow-1">
                                    <?php echo htmlspecialchars(mb_strimwidth($post['content'], 0, 120, '...')); ?>
                                </p>
                                <a href="post.php?id=<?php echo $post['id']; ?>" class="btn btn-outline-primary mt-auto">Read More</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $categoryId; ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $categoryId; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $categoryId; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="assets/js/bootstrap.min.js"></script>
</body>
</html>
