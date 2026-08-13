-- Migration: magic link security hardening
-- 1) Single-use magic link tokens (token hash primary key, atomic INSERT ... ON CONFLICT)
-- 2) Server-side session registry for revocation / expiry
-- 3) Optional TOTP 2FA secret on admins

-- Records every magic link token that has been redeemed. The PRIMARY KEY on
-- token_hash makes "consume" atomic: only the first request to insert wins.
CREATE TABLE IF NOT EXISTS magic_link_uses (
    token_hash VARCHAR(64) PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    used_at TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Server-side session registry. A row is created on every successful login and
-- revoked on logout or via the admin profile page. Auth::check() validates the
-- current PHP session against this table so a revoked/expired session dies.
CREATE TABLE IF NOT EXISTS auth_sessions (
    id SERIAL PRIMARY KEY,
    admin_id INT NOT NULL REFERENCES admins(id) ON DELETE CASCADE,
    session_token_hash VARCHAR(64) NOT NULL UNIQUE,
    ip VARCHAR(45),
    user_agent TEXT,
    expires_at TIMESTAMP NOT NULL,
    revoked_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_auth_sessions_admin ON auth_sessions(admin_id);
CREATE INDEX IF NOT EXISTS idx_auth_sessions_token ON auth_sessions(session_token_hash);

-- Optional TOTP 2FA secret. NULL / empty means 2FA is disabled for that admin.
ALTER TABLE admins ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64);