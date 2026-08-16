<?php

namespace App\Models;

use App\Database\Connection;
use App\Mail\Mailer;
use App\Support\Env;

class Comment
{
    /**
     * Create a new (pending) comment on a blog post.
     * Returns the new comment id, or null on validation failure.
     */
    public static function create(int $blogId, string $name, string $email, string $content): ?int
    {
        $name = trim($name);
        $email = trim($email);
        $content = trim($content);

        if ($name === '' || mb_strlen($name) > 100) {
            return null;
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        if ($email !== '' && strlen($email) > 255) {
            return null;
        }
        if ($content === '') {
            return null;
        }

        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("
            INSERT INTO comments (blog_id, author_name, author_email, content, status, user_ip, created_at)
            VALUES (?, ?, ?, ?, 'pending', ?, NOW())
        ");
        $stmt->execute([
            $blogId,
            $name,
            $email !== '' ? $email : null,
            $content,
            ($_SERVER['REMOTE_ADDR'] ?? null) ?: null,
        ]);

        $id = (int)$pdo->lastInsertId();

        ActivityLog::log('comment_created', 'comment', $id, ['blog_id' => $blogId]);

        // Best-effort moderation notice to the configured admins. A mail
        // failure is logged and never blocks the comment itself.
        try {
            $recipients = array_filter(array_map('trim', explode(',', (string)(Env::get('ADMIN_NOTIFICATION_EMAILS', '') ?: ''))));
            if ($recipients !== []) {
                $mailer = new Mailer();
                $postTitle = self::postTitle($blogId);
                $html = '<!DOCTYPE html><html><body style="font-family:Georgia,serif;background:#FBF9F1;color:#2E2910;padding:24px;">'
                    . '<h1 style="color:#2C5745;">New comment awaiting moderation</h1>'
                    . '<p><strong>On:</strong> ' . htmlspecialchars($postTitle ?: "(post #$blogId)", ENT_QUOTES, 'UTF-8') . '</p>'
                    . '<p><strong>From:</strong> ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ($email !== '' ? ' (' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . ')' : '') . '</p>'
                    . '<p>' . nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8')) . '</p>'
                    . '<p style="color:#5C5340;font-size:14px;">Approve or delete it in the Comments panel.</p>'
                    . '</body></html>';
                foreach ($recipients as $to) {
                    $mailer->send($to, 'New comment on WAM Blog: ' . ($postTitle ?: 'post'), $html, "New comment on {$postTitle} from {$name}:\n\n{$content}");
                }
            }
        } catch (\Throwable $e) {
            error_log('Comment moderation notice failed: ' . $e->getMessage());
        }

        return $id;
    }

    /**
     * Approved comments for a post, oldest first.
     */
    public static function approvedFor(int $blogId): array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("
            SELECT id, author_name, content, created_at
            FROM comments
            WHERE blog_id = ? AND status = 'approved'
            ORDER BY created_at ASC, id ASC
        ");
        $stmt->execute([$blogId]);
        return $stmt->fetchAll();
    }

    /**
     * Admin listing, optionally filtered by status.
     */
    public static function list(?string $status = null, int $limit = 200): array
    {
        $pdo = Connection::getInstance();
        if ($status !== null && in_array($status, ['pending', 'approved'], true)) {
            $stmt = $pdo->prepare("
                SELECT c.*, b.title AS blog_title
                FROM comments c
                LEFT JOIN blogs b ON c.blog_id = b.id
                WHERE c.status = ?
                ORDER BY c.created_at DESC, c.id DESC
                LIMIT ?
            ");
            $stmt->execute([$status, $limit]);
        } else {
            $stmt = $pdo->prepare("
                SELECT c.*, b.title AS blog_title
                FROM comments c
                LEFT JOIN blogs b ON c.blog_id = b.id
                ORDER BY c.created_at DESC, c.id DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
        }
        return $stmt->fetchAll();
    }

    public static function countPending(): int
    {
        $pdo = Connection::getInstance();
        return (int)$pdo->query("SELECT COUNT(*) FROM comments WHERE status = 'pending'")->fetchColumn();
    }

    public static function approve(int $id): bool
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("UPDATE comments SET status = 'approved' WHERE id = ?");
        $stmt->execute([$id]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            ActivityLog::log('comment_approved', 'comment', $id);
        }
        return $ok;
    }

    public static function delete(int $id): bool
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
        $stmt->execute([$id]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            ActivityLog::log('comment_deleted', 'comment', $id);
        }
        return $ok;
    }

    private static function postTitle(int $blogId): string
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("SELECT title FROM blogs WHERE id = ?");
        $stmt->execute([$blogId]);
        $row = $stmt->fetch();
        return $row['title'] ?? '';
    }
}