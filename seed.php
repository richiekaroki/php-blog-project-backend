<?php
// seed.php - Add sample content (run once, then delete)

require 'includes/connect.php';

// Add categories
$cats = [
    ['name' => 'Technology', 'description' => 'Articles about programming and tech'],
    ['name' => 'Security', 'description' => 'Web security best practices'],
    ['name' => 'DevOps', 'description' => 'Deployment, CI/CD, and infrastructure'],
    ['name' => 'PHP', 'description' => 'PHP tips, tricks, and tutorials'],
];

foreach ($cats as $cat) {
    $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?) ON CONFLICT (name) DO NOTHING");
    $stmt->execute([$cat['name'], $cat['description']]);
}

// Get category IDs
$catIds = [];
foreach ($cats as $cat) {
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
    $stmt->execute([$cat['name']]);
    $catIds[$cat['name']] = $stmt->fetch()['id'];
}

// Add blog posts
$posts = [
    [
        'title' => 'Why Application Security Matters in 2026',
        'content' => "In today's digital landscape, application security is no longer optional. With increasing sophisticated attacks targeting web applications, developers must understand and implement security best practices from day one.\n\nThis project implements multiple layers of security including CSRF protection, XSS prevention, SQL injection prevention through prepared statements, and rate limiting. These aren't just buzzwords — each one addresses a real attack vector that could compromise user data.\n\nThe OWASP Top 10 provides a framework for understanding the most critical security risks. By building this project with OWASP guidelines in mind, I gained practical experience in securing PHP applications against real-world threats.",
        'category' => 'Security'
    ],
    [
        'title' => 'Building REST APIs with PHP',
        'content' => "REST APIs are the backbone of modern web applications. In this project, I built a complete REST API for blog management with proper HTTP methods, status codes, and authentication.\n\nThe API supports full CRUD operations for blogs and categories, with write operations requiring session-based authentication. I implemented field whitelisting to prevent mass assignment vulnerabilities, and rate limiting to prevent abuse.\n\nKey design decisions include using JSON for request/response format, proper HTTP status codes (200, 201, 400, 401, 404, 429, 500), and consistent error response format. The API also includes a health check endpoint for monitoring.",
        'category' => 'Technology'
    ],
    [
        'title' => 'PostgreSQL vs MySQL: Why I Chose PostgreSQL',
        'content' => "For this project, I chose PostgreSQL over MySQL for several reasons. PostgreSQL offers superior data integrity through strict ACID compliance, better support for complex queries, and more advanced data types.\n\nPostgreSQL's support for JSONB columns, full-text search, and array types makes it incredibly versatile. The ILIKE operator for case-insensitive search was particularly useful for the blog search functionality.\n\nSetting up PostgreSQL with PDO in PHP is straightforward. The key is using the pgsql DSN format and enabling native prepared statements with ATTR_EMULATE_PREPARES set to false. This ensures that SQL injection prevention is handled at the database level, not just in PHP.",
        'category' => 'Technology'
    ],
    [
        'title' => 'Deploying PHP Applications with Docker',
        'content' => "Docker has revolutionized how we deploy applications. In this project, I containerized the PHP application using PHP-FPM and Nginx, creating a production-ready setup that runs consistently across environments.\n\nThe Dockerfile uses Alpine Linux for a minimal image size, installs required PHP extensions (PDO, GD, Zip), and configures both Nginx and PHP-FPM. The key insight is that PHP-FPM drops environment variables by default — the fix is adding clear_env = no to the PHP-FPM configuration.\n\nThis containerized approach means the application runs identically on my local machine, in CI/CD pipelines, and on cloud platforms like Render.",
        'category' => 'DevOps'
    ],
    [
        'title' => 'Understanding Session Security in PHP',
        'content' => "Session security is often overlooked but critical for protecting user accounts. This project implements multiple session hardening techniques that go beyond the basics.\n\nThe key settings include: httponly cookies (prevents JavaScript access), samesite=Lax (prevents cross-site request forgery), strict_mode (rejects attacker-planted session IDs), and session regeneration on login (prevents session fixation).\n\nI also implemented IP-based rate limiting for login attempts, storing attempt counts keyed to the client's IP address rather than the session. This prevents attackers from bypassing rate limits by clearing cookies or using incognito mode.",
        'category' => 'Security'
    ],
    [
        'title' => 'Testing PHP Applications with PHPUnit',
        'content' => "Automated testing is essential for maintaining code quality and catching regressions. This project includes 41 tests covering database operations, security features, CRUD operations, and API endpoints.\n\nThe test suite uses PHPUnit with a real PostgreSQL database connection, testing actual database operations rather than mocking. This approach catches issues that unit tests with mocks might miss, such as schema mismatches and foreign key constraints.\n\nKey testing patterns include: testing password hashing with bcrypt, verifying prepared statements prevent SQL injection, validating XSS escaping, and testing the full blog lifecycle (create, read, update, delete). The API tests verify that write operations require authentication.",
        'category' => 'PHP'
    ],
];

foreach ($posts as $post) {
    $stmt = $pdo->prepare("INSERT INTO blogs (title, content, category_id) VALUES (?, ?, ?) ON CONFLICT DO NOTHING");
    $stmt->execute([$post['title'], $post['content'], $catIds[$post['category']]]);
}

echo "Seeded " . count($cats) . " categories and " . count($posts) . " blog posts.\n";
