<?php
// admin/login.php - Admin login with PDO, CSRF and Rate Limiting

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Database\Connection;
use App\Middleware\CSRF;
use App\Middleware\CORS;
use App\Auth\MagicLink;
use App\Mail\Mailer;

CORS::handle();

$pdo = Connection::getInstance();
CSRF::init();

// Determine the response type: JSON for API requests, HTML for the page/form.
$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
    || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
header('Content-Type: ' . ($wantsJson ? 'application/json' : 'text/html; charset=UTF-8'));

// Handle passwordless magic link request (HTML form POST)
// The email must belong to a registered admin; otherwise no email is sent (no account leaking).
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['magic_email'])) {
    if (!CSRF::verify($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        $magicError = 'Invalid CSRF token. Please reload and try again.';
    } else {
        $magicEmail = strtolower(trim($_POST['magic_email']));

        if (!filter_var($magicEmail, FILTER_VALIDATE_EMAIL)) {
            $magicError = 'Please enter a valid email address.';
        } else {
            $stmt = $pdo->prepare("SELECT id, email FROM admins WHERE LOWER(email) = ? LIMIT 1");
            $stmt->execute([$magicEmail]);
            $user = $stmt->fetch();

            // Always show the same generic confirmation to avoid revealing registered emails.
            if ($user) {
                try {
                    $ttl = (int)(getenv('MAGIC_LINK_TTL') ?: 900);
                    $magic = new MagicLink();
                    $token = $magic->create($magicEmail, $ttl);

                    $appUrl = rtrim((string)(getenv('APP_URL') ?: 'https://php-blog-backend.onrender.com'), '/');
                    $loginUrl = $appUrl . '/admin/login.php?action=magic&token=' . urlencode($token);
                    $safeUrl = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');

                    $htmlBody = '<!DOCTYPE html><html><body style="font-family:Georgia,serif;background:#FBF9F1;color:#2E2910;padding:24px;text-align:center;">'
                        . '<h1 style="color:#2C5745;">Sign in to WAM Blog</h1>'
                        . '<p>Click the button below to sign in. This link is valid for 15 minutes.</p>'
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
                } catch (\Throwable $e) {
                    error_log('Magic link send failed: ' . $e->getMessage());
                    $magicError = 'Could not send the sign in link. Please try again later.';
                }
            }

            if (!isset($magicError)) {
                $magicSent = true;
            }
        }
    }
}

// Handle magic link verification (passwordless sign in)
if (isset($_GET['action']) && $_GET['action'] === 'magic') {
    $token = $_GET['token'] ?? '';
    if ($token === '') {
        http_response_code(400);
        die('Missing token.');
    }

    $magic = new MagicLink();
    $email = $magic->verify($token);

    if ($email === null) {
        http_response_code(401);
        die('This sign in link is invalid or has expired. Please request a new one.');
    }

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE LOWER(email) = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        die('No account is linked to that email.');
    }

    $_SESSION['admin'] = $user['username'];
    $_SESSION['user_role'] = $user['role'] ?? 'editor';
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

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

// Handle auth status check (for Vue frontend)
if (isset($_GET['action']) && $_GET['action'] === 'status') {
    if (isset($_SESSION['admin'])) {
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
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Logged out']);
    exit;
}

// HIGH-4: IP-based rate limiting (not session-based — session can be cleared)
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateKey = "login_attempts_" . md5($ip);

// Initialize rate limit counter in session if not set
if (!isset($_SESSION[$rateKey])) {
    $_SESSION[$rateKey] = ['count' => 0, 'time' => 0];
}

// Check rate limit (max 5 attempts per 15 minutes)
$max_attempts = 5;
$lockout_time = 15 * 60; // 15 minutes in seconds

$current_time = time();

// Check if user is locked out
if ($_SESSION[$rateKey]['count'] >= $max_attempts && 
    ($current_time - $_SESSION[$rateKey]['time']) < $lockout_time) {
    $remaining = $lockout_time - ($current_time - $_SESSION[$rateKey]['time']);
    die('Too many login attempts. Please try again in ' . ceil($remaining / 60) . ' minutes.');
}

// Handle login form submission (skip when a magic link request was posted above)
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['magic_email'])) {
    // Validate CSRF token INSIDE the POST handler
    if (!isset($_POST['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token'] ||
        hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']) === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    // Increment attempt counter (IP-based)
    $_SESSION[$rateKey]['count']++;
    $_SESSION[$rateKey]['time'] = $current_time;

    // Get username and password from the form
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Check if username and password are valid using PDO prepared statement
    $sql = "SELECT * FROM admins WHERE username = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Successful login
        $_SESSION['admin'] = $username;
        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);
        // HIGH-3: Regenerate CSRF token after login (prevent token fixation)
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        // Reset login attempts on success
        $_SESSION[$rateKey] = ['count' => 0, 'time' => 0];
        $_SESSION['user_role'] = $user['role'] ?? 'editor';

        // Return JSON for API requests, redirect for HTML forms
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
    } else {
        // Invalid credentials - show error but don't reveal if user exists
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid username or password']);
        } else {
            $error = "Invalid username or password";
        }
    }
}
?>
<?php $magicMode = true; ?>
<?php if (isset($_GET['mode']) && $_GET['mode'] === 'password'): ?>
    <?php $magicMode = false; ?>
