<?php
// admin/edit-blog.php - Edit blog with PDO and CSRF protection

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Database\Connection;
use App\Middleware\Auth;
use App\Middleware\CSRF;

Auth::check();
$pdo = Connection::getInstance();
CSRF::init();

// Existing code for blog editing...
if (!isset($_GET['id'])) {
    die("Blog ID is required.");
}

$blog_id = $_GET['id'];

// Fetch blog details using PDO prepared statement
$sql = "SELECT * FROM blogs WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$blog_id]);
$blog = $stmt->fetch();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token'] ||
        hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']) === false) {
        die('Invalid CSRF token');
    }

    $title = $_POST['title'];
    $content = $_POST['content'];

    $sql = "UPDATE blogs SET title = ?, content = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$title, $content, $blog_id]);
    header("Location: blogs.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Post - WAM Blog</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Source+Sans+3:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #FBF9F1;
            --fg: #2E2910;
            --card: #FFFFFF;
            --primary: #2C5745;
            --primary-fg: #FFFFFF;
            --muted: #F5F0DC;
            --muted-fg: #5C5340;
            --border: #D4C9A8;
            --green: #2C5745;
            --olive: #2E2910;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Source Sans 3', sans-serif; background: var(--bg); color: var(--fg); }
        h1, h2 { font-family: 'Lora', serif; color: var(--olive); }
        a { color: var(--green); text-decoration: none; }
        a:hover { color: #EB7D00; }

        .layout { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: var(--card); border-right: 1px solid var(--border); position: fixed; top: 0; left: 0; bottom: 0; display: flex; flex-direction: column; }
        .sidebar-header { padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 0.75rem; }
        .sidebar-logo { width: 36px; height: 36px; background: var(--green); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-family: 'Lora', serif; font-weight: 700; }
        .sidebar-brand { font-family: 'Lora', serif; font-weight: 600; color: var(--olive); }
        .sidebar-sub { font-size: 0.75rem; color: var(--muted-fg); }
        .sidebar-nav { flex: 1; padding: 1rem; }
        .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.9rem; font-weight: 500; color: var(--muted-fg); margin-bottom: 0.25rem; transition: all 0.2s; }
        .nav-item:hover { background: var(--muted); color: var(--fg); }
        .nav-item.active { background: rgba(44,87,69,0.1); color: var(--green); }
        .nav-item svg { width: 20px; height: 20px; }

        .main { flex: 1; margin-left: 260px; }
        .header { height: 64px; background: var(--card); border-bottom: 1px solid var(--border); display: flex; align-items: center; padding: 0 1.5rem; position: sticky; top: 0; z-index: 40; }
        .header-title { font-family: 'Lora', serif; font-size: 1.1rem; font-weight: 600; }
        .content { padding: 2rem; max-width: 800px; }

        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.25rem; border-radius: 0.5rem; font-size: 0.9rem; font-weight: 500; font-family: inherit; cursor: pointer; transition: all 0.2s; border: none; }
        .btn-primary { background: var(--green); color: white; }
        .btn-primary:hover { background: #234a3a; }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--muted-fg); }
        .btn-outline:hover { border-color: var(--green); color: var(--green); }

        .card { background: var(--card); border-radius: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06); margin-bottom: 1.5rem; }
        .card-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .card-header h2 { font-size: 1.1rem; font-weight: 600; }
        .card-body { padding: 1.5rem; }

        .form-group { margin-bottom: 1.25rem; }
        .form-label { display: block; font-size: 0.875rem; font-weight: 500; color: var(--olive); margin-bottom: 0.5rem; }
        .form-input, .form-textarea { width: 100%; padding: 0.65rem 1rem; font-size: 0.95rem; font-family: inherit; border: 1px solid var(--border); border-radius: 0.5rem; background: var(--bg); color: var(--fg); }
        .form-input:focus, .form-textarea:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 3px rgba(44,87,69,0.1); }
        .form-textarea { resize: vertical; min-height: 200px; line-height: 1.7; }

        @media (max-width: 900px) { .sidebar { display: none; } .main { margin-left: 0; } }
    </style>
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">W</div>
                <div><div class="sidebar-brand">WAM Blog</div><div class="sidebar-sub">Content Studio</div></div>
            </div>
            <nav class="sidebar-nav">
                <a href="blogs.php" class="nav-item active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
                    Blogs
                </a>
                <a href="categories.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg>
                    Categories
                </a>
                <a href="/" class="nav-item" target="_blank">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                    View Site
                </a>
            </nav>
        </aside>

        <div class="main">
            <header class="header">
                <h1 class="header-title">Edit Post</h1>
            </header>
            <div class="content">
                <div class="card">
                    <div class="card-header">
                        <h2><?php echo htmlspecialchars($blog['title']); ?></h2>
                        <a href="blogs.php" class="btn btn-outline" style="font-size: 0.8rem;">Back to Posts</a>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="edit-blog.php?id=<?php echo $blog_id; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="form-group">
                                <label class="form-label">Title</label>
                                <input type="text" name="title" class="form-input" value="<?php echo htmlspecialchars($blog['title']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Content</label>
                                <textarea name="content" class="form-textarea" required><?php echo htmlspecialchars($blog['content']); ?></textarea>
                            </div>
                            <div style="display: flex; gap: 0.75rem;">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                <a href="blogs.php" class="btn btn-outline">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
