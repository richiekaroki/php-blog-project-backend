<?php

namespace App\Auth;

use App\Support\Env;

/**
 * Stateless magic link tokens signed with APP_KEY (HMAC-SHA256).
 * No token is stored in the database — each link carries its own
 * signed payload (email + expiry) and is verified on click.
 */
class MagicLink
{
    private string $secret;

    public function __construct(?string $secret = null)
    {
        $secret = $secret ?: self::appKey();
        if ($secret === '' || $secret === null) {
            throw new \RuntimeException('APP_KEY is required to sign magic links.');
        }
        // Support base64: prefixed keys (Laravel-style) and raw keys.
        $this->secret = str_starts_with($secret, 'base64:')
            ? base64_decode(substr($secret, 7))
            : $secret;
    }

    public function create(string $email, int $ttlSeconds = 600): string
    {
        $payload = $this->encodePayload([
            'email' => strtolower(trim($email)),
            'exp' => time() + $ttlSeconds,
        ]);
        $signature = hash_hmac('sha256', $payload, $this->secret);
        return $payload . '.' . $signature;
    }

    /**
     * Verify a token. Returns the email if valid and unexpired, else null.
     */
    public function verify(string $token): ?string
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $signature] = $parts;

        $expected = hash_hmac('sha256', $payload, $this->secret);
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $data = $this->decodePayload($payload);
        if ($data === null || empty($data['email']) || empty($data['exp'])) {
            return null;
        }

        if ((int)$data['exp'] < time()) {
            return null;
        }

        return strtolower(trim($data['email']));
    }

    /**
     * Atomically mark a token as used. Returns true if this call won the race
     * (i.e. the token was NOT previously redeemed), false if it was already used.
     *
     * The PRIMARY KEY on magic_link_uses.token_hash makes the check-and-set
     * atomic: concurrent redeems of the same token result in exactly one winner.
     */
    public function consume(\PDO $pdo, string $token): bool
    {
        $email = $this->verify($token);
        if ($email === null) {
            return false;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO magic_link_uses (token_hash, email) VALUES (?, ?) ON CONFLICT (token_hash) DO NOTHING"
        );
        $stmt->execute([self::tokenHash($token), $email]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Stable SHA-256 identifier for a raw token (used as the table key).
     */
    public static function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function encodePayload(array $data): string
    {
        return rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
    }

    private function decodePayload(string $payload): ?array
    {
        $json = base64_decode(strtr($payload, '-_', '+/'));
        if ($json === false) {
            return null;
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    private static function appKey(): string
    {
        return (string)Env::get('APP_KEY', '');
    }
}