<?php
// admin/profile.php - Account settings: profile info, TOTP 2FA, active sessions

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Database\Connection;
use App\Middleware\Auth;
use App\Middleware\CSRF;
use App\Auth\Totp;
use App\Models\ActivityLog;

Auth::check();
$pdo = Connection::getInstance();
CSRF::init();

$username = $_SESSION['admin'];

$stmt = $pdo->prepare("SELECT id, username, email, role, totp_secret FROM admins WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    Auth::logout();
    header("Location: login.php");
    exit;
}

$message = null;
$error = null;
$pendingSecret = $_SESSION['pending_totp_secret'] ?? null;

// --- Actions ---

// Enable 2FA: generate a new secret and hold it until confirmed with a valid code.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_2fa') {
    if (!CSRF::verify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token. Please reload and try again.';
    } else {
        $pendingSecret = Totp::generateSecret();
        $_SESSION['pending_totp_secret'] = $pendingSecret;
        ActivityLog::log('2fa_secret_generated', 'admin', (int)$user['id'], ['email' => $user['email']]);
    }
}

// Confirm 2FA: verify the code against the pending secret, then persist it.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_2fa') {
    if (!CSRF::verify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token. Please reload and try again.';
    } elseif (empty($pendingSecret)) {
        $error = 'No pending secret. Start the enrollment again.';
    } elseif (!Totp::verify($pendingSecret, $_POST['code'] ?? '')) {
        $error = 'Invalid verification code. Please try again.';
    } else {
        $stmt = $pdo->prepare("UPDATE admins SET totp_secret = ? WHERE id = ?");
        $stmt->execute([$pendingSecret, $user['id']]);
        unset($_SESSION['pending_totp_secret']);
        $pendingSecret = null;
        $message = 'Two-factor authentication is now enabled.';
        ActivityLog::log('2fa_enabled', 'admin', (int)$user['id'], ['email' => $user['email']]);
    }
}

// Disable 2FA: require the current code to prevent lockout by an attacker.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'disable_2fa') {
    if (!CSRF::verify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token. Please reload and try again.';
    } elseif (empty($user['totp_secret'])) {
        $error = 'Two-factor authentication is not enabled.';
    } elseif (!Totp::verify($user['totp_secret'], $_POST['code'] ?? '')) {
        $error = 'Invalid verification code. Your 2FA was not disabled.';
    } else {
        $stmt = $pdo->prepare("UPDATE admins SET totp_secret = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);
        $user['totp_secret'] = null;
        $message = 'Two-factor authentication has been disabled.';
        ActivityLog::log('2fa_disabled', 'admin', (int)$user['id'], ['email' => $user['email']]);
    }
}

// Revoke all sessions except the current one.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'revoke_sessions') {
    if (!CSRF::verify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token. Please reload and try again.';
    } else {
        $revoked = Auth::revokeOtherSessions();
        $message = "Signed out $revoked other session" . ($revoked === 1 ? '' : 's') . '.';
        ActivityLog::log('sessions_revoked', 'admin', (int)$user['id'], ['count' => $revoked]);
    }
}

// Refresh 2FA state after a possible update.
if (empty($user['totp_secret'])) {
    $user['totp_secret'] = null;
}

