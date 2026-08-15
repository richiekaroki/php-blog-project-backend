<?php

namespace App\Middleware;

class SecurityHeaders
{
    public static function send(): void
    {
        // Fonts are self-hosted now (public/assets/fonts) — no Google Fonts CDN.
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'");
        header("X-Frame-Options: DENY");
        header("X-Content-Type-Options: nosniff");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    }
}
