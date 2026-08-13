<?php
// api/index.php - API entry point and router

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Database\Connection;
use App\Middleware\CORS;

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
if (in_array($method, $writeMethods)) {
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
        default:
            sendResponse(404, ['error' => 'Endpoint not found', 'available' => ['/api/blogs', '/api/categories', '/api/health']]);
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
            
            sendResponse(200, ['success' => true, 'message' => 'Blog updated']);
            break;
            
        case 'DELETE':
            // Delete blog
            if (!$id) {
                sendResponse(400, ['error' => 'Blog ID is required']);
            }
            
            $stmt = $pdo->prepare("DELETE FROM blogs WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() === 0) {
                sendResponse(404, ['error' => 'Blog not found']);
            }
            
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