<?php
// admin/users.php - User management (admin only)
// Admins change user roles and delete users. Accounts are self-served at
// /signup.php (any email becomes an editor), so no approval is needed here.

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Database\Connection;
use App\Middleware\Auth;
use App\Middleware\CSRF;
use App\Models\ActivityLog;

Auth::requireRole('admin');
$pdo = Connection::getInstance();
CSRF::init();

// Current admin id for protecting the last remaining admin.
$me = $pdo->prepare("SELECT id, role FROM admins WHERE username = ?");
$me->execute([$_SESSION['admin']]);
$currentAdmin = $me->fetch();

$error = '';
$success = '';

// --- Handle POST actions (all admin-only, all POST + CSRF) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token. Please reload and try again.';
    } elseif (isset($_POST['change_role'])) {
        $userId = (int)$_POST['user_id'];
        $newRole = $_POST['role'] ?? '';
        if (!in_array($newRole, ['admin', 'editor', 'viewer'], true)) {
            $error = 'Invalid role.';
        } else {
            $stmt = $pdo->prepare("SELECT id, username, role FROM admins WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $target = $stmt->fetch();
            if (!$target) {
                $error = 'User not found.';
            } elseif ((int)$target['id'] === (int)($currentAdmin['id'] ?? 0)) {
                $error = 'Cannot change your own role here.';
            } else {
                // Prevent demoting the last admin.
                if ($target['role'] === 'admin' && $newRole !== 'admin') {
                    $admins = $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'admin'")->fetchColumn();
                    if ((int)$admins <= 1) {
                        $error = 'Cannot demote the last admin.';
                    }
                }
                if (!$error) {
                    $upd = $pdo->prepare("UPDATE admins SET role = ? WHERE id = ?");
                    $upd->execute([$newRole, $userId]);
                    ActivityLog::log('role_changed', 'admin', $userId, ['user' => $target['username'], 'from' => $target['role'], 'to' => $newRole, 'by' => $_SESSION['admin']]);
                    $success = 'Updated role for ' . $target['username'] . ' to ' . $newRole . '.';
                }
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        $userId = (int)$_POST['user_id'];
        $stmt = $pdo->prepare("SELECT id, username, role FROM admins WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $target = $stmt->fetch();
        if (!$target) {
            $error = 'User not found.';
        } elseif ((int)$target['id'] === (int)($currentAdmin['id'] ?? 0)) {
            $error = 'You cannot delete your own account.';
        } elseif ($target['role'] === 'admin') {
            $admins = $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'admin'")->fetchColumn();
            if ((int)$admins <= 1) {
                $error = 'Cannot delete the last admin.';
            }
        }
        if (!$error) {
            try {
                $pdo->prepare("DELETE FROM auth_sessions WHERE admin_id = ?")->execute([$userId]);
                $pdo->prepare("DELETE FROM admins WHERE id = ?")->execute([$userId]);
                ActivityLog::log('user_deleted', 'admin', $userId, ['user' => $target['username'], 'by' => $_SESSION['admin']]);
                $success = 'Deleted user ' . $target['username'] . '.';
            } catch (\Throwable $e) {
                error_log('Delete user failed: ' . $e->getMessage());
                $error = 'Could not delete this user. They may own content that references them.';
            }
        }
    }
}

// All users
$users = $pdo->query("SELECT id, username, email, role, totp_secret FROM admins ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - WAM Blog</title>
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
        .sidebar-footer { padding: 1rem; border-top: 1px solid var(--border); }

        .main { flex: 1; margin-left: 260px; }
        .header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--border); background: var(--card); display: flex; align-items: center; justify-content: space-between; }
        .header-title { font-size: 1.4rem; }
        .header-actions { display: flex; gap: 1rem; align-items: center; }
        .content { padding: 2rem; max-width: 1000px; }

        .card { background: var(--card); border-radius: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 2rem; overflow: hidden; }
        .card-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .card-header h2 { font-size: 1.1rem; }
        .card-body { padding: 1.5rem; }

        .alert { padding: 1rem; border-radius: 0.5rem; font-size: 0.875rem; margin-bottom: 1.5rem; }
        .alert.success { background: rgba(44,87,69,0.1); border: 1px solid rgba(44,87,69,0.3); color: var(--green); }
        .alert.error { background: rgba(197,48,48,0.1); border: 1px solid rgba(197,48,48,0.2); color: var(--destructive); }

        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 0.9rem 1.25rem; border-bottom: 1px solid var(--border); font-size: 0.9rem; vertical-align: middle; }
        th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted-fg); background: var(--muted); }
        tr:last-child td { border-bottom: none; }

        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 2rem; font-size: 0.75rem; font-weight: 600; }
        .badge.admin { background: rgba(44,87,69,0.15); color: var(--green); }
        .badge.editor { background: rgba(235,125,0,0.15); color: var(--orange); }
        .badge.viewer { background: rgba(92,83,64,0.12); color: var(--muted-fg); }

        .btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.85rem; font-weight: 500; cursor: pointer; border: 1px solid var(--border); background: var(--card); color: var(--fg); transition: all 0.2s; font-family: inherit; }
        .btn:hover { background: var(--muted); }
        .btn-primary { background: var(--green); border-color: var(--green); color: white; }
        .btn-primary:hover { background: #234a3a; }
        .btn-danger { background: transparent; border-color: rgba(197,48,48,0.4); color: var(--destructive); }
        .btn-danger:hover { background: rgba(197,48,48,0.1); }
        .btn-sm { padding: 0.35rem 0.7rem; font-size: 0.8rem; }
        select { padding: 0.45rem 0.6rem; border: 1px solid var(--border); border-radius: 0.5rem; font-family: inherit; font-size: 0.85rem; background: var(--card); color: var(--fg); }

        .empty-state { text-align: center; padding: 2.5rem 1rem; color: var(--muted-fg); }
        .empty-state svg { width: 44px; height: 44px; color: rgba(44,87,69,0.2); margin: 0 auto 0.75rem; }
        .role-form { display: flex; gap: 0.5rem; align-items: center; }

        @media (max-width: 900px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .content { padding: 1rem; }
        }
    </style>
</head>
<body>
    <div class="layout">
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
                <a href="users.php" class="nav-item active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Users
                </a>
                <a href="activity.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                    Activity
                </a>
                <a href="/" class="nav-item" target="_blank">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                    View Site
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="login.php?action=logout" class="nav-item" style="color: var(--destructive);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                    Logout
                </a>
            </div>
        </aside>

        <div class="main">
            <header class="header">
                <h1 class="header-title">Users &amp; Access</h1>
                <div class="header-actions">
                    <span style="font-size: 0.85rem; color: var(--muted-fg);"><?php echo count($users); ?> users</span>
                </div>
            </header>

            <div class="content">
                <?php if ($success): ?>
                    <div class="alert success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <!-- All users -->
                <div class="card">
                    <div class="card-header">
                        <h2>Team members</h2>
                        <span style="font-size: 0.8rem; color: var(--muted-fg);"><?php echo count($users); ?> total</span>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>2FA</th>
                                    <th style="text-align: right;">Manage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8'); ?></strong><?php if ($u['username'] === $_SESSION['admin']): ?> <span style="color: var(--muted-fg); font-size: 0.75rem;">(you)</span><?php endif; ?></td>
                                        <td><?php echo htmlspecialchars($u['email'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><span class="badge <?php echo htmlspecialchars($u['role'] ?? 'viewer', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($u['role'] ?? 'viewer', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td><?php echo !empty($u['totp_secret']) ? '<span style="color: var(--green); font-size: 0.85rem;">Enabled</span>' : '<span style="color: var(--muted-fg); font-size: 0.85rem;">Off</span>'; ?></td>
                                        <td style="text-align: right;">
                                            <div class="role-form" style="justify-content: flex-end;">
                                                <form method="POST" action="users.php" style="display: flex; gap: 0.5rem; align-items: center;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <select name="role">
                                                        <?php foreach (['admin', 'editor', 'viewer'] as $r): ?>
                                                            <option value="<?php echo $r; ?>" <?php echo ($u['role'] ?? 'viewer') === $r ? 'selected' : ''; ?>><?php echo ucfirst($r); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" name="change_role" class="btn btn-sm">Save</button>
                                                </form>
                                                <?php if ($u['username'] !== $_SESSION['admin']): ?>
                                                <form method="POST" action="users.php" style="display: inline;" onsubmit="return confirm('Delete this user? This permanently removes their account.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <button type="submit" name="delete_user" class="btn btn-danger btn-sm">Delete</button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <p style="font-size: 0.85rem; color: var(--muted-fg);">
                            <strong>Roles:</strong> <strong>admin</strong> — full access (manage users and roles, delete anything) &middot; <strong>editor</strong> — create and edit posts &amp; categories, no deleting &middot; <strong>viewer</strong> — read-only dashboard access. Anyone can sign up with their email and starts as an editor.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>