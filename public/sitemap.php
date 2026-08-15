<?php
// sitemap.php - XML sitemap of all crawlable pages. Served at /sitemap.xml
// via an nginx rewrite so search engines find the conventional URL.
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Database\Connection;

$pdo = Connection::getInstance();

$base = (($_SERVER['HTTPS'] ?? 'off') !== 'off' ? 'https' : 'http') . '://'
    . ($_SERVER['HTTP_HOST'] ?? 'php-blog-backend.onrender.com');

$stmt = $pdo->query("
    SELECT blogs.id, blogs.created_at
    FROM blogs
    WHERE blogs.status = 'published'
    ORDER BY blogs.created_at DESC, blogs.id DESC
");
$posts = $stmt->fetchAll();

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<url><loc><?php echo $base; ?>/</loc><changefreq>daily</changefreq><priority>1.0</priority></url>
<url><loc><?php echo $base; ?>/signup.php</loc><changefreq>monthly</changefreq><priority>0.4</priority></url>
<?php foreach ($posts as $p): ?>
<url>
<loc><?php echo $base; ?>/post.php?id=<?php echo $p['id']; ?></loc>
<?php if (!empty($p['created_at'])): ?>
<lastmod><?php echo gmdate('Y-m-d\TH:i:s\Z', strtotime($p['created_at'])); ?></lastmod>
<?php endif; ?>
<changefreq>monthly</changefreq>
<priority>0.8</priority>
</url>
<?php endforeach; ?>
</urlset>