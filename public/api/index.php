<?php
// api/index.php - API entry point and router

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Database\Connection;
use App\Middleware\CORS;
use App\Models\ActivityLog;
use App\Auth\MagicLink;
use App\Mail\Mailer;

CORS::handle();

// Generate unique request ID for debugging
$requestId = bin2hex(random_bytes(8));
header("X-Request-ID: $requestId");

header('Content-Type: application/json');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

$pdo = Connection::getInstance();

// Parse the request - supports both path-based and query string routing
$method = $_SERVER['REQUEST_METHOD'];

// --- CRITICAL-1: Authentication for write operations ---
$writeMethods = ['POST', 'PUT', 'DELETE'];
$isMagicRequest = ($_GET['action'] ?? '') === 'magic';
if (in_array($method, $writeMethods) && !$isMagicRequest) {
    session_start();
    if (!isset($_SESSION['admin'])) {
        sendResponse(401, ['error' => 'Authentication required for write operations']);
    }
}

// --- Content-Type enforcement for POST/PUT ---
if (in_array($method, ['POST', 'PUT'])) {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (!str_contains($contentType, 'application/json')) {
        sendResponse(415, ['error' => 'Content-Type must be application/json']);
    }
}

// --- CRITICAL-2: Rate limiting (100 requests per minute per IP) ---
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateFile = sys_get_temp_dir() . '/api_rate_' . md5($ip);
$rateData = ['count' => 0, 'window' => time()];

if (file_exists($rateFile)) {
    $stored = json_decode(file_get_contents($rateFile), true);
    if ($stored && (time() - $stored['window']) < 60) {
        $rateData = $stored;
        $rateData['count']++;
    } else {
        $rateData = ['count' => 1, 'window' => time()];
    }
} else {
    $rateData = ['count' => 1, 'window' => time()];
}

file_put_contents($rateFile, json_encode($rateData));

// Add rate limit headers
$remaining = max(0, 100 - $rateData['count']);
$resetTime = $rateData['window'] + 60;
header("X-RateLimit-Limit: 100");
header("X-RateLimit-Remaining: $remaining");
header("X-RateLimit-Reset: $resetTime");

if ($rateData['count'] > 100) {
    sendResponse(429, ['error' => 'Rate limit exceeded. Max 100 requests per minute.']);
}

// Try path-based routing first: /api/blogs/1
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode('/', trim($uri, '/'));
$uri = array_values(array_filter($uri));
if (isset($uri[0]) && $uri[0] === 'api') {
    array_shift($uri);
}
if (isset($uri[0]) && $uri[0] === 'index.php') {
    array_shift($uri);
}

$endpoint = $uri[0] ?? '';
$id = $uri[1] ?? null;

// Fallback to query string routing: /api/index.php?action=blogs&id=1
if ($endpoint === '' && isset($_GET['action'])) {
    $endpoint = $_GET['action'];
}
if ($id === null && isset($_GET['id'])) {
    $id = $_GET['id'];
}

// Route the request
try {
    switch ($endpoint) {
        case 'blogs':
        case '':
            // Default to blogs when hitting /api/ or /api/index.php
            handleBlogs($method, $id, $pdo);
            break;
        case 'categories':
            handleCategories($method, $id, $pdo);
            break;
        case 'health':
            // Check database connectivity
            try {
                $pdo->query("SELECT 1");
                $dbStatus = 'connected';
                $httpStatus = 200;
            } catch (PDOException $e) {
                $dbStatus = 'disconnected';
                $httpStatus = 503;
            }
            
            sendResponse($httpStatus, [
                'status' => $httpStatus === 200 ? 'ok' : 'degraded',
                'database' => $dbStatus,
                'timestamp' => date('c'),
                'uptime' => time(),
            ]);
            break;
        case 'upload':
            handleUpload($method, $pdo);
            break;
        case 'activity':
            handleActivity($method);
            break;
        case 'magic':
            handleMagic($method, $pdo);
            break;
        case 'signup-request':
            handleSignupRequest($method, $pdo);
            break;
        case 'profile':
            handleProfile($method, $pdo);
            break;
        default:
            sendResponse(404, ['error' => 'Endpoint not found', 'available' => ['/api/blogs', '/api/categories', '/api/health', '/api/upload', '/api/activity']]);
    }
} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    sendResponse(500, ['error' => 'Internal server error']);
}

