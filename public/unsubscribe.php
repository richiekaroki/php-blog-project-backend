<?php
// unsubscribe.php - One-click unsubscribe from the newsletter, keyed on the
// unguessable per-subscriber token in the email link.

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Database\Connection;
use App\Middleware\SecurityHeaders;

$pdo = Connection::getInstance();
SecurityHeaders::send();

$token = trim((string)($_GET['token'] ?? ''));
$removed = $token !== '' && \App\Models\Subscriber::removeByToken($token);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/svg+xml" href="/favicon-v2.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribe · WAM Blog</title>
    <link rel="stylesheet" href="assets/site.css">
    <script>
        (function() {
            if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>
</head>
<body>
    <nav class="nav">
        <div class="nav-inner">
            <a href="/" class="nav-brand">
                <img class="nav-logo" src="/favicon-v2.svg" alt="WAM" width="64" height="64">
                <span class="nav-title">WAM Blog</span>
            </a>
        </div>
    </nav>
    <main class="container" style="max-width: 560px; margin: 4rem auto; padding: 0 1.25rem;">
        <div class="card" style="padding: 2rem;">
            <h1 style="font-size: 1.5rem; color: var(--olive); margin-bottom: 0.75rem;"><?php echo $removed ? 'You are unsubscribed' : 'Unsubscribe link'; ?></h1>
            <?php if ($removed): ?>
                <p>You have been removed from the WAM Blog newsletter. You will no longer receive new-post emails. If this was a mistake, you can resubscribe any time from the site footer.</p>
            <?php else: ?>
                <p>This link is invalid or has already been used.</p>
            <?php endif; ?>
            <p style="margin-top: 1.5rem;"><a href="/" class="btn-outline">Back to WAM Blog</a></p>
        </div>
    </main>
</body>
</html>