<?php
// admin/preview.php - Live preview for the editor.
// Receives POSTed markdown from edit-blog.php, renders it through the same
// renderer the public site uses, and returns a standalone HTML fragment.
// Only authenticated editors/admins may call it, and every call must carry
// a valid CSRF token.

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/../inc/post-format.php';

use App\Middleware\Auth;
use App\Middleware\CSRF;

Auth::check();
CSRF::init();

$role = Auth::getRole() ?? 'viewer';
if (!in_array($role, ['admin', 'editor'], true)) {
    http_response_code(403);
    die('Access denied');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed');
}

if (!isset($_POST['csrf_token']) ||
    $_POST['csrf_token'] !== $_SESSION['csrf_token'] ||
    hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']) === false) {
    http_response_code(403);
    die('Invalid CSRF token');
}

$content = (string)($_POST['content'] ?? '');

// Soft cap so a stray paste cannot swamp the editor pane.
if (mb_strlen($content) > 200000) {
    $content = mb_substr($content, 0, 200000);
}

$rendered = renderPostContent($content);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="/assets/site.css">
    <script>
        (function() {
            if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>
</head>
<body>
    <div class="post-preview-body">
        <?php if ($rendered === ''): ?>
            <p class="post-info-label">Nothing to preview yet — start typing to see your story rendered.</p>
        <?php else: ?>
            <?php echo $rendered; ?>
        <?php endif; ?>
    </div>
</body>
</html>