/**
 * Handle blogs endpoint
 */
function handleBlogs($method, $id, $pdo) {
    switch ($method) {
        case 'GET':
            if ($id) {
                // Get single blog
                $stmt = $pdo->prepare("
                    SELECT b.*, c.name AS category_name 
                    FROM blogs b 
                    LEFT JOIN categories c ON b.category_id = c.id 
                    WHERE b.id = ?
                ");
                $stmt->execute([$id]);
                $blog = $stmt->fetch();
                
                if (!$blog) {
                    sendResponse(404, ['error' => 'Blog not found']);
                }
                setCacheHeaders($blog, 30);
                sendResponse(200, ['success' => true, 'data' => $blog]);
            } else {
                // Get all blogs with pagination (supports both offset and cursor)
                $page = max(1, (int)($_GET['page'] ?? 1));
                $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
                $cursor = $_GET['cursor'] ?? null;
                
                // Get total count
                $countStmt = $pdo->query("SELECT COUNT(*) AS total FROM blogs");
                $total = $countStmt->fetch()['total'];
                
                // Cursor-based or offset-based pagination
                if ($cursor) {
                    $stmt = $pdo->prepare("
                        SELECT b.*, c.name AS category_name 
                        FROM blogs b 
                        LEFT JOIN categories c ON b.category_id = c.id 
                        WHERE b.id < ?
                        ORDER BY b.id DESC 
                        LIMIT ?
                    ");
                    $stmt->execute([(int)$cursor, $limit]);
                } else {
                    $offset = ($page - 1) * $limit;
                    $stmt = $pdo->prepare("
                        SELECT b.*, c.name AS category_name 
                        FROM blogs b 
                        LEFT JOIN categories c ON b.category_id = c.id 
                        ORDER BY b.id DESC 
                        LIMIT ? OFFSET ?
                    ");
                    $stmt->execute([$limit, $offset]);
                }
                $blogs = $stmt->fetchAll();
                
                // Get next cursor for cursor-based pagination
                $nextCursor = null;
                if ($cursor && count($blogs) === $limit) {
                    $nextCursor = end($blogs)['id'];
                }
                
                $response = [
                    'success' => true,
                    'data' => $blogs,
                    'pagination' => [
                        'total' => (int)$total,
                        'page' => $page,
                        'limit' => $limit,
                        'pages' => ceil($total / $limit),
                        'next_cursor' => $nextCursor,
                    ]
                ];
                setCacheHeaders($response, 60);
                sendResponse(200, $response);
            }
            break;
            
        case 'POST':
            // Create new blog — CRITICAL-3: whitelist allowed fields
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || empty($data['title']) || empty($data['content'])) {
                sendResponse(400, ['error' => 'Title and content are required']);
            }
            
            $allowed = ['title', 'content', 'category_id', 'image'];
            $data = array_intersect_key($data, array_flip($allowed));
            
            // Sanitize text fields (prevent stored XSS via API)
            $title = strip_tags(trim($data['title']));
            $content = strip_tags(trim($data['content']));
            $categoryId = $data['category_id'] ?? null;
            $image = $data['image'] ?? null;
            
            if (empty($title)) {
                sendResponse(400, ['error' => 'Title cannot be empty after sanitization']);
            }
            
            $stmt = $pdo->prepare("INSERT INTO blogs (title, content, image, category_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $content, $image, $categoryId]);
            
            $newId = $pdo->lastInsertId();
            ActivityLog::log('created', 'blog', (int)$newId, ['title' => $title]);
            sendResponse(201, ['success' => true, 'id' => $newId, 'message' => 'Blog created']);
            break;
            
        case 'PUT':
            // Update blog
            if (!$id) {
                sendResponse(400, ['error' => 'Blog ID is required']);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                sendResponse(400, ['error' => 'Invalid JSON data']);
            }
            
            // CRITICAL-3: whitelist allowed fields
            $allowed = ['title', 'content', 'image', 'category_id'];
            $data = array_intersect_key($data, array_flip($allowed));
            
            // Check if blog exists
            $stmt = $pdo->prepare("SELECT id FROM blogs WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                sendResponse(404, ['error' => 'Blog not found']);
            }
            
            // Build update query
            $updates = [];
            $params = [];
            
            if (isset($data['title'])) {
                $updates[] = "title = ?";
                $params[] = strip_tags(trim($data['title']));
            }
            if (isset($data['content'])) {
                $updates[] = "content = ?";
                $params[] = strip_tags(trim($data['content']));
            }
            if (isset($data['image'])) {
                $updates[] = "image = ?";
                $params[] = $data['image'];
            }
            if (isset($data['category_id'])) {
                $updates[] = "category_id = ?";
                $params[] = $data['category_id'];
            }
            
            if (empty($updates)) {
                sendResponse(400, ['error' => 'No fields to update']);
            }
            
            $params[] = $id;
            $sql = "UPDATE blogs SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            ActivityLog::log('updated', 'blog', (int)$id, ['fields' => array_keys($updates)]);
            sendResponse(200, ['success' => true, 'message' => 'Blog updated']);
            break;
            
        case 'DELETE':
            // Delete blog
            if (!$id) {
                sendResponse(400, ['error' => 'Blog ID is required']);
            }
            
            // Get blog title before deleting
            $stmt = $pdo->prepare("SELECT title FROM blogs WHERE id = ?");
            $stmt->execute([$id]);
            $blog = $stmt->fetch();
            
            $stmt = $pdo->prepare("DELETE FROM blogs WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() === 0) {
                sendResponse(404, ['error' => 'Blog not found']);
            }
            
            ActivityLog::log('deleted', 'blog', (int)$id, ['title' => $blog['title'] ?? 'unknown']);
            
            sendResponse(200, ['success' => true, 'message' => 'Blog deleted']);
            break;
            
        default:
            sendResponse(405, ['error' => 'Method not allowed']);
    }
}

/**
 * Handle categories endpoint
 */
function handleCategories($method, $id, $pdo) {
    switch ($method) {
        case 'GET':
            if ($id) {
                // Get single category with its blogs
                $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $category = $stmt->fetch();
                
                if (!$category) {
                    sendResponse(404, ['error' => 'Category not found']);
                }
                
                // Get blogs in this category
                $blogStmt = $pdo->prepare("SELECT * FROM blogs WHERE category_id = ? ORDER BY id DESC");
                $blogStmt->execute([$id]);
                $category['blogs'] = $blogStmt->fetchAll();
                
                setCacheHeaders($category, 60);
                sendResponse(200, ['success' => true, 'data' => $category]);
            } else {
                // Get all categories
                $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
                $categories = $stmt->fetchAll();
                setCacheHeaders($categories, 120);
                sendResponse(200, ['success' => true, 'data' => $categories]);
            }
            break;
            
        case 'POST':
            // Create new category — CRITICAL-3: whitelist allowed fields
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || empty($data['name'])) {
                sendResponse(400, ['error' => 'Name is required']);
            }
            
            $allowed = ['name', 'description'];
            $data = array_intersect_key($data, array_flip($allowed));
            
            // Sanitize text fields
            $name = strip_tags(trim($data['name']));
            $description = strip_tags(trim($data['description'] ?? ''));
            
            if (empty($name)) {
                sendResponse(400, ['error' => 'Name cannot be empty after sanitization']);
            }
            
            $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $description]);
            
            $newId = $pdo->lastInsertId();
            sendResponse(201, ['success' => true, 'id' => $newId, 'message' => 'Category created']);
            break;
            
        case 'PUT':
            // Update category
            if (!$id) {
                sendResponse(400, ['error' => 'Category ID is required']);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                sendResponse(400, ['error' => 'Invalid JSON data']);
            }
            
            // CRITICAL-3: whitelist allowed fields
            $allowed = ['name', 'description'];
            $data = array_intersect_key($data, array_flip($allowed));
            
            // Check if category exists
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                sendResponse(404, ['error' => 'Category not found']);
            }
            
            $name = $data['name'] ?? null;
            $description = $data['description'] ?? null;
            
            if ($name) {
                $name = strip_tags(trim($name));
                $description = $description ? strip_tags(trim($description)) : null;
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $description, $id]);
            }
            
            sendResponse(200, ['success' => true, 'message' => 'Category updated']);
            break;
            
        case 'DELETE':
            // Delete category
            if (!$id) {
                sendResponse(400, ['error' => 'Category ID is required']);
            }
            
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() === 0) {
                sendResponse(404, ['error' => 'Category not found']);
            }
            
            sendResponse(200, ['success' => true, 'message' => 'Category deleted']);
            break;
            
        default:
            sendResponse(405, ['error' => 'Method not allowed']);
    }
}

