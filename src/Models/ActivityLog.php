<?php

namespace App\Models;

use App\Database\Connection;

class ActivityLog
{
    private static $pdo;

    private static function getPdo()
    {
        if (self::$pdo === null) {
            self::$pdo = Connection::getInstance();
        }
        return self::$pdo;
    }

    public static function log(string $action, string $entityType, ?int $entityId = null, ?array $details = null): void
    {
        $pdo = self::getPdo();
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (action, entity_type, entity_id, details, user_ip, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $action,
            $entityType,
            $entityId,
            $details ? json_encode($details) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }

    public static function getRecent(int $limit = 50): array
    {
        $pdo = self::getPdo();
        $stmt = $pdo->prepare("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}
