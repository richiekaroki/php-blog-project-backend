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
    <title><?php echo htmlspecialchars($post['title']); ?> - WAM Blog</title>
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

        /* NAV */
        .nav {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(251, 249, 241, 0.9);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
        }
        .nav-inner {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 1.5rem;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .nav-logo {
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
        .nav-title {
            font-family: 'Lora', serif;
            font-weight: 600;
            font-size: 1.2rem;
            color: var(--olive);
        }
        .nav-links { display: flex; gap: 1rem; align-items: center; }
        .nav-link {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            color: var(--muted-fg);
            transition: color 0.2s;
        }
        .nav-link:hover { color: var(--green); }

        /* POST HERO */
        .post-hero {
            padding: 4rem 1.5rem 3rem;
            background: linear-gradient(180deg, rgba(235, 227, 167, 0.4) 0%, var(--bg) 100%);
        }
        .post-hero-inner {
            max-width: 750px;
            margin: 0 auto;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--muted-fg);
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }
        .back-link:hover { color: var(--green); }
        .post-tag {
            display: inline-block;
            padding: 0.3rem 0.85rem;
            background: rgba(44,87,69,0.1);
            color: var(--green);
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }
        .post-hero h1 {
            font-size: clamp(2rem, 4vw, 2.75rem);
            font-weight: 700;
            line-height: 1.25;
            margin-bottom: 1rem;
        }

        /* POST CONTENT */
        .post-content {
            max-width: 750px;
            margin: 0 auto;
            padding: 0 1.5rem 4rem;
        }
        .post-image {
            width: 100%;
            border-radius: 1rem;
            margin-bottom: 2.5rem;
            max-height: 500px;
            object-fit: cover;
        }
        .post-body {
            font-size: 1.15rem;
            line-height: 1.85;
            color: var(--fg);
        }
        .post-body p { margin-bottom: 1.5rem; }

        /* SIDEBAR */
        .post-layout {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 1.5rem 4rem;
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 3rem;
        }
        .sidebar-card {
            background: var(--card);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .sidebar-card h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
        }
        .post-info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            font-size: 0.9rem;
        }
        .post-info-label { color: var(--muted-fg); }
        .post-info-value { font-weight: 500; color: var(--olive); }

        /* RELATED */
        .related-item {
            display: flex;
            gap: 0.75rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }
        .related-item:last-child { border-bottom: none; }
        .related-img {
            width: 56px;
            height: 56px;
            border-radius: 0.5rem;
            object-fit: cover;
            flex-shrink: 0;
            background: var(--muted);
        }
        .related-img-placeholder {
            width: 56px;
            height: 56px;
            border-radius: 0.5rem;
            background: linear-gradient(135deg, var(--cream), var(--muted));
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .related-img-placeholder svg { width: 20px; height: 20px; color: rgba(44,87,69,0.3); }
        .related-title {
            font-family: 'Lora', serif;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--olive);
            margin-bottom: 0.25rem;
            transition: color 0.2s;
        }
        .related-title:hover { color: var(--green); }
        .related-category {
            font-size: 0.8rem;
            color: var(--muted-fg);
        }

        /* FOOTER */
        .footer {
            border-top: 1px solid var(--border);
            padding: 3rem 1.5rem;
            background: rgba(235, 227, 167, 0.15);
        }
        .footer-inner {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        .footer-brand { display: flex; align-items: center; gap: 0.75rem; }
        .footer-text { color: var(--muted-fg); font-size: 0.9rem; }
        .footer-links { display: flex; gap: 1.5rem; }
        .footer-links a { color: var(--muted-fg); font-size: 0.9rem; }
        .footer-links a:hover { color: var(--green); }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .post-layout {
                grid-template-columns: 1fr;
            }
            .sidebar { order: -1; }
        }
    </style>
</head>
<body>
    <!-- Nav -->
    <nav class="nav">
        <div class="nav-inner">
            <a href="/" class="nav-brand">
                <div class="nav-logo">W</div>
                <span class="nav-title">WAM Blog</span>
            </a>
            <div class="nav-links">
                <a href="/" class="nav-link">Home</a>
                <a href="/admin/login.php" class="nav-link">Admin</a>
            </div>
        </div>
    </nav>

    <!-- Post Hero -->
    <header class="post-hero">
        <div class="post-hero-inner">
            <a href="index.php" class="back-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                Back to all stories
            </a>
            <?php if (!empty($post['category_name'])): ?>
                <span class="post-tag"><?php echo htmlspecialchars($post['category_name']); ?></span>
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($post['title']); ?></h1>
        </div>
    </header>

    <!-- Content + Sidebar -->
    <div class="post-layout">
        <article class="post-content">
            <?php if (!empty($post['image'])): ?>
                <img src="<?php echo htmlspecialchars($post['image']); ?>" 
                     class="post-image" 
                     alt="<?php echo htmlspecialchars($post['title']); ?>">
            <?php endif; ?>
            
            <div class="post-body">
                <?php echo nl2br(htmlspecialchars($post['content'])); ?>
            </div>
        </article>

        <aside class="sidebar">
            <!-- Post Info -->
            <div class="sidebar-card">
                <h3>Post Details</h3>
                <div class="post-info-item">
                    <span class="post-info-label">Category</span>
                    <span class="post-info-value"><?php echo htmlspecialchars($post['category_name']); ?></span>
                </div>
                <div class="post-info-item">
                    <span class="post-info-label">Post ID</span>
                    <span class="post-info-value">#<?php echo $post['id']; ?></span>
                </div>
            </div>

            <!-- Related Posts -->
            <?php if (!empty($relatedPosts)): ?>
                <div class="sidebar-card">
                    <h3>Related Stories</h3>
                    <?php foreach ($relatedPosts as $related): ?>
                        <div class="related-item">
                            <?php if (!empty($related['image'])): ?>
                                <img src="<?php echo htmlspecialchars($related['image']); ?>" 
                                     class="related-img" 
                                     alt="<?php echo htmlspecialchars($related['title']); ?>">
                            <?php else: ?>
                                <div class="related-img-placeholder">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"/></svg>
                                </div>
                            <?php endif; ?>
                            <div>
                                <a href="post.php?id=<?php echo $related['id']; ?>" class="related-title">
                                    <?php echo htmlspecialchars($related['title']); ?>
                                </a>
                                <span class="related-category"><?php echo htmlspecialchars($related['category_name']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </aside>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-inner">
            <div class="footer-brand">
                <div class="nav-logo" style="width: 32px; height: 32px; font-size: 0.9rem;">W</div>
                <span class="nav-title" style="font-size: 1rem;">WAM Blog</span>
            </div>
            <p class="footer-text">Crafted with care for readers who appreciate quality content.</p>
            <div class="footer-links">
                <a href="https://github.com/richiekaroki/php-blog-project-backend">GitHub</a>
                <a href="/admin/login.php">Admin</a>
            </div>
        </div>
    </footer>
</body>
</html>