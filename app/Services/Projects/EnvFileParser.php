<?php

declare(strict_types=1);

namespace App\Services\Projects;

/**
 * Minimal .env reader for a target project.
 *
 * We only need a handful of DB_* keys, so this is a small, dependency-free
 * line parser (quotes stripped, comments/blank lines ignored) rather than a
 * full Dotenv bootstrap of another application.
 */
final class EnvFileParser
{
    /**
     * @return array<string, string>
     */
    public function parse(string $envPath): array
    {
        if (! is_file($envPath) || ! is_readable($envPath)) {
            return [];
        }

        $contents = file_get_contents($envPath);

        if ($contents === false) {
            return [];
        }

        $values = [];

        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            $values[$key] = $this->normaliseValue($value);
        }

        return $values;
    }

    /**
     * Strip surrounding quotes, or an unquoted trailing "# comment", and trailing space.
     */
    private function normaliseValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $first = $value[0];

        // Quoted value: take everything up to the matching closing quote; ignore the rest.
        if ($first === '"' || $first === "'") {
            $closing = strpos($value, $first, 1);

            return $closing === false ? substr($value, 1) : substr($value, 1, $closing - 1);
        }

        // Unquoted: a value that starts with '#' is an (empty) commented-out value.
        if ($first === '#') {
            return '';
        }

        // Unquoted: drop an inline comment introduced by whitespace + '#'.
        $value = (string) preg_replace('/\s+#.*$/', '', $value);

        return rtrim($value);
    }
}
