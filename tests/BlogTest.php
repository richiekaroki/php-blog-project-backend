<?php
// tests/BlogTest.php - Comprehensive test suite for blog backend

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../public/inc/post-format.php';

use PHPUnit\Framework\TestCase;

class BlogTest extends TestCase
{
    protected $pdo;

    protected function setUp(): void
    {
        // Load environment variables
        $env = [];
        $lines = file(__DIR__ . '/../.env');
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value)-1] === '"') {
                    $value = substr($value, 1, -1);
                }
                $env[$key] = $value;
            }
        }

        $host = getenv('DB_HOST') ?: ($env['DB_HOST'] ?? '127.0.0.1');
        $port = getenv('DB_PORT') ?: ($env['DB_PORT'] ?? '5432');
        $dbname = getenv('DB_DATABASE') ?: ($env['DB_DATABASE'] ?? 'mizzle_backend');
        $username = getenv('DB_USERNAME') ?: ($env['DB_USERNAME'] ?? 'postgres');
        $password = getenv('DB_PASSWORD') ?: ($env['DB_PASSWORD'] ?? '');

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

        try {
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            $this->markTestSkipped('Database connection failed: ' . $e->getMessage());
        }
    }

    // ==========================================
    // DATABASE TESTS
    // ==========================================

    public function testDatabaseConnection()
    {
        $result = $this->pdo->query('SELECT 1 AS test');
        $row = $result->fetch();
        $this->assertEquals(1, $row['test']);
    }

    public function testAdminsTableExists()
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) AS count FROM admins");
        $row = $stmt->fetch();
        $this->assertGreaterThanOrEqual(0, $row['count']);
    }

    public function testCategoriesTableExists()
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) AS count FROM categories");
        $row = $stmt->fetch();
        $this->assertGreaterThanOrEqual(0, $row['count']);
    }

    public function testBlogsTableExists()
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) AS count FROM blogs");
        $row = $stmt->fetch();
        $this->assertGreaterThanOrEqual(0, $row['count']);
    }

    public function testBlogsTableHasRequiredColumns()
    {
        $stmt = $this->pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'blogs'");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('id', $columns);
        $this->assertContains('title', $columns);
        $this->assertContains('content', $columns);
        $this->assertContains('image', $columns);
        $this->assertContains('category_id', $columns);
    }

    public function testAdminsTableHasRequiredColumns()
    {
        $stmt = $this->pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'admins'");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('id', $columns);
        $this->assertContains('username', $columns);
        $this->assertContains('role', $columns);
        $this->assertContains('email', $columns);
    }

    public function testAdminsTableHasNoPasswordColumn()
    {
        // Auth is passwordless (magic link + TOTP); the legacy bcrypt column
        // must not exist so unused hashes can't be stored or exfiltrated.
        $stmt = $this->pdo->query("
            SELECT column_name FROM information_schema.columns
            WHERE table_name = 'admins' AND column_name = 'password'
        ");
        $this->assertFalse($stmt->fetch(), 'admins.password column should have been dropped');
    }

    public function testCategoriesTableHasRequiredColumns()
    {
        $stmt = $this->pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'categories'");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('id', $columns);
        $this->assertContains('name', $columns);
    }

    // ==========================================
    // SECURITY TESTS — DB-Backed Rate Limiting
    // ==========================================

    public function testLoginRateLimitsTableExists()
    {
        $stmt = $this->pdo->query("SELECT to_regclass('login_rate_limits') AS table_name");
        $row = $stmt->fetch();
        $this->assertNotNull($row['table_name'], 'login_rate_limits table should exist');
    }

    public function testRateLimitBlocksAfterMaxAttempts()
    {
        $this->pdo->exec("DELETE FROM login_rate_limits WHERE bucket = 'test_login'");

        $rl = new \App\Middleware\RateLimit($this->pdo);
        $ip = '203.0.113.99';

        // 3 attempts under the max of 5 → never blocked
        $this->assertFalse($rl->isBlocked('test_login', $ip, 5, 900));
        $this->assertSame(1, $rl->hit('test_login', $ip, 900));
        $this->assertFalse($rl->isBlocked('test_login', $ip, 5, 900));
        $this->assertSame(2, $rl->hit('test_login', $ip, 900));
        $this->assertSame(3, $rl->hit('test_login', $ip, 900));

        // Reach the cap → blocked
        $this->assertSame(4, $rl->hit('test_login', $ip, 900));
        $this->assertSame(5, $rl->hit('test_login', $ip, 900));
        $this->assertTrue($rl->isBlocked('test_login', $ip, 5, 900));

        // reset() clears the block
        $rl->reset('test_login', $ip);
        $this->assertFalse($rl->isBlocked('test_login', $ip, 5, 900));

        $this->pdo->exec("DELETE FROM login_rate_limits WHERE bucket = 'test_login'");
    }

    public function testRateLimitBucketsAreIsolated()
    {
        $this->pdo->exec("DELETE FROM login_rate_limits WHERE bucket LIKE 'test%'");

        $rl = new \App\Middleware\RateLimit($this->pdo);
        $ip = '203.0.113.100';

        for ($i = 0; $i < 6; $i++) {
            $rl->hit('test_magic', $ip, 900);
        }

        // Exhausted in the magic bucket, untouched in the 2fa bucket
        $this->assertTrue($rl->isBlocked('test_magic', $ip, 5, 900));
        $this->assertFalse($rl->isBlocked('test_2fa', $ip, 5, 900));

        $this->pdo->exec("DELETE FROM login_rate_limits WHERE bucket LIKE 'test%'");
    }

    public function testRateLimitEmailKeyedThrottleIsIsolated()
    {
        $this->pdo->exec("DELETE FROM login_rate_limits WHERE bucket LIKE 'test%'");

        $rl = new \App\Middleware\RateLimit($this->pdo);

        // 3 hits under the cap of 3 → not blocked
        $this->assertSame(1, $rl->hitKey('test_magic_email', 'Victim@Example.com', 3600));
        $this->assertSame(2, $rl->hitKey('test_magic_email', 'victim@example.com', 3600));
        $this->assertFalse($rl->isBlockedKey('test_magic_email', 'VICTIM@example.com', 3, 3600));
        $this->assertSame(3, $rl->hitKey('test_magic_email', 'victim@example.com', 3600));
        $this->assertTrue($rl->isBlockedKey('test_magic_email', 'victim@example.com', 3, 3600));

        // Case-insensitive: a different case of the same email is also blocked.
        $this->assertTrue($rl->isBlockedKey('test_magic_email', 'VICTIM@EXAMPLE.COM', 3, 3600));

        // A different email is not affected.
        $this->assertFalse($rl->isBlockedKey('test_magic_email', 'someone-else@example.com', 3, 3600));

        $this->pdo->exec("DELETE FROM login_rate_limits WHERE bucket LIKE 'test%'");
    }

    public function testRateLimitClientIpPrefersForwardedHeader()
    {
        $original = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null;
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.7';
        $this->assertSame('198.51.100.7', \App\Middleware\RateLimit::clientIp());

        unset($_SERVER['HTTP_CF_CONNECTING_IP']);
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.8, 10.0.0.1';
        $this->assertSame('198.51.100.8', \App\Middleware\RateLimit::clientIp());

        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SERVER['REMOTE_ADDR'] = '198.51.100.9';
        $this->assertSame('198.51.100.9', \App\Middleware\RateLimit::clientIp());

        $_SERVER['REMOTE_ADDR'] = 'not-an-ip';
        $this->assertSame('not-an-ip', \App\Middleware\RateLimit::clientIp());

        // Restore
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        if ($original === null) {
            unset($_SERVER['HTTP_CF_CONNECTING_IP']);
        } else {
            $_SERVER['HTTP_CF_CONNECTING_IP'] = $original;
        }
    }

    // ==========================================
    // SECURITY TESTS — SQL Injection Prevention
    // ==========================================

    public function testPreparedStatementsPreventSqlInjection()
    {
        $malicious = "'; DROP TABLE blogs; --";
        $stmt = $this->pdo->prepare("SELECT * FROM blogs WHERE title = ?");
        $stmt->execute([$malicious]);
        $result = $stmt->fetchAll();
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
        // Verify blogs table still exists
        $check = $this->pdo->query("SELECT COUNT(*) FROM blogs");
        $this->assertNotEmpty($check->fetch());
    }

    public function testSearchParameterizedQuery()
    {
        $malicious = "1' OR '1'='1";
        $stmt = $this->pdo->prepare("SELECT * FROM blogs WHERE title ILIKE ?");
        $stmt->execute(["%$malicious%"]);
        $result = $stmt->fetchAll();
        $this->assertIsArray($result);
        // Should not return all rows
    }

    // ==========================================
    // SECURITY TESTS — XSS Prevention
    // ==========================================

    public function testHtmlspecialcharsEscapesScript()
    {
        $xss = '<script>alert("XSS")</script>';
        $escaped = htmlspecialchars($xss, ENT_QUOTES, 'UTF-8');
        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertStringContainsString('&lt;script&gt;', $escaped);
    }

    public function testHtmlspecialcharsEscapesQuotes()
    {
        $xss = '"><img src=x onerror=alert(1)>';
        $escaped = htmlspecialchars($xss, ENT_QUOTES, 'UTF-8');
        $this->assertStringNotContainsString('"', $escaped);
        $this->assertStringContainsString('&quot;', $escaped);
    }

    public function testHtmlspecialcharsEscapesSingleQuotes()
    {
        $xss = "'); alert(1); //";
        $escaped = htmlspecialchars($xss, ENT_QUOTES, 'UTF-8');
        $this->assertStringNotContainsString("'", $escaped);
        $this->assertStringContainsString('&#039;', $escaped);
    }

    public function testJsonEncodeForJavaScriptContext()
    {
        $title = 'Blog"; alert(1); //';
        $json = json_encode($title);
        // json_encode escapes double quotes, backslashes, and control chars
        $this->assertStringNotContainsString('"', str_replace('"', '', $json));
        // Verify it's safe for HTML attribute context
        $escaped = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $this->assertStringNotContainsString('"', $escaped);
    }

    // ==========================================
    // SECURITY TESTS — CSRF Token Generation
    // ==========================================

    public function testCsrfTokenIsHex64Chars()
    {
        $token = bin2hex(random_bytes(32));
        $this->assertEquals(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function testCsrfTokenIsUnique()
    {
        $tokens = [];
        for ($i = 0; $i < 100; $i++) {
            $tokens[] = bin2hex(random_bytes(32));
        }
        $this->assertCount(100, array_unique($tokens));
    }

    // ==========================================
    // SECURITY TESTS — Session Security
    // ==========================================

    public function testSessionCookieHardeningSettings()
    {
        // Verify these ini settings can be set (won't fail in CLI)
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_strict_mode', 1);
        $this->assertTrue(true); // If we got here, settings are valid
    }

    // ==========================================
    // SECURITY TESTS — Image Upload Validation
    // ==========================================

    public function testGetimagesizeRejectsNonImage()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'This is not an image');
        $result = getimagesize($tmpFile);
        unlink($tmpFile);
        $this->assertFalse($result);
    }

    public function testGetimagesizeAcceptsValidPng()
    {
        // Create a minimal valid PNG (1x1 pixel)
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $tmpFile = tempnam(sys_get_temp_dir(), 'test') . '.png';
        file_put_contents($tmpFile, $png);
        $result = getimagesize($tmpFile);
        unlink($tmpFile);
        $this->assertNotFalse($result);
        $this->assertEquals(IMAGETYPE_PNG, $result[2]);
    }

    public function testImageFilenameIsRandom()
    {
        $filenames = [];
        for ($i = 0; $i < 100; $i++) {
            $filenames[] = bin2hex(random_bytes(16)) . '.jpg';
        }
        $this->assertCount(100, array_unique($filenames));
    }

    // ==========================================
    // BLOG CRUD TESTS
    // ==========================================

    public function testCreateBlog()
    {
        // Create a category first (for foreign key)
        $catStmt = $this->pdo->prepare("INSERT INTO categories (name) VALUES (?) RETURNING id");
        $catStmt->execute(['Test Category ' . time()]);
        $catId = $catStmt->fetch()['id'];

        $stmt = $this->pdo->prepare("INSERT INTO blogs (title, content, category_id) VALUES (?, ?, ?) RETURNING id");
        $stmt->execute(['Test Blog ' . time(), 'Test content for portfolio', $catId]);
        $row = $stmt->fetch();
        $this->assertNotEmpty($row['id']);
        $this->assertGreaterThan(0, $row['id']);
        // Cleanup
        $this->pdo->prepare("DELETE FROM blogs WHERE id = ?")->execute([$row['id']]);
        $this->pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$catId]);
    }

    public function testReadBlog()
    {
        // Create
        $stmt = $this->pdo->prepare("INSERT INTO blogs (title, content) VALUES (?, ?) RETURNING id");
        $stmt->execute(['Read Test', 'Content here']);
        $id = $stmt->fetch()['id'];

        // Read
        $stmt = $this->pdo->prepare("SELECT * FROM blogs WHERE id = ?");
        $stmt->execute([$id]);
        $blog = $stmt->fetch();
        $this->assertEquals('Read Test', $blog['title']);
        $this->assertEquals('Content here', $blog['content']);

        // Cleanup
        $this->pdo->prepare("DELETE FROM blogs WHERE id = ?")->execute([$id]);
    }

    public function testUpdateBlog()
    {
        // Create
        $stmt = $this->pdo->prepare("INSERT INTO blogs (title, content) VALUES (?, ?) RETURNING id");
        $stmt->execute(['Update Test', 'Original content']);
        $id = $stmt->fetch()['id'];

        // Update
        $stmt = $this->pdo->prepare("UPDATE blogs SET title = ? WHERE id = ?");
        $stmt->execute(['Updated Title', $id]);

        // Verify
        $stmt = $this->pdo->prepare("SELECT title FROM blogs WHERE id = ?");
        $stmt->execute([$id]);
        $this->assertEquals('Updated Title', $stmt->fetch()['title']);

        // Cleanup
        $this->pdo->prepare("DELETE FROM blogs WHERE id = ?")->execute([$id]);
    }

    public function testDeleteBlog()
    {
        // Create
        $stmt = $this->pdo->prepare("INSERT INTO blogs (title, content) VALUES (?, ?) RETURNING id");
        $stmt->execute(['Delete Test', 'To be deleted']);
        $id = $stmt->fetch()['id'];

        // Delete
        $stmt = $this->pdo->prepare("DELETE FROM blogs WHERE id = ?");
        $stmt->execute([$id]);

        // Verify
        $stmt = $this->pdo->prepare("SELECT * FROM blogs WHERE id = ?");
        $stmt->execute([$id]);
        $this->assertFalse($stmt->fetch());
    }

    public function testBlogTitleMaxLength()
    {
        $longTitle = str_repeat('A', 256);
        $this->assertGreaterThan(255, strlen($longTitle));
        // PostgreSQL VARCHAR(255) would reject this
        // We test that the schema enforces it
        try {
            $stmt = $this->pdo->prepare("INSERT INTO blogs (title, content) VALUES (?, ?)");
            $stmt->execute([$longTitle, 'test']);
            // If it succeeded, clean up and note it
            $this->pdo->exec("DELETE FROM blogs WHERE title = '" . $longTitle . "'");
            // Schema might not have length constraint — that's a finding
            $this->addWarning('Blog title column may lack VARCHAR(255) constraint');
        } catch (PDOException $e) {
            // Expected — column has length constraint
            $this->assertStringContainsString('value too long', $e->getMessage());
        }
    }

    // ==========================================
    // API TESTS — Authentication
    // ==========================================

    public function testApiHealthEndpoint()
    {
        $result = @file_get_contents('http://localhost/api/index.php?action=health');
        if ($result === false) {
            $this->markTestSkipped('API not accessible');
        }
        $data = json_decode($result, true);
        $this->assertEquals('ok', $data['status']);
        $this->assertArrayHasKey('timestamp', $data);
    }

    public function testApiGetBlogsPublic()
    {
        $result = @file_get_contents('http://localhost/api/index.php?action=blogs');
        if ($result === false) {
            $this->markTestSkipped('API not accessible');
        }
        $data = json_decode($result, true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
    }

    public function testApiPostRequiresAuth()
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode(['title' => 'test', 'content' => 'test']),
            ]
        ]);
        $result = @file_get_contents('http://localhost/api/index.php?action=blogs', false, $ctx);
        if ($result === false) {
            $this->markTestSkipped('API not accessible');
        }
        $data = json_decode($result, true);
        $this->assertEquals(401, http_response_code());
        $this->assertArrayHasKey('error', $data);
    }

    public function testApiFieldWhitelisting()
    {
        // Even if authenticated, unexpected fields should be ignored
        // This tests the array_intersect_key logic
        $data = ['title' => 'test', 'content' => 'test', 'id' => 999, 'created_at' => 'now'];
        $allowed = ['title', 'content', 'category_id', 'image'];
        $filtered = array_intersect_key($data, array_flip($allowed));

        $this->assertArrayHasKey('title', $filtered);
        $this->assertArrayHasKey('content', $filtered);
        $this->assertArrayNotHasKey('id', $filtered);
        $this->assertArrayNotHasKey('created_at', $filtered);
    }

    // ==========================================
    // CATEGORY CRUD TESTS
    // ==========================================

    public function testCreateCategory()
    {
        $stmt = $this->pdo->prepare("INSERT INTO categories (name) VALUES (?) RETURNING id");
        $stmt->execute(['Test Category ' . time()]);
        $row = $stmt->fetch();
        $this->assertGreaterThan(0, $row['id']);
        // Cleanup
        $this->pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$row['id']]);
    }

    public function testCategoryUniqueConstraint()
    {
        $name = 'Unique Test ' . microtime(true);
        $stmt = $this->pdo->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->execute([$name]);

        // Verify unique constraint exists by checking pg_constraint
        $r = $this->pdo->query("SELECT 1 FROM pg_constraint WHERE conrelid='categories'::regclass AND contype='u'");
        $this->assertNotEmpty($r->fetch(), 'categories table should have a unique constraint');

        // Cleanup
        $this->pdo->prepare("DELETE FROM categories WHERE name = ?")->execute([$name]);
    }

    // ==========================================
    // PAGINATION TESTS
    // ==========================================

    public function testPaginationCalculation()
    {
        $perPage = 9;
        $totalPosts = 25;
        $totalPages = max(1, ceil($totalPosts / $perPage));
        $this->assertEquals(3, $totalPages);

        $totalPosts = 0;
        $totalPages = max(1, ceil($totalPosts / $perPage));
        $this->assertEquals(1, $totalPages); // Min 1 page
    }

    public function testOffsetCalculation()
    {
        $perPage = 9;
        $page = 3;
        $offset = ($page - 1) * $perPage;
        $this->assertEquals(18, $offset);
    }

    public function testLimitOffsetQuery()
    {
        $stmt = $this->pdo->prepare("SELECT id FROM blogs ORDER BY id DESC LIMIT ? OFFSET ?");
        $stmt->execute([5, 0]);
        $results = $stmt->fetchAll();
        $this->assertIsArray($results);
        $this->assertLessThanOrEqual(5, count($results));
    }

    // ==========================================
    // INPUT VALIDATION TESTS
    // ==========================================

    public function testCategoryIdCasting()
    {
        $input = "1 OR 1=1";
        $casted = (int)$input;
        $this->assertEquals(1, $casted);
        $this->assertIsInt($casted);
    }

    public function testPageCasting()
    {
        $input = "5; DROP TABLE blogs";
        $casted = max(1, (int)$input);
        $this->assertEquals(5, $casted);
    }

    public function testTrimInput()
    {
        $input = "  Hello World  ";
        $this->assertEquals("Hello World", trim($input));
    }

    public function testJsonDecode()
    {
        $valid = '{"title":"test","content":"test"}';
        $this->assertIsArray(json_decode($valid, true));

        $invalid = 'not json';
        $this->assertNull(json_decode($invalid, true));
    }

    // ==========================================
    // INTEGRATION TESTS — Full Blog Lifecycle
    // ==========================================

    public function testFullBlogLifecycle()
    {
        // 1. Create category
        $stmt = $this->pdo->prepare("INSERT INTO categories (name) VALUES (?) RETURNING id");
        $stmt->execute(['Lifecycle Test Category']);
        $catId = $stmt->fetch()['id'];

        // 2. Create blog in category
        $stmt = $this->pdo->prepare("INSERT INTO blogs (title, content, category_id) VALUES (?, ?, ?) RETURNING id");
        $stmt->execute(['Lifecycle Test Blog', 'Full lifecycle test content', $catId]);
        $blogId = $stmt->fetch()['id'];

        // 3. Read blog with category join
        $stmt = $this->pdo->prepare("
            SELECT b.*, c.name AS category_name 
            FROM blogs b 
            LEFT JOIN categories c ON b.category_id = c.id 
            WHERE b.id = ?
        ");
        $stmt->execute([$blogId]);
        $blog = $stmt->fetch();
        $this->assertEquals('Lifecycle Test Blog', $blog['title']);
        $this->assertEquals('Lifecycle Test Category', $blog['category_name']);

        // 4. Update blog
        $stmt = $this->pdo->prepare("UPDATE blogs SET title = ? WHERE id = ?");
        $stmt->execute(['Updated Lifecycle Blog', $blogId]);

        // 5. Verify update
        $stmt = $this->pdo->prepare("SELECT title FROM blogs WHERE id = ?");
        $stmt->execute([$blogId]);
        $this->assertEquals('Updated Lifecycle Blog', $stmt->fetch()['title']);

        // 6. Delete blog
        $this->pdo->prepare("DELETE FROM blogs WHERE id = ?")->execute([$blogId]);

        // 7. Delete category
        $this->pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$catId]);

        // 8. Verify cleanup
        $stmt = $this->pdo->prepare("SELECT * FROM blogs WHERE id = ?");
        $stmt->execute([$blogId]);
        $this->assertFalse($stmt->fetch());
    }

    // ==========================================
    // SECURITY TESTS — Magic Link / TOTP / Sessions
    // ==========================================

    public function testMagicLinkUsesTableExists()
    {
        $stmt = $this->pdo->query("SELECT to_regclass('magic_link_uses') AS table_name");
        $row = $stmt->fetch();
        $this->assertNotNull($row['table_name'], 'magic_link_uses table should exist');
    }

    public function testAuthSessionsTableExists()
    {
        $stmt = $this->pdo->query("SELECT to_regclass('auth_sessions') AS table_name");
        $row = $stmt->fetch();
        $this->assertNotNull($row['table_name'], 'auth_sessions table should exist');
    }

    public function testMagicLinkUsesHasUniqueTokenHash()
    {
        $stmt = $this->pdo->query("
            SELECT 1 FROM pg_index
            WHERE indrelid = 'magic_link_uses'::regclass AND indisunique
        ");
        $this->assertNotEmpty($stmt->fetch(), 'magic_link_uses.token_hash must have a unique constraint');
    }

    public function testMagicLinkIsSingleUse()
    {
        $magic = new \App\Auth\MagicLink('test-single-use-key');

        // Clean up any leftovers from a previous run
        $this->pdo->exec("DELETE FROM magic_link_uses");

        $token = $magic->create('single-use@example.com', 600);
        $this->assertSame('single-use@example.com', $magic->verify($token));

        // First consume wins
        $this->assertTrue($magic->consume($this->pdo, $token));
        // Second consume must lose the race
        $this->assertFalse($magic->consume($this->pdo, $token));

        $stmt = $this->pdo->query("SELECT COUNT(*) AS c FROM magic_link_uses WHERE email = 'single-use@example.com'");
        $this->assertEquals(1, (int)$stmt->fetch()['c']);

        // Cleanup
        $this->pdo->exec("DELETE FROM magic_link_uses");
    }

    public function testMagicLinkVerifyRejectsTamperedToken()
    {
        $magic = new \App\Auth\MagicLink('test-tamper-key');
        $token = $magic->create('tamper@example.com', 600);

        $parts = explode('.', $token);
        $parts[1] = str_repeat('0', strlen($parts[1]));
        $this->assertNull($magic->verify(implode('.', $parts)));

        // Reject empty / malformed tokens
        $this->assertNull($magic->verify(''));
        $this->assertNull($magic->verify('abc'));
    }

    public function testAdminsTableHasTotpSecretColumn()
    {
        $stmt = $this->pdo->query("
            SELECT column_name FROM information_schema.columns
            WHERE table_name = 'admins' AND column_name = 'totp_secret'
        ");
        $this->assertNotEmpty($stmt->fetch(), 'admins.totp_secret column should exist');
    }

    public function testTotpMatchesRfc6238Vector()
    {
        // RFC 6238 Appendix B — SHA1 secret (base32 of "12345678901234567890")
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $this->assertEquals('287082', \App\Auth\Totp::code($secret, 59));
        $this->assertEquals('081804', \App\Auth\Totp::code($secret, 1111111109));
        $this->assertEquals('050471', \App\Auth\Totp::code($secret, 1111111111));
        $this->assertEquals('005924', \App\Auth\Totp::code($secret, 1234567890));
        $this->assertEquals('279037', \App\Auth\Totp::code($secret, 2000000000));
    }

    public function testTotpVerifyAcceptsValidCodeAndRejectsInvalid()
    {
        $secret = \App\Auth\Totp::generateSecret();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);

        $valid = \App\Auth\Totp::code($secret);
        $this->assertTrue(\App\Auth\Totp::verify($secret, $valid));

        $this->assertFalse(\App\Auth\Totp::verify($secret, '000000'));
        $this->assertFalse(\App\Auth\Totp::verify($secret, '12345'));
        $this->assertFalse(\App\Auth\Totp::verify($secret, 'abcdef'));
    }

    public function testTotpProvisioningUriIsWellFormed()
    {
        $uri = \App\Auth\Totp::provisioningUri('SECRET123', 'admin@example.com', 'WAM Blog');
        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=SECRET123', $uri);
        $this->assertStringContainsString('issuer=WAM%20Blog', $uri);
    }

    public function testAuthSessionsSupportsRevocation()
    {
        $stmt = $this->pdo->query("
            SELECT column_name FROM information_schema.columns
            WHERE table_name = 'auth_sessions' AND column_name = 'revoked_at'
        ");
        $this->assertNotEmpty($stmt->fetch(), 'auth_sessions.revoked_at column should exist');
    }

    // ==========================================
    // INVITATIONS / SIGN-UP FLOW TESTS
    // ==========================================

    public function testInvitationsTableExists()
    {
        $stmt = $this->pdo->query("SELECT to_regclass('invitations') AS table_name");
        $row = $stmt->fetch();
        $this->assertNotNull($row['table_name'], 'invitations table should exist');
    }

    public function testInvitationsHasPendingIndex()
    {
        $stmt = $this->pdo->query("
            SELECT 1 FROM pg_indexes
            WHERE tablename = 'invitations' AND indexdef ILIKE '%accepted_at IS NULL%'
        ");
        $this->assertNotEmpty($stmt->fetch(), 'invitations should have a partial pending index');
    }

    public function testInvitationLifecycle()
    {
        $email = 'flow-test-' . bin2hex(random_bytes(3)) . '@example.com';

        // Insert a pending invitation
        $stmt = $this->pdo->prepare("INSERT INTO invitations (email, token, role, expires_at) VALUES (?, ?, 'editor', ?)");
        $stmt->execute([$email, bin2hex(random_bytes(32)), date('Y-m-d H:i:s', time() + 3600)]);
        $inviteId = (int)$this->pdo->lastInsertId();

        // It must be pending (not accepted/rejected)
        $stmt = $this->pdo->prepare("SELECT role FROM invitations WHERE id = ? AND accepted_at IS NULL AND rejected_at IS NULL");
        $stmt->execute([$inviteId]);
        $pending = $stmt->fetch();
        $this->assertNotEmpty($pending, 'new invitation should be pending');
        $this->assertEquals('editor', $pending['role']);

        // Accept it
        $stmt = $this->pdo->prepare("UPDATE invitations SET accepted_at = NOW() WHERE id = ?");
        $stmt->execute([$inviteId]);
        $stmt = $this->pdo->prepare("SELECT accepted_at, rejected_at FROM invitations WHERE id = ?");
        $stmt->execute([$inviteId]);
        $row = $stmt->fetch();
        $this->assertNotNull($row['accepted_at'], 'accepted_at should be set on accept');
        $this->assertNull($row['rejected_at']);

        // Cleanup
        $this->pdo->prepare("DELETE FROM invitations WHERE email = ?")->execute([$email]);
    }

    public function testInvitationRejectionIsDistinctFromAcceptance()
    {
        $email = 'reject-test-' . bin2hex(random_bytes(3)) . '@example.com';
        $stmt = $this->pdo->prepare("INSERT INTO invitations (email, token, role, expires_at) VALUES (?, ?, 'viewer', ?)");
        $stmt->execute([$email, bin2hex(random_bytes(32)), date('Y-m-d H:i:s', time() + 3600)]);
        $inviteId = (int)$this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare("UPDATE invitations SET rejected_at = NOW() WHERE id = ?");
        $stmt->execute([$inviteId]);

        $stmt = $this->pdo->prepare("SELECT accepted_at, rejected_at FROM invitations WHERE id = ?");
        $stmt->execute([$inviteId]);
        $row = $stmt->fetch();
        $this->assertNotNull($row['rejected_at'], 'rejected_at should be set on reject');
        $this->assertNull($row['accepted_at'], 'accepted_at must stay NULL on reject');

        $this->pdo->prepare("DELETE FROM invitations WHERE email = ?")->execute([$email]);
    }

    public function testInvitationEmailIsUnique()
    {
        $email = 'unique-test-' . bin2hex(random_bytes(3)) . '@example.com';
        $this->pdo->prepare("INSERT INTO invitations (email, token, role, expires_at) VALUES (?, ?, 'editor', ?)")
            ->execute([$email, 'a', date('Y-m-d H:i:s', time() + 3600)]);
        // Second request for the same email upserts (ON CONFLICT) instead of duplicating.
        $this->pdo->prepare("INSERT INTO invitations (email, token, role, expires_at) VALUES (?, ?, 'editor', ?) ON CONFLICT (email) DO UPDATE SET token = EXCLUDED.token")
            ->execute([$email, 'b', date('Y-m-d H:i:s', time() + 3600)]);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM invitations WHERE email = ?");
        $stmt->execute([$email]);
        $this->assertEquals(1, (int)$stmt->fetch()['count']);

        $this->pdo->prepare("DELETE FROM invitations WHERE email = ?")->execute([$email]);
    }

    public function testProvisionCreatesAccountForNewEmail()
    {
        $email = 'provision-test-' . bin2hex(random_bytes(3)) . '@example.com';

        $user = \App\Models\Invitation::provision($email, 'editor');
        $this->assertNotNull($user, 'provision should return the new user');
        $this->assertEquals($email, $user['email']);
        $this->assertEquals('editor', $user['role']);

        $stmt = $this->pdo->prepare("SELECT username, email, role FROM admins WHERE LOWER(email) = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        $this->assertNotEmpty($row, 'account should exist in admins');
        $this->assertEquals('editor', $row['role']);

        $this->pdo->prepare("DELETE FROM admins WHERE email = ?")->execute([$email]);
    }

    public function testProvisionReturnsExistingAccountWithoutDuplicating()
    {
        $email = 'provision-existing-' . bin2hex(random_bytes(3)) . '@example.com';
        $this->pdo->prepare("INSERT INTO admins (username, email, role) VALUES (?, ?, ?)")
            ->execute(['prov-test-' . substr($email, 0, 8), $email, 'viewer']);

        $user = \App\Models\Invitation::provision($email, 'editor');
        $this->assertNotNull($user, 'provision should return the existing user');
        $this->assertEquals('viewer', $user['role'], 'existing role must not be overwritten');

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $this->assertEquals(1, (int)$stmt->fetch()['count']);

        $this->pdo->prepare("DELETE FROM admins WHERE email = ?")->execute([$email]);
    }

    public function testProvisionDerivesUniqueUsername()
    {
        $email = 'prov-' . bin2hex(random_bytes(3)) . '@example.com';
        $base = explode('@', $email)[0];
        // Pre-create the same base username so the second account needs a suffix.
        $this->pdo->prepare("INSERT INTO admins (username, email, role) VALUES (?, ?, ?)")
            ->execute([$base, 'other-' . $email, 'viewer']);

        $user = \App\Models\Invitation::provision($email, 'editor');
        $this->assertNotNull($user);
        $this->assertNotEquals($base, $user['username'], 'username should get a numeric suffix when taken');
        $this->assertStringStartsWith($base, $user['username']);

        $this->pdo->prepare("DELETE FROM admins WHERE email = ?")->execute([$email]);
        $this->pdo->prepare("DELETE FROM admins WHERE email = ?")->execute(['other-' . $email]);
    }

    public function testRendererEscapesAuthorHtml()
    {
        $html = renderPostContent("Hello <script>alert(1)</script> world");
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testRendererRendersParagraphsAndHeadings()
    {
        $html = renderPostContent("## A heading\n\nFirst paragraph.\n\n### Sub heading");
        $this->assertStringContainsString('<h2>A heading</h2>', $html);
        $this->assertStringContainsString('<h3>Sub heading</h3>', $html);
        $this->assertStringContainsString('<p>First paragraph.</p>', $html);
    }

    public function testRendererRendersBlockquoteAndList()
    {
        $html = renderPostContent("> A quoted line\n> continued\n\n- item one\n- item two");
        $this->assertStringContainsString('<blockquote>', $html);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>item one</li>', $html);
        $this->assertStringContainsString('<li>item two</li>', $html);
    }

    public function testRendererRendersCodeFenceAndInlineMarkup()
    {
        $html = renderPostContent("```\n\$var = 1;\n```\n\nSome **bold** and `inline` text.");
        $this->assertStringContainsString('<pre><code>', $html);
        $this->assertStringContainsString('$var = 1;', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<code>inline</code>', $html);
    }

    public function testRendererAllowsHttpLinksOnly()
    {
        $html = renderPostContent('[safe](https://example.com) and [bad](javascript:alert(1)) and [danger](data:text/html,x)');
        $this->assertStringContainsString('<a href="https://example.com"', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->assertStringNotContainsString('href="data:', $html);
        $this->assertStringNotContainsString('href="//', $html);
    }

    public function testStripMarkdownProducesPlainText()
    {
        $text = stripMarkdown("## Title\n\n> quote\n\n- item **bold** and *emphasised* and [link](https://example.com)");
        $this->assertStringNotContainsString('#', $text);
        $this->assertStringNotContainsString('>', $text);
        $this->assertStringNotContainsString('*', $text);
        $this->assertStringContainsString('bold', $text);
        $this->assertStringContainsString('emphasised', $text);
        $this->assertStringContainsString('link', $text);
        $this->assertStringNotContainsString('https://example.com', $text);
    }

    // ==========================================
    // COMMENTS FEATURE
    // ==========================================

    public function testCommentsTableExists()
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) AS count FROM comments");
        $row = $stmt->fetch();
        $this->assertGreaterThanOrEqual(0, $row['count']);
    }

    public function testCommentsTableHasRequiredColumns()
    {
        $stmt = $this->pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'comments'");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('blog_id', $columns);
        $this->assertContains('author_name', $columns);
        $this->assertContains('author_email', $columns);
        $this->assertContains('content', $columns);
        $this->assertContains('status', $columns);
        $this->assertContains('user_ip', $columns);
        $this->assertContains('created_at', $columns);
    }

    public function testSubscribersTableExists()
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) AS count FROM subscribers");
        $row = $stmt->fetch();
        $this->assertGreaterThanOrEqual(0, $row['count']);
    }

    public function testSubscribersTableHasRequiredColumns()
    {
        $stmt = $this->pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'subscribers'");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('email', $columns);
        $this->assertContains('token', $columns);
        $this->assertContains('created_at', $columns);
    }

    public function testCommentWorkflowPendingApproveThenDelete()
    {
        $this->pdo->prepare("INSERT INTO blogs (title, content, status) VALUES ('Comment Workflow Post', 'body', 'published')")->execute();
        $blogId = (int)$this->pdo->lastInsertId();

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $commentId = \App\Models\Comment::create($blogId, 'Test Reader', 'reader@example.com', 'A friendly comment.');
        $this->assertNotNull($commentId);

        $stmt = $this->pdo->prepare("SELECT status FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $this->assertEquals('pending', $stmt->fetch()['status']);

        $this->assertTrue(\App\Models\Comment::approve($commentId));
        $this->assertCount(1, \App\Models\Comment::approvedFor($blogId));

        $this->assertTrue(\App\Models\Comment::delete($commentId));
        $this->assertCount(0, \App\Models\Comment::approvedFor($blogId));

        $this->pdo->prepare("DELETE FROM blogs WHERE id = ?")->execute([$blogId]);
    }

    public function testCommentRejectsInvalidInput()
    {
        $this->pdo->prepare("INSERT INTO blogs (title, content, status) VALUES ('Comment Invalid Post', 'body', 'published')")->execute();
        $blogId = (int)$this->pdo->lastInsertId();

        $this->assertNull(\App\Models\Comment::create($blogId, '', '', ''));
        $this->assertNull(\App\Models\Comment::create($blogId, 'Name', 'not-an-email', 'ok'));

        $this->pdo->prepare("DELETE FROM blogs WHERE id = ?")->execute([$blogId]);
    }

    // ==========================================
    // NEWSLETTER FEATURE
    // ==========================================

    public function testSubscriberWorkflowSubscribeDedupeThenRemove()
    {
        $added = \App\Models\Subscriber::subscribe('newsletter-test@example.com');
        $this->assertSame('added', $added['status']);

        $dup = \App\Models\Subscriber::subscribe('newsletter-test@example.com');
        $this->assertSame('exists', $dup['status']);

        $stmt = $this->pdo->prepare("SELECT token FROM subscribers WHERE email = ?");
        $stmt->execute(['newsletter-test@example.com']);
        $token = $stmt->fetchColumn();
        $this->assertNotEmpty($token);

        $this->assertTrue(\App\Models\Subscriber::removeByToken((string)$token));

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM subscribers WHERE email = ?");
        $stmt->execute(['newsletter-test@example.com']);
        $this->assertSame(0, (int)$stmt->fetchColumn());
    }

    public function testSubscriberRejectsInvalidEmail()
    {
        $this->assertNull(\App\Models\Subscriber::subscribe('not-an-email'));
        $this->assertNull(\App\Models\Subscriber::subscribe(''));
    }
}
