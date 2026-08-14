<?php

namespace App\Models;

use App\Database\Connection;

class Invitation
{
    /**
     * Create or refresh a pending sign-up invitation for an email.
     * Returns the created/updated invitation row on success, false on error.
     */
    public static function request(string $email, string $role = 'editor', int $ttlSeconds = 86400): array|false
    {
        $pdo = Connection::getInstance();
        $token = bin2hex(random_bytes(32));
        $expiresAt = (new \DateTime())->modify("+$ttlSeconds seconds")->format('Y-m-d H:i:s');

        try {
            // Deactivate any previous unused invitations for this email.
            $stmt = $pdo->prepare("UPDATE invitations SET rejected_at = NOW() WHERE email = ? AND accepted_at IS NULL AND rejected_at IS NULL AND expires_at > NOW()");
            $stmt->execute([$email]);

            $stmt = $pdo->prepare("INSERT INTO invitations (email, token, role, expires_at) VALUES (?, ?, ?, ?) ON CONFLICT (email) DO UPDATE SET token = EXCLUDED.token, expires_at = EXCLUDED.expires_at, rejected_at = NULL, accepted_at = NULL");
            $stmt->execute([$email, $token, $role, $expiresAt]);

            ActivityLog::log('signup_requested', 'invitation', null, ['email' => $email]);

            return [
                'email' => $email,
                'token' => $token,
                'role' => $role,
                'expires_at' => $expiresAt,
            ];
        } catch (\Throwable $e) {
            error_log('Invitation::request failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Whether the given email already has an admin account.
     */
    public static function isAdmin(string $email): bool
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE LOWER(email) = ? LIMIT 1");
        $stmt->execute([$email]);
        return (bool)$stmt->fetch();
    }
}