<?php
// auth.php - Authentication middleware with role support

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