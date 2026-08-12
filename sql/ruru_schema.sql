-- schema.sql - Database Schema

CREATE TABLE admins (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
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
