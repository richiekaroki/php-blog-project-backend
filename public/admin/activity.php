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
    'signup_requested'     => ['Sign-up requested', 'invitation'],
    'signup_approved'      => ['Access approved', 'invitation'],
    'signup_rejected'      => ['Access rejected', 'invitation'],
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity - WAM Blog</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Source+Sans+3:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #FBF9F1; --fg: #2E2910; --card: #FFFFFF; --primary: #2C5745;
            --primary-fg: #FFFFFF; --muted: #F5F0DC; --muted-fg: #5C5340;
            --border: #D4C9A8; --cream: #EBE3A7; --orange: #EB7D00; --green: #2C5745;
            --olive: #2E2910; --destructive: #C53030;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Source Sans 3', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--bg); color: var(--fg); line-height: 1.6; }
        h1, h2, h3 { font-family: 'Lora', Georgia, serif; color: var(--olive); }
        a { color: var(--green); text-decoration: none; }
        a:hover { color: var(--orange); }

        .layout { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: var(--card); border-right: 1px solid var(--border); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; bottom: 0; }
        .sidebar-header { padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 0.75rem; }
        .sidebar-logo { width: 36px; height: 36px; background: var(--green); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-family: 'Lora', serif; font-weight: 700; font-size: 1.1rem; }
        .sidebar-brand { font-family: 'Lora', serif; font-weight: 600; font-size: 1.1rem; color: var(--olive); }
        .sidebar-sub { font-size: 0.75rem; color: var(--muted-fg); margin-top: 0.15rem; }
        .sidebar-nav { flex: 1; padding: 1rem; }
        .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.9rem; font-weight: 500; color: var(--muted-fg); transition: all 0.2s; margin-bottom: 0.25rem; }
        .nav-item:hover { background: var(--muted); color: var(--fg); }
        .nav-item.active { background: rgba(44,87,69,0.1); color: var(--green); }
        .nav-item svg { width: 20px; height: 20px; }

        .main { flex: 1; margin-left: 260px; }
        .header { height: 64px; background: var(--card); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 1.5rem; position: sticky; top: 0; z-index: 40; }
        .header-title { font-family: 'Lora', serif; font-size: 1.1rem; font-weight: 600; }
        .content { padding: 2rem; max-width: 860px; }

        .card { background: var(--card); border-radius: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06); margin-bottom: 1.5rem; }
        .card-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .card-header h2 { font-size: 1.1rem; font-weight: 600; }

        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.25rem; border-radius: 0.5rem; font-size: 0.9rem; font-weight: 500; font-family: inherit; cursor: pointer; transition: all 0.2s; border: none; }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--muted-fg); }
        .btn-outline:hover { border-color: var(--green); color: var(--green); }
        .btn-sm { padding: 0.4rem 0.75rem; font-size: 0.8rem; }

        .event-row { display: flex; align-items: flex-start; gap: 1rem; padding: 0.9rem 1.5rem; border-bottom: 1px solid var(--border); }
        .event-row:last-child { border-bottom: none; }
        .event-dot { flex-shrink: 0; width: 10px; height: 10px; border-radius: 50%; margin-top: 0.4rem; }
        .event-main { flex: 1; min-width: 0; }
        .event-title { font-weight: 600; font-size: 0.95rem; color: var(--olive); display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
        .event-meta { font-size: 0.85rem; color: var(--muted-fg); margin-top: 0.15rem; }
        .event-details { font-size: 0.8rem; color: var(--muted-fg); margin-top: 0.25rem; font-family: 'Courier New', monospace; word-break: break-all; }

        .tag { display: inline-block; padding: 0.15rem 0.6rem; border-radius: 1rem; font-size: 0.7rem; font-weight: 600; text-transform: capitalize; }
        .empty { padding: 3rem 1.5rem; text-align: center; color: var(--muted-fg); }

        @media (max-width: 900px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">W</div>
                <div>
                    <div class="sidebar-brand">WAM Blog</div>
                    <div class="sidebar-sub">Content Studio</div>
                </div>
            </div>
            <nav class="sidebar-nav">
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
                <a href="/" class="nav-item" target="_blank">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                    View Site
                </a>
            </nav>
            <div style="padding: 1rem; border-top: 1px solid var(--border);">
                <a href="login.php?action=logout" class="nav-item" style="color: var(--destructive);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                    Logout
                </a>
            </div>
        </aside>

        <!-- Main -->
        <div class="main">
            <header class="header">
                <h1 class="header-title">Activity</h1>
                <a href="activity.php?limit=100" class="btn btn-outline btn-sm">Show 100</a>
            </header>

            <div class="content">
                <div class="card">
                    <div class="card-header">
                        <h2>Recent Activity</h2>
                    </div>
                    <?php if (empty($activities)): ?>
                        <div class="empty">
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
</body>
</html>