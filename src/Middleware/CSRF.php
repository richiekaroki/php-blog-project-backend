<?php

namespace App\Middleware;

class CSRF
{
    public static function init(): void
    {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_strict_mode', 1);
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }

        session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function token(): string
    {
        return $_SESSION['csrf_token'] ?? '';
    }

    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . self::token() . '">';
    }

    public static function verify(?string $token = null): bool
    {
        $token = $token ?? $_POST['csrf_token'] ?? '';
        return hash_equals(self::token(), $token);
    }
}
