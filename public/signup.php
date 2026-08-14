<?php
// signup.php - Public access request (passwordless sign-up)
// Visitors request an account; an admin approves it later in the admin panel.

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Middleware\RateLimit;
use App\Models\Invitation;

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
        } elseif (Invitation::isAdmin($email)) {
            // Already a team member: point them at the normal sign-in.
            $error = 'An account already exists for this email. Please sign in instead.';
        } elseif (Invitation::request($email, 'editor') === false) {
            $error = 'Could not submit your request. Please try again later.';
        } else {
            $sent = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Access - WAM Blog</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Source+Sans+3:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #FBF9F1; --fg: #2E2910; --card: #FFFFFF; --primary: #2C5745;
            --primary-fg: #FFFFFF; --muted: #F5F0DC; --muted-fg: #5C5340;
            --border: #D4C9A8; --green: #2C5745; --olive: #2E2910;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Source Sans 3', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--bg) 0%, #EBE3A7 50%, var(--bg) 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 1rem; color: var(--fg);
        }
        .card {
            background: var(--card); border-radius: 1rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            padding: 2rem; width: 100%; max-width: 420px;
        }
        .logo {
            width: 3rem; height: 3rem; background: var(--green); border-radius: 50%;
            display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;
            font-family: 'Lora', Georgia, serif; font-weight: 700; font-size: 1.5rem; color: white;
        }
        h1 { font-family: 'Lora', Georgia, serif; font-size: 1.5rem; text-align: center; color: var(--olive); margin-bottom: 0.5rem; }
        .subtitle { text-align: center; color: var(--muted-fg); font-size: 0.875rem; margin-bottom: 2rem; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-size: 0.875rem; font-weight: 500; color: var(--olive); margin-bottom: 0.5rem; }
        input[type="email"] {
            width: 100%; padding: 0.75rem 1rem; font-size: 1rem; font-family: inherit;
            border: 1px solid var(--border); border-radius: 0.5rem; background: var(--bg); color: var(--fg);
        }
        input[type="email"]:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 3px rgba(44,87,69,0.1); }
        .btn-primary {
            width: 100%; padding: 0.75rem 1.5rem; font-size: 1rem; font-weight: 500; font-family: inherit;
            background: var(--green); color: white; border: none; border-radius: 0.5rem; cursor: pointer;
        }
        .btn-primary:hover { background: #234a3a; }
        .btn-primary:disabled { opacity: 0.7; cursor: not-allowed; }
        .message {
            padding: 1rem; border-radius: 0.5rem; font-size: 0.875rem; margin-bottom: 1.5rem; line-height: 1.5;
        }
        .message.success { background: rgba(44,87,69,0.1); border: 1px solid rgba(44,87,69,0.3); color: var(--green); }
        .message.error { background: rgba(197,48,48,0.1); border: 1px solid rgba(197,48,48,0.2); color: #c53030; }
        .footer-text { text-align: center; color: var(--muted-fg); font-size: 0.875rem; margin-top: 1.5rem; }
        .footer-text a { color: var(--green); }
        .back-link {
            display: inline-flex; align-items: center; gap: 0.5rem; color: var(--muted-fg);
            text-decoration: none; font-size: 0.875rem; margin-bottom: 2rem; transition: color 0.2s;
        }
        .back-link:hover { color: var(--green); }
    </style>
</head>
<body>
    <div style="width: 100%; max-width: 420px;">
        <a href="/" class="back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            Back to blog
        </a>
        <div class="card">
            <div class="logo">W</div>
            <h1>Request access</h1>
            <p class="subtitle">Join WAM Blog as a writer. An administrator reviews each request.</p>

            <?php if ($sent): ?>
                <div class="message success">Your request has been submitted. An administrator will review it shortly.</div>
            <?php endif; ?>

            <?php if (!$sent): ?>
                <?php if ($error): ?>
                    <div class="message error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <form method="POST" action="signup.php">
                    <div class="form-group">
                        <label for="email">Email address</label>
                        <input type="email" name="email" id="email" required placeholder="you@example.com" <?php echo $blocked ? 'disabled' : ''; ?>>
                    </div>
                    <button type="submit" class="btn-primary" <?php echo $blocked ? 'disabled' : ''; ?>>Request access</button>
                </form>
                <p class="footer-text" style="margin-top: 1rem;">Already have access? <a href="/admin/login.php">Sign in</a></p>
            <?php endif; ?>
        </div>
        <p class="footer-text">A place for thoughtful stories and ideas.</p>
    </div>
</body>
</html>