// Fetch active sessions for this admin.
$sessions = [];
if (!empty($user['id'])) {
    $stmt = $pdo->prepare(
        "SELECT id, session_token_hash, ip, user_agent, created_at, expires_at, revoked_at
         FROM auth_sessions WHERE admin_id = ? ORDER BY created_at DESC"
    );
    $stmt->execute([$user['id']]);
    $sessions = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/svg+xml" href="/favicon-v2.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings · WAM Blog</title>
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
                <a href="profile.php" class="nav-item active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Account
                </a>
                <?php if (Auth::getRole() === 'admin'): ?>
                <a href="users.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Users
                </a>
                <?php endif; ?>
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
                <h1 class="header-title">Account Settings</h1>
                <div class="header-actions">
                    <button class="theme-toggle" onclick="toggleTheme()" title="Toggle theme" aria-label="Toggle theme">&#9681;</button>
                </div>
            </header>

            <div class="content">
                <?php if ($message): ?>
                    <div class="flash flash-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="flash flash-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <!-- Profile Info -->
                <div class="card">
                    <div class="card-header"><h2>Profile</h2></div>
                    <div class="card-body">
                        <div class="info-grid">
                            <span class="info-label">Username</span>
                            <span class="info-value"><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="info-label">Email</span>
                            <span class="info-value"><?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="info-label">Role</span>
                            <span class="info-value"><span class="badge <?php echo $user['role'] === 'admin' ? 'admin' : ($user['role'] === 'editor' ? 'editor' : 'viewer'); ?>"><?php echo htmlspecialchars($user['role'] ?? 'viewer', ENT_QUOTES, 'UTF-8'); ?></span></span>
                            <span class="info-label">Two-factor auth</span>
                            <span class="info-value">
                                <?php if (!empty($user['totp_secret'])): ?>
                                    <span class="badge badge-on">Enabled</span>
                                <?php else: ?>
                                    <span class="badge badge-off">Disabled</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 2FA Enrollment -->
                <div class="card">
                    <div class="card-header"><h2>Two-Factor Authentication</h2></div>
                    <div class="card-body">
                        <?php if (empty($user['totp_secret'])): ?>
                            <?php if ($pendingSecret): ?>
                                <p style="color: var(--muted-fg); font-size: 0.9rem; margin-bottom: 1rem;">
                                    Scan the code below with your authenticator app (or add the account manually), then enter the 6-digit code to confirm.
                                </p>
                                <div class="secret-box">
                                    Secret: <strong><?php echo chunk_split($pendingSecret, 4, ' '); ?></strong>
                                </div>
                                <div class="secret-box">
                                    <?php
                                        $uri = Totp::provisioningUri($pendingSecret, $user['email'] ?? $user['username'], 'WAM Blog');
                                        echo htmlspecialchars($uri, ENT_QUOTES, 'UTF-8');
                                    ?>
                                </div>
                                <form method="POST" action="profile.php">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="action" value="confirm_2fa">
                                    <div class="form-row-inline">
                                        <div class="form-group">
                                            <label class="form-label">6-digit code</label>
                                            <input type="text" name="code" class="form-input" maxlength="6" inputmode="numeric" autocomplete="one-time-code" required placeholder="000000">
                                        </div>
                                        <button type="submit" class="btn btn-primary">Confirm &amp; Enable</button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <p style="color: var(--muted-fg); font-size: 0.9rem; margin-bottom: 1rem;">
                                    Add an extra layer of security. After enabling, every sign in will require a code from your authenticator app.
                                </p>
                                <form method="POST" action="profile.php">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="action" value="generate_2fa">
                                    <button type="submit" class="btn btn-primary">Enable Two-Factor Auth</button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <p style="color: var(--muted-fg); font-size: 0.9rem; margin-bottom: 1rem;">
                                Two-factor authentication is active. Disabling requires your current verification code.
                            </p>
                            <form method="POST" action="profile.php">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="disable_2fa">
                                <div class="form-row-inline">
                                    <div class="form-group">
                                        <label class="form-label">Current 6-digit code</label>
                                        <input type="text" name="code" class="form-input" maxlength="6" inputmode="numeric" autocomplete="one-time-code" required placeholder="000000">
                                    </div>
                                    <button type="submit" class="btn btn-danger">Disable 2FA</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sessions -->
                <div class="card">
                    <div class="card-header">
                        <h2>Active Sessions</h2>
                        <form method="POST" action="profile.php" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="revoke_sessions">
                            <button type="submit" class="btn btn-outline btn-sm">Sign out other devices</button>
                        </form>
                    </div>
                    <div class="card-body" style="padding-top: 0.5rem;">
                        <?php if (empty($sessions)): ?>
                            <p style="color: var(--muted-fg); font-size: 0.875rem;">No sessions found.</p>
                        <?php else: ?>
                            <?php foreach ($sessions as $s): ?>
                                <div class="session-row">
                                    <div>
                                        <div>
                                            <?php
                                                $isCurrent = hash_equals(hash('sha256', session_id()), (string)$s['session_token_hash']);
                                            ?>
                                            <?php if ($s['revoked_at'] !== null): ?>
                                                <span class="session-tag" style="color: var(--destructive);">Revoked</span>
                                            <?php elseif ($isCurrent): ?>
                                                <span class="session-tag">This device</span>
                                            <?php else: ?>
                                                <span class="session-tag" style="color: var(--muted-fg);">Active</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="session-meta">
                                            <?php echo htmlspecialchars($s['ip'] ?? 'Unknown IP', ENT_QUOTES, 'UTF-8'); ?>
                                            &nbsp;·&nbsp;
                                            <?php echo htmlspecialchars((string)($s['user_agent'] ?? 'Unknown device'), ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    </div>
                                    <div class="session-meta">
                                        <?php
                                            $created = new DateTime($s['created_at']);
                                            echo 'Signed in ' . $created->format('M j, Y g:i A');
                                        ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
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