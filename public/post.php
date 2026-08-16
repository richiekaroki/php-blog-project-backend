<?php
// post.php - Display single blog post

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/inc/post-format.php';

use App\Database\Connection;
use App\Middleware\CSRF;
use App\Middleware\RateLimit;
use App\Middleware\SecurityHeaders;
use App\Models\Comment;

$pdo = Connection::getInstance();
SecurityHeaders::send();
CSRF::init();

// Get post ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch post with category (published only — drafts are admin work)
$stmt = $pdo->prepare("
    SELECT blogs.*, categories.name AS category_name 
    FROM blogs 
    JOIN categories ON blogs.category_id = categories.id 
    WHERE blogs.id = ? AND blogs.status = 'published'
");
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) {
    header("Location: index.php");
    exit;
}

// Handle a new comment submission (honeypot + CSRF + rate limited)
$commentFlash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_submit'])) {
    $honeypot = trim((string)($_POST['website'] ?? ''));
    if ($honeypot !== '' || !CSRF::verify()) {
        $commentFlash = 'invalid';
    } else {
        $rateLimit = new RateLimit($pdo);
        $ip = RateLimit::clientIp();
        if ($rateLimit->isBlocked('comment', $ip, 5, 900)) {
            $commentFlash = 'rate';
        } else {
            $rateLimit->hit('comment', $ip, 900);
            $newId = Comment::create(
                (int)$post['id'],
                trim((string)($_POST['name'] ?? '')),
                strtolower(trim((string)($_POST['email'] ?? ''))),
                trim((string)($_POST['comment'] ?? ''))
            );
            $commentFlash = $newId !== null ? 'pending' : 'invalid';
        }
    }
}

$approvedComments = Comment::approvedFor((int)$post['id']);

// Banner state from newsletter subscribe redirects
$newsFlash = isset($_GET['subscribed']) ? 'subscribed' : (isset($_GET['subscribe_error']) ? 'error' : null);

// Count this read (fire-and-forget; drafts are never reached here)
$pdo->prepare("UPDATE blogs SET views = views + 1 WHERE id = ?")->execute([$id]);

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

// Canonical URL + social/SEO meta for this post
$postUrl = (($_SERVER['HTTPS'] ?? 'off') !== 'off' ? 'https' : 'http') . '://'
    . ($_SERVER['HTTP_HOST'] ?? '') . '/post.php?id=' . $id;
