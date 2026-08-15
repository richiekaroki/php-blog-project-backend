<?php

namespace App\Middleware;

use App\Database\Connection;
use App\Support\Env;

class Auth
{
    /**
     * Start a hardened PHP session (HttpOnly, SameSite, strict mode, Secure on HTTPS).
     */
    public static function startSession(): void
    {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_strict_mode', 1);
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /**
     * Require a valid, non-revoked, non-expired session. Redirects to the
     * PHP admin login when unauthenticated or the server-side session record
     * has been revoked/expired.
     */
    public static function check(): void
    {
        self::startSession();
        self::maybeDevAutoLogin();

        if (!isset($_SESSION['admin'])) {
            self::redirectToLogin();
        }

        if (!self::isSessionValid()) {
            self::logout();
            self::redirectToLogin();
        }

        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("SELECT id, role FROM admins WHERE username = ?");
        $stmt->execute([$_SESSION['admin']]);
        $user = $stmt->fetch();

        if (!$user) {
            self::logout();
            self::redirectToLogin();
        }

        $_SESSION['user_role'] = $user['role'];
    }

    /**
     * Check that the current session has a valid server-side record.
     */
    public static function isSessionValid(): bool
    {
        if (!isset($_SESSION['admin'])) {
            return false;
        }

        try {
            $pdo = Connection::getInstance();
            $stmt = $pdo->prepare(
                "SELECT s.revoked_at, s.expires_at, s.session_token_hash
                 FROM auth_sessions s
                 JOIN admins a ON a.id = s.admin_id
                 WHERE s.session_token_hash = ? AND a.username = ?"
            );
            $stmt->execute([self::sessionTokenHash(), $_SESSION['admin']]);
            $row = $stmt->fetch();

            if (!$row) {
                return false;
            }
            if ($row['revoked_at'] !== null) {
                return false;
            }
            if ($row['expires_at'] !== null && strtotime($row['expires_at']) < time()) {
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            // If the auth_sessions table is missing, fail closed.
            return false;
        }
    }

    /**
     * Require one of the given roles. Fails with a 403 if not authorized.
     */
    public static function requireRole(string ...$roles): void
    {
        self::check();

        $role = $_SESSION['user_role'] ?? null;
        if (!in_array($role, $roles, true)) {
            http_response_code(403);
            die('Access denied: your role (' . htmlspecialchars((string)$role) . ') does not permit this action.');
        }
    }

    /**
     * Register a new server-side session record for a freshly signed-in admin.
     * Returns the username on success.
     */
    public static function registerSession(string $username, int $lifetimeSeconds = 604800): void
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        if (!$admin) {
            return;
        }

        // Rotate the PHP session id so the pre-auth session is discarded, then
        // register the fresh id in auth_sessions.
        session_regenerate_id(true);

        $stmt = $pdo->prepare(
            "INSERT INTO auth_sessions (admin_id, session_token_hash, ip, user_agent, expires_at)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            (int)$admin['id'],
            self::sessionTokenHash(),
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            date('Y-m-d H:i:s', time() + $lifetimeSeconds),
        ]);

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    /**
     * Revoke the current server-side session and destroy the PHP session.
     */
    public static function logout(): void
    {
        self::startSession();

        try {
            $pdo = Connection::getInstance();
            $stmt = $pdo->prepare("UPDATE auth_sessions SET revoked_at = NOW() WHERE session_token_hash = ? AND revoked_at IS NULL");
            $stmt->execute([self::sessionTokenHash()]);
        } catch (\Throwable $e) {
            error_log('Auth::logout session revoke failed: ' . $e->getMessage());
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    /**
     * Revoke every session for the current admin except the active one.
     */
    public static function revokeOtherSessions(): int
    {
        self::startSession();
        $username = $_SESSION['admin'] ?? null;
        if ($username === null) {
            return 0;
        }

        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            "UPDATE auth_sessions s SET revoked_at = NOW()
             FROM admins a
             WHERE a.id = s.admin_id AND a.username = ? AND s.session_token_hash <> ? AND s.revoked_at IS NULL"
        );
        $stmt->execute([$username, self::sessionTokenHash()]);
        return $stmt->rowCount();
    }

    public static function getRole(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    /**
     * Development-only convenience: silently sign in as a local admin so the
     * admin pages can be exercised in a browser without going through the
     * magic-link email flow. Strictly gated on APP_ENV=local AND an explicit
     * DEV_AUTOLOGIN=true opt-in, so a production deploy can never hit it even
     * if APP_ENV is misconfigured. The admin account (default dev@local.test,
     * role admin) is created on first use.
     */
    private static function maybeDevAutoLogin(): void
    {
        if (Env::get('APP_ENV') !== 'local' || Env::get('DEV_AUTOLOGIN') !== 'true' || isset($_SESSION['admin'])) {
            return;
        }

        try {
            $pdo = Connection::getInstance();
            $email = Env::get('DEV_ADMIN_EMAIL', 'dev@local.test');

            $stmt = $pdo->prepare("SELECT id, username, role FROM admins WHERE LOWER(email) = ? LIMIT 1");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if (!$admin) {
                $stmt = $pdo->prepare("SELECT id, username, role FROM admins WHERE username = 'dev' LIMIT 1");
                $stmt->execute();
                $admin = $stmt->fetch();
            }

            if (!$admin) {
                $ins = $pdo->prepare("INSERT INTO admins (username, email, role) VALUES ('dev', ?, 'admin')");
                $ins->execute([$email]);
                $admin = ['username' => 'dev', 'role' => 'admin'];
            }

            $_SESSION['admin'] = $admin['username'];
            self::registerSession($admin['username']);
            $_SESSION['user_role'] = $admin['role'];
        } catch (\Throwable $e) {
            error_log('Dev auto-login failed: ' . $e->getMessage());
        }
    }

    public static function currentUsername(): ?string
    {
        return $_SESSION['admin'] ?? null;
    }

    private static function sessionTokenHash(): string
    {
        return hash('sha256', session_id());
    }

    private static function redirectToLogin(): void
    {
        header("Location: login.php");
        exit;
    }
}