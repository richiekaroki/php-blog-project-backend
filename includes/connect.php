<?php
// connect.php - PDO PostgreSQL connection

// Try multiple ways to get DATABASE_URL (Docker, PHP-FPM, CLI)
$dbUrlRaw = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?: null;

// Also check if individual DB_* vars are set (Render/Neon env vars)
$dbHost = $_SERVER['DB_HOST'] ?? $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: null;
$dbPort = $_SERVER['DB_PORT'] ?? $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: null;
$dbName = $_SERVER['DB_DATABASE'] ?? $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: null;
$dbUser = $_SERVER['DB_USERNAME'] ?? $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: null;
$dbPass = $_SERVER['DB_PASSWORD'] ?? $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: null;

if ($dbUrlRaw) {
    // Parse DATABASE_URL: postgres://user:password@host:port/dbname?sslmode=require
    $dbUrl = parse_url($dbUrlRaw);
    $host = $dbUrl['host'] ?? '127.0.0.1';
    $port = $dbUrl['port'] ?? '5432';
    $dbname = ltrim($dbUrl['path'], '/');
    $username = $dbUrl['user'] ?? 'postgres';
    $password = $dbUrl['pass'] ?? '';
    $sslmode = 'require';
} elseif ($dbHost) {
    // Individual DB_* vars set
    $host = $dbHost;
    $port = $dbPort ?? '5432';
    $dbname = $dbName ?? 'mizzle_backend';
    $username = $dbUser ?? 'postgres';
    $password = $dbPass ?? '';
    $sslmode = 'require';
} else {
    // Local .env fallback
    $env = [];
    $envFile = __DIR__.'/../.env';
    if (file_exists($envFile)) {
        foreach (file($envFile) as $line) {
            if (trim($line) === '' || trim($line)[0] === '#') continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $env[trim($parts[0])] = trim(trim($parts[1]), '"');
            }
        }
    }
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $port = $env['DB_PORT'] ?? '5432';
    $dbname = $env['DB_DATABASE'] ?? 'mizzle_backend';
    $username = $env['DB_USERNAME'] ?? 'postgres';
    $password = $env['DB_PASSWORD'] ?? '';
    $sslmode = 'prefer';
}

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=$sslmode";

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->query("SELECT 1");
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed. Please try again later.']));
}

return $pdo;
