-- schema.sql - Database Schema

CREATE TABLE admins (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) UNIQUE,
    role VARCHAR(50) DEFAULT 'editor',
    totp_secret VARCHAR(255)
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

-- Invitations: passwordless sign-up requests awaiting admin approval
CREATE TABLE IF NOT EXISTS invitations (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    token VARCHAR(255) NOT NULL,
    invited_by INT,
    role VARCHAR(50) DEFAULT 'editor',
    expires_at TIMESTAMP NOT NULL,
    accepted_at TIMESTAMP,
    rejected_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    FOREIGN KEY (invited_by) REFERENCES admins(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_invitations_token ON invitations(token);
CREATE INDEX IF NOT EXISTS idx_invitations_email ON invitations(email);
CREATE INDEX IF NOT EXISTS idx_invitations_pending ON invitations(expires_at) WHERE accepted_at IS NULL AND rejected_at IS NULL;

CREATE TABLE blogs (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    image VARCHAR(500),
    category_id INT,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Example admin for login (username: admin)
-- Passwordless auth: admins sign in via magic links using their email.
-- Insert a row with the admin's email, e.g.:
-- INSERT INTO admins (username, email, role) VALUES ('admin', 'you@example.com', 'admin');

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
