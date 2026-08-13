-- Migration: DB-backed IP rate limiting for the login endpoint
-- Replaces the session-based counters (which were never incremented and could
-- be reset by clearing cookies). Keyed on a SHA-256 hash of the client IP so
-- raw IPs are not stored.

CREATE TABLE IF NOT EXISTS login_rate_limits (
    bucket VARCHAR(32) NOT NULL,      -- 'magic' | 'login' | '2fa'
    ip_hash VARCHAR(64) NOT NULL,     -- sha256 of client IP
    attempt_count INT NOT NULL DEFAULT 1,
    window_start TIMESTAMP NOT NULL DEFAULT NOW(),
    PRIMARY KEY (bucket, ip_hash)
);

CREATE INDEX IF NOT EXISTS idx_login_rate_limits_bucket ON login_rate_limits(bucket);