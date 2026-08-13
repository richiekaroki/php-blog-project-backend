-- Migration: drop the legacy admins.password column
-- Auth is passwordless (magic link + TOTP). No code path reads or writes this
-- column anymore, so the unused bcrypt hash is removed to reduce attack surface.
ALTER TABLE admins DROP COLUMN IF EXISTS password;