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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - WAM Blog</title>
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
        .content { padding: 2rem; max-width: 760px; }

        .card { background: var(--card); border-radius: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06); margin-bottom: 1.5rem; }
        .card-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .card-header h2 { font-size: 1.1rem; font-weight: 600; }
        .card-body { padding: 1.5rem; }

        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.25rem; border-radius: 0.5rem; font-size: 0.9rem; font-weight: 500; font-family: inherit; cursor: pointer; transition: all 0.2s; border: none; }
        .btn-primary { background: var(--green); color: white; }
        .btn-primary:hover { background: #234a3a; }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--muted-fg); }
        .btn-outline:hover { border-color: var(--green); color: var(--green); }
        .btn-danger { background: var(--destructive); color: white; }
        .btn-danger:hover { background: #a02020; }
        .btn-sm { padding: 0.4rem 0.75rem; font-size: 0.8rem; }

        .form-group { margin-bottom: 1.25rem; }
        .form-label { display: block; font-size: 0.875rem; font-weight: 500; color: var(--olive); margin-bottom: 0.5rem; }
        .form-input { width: 100%; padding: 0.65rem 1rem; font-size: 0.95rem; font-family: inherit; border: 1px solid var(--border); border-radius: 0.5rem; background: var(--bg); color: var(--fg); transition: border-color 0.2s, box-shadow 0.2s; }
        .form-input:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 3px rgba(44,87,69,0.1); }

        .info-grid { display: grid; grid-template-columns: 140px 1fr; gap: 0.75rem 1.5rem; }
        .info-label { color: var(--muted-fg); font-size: 0.875rem; }
        .info-value { font-weight: 500; }

        .secret-box {
            background: var(--muted); border: 1px dashed var(--border); border-radius: 0.5rem;
            padding: 1rem; font-family: 'Courier New', monospace; font-size: 0.9rem;
            word-break: break-all; margin-bottom: 1rem; text-align: center;
        }

        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 600; }
        .badge-on { background: rgba(44,87,69,0.12); color: var(--green); }
        .badge-off { background: rgba(197,48,48,0.1); color: var(--destructive); }

        .session-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 0.75rem 0; border-bottom: 1px solid var(--border); }
        .session-row:last-child { border-bottom: none; }
        .session-meta { font-size: 0.85rem; color: var(--muted-fg); }
        .session-tag { font-size: 0.7rem; color: var(--green); font-weight: 600; }

        .flash { padding: 1rem; border-radius: 0.5rem; font-size: 0.875rem; margin-bottom: 1.5rem; }
        .flash-success { background: rgba(44,87,69,0.08); border: 1px solid rgba(44,87,69,0.2); color: var(--green); }
        .flash-error { background: rgba(197,48,48,0.1); border: 1px solid rgba(197,48,48,0.2); color: var(--destructive); }

        .form-row-inline { display: flex; gap: 0.75rem; align-items: flex-end; }
        .form-row-inline .form-group { flex: 1; margin-bottom: 0; }

        @media (max-width: 900px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .info-grid { grid-template-columns: 1fr; gap: 0.25rem 0; }
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
                <a href="profile.php" class="nav-item active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Account
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
                <h1 class="header-title">Account Settings</h1>
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
                            <span class="info-value"><span class="badge" style="background: rgba(44,87,69,0.1); color: var(--green); text-transform: capitalize;"><?php echo htmlspecialchars($user['role'] ?? 'viewer', ENT_QUOTES, 'UTF-8'); ?></span></span>
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
</body>
</html>