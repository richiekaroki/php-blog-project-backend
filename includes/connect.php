<?php
// connect.php - PDO PostgreSQL connection

// Support both Render DATABASE_URL and local .env
if (isset($_ENV['DATABASE_URL']) || isset(getenv()['DATABASE_URL'])) {
    // Render provides DATABASE_URL: postgres://user:password@host:port/dbname
    $dbUrl = parse_url(getenv('DATABASE_URL') ?: $_ENV['DATABASE_URL']);
    $host = $dbUrl['host'] ?? '127.0.0.1';
    $port = $dbUrl['port'] ?? '5432';
    $dbname = ltrim($dbUrl['path'], '/');
    $username = $dbUrl['user'] ?? 'postgres';
    $password = $dbUrl['pass'] ?? '';
} else {
    // Load from .env (local development)
    $env = [];
    $envFile = __DIR__.'/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile);
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
    }
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $port = $env['DB_PORT'] ?? '5432';
    $dbname = $env['DB_DATABASE'] ?? 'mizzle_backend';
    $username = $env['DB_USERNAME'] ?? 'postgres';
    $password = $env['DB_PASSWORD'] ?? '';
}

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

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
