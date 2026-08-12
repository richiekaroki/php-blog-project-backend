<?php
// auth.php - Authentication middleware with role support

// HIGH-5: Session cookie hardening (before session_start)
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

session_start();

// Check if user is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit;
}

// Fetch user role from database
require '../includes/connect.php';

$stmt = $pdo->prepare("SELECT role FROM admins WHERE username = ?");
$stmt->execute([$_SESSION['admin']]);
$user = $stmt->fetch();

if (!$user) {
    // User not found, destroy session
    session_destroy();
    header("Location: index.php");
    exit;
}

// Store role in session
$_SESSION['user_role'] = $user['role'];

// Regenerate session ID to prevent fixation
session_regenerate_id(true);