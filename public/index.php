<?php
// index.php - Blog homepage with pagination, search, category filtering

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/inc/post-format.php';

use App\Database\Connection;
use App\Middleware\SecurityHeaders;

$pdo = Connection::getInstance();
SecurityHeaders::send();

// Canonical URL so search engines consolidate /index.php with the root (/)
$canonical = (($_SERVER['HTTPS'] ?? 'off') !== 'off' ? 'https' : 'http') . '://'
    . ($_SERVER['HTTP_HOST'] ?? '')
    . ($_SERVER['REQUEST_URI'] ?? '/');

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

// Only published posts appear on the public site (drafts are write-ahead work).
$where[] = "blogs.status = 'published'";

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total posts
$countSql = "SELECT COUNT(*) FROM blogs $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalPosts = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalPosts / $perPage));

// Fetch posts
$sql = "SELECT blogs.id, blogs.title, blogs.content, blogs.image, blogs.created_at, blogs.views, categories.name AS category_name
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
    <title>WAM Blog · Stories Worth Your Time</title>
    <meta name="description" content="A quiet journal of the craft — essays and stories on writing, building, and the ideas we keep coming back to. No noise, no clickbait, just reading worth slowing down for.">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="alternate" type="application/rss+xml" title="WAM Blog RSS" href="rss.php">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="WAM Blog">
    <meta property="og:title" content="WAM Blog — Stories Worth Your Time">
    <meta property="og:description" content="A quiet journal of the craft — essays on writing, building, and the ideas we keep coming back to.">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="assets/site.css">
    <script>
        (function() {
            if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>
</head>
<body>
    <!-- Nav -->
    <nav class="nav">
        <div class="nav-inner">
            <a href="/" class="nav-brand">
                <img class="nav-logo" src="/favicon.svg" alt="WAM" width="64" height="64">
                <span class="nav-title">WAM Blog</span>
            </a>
            <div class="nav-links">
                <a href="/" class="nav-link">Home</a>
                <button class="btn-ghost" onclick="toggleTheme()" title="Toggle theme" aria-label="Toggle theme" style="padding: 0.4rem 0.6rem;">&#9681;</button>
                <a href="/admin/login.php" class="btn-outline">Sign in</a>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <section class="hero">
        <div class="hero-inner">
            <span class="eyebrow">A quiet journal &middot; WAM Blog</span>
            <h1>A quiet place for <em>stories<svg viewBox="0 0 200 9" fill="none" preserveAspectRatio="none" aria-hidden="true"><path d="M3 6.5C40 2 160 2 197 6.5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg></em> worth your time</h1>
            <p>Carefully written stories on the craft of writing, building, and thinking. No noise, no distractions — just good writing that earns your attention.</p>
            <div class="hero-actions">
                <a href="#posts" class="btn-primary">
                    Start reading
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
                <a href="#about" class="btn-ghost">Learn more</a>
            </div>
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
                    <?php if ($search !== '' || $categoryId > 0): ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"/></svg>
                        <h3>Nothing matched your search</h3>
                        <p>No stories match your query. Try another phrase, or wander back to the full journal.</p>
                        <a href="index.php" class="btn-primary" style="display: inline-flex;">Browse all stories</a>
                    <?php else: ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"/></svg>
                        <h3>The journal is still being written</h3>
                        <p>Stories are taking shape behind the scenes. Come back soon — or sign in and start writing your own.</p>
                        <a href="signup.php" class="btn-primary" style="display: inline-flex;">Join the journal</a>
                    <?php endif; ?>
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
                                        <span class="placeholder-initial"><?php echo htmlspecialchars(mb_substr($post['title'], 0, 1)); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="blog-card-body">
                                <?php if (!empty($post['category_name'])): ?>
                                    <span class="blog-card-tag"><?php echo htmlspecialchars($post['category_name']); ?></span>
                                <?php endif; ?>
                                <h3 class="blog-card-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                                <p class="blog-card-excerpt"><?php echo htmlspecialchars(mb_strimwidth(stripMarkdown($post['content']), 0, 140, '...')); ?></p>
                                <div class="blog-card-meta">
                                    <?php if (!empty($post['created_at'])): ?>
                                        <span><?php echo date('M j, Y', strtotime($post['created_at'])); ?></span>
                                    <?php endif; ?>
                                    <span><?php echo max(1, ceil(str_word_count($post['content']) / 200)); ?> min read</span>
                                    <?php if ((int)$post['views'] > 0): ?>
                                        <span class="dot"></span>
                                        <span><?php echo number_format((int)$post['views']); ?> reads</span>
                                    <?php endif; ?>
                                </div>
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
        <div class="about-inner">
            <span class="eyebrow" style="display: inline-block; margin-bottom: 1.5rem;">Why WAM</span>
            <blockquote>In a world of endless scrolling and clickbait, we believe in the power of thoughtful writing. Every piece here is crafted with care — written and built to inform, inspire, and linger a little longer than a feed post.</blockquote>
            <p class="attribution">— The WAM editors</p>
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
                <img class="nav-logo" style="width: 32px; height: 32px;" src="/favicon.svg" alt="WAM" width="64" height="64">
                <span class="nav-title" style="font-size: 1rem;">WAM Blog</span>
            </div>
            <p class="footer-text">Crafted with care for readers who appreciate quality content.</p>
            <div class="footer-links">
                <a href="https://github.com/richiekaroki/php-blog-project-backend">GitHub</a>
                <a href="/admin/login.php">Admin</a>
            </div>
        </div>
    </footer>
    <script>
        function toggleTheme() {
            const root = document.documentElement;
            root.classList.toggle('dark');
            localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light');
        }
    </script>
</body>
</html>
