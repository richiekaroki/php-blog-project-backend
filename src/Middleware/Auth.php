<?php

namespace App\Middleware;

class Auth
{
    public static function check(): void
    {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_strict_mode', 1);
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }

        session_start();

        if (!isset($_SESSION['admin'])) {
            header("Location: index.php");
            exit;
        }

        $pdo = \App\Database\Connection::getInstance();
        $stmt = $pdo->prepare("SELECT role FROM admins WHERE username = ?");
        $stmt->execute([$_SESSION['admin']]);
        $user = $stmt->fetch();

        if (!$user) {
            session_destroy();
            header("Location: index.php");
            exit;
        }

        $_SESSION['user_role'] = $user['role'];
        session_regenerate_id(true);
    }

    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['admin']);
    }

    public static function getRole(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }
}
