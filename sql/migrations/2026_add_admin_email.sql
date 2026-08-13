-- Migration: add email column to admins for passwordless magic link auth
ALTER TABLE admins ADD COLUMN IF NOT EXISTS email VARCHAR(255) UNIQUE;

-- Optional: backfill the default admin with your email (edit before running)
-- UPDATE admins SET email = 'you@example.com' WHERE username = 'admin';