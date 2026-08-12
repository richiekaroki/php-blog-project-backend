<?php
// api/index.php - API entry point and router

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load database connection
require '../includes/connect.php';

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
            sendResponse(200, ['status' => 'ok', 'timestamp' => date('c')]);
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
                sendResponse(200, ['success' => true, 'data' => $blog]);
            } else {
                // Get all blogs with pagination
                $page = max(1, (int)($_GET['page'] ?? 1));
                $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
                $offset = ($page - 1) * $limit;
                
                // Get total count
                $countStmt = $pdo->query("SELECT COUNT(*) AS total FROM blogs");
                $total = $countStmt->fetch()['total'];
                
                // Get blogs
                $stmt = $pdo->prepare("
                    SELECT b.*, c.name AS category_name 
                    FROM blogs b 
                    LEFT JOIN categories c ON b.category_id = c.id 
                    ORDER BY b.id DESC 
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute([$limit, $offset]);
                $blogs = $stmt->fetchAll();
                
                sendResponse(200, [
                    'success' => true,
                    'data' => $blogs,
                    'pagination' => [
                        'total' => (int)$total,
                        'page' => $page,
                        'limit' => $limit,
                        'pages' => ceil($total / $limit)
                    ]
                ]);
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
            
            $title = $data['title'];
            $content = $data['content'];
            $categoryId = $data['category_id'] ?? null;
            $image = $data['image'] ?? null;
            
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
                $params[] = $data['title'];
            }
            if (isset($data['content'])) {
                $updates[] = "content = ?";
                $params[] = $data['content'];
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
                
                sendResponse(200, ['success' => true, 'data' => $category]);
            } else {
                // Get all categories
                $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
                $categories = $stmt->fetchAll();
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
            
            $name = $data['name'];
            $description = $data['description'] ?? '';
            
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
 * Send JSON response
 */
function sendResponse($statusCode, $data) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}