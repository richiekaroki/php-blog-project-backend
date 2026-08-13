<?php
// post.php - Display single blog post

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Database\Connection;
use App\Middleware\SecurityHeaders;

$pdo = Connection::getInstance();
SecurityHeaders::send();

// Get post ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch post with category
$stmt = $pdo->prepare("
    SELECT blogs.*, categories.name AS category_name 
    FROM blogs 
    JOIN categories ON blogs.category_id = categories.id 
    WHERE blogs.id = ?
");
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) {
    header("Location: index.php");
    exit;
}

// Fetch related posts (same category, excluding current)
$relatedStmt = $pdo->prepare("
    SELECT blogs.id, blogs.title, blogs.image, categories.name AS category_name
    FROM blogs
    JOIN categories ON blogs.category_id = categories.id
    WHERE blogs.category_id = ? AND blogs.id != ?
    ORDER BY blogs.id DESC
    LIMIT 3
");
$relatedStmt->execute([$post['category_id'], $id]);
$relatedPosts = $relatedStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?> - Blog</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <style>
        .post-hero { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            padding: 60px 0; 
            margin-bottom: 40px;
        }
        .post-content { font-size: 1.1rem; line-height: 1.8; }
        .related-card { transition: transform 0.2s; }
        .related-card:hover { transform: translateY(-3px); }
    </style>
</head>
<body>
    <!-- Post Hero -->
    <div class="post-hero">
        <div class="container">
            <a href="index.php" class="text-white text-decoration-none mb-3 d-inline-block">&larr; Back to All Posts</a>
            <span class="badge bg-light text-dark mb-3"><?php echo htmlspecialchars($post['category_name']); ?></span>
            <h1 class="display-4"><?php echo htmlspecialchars($post['title']); ?></h1>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <?php if (!empty($post['image'])): ?>
                    <img src="<?php echo htmlspecialchars($post['image']); ?>" 
                         class="img-fluid rounded mb-4" 
                         alt="<?php echo htmlspecialchars($post['title']); ?>">
                <?php endif; ?>
                
                <div class="post-content">
                    <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Post Info Card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Post Information</h5>
                        <ul class="list-unstyled">
                            <li class="mb-2"><strong>Category:</strong> <?php echo htmlspecialchars($post['category_name']); ?></li>
                            <li class="mb-2"><strong>Post ID:</strong> #<?php echo $post['id']; ?></li>
                        </ul>
                    </div>
                </div>

                <!-- Related Posts -->
                <?php if (!empty($relatedPosts)): ?>
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Related Posts</h5>
                            <?php foreach ($relatedPosts as $related): ?>
                                <div class="d-flex mb-3 related-card">
                                    <?php if (!empty($related['image'])): ?>
                                        <img src="<?php echo htmlspecialchars($related['image']); ?>" 
                                             class="rounded me-3" 
                                             style="width: 60px; height: 60px; object-fit: cover;"
                                             alt="<?php echo htmlspecialchars($related['title']); ?>">
                                    <?php else: ?>
                                        <div class="rounded me-3 d-flex align-items-center justify-content-center bg-light"
                                             style="width: 60px; height: 60px;">
                                            <span class="text-muted">📄</span>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <a href="post.php?id=<?php echo $related['id']; ?>" class="text-decoration-none">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($related['title']); ?></h6>
                                        </a>
                                        <small class="text-muted"><?php echo htmlspecialchars($related['category_name']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.min.js"></script>
</body>
</html>