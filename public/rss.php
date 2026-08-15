<?php
// rss.php - RSS 2.0 feed of the latest posts
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Database\Connection;
use App\Middleware\SecurityHeaders;

$pdo = Connection::getInstance();
SecurityHeaders::send();

$base = (($_SERVER['HTTPS'] ?? 'off') !== 'off' ? 'https' : 'http') . '://'
    . ($_SERVER['HTTP_HOST'] ?? 'php-blog-backend.onrender.com');

$stmt = $pdo->query("
    SELECT blogs.id, blogs.title, blogs.content, blogs.image, blogs.created_at,
           categories.name AS category_name
    FROM blogs
    LEFT JOIN categories ON blogs.category_id = categories.id
    WHERE blogs.status = 'published'
    ORDER BY blogs.created_at DESC, blogs.id DESC
    LIMIT 20
");
$posts = $stmt->fetchAll();

header('Content-Type: application/rss+xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
<title>WAM Blog — Stories Worth Your Time</title>
<link><?php echo $base; ?>/</link>
<description>A quiet journal of the craft — essays and stories on writing, building, and the ideas we keep coming back to. No noise, no clickbait.</description>
<language>en-us</language>
<atom:link href="<?php echo $base; ?>/rss.php" rel="self" type="application/rss+xml" />
<?php foreach ($posts as $p):
    $link = $base . '/post.php?id=' . $p['id'];
    $date = date(DATE_RSS, strtotime($p['created_at'] ?: 'now'));
?>
<item>
<title><?php echo htmlspecialchars($p['title'], ENT_XML1, 'UTF-8'); ?></title>
<link><?php echo $link; ?></link>
<guid isPermaLink="true"><?php echo $link; ?></guid>
<pubDate><?php echo $date; ?></pubDate>
<?php if (!empty($p['category_name'])): ?>
<category><?php echo htmlspecialchars($p['category_name'], ENT_XML1, 'UTF-8'); ?></category>
<?php endif; ?>
<?php if (!empty($p['image'])): ?>
<enclosure url="<?php echo htmlspecialchars($p['image'], ENT_XML1, 'UTF-8'); ?>" type="image/jpeg" length="0" />
<?php endif; ?>
<description><?php echo htmlspecialchars($p['content'], ENT_XML1, 'UTF-8'); ?></description>
</item>
<?php endforeach; ?>
</channel>
</rss>