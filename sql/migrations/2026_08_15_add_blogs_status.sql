-- Add a draft/published status to posts so the journal can write ahead.
ALTER TABLE blogs ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'published';
CREATE INDEX IF NOT EXISTS idx_blogs_status ON blogs(status);