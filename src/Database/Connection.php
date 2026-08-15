<?php

namespace App\Database;

use App\Support\Env;
use PDO;
use PDOException;

class Connection
{
    private static ?PDO $pdo = null;

    public static function getInstance(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::createConnection();
        }
        return self::$pdo;
    }

    private static function createConnection(): PDO
    {
        $dbUrlRaw = Env::get('DATABASE_URL');
        $dbHost = Env::get('DB_HOST');
        $dbPort = Env::get('DB_PORT');
        $dbName = Env::get('DB_DATABASE');
        $dbUser = Env::get('DB_USERNAME');
        $dbPass = Env::get('DB_PASSWORD');

        if ($dbUrlRaw) {
            $dbUrl = parse_url($dbUrlRaw);
            $host = $dbUrl['host'] ?? '127.0.0.1';
            $port = $dbUrl['port'] ?? '5432';
            $dbname = ltrim($dbUrl['path'], '/');
            $username = $dbUrl['user'] ?? 'postgres';
            $password = $dbUrl['pass'] ?? '';
            $sslmode = 'require';
            $channelBinding = null;
            // Honour query-string params such as sslmode and channel_binding
            // (Neon recommends channel_binding=require for SCRAM connections).
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
            $sslmode = Env::get('DB_SSLMODE', 'require');
            $channelBinding = null;
        } else {
            $host = $dbHost ?? '127.0.0.1';
            $port = $dbPort ?? '5432';
            $dbname = $dbName ?? 'mizzle_backend';
            $username = $dbUser ?? 'postgres';
            $password = $dbPass ?? '';
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
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->query("SELECT 1");
            return $pdo;
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed. Please try again later.']));
        }
    }
}