$postDesc = trim(preg_replace('/\s+/', ' ', stripMarkdown($post['content'])));
$postDesc = mb_strimwidth($postDesc, 0, 155, '…');
$postDate = $post['created_at'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/svg+xml" href="/favicon-v2.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?> · WAM Blog</title>
    <meta name="description" content="<?php echo htmlspecialchars($postDesc, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($postUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="alternate" type="application/rss+xml" title="WAM Blog RSS" href="rss.php">
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="WAM Blog">
    <meta property="og:title" content="<?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($postDesc, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($postUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if (!empty($post['image'])): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($post['image'], ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php if ($postDate): ?>
    <meta property="article:published_time" content="<?php echo htmlspecialchars($postDate, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="assets/site.css">
    <script>
        (function() {
            if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>
</head>
<body>
    <!-- Reading progress -->
    <div class="progress-bar" aria-hidden="true"><span id="progress-bar"></span></div>

    <!-- Nav -->
    <nav class="nav">
        <div class="nav-inner">
            <a href="/" class="nav-brand">
                <img class="nav-logo" src="/favicon-v2.svg" alt="WAM" width="64" height="64">
                <span class="nav-title">WAM Blog</span>
            </a>
            <div class="nav-links">
                <a href="/" class="nav-link">Home</a>
                <button class="btn-ghost" onclick="toggleTheme()" title="Toggle theme" aria-label="Toggle theme" style="padding: 0.4rem 0.6rem;">&#9681;</button>
                <a href="/admin/login.php" class="btn-outline">Admin</a>
            </div>
        </div>
    </nav>

    <?php if ($newsFlash === 'subscribed'): ?>
        <div class="banner banner-success" style="max-width: 1100px; margin: 1.25rem auto 0;">Thanks — you are on the list. We will email you when a new story goes live.</div>
    <?php elseif ($newsFlash === 'error'): ?>
        <div class="banner banner-error" style="max-width: 1100px; margin: 1.25rem auto 0;">That email could not be subscribed. Please check the address and try again.</div>
    <?php endif; ?>

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
            <h1 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h1>
            <?php if (!empty($post['created_at'])): ?>
                <div class="post-meta">
                    <span><?php echo date('F j, Y', strtotime($post['created_at'])); ?></span>
                    <span class="dot"></span>
                    <span><?php echo max(1, ceil(str_word_count($post['content']) / 200)); ?> min read</span>
                    <span class="dot"></span>
                    <span><?php echo number_format((int)$post['views']); ?> reads</span>
                </div>
            <?php endif; ?>
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
                <?php echo renderPostContent($post['content']); ?>
            </div>

            <footer class="post-signoff">
                <span class="fleuron" aria-hidden="true"><span class="glyph">&#10086;</span></span>
                <p class="post-signoff-text">Written by hand for the readers of WAM Blog. If it moved you, pass it on.</p>
                <a href="index.php" class="btn-outline">Back to the journal</a>
            </footer>
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

            <!-- Newsletter -->
            <div class="sidebar-card">
                <h3>Never miss a story</h3>
                <p class="post-info-label" style="margin-bottom: 0.75rem;">Join the list — one gentle email when a new post goes live. Unsubscribe anytime.</p>
                <form method="POST" action="subscribe.php" class="newsletter-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="back" value="post.php?id=<?php echo $post['id']; ?>">
                    <input type="text" name="website" class="hp-field" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <input type="email" name="email" placeholder="you@example.com" required>
                    <button type="submit" class="btn-outline" style="width: 100%;">Subscribe</button>
                </form>
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
                                    <span class="placeholder-initial"><?php echo htmlspecialchars(mb_substr($related['title'], 0, 1)); ?></span>
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

    <!-- Comments -->
    <section class="comments-section">
        <?php if ($commentFlash === 'pending'): ?>
            <div class="banner banner-success">Thanks! Your comment has been received and will appear once it is approved.</div>
        <?php elseif ($commentFlash === 'invalid'): ?>
            <div class="banner banner-error">Your comment could not be saved. Please check your details and try again.</div>
        <?php elseif ($commentFlash === 'rate'): ?>
            <div class="banner banner-error">You have posted too many comments recently — please try again in a little while.</div>
        <?php endif; ?>

        <h2 class="comments-title">Comments</h2>
        <?php if (empty($approvedComments)): ?>
            <p class="comments-empty">No comments yet — start the conversation.</p>
        <?php else: ?>
            <?php foreach ($approvedComments as $c): ?>
                <div class="comment">
                    <div class="comment-head">
                        <span class="comment-author"><?php echo htmlspecialchars($c['author_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="comment-date"><?php echo date('M j, Y', strtotime($c['created_at'])); ?></span>
                    </div>
                    <div class="comment-body"><?php echo nl2br(htmlspecialchars($c['content'], ENT_QUOTES, 'UTF-8')); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($commentFlash !== 'pending'): ?>
            <form method="POST" action="post.php?id=<?php echo $post['id']; ?>" class="comment-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="text" name="website" class="hp-field" tabindex="-1" autocomplete="off" aria-hidden="true">
                <h3 style="font-family: 'Fraunces', Georgia, serif; font-size: 1.15rem; font-weight: 600; color: var(--olive); margin-bottom: 1rem;">Leave a comment</h3>
                <div class="comment-fields">
                    <input type="text" name="name" class="form-input" placeholder="Your name *" required maxlength="100">
                    <input type="email" name="email" class="form-input" placeholder="Email (optional, never shown)" maxlength="255">
                </div>
                <textarea name="comment" class="form-textarea" placeholder="Share a thought…" required></textarea>
                <button type="submit" name="comment_submit" value="1" class="btn-outline">Post comment</button>
            </form>
        <?php endif; ?>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-inner">
            <div class="footer-brand">
                <img class="nav-logo" style="width: 32px; height: 32px;" src="/favicon-v2.svg" alt="WAM" width="64" height="64">
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
    <script>
        (function() {
            const bar = document.getElementById('progress-bar');
            if (!bar) return;
            function update() {
                const doc = document.documentElement;
                const max = doc.scrollHeight - doc.clientHeight;
                bar.style.width = (max > 0 ? (doc.scrollTop / max) * 100 : 0) + '%';
            }
            window.addEventListener('scroll', update, { passive: true });
            window.addEventListener('resize', update);
            update();
        })();
    </script>
</body>
</html>