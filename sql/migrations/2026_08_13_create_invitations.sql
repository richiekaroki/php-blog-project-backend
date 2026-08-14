-- Migration: create invitations table for passwordless sign-up requests
-- Allows visitors to request access; an admin later approves and an account
-- is created. Rejection is recorded separately from acceptance.

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

-- Idempotent upgrade for tables created by an earlier version of this migration.
ALTER TABLE invitations ADD COLUMN IF NOT EXISTS rejected_at TIMESTAMP;

CREATE INDEX IF NOT EXISTS idx_invitations_token ON invitations(token);
CREATE INDEX IF NOT EXISTS idx_invitations_email ON invitations(email);
CREATE INDEX IF NOT EXISTS idx_invitations_pending ON invitations(expires_at) WHERE accepted_at IS NULL AND rejected_at IS NULL;