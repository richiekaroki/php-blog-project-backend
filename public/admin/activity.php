<?php
// admin/activity.php - Activity feed: sign-ins, 2FA changes, session & content events

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Database\Connection;
use App\Middleware\Auth;
use App\Middleware\CSRF;
use App\Models\ActivityLog;

Auth::check();
$pdo = Connection::getInstance();
CSRF::init();

$limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 50;
$activities = ActivityLog::getRecent($limit);

// Human-readable labels so security events (esp. 2FA) are obvious at a glance.
$eventLabels = [
    'magic_link_used'      => ['Signed in via magic link', 'auth'],
    'magic_link_sent'      => ['Magic sign-in link sent', 'auth'],
    'signup_auto'          => ['New account created', 'auth'],
    'role_changed'         => ['Role changed', 'admin'],
    'user_deleted'         => ['User deleted', 'admin'],
    '2fa_secret_generated' => ['2FA secret generated', '2fa'],
    '2fa_enabled'          => ['Two-factor authentication enabled', '2fa'],
    '2fa_disabled'         => ['Two-factor authentication disabled', '2fa'],
    'sessions_revoked'     => ['Signed out other devices', 'session'],
    'created'              => ['Created', 'content'],
    'updated'              => ['Updated', 'content'],
    'deleted'              => ['Deleted', 'content'],
    'profile_updated'      => ['Profile updated', 'admin'],
];

$typeColors = [
    '2fa'        => 'rgba(44,87,69,0.12)',
    'auth'       => 'rgba(44,87,69,0.12)',
    'session'    => 'rgba(235,125,0,0.14)',
    'content'    => 'rgba(44,87,69,0.10)',
    'invitation' => 'rgba(197,48,48,0.1)',
    'admin'      => 'rgba(44,87,69,0.12)',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/svg+xml" href="/favicon-v2.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity · WAM Blog</title>
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
                <a href="profile.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Account
                </a>
                <?php if (Auth::getRole() === 'admin'): ?>
                <a href="users.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Users
                </a>
                <?php endif; ?>
                <a href="activity.php" class="nav-item active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                    Activity
</a>
                <a href="comments.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Comments
                    <?php if (\App\Models\Comment::countPending() > 0): ?><span class="nav-badge"><?php echo \App\Models\Comment::countPending(); ?></span><?php endif; ?>
                </a>
                <a href="subscribers.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                    Subscribers
                </a>
                <a href="analytics.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M7 13v3"/><path d="M11 9v7"/><path d="M15 5v11"/><path d="M19 9v7"/></svg>
                    Analytics
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
                <h1 class="header-title">Activity</h1>
                <div class="header-actions">
                    <a href="activity.php?limit=100" class="btn btn-outline btn-sm">Show 100</a>
                    <button class="theme-toggle" onclick="toggleTheme()" title="Toggle theme" aria-label="Toggle theme">&#9681;</button>
                </div>
            </header>

            <div class="content">
                <div class="card">
                    <div class="card-header">
                        <h2>Recent Activity</h2>
                    </div>
                    <?php if (empty($activities)): ?>
<div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <p>No activity yet. Sign-ins, 2FA changes, and content edits will appear here.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($activities as $a): ?>
                            <?php
                                $action = $a['action'] ?? 'unknown';
                                $label = $eventLabels[$action] ?? [$action, 'admin'];
                                $labelText = $label[0];
                                $type = $label[1];
                                $color = $typeColors[$type] ?? 'rgba(44,87,69,0.1)';
                                $details = $a['details'] ?? null;
                                $detailsText = is_string($details) ? $details : (is_array($details) ? json_encode($details) : '');
                                $ip = $a['user_ip'] ?? null;
                                $entity = $a['entity_type'] ?? '';
                                // A 2FA-enabled magic-link sign-in carries details {"2fa": true}.
                                $via2fa = $action === 'magic_link_used' && str_contains($detailsText, '"2fa":true');
                            ?>
                            <div class="event-row">
                                <div class="event-dot" style="background: <?php echo $color; ?>;"></div>
                                <div class="event-main">
                                    <div class="event-title">
                                        <?php echo htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8'); ?>
                                        <span class="tag" style="background: <?php echo $color; ?>; color: var(--olive);"><?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if ($via2fa): ?>
                                            <span class="tag" style="background: rgba(44,87,69,0.12); color: var(--green);">2FA</span>
                                        <?php endif; ?>
                                        <?php if ($action === 'sessions_revoked' && $detailsText !== ''): ?>
                                            <span class="tag" style="background: rgba(235,125,0,0.14); color: var(--olive);"><?php echo (int)($a['details']['count'] ?? 0); ?> sessions</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="event-meta">
                                        <?php echo htmlspecialchars((string)($a['entity_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if (!empty($a['entity_id'])): ?>#<?php echo (int)$a['entity_id']; ?><?php endif; ?>
                                        <?php if ($ip): ?> &nbsp;·&nbsp; <?php echo htmlspecialchars($ip, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                                        &nbsp;·&nbsp;
                                        <?php
                                            $ts = new DateTime($a['created_at']);
                                            echo $ts->format('M j, Y g:i A');
                                        ?>
                                    </div>
                                    <?php if ($detailsText !== ''): ?>
                                        <div class="event-details"><?php echo htmlspecialchars($detailsText, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
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