/**
 * Handle file upload
 */
function handleUpload($method, $pdo) {
    if ($method !== 'POST') {
        sendResponse(405, ['error' => 'Method not allowed']);
    }
    
    // Check authentication
    session_start();
    if (!isset($_SESSION['admin'])) {
        sendResponse(401, ['error' => 'Authentication required']);
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        sendResponse(400, ['error' => 'No file uploaded or upload error']);
    }
    
    $file = $_FILES['file'];
    
    // Validate file size (5MB max)
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        sendResponse(400, ['error' => 'File too large. Maximum size is 5MB']);
    }
    
    // Validate image content
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        sendResponse(400, ['error' => 'Invalid image file']);
    }
    
    // Map MIME to extension
    $mimeToExt = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];
    $ext = $mimeToExt[$imageInfo[2]] ?? null;
    if (!$ext) {
        sendResponse(400, ['error' => 'Unsupported image type']);
    }
    
    // Generate random filename
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $uploadDir = dirname(__DIR__) . '/uploads/';
    
    // Create uploads directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        sendResponse(500, ['error' => 'Failed to save file']);
    }
    
    $url = '/uploads/' . $filename;
    
    sendResponse(201, [
        'success' => true,
        'url' => $url,
        'filename' => $filename,
        'message' => 'File uploaded successfully',
    ]);
}

