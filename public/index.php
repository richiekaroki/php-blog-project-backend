<?php
// index.php - Blog homepage with pagination, search, category filtering

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Database\Connection;
use App\Middleware\SecurityHeaders;

$pdo = Connection::getInstance();
SecurityHeaders::send();

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
    <title>WAM Blog - Stories Worth Your Time</title>
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
        .btn-outline {
            padding: 0.5rem 1.25rem;
            border: 1px solid var(--green);
            color: var(--green);
            border-radius: 0.5rem;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            background: transparent;
            transition: all 0.2s;
        }
        .btn-outline:hover { background: var(--green); color: white; }

        /* HERO */
        .hero {
            padding: 5rem 1.5rem 4rem;
            text-align: center;
            background: linear-gradient(180deg, rgba(235, 227, 167, 0.3) 0%, var(--bg) 100%);
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(44, 87, 69, 0.1);
            color: var(--green);
            border-radius: 2rem;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 2rem;
        }
        .hero h1 {
            font-size: clamp(2.5rem, 5vw, 3.5rem);
            font-weight: 700;
            line-height: 1.15;
            margin-bottom: 1.5rem;
            color: var(--olive);
        }
        .hero p {
            font-size: 1.2rem;
            color: var(--muted-fg);
            max-width: 600px;
            margin: 0 auto 2.5rem;
            line-height: 1.7;
        }
        .hero-actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.85rem 2rem;
            background: var(--green);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-primary:hover { background: #234a3a; color: white; }
        .btn-ghost {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.85rem 2rem;
            background: transparent;
            color: var(--muted-fg);
            border: none;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
        }
        .btn-ghost:hover { color: var(--green); }

        /* SECTION */
        .section { padding: 4rem 1.5rem; }
        .section-header {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            max-width: 1100px;
            margin: 0 auto 2.5rem;
        }
        .section-header h2 { font-size: 1.75rem; }
        .section-header a {
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        .container { max-width: 1100px; margin: 0 auto; }

        /* BLOG GRID */
        .blog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
        }
        .blog-card {
            background: var(--card);
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .blog-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.1); }
        .blog-card-img {
            aspect-ratio: 16/10;
            overflow: hidden;
            background: var(--muted);
        }
        .blog-card-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        .blog-card:hover .blog-card-img img { transform: scale(1.05); }
        .blog-card-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--cream) 0%, var(--muted) 100%);
        }
        .blog-card-placeholder svg { width: 48px; height: 48px; color: rgba(44,87,69,0.2); }
        .blog-card-body { padding: 1.5rem; }
        .blog-card-tag {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: rgba(44,87,69,0.1);
            color: var(--green);
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 0.75rem;
        }
        .blog-card-title {
            font-family: 'Lora', serif;
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--olive);
            transition: color 0.2s;
        }
        .blog-card:hover .blog-card-title { color: var(--green); }
        .blog-card-excerpt {
            color: var(--muted-fg);
            font-size: 0.9rem;
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* ABOUT */
        .about {
            padding: 5rem 1.5rem;
            background: rgba(235, 227, 167, 0.2);
            text-align: center;
        }
        .about h2 { font-size: 1.75rem; margin-bottom: 1.5rem; }
        .about p {
            font-size: 1.1rem;
            color: var(--muted-fg);
            max-width: 650px;
            margin: 0 auto 2rem;
            line-height: 1.8;
        }
        .about-features {
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
        }
        .about-feature {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--muted-fg);
        }
        .about-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        /* CATEGORIES */
        .categories {
            padding: 4rem 1.5rem;
            text-align: center;
        }
        .categories h2 { font-size: 1.75rem; margin-bottom: 2rem; }
        .category-pills {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.75rem;
            max-width: 800px;
            margin: 0 auto;
        }
        .category-pill {
            padding: 0.6rem 1.25rem;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 2rem;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--muted-fg);
            cursor: pointer;
            transition: all 0.2s;
        }
        .category-pill:hover, .category-pill.active {
            border-color: var(--green);
            color: var(--green);
            background: rgba(44,87,69,0.05);
        }

        /* FILTER BAR */
        .filter-bar {
            background: var(--muted);
            padding: 1.25rem 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 2.5rem;
        }
        .filter-form {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-input {
            flex: 1;
            min-width: 200px;
            padding: 0.65rem 1rem;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            font-size: 0.95rem;
            font-family: inherit;
            background: var(--card);
            color: var(--fg);
        }
        .filter-input:focus {
            outline: none;
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(44,87,69,0.1);
        }
        .filter-select {
            padding: 0.65rem 1rem;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            font-size: 0.95rem;
            font-family: inherit;
            background: var(--card);
            color: var(--fg);
            cursor: pointer;
        }
        .filter-btn {
            padding: 0.65rem 1.5rem;
            background: var(--green);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }
        .filter-btn:hover { background: #234a3a; }
        .filter-clear {
            padding: 0.65rem 1rem;
            background: transparent;
            color: var(--muted-fg);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .filter-clear:hover { border-color: var(--green); color: var(--green); }

        /* RESULTS INFO */
        .results-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            color: var(--muted-fg);
            font-size: 0.95rem;
        }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 4rem 1rem;
        }
        .empty-state svg { width: 64px; height: 64px; color: rgba(44,87,69,0.2); margin: 0 auto 1rem; }
        .empty-state h3 { color: var(--muted-fg); margin-bottom: 0.5rem; }
        .empty-state p { color: var(--muted-fg); margin-bottom: 1.5rem; }

        /* PAGINATION */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 3rem;
        }
        .page-link {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            color: var(--muted-fg);
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .page-link:hover { border-color: var(--green); color: var(--green); }
        .page-link.active {
            background: var(--green);
            border-color: var(--green);
            color: white;
        }
        .page-link.disabled { opacity: 0.4; pointer-events: none; }

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
        @media (max-width: 640px) {
            .blog-grid { grid-template-columns: 1fr; }
            .filter-form { flex-direction: column; }
            .filter-input, .filter-select, .filter-btn, .filter-clear { width: 100%; }
            .nav-links { gap: 0.5rem; }
            .nav-link { display: none; }
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
                <a href="/admin/login.php" class="btn-outline">Sign In</a>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <section class="hero">
        <div class="hero-badge">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/></svg>
            Welcome to thoughtful reading
        </div>
        <h1>Stories worth<br>your time</h1>
        <p>A space for carefully crafted articles, ideas, and perspectives. No noise, no distractions — just meaningful content that inspires curiosity.</p>
        <div class="hero-actions">
            <a href="#posts" class="btn-primary">
                Start Reading
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </a>
            <a href="#about" class="btn-ghost">Learn more</a>
        </div>
    </section>

    <!-- Featured Posts -->
    <section id="posts" class="section">
        <div class="container">
            <div class="section-header">
                <div>
                    <h2>Latest Stories</h2>
                    <p style="color: var(--muted-fg); margin-top: 0.25rem;">Fresh perspectives and ideas</p>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET" class="filter-form">
                    <input type="text" name="search" class="filter-input" placeholder="Search stories..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="category" class="filter-select">
                        <option value="0">All Topics</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $categoryId == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="filter-btn">Filter</button>
                    <?php if ($search !== '' || $categoryId > 0): ?>
                        <a href="index.php" class="filter-clear">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (empty($posts)): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"/></svg>
                    <h3>No stories found</h3>
                    <p>Try adjusting your search or filter criteria.</p>
                    <a href="index.php" class="btn-primary" style="display: inline-flex;">View All Stories</a>
                </div>
            <?php else: ?>
                <div class="blog-grid">
                    <?php foreach ($posts as $post): ?>
                        <a href="post.php?id=<?php echo $post['id']; ?>" class="blog-card">
                            <div class="blog-card-img">
                                <?php if (!empty($post['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($post['image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="blog-card-placeholder">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"/></svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="blog-card-body">
                                <?php if (!empty($post['category_name'])): ?>
                                    <span class="blog-card-tag"><?php echo htmlspecialchars($post['category_name']); ?></span>
                                <?php endif; ?>
                                <h3 class="blog-card-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                                <p class="blog-card-excerpt"><?php echo htmlspecialchars(mb_strimwidth($post['content'], 0, 140, '...')); ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <a class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $categoryId; ?>">← Prev</a>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a class="page-link <?php echo $i === $page ? 'active' : ''; ?>" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $categoryId; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <a class="page-link <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $categoryId; ?>">Next →</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- About -->
    <section id="about" class="about">
        <div class="container">
            <h2>A quiet corner for curious minds</h2>
            <p>In a world of endless scrolling and clickbait, we believe in the power of thoughtful writing. Each piece here is crafted with care, designed to inform, inspire, and spark meaningful conversation.</p>
            <div class="about-features">
                <div class="about-feature">
                    <div class="about-dot" style="background: var(--green);"></div>
                    <span>Thoughtful content</span>
                </div>
                <div class="about-feature">
                    <div class="about-dot" style="background: var(--orange);"></div>
                    <span>No distractions</span>
                </div>
                <div class="about-feature">
                    <div class="about-dot" style="background: var(--olive);"></div>
                    <span>Clean design</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories -->
    <?php if (!empty($categories)): ?>
    <section class="categories">
        <div class="container">
            <h2>Explore Topics</h2>
            <div class="category-pills">
                <?php foreach ($categories as $cat): ?>
                    <a href="index.php?category=<?php echo $cat['id']; ?>" class="category-pill <?php echo $categoryId == $cat['id'] ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

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
