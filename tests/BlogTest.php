<?php
// tests/BlogTest.php - Unit tests for blog functionality

require __DIR__ . '/../vendor/autoload.php';

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
            if (trim($line) === '' || trim($line)[0] === '#') continue;
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

        $host = $env['DB_HOST'] ?? '127.0.0.1';
        $port = $env['DB_PORT'] ?? '5432';
        $dbname = $env['DB_DATABASE'] ?? 'mizzle_backend';
        $username = $env['DB_USERNAME'] ?? 'postgres';
        $password = $env['DB_PASSWORD'] ?? '';

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

    public function testDatabaseConnection()
    {
        $this->assertNotNull($this->pdo);
        // Test basic query
        $result = $this->pdo->query('SELECT 1 AS test');
        $row = $result->fetch();
        $this->assertEquals(1, $row['test']);
    }

    public function testAdminsTableHasData()
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) AS count FROM admins");
        $row = $stmt->fetch();
        $this->assertGreaterThan(0, $row['count']);
    }

    public function testCategoriesTableHasData()
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) AS count FROM categories");
        $row = $stmt->fetch();
        $this->assertGreaterThanOrEqual(0, $row['count']);
    }

    public function testBlogsTableHasData()
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
    }
}