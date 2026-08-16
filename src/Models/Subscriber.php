<?php

namespace App\Models;

use App\Database\Connection;
use App\Mail\Mailer;
use App\Support\Env;

class Subscriber
{
    /**
     * Subscribe an email. Returns ['status' => 'added'|'exists', 'id' => int],
     * or null if the email is invalid.
     */
    public static function subscribe(string $email): ?array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $pdo = Connection::getInstance();

        $stmt = $pdo->prepare("SELECT id FROM subscribers WHERE LOWER(email) = ? LIMIT 1");
        $stmt->execute([$email]);
        $existing = $stmt->fetch();
        if ($existing) {
            return ['status' => 'exists', 'id' => (int)$existing['id']];
        }

        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("INSERT INTO subscribers (email, token) VALUES (?, ?)");
        $stmt->execute([$email, $token]);

        $id = (int)$pdo->lastInsertId();
        ActivityLog::log('subscriber_added', 'subscriber', $id, ['email' => $email]);

        return ['status' => 'added', 'id' => $id];
    }

    /**
     * All subscribers, newest first.
     */
    public static function all(): array
    {
        $pdo = Connection::getInstance();
        return $pdo->query("SELECT id, email, created_at FROM subscribers ORDER BY id DESC")->fetchAll();
    }

    public static function count(): int
    {
        $pdo = Connection::getInstance();
        return (int)$pdo->query("SELECT COUNT(*) FROM subscribers")->fetchColumn();
    }

    /**
     * Remove a subscriber by its unguessable token (used by the unsubscribe
     * link). Returns true when a row was removed.
     */
    public static function removeByToken(string $token): bool
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("DELETE FROM subscribers WHERE token = ?");
        $stmt->execute([$token]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            ActivityLog::log('subscriber_removed', 'subscriber');
        }
        return $ok;
    }

    /**
     * Admin removal by id.
     */
    public static function removeById(int $id): bool
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("DELETE FROM subscribers WHERE id = ?");
        $stmt->execute([$id]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            ActivityLog::log('subscriber_removed', 'subscriber', $id);
        }
        return $ok;
    }

    public static function unsubscribeUrl(string $token): string
    {
        $appUrl = rtrim((string)(Env::get('APP_URL') ?: 'https://php-blog-backend.onrender.com'), '/');
        return $appUrl . '/unsubscribe.php?token=' . urlencode($token);
    }

    /**
     * Email every subscriber about a newly published post. Best-effort:
     * failures are logged per recipient and never break publishing.
     * Returns the number of recipients the send was attempted for.
     */
    public static function notifyNewPost(array $post): int
    {
        $pdo = Connection::getInstance();
        $rows = $pdo->query("SELECT id, email, token FROM subscribers")->fetchAll();
        if ($rows === []) {
            return 0;
        }

        $title = $post['title'] ?? 'New post';
        $postId = (int)($post['id'] ?? 0);
        $appUrl = rtrim((string)(Env::get('APP_URL') ?: 'https://php-blog-backend.onrender.com'), '/');
        $postUrl = $appUrl . '/post.php?id=' . $postId;
        $excerpt = trim(preg_replace('/\s+/', ' ', (string)($post['content'] ?? '')));
        $excerpt = mb_strimwidth($excerpt, 0, 220, '…');

        $html = '<!DOCTYPE html><html><body style="font-family:Georgia,serif;background:#FBF9F1;color:#2E2910;padding:24px;">'
            . '<h1 style="color:#2C5745;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
            . '<p style="color:#5C5340;">' . htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<a href="' . htmlspecialchars($postUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;margin:16px auto;padding:12px 24px;background:#2C5745;color:#fff;text-decoration:none;border-radius:8px;">Read the story</a>'
            . '</body></html>';

        $mailer = new Mailer();
        $sent = 0;
        foreach ($rows as $row) {
            try {
                $mailer->send(
                    $row['email'],
                    'New on WAM Blog: ' . $title,
                    $html,
                    "New post on WAM Blog: $title\n\n$postUrl"
                );
                $sent++;
            } catch (\Throwable $e) {
                error_log("Newsletter send failed for {$row['email']}: " . $e->getMessage());
            }
        }

        ActivityLog::log('newsletter_sent', 'blog', $postId, ['title' => $title, 'recipients' => $sent]);
        return $sent;
    }
}