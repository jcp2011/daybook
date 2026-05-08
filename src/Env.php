<?php

declare(strict_types=1);

namespace App;

/**
 * Minimal .env file loader.
 *
 * Parses KEY=VALUE pairs into $_ENV. Lines starting with '#' and blank lines
 * are ignored. Surrounding single or double quotes are stripped from values.
 */
class Env
{
    /**
     * Loads a .env file and populates $_ENV.
     *
     * Silently does nothing when the file does not exist.
     *
     * @param string $path Filesystem path to the .env file.
     */
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key   = trim($parts[0]);
            $value = trim($parts[1]);

            if (
                strlen($value) >= 2
                && (($value[0] === '"' && $value[-1] === '"')
                    || ($value[0] === "'" && $value[-1] === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$key] = $value;
        }
    }

    /**
     * Returns the value of an environment variable, or null when absent.
     *
     * @param string $key Variable name.
     * @return string|null The value, or null if not set.
     */
    public static function get(string $key): string|null
    {
        $value = $_ENV[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Returns the value of a required environment variable.
     *
     * @param  string $key Variable name.
     * @return string The value.
     * @throws \RuntimeException If the variable is not set.
     */
    public static function require(string $key): string
    {
        $value = $_ENV[$key] ?? null;

        if (!is_string($value)) {
            throw new \RuntimeException(
                sprintf('Missing required environment variable: %s', $key)
            );
        }

        return $value;
    }
}
