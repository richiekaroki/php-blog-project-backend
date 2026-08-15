-- Migration: add published timestamp to blogs
-- The API and frontend already expect blogs.created_at for display dates;
-- the column was missing from the original schema. Idempotent for safety.

ALTER TABLE blogs ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_blogs_created_at ON blogs(created_at DESC);