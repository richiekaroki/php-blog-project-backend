<?php
// admin/comments.php - Comment moderation

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Database\Connection;
use App\Middleware\Auth;
use App\Middleware\CSRF;
use App\Models\Comment;

Auth::check();
$pdo = Connection::getInstance();
CSRF::init();

$currentRole = Auth::getRole() ?? 'viewer';
$canModerate = in_array($currentRole, ['admin', 'editor'], true);

// Approve / delete — POST + CSRF only
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canModerate) {
        http_response_code(403);
        die('Access denied: your role does not permit moderating comments.');
    }
    if (!isset($_POST['csrf_token']) ||
        $_POST['csrf_token'] !== $_SESSION['csrf_token'] ||
        hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']) === false) {
        die('Invalid CSRF token');
    }

    if (isset($_POST['approve'])) {
        Comment::approve((int)$_POST['approve']);
    } elseif (isset($_POST['delete'])) {
        Comment::delete((int)$_POST['delete']);
    }
    header("Location: comments.php" . (isset($_GET['status']) && in_array($_GET['status'], ['pending', 'approved'], true) ? '?status=' . $_GET['status'] : ''));
    exit;
}

$status = (isset($_GET['status']) && in_array($_GET['status'], ['pending', 'approved'], true)) ? $_GET['status'] : null;
$comments = Comment::list($status);
$pendingCount = Comment::countPending();

