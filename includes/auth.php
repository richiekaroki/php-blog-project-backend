<?php
// auth.php - Authentication middleware

session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: index.php");  // Redirect to login page if not authenticated
    exit;
}