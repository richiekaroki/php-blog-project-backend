<?php
// admin/login.php - Admin login with PDO, CSRF and Rate Limiting

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Database\Connection;
use App\Middleware\Auth;
use App\Middleware\CSRF;
use App\Middleware\CORS;
use App\Auth\MagicLink;
use App\Auth\Totp;
use App\Mail\Mailer;
use App\Models\ActivityLog;

CORS::handle();

$pdo = Connection::getInstance();
CSRF::init();

// HIGH-4: IP-based rate limiting stored in the DB (session counters were
// never incremented and could be reset by clearing cookies).
$rateLimit = new \App\Middleware\RateLimit($pdo);
$clientIp = \App\Middleware\RateLimit::clientIp();

// Determine the response type: JSON for API requests, HTML for the page/form.
$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
    || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
header('Content-Type: ' . ($wantsJson ? 'application/json' : 'text/html; charset=UTF-8'));

// Handle passwordless magic link request (HTML form POST)
// Any email can request a link; if no account exists yet, one is auto-created
// (editor role) so first-time sign-in is smooth.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['magic_email'])) {
    if (!CSRF::verify($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        $magicError = 'Invalid CSRF token. Please reload and try again.';
    } elseif ($rateLimit->isBlocked('magic', $clientIp, 5, 900)) {
        http_response_code(429);
        $magicError = 'Too many sign in requests. Please try again in 15 minutes.';
    } else {
        $magicEmail = strtolower(trim($_POST['magic_email']));
        $rateLimit->hit('magic', $clientIp, 900);

        if (!filter_var($magicEmail, FILTER_VALIDATE_EMAIL)) {
            $magicError = 'Please enter a valid email address.';
        } elseif ($rateLimit->isBlockedKey('magic_email', $magicEmail, 3, 3600)) {
            http_response_code(429);
            $magicError = 'Too many sign in links sent to this email. Please try again in an hour.';
        } else {
            $rateLimit->hitKey('magic_email', $magicEmail, 3600);
            try {
                // Auto-provision: create the account if this is a new email.
                $user = \App\Models\Invitation::provision($magicEmail, 'editor');

                $ttl = (int)(getenv('MAGIC_LINK_TTL') ?: 600);
                $magic = new MagicLink();
                $token = $magic->create($magicEmail, $ttl);

                $appUrl = rtrim((string)(getenv('APP_URL') ?: 'https://php-blog-backend.onrender.com'), '/');
                // Deliver the token in the URL fragment (#magic=...) instead of the
                // query string so it never appears in server/referer logs.
                $loginUrl = $appUrl . '/admin/login.php#magic=' . urlencode($token);
                $safeUrl = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');

                $htmlBody = '<!DOCTYPE html><html><body style="font-family:Georgia,serif;background:#FBF9F1;color:#2E2910;padding:24px;text-align:center;">'
                    . '<h1 style="color:#2C5745;">Sign in to WAM Blog</h1>'
                    . '<p>Click the button below to sign in. This link is valid for 10 minutes.</p>'
                    . '<a href="' . $safeUrl . '" style="display:inline-block;margin:16px auto;padding:12px 24px;background:#2C5745;color:#fff;text-decoration:none;border-radius:8px;">Sign In</a>'
                    . '<p style="color:#5C5340;font-size:14px;">If you did not request this, you can safely ignore this email.</p>'
                    . '</body></html>';

                $mailer = new Mailer();
                $mailer->send(
                    $magicEmail,
                    'Your WAM Blog sign in link',
                    $htmlBody,
                    "Open this link to sign in to WAM Blog:\n\n$loginUrl\n\nThis link expires in " . round($ttl / 60) . " minutes."
                );

                ActivityLog::log('magic_link_sent', 'auth', (int)($user['id'] ?? 0), ['email' => $magicEmail]);
            } catch (\Throwable $e) {
                error_log('Magic link send failed: ' . $e->getMessage());
                $magicError = 'Could not send the sign in link. Please try again later.';
            }

            if (!isset($magicError)) {
                $magicSent = true;
            }
        }
    }
}

