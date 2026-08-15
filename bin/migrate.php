<?php
/**
 * Apply pending SQL migrations from sql/migrations/ in filename order.
 *
 * Usage:
 *   php bin/migrate.php                # apply pending migrations
 *   php bin/migrate.php --status       # list applied/pending without applying
 *   php bin/migrate.php --force        # re-run migrations (not recommended)
 *
 * Connects using DATABASE_URL (preferred) or DB_HOST/DB_PORT/DB_DATABASE/
 * DB_USERNAME/DB_PASSWORD, mirroring src/Database/Connection.php. Applied
 * migrations are recorded in the schema_migrations table so each file runs
 * exactly once per database. All statements in a file run in one transaction.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

// ---- Resolve configuration the same way Connection.php does ----
$dbUrlRaw = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?: null;
$dbHost = $_SERVER['DB_HOST'] ?? $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: null;
$dbPort = $_SERVER['DB_PORT'] ?? $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: null;
$dbName = $_SERVER['DB_DATABASE'] ?? $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: null;
$dbUser = $_SERVER['DB_USERNAME'] ?? $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: null;
$dbPass = $_SERVER['DB_PASSWORD'] ?? $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: null;

if ($dbUrlRaw) {
    $dbUrl = parse_url($dbUrlRaw);
    $host = $dbUrl['host'] ?? '127.0.0.1';
    $port = $dbUrl['port'] ?? '5432';
    $dbname = ltrim($dbUrl['path'], '/');
    $username = $dbUrl['user'] ?? 'postgres';
    $password = $dbUrl['pass'] ?? '';
    $sslmode = 'require';
    $channelBinding = null;
    if (isset($dbUrl['query'])) {
        parse_str($dbUrl['query'], $q);
        if (!empty($q['sslmode'])) $sslmode = $q['sslmode'];
        if (!empty($q['channel_binding'])) $channelBinding = $q['channel_binding'];
    }
} elseif ($dbHost) {
    $host = $dbHost;
    $port = $dbPort ?? '5432';
    $dbname = $dbName ?? 'mizzle_backend';
    $username = $dbUser ?? 'postgres';
    $password = $dbPass ?? '';
    $sslmode = 'require';
    $channelBinding = null;
} else {
    // Fall back to a local .env file (same behaviour as Connection.php).
    // Real env vars still take priority over .env values.
    $env = [];
    $envFile = dirname(__DIR__) . '/.env';
    if (file_exists($envFile)) {
        foreach (file($envFile) as $line) {
            if (trim($line) === '' || trim($line)[0] === '#') continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $env[trim($parts[0])] = trim(trim($parts[1]), '"');
            }
        }
    }
    $host = $dbHost ?? $env['DB_HOST'] ?? '127.0.0.1';
    $port = $dbPort ?? $env['DB_PORT'] ?? '5432';
    $dbname = $dbName ?? $env['DB_DATABASE'] ?? 'mizzle_backend';
    $username = $dbUser ?? $env['DB_USERNAME'] ?? 'postgres';
    $password = $dbPass ?? $env['DB_PASSWORD'] ?? '';
    $sslmode = 'prefer';
    $channelBinding = null;
}

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=$sslmode";
if (!empty($channelBinding)) {
    $dsn .= ";channel_binding=$channelBinding";
}

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

// ---- Tracking table ----
$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(255) PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT NOW()
)");

$mode = $argv[1] ?? 'migrate';
$force = in_array('--force', $argv, true);

$dir = dirname(__DIR__) . '/sql/migrations';
$files = glob($dir . '/*.sql');
if ($files === false || $files === []) {
    fwrite(STDOUT, "No migrations found in $dir\n");
    exit(0);
}
sort($files);

$applied = [];
foreach ($pdo->query("SELECT version FROM schema_migrations")->fetchAll() as $row) {
    $applied[$row['version']] = true;
}

if ($mode === '--status') {
    foreach ($files as $file) {
        $name = basename($file);
        $state = isset($applied[$name]) ? 'APPLIED  ' : 'PENDING  ';
        fwrite(STDOUT, "$state $name\n");
    }
    exit(0);
}

$ran = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (isset($applied[$name]) && !$force) {
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
        continue;
    }

    // Strip full-line and inline comments BEFORE splitting, so a semicolon
    // inside a comment cannot break statements apart.
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $sql = preg_replace('/\s+--.*$/m', '', $sql);

    $statements = array_filter(array_map('trim', explode(';', $sql)), function ($s) {
        $s = trim($s);
        return $s !== '';
    });

    try {
        $pdo->beginTransaction();
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
        $stmt = $pdo->prepare("INSERT INTO schema_migrations (version) VALUES (?) ON CONFLICT (version) DO NOTHING");
        $stmt->execute([$name]);
        $pdo->commit();
        $ran++;
        fwrite(STDOUT, "Applied $name\n");
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, "Migration $name failed: " . $e->getMessage() . "\n");
        exit(1);
    }
}

fwrite(STDOUT, $ran === 0 ? "Nothing to migrate.\n" : "Done. Applied $ran migration(s).\n");