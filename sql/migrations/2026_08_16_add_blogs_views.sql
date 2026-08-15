-- Track how many times each post has been read, for the read counts.
ALTER TABLE blogs ADD COLUMN IF NOT EXISTS views INT NOT NULL DEFAULT 0;