// Shared redemption logic for magic-link tokens. Emits an HTTP error (via die)
// on failure, otherwise signs the user in (establishing the session) and exits.
function redeemMagicToken($pdo, string $token): void
{
    $magic = new MagicLink();
    $email = $magic->verify($token);

    if ($email === null) {
        http_response_code(401);
        die('This sign in link is invalid or has expired. Please request a new one.');
    }

    // Single-use: atomically consume the token. A token that was already
    // redeemed (even by the legitimate user) is rejected.
    if (!$magic->consume($pdo, $token)) {
        http_response_code(401);
        die('This sign in link has already been used. Please request a new one.');
    }

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE LOWER(email) = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        die('No account is linked to that email.');
    }

    // If the admin has TOTP 2FA enabled, require a code before establishing
    // the session. The pending email is kept in the session until verified.
    if (!empty($user['totp_secret'])) {
        $_SESSION['pending_2fa_email'] = $user['email'];
        $_SESSION['pending_2fa_username'] = $user['username'];
        $_SESSION['pending_2fa_role'] = $user['role'] ?? 'editor';
        header('Content-Type: text/html; charset=UTF-8');
        render2faChallenge();
        exit;
    }

    // No 2FA configured: sign in immediately.
    $_SESSION['admin'] = $user['username'];
    $_SESSION['user_role'] = $user['role'] ?? 'editor';
    Auth::registerSession($user['username']);
    ActivityLog::log('magic_link_used', 'auth', (int)$user['id'], ['email' => $user['email']]);

    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'email' => $user['email'] ?? null,
                'role' => $user['role'] ?? 'editor',
            ]
        ]);
    } else {
        header("Location: blogs.php");
    }
    exit;
}

// Legacy query-string redemption (?action=magic&token=...) â€” still accepted so
// previously-issued links keep working, but new links use the fragment instead.
if (isset($_GET['action']) && $_GET['action'] === 'magic') {
    $token = $_GET['token'] ?? '';
    if ($token === '') {
        http_response_code(400);
        die('Missing token.');
    }
    redeemMagicToken($pdo, $token);
}

// Fragment-based redemption (#magic=...). The token never travels in the query
// string, so it is absent from server logs, browser history, and Referer headers.
// The browser posts it here as JSON from a small snippet on this page.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'redeem_magic') {
    if (!CSRF::verify($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Invalid CSRF token. Please reload and try again.');
    }
    $token = $_POST['magic_token'] ?? '';
    if ($token === '') {
        http_response_code(400);
        die('Missing token.');
    }
    redeemMagicToken($pdo, $token);
}

