-- Migration: comments for blog posts
-- Readers can leave a comment on a published post. Comments start as
-- 'pending' and are shown only after an admin approves them (moderation).
-- Cascade: deleting a blog removes its comments.

CREATE TABLE IF NOT EXISTS comments (
    id SERIAL PRIMARY KEY,
    blog_id INT NOT NULL REFERENCES blogs(id) ON DELETE CASCADE,
    author_name VARCHAR(100) NOT NULL,
    author_email VARCHAR(255),
    content TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    user_ip INET,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_comments_blog ON comments(blog_id, status);
CREATE INDEX IF NOT EXISTS idx_comments_status ON comments(status, created_at);