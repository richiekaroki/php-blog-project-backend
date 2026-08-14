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

    /**
     * Auto-provision an account for an email that does not have one yet.
     * Returns the user row (existing or newly created) on success, null on error.
     * New accounts default to the editor role; the username is derived from
     * the email local-part and made unique with a numeric suffix.
     */
    public static function provision(string $email, string $role = 'editor'): ?array
    {
        $pdo = Connection::getInstance();

        $stmt = $pdo->prepare("SELECT id, username, email, role FROM admins WHERE LOWER(email) = ? LIMIT 1");
        $stmt->execute([$email]);
        $existing = $stmt->fetch();
        if ($existing) {
            return $existing;
        }

        // Derive a unique username from the email local part.
        $base = strtolower(preg_replace('/[^a-z0-9_.-]/i', '', explode('@', $email)[0]));
        if ($base === '' || strlen($base) > 50) {
            $base = 'user' . substr(bin2hex(random_bytes(4)), 0, 6);
        }
        $username = $base;
        $suffix = 1;
        while (true) {
            $dup = $pdo->prepare("SELECT id FROM admins WHERE LOWER(username) = ? LIMIT 1");
            $dup->execute([$username]);
            if (!$dup->fetch()) {
                break;
            }
            $username = $base . ($suffix++);
        }

        try {
            $ins = $pdo->prepare("INSERT INTO admins (username, email, role) VALUES (?, ?, ?)");
            $ins->execute([$username, $email, $role]);
            $userId = (int)$pdo->lastInsertId();

            ActivityLog::log('signup_auto', 'admin', $userId, ['email' => $email, 'role' => $role]);

            self::notifyAdmins($email, $role, $username);

            return ['id' => $userId, 'username' => $username, 'email' => $email, 'role' => $role];
        } catch (\Throwable $e) {
            error_log('Invitation::provision failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Notify the admin(s) in ADMIN_NOTIFICATION_EMAILS (comma-separated) that a
     * new account was auto-provisioned. Failures are logged but never break the
     * provisioning flow, so a mail hiccup cannot block sign-in.
     */
    private static function notifyAdmins(string $email, string $role, string $username): void
    {
        $recipients = array_filter(array_map('trim', explode(',', (string)(getenv('ADMIN_NOTIFICATION_EMAILS') ?: ''))));
        if ($recipients === []) {
            return;
        }

        try {
            $mailer = new \App\Mail\Mailer();
            $html = '<!DOCTYPE html><html><body style="font-family:Georgia,serif;background:#FBF9F1;color:#2E2910;padding:24px;">'
                . '<h1 style="color:#2C5745;">New account auto-provisioned</h1>'
                . '<p>A new editor account was created automatically via magic-link sign-in:</p>'
                . '<ul><li><strong>Email:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</li>'
                . '<li><strong>Username:</strong> ' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</li>'
                . '<li><strong>Role:</strong> ' . htmlspecialchars($role, ENT_QUOTES, 'UTF-8') . '</li></ul>'
                . '<p style="color:#5C5340;font-size:14px;">You can review or change roles in the admin area.</p>'
                . '</body></html>';

            foreach ($recipients as $to) {
                $mailer->send(
                    $to,
                    'New WAM Blog account: ' . $username,
                    $html,
                    "New editor account auto-provisioned:\n\nEmail: $email\nUsername: $username\nRole: $role"
                );
            }

            ActivityLog::log('admin_notified', 'admin', null, ['for' => $email, 'recipients' => $recipients]);
        } catch (\Throwable $e) {
            error_log('Admin notification failed: ' . $e->getMessage());
        }
    }
}