$statApproved = (int)$pdo->query("SELECT COUNT(*) FROM comments WHERE status = 'approved'")->fetchColumn();
$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$greetingName = Auth::currentUsername() ?? 'author';
$roleLabel = ucfirst($currentRole);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/svg+xml" href="/favicon-v2.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comment Moderation · WAM Blog</title>
    <link rel="stylesheet" href="../assets/admin.css">
    <script>
        (function() {
            if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>
</head>
<body>
    <div class="layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img class="sidebar-logo" src="/favicon-v2.svg" alt="WAM" width="64" height="64">
                <div>
                    <div class="sidebar-brand">WAM Blog</div>
                    <div class="sidebar-sub">Content Studio</div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <p class="nav-section-label">Manage</p>
                <a href="blogs.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
                    Blogs
                </a>
                <a href="categories.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg>
                    Categories
                </a>
                <?php if (Auth::getRole() === 'admin'): ?>
                <a href="users.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Users
                </a>
                <?php endif; ?>
                <a href="comments.php" class="nav-item active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Comments
                    <?php if ($pendingCount > 0): ?><span class="nav-badge"><?php echo $pendingCount; ?></span><?php endif; ?>
                </a>
                <a href="subscribers.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                    Subscribers
                </a>
                <a href="analytics.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M7 13v3"/><path d="M11 9v7"/><path d="M15 5v11"/><path d="M19 9v7"/></svg>
                    Analytics
                </a>
                <a href="activity.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                    Activity
                </a>
                <p class="nav-section-label" style="margin-top: 1rem;">Site</p>
                <a href="/" class="nav-item" target="_blank">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                    View Site
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="login.php?action=logout" class="nav-item danger">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                    Logout
                </a>
            </div>
        </aside>
        <div class="sidebar-backdrop" onclick="closeSidebar()"></div>

        <!-- Main -->
        <div class="main">
            <header class="header">
                <button class="menu-btn" onclick="toggleSidebar()" aria-label="Open navigation menu" aria-expanded="false" id="menuBtn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
                <h1 class="header-title">Comment Moderation</h1>
                <div class="header-actions">
                    <span class="header-meta"><?php echo count($comments); ?> shown</span>
                    <button class="theme-toggle" onclick="toggleTheme()" title="Toggle theme" aria-label="Toggle theme">&#9681;</button>
                </div>
            </header>

            <div class="content">
                <!-- Welcome band -->
                <div class="welcome-band">
                    <div>
                        <h2 class="welcome-title"><?php echo htmlspecialchars($greeting); ?>, <?php echo htmlspecialchars($greetingName); ?></h2>
                        <p class="welcome-sub">Reader voices land here. Approve the ones worth keeping, sweep away the noise.</p>
                    </div>
                    <span class="badge <?php echo htmlspecialchars($currentRole); ?>"><?php echo htmlspecialchars($roleLabel); ?></span>
                </div>

                <div class="stat-row">
                    <div class="stat-card">
                        <span class="stat-value"><?php echo $pendingCount; ?></span>
                        <span class="stat-label">Awaiting review</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-value"><?php echo $statApproved; ?></span>
                        <span class="stat-label">Approved</span>
                    </div>
                </div>

                <!-- Filter tabs -->
                <div class="card">
                    <div class="card-header">
                        <h2>Comments</h2>
                        <div class="filter-tabs">
                            <a href="comments.php" class="filter-tab <?php echo $status === null ? 'active' : ''; ?>">All</a>
                            <a href="comments.php?status=pending" class="filter-tab <?php echo $status === 'pending' ? 'active' : ''; ?>">Pending</a>
                            <a href="comments.php?status=approved" class="filter-tab <?php echo $status === 'approved' ? 'active' : ''; ?>">Approved</a>
                        </div>
                    </div>
                    <?php if (empty($comments)): ?>
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            <p><?php echo $status === 'pending' ? 'Nothing waiting for review — a clean slate.' : 'No comments to show yet.'; ?></p>
                        </div>
                    <?php else: ?>
                        <div class="comment-list">
                            <?php foreach ($comments as $c): ?>
                                <div class="comment-card <?php echo $c['status'] === 'pending' ? 'comment-pending' : ''; ?>">
                                    <div class="comment-card-head">
                                        <span class="blog-title"><?php echo htmlspecialchars($c['author_name'] ?? 'Anonymous', ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="badge <?php echo $c['status'] === 'pending' ? 'draft' : 'admin'; ?>"><?php echo htmlspecialchars($c['status']); ?></span>
                                    </div>
                                    <p class="blog-excerpt">
                                        On <strong><?php echo htmlspecialchars($c['blog_title'] ?? '(deleted post)', ENT_QUOTES, 'UTF-8'); ?></strong>
                                        · <?php echo date('M j, Y · g:i a', strtotime($c['created_at'])); ?>
                                        <?php if (!empty($c['author_email'])): ?>· <a href="mailto:<?php echo htmlspecialchars($c['author_email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($c['author_email'], ENT_QUOTES, 'UTF-8'); ?></a><?php endif; ?>
                                    </p>
                                    <div class="comment-card-body"><?php echo nl2br(htmlspecialchars($c['content'], ENT_QUOTES, 'UTF-8')); ?></div>
                                    <div class="comment-card-actions">
                                        <?php if ($canModerate): ?>
                                            <?php if ($c['status'] === 'pending'): ?>
                                                <form method="POST" action="comments.php<?php echo $status ? '?status=' . urlencode($status) : ''; ?>" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="approve" value="<?php echo $c['id']; ?>">
                                                    <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" action="comments.php<?php echo $status ? '?status=' . urlencode($status) : ''; ?>" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="delete" value="<?php echo $c['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this comment permanently?')">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleTheme() {
            const root = document.documentElement;
            root.classList.toggle('dark');
            localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light');
        }
    </script>
<script>
        function toggleSidebar() {
            var layout = document.querySelector('.layout');
            var btn = document.getElementById('menuBtn');
            layout.classList.toggle('sidebar-open');
            if (btn) btn.setAttribute('aria-expanded', layout.classList.contains('sidebar-open'));
        }
        function closeSidebar() {
            var layout = document.querySelector('.layout');
            layout.classList.remove('sidebar-open');
            var btn = document.getElementById('menuBtn');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeSidebar();
        });
    </script>
</body>
</html>