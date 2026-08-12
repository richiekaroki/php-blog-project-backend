<?php
// connect.php - PDO PostgreSQL connection (supports Neon, Render, local)

// Check for DATABASE_URL (Neon, Render, or any cloud provider)
$dbUrl = null;
if (isset($_ENV['DATABASE_URL'])) {
    $dbUrl = parse_url($_ENV['DATABASE_URL']);
} elseif (getenv('DATABASE_URL')) {
    $dbUrl = parse_url(getenv('DATABASE_URL'));
}

if ($dbUrl) {
    $host = $dbUrl['host'] ?? '127.0.0.1';
    $port = $dbUrl['port'] ?? '5432';
    $dbname = ltrim($dbUrl['path'], '/');
    $username = $dbUrl['user'] ?? 'postgres';
    $password = $dbUrl['pass'] ?? '';
    // Neon requires SSL
    $sslmode = 'require';
} else {
    // Local .env
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
