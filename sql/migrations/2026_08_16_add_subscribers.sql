-- Migration: newsletter subscribers
-- Readers can subscribe to get an email when a new post is published.
-- token is a random unguessable string used in the unsubscribe link.

CREATE TABLE IF NOT EXISTS subscribers (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    token VARCHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_subscribers_token ON subscribers(token);