<?php
// init_db.php - One-time database setup (DELETE AFTER USE)

require 'includes/connect.php';

$schema = "
CREATE TABLE IF NOT EXISTS admins (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'editor'
);

CREATE TABLE IF NOT EXISTS roles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS user_roles (
    user_id INT REFERENCES admins(id),
    role_id INT REFERENCES roles(id),
    PRIMARY KEY (user_id, role_id)
);

CREATE TABLE IF NOT EXISTS categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT
);

CREATE TABLE IF NOT EXISTS blogs (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    image VARCHAR(500),
    category_id INT,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Insert default data
INSERT INTO roles (name) VALUES ('admin'), ('editor'), ('viewer') ON CONFLICT DO NOTHING;

INSERT INTO admins (username, password, role)
VALUES ('admin', '\$2y\$12\$JiUQRNsBrb61Lrgq6y6XUeU1YzcbP0OgGcJI4InSil3MMuFfANosm', 'admin')
ON CONFLICT (username) DO NOTHING;

INSERT INTO user_roles (user_id, role_id) 
SELECT a.id, r.id FROM admins a, roles r WHERE a.username = 'admin' AND r.name = 'admin'
AND NOT EXISTS (SELECT 1 FROM user_roles WHERE user_id = a.id AND role_id = r.id);
";

try {
    $pdo->exec($schema);
    echo json_encode(['success' => true, 'message' => 'Database initialized successfully! Delete this file now.']);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
