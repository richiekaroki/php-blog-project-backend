-- schema.sql - Database Schema

CREATE TABLE admins (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    role VARCHAR(50) DEFAULT 'editor'
);

CREATE TABLE roles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

INSERT INTO roles (name) VALUES ('admin'), ('editor'), ('viewer');

CREATE TABLE user_roles (
    user_id INT REFERENCES admins(id),
    role_id INT REFERENCES roles(id),
    PRIMARY KEY (user_id, role_id)
);

INSERT INTO user_roles (user_id, role_id) VALUES (1, 1);

CREATE TABLE blogs (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    image VARCHAR(500),
    category_id INT,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Example admin for login (username: admin)
-- Password 'password' should be hashed using PHP: password_hash('password', PASSWORD_DEFAULT)
-- Example: INSERT INTO admins (username, password) VALUES ('admin', '$2y$10$...');
INSERT INTO admins (username, password)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4KzaCEoXf2uVEr59uRJ1uKwY ghost hasher$');

-- Performance indexes
CREATE INDEX IF NOT EXISTS idx_blogs_category_id ON blogs(category_id);
CREATE INDEX IF NOT EXISTS idx_blogs_id_desc ON blogs(id DESC);
CREATE INDEX IF NOT EXISTS idx_categories_name ON categories(name);

-- Activity log table
CREATE TABLE IF NOT EXISTS activity_log (
    id SERIAL PRIMARY KEY,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT,
    details JSONB,
    user_ip INET,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_activity_log_created_at ON activity_log(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_activity_log_entity ON activity_log(entity_type, entity_id);
