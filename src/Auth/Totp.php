<?php

namespace App\Auth;

/**
 * RFC 6238 Time-based One-Time Password (TOTP) with no external dependencies.
 * Defaults match the widely-used Google Authenticator profile: HMAC-SHA1,
 * 6 digits, 30 second period, base32 secrets.
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PERIOD = 30;
    private const DIGITS = 6;

    /**
     * Generate a new base32 secret (RFC 4226 recommends 160-bit secrets).
     */
    public static function generateSecret(int $byteLength = 20): string
    {
        return self::base32Encode(random_bytes($byteLength));
    }

    /**
     * Generate the current 6-digit code for a secret.
     */
    public static function code(string $secret, ?int $at = null): string
    {
        $at = $at ?? time();
        $counter = intdiv($at, self::PERIOD);

        // 8-byte big-endian counter
        $binary = "\0\0\0\0" . pack('N', $counter);

        $hash = hash_hmac('sha1', $binary, self::base32Decode($secret), true);

        // Dynamic truncation per RFC 4226
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = (
            (ord($hash[$offset]) & 0x7F) << 24
        ) | (
            (ord($hash[$offset + 1]) & 0xFF) << 16
        ) | (
            (ord($hash[$offset + 2]) & 0xFF) << 8
        ) | (
            ord($hash[$offset + 3]) & 0xFF
        );

        return str_pad((string)($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a submitted code, allowing a small clock-drift window
     * (± $window periods).
     */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{' . self::DIGITS . '}$/', $code)) {
            return false;
        }

        $now = time();
        for ($i = -$window; $i <= $window; $i++) {
            $candidate = self::code($secret, $now + ($i * self::PERIOD));
            if (hash_equals($candidate, $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * otpauth:// provisioning URI for QR codes / manual entry.
     */
    public static function provisioningUri(string $secret, string $accountName, string $issuer = 'WAM Blog'): string
    {
        $label = $issuer . ':' . $accountName;
        return 'otpauth://totp/' . rawurlencode($label)
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    private static function base32Encode(string $data): string
    {
        $bits = '';
        $length = strlen($data);
        for ($i = 0; $i < $length; $i++) {
            $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }

        $result = '';
        for ($i = 0; $i < strlen($bits); $i += 5) {
            $chunk = substr($bits, $i, 5);
            $result .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }
        return $result;
    }

    private static function base32Decode(string $base32): string
    {
        $base32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $base32));
        if ($base32 === '') {
            return '';
        }

        $bits = '';
        $length = strlen($base32);
        for ($i = 0; $i < $length; $i++) {
            $value = strpos(self::ALPHABET, $base32[$i]);
            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $result = '';
        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $result .= chr(bindec(substr($bits, $i, 8)));
        }
        return $result;
    }
}