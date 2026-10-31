<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    public static function load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException(sprintf('Fichier d\'environnement introuvable ou illisible : %s', $path));
        }

        $values = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $index => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^([A-Z][A-Z0-9_]*)\s*=\s*(.*)$/', $line, $match) !== 1) {
                throw new RuntimeException(sprintf('Ligne %d invalide dans %s', $index + 1, basename($path)));
            }

            $values[$match[1]] = self::parseValue($match[2], $index + 1, basename($path));
        }

        self::$values = $values;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        // Une variable définie par le serveur ou le conteneur prime sur le fichier.
        $value = getenv($key);
        if ($value === false) {
            $value = self::$values[$key] ?? null;
        }

        return $value === null || $value === '' ? $default : $value;
    }

    public static function required(string $key): string
    {
        $value = self::get($key);
        if ($value === null) {
            throw new RuntimeException(sprintf('Variable d\'environnement manquante : %s', $key));
        }

        return $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
    }

    public static function int(string $key, int $default): int
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new RuntimeException(sprintf('La variable %s doit être un entier.', $key));
        }

        return (int) $value;
    }

    private static function parseValue(string $raw, int $lineNumber, string $file): string
    {
        if ($raw === '') {
            return '';
        }

        $quote = $raw[0];
        if ($quote === '"' || $quote === "'") {
            $end = strpos($raw, $quote, 1);
            if ($end === false) {
                throw new RuntimeException(sprintf('Guillemet non fermé ligne %d dans %s', $lineNumber, $file));
            }

            return substr($raw, 1, $end - 1);
        }

        // Un « # » n'ouvre un commentaire que s'il suit un espace : il reste utilisable dans un mot de passe.
        return trim((string) preg_replace('/\s+#.*$/', '', $raw));
    }
}