/**
 * Send JSON response with consistent format
 */
function sendResponse($statusCode, $data) {
    global $requestId;
    
    http_response_code($statusCode);
    
    // Add request_id to all responses
    $data['request_id'] = $requestId;
    
    // For error responses, use consistent error format
    if ($statusCode >= 400 && isset($data['error']) && !is_array($data['error'])) {
        $data['error'] = [
            'code' => 'ERROR_' . $statusCode,
            'message' => $data['error'],
        ];
    }
    
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Set cache headers for GET requests
 */
function setCacheHeaders($data, $maxAge = 60) {
    $etag = '"' . md5(json_encode($data)) . '"';
    header("Cache-Control: public, max-age=$maxAge");
    header("ETag: $etag");
    
    // Check if client has matching ETag
    $clientEtag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
    if ($clientEtag === $etag) {
        http_response_code(304);
        exit;
    }
}

/**
 * Handle activity log
 */
function handleActivity($method) {
    if ($method !== 'GET') {
        sendResponse(405, ['error' => 'Method not allowed']);
    }
    
    $limit = (int)($_GET['limit'] ?? 50);
    $limit = min(max($limit, 1), 100);
    
    $activities = \App\Models\ActivityLog::getRecent($limit);
    sendResponse(200, ['success' => true, 'data' => $activities]);
}

/**
 * Handle passwordless magic link requests.
 * POST /api/magic/request  { "email": "..." }
 */
function handleMagic($method, $pdo) {
    if ($method !== 'POST') {
        sendResponse(405, ['error' => 'Method not allowed']);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $email = strtolower(trim($data['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(400, ['error' => 'A valid email address is required']);
    }

    // Look up admin by email (no password involved)
    $stmt = $pdo->prepare("SELECT id, username, email FROM admins WHERE LOWER(email) = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Always return the same generic response to avoid leaking which emails are registered.
    if ($user) {
        try {
            $ttl = (int)(getenv('MAGIC_LINK_TTL') ?: 900);
            $magic = new MagicLink();
            $token = $magic->create($email, $ttl);

            $appUrl = rtrim((string)(getenv('APP_URL') ?: 'https://php-blog-backend.onrender.com'), '/');
            $loginUrl = $appUrl . '/admin/login.php?action=magic&token=' . urlencode($token);

            $mailer = new Mailer();
            $mailer->send(
                $email,
                'Your WAM Blog sign in link',
                $emailHtml($loginUrl),
                "Open this link to sign in to WAM Blog:\n\n$loginUrl\n\nThis link expires in " . round($ttl / 60) . " minutes."
            );

            ActivityLog::log('magic_link_sent', 'auth', (int)$user['id'], ['email' => $email]);
        } catch (\Throwable $e) {
            error_log('Magic link send failed: ' . $e->getMessage());
            sendResponse(500, ['error' => 'Could not send the sign in link. Please try again later.']);
        }
    }

    sendResponse(200, ['success' => true, 'message' => 'If that email is registered, a sign in link is on its way.']);
}

/**
 * Handle a passwordless sign-up request.
 * POST /api/signup-request  { "email": "..." }
 *
 * Stores a pending invitation token and emails a secure link to the owner.
 * An admin later approves the request and the user becomes an admin.
 */
function handleSignupRequest($method, $pdo) {
    if ($method !== 'POST') {
        sendResponse(405, ['error' => 'Method not allowed']);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $email = strtolower(trim($data['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(400, ['error' => 'A valid email address is required']);
    }

    // If the visitor already has an account, redirect them to the normal magic-link sign-in.
    $stmt = $pdo->prepare("SELECT id FROM admins WHERE LOWER(email) = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendResponse(200, [
            'success' => true,
            'message' => 'An account already exists for this email. We sent a sign in link to your inbox.',
            'existing' => true
        ]);
        return;
    }

    // Create or re-use a pending invitation.
    $token = bin2hex(random_bytes(32));
    $expiresAt = (new \DateTime())->modify('+24 hours')->format('Y-m-d H:i:s');

    try {
        // Deactivate any previous unused invitations for this email.
        $stmt = $pdo->prepare("UPDATE invitations SET accepted_at = NOW() WHERE email = ? AND accepted_at IS NULL AND expires_at > NOW()");
        $stmt->execute([$email]);

        $stmt = $pdo->prepare("INSERT INTO invitations (email, token, role, expires_at) VALUES (?, ?, 'editor', ?) ON CONFLICT (email) DO UPDATE SET token = EXCLUDED.token, expires_at = EXCLUDED.expires_at");
        $stmt->execute([$email, $token, $expiresAt]);

        ActivityLog::log('signup_requested', 'invitation', null, ['email' => $email]);

        sendResponse(200, ['success' => true, 'message' => 'An access request has been submitted. Check your inbox to activate your account.']);
    } catch (\Throwable $e) {
        error_log('Signup request failed: ' . $e->getMessage());
        sendResponse(500, ['error' => 'Could not process your request. Please try again later.']);
    }
}

function emailHtml(string $loginUrl): string {
    $safeUrl = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');
    return '<!DOCTYPE html><html><body style="font-family:Georgia,serif;background:#FBF9F1;color:#2E2910;padding:24px;text-align:center;">'
        . '<h1 style="color:#2C5745;">Sign in to WAM Blog</h1>'
        . '<p>Click the button below to sign in. This link is valid for 15 minutes.</p>'
        . '<a href="' . $safeUrl . '" style="display:inline-block;margin:16px auto;padding:12px 24px;background:#2C5745;color:#fff;text-decoration:none;border-radius:8px;">Sign In</a>'
        . '<p style="color:#5C5340;font-size:14px;">If you did not request this, you can safely ignore this email.</p>'
        . '</body></html>';
}

/**
 * Handle the signed-in admin's own profile.
 * GET  /api/profile              -> fetch username, email, role
 * PUT  /api/profile {username?, email?}
 * PUT  /api/profile {current_password, new_password} -> change password
 */
function handleProfile($method, $pdo) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!isset($_SESSION['admin'])) {
        sendResponse(401, ['error' => 'Authentication required']);
    }

    $username = $_SESSION['admin'];

    if ($method === 'GET') {
        $stmt = $pdo->prepare("SELECT id, username, email, role FROM admins WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user) {
            sendResponse(404, ['error' => 'Account not found']);
        }
        sendResponse(200, ['success' => true, 'data' => $user]);
    }

    if ($method !== 'PUT') {
        sendResponse(405, ['error' => 'Method not allowed']);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        sendResponse(400, ['error' => 'Invalid JSON data']);
    }

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user) {
        sendResponse(404, ['error' => 'Account not found']);
    }

    $changes = [];

    // --- Password change flow ---
    $changingPassword = isset($data['current_password']) || isset($data['new_password']);
    if ($changingPassword) {
        if (!isset($data['current_password'], $data['new_password'])) {
            sendResponse(400, ['error' => 'Current and new password are both required']);
        }
        if (!password_verify($data['current_password'], $user['password'])) {
            sendResponse(400, ['error' => 'Current password is incorrect']);
        }
        $newPassword = $data['new_password'];
        if (strlen($newPassword) < 8) {
            sendResponse(400, ['error' => 'New password must be at least 8 characters']);
        }
        $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
        $changes[] = 'password';
    }

    // --- Profile field updates ---
    $updates = [];
    $params = [];

    if (array_key_exists('username', $data)) {
        $newUsername = strip_tags(trim((string)$data['username']));
        if ($newUsername === '' || strlen($newUsername) > 50) {
            sendResponse(400, ['error' => 'Username must be 1-50 characters']);
        }
        if ($newUsername !== $user['username']) {
            $dup = $pdo->prepare("SELECT id FROM admins WHERE LOWER(username) = ? AND id != ? LIMIT 1");
            $dup->execute([strtolower($newUsername), $user['id']]);
            if ($dup->fetch()) {
                sendResponse(400, ['error' => 'Username is already taken']);
            }
            $updates[] = "username = ?";
            $params[] = $newUsername;
        }
    }

    if (array_key_exists('email', $data)) {
        $email = strtolower(trim((string)$data['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendResponse(400, ['error' => 'A valid email address is required']);
        }
        if ($email !== ($user['email'] ?? '')) {
            $dup = $pdo->prepare("SELECT id FROM admins WHERE LOWER(email) = ? AND id != ? LIMIT 1");
            $dup->execute([$email, $user['id']]);
            if ($dup->fetch()) {
                sendResponse(400, ['error' => 'Email is already in use']);
            }
            $updates[] = "email = ?";
            $params[] = $email;
        }
    }

    if ($updates) {
        $params[] = $user['id'];
        $sql = "UPDATE admins SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if (array_key_exists('username', $data) && in_array('username = ?', $updates)) {
            $_SESSION['admin'] = $params[array_search('username = ?', $updates)];
        }
        $changes = array_merge($changes, array_map(fn($u) => str_replace(' = ?', '', $u), $updates));
    }

    ActivityLog::log('profile_updated', 'admin', (int)$user['id'], ['fields' => $changes]);

    // Re-fetch the current profile so the response always reflects the DB.
    $stmt = $pdo->prepare("SELECT id, username, email, role FROM admins WHERE id = ? LIMIT 1");
    $stmt->execute([$user['id']]);
    $fresh = $stmt->fetch();

    sendResponse(200, [
        'success' => true,
        'message' => 'Profile updated',
        'data' => $fresh,
    ]);
}