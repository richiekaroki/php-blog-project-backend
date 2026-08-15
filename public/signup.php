<?php
// signup.php - Public sign-up (passwordless)
// Any email gets an account (editor role) and a sign-in link immediately.
// No admin approval is required.

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Middleware\RateLimit;
use App\Models\Invitation;
use App\Auth\MagicLink;
use App\Mail\Mailer;
use App\Support\Env;

// Rate limit signup requests by IP (5 per 15 minutes) to prevent abuse.
$pdo = \App\Database\Connection::getInstance();
$ip = RateLimit::clientIp();
$rate = new RateLimit($pdo);
$blocked = $rate->isBlocked('signup', $ip, 5, 900);

$error = '';
$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($blocked) {
        http_response_code(429);
        $error = 'Too many requests. Please try again in 15 minutes.';
    } else {
        $rate->hit('signup', $ip, 900);
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                $user = Invitation::provision($email, 'editor');
                if ($user === null) {
                    $error = 'Could not create your account. Please try again later.';
                } else {
                    $ttl = (int)(Env::get('MAGIC_LINK_TTL') ?: 600);
                    $magic = new MagicLink();
                    $token = $magic->create($email, $ttl);
                    $appUrl = rtrim((string)(Env::get('APP_URL') ?: 'https://php-blog-backend.onrender.com'), '/');
                    $loginUrl = $appUrl . '/admin/login.php?action=magic&token=' . urlencode($token);
                    $safeUrl = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');

                    $htmlBody = '<!DOCTYPE html><html><body style="font-family:Georgia,serif;background:#FBF9F1;color:#2E2910;padding:24px;text-align:center;">'
                        . '<h1 style="color:#2C5745;">Welcome to WAM Blog</h1>'
                        . '<p>Your account is ready. Click the button below to sign in. This link is valid for 10 minutes.</p>'
                        . '<a href="' . $safeUrl . '" style="display:inline-block;margin:16px auto;padding:12px 24px;background:#2C5745;color:#fff;text-decoration:none;border-radius:8px;">Sign In</a>'
                        . '<p style="color:#5C5340;font-size:14px;">If you did not request this, you can safely ignore this email.</p>'
                        . '</body></html>';

                    $mailer = new Mailer();
                    $mailer->send(
                        $email,
                        'Welcome to WAM Blog — sign in',
                        $htmlBody,
                        "Welcome to WAM Blog. Open this link to sign in:\n\n$loginUrl\n\nThis link expires in " . round($ttl / 60) . " minutes."
                    );
                    $sent = true;
                }
            } catch (\Throwable $e) {
                error_log('Signup send failed: ' . $e->getMessage());
                $error = 'Could not send the sign in link. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/svg+xml" href="/favicon-v2.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up · WAM Blog</title>
    <link rel="stylesheet" href="assets/site.css">
    <script>
        (function() {
            if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>
</head>
<body class="auth-page">
    <div class="auth-container">
        <a href="/" class="back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            Back to blog
        </a>
        <div class="auth-card">
            <img class="auth-logo" src="/favicon-v2.svg" alt="WAM" width="64" height="64">
            <h1>Join WAM Blog</h1>
            <p class="subtitle">Enter your email and we'll create your account and send you a secure sign in link.</p>

            <?php if ($sent): ?>
                <div class="alert success">Your account is ready. We sent a sign in link to your inbox.</div>
            <?php endif; ?>

            <?php if (!$sent): ?>
                <?php if ($error): ?>
                    <div class="alert error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <form method="POST" action="signup.php">
                    <div class="form-group">
                        <label for="email">Email address</label>
                        <input type="email" name="email" id="email" required placeholder="you@example.com" <?php echo $blocked ? 'disabled' : ''; ?>>
                    </div>
                    <button type="submit" class="btn-primary" style="width: 100%;" <?php echo $blocked ? 'disabled' : ''; ?>>Create my account</button>
                </form>
                <p class="subtitle" style="margin-top: 1rem; margin-bottom: 0;">Already have an account? <a href="/admin/login.php">Sign in</a></p>
            <?php endif; ?>
        </div>
        <p class="subtitle" style="margin-bottom: 0;">A place for thoughtful stories and ideas.</p>
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