<?php

namespace App\Middleware;

use PDO;

/**
 * DB-backed IP rate limiter.
 *
 * Replaces session-based counters: sessions can be cleared by the attacker
 * (cookies), so attempt state lives in Postgres keyed on a SHA-256 hash of the
 * client IP. Buckets: 'magic', 'login', '2fa'.
 */
class RateLimit
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function clientIp(): string
    {
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($parts[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Record one attempt for this bucket/IP. Returns the updated attempt count.
     */
    public function hit(string $bucket, string $ip, int $windowSeconds): int
    {
        $key = hash('sha256', $bucket . '|' . $ip);
        return $this->record($bucket, $key, $windowSeconds);
    }

    /**
     * True if this bucket/IP has hit or exceeded maxAttempts within the window.
     */
    public function isBlocked(string $bucket, string $ip, int $maxAttempts, int $windowSeconds): bool
    {
        $key = hash('sha256', $bucket . '|' . $ip);
        return $this->check($bucket, $key, $maxAttempts, $windowSeconds);
    }

    /**
     * Record an attempt keyed on an arbitrary identifier (e.g. an email address),
     * hashed so raw addresses are never stored. Returns the updated count.
     */
    public function hitKey(string $bucket, string $identifier, int $windowSeconds): int
    {
        $key = hash('sha256', $bucket . '|' . strtolower(trim($identifier)));
        return $this->record($bucket, $key, $windowSeconds);
    }

    /**
     * True if an arbitrary identifier (e.g. email) has hit or exceeded maxAttempts.
     */
    public function isBlockedKey(string $bucket, string $identifier, int $maxAttempts, int $windowSeconds): bool
    {
        $key = hash('sha256', $bucket . '|' . strtolower(trim($identifier)));
        return $this->check($bucket, $key, $maxAttempts, $windowSeconds);
    }

    private function record(string $bucket, string $key, int $windowSeconds): int
    {
        // UPSERT with window rollover: if the current window has elapsed, the
        // counter resets to 1; otherwise it increments. Atomic via ON CONFLICT.
        $stmt = $this->pdo->prepare("
            INSERT INTO login_rate_limits (bucket, ip_hash, attempt_count, window_start)
            VALUES (?, ?, 1, NOW())
            ON CONFLICT (bucket, ip_hash) DO UPDATE SET
                attempt_count = CASE
                    WHEN login_rate_limits.window_start < NOW() - (? * INTERVAL '1 second')
                        THEN 1
                    ELSE login_rate_limits.attempt_count + 1
                END,
                window_start = CASE
                    WHEN login_rate_limits.window_start < NOW() - (? * INTERVAL '1 second')
                        THEN NOW()
                    ELSE login_rate_limits.window_start
                END
            RETURNING attempt_count
        ");
        $stmt->execute([$bucket, $key, $windowSeconds, $windowSeconds]);
        return (int)$stmt->fetchColumn();
    }

    private function check(string $bucket, string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT attempt_count, window_start
            FROM login_rate_limits
            WHERE bucket = ? AND ip_hash = ?
        ");
        $stmt->execute([$bucket, $key]);
        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }

        $windowExpired = (strtotime($row['window_start']) + $windowSeconds) <= time();
        return !$windowExpired && (int)$row['attempt_count'] >= $maxAttempts;
    }

    /**
     * Clear the counter for this bucket/IP (e.g. after a successful login).
     */
    public function reset(string $bucket, string $ip): void
    {
        $key = hash('sha256', $bucket . '|' . $ip);
        $stmt = $this->pdo->prepare("DELETE FROM login_rate_limits WHERE bucket = ? AND ip_hash = ?");
        $stmt->execute([$bucket, $key]);
    }
}