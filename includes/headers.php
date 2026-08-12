<?php
// headers.php - Security headers (include on every page)

// MEDIUM-2: Content Security Policy
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'");

// MEDIUM-3: Clickjacking protection
header("X-Frame-Options: DENY");

// MEDIUM-3: MIME type sniffing prevention
header("X-Content-Type-Options: nosniff");

// Referrer policy
header("Referrer-Policy: strict-origin-when-cross-origin");

// Permissions policy
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
