<?php
// connect.php - PDO PostgreSQL connection using .env

// Load environment variables from .env
$env = [];
$lines = file(__DIR__.'/../.env');
foreach ($lines as $line) {
    // Skip comments and empty lines
    if (trim($line) === '' || trim($line)[0] === '#') continue;
    
    // Parse KEY=VALUE
    $parts = explode('=', $line, 2);
    if (count($parts) === 2) {
        $key = trim($parts[0]);
        $value = trim($parts[1]);
        // Remove surrounding quotes if present
        if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value)-1] === '"') {
            $value = substr($value, 1, -1);
        }
        $env[$key] = $value;
    }
}

// Use environment variables (from .env) - PostgreSQL configured
$host = $env['DB_HOST'] ?? '127.0.0.1';
$dbname = $env['DB_DATABASE'] ?? 'mizzle_backend';
$username = $env['DB_USERNAME'] ?? 'postgres';
$password = $env['DB_PASSWORD'] ?: '';

$connection = $env['DB_CONNECTION'] ?? 'pgsql';

// Build PostgreSQL DSN
$dsn = "pgsql:host=$host;port=5432;dbname=$dbname";

// Create PDO connection
// Exception mode ensures errors throw exceptions instead of warnings
try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false, // Use native prepared statements
    ]);
    
    // Test connection with a simple query
    $pdo->query("SELECT 1");
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

return $pdo;