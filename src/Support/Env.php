<?php

namespace App\Support;

/**
 * Reads configuration values from real environment variables ($_SERVER,
 * $_ENV, getenv) and, as a fallback, from the project's .env file.
 *
 * This is the single source of truth for config loading — Connection,
 * bin/migrate.php, MagicLink, Mailer and Invitation all used to re-parse
 * .env with their own copy of this logic.
 */
class Env
{
    private static ?array $file = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        foreach (['_SERVER', '_ENV'] as $scope) {
            $values = $scope === '_SERVER' ? $_SERVER : $_ENV;
            if (isset($values[$key]) && $values[$key] !== '') {
                return (string)$values[$key];
            }
        }

        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        $file = self::loadFile();
        if (array_key_exists($key, $file)) {
            return $file[$key];
        }

        return $default;
    }

    private static function loadFile(): array
    {
        if (self::$file !== null) {
            return self::$file;
        }

        $parsed = [];
        $path = dirname(__DIR__, 2) . '/.env';
        if (file_exists($path)) {
            foreach (file($path) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $parsed[trim($parts[0])] = trim(trim($parts[1]), '"');
                }
            }
        }

        return self::$file = $parsed;
    }
}