// Handle 2FA code submission (only valid when a magic link has set a pending email)
if (isset($_POST['action']) && $_POST['action'] === 'verify_2fa') {
    if (!isset($_SESSION['pending_2fa_email'])) {
        http_response_code(401);
        die('No pending sign in. Please request a new magic link.');
    }

    if ($rateLimit->isBlocked('2fa', $clientIp, 5, 900)) {
        http_response_code(429);
        die('Too many verification attempts. Please request a new magic link in 15 minutes.');
    }

    if (!CSRF::verify($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        die('Invalid CSRF token. Please reload the page and try again.');
    }

    $code = trim($_POST['code'] ?? '');
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE LOWER(email) = ? LIMIT 1");
    $stmt->execute([$_SESSION['pending_2fa_email']]);
    $user = $stmt->fetch();

    if (!$user || empty($user['totp_secret'])) {
        http_response_code(401);
        die('No account or 2FA configuration found. Please request a new magic link.');
    }

    if (Totp::verify($user['totp_secret'], $code)) {
        $rateLimit->reset('2fa', $clientIp);
        $_SESSION['admin'] = $user['username'];
        $_SESSION['user_role'] = $user['role'] ?? 'editor';
        unset($_SESSION['pending_2fa_email'], $_SESSION['pending_2fa_username'], $_SESSION['pending_2fa_role']);
        Auth::registerSession($user['username']);
        ActivityLog::log('magic_link_used', 'auth', (int)$user['id'], ['email' => $user['email'], '2fa' => true]);

        header("Location: blogs.php");
        exit;
    }

    $rateLimit->hit('2fa', $clientIp, 900);
    http_response_code(401);
    header('Content-Type: text/html; charset=UTF-8');
    render2faChallenge('Invalid verification code. Please try again.');
}

// Handle auth status check (for Vue frontend)
if (isset($_GET['action']) && $_GET['action'] === 'status') {
    if (isset($_SESSION['admin']) && Auth::isSessionValid()) {
        $stmt = $pdo->prepare("SELECT id, username, email, role FROM admins WHERE username = ? LIMIT 1");
        $stmt->execute([$_SESSION['admin']]);
        $user = $stmt->fetch();
        echo json_encode([
            'authenticated' => true,
            'user' => $user ? [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'email' => $user['email'] ?? null,
                'role' => $user['role'] ?? 'editor',
            ] : [
                'id' => null,
                'username' => $_SESSION['admin'],
                'email' => null,
                'role' => $_SESSION['user_role'] ?? 'editor',
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['authenticated' => false]);
    }
    exit;
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    Auth::logout();
    echo json_encode(['success' => true, 'message' => 'Logged out']);
    exit;
}

// HIGH-4: IP-based rate limiting stored in the DB (session counters were
// never incremented and could be reset by clearing cookies).
$rateLimit = new \App\Middleware\RateLimit($pdo);
$clientIp = \App\Middleware\RateLimit::clientIp();

/**
 * Render the TOTP 2FA challenge page.
 */
function render2faChallenge(?string $error = null): void
{
    $email = htmlspecialchars($_SESSION['pending_2fa_email'] ?? '', ENT_QUOTES, 'UTF-8');
    $csrf = $_SESSION['csrf_token'] ?? '';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication · WAM Blog</title>
    <link rel="stylesheet" href="../assets/admin.css">
    <script>
        (function() {
            if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-logo"><span class="auth-logo-text">W</span></div>
            <h1>Two-factor check</h1>
            <p class="subtitle">Enter the 6-digit code from your authenticator app for <strong><?php echo $email; ?></strong></p>
            <?php if ($error): ?>
                <div class="alert error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form method="POST" action="login.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="action" value="verify_2fa">
                <div class="form-group">
                    <label for="code">Verification code</label>
                    <input type="text" name="code" id="code" class="form-input code-input" maxlength="6" inputmode="numeric" autocomplete="one-time-code" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Verify &amp; Sign In</button>
            </form>
            <p class="subtitle" style="margin-top: 1.25rem; margin-bottom: 0;">The magic link is single-use. If this fails, request a new one.</p>
        </div>
    </div>
    <button class="theme-toggle" onclick="toggleTheme()" title="Toggle theme" aria-label="Toggle theme">&#9681;</button>
    <script>
        function toggleTheme() {
            const root = document.documentElement;
            root.classList.toggle('dark');
            localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light');
        }
    </script>
</body>
</html>
<?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login · WAM Blog</title>
    <link rel="stylesheet" href="../assets/admin.css">
    <script>
        // Apply saved theme before paint to avoid a flash of light mode.
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="auth-page">
    <div class="auth-container">
        <a href="/" class="back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m15 18-6-6 6-6"/>
            </svg>
            Back to blog
        </a>

        <div class="auth-card">
            <div class="auth-logo"><span class="auth-logo-text">W</span></div>
            <h1>Welcome back</h1>
            <p class="subtitle">Sign in to manage your stories</p>

            <?php if (isset($error)): ?>
                <div class="alert error">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <!-- Passwordless sign in via email link -->
            <?php if (!empty($magicSent)): ?>
                <div class="magic-sent">
                    <div class="magic-sent-icon">&#10003;</div>
                    <p>We sent a sign in link to <strong><?php echo htmlspecialchars($magicEmail ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>. Click it to get started. The link expires in 10 minutes.</p>
                </div>
            <?php else: ?>
                <?php if (!empty($magicError)): ?>
                    <div class="alert error">
                        <strong>Error:</strong> <?php echo htmlspecialchars($magicError, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                <p class="magic-subtitle">We will send a secure sign in link to your email. No password required.</p>
                <form method="POST" action="login.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="form-group">
                        <label for="magic_email">Email address</label>
                        <input type="email" name="magic_email" id="magic_email" required placeholder="you@example.com">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">Send me a secure link</button>
                </form>
            <?php endif; ?>

            <p class="subtitle" style="margin-top: 1.5rem; margin-bottom: 0;">A place for thoughtful stories and ideas.</p>
        </div>

        <p style="text-align: center; margin-top: 1.25rem; color: var(--muted-fg); font-size: 0.9rem;">New here? <a href="/signup.php">Create an account</a></p>
    </div>

    <button id="theme-toggle" type="button" aria-label="Toggle dark mode" class="theme-toggle" title="Toggle dark mode">&#9681;</button>

    <script>
        // Redeem a magic-link token delivered in the URL fragment (#magic=...).
        // POSTing it keeps the token out of the query string (server logs,
        // browser history, Referer headers).
        (function () {
            const hash = window.location.hash;
            if (!hash || !hash.startsWith('#magic=')) return;

            const token = decodeURIComponent(hash.slice('#magic='.length));
            const csrf = document.querySelector('input[name="csrf_token"]');
            const csrfToken = csrf ? csrf.value : '';

            if (!csrfToken) {
                window.location.replace('/admin/login.php');
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'login.php';
            form.style.display = 'none';

            const hiddenAction = document.createElement('input');
            hiddenAction.type = 'hidden';
            hiddenAction.name = 'action';
            hiddenAction.value = 'redeem_magic';

            const hiddenToken = document.createElement('input');
            hiddenToken.type = 'hidden';
            hiddenToken.name = 'magic_token';
            hiddenToken.value = token;

            const hiddenCsrf = document.createElement('input');
            hiddenCsrf.type = 'hidden';
            hiddenCsrf.name = 'csrf_token';
            hiddenCsrf.value = csrfToken;

            form.appendChild(hiddenAction);
            form.appendChild(hiddenToken);
            form.appendChild(hiddenCsrf);
            document.body.appendChild(form);

            // Clear the fragment immediately so the token doesn't linger in the URL.
            history.replaceState(null, '', window.location.pathname);
            form.submit();
        })();
    </script>
    <script>
        // Toggle dark mode (persists preference in localStorage)
        const themeToggle = document.getElementById('theme-toggle');
        themeToggle.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
        });
    </script>
</body>
</html>
