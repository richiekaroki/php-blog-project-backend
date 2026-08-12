<?php
// csrf.php - CSRF protection with secure session configuration

// HIGH-5: Session cookie hardening (before session_start)
ini_set('session.cookie_httponly', 1);   // Prevent JavaScript access to session cookie
ini_set('session.cookie_samesite', 'Lax'); // Prevent cross-site cookie sending
ini_set('session.use_strict_mode', 1);   // Reject attacker-planted session IDs
// Note: session.cookie_secure set conditionally below
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1); // Only send cookie over HTTPS
}

session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
