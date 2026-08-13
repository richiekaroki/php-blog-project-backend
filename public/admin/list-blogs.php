<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog List - WAM Blog</title>
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
            --cream: #EBE3A7;
            --orange: #EB7D00;
            --green: #2C5745;
            --olive: #2E2910;
            --destructive: #C53030;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Source Sans 3', sans-serif; background: var(--bg); color: var(--fg); }
        h1, h2, h3 { font-family: 'Lora', serif; color: var(--olive); }
        a { color: var(--green); text-decoration: none; }
        a:hover { color: var(--orange); }

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
        .btn-danger { background: var(--destructive); color: white; }
        .btn-danger:hover { background: #a02020; }
        .btn-sm { padding: 0.4rem 0.75rem; font-size: 0.8rem; }

        .card { background: var(--card); border-radius: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06); margin-bottom: 1.5rem; }
        .card-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .card-header h2 { font-size: 1.1rem; font-weight: 600; }
        .card-body { padding: 1.5rem; }

        .form-group { margin-bottom: 1.25rem; }
        .form-label { display: block; font-size: 0.875rem; font-weight: 500; color: var(--olive); margin-bottom: 0.5rem; }
        .form-input, .form-textarea { width: 100%; padding: 0.65rem 1rem; font-size: 0.95rem; font-family: inherit; border: 1px solid var(--border); border-radius: 0.5rem; background: var(--bg); color: var(--fg); }
        .form-input:focus, .form-textarea:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 3px rgba(44,87,69,0.1); }
        .form-textarea { resize: vertical; min-height: 80px; line-height: 1.7; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.85rem 1rem; text-align: left; border-bottom: 1px solid var(--border); }
        th { font-size: 0.8rem; font-weight: 600; color: var(--muted-fg); text-transform: uppercase; letter-spacing: 0.05em; }
        tr:hover td { background: rgba(245, 240, 220, 0.5); }
        .tag { display: inline-block; padding: 0.2rem 0.6rem; background: rgba(44,87,69,0.1); color: var(--green); border-radius: 1rem; font-size: 0.75rem; font-weight: 500; }

        .empty-state { text-align: center; padding: 3rem 1rem; color: var(--muted-fg); }

        .pagination { display: flex; justify-content: center; gap: 0.5rem; margin-top: 2rem; }
        .page-link { padding: 0.5rem 1rem; border: 1px solid var(--border); border-radius: 0.5rem; color: var(--muted-fg); font-size: 0.9rem; transition: all 0.2s; }
        .page-link:hover { border-color: var(--green); color: var(--green); }
        .page-link.active { background: var(--green); border-color: var(--green); color: white; }
        .page-link.disabled { opacity: 0.4; pointer-events: none; }

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
            <div style="padding: 1rem; border-top: 1px solid var(--border);">
                <a href="?action=logout" class="nav-item" style="color: var(--destructive);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                    Logout
                </a>
            </div>
        </aside>

        <div class="main">
            <header class="header">
                <h1 class="header-title">Blog List</h1>
            </header>
            <div class="content">
                <?php if (empty($result)): ?>
                    <div class="empty-state">
                        <p>No blogs found.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th style="width: 200px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result as $row): ?>
                            <tr>
                                <td style="color: var(--olive); font-weight: 600;"><?php echo htmlspecialchars($row['title']); ?></td>
                                <td style="color: var(--muted-fg);"><?php echo htmlspecialchars($row['category_name']); ?></td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <a href="edit-blog.php?id=<?php echo $row['id']; ?>" class="btn btn-outline btn-sm">Edit</a>
                                        <button class="btn btn-danger btn-sm" onclick="if(confirm('Delete this blog?')) window.location='?delete=<?php echo $row['id']; ?>'">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>