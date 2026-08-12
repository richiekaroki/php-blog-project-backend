<?php
// admin/login.php - Admin login with PDO, CSRF and Rate Limiting

require '../includes/connect.php';
require '../includes/csrf.php';
// session_start() is handled by csrf.php

// HIGH-4: IP-based rate limiting (not session-based — session can be cleared)
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateKey = "login_attempts_" . md5($ip);

// Initialize rate limit counter in session if not set
if (!isset($_SESSION[$rateKey])) {
    $_SESSION[$rateKey] = ['count' => 0, 'time' => 0];
}

// Check rate limit (max 5 attempts per 15 minutes)
$max_attempts = 5;
$lockout_time = 15 * 60; // 15 minutes in seconds

$current_time = time();

// Check if user is locked out
if ($_SESSION[$rateKey]['count'] >= $max_attempts && 
    ($current_time - $_SESSION[$rateKey]['time']) < $lockout_time) {
    $remaining = $lockout_time - ($current_time - $_SESSION[$rateKey]['time']);
    die('Too many login attempts. Please try again in ' . ceil($remaining / 60) . ' minutes.');
}

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token INSIDE the POST handler
    if (!isset($_POST['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token'] ||
        hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']) === false) {
        die('Invalid CSRF token');
    }

    // Increment attempt counter (IP-based)
    $_SESSION[$rateKey]['count']++;
    $_SESSION[$rateKey]['time'] = $current_time;

    // Get username and password from the form
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Check if username and password are valid using PDO prepared statement
    $sql = "SELECT * FROM admins WHERE username = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Successful login
        $_SESSION['admin'] = $username;
        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);
        // HIGH-3: Regenerate CSRF token after login (prevent token fixation)
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        // Reset login attempts on success
        $_SESSION[$rateKey] = ['count' => 0, 'time' => 0];
        $_SESSION['user_role'] = $user['role'] ?? 'editor';
        header("Location: blogs.php");  // Redirect to blog management
        exit;
    } else {
        // Invalid credentials - show error but don't reveal if user exists
        $error = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="text-center">Admin Login</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <strong>Login failed.</strong> <small><?php echo $error; ?></small>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="login.php">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" id="username" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" id="password" class="form-control" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>