<?php endif; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - WAM Blog</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Source+Sans+3:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-background: #FBF9F1;
            --color-foreground: #2E2910;
            --color-card: #FFFFFF;
            --color-primary: #2C5745;
            --color-primary-foreground: #FFFFFF;
            --color-muted: #F5F0DC;
            --color-muted-foreground: #5C5340;
            --color-border: #D4C9A8;
            --color-warm-cream: #EBE3A7;
            --color-warm-orange: #EB7D00;
            --color-forest-green: #2C5745;
            --color-dark-olive: #2E2910;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Source Sans 3', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--color-background) 0%, var(--color-warm-cream) 50%, var(--color-background) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            color: var(--color-foreground);
        }

        .login-container {
            width: 100%;
            max-width: 400px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--color-muted-foreground);
            text-decoration: none;
            font-size: 0.875rem;
            margin-bottom: 2rem;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: var(--color-forest-green);
        }

        .card {
            background: var(--color-card);
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 2rem;
        }

        .logo {
            width: 3rem;
            height: 3rem;
            background: var(--color-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .logo-text {
            font-family: 'Lora', Georgia, serif;
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--color-primary-foreground);
        }

        h1 {
            font-family: 'Lora', Georgia, serif;
            font-size: 1.5rem;
            font-weight: 600;
            text-align: center;
            color: var(--color-dark-olive);
            margin-bottom: 0.5rem;
        }

        .subtitle {
            text-align: center;
            color: var(--color-muted-foreground);
            font-size: 0.875rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--color-dark-olive);
            margin-bottom: 0.5rem;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            font-family: inherit;
            border: 1px solid var(--color-border);
            border-radius: 0.5rem;
            background: var(--color-background);
            color: var(--color-foreground);
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--color-forest-green);
            box-shadow: 0 0 0 3px rgba(44, 87, 69, 0.1);
        }

        .btn-primary {
            width: 100%;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            font-family: inherit;
            background: var(--color-forest-green);
            color: var(--color-primary-foreground);
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
        }

        .btn-primary:hover {
            background: #234a3a;
        }

        .btn-primary:active {
            transform: scale(0.98);
        }

        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .error-message {
            padding: 1rem;
            background: rgba(197, 48, 48, 0.1);
            border: 1px solid rgba(197, 48, 48, 0.2);
            border-radius: 0.5rem;
            color: #c53030;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }

        .mode-toggle {
            display: flex;
            gap: 0.25rem;
            padding: 0.25rem;
            background: var(--color-muted);
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .mode-btn {
            flex: 1;
            padding: 0.6rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            font-family: inherit;
            background: transparent;
            border: none;
            border-radius: 0.5rem;
            color: var(--color-muted-foreground);
            cursor: pointer;
            transition: background-color 0.2s, color 0.2s;
        }

        .mode-btn:hover {
            color: var(--color-forest-green);
        }

        .mode-btn.active {
            background: var(--color-card);
            color: var(--color-foreground);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
        }

        .magic-subtitle {
            text-align: center;
            color: var(--color-muted-foreground);
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }

        .magic-sent {
            text-align: center;
            padding: 1.5rem 1rem;
            background: rgba(44, 87, 69, 0.08);
            border: 1px solid rgba(44, 87, 69, 0.2);
            border-radius: 0.75rem;
            color: var(--color-forest-green);
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }

        .magic-sent-icon {
            width: 3rem;
            height: 3rem;
            margin: 0 auto 0.75rem;
            background: var(--color-forest-green);
            color: var(--color-primary-foreground);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .magic-sent p {
            line-height: 1.5;
        }

        .footer-text {
            text-align: center;
            color: var(--color-muted-foreground);
            font-size: 0.875rem;
            margin-top: 1.5rem;
        }

        .theme-toggle {
            position: fixed;
            top: 1.25rem;
            right: 1.25rem;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.625rem;
            background: var(--color-muted);
            color: var(--color-foreground);
            border: 1px solid var(--color-border);
            cursor: pointer;
            font-size: 1.125rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s, color 0.2s;
        }
        .theme-toggle:hover {
            background: rgba(44, 87, 69, 0.08);
        }

        /* Dark mode overrides for the login page */
        .dark body {
            background: linear-gradient(135deg, #1A1708 0%, #3D3520 50%, #1A1708 100%);
        }
        .dark .nav { color: #EB7317; }
        .dark .card {
            background: #252010;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.3), 0 2px 4px -1px rgba(0,0,0,0.2);
        }
        .dark .back-link { color: #A09580; }
        .dark .back-link:hover { color: #3D7A63; }
        .dark h1 { color: #EB7317; }
        .dark .subtitle { color: #A09580; }
        .dark input[type="email"] {
            background: #2A2412;
            border-color: #3D3520;
            color: #EB7317;
        }
        .dark .btn-primary {
            background: #3D7A63;
            color: #fff;
        }
        .dark .mode-btn,
        .dark .footer-text {
            color: #A09580;
        }
        .dark .magic-sent {
            background: rgba(61, 122, 99, 0.15);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <a href="/" class="back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m15 18-6-6 6-6"/>
            </svg>
            Back to blog
        </a>
        
        <div class="card">
            <div class="logo">
                <span class="logo-text">W</span>
            </div>
            <h1>Welcome back</h1>
            <p class="subtitle">Sign in to manage your stories</p>
            
            <?php if (isset($error)): ?>
                <div class="error-message">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <!-- Passwordless sign in via email link -->
            <?php if (!empty($magicSent)): ?>
                <div class="magic-sent">
                    <div class="magic-sent-icon">✓</div>
                    <p>We sent a sign in link to <strong><?php echo htmlspecialchars($magicEmail ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>. Click it to get started. The link expires in 15 minutes.</p>
                </div>
            <?php else: ?>
                <?php if (!empty($magicError)): ?>
                    <div class="error-message">
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

                    <button type="submit" class="btn-primary">Send me a secure link</button>
                </form>
            <?php endif; ?>
        </div>
        
         <p class="footer-text">A place for thoughtful stories and ideas.</p>
         <button id="theme-toggle" type="button" aria-label="Toggle dark mode" class="theme-toggle" title="Toggle dark mode">
           🌙
         </button>
     </div>

    <script>
        // Toggle dark mode (persists preference in localStorage)
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
        const themeToggle = document.getElementById('theme-toggle');
        themeToggle.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
        });
    </script>
</